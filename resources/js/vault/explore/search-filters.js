export function createSearchFiltersController(options){
    const {
        search,
        score,
        scoreMobile,
        exploreSearchModalInput,
        exploreSearchApplyBtn,
        exploreSearchClearBtn,
        exploreSearchSegmentSelect,
        exploreSearchNicheSelect,
        exploreSearchOriginSelect,
        exploreSearchCitiesInput,
        exploreSearchStatesInput,
        exploreSearchModalEl,
        openSearchModalBtn,
        clearFiltersBtn,
        clearFiltersBtnMobile,
        searchFilters,
        onReload,
        onResetColumns,
        onFiltersUpdated,
        getPreviewParams,
        onPreviewSearch,
        previewElements
    } = options || {}

    let previewTimer = null
    const schedulePreview = ()=>{
        if(!onPreviewSearch || !getPreviewParams) return
        clearTimeout(previewTimer)
        previewTimer = setTimeout(()=>{
            onPreviewSearch(getPreviewParams())
        }, 350)
    }

    const syncSearchFromModal = (reload = true)=>{
        if(!search || !exploreSearchModalInput) return
        search.value = exploreSearchModalInput.value || ''
        searchFilters.segment_id = String(exploreSearchSegmentSelect?.value || '').trim()
        searchFilters.niche_id = String(exploreSearchNicheSelect?.value || '').trim()
        searchFilters.origin_id = String(exploreSearchOriginSelect?.value || '').trim()
        searchFilters.cities = String(exploreSearchCitiesInput?.value || '')
            .split(',')
            .map(v=>v.trim())
            .filter(Boolean)
        searchFilters.states = String(exploreSearchStatesInput?.value || '')
            .split(',')
            .map(v=>v.trim().toUpperCase())
            .filter(Boolean)

        if(reload){
            onReload?.()
        }
        onFiltersUpdated?.()
    }

    const clearFilters = (fromShortcut = false)=>{
        if(document.body.classList.contains('modal-open')) return
        const activeTag = document.activeElement?.tagName?.toLowerCase()
        if(fromShortcut && (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select')) return

        if(search) search.value = ''
        if(exploreSearchModalInput) exploreSearchModalInput.value = ''
        if(exploreSearchSegmentSelect) exploreSearchSegmentSelect.value = ''
        if(exploreSearchNicheSelect) exploreSearchNicheSelect.value = ''
        if(exploreSearchOriginSelect) exploreSearchOriginSelect.value = ''
        if(exploreSearchCitiesInput) exploreSearchCitiesInput.value = ''
        if(exploreSearchStatesInput) exploreSearchStatesInput.value = ''
        searchFilters.segment_id = ''
        searchFilters.niche_id = ''
        searchFilters.origin_id = ''
        searchFilters.cities = []
        searchFilters.states = []
        if(score) score.value = ''
        if(scoreMobile) scoreMobile.value = ''

        onResetColumns?.()
        onReload?.()
        onFiltersUpdated?.()
    }

    const openSearchModal = ()=>{
        if(exploreSearchModalEl && window.bootstrap?.Modal){
            window.bootstrap.Modal.getOrCreateInstance(exploreSearchModalEl).show()
        }
    }

    const bind = ()=>{
        openSearchModalBtn?.addEventListener('click', openSearchModal)

        score?.addEventListener('change', ()=>{
            if(scoreMobile) scoreMobile.value = score.value
            schedulePreview()
            onFiltersUpdated?.()
        })
        scoreMobile?.addEventListener('change', ()=>{
            if(score) score.value = scoreMobile.value
            schedulePreview()
            onFiltersUpdated?.()
        })

        exploreSearchModalInput?.addEventListener('input', ()=>{
            schedulePreview()
        })
        exploreSearchSegmentSelect?.addEventListener('change', ()=>{
            schedulePreview()
        })
        exploreSearchNicheSelect?.addEventListener('change', ()=>{
            schedulePreview()
        })
        exploreSearchOriginSelect?.addEventListener('change', ()=>{
            schedulePreview()
        })
        exploreSearchCitiesInput?.addEventListener('input', ()=>{
            schedulePreview()
        })
        exploreSearchStatesInput?.addEventListener('input', ()=>{
            schedulePreview()
        })

        exploreSearchApplyBtn?.addEventListener('click', ()=>{
            syncSearchFromModal(true)
            if(exploreSearchModalEl && window.bootstrap?.Modal){
                window.bootstrap.Modal.getOrCreateInstance(exploreSearchModalEl).hide()
            }
        })

        exploreSearchClearBtn?.addEventListener('click', ()=>{
            if(exploreSearchModalInput) exploreSearchModalInput.value = ''
            if(exploreSearchSegmentSelect) exploreSearchSegmentSelect.value = ''
            if(exploreSearchNicheSelect) exploreSearchNicheSelect.value = ''
            if(exploreSearchOriginSelect) exploreSearchOriginSelect.value = ''
            if(exploreSearchCitiesInput) exploreSearchCitiesInput.value = ''
            if(exploreSearchStatesInput) exploreSearchStatesInput.value = ''
            schedulePreview()
        })

        exploreSearchModalInput?.addEventListener('keydown', (e)=>{
            if(e.key === 'Enter'){
                e.preventDefault()
                syncSearchFromModal(true)
                if(exploreSearchModalEl && window.bootstrap?.Modal){
                    window.bootstrap.Modal.getOrCreateInstance(exploreSearchModalEl).hide()
                }
            }
        })

        exploreSearchModalEl?.addEventListener('shown.bs.modal', ()=>{
            if(exploreSearchModalInput){
                exploreSearchModalInput.value = search?.value || ''
                exploreSearchModalInput.focus()
                exploreSearchModalInput.select()
            }
            if(exploreSearchSegmentSelect) exploreSearchSegmentSelect.value = searchFilters.segment_id || ''
            if(exploreSearchNicheSelect) exploreSearchNicheSelect.value = searchFilters.niche_id || ''
            if(exploreSearchOriginSelect) exploreSearchOriginSelect.value = searchFilters.origin_id || ''
            if(exploreSearchCitiesInput) exploreSearchCitiesInput.value = (searchFilters.cities || []).join(', ')
            if(exploreSearchStatesInput) exploreSearchStatesInput.value = (searchFilters.states || []).join(', ')
            schedulePreview()
        })

        clearFiltersBtn?.addEventListener('click', ()=> clearFilters(false))
        clearFiltersBtnMobile?.addEventListener('click', ()=> clearFilters(false))

        document.addEventListener('keydown', (e)=>{
            if(e.key === '/'){
                const activeTag = document.activeElement?.tagName?.toLowerCase()
                if(activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') return
                e.preventDefault()
                openSearchModal()
                return
            }
            if(e.key !== 'Escape') return
            clearFilters(true)
        })
    }

    return {
        bind,
        clearFilters,
        syncSearchFromModal
    }
}
