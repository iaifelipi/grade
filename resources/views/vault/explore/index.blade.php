@extends('layouts.app')

@section('page-title','Explore Registros')

@section('topbar-tools')
    @include('partials.universal-toolbar', ['inTopbar' => true, 'isGuest' => $isGuest ?? false])
@endsection

@section('content')

<div class="vault-explore">
    <div id="exploreLockedOverlay" class="explore-locked-overlay d-none">
        <div class="explore-locked-card">
            <div class="explore-locked-title">Importe um arquivo para continuar</div>
            <div class="explore-locked-text">A tela será liberada automaticamente após o primeiro arquivo começar a ser processado.</div>
        </div>
    </div>

	    <div class="explore-selection-bar d-none" id="selectionBar">
	        <div class="explore-selection-info">
	            <span id="selectionCount">0</span> selecionados
	        </div>
	        <div class="explore-selection-actions">
	            <button class="btn btn-outline-secondary btn-sm" id="selectionSelectAllBtn" type="button">
	                Selecionar todos
	            </button>
	            <button class="btn btn-outline-danger btn-sm" id="selectionClearBtn" type="button">
	                Remover seleção
	            </button>
	        </div>
	    </div>


    {{-- ======================================================
       GRID PRO • VIRTUAL SCROLL
    ======================================================= --}}
    <div class="explore-table-wrap">
        <div class="explore-loading d-none" id="exploreLoading" role="status" aria-live="polite" aria-hidden="true">
            <div class="explore-loading-card">
                <div class="spinner-border text-primary" role="status"></div>
                <span>Carregando registros...</span>
            </div>
        </div>

        <table class="explore-table">

            {{-- HEADER STICKY --}}
            <thead>
<tr id="leadsHeaderRow">
    <th data-col="select" style="width:42px" scope="col">
        <input type="checkbox" id="checkAll" aria-label="Selecionar todos">
    </th>

    <th data-col="nome" scope="col">Nome</th>
    <th data-col="cpf" scope="col">CPF</th>
    <th data-col="email" scope="col">E-mail</th>
    <th data-col="phone" scope="col">Telefone</th>
    <th data-col="data_nascimento" scope="col">Data de nascimento</th>
    <th data-col="sex" style="width:70px;text-align:center" scope="col">Sexo</th>
    <th data-col="score" style="width:90px" scope="col">Score</th>
</tr>
</thead>

            {{-- VIRTUAL BODY --}}
            <tbody id="leadsBody"></tbody>

        </table>


        {{-- EMPTY STATE --}}
        <div id="emptyState" class="empty-state d-none" role="status" aria-live="polite">
            <div class="empty-state-title" id="emptyStateTitle">Nenhum registro encontrado</div>
            <div class="empty-state-meta" id="emptyStateMeta"></div>
            <div class="empty-state-meta" id="emptyStateFiltersSummary"></div>
            <div class="empty-state-actions">
                <button id="emptyStateClearBtn" class="btn btn-outline-secondary" type="button">
                    Limpar filtros
                </button>
                <button id="emptyStateSampleBtn" class="btn btn-outline-secondary" type="button">
                    Ver exemplo
                </button>
                @if((auth()->check() && auth()->user()?->hasPermission('leads.import')) || ($isGuest ?? false))
                    <button
                        id="emptyStateImportBtn"
                        class="btn btn-dark"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#exploreImportModal"
                    >
                        Importar arquivo
                    </button>
                @endif
            </div>
        </div>

    </div>

    <div class="explore-bottom-meta">
        <div class="explore-semantic-controls d-none" id="semanticTopSummary">
            <div class="explore-semantic-head">
                <span class="explore-layout-label">ID SEMÂNTICA:</span>
            </div>
            <div class="explore-semantic-pills" id="semanticTopHoverPills"></div>
            <span class="semantic-pill semantic-pill--anchor d-none" id="semanticTopAnchor">não definida</span>
        </div>

        <div class="explore-toolbar-meta explore-toolbar-meta--table">
            <div class="explore-layout-controls explore-layout-controls--topbar">
                <div class="explore-layout-head">
                    <span class="explore-layout-label">Visualização</span>
                    <select id="layoutPresetSelect" class="form-select form-select-sm">
                        <option value="">-------- Selecionar --------</option>
                    </select>
                </div>
                <div class="explore-layout-stats">
                    <span class="small text-muted">
                        <b id="foundCount">0</b> filtrados
                    </span>
                    <span class="small text-muted">
                        <b id="selectedCount">0</b> selecionados
                    </span>
                    <span class="small text-muted">
                        <b id="counter">0</b> total
                    </span>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="explore-toast" id="exploreToast" role="status" aria-live="polite"></div>

@include('vault.explore.columns-modal')
@include('vault.explore.semantic-modal')

@if($isGuest ?? false)
    <div class="modal fade" id="guestWelcomeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered grade-modal-pattern-dialog">
            <div class="modal-content grade-modal-pattern guest-welcome-modal">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="guest-welcome-kicker">Bem-vindo ao Grade</div>
                        <h5 class="modal-title">Conecte dados, automatize decisões e dispare ações inteligentes</h5>
                    </div>
                </div>
                <div class="modal-body pt-2">
                    <p class="guest-standard-subtitle">Transforme arquivos em inteligência prática com semântica, inferência e ações em poucos cliques.</p>
                    <div class="guest-standard-grid">
                        <div class="guest-standard-item"><i class="bi bi-diagram-3"></i>Conexão de dados com semântica viva</div>
                        <div class="guest-standard-item"><i class="bi bi-cpu"></i>Inferência para descobrir padrões ocultos</div>
                        <div class="guest-standard-item"><i class="bi bi-lightning-charge"></i>Automação de decisões e disparos</div>
                        <div class="guest-standard-item"><i class="bi bi-stars"></i>Exploração rápida com filtros e visualizações</div>
                    </div>
                    <div class="guest-standard-tip">
                        <div class="guest-standard-tip-title">Comece em 3 passos</div>
                        <ol>
                            <li>Importe um arquivo para montar sua base.</li>
                            <li>Ative filtros e colunas para explorar rápido.</li>
                            <li>Use semântica, inferência e ações para decidir.</li>
                        </ol>
                    </div>
                </div>
                <div class="modal-footer border-0 guest-welcome-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Explorar agora</button>
                    <button type="button" class="btn btn-dark" data-guest-import>Importar arquivo</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="guestGoodbyeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 guest-goodbye-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Volte em breve</h5>
                </div>
                <div class="modal-body pt-0">
                    <p class="guest-goodbye-subtitle">Escolha uma conta para continuar.</p>

                    <button type="button" class="guest-goodbye-account" data-guest-auth="login">
                        <span class="guest-goodbye-account-avatar">G</span>
                        <span class="guest-goodbye-account-text">
                            <strong>Entrar no Grade</strong>
                            <small>Acesse sua conta e continue de onde parou</small>
                        </span>
                    </button>

                    <div class="guest-goodbye-or"><span>ou</span></div>

                    <div class="guest-goodbye-actions">
                        <button type="button" class="btn btn-outline-light" data-guest-auth="login">Entrar em outra conta</button>
                        <button type="button" class="btn btn-outline-light" data-guest-auth="register">Criar conta</button>
                    </div>
                    <button type="button" class="guest-goodbye-stay-link" data-bs-dismiss="modal">Permanecer desconectado</button>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="actionsManualWizardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern actions-wizard-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="actions-wizard-kicker">Ações manuais</div>
                    <h5 class="modal-title">Dispare ações sob demanda com total controle</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="actions-wizard-steps">
                    <div><span>1</span>Selecione os registros e filtros</div>
                    <div><span>2</span>Escolha a ação manual</div>
                    <div><span>3</span>Revise e confirme o disparo</div>
                </div>
                <div class="actions-wizard-hint">
                    Ideal para campanhas pontuais e operações com validação humana.
                </div>
            </div>
            <div class="modal-footer border-0 actions-wizard-actions">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Depois</button>
                <button type="button" class="btn btn-dark">Iniciar wizard</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionsAutoWizardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern actions-wizard-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="actions-wizard-kicker">Ações automatizadas</div>
                    <h5 class="modal-title">Crie fluxos inteligentes que disparam no momento certo</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="actions-wizard-steps">
                    <div><span>1</span>Defina gatilhos e condições</div>
                    <div><span>2</span>Escolha os canais e ações</div>
                    <div><span>3</span>Ative o fluxo e monitore resultados</div>
                </div>
                <div class="actions-wizard-hint">
                    Perfeito para operações contínuas e decisões em tempo real.
                </div>
            </div>
            <div class="modal-footer border-0 actions-wizard-actions">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Depois</button>
                <button type="button" class="btn btn-dark">Iniciar wizard</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreDataQualityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered modal-dialog-scrollable grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern explore-dq-modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Qualidade de Dados</h5>
            </div>
            <div class="modal-body p-0">
                <div id="exploreDataQualityModalBody" class="explore-dq-modal-body">
                    <div class="explore-dq-modal-loading">
                        <div class="spinner-border text-primary" role="status"></div>
                        <span>Carregando qualidade de dados...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreColumnsAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered modal-dialog-scrollable explore-columns-modal-dialog grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern explore-dq-modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Catálogo de Colunas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="exploreColumnsAdminModalBody" class="explore-dq-modal-body">
                    <div class="explore-dq-modal-loading">
                        <div class="spinner-border text-primary" role="status"></div>
                        <span>Carregando catálogo de colunas...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable grade-modal-pattern-dialog grade-modal-search-dialog">
        <div class="modal-content grade-modal-pattern grade-modal-search">
            <div class="modal-header">
                <h5 class="modal-title">Buscar registros</h5>
            </div>
            <div class="modal-body">
                <label for="exploreSearchModalInput" class="grade-field-box">
                    <span class="grade-field-kicker">Nome, e-mail, CPF ou telefone</span>
                    <input
                        id="exploreSearchModalInput"
                        type="text"
                        class="grade-field-input"
                        autocomplete="off"
                        placeholder="Ex: maria, 11987654321, 123.456.789-00"
                    >
                </label>
                <div class="row g-2 mt-2" id="exploreSearchAdvancedFields">
                    <div class="col-md-4">
                        <label for="exploreSearchSegmentSelect" class="grade-field-box grade-field-box--compact">
                            <span class="grade-field-kicker">Segmento</span>
                            <select id="exploreSearchSegmentSelect" class="grade-field-input grade-field-input--select">
                                <option value="">Todos</option>
                            </select>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label for="exploreSearchNicheSelect" class="grade-field-box grade-field-box--compact">
                            <span class="grade-field-kicker">Nicho</span>
                            <select id="exploreSearchNicheSelect" class="grade-field-input grade-field-input--select">
                                <option value="">Todos</option>
                            </select>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label for="exploreSearchOriginSelect" class="grade-field-box grade-field-box--compact">
                            <span class="grade-field-kicker">Origem</span>
                            <select id="exploreSearchOriginSelect" class="grade-field-input grade-field-input--select">
                                <option value="">Todas</option>
                            </select>
                        </label>
                    </div>
                    <div class="col-md-7">
                        <label for="exploreSearchCitiesInput" class="grade-field-box grade-field-box--compact">
                            <span class="grade-field-kicker">Cidades (vírgula)</span>
                            <input id="exploreSearchCitiesInput" type="text" class="grade-field-input" placeholder="Ex: São Paulo, Campinas">
                        </label>
                    </div>
                    <div class="col-md-5">
                        <label for="exploreSearchStatesInput" class="grade-field-box grade-field-box--compact">
                            <span class="grade-field-kicker">UFs (vírgula)</span>
                            <input id="exploreSearchStatesInput" type="text" class="grade-field-input" placeholder="Ex: SP, RJ">
                        </label>
                    </div>
                </div>
                <small class="text-muted d-block mt-2 grade-modal-hint">
                    Dica inteligente: use <code>cpf:123</code>, <code>tel:1199</code>, <code>cidade:Campinas</code>, <code>uf:SP</code>, <code>score:80+</code>.
                </small>
                <small class="text-muted d-block grade-modal-hint">Múltiplos termos funcionam em conjunto (AND).</small>
                <small class="text-muted d-block grade-modal-hint">Atalho: <code>/</code> para abrir busca.</small>

                <div class="explore-search-preview" id="exploreSearchPreviewWrap">
                    <div class="explore-search-preview-header">
                        <div class="explore-search-preview-title">Prévia em tempo real</div>
                        <div class="explore-search-preview-header-right">
                            <div class="explore-search-preview-count" id="exploreSearchPreviewCount">0 registros</div>
                            <div class="explore-search-preview-selected" id="exploreSearchPreviewSelected">Selecionados: 0</div>
                        </div>
                    </div>
                    <div class="explore-search-preview-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exploreSearchPreviewSelectPageBtn">Selecionar prévia</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exploreSearchPreviewSelectAllBtn">Selecionar todos encontrados</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exploreSearchPreviewClearSelectionBtn">Limpar seleção</button>
                    </div>
                    <div class="explore-search-preview-body" id="exploreSearchPreviewBody">
                        <div class="explore-search-preview-empty" id="exploreSearchPreviewEmpty">
                            Ajuste os filtros para ver a prévia.
                        </div>
                    </div>
                    <div class="explore-search-preview-loading d-none" id="exploreSearchPreviewLoading">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span>Atualizando prévia...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="exploreSearchClearBtn" type="button" class="btn btn-outline-secondary">Cancelar</button>
                <button id="exploreSearchApplyBtn" type="button" class="btn btn-dark">Buscar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern explore-import-modal-content">
            <form id="exploreImportForm" action="{{ route('vault.sources.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="exploreImportModalTitle">Importar arquivos</h5>
                    <span class="badge text-bg-light border" id="exploreImportModalModeBadge">Envio rápido</span>
                </div>
                <div class="modal-body explore-sources-modal-body">
                    <div id="exploreImportUploadWrap">
                        <div id="exploreImportDropzone" class="border rounded-3 p-3 text-center bg-light mb-3" style="cursor:pointer">
                            <input id="exploreImportInput" type="file" name="files[]" multiple class="form-control mb-2" accept=".csv,.xlsx,.txt">
                            <small class="text-muted d-block">Arraste e solte aqui ou clique para escolher</small>
                            <small class="text-muted">CSV, XLSX e TXT (múltiplos arquivos)</small>
                        </div>

                        <div id="exploreImportSelectedList" class="mb-3 d-none">
                            <small class="text-muted d-block mb-2">Arquivos selecionados</small>
                            <ul class="list-group list-group-flush"></ul>
                        </div>

                        <div id="exploreImportInvalidAlert" class="alert alert-warning py-2 d-none">
                            Alguns arquivos foram ignorados por extensão inválida.
                        </div>

                        <div class="mb-3">
                            <div class="progress" style="height:8px">
                                <div id="exploreImportBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                            </div>
                        </div>
                    </div>

                    <div id="exploreImportEventWrap" class="border rounded-3 p-2">
                        <div id="exploreQueueHealthAlert" class="alert alert-warning py-2 px-3 small d-none mb-2" role="status" aria-live="polite"></div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-semibold">Arquivos enviados neste evento</div>
                            <small id="exploreImportAutoCloseHint" class="text-muted d-none"></small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Arquivo</th>
                                        <th style="width:120px">Status</th>
                                        <th style="width:180px">Progresso</th>
                                        <th style="width:90px">Inseridos</th>
                                    </tr>
                                </thead>
                                <tbody id="exploreImportEventBody">
                                    <tr><td colspan="4" class="text-muted">Nenhum envio iniciado.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="exploreSourcesPanelWrap" class="border rounded-3 p-2 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-semibold">Todos os arquivos importados</div>
                            <div class="d-flex align-items-center gap-2">
                                <button id="exploreSourcesRefreshBtn" type="button" class="btn btn-sm btn-outline-secondary">
                                    Atualizar
                                </button>
                                <button id="exploreSourcesPurgeBtn" type="button" class="btn btn-sm btn-outline-danger" disabled>
                                    Excluir selecionados
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:36px">
                                            <input type="checkbox" id="exploreSourcesCheckAll">
                                        </th>
                                        <th style="width:70px">#</th>
                                        <th>Arquivo</th>
                                        <th style="width:120px">Status</th>
                                        <th style="width:180px">Progresso</th>
                                        <th style="width:90px">Inseridos</th>
                                        <th style="width:170px">Criado em</th>
                                        <th style="width:160px">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="exploreImportStatusBody">
                                    <tr><td colspan="8" class="text-muted">Nenhum arquivo importado.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button id="exploreImportSubmitBtn" type="submit" class="btn btn-primary">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreSourcesPurgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered explore-purge-dialog">
        <div class="modal-content grade-modal-pattern explore-purge-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger">Excluir arquivos selecionados</h5>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-2">Essa ação remove os arquivos selecionados e dados relacionados.</p>
                <div class="alert alert-warning py-2 mb-2">Ação irreversível.</div>
                <label for="exploreSourcesPurgeInput" class="grade-field-box grade-field-box--compact mb-0">
                    <span class="grade-field-kicker">Digite EXCLUIR para confirmar</span>
                    <input id="exploreSourcesPurgeInput" type="text" class="grade-field-input" placeholder="EXCLUIR">
                </label>
                <div id="exploreSourcesPurgeProgressWrap" class="mt-3 d-none">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted" id="exploreSourcesPurgeProgressText">Excluindo...</small>
                        <small class="text-muted" id="exploreSourcesPurgeProgressCount">0/0</small>
                    </div>
                    <div class="progress" style="height:8px">
                        <div id="exploreSourcesPurgeProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button id="exploreSourcesPurgeConfirmBtn" type="button" class="btn btn-danger">Excluir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreLeaveConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Alterações pendentes</h5>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-2">Você tem alterações pendentes neste arquivo.</p>
                <p class="mb-2 text-muted">Ao salvar, será gerado um novo arquivo derivado com essas alterações.</p>
                <p class="mb-0 text-muted" id="exploreLeaveConfirmCount">0 alteração(ões) pendente(s).</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" id="exploreLeaveKeepPendingBtn" data-bs-dismiss="modal">Sair sem salvar</button>
                <button type="button" class="btn btn-outline-primary" id="exploreLeaveCancelBtn" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="exploreLeavePublishBtn">Publicar novo arquivo e sair</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="overridesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Alterações pendentes</h5>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Registro ID</th>
                                <th>Coluna</th>
                                <th>Valor</th>
                                <th>Atualizado em</th>
                            </tr>
                        </thead>
                        <tbody id="overridesModalBody">
                            <tr><td colspan="4" class="text-muted">Nenhuma alteração pendente.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="layoutManageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered layout-manage-dialog">
        <div class="modal-content grade-modal-pattern layout-manage-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Gerenciar visualização</h5>
            </div>
            <div class="modal-body pt-2 layout-manage-modal-body">
                <div class="layout-manage-card layout-manage-card--create mb-3" id="layoutManageCreateSection">
                    <div class="layout-manage-card-title">Salvar nova visualização</div>
                    <div class="layout-manage-card-text">Cria uma visualização com as colunas e ordem atuais da tela.</div>
                    <div class="d-flex gap-2 mt-2 layout-manage-create-row">
                        <label class="grade-field-box grade-field-box--compact flex-grow-1 mb-0">
                            <span class="grade-field-kicker">Nome da visualização</span>
                            <input
                                id="layoutManageCreateInput"
                                type="text"
                                class="grade-field-input"
                                maxlength="80"
                                placeholder="Ex: Comercial compacto"
                            >
                        </label>
                    </div>
                    <div class="layout-manage-inline-error" id="layoutManageCreateError"></div>
                </div>

                <div id="layoutManageExistingSection">
                    <div class="layout-manage-current mb-3">
                        <div>
                            <span class="layout-manage-label">Selecionada</span>
                            <span class="layout-manage-name" id="layoutManageCurrentName">-</span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm layout-manage-actions-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button class="dropdown-item" type="button" id="layoutManageActionRename">Renomear</button></li>
                                <li><button class="dropdown-item" type="button" id="layoutManageActionUpdate">Atualizar</button></li>
                                <li><button class="dropdown-item text-danger" type="button" id="layoutManageActionDelete">Excluir</button></li>
                            </ul>
                        </div>
                    </div>

                    <div class="layout-manage-rename-wrap mb-3 d-none" id="layoutManageRenameSection">
                        <label for="layoutManageNameInput" class="form-label">Renomear visualização</label>
                        <div class="d-block">
                            <div class="grade-field-box grade-field-box--compact flex-grow-1 mb-0 layout-manage-rename-box">
                                <span class="grade-field-kicker">Novo nome</span>
                                <input
                                    id="layoutManageNameInput"
                                    type="text"
                                    class="grade-field-input"
                                    maxlength="80"
                                    placeholder="Novo nome"
                                >
                                <div class="layout-manage-inline-actions">
                                <button class="btn btn-dark" type="button" id="layoutManageRenameBtn">Renomear</button>
                                </div>
                            </div>
                        </div>
                        <div class="layout-manage-inline-error" id="layoutManageRenameError"></div>
                    </div>

                    <div class="layout-manage-card mb-3 d-none" id="layoutManageUpdateSection">
                        <div class="layout-manage-card-title">Atualizar visualização</div>
                        <div class="layout-manage-card-text">Substitui esta visualização pelas colunas e ordem atuais da tela.</div>
                        <div class="layout-manage-inline-actions">
                            <button class="btn btn-outline-secondary mt-2" type="button" id="layoutManageUpdateBtn">Atualizar com a tela atual</button>
                        </div>
                    </div>

                    <div class="layout-manage-card layout-manage-card--danger d-none" id="layoutManageDeleteSection">
                        <div class="layout-manage-card-title">Excluir visualização</div>
                        <div class="layout-manage-card-text">Remove permanentemente esta visualização salva para este arquivo.</div>
                        <div class="layout-manage-inline-actions">
                            <button class="btn btn-outline-danger mt-2" type="button" id="layoutManageDeleteBtn">Excluir visualização</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Fechar</button>
                <button class="btn btn-dark" type="button" id="layoutManageCreateBtn">Salvar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="layoutDeleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Confirmar exclusão</h5>
            </div>
            <div class="modal-body pt-2">
                <div class="text-muted" id="layoutDeleteConfirmText">Excluir visualização?</div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                <button class="btn btn-danger" type="button" id="layoutDeleteConfirmBtn">Excluir</button>
            </div>
        </div>
    </div>
</div>



@auth
    @if(auth()->user()->hasPermission('automation.run'))
        <div class="modal fade" id="exploreMarketingWizardModal" tabindex="-1" aria-labelledby="exploreMarketingWizardModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable grade-modal-pattern-dialog">
                <div class="modal-content grade-modal-pattern actions-wizard-modal">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <h5 class="modal-title" id="exploreMarketingWizardModalLabel">Operações: Disparos</h5>
                            <div class="text-muted small">Wizard multi-canal (E-mail, SMS, Zap). Envio para os registros selecionados.</div>
                        </div>
                    </div>
                    <div class="modal-body pt-3">
                        <div class="alert alert-warning small">
                            Para disparar: <b>E-mail</b> precisa da coluna <code>email</code>. <b>SMS</b> e <b>Zap</b> precisam da coluna <code>telefone</code> (preferencialmente em formato E.164).
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="small text-muted">Passo <span id="opsWizardStepIndex">1</span>/<span id="opsWizardStepTotal">1</span></div>
                            <div class="small text-muted" id="opsWizardStepTitle">Canais</div>
                        </div>

                        <div class="alert alert-danger d-none" id="opsWizardError"></div>
                        <div class="alert alert-success d-none" id="opsWizardSuccess"></div>

                        <div data-ops-step="channels">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">Escolha os canais</div>
                                <div class="small text-muted">Selecionados: <b id="opsWizSelectedCountLive">0</b></div>
                            </div>
                            <div class="d-grid gap-2">
                                <label class="btn btn-outline-secondary text-start" for="opsWizEmailToggle">
                                    <input class="form-check-input me-2" type="checkbox" id="opsWizEmailToggle" data-ops-wiz-channel="email">
                                    E-mail Marketing
                                    <span class="small text-muted ms-2" data-ops-wiz-reason="email"></span>
                                </label>
                                <label class="btn btn-outline-secondary text-start" for="opsWizSmsToggle">
                                    <input class="form-check-input me-2" type="checkbox" id="opsWizSmsToggle" data-ops-wiz-channel="sms">
                                    SMS Marketing
                                    <span class="small text-muted ms-2" data-ops-wiz-reason="sms"></span>
                                </label>
                                <label class="btn btn-outline-secondary text-start" for="opsWizZapToggle">
                                    <input class="form-check-input me-2" type="checkbox" id="opsWizZapToggle" data-ops-wiz-channel="whatsapp">
                                    Zap Marketing
                                    <span class="small text-muted ms-2" data-ops-wiz-reason="whatsapp"></span>
                                </label>
                            </div>
                            <div class="text-muted small mt-2">Dica: você pode marcar 1, 2 ou 3 canais e disparar tudo junto.</div>
                        </div>

                        <div class="d-none" data-ops-step="email">
                            <div class="fw-semibold mb-2">E-mail Marketing</div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label mb-0">Conta de envio (remetente)</label>
                                    <span class="small text-muted">Dica: use variáveis: <code>{nome}</code> <code>{email}</code> <code>{telefone}</code></span>
                                </div>
                                <div class="row g-2 mt-1">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="opsWizEmailFromName" maxlength="120" placeholder="Nome do remetente (ex: Felipe)">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="email" class="form-control" id="opsWizEmailFromEmail" maxlength="190" placeholder="E-mail do remetente (ex: contato@empresa.com)">
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <input type="email" class="form-control" id="opsWizEmailReplyTo" maxlength="190" placeholder="Reply-to (opcional)">
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label mb-0">Assuntos (variações)</label>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-ops-wiz-add-variant="email_subject">Adicionar assunto</button>
                                    </div>
                                </div>
                                <div class="mt-2 d-grid gap-2" data-ops-wiz-variants="email_subject">
                                    <input type="text" class="form-control" maxlength="190" placeholder="Assunto #1 (ex: Oferta exclusiva para você)">
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <label class="form-label mb-0">Mensagens (variações)</label>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-ops-wiz-ai="email_message">Criar com IA</button>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-ops-wiz-add-variant="email_message">Adicionar mensagem</button>
                                    </div>
                                </div>
                                <div class="mt-2 d-grid gap-2" data-ops-wiz-variants="email_message">
                                    <textarea class="form-control" rows="6" maxlength="4000" placeholder="Mensagem #1. Use {nome}, {email}, {telefone}."></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <label class="form-label mb-0">Editor</label>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Formato do e-mail">
                                        <input type="radio" class="btn-check" name="opsWizEmailFormat" id="opsWizEmailFormatText" autocomplete="off" checked>
                                        <label class="btn btn-outline-secondary" for="opsWizEmailFormatText">Texto</label>
                                        <input type="radio" class="btn-check" name="opsWizEmailFormat" id="opsWizEmailFormatHtml" autocomplete="off">
                                        <label class="btn btn-outline-secondary" for="opsWizEmailFormatHtml">HTML</label>
                                    </div>
                                </div>
                                <div class="mt-2 d-none" id="opsWizEmailHtmlPreviewWrap">
                                    <div class="small text-muted mb-1">Prévia (sandbox)</div>
                                    <iframe
                                        id="opsWizEmailHtmlPreview"
                                        sandbox
                                        style="width:100%;height:220px;border:1px solid rgba(0,0,0,.1);border-radius:.75rem;background:#fff"
                                        title="Prévia HTML"
                                    ></iframe>
                                </div>
                            </div>
                        </div>

                        <div class="d-none" data-ops-step="sms">
                            <div class="fw-semibold mb-2">SMS Marketing</div>
                            <div class="mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label mb-0">Mensagens (variações)</label>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-ops-wiz-ai="sms_message">Criar com IA</button>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-ops-wiz-add-variant="sms_message">Adicionar mensagem</button>
                                    </div>
                                </div>
                                <div class="mt-2 d-grid gap-2" data-ops-wiz-variants="sms_message">
                                    <textarea class="form-control" rows="5" maxlength="800" placeholder="Mensagem #1 (curta). Use {nome}."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-none" data-ops-step="whatsapp">
                            <div class="fw-semibold mb-2">Zap Marketing</div>
                            <div class="mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label mb-0">Mensagens (variações)</label>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-ops-wiz-ai="whatsapp_message">Criar com IA</button>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-ops-wiz-add-variant="whatsapp_message">Adicionar mensagem</button>
                                    </div>
                                </div>
                                <div class="mt-2 d-grid gap-2" data-ops-wiz-variants="whatsapp_message">
                                    <textarea class="form-control" rows="6" maxlength="1200" placeholder="Mensagem #1. Use {nome}."></textarea>
                                </div>
                                <div class="text-muted small mt-1">Obs: o Zap usa o mesmo <code>telefone</code> do SMS.</div>
                            </div>
                        </div>

                        <div class="d-none" data-ops-step="review">
                            <div class="fw-semibold mb-2">Revisão</div>
                            <div class="text-muted small mb-2">Resumo do disparo antes de enfileirar.</div>
                            <div class="border rounded-3 p-3 bg-light">
                                <div class="small"><b>Selecionados:</b> <span id="opsWizSelectedCount">0</span></div>
                                <div class="small mt-1"><b>Canais:</b> <span id="opsWizChannelsLabel">—</span></div>
                                <div class="small mt-2 text-muted">Após disparar, você pode acompanhar em <a href="{{ route('vault.automation.index') }}">Operações</a>.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-secondary" id="opsWizardBackBtn">Voltar</button>
                        <button type="button" class="btn btn-dark" id="opsWizardNextBtn">Próximo</button>
                        <button type="button" class="btn btn-dark d-none" id="opsWizardDispatchBtn">Disparar</button>
                    </div>
                </div>
            </div>
	        </div>

	        <script>
	            window.exploreOpsWizardConfig = {
	                availabilityUrl: "{{ route('vault.explore.marketing.availability') }}",
	                dispatchUrl: "{{ route('vault.explore.marketing.dispatch') }}",
	            }
	        </script>
	    @endif
	@endauth


{{-- ======================================================
   CONFIG GLOBAL (somente dados)
====================================================== --}}
<script>
@php($effectiveActor = app()->bound('impersonated_user') ? app('impersonated_user') : auth()->user())
@php($isGlobalSuperReadOnly = auth()->check() && auth()->user()?->isSuperAdmin() && !session()->has('impersonate_user_id') && !session()->has('tenant_uuid_override'))
window.exploreConfig = {
    dbUrl: "{{ ($isGuest ?? false) ? route('guest.explore.db') : route('vault.explore.db') }}",
    sourcesListUrl: "{{ ($isGuest ?? false) ? route('guest.explore.sourcesList') : route('vault.explore.sourcesList') }}",
    sourceClearUrl: "{{ ($isGuest ?? false) ? route('guest.explore.clearSource') : route('vault.explore.clearSource') }}",
    sourceSelectUrlTemplate: "{{ ($isGuest ?? false) ? route('guest.explore.selectSource', ['id' => '__ID__']) : route('vault.explore.selectSource', ['id' => '__ID__']) }}",
    semanticOptionsUrl: "{{ ($isGuest ?? false) ? route('guest.explore.options') : route('vault.explore.options') }}",
    semanticAutocompleteUrl: "{{ ($isGuest ?? false) ? route('guest.explore.semanticAutocomplete') : route('vault.explore.semanticAutocomplete') }}",
    semanticAutocompleteUnifiedUrl: "{{ ($isGuest ?? false) ? route('guest.explore.semanticAutocompleteUnified') : route('vault.explore.semanticAutocompleteUnified') }}",
    semanticLoadUrl: "{{ ($isGuest ?? false) ? route('guest.explore.semanticLoad') : route('vault.explore.semanticLoad') }}",
    semanticSaveUrl: "{{ ($isGuest ?? false) ? route('guest.explore.semanticSave') : route('vault.explore.semanticSave') }}",
    sourcesUploadUrl: "{{ ($isGuest ?? false) ? route('guest.sources.store') : route('vault.sources.store') }}",
    sourcesStatusUrl: "{{ ($isGuest ?? false) ? route('guest.sources.status') : route('vault.sources.status') }}",
    sourcesHealthUrl: "{{ ($isGuest ?? false) ? route('guest.sources.health') : route('vault.sources.health') }}",
    sourceCancelUrlTemplate: "{{ route('vault.sources.cancel', ['id' => '__ID__']) }}",
    sourceReprocessUrlTemplate: "{{ route('vault.sources.reprocess', ['id' => '__ID__']) }}",
    sourcesPurgeSelectedUrl: "{{ ($isGuest ?? false) ? route('guest.sources.purgeSelected') : route('vault.sources.purgeSelected') }}",
    saveViewPreferenceUrl: "{{ route('vault.explore.saveViewPreference') }}",
    saveOverrideUrl: "{{ ($isGuest ?? false) ? '' : route('vault.explore.saveOverride') }}",
    overridesSummaryUrl: "{{ ($isGuest ?? false) ? '' : route('vault.explore.overridesSummary') }}",
    publishOverridesUrl: "{{ ($isGuest ?? false) ? '' : route('vault.explore.publishOverrides') }}",
    discardOverridesUrl: "{{ ($isGuest ?? false) ? '' : route('vault.explore.discardOverrides') }}",
    canInlineEdit: @json((bool) ($effectiveActor && ($effectiveActor->hasPermission('leads.normalize') || $effectiveActor->hasPermission('system.settings')))),
    isGlobalSuperReadOnly: @json((bool) $isGlobalSuperReadOnly),
    canImportLeads: @json((bool) (!$isGlobalSuperReadOnly && (($effectiveActor && $effectiveActor->hasPermission('leads.import')) || ($isGuest ?? false)))),
    canDeleteSources: @json((bool) (($effectiveActor && $effectiveActor->hasPermission('leads.delete')) || ($isGuest ?? false))),
    canCancelImport: @json((bool) (!$isGlobalSuperReadOnly && $effectiveActor && $effectiveActor->hasPermission('automation.cancel'))),
    canReprocessImport: @json((bool) (!$isGlobalSuperReadOnly && $effectiveActor && $effectiveActor->hasPermission('automation.reprocess'))),
    hasSources: @json((bool) ($hasSources ?? false)),
    forceImportGate: @json((bool) ($forceImportGate ?? false)),
    activeTenantUuid: @json(isset($tenant_uuid) ? (string) $tenant_uuid : null),
    isGuest: @json((bool) ($isGuest ?? false))
}
window.exploreColumnSettings = @json($columnSettings ?? []);
window.exploreViewPreference = @json($viewPreference ?? null);
</script>

@endsection
