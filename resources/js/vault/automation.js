const els = {}

const state = {
    selectedFlowId: null,
    selectedRunId: null,
    runPollingTimer: null,
    recordsPage: 1,
    recordsLastPage: 1,
    recordsTotal: 0,
    recordsSortBy: 'next_action_at',
    recordsSortDir: 'desc',
    selectedRecordIds: new Set(),
    selectedBulkTaskId: null,
    runCancelNoticeTimer: null,
    lastRunCancelNoticeId: null,
}

const RECORD_FILTER_STORAGE_KEY = `pixip.automation.records.filters.${document.body?.dataset?.authUserId || 'guest'}`
let lastBulkDeleteFailedIds = []

function byId(id){
    if(!els[id]) els[id] = document.getElementById(id)
    return els[id]
}

function csrfToken(){
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

async function request(url, options = {}){
    const method = String(options.method || 'GET').toUpperCase()
    const headers = {
        'Accept': 'application/json',
        ...(options.headers || {}),
    }

    if(method !== 'GET'){
        headers['X-CSRF-TOKEN'] = csrfToken()
    }

    const body = options.body
    if(body && !(body instanceof FormData)){
        headers['Content-Type'] = 'application/json'
    }

    const res = await fetch(url, {
        ...options,
        headers,
        body: body && !(body instanceof FormData) ? JSON.stringify(body) : body,
    })

    let data = null
    try {
        data = await res.json()
    } catch (_e) {
        data = null
    }

    if(!res.ok){
        const msg = data?.message || `Erro ${res.status} em ${url}`
        throw new Error(msg)
    }

    return data
}

function setStatus(text, isError = false){
    const node = byId('automationStatusText')
    if(!node) return
    node.textContent = text
    node.classList.toggle('text-danger', Boolean(isError))
    node.classList.toggle('text-muted', !isError)
    node.classList.toggle('is-error', Boolean(isError))
    node.classList.toggle('is-success', !isError)
}

function shortDate(v){
    if(!v) return '—'
    const d = new Date(v)
    if(Number.isNaN(d.getTime())) return '—'
    return d.toLocaleString('pt-BR')
}

const FLOW_STATUS_LABELS = {
    draft: 'Rascunho',
    active: 'Ativo',
    paused: 'Pausado',
    archived: 'Arquivado',
}

const RUN_STATUS_LABELS = {
    queued: 'Na fila',
    running: 'Em execução',
    cancel_requested: 'Cancelamento solicitado',
    processing: 'Processando',
    done: 'Concluída',
    done_with_errors: 'Concluída com erros',
    failed: 'Falhou',
    cancelled: 'Cancelada',
}

const STEP_TYPE_LABELS = {
    action: 'Ação',
    dispatch_message: 'Disparo de mensagem',
    wait: 'Espera',
    tag: 'Tag',
}

const BULK_TASK_STATUS_LABELS = {
    queued: 'Na fila',
    running: 'Em execução',
    cancel_requested: 'Cancelamento solicitado',
    cancelled: 'Cancelada',
    done: 'Concluída',
    done_with_errors: 'Concluída com erros',
    failed: 'Falhou',
}

const BULK_ACTION_LABELS = {
    update_fields: 'Atualizar campos',
    set_next_action: 'Agendar próxima ação',
    set_consent: 'Atualizar consentimento',
}

const BULK_SCOPE_LABELS = {
    selected_ids: 'Selecionados',
    filtered: 'Filtro atual',
}

function normalizeKey(v){
    return String(v || '').trim().toLowerCase()
}

function flowStatusLabel(v){
    const key = normalizeKey(v)
    return FLOW_STATUS_LABELS[key] || (key ? key : '—')
}

function runStatusLabel(v){
    const key = normalizeKey(v)
    return RUN_STATUS_LABELS[key] || (key ? key : '—')
}

function stepTypeLabel(v){
    const key = normalizeKey(v)
    return STEP_TYPE_LABELS[key] || (key ? key : '—')
}

function bulkTaskStatusLabel(v){
    const key = normalizeKey(v)
    return BULK_TASK_STATUS_LABELS[key] || (key ? key : '—')
}

function bulkActionLabel(v){
    const key = normalizeKey(v)
    return BULK_ACTION_LABELS[key] || (key ? key : '—')
}

function bulkScopeLabel(v){
    const key = normalizeKey(v)
    return BULK_SCOPE_LABELS[key] || (key ? key : '—')
}

function renderRunEventStatusBadge(statusRaw){
    const status = normalizeKey(statusRaw)
    if(status === 'processing'){
        return '<span class="badge text-bg-warning d-inline-flex align-items-center gap-1"><i class="bi bi-arrow-repeat auto-spin"></i>processando</span>'
    }
    if(status === 'success'){
        return '<span class="badge text-bg-success">sucesso</span>'
    }
    if(status === 'failed'){
        return '<span class="badge text-bg-danger">falha</span>'
    }
    if(status === 'skipped'){
        return '<span class="badge text-bg-secondary">ignorado</span>'
    }
    if(status === 'queued'){
        return '<span class="badge text-bg-info">na fila</span>'
    }
    if(status === 'running'){
        return '<span class="badge text-bg-primary">em execução</span>'
    }
    if(status === 'cancel_requested'){
        return '<span class="badge text-bg-warning text-dark">cancelamento solicitado</span>'
    }
    if(status === 'done'){
        return '<span class="badge text-bg-success">concluída</span>'
    }
    if(status === 'done_with_errors'){
        return '<span class="badge text-bg-warning text-dark">concluída com erros</span>'
    }
    if(status === 'cancelled'){
        return '<span class="badge text-bg-secondary">cancelada</span>'
    }

    return `<span class="badge text-bg-light">${text(runStatusLabel(status))}</span>`
}

function renderFlowStatusBadge(statusRaw){
    const status = normalizeKey(statusRaw)
    if(status === 'active'){
        return '<span class="badge text-bg-success">ativo</span>'
    }
    if(status === 'draft'){
        return '<span class="badge text-bg-secondary">rascunho</span>'
    }
    if(status === 'paused'){
        return '<span class="badge text-bg-warning text-dark">pausado</span>'
    }
    if(status === 'archived'){
        return '<span class="badge text-bg-dark">arquivado</span>'
    }

    return `<span class="badge text-bg-light">${text(flowStatusLabel(status))}</span>`
}

function readStoredRecordFilters(){
    try {
        const raw = localStorage.getItem(RECORD_FILTER_STORAGE_KEY)
        if(!raw) return null
        const parsed = JSON.parse(raw)
        return parsed && typeof parsed === 'object' ? parsed : null
    } catch (_e){
        return null
    }
}

function storeRecordFilters(){
    const payload = {
        q: byId('recordsQ')?.value || '',
        entity_type: byId('recordsEntityType')?.value || '',
        lifecycle_stage: byId('recordsLifecycleStage')?.value || '',
        channel_optin: byId('recordsChannelOptin')?.value || '',
        channel_type: byId('recordsChannelType')?.value || '',
        channel_value: byId('recordsChannelValue')?.value || '',
        channel_is_primary: byId('recordsChannelPrimary')?.value || '',
        channel_can_contact: byId('recordsChannelCanContact')?.value || '',
        consent_status: byId('recordsConsentStatus')?.value || '',
        consent_channel: byId('recordsConsentChannel')?.value || '',
        consent_purpose: byId('recordsConsentPurpose')?.value || '',
        per_page: byId('recordsPerPage')?.value || '30',
        page: state.recordsPage || 1,
        sort_by: state.recordsSortBy || 'next_action_at',
        sort_dir: state.recordsSortDir || 'desc',
    }

    try {
        localStorage.setItem(RECORD_FILTER_STORAGE_KEY, JSON.stringify(payload))
    } catch (_e) {}
}

function restoreRecordFilters(){
    const stored = readStoredRecordFilters()
    if(!stored) return

    const setIf = (id, key)=>{
        const el = byId(id)
        if(!el || !Object.prototype.hasOwnProperty.call(stored, key)) return
        el.value = String(stored[key] ?? '')
    }

    setIf('recordsQ', 'q')
    setIf('recordsEntityType', 'entity_type')
    setIf('recordsLifecycleStage', 'lifecycle_stage')
    setIf('recordsChannelOptin', 'channel_optin')
    setIf('recordsChannelType', 'channel_type')
    setIf('recordsChannelValue', 'channel_value')
    setIf('recordsChannelPrimary', 'channel_is_primary')
    setIf('recordsChannelCanContact', 'channel_can_contact')
    setIf('recordsConsentStatus', 'consent_status')
    setIf('recordsConsentChannel', 'consent_channel')
    setIf('recordsConsentPurpose', 'consent_purpose')
    setIf('recordsPerPage', 'per_page')

    const savedPage = Number(stored.page || 1)
    state.recordsPage = Number.isFinite(savedPage) && savedPage > 0 ? savedPage : 1

    const sortBy = String(stored.sort_by || '').trim()
    const sortDir = String(stored.sort_dir || '').trim().toLowerCase()
    if(['name', 'score', 'next_action_at'].includes(sortBy)){
        state.recordsSortBy = sortBy
    }
    if(['asc', 'desc'].includes(sortDir)){
        state.recordsSortDir = sortDir
    }
}

function updateSortIndicators(){
    document.querySelectorAll('[data-sort-indicator]').forEach((el)=>{
        const key = el.getAttribute('data-sort-indicator')
        if(key === state.recordsSortBy){
            el.textContent = state.recordsSortDir === 'asc' ? '↑' : '↓'
        } else {
            el.textContent = '↕'
        }
    })
}

function updateBulkSelectionUi(){
    const node = byId('bulkSelectedCount')
    if(node){
        node.textContent = String(state.selectedRecordIds.size)
    }
}

function text(v){
    return String(v ?? '').replace(/[&<>"']/g, (m)=>({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]))
}

function parseJsonOrNull(raw){
    const value = String(raw || '').trim()
    if(value === '') return null
    return JSON.parse(value)
}

function setRunRealtime(active){
    const btn = byId('toggleRunRealtimeBtn')
    if(!btn) return
    btn.dataset.realtime = active ? 'on' : 'off'
    btn.textContent = `Tempo real: ${active ? 'ativado' : 'desativado'}`
    btn.classList.toggle('btn-outline-success', active)
    btn.classList.toggle('btn-outline-primary', !active)
}

function hideRunCancelNotice(){
    const box = byId('runCancelNotice')
    if(box){
        box.classList.add('d-none')
    }
    if(state.runCancelNoticeTimer){
        clearTimeout(state.runCancelNoticeTimer)
        state.runCancelNoticeTimer = null
    }
}

function showRunCancelNotice(runId, message){
    const box = byId('runCancelNotice')
    const title = byId('runCancelNoticeTitle')
    const textNode = byId('runCancelNoticeText')
    if(!box || !title || !textNode) return

    title.textContent = `Cancelamento solicitado · Execução #${runId}`
    textNode.textContent = message || 'A execução será interrompida em segurança no próximo checkpoint.'
    box.classList.remove('d-none')
    state.lastRunCancelNoticeId = Number(runId)

    if(state.runCancelNoticeTimer){
        clearTimeout(state.runCancelNoticeTimer)
    }
    state.runCancelNoticeTimer = setTimeout(()=>{
        hideRunCancelNotice()
    }, 7000)
}

function stopRunPolling(){
    if(state.runPollingTimer){
        clearInterval(state.runPollingTimer)
        state.runPollingTimer = null
    }
    setRunRealtime(false)
}

function startRunPolling(){
    stopRunPolling()
    if(!state.selectedRunId) return

    setRunRealtime(true)
    state.runPollingTimer = setInterval(async ()=>{
        try {
            await loadRunMonitor(state.selectedRunId, true)
        } catch (_e) {
            stopRunPolling()
        }
    }, 3000)
}

async function loadStats(){
    const [autoStats, recordsStats, healthStats] = await Promise.all([
        request('/vault/automation/stats'),
        request('/vault/operational-records/stats'),
        request('/vault/automation/ops/health'),
    ])

    byId('statSources').textContent = autoStats?.sources ?? 0
    byId('statLeads').textContent = autoStats?.leads ?? 0
    byId('statFlows').textContent = autoStats?.flows ?? 0
    byId('statRuns').textContent = autoStats?.runs ?? 0

    byId('statRecords').textContent = recordsStats?.total_records ?? 0
    byId('statPendingActions').textContent = recordsStats?.pending_next_actions ?? 0

    const healthNode = byId('statHealthState')
    if(healthNode){
        const state = String(healthStats?.state || 'ok').toLowerCase()
        if(state === 'critical'){
            healthNode.textContent = 'CRÍTICO'
            healthNode.style.color = '#dc3545'
        } else if(state === 'warning'){
            healthNode.textContent = 'ATENÇÃO'
            healthNode.style.color = '#fd7e14'
        } else {
            healthNode.textContent = 'OK'
            healthNode.style.color = '#198754'
        }
    }
}

function bindFlowActions(body){
    body.querySelectorAll('[data-run-flow]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const flowId = Number(btn.getAttribute('data-run-flow') || 0)
            if(!flowId) return
            btn.disabled = true
            try {
                const run = await request(`/vault/automation/flows/${flowId}/run`, {
                    method: 'POST',
                    body: { limit: 500 },
                })
                setStatus(`Execução #${run.run_id} enfileirada.`)
                await loadRuns()
                await loadStats()
                await selectRun(run.run_id, true)
            } catch (e) {
                setStatus(e.message || 'Falha ao executar fluxo.', true)
            } finally {
                btn.disabled = false
            }
        })
    })

    body.querySelectorAll('[data-open-flow]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const flowId = Number(btn.getAttribute('data-open-flow') || 0)
            if(!flowId) return
            btn.disabled = true
            try {
                await loadFlowDetail(flowId)
            } catch (e) {
                setStatus(e.message || 'Falha ao carregar detalhe do fluxo.', true)
            } finally {
                btn.disabled = false
            }
        })
    })
}

async function loadFlows(){
    const body = byId('flowsBody')
    if(!body) return

    const payload = await request('/vault/automation/flows?per_page=20')
    const rows = payload?.data || []

    if(!rows.length){
        body.innerHTML = '<tr><td colspan="6" class="text-muted">Nenhum fluxo cadastrado.</td></tr>'
        return
    }

    body.innerHTML = rows.map((row)=>`
        <tr class="${state.selectedFlowId === row.id ? 'is-selected' : ''}">
            <td>${row.id}</td>
            <td>${text(row.name)}</td>
            <td>${renderFlowStatusBadge(row.status)}</td>
            <td>${row.steps_count}</td>
            <td>${text(shortDate(row.last_run_at))}</td>
            <td class="auto-actions-cell">
                <button class="btn btn-sm btn-outline-secondary" data-open-flow="${row.id}">Abrir</button>
                <button class="btn btn-sm btn-primary" data-run-flow="${row.id}">Rodar</button>
            </td>
        </tr>
    `).join('')

    bindFlowActions(body)
}

function bindRunActions(body){
    body.querySelectorAll('[data-monitor-run]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const runId = Number(btn.getAttribute('data-monitor-run') || 0)
            if(!runId) return
            await selectRun(runId, true)
        })
    })

    body.querySelectorAll('[data-cancel-run]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const runId = Number(btn.getAttribute('data-cancel-run') || 0)
            if(!runId) return
            if(!window.confirm(`Solicitar cancelamento da execução #${runId}?`)) return

            btn.disabled = true
            try {
                const result = await request(`/vault/automation/runs/${runId}/cancel`, { method: 'POST' })
                const statusLabel = runStatusLabel(result?.status || '')
                setStatus(result?.message ? `${result.message} (#${runId})` : `Execução #${runId}: ${statusLabel}.`)
                if(normalizeKey(result?.status) === 'cancel_requested'){
                    showRunCancelNotice(runId, result?.message || null)
                } else if(normalizeKey(result?.status) === 'cancelled'){
                    hideRunCancelNotice()
                }
                await loadRuns()
                if(state.selectedRunId === runId){
                    await loadRunMonitor(runId, true)
                }
            } catch (e) {
                setStatus(e.message || 'Falha ao cancelar execução.', true)
            } finally {
                btn.disabled = false
            }
        })
    })
}

async function loadRuns(){
    const body = byId('runsBody')
    if(!body) return

    const payload = await request('/vault/automation/runs?per_page=20')
    const rows = payload?.data || []

    if(!rows.length){
        body.innerHTML = '<tr><td colspan="8" class="text-muted">Nenhuma execução encontrada.</td></tr>'
        return
    }

    body.innerHTML = rows.map((row)=>{
        const canCancel = ['queued', 'running', 'cancel_requested'].includes(normalizeKey(row.status))
        return `
        <tr class="${state.selectedRunId === row.id ? 'is-selected' : ''}">
            <td>${row.id}</td>
            <td>${text(row.flow_name || `#${row.flow_id}`)}</td>
            <td>${renderRunEventStatusBadge(row.status)}</td>
            <td>${row.processed_count}</td>
            <td>${row.processing_count ?? 0}</td>
            <td>${row.success_count}</td>
            <td>${row.failure_count}</td>
            <td>
                <button class="btn btn-sm btn-outline-secondary" data-monitor-run="${row.id}">Monitorar</button>
                ${canCancel ? `<button class="btn btn-sm btn-outline-danger" data-cancel-run="${row.id}">Cancelar</button>` : ''}
            </td>
        </tr>
    `
    }).join('')

    bindRunActions(body)
}

function renderStepRows(steps = []){
    const body = byId('stepsEditorBody')
    if(!body) return

    if(!steps.length){
        body.innerHTML = '<tr><td colspan="6" class="text-muted">Sem etapas. Adicione uma nova etapa.</td></tr>'
        return
    }

    body.innerHTML = steps.map((step, index)=>`
        <tr class="step-row" data-step-index="${index}">
            <td><input class="form-control form-control-sm" data-step-key="step_order" type="number" min="1" value="${Number(step.step_order || index + 1)}"></td>
            <td>
                <select class="form-select form-select-sm" data-step-key="step_type">
                    <option value="dispatch_message" ${step.step_type === 'dispatch_message' ? 'selected' : ''}>${stepTypeLabel('dispatch_message')}</option>
                    <option value="wait" ${step.step_type === 'wait' ? 'selected' : ''}>${stepTypeLabel('wait')}</option>
                    <option value="tag" ${step.step_type === 'tag' ? 'selected' : ''}>${stepTypeLabel('tag')}</option>
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm" data-step-key="channel">
                    <option value="email" ${step.channel === 'email' ? 'selected' : ''}>email</option>
                    <option value="sms" ${step.channel === 'sms' ? 'selected' : ''}>sms</option>
                    <option value="whatsapp" ${step.channel === 'whatsapp' ? 'selected' : ''}>whatsapp</option>
                    <option value="manual" ${step.channel === 'manual' ? 'selected' : ''}>manual</option>
                </select>
            </td>
            <td><input class="form-control form-control-sm" data-step-key="next_action_in_days" type="number" min="0" value="${Number(step.config_json?.next_action_in_days || 0)}"></td>
            <td class="text-center"><input class="form-check-input" data-step-key="is_active" type="checkbox" ${step.is_active !== false ? 'checked' : ''}></td>
            <td><button class="btn btn-sm btn-outline-danger" data-remove-step="1">Remover</button></td>
        </tr>
    `).join('')

    body.querySelectorAll('[data-remove-step]').forEach((btn)=>{
        btn.addEventListener('click', ()=>{
            const row = btn.closest('tr')
            if(row) row.remove()
            if(!body.querySelector('tr')){
                renderStepRows([])
            }
        })
    })
}

function collectStepRows(){
    const body = byId('stepsEditorBody')
    if(!body) return []

    const rows = Array.from(body.querySelectorAll('tr.step-row'))
    const steps = rows.map((row, index)=>{
        const get = (key)=> row.querySelector(`[data-step-key="${key}"]`)
        const order = Number(get('step_order')?.value || index + 1)
        const stepType = String(get('step_type')?.value || 'dispatch_message').trim()
        const channel = String(get('channel')?.value || 'manual').trim()
        const nextDays = Number(get('next_action_in_days')?.value || 0)
        const isActive = Boolean(get('is_active')?.checked)

        return {
            step_order: Number.isFinite(order) ? Math.max(1, order) : index + 1,
            step_type: stepType,
            channel,
            config_json: {
                next_action_in_days: Number.isFinite(nextDays) ? Math.max(0, nextDays) : 0,
            },
            is_active: isActive,
        }
    })

    return steps.sort((a, b)=>a.step_order - b.step_order)
}

async function loadFlowDetail(flowId){
    const flow = await request(`/vault/automation/flows/${flowId}`)

    state.selectedFlowId = flow.id

    byId('flowDetailId').value = flow.id
    byId('flowDetailName').value = flow.name || ''
    byId('flowDetailStatus').value = flow.status || 'draft'
    byId('flowDetailTrigger').value = flow.trigger_type || 'manual'
    byId('flowDetailAudience').value = flow.audience_filter
        ? JSON.stringify(flow.audience_filter, null, 2)
        : ''

    byId('flowDetailStatusBadge').textContent = `Fluxo #${flow.id} · ${flowStatusLabel(flow.status)}`

    renderStepRows(Array.isArray(flow.steps) ? flow.steps : [])
    await loadFlows()
}

async function saveFlowMeta(){
    const flowId = Number(byId('flowDetailId')?.value || 0)
    if(!flowId){
        setStatus('Selecione um fluxo para salvar metadados.', true)
        return
    }

    let audience = null
    try {
        audience = parseJsonOrNull(byId('flowDetailAudience')?.value)
    } catch (_e) {
        setStatus('JSON de audiência inválido no detalhe do fluxo.', true)
        return
    }

    await request(`/vault/automation/flows/${flowId}`, {
        method: 'PUT',
        body: {
            name: (byId('flowDetailName')?.value || '').trim(),
            status: byId('flowDetailStatus')?.value || 'draft',
            trigger_type: byId('flowDetailTrigger')?.value || 'manual',
            audience_filter: audience,
        },
    })

    setStatus(`Metadados do fluxo #${flowId} salvos.`)
    await loadFlowDetail(flowId)
}

async function saveFlowSteps(){
    const flowId = Number(byId('flowDetailId')?.value || 0)
    if(!flowId){
        setStatus('Selecione um fluxo para salvar etapas.', true)
        return
    }

    const steps = collectStepRows()
    if(!steps.length){
        setStatus('Adicione ao menos uma etapa antes de salvar.', true)
        return
    }

    await request(`/vault/automation/flows/${flowId}/steps`, {
        method: 'POST',
        body: { steps },
    })

    setStatus(`Etapas do fluxo #${flowId} salvas (${steps.length} itens).`)
    await loadFlowDetail(flowId)
}

function addStepRow(){
    const body = byId('stepsEditorBody')
    if(!body) return

    const current = Array.from(body.querySelectorAll('tr.step-row')).length
    if(current === 0){
        renderStepRows([{
            step_order: 1,
            step_type: 'dispatch_message',
            channel: 'email',
            config_json: { next_action_in_days: 0 },
            is_active: true,
        }])
        return
    }

    const step = {
        step_order: current + 1,
        step_type: 'dispatch_message',
        channel: 'email',
        config_json: { next_action_in_days: 0 },
        is_active: true,
    }

    const rows = collectStepRows()
    rows.push(step)
    renderStepRows(rows)
}

async function loadRunMonitor(runId, silent = false){
    const run = await request(`/vault/automation/runs/${runId}`)
    const eventsPayload = await request(`/vault/automation/runs/${runId}/events?per_page=50`)
    const events = eventsPayload?.data || []

    state.selectedRunId = run.id

    byId('runMonitorId').value = run.id
    byId('runMonitorStatus').value = runStatusLabel(run.status)
    byId('runMonitorProcessed').value = `${run.processed_count || 0}/${run.scheduled_count || 0}`
    const processingInput = byId('runMonitorProcessing')
    if(processingInput){
        processingInput.value = String(run.processing_count || 0)
    }

    const runStatusKey = normalizeKey(run.status)
    if(runStatusKey === 'cancel_requested'){
        if(state.lastRunCancelNoticeId !== Number(run.id)){
            showRunCancelNotice(run.id, 'Cancelamento solicitado. Aguardando checkpoint de parada segura.')
        }
    } else {
        hideRunCancelNotice()
        state.lastRunCancelNoticeId = null
    }

    const body = byId('runEventsBody')
    if(body){
        if(!events.length){
            body.innerHTML = '<tr><td colspan="6" class="text-muted">Sem eventos para esta execução.</td></tr>'
        } else {
            body.innerHTML = events.map((event)=>`
                <tr>
                    <td>${event.id}</td>
                    <td>${event.lead_id || '—'}</td>
                    <td>${event.attempt || 1}</td>
                    <td>${text(stepTypeLabel(event.event_type || '—'))}</td>
                    <td>${renderRunEventStatusBadge(event.status)}</td>
                    <td>${text(shortDate(event.occurred_at || event.created_at))}</td>
                </tr>
            `).join('')
        }
    }

    await loadRuns()

    const terminal = ['done', 'done_with_errors', 'failed', 'cancelled']
    if(terminal.includes(String(run.status || '').toLowerCase())){
        stopRunPolling()
    }

    if(!silent){
        setStatus(`Monitor atualizado para execução #${runId}.`)
    }
}

async function selectRun(runId, withRealtime = false){
    await loadRunMonitor(runId)
    if(withRealtime){
        startRunPolling()
    }
}

async function createFlow(){
    const name = (byId('flowName')?.value || '').trim()
    if(name === ''){
        setStatus('Informe o nome do fluxo.', true)
        return
    }

    let audienceFilter = null
    try {
        audienceFilter = parseJsonOrNull(byId('flowAudienceFilter')?.value)
    } catch (_e){
        setStatus('JSON do filtro de audiência inválido.', true)
        return
    }

    const status = byId('flowStatus')?.value || 'draft'
    const triggerType = byId('flowTriggerType')?.value || 'manual'
    const stepType = byId('stepType')?.value || 'dispatch_message'
    const stepChannel = byId('stepChannel')?.value || 'email'
    const nextDays = Number(byId('stepNextActionDays')?.value || 0)

    const created = await request('/vault/automation/flows', {
        method: 'POST',
        body: {
            name,
            status,
            trigger_type: triggerType,
            audience_filter: audienceFilter,
        },
    })

    const flowId = created?.flow_id
    if(!flowId){
        throw new Error('Fluxo criado sem ID retornado.')
    }

    await request(`/vault/automation/flows/${flowId}/steps`, {
        method: 'POST',
        body: {
            steps: [
                {
                    step_order: 1,
                    step_type: stepType,
                    channel: stepChannel,
                    config_json: {
                        next_action_in_days: Number.isFinite(nextDays) ? Math.max(0, nextDays) : 0,
                    },
                    is_active: true,
                },
            ],
        },
    })

    byId('flowName').value = ''
    setStatus(`Fluxo #${flowId} criado com etapa inicial.`)

    await Promise.all([loadFlows(), loadStats()])
    await loadFlowDetail(flowId)
}

function buildRecordsQuery(){
    const params = new URLSearchParams()

    const q = (byId('recordsQ')?.value || '').trim()
    const entityType = (byId('recordsEntityType')?.value || '').trim()
    const lifecycleStage = (byId('recordsLifecycleStage')?.value || '').trim()
    const channelOptin = (byId('recordsChannelOptin')?.value || '').trim()
    const channelType = (byId('recordsChannelType')?.value || '').trim()
    const channelValue = (byId('recordsChannelValue')?.value || '').trim()
    const channelPrimary = (byId('recordsChannelPrimary')?.value || '').trim()
    const channelCanContact = (byId('recordsChannelCanContact')?.value || '').trim()
    const consentStatus = (byId('recordsConsentStatus')?.value || '').trim()
    const consentChannel = (byId('recordsConsentChannel')?.value || '').trim()
    const consentPurpose = (byId('recordsConsentPurpose')?.value || '').trim()
    const perPage = Number(byId('recordsPerPage')?.value || 30)

    if(q) params.set('q', q)
    if(entityType) params.set('entity_type', entityType)
    if(lifecycleStage) params.set('lifecycle_stage', lifecycleStage)
    if(channelOptin) params.set('channel_optin', channelOptin)
    if(channelType) params.set('channel_type', channelType)
    if(channelValue) params.set('channel_value', channelValue)
    if(channelPrimary !== '') params.set('channel_is_primary', channelPrimary)
    if(channelCanContact !== '') params.set('channel_can_contact', channelCanContact)
    if(consentStatus) params.set('consent_status', consentStatus)
    if(consentChannel) params.set('consent_channel', consentChannel)
    if(consentPurpose) params.set('consent_purpose', consentPurpose)
    params.set('per_page', String(Number.isFinite(perPage) && perPage > 0 ? perPage : 30))
    params.set('page', String(Math.max(1, Number(state.recordsPage || 1))))
    params.set('sort_by', state.recordsSortBy || 'next_action_at')
    params.set('sort_dir', state.recordsSortDir || 'desc')

    return params.toString()
}

function buildCurrentFilterPayload(){
    const payload = {}
    const map = [
        ['recordsQ', 'q'],
        ['recordsEntityType', 'entity_type'],
        ['recordsLifecycleStage', 'lifecycle_stage'],
        ['recordsChannelOptin', 'channel_optin'],
        ['recordsChannelType', 'channel_type'],
        ['recordsChannelValue', 'channel_value'],
        ['recordsChannelPrimary', 'channel_is_primary'],
        ['recordsChannelCanContact', 'channel_can_contact'],
        ['recordsConsentStatus', 'consent_status'],
        ['recordsConsentChannel', 'consent_channel'],
        ['recordsConsentPurpose', 'consent_purpose'],
    ]

    map.forEach(([id, key])=>{
        const value = String(byId(id)?.value || '').trim()
        if(value !== ''){
            payload[key] = value
        }
    })

    return payload
}

async function loadRecords(){
    const body = byId('recordsBody')
    if(!body) return

    const query = buildRecordsQuery()
    const payload = await request(`/vault/operational-records${query ? `?${query}` : ''}`)
    const rows = payload?.data || []
    state.recordsPage = Math.max(1, Number(payload?.current_page || 1))
    state.recordsLastPage = Math.max(1, Number(payload?.last_page || 1))
    state.recordsTotal = Math.max(0, Number(payload?.total || rows.length || 0))

    if(!rows.length){
        body.innerHTML = '<tr><td colspan="9" class="text-muted">Nenhum registro encontrado.</td></tr>'
        updateRecordsPaginationUi()
        storeRecordFilters()
        updateBulkSelectionUi()
        return
    }

    body.innerHTML = rows.map((row)=>`
            <tr>
                <td><input class="form-check-input" type="checkbox" data-record-select="${row.id}" ${state.selectedRecordIds.has(Number(row.id)) ? 'checked' : ''}></td>
                <td>${row.id}</td>
                <td>${text(row.name || '—')}</td>
                <td>${text(row.entity_type || '—')}</td>
                <td>${text(row.lifecycle_stage || '—')}</td>
                <td>${text(row.email || row.phone_e164 || row.whatsapp_e164 || '—')}</td>
                <td>${Number(row.score || 0)}</td>
                <td>${text(shortDate(row.next_action_at))}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" data-next7="${row.id}">+7 dias</button>
                    <button class="btn btn-sm btn-outline-secondary" data-select-record="${row.id}">Interagir</button>
                    <button class="btn btn-sm btn-outline-danger" data-delete-record="${row.id}">Excluir</button>
                </td>
            </tr>
        `).join('')

    updateRecordsPaginationUi()
    storeRecordFilters()
    updateBulkSelectionUi()

    const selectPage = byId('recordsSelectPage')
    if(selectPage){
        selectPage.checked = rows.length > 0 && rows.every((row)=>state.selectedRecordIds.has(Number(row.id)))
        selectPage.onchange = (e)=>{
            const checked = Boolean(e.target?.checked)
            rows.forEach((row)=>{
                const id = Number(row.id)
                if(!Number.isFinite(id) || id <= 0) return
                if(checked){
                    state.selectedRecordIds.add(id)
                } else {
                    state.selectedRecordIds.delete(id)
                }
            })
            body.querySelectorAll('[data-record-select]').forEach((chk)=>{
                chk.checked = checked
            })
            updateBulkSelectionUi()
        }
    }

    body.querySelectorAll('[data-record-select]').forEach((chk)=>{
        chk.addEventListener('change', ()=>{
            const id = Number(chk.getAttribute('data-record-select') || 0)
            if(!id) return
            if(chk.checked){
                state.selectedRecordIds.add(id)
            } else {
                state.selectedRecordIds.delete(id)
            }
            updateBulkSelectionUi()
        })
    })

    body.querySelectorAll('[data-next7]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const id = btn.getAttribute('data-next7')
            if(!id) return
            btn.disabled = true
            try {
                const next = new Date(Date.now() + (7 * 24 * 60 * 60 * 1000)).toISOString()
                await request(`/vault/operational-records/${id}`, {
                    method: 'PATCH',
                    body: { next_action_at: next },
                })
                setStatus(`Registro #${id} atualizado para próxima ação em +7 dias.`)
                await loadRecords()
                await loadStats()
            } catch (e) {
                setStatus(e.message || 'Falha ao atualizar próxima ação.', true)
            } finally {
                btn.disabled = false
            }
        })
    })

    body.querySelectorAll('[data-select-record]').forEach((btn)=>{
        btn.addEventListener('click', ()=>{
            const id = btn.getAttribute('data-select-record')
            if(id) byId('interactionRecordId').value = id
            byId('interactionMessage')?.focus()
        })
    })

    body.querySelectorAll('[data-delete-record]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const id = Number(btn.getAttribute('data-delete-record') || 0)
            if(!id) return
            if(!window.confirm(`Excluir o registro #${id}?`)) return

            btn.disabled = true
            try {
                await request(`/vault/operational-records/${id}`, {
                    method: 'DELETE',
                    body: { reason: 'delete_single_ui' },
                })
                state.selectedRecordIds.delete(id)
                updateBulkSelectionUi()
                setStatus(`Registro #${id} excluído.`)
                await Promise.all([loadRecords(), loadStats()])
            } catch (e) {
                setStatus(e.message || 'Falha ao excluir registro.', true)
            } finally {
                btn.disabled = false
            }
        })
    })
}

function updateRecordsPaginationUi(){
    const info = byId('recordsPaginationInfo')
    const prevBtn = byId('recordsPrevPageBtn')
    const nextBtn = byId('recordsNextPageBtn')

    if(info){
        info.textContent = `Página ${state.recordsPage} de ${state.recordsLastPage} · ${state.recordsTotal} registros`
    }
    if(prevBtn){
        prevBtn.disabled = state.recordsPage <= 1
    }
    if(nextBtn){
        nextBtn.disabled = state.recordsPage >= state.recordsLastPage
    }

    updateSortIndicators()
}

function updateBulkActionUi(){
    const action = byId('bulkActionType')?.value || 'update_fields'
    byId('bulkUpdatesWrap')?.classList.toggle('d-none', action !== 'update_fields')
    byId('bulkNextActionWrap')?.classList.toggle('d-none', action !== 'set_next_action')
    byId('bulkConsentWrap')?.classList.toggle('d-none', action !== 'set_consent')
    byId('bulkConsentStatusWrap')?.classList.toggle('d-none', action !== 'set_consent')
    byId('bulkConsentSourceWrap')?.classList.toggle('d-none', action !== 'set_consent')
}

function buildBulkTaskPayload(){
    const scopeType = byId('bulkScopeType')?.value || 'selected_ids'
    const actionType = byId('bulkActionType')?.value || 'update_fields'

    const scope = scopeType === 'filtered'
        ? { filters: buildCurrentFilterPayload() }
        : { ids: Array.from(state.selectedRecordIds) }

    if(scopeType === 'selected_ids' && scope.ids.length === 0){
        throw new Error('Selecione ao menos um registro para executar em lote.')
    }

    let action = {}
    if(actionType === 'update_fields'){
        const raw = (byId('bulkUpdatesJson')?.value || '').trim()
        if(raw === ''){
            throw new Error('Informe o JSON de updates para a ação em lote.')
        }
        let updates = null
        try {
            updates = JSON.parse(raw)
        } catch (_e){
            throw new Error('JSON inválido em updates de lote.')
        }
        if(!updates || typeof updates !== 'object' || Array.isArray(updates)){
            throw new Error('Updates de lote deve ser um objeto JSON.')
        }
        action = { updates }
    } else if(actionType === 'set_next_action'){
        const days = Number(byId('bulkNextActionDays')?.value || 0)
        if(!Number.isFinite(days) || days < 0){
            throw new Error('Dias inválido para próxima ação.')
        }
        action = { days: Math.floor(days) }
    } else if(actionType === 'set_consent'){
        action = {
            channel: byId('bulkConsentChannel')?.value || 'email',
            status: byId('bulkConsentStatus')?.value || 'granted',
            source: (byId('bulkConsentSource')?.value || '').trim(),
        }
    }

    return {
        scope_type: scopeType,
        scope,
        action_type: actionType,
        action,
    }
}

function bindBulkTaskActions(body){
    body.querySelectorAll('[data-open-bulk-task]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const id = Number(btn.getAttribute('data-open-bulk-task') || 0)
            if(!id) return
            await loadBulkTaskDetail(id)
        })
    })

    body.querySelectorAll('[data-cancel-bulk-task]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const id = Number(btn.getAttribute('data-cancel-bulk-task') || 0)
            if(!id) return
            btn.disabled = true
            try {
                await request(`/vault/operational-records/bulk-tasks/${id}/cancel`, { method: 'POST' })
                setStatus(`Cancelamento solicitado para tarefa #${id}.`)
                await loadBulkTasks()
            } catch (e) {
                setStatus(e.message || 'Falha ao cancelar tarefa em lote.', true)
            } finally {
                btn.disabled = false
            }
        })
    })
}

async function loadBulkTasks(){
    const body = byId('bulkTasksBody')
    if(!body) return

    const payload = await request('/vault/operational-records/bulk-tasks?per_page=20')
    const rows = payload?.data || []
    if(!rows.length){
        body.innerHTML = '<tr><td colspan="7" class="text-muted">Sem tarefas.</td></tr>'
        return
    }

    body.innerHTML = rows.map((row)=>{
        const progress = `${row.processed_items}/${row.total_items}`
        const canCancel = ['queued', 'running', 'cancel_requested'].includes(String(row.status || ''))
        return `
            <tr class="${state.selectedBulkTaskId === row.id ? 'is-selected' : ''}">
                <td>${row.id}</td>
                <td>${text(bulkTaskStatusLabel(row.status))}</td>
                <td>${text(bulkActionLabel(row.action_type))}</td>
                <td>${text(bulkScopeLabel(row.scope_type))}</td>
                <td>${progress} (sucesso ${row.success_items} / falha ${row.failed_items})</td>
                <td>${text(shortDate(row.created_at))}</td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary" data-open-bulk-task="${row.id}">Detalhe</button>
                    ${canCancel ? `<button class="btn btn-sm btn-outline-danger" data-cancel-bulk-task="${row.id}">Cancelar</button>` : ''}
                </td>
            </tr>
        `
    }).join('')

    bindBulkTaskActions(body)
}

async function loadBulkTaskDetail(taskId){
    const payload = await request(`/vault/operational-records/bulk-tasks/${taskId}?per_page=30`)
    const task = payload?.task || null
    const rows = payload?.items?.data || []
    state.selectedBulkTaskId = Number(task?.id || taskId)

    const body = byId('bulkTaskItemsBody')
    if(!body) return
    if(!rows.length){
        body.innerHTML = '<tr><td colspan="5" class="text-muted">Sem itens para esta tarefa.</td></tr>'
        return
    }

    body.innerHTML = rows.map((row)=>`
        <tr>
            <td>${row.id}</td>
            <td>${row.operational_record_id || '—'}</td>
            <td>${text(row.status || '—')}</td>
            <td>${text(row.error_message || '—')}</td>
            <td>${text(shortDate(row.processed_at))}</td>
        </tr>
    `).join('')

    if(task){
        setStatus(`Tarefa #${task.id}: ${bulkTaskStatusLabel(task.status)} (${task.processed_items}/${task.total_items}).`)
    }
    await loadBulkTasks()
}

async function runBulkTask(){
    const payload = buildBulkTaskPayload()
    const created = await request('/vault/operational-records/bulk-tasks', {
        method: 'POST',
        body: payload,
    })

    const taskId = Number(created?.task_id || 0)
    setStatus(`Tarefa em lote #${taskId} criada (${created?.total_items || 0} itens).`)

    await loadBulkTasks()
    if(taskId > 0){
        await loadBulkTaskDetail(taskId)
    }
}

async function saveInteraction(){
    const recordId = Number(byId('interactionRecordId')?.value || 0)
    if(!recordId){
        setStatus('Informe um record ID válido para registrar interação.', true)
        return
    }

    const payload = {
        channel: byId('interactionChannel')?.value || 'manual',
        status: byId('interactionStatus')?.value || 'new',
        direction: 'outbound',
        subject: (byId('interactionSubject')?.value || '').trim(),
        message: (byId('interactionMessage')?.value || '').trim(),
    }

    const nextAction = (byId('interactionNextActionAt')?.value || '').trim()
    if(nextAction){
        payload.next_action_at = new Date(nextAction).toISOString()
    }

    await request(`/vault/operational-records/${recordId}/interactions`, {
        method: 'POST',
        body: payload,
    })

    byId('interactionSubject').value = ''
    byId('interactionMessage').value = ''
    setStatus(`Interação salva para o registro #${recordId}.`)

    await Promise.all([loadRecords(), loadStats()])
}

function buildCreateRecordPayload(){
    const payload = {
        name: (byId('createRecordName')?.value || '').trim(),
        entity_type: (byId('createRecordEntityType')?.value || '').trim(),
        lifecycle_stage: (byId('createRecordLifecycleStage')?.value || '').trim(),
        email: (byId('createRecordEmail')?.value || '').trim(),
        phone_e164: (byId('createRecordPhone')?.value || '').trim(),
        whatsapp_e164: (byId('createRecordWhatsapp')?.value || '').trim(),
        city: (byId('createRecordCity')?.value || '').trim(),
        uf: (byId('createRecordUf')?.value || '').trim(),
        consent_source: (byId('createRecordConsentSource')?.value || '').trim(),
    }

    const scoreValue = Number(byId('createRecordScore')?.value || 0)
    payload.score = Number.isFinite(scoreValue) ? Math.max(0, Math.min(100, Math.floor(scoreValue))) : 0

    const compact = {}
    Object.entries(payload).forEach(([key, value])=>{
        if(typeof value === 'string'){
            if(value !== '') compact[key] = value
            return
        }
        compact[key] = value
    })

    return compact
}

async function createOperationalRecord(){
    const payload = buildCreateRecordPayload()
    if(!payload.name && !payload.email && !payload.phone_e164 && !payload.whatsapp_e164){
        throw new Error('Informe ao menos nome, email, telefone ou WhatsApp para criar o registro.')
    }

    const created = await request('/vault/operational-records', {
        method: 'POST',
        body: payload,
    })
    const recordId = Number(created?.record_id || 0)

    ;[
        'createRecordName',
        'createRecordEntityType',
        'createRecordLifecycleStage',
        'createRecordEmail',
        'createRecordPhone',
        'createRecordWhatsapp',
        'createRecordCity',
        'createRecordUf',
        'createRecordConsentSource',
    ].forEach((id)=>{
        const el = byId(id)
        if(el) el.value = ''
    })
    if(byId('createRecordScore')) byId('createRecordScore').value = '0'

    setStatus(`Registro operacional #${recordId || 'novo'} criado.`)
    state.recordsPage = 1
    await Promise.all([loadRecords(), loadStats()])
}

async function deleteSelectedRecords(){
    const ids = Array.from(state.selectedRecordIds)
        .map((id)=>Number(id))
        .filter((id)=>Number.isFinite(id) && id > 0)

    if(!ids.length){
        throw new Error('Selecione ao menos um registro para excluir em lote.')
    }

    const reason = (byId('bulkDeleteReason')?.value || '').trim()
    if(!window.confirm(`Excluir ${ids.length} registro(s) selecionado(s)?`)){
        return
    }

    let deleted = 0
    const results = []
    for (const id of ids){
        try {
            await request(`/vault/operational-records/${id}`, {
                method: 'DELETE',
                body: {
                    reason: reason || 'bulk_delete_ui',
                },
            })
            deleted++
            state.selectedRecordIds.delete(id)
            results.push({
                id,
                ok: true,
                detail: 'Excluído.',
            })
        } catch (e) {
            results.push({
                id,
                ok: false,
                detail: e?.message || 'Falha ao excluir.',
            })
        }
    }

    renderBulkDeleteFeedback(results, ids.length, deleted)
    updateBulkSelectionUi()
    setStatus(`Exclusão em lote concluída: ${deleted}/${ids.length} removidos.`)
    state.recordsPage = 1
    await Promise.all([loadRecords(), loadStats()])
}

function renderBulkDeleteFeedback(results, total, deleted){
    const box = byId('bulkDeleteFeedbackBox')
    const summary = byId('bulkDeleteFeedbackSummary')
    const failedIdsNode = byId('bulkDeleteFeedbackFailedIds')
    const copyBtn = byId('copyBulkDeleteFailedIdsBtn')
    const body = byId('bulkDeleteFeedbackBody')
    if(!box || !summary || !failedIdsNode || !body) return

    const failures = results.filter((item)=>!item.ok)
    const failedIds = failures.map((item)=>item.id)
    lastBulkDeleteFailedIds = failedIds
    const failedCount = failures.length

    box.classList.remove('d-none')
    summary.textContent = `Resultado exclusão em lote: ${deleted}/${total} removidos · ${failedCount} falha(s).`
    failedIdsNode.textContent = failedIds.length ? `IDs com falha: ${failedIds.join(', ')}` : 'Sem falhas.'
    if(copyBtn){
        copyBtn.classList.toggle('d-none', !failedIds.length)
    }

    body.innerHTML = results.map((item)=>`
        <tr>
            <td>${item.id}</td>
            <td>${item.ok ? '<span class="badge text-bg-success">ok</span>' : '<span class="badge text-bg-danger">falha</span>'}</td>
            <td>${text(item.detail || '—')}</td>
        </tr>
    `).join('')
}

async function copyTextToClipboard(text){
    if(typeof navigator !== 'undefined' && navigator.clipboard?.writeText){
        await navigator.clipboard.writeText(text)
        return
    }

    const area = document.createElement('textarea')
    area.value = text
    area.setAttribute('readonly', 'readonly')
    area.style.position = 'fixed'
    area.style.opacity = '0'
    document.body.appendChild(area)
    area.select()
    area.setSelectionRange(0, area.value.length)
    const ok = document.execCommand('copy')
    document.body.removeChild(area)
    if(!ok){
        throw new Error('Não foi possível copiar para a área de transferência.')
    }
}

function bindEvents(){
    byId('autoRefreshBtn')?.addEventListener('click', ()=>refreshAll())
    byId('reloadFlowsBtn')?.addEventListener('click', ()=>loadFlows())
    byId('loadRecordsBtn')?.addEventListener('click', ()=>{
        state.recordsPage = 1
        storeRecordFilters()
        loadRecords()
    })
    byId('recordsPrevPageBtn')?.addEventListener('click', async ()=>{
        if(state.recordsPage <= 1) return
        state.recordsPage -= 1
        storeRecordFilters()
        await loadRecords()
    })
    byId('recordsNextPageBtn')?.addEventListener('click', async ()=>{
        if(state.recordsPage >= state.recordsLastPage) return
        state.recordsPage += 1
        storeRecordFilters()
        await loadRecords()
    })
    byId('recordsPerPage')?.addEventListener('change', ()=>{
        state.recordsPage = 1
        storeRecordFilters()
        loadRecords()
    })
    byId('bulkActionType')?.addEventListener('change', ()=>{
        updateBulkActionUi()
    })
    byId('reloadBulkTasksBtn')?.addEventListener('click', ()=>loadBulkTasks())
    byId('runBulkTaskBtn')?.addEventListener('click', async ()=>{
        const btn = byId('runBulkTaskBtn')
        if(btn) btn.disabled = true
        try {
            await runBulkTask()
        } catch (e) {
            setStatus(e.message || 'Falha ao executar tarefa em lote.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })
    byId('bulkDeleteSelectedBtn')?.addEventListener('click', async ()=>{
        const btn = byId('bulkDeleteSelectedBtn')
        if(btn) btn.disabled = true
        try {
            await deleteSelectedRecords()
        } catch (e) {
            setStatus(e.message || 'Falha ao excluir registros selecionados.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })
    byId('copyBulkDeleteFailedIdsBtn')?.addEventListener('click', async ()=>{
        const ids = Array.isArray(lastBulkDeleteFailedIds) ? lastBulkDeleteFailedIds : []
        if(!ids.length){
            setStatus('Não há IDs com falha para copiar.', true)
            return
        }

        const btn = byId('copyBulkDeleteFailedIdsBtn')
        if(btn) btn.disabled = true
        try {
            await copyTextToClipboard(ids.join(','))
            setStatus(`IDs com falha copiados (${ids.length}).`)
        } catch (e) {
            setStatus(e.message || 'Falha ao copiar IDs com falha.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })
    document.querySelectorAll('[data-records-sort]').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const key = btn.getAttribute('data-records-sort')
            if(!key) return
            if(state.recordsSortBy === key){
                state.recordsSortDir = state.recordsSortDir === 'asc' ? 'desc' : 'asc'
            } else {
                state.recordsSortBy = key
                state.recordsSortDir = key === 'name' ? 'asc' : 'desc'
            }
            state.recordsPage = 1
            storeRecordFilters()
            await loadRecords()
        })
    })

    ;[
        'recordsChannelOptin',
        'recordsChannelType',
        'recordsChannelPrimary',
        'recordsChannelCanContact',
        'recordsConsentStatus',
        'recordsConsentChannel',
    ].forEach((id)=>{
        byId(id)?.addEventListener('change', ()=>{
            state.recordsPage = 1
            storeRecordFilters()
            loadRecords()
        })
    })

    ;[
        'recordsQ',
        'recordsEntityType',
        'recordsLifecycleStage',
        'recordsChannelValue',
        'recordsConsentPurpose',
    ].forEach((id)=>{
        byId(id)?.addEventListener('keydown', (e)=>{
            if(e.key !== 'Enter') return
            e.preventDefault()
            state.recordsPage = 1
            storeRecordFilters()
            loadRecords()
        })
    })

    byId('createFlowBtn')?.addEventListener('click', async ()=>{
        const btn = byId('createFlowBtn')
        if(btn) btn.disabled = true
        try {
            await createFlow()
        } catch (e) {
            setStatus(e.message || 'Falha ao criar fluxo.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })

    byId('createRecordBtn')?.addEventListener('click', async ()=>{
        const btn = byId('createRecordBtn')
        if(btn) btn.disabled = true
        try {
            await createOperationalRecord()
        } catch (e) {
            setStatus(e.message || 'Falha ao criar registro operacional.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })

    byId('saveInteractionBtn')?.addEventListener('click', async ()=>{
        const btn = byId('saveInteractionBtn')
        if(btn) btn.disabled = true
        try {
            await saveInteraction()
        } catch (e) {
            setStatus(e.message || 'Falha ao salvar interação.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })

    byId('saveFlowMetaBtn')?.addEventListener('click', async ()=>{
        const btn = byId('saveFlowMetaBtn')
        if(btn) btn.disabled = true
        try {
            await saveFlowMeta()
        } catch (e) {
            setStatus(e.message || 'Falha ao salvar metadados do fluxo.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })

    byId('reloadFlowDetailBtn')?.addEventListener('click', async ()=>{
        if(!state.selectedFlowId){
            setStatus('Selecione um fluxo para recarregar.', true)
            return
        }
        await loadFlowDetail(state.selectedFlowId)
    })

    byId('addStepRowBtn')?.addEventListener('click', ()=>addStepRow())

    byId('saveStepsBtn')?.addEventListener('click', async ()=>{
        const btn = byId('saveStepsBtn')
        if(btn) btn.disabled = true
        try {
            await saveFlowSteps()
        } catch (e) {
            setStatus(e.message || 'Falha ao salvar etapas.', true)
        } finally {
            if(btn) btn.disabled = false
        }
    })

    byId('refreshRunMonitorBtn')?.addEventListener('click', async ()=>{
        if(!state.selectedRunId){
            setStatus('Selecione uma execução para monitorar.', true)
            return
        }
        await loadRunMonitor(state.selectedRunId)
    })

    byId('toggleRunRealtimeBtn')?.addEventListener('click', async ()=>{
        if(!state.selectedRunId){
            setStatus('Selecione uma execução para ativar tempo real.', true)
            return
        }

        if(state.runPollingTimer){
            stopRunPolling()
            setStatus('Monitor em tempo real pausado.')
            return
        }

        await loadRunMonitor(state.selectedRunId)
        startRunPolling()
        setStatus('Monitor em tempo real ativado.')
    })

    byId('runCancelNoticeCloseBtn')?.addEventListener('click', ()=>{
        hideRunCancelNotice()
    })
}

async function refreshAll(){
    setStatus('Atualizando painel...')
    await Promise.all([loadStats(), loadFlows(), loadRuns(), loadRecords(), loadBulkTasks()])

    if(state.selectedFlowId){
        await loadFlowDetail(state.selectedFlowId)
    }

    if(state.selectedRunId){
        await loadRunMonitor(state.selectedRunId, true)
    }

    if(state.selectedBulkTaskId){
        await loadBulkTaskDetail(state.selectedBulkTaskId)
    }

    setStatus('Painel atualizado.')
}

export default async function initAutomation(){
    if(!byId('automationPage')) return

    restoreRecordFilters()
    updateSortIndicators()
    bindEvents()
    updateBulkActionUi()
    updateBulkSelectionUi()
    setRunRealtime(false)

    try {
        await refreshAll()
    } catch (e) {
        setStatus(e.message || 'Falha ao carregar painel.', true)
    }
}
