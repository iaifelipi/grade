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
            <button class="btn btn-outline-secondary btn-sm" id="selectionExportBtn" type="button">
                Exportar selecionados
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
    <th data-col="email" scope="col">Email</th>
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
                <button id="emptyStateSampleBtn" class="btn btn-outline-primary" type="button">
                    Ver exemplo
                </button>
                @if((auth()->check() && auth()->user()?->hasPermission('leads.import')) || ($isGuest ?? false))
                    <button
                        class="btn btn-primary"
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
                        <b id="foundCount">0</b> encontrados
                    </span>
                    <span class="small text-muted">
                        <b id="selectedCount">0</b> selecionados
                    </span>
                    <span class="small text-muted">
                        <b id="counter">0</b> registros
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
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 guest-welcome-modal">
                <div class="guest-welcome-shell">
                    <div class="guest-welcome-content">
                        <div class="guest-welcome-header">
                            <div>
                                <div class="guest-welcome-kicker">Bem-vindo ao Grade</div>
                                <h5 class="modal-title">Conecte dados, automatize decisões e dispare ações inteligentes</h5>
                                <p class="guest-welcome-subtitle">
                                    O Grade é para times que precisam transformar arquivos em inteligência prática:
                                    semântica, inferência e ações em poucos cliques.
                                </p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>

                        <div class="guest-welcome-body">
                            <div class="guest-welcome-features">
                                <div><i class="bi bi-diagram-3"></i>Conexão de dados com semântica viva</div>
                                <div><i class="bi bi-cpu"></i>Inferência para descobrir padrões ocultos</div>
                                <div><i class="bi bi-lightning-charge"></i>Automação de decisões e disparos de ações</div>
                                <div><i class="bi bi-stars"></i>Exploração rápida com filtros e visualizações</div>
                            </div>

                            <div class="guest-welcome-card">
                                <div class="guest-welcome-card-title">Comece em 3 passos</div>
                                <ol>
                                    <li>Importe um arquivo para montar sua base de dados.</li>
                                    <li>Ative filtros e colunas inteligentes para explorar rápido.</li>
                                    <li>Use semântica, automação, ações rápidas e inferência para tomar decisões.</li>
                                </ol>
                            </div>
                        </div>

                        <div class="guest-welcome-actions">
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Explorar agora</button>
                            <button type="button" class="btn btn-primary" data-guest-import>Importar arquivo</button>
                        </div>
                    </div>

                    <div class="guest-welcome-visual" aria-hidden="true">
                        <div class="guest-welcome-visual-label">Pipeline Inteligente</div>
                        <div class="guest-welcome-visual-flow">
                            <div class="guest-welcome-node">Dados</div>
                            <div class="guest-welcome-node">Semântica</div>
                            <div class="guest-welcome-node">Inferência</div>
                            <div class="guest-welcome-node">Ações</div>
                        </div>
                        <div class="guest-welcome-visual-caption">
                            Conexões automáticas entre fontes, regras e resultados.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="guestGoodbyeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 guest-welcome-modal">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="guest-welcome-kicker">Até breve</div>
                        <h5 class="modal-title">Volte logo para continuar acelerando sua base</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <p>Seus arquivos e configurações continuam disponíveis. Quando quiser, é só voltar e seguir explorando com mais inteligência.</p>
                </div>
                <div class="modal-footer border-0 guest-welcome-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Continuar como visitante</button>
                    <button type="button" class="btn btn-primary" data-guest-auth="login">Entrar</button>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="actionsManualWizardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 actions-wizard-modal">
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
                <button type="button" class="btn btn-primary">Iniciar wizard</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionsAutoWizardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 actions-wizard-modal">
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
                <button type="button" class="btn btn-primary">Iniciar wizard</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreDataQualityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content explore-dq-modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Qualidade de Dados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
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
    <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered modal-dialog-scrollable explore-columns-modal-dialog">
        <div class="modal-content explore-dq-modal-content">
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
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buscar registros</h5>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="tooltip"
                    data-bs-placement="left"
                    title="Use termos livres ou operadores como nome:, email:, cpf:, tel:, cidade:, uf:, score:80+, segmento:ID, nicho:ID, origem:ID."
                >
                    <i class="bi bi-info-circle"></i>
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <label for="exploreSearchModalInput" class="form-label">Nome, email, CPF ou telefone</label>
                <input
                    id="exploreSearchModalInput"
                    type="text"
                    class="form-control"
                    autocomplete="off"
                    placeholder="Ex: maria, 11987654321, 123.456.789-00"
                >
                <div class="row g-2 mt-2" id="exploreSearchAdvancedFields">
                    <div class="col-md-4">
                        <label for="exploreSearchSegmentSelect" class="form-label mb-1">Segmento</label>
                        <select id="exploreSearchSegmentSelect" class="form-select form-select-sm">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="exploreSearchNicheSelect" class="form-label mb-1">Nicho</label>
                        <select id="exploreSearchNicheSelect" class="form-select form-select-sm">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="exploreSearchOriginSelect" class="form-label mb-1">Origem</label>
                        <select id="exploreSearchOriginSelect" class="form-select form-select-sm">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label for="exploreSearchCitiesInput" class="form-label mb-1">Cidades (separadas por vírgula)</label>
                        <input id="exploreSearchCitiesInput" type="text" class="form-control form-control-sm" placeholder="Ex: São Paulo, Campinas">
                    </div>
                    <div class="col-md-5">
                        <label for="exploreSearchStatesInput" class="form-label mb-1">UFs (vírgula)</label>
                        <input id="exploreSearchStatesInput" type="text" class="form-control form-control-sm" placeholder="Ex: SP, RJ">
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    Dica inteligente: use <code>cpf:123</code>, <code>tel:1199</code>, <code>cidade:Campinas</code>, <code>uf:SP</code>, <code>score:80+</code>.
                </small>
                <small class="text-muted d-block">Múltiplos termos funcionam em conjunto (AND).</small>
                <small class="text-muted d-block">Atalho: <code>/</code> para abrir busca.</small>

                <div class="explore-search-preview" id="exploreSearchPreviewWrap">
                    <div class="explore-search-preview-header">
                        <div class="explore-search-preview-title">Prévia em tempo real</div>
                        <div class="explore-search-preview-count" id="exploreSearchPreviewCount">0 registros</div>
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
                <button id="exploreSearchClearBtn" type="button" class="btn btn-outline-secondary">Limpar</button>
                <button id="exploreSearchApplyBtn" type="button" class="btn btn-primary">Buscar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="exploreImportForm" action="{{ route('vault.sources.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exploreImportModalTitle">Importar arquivos</h5>
                    <span class="badge text-bg-light border" id="exploreImportModalModeBadge">Envio rápido</span>
                    <button id="exploreImportCloseBtn" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button id="exploreImportSubmitBtn" type="submit" class="btn btn-primary">Registrar e Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreSourcesPurgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Excluir arquivos selecionados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Essa ação remove os arquivos selecionados e dados relacionados.</p>
                <div class="alert alert-warning py-2 mb-2">Ação irreversível.</div>
                <label for="exploreSourcesPurgeInput" class="form-label mb-1">Digite <b>EXCLUIR</b> para confirmar</label>
                <input id="exploreSourcesPurgeInput" type="text" class="form-control" placeholder="EXCLUIR">
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
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button id="exploreSourcesPurgeConfirmBtn" type="button" class="btn btn-danger">Excluir</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exploreLeaveConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alterações pendentes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Você tem alterações pendentes neste arquivo.</p>
                <p class="mb-2 text-muted">Ao salvar, será gerado um novo arquivo derivado com essas alterações.</p>
                <p class="mb-0 text-muted" id="exploreLeaveConfirmCount">0 alteração(ões) pendente(s).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="exploreLeaveKeepPendingBtn" data-bs-dismiss="modal">Sair sem salvar</button>
                <button type="button" class="btn btn-outline-primary" id="exploreLeaveCancelBtn" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="exploreLeavePublishBtn">Publicar novo arquivo e sair</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="overridesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alterações pendentes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
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
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="layoutManageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gerenciar visualização</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="layout-manage-card mb-3">
                    <div class="layout-manage-card-title">Salvar nova visualização</div>
                    <div class="layout-manage-card-text">Cria uma visualização com as colunas e ordem atuais da tela.</div>
                    <div class="d-flex gap-2 mt-2">
                        <input
                            id="layoutManageCreateInput"
                            type="text"
                            class="form-control"
                            maxlength="80"
                            placeholder="Ex: Comercial compacto"
                        >
                        <button class="btn btn-success" type="button" id="layoutManageCreateBtn">Salvar</button>
                    </div>
                </div>

                <div id="layoutManageExistingSection">
                <div class="layout-manage-current mb-3">
                    <span class="layout-manage-label">Selecionada</span>
                    <span class="layout-manage-name" id="layoutManageCurrentName">-</span>
                </div>

                <label for="layoutManageNameInput" class="form-label">Renomear visualização</label>
                <div class="d-flex gap-2 mb-3">
                    <input
                        id="layoutManageNameInput"
                        type="text"
                        class="form-control"
                        maxlength="80"
                        placeholder="Novo nome"
                    >
                    <button class="btn btn-outline-primary" type="button" id="layoutManageRenameBtn">Renomear</button>
                </div>

                <div class="layout-manage-card mb-3">
                    <div class="layout-manage-card-title">Atualizar visualização</div>
                    <div class="layout-manage-card-text">Substitui esta visualização pelas colunas e ordem atuais da tela.</div>
                    <button class="btn btn-outline-secondary mt-2" type="button" id="layoutManageUpdateBtn">Atualizar com a tela atual</button>
                </div>

                <div class="layout-manage-card layout-manage-card--danger">
                    <div class="layout-manage-card-title">Excluir visualização</div>
                    <div class="layout-manage-card-text">Remove permanentemente esta visualização salva para este arquivo.</div>
                    <button class="btn btn-outline-danger mt-2" type="button" id="layoutManageDeleteBtn">Excluir visualização</button>
                </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="layoutDeleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted" id="layoutDeleteConfirmText">Excluir visualização?</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                <button class="btn btn-danger" type="button" id="layoutDeleteConfirmBtn">Excluir</button>
            </div>
        </div>
    </div>
</div>



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
