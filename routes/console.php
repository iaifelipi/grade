<?php

use App\Services\LeadsVault\OperationalCatalogSyncService;
use App\Services\Security\CloudflareSecurityService;
use App\Services\Security\SecurityAccessEventWriter;
use App\Services\Security\SecurityRiskEvaluatorService;
use App\Services\Tenants\TenantDeletionService;
use App\Services\Users\UsernameService;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('security:access:ingest {--minutes=} {--limit=}', function () {
    $minutes = (int) ($this->option('minutes') ?? config('security_monitoring.risk.window_minutes', 15));
    $minutes = max(1, $minutes);
    $limit = (int) ($this->option('limit') ?? config('security_monitoring.cloudflare.ingest_limit', 250));
    $limit = max(1, $limit);

    /** @var CloudflareSecurityService $cf */
    $cf = app(CloudflareSecurityService::class);
    if (!$cf->isEnabled()) {
        $this->error('Cloudflare nao configurado (CLOUDFLARE_API_TOKEN + CLOUDFLARE_ZONE_ID).');
        return 2;
    }

    /** @var SecurityAccessEventWriter $writer */
    $writer = app(SecurityAccessEventWriter::class);
    $this->info('Grade • Security access ingest (Cloudflare)');
    $this->line("Window: {$minutes} min");
    $this->line("Limit: {$limit}");

    $result = $cf->ingestFirewallEvents($writer, $minutes, $limit);

    $ok = (bool) ($result['ok'] ?? false);
    $created = (int) ($result['created'] ?? 0);
    $message = (string) ($result['message'] ?? '');

    $this->newLine();
    $this->line('ok=' . ($ok ? 'true' : 'false') . ' created=' . $created);
    if ($message !== '') {
        $this->line($message);
    }

    return $ok ? 0 : 1;
})->purpose('Ingere eventos de firewall do Cloudflare para security_access_events');

Artisan::command('security:access:evaluate {--minutes=}', function () {
    $minutes = (int) ($this->option('minutes') ?? config('security_monitoring.risk.window_minutes', 15));
    $minutes = max(1, $minutes);

    /** @var SecurityRiskEvaluatorService $svc */
    $svc = app(SecurityRiskEvaluatorService::class);
    $this->info('Grade • Security access evaluate');
    $this->line("Window: {$minutes} min");

    $result = $svc->evaluate($minutes);

    $ok = (bool) ($result['ok'] ?? false);
    $upserted = (int) ($result['incidents_upserted'] ?? 0);
    $message = (string) ($result['message'] ?? '');

    $this->newLine();
    $this->line('ok=' . ($ok ? 'true' : 'false') . ' incidents_upserted=' . $upserted);
    if ($message !== '') {
        $this->line($message);
    }

    return $ok ? 0 : 1;
})->purpose('Avalia riscos e upserta incidentes em security_access_incidents');

Artisan::command('security:access:run {--ingest} {--evaluate} {--minutes=} {--limit=}', function () {
    $doIngest = (bool) $this->option('ingest');
    $doEvaluate = (bool) $this->option('evaluate');
    if (!$doIngest && !$doEvaluate) {
        $doIngest = true;
        $doEvaluate = true;
    }

    $minutes = (int) ($this->option('minutes') ?? config('security_monitoring.risk.window_minutes', 15));
    $minutes = max(1, $minutes);
    $limit = (int) ($this->option('limit') ?? config('security_monitoring.cloudflare.ingest_limit', 250));
    $limit = max(1, $limit);

    $this->info('Grade • Security access run');
    $this->line('Mode: sync (cron-friendly)');
    $this->line("Window: {$minutes} min");
    $this->line("Limit: {$limit}");

    $exit = 0;

    if ($doIngest) {
        $code = Artisan::call('security:access:ingest', [
            '--minutes' => $minutes,
            '--limit' => $limit,
        ]);
        $exit = max($exit, (int) $code);
        $this->output->write(Artisan::output());
    }

    if ($doEvaluate) {
        $code = Artisan::call('security:access:evaluate', [
            '--minutes' => $minutes,
        ]);
        $exit = max($exit, (int) $code);
        $this->output->write(Artisan::output());
    }

    return $exit;
})->purpose('Executa ingest/evaluate em modo sync (ideal para cron) sem depender de workers');

Artisan::command('security:access:prune {--events-days=} {--actions-days=} {--incidents-days=} {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');

    $eventsDays = (int) ($this->option('events-days') ?? config('security_monitoring.retention.events_days', 90));
    $actionsDays = (int) ($this->option('actions-days') ?? config('security_monitoring.retention.actions_days', 180));
    $incidentsDays = (int) ($this->option('incidents-days') ?? config('security_monitoring.retention.incidents_days', 365));

    $eventsDays = max(1, $eventsDays);
    $actionsDays = max(1, $actionsDays);
    $incidentsDays = max(1, $incidentsDays);

    $this->info('Grade • Security access prune');
    $this->line("Events: {$eventsDays} dias");
    $this->line("Actions: {$actionsDays} dias");
    $this->line("Incidents: {$incidentsDays} dias");
    $this->line($dryRun ? 'Modo: DRY-RUN (sem alterações)' : 'Modo: EXECUÇÃO');

    $summary = [];

    $prune = function (string $table, string $column, int $days) use (&$summary, $dryRun) {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            $summary[] = ['table' => $table, 'found' => 0, 'deleted' => 0, 'note' => 'skip'];
            return;
        }

        $cutoff = now()->subDays($days);
        $query = DB::table($table)->where($column, '<', $cutoff);
        $found = (int) (clone $query)->count();
        $deleted = 0;
        if (!$dryRun && $found > 0) {
            $deleted = (int) $query->delete();
        }

        $summary[] = ['table' => $table, 'found' => $found, 'deleted' => $deleted, 'note' => 'ok'];
    };

    $prune('security_access_events', 'occurred_at', $eventsDays);
    $prune('security_access_actions', 'created_at', $actionsDays);
    $prune('security_access_incidents', 'last_seen_at', $incidentsDays);

    $this->newLine();
    $this->table(
        ['Tabela', 'Encontrados', $dryRun ? 'Simulado' : 'Removidos', 'Nota'],
        array_map(fn ($r) => [$r['table'], $r['found'], $r['deleted'], $r['note']], $summary)
    );

    return 0;
})->purpose('Aplica retencao nas tabelas security_access_* (cron-friendly)');

Artisan::command('leads:sweep-orphans {--tenant=} {--dry-run}', function () {
    $tenant = trim((string) ($this->option('tenant') ?? ''));
    $dryRun = (bool) $this->option('dry-run');

    $this->info('Grade • Varredura órfã de leads');
    $this->line($tenant !== '' ? "Tenant: {$tenant}" : 'Tenant: todos');
    $this->line($dryRun ? 'Modo: DRY-RUN (sem alterações)' : 'Modo: EXECUÇÃO');

    $summary = [];

    $deleteWithLeadSource = function (string $table, bool $nullable = false) use (&$summary, $tenant, $dryRun) {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'lead_source_id')) {
            return;
        }

        $query = DB::table($table);
        if ($tenant !== '' && Schema::hasColumn($table, 'tenant_uuid')) {
            $query->where('tenant_uuid', $tenant);
        }
        if ($nullable) {
            $query->whereNotNull('lead_source_id');
        }

        $query->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('lead_sources')
                ->whereColumn('lead_sources.id', 'lead_source_id');
        });

        $count = (clone $query)->count();
        $deleted = 0;
        if (!$dryRun && $count > 0) {
            $deleted = $query->delete();
        }

        $summary[] = [
            'table' => $table,
            'found' => (int) $count,
            'deleted' => (int) $deleted,
        ];
    };

    $deleteWithLeadSource('lead_raw');
    $deleteWithLeadSource('leads_normalized');
    $deleteWithLeadSource('lead_overrides');
    $deleteWithLeadSource('lead_source_semantics');
    $deleteWithLeadSource('lead_column_settings', true);
    $deleteWithLeadSource('explore_view_preferences', true);

    if (Schema::hasTable('semantic_locations')) {
        $query = DB::table('semantic_locations')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('lead_source_semantics')
                    ->whereColumn('lead_source_semantics.id', 'semantic_locations.lead_source_semantic_id');
            });

        if ($tenant !== '' && Schema::hasTable('lead_source_semantics') && Schema::hasColumn('lead_source_semantics', 'tenant_uuid')) {
            $query->whereNotExists(function ($q) use ($tenant) {
                $q->select(DB::raw(1))
                    ->from('lead_source_semantics')
                    ->whereColumn('lead_source_semantics.id', 'semantic_locations.lead_source_semantic_id')
                    ->where('lead_source_semantics.tenant_uuid', '!=', $tenant);
            });
        }

        $count = (clone $query)->count();
        $deleted = 0;
        if (!$dryRun && $count > 0) {
            $deleted = $query->delete();
        }
        $summary[] = [
            'table' => 'semantic_locations',
            'found' => (int) $count,
            'deleted' => (int) $deleted,
        ];
    }

    if (Schema::hasTable('lead_sources') && Schema::hasColumn('lead_sources', 'parent_source_id')) {
        $query = DB::table('lead_sources')
            ->whereNotNull('parent_source_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('lead_sources as parent')
                    ->whereColumn('parent.id', 'lead_sources.parent_source_id');
            });

        if ($tenant !== '' && Schema::hasColumn('lead_sources', 'tenant_uuid')) {
            $query->where('tenant_uuid', $tenant);
        }

        $count = (clone $query)->count();
        $updated = 0;
        if (!$dryRun && $count > 0) {
            $updated = $query->update(['parent_source_id' => null]);
        }
        $summary[] = [
            'table' => 'lead_sources(parent_source_id)',
            'found' => (int) $count,
            'deleted' => (int) $updated,
        ];
    }

    $this->newLine();
    $this->table(['Tabela', 'Orfãos', $dryRun ? 'Simulado' : 'Removidos'], array_map(
        fn ($row) => [$row['table'], $row['found'], $row['deleted']],
        $summary
    ));

    $totalFound = array_sum(array_map(fn ($row) => (int) $row['found'], $summary));
    $totalDeleted = array_sum(array_map(fn ($row) => (int) $row['deleted'], $summary));
    $this->newLine();
    $this->info("Total órfãos: {$totalFound}");
    $this->info(($dryRun ? 'Total simulado: ' : 'Total removido: ') . $totalDeleted);
})->purpose('Limpa registros órfãos de leads sem lead_source associado (opcional)');

Artisan::command('guest:prune-audit {--sessions-days=30} {--events-days=90} {--dry-run}', function () {
    $sessionsDays = max(1, (int) ($this->option('sessions-days') ?? 30));
    $eventsDays = max(1, (int) ($this->option('events-days') ?? 90));
    $dryRun = (bool) $this->option('dry-run');

    $this->info('Grade • Retenção guest');
    $this->line("Sessões: {$sessionsDays} dias");
    $this->line("Eventos: {$eventsDays} dias");
    $this->line($dryRun ? 'Modo: DRY-RUN (sem alterações)' : 'Modo: EXECUÇÃO');

    if (!Schema::hasTable('guest_sessions') || !Schema::hasTable('guest_file_events')) {
        $this->warn('Tabelas guest_sessions/guest_file_events não encontradas.');
        return;
    }

    $sessionsCutoff = now()->subDays($sessionsDays);
    $eventsCutoff = now()->subDays($eventsDays);

    $eventsQuery = DB::table('guest_file_events')
        ->where('created_at', '<', $eventsCutoff);
    if (Schema::hasColumn('guest_file_events', 'actor_type')) {
        $eventsQuery->where('actor_type', 'guest');
    }
    $eventsFound = (int) (clone $eventsQuery)->count();
    $eventsDeleted = 0;
    if (!$dryRun && $eventsFound > 0) {
        $eventsDeleted = (int) $eventsQuery->delete();
    }

    $sessionsQuery = DB::table('guest_sessions')
        ->where(function ($q) use ($sessionsCutoff) {
            $q->whereNotNull('last_seen_at')
                ->where('last_seen_at', '<', $sessionsCutoff)
                ->orWhere(function ($qq) use ($sessionsCutoff) {
                    $qq->whereNull('last_seen_at')
                        ->where('created_at', '<', $sessionsCutoff);
                });
        })
        ->whereNotExists(function ($q) use ($sessionsCutoff) {
            $q->select(DB::raw(1))
                ->from('guest_file_events')
                ->whereColumn('guest_file_events.guest_uuid', 'guest_sessions.guest_uuid')
                ->where('guest_file_events.created_at', '>=', $sessionsCutoff);
        });
    if (Schema::hasColumn('guest_sessions', 'actor_type')) {
        $sessionsQuery->where('actor_type', 'guest');
    }

    $sessionsFound = (int) (clone $sessionsQuery)->count();
    $sessionsDeleted = 0;
    if (!$dryRun && $sessionsFound > 0) {
        $sessionsDeleted = (int) $sessionsQuery->delete();
    }

    $this->newLine();
    $this->table(
        ['Escopo', 'Encontrados', $dryRun ? 'Simulado' : 'Removidos'],
        [
            ['guest_file_events', $eventsFound, $eventsDeleted],
            ['guest_sessions', $sessionsFound, $sessionsDeleted],
        ]
    );

    $this->newLine();
    $this->info("Eventos > {$eventsDays}d: {$eventsFound}");
    $this->info("Sessões antigas sem evento recente: {$sessionsFound}");
})->purpose('Aplica retenção de auditoria guest em sessões e eventos');

Artisan::command('guest:backfill-storage {--tenant=} {--dry-run}', function () {
    $tenant = trim((string) ($this->option('tenant') ?? ''));
    $dryRun = (bool) $this->option('dry-run');

    $this->info('Grade • Backfill de storage guest');
    $this->line($tenant !== '' ? "Tenant guest alvo: {$tenant}" : 'Tenant guest alvo: automático (guest_sessions)');
    $this->line($dryRun ? 'Modo: DRY-RUN (sem alterações)' : 'Modo: EXECUÇÃO');

    if (!Schema::hasTable('lead_sources')) {
        $this->warn('Tabela lead_sources não encontrada.');
        return;
    }

    $guestUuids = [];
    if ($tenant !== '') {
        $guestUuids[$tenant] = true;
    } elseif (Schema::hasTable('guest_sessions')) {
        DB::table('guest_sessions')
            ->select(['id', 'guest_uuid'])
            ->whereNotNull('guest_uuid')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$guestUuids) {
                foreach ($rows as $row) {
                    $uuid = trim((string) ($row->guest_uuid ?? ''));
                    if ($uuid !== '') {
                        $guestUuids[$uuid] = true;
                    }
                }
            });
    }

    if (Schema::hasTable('lead_sources')) {
        DB::table('lead_sources')
            ->select(['id', 'tenant_uuid', 'file_path'])
            ->where(function ($q) {
                $q->where('tenant_uuid', 'like', 'guest_%')
                    ->orWhere('file_path', 'like', 'guest_%/%');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$guestUuids) {
                foreach ($rows as $row) {
                    $tenantUuid = trim((string) ($row->tenant_uuid ?? ''));
                    if ($tenantUuid !== '') {
                        if (str_starts_with($tenantUuid, 'guest_')) {
                            $tenantUuid = substr($tenantUuid, 6);
                        }
                        if (preg_match('#^[a-f0-9-]{36}$#i', $tenantUuid) === 1) {
                            $guestUuids[strtolower($tenantUuid)] = true;
                        }
                    }

                    $filePath = trim((string) ($row->file_path ?? ''));
                    if (preg_match('#^guest_([a-f0-9-]{36})/#i', $filePath, $m) === 1) {
                        $guestUuids[strtolower($m[1])] = true;
                    }
                }
            });
    }

    if (!$guestUuids) {
        $this->warn('Nenhum guest_uuid encontrado. Use --tenant=<guest_uuid> para rodar manualmente.');
        return;
    }

    $isGuestTenant = function (string $tenantUuid) use ($guestUuids): bool {
        $tenantUuid = trim($tenantUuid);
        if ($tenantUuid === '') {
            return false;
        }
        if (isset($guestUuids[$tenantUuid])) {
            return true;
        }
        if (str_starts_with($tenantUuid, 'guest_')) {
            $raw = substr($tenantUuid, 6);
            return isset($guestUuids[$raw]);
        }
        return false;
    };

    $resolveTargetPath = function (string $tenantUuid, string $filePath) use ($isGuestTenant): array {
        $tenantUuid = trim($tenantUuid);
        $normalized = ltrim(str_replace('\\', '/', trim($filePath)), '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;
        if ($normalized === '' || str_starts_with($normalized, 'tenants_guest/')) {
            return [null, null];
        }

        if (preg_match('#^guest_([a-f0-9-]{36})/(.+)$#i', $normalized, $m) === 1) {
            return ['tenants_guest/' . strtolower($m[1]) . '/' . $m[2], 'guest_prefixed_path'];
        }

        if ($tenantUuid !== '' && $isGuestTenant($tenantUuid) && str_starts_with($normalized, $tenantUuid . '/')) {
            return ['tenants_guest/' . $tenantUuid . '/' . substr($normalized, strlen($tenantUuid) + 1), 'tenant_root_path'];
        }

        if ($tenantUuid !== '' && $isGuestTenant($tenantUuid) && str_starts_with($normalized, 'guest_' . $tenantUuid . '/')) {
            return ['tenants_guest/' . $tenantUuid . '/' . substr($normalized, strlen('guest_' . $tenantUuid) + 1), 'tenant_guest_prefixed_path'];
        }

        return [null, null];
    };

    $storageRoot = storage_path('app/private/tenants');
    $summary = [
        'candidates' => 0,
        'updated' => 0,
        'moved' => 0,
        'already_new_path' => 0,
        'old_missing_new_exists' => 0,
        'missing_both' => 0,
        'move_errors' => 0,
        'collisions' => 0,
    ];
    $reasonCounters = [];

    DB::table('lead_sources')
        ->select(['id', 'tenant_uuid', 'file_path'])
        ->whereNotNull('file_path')
        ->where('file_path', '!=', '')
        ->orderBy('id')
        ->chunkById(500, function ($rows) use (
            $tenant,
            $dryRun,
            $resolveTargetPath,
            $storageRoot,
            &$summary,
            &$reasonCounters
        ) {
            foreach ($rows as $row) {
                $tenantUuid = trim((string) ($row->tenant_uuid ?? ''));
                if ($tenant !== '' && $tenantUuid !== $tenant && $tenantUuid !== 'guest_' . $tenant) {
                    continue;
                }

                $oldPath = ltrim(str_replace('\\', '/', (string) ($row->file_path ?? '')), '/');
                $oldPath = preg_replace('#/+#', '/', $oldPath) ?? $oldPath;
                if ($oldPath === '') {
                    continue;
                }

                [$newPath, $reason] = $resolveTargetPath($tenantUuid, $oldPath);
                if ($newPath === null) {
                    if (str_starts_with($oldPath, 'tenants_guest/')) {
                        $summary['already_new_path']++;
                    }
                    continue;
                }

                $summary['candidates']++;
                if ($reason !== null) {
                    $reasonCounters[$reason] = (int) ($reasonCounters[$reason] ?? 0) + 1;
                }

                $oldAbs = $storageRoot . '/' . $oldPath;
                $newAbs = $storageRoot . '/' . $newPath;
                $oldExists = is_file($oldAbs);
                $newExists = is_file($newAbs);

                if (!$oldExists && !$newExists) {
                    $summary['missing_both']++;
                    continue;
                }

                $canUpdateDb = false;
                if ($oldExists && !$newExists) {
                    if (!$dryRun) {
                        $newDir = dirname($newAbs);
                        if (!is_dir($newDir) && !@mkdir($newDir, 0775, true) && !is_dir($newDir)) {
                            $summary['move_errors']++;
                            continue;
                        }
                        if (!@rename($oldAbs, $newAbs)) {
                            $summary['move_errors']++;
                            continue;
                        }
                    }
                    $summary['moved']++;
                    $canUpdateDb = true;
                } elseif (!$oldExists && $newExists) {
                    $summary['old_missing_new_exists']++;
                    $canUpdateDb = true;
                } elseif ($oldExists && $newExists) {
                    $summary['collisions']++;
                    $canUpdateDb = true;
                }

                if ($canUpdateDb) {
                    if (!$dryRun) {
                        DB::table('lead_sources')
                            ->where('id', (int) $row->id)
                            ->update(['file_path' => $newPath, 'updated_at' => now()]);
                    }
                    $summary['updated']++;
                }
            }
        });

    $this->newLine();
    $this->table(
        ['Métrica', 'Total'],
        [
            ['Candidatos', $summary['candidates']],
            ['Paths atualizados (DB)', $summary['updated']],
            ['Arquivos movidos', $summary['moved']],
            ['Já no novo path', $summary['already_new_path']],
            ['Antigo ausente / novo existe', $summary['old_missing_new_exists']],
            ['Ausente em ambos', $summary['missing_both']],
            ['Colisões (antigo e novo)', $summary['collisions']],
            ['Erros de move', $summary['move_errors']],
        ]
    );

    if ($reasonCounters) {
        $this->newLine();
        $rows = [];
        foreach ($reasonCounters as $reason => $count) {
            $rows[] = [$reason, $count];
        }
        $this->table(['Regra de conversão', 'Ocorrências'], $rows);
    }
})->purpose('Move paths legados de guest para tenants_guest/{guest_uuid} e atualiza lead_sources.file_path');

Artisan::command('tenants:cleanup-orphans {--force} {--confirm=} {--limit=}', function () {
    $force = (bool) $this->option('force');
    $confirm = trim((string) ($this->option('confirm') ?? ''));
    $limit = (int) ($this->option('limit') ?? 200);
    $limit = max(1, $limit);

    $dryRun = !$force;

    $this->info('Grade • Tenants cleanup orphans');
    $this->line($dryRun ? 'Modo: DRY-RUN (sem alterações)' : 'Modo: EXECUÇÃO');
    $this->line("Limit: {$limit}");

    if (!$dryRun && $confirm !== 'DELETE_ORPHAN_TENANTS') {
        $this->error('Para executar, use: --force --confirm=DELETE_ORPHAN_TENANTS');
        return 2;
    }

    if (!Schema::hasTable('tenants') || !Schema::hasTable('users')) {
        $this->error('Tabelas tenants/users não encontradas.');
        return 2;
    }

    $orphans = DB::table('tenants')
        ->leftJoin('users', 'users.tenant_uuid', '=', 'tenants.uuid')
        ->select([
            'tenants.uuid',
            'tenants.slug',
            'tenants.name',
            'tenants.plan',
            DB::raw('COUNT(users.id) as users_count'),
        ])
        ->groupBy(['tenants.uuid', 'tenants.slug', 'tenants.name', 'tenants.plan'])
        ->havingRaw('COUNT(users.id) = 0')
        ->orderBy('tenants.name')
        ->limit($limit)
        ->get();

    if ($orphans->isEmpty()) {
        $this->line('Nenhum tenant órfão encontrado.');
        return 0;
    }

    $this->newLine();
    $this->table(
        ['UUID', 'Slug', 'Nome', 'Plano', 'Users'],
        $orphans
            ->map(fn ($t) => [
                (string) $t->uuid,
                (string) ($t->slug ?? ''),
                (string) ($t->name ?? ''),
                (string) ($t->plan ?? ''),
                (int) ($t->users_count ?? 0),
            ])
            ->all()
    );

    if ($dryRun) {
        $this->newLine();
        $this->line('DRY-RUN: nada foi removido.');
        $this->line('Para executar: php artisan tenants:cleanup-orphans --force --confirm=DELETE_ORPHAN_TENANTS');
        return 0;
    }

    /** @var TenantDeletionService $svc */
    $svc = app(TenantDeletionService::class);

    $deleted = 0;
    foreach ($orphans as $t) {
        $uuid = trim((string) $t->uuid);
        if ($uuid === '') {
            continue;
        }
        $result = $svc->deleteTenantAndAllData($uuid);
        $deleted++;
        $this->line('deleted=' . $deleted . ' uuid=' . $uuid . ' tenant_deleted=' . (((bool) ($result['tenant_deleted'] ?? false)) ? 'true' : 'false'));
    }

    $this->newLine();
    $this->info("Concluído. Tenants órfãos removidos: {$deleted}");
    return 0;
})->purpose('Remove tenants sem usuários (dry-run por padrão; exige confirmação forte para executar)');

Artisan::command('vault:sync-operational {--tenant=} {--source_id=} {--lead_id=*} {--limit=0}', function () {
    $tenantUuid = trim((string) ($this->option('tenant') ?? ''));
    $sourceId = (int) ($this->option('source_id') ?? 0);
    $leadIds = collect((array) ($this->option('lead_id') ?? []))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values()
        ->all();
    $limit = max(0, (int) ($this->option('limit') ?? 0));

    /** @var OperationalCatalogSyncService $sync */
    $sync = app(OperationalCatalogSyncService::class);

    $this->info('Grade • Sync Cadastro Operacional');
    $this->line($tenantUuid !== '' ? "Tenant: {$tenantUuid}" : 'Tenant: todos');
    $this->line($sourceId > 0 ? "Source ID: {$sourceId}" : 'Source ID: n/a');
    $this->line($limit > 0 ? "Limit: {$limit}" : 'Limit: sem limite');

    $startedAt = microtime(true);

    if ($leadIds) {
        $this->line('Modo: IDs específicos');
        $synced = $sync->syncLeadIds($leadIds, $tenantUuid !== '' ? $tenantUuid : null);
    } elseif ($sourceId > 0) {
        $this->line('Modo: por source');
        $synced = $sync->syncBySourceId($sourceId, $tenantUuid !== '' ? $tenantUuid : null, $limit > 0 ? $limit : null);
    } else {
        $this->line('Modo: tenant/global');
        $synced = $sync->syncTenant($tenantUuid !== '' ? $tenantUuid : null, $limit > 0 ? $limit : null);
    }

    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
    $this->newLine();
    $this->info("Registros sincronizados: {$synced}");
    $this->info("Tempo: {$elapsedMs} ms");
})->purpose('Sincroniza leads_normalized no novo Cadastro Operacional (operational_*)');

Artisan::command('users:backfill-usernames {--tenant=} {--dry-run}', function () {
    $tenant = trim((string) ($this->option('tenant') ?? ''));
    $dryRun = (bool) $this->option('dry-run');

    if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'username')) {
        $this->error('Tabela/coluna users.username não encontrada. Rode as migrations primeiro.');
        return 2;
    }

    $q = User::query()
        ->where(function ($qq) {
            $qq->whereNull('username')->orWhere('username', '');
        })
        ->whereNotNull('email')
        ->orderBy('id');

    if ($tenant !== '') {
        $q->where('tenant_uuid', $tenant);
    }

    $total = (clone $q)->count();
    $this->info('Grade • Backfill usernames');
    $this->line($tenant !== '' ? "Tenant: {$tenant}" : 'Tenant: todos');
    $this->line($dryRun ? 'Modo: DRY-RUN (sem alterações)' : 'Modo: EXECUÇÃO');
    $this->line("Pendentes: {$total}");

    /** @var UsernameService $svc */
    $svc = app(UsernameService::class);
    $updated = 0;

    $q->chunkById(200, function ($users) use (&$updated, $svc, $dryRun) {
        foreach ($users as $u) {
            $email = (string) ($u->email ?? '');
            if ($email === '') {
                continue;
            }
            $username = $svc->generateUniqueFromEmail($email, (int) $u->id);
            if ($dryRun) {
                $this->line("id={$u->id} email={$email} -> username={$username}");
                continue;
            }
            $u->username = $username;
            $u->save();
            $updated++;
        }
    });

    $this->newLine();
    $this->line('Atualizados: ' . ($dryRun ? '0 (dry-run)' : (string) $updated));
    return 0;
})->purpose('Preenche users.username (único global) para usuários existentes, a partir do prefixo do e-mail');

Artisan::command('monetization:compat:cleanup {--dry-run} {--force}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $force = (bool) $this->option('force');

    $legacyViews = [
        'payment_gateways',
        'currencies',
        'tax_rates',
        'price_plans',
        'promo_codes',
        'orders',
    ];

    $this->info('Grade • Monetization compat cleanup');
    $this->line($dryRun ? 'Modo: DRY-RUN (sem alterações)' : 'Modo: EXECUÇÃO');

    $viewRows = [];
    foreach ($legacyViews as $name) {
        $type = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $name)
            ->value('TABLE_TYPE');

        $viewRows[] = [
            'name' => $name,
            'type' => (string) ($type ?? 'missing'),
        ];
    }

    $this->table(
        ['Objeto legado', 'Tipo atual'],
        array_map(fn ($r) => [$r['name'], $r['type']], $viewRows)
    );

    $toDrop = collect($viewRows)->filter(fn ($r) => $r['type'] === 'VIEW')->pluck('name')->values()->all();
    if (!$toDrop) {
        $this->newLine();
        $this->info('Nenhuma view legada para remover.');
        return 0;
    }

    if (!$dryRun && !$force) {
        $this->newLine();
        $this->error('Use --force para remover as views legadas.');
        return 1;
    }

    if ($dryRun) {
        $this->newLine();
        $this->line('DRY-RUN: views que seriam removidas: ' . implode(', ', $toDrop));
        return 0;
    }

    foreach ($toDrop as $view) {
        DB::statement('DROP VIEW IF EXISTS `' . str_replace('`', '``', $view) . '`');
    }

    $this->newLine();
    $this->info('Compatibilidade legada removida com sucesso.');
    return 0;
})->purpose('Etapa 3: remove views legadas payment_gateways/currencies/tax_rates/price_plans/promo_codes/orders');
