@php
    $embedded = (bool) ($embedded ?? false);
@endphp
<div class="{{ $embedded ? 'dq-embedded dq-embedded-premium' : 'container py-4' }}" id="dataQualityPage" data-dq-embedded="{{ $embedded ? '1' : '0' }}">
    @unless($embedded)
        <div class="dq-hero mb-4">
            <div class="dq-hero-copy">
                <h2 class="dq-title mb-1">Qualidade de Dados</h2>
                <div class="dq-subtitle">
                    Padronize e normalize colunas com versionamento seguro.
                </div>
                <div class="dq-hero-tags mt-2">
                    <span class="dq-chip">Original preservado</span>
                    <span class="dq-chip">Atual editável</span>
                    <span class="dq-chip">Retenção curta</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 dq-hero-actions">
                <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary">Voltar ao Explore</a>
                <span class="badge text-bg-light border dq-badge-highlight">Prévia + lote</span>
            </div>
        </div>
    @endunless

    @if(session('dq_job'))
        <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2 dq-job-alert">
            <div>
                Importação iniciada para o arquivo: <strong>{{ session('dq_job.name') }}</strong> (ID {{ session('dq_job.id') }}).
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-success" href="{{ route('vault.explore.selectSource', session('dq_job.id')) }}">
                    Abrir no Explore
                </a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('vault.sources.index') }}">
                    Abrir Arquivos
                </a>
            </div>
        </div>
    @endif

    <div class="card mb-4 dq-panel dq-panel--controls">
        <div class="card-body">
            @if($embedded && $sourceId && $currentSource)
                <div class="dq-current-source mb-3">
                    <div class="dq-current-source-label">Arquivo ativo</div>
                    <div class="dq-current-source-value">{{ $currentSource->original_name }} <span>#{{ $currentSource->id }}</span></div>
                </div>
            @endif

            @if($embedded && !$sourceId)
                <div class="alert alert-warning py-2 px-3 mb-3">
                    Nenhum arquivo ativo selecionado.
                </div>
            @endif

            <div class="row g-3 dq-top-row">
                @if($embedded)
                    <select id="dqSourceSelect" class="d-none" data-base-url="{{ route('explore.dataQuality.modal') }}">
                        @if($sourceId && $currentSource)
                            <option value="{{ $sourceId }}" selected>{{ $currentSource->original_name }}</option>
                        @else
                            <option value="" selected>Sem arquivo</option>
                        @endif
                    </select>
                @else
                    <div class="col-lg-5 dq-top-col dq-top-col-source">
                        <label class="form-label">Arquivo</label>
                        <div class="d-flex gap-2 dq-source-controls">
                            <select id="dqSourceSelect" class="form-select" data-base-url="{{ route('explore.dataQuality.index') }}">
                                <option value="">Selecione um arquivo</option>
                                @foreach($sources as $source)
                                    <option value="{{ $source->id }}" data-kind="current" @selected($sourceId == $source->id)>
                                        {{ $source->display_name ?? $source->original_name }} (ID {{ $source->id }}) • atual
                                    </option>
                                @endforeach
                            </select>
                            <a id="dqClearBtn" href="{{ route('explore.dataQuality.index') }}" class="btn btn-outline-secondary">Limpar</a>
                        </div>
                        <div class="form-text">
                            @if($sourceId && $rootSourceId)
                                Cadeia do original ID {{ $rootSourceId }}: edição no arquivo atual (mantém atual + última).
                            @else
                                Selecione o arquivo atual da cadeia para aplicar regras.
                            @endif
                        </div>
                    </div>
                @endif

                <div class="{{ $embedded ? 'col-lg-6' : 'col-lg-3' }} dq-top-col">
                    <label class="form-label">Coluna</label>
                    <select id="dqColumnSelect" class="form-select" @if(!$sourceId) disabled @endif>
                        <option value="">Selecione uma coluna</option>
                        @foreach($columns as $col)
                            <option value="{{ $col['key'] }}">
                                {{ $col['label'] }} ({{ $col['type'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="{{ $embedded ? 'col-lg-6' : 'col-lg-4' }} dq-top-col dq-top-col-actions">
                    <label class="form-label">Ações</label>
                    <div class="d-flex gap-2 dq-action-controls">
                        <button id="dqPreviewBtn" class="btn btn-primary" data-preview-url="{{ route('explore.dataQuality.preview') }}" @if(!$sourceId) disabled @endif>Gerar prévia</button>
                        <button id="dqApplyBtn" class="btn btn-success" @if(!$sourceId) disabled @endif>Aplicar em lote</button>
                    </div>
                </div>
            </div>

            <div class="dq-rules mt-4">
                <div class="fw-semibold mb-2">Regras de transformação</div>
                <div class="row g-2">
                    @php
                        $rules = [
                            ['key' => 'trim', 'label' => 'Trim (remover espaços)'],
                            ['key' => 'upper', 'label' => 'Tudo MAIÚSCULO'],
                            ['key' => 'lower', 'label' => 'Tudo minúsculo'],
                            ['key' => 'title', 'label' => 'Title Case'],
                            ['key' => 'remove_accents', 'label' => 'Remover acentos'],
                            ['key' => 'digits_only', 'label' => 'Somente números'],
                            ['key' => 'date_iso', 'label' => 'Data → YYYY-MM-DD'],
                            ['key' => 'null_if_empty', 'label' => 'Converter vazio em null'],
                        ];
                    @endphp
                    @foreach($rules as $rule)
                        <div class="col-md-3 col-sm-6">
                            <label class="dq-rule-item">
                                <input class="form-check-input" type="checkbox" value="{{ $rule['key'] }}" id="rule-{{ $rule['key'] }}" name="rules[]" @if(!$sourceId) disabled @endif>
                                <span>{{ $rule['label'] }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if($sourceId)
        <div class="card mb-4 dq-panel" id="dqDerivedCard"
             data-status-url="{{ route('explore.dataQuality.statuses') }}"
             data-source-id="{{ $sourceId }}">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Versões editadas recentes</span>
                <span class="text-muted small">{{ $derivedSources->count() }} registros</span>
            </div>
            <div class="card-body">
                @if($derivedSources->isEmpty())
                    <div class="text-muted">Nenhuma versão editada ainda.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle dq-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Status</th>
                                    <th>Criada em</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($derivedSources as $derived)
                                    <tr data-dq-derived-id="{{ $derived->id }}">
                                        <td>{{ $derived->id }}</td>
                                        <td>
                                            <a href="{{ route('vault.explore.selectSource', $derived->id) }}"
                                               class="link-primary link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">
                                                {{ $derived->original_name }}
                                            </a>
                                            @if($loop->first)
                                                <span class="badge text-bg-primary ms-2">Atual</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $statusClass = match($derived->status) {
                                                    'queued' => 'dq-status dq-status-queued',
                                                    'importing' => 'dq-status dq-status-importing',
                                                    'normalizing' => 'dq-status dq-status-importing',
                                                    'done' => 'dq-status dq-status-done',
                                                    'failed' => 'dq-status dq-status-failed',
                                                    'cancelled' => 'dq-status dq-status-cancelled',
                                                    default => 'dq-status dq-status-default',
                                                };
                                            @endphp
                                            <span class="{{ $statusClass }}"
                                                  data-dq-status-badge
                                                  data-dq-status="{{ $derived->status }}">{{ $derived->status }}</span>
                                        </td>
                                        <td>{{ $derived->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <button class="btn btn-sm btn-outline-primary" type="button"
                                                        data-dq-log
                                                        data-dq-id="{{ $derived->id }}"
                                                        data-dq-name="{{ $derived->original_name }}"
                                                        data-dq-status="{{ $derived->status }}"
                                                        data-dq-created-at="{{ $derived->created_at?->format('d/m/Y H:i') }}"
                                                        data-dq-parent-id="{{ $derived->parent_source_id }}"
                                                        data-dq-file-path="{{ $derived->file_path }}"
                                                        data-dq-log-json="{{ e(json_encode($derived->derived_from ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}">
                                                    Log
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" type="button"
                                                        data-dq-discard
                                                        data-dq-id="{{ $derived->id }}"
                                                        data-dq-name="{{ $derived->original_name }}">
                                                    Descartar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="card dq-panel">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="fw-semibold">Prévia (20 linhas)</span>
            <span class="text-muted small" id="dqPreviewStatus">Nenhuma prévia gerada.</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle dq-table">
                    <thead>
                        <tr>
                            <th style="width:80px">ID</th>
                            <th>Antes</th>
                            <th>Depois</th>
                        </tr>
                    </thead>
                    <tbody id="dqPreviewBody">
                        <tr>
                            <td colspan="3" class="text-muted">Selecione o arquivo, a coluna e as regras.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('explore.dataQuality.apply') }}" id="dqApplyForm" class="d-none">
    @csrf
    <input type="hidden" name="source_id" id="dqApplySource">
    <input type="hidden" name="column_key" id="dqApplyColumn">
</form>

<form method="POST" action="" id="dqDiscardForm" class="d-none" data-base-url="{{ url('/explore/data-quality/discard') }}">
    @csrf
</form>

<div class="modal fade" id="dqApplyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Aplicar regras em lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small">
                    Uma nova versão editada será gerada para este arquivo.
                    O original permanece preservado.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" type="button" id="dqApplyConfirm">Aplicar em lote</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqDiscardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Descartar versão editada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small" id="dqDiscardText">
                    Esta ação remove a versão editada e os dados dela.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger" type="button" id="dqDiscardConfirm">Descartar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content dq-log-modal">
            <div class="modal-header">
                <h5 class="modal-title">Log da versão editada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="dq-log-head">
                    <div class="dq-log-name" id="dqLogName">-</div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="dq-status dq-status-default" id="dqLogStatus">-</span>
                        <span class="text-muted small" id="dqLogCreatedAt">-</span>
                    </div>
                </div>
                <div class="dq-log-grid mt-3">
                    <div class="dq-log-item">
                        <span class="dq-log-label">Arquivo origem</span>
                        <span class="dq-log-value" id="dqLogSourceId">-</span>
                    </div>
                    <div class="dq-log-item">
                        <span class="dq-log-label">Coluna</span>
                        <span class="dq-log-value" id="dqLogColumn">-</span>
                    </div>
                    <div class="dq-log-item">
                        <span class="dq-log-label">Regras</span>
                        <span class="dq-log-value" id="dqLogRules">-</span>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="dq-log-label mb-1">Arquivo gerado</div>
                    <div class="dq-log-path" id="dqLogPath">-</div>
                </div>
                <div class="mt-3 d-none" id="dqLogChangesSection">
                    <div class="dq-log-label mb-1">Amostra de alterações (antes/depois)</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle dq-table dq-log-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Registro ID</th>
                                    <th style="width: 140px;">Coluna</th>
                                    <th>Antes</th>
                                    <th>Depois</th>
                                </tr>
                            </thead>
                            <tbody id="dqLogChangesBody">
                                <tr><td colspan="4" class="text-muted">Sem detalhes de alterações.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="dq-log-empty mt-3 d-none" id="dqLogEmpty">
                    Log indisponível para esta versão.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
