@extends('layouts.app')

@section('title','Admin — Lista de Arquivos')
@section('page-title','Admin')

@section('content')
<div class="admin-tenant-users-page">
    <style>
        .file-detail-copy-value {
            word-break: break-all;
            overflow-wrap: anywhere;
        }
        .file-detail-status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 12px;
            border: 1px solid #d8c7aa;
            background: #f6efe4;
            color: #7b5e32;
            text-transform: lowercase;
        }
        .file-detail-status-pill.is-success {
            background: #e7f8ef;
            border-color: #9ddbb8;
            color: #157347;
        }
        .file-detail-status-pill.is-danger {
            background: #fdeaea;
            border-color: #f1b5b5;
            color: #b42318;
        }
        .file-detail-progress-wrap .progress {
            height: 8px;
            border-radius: 999px;
            background: #eee2d2;
        }
        .file-detail-progress-wrap .progress-bar {
            background: #7b5e32;
        }
        .file-detail-modal-dialog {
            max-width: min(1320px, 96vw);
        }
        .file-detail-modal-dialog .modal-content {
            max-height: 92vh;
        }
        .file-detail-modal-dialog .modal-body {
            overflow-y: auto;
        }
    </style>
    <x-admin.page-header
        title="Lista de Arquivos"
        subtitle="Histórico de importações por cliente, status de processamento e auditoria básica."
    >
        @php
            $statusLabelsPt = [
                'queued' => 'na fila',
                'uploading' => 'enviando',
                'importing' => 'importando',
                'normalizing' => 'normalizando',
                'done' => 'concluído',
                'failed' => 'falhou',
                'canceled' => 'cancelado',
                'cancelled' => 'cancelado',
            ];
        @endphp
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.customers.files.index') }}" class="d-flex align-items-center gap-2">
                @if($isGlobalSuper)
                    <select name="tenant_uuid" class="form-select form-select-sm" style="max-width:260px;">
                        <option value="">Todos os clientes</option>
                        @foreach($tenants as $tenant)
                            <option value="{{ $tenant->uuid }}" @selected((string)($selectedTenantUuid ?? '') === (string)$tenant->uuid)>
                                {{ $tenant->name }} ({{ $tenant->slug ?? $tenant->uuid }})
                            </option>
                        @endforeach
                    </select>
                @endif
                <select name="status" class="form-select form-select-sm" style="max-width:180px;">
                    <option value="">Todos os status</option>
                    @foreach(($statusOptions ?? []) as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected((string)($selectedStatus ?? '') === (string)$statusKey)>{{ $statusLabelsPt[(string) $statusKey] ?? (string) $statusLabel }}</option>
                    @endforeach
                </select>
                <select name="archived" class="form-select form-select-sm" style="max-width:180px;">
                    <option value="" @selected((string)($selectedArchivedMode ?? '') === '')>Ativos</option>
                    <option value="only" @selected((string)($selectedArchivedMode ?? '') === 'only')>Arquivados</option>
                </select>
                <input type="search" name="q" value="{{ $search ?? '' }}" class="form-control form-control-sm" placeholder="Buscar por ID único, hash ou ID interno" style="max-width:260px;">
                <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Filtrar</button>
                <a href="{{ route('admin.customers.files.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Limpar</a>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <div class="alert alert-success admin-tenant-users-alert">{{ session('status') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Arquivos visíveis</span>
                        <div class="admin-tenant-users-metric-value">{{ (int) ($visibleCount ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Concluídos</span>
                        <div class="admin-tenant-users-metric-value">{{ (int) ($completedCount ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Em processamento</span>
                        <div class="admin-tenant-users-metric-value">{{ (int) ($processingCount ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm admin-tenant-users-metric">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-tenant-users-metric-box">
                        <span class="grade-profile-field-kicker">Falhas/Cancelados</span>
                        <div class="admin-tenant-users-metric-value">{{ (int) ($failedCount ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm admin-tenant-users-table-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Arquivos importados</strong>
            @php
                $totalListas = (int) $files->total();
                $labelListas = $totalListas === 1 ? 'lista' : 'listas';
            @endphp
            <span class="badge bg-light text-dark border rounded-pill">{{ $totalListas }} {{ $labelListas }}</span>
        </div>
        @php
            $tenantNameByUuid = collect($tenants ?? [])->mapWithKeys(function ($tenant): array {
                $name = trim((string) ($tenant->name ?? ''));
                $slug = trim((string) ($tenant->slug ?? ''));
                $label = $name !== '' ? $name : ($slug !== '' ? $slug : '—');
                return [(string) ($tenant->uuid ?? '') => $label];
            });
        @endphp
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-tenant-users-table admin-enterprise-table">
                <thead class="table-light">
                <tr>
                    <th>LISTA</th>
                    <th>CLIENTE</th>
                    <th>NOME DA LISTA</th>
                    <th>Status</th>
                    <th>Tamanho</th>
                    <th>Linhas</th>
                    <th>Criado em</th>
                    <th>Atualizado em</th>
                    <th class="text-end" style="width:88px">Ações</th>
                </tr>
                </thead>
                <tbody>
                @forelse($files as $file)
                    @php
                        $status = (string) ($file->status ?? 'queued');
                        $size = (int) ($file->file_size_bytes ?? 0);
                        $sizeKb = $size > 0 ? number_format($size / 1024, 1, ',', '.') . ' KB' : '0 KB';
                        $displayName = (string) ($file->display_name ?? '');
                        $displayNameResolved = trim($displayName) !== '' ? $displayName : '—';
                        $fileUniqueId = (string) ($file->public_uid ?? '');
                        if ($fileUniqueId === '') {
                            $fileUniqueId = 'x' . str_pad(base_convert((string) ((int) $file->id), 10, 36), 13, '0', STR_PAD_LEFT);
                        }
                        $customerLabel = (string) ($tenantNameByUuid->get((string) ($file->tenant_uuid ?? ''), ''));
                        if ($customerLabel === '') {
                            $customerLabel = $isGlobalSuper ? (string) ($file->tenant_uuid ?? '—') : 'Conta atual';
                        }
                        $tags = collect($file->admin_tags_json ?? [])->map(fn ($tag) => trim((string) $tag))->filter()->values()->all();
                        $notes = (string) ($file->admin_notes ?? '');
                        $isArchived = !empty($file->archived_at);
                    @endphp
                    <tr data-enterprise-row="1">
                        <td>
                            <a
                                href="{{ route('admin.customers.files.show', array_filter(['id' => $fileUniqueId, 'status' => $selectedStatus, 'archived' => $selectedArchivedMode, 'q' => $search, 'page' => $files->currentPage() > 1 ? $files->currentPage() : null], static fn ($v): bool => $v !== null && $v !== '')) }}"
                                class="badge bg-light text-dark border rounded-pill text-decoration-none"
                                title="Abrir detalhes da lista"
                            >
                                {{ $fileUniqueId }}
                            </a>
                            @if($isArchived)
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark border rounded-pill">arquivado</span>
                                </div>
                            @endif
                        </td>
                        <td><span class="badge bg-light text-dark border rounded-pill" title="{{ (string) ($file->tenant_uuid ?? '') }}">{{ $customerLabel }}</span></td>
                        <td>
                            <span class="fw-semibold">{{ $displayNameResolved }}</span>
                        </td>
                        <td>
                            <span class="badge rounded-pill admin-tenant-users-status {{ in_array($status, ['done'], true) ? 'is-active' : (in_array($status, ['failed','canceled','cancelled'], true) ? 'is-disabled' : 'is-invited') }}">
                                {{ $statusLabelsPt[$status] ?? $status }}
                            </span>
                        </td>
                        <td>{{ $sizeKb }}</td>
                        <td>
                            <span class="badge bg-light text-dark border rounded-pill">{{ (int) ($file->processed_rows ?? 0) }}/{{ (int) ($file->total_rows ?? 0) }}</span>
                        </td>
                        <td>{{ optional($file->created_at)->format('d/m/Y H:i') ?: '—' }}</td>
                        <td>{{ optional($file->updated_at)->format('d/m/Y H:i') ?: '—' }}</td>
                        <td class="text-end">
                            <div class="dropdown admin-table-actions-dropdown">
                                <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações">
                                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                                    <li>
                                        <a
                                            class="dropdown-item admin-table-action-item"
                                            href="{{ route('admin.customers.files.show', array_filter(['id' => $fileUniqueId, 'status' => $selectedStatus, 'archived' => $selectedArchivedMode, 'q' => $search, 'page' => $files->currentPage() > 1 ? $files->currentPage() : null], static fn ($v): bool => $v !== null && $v !== '')) }}"
                                        >
                                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                            <span>Detalhes</span>
                                        </a>
                                    </li>
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item admin-table-action-item"
                                            data-bs-toggle="modal"
                                            data-bs-target="#fileHistoryModal"
                                            data-file-history="1"
                                            data-file-id="{{ (int) $file->id }}"
                                            data-file-uid="{{ e($fileUniqueId) }}"
                                            data-history-url="{{ route('admin.customers.files.history', ['id' => $fileUniqueId]) }}"
                                            data-file-name="{{ e($displayName !== '' ? $displayName : (string) ($file->original_name ?? '')) }}"
                                        >
                                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                                            <span>Histórico de atualizações</span>
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item admin-table-action-item"
                                            data-bs-toggle="modal"
                                            data-bs-target="#fileActionConfirmModal"
                                            data-file-action="archive"
                                            data-file-action-label="{{ $isArchived ? 'Desarquivar' : 'Arquivar' }}"
                                            data-file-action-submit-label="{{ $isArchived ? 'Desarquivar agora' : 'Arquivar agora' }}"
                                            data-file-action-url="{{ route('admin.customers.files.archive', ['id' => $fileUniqueId]) }}"
                                            data-file-action-method="POST"
                                            data-file-action-tenant="{{ $isGlobalSuper && !empty($selectedTenantUuid) ? $selectedTenantUuid : '' }}"
                                            data-file-action-list="{{ $fileUniqueId }}"
                                            data-file-action-name="{{ e($displayNameResolved) }}"
                                            data-file-action-rows="{{ (int) ($file->total_rows ?? 0) }}"
                                            data-file-action-subscribers="{{ (int) ($file->processed_rows ?? 0) }}"
                                        >
                                            <i class="bi bi-archive" aria-hidden="true"></i>
                                            <span>{{ $isArchived ? 'Desarquivar' : 'Arquivar' }}</span>
                                        </button>
                                    </li>
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item admin-table-action-item is-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#fileActionConfirmModal"
                                            data-file-action="delete"
                                            data-file-action-label="Deletar"
                                            data-file-action-submit-label="Deletar agora"
                                            data-file-action-url="{{ route('admin.customers.files.destroy', ['id' => $fileUniqueId]) }}"
                                            data-file-action-method="DELETE"
                                            data-file-action-tenant="{{ $isGlobalSuper && !empty($selectedTenantUuid) ? $selectedTenantUuid : '' }}"
                                            data-file-action-list="{{ $fileUniqueId }}"
                                            data-file-action-name="{{ e($displayNameResolved) }}"
                                            data-file-action-rows="{{ (int) ($file->total_rows ?? 0) }}"
                                            data-file-action-subscribers="{{ (int) ($file->processed_rows ?? 0) }}"
                                        >
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                            <span>Deletar</span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="admin-tenant-users-empty">
                                <div class="admin-tenant-users-empty-title">Nenhum arquivo encontrado</div>
                                <div class="small text-muted">A lista será preenchida conforme novos uploads/importações forem criados.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $files->links() }}
        </div>
    </div>
</div>

<div class="modal fade" id="fileDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable file-detail-modal-dialog">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Detalhes</h5>
                    <p class="grade-modal-hint mb-0">Resumo consolidado do arquivo, processamento, qualidade e semântica.</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-3">
                    <div class="col-12"><div class="small text-muted fw-semibold">Resumo</div></div>
                    <div class="col-md-4"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Lista</span><div class="admin-metric-field-value" id="fileDetailId">—</div></div></div>
                    <div class="col-md-8"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Nome do arquivo</span><div class="admin-metric-field-value" id="fileDetailName">—</div></div></div>
                    <div class="col-md-6"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Cliente (Tenant)</span><div class="admin-metric-field-value" id="fileDetailTenant">—</div></div></div>
                    <div class="col-md-3"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Tamanho</span><div class="admin-metric-field-value" id="fileDetailSize">—</div></div></div>
                    <div class="col-md-3">
                        <div class="grade-profile-field-box">
                            <span class="grade-profile-field-kicker">Hash</span>
                            <div class="d-flex align-items-start gap-2">
                                <div class="admin-metric-field-value file-detail-copy-value flex-grow-1" id="fileDetailHash">—</div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-copy-target="fileDetailHash">Copiar</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="grade-profile-field-box">
                            <span class="grade-profile-field-kicker">Caminho interno</span>
                            <div class="d-flex align-items-start gap-2">
                                <div class="admin-metric-field-value file-detail-copy-value flex-grow-1" id="fileDetailPath">—</div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-copy-target="fileDetailPath">Copiar</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 pt-1"><div class="small text-muted fw-semibold">Processamento</div></div>
                    <div class="col-md-4"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Status do processamento</span><div class="admin-metric-field-value"><span class="file-detail-status-pill" id="fileDetailStatusPill">—</span></div></div></div>
                    <div class="col-md-4">
                        <div class="grade-profile-field-box file-detail-progress-wrap">
                            <span class="grade-profile-field-kicker">Progresso</span>
                            <div class="progress mb-2">
                                <div class="progress-bar" id="fileDetailProgressBar" role="progressbar" style="width:0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="admin-metric-field-value" id="fileDetailProgress">—</div>
                        </div>
                    </div>
                    <div class="col-md-4"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Último erro</span><div class="admin-metric-field-value" id="fileDetailError">—</div></div></div>

                    <div class="col-12 pt-1"><div class="small text-muted fw-semibold">Qualidade do arquivo</div></div>
                    <div class="col-md-4"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Score estimado de qualidade</span><div class="admin-metric-field-value" id="fileQualityScore" title="Estimativa baseada em progresso, status e cobertura de linhas.">—</div></div></div>
                    <div class="col-md-4"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Linhas válidas</span><div class="admin-metric-field-value" id="fileQualityValidRows">—</div></div></div>
                    <div class="col-md-4"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Linhas inválidas</span><div class="admin-metric-field-value" id="fileQualityInvalidRows">—</div></div></div>
                    <div class="col-md-6"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">% de completude</span><div class="admin-metric-field-value" id="fileQualityCompleteness">—</div></div></div>
                    <div class="col-md-6"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Campos críticos com falha</span><div class="admin-metric-field-value" id="fileQualityCritical">—</div></div></div>

                    <div class="col-12 pt-1"><div class="small text-muted fw-semibold">Semântica</div></div>
                    <div class="col-12 d-none" id="fileSemanticEmptyState"><div class="alert alert-light border mb-0">Sem análise semântica para este arquivo.</div></div>
                    <div class="col-md-6" id="fileSemanticFieldAnchor"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Âncora semântica</span><div class="admin-metric-field-value" id="fileSemanticAnchor">—</div></div></div>
                    <div class="col-md-6" id="fileSemanticFieldCategories"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Categorias detectadas</span><div class="admin-metric-field-value" id="fileSemanticCategories">—</div></div></div>
                    <div class="col-md-6" id="fileSemanticFieldLocations"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Localizações detectadas</span><div class="admin-metric-field-value" id="fileSemanticLocations">—</div></div></div>
                    <div class="col-md-6" id="fileSemanticFieldConfidence"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Confiabilidade semântica</span><div class="admin-metric-field-value" id="fileSemanticConfidence">—</div></div></div>

                    <div class="col-12 pt-1"><div class="small text-muted fw-semibold">Auditoria rápida</div></div>
                    <div class="col-md-6"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Data de criação</span><div class="admin-metric-field-value" id="fileDetailCreated">—</div></div></div>
                    <div class="col-md-6"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Última atualização</span><div class="admin-metric-field-value" id="fileDetailUpdated">—</div></div></div>
                    <div class="col-md-6"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Tags</span><div class="admin-metric-field-value" id="fileDetailTags">—</div></div></div>
                    <div class="col-md-6"><div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Notas internas</span><div class="admin-metric-field-value" id="fileDetailNotes">—</div></div></div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-3">
                    <div class="d-flex gap-2">
                        <a href="#" id="fileDetailOpenFull" class="btn btn-outline-secondary btn-sm">Detalhes</a>
                        <button type="button" id="fileDetailOpenHistory" class="btn btn-outline-secondary btn-sm">Ver histórico</button>
                        <button type="button" id="fileDetailReprocessBtn" class="btn btn-outline-secondary btn-sm">Reprocessar</button>
                        <a href="#" id="fileDetailErrorReportBtn" class="btn btn-outline-secondary btn-sm">Baixar relatório de erros</a>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>
<form method="POST" id="fileDetailReprocessForm" class="d-none">
    @csrf
    <input type="hidden" name="tenant_uuid" id="fileDetailReprocessTenantUuid" value="">
</form>

<div class="modal fade" id="fileHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1">Histórico de atualizações</h5>
                    <p class="grade-modal-hint mb-0" id="fileHistoryTitleHint">Histórico de eventos do arquivo.</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="small text-muted mb-1 d-block">Ação</label>
                        <select id="fileHistoryActionFilter" class="form-select form-select-sm">
                            <option value="">Todas as ações</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="small text-muted mb-1 d-block">Ordem</label>
                        <select id="fileHistorySort" class="form-select form-select-sm">
                            <option value="desc">Mais recente</option>
                            <option value="asc">Mais antigo</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted mb-1 d-block">Data inicial</label>
                        <input id="fileHistoryDateFrom" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted mb-1 d-block">Data final</label>
                        <input id="fileHistoryDateTo" type="date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="button" id="fileHistoryApplyFilter" class="btn btn-outline-secondary btn-sm">Filtrar</button>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="button" id="fileHistoryClearFilter" class="btn btn-outline-secondary btn-sm">Limpar filtros</button>
                    </div>
                </div>
                <div id="fileHistoryLoading" class="small text-muted">Carregando histórico...</div>
                <div id="fileHistoryEmpty" class="small text-muted d-none">Sem histórico para este arquivo.</div>
                <div class="table-responsive d-none" id="fileHistoryTableWrap">
                    <table class="table table-sm align-middle mb-0 admin-enterprise-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:180px">Data</th>
                                <th style="width:180px">Ação</th>
                                <th style="width:220px">Ator</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody id="fileHistoryTableBody"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-3">
                    <div class="small text-muted" id="fileHistoryPaginationInfo">Página 1 de 1</div>
                    <div class="d-flex gap-2">
                        <a href="#" id="fileHistoryExport" class="btn btn-outline-secondary btn-sm">Exportar CSV</a>
                        <button type="button" id="fileHistoryPrev" class="btn btn-outline-secondary btn-sm">Anterior</button>
                        <button type="button" id="fileHistoryNext" class="btn btn-outline-secondary btn-sm">Próxima</button>
                    </div>
                </div>
                <div class="d-flex justify-content-end pt-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="fileActionConfirmModal" tabindex="-1" aria-labelledby="fileActionConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="fileActionConfirmModalLabel">Confirmar ação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-2">Você está prestes a executar: <strong id="fileActionConfirmName">—</strong>.</p>
                <div class="grade-profile-field-box mb-2">
                    <span class="grade-profile-field-kicker">Lista</span>
                    <div id="fileActionConfirmList">—</div>
                </div>
                <div class="grade-profile-field-box mb-2">
                    <span class="grade-profile-field-kicker">Nome amigável</span>
                    <div id="fileActionConfirmDisplayName">—</div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="grade-profile-field-box">
                            <span class="grade-profile-field-kicker">Assinantes (estimado)</span>
                            <div id="fileActionConfirmSubscribers">0</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="grade-profile-field-box">
                            <span class="grade-profile-field-kicker">Linhas do arquivo</span>
                            <div id="fileActionConfirmRows">0</div>
                        </div>
                    </div>
                </div>
                <div class="small text-muted mt-2" id="fileActionConfirmHint">Confirme para continuar.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <form id="fileActionConfirmForm" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="fileActionConfirmMethod" value="POST">
                    <input type="hidden" name="tenant_uuid" id="fileActionConfirmTenant" value="">
                    <button type="submit" class="btn btn-primary rounded-pill btn-sm" id="fileActionConfirmSubmit">Confirmar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var pageRoot = document.querySelector('.admin-tenant-users-page');
    var detailModalEl = document.getElementById('fileDetailModal');
    var detailButtons = Array.prototype.slice.call(document.querySelectorAll('[data-file-detail="1"]'));
    var historyModalEl = document.getElementById('fileHistoryModal');
    var historyButtons = Array.prototype.slice.call(document.querySelectorAll('[data-file-history="1"]'));
    var actionConfirmModalEl = document.getElementById('fileActionConfirmModal');
    var historyActionFilter = document.getElementById('fileHistoryActionFilter');
    var historySort = document.getElementById('fileHistorySort');
    var historyDateFrom = document.getElementById('fileHistoryDateFrom');
    var historyDateTo = document.getElementById('fileHistoryDateTo');
    var historyApplyBtn = document.getElementById('fileHistoryApplyFilter');
    var historyClearBtn = document.getElementById('fileHistoryClearFilter');
    var historyExportBtn = document.getElementById('fileHistoryExport');
    var historyPrevBtn = document.getElementById('fileHistoryPrev');
    var historyNextBtn = document.getElementById('fileHistoryNext');
    var historyPageInfo = document.getElementById('fileHistoryPaginationInfo');
    var historyState = {
        url: '',
        page: 1,
        lastPage: 1,
        loadedActions: false
    };
    var historyActionTemplate = @json(url('/admin/lists/__ID__/history'));
    var reprocessActionTemplate = @json(url('/admin/lists/__ID__/reprocess'));
    var errorReportActionTemplate = @json(url('/admin/lists/__ID__/error-report'));
    var detailHistoryBtn = document.getElementById('fileDetailOpenHistory');
    var detailOpenFullBtn = document.getElementById('fileDetailOpenFull');
    var detailReprocessBtn = document.getElementById('fileDetailReprocessBtn');
    var detailErrorReportBtn = document.getElementById('fileDetailErrorReportBtn');
    var actionConfirmForm = document.getElementById('fileActionConfirmForm');
    var actionConfirmMethod = document.getElementById('fileActionConfirmMethod');
    var actionConfirmTenant = document.getElementById('fileActionConfirmTenant');
    var actionConfirmSubmit = document.getElementById('fileActionConfirmSubmit');
    var detailReprocessForm = document.getElementById('fileDetailReprocessForm');
    var detailReprocessTenantUuid = document.getElementById('fileDetailReprocessTenantUuid');
    var detailStatusPill = document.getElementById('fileDetailStatusPill');
    var detailProgressBar = document.getElementById('fileDetailProgressBar');
    var detailSemanticEmpty = document.getElementById('fileSemanticEmptyState');
    var semanticFieldIds = ['fileSemanticFieldAnchor', 'fileSemanticFieldCategories', 'fileSemanticFieldLocations', 'fileSemanticFieldConfidence'];
    var currentDetail = {
        id: '',
        routeKey: '',
        name: '',
        tenantUuid: '',
        showUrl: ''
    };

    if (!detailModalEl) return;

    function renderUiHealth(message, level) {
        if (!pageRoot || !message) return;
        var id = 'fileActionsHealthAlert';
        var existing = document.getElementById(id);
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }
        var alert = document.createElement('div');
        alert.id = id;
        alert.className = 'alert ' + (level === 'danger' ? 'alert-danger' : 'alert-warning') + ' mb-3';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        pageRoot.insertBefore(alert, pageRoot.firstChild);
    }

    function esc(value) {
        return String(value || '').replace(/[&<>"']/g, function (ch) {
            var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};
            return map[ch] || ch;
        });
    }

    function showModal(el) {
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
            return;
        }
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(el).modal('show');
            return;
        }
        renderUiHealth('Diagnóstico: modal indisponível no navegador. Recarregue a página (Ctrl+F5).', 'danger');
    }

    function bindText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(value || '—');
    }

    function buildUrl(template, id, tenantUuid) {
        var out = String(template || '').replace('__ID__', encodeURIComponent(String(id || '')));
        var hasQuery = out.indexOf('?') !== -1;
        if (tenantUuid) {
            out += (hasQuery ? '&' : '?') + 'tenant_uuid=' + encodeURIComponent(String(tenantUuid));
        }
        return out;
    }

    function statusMeta(rawStatus) {
        var value = String(rawStatus || '').toLowerCase();
        var label = value || 'queued';
        var cls = '';
        if (value === 'done') {
            label = 'concluído';
            cls = 'is-success';
        } else if (value === 'failed' || value === 'canceled' || value === 'cancelled') {
            label = value === 'failed' ? 'falhou' : 'cancelado';
            cls = 'is-danger';
        } else if (value === 'importing' || value === 'uploading' || value === 'normalizing') {
            label = 'processando';
        } else if (value === 'queued') {
            label = 'na fila';
        }
        return { cls: cls, label: label };
    }

    function setStatusPill(rawStatus) {
        if (!detailStatusPill) return;
        var meta = statusMeta(rawStatus);
        detailStatusPill.className = 'file-detail-status-pill';
        if (meta.cls) detailStatusPill.classList.add(meta.cls);
        detailStatusPill.textContent = meta.label;
    }

    function setProgress(processed, total) {
        var safeProcessed = Number(processed || 0);
        var safeTotal = Number(total || 0);
        if (safeProcessed < 0) safeProcessed = 0;
        if (safeTotal < 0) safeTotal = 0;
        var pct = safeTotal > 0 ? Math.round((safeProcessed / safeTotal) * 100) : 0;
        if (pct > 100) pct = 100;
        bindText('fileDetailProgress', String(safeProcessed) + '/' + String(safeTotal) + ' (' + String(pct) + '%)');
        if (!detailProgressBar) return;
        detailProgressBar.style.width = String(pct) + '%';
        detailProgressBar.setAttribute('aria-valuenow', String(pct));
        detailProgressBar.textContent = '';
    }

    function setSemanticVisibility(hasSemantic) {
        var i;
        if (detailSemanticEmpty) {
            detailSemanticEmpty.classList.toggle('d-none', hasSemantic);
        }
        for (i = 0; i < semanticFieldIds.length; i++) {
            var el = document.getElementById(semanticFieldIds[i]);
            if (el) {
                el.classList.toggle('d-none', !hasSemantic);
            }
        }
    }

    function copyText(value, btn) {
        var done = function () {
            if (!btn) return;
            var oldText = btn.textContent;
            btn.textContent = 'Copiado';
            setTimeout(function () { btn.textContent = oldText; }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(value || '')).then(done);
            return;
        }
        var helper = document.createElement('textarea');
        helper.value = String(value || '');
        document.body.appendChild(helper);
        helper.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(helper);
        done();
    }

    function buildHistoryUrl(baseUrl, page) {
        var out = baseUrl;
        var hasQuery = out.indexOf('?') !== -1;
        function addParam(k, v) {
            if (v === null || v === undefined || v === '') return;
            out += (hasQuery ? '&' : '?') + encodeURIComponent(k) + '=' + encodeURIComponent(v);
            hasQuery = true;
        }
        addParam('page', String(page || 1));
        addParam('per_page', '10');
        addParam('action', historyActionFilter && historyActionFilter.value ? historyActionFilter.value : '');
        addParam('date_from', historyDateFrom && historyDateFrom.value ? historyDateFrom.value : '');
        addParam('date_to', historyDateTo && historyDateTo.value ? historyDateTo.value : '');
        addParam('sort', historySort && historySort.value ? historySort.value : 'desc');
        return out;
    }

    function getJson(url, onSuccess, onError) {
        if (window.fetch) {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json(); })
                .then(onSuccess)
                .catch(onError);
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    onSuccess(JSON.parse(xhr.responseText));
                } catch (e) {
                    onError(e);
                }
            } else {
                onError(new Error('HTTP ' + xhr.status));
            }
        };
        xhr.send();
    }

    function updateHistoryExportUrl() {
        if (!historyExportBtn || !historyState.url) return;
        var exportBase = historyState.url.replace('/history', '/history-export');
        historyExportBtn.href = buildHistoryUrl(exportBase, 1).replace(/([?&])page=1(&|$)/, '$1').replace(/[?&]$/, '');
    }

    function renderHistoryRows(events) {
        var body = document.getElementById('fileHistoryTableBody');
        if (!body) return;
        body.innerHTML = '';
        for (var i = 0; i < events.length; i++) {
            var event = events[i] || {};
            var tr = document.createElement('tr');
            var when = esc(event.created_at || '—');
            var action = esc(event.action || '—');
            var actorType = String(event.actor_type || 'user');
            var actorName = String(event.actor_name || '').trim();
            var actorEmail = String(event.actor_email || '').trim();
            var actor = actorName !== ''
                ? esc(actorName) + (actorEmail !== '' ? '<div class="small text-muted">' + esc(actorEmail) + '</div>' : '')
                : esc(actorType);

            var detail = '-';
            var payload = event.payload || {};
            var diff = payload.diff || null;
            if (diff && typeof diff === 'object') {
                var chunks = [];
                for (var field in diff) {
                    if (!Object.prototype.hasOwnProperty.call(diff, field)) continue;
                    var values = diff[field] || {};
                    var from = Array.isArray(values.from) ? values.from.join(', ') : String(values.from != null ? values.from : '—');
                    var to = Array.isArray(values.to) ? values.to.join(', ') : String(values.to != null ? values.to : '—');
                    chunks.push('<div><strong>' + esc(field) + '</strong>: ' + esc(from) + ' -> ' + esc(to) + '</div>');
                }
                if (chunks.length > 0) detail = chunks.join('');
            } else if (payload.changed_fields && payload.changed_fields.length) {
                detail = 'Campos alterados: ' + esc(payload.changed_fields.join(', '));
            } else if (payload.mvp_mode) {
                detail = 'Modo: ' + esc(String(payload.mvp_mode));
            }

            tr.innerHTML = '<td>' + when + '</td><td><span class="badge bg-light text-dark border">' + action + '</span></td><td>' + actor + '</td><td>' + detail + '</td>';
            body.appendChild(tr);
        }
    }

    function loadHistoryPage(page) {
        var loading = document.getElementById('fileHistoryLoading');
        var empty = document.getElementById('fileHistoryEmpty');
        var wrap = document.getElementById('fileHistoryTableWrap');
        var body = document.getElementById('fileHistoryTableBody');
        if (!historyState.url) return;
        if (loading) loading.classList.remove('d-none');
        if (empty) empty.classList.add('d-none');
        if (wrap) wrap.classList.add('d-none');
        if (body) body.innerHTML = '';

        var url = buildHistoryUrl(historyState.url, page || 1);
        updateHistoryExportUrl();

        getJson(url, function (payload) {
            var events = payload && payload.events && payload.events.length ? payload.events : [];
            var actionOptions = payload && payload.action_options && payload.action_options.length ? payload.action_options : [];
            var pagination = payload && payload.pagination ? payload.pagination : {};
            historyState.page = Number(pagination.page || page || 1) || 1;
            historyState.lastPage = Number(pagination.last_page || 1) || 1;

            if (!historyState.loadedActions && historyActionFilter) {
                historyActionFilter.innerHTML = '<option value="">Todas as ações</option>';
                for (var i = 0; i < actionOptions.length; i++) {
                    var opt = document.createElement('option');
                    opt.value = String(actionOptions[i]);
                    opt.textContent = String(actionOptions[i]);
                    historyActionFilter.appendChild(opt);
                }
                historyState.loadedActions = true;
            }

            if (loading) loading.classList.add('d-none');
            if (historyPageInfo) {
                var total = Number(pagination.total || events.length || 0);
                historyPageInfo.textContent = 'Página ' + historyState.page + ' de ' + historyState.lastPage + ' • ' + total + ' evento(s)';
            }
            if (historyPrevBtn) historyPrevBtn.disabled = historyState.page <= 1;
            if (historyNextBtn) historyNextBtn.disabled = historyState.page >= historyState.lastPage;

            if (!events.length) {
                if (empty) empty.classList.remove('d-none');
                return;
            }
            if (wrap) wrap.classList.remove('d-none');
            renderHistoryRows(events);
        }, function () {
            if (loading) loading.classList.add('d-none');
            if (empty) {
                empty.textContent = 'Não foi possível carregar o histórico.';
                empty.classList.remove('d-none');
            }
        });
    }

    if (!historyModalEl) {
        console.warn('[admin/files] Some modal elements are missing in DOM.');
        renderUiHealth('Diagnóstico: elementos de modal ausentes na página. Verifique cache de views/assets.', 'danger');
    }

    if (actionConfirmModalEl && actionConfirmForm) {
        actionConfirmModalEl.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;

            var actionLabel = String(trigger.getAttribute('data-file-action-label') || 'Confirmar');
            var submitLabel = String(trigger.getAttribute('data-file-action-submit-label') || 'Confirmar');
            var actionUrl = String(trigger.getAttribute('data-file-action-url') || '');
            var actionMethod = String(trigger.getAttribute('data-file-action-method') || 'POST').toUpperCase();
            var tenantUuid = String(trigger.getAttribute('data-file-action-tenant') || '');
            var listId = String(trigger.getAttribute('data-file-action-list') || '—');
            var displayName = String(trigger.getAttribute('data-file-action-name') || '—');
            var rowsCount = Number(trigger.getAttribute('data-file-action-rows') || 0);
            var subscribersCount = Number(trigger.getAttribute('data-file-action-subscribers') || 0);

            bindText('fileActionConfirmName', actionLabel);
            bindText('fileActionConfirmList', listId || '—');
            bindText('fileActionConfirmDisplayName', displayName || '—');
            bindText('fileActionConfirmRows', String(isNaN(rowsCount) ? 0 : rowsCount));
            bindText('fileActionConfirmSubscribers', String(isNaN(subscribersCount) ? 0 : subscribersCount));

            var hint = document.getElementById('fileActionConfirmHint');
            if (hint) {
                hint.textContent = actionMethod === 'DELETE'
                    ? 'Esta ação aplica soft delete no arquivo e pode ser revertida apenas por restore técnico.'
                    : 'A ação altera somente o estado do arquivo e mantém os dados existentes.';
            }

            actionConfirmForm.action = actionUrl;
            actionConfirmMethod.value = actionMethod === 'DELETE' ? 'DELETE' : 'POST';
            actionConfirmTenant.value = tenantUuid;
            if (actionConfirmSubmit) {
                actionConfirmSubmit.textContent = submitLabel;
                actionConfirmSubmit.classList.toggle('btn-danger', actionMethod === 'DELETE');
                actionConfirmSubmit.classList.toggle('btn-primary', actionMethod !== 'DELETE');
            }
        });
    }

    for (var i = 0; i < detailButtons.length; i++) {
        (function (button) {
            button.addEventListener('click', function () {
                var processed = parseInt(button.getAttribute('data-file-processed') || '0', 10);
                var total = parseInt(button.getAttribute('data-file-total') || '0', 10);
                if (isNaN(processed) || processed < 0) processed = 0;
                if (isNaN(total) || total < 0) total = 0;
                var invalid = total > processed ? (total - processed) : 0;
                var baseScore = total > 0 ? Math.round((processed / total) * 100) : 0;
                var status = String(button.getAttribute('data-file-status') || '').toLowerCase();
                if (status === 'failed' || status === 'canceled' || status === 'cancelled') {
                    baseScore = Math.max(0, baseScore - 20);
                }
                var errorText = String(button.getAttribute('data-file-error') || '');
                var critical = errorText ? 'Verifique o último erro' : 'Nenhuma falha crítica detectada';
                var fileId = String(button.getAttribute('data-file-id') || '').replace(/^\s+|\s+$/g, '');
                var fileRouteKey = String(button.getAttribute('data-file-uid') || fileId).replace(/^\s+|\s+$/g, '');
                var fileName = String(button.getAttribute('data-file-name') || '').replace(/^\s+|\s+$/g, '');
                var fileTenant = String(button.getAttribute('data-file-tenant') || '').replace(/^\s+|\s+$/g, '');
                var semanticAnchor = String(button.getAttribute('data-file-semantic-anchor') || '').replace(/^\s+|\s+$/g, '');
                var showUrl = String(button.getAttribute('data-file-show-url') || '');

                currentDetail.id = fileId;
                currentDetail.routeKey = fileRouteKey;
                currentDetail.name = fileName;
                currentDetail.tenantUuid = fileTenant;
                currentDetail.showUrl = showUrl;

                bindText('fileDetailId', String(fileRouteKey || '—') + (fileId ? (' (ID interno #' + String(fileId) + ')') : ''));
                bindText('fileDetailName', fileName);
                bindText('fileDetailPath', button.getAttribute('data-file-path'));
                bindText('fileDetailTenant', button.getAttribute('data-file-tenant'));
                bindText('fileDetailSize', button.getAttribute('data-file-size'));
                bindText('fileDetailHash', button.getAttribute('data-file-hash'));
                bindText('fileDetailCreated', button.getAttribute('data-file-created'));
                bindText('fileDetailUpdated', button.getAttribute('data-file-updated'));
                bindText('fileDetailTags', button.getAttribute('data-file-tags') || '—');
                bindText('fileDetailNotes', button.getAttribute('data-file-notes') || '—');
                bindText('fileDetailError', button.getAttribute('data-file-error') || 'Sem erro registrado');
                bindText('fileQualityScore', String(baseScore) + '/100');
                bindText('fileQualityValidRows', String(processed));
                bindText('fileQualityInvalidRows', String(invalid));
                bindText('fileQualityCompleteness', total > 0 ? (String(Math.round((processed / total) * 100)) + '%') : '0%');
                bindText('fileQualityCritical', critical);
                setStatusPill(button.getAttribute('data-file-status'));
                setProgress(processed, total);
                bindText('fileSemanticAnchor', semanticAnchor || 'Não definida');
                bindText('fileSemanticCategories', semanticAnchor ? 'Não disponível neste overview' : '—');
                bindText('fileSemanticLocations', semanticAnchor ? 'Não disponível neste overview' : '—');
                bindText('fileSemanticConfidence', semanticAnchor ? 'Não disponível neste overview' : '—');
                setSemanticVisibility(Boolean(semanticAnchor));
                if (detailErrorReportBtn) {
                    detailErrorReportBtn.href = buildUrl(errorReportActionTemplate, fileRouteKey, fileTenant);
                }
                if (detailOpenFullBtn) {
                    detailOpenFullBtn.href = showUrl || '#';
                }
                showModal(detailModalEl);
            });
        })(detailButtons[i]);
    }

    if (detailHistoryBtn) {
        detailHistoryBtn.addEventListener('click', function () {
            if (!currentDetail.id || !historyModalEl) return;
            var titleHint = document.getElementById('fileHistoryTitleHint');
            if (titleHint) titleHint.textContent = 'Histórico de eventos: ' + (currentDetail.name || ('#' + currentDetail.id));
            historyState.url = buildUrl(historyActionTemplate, currentDetail.routeKey || currentDetail.id, currentDetail.tenantUuid);
            historyState.page = 1;
            historyState.lastPage = 1;
            historyState.loadedActions = false;
            if (historyActionFilter) historyActionFilter.value = '';
            if (historySort) historySort.value = 'desc';
            if (historyDateFrom) historyDateFrom.value = '';
            if (historyDateTo) historyDateTo.value = '';
            if (historyPageInfo) historyPageInfo.textContent = 'Página 1 de 1';
            if (historyPrevBtn) historyPrevBtn.disabled = true;
            if (historyNextBtn) historyNextBtn.disabled = true;
            showModal(historyModalEl);
            loadHistoryPage(1);
        });
    }

    if (detailReprocessBtn) {
        detailReprocessBtn.addEventListener('click', function () {
            if (!currentDetail.id || !detailReprocessForm) return;
            if (!window.confirm('Reprocessar este arquivo agora?')) return;
            detailReprocessForm.action = buildUrl(reprocessActionTemplate, currentDetail.routeKey || currentDetail.id, '');
            if (detailReprocessTenantUuid) {
                detailReprocessTenantUuid.value = currentDetail.tenantUuid || '';
            }
            detailReprocessForm.submit();
        });
    }

    var copyButtons = Array.prototype.slice.call(document.querySelectorAll('[data-copy-target]'));
    for (var c = 0; c < copyButtons.length; c++) {
        (function (button) {
            button.addEventListener('click', function () {
                var targetId = button.getAttribute('data-copy-target');
                if (!targetId) return;
                var sourceEl = document.getElementById(targetId);
                if (!sourceEl) return;
                copyText(sourceEl.textContent || '', button);
            });
        })(copyButtons[c]);
    }

    for (var k = 0; k < historyButtons.length; k++) {
        (function (button) {
            button.addEventListener('click', function () {
                if (!historyModalEl) return;
                var titleHint = document.getElementById('fileHistoryTitleHint');
                var historyUrl = String(button.getAttribute('data-history-url') || '');
                var fileName = String(button.getAttribute('data-file-name') || '');
                if (titleHint) titleHint.textContent = 'Histórico de eventos: ' + fileName;
                historyState.url = historyUrl;
                historyState.page = 1;
                historyState.lastPage = 1;
                historyState.loadedActions = false;
                if (historyActionFilter) historyActionFilter.value = '';
                if (historySort) historySort.value = 'desc';
                if (historyDateFrom) historyDateFrom.value = '';
                if (historyDateTo) historyDateTo.value = '';
                if (historyPageInfo) historyPageInfo.textContent = 'Página 1 de 1';
                if (historyPrevBtn) historyPrevBtn.disabled = true;
                if (historyNextBtn) historyNextBtn.disabled = true;
                showModal(historyModalEl);
                loadHistoryPage(1);
            });
        })(historyButtons[k]);
    }

    if (historyApplyBtn) {
        historyApplyBtn.addEventListener('click', function () { loadHistoryPage(1); });
    }
    if (historyClearBtn) {
        historyClearBtn.addEventListener('click', function () {
            if (historyActionFilter) historyActionFilter.value = '';
            if (historyDateFrom) historyDateFrom.value = '';
            if (historyDateTo) historyDateTo.value = '';
            if (historySort) historySort.value = 'desc';
            loadHistoryPage(1);
        });
    }
    if (historyPrevBtn) {
        historyPrevBtn.addEventListener('click', function () {
            if (historyState.page > 1) loadHistoryPage(historyState.page - 1);
        });
    }
    if (historyNextBtn) {
        historyNextBtn.addEventListener('click', function () {
            if (historyState.page < historyState.lastPage) loadHistoryPage(historyState.page + 1);
        });
    }
})();
</script>
@endsection
