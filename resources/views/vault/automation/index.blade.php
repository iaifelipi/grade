@extends('layouts.vault')

@section('page-title','Automação Operacional')

@section('content')
<div class="vault-page vault-automation" id="automationPage">
    <div class="vault-standard-header">
        <div>
            <h4 class="vault-standard-header__title">Operações</h4>
            <p class="vault-standard-header__subtitle">Cadastro Operacional + automação de fluxos, execução e monitoramento em tempo real.</p>
        </div>
        <div class="vault-standard-header__actions">
            <button class="btn btn-outline-secondary btn-sm" id="autoRefreshBtn">Atualizar tudo</button>
        </div>
    </div>

    <div class="vault-card p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h6 class="mb-0">Visão geral</h6>
        </div>

        <div class="auto-stats">
            <div class="auto-stat"><h3 id="statSources">0</h3><span>Fontes</span></div>
            <div class="auto-stat"><h3 id="statLeads">0</h3><span>Leads brutos</span></div>
            <div class="auto-stat"><h3 id="statFlows">0</h3><span>Fluxos</span></div>
            <div class="auto-stat"><h3 id="statRuns">0</h3><span>Execuções</span></div>
            <div class="auto-stat"><h3 id="statRecords">0</h3><span>Registros operacionais</span></div>
            <div class="auto-stat"><h3 id="statPendingActions">0</h3><span>Ações pendentes</span></div>
            <div class="auto-stat"><h3 id="statHealthState">OK</h3><span>Saúde operacional</span></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="vault-card p-3 h-100">
                <h6 class="mb-3">Novo Fluxo</h6>
                <div class="mb-2">
                    <label class="form-label">Nome</label>
                    <input id="flowName" class="form-control" placeholder="Ex: Reativação WhatsApp">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select id="flowStatus" class="form-select">
                            <option value="draft">Rascunho</option>
                            <option value="active">Ativo</option>
                            <option value="paused">Pausado</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gatilho</label>
                        <select id="flowTriggerType" class="form-select">
                            <option value="manual">Manual</option>
                            <option value="schedule">Agendado</option>
                            <option value="event">Evento</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Filtro da audiência (JSON)</label>
                    <textarea id="flowAudienceFilter" class="form-control" rows="4" placeholder='{"channel_optin":"whatsapp","entity_type":"cliente"}'></textarea>
                </div>
                <hr>
                <h6 class="mb-2">Etapa inicial</h6>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Tipo</label>
                        <select id="stepType" class="form-select">
                            <option value="dispatch_message">Disparo de mensagem</option>
                            <option value="wait">Espera</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Canal</label>
                        <select id="stepChannel" class="form-select">
                            <option value="email">email</option>
                            <option value="sms">sms</option>
                            <option value="whatsapp">whatsapp</option>
                            <option value="manual">manual</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Próxima ação em (dias)</label>
                    <input type="number" min="0" id="stepNextActionDays" class="form-control" value="7">
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary" id="createFlowBtn">Criar fluxo e etapa</button>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="vault-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0">Fluxos</h6>
                    <button class="btn btn-sm btn-outline-secondary" id="reloadFlowsBtn">Recarregar</button>
                </div>
                <div class="auto-table mb-3">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Etapas</th>
                                <th>Última execução</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="flowsBody">
                            <tr><td colspan="6" class="text-muted">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="mb-2">Execuções</h6>
                <div class="auto-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fluxo</th>
                                <th>Status</th>
                                <th>Processado</th>
                                <th>Processando</th>
                                <th>Sucesso</th>
                                <th>Falha</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="runsBody">
                            <tr><td colspan="8" class="text-muted">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="vault-card p-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <h6 class="mb-0">Detalhe do Fluxo</h6>
                    <div class="auto-inline-meta" id="flowDetailStatusBadge">Nenhum fluxo selecionado</div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-2">
                        <label class="form-label">ID</label>
                        <input id="flowDetailId" class="form-control" readonly>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Nome</label>
                        <input id="flowDetailName" class="form-control" placeholder="Nome do fluxo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select id="flowDetailStatus" class="form-select">
                            <option value="draft">Rascunho</option>
                            <option value="active">Ativo</option>
                            <option value="paused">Pausado</option>
                            <option value="archived">Arquivado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Gatilho</label>
                        <select id="flowDetailTrigger" class="form-select">
                            <option value="manual">Manual</option>
                            <option value="schedule">Agendado</option>
                            <option value="event">Evento</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Filtro da audiência (JSON)</label>
                    <textarea id="flowDetailAudience" class="form-control" rows="3" placeholder='{"channel_optin":"email"}'></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-outline-primary btn-sm" id="saveFlowMetaBtn">Salvar metadados</button>
                    <button class="btn btn-outline-secondary btn-sm" id="reloadFlowDetailBtn">Recarregar</button>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0">Etapas</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="addStepRowBtn">Adicionar etapa</button>
                        <button class="btn btn-sm btn-primary" id="saveStepsBtn">Salvar etapas</button>
                    </div>
                </div>

                <div class="auto-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Ordem</th>
                                <th>Tipo</th>
                                <th>Canal</th>
                                <th>Próx. ação (dias)</th>
                                <th>Ativo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="stepsEditorBody">
                            <tr><td colspan="6" class="text-muted">Selecione um fluxo para editar as etapas.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="vault-card p-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <h6 class="mb-0">Monitor de Execução</h6>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" id="refreshRunMonitorBtn">Atualizar</button>
                        <button class="btn btn-sm btn-outline-primary" id="toggleRunRealtimeBtn" data-realtime="off">Tempo real: desativado</button>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">ID da execução</label>
                        <input id="runMonitorId" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <input id="runMonitorStatus" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Processado</label>
                        <input id="runMonitorProcessed" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Em processamento</label>
                        <input id="runMonitorProcessing" class="form-control" readonly>
                    </div>
                </div>

                <div id="runCancelNotice" class="auto-cancel-notice d-none" role="alert" aria-live="polite">
                    <div class="auto-cancel-notice__icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="auto-cancel-notice__body">
                        <strong id="runCancelNoticeTitle">Cancelamento solicitado</strong>
                        <div id="runCancelNoticeText">A execução será interrompida em segurança no próximo checkpoint.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="runCancelNoticeCloseBtn">Fechar</button>
                </div>

                <div class="auto-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Lead</th>
                                <th>Tentativa</th>
                                <th>Evento</th>
                                <th>Status</th>
                                <th>Quando</th>
                            </tr>
                        </thead>
                        <tbody id="runEventsBody">
                            <tr><td colspan="6" class="text-muted">Selecione uma execução para acompanhar eventos.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="vault-card p-3">
        <div class="border rounded-3 p-3 mb-3">
            <h6 class="mb-3">Novo Registro Operacional</h6>
            <div class="row g-2 mb-2">
                <div class="col-md-3">
                    <label class="form-label">Nome</label>
                    <input id="createRecordName" class="form-control" placeholder="Nome do contato">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select id="createRecordEntityType" class="form-select">
                        @foreach(($entityTypes ?? []) as $type)
                            <option value="{{ $type['key'] }}" @selected(($type['key'] ?? '') === 'lead')>{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estágio</label>
                    <input id="createRecordLifecycleStage" class="form-control" placeholder="novo">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input id="createRecordEmail" class="form-control" placeholder="contato@empresa.com">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Score</label>
                    <input id="createRecordScore" type="number" min="0" max="100" value="0" class="form-control">
                </div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Telefone</label>
                    <input id="createRecordPhone" class="form-control" placeholder="+5511999999999">
                </div>
                <div class="col-md-2">
                    <label class="form-label">WhatsApp</label>
                    <input id="createRecordWhatsapp" class="form-control" placeholder="+5511999999999">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cidade</label>
                    <input id="createRecordCity" class="form-control" placeholder="São Paulo">
                </div>
                <div class="col-md-1">
                    <label class="form-label">UF</label>
                    <input id="createRecordUf" class="form-control" placeholder="SP">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fonte do consentimento</label>
                    <input id="createRecordConsentSource" class="form-control" placeholder="manual">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" id="createRecordBtn">Criar registro</button>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div>
                <label class="form-label">Busca</label>
                <input id="recordsQ" class="form-control" placeholder="Nome, email, CPF, telefone">
            </div>
            <div>
                <label class="form-label">Tipo</label>
                <select id="recordsEntityType" class="form-select">
                    <option value="">todos</option>
                    @foreach(($entityTypes ?? []) as $type)
                        <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                    <label class="form-label">Estágio</label>
                <input id="recordsLifecycleStage" class="form-control" placeholder="novo, ativo, reativação...">
            </div>
            <div>
                <label class="form-label">Opt-in</label>
                <select id="recordsChannelOptin" class="form-select">
                    <option value="">todos</option>
                    <option value="email">email</option>
                    <option value="sms">sms</option>
                    <option value="whatsapp">whatsapp</option>
                </select>
            </div>
            <div>
                <label class="form-label">Canal</label>
                <select id="recordsChannelType" class="form-select">
                    <option value="">todos</option>
                    <option value="email">email</option>
                    <option value="sms">sms</option>
                    <option value="whatsapp">whatsapp</option>
                    <option value="phone">phone</option>
                    <option value="manual">manual</option>
                </select>
            </div>
            <div>
                <label class="form-label">Valor do canal</label>
                <input id="recordsChannelValue" class="form-control" placeholder="email / telefone / whatsapp">
            </div>
            <div>
                <label class="form-label">Canal primário</label>
                <select id="recordsChannelPrimary" class="form-select">
                    <option value="">todos</option>
                    <option value="1">sim</option>
                    <option value="0">não</option>
                </select>
            </div>
            <div>
                <label class="form-label">Contato permitido</label>
                <select id="recordsChannelCanContact" class="form-select">
                    <option value="">todos</option>
                    <option value="1">sim</option>
                    <option value="0">não</option>
                </select>
            </div>
            <div>
                <label class="form-label">Consentimento (status)</label>
                <select id="recordsConsentStatus" class="form-select">
                    <option value="">todos</option>
                    <option value="granted">concedido</option>
                    <option value="revoked">revogado</option>
                    <option value="pending">pendente</option>
                    <option value="unknown">indefinido</option>
                </select>
            </div>
            <div>
                <label class="form-label">Consentimento (canal)</label>
                <select id="recordsConsentChannel" class="form-select">
                    <option value="">todos</option>
                    <option value="email">email</option>
                    <option value="sms">sms</option>
                    <option value="whatsapp">whatsapp</option>
                    <option value="phone">phone</option>
                    <option value="manual">manual</option>
                </select>
            </div>
            <div>
                <label class="form-label">Consentimento (finalidade)</label>
                <input id="recordsConsentPurpose" class="form-control" placeholder="marketing">
            </div>
            <div>
                <label class="form-label">Itens por página</label>
                <select id="recordsPerPage" class="form-select">
                    <option value="20">20</option>
                    <option value="30" selected>30</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <button class="btn btn-outline-primary" id="loadRecordsBtn">Buscar registros</button>
        </div>

        <div class="auto-table">
            <table>
                <thead>
                    <tr>
                        <th>
                            <input class="form-check-input" type="checkbox" id="recordsSelectPage">
                        </th>
                        <th>ID</th>
                        <th>
                            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-records-sort="name">
                                Nome <span data-sort-indicator="name">↕</span>
                            </button>
                        </th>
                        <th>Tipo</th>
                            <th>Estágio</th>
                        <th>Contato</th>
                        <th>
                            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-records-sort="score">
                                Score <span data-sort-indicator="score">↕</span>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-records-sort="next_action_at">
                                Próxima ação <span data-sort-indicator="next_action_at">↕</span>
                            </button>
                        </th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="recordsBody">
                    <tr><td colspan="9" class="text-muted">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-2">
            <small class="text-muted" id="recordsPaginationInfo">Página 1 de 1</small>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="recordsPrevPageBtn">Anterior</button>
                <button class="btn btn-sm btn-outline-secondary" id="recordsNextPageBtn">Próxima</button>
            </div>
        </div>
    </div>

    <div class="vault-card p-3">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div class="me-3">
                <small class="text-muted d-block">Selecionados</small>
                <strong id="bulkSelectedCount">0</strong>
            </div>
            <div>
                <label class="form-label">Motivo exclusão</label>
                <input id="bulkDeleteReason" class="form-control" placeholder="Limpeza, duplicado, inválido...">
            </div>
            <div>
                <label class="form-label">Escopo</label>
                <select id="bulkScopeType" class="form-select">
                    <option value="selected_ids">Selecionados</option>
                    <option value="filtered">Filtro atual</option>
                </select>
            </div>
            <div>
                <label class="form-label">Ação</label>
                <select id="bulkActionType" class="form-select">
                    <option value="update_fields">Atualizar campos</option>
                    <option value="set_next_action">Agendar próxima ação</option>
                    <option value="set_consent">Atualizar consentimento</option>
                </select>
            </div>
            <div id="bulkNextActionWrap" class="d-none">
                <label class="form-label">Dias</label>
                <input type="number" min="0" max="3650" value="7" id="bulkNextActionDays" class="form-control">
            </div>
            <div id="bulkConsentWrap" class="d-none">
                <label class="form-label">Canal</label>
                <select id="bulkConsentChannel" class="form-select">
                    <option value="email">email</option>
                    <option value="sms">sms</option>
                    <option value="whatsapp">whatsapp</option>
                </select>
            </div>
            <div id="bulkConsentStatusWrap" class="d-none">
                <label class="form-label">Status</label>
                <select id="bulkConsentStatus" class="form-select">
                    <option value="granted">granted</option>
                    <option value="revoked">revoked</option>
                </select>
            </div>
            <div id="bulkConsentSourceWrap" class="d-none">
                <label class="form-label">Fonte</label>
                <input id="bulkConsentSource" class="form-control" placeholder="import, formulário, manual...">
            </div>
            <div id="bulkUpdatesWrap">
                <label class="form-label">Atualizações (JSON)</label>
                <input id="bulkUpdatesJson" class="form-control" placeholder='{"lifecycle_stage":"reativacao"}'>
            </div>
            <button class="btn btn-primary" id="runBulkTaskBtn">Executar lote</button>
            <button class="btn btn-outline-danger" id="bulkDeleteSelectedBtn">Excluir selecionados</button>
        </div>

        <div class="border rounded-3 p-2 mb-3 d-none" id="bulkDeleteFeedbackBox">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <strong id="bulkDeleteFeedbackSummary">Sem execução.</strong>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted" id="bulkDeleteFeedbackFailedIds"></small>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="copyBulkDeleteFailedIdsBtn">Copiar IDs com falha</button>
                </div>
            </div>
            <div class="auto-table mt-2">
                <table>
                    <thead>
                        <tr>
                            <th>ID do registro</th>
                            <th>Status</th>
                            <th>Detalhe</th>
                        </tr>
                    </thead>
                    <tbody id="bulkDeleteFeedbackBody">
                        <tr><td colspan="3" class="text-muted">Sem resultados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0">Tarefas em lote</h6>
            <button class="btn btn-sm btn-outline-secondary" id="reloadBulkTasksBtn">Recarregar</button>
        </div>
        <div class="auto-table mb-3">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Ação</th>
                        <th>Escopo</th>
                        <th>Progresso</th>
                        <th>Criado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="bulkTasksBody">
                    <tr><td colspan="7" class="text-muted">Sem tarefas.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="auto-table">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Registro</th>
                        <th>Status</th>
                        <th>Erro</th>
                        <th>Quando</th>
                    </tr>
                </thead>
                <tbody id="bulkTaskItemsBody">
                    <tr><td colspan="5" class="text-muted">Selecione uma tarefa para ver itens.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="vault-card p-3">
        <h6 class="mb-3">Registrar Interação Manual</h6>
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <label class="form-label">ID do registro</label>
                <input id="interactionRecordId" class="form-control" placeholder="123">
            </div>
            <div class="col-md-2">
                <label class="form-label">Canal</label>
                <select id="interactionChannel" class="form-select">
                    <option value="email">email</option>
                    <option value="sms">sms</option>
                    <option value="whatsapp">whatsapp</option>
                    <option value="manual">manual</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="interactionStatus" class="form-select">
                    <option value="sent">enviado</option>
                    <option value="new">novo</option>
                    <option value="failed">falhou</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Próxima ação</label>
                <input type="datetime-local" id="interactionNextActionAt" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Assunto</label>
                <input id="interactionSubject" class="form-control" placeholder="Resumo da interação">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Mensagem</label>
            <textarea id="interactionMessage" class="form-control" rows="3" placeholder="Detalhes da interação"></textarea>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-primary" id="saveInteractionBtn">Salvar interação</button>
            <small class="auto-status-pill text-muted" id="automationStatusText">Pronto.</small>
        </div>
    </div>
</div>
@endsection
