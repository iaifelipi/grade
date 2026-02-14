@extends('layouts.app')

@section('title', 'Admin — Monitoramento')
@section('page-title', 'Admin')

@section('content')
<div
    class="admin-monitoring-page"
    id="monitoringPage"
    data-health-url="{{ route('admin.monitoring.health') }}"
    data-restart-url="{{ route('admin.monitoring.queueRestart') }}"
    data-recover-url="{{ route('admin.monitoring.recoverQueue') }}"
    data-incidents-export-url="{{ route('admin.monitoring.incidentsExport') }}"
    data-incidents-ack-url-template="{{ route('admin.monitoring.incidentsAck', ['id' => '__ID__']) }}"
    data-csrf-token="{{ csrf_token() }}"
>
    <x-admin.page-header
        title="Monitoramento"
        subtitle="Status em tempo real de workers, filas e importações."
    >
        <x-slot:actions>
            <a href="{{ route('admin.monitoring.performance') }}" class="btn btn-outline-secondary btn-sm">Performance</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="monitoringMuteBtn">Som: ligado</button>
            <span class="badge text-bg-light border" id="monitoringCheckedAt">Aguardando...</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="monitoringRefreshBtn">Atualizar</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div id="monitoringActiveAlert" class="alert alert-danger d-none mb-3" role="alert">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div>
                <strong>Alerta operacional ativo</strong>
                <span class="small ms-2" id="monitoringActiveAlertText">Falha crítica detectada.</span>
            </div>
            <span class="badge text-bg-danger" id="monitoringActiveAlertBadge">CRÍTICO</span>
        </div>
    </div>

    <div id="monitoringAlert" class="alert alert-light border d-none mb-3"></div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Scheduler</div>
                    <div class="fw-bold fs-5" id="monitoringSchedulerStatus">—</div>
                    <div class="small text-muted" id="monitoringSchedulerMode">modo: —</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Banco (latência)</div>
                    <div class="fw-bold fs-5" id="monitoringDbLatency">—</div>
                    <div class="small text-muted" id="monitoringDbStatus">status: —</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">APIs externas degradadas</div>
                    <div class="fw-bold fs-5" id="monitoringExternalDown">0</div>
                    <div class="small text-muted" id="monitoringExternalSummary">serviços monitorados: 0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Throughput médio (15m)</div>
                    <div class="fw-bold fs-5" id="monitoringThroughputAvg">0/min</div>
                    <div class="small text-muted" id="monitoringThroughputWindow">janela: 15 min</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" id="monitoringRedisCard">
                <div class="card-body">
                    <div class="text-muted small">Redis / Cache</div>
                    <div class="fw-bold fs-5" id="monitoringRedisStatus">—</div>
                    <div class="small text-muted" id="monitoringRedisSummary">driver: —</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" id="monitoringDiskCard">
                <div class="card-body">
                    <div class="text-muted small">Disco / Storage</div>
                    <div class="fw-bold fs-5" id="monitoringDiskUsage">—</div>
                    <div class="small text-muted" id="monitoringDiskSummary">logs: —</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" id="monitoringQueueDelayCard">
                <div class="card-body">
                    <div class="text-muted small">Delay / Retries de fila</div>
                    <div class="fw-bold fs-5" id="monitoringQueueDelayAvg">—</div>
                    <div class="small text-muted" id="monitoringQueueRetrySummary">retries: 0</div>
                </div>
            </div>
        </div>
    </div>

    <div id="monitoringCriticalPanel" class="card border-0 shadow-sm mb-3 d-none">
        <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
            <span>Fila crítica (prioridade automática)</span>
            <button type="button" class="btn btn-danger btn-sm" id="monitoringRecoverTopBtn" disabled>Recuperar agora</button>
        </div>
        <div class="card-body py-2">
            <div class="small text-muted mb-2" id="monitoringCriticalSummary">Sem filas críticas no momento.</div>
            <div id="monitoringCriticalList" class="small"></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Workers OK</div>
                    <div class="fw-bold fs-4" id="monitoringWorkersRunning">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Workers esperados</div>
                    <div class="fw-bold fs-4" id="monitoringWorkersExpected">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Jobs pendentes</div>
                    <div class="fw-bold fs-4" id="monitoringPendingJobs">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Falhas 24h</div>
                    <div class="fw-bold fs-4" id="monitoringFailedJobs">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between gap-2">
                    <div>
                        <div class="text-muted small">Usuários ativos (5 min)</div>
                        <div class="fw-bold fs-4" id="monitoringActiveUsers">0</div>
                    </div>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="monitoringQueueRestartBtn">
                        Reiniciar filas
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Ações rápidas e instruções operacionais</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge text-bg-light border"><code>php artisan queue:restart</code></span>
                <span class="badge text-bg-light border"><code>sudo supervisorctl status</code></span>
                <span class="badge text-bg-light border"><code>sudo supervisorctl restart grade-imports:*</code></span>
                <span class="badge text-bg-light border"><code>sudo supervisorctl restart grade-normalize:*</code></span>
            </div>
            <div class="small text-muted mb-2">
                Recuperação por fila exige confirmação segura: digite <code>RECUPERAR NOME_DA_FILA</code>.
            </div>
            <ul class="mb-0 small" id="monitoringInstructions">
                <li class="text-muted">Sem recomendações no momento.</li>
            </ul>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Thresholds ativos (somente leitura)</div>
        <div class="card-body">
            <div class="row g-2 small">
                <div class="col-md-3">
                    <div class="border rounded px-2 py-2 h-100">
                        <div class="text-muted">DB latência warning</div>
                        <div class="fw-semibold" id="monitoringThresholdDbWarning">—</div>
                        <div class="text-muted" id="monitoringThresholdDbWarningSource">—</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded px-2 py-2 h-100">
                        <div class="text-muted">DB latência critical</div>
                        <div class="fw-semibold" id="monitoringThresholdDbCritical">—</div>
                        <div class="text-muted" id="monitoringThresholdDbCriticalSource">—</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded px-2 py-2 h-100">
                        <div class="text-muted">Fila backlog warning</div>
                        <div class="fw-semibold" id="monitoringThresholdQueueBacklogWarning">—</div>
                        <div class="text-muted" id="monitoringThresholdQueueBacklogWarningSource">—</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded px-2 py-2 h-100">
                        <div class="text-muted">Fila backlog critical</div>
                        <div class="fw-semibold" id="monitoringThresholdQueueBacklogCritical">—</div>
                        <div class="text-muted" id="monitoringThresholdQueueBacklogCriticalSource">—</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded px-2 py-2 h-100">
                        <div class="text-muted">Fila falhas 15m warning</div>
                        <div class="fw-semibold" id="monitoringThresholdQueueFail15Warning">—</div>
                        <div class="text-muted" id="monitoringThresholdQueueFail15WarningSource">—</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded px-2 py-2 h-100">
                        <div class="text-muted">Fila falhas 15m critical</div>
                        <div class="fw-semibold" id="monitoringThresholdQueueFail15Critical">—</div>
                        <div class="text-muted" id="monitoringThresholdQueueFail15CriticalSource">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Scheduler e banco</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Componente</th>
                                <th>Status</th>
                                <th>Métrica</th>
                                <th>Detalhe</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringInfraTable">
                            <tr><td colspan="4" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">APIs externas</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Serviço</th>
                                <th>Status</th>
                                <th>Destino</th>
                                <th>Latência</th>
                                <th>Erro</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringExternalTable">
                            <tr><td colspan="5" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Workers por fila</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fila</th>
                                <th>Status</th>
                                <th>Ativos</th>
                                <th>Esperado</th>
                                <th>Estados</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringWorkersTable">
                            <tr><td colspan="6" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Throughput de fila</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fila</th>
                                <th>Processados (15m)</th>
                                <th>Por min</th>
                                <th>Pendentes</th>
                                <th>Falhas 15m</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringThroughputTable">
                            <tr><td colspan="5" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Redis + Storage</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Componente</th>
                                <th>Status</th>
                                <th>Métrica</th>
                                <th>Detalhe</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringStorageRedisTable">
                            <tr><td colspan="4" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Delay e retries por fila</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fila</th>
                                <th>Pendentes</th>
                                <th>Espera média</th>
                                <th>Max tentativas</th>
                                <th>Em retry</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringQueueDelayTable">
                            <tr><td colspan="5" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Filas (jobs pendentes e falhas)</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fila</th>
                                <th>Pendentes</th>
                                <th>Falhas 15m</th>
                                <th>Falhas 24h</th>
                                <th>Ação recomendada</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringQueueTable">
                            <tr><td colspan="5" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Importações em andamento</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Arquivo</th>
                                <th>Status</th>
                                <th>Progresso</th>
                                <th>Atualizado</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringRunningImportsTable">
                            <tr><td colspan="5" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Importações possivelmente travadas (+5 min)</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Arquivo</th>
                                <th>Status</th>
                                <th>Progresso</th>
                                <th>Atualizado</th>
                            </tr>
                        </thead>
                        <tbody id="monitoringStalledImportsTable">
                            <tr><td colspan="5" class="text-muted text-center py-3">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>Histórico de incidentes</span>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <select class="form-select form-select-sm" id="monitoringIncidentActionFilter" style="min-width: 170px;">
                    <option value="">Ação: todas</option>
                    <option value="queue_restart_all">queue_restart_all</option>
                    <option value="queue_recover">queue_recover</option>
                    <option value="incident_ack">incident_ack</option>
                </select>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    id="monitoringIncidentQueueFilter"
                    placeholder="Fila (ex.: imports)"
                    style="min-width: 170px;"
                >
                <select class="form-select form-select-sm" id="monitoringIncidentOutcomeFilter" style="min-width: 150px;">
                    <option value="">Resultado: todos</option>
                    <option value="ok">ok</option>
                    <option value="fallback">fallback</option>
                    <option value="blocked">blocked</option>
                    <option value="error">error</option>
                </select>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="monitoringIncidentApplyFilterBtn">Aplicar</button>
                <a href="{{ route('admin.monitoring.incidentsExport') }}" class="btn btn-outline-primary btn-sm" id="monitoringIncidentExportBtn">Exportar CSV</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Quando</th>
                        <th>Ação</th>
                        <th>Fila</th>
                        <th>Resultado</th>
                        <th>ACK</th>
                        <th>Ator</th>
                        <th>Mensagem</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="monitoringIncidentTable">
                    <tr><td colspan="9" class="text-muted text-center py-3">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <small class="text-muted" id="monitoringIncidentPageInfo">Página 1 de 1</small>
            <div class="btn-group btn-group-sm" role="group" aria-label="Paginação de incidentes">
                <button type="button" class="btn btn-outline-secondary" id="monitoringIncidentPrevBtn">Anterior</button>
                <button type="button" class="btn btn-outline-secondary" id="monitoringIncidentNextBtn">Próxima</button>
            </div>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="monitoringToastAlert" class="toast border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Monitoramento</strong>
            <small>agora</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Fechar"></button>
        </div>
        <div class="toast-body" id="monitoringToastBody">Alerta operacional detectado.</div>
    </div>
</div>

<div class="modal fade" id="monitoringRecoverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmação de recuperação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Você está prestes a recuperar a fila <strong id="monitoringRecoverQueueName">—</strong>.</p>
                <p class="small text-muted mb-2">Digite exatamente o texto abaixo para confirmar:</p>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <code id="monitoringRecoverExpectedText">RECUPERAR IMPORTS</code>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="monitoringRecoverCopyBtn">Copiar</button>
                </div>
                <input
                    type="text"
                    class="form-control"
                    id="monitoringRecoverInput"
                    placeholder="Digite a confirmação"
                    autocomplete="off"
                >
                <div class="form-text">Ação operacional sensível.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="monitoringRecoverConfirmBtn" disabled>Confirmar recuperação</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="monitoringAckModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reconhecer incidente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Incidente <strong id="monitoringAckIncidentLabel">#—</strong></p>
                <label for="monitoringAckComment" class="form-label">Comentário operacional (opcional)</label>
                <textarea
                    id="monitoringAckComment"
                    class="form-control"
                    rows="3"
                    maxlength="255"
                    placeholder="Ex.: Ação aplicada e monitoramento estabilizado."
                ></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="monitoringAckConfirmBtn">Confirmar ACK</button>
            </div>
        </div>
    </div>
</div>
@endsection
