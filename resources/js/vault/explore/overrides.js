export function createOverridesController(options){
    const {
        getCurrentSourceId,
        saveMenuBadge,
        railSaveBadge,
        railSaveBtn,
        viewOverridesBtn,
        viewOverridesBtnMobile,
        publishOverridesBtn,
        publishOverridesBtnMobile,
        discardOverridesBtn,
        discardOverridesBtnMobile,
        overridesModalEl,
        overridesModalBody,
        showToast,
        escapeHtml,
        reload
    } = options || {}

    let overridesSummary = { total: 0, items: [] }
    let isPublishing = false

    const canonicalDisplayName = (name)=>{
        const raw = String(name || '').trim()
        if(!raw) return ''
        const match = raw.match(/^(.*?)(\.[^.]*)$/)
        const base = match ? match[1] : raw
        const ext = match ? match[2] : ''
        const clean = base.replace(/(?:_v\d+|\s*\(\d+\))$/i, '').trim()
        return `${clean}${ext}`
    }

    const setPublishingState = (publishing)=>{
        isPublishing = !!publishing
        ;[publishOverridesBtn, publishOverridesBtnMobile, railSaveBtn].forEach((btn)=>{
            if(!btn) return
            btn.disabled = isPublishing
            btn.classList.toggle('is-busy', isPublishing)
            btn.setAttribute('aria-busy', isPublishing ? 'true' : 'false')
        })
    }

    const updateOverridesActionsState = ()=>{
        const total = Number(overridesSummary?.total || 0)
        if(saveMenuBadge){
            saveMenuBadge.textContent = String(total)
            saveMenuBadge.classList.toggle('d-none', total < 1)
        }
        if(railSaveBadge){
            railSaveBadge.textContent = String(total)
            railSaveBadge.classList.toggle('d-none', total < 1)
        }
        if(railSaveBtn){
            railSaveBtn.classList.toggle('has-pending', total > 0)
        }

        const disabled = total < 1
        ;[
            viewOverridesBtn, viewOverridesBtnMobile,
            discardOverridesBtn, discardOverridesBtnMobile
        ].forEach(btn=>{
            if(btn) btn.disabled = disabled
        })

        ;[publishOverridesBtn, publishOverridesBtnMobile, railSaveBtn].forEach((btn)=>{
            if(!btn) return
            btn.disabled = disabled || isPublishing
        })
    }

    const resetOverridesSummary = ()=>{
        overridesSummary = { total: 0, items: [] }
        updateOverridesActionsState()
    }

    const loadOverridesSummary = async ()=>{
        const sourceId = getCurrentSourceId?.()
        if(!sourceId || !window.exploreConfig?.overridesSummaryUrl){
            resetOverridesSummary()
            return
        }

        try{
            const params = new URLSearchParams({ source_id: String(sourceId) })
            const res = await fetch(`${window.exploreConfig.overridesSummaryUrl}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            const data = await res.json().catch(()=>null)
            if(!res.ok || !data?.ok){
                return
            }
            overridesSummary = {
                total: Number(data.total || 0),
                items: Array.isArray(data.items) ? data.items : []
            }
            updateOverridesActionsState()
        }catch(e){
            console.error('Overrides summary error:', e)
        }
    }

    const openOverridesModal = ()=>{
        const items = Array.isArray(overridesSummary?.items) ? overridesSummary.items : []
        if(!overridesModalBody || !overridesModalEl) return

        if(!items.length){
            overridesModalBody.innerHTML = '<tr><td colspan="4" class="text-muted">Nenhuma alteração pendente.</td></tr>'
        }else{
            overridesModalBody.innerHTML = items.map(item=>`
                <tr>
                    <td>${escapeHtml(item.lead_id ?? '')}</td>
                    <td>${escapeHtml(item.column_key === 'registro' ? 'nome' : (item.column_key ?? ''))}</td>
                    <td class="text-break">${escapeHtml(item.value_text ?? '')}</td>
                    <td>${escapeHtml(item.updated_at ?? '')}</td>
                </tr>
            `).join('')
        }

        if(window.bootstrap?.Modal){
            window.bootstrap.Modal.getOrCreateInstance(overridesModalEl).show()
        }
    }

    const getPendingTotal = ()=>{
        return Number(overridesSummary?.total || 0)
    }

    const publishOverrides = async (options = {})=>{
        const isOptionsEvent = options instanceof Event
        const safeOptions = isOptionsEvent ? {} : (options || {})
        const { showMessages = true, navigateToCurrent = false } = safeOptions
        const sourceId = getCurrentSourceId?.()
        if(!sourceId || !window.exploreConfig?.publishOverridesUrl) return
        if(isPublishing){
            return { ok: false, busy: true, message: 'publish_in_progress' }
        }
        setPublishingState(true)
        try{
            const res = await fetch(window.exploreConfig.publishOverridesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ source_id: sourceId })
            })
            const data = await res.json().catch(()=>null)
            if(!res.ok || !data?.ok){
                if(data?.message === 'no_overrides'){
                    await loadOverridesSummary()
                    if(showMessages) showToast?.('Nenhuma alteração pendente.')
                    return { ok: true, noop: true, data }
                }
                if(showMessages) showToast?.('Não foi possível atualizar o arquivo.')
                return { ok: false, message: data?.message || `HTTP ${res.status}` }
            }
            const currentId = Number(data?.current?.id || data?.derived?.id || 0)
            const currentName = canonicalDisplayName(String(data?.current?.name || data?.derived?.name || '').trim())
            if(showMessages){
                if(currentName){
                    showToast?.(`Arquivo atualizado: ${currentName}`)
                }else if(currentId > 0){
                    showToast?.(`Arquivo atualizado (#${currentId})`)
                }else{
                    showToast?.('Arquivo atualizado.')
                }
            }
            if(navigateToCurrent && currentId > 0 && String(currentId) !== String(sourceId)){
                const template = window.exploreConfig?.sourceSelectUrlTemplate || '/vault/explore/source/__ID__'
                window.__exploreSkipBeforeUnload = '1'
                window.location.href = template.replace('__ID__', String(currentId))
                return { ok: true, data, redirected: true }
            }
            await loadOverridesSummary()
            return { ok: true, data }
        }catch(e){
            console.error('Publish overrides error:', e)
            if(showMessages) showToast?.('Não foi possível atualizar o arquivo.')
            return { ok: false, message: e?.message || 'publish_error' }
        }finally{
            setPublishingState(false)
            updateOverridesActionsState()
        }
    }

    const discardOverrides = async ()=>{
        const sourceId = getCurrentSourceId?.()
        if(!sourceId || !window.exploreConfig?.discardOverridesUrl) return
        try{
            const res = await fetch(window.exploreConfig.discardOverridesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ source_id: sourceId })
            })
            const data = await res.json().catch(()=>null)
            if(!res.ok || !data?.ok){
                showToast?.('Não foi possível descartar alterações.')
                return
            }
            showToast?.(`Alterações removidas: ${data.removed || 0}`)
            await loadOverridesSummary()
            reload?.()
        }catch(e){
            console.error('Discard overrides error:', e)
            showToast?.('Não foi possível descartar alterações.')
        }
    }

    return {
        loadOverridesSummary,
        openOverridesModal,
        publishOverrides,
        discardOverrides,
        updateOverridesActionsState,
        resetOverridesSummary,
        getPendingTotal
    }
}
