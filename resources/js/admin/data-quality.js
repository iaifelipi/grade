import '../../css/admin/data-quality.css'

export default function initDataQualityPage(options = {}){
    const root = options?.root && options.root instanceof Element ? options.root : document
    const onSourceChange = typeof options?.onSourceChange === 'function' ? options.onSourceChange : null
    const byId = (id) => root.querySelector(`#${id}`) || document.getElementById(id)

    const escapeHtml = (value) => {
        if(value === null || value === undefined) return ''
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
    }

    const decodeHtmlEntities = (value) => {
        if(typeof value !== 'string' || !value) return ''
        const textarea = document.createElement('textarea')
        textarea.innerHTML = value
        return textarea.value
    }

    const sourceSelect = byId('dqSourceSelect')
    const columnSelect = byId('dqColumnSelect')
    const previewBtn = byId('dqPreviewBtn')
    const previewBody = byId('dqPreviewBody')
    const previewStatus = byId('dqPreviewStatus')
    const applyBtn = byId('dqApplyBtn')
    const clearBtn = byId('dqClearBtn')
    const applyModalEl = byId('dqApplyModal')
    const applyConfirm = byId('dqApplyConfirm')
    const applyForm = byId('dqApplyForm')
    const applySource = byId('dqApplySource')
    const applyColumn = byId('dqApplyColumn')
    const discardButtons = Array.from(root.querySelectorAll('[data-dq-discard]'))
    const logButtons = Array.from(root.querySelectorAll('[data-dq-log]'))
    const discardModalEl = byId('dqDiscardModal')
    const discardConfirm = byId('dqDiscardConfirm')
    const discardForm = byId('dqDiscardForm')
    const discardText = byId('dqDiscardText')
    const logModalEl = byId('dqLogModal')
    const logName = byId('dqLogName')
    const logStatus = byId('dqLogStatus')
    const logCreatedAt = byId('dqLogCreatedAt')
    const logSourceId = byId('dqLogSourceId')
    const logColumn = byId('dqLogColumn')
    const logRules = byId('dqLogRules')
    const logPath = byId('dqLogPath')
    const logEmpty = byId('dqLogEmpty')
    const logChangesSection = byId('dqLogChangesSection')
    const logChangesBody = byId('dqLogChangesBody')
    const derivedCard = byId('dqDerivedCard')
    const statusUrl = derivedCard?.getAttribute('data-status-url') || ''
    const statusSourceId = derivedCard?.getAttribute('data-source-id') || ''
    let discardTarget = null
    const ruleInputs = Array.from(root.querySelectorAll('input[name="rules[]"]'))
    let previewTimer = null
    const statusClassByValue = {
        queued: 'dq-status dq-status-queued',
        importing: 'dq-status dq-status-importing',
        normalizing: 'dq-status dq-status-importing',
        done: 'dq-status dq-status-done',
        failed: 'dq-status dq-status-failed',
        cancelled: 'dq-status dq-status-cancelled',
    }
    let statusPollTimer = null
    let statusPollInFlight = false

    sourceSelect?.addEventListener('change', () => {
        const id = sourceSelect.value
        if(onSourceChange){
            onSourceChange(id)
            return
        }
        if(!id){
            window.location.href = sourceSelect.dataset.baseUrl || ''
            return
        }
        window.location.href = `${sourceSelect.dataset.baseUrl || ''}?source_id=${id}`
    })

    clearBtn?.addEventListener('click', (event) => {
        if(!onSourceChange) return
        event.preventDefault()
        onSourceChange('')
    })

    const runPreview = async () => {
        const sourceId = sourceSelect?.value
        const columnKey = columnSelect?.value
        if(!sourceId || !columnKey){
            return
        }
        const rules = Array.from(root.querySelectorAll('input[name="rules[]"]:checked')).map(i => i.value)
        previewStatus.textContent = 'Gerando prévia...'
        previewBody.innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>'

        try{
            const res = await fetch(previewBtn.dataset.previewUrl || '', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    source_id: Number(sourceId),
                    column_key: columnKey,
                    rules
                })
            })
            const data = await res.json()
            if(!res.ok || !data.ok){
                throw new Error('Preview failed')
            }
            const items = data.items || []
            if(!items.length){
                previewBody.innerHTML = '<tr><td colspan="3" class="text-muted">Sem dados para prévia.</td></tr>'
            }else{
                previewBody.innerHTML = items.map(item => {
                    const before = item.before ?? ''
                    const after = item.after ?? ''
                    return `
                        <tr>
                            <td>${item.id}</td>
                            <td class="text-break">${before}</td>
                            <td class="text-break">${after}</td>
                        </tr>
                    `
                }).join('')
            }
            previewStatus.textContent = `Prévia gerada (${items.length} linhas).`
        }catch(e){
            console.error(e)
            previewBody.innerHTML = '<tr><td colspan="3" class="text-danger">Não foi possível gerar a prévia.</td></tr>'
            previewStatus.textContent = 'Não foi possível gerar a prévia.'
        }
    }

    const schedulePreview = () => {
        clearTimeout(previewTimer)
        previewTimer = setTimeout(runPreview, 450)
    }

    const resetPreview = () => {
        previewStatus.textContent = 'Nenhuma prévia gerada.'
        previewBody.innerHTML = '<tr><td colspan="3" class="text-muted">Selecione o arquivo, a coluna e as regras.</td></tr>'
    }

    previewBtn?.addEventListener('click', () => {
        runPreview()
    })

    columnSelect?.addEventListener('change', () => {
        ruleInputs.forEach(input => { input.checked = false })
        resetPreview()
    })

    ruleInputs.forEach(input => {
        input.addEventListener('change', () => {
            schedulePreview()
        })
    })

    applyBtn?.addEventListener('click', () => {
        const sourceId = sourceSelect?.value
        const columnKey = columnSelect?.value
        const rules = ruleInputs.filter(i => i.checked).map(i => i.value)
        if(!sourceId || !columnKey || !rules.length){
            return
        }
        if(applySource) applySource.value = sourceId
        if(applyColumn) applyColumn.value = columnKey

        applyForm?.querySelectorAll('input[name="rules[]"]').forEach(el => el.remove())
        rules.forEach(rule => {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = 'rules[]'
            input.value = rule
            applyForm?.appendChild(input)
        })

        if(window.bootstrap?.Modal && applyModalEl){
            window.bootstrap.Modal.getOrCreateInstance(applyModalEl).show()
        }
    })

    applyConfirm?.addEventListener('click', () => {
        applyForm?.submit()
    })

    discardButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            discardTarget = {
                id: btn.getAttribute('data-dq-id'),
                name: btn.getAttribute('data-dq-name') || ''
            }
            if(discardText && discardTarget?.name){
                discardText.textContent = `Descartar a versão editada \"${discardTarget.name}\"? Isso apagará os dados dessa versão.`
            }
            if(window.bootstrap?.Modal && discardModalEl){
                window.bootstrap.Modal.getOrCreateInstance(discardModalEl).show()
            }
        })
    })

    discardConfirm?.addEventListener('click', () => {
        if(!discardTarget?.id || !discardForm) return
        const baseUrl = discardForm.dataset.baseUrl || '/explore/data-quality/discard'
        discardForm.action = `${baseUrl}/${discardTarget.id}`
        discardForm.submit()
    })

    logButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const name = btn.getAttribute('data-dq-name') || '-'
            const status = (btn.getAttribute('data-dq-status') || '').trim()
            const createdAt = btn.getAttribute('data-dq-created-at') || '-'
            const fallbackParentId = btn.getAttribute('data-dq-parent-id') || '-'
            const fallbackFilePath = btn.getAttribute('data-dq-file-path') || '-'
            const raw = btn.getAttribute('data-dq-log-json') || ''
            let log = null

            try{
                const decodedRaw = decodeHtmlEntities(raw)
                log = decodedRaw ? JSON.parse(decodedRaw) : null
            }catch{
                log = null
            }

            if(logName) logName.textContent = `${name} (ID ${btn.getAttribute('data-dq-id') || '-'})`
            if(logCreatedAt) logCreatedAt.textContent = createdAt
            if(logStatus){
                logStatus.className = statusClassByValue[status] || 'dq-status dq-status-default'
                logStatus.textContent = status || 'desconhecido'
            }

            const hasLog = !!(log && typeof log === 'object' && Object.keys(log).length)
            const hasFallbackDetails = Boolean(
                (fallbackParentId && fallbackParentId !== '-')
                || (fallbackFilePath && fallbackFilePath !== '-')
            )
            if(logEmpty) logEmpty.classList.toggle('d-none', hasLog || hasFallbackDetails)

            const sourceIdValue = hasLog ? (log.source_id ?? fallbackParentId ?? '-') : fallbackParentId
            if(logSourceId) logSourceId.textContent = sourceIdValue || '-'

            let columnText = '-'
            if(hasLog && log.column_key){
                const key = String(log.column_key)
                columnText = key === 'registro' ? 'nome' : key
            }else if(hasLog && Array.isArray(log.changed_columns) && log.changed_columns.length){
                columnText = log.changed_columns
                    .map(item => item?.key === 'registro' ? 'nome' : item?.key)
                    .filter(Boolean)
                    .join(', ')
            }
            if(logColumn) logColumn.textContent = columnText || '-'

            const pathValue = hasLog ? (log.output_file_path ?? fallbackFilePath ?? '-') : fallbackFilePath
            if(logPath) logPath.textContent = pathValue || '-'

            if(logRules){
                if(hasLog && Array.isArray(log.rules) && log.rules.length){
                    logRules.textContent = log.rules.join(', ')
                }else if(hasLog && Array.isArray(log.changed_columns) && log.changed_columns.length){
                    const total = Number(log.overrides_count || 0)
                    logRules.textContent = `publicação de alterações • ${total} alterações`
                }else if(hasFallbackDetails){
                    logRules.textContent = 'edição manual (metadados legados)'
                }else{
                    logRules.textContent = '-'
                }
            }

            const hasChangesPreview = hasLog && Array.isArray(log.changes_preview) && log.changes_preview.length
            if(logChangesSection){
                logChangesSection.classList.toggle('d-none', !hasChangesPreview)
            }
            if(logChangesBody){
                if(hasChangesPreview){
                    logChangesBody.innerHTML = log.changes_preview.map(change => `
                        <tr>
                            <td>${escapeHtml(change.lead_id ?? '-')}</td>
                            <td>${escapeHtml(change.column_key === 'registro' ? 'nome' : (change.column_key ?? '-'))}</td>
                            <td class="text-break">${escapeHtml(change.before ?? '')}</td>
                            <td class="text-break">${escapeHtml(change.after ?? '')}</td>
                        </tr>
                    `).join('')
                }else{
                    logChangesBody.innerHTML = '<tr><td colspan="4" class="text-muted">Sem detalhes de alterações.</td></tr>'
                }
            }

            if(window.bootstrap?.Modal && logModalEl){
                window.bootstrap.Modal.getOrCreateInstance(logModalEl).show()
            }
        })
    })

    const applyStatusToRow = (id, status) => {
        const row = document.querySelector(`tr[data-dq-derived-id="${id}"]`)
        if(!row) return
        const badge = row.querySelector('[data-dq-status-badge]')
        if(badge){
            badge.className = statusClassByValue[status] || 'dq-status dq-status-default'
            badge.textContent = status || 'unknown'
            badge.setAttribute('data-dq-status', status || 'unknown')
        }
        const logBtn = row.querySelector('[data-dq-log]')
        if(logBtn){
            logBtn.setAttribute('data-dq-status', status || 'unknown')
        }
    }

    const pollDerivedStatuses = async () => {
        if(!statusUrl || !statusSourceId || statusPollInFlight) return
        statusPollInFlight = true
        try{
            const params = new URLSearchParams({ source_id: String(statusSourceId) })
            const res = await fetch(`${statusUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            const data = await res.json()
            if(!res.ok || !data?.ok || !Array.isArray(data?.items)){
                return
            }
            data.items.forEach(item => {
                applyStatusToRow(item.id, item.status)
            })
        }catch(e){
            console.error('DQ status poll error:', e)
        }finally{
            statusPollInFlight = false
        }
    }

    if(derivedCard && statusUrl && statusSourceId){
        pollDerivedStatuses()
        statusPollTimer = setInterval(pollDerivedStatuses, 5000)
        window.addEventListener('beforeunload', () => {
            if(statusPollTimer){
                clearInterval(statusPollTimer)
            }
        })
    }
}
