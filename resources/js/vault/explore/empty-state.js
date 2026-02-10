export function createEmptyStateController(options){
    const {
        emptyState,
        emptyStateTitle,
        emptyStateMeta,
        emptyStateFiltersSummary,
        emptyStateClearBtn,
        search,
        score,
        sourceSelect,
        searchFilters,
        exploreSearchSegmentSelect,
        exploreSearchNicheSelect,
        exploreSearchOriginSelect,
        hasSources,
        forceImportGate,
        canImportLeads
    } = options || {}

    let currentHasSources = !!hasSources
    let currentForceImportGate = !!forceImportGate

    const getSelectLabel = (selectEl, value, labelPrefix) => {
        if(!selectEl || !value) return ''
        const option = Array.from(selectEl.options || []).find(opt => String(opt.value) === String(value))
        const label = option?.textContent?.trim()
        if(label) return label
        return labelPrefix ? `${labelPrefix} ${value}` : String(value)
    }

    const hasActiveFilters = () => {
        const q = (search?.value || '').trim()
        if(q) return true
        const minScore = (score?.value || '').trim()
        if(minScore) return true
        const sourceId = (sourceSelect?.value || '').trim()
        if(sourceId) return true
        if(searchFilters?.segment_id || searchFilters?.niche_id || searchFilters?.origin_id) return true
        if((searchFilters?.cities || []).length) return true
        if((searchFilters?.states || []).length) return true
        return false
    }

    const updateEmptyStateCopy = () => {
        if(!emptyStateTitle || !emptyStateMeta) return
        if(currentForceImportGate || !currentHasSources){
            emptyStateTitle.textContent = 'Importe um arquivo para continuar'
            emptyStateMeta.textContent = 'Assim que o arquivo começar a ser processado, a tela será liberada.'
            return
        }
        if(hasActiveFilters()){
            emptyStateTitle.textContent = 'Nenhum registro encontrado para os filtros atuais'
            emptyStateMeta.textContent = 'Ajuste os filtros ou limpe para ver todos os registros.'
            return
        }
        emptyStateTitle.textContent = 'Sua base ainda não tem registros'
        emptyStateMeta.textContent = canImportLeads
            ? 'Importe um arquivo para começar a explorar.'
            : 'Solicite permissão para importar arquivos e começar a explorar.'
    }

    const updateEmptyStateSummary = () => {
        if(!emptyStateFiltersSummary) return
        const parts = []
        const q = (search?.value || '').trim()
        if(q) parts.push(`Busca: "${q}"`)
        const minScore = (score?.value || '').trim()
        if(minScore) parts.push(`Score ${minScore}+`)
        const sourceLabel = getSelectLabel(sourceSelect, sourceSelect?.value, 'Arquivo')
        if(sourceLabel) parts.push(`Arquivo: ${sourceLabel}`)
        if(searchFilters?.segment_id){
            parts.push(`Segmento: ${getSelectLabel(exploreSearchSegmentSelect, searchFilters.segment_id, 'ID')}`)
        }
        if(searchFilters?.niche_id){
            parts.push(`Nicho: ${getSelectLabel(exploreSearchNicheSelect, searchFilters.niche_id, 'ID')}`)
        }
        if(searchFilters?.origin_id){
            parts.push(`Origem: ${getSelectLabel(exploreSearchOriginSelect, searchFilters.origin_id, 'ID')}`)
        }
        if((searchFilters?.cities || []).length){
            parts.push(`Cidades: ${(searchFilters.cities || []).join(', ')}`)
        }
        if((searchFilters?.states || []).length){
            parts.push(`UFs: ${(searchFilters.states || []).join(', ')}`)
        }

        emptyStateFiltersSummary.textContent = parts.length
            ? `Filtros ativos: ${parts.join(' • ')}`
            : 'Sem filtros ativos.'
    }

    const updateEmptyState = () => {
        updateEmptyStateCopy()
        updateEmptyStateSummary()
    }

    const bindClear = (handler) => {
        emptyStateClearBtn?.addEventListener('click', handler)
    }

    if(emptyState && emptyStateFiltersSummary){
        updateEmptyState()
    }

    return {
        updateEmptyState,
        updateEmptyStateCopy,
        updateEmptyStateSummary,
        setForceImportGate(value){
            currentForceImportGate = !!value
            updateEmptyStateCopy()
        },
        setHasSources(value){
            currentHasSources = !!value
            updateEmptyStateCopy()
        },
        bindClear,
        hasActiveFilters
    }
}
