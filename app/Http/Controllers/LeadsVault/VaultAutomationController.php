<?php

namespace App\Http\Controllers\LeadsVault;

use App\Http\Controllers\Controller;
use App\Jobs\ImportLeadSourceJob;
use App\Models\AutomationFlow;
use App\Models\AutomationFlowStep;
use App\Models\AutomationRun;
use App\Models\LeadRaw;
use App\Models\LeadSource;
use App\Models\LeadSourceSemantic;
use App\Services\LeadsVault\AutomationExecutionService;
use App\Services\LeadsVault\OperationalEntityTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VaultAutomationController extends Controller
{
    public function __construct(private readonly AutomationExecutionService $execution)
    {
    }

    private function isGlobalSuperAdminContext(): bool
    {
        return auth()->check()
            && auth()->user()?->isSuperAdmin()
            && !session()->has('impersonate_user_id');
    }

    private function sourceQueryForActor()
    {
        return $this->isGlobalSuperAdminContext()
            ? LeadSource::withoutGlobalScopes()
            : LeadSource::query();
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index(OperationalEntityTypeCatalog $entityTypeCatalog)
    {
        return view('vault.automation.index', [
            'entityTypes' => $entityTypeCatalog->list(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LEGACY STATS (sources/leads)
    |--------------------------------------------------------------------------
    */

    public function stats(): JsonResponse
    {
        return response()->json([
            'sources' => LeadSource::count(),
            'leads' => LeadRaw::count(),
            'flows' => AutomationFlow::count(),
            'runs' => AutomationRun::count(),
        ]);
    }

    public function health(): JsonResponse
    {
        $queueBacklog = Schema::hasTable('jobs')
            ? (int) DB::table('jobs')->count()
            : 0;

        $failedJobs = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count()
            : 0;

        $importsRunning = (int) LeadSource::query()
            ->whereIn('status', ['queued', 'importing', 'normalizing'])
            ->count();

        $importsFailed = (int) LeadSource::query()
            ->where('status', 'failed')
            ->count();

        $runsRunning = (int) AutomationRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->count();

        $bulkRunning = Schema::hasTable('operational_bulk_tasks')
            ? (int) DB::table('operational_bulk_tasks')
                ->whereIn('status', ['queued', 'running', 'cancel_requested'])
                ->count()
            : 0;

        $state = 'ok';
        if ($importsFailed > 0 || $failedJobs > 0) {
            $state = 'warning';
        }
        if ($failedJobs >= 20) {
            $state = 'critical';
        }

        return response()->json([
            'state' => $state,
            'queue_backlog' => $queueBacklog,
            'failed_jobs_24h' => $failedJobs,
            'imports_running' => $importsRunning,
            'imports_failed' => $importsFailed,
            'runs_running' => $runsRunning,
            'bulk_running' => $bulkRunning,
            'checked_at' => now()->toISOString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FLOWS
    |--------------------------------------------------------------------------
    */

    public function listFlows(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 30), 100));

        $flows = AutomationFlow::query()
            ->withCount(['steps', 'runs'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($flow) => [
                'id' => (int) $flow->id,
                'name' => $flow->name,
                'status' => $flow->status,
                'trigger_type' => $flow->trigger_type,
                'audience_filter' => $flow->audience_filter,
                'steps_count' => (int) ($flow->steps_count ?? 0),
                'runs_count' => (int) ($flow->runs_count ?? 0),
                'published_at' => optional($flow->published_at)?->toISOString(),
                'last_run_at' => optional($flow->last_run_at)?->toISOString(),
                'created_at' => optional($flow->created_at)?->toISOString(),
                'updated_at' => optional($flow->updated_at)?->toISOString(),
            ]);

        return response()->json($flows);
    }

    public function storeFlow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'status' => ['nullable', 'in:draft,active,paused,archived'],
            'trigger_type' => ['nullable', 'string', 'max:40'],
            'trigger_config' => ['nullable', 'array'],
            'audience_filter' => ['nullable', 'array'],
            'goal_config' => ['nullable', 'array'],
        ]);

        $flow = AutomationFlow::query()->create([
            'name' => trim($data['name']),
            'status' => (string) ($data['status'] ?? 'draft'),
            'trigger_type' => Str::lower((string) ($data['trigger_type'] ?? 'manual')),
            'trigger_config' => $data['trigger_config'] ?? null,
            'audience_filter' => $data['audience_filter'] ?? null,
            'goal_config' => $data['goal_config'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'published_at' => (($data['status'] ?? '') === 'active') ? now() : null,
        ]);

        return response()->json([
            'ok' => true,
            'flow_id' => (int) $flow->id,
        ], 201);
    }

    public function showFlow(int $id): JsonResponse
    {
        $flow = AutomationFlow::query()
            ->with(['steps' => fn ($q) => $q->orderBy('step_order')])
            ->findOrFail($id);

        return response()->json([
            'id' => (int) $flow->id,
            'name' => $flow->name,
            'status' => $flow->status,
            'trigger_type' => $flow->trigger_type,
            'trigger_config' => $flow->trigger_config,
            'audience_filter' => $flow->audience_filter,
            'goal_config' => $flow->goal_config,
            'steps' => $flow->steps->map(fn ($step) => [
                'id' => (int) $step->id,
                'step_order' => (int) $step->step_order,
                'step_type' => $step->step_type,
                'channel' => $step->channel,
                'config_json' => $step->config_json,
                'is_active' => (bool) $step->is_active,
            ])->values()->all(),
            'published_at' => optional($flow->published_at)?->toISOString(),
            'last_run_at' => optional($flow->last_run_at)?->toISOString(),
            'created_at' => optional($flow->created_at)?->toISOString(),
            'updated_at' => optional($flow->updated_at)?->toISOString(),
        ]);
    }

    public function updateFlow(int $id, Request $request): JsonResponse
    {
        $flow = AutomationFlow::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:draft,active,paused,archived'],
            'trigger_type' => ['nullable', 'string', 'max:40'],
            'trigger_config' => ['nullable', 'array'],
            'audience_filter' => ['nullable', 'array'],
            'goal_config' => ['nullable', 'array'],
        ]);

        if (array_key_exists('name', $data)) {
            $flow->name = trim((string) $data['name']);
        }

        if (array_key_exists('status', $data)) {
            $flow->status = (string) $data['status'];
            if ($flow->status === 'active' && !$flow->published_at) {
                $flow->published_at = now();
            }
        }

        foreach (['trigger_config', 'audience_filter', 'goal_config'] as $key) {
            if (array_key_exists($key, $data)) {
                $flow->{$key} = $data[$key];
            }
        }

        if (array_key_exists('trigger_type', $data)) {
            $flow->trigger_type = Str::lower((string) $data['trigger_type']);
        }

        $flow->updated_by = auth()->id();
        $flow->save();

        return response()->json(['ok' => true]);
    }

    public function replaceFlowSteps(int $id, Request $request): JsonResponse
    {
        $flow = AutomationFlow::query()->findOrFail($id);

        $data = $request->validate([
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_order' => ['required', 'integer', 'min:1'],
            'steps.*.step_type' => ['required', 'string', 'max:40'],
            'steps.*.channel' => ['nullable', 'in:email,sms,whatsapp,manual'],
            'steps.*.config_json' => ['nullable', 'array'],
            'steps.*.is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($flow, $data): void {
            AutomationFlowStep::query()
                ->where('flow_id', $flow->id)
                ->delete();

            foreach ($data['steps'] as $step) {
                AutomationFlowStep::query()->create([
                    'flow_id' => (int) $flow->id,
                    'step_order' => (int) $step['step_order'],
                    'step_type' => Str::lower((string) $step['step_type']),
                    'channel' => filled($step['channel'] ?? null) ? Str::lower((string) $step['channel']) : null,
                    'config_json' => $step['config_json'] ?? null,
                    'is_active' => (bool) ($step['is_active'] ?? true),
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function runFlow(int $id, Request $request): JsonResponse
    {
        $flow = AutomationFlow::query()->findOrFail($id);

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'audience_filter' => ['nullable', 'array'],
        ]);

        $run = $this->execution->queueRun(
            $flow,
            'user',
            auth()->id(),
            [
                'limit' => $data['limit'] ?? null,
                'audience_filter' => $data['audience_filter'] ?? null,
            ]
        );

        return response()->json([
            'ok' => true,
            'run_id' => (int) $run->id,
            'status' => $run->status,
        ]);
    }

    public function listRuns(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 30), 100));

        $runs = AutomationRun::query()
            ->with('flow:id,name')
            ->withCount([
                'events as processing_events_count' => fn ($q) => $q->where('status', 'processing'),
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($run) => [
                'id' => (int) $run->id,
                'flow_id' => (int) $run->flow_id,
                'flow_name' => optional($run->flow)->name,
                'status' => $run->status,
                'scheduled_count' => (int) $run->scheduled_count,
                'processed_count' => (int) $run->processed_count,
                'success_count' => (int) $run->success_count,
                'failure_count' => (int) $run->failure_count,
                'processing_count' => (int) ($run->processing_events_count ?? 0),
                'started_at' => optional($run->started_at)?->toISOString(),
                'finished_at' => optional($run->finished_at)?->toISOString(),
                'created_at' => optional($run->created_at)?->toISOString(),
            ]);

        return response()->json($runs);
    }

    public function showRun(int $id): JsonResponse
    {
        $run = AutomationRun::query()->findOrFail($id);
        $processingCount = (int) $run->events()->where('status', 'processing')->count();

        return response()->json([
            'id' => (int) $run->id,
            'flow_id' => (int) $run->flow_id,
            'status' => $run->status,
            'context_json' => $run->context_json,
            'scheduled_count' => (int) $run->scheduled_count,
            'processed_count' => (int) $run->processed_count,
            'success_count' => (int) $run->success_count,
            'failure_count' => (int) $run->failure_count,
            'processing_count' => $processingCount,
            'started_at' => optional($run->started_at)?->toISOString(),
            'finished_at' => optional($run->finished_at)?->toISOString(),
            'last_error' => $run->last_error,
        ]);
    }

    public function runEvents(int $id, Request $request): JsonResponse
    {
        $run = AutomationRun::query()->findOrFail($id);
        $perPage = max(1, min((int) $request->integer('per_page', 50), 200));

        $events = $run->events()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($event) => [
                'id' => (int) $event->id,
                'lead_id' => (int) ($event->lead_id ?? 0),
                'event_type' => $event->event_type,
                'channel' => $event->channel,
                'status' => $event->status,
                'attempt' => (int) ($event->attempt ?? 1),
                'error_message' => $event->error_message,
                'occurred_at' => optional($event->occurred_at)?->toISOString(),
                'created_at' => optional($event->created_at)?->toISOString(),
            ]);

        return response()->json($events);
    }

    public function cancelRun(int $id): JsonResponse
    {
        $run = AutomationRun::query()->findOrFail($id);
        $current = Str::lower((string) ($run->status ?? ''));

        if (in_array($current, ['done', 'done_with_errors', 'failed', 'cancelled'], true)) {
            return response()->json([
                'ok' => true,
                'run_id' => (int) $run->id,
                'status' => $run->status,
                'message' => 'Execução já finalizada.',
            ]);
        }

        if ($current === 'queued') {
            $run->status = 'cancelled';
            $run->finished_at = now();
            $run->last_error = null;
            $run->save();

            return response()->json([
                'ok' => true,
                'run_id' => (int) $run->id,
                'status' => $run->status,
                'message' => 'Execução cancelada.',
            ]);
        }

        $run->status = 'cancel_requested';
        $run->save();

        return response()->json([
            'ok' => true,
            'run_id' => (int) $run->id,
            'status' => $run->status,
            'message' => 'Cancelamento solicitado.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LEGACY SOURCE ACTIONS
    |--------------------------------------------------------------------------
    */

    public function reprocessSource(int $id): JsonResponse
    {
        $source = $this->sourceQueryForActor()->findOrFail($id);
        $this->authorize('reprocess', $source);

        $rootSourceId = !empty($source->parent_source_id)
            ? (int) $source->parent_source_id
            : (int) $source->id;
        $rootSource = $this->sourceQueryForActor()->findOrFail($rootSourceId);

        $rootSource->update([
            'status' => 'queued',
            'processed_rows' => 0,
            'progress_percent' => 0,
            'started_at' => null,
            'finished_at' => null,
            'last_error' => null,
        ]);

        ImportLeadSourceJob::dispatch((int) $rootSource->id);

        return response()->json(['ok' => true]);
    }

    public function rebuildExtras(int $id): JsonResponse
    {
        $source = $this->sourceQueryForActor()->findOrFail($id);
        $this->authorize('reprocess', $source);

        return response()->json(['ok' => false, 'message' => 'Extras cache desativado.'], 410);
    }

    public function applySemanticBulk(Request $request): JsonResponse
    {
        $fromId = (int) $request->input('from_id');
        $toIds = collect($request->input('to_ids', []))->unique();

        $baseQuery = $this->isGlobalSuperAdminContext()
            ? LeadSourceSemantic::withoutGlobalScopes()
            : LeadSourceSemantic::query();
        $base = $baseQuery->where('lead_source_id', $fromId)->firstOrFail();
        $this->authorize('normalize', $base->source);

        DB::transaction(function () use ($base, $toIds): void {
            foreach ($toIds as $id) {
                LeadSourceSemantic::updateOrCreate(
                    ['tenant_uuid' => $base->tenant_uuid, 'lead_source_id' => $id],
                    [
                        'segment_id' => $base->segment_id,
                        'niche_id' => $base->niche_id,
                        'origin_id' => $base->origin_id,
                    ]
                );

                $this->sourceQueryForActor()->where('id', $id)
                    ->update(['semantic_anchor' => $base->source->semantic_anchor]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function deleteLeadsBulk(Request $request): JsonResponse
    {
        $this->authorize('purge', LeadSource::class);
        LeadRaw::whereIn('id', $request->ids ?? [])->delete();

        return response()->json(['ok' => true]);
    }

    public function cancelImport(int $id): JsonResponse
    {
        $source = $this->sourceQueryForActor()->findOrFail($id);
        $this->authorize('cancel', $source);

        $this->sourceQueryForActor()
            ->where('id', $id)
            ->update(['cancel_requested' => true]);

        return response()->json(['ok' => true]);
    }

    public function purgeSource(int $id): JsonResponse
    {
        $source = $this->sourceQueryForActor()->findOrFail($id);
        $this->authorize('delete', $source);

        DB::transaction(function () use ($source, $id): void {
            LeadRaw::where('lead_source_id', $id)->delete();
            LeadSourceSemantic::where('lead_source_id', $id)->delete();

            if ($source->file_path) {
                Storage::disk('private')->delete($source->file_path);
            }

            $source->delete();
        });

        return response()->json(['ok' => true]);
    }
}
