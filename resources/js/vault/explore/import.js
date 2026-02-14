export function createImportController(options){
    const {
        elements,
        permissions,
        showToast,
        escapeHtml,
        loadSources,
        reloadGrid,
        getForceImportGate,
        setForceImportGate
    } = options || {}

    const {
        openExploreImportBtn,
        exploreImportModalEl,
        exploreImportModalTitle,
        exploreImportModalModeBadge,
        exploreImportForm,
        exploreImportInput,
        exploreImportDropzone,
        exploreImportUploadWrap,
        exploreImportSelectedList,
        exploreImportInvalidAlert,
        exploreImportBar,
        exploreImportEventWrap,
        exploreImportEventBody,
        exploreQueueHealthAlert,
        exploreSourcesPanelWrap,
        exploreImportStatusBody,
        exploreImportSubmitBtn,
        exploreImportAutoCloseHint,
        exploreImportCloseBtn,
        exploreLockedOverlay,
        exploreSourcesRefreshBtn,
        exploreSourcesCheckAll,
        exploreSourcesPurgeBtn,
        exploreSourcesPurgeModalEl,
        exploreSourcesPurgeInput,
        exploreSourcesPurgeConfirmBtn,
        exploreSourcesPurgeProgressWrap,
        exploreSourcesPurgeProgressText,
        exploreSourcesPurgeProgressCount,
        exploreSourcesPurgeProgressBar
    } = elements || {}

    const canImportLeads = !!permissions?.canImportLeads
    const canDeleteSources = !!permissions?.canDeleteSources
    const canCancelImport = !!permissions?.canCancelImport
    const canReprocessImport = !!permissions?.canReprocessImport

    let importPollTimer = null
    let healthPollTimer = null
    let importAutoCloseTimer = null
    let importAutoCloseRemaining = 0
    let isUploading = false
    const importTrackedNames = new Set()
    const importTrackedSourceIds = new Set()
    let importLastTrackedName = ''
    let importLastUploadedSourceId = 0
    let importNavigatedToLatestSource = false
    let importFinished = false
    let importLastRows = []
    const selectedSourceIds = new Set()
    let importModalMode = 'upload'
    const IMPORT_SUBMIT_LABEL = 'Importar'

    const setImportSubmitBusy = (busy)=>{
        if(!exploreImportSubmitBtn) return
        exploreImportSubmitBtn.disabled = !!busy
        exploreImportSubmitBtn.textContent = IMPORT_SUBMIT_LABEL
        exploreImportSubmitBtn.classList.toggle('is-submitting', !!busy)
    }

    const filterAllowedImportFiles = (files)=>{
        const allow = new Set(['csv','xlsx','txt'])
        return (files || []).filter(file=>{
            const parts = String(file?.name || '').toLowerCase().split('.')
            const ext = parts.length > 1 ? parts.pop() : ''
            return allow.has(ext)
        })
    }

    const setImportInputFiles = (files)=>{
        if(!exploreImportInput) return
        const dt = new DataTransfer()
        files.forEach(file=>dt.items.add(file))
        exploreImportInput.files = dt.files
    }

    const renderImportSelectedFiles = ()=>{
        if(!exploreImportSelectedList || !exploreImportInput) return
        const listEl = exploreImportSelectedList.querySelector('ul')
        if(!listEl) return
        const files = Array.from(exploreImportInput.files || [])
        if(!files.length){
            exploreImportSelectedList.classList.add('d-none')
            listEl.innerHTML = ''
            return
        }
        exploreImportSelectedList.classList.remove('d-none')
        listEl.innerHTML = files
            .map(file=>`<li class="list-group-item px-0 py-1">${escapeHtml(file.name)} <small class="text-muted">(${Math.round((file.size || 0)/1024)} KB)</small></li>`)
            .join('')
    }

    const showImportInvalid = (count)=>{
        if(!exploreImportInvalidAlert) return
        if(count > 0){
            exploreImportInvalidAlert.textContent = `${count} arquivo(s) ignorado(s) por extensão inválida.`
            exploreImportInvalidAlert.classList.remove('d-none')
            return
        }
        exploreImportInvalidAlert.classList.add('d-none')
    }

    const statusBadgeClass = (status)=>{
        if(status === 'done') return 'success'
        if(status === 'importing' || status === 'normalizing') return 'warning'
        if(status === 'uploading') return 'info'
        if(status === 'failed') return 'danger'
        if(status === 'cancelled') return 'secondary'
        if(status === 'queued') return 'secondary'
        return 'secondary'
    }

    const statusLabel = (status)=>{
        if(status === 'done') return 'Concluido'
        if(status === 'importing') return 'Importando'
        if(status === 'uploading') return 'Enviando'
        if(status === 'normalizing') return 'Normalizando'
        if(status === 'queued') return 'Na fila'
        if(status === 'failed') return 'Falhou'
        if(status === 'cancelled') return 'Cancelado'
        return String(status || '-')
    }

    const isBusyStatus = (status)=>{
        return ['queued', 'uploading', 'importing', 'normalizing'].includes(String(status || ''))
    }

    const renderStatusBadge = (status)=>{
        const busy = isBusyStatus(status)
        return `
            <span class="badge text-bg-${statusBadgeClass(status)} explore-status-pill ${busy ? 'explore-status-busy' : ''}">
                ${statusLabel(status)}
                ${busy ? '<span class="explore-status-dots" aria-hidden="true"></span>' : ''}
            </span>
        `
    }

    const setImportModalMode = (mode)=>{
        importModalMode = mode === 'panel' ? 'panel' : 'upload'
        const isPanel = importModalMode === 'panel'
        if(exploreImportModalTitle){
            exploreImportModalTitle.textContent = isPanel ? 'Painel de Arquivos' : 'Importar arquivos'
        }
        if(exploreImportModalModeBadge){
            exploreImportModalModeBadge.textContent = isPanel ? 'Painel completo' : 'Envio rápido'
            exploreImportModalModeBadge.classList.toggle('text-bg-primary', isPanel)
            exploreImportModalModeBadge.classList.toggle('text-bg-light', !isPanel)
        }
        if(exploreImportUploadWrap){
            exploreImportUploadWrap.classList.toggle('d-none', isPanel)
        }
        if(exploreSourcesPanelWrap){
            exploreSourcesPanelWrap.classList.toggle('d-none', !isPanel)
        }
        if(exploreImportEventWrap){
            exploreImportEventWrap.classList.toggle('d-none', isPanel)
        }
        if(exploreImportSubmitBtn){
            exploreImportSubmitBtn.classList.toggle('d-none', isPanel)
        }
        if(exploreImportAutoCloseHint){
            exploreImportAutoCloseHint.classList.toggle('d-none', isPanel || importAutoCloseRemaining <= 0)
        }
        if(exploreSourcesPurgeBtn){
            exploreSourcesPurgeBtn.classList.toggle('d-none', !isPanel)
        }
        if(exploreSourcesRefreshBtn){
            exploreSourcesRefreshBtn.classList.toggle('d-none', !isPanel)
        }
    }

    const setExploreLocked = (locked)=>{
        if(exploreLockedOverlay){
            exploreLockedOverlay.classList.toggle('d-none', !locked)
        }
        if(exploreImportCloseBtn){
            exploreImportCloseBtn.classList.toggle('d-none', !!locked)
        }
        if(exploreImportModalEl){
            if(locked){
                exploreImportModalEl.setAttribute('data-bs-backdrop', 'static')
                exploreImportModalEl.setAttribute('data-bs-keyboard', 'false')
            }else{
                exploreImportModalEl.removeAttribute('data-bs-backdrop')
                exploreImportModalEl.removeAttribute('data-bs-keyboard')
            }
        }
    }

    const sourceActionButtons = (row)=>{
        const status = String(row.status || '')
        if(status === 'importing'){
            if(!canCancelImport) return ''
            return `<button class="btn btn-sm btn-outline-danger" type="button" data-source-action="cancel" data-source-id="${row.id}">Cancelar</button>`
        }
        if(['failed','done','cancelled'].includes(status)){
            if(!canReprocessImport) return ''
            return `<button class="btn btn-sm btn-outline-secondary" type="button" data-source-action="reprocess" data-source-id="${row.id}">Reprocessar</button>`
        }
        return ''
    }

    const updateSourcesSelectionState = ()=>{
        if(exploreSourcesPurgeBtn){
            exploreSourcesPurgeBtn.disabled = !canDeleteSources || selectedSourceIds.size === 0
        }
        if(exploreSourcesCheckAll){
            const rows = Array.from(exploreImportStatusBody?.querySelectorAll('input[data-source-check]') || [])
            const selectable = rows.filter(chk=>!chk.disabled)
            const checked = selectable.filter(chk=>chk.checked)
            exploreSourcesCheckAll.checked = selectable.length > 0 && checked.length === selectable.length
        }
    }

    const renderImportStatusRows = (rows)=>{
        if(!exploreImportStatusBody) return
        if(!rows.length){
            exploreImportStatusBody.innerHTML = '<tr><td colspan="8" class="text-muted">Nenhum arquivo importado.</td></tr>'
            selectedSourceIds.clear()
            updateSourcesSelectionState()
            return
        }

        exploreImportStatusBody.innerHTML = rows.map(row=>{
            const pct = Number(row.progress_percent || 0)
            const id = Number(row.id || 0)
            const isSelectable = !['uploading'].includes(String(row.status || '')) && id > 0
            const checked = selectedSourceIds.has(id) ? 'checked' : ''
            return `
                <tr>
                    <td>
                        ${isSelectable ? `<input type="checkbox" data-source-check="1" data-source-id="${id}" ${checked}>` : ''}
                    </td>
                    <td>${id > 0 ? `#${id}` : '—'}</td>
                    <td class="text-truncate" style="max-width:320px">
                        ${id > 0 ? `<a href="/vault/explore/source/${id}" class="text-decoration-none">${escapeHtml(row.original_name || '-')}</a>` : escapeHtml(row.original_name || '-')}
                    </td>
                    <td>${renderStatusBadge(row.status)}</td>
                    <td>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar" style="width:${Math.max(0, Math.min(100, pct))}%"></div>
                        </div>
                    </td>
                    <td>${Number(row.inserted_rows || 0)}</td>
                    <td>${row.created_at ? new Date(row.created_at).toLocaleString() : '—'}</td>
                    <td>${sourceActionButtons(row)}</td>
                </tr>
            `
        }).join('')

        exploreImportStatusBody.querySelectorAll('input[data-source-check]').forEach(chk=>{
            chk.addEventListener('change', ()=>{
                const id = Number(chk.getAttribute('data-source-id'))
                if(!id) return
                if(chk.checked) selectedSourceIds.add(id)
                else selectedSourceIds.delete(id)
                updateSourcesSelectionState()
            })
        })

        exploreImportStatusBody.querySelectorAll('button[data-source-action]').forEach(btn=>{
            btn.addEventListener('click', ()=>handleSourceAction(btn))
        })

        updateSourcesSelectionState()
    }

    const renderImportEventRows = (rows)=>{
        if(!exploreImportEventBody) return
        if(!rows.length){
            exploreImportEventBody.innerHTML = '<tr><td colspan="4" class="text-muted">Nenhum envio iniciado.</td></tr>'
            return
        }
        exploreImportEventBody.innerHTML = rows.map(row=>{
            const pct = Number(row.progress_percent || 0)
            return `
                <tr>
                    <td class="text-truncate" style="max-width:360px">${escapeHtml(row.original_name || '-')}</td>
                    <td>${renderStatusBadge(row.status)}</td>
                    <td>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar" style="width:${Math.max(0, Math.min(100, pct))}%"></div>
                        </div>
                    </td>
                    <td>${Number(row.inserted_rows || 0)}</td>
                </tr>
            `
        }).join('')
    }

    const stopImportPolling = ()=>{
        if(importPollTimer){
            window.clearInterval(importPollTimer)
            importPollTimer = null
        }
    }

    const renderQueueHealth = (payload)=>{
        if(!exploreQueueHealthAlert) return
        const level = String(payload?.overall || 'healthy')
        const message = String(payload?.message || '')
        const details = Array.isArray(payload?.details) ? payload.details : []

        if(level === 'healthy'){
            exploreQueueHealthAlert.classList.add('d-none')
            exploreQueueHealthAlert.textContent = ''
            exploreQueueHealthAlert.classList.remove('alert-danger', 'alert-warning', 'alert-info')
            return
        }

        exploreQueueHealthAlert.classList.remove('d-none')
        exploreQueueHealthAlert.classList.remove('alert-danger', 'alert-warning', 'alert-info')
        exploreQueueHealthAlert.classList.add(level === 'critical' ? 'alert-danger' : 'alert-warning')
        const suffix = details.length ? ` (${details.join(' | ')})` : ''
        exploreQueueHealthAlert.innerHTML = `
            <span class="explore-health-blink">${level === 'critical' ? 'Atenção' : 'Aviso'}:</span>
            ${escapeHtml(message || 'Fila com instabilidade')}${escapeHtml(suffix)}
        `
    }

    const refreshQueueHealth = async ()=>{
        const url = String(window.exploreConfig?.sourcesHealthUrl || '').trim()
        if(!url){
            return
        }

        try{
            const healthUrl = new URL(url, window.location.origin)
            const activeTenantUuid = String(window.exploreConfig?.activeTenantUuid || '').trim()
            if(activeTenantUuid){
                healthUrl.searchParams.set('tenant_uuid', activeTenantUuid)
            }
            healthUrl.searchParams.set('_ts', String(Date.now()))

            const res = await fetch(healthUrl.toString(), {
                headers:{ 'X-Requested-With':'XMLHttpRequest' },
                cache:'no-store'
            })
            if(!res.ok){
                return
            }
            const payload = await res.json().catch(()=>null)
            renderQueueHealth(payload || {})
        }catch(_e){
            // silent fail to avoid noisy UX in intermittent network blips
        }
    }

    const stopHealthPolling = ()=>{
        if(healthPollTimer){
            window.clearInterval(healthPollTimer)
            healthPollTimer = null
        }
    }

    const startHealthPolling = ()=>{
        stopHealthPolling()
        refreshQueueHealth()
        healthPollTimer = window.setInterval(refreshQueueHealth, 6000)
    }

    const stopImportAutoCloseCountdown = ()=>{
        if(importAutoCloseTimer){
            window.clearInterval(importAutoCloseTimer)
            importAutoCloseTimer = null
        }
        importAutoCloseRemaining = 0
        if(exploreImportAutoCloseHint){
            exploreImportAutoCloseHint.classList.add('d-none')
            exploreImportAutoCloseHint.textContent = ''
        }
    }

    const startImportAutoCloseCountdown = ()=>{
        if(!exploreImportModalEl || !window.bootstrap?.Modal) return
        if(importAutoCloseTimer) return
        importAutoCloseRemaining = 5
        if(exploreImportAutoCloseHint){
            exploreImportAutoCloseHint.classList.remove('d-none')
            exploreImportAutoCloseHint.textContent = `Fechando em ${importAutoCloseRemaining}s...`
        }
        importAutoCloseTimer = window.setInterval(()=>{
            importAutoCloseRemaining -= 1
            if(exploreImportAutoCloseHint){
                exploreImportAutoCloseHint.textContent = `Fechando em ${Math.max(0, importAutoCloseRemaining)}s...`
            }
            if(importAutoCloseRemaining <= 0){
                stopImportAutoCloseCountdown()
                window.bootstrap.Modal.getOrCreateInstance(exploreImportModalEl).hide()
            }
        }, 1000)
    }

    const trackedImportsAllDone = (rows)=>{
        if(importTrackedSourceIds.size){
            for(const id of importTrackedSourceIds){
                const match = rows.find(row=>Number(row?.id || 0) === Number(id))
                if(!match || String(match.status || '') !== 'done'){
                    return false
                }
            }
            return true
        }

        if(!importTrackedNames.size) return false
        for(const name of importTrackedNames){
            const match = rows.find(row=>String(row.original_name || '') === name)
            if(!match || match.status !== 'done'){
                return false
            }
        }
        return true
    }

    const openLatestImportedSourceIfReady = (rows)=>{
        if(!trackedImportsAllDone(rows) || importNavigatedToLatestSource){
            return false
        }

        importNavigatedToLatestSource = true
        let targetId = Number(importLastUploadedSourceId || 0)

        if(targetId <= 0){
            const doneTracked = rows
                .filter(row=>importTrackedSourceIds.has(Number(row?.id || 0)) && String(row?.status || '') === 'done')
                .map(row=>Number(row?.id || 0))
                .filter(id=>id > 0)
            if(doneTracked.length){
                targetId = Math.max(...doneTracked)
            }
        }

        if(targetId <= 0 && importLastTrackedName){
            const byName = rows
                .filter(row=>String(row?.original_name || '') === importLastTrackedName && String(row?.status || '') === 'done')
                .map(row=>Number(row?.id || 0))
                .filter(id=>id > 0)
            if(byName.length){
                targetId = Math.max(...byName)
            }
        }

        if(targetId > 0){
            const template = window.exploreConfig?.sourceSelectUrlTemplate || '/vault/explore/source/__ID__'
            window.location.href = String(template).replace('__ID__', String(targetId))
            return true
        }

        importNavigatedToLatestSource = false
        return false
    }

    const refreshImportStatuses = async ()=>{
        if(!window.exploreConfig?.sourcesStatusUrl) return
        try{
            const statusUrl = new URL(window.exploreConfig.sourcesStatusUrl, window.location.origin)
            const activeTenantUuid = String(window.exploreConfig?.activeTenantUuid || '').trim()
            if(activeTenantUuid){
                statusUrl.searchParams.set('tenant_uuid', activeTenantUuid)
            }
            if(importTrackedSourceIds.size){
                statusUrl.searchParams.set('ids', Array.from(importTrackedSourceIds).join(','))
            }
            statusUrl.searchParams.set('_ts', String(Date.now()))
            const res = await fetch(statusUrl.toString(), {
                headers:{ 'X-Requested-With':'XMLHttpRequest' },
                cache: 'no-store'
            })
            if(!res.ok){
                return
            }
            const data = await res.json()
            const rows = Array.isArray(data?.sources) ? data.sources : []
            importLastRows = rows

            if(importModalMode === 'panel'){
                renderImportStatusRows(importLastRows)
                stopImportAutoCloseCountdown()
                if(openLatestImportedSourceIfReady(rows)){
                    stopImportPolling()
                    return
                }
                if(trackedImportsAllDone(rows)){
                    importFinished = true
                    setImportSubmitBusy(false)
                }
            }else{
                let trackedRows = []
                if(importTrackedSourceIds.size){
                    trackedRows = Array.from(importTrackedSourceIds).map((sourceId)=>{
                        const match = rows.find(row=>Number(row?.id || 0) === Number(sourceId))
                        if(match) return match
                        return {
                            id: Number(sourceId),
                            original_name: importLastTrackedName || `source#${sourceId}`,
                            status: 'uploading',
                            progress_percent: 0,
                            inserted_rows: 0,
                        }
                    })
                } else {
                    trackedRows = Array.from(importTrackedNames).map((name)=>{
                        const match = rows.find(row=>String(row.original_name || '') === name)
                        if(match) return match
                        return {
                            original_name: name,
                            status: 'uploading',
                            progress_percent: 0,
                            inserted_rows: 0,
                        }
                    })
                }
                renderImportEventRows(trackedRows)

                if(openLatestImportedSourceIfReady(rows)){
                    stopImportPolling()
                    return
                }

                if(trackedImportsAllDone(rows)){
                    importFinished = true
                    setImportSubmitBusy(false)
                    stopImportPolling()
                    startImportAutoCloseCountdown()
                    await loadSources?.()
                }
            }
        }catch(e){
            console.error('Import status error:', e)
        }
    }

    const startImportPolling = ()=>{
        stopImportPolling()
        refreshImportStatuses()
        importPollTimer = window.setInterval(refreshImportStatuses, 1200)
    }

    const sourceUrlFromTemplate = (template, id)=>{
        return String(template || '').replace('__ID__', String(id))
    }

    const sourceActionRequest = async (url, payload = null)=>{
        const activeTenantUuid = String(window.exploreConfig?.activeTenantUuid || '').trim()
        let bodyPayload = payload
        if(bodyPayload && typeof bodyPayload === 'object' && activeTenantUuid){
            bodyPayload = { ...bodyPayload, tenant_uuid: activeTenantUuid }
        }

        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With':'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...(bodyPayload ? { 'Content-Type': 'application/json' } : {})
            },
            ...(bodyPayload ? { body: JSON.stringify(bodyPayload) } : {})
        })
        const responsePayload = await res.json().catch(()=>null)
        if(!res.ok){
            const err = new Error(
                responsePayload?.message
                || responsePayload?.error
                || `HTTP ${res.status}`
            )
            err.status = res.status
            err.payload = responsePayload
            throw err
        }
        return responsePayload || { ok:true }
    }

    const handleSourceAction = async (button)=>{
        const action = String(button.getAttribute('data-source-action') || '')
        const id = Number(button.getAttribute('data-source-id') || 0)
        if(!action || !id) return

        button.disabled = true
        try{
            if(action === 'cancel'){
                const url = sourceUrlFromTemplate(window.exploreConfig?.sourceCancelUrlTemplate, id)
                await sourceActionRequest(url, {})
                showToast?.(`Importação cancelada (#${id}).`)
            }else if(action === 'reprocess'){
                const url = sourceUrlFromTemplate(window.exploreConfig?.sourceReprocessUrlTemplate, id)
                await sourceActionRequest(url, {})
                showToast?.(`Reprocessamento iniciado (#${id}).`)
            }
            await refreshImportStatuses()
        }catch(e){
            console.error('Source action error:', e)
            showToast?.(e?.message || 'Não foi possível concluir a ação.')
        }finally{
            button.disabled = false
        }
    }

    const purgeSelectedSources = async ()=>{
        if(!canDeleteSources || !selectedSourceIds.size || !window.exploreConfig?.sourcesPurgeSelectedUrl) return
        const ids = Array.from(selectedSourceIds)
        const deletedSet = new Set(ids.map(id=>Number(id)).filter(id=>id > 0))
        const total = ids.length
        const chunkSize = 20
        let done = 0

        const updatePurgeProgress = ()=>{
            const pct = total > 0 ? Math.round((done / total) * 100) : 0
            if(exploreSourcesPurgeProgressWrap){
                exploreSourcesPurgeProgressWrap.classList.remove('d-none')
            }
            if(exploreSourcesPurgeProgressText){
                exploreSourcesPurgeProgressText.innerHTML = done < total
                    ? 'Excluindo arquivos selecionados<span class="explore-status-dots" aria-hidden="true"></span>'
                    : 'Finalizando<span class="explore-status-dots" aria-hidden="true"></span>'
            }
            if(exploreSourcesPurgeProgressCount){
                exploreSourcesPurgeProgressCount.textContent = `${done}/${total}`
            }
            if(exploreSourcesPurgeProgressBar){
                exploreSourcesPurgeProgressBar.style.width = `${pct}%`
            }
        }

        if(exploreSourcesPurgeConfirmBtn){
            exploreSourcesPurgeConfirmBtn.disabled = true
            exploreSourcesPurgeConfirmBtn.classList.add('is-submitting')
        }

        updatePurgeProgress()

        let deletedSuccessfully = false

        try{
            for(let i = 0; i < ids.length; i += chunkSize){
                const batch = ids.slice(i, i + chunkSize)
                await sourceActionRequest(window.exploreConfig.sourcesPurgeSelectedUrl, { ids: batch })
                done += batch.length
                updatePurgeProgress()
            }

            deletedSuccessfully = true

            selectedSourceIds.clear()
            if(exploreSourcesPurgeInput) exploreSourcesPurgeInput.value = ''
            if(exploreSourcesPurgeModalEl && window.bootstrap?.Modal){
                window.bootstrap.Modal.getOrCreateInstance(exploreSourcesPurgeModalEl).hide()
            }
            showToast?.('Arquivos selecionados excluídos.')

            const currentSourceValue = String(
                document.getElementById('sourceSelect')?.value
                || document.getElementById('sourceSelectMobile')?.value
                || ''
            ).trim()
            const currentSourceId = Number(currentSourceValue || 0)
            if(currentSourceId > 0 && deletedSet.has(currentSourceId)){
                const clearUrl = window.exploreConfig?.sourceClearUrl || '/vault/explore/source/clear'
                window.location.href = clearUrl
                return
            }

            await refreshImportStatuses()
            await loadSources?.()
            if(reloadGrid){
                await reloadGrid()
            }else{
                window.location.reload()
            }
        }catch(e){
            console.error('Purge selected sources error:', e)
            if(!deletedSuccessfully){
                showToast?.(e?.message || 'Falha ao excluir os arquivos selecionados.')
                return
            }

            // Exclusão já confirmada no backend; apenas atualização visual falhou.
            showToast?.('Arquivos excluídos. Atualize a tela para sincronizar a visualização.')
        }finally{
            if(exploreSourcesPurgeConfirmBtn){
                exploreSourcesPurgeConfirmBtn.disabled = false
                exploreSourcesPurgeConfirmBtn.classList.remove('is-submitting')
            }
            if(exploreSourcesPurgeProgressWrap){
                exploreSourcesPurgeProgressWrap.classList.add('d-none')
            }
            if(exploreSourcesPurgeProgressBar){
                exploreSourcesPurgeProgressBar.style.width = '0%'
            }
            if(exploreSourcesPurgeProgressCount){
                exploreSourcesPurgeProgressCount.textContent = '0/0'
            }
            if(exploreSourcesPurgeProgressText){
                exploreSourcesPurgeProgressText.textContent = 'Excluindo...'
            }
        }
    }

    const resetImportModalState = ()=>{
        if(exploreImportForm) exploreImportForm.reset()
        if(exploreImportBar) exploreImportBar.style.width = '0%'
        showImportInvalid(0)
        renderImportSelectedFiles()
        renderImportEventRows([])
        importTrackedNames.clear()
        importTrackedSourceIds.clear()
        importLastTrackedName = ''
        importLastUploadedSourceId = 0
        importNavigatedToLatestSource = false
        importFinished = false
        selectedSourceIds.clear()
        importLastRows = []
        renderImportStatusRows([])
        if(exploreSourcesCheckAll) exploreSourcesCheckAll.checked = false
        setImportModalMode('upload')
        stopImportPolling()
        stopImportAutoCloseCountdown()
        if(exploreImportSubmitBtn){
            setImportSubmitBusy(false)
        }
        isUploading = false
    }

    const bind = ()=>{
        exploreImportInput?.addEventListener('change', ()=>{
            const files = Array.from(exploreImportInput.files || [])
            const valid = filterAllowedImportFiles(files)
            showImportInvalid(files.length - valid.length)
            if(valid.length !== files.length){
                setImportInputFiles(valid)
            }
            renderImportSelectedFiles()
        })

        if(exploreImportDropzone && exploreImportInput){
            const setDropHighlight = (on)=>exploreImportDropzone.classList.toggle('border-primary', !!on)
            ;['dragenter','dragover'].forEach(evt=>{
                exploreImportDropzone.addEventListener(evt, (e)=>{
                    e.preventDefault()
                    e.stopPropagation()
                    setDropHighlight(true)
                })
            })
            ;['dragleave','drop'].forEach(evt=>{
                exploreImportDropzone.addEventListener(evt, (e)=>{
                    e.preventDefault()
                    e.stopPropagation()
                    setDropHighlight(false)
                })
            })
            exploreImportDropzone.addEventListener('click', (e)=>{
                if(e.target === exploreImportInput) return
                exploreImportInput.click()
            })
            exploreImportDropzone.addEventListener('drop', (e)=>{
                const files = Array.from(e.dataTransfer?.files || [])
                if(!files.length) return
                const valid = filterAllowedImportFiles(files)
                showImportInvalid(files.length - valid.length)
                if(!valid.length) return
                setImportInputFiles(valid)
                renderImportSelectedFiles()
            })
        }

        exploreImportForm?.addEventListener('submit', (e)=>{
            e.preventDefault()
            if(isUploading){
                showToast?.('Upload em andamento. Aguarde concluir.')
                return
            }
            if(!canImportLeads || !exploreImportInput || !window.exploreConfig?.sourcesUploadUrl){
                return
            }
            const files = Array.from(exploreImportInput.files || [])
            if(!files.length){
                showImportInvalid(1)
                return
            }

            const formData = new FormData(exploreImportForm)
            const activeTenantUuid = String(window.exploreConfig?.activeTenantUuid || '').trim()
            if(activeTenantUuid){
                formData.set('tenant_uuid', activeTenantUuid)
            }
            importTrackedNames.clear()
            importTrackedSourceIds.clear()
            importLastTrackedName = ''
            importLastUploadedSourceId = 0
            importNavigatedToLatestSource = false
            importFinished = false
            files.forEach(file=>importTrackedNames.add(String(file.name)))
            importLastTrackedName = files.length ? String(files[files.length - 1]?.name || '') : ''
            stopImportAutoCloseCountdown()
            startImportPolling()
            isUploading = true

            if(exploreImportSubmitBtn){
                setImportSubmitBusy(true)
            }

            const xhr = new XMLHttpRequest()
            xhr.open('POST', window.exploreConfig.sourcesUploadUrl)
            xhr.setRequestHeader(
                'X-CSRF-TOKEN',
                document.querySelector('meta[name="csrf-token"]')?.content || ''
            )

            xhr.upload.onprogress = (evt)=>{
                if(!exploreImportBar || !evt.lengthComputable) return
                const pct = (evt.loaded / evt.total) * 100
                exploreImportBar.style.width = `${pct}%`
            }

            const finish = ()=>{
                isUploading = false
                if(exploreImportBar){
                    exploreImportBar.style.width = '0%'
                }
                if(exploreImportInput){
                    exploreImportInput.value = ''
                }
                renderImportSelectedFiles()
            }

            xhr.onload = ()=>{
                let response = {}
                try{
                    response = JSON.parse(String(xhr.responseText || '{}'))
                    const sources = Array.isArray(response?.sources) ? response.sources : []
                    if(sources.length){
                        sources.forEach(source=>{
                            const sourceId = Number(source?.id || 0)
                            if(sourceId > 0){
                                importTrackedSourceIds.add(sourceId)
                            }
                        })
                        importLastUploadedSourceId = Number(sources[sources.length - 1]?.id || 0)
                    }
                }catch(_e){
                    // ignore malformed payload; name-based fallback remains active
                }
                if(xhr.status < 200 || xhr.status >= 300){
                    importTrackedNames.clear()
                    importTrackedSourceIds.clear()
                    importLastTrackedName = ''
                    importLastUploadedSourceId = 0
                    showToast?.(response?.message || 'Falha ao importar arquivo.')
                    setImportSubmitBusy(false)
                }
                finish()
                refreshImportStatuses()
            }
            xhr.onerror = ()=>{
                importTrackedNames.clear()
                importTrackedSourceIds.clear()
                importLastTrackedName = ''
                importLastUploadedSourceId = 0
                showToast?.('Falha de rede ao enviar arquivo.')
                setImportSubmitBusy(false)
                finish()
            }
            xhr.onabort = ()=>{
                importTrackedNames.clear()
                importTrackedSourceIds.clear()
                importLastTrackedName = ''
                importLastUploadedSourceId = 0
                showToast?.('Upload cancelado.')
                setImportSubmitBusy(false)
                finish()
            }
            xhr.send(formData)
        })

        openExploreImportBtn?.addEventListener('click', ()=>{
            if(!canImportLeads){
                showToast?.('Sem permissão para importar arquivos')
                return
            }
            if(!isUploading){
                importTrackedNames.clear()
                importTrackedSourceIds.clear()
                importLastTrackedName = ''
                importLastUploadedSourceId = 0
                importNavigatedToLatestSource = false
                importFinished = false
                renderImportEventRows([])
                stopImportAutoCloseCountdown()
            }
            setImportModalMode('upload')
        })

        exploreSourcesRefreshBtn?.addEventListener('click', ()=>{
            refreshImportStatuses()
        })

        exploreSourcesCheckAll?.addEventListener('change', ()=>{
            const checked = !!exploreSourcesCheckAll.checked
            exploreImportStatusBody?.querySelectorAll('input[data-source-check]').forEach(chk=>{
                if(chk.disabled) return
                chk.checked = checked
                const id = Number(chk.getAttribute('data-source-id'))
                if(!id) return
                if(checked) selectedSourceIds.add(id)
                else selectedSourceIds.delete(id)
            })
            updateSourcesSelectionState()
        })

        exploreSourcesPurgeBtn?.addEventListener('click', ()=>{
            if(!canDeleteSources || !selectedSourceIds.size) return
            if(exploreSourcesPurgeInput) exploreSourcesPurgeInput.value = ''
            if(exploreSourcesPurgeProgressWrap) exploreSourcesPurgeProgressWrap.classList.add('d-none')
            if(exploreSourcesPurgeProgressBar) exploreSourcesPurgeProgressBar.style.width = '0%'
            if(exploreSourcesPurgeProgressCount) exploreSourcesPurgeProgressCount.textContent = '0/0'
            if(exploreSourcesPurgeProgressText) exploreSourcesPurgeProgressText.textContent = 'Excluindo...'
            if(exploreSourcesPurgeModalEl && window.bootstrap?.Modal){
                window.bootstrap.Modal.getOrCreateInstance(exploreSourcesPurgeModalEl).show()
            }
        })

        exploreSourcesPurgeConfirmBtn?.addEventListener('click', ()=>{
            const value = String(exploreSourcesPurgeInput?.value || '').trim().toUpperCase()
            if(value !== 'EXCLUIR'){
                showToast?.('Digite EXCLUIR para confirmar')
                return
            }
            purgeSelectedSources()
        })

        exploreImportModalEl?.addEventListener('shown.bs.modal', ()=>{
            importLastRows = []
            selectedSourceIds.clear()
            if(importModalMode === 'panel'){
                renderImportStatusRows([])
            }else{
                renderImportEventRows([])
            }
            stopImportAutoCloseCountdown()
            startImportPolling()
            startHealthPolling()
        })

        exploreImportModalEl?.addEventListener('hidden.bs.modal', ()=>{
            if(getForceImportGate?.()){
                if(window.bootstrap?.Modal && exploreImportModalEl){
                    window.bootstrap.Modal.getOrCreateInstance(exploreImportModalEl).show()
                }
                return
            }
            if(importFinished && !importNavigatedToLatestSource){
                const targetId = Number(importLastUploadedSourceId || 0)
                if(targetId > 0){
                    const template = window.exploreConfig?.sourceSelectUrlTemplate || '/vault/explore/source/__ID__'
                    window.location.href = String(template).replace('__ID__', String(targetId))
                    return
                }
                if(reloadGrid){
                    reloadGrid()
                }else{
                    window.location.reload()
                }
            }
            resetImportModalState()
            stopHealthPolling()
            renderQueueHealth({ overall:'healthy' })
        })
    }

    return {
        bind,
        setImportModalMode,
        setExploreLocked,
        refreshImportStatuses,
        resetImportModalState,
        startImportPolling,
        stopImportPolling,
        stopImportAutoCloseCountdown,
        startImportAutoCloseCountdown,
        setForceImportGate: (value)=>setForceImportGate?.(!!value),
        getForceImportGate: ()=>getForceImportGate?.()
    }
}
