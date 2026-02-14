import '../../css/vault/explore.css'
import { createEmptyStateController } from './explore/empty-state'
import { createLoadingController } from './explore/loading'
import { createSearchFiltersController } from './explore/search-filters'
import { createOverridesController } from './explore/overrides'
import { createImportController } from './explore/import'
import initExploreOperationsWizard from './explore/operations-wizard'

export default function initExplore(){

    const $  = id => document.getElementById(id)
    const qs = s  => document.querySelector(s)

    let nextAfterId = null
    let hasMore = true
    let loading = false
    let total   = 0
    let filteredTotal = null
    let sourceTotalRecords = null
    let sourceTotalSourceId = null
    let debounceTimer = null
    let lastSelectedSourceValue = ''
    let importAttentionTimer = null
    let importAttentionActive = false
    let importAttentionRuns = 0
    const IMPORT_ATTENTION_MAX_RUNS = 10
    const IMPORT_ATTENTION_INTERVAL_MS = 30000
    const canInlineEdit = !!window.exploreConfig?.canInlineEdit

    const body      = $('leadsBody')
    const search    = $('searchInput')
    const openSearchModalBtn = $('openSearchModalBtn')
    const exploreSearchModalEl = $('exploreSearchModal')
    const exploreSearchModalInput = $('exploreSearchModalInput')
    const exploreSearchApplyBtn = $('exploreSearchApplyBtn')
    const exploreSearchClearBtn = $('exploreSearchClearBtn')
    const exploreSearchSegmentSelect = $('exploreSearchSegmentSelect')
    const exploreSearchNicheSelect = $('exploreSearchNicheSelect')
    const exploreSearchOriginSelect = $('exploreSearchOriginSelect')
    const exploreSearchCitiesInput = $('exploreSearchCitiesInput')
    const exploreSearchStatesInput = $('exploreSearchStatesInput')
    const exploreSearchAdvancedFields = $('exploreSearchAdvancedFields')
    const exploreSearchPreviewWrap = $('exploreSearchPreviewWrap')
    const exploreSearchPreviewBody = $('exploreSearchPreviewBody')
    const exploreSearchPreviewCount = $('exploreSearchPreviewCount')
    const exploreSearchPreviewSelected = $('exploreSearchPreviewSelected')
    const exploreSearchPreviewEmpty = $('exploreSearchPreviewEmpty')
    const exploreSearchPreviewLoading = $('exploreSearchPreviewLoading')
    const exploreSearchPreviewSelectPageBtn = $('exploreSearchPreviewSelectPageBtn')
    const exploreSearchPreviewSelectAllBtn = $('exploreSearchPreviewSelectAllBtn')
    const exploreSearchPreviewClearSelectionBtn = $('exploreSearchPreviewClearSelectionBtn')
    const score     = $('scoreInput')
    const scoreMobile = $('scoreInputMobile')
    const counter   = $('counter')
    const foundCount = $('foundCount')
    const exportBtn = $('exportBtn')
    const clearFiltersBtn = $('clearFiltersBtn')
    const clearFiltersBtnMobile = $('clearFiltersBtnMobile')
    const columnsBtn = $('columnsBtn')
    const columnsBtnMobile = $('columnsBtnMobile')
    const layoutPresetSelect = $('layoutPresetSelect')
    const layoutManageModalEl = $('layoutManageModal')
    const layoutManageCreateInput = $('layoutManageCreateInput')
    const layoutManageCreateSection = $('layoutManageCreateSection')
    const layoutManageCreateError = $('layoutManageCreateError')
    const layoutManageCreateBtn = $('layoutManageCreateBtn')
    const layoutManageExistingSection = $('layoutManageExistingSection')
    const layoutManageCurrentName = $('layoutManageCurrentName')
    const layoutManageNameInput = $('layoutManageNameInput')
    const layoutManageRenameSection = $('layoutManageRenameSection')
    const layoutManageRenameError = $('layoutManageRenameError')
    const layoutManageUpdateSection = $('layoutManageUpdateSection')
    const layoutManageDeleteSection = $('layoutManageDeleteSection')
    const layoutManageActionRename = $('layoutManageActionRename')
    const layoutManageActionUpdate = $('layoutManageActionUpdate')
    const layoutManageActionDelete = $('layoutManageActionDelete')
    const layoutManageRenameBtn = $('layoutManageRenameBtn')
    const layoutManageUpdateBtn = $('layoutManageUpdateBtn')
    const layoutManageDeleteBtn = $('layoutManageDeleteBtn')
    const layoutDeleteConfirmModalEl = $('layoutDeleteConfirmModal')
    const layoutDeleteConfirmText = $('layoutDeleteConfirmText')
    const layoutDeleteConfirmBtn = $('layoutDeleteConfirmBtn')
    const viewOverridesBtn = $('viewOverridesBtn')
    const viewOverridesBtnMobile = $('viewOverridesBtnMobile')
    const publishOverridesBtn = $('publishOverridesBtn')
    const publishOverridesBtnMobile = $('publishOverridesBtnMobile')
    const discardOverridesBtn = $('discardOverridesBtn')
    const discardOverridesBtnMobile = $('discardOverridesBtnMobile')
    const saveMenuBadge = $('saveMenuBadge')
    const railSaveBadge = $('railSaveBadge')
    const railSaveBtn = $('railSaveBtn')
    const overridesModalEl = $('overridesModal')
    const overridesModalBody = $('overridesModalBody')
    const sourceSelect = $('sourceSelect')
    const sourceSelectMobile = $('sourceSelectMobile')
    const dataQualityBtn = $('dataQualityBtn')
    const dataQualityBtnMobile = $('dataQualityBtnMobile')
    const configRailDataBtn = document.querySelector('#gradeConfigRail [data-config-rail-action="data"]')
    const configRailSemanticBtn = document.querySelector('#gradeConfigRail [data-config-rail-action="semantic"]')
    const exploreDataQualityModalEl = $('exploreDataQualityModal')
    const exploreDataQualityModalBody = $('exploreDataQualityModalBody')
    const openColumnsAdminModalBtn = $('openColumnsAdminModalBtn')
    const exploreColumnsEditBtn = $('exploreColumnsEditBtn')
    const exploreColumnsAdminModalEl = $('exploreColumnsAdminModal')
    const exploreColumnsAdminModalBody = $('exploreColumnsAdminModalBody')
    const semanticBtn = $('semanticBtn')
    const semanticSaveBtn = $('semanticSaveBtn')
    const semanticPills = $('semanticPills')
    const semanticTopSummary = $('semanticTopSummary')
    const semanticTopAnchor = $('semanticTopAnchor')
    const semanticTopHoverPills = $('semanticTopHoverPills')
    const semanticSegmentInput = $('semanticSegmentInput')
    const semanticSegmentResults = $('semanticSegmentResults')
    const semanticSegmentPill = $('semanticSegmentPill')
    const semanticNicheInput = $('semanticNicheInput')
    const semanticNicheResults = $('semanticNicheResults')
    const semanticNichePill = $('semanticNichePill')
    const semanticOriginInput = $('semanticOriginInput')
    const semanticOriginResults = $('semanticOriginResults')
    const semanticOriginPill = $('semanticOriginPill')
    const semanticCityInput = $('semanticCityInput')
    const semanticCityResults = $('semanticCityResults')
    const semanticCityPills = $('semanticCityPills')
    const semanticStateInput = $('semanticStateInput')
    const semanticStateResults = $('semanticStateResults')
    const semanticStatePills = $('semanticStatePills')
    const semanticCountryInput = $('semanticCountryInput')
    const semanticCountryResults = $('semanticCountryResults')
    const semanticCountryPills = $('semanticCountryPills')
    const semanticAnchor = $('semanticAnchor')
    const selectionBar = $('selectionBar')
    const selectionCount = $('selectionCount')
    const selectionSelectAllBtn = $('selectionSelectAllBtn')
    const selectionClearBtn = $('selectionClearBtn')
    const checkAll = $('checkAll')
    const loadingEl = $('exploreLoading')
    const toastEl = $('exploreToast')
    let semanticCitiesHaveState = false
    let semanticCitiesData = []
    const semanticSelected = {
        segment: new Map(),
        niche: new Map(),
        city: new Map(),
        state: new Map(),
        country: new Map()
    }
    const semanticSelectedSingle = {
        origin: null
    }
    const wrap      = qs('.explore-table-wrap')
    const headerRow = $('leadsHeaderRow')
    const emptyState = $('emptyState')
    const emptyStateTitle = $('emptyStateTitle')
    const emptyStateMeta = $('emptyStateMeta')
    const emptyStateClearBtn = $('emptyStateClearBtn')
    const emptyStateSampleBtn = $('emptyStateSampleBtn')
    const emptyStateImportBtn = $('emptyStateImportBtn')
    const emptyStateFiltersSummary = $('emptyStateFiltersSummary')
    const columnsModalEl = $('columnsModal')
    const columnsList = $('columnsList')
    const columnsSelectAll = $('columnsSelectAll')
    const columnsResetDefault = $('columnsResetDefault')
    const openExploreImportBtn = $('openExploreImportBtn')
    const exploreImportModalEl = $('exploreImportModal')
    const exploreImportModalTitle = $('exploreImportModalTitle')
    const exploreImportModalModeBadge = $('exploreImportModalModeBadge')
    const exploreImportForm = $('exploreImportForm')
    const exploreImportInput = $('exploreImportInput')
    const exploreImportDropzone = $('exploreImportDropzone')
    const exploreImportUploadWrap = $('exploreImportUploadWrap')
    const exploreImportSelectedList = $('exploreImportSelectedList')
    const exploreImportInvalidAlert = $('exploreImportInvalidAlert')
    const exploreImportBar = $('exploreImportBar')
    const exploreImportEventWrap = $('exploreImportEventWrap')
    const exploreImportEventBody = $('exploreImportEventBody')
    const exploreQueueHealthAlert = $('exploreQueueHealthAlert')
    const exploreSourcesPanelWrap = $('exploreSourcesPanelWrap')
    const exploreImportStatusBody = $('exploreImportStatusBody')
    const exploreImportSubmitBtn = $('exploreImportSubmitBtn')
    const exploreImportAutoCloseHint = $('exploreImportAutoCloseHint')
    const exploreImportCloseBtn = $('exploreImportCloseBtn')
    const exploreLockedOverlay = $('exploreLockedOverlay')
    const exploreSourcesRefreshBtn = $('exploreSourcesRefreshBtn')
    const exploreSourcesCheckAll = $('exploreSourcesCheckAll')
    const exploreSourcesPurgeBtn = $('exploreSourcesPurgeBtn')
    const exploreSourcesPurgeModalEl = $('exploreSourcesPurgeModal')
    const exploreSourcesPurgeInput = $('exploreSourcesPurgeInput')
    const exploreSourcesPurgeConfirmBtn = $('exploreSourcesPurgeConfirmBtn')
    const exploreSourcesPurgeProgressWrap = $('exploreSourcesPurgeProgressWrap')
    const exploreSourcesPurgeProgressText = $('exploreSourcesPurgeProgressText')
    const exploreSourcesPurgeProgressCount = $('exploreSourcesPurgeProgressCount')
    const exploreSourcesPurgeProgressBar = $('exploreSourcesPurgeProgressBar')
    const exploreLeaveConfirmModalEl = $('exploreLeaveConfirmModal')
    const exploreLeaveConfirmCount = $('exploreLeaveConfirmCount')
    const exploreLeavePublishBtn = $('exploreLeavePublishBtn')
    const exploreLeaveKeepPendingBtn = $('exploreLeaveKeepPendingBtn')
    const exploreLeaveCancelBtn = $('exploreLeaveCancelBtn')

    if(!body || !wrap) return

    const STORAGE_PREFIX = 'vaultExploreColumns'
    const LAYOUTS_STORAGE_PREFIX = 'vaultExploreLayouts'
    const LAST_LAYOUT_STORAGE_PREFIX = 'vaultExploreLastLayout'
    const LAST_LAYOUT_DEFAULT_SENTINEL = '__default__'
    let storageKey = STORAGE_PREFIX
    let orderKey = `${STORAGE_PREFIX}:order`
    let layoutSourceContext = null

    let baseColumns = [
        { key:'nome',  label:'Nome' },
        { key:'cpf',   label:'CPF' },
        { key:'email', label:'E-mail' },
        { key:'phone', label:'Telefone' },
        { key:'data_nascimento', label:'Data de nascimento' },
        { key:'sex',   label:'Sexo' }
    ]
    const baseKeySet = new Set(baseColumns.map(c=>c.key))

    const normalizeKey = v => String(v || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g,'')

    const baseAliases = new Set([
        'registro','name','nome','fullname','fullnome',
        'email','e-mail','e_mail','mail',
        'cpf','documento','doc',
        'phone','telefone','tel','celular','phonee164',
        'data_nascimento','datanascimento','nascimento','dt_nascimento','data-de-nascimento',
        'sex','sexo','gender','genero'
    ].map(normalizeKey))

    const normalizeBaseKeyAlias = (key) => {
        const k = String(key || '').trim().toLowerCase()
        if(k === 'registro' || k === 'name' || k === 'lead') return 'nome'
        return String(key || '').trim()
    }

    const columnLabelMap = new Map()

    function titleCasePt(v){
        if(!v) return ''
        const small = new Set([
            'da','das','de','do','dos','e','em','na','nas','no','nos','a','o','as','os'
        ])
        return v
            .toLowerCase()
            .replace(/\s+/g,' ')
            .trim()
            .split(' ')
            .map((w,i)=>{
                if(i > 0 && small.has(w)) return w
                return w.charAt(0).toUpperCase() + w.slice(1)
            })
            .join(' ')
    }

    function normalizeColumnLabel(key){
        if(!key) return ''
        const rawKey = String(key).trim()
        if(columnLabelMap.has(rawKey)) return columnLabelMap.get(rawKey)
        const normalizedKey = normalizeBaseKeyAlias(rawKey)
        if(columnLabelMap.has(normalizedKey)) return columnLabelMap.get(normalizedKey)
        const raw = String(key).trim()
        const spaced = raw
            .replace(/[_\-]+/g,' ')
            .replace(/([a-z0-9])([A-Z])/g,'$1 $2')
            .replace(/\s+/g,' ')
            .trim()
        return titleCasePt(spaced)
    }

    const isExtraHidden = key => {
        const label = normalizeColumnLabel(key)
        return baseAliases.has(normalizeKey(key)) || baseAliases.has(normalizeKey(label))
    }

    const extrasColumns = new Set()
    const extrasCache = new Map()
    let DEFAULT_VISIBLE = ['nome','cpf','email','phone']
    let visibleColumns = new Set(DEFAULT_VISIBLE)
    let columnOrder = []
    const canImportLeads = !!window.exploreConfig?.canImportLeads
    const canDeleteSources = !!window.exploreConfig?.canDeleteSources
    const canCancelImport = !!window.exploreConfig?.canCancelImport
    const canReprocessImport = !!window.exploreConfig?.canReprocessImport
    let isForceImportGate = !!window.exploreConfig?.forceImportGate
    const serverViewPreference = window.exploreViewPreference && typeof window.exploreViewPreference === 'object'
        ? window.exploreViewPreference
        : null
    const searchFilters = {
        segment_id: '',
        niche_id: '',
        origin_id: '',
        cities: [],
        states: [],
    }

    const setLoading = createLoadingController({ loadingEl, wrap })
    const emptyStateController = createEmptyStateController({
        emptyState,
        emptyStateTitle,
        emptyStateMeta,
        emptyStateFiltersSummary,
        emptyStateClearBtn,
        emptyStateSampleBtn,
        emptyStateImportBtn,
        search,
        score,
        sourceSelect,
        searchFilters,
        exploreSearchSegmentSelect,
        exploreSearchNicheSelect,
        exploreSearchOriginSelect,
        hasSources: !!window.exploreConfig?.hasSources,
        forceImportGate: isForceImportGate,
        canImportLeads
    })
    const setForceImportGate = (value)=>{
        isForceImportGate = !!value
        emptyStateController?.setForceImportGate(isForceImportGate)
    }
    const setHasSources = (value)=>{
        emptyStateController?.setHasSources(!!value)
    }
    const getMinScoreValue = ()=> String(score?.value || scoreMobile?.value || '').trim()
    const getSourceIdFromContext = ()=>{
        const urlParams = new URLSearchParams(window.location.search || '')
        const sourceId = getCurrentSourceId()
            || (() => {
                const raw = urlParams.get('source_id') || urlParams.get('lead_source_id') || ''
                const parsed = Number(raw)
                return Number.isFinite(parsed) && parsed > 0 ? parsed : null
            })()
        return sourceId ? Number(sourceId) : null
    }
    const refreshSourceTotalRecords = async ()=>{
        if(!window.exploreConfig?.dbUrl) return
        const sourceId = getSourceIdFromContext()
        if(!sourceId){
            sourceTotalRecords = null
            sourceTotalSourceId = null
            syncFooterCounters()
            return
        }
        if(sourceTotalSourceId === sourceId && Number.isFinite(sourceTotalRecords)){
            syncFooterCounters()
            return
        }
        try{
            const params = new URLSearchParams({
                lead_source_id: String(sourceId),
                q: '',
                min_score: '0',
                with_total: '1',
                per_page: '1',
            })
            const res = await fetch(`${window.exploreConfig.dbUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            })
            const data = await res.json().catch(() => null)
            if(res.ok && data && typeof data.total === 'number'){
                sourceTotalRecords = Number(data.total)
                sourceTotalSourceId = sourceId
                syncFooterCounters()
            }
        }catch(e){
            // Fallback keeps current counter based on loaded rows.
        }
    }
    const setButtonEnabled = (el, enabled)=>{
        if(!el) return
        el.classList.toggle('disabled', !enabled)
        el.setAttribute('aria-disabled', enabled ? 'false' : 'true')
        if('disabled' in el){
            el.disabled = !enabled
        }
    }
    const setDataQualityEnabled = (enabled)=>{
        setButtonEnabled(dataQualityBtn, enabled)
        setButtonEnabled(dataQualityBtnMobile, enabled)
        setButtonEnabled(configRailDataBtn, enabled)
    }
    const setSemanticEnabled = (enabled)=>{
        setButtonEnabled(semanticBtn, enabled)
        setButtonEnabled(configRailSemanticBtn, enabled)
    }
    const isDataQualityDisabled = ()=>{
        return dataQualityBtn?.classList.contains('disabled')
            || dataQualityBtnMobile?.classList.contains('disabled')
    }
    const getCurrentSourceIdForDQ = ()=>{
        const id = String(sourceSelect?.value || sourceSelectMobile?.value || '').trim()
        return id
    }
    const buildDataQualityModalUrl = (sourceId = '')=>{
        const base = dataQualityBtn?.dataset.modalUrl
            || dataQualityBtnMobile?.dataset.modalUrl
            || '/explore/data-quality/modal'
        const params = new URLSearchParams()
        if(sourceId){
            params.set('source_id', sourceId)
        }
        return params.toString() ? `${base}?${params.toString()}` : base
    }
    const setDataQualityLoading = (message = 'Carregando qualidade de dados...')=>{
        if(!exploreDataQualityModalBody) return
        exploreDataQualityModalBody.innerHTML = `
            <div class="explore-dq-modal-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <span>${escapeHtml(message)}</span>
            </div>
        `
    }
    const loadDataQualityModal = async (sourceId = '')=>{
        if(!exploreDataQualityModalBody) return
        try{
            setDataQualityLoading('Carregando qualidade de dados...')
            const response = await fetch(buildDataQualityModalUrl(sourceId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            if(!response.ok){
                throw new Error(`HTTP ${response.status}`)
            }
            const html = await response.text()
            exploreDataQualityModalBody.innerHTML = html
            const { default: initDataQualityPage } = await import('../admin/data-quality')
            initDataQualityPage({
                root: exploreDataQualityModalBody,
                onSourceChange: (id)=>loadDataQualityModal(id)
            })
        }catch(e){
            console.error('Data quality modal load error:', e)
            exploreDataQualityModalBody.innerHTML = `
                <div class="explore-dq-modal-error">
                    <div>Não foi possível carregar qualidade de dados.</div>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="exploreDataQualityRetryBtn">
                        Tentar novamente
                    </button>
                </div>
            `
            exploreDataQualityModalBody.querySelector('#exploreDataQualityRetryBtn')?.addEventListener('click', ()=>{
                loadDataQualityModal(sourceId)
            })
        }
    }
    const openDataQualityModal = async (event)=>{
        if(isDataQualityDisabled()){
            event?.preventDefault()
            return
        }
        if(!exploreDataQualityModalEl || !window.bootstrap?.Modal){
            return
        }
        event?.preventDefault()
        const modal = window.bootstrap.Modal.getOrCreateInstance(exploreDataQualityModalEl)
        modal.show()
        await loadDataQualityModal(getCurrentSourceIdForDQ())
    }
    const buildColumnsAdminModalUrl = ()=>{
        return openColumnsAdminModalBtn?.dataset.modalUrl || '/explore/columns/modal'
    }
    const setColumnsAdminLoading = (message = 'Carregando catálogo de colunas...')=>{
        if(!exploreColumnsAdminModalBody) return
        exploreColumnsAdminModalBody.innerHTML = `
            <div class="explore-dq-modal-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <span>${escapeHtml(message)}</span>
            </div>
        `
    }
    const loadColumnsAdminModal = async ()=>{
        if(!exploreColumnsAdminModalBody) return
        try{
            setColumnsAdminLoading()
            const response = await fetch(buildColumnsAdminModalUrl(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            if(!response.ok){
                throw new Error(`HTTP ${response.status}`)
            }
            const html = await response.text()
            exploreColumnsAdminModalBody.innerHTML = html
            const { default: initColumnsAdminPage } = await import('../admin/columns')
            initColumnsAdminPage({ force: true })
        }catch(e){
            console.error('Columns admin modal load error:', e)
            exploreColumnsAdminModalBody.innerHTML = `
                <div class="explore-dq-modal-error">
                    <div>Não foi possível carregar o catálogo de colunas.</div>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="exploreColumnsAdminRetryBtn">
                        Tentar novamente
                    </button>
                </div>
            `
            exploreColumnsAdminModalBody.querySelector('#exploreColumnsAdminRetryBtn')?.addEventListener('click', ()=>{
                loadColumnsAdminModal()
            })
        }
    }
    const openColumnsAdminModal = async (event)=>{
        event?.preventDefault()
        if(!exploreColumnsAdminModalEl || !window.bootstrap?.Modal){
            return
        }
        if(columnsModalEl){
            window.bootstrap.Modal.getOrCreateInstance(columnsModalEl).hide()
        }
        const modal = window.bootstrap.Modal.getOrCreateInstance(exploreColumnsAdminModalEl)
        modal.show()
        await loadColumnsAdminModal()
    }

    const settings = Array.isArray(window.exploreColumnSettings)
        ? window.exploreColumnSettings
        : []

    if(settings.length){
        settings.forEach(s=>{
            if(!s || !s.column_key) return
            const normalizedKey = normalizeBaseKeyAlias(s.column_key)
            if(s.label){
                columnLabelMap.set(String(normalizedKey), String(s.label))
            }
        })

        const visible = settings
            .filter(s=>s.visible)
            .map(s=>normalizeBaseKeyAlias(s.column_key))
        if(visible.length){
            DEFAULT_VISIBLE = visible
            visibleColumns = new Set(DEFAULT_VISIBLE)
        }

        const order = settings
            .slice()
            .sort((a,b)=>(a.sort_order ?? 0) - (b.sort_order ?? 0))
            .map(s=>normalizeBaseKeyAlias(s.column_key))
        if(order.length){
            columnOrder = order
        }

        settings.forEach(s=>{
            const key = normalizeBaseKeyAlias(s.column_key)
            if(!key) return
            if(baseKeySet.has(key)) return
            if(isExtraHidden(key)) return
            extrasColumns.add(key)
        })
    }

    baseColumns = baseColumns.map(c=>({
        key: c.key,
        label: columnLabelMap.get(c.key) || c.label
    }))

    const applyBaseHeaderLabels = ()=>{
        if(!headerRow) return
        baseColumns.forEach((column)=>{
            const th = headerRow.querySelector(`th[data-col="${column.key}"]`)
            if(!th) return
            const resizer = th.querySelector('.col-resizer')
            th.textContent = String(column.label || normalizeColumnLabel(column.key) || column.key)
            if(resizer){
                th.appendChild(resizer)
            }
        })
    }

    const selectedIds = new Set()
    let selectionMode = 'manual' // 'manual' | 'all_matching'
    let allMatchingTotal = null
    const excludedIds = new Set()
    let preserveSelectionOnNextReset = false

    function getAllMatchingSelectedCount(){
        const total = (typeof allMatchingTotal === 'number' && Number.isFinite(allMatchingTotal))
            ? allMatchingTotal
            : null
        if(total === null) return null
        return Math.max(0, total - excludedIds.size)
    }

    const formatCount = (value)=>{
        const n = Number(value)
        if(!Number.isFinite(n)) return '0'
        return n.toLocaleString('pt-BR')
    }

    const syncFooterCounters = ()=>{
        const filtered = Number.isFinite(filteredTotal) ? Number(filteredTotal) : total
        const sourceTotal = Number.isFinite(sourceTotalRecords) ? Number(sourceTotalRecords) : null
        const footerTotal = sourceTotal ?? filtered
        if(foundCount) foundCount.textContent = formatCount(filtered)
        if(counter) counter.textContent = formatCount(footerTotal)
    }

    // Expose selection to other UI modules (wizard, sidebar).
    window.exploreSelection = {
        getSelectedIds: () => selectionMode === 'manual' ? Array.from(selectedIds) : [],
        getSelection: () => {
            const filters = getExploreFilterSnapshot()
            const count = selectionMode === 'manual'
                ? selectedIds.size
                : getAllMatchingSelectedCount()
            return {
                mode: selectionMode,
                ids: selectionMode === 'manual' ? Array.from(selectedIds) : [],
                filters,
                excluded_ids: Array.from(excludedIds),
                selected_count: count,
                total_count: (typeof allMatchingTotal === 'number' ? allMatchingTotal : null),
            }
        },
    }

    try{
        const raw = localStorage.getItem(storageKey)
        if(raw !== null){
            const saved = (JSON.parse(raw || '[]') || []).map(k => {
                const kk = String(k || '').trim().toLowerCase()
                return (kk === 'registro' || kk === 'name' || kk === 'lead') ? 'nome' : String(k || '').trim()
            })
            if(Array.isArray(saved)){
                visibleColumns = new Set(saved)
            }
        }
    }catch(e){}


    try{
        const savedOrder = (JSON.parse(localStorage.getItem(orderKey) || 'null') || []).map(k => {
            const kk = String(k || '').trim().toLowerCase()
            return (kk === 'registro' || kk === 'name' || kk === 'lead') ? 'nome' : String(k || '').trim()
        })
        if(Array.isArray(savedOrder) && savedOrder.length){
            columnOrder = savedOrder
        }
    }catch(e){}

    const normalizeLayoutKey = normalizeBaseKeyAlias

    const normalizeLayoutKeys = (arr) => Array.isArray(arr)
        ? arr.map(normalizeLayoutKey).filter(Boolean)
        : []

    function applyStorageForSource(sourceId){
        layoutSourceContext = sourceId ? Number(sourceId) : null
        storageKey = sourceId ? `${STORAGE_PREFIX}:${sourceId}` : STORAGE_PREFIX
        orderKey = `${storageKey}:order`
        let nextVisible = null
        let nextOrder = null

        try{
            const raw = localStorage.getItem(storageKey)
            if(raw === null){
                const isSourceScope = sourceId && Number(serverViewPreference?.lead_source_id) === Number(sourceId)
                const isGlobalScope = serverViewPreference?.scope_key === 'global'
                if(isSourceScope || isGlobalScope){
                    const prefVisible = normalizeLayoutKeys(serverViewPreference?.visible_columns)
                    if(prefVisible.length){
                        nextVisible = new Set(prefVisible)
                    }
                    const prefOrder = normalizeLayoutKeys(serverViewPreference?.column_order)
                    if(prefOrder.length){
                        nextOrder = prefOrder
                    }
                }
                if(!nextVisible){
                    nextVisible = new Set(DEFAULT_VISIBLE)
                }
            }else{
                const saved = normalizeLayoutKeys(JSON.parse(raw || '[]'))
                if(Array.isArray(saved)){
                    nextVisible = new Set(saved)
                }
            }
        }catch(e){}

        try{
            const rawOrder = localStorage.getItem(orderKey)
            if(rawOrder){
                const savedOrder = normalizeLayoutKeys(JSON.parse(rawOrder || '[]'))
                if(Array.isArray(savedOrder)){
                    nextOrder = savedOrder
                }
            }
        }catch(e){}

        if(nextVisible) visibleColumns = nextVisible
        if(Array.isArray(nextOrder)) columnOrder = nextOrder

        rebuildColumnsModal()
        applyColumnVisibility()
        applyColumnOrder()
        restoreLastLayoutSelection(sourceId)
    }

    function getLayoutsStorageKey(){
        const sourceId = Number.isFinite(layoutSourceContext) && layoutSourceContext > 0
            ? layoutSourceContext
            : getCurrentSourceId()
        return sourceId ? `${LAYOUTS_STORAGE_PREFIX}:${sourceId}` : null
    }

    function loadSavedLayouts(){
        try{
            const key = getLayoutsStorageKey()
            if(!key) return {}
            const raw = localStorage.getItem(key)
            const parsed = raw ? JSON.parse(raw) : {}
            return parsed && typeof parsed === 'object' ? parsed : {}
        }catch(e){
            return {}
        }
    }

    function saveSavedLayouts(layouts){
        const key = getLayoutsStorageKey()
        if(!key) return
        localStorage.setItem(key, JSON.stringify(layouts || {}))
    }

    function getLastLayoutStorageKey(sourceId = null){
        const source = sourceId != null
            ? Number(sourceId)
            : (Number.isFinite(layoutSourceContext) && layoutSourceContext > 0 ? layoutSourceContext : getCurrentSourceId())
        return source && Number.isFinite(source) && source > 0
            ? `${LAST_LAYOUT_STORAGE_PREFIX}:${source}`
            : null
    }

    function saveLastLayoutSelection(name, sourceId = null){
        const key = getLastLayoutStorageKey(sourceId)
        if(!key) return
        const value = String(name || '').trim()
        if(value === LAST_LAYOUT_DEFAULT_SENTINEL){
            localStorage.setItem(key, LAST_LAYOUT_DEFAULT_SENTINEL)
            return
        }
        if(!value){
            localStorage.removeItem(key)
            return
        }
        localStorage.setItem(key, value)
    }

    function loadLastLayoutSelectionRaw(sourceId = null){
        const key = getLastLayoutStorageKey(sourceId)
        if(!key) return null
        const raw = localStorage.getItem(key)
        if(raw === null) return null
        return String(raw).trim()
    }

    function loadLastLayoutSelection(sourceId = null){
        const raw = loadLastLayoutSelectionRaw(sourceId)
        if(!raw || raw === LAST_LAYOUT_DEFAULT_SENTINEL) return ''
        return raw
    }

    function restoreLastLayoutSelection(sourceId = null){
        const layouts = loadSavedLayouts()
        const lastRaw = loadLastLayoutSelectionRaw(sourceId)
        if(lastRaw === LAST_LAYOUT_DEFAULT_SENTINEL){
            resetColumnsToDefault()
            refreshLayoutPresetOptions()
            return
        }
        const last = lastRaw || ''
        if(last && layouts[last]){
            refreshLayoutPresetOptions(last)
            applyNamedLayout(last, { silent: true, markSelected: true })
            return
        }
        const names = Object.keys(layouts || {})
        if(names.length){
            const normalize = (v)=>String(v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            const preferred = names.find((name)=>{
                const key = normalize(name).replace(/\s+/g, '')
                return key === 'visao1'
            }) || names.sort((a,b)=>a.localeCompare(b, 'pt-BR'))[0]

            if(preferred){
                saveLastLayoutSelection(preferred, sourceId)
                refreshLayoutPresetOptions(preferred)
                applyNamedLayout(preferred, { silent: true, markSelected: true })
                return
            }
        }
        if(last){
            saveLastLayoutSelection('', sourceId)
        }
        resetColumnsToDefault()
        refreshLayoutPresetOptions()
    }

    function getLayoutSnapshot(){
        return {
            visible_columns: Array.from(visibleColumns),
            column_order: Array.isArray(columnOrder) ? columnOrder : []
        }
    }

    function refreshLayoutPresetOptions(selectedName = ''){
        if(!layoutPresetSelect) return
        const hasSource = !!getLayoutsStorageKey()
        layoutPresetSelect.disabled = !hasSource
        const layouts = loadSavedLayouts()
        const names = Object.keys(layouts).sort((a,b)=>a.localeCompare(b, 'pt-BR'))
        const createOption = hasSource && names.length === 0
            ? [`<option value="__manage_create__">↳ Criar visualização...</option>`]
            : []
        layoutPresetSelect.innerHTML = [
            `<option value="">${hasSource ? '-------- Selecionar --------' : '-------- --------'}</option>`,
            ...createOption,
            ...names.map(name => `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`)
        ].join('')
        if(selectedName && layouts[selectedName]){
            layoutPresetSelect.value = selectedName
        }
        syncLayoutSelectPlaceholderState()
    }

    function syncLayoutSelectPlaceholderState(){
        if(!layoutPresetSelect) return
        const empty = !String(layoutPresetSelect.value || '').trim()
        layoutPresetSelect.classList.toggle('is-placeholder', empty)
    }

    function clearLayoutSelectionUI(){
        if(!layoutPresetSelect) return
        layoutPresetSelect.value = ''
        syncLayoutSelectPlaceholderState()
    }

    function getSelectedLayoutName(){
        const selected = String(layoutPresetSelect?.value || '').trim()
        return selected || ''
    }

    function applyNamedLayout(name, options = {}){
        const { silent = false, markSelected = true } = options
        const layouts = loadSavedLayouts()
        const selected = layouts?.[name]
        if(!selected) return

        const nextVisible = normalizeLayoutKeys(selected.visible_columns)
        const nextOrder = normalizeLayoutKeys(selected.column_order)

        if(nextVisible.length){
            visibleColumns = new Set(nextVisible)
        }else{
            visibleColumns = new Set(DEFAULT_VISIBLE)
        }
        columnOrder = nextOrder.length ? nextOrder : [...baseColumns.map(c=>c.key)]
        persistColumns()
        persistOrder()
        rebuildColumnsModal()
        applyColumnVisibility()
        applyColumnOrder()
        if(markSelected && layoutPresetSelect){
            layoutPresetSelect.value = name
            syncLayoutSelectPlaceholderState()
        }
        if(!silent){
            showToast(`Visualização aplicada: ${name}`)
        }
    }

    // resetColumnsToDefault moved up near applyStorageForSource


    /* ======================================================
       FORMATADORES
    ====================================================== */

    const formatName = titleCasePt
    const formatCity = titleCasePt
    const formatUF   = v => v ? v.toUpperCase().slice(0,2) : ''

    const formatSex = v =>
        v === 'M' ? '♂' :
        v === 'F' ? '♀' : ''


    /* ---------------- CPF ---------------- */
    const formatCPF = v =>
        v?.replace(/\D/g,'')
         .replace(/(\d{3})(\d)/,'$1.$2')
         .replace(/(\d{3})(\d)/,'$1.$2')
         .replace(/(\d{3})(\d{1,2})$/,'$1-$2') || ''


    /* ======================================================
       PHONE (SOMENTE MÁSCARA • NÃO ALTERA DADOS)
       Remove prefixos: +55 | 55 | 055 | 0055
    ====================================================== */
    const formatPhone = v => {

        if(!v) return ''

        let digits = v.replace(/\D/g,'')
        if(!digits) return ''

        // remove qualquer prefixo de país BR
        digits = digits.replace(/^(\+?55|0+55)/, '')

        // agora deve ser: DDD + número
        if(digits.length < 10) return v

        const ddd    = digits.slice(0,2)
        const number = digits.slice(2)

        // celular (9)
        if(number.length === 9){
            return `+55 (${ddd}) ${number.slice(0,5)}-${number.slice(5)}`
        }

        // fixo (8)
        if(number.length === 8){
            return `+55 (${ddd}) ${number.slice(0,4)}-${number.slice(4)}`
        }

        return `+55 (${ddd}) ${number}`
    }


    /* ---------------- SCORE ---------------- */
    const scoreClass = s=>{
        if(s >= 80) return 'score-high'
        if(s >= 60) return 'score-mid'
        return 'score-low'
    }

    const safeText = v =>{
        if(v === null || v === undefined) return ''
        if(typeof v === 'object') return JSON.stringify(v)
        return String(v)
    }

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')

    const importController = createImportController({
        elements: {
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
        },
        permissions: {
            canImportLeads,
            canDeleteSources,
            canCancelImport,
            canReprocessImport
        },
        showToast,
        escapeHtml,
        loadSources,
        reloadGrid: ()=>load(true),
        getForceImportGate: ()=>isForceImportGate,
        setForceImportGate
    })

    const isNameKey = key =>
        /nome|sobrenome|primeiro|segundo|apelido|nickname|nomecracha|nome\s*cracha/i.test(String(key))

    const formatExtraValue = (key, value) =>{
        if(value === null || value === undefined) return ''
        if(typeof value === 'string' && isNameKey(key)){
            return titleCasePt(value)
        }
        if(typeof value === 'object') return JSON.stringify(value)
        return String(value)
    }


    /* ======================================================
       LOAD
    ====================================================== */

	    async function load(reset=false){

        if(loading) return
        loading = true
        setLoading(true)

        if(reset){
            if(sampleMode){
                sampleMode = false
            }
            body.innerHTML = ''
	            nextAfterId = null
	            hasMore = true
            total = 0
            filteredTotal = null
            syncFooterCounters()
            if(preserveSelectionOnNextReset){
                preserveSelectionOnNextReset = false
            }else{
                clearSelections()
            }
	        }

	        const urlParams = new URLSearchParams(window.location.search || '')
	        const sourceId = getCurrentSourceId()
	            || (() => {
	                const raw = urlParams.get('source_id') || urlParams.get('lead_source_id') || ''
	                const parsed = Number(raw)
	                return Number.isFinite(parsed) && parsed > 0 ? parsed : null
	            })()
	        const minScore = getMinScoreValue()
	        const isListingWholeFile = !!sourceId && String(search?.value || '').trim() === '' && (minScore === '' || minScore === '0')

	        const params = new URLSearchParams({
	            q: search?.value || '',
	            min_score: minScore,
	            per_page: isListingWholeFile ? 1000 : 500
	        })
	        if(sourceId){
	            params.set('lead_source_id', String(sourceId))
	        }else{
	            if(searchFilters.segment_id) params.set('segment_id', searchFilters.segment_id)
	            if(searchFilters.niche_id) params.set('niche_id', searchFilters.niche_id)
	            if(searchFilters.origin_id) params.set('origin_id', searchFilters.origin_id)
	            ;(searchFilters.cities || []).forEach(city => params.append('cities[]', city))
	            ;(searchFilters.states || []).forEach(uf => params.append('states[]', uf))
	        }

	        if(reset){
	            params.set('with_total', '1')
	        }else if(nextAfterId){
	            params.set('after_id', String(nextAfterId))
	        }

	        const url = `${window.exploreConfig.dbUrl}?${params}`

        try{
            if(reset){
                refreshSourceTotalRecords()
            }
            const r = await fetch(url)
            const d = await r.json()

	            render(d.rows)
	            nextAfterId = d?.next_cursor ? Number(d.next_cursor) : null
	            hasMore = !!d?.has_more && !!nextAfterId
                if(typeof d.total === 'number'){
                    filteredTotal = Number(d.total)
                }
                syncFooterCounters()
	            if(reset && emptyState){
	                emptyState.classList.toggle('d-none', (d.rows || []).length > 0)
            }
            emptyStateController?.updateEmptyState()
        }
        catch(e){
            console.error('Explore load error:', e)
        }

        setLoading(false)
        loading = false
        continuePendingPreviewFocusSearch()
    }


    /* ======================================================
       RENDER
    ====================================================== */

    function render(rows){

        let html = ''

        rows.forEach(r=>{

            const extras = parseExtras(r.extras_json)
            extrasCache.set(r.id, extras)
            trackExtrasColumns(extras)
            const rawName = safeText(r.name || '')
            const rawEmail = safeText(r.email || '')
            const rawCpf = safeText(r.cpf || '')
            const rawPhone = safeText(r.phone || '')
            const rawSex = safeText(r.sex || '')
            const rawScore = safeText(r.score ?? '')
            const rawBirth = safeText(extras?.data_nascimento ?? r.data_nascimento ?? '')

            html += `
            <tr data-id="${r.id}">
                <td data-col="select"><input type="checkbox" data-select-id="${r.id}"></td>

                <td data-col="nome" data-editable="1" data-edit-key="nome" data-edit-raw="${escapeHtml(rawName)}" title="Duplo clique para editar">
                    <div class="registro-name">${formatName(r.name)}</div>
                </td>

                <td data-col="email" data-editable="1" data-edit-key="email" data-edit-raw="${escapeHtml(rawEmail)}" title="Duplo clique para editar">
                    <span class="email-pill">${r.email || ''}</span>
                </td>

                <td data-col="cpf" class="num" data-editable="1" data-edit-key="cpf" data-edit-raw="${escapeHtml(rawCpf)}" title="Duplo clique para editar">${formatCPF(r.cpf)}</td>

                <td data-col="phone" class="num" data-editable="1" data-edit-key="phone" data-edit-raw="${escapeHtml(rawPhone)}" title="Duplo clique para editar">${formatPhone(r.phone)}</td>

                <td data-col="data_nascimento" data-editable="1" data-edit-key="data_nascimento" data-edit-raw="${escapeHtml(rawBirth)}" title="Duplo clique para editar">${escapeHtml(rawBirth)}</td>

                <td data-col="sex" class="text-center" data-editable="1" data-edit-key="sex" data-edit-raw="${escapeHtml(rawSex)}" title="Duplo clique para editar">${formatSex(r.sex)}</td>

                <td data-col="score" data-editable="1" data-edit-key="score" data-edit-raw="${escapeHtml(rawScore)}" title="Duplo clique para editar">
                    <span class="${scoreClass(r.score)}">${r.score}</span>
                </td>
                ${renderExtrasCells(extras)}
            </tr>`
        })

        body.insertAdjacentHTML('beforeend', html)

        if(emptyState && rows.length){
            emptyState.classList.add('d-none')
        }

        total += rows.length
        syncFooterCounters()

        applyColumnVisibility()
        applyColumnOrder()
        syncRowSelectionState()
        tryFocusPendingPreviewRow()
    }

    function parseExtras(raw){
        if(!raw) return {}
        if(typeof raw === 'object') return raw
        try{
            return JSON.parse(raw)
        }catch(e){
            return {}
        }
    }

    function trackExtrasColumns(extras){
        Object.keys(extras || {}).forEach(k=>{
            if(isExtraHidden(k)) return
            if(!extrasColumns.has(k)){
                extrasColumns.add(k)
                addExtrasHeader(k)
                addExtrasCellsToExistingRows(k)
                rebuildColumnsModal()
                applyColumnOrder()
            }
        })
    }

    function addExtrasHeader(key){
        if(!headerRow) return
        if(isExtraHidden(key)) return
        const th = document.createElement('th')
        th.dataset.col = `extra:${key}`
        th.textContent = normalizeColumnLabel(key)
        headerRow.appendChild(th)
    }

    function addExtrasCellsToExistingRows(key){
        if(isExtraHidden(key)) return
        const colKey = `extra:${key}`
        body.querySelectorAll('tr').forEach(tr=>{
            const id = tr.getAttribute('data-id')
            const extras = extrasCache.get(Number(id)) || {}
            const rawValue = safeText(extras[key] ?? '')
            const td = document.createElement('td')
            td.dataset.col = colKey
            td.dataset.editable = '1'
            td.dataset.editKey = key
            td.dataset.editRaw = rawValue
            td.title = 'Duplo clique para editar'
            td.textContent = formatExtraValue(key, extras[key])
            tr.appendChild(td)
        })
    }

    function bootstrapExtrasHeaders(){
        extrasColumns.forEach(key=>{
            addExtrasHeader(key)
        })
    }

    function renderExtrasCells(extras){
        let html = ''
        extrasColumns.forEach(key=>{
            if(isExtraHidden(key)) return
            const raw = safeText(extras[key] ?? '')
            html += `<td data-col="extra:${key}" data-editable="1" data-edit-key="${escapeHtml(key)}" data-edit-raw="${escapeHtml(raw)}" title="Duplo clique para editar">${escapeHtml(formatExtraValue(key, extras[key]))}</td>`
        })
        return html
    }

    function applyColumnVisibility(){
        const allCells = document.querySelectorAll('[data-col]')
        allCells.forEach(el=>{
            const col = el.getAttribute('data-col')
            if(col === 'select') return
            const isExtra = col.startsWith('extra:')
            const key = isExtra ? col.replace('extra:', '') : col
            const visible = visibleColumns.has(key)
            el.classList.toggle('col-hidden', !visible)
        })
        updateTableScrollMode()
    }

    function updateTableScrollMode(){
        if(!wrap) return
        const visibleKeys = Array.from(visibleColumns)
        const onlyBaseVisible = visibleKeys.every(k => baseKeySet.has(k))
        const isDefaultLike = onlyBaseVisible && visibleKeys.length <= baseColumns.length
        const prev = wrap.classList.contains('no-x-scroll')
        wrap.classList.toggle('no-x-scroll', isDefaultLike)
        if(isDefaultLike && !prev){
            clearAppliedColumnWidths()
        }
        if(!isDefaultLike){
            applyColumnWidths()
        }
    }

    function getColKey(el){
        const col = el?.getAttribute?.('data-col') || ''
        if(col === 'select') return 'select'
        if(col.startsWith('extra:')) return col.replace('extra:', '')
        return col
    }

    function getOrderedKeys(){
        const keys = [
            ...baseColumns.map(c=>c.key),
            ...Array.from(extrasColumns)
        ]

        if(!columnOrder.length){
            return keys
        }

        const inOrder = columnOrder.filter(k=>keys.includes(k))
        const missing = keys.filter(k=>!inOrder.includes(k))
        return [...inOrder, ...missing]
    }

    function applyColumnOrder(){
        const order = getOrderedKeys()
        if(!order.length || !headerRow) return

        const colIndex = new Map()
        order.forEach((k,i)=>colIndex.set(k, i))

        const sortByOrder = (a,b)=>{
            const ka = getColKey(a)
            const kb = getColKey(b)
            const ia = colIndex.has(ka) ? colIndex.get(ka) : 9999
            const ib = colIndex.has(kb) ? colIndex.get(kb) : 9999
            return ia - ib
        }

        const headerCells = Array.from(headerRow.children).filter(c=>getColKey(c) !== 'select')
        headerCells.sort(sortByOrder).forEach(cell=>headerRow.appendChild(cell))

        body.querySelectorAll('tr').forEach(tr=>{
            const cells = Array.from(tr.children).filter(c=>getColKey(c) !== 'select')
            cells.sort(sortByOrder).forEach(cell=>tr.appendChild(cell))
        })
        addResizeHandles()
        applyColumnWidths()
    }

    function showToast(message){
        if(!toastEl) return
        toastEl.textContent = message
        toastEl.classList.add('is-visible')
        setTimeout(()=>{
            toastEl.classList.remove('is-visible')
        }, 1800)
    }

    /* ======================================================
       COLUMN RESIZE
    ====================================================== */

    const COL_WIDTHS_KEY = 'grade_explore_col_widths'
    let columnWidths = {}

    const loadColumnWidths = ()=>{
        try{
            const raw = localStorage.getItem(COL_WIDTHS_KEY)
            columnWidths = raw ? JSON.parse(raw) : {}
        }catch(e){
            columnWidths = {}
        }
    }

    const saveColumnWidths = ()=>{
        try{
            localStorage.setItem(COL_WIDTHS_KEY, JSON.stringify(columnWidths))
        }catch(e){}
    }

    const resetColumnWidths = ()=>{
        columnWidths = {}
        try{
            localStorage.removeItem(COL_WIDTHS_KEY)
        }catch(e){}
        if(!headerRow) return
        headerRow.querySelectorAll('[data-col]').forEach(th=>{
            th.style.width = ''
            th.style.minWidth = ''
        })
        body?.querySelectorAll('[data-col]').forEach(td=>{
            td.style.width = ''
            td.style.minWidth = ''
        })
    }

    const applyColumnWidths = ()=>{
        if(!headerRow) return
        if(wrap?.classList.contains('no-x-scroll')) return
        Object.entries(columnWidths || {}).forEach(([key, width])=>{
            const th = headerRow.querySelector(`[data-col="${key}"], [data-col="extra:${key}"]`)
            if(th){
                th.style.width = `${width}px`
                th.style.minWidth = `${width}px`
            }
            body?.querySelectorAll(`[data-col="${key}"], [data-col="extra:${key}"]`).forEach(td=>{
                td.style.width = `${width}px`
                td.style.minWidth = `${width}px`
            })
        })
    }

    const clearAppliedColumnWidths = ()=>{
        if(!headerRow) return
        Object.keys(columnWidths || {}).forEach((key)=>{
            const th = headerRow.querySelector(`[data-col="${key}"], [data-col="extra:${key}"]`)
            if(th){
                th.style.width = ''
                th.style.minWidth = ''
            }
            body?.querySelectorAll(`[data-col="${key}"], [data-col="extra:${key}"]`).forEach((td)=>{
                td.style.width = ''
                td.style.minWidth = ''
            })
        })
    }

    const addResizeHandles = ()=>{
        if(!headerRow) return
        headerRow.querySelectorAll('th[data-col]').forEach(th=>{
            const key = getColKey(th)
            if(!key || key === 'select') return
            if(th.querySelector('.col-resizer')) return
            const handle = document.createElement('span')
            handle.className = 'col-resizer'
            handle.addEventListener('mousedown', (e)=>{
                e.preventDefault()
                e.stopPropagation()
                const startX = e.clientX
                const startWidth = th.getBoundingClientRect().width
                const onMove = (ev)=>{
                    const next = Math.max(80, startWidth + (ev.clientX - startX))
                    columnWidths[key] = Math.round(next)
                    th.style.width = `${next}px`
                    th.style.minWidth = `${next}px`
                    body?.querySelectorAll(`[data-col="${key}"], [data-col="extra:${key}"]`).forEach(td=>{
                        td.style.width = `${next}px`
                        td.style.minWidth = `${next}px`
                    })
                }
                const onUp = ()=>{
                    document.removeEventListener('mousemove', onMove)
                    document.removeEventListener('mouseup', onUp)
                    saveColumnWidths()
                }
                document.addEventListener('mousemove', onMove)
                document.addEventListener('mouseup', onUp)
            })
            th.appendChild(handle)
        })
    }

	    function updateSelectionBar(){
	        const computed = selectionMode === 'manual'
	            ? selectedIds.size
	            : getAllMatchingSelectedCount()

	        const isUnknownAll = selectionMode === 'all_matching' && computed === null
	        const countForUi = isUnknownAll ? 'todos' : String(computed ?? 0)
	        const countForEvent = isUnknownAll ? null : (computed ?? 0)

	        if(selectionCount) selectionCount.textContent = countForUi
	        if(selectionBar){
	            const shouldShow = selectionMode === 'all_matching' ? true : (computed ?? 0) > 0
	            selectionBar.classList.toggle('d-none', !shouldShow)
                wrap?.classList.toggle('has-selection-active', shouldShow)
	        }
	        const selectedCountEl = document.getElementById('selectedCount')
	        if(selectedCountEl){
	            selectedCountEl.textContent = countForUi
	        }

	        try{
	            window.dispatchEvent(new CustomEvent('explore:selection:changed', { detail: { count: countForEvent, mode: selectionMode } }))
	        }catch(e){}
	    }

	    function clearSelections(){
	        selectedIds.clear()
	        excludedIds.clear()
	        selectionMode = 'manual'
	        allMatchingTotal = null
	        body.querySelectorAll('[data-select-id]').forEach(chk=>{ chk.checked = false })
	        if(checkAll){
                checkAll.checked = false
                checkAll.indeterminate = false
            }
	        updateSelectionBar()
	    }

	    function getExploreFilterSnapshot(){
	        const params = buildCurrentExploreDbParams()
	        const snapshot = {
	            q: String(params.get('q') || ''),
	            min_score: String(params.get('min_score') || ''),
	            lead_source_id: params.get('lead_source_id') ? Number(params.get('lead_source_id')) : null,
	            segment_id: params.get('segment_id') ? Number(params.get('segment_id')) : null,
	            niche_id: params.get('niche_id') ? Number(params.get('niche_id')) : null,
	            origin_id: params.get('origin_id') ? Number(params.get('origin_id')) : null,
	            cities: params.getAll('cities[]'),
	            states: params.getAll('states[]'),
	        }
	        Object.keys(snapshot).forEach((k)=>{
	            const v = snapshot[k]
	            if(v === null) return
	            if(typeof v === 'number' && !Number.isFinite(v)) snapshot[k] = null
	        })
	        return snapshot
	    }

	    function buildCurrentExploreDbParams(){
        const urlParams = new URLSearchParams(window.location.search || '')
        const sourceId = getCurrentSourceId()
            || (() => {
                const raw = urlParams.get('source_id') || urlParams.get('lead_source_id') || ''
                const parsed = Number(raw)
                return Number.isFinite(parsed) && parsed > 0 ? parsed : null
            })()

        const params = new URLSearchParams({
            q: search?.value || '',
            min_score: getMinScoreValue(),
        })
        if(sourceId){
            params.set('lead_source_id', String(sourceId))
        }else{
            if(searchFilters.segment_id) params.set('segment_id', searchFilters.segment_id)
            if(searchFilters.niche_id) params.set('niche_id', searchFilters.niche_id)
            if(searchFilters.origin_id) params.set('origin_id', searchFilters.origin_id)
            ;(searchFilters.cities || []).forEach(city => params.append('cities[]', city))
            ;(searchFilters.states || []).forEach(uf => params.append('states[]', uf))
        }

	        return params
	    }

	    async function selectAllMatching(){
	        if(!window.exploreConfig?.dbUrl) return
	        try{
	            showToast('Selecionando todos os encontrados...')

	            selectionMode = 'all_matching'
	            selectedIds.clear()
	            excludedIds.clear()

	            // Prefer current total if already known (first load/filter changes).
	            let total = null
	            if(foundCount){
	                const parsed = Number(String(foundCount.textContent || '').replace(/\\D+/g, ''))
	                if(Number.isFinite(parsed)) total = parsed
	            }

	            // If total is unknown, ask backend once (does a count) and keep it cached.
	            if(total === null){
	                const params = buildCurrentExploreDbParams()
	                params.set('with_total', '1')
	                params.set('per_page', '1')
	                const res = await fetch(`${window.exploreConfig.dbUrl}?${params.toString()}`, {
	                    headers: { 'Accept': 'application/json' }
	                })
	                const data = await res.json().catch(() => null)
	                if(res.ok && data && typeof data.total === 'number'){
	                    total = data.total
	                }
	            }
	            allMatchingTotal = (typeof total === 'number' && Number.isFinite(total)) ? total : null
                if(typeof allMatchingTotal === 'number'){
                    filteredTotal = Number(allMatchingTotal)
                    syncFooterCounters()
                }

                syncRowSelectionState()

	            const picked = getAllMatchingSelectedCount()
	            if(typeof picked === 'number' && typeof allMatchingTotal === 'number'){
	                showToast(`Selecionados: ${picked} (de ${allMatchingTotal})`)
	            }else{
	                showToast('Selecionados: todos os encontrados')
	            }
	        }catch(e){
	            showToast('Erro ao selecionar todos.')
	        }
	    }

	    function syncRowSelectionState(){
            const visibleChecks = Array.from(body.querySelectorAll('[data-select-id]'))
	        visibleChecks.forEach(chk=>{
	            const id = Number(chk.getAttribute('data-select-id'))
                if(selectionMode === 'manual'){
                    chk.indeterminate = false
                    chk.checked = selectedIds.has(id)
                }else{
                    // Keep row checkboxes visually clear in global-select mode.
                    chk.checked = false
                    chk.indeterminate = false
                }
	        })
            if(checkAll){
                if(selectionMode === 'manual'){
                    checkAll.indeterminate = false
                    checkAll.checked = visibleChecks.length > 0 && visibleChecks.every(chk => chk.checked)
                }else{
                    checkAll.checked = false
                    checkAll.indeterminate = false
                }
            }
	        updateSelectionBar()
	    }

    function openColumnsModal(){
        if(!columnsModalEl) return
        rebuildColumnsModal()
        const Modal = window.bootstrap?.Modal
        if(Modal){
            const instance = Modal.getOrCreateInstance(columnsModalEl)
            instance.show()
        }
    }

    function rebuildColumnsModal(){
        if(!columnsList) return
        const items = []
        const baseKeys = new Set(baseColumns.map(c=>c.key))
        baseColumns.forEach(c=>baseAliases.add(normalizeKey(c.key)))
        const seenKeys = new Set()
        const seenLabels = new Set()

        baseColumns.forEach(c=>{
            items.push({ key: c.key, label: c.label })
            seenKeys.add(c.key)
            seenLabels.add(normalizeKey(c.label))
        })

        const extraKeys = new Set()
        Array.from(extrasColumns).forEach(k=>extraKeys.add(k))
        visibleColumns.forEach(k=>{
            if(!baseKeys.has(k)) extraKeys.add(k)
        })
        columnOrder.forEach(k=>{
            if(!baseKeys.has(k)) extraKeys.add(k)
        })
        if(headerRow){
            Array.from(headerRow.children).forEach(cell=>{
                const k = getColKey(cell)
                if(k && k !== 'select' && !baseKeys.has(k)) extraKeys.add(k)
            })
        }

        Array.from(extraKeys).forEach(k=>{
            if(k === 'select') return
            const label = normalizeColumnLabel(k)
            if(isExtraHidden(k)){
                return
            }
            if(seenKeys.has(k)) return
            const labelKey = normalizeKey(label)
            if(seenLabels.has(labelKey)) return
            items.push({ key: k, label })
            seenKeys.add(k)
            seenLabels.add(labelKey)
        })

        const orderedKeys = getOrderedKeys()
        const baseIndex = new Map()
        baseColumns.forEach((c,i)=>baseIndex.set(c.key, i))
        const orderIndex = new Map()
        orderedKeys.forEach((k,i)=>orderIndex.set(k, i))

        items.sort((a,b)=>{
            const ia = orderIndex.has(a.key)
                ? orderIndex.get(a.key)
                : (baseIndex.has(a.key) ? baseIndex.get(a.key) : 9999)
            const ib = orderIndex.has(b.key)
                ? orderIndex.get(b.key)
                : (baseIndex.has(b.key) ? baseIndex.get(b.key) : 9999)
            if(ia !== ib) return ia - ib
            return String(a.label || '').localeCompare(String(b.label || ''), 'pt-BR')
        })

        columnsList.innerHTML = items.map(i=>{
            const checked = visibleColumns.has(i.key) ? 'checked' : ''
            return `
                <div class="columns-item" draggable="true" data-col-key="${i.key}">
                    <span class="columns-handle">⋮⋮</span>
                    <label class="columns-item__label">
                        <input type="checkbox" data-col-key="${i.key}" ${checked}>
                        <span
                            title="${i.label}"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                        >${i.label}</span>
                    </label>
                </div>
            `
        }).join('')

        columnsList.querySelectorAll('input[type="checkbox"]').forEach(chk=>{
            chk.addEventListener('change', e=>{
                const key = e.target.getAttribute('data-col-key')
                if(e.target.checked) visibleColumns.add(key)
                else visibleColumns.delete(key)
                persistColumns()
                applyColumnVisibility()
            })
        })

        columnsList.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=>{
            if(window.bootstrap?.Tooltip){
                bootstrap.Tooltip.getOrCreateInstance(el)
            }
        })

        enableDragReorder()
    }

    function persistColumns(){
        localStorage.setItem(storageKey, JSON.stringify(Array.from(visibleColumns)))
    }

    function persistOrder(){
        localStorage.setItem(orderKey, JSON.stringify(columnOrder))
    }

    function getCurrentSourceId(){
        const id = sourceSelect?.value || sourceSelectMobile?.value || ''
        if(!id) return null
        const parsed = Number(id)
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null
    }

    const overridesController = createOverridesController({
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
        reload: ()=>load(true)
    })

    async function saveViewPreference(showMessages = true){
        const url = window.exploreConfig?.saveViewPreferenceUrl
        if(!url) return

        const sourceId = getCurrentSourceId()
        const payload = {
            lead_source_id: sourceId,
            visible_columns: Array.from(visibleColumns),
            column_order: Array.isArray(columnOrder) ? columnOrder : []
        }

        if(!payload.visible_columns.length){
            if(showMessages) showToast('Selecione ao menos 1 coluna')
            return
        }

        try{
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(payload)
            })

            if(!res.ok){
                if(showMessages) showToast('Erro ao salvar visualização')
                return
            }

            if(showMessages) showToast('Visualização salva')
        }catch(e){
            console.error('Save view preference error:', e)
            if(showMessages) showToast('Erro ao salvar visualização')
        }
    }

    async function runModalButtonWithFrost(button, action, delayMs = 2000){
        if(!button || button.disabled) return
        button.disabled = true
        button.classList.add('is-submitting')
        const startedAt = Date.now()
        try{
            await action()
        }finally{
            const elapsed = Date.now() - startedAt
            const wait = Math.max(0, delayMs - elapsed)
            if(wait > 0){
                await new Promise(resolve => setTimeout(resolve, wait))
            }
            button.classList.remove('is-submitting')
            button.disabled = false
        }
    }

    async function createLayoutFromManage(){
        if(!getLayoutsStorageKey()){
            showToast('Selecione um arquivo para salvar visualização')
            return
        }
        const name = (layoutManageCreateInput?.value || '').trim()
        if(!name){
            showToast('Informe um nome para a visualização')
            layoutManageCreateInput?.focus()
            return
        }

        const layouts = loadSavedLayouts()
        if(layouts[name]){
            showToast('Já existe uma visualização com esse nome')
            layoutManageCreateInput?.focus()
            layoutManageCreateInput?.select()
            return
        }
        layouts[name] = getLayoutSnapshot()
        saveSavedLayouts(layouts)
        refreshLayoutPresetOptions(name)
        saveLastLayoutSelection(name)

        await saveViewPreference(false)
        showToast(`Visualização salva: ${name}`)
        if(layoutManageCreateInput){
            layoutManageCreateInput.value = ''
        }
        openManageLayoutModal('manage')
    }

    function suggestNextLayoutName(){
        if(!getLayoutsStorageKey()){
            return ''
        }
        const layouts = loadSavedLayouts()
        const baseName = 'Visao'
        let next = 1
        while(layouts[`${baseName} ${next}`]){
            next += 1
        }
        return `${baseName} ${next}`
    }

    const layoutManageState = {
        mode: 'create', // create | manage
        panel: null,    // rename | update | delete | null
    }

    function normalizeLayoutName(v){
        return String(v || '').trim()
    }

    function setLayoutManageError(el, msg){
        if(!el) return
        el.textContent = msg || ''
        el.classList.toggle('has-error', !!msg)
    }

    function getCreateNameError(){
        const name = normalizeLayoutName(layoutManageCreateInput?.value)
        if(!name) return 'Informe um nome para a visualização.'
        const layouts = loadSavedLayouts()
        if(layouts[name]) return 'Já existe uma visualização com esse nome.'
        return ''
    }

    function getRenameNameError(){
        const selectedName = getSelectedLayoutName()
        const nextName = normalizeLayoutName(layoutManageNameInput?.value)
        if(!selectedName) return 'Selecione uma visualização.'
        if(!nextName) return 'Informe o novo nome.'
        const layouts = loadSavedLayouts()
        if(nextName !== selectedName && layouts[nextName]) return 'Já existe uma visualização com esse nome.'
        if(nextName === selectedName) return 'Informe um nome diferente do atual.'
        return ''
    }

    function setLayoutManagePanel(panel){
        layoutManageState.panel = panel
        layoutManageRenameSection?.classList.toggle('d-none', panel !== 'rename')
        layoutManageUpdateSection?.classList.toggle('d-none', panel !== 'update')
        layoutManageDeleteSection?.classList.toggle('d-none', panel !== 'delete')
    }

    function refreshLayoutManageValidity(){
        const createErr = getCreateNameError()
        const renameErr = getRenameNameError()
        setLayoutManageError(layoutManageCreateError, layoutManageState.mode === 'create' ? createErr : '')
        setLayoutManageError(layoutManageRenameError, layoutManageState.panel === 'rename' ? renameErr : '')

        if(layoutManageCreateBtn){
            if(layoutManageState.mode === 'create'){
                layoutManageCreateBtn.disabled = !!createErr
                layoutManageCreateBtn.textContent = 'Salvar'
            }else{
                const hasSelected = !!getSelectedLayoutName()
                layoutManageCreateBtn.disabled = !hasSelected
                layoutManageCreateBtn.textContent = 'Salvar'
            }
        }
        if(layoutManageRenameBtn){
            layoutManageRenameBtn.disabled = layoutManageState.panel !== 'rename' || !!renameErr
        }
        if(layoutManageUpdateBtn){
            layoutManageUpdateBtn.disabled = layoutManageState.panel !== 'update' || !getSelectedLayoutName()
        }
        if(layoutManageDeleteBtn){
            layoutManageDeleteBtn.disabled = layoutManageState.panel !== 'delete' || !getSelectedLayoutName()
        }
    }

    async function runLayoutManagePrimaryAction(){
        if(layoutManageState.mode === 'create'){
            return createLayoutFromManage()
        }
        return updateSelectedLayoutFromCurrent()
    }

    function openManageLayoutModal(mode = 'manage'){
        if(!getLayoutsStorageKey()){
            showToast('Selecione um arquivo para gerenciar visualizações')
            return
        }
        const layouts = loadSavedLayouts()
        const selectedName = getSelectedLayoutName()
        const hasSelected = !!selectedName && !!layouts[selectedName]
        const createMode = mode === 'create' || !hasSelected
        layoutManageState.mode = createMode ? 'create' : 'manage'

        if(layoutManageCreateInput){
            if(createMode){
                layoutManageCreateInput.value = suggestNextLayoutName()
                layoutManageCreateInput.focus()
                layoutManageCreateInput.select()
            }
        }
        if(layoutManageCreateSection){
            layoutManageCreateSection.classList.toggle('d-none', !createMode)
        }
        if(layoutManageExistingSection){
            layoutManageExistingSection.classList.toggle('d-none', createMode)
        }

        if(hasSelected){
            if(layoutManageCurrentName) layoutManageCurrentName.textContent = selectedName
            if(layoutManageNameInput){
                layoutManageNameInput.value = selectedName
            }
        }else{
            if(layoutManageCurrentName) layoutManageCurrentName.textContent = '-'
            if(layoutManageNameInput) layoutManageNameInput.value = ''
        }
        if(createMode){
            setLayoutManagePanel(null)
        }else{
            setLayoutManagePanel('update')
        }
        refreshLayoutManageValidity()
        if(window.bootstrap?.Modal && layoutManageModalEl){
            window.bootstrap.Modal.getOrCreateInstance(layoutManageModalEl).show()
        }
    }

    function renameSelectedLayout(){
        const selectedName = getSelectedLayoutName()
        if(!selectedName) return
        const nextName = String(layoutManageNameInput?.value || '').trim()
        if(!nextName){
            showToast('Informe o novo nome da visualização')
            layoutManageNameInput?.focus()
            return
        }

        const layouts = loadSavedLayouts()
        if(!layouts[selectedName]){
            showToast('Visualização não encontrada')
            return
        }
        if(nextName !== selectedName && layouts[nextName]){
            showToast('Já existe uma visualização com esse nome')
            return
        }

        layouts[nextName] = layouts[selectedName]
        if(nextName !== selectedName){
            delete layouts[selectedName]
        }
        saveSavedLayouts(layouts)
        refreshLayoutPresetOptions(nextName)
        const last = loadLastLayoutSelection()
        if(last === selectedName){
            saveLastLayoutSelection(nextName)
        }
        if(layoutManageCurrentName) layoutManageCurrentName.textContent = nextName
        showToast(`Visualização renomeada: ${nextName}`)
    }

    function updateSelectedLayoutFromCurrent(){
        const selectedName = getSelectedLayoutName()
        if(!selectedName){
            showToast('Selecione uma visualização para atualizar')
            return
        }
        const layouts = loadSavedLayouts()
        if(!layouts[selectedName]){
            showToast('Visualização não encontrada')
            return
        }
        layouts[selectedName] = getLayoutSnapshot()
        saveSavedLayouts(layouts)
        saveViewPreference(false)
        showToast(`Visualização atualizada: ${selectedName}`)
    }

    function requestDeleteSelectedLayout(){
        const selectedName = getSelectedLayoutName()
        if(!selectedName){
            showToast('Selecione uma visualização para excluir')
            return
        }
        if(layoutDeleteConfirmText){
            layoutDeleteConfirmText.textContent = `Excluir a visualização "${selectedName}"?`
        }
        if(window.bootstrap?.Modal && layoutDeleteConfirmModalEl){
            window.bootstrap.Modal.getOrCreateInstance(layoutDeleteConfirmModalEl).show()
        }
    }

    function deleteSelectedLayout(){
        const selectedName = getSelectedLayoutName()
        if(!selectedName) return
        const manageModalIsOpen = !!(layoutManageModalEl && layoutManageModalEl.classList.contains('show'))
        const layouts = loadSavedLayouts()
        if(!layouts[selectedName]){
            showToast('Visualização não encontrada')
            return
        }
        delete layouts[selectedName]
        saveSavedLayouts(layouts)

        const remainingNames = Object.keys(layouts).sort((a,b)=>a.localeCompare(b, 'pt-BR'))
        if(remainingNames.length === 0){
            saveLastLayoutSelection(LAST_LAYOUT_DEFAULT_SENTINEL)
            clearLayoutSelectionUI()
            refreshLayoutPresetOptions()
            resetColumnsToDefault()
            saveViewPreference(false)
            if(manageModalIsOpen){
                openManageLayoutModal('create')
            }else if(window.bootstrap?.Modal && layoutManageModalEl){
                window.bootstrap.Modal.getOrCreateInstance(layoutManageModalEl).hide()
            }
            showToast('Visualização excluída')
            return
        }

        const nextSelectedName = remainingNames[0]
        saveLastLayoutSelection(nextSelectedName)
        refreshLayoutPresetOptions(nextSelectedName)
        applyNamedLayout(nextSelectedName, { silent: true, markSelected: true })
        saveViewPreference(false)

        if(manageModalIsOpen){
            openManageLayoutModal('manage')
        }

        showToast(`Visualização excluída. Ativa: ${nextSelectedName}`)
    }

    function setCellRawValue(cell, value){
        if(!cell) return
        cell.dataset.editRaw = value == null ? '' : String(value)
    }

    function updateRenderedCell(cell, key, value, tr, overridden = true){
        if(!cell) return
        const normalized = value == null ? '' : String(value)
        setCellRawValue(cell, normalized)
        cell.classList.toggle('is-overridden', !!overridden)

        if(key === 'nome'){
            cell.innerHTML = `
                <div class="registro-name">${escapeHtml(formatName(normalized))}</div>
            `
            return
        }

        if(key === 'email'){
            cell.innerHTML = `<span class="email-pill">${escapeHtml(normalized)}</span>`
            return
        }

        if(key === 'cpf'){
            cell.textContent = formatCPF(normalized)
            return
        }

        if(key === 'phone'){
            cell.textContent = formatPhone(normalized)
            return
        }

        if(key === 'sex'){
            cell.textContent = formatSex(normalized)
            return
        }

        if(key === 'score'){
            const score = Number(normalized)
            const safeScore = Number.isFinite(score) ? score : normalized
            const cls = Number.isFinite(score) ? scoreClass(score) : 'score-low'
            cell.innerHTML = `<span class="${cls}">${escapeHtml(String(safeScore))}</span>`
            return
        }

        cell.textContent = formatExtraValue(key, normalized)
        const leadId = Number(tr?.getAttribute('data-id'))
        if(Number.isFinite(leadId)){
            const extras = extrasCache.get(leadId) || {}
            extras[key] = normalized
            extrasCache.set(leadId, extras)
        }
    }

    async function saveInlineOverride(leadId, key, value){
        const url = window.exploreConfig?.saveOverrideUrl
        if(!url){
            return { ok: false, message: 'Configuração ausente' }
        }

        const sourceId = getCurrentSourceId()
        const payload = {
            lead_id: leadId,
            column_key: key,
            value,
            source_id: sourceId
        }

        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(payload)
        })
        const data = await res.json().catch(()=>null)
        if(!res.ok || !data?.ok){
            return { ok: false, message: data?.message || `HTTP ${res.status}` }
        }

        return {
            ok: true,
            value: data.value ?? '',
            overridden: !!data.overridden
        }
    }

    function applyInlineInputRules(input, key){
        if(!input) return
        if(key === 'cpf' || key === 'phone' || key === 'score'){
            input.setAttribute('inputmode', 'numeric')
        }
        if(key === 'sex'){
            input.setAttribute('maxlength', '1')
        }
        if(key === 'uf'){
            input.setAttribute('maxlength', '2')
        }

        input.addEventListener('input', ()=>{
            let v = input.value || ''
            if(key === 'cpf'){
                v = v.replace(/\D+/g, '').slice(0, 11)
            }else if(key === 'phone'){
                v = v.replace(/\D+/g, '').slice(0, 13)
            }else if(key === 'sex'){
                v = v.toUpperCase().replace(/[^MF]/g, '').slice(0, 1)
            }else if(key === 'uf'){
                v = v.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2)
            }else if(key === 'score'){
                v = v.replace(/[^\d]/g, '').slice(0, 3)
            }
            if(v !== input.value){
                input.value = v
            }
        })
    }

    function canonicalizeInlineValue(key, raw){
        const value = String(raw ?? '').trim()
        if(value === '') return ''

        if(key === 'cpf'){
            return value.replace(/\D+/g, '')
        }

        if(key === 'phone'){
            let digits = value.replace(/\D+/g, '')
            if(digits.startsWith('0055')) digits = digits.slice(4)
            else if(digits.startsWith('055')) digits = digits.slice(3)
            else if(digits.startsWith('55') && digits.length > 11) digits = digits.slice(2)
            if(digits.length === 10 || digits.length === 11){
                return `+55${digits}`
            }
            return digits
        }

        if(key === 'email'){
            return value.toLowerCase()
        }

        if(key === 'sex'){
            return value.toUpperCase().replace(/[^MF]/g, '')
        }

        if(key === 'uf'){
            return value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2)
        }

        if(key === 'score'){
            return value.replace(/[^\d]/g, '')
        }

        return value
    }

    function openInlineCellEditor(cell){
        if(!canInlineEdit){
            showToast('Sem permissão para editar')
            return
        }
        if(!cell || cell.dataset.editing === '1') return
        const tr = cell.closest('tr')
        const leadId = Number(tr?.getAttribute('data-id'))
        const key = cell.dataset.editKey
        if(!leadId || !key) return

        const current = cell.dataset.editRaw || ''
        const previousHtml = cell.innerHTML
        const inputType = key === 'score' ? 'number' : 'text'
        cell.dataset.editing = '1'
        cell.innerHTML = `
            <div class="explore-inline-editor">
                <input class="form-control form-control-sm explore-inline-input" type="${inputType}" value="${escapeHtml(current)}" />
            </div>
        `
        const input = cell.querySelector('.explore-inline-input')
        let committed = false
        applyInlineInputRules(input, key)

        const closeEditor = () => {
            delete cell.dataset.editing
        }

        const restore = () => {
            cell.innerHTML = previousHtml
            closeEditor()
        }

        const submit = async () => {
            if(committed) return
            const nextValue = input?.value ?? ''
            const nextCanonical = canonicalizeInlineValue(key, nextValue)
            const currentCanonical = canonicalizeInlineValue(key, current)
            if(nextCanonical === currentCanonical){
                restore()
                return
            }
            committed = true
            const result = await saveInlineOverride(leadId, key, nextValue)
            if(!result.ok){
                showToast(result.message || 'Falha ao salvar')
                restore()
                return
            }
            closeEditor()
            updateRenderedCell(cell, key, result.value ?? '', tr, result.overridden)
            showToast(result.overridden ? 'Registro atualizado' : 'Sem alteração')
            overridesController.loadOverridesSummary()
        }

        input?.addEventListener('keydown', (e)=>{
            if(e.key === 'Enter'){
                e.preventDefault()
                submit()
            }
            if(e.key === 'Escape'){
                e.preventDefault()
                restore()
            }
        })
        input?.addEventListener('blur', ()=>{
            submit()
        })
        input?.focus()
        input?.select()
    }

    function enableDragReorder(){
        const items = Array.from(columnsList.querySelectorAll('.columns-item'))
        let dragEl = null

        const syncOrder = ()=>{
            columnOrder = Array.from(columnsList.querySelectorAll('.columns-item'))
                .map(el=>el.getAttribute('data-col-key'))
            persistOrder()
            applyColumnOrder()
        }

        items.forEach(item=>{
            item.addEventListener('dragstart', e=>{
                dragEl = item
                e.dataTransfer.effectAllowed = 'move'
                e.dataTransfer.setData('text/plain', item.getAttribute('data-col-key') || '')
                item.classList.add('dragging')
            })
            item.addEventListener('dragend', ()=>{
                if(dragEl) dragEl.classList.remove('dragging')
                dragEl = null
                syncOrder()
            })
            item.addEventListener('dragover', e=>{
                e.preventDefault()
                if(!dragEl || dragEl === item) return
                const rect = item.getBoundingClientRect()
                const next = (e.clientY - rect.top) > rect.height / 2
                columnsList.insertBefore(dragEl, next ? item.nextSibling : item)
            })
        })

        columnsList.addEventListener('drop', e=>{
            e.preventDefault()
            syncOrder()
        })
    }

    columnsBtn?.addEventListener('click', openColumnsModal)
    columnsBtnMobile?.addEventListener('click', openColumnsModal)
    layoutManageCreateBtn?.addEventListener('click', ()=>{
        const shouldCloseAfterSave = layoutManageState.mode !== 'create'
        runModalButtonWithFrost(layoutManageCreateBtn, ()=>runLayoutManagePrimaryAction(), 2000).then(()=>{
            if(shouldCloseAfterSave && window.bootstrap?.Modal && layoutManageModalEl){
                window.bootstrap.Modal.getOrCreateInstance(layoutManageModalEl).hide()
            }
        })
    })
    layoutManageCreateInput?.addEventListener('keydown', (e)=>{
        if(e.key === 'Enter'){
            e.preventDefault()
            layoutManageCreateBtn?.click()
        }
    })
    layoutManageCreateInput?.addEventListener('input', refreshLayoutManageValidity)
    layoutManageNameInput?.addEventListener('input', refreshLayoutManageValidity)
    layoutManageActionRename?.addEventListener('click', ()=>{
        setLayoutManagePanel('rename')
        refreshLayoutManageValidity()
        layoutManageNameInput?.focus()
        layoutManageNameInput?.select()
    })
    layoutManageActionUpdate?.addEventListener('click', ()=>{
        setLayoutManagePanel('update')
        refreshLayoutManageValidity()
    })
    layoutManageActionDelete?.addEventListener('click', ()=>{
        setLayoutManagePanel('delete')
        refreshLayoutManageValidity()
    })
    layoutManageRenameBtn?.addEventListener('click', ()=>{
        runModalButtonWithFrost(layoutManageRenameBtn, ()=>renameSelectedLayout())
    })
    layoutManageUpdateBtn?.addEventListener('click', ()=>{
        runModalButtonWithFrost(layoutManageUpdateBtn, ()=>updateSelectedLayoutFromCurrent())
    })
    layoutManageDeleteBtn?.addEventListener('click', ()=>{
        runModalButtonWithFrost(layoutManageDeleteBtn, async ()=>{
            await new Promise(resolve=>setTimeout(resolve, 2000))
            deleteSelectedLayout()
        }, 0)
    })
    layoutDeleteConfirmBtn?.addEventListener('click', ()=>{
        runModalButtonWithFrost(layoutDeleteConfirmBtn, async ()=>{
            await new Promise(resolve=>setTimeout(resolve, 2000))
            deleteSelectedLayout()
        }, 0)
    })
    layoutManageNameInput?.addEventListener('keydown', (e)=>{
        if(e.key === 'Enter'){
            e.preventDefault()
            layoutManageRenameBtn?.click()
        }
    })
    layoutPresetSelect?.addEventListener('change', ()=>{
        const selected = (layoutPresetSelect.value || '').trim()
        syncLayoutSelectPlaceholderState()
        if(selected === '__manage_create__'){
            clearLayoutSelectionUI()
            openManageLayoutModal('create')
            return
        }
        if(!selected){
            resetColumnsToDefault()
            saveLastLayoutSelection(LAST_LAYOUT_DEFAULT_SENTINEL)
            saveViewPreference(false)
            showToast('Visualização padrão aplicada')
            return
        }
        applyNamedLayout(selected)
        saveLastLayoutSelection(selected)
    })
    layoutPresetSelect?.addEventListener('dblclick', ()=>{
        const selected = (layoutPresetSelect.value || '').trim()
        if(selected === '__manage_create__' || !selected){
            openManageLayoutModal('create')
            return
        }
        openManageLayoutModal('manage')
    })
    viewOverridesBtn?.addEventListener('click', overridesController.openOverridesModal)
    viewOverridesBtnMobile?.addEventListener('click', overridesController.openOverridesModal)
    publishOverridesBtn?.addEventListener('click', overridesController.publishOverrides)
    publishOverridesBtnMobile?.addEventListener('click', overridesController.publishOverrides)
    discardOverridesBtn?.addEventListener('click', overridesController.discardOverrides)
    discardOverridesBtnMobile?.addEventListener('click', overridesController.discardOverrides)

    columnsSelectAll?.addEventListener('click', ()=>{
        baseColumns.forEach(c=>visibleColumns.add(c.key))
        extrasColumns.forEach(k=>visibleColumns.add(k))
        persistColumns()
        rebuildColumnsModal()
        applyColumnVisibility()
    })

    columnsResetDefault?.addEventListener('click', ()=>{
        visibleColumns = new Set(DEFAULT_VISIBLE)
        columnOrder = [
            ...baseColumns.map(c=>c.key)
        ]
        persistColumns()
        persistOrder()
        clearLayoutSelectionUI()
        rebuildColumnsModal()
        applyColumnVisibility()
        applyColumnOrder()
    })

	    wrap.addEventListener('scroll', ()=>{
	        if(!hasMore || loading) return

        const nearBottom =
            wrap.scrollTop + wrap.clientHeight >= wrap.scrollHeight - 180

	        if(nearBottom) load()
	    })


    function debounceReload(){
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(()=>load(true), 300)
    }

    let searchPreviewController = null
    let searchPreviewAbort = null
    let sampleMode = false
    let pendingPreviewFocusLeadId = null
    let pendingPreviewFocusPagesTried = 0
    const MAX_PREVIEW_FOCUS_PAGES = 8
    let previewFocusFlashTimer = null

    function clearPendingPreviewFocus(){
        pendingPreviewFocusLeadId = null
        pendingPreviewFocusPagesTried = 0
    }

    function setPendingPreviewFocus(leadId){
        const parsed = Number(leadId)
        if(!Number.isFinite(parsed) || parsed <= 0){
            clearPendingPreviewFocus()
            return
        }
        pendingPreviewFocusLeadId = parsed
        pendingPreviewFocusPagesTried = 0
    }

    function tryFocusPendingPreviewRow(){
        if(!pendingPreviewFocusLeadId || !body) return false
        const row = body.querySelector(`tr[data-id="${pendingPreviewFocusLeadId}"]`)
        if(!row) return false
        row.scrollIntoView({ behavior: 'smooth', block: 'center' })
        row.classList.add('explore-row-preview-focus')
        if(previewFocusFlashTimer){
            clearTimeout(previewFocusFlashTimer)
        }
        previewFocusFlashTimer = setTimeout(()=>{
            row.classList.remove('explore-row-preview-focus')
        }, 2200)
        clearPendingPreviewFocus()
        return true
    }

    function continuePendingPreviewFocusSearch(){
        if(!pendingPreviewFocusLeadId) return
        if(tryFocusPendingPreviewRow()) return
        if(loading) return
        if(!hasMore || pendingPreviewFocusPagesTried >= MAX_PREVIEW_FOCUS_PAGES){
            clearPendingPreviewFocus()
            return
        }
        pendingPreviewFocusPagesTried += 1
        load(false)
    }

    const previewSelectedIds = new Set()
    let previewSelectAllMatching = false
    let previewRowsCache = []
    let previewTotalCount = 0

    function resetPreviewSelectionState(){
        previewSelectedIds.clear()
        previewSelectAllMatching = false
        previewRowsCache = []
        previewTotalCount = 0
        updatePreviewSelectionSummary()
    }

    function updatePreviewSelectionSummary(){
        if(!exploreSearchPreviewSelected) return
        const count = previewSelectAllMatching
            ? (Number.isFinite(previewTotalCount) ? previewTotalCount : 0)
            : previewSelectedIds.size
        exploreSearchPreviewSelected.textContent = `Selecionados: ${count}`
    }

    function setPreviewSelection(ids){
        previewSelectedIds.clear()
        ids.forEach((id)=>{
            const parsed = Number(id)
            if(Number.isFinite(parsed) && parsed > 0){
                previewSelectedIds.add(parsed)
            }
        })
        previewSelectAllMatching = false
        updatePreviewSelectionSummary()
        renderSearchPreview(previewRowsCache, previewTotalCount)
    }

    function setPreviewSelectAllMatching(enabled){
        previewSelectAllMatching = !!enabled
        if(previewSelectAllMatching){
            previewSelectedIds.clear()
        }
        updatePreviewSelectionSummary()
        renderSearchPreview(previewRowsCache, previewTotalCount)
    }

    function applyPreviewSelectionToGrid(){
        if(previewSelectAllMatching){
            preserveSelectionOnNextReset = false
            selectionMode = 'manual'
            selectedIds.clear()
            excludedIds.clear()
            allMatchingTotal = null
            if(Number.isFinite(previewTotalCount)){
                filteredTotal = Number(previewTotalCount)
                syncFooterCounters()
            }
            updateSelectionBar()
            return
        }
        if(previewSelectedIds.size > 0){
            preserveSelectionOnNextReset = true
            selectionMode = 'manual'
            excludedIds.clear()
            selectedIds.clear()
            previewSelectedIds.forEach((id)=>selectedIds.add(id))
            allMatchingTotal = null
            updateSelectionBar()
        }
    }

    function buildPreviewParams(){
        const params = new URLSearchParams({
            q: exploreSearchModalInput?.value || '',
            min_score: getMinScoreValue(),
            per_page: 20
        })
        const sourceId = getCurrentSourceId()
        const segmentId = String(exploreSearchSegmentSelect?.value || '').trim()
        const nicheId = String(exploreSearchNicheSelect?.value || '').trim()
        const originId = String(exploreSearchOriginSelect?.value || '').trim()
        if(!sourceId){
            if(segmentId) params.set('segment_id', segmentId)
            if(nicheId) params.set('niche_id', nicheId)
            if(originId) params.set('origin_id', originId)
        }
        const cities = String(exploreSearchCitiesInput?.value || '')
            .split(',')
            .map(v=>v.trim())
            .filter(Boolean)
        const states = String(exploreSearchStatesInput?.value || '')
            .split(',')
            .map(v=>v.trim().toUpperCase())
            .filter(Boolean)
        if(!sourceId){
            cities.forEach(city => params.append('cities[]', city))
            states.forEach(uf => params.append('states[]', uf))
        }
        return params
    }

    function renderSearchPreview(rows, total = 0){
        if(!exploreSearchPreviewBody || !exploreSearchPreviewCount) return
        previewRowsCache = Array.isArray(rows) ? rows : []
        previewTotalCount = Number.isFinite(total) ? Number(total) : previewRowsCache.length
        updatePreviewSelectionSummary()
        const searchTerm = String(exploreSearchModalInput?.value || '').trim()
        const resolvePreviewMeta = (row, term)=>{
            const baseCandidates = [
                { key: 'name', label: 'Nome', value: row?.name },
                { key: 'email', label: 'Email', value: row?.email },
                { key: 'cpf', label: 'CPF', value: row?.cpf },
                { key: 'phone', label: 'Telefone', value: row?.phone },
                { key: 'city', label: 'Cidade', value: row?.city },
                { key: 'uf', label: 'UF', value: row?.uf },
            ]

            const extras = (row && typeof row.extras_json === 'object' && row.extras_json !== null)
                ? row.extras_json
                : {}
            const extraCandidates = Object.entries(extras).map(([key, value])=>({
                key,
                label: normalizeColumnLabel(key),
                value
            }))

            const candidates = [...baseCandidates, ...extraCandidates]
            const normalizedTerm = String(term || '').toLowerCase()

            if(normalizedTerm){
                const matched = candidates.find((candidate)=>{
                    const value = candidate?.value
                    if(value === null || value === undefined) return false
                    return String(value).toLowerCase().includes(normalizedTerm)
                })
                if(matched){
                    return {
                        label: matched.label,
                        value: matched.value
                    }
                }
            }

            const fallback = candidates.find((candidate)=>{
                const value = candidate?.value
                return value !== null && value !== undefined && String(value).trim() !== ''
            })

            if(fallback){
                return {
                    label: fallback.label,
                    value: fallback.value
                }
            }

            return {
                label: 'Registro',
                value: 'Sem detalhes'
            }
        }
        if(typeof total === 'number'){
            exploreSearchPreviewCount.textContent = `${total} registros`
        }else{
            const n = Array.isArray(rows) ? rows.length : 0
            exploreSearchPreviewCount.textContent = n ? `${n}+ registros (amostra)` : `0 registros`
	        }
	        if(!rows.length){
	            if(exploreSearchPreviewEmpty){
	                exploreSearchPreviewEmpty.textContent = 'Nenhum registro encontrado com esses filtros.'
	                exploreSearchPreviewEmpty.classList.remove('d-none')
	            }
            exploreSearchPreviewBody.innerHTML = exploreSearchPreviewEmpty?.outerHTML || ''
            return
        }
        if(exploreSearchPreviewEmpty){
            exploreSearchPreviewEmpty.classList.add('d-none')
        }
        exploreSearchPreviewBody.innerHTML = rows.map(r=>{
            const city = formatCity(r.city)
            const uf = formatUF(r.uf)
            const sub = [city, uf].filter(Boolean).join(' • ')
            const meta = resolvePreviewMeta(r, searchTerm)
            const metaText = `${meta.label}: ${meta.value ?? ''}`
            const rowId = Number(r?.id)
            const isSelectable = Number.isFinite(rowId) && rowId > 0
            const checked = previewSelectAllMatching || (isSelectable && previewSelectedIds.has(rowId))
            return `
                <div class="explore-search-preview-row ${isSelectable ? 'is-selectable' : ''} ${checked ? 'is-selected' : ''}" ${isSelectable ? `data-preview-row-id="${rowId}" role="button" tabindex="0"` : ''}>
                    <div class="explore-search-preview-main-wrap">
                        ${isSelectable ? `
                            <label class="explore-search-preview-check">
                                <input type="checkbox" data-preview-select-id="${rowId}" ${checked ? 'checked' : ''}>
                            </label>
                        ` : ''}
                        <div class="explore-search-preview-main">
                            <div class="explore-search-preview-name">${escapeHtml(formatName(r.name || ''))}</div>
                            <div class="explore-search-preview-sub">
                                <span>${escapeHtml(r.email || '')}</span>
                                ${sub ? `<span>${escapeHtml(sub)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="explore-search-preview-meta">${escapeHtml(metaText)}</div>
                </div>
            `
        }).join('')
    }

    async function fetchSearchPreview(params){
        if(!exploreSearchPreviewWrap || !window.exploreConfig?.dbUrl) return
        const term = String(params.get('q') || '').trim()
        const hasFilters = !!(
            term ||
            params.has('segment_id') ||
            params.has('niche_id') ||
            params.has('origin_id') ||
            params.has('cities[]') ||
            params.has('states[]') ||
            String(params.get('min_score') || '').trim()
        )
        if(!hasFilters){
            if(exploreSearchPreviewEmpty){
                exploreSearchPreviewEmpty.textContent = 'Digite para buscar na prévia.'
            }
            renderSearchPreview([], 0)
            if(exploreSearchPreviewLoading){
                exploreSearchPreviewLoading.classList.add('d-none')
            }
            return
        }
        if(sampleMode){
            const termLower = term.toLowerCase()
            const sampleRows = [
                {
                    id: -1,
                    name: 'Marina Costa',
                    email: 'marina@exemplo.com',
                    score: 84,
                    city: 'São Paulo',
                    uf: 'SP',
                    cpf: '12345678901',
                    phone: '11987654321',
                    data_nascimento: '12/05/1988',
                    sex: 'F',
                    empresa: 'Construtora Aurora',
                    cargo: 'Coordenadora de Obras',
                    status: 'Ativo',
                    segmento: 'Construção Civil',
                    origem: 'Indicação',
                    ultima_compra: '12/2025',
                    bairro: 'Vila Olímpia',
                    rua: 'Rua das Acácias, 230',
                    endereco: 'Vila Olímpia, São Paulo - SP',
                    telefone_residencial: '(11) 4000-2100'
                },
                {
                    id: -2,
                    name: 'Rafael Lima',
                    email: 'rafael@exemplo.com',
                    score: 62,
                    city: 'Rio de Janeiro',
                    uf: 'RJ',
                    cpf: '98765432100',
                    phone: '21999887766',
                    data_nascimento: '03/11/1982',
                    sex: 'M',
                    empresa: 'Aliança Engenharia',
                    cargo: 'Diretor Comercial',
                    status: 'Prospect',
                    segmento: 'Infraestrutura',
                    origem: 'Inbound',
                    ultima_compra: '—',
                    bairro: 'Barra da Tijuca',
                    rua: 'Av. das Américas, 900',
                    endereco: 'Barra da Tijuca, Rio de Janeiro - RJ',
                    telefone_residencial: '(21) 3002-2400'
                },
                {
                    id: -3,
                    name: 'Carolina Alves',
                    email: 'carol@exemplo.com',
                    score: 45,
                    city: 'Belo Horizonte',
                    uf: 'MG',
                    cpf: '45678912300',
                    phone: '31988776655',
                    data_nascimento: '22/01/1991',
                    sex: 'F',
                    empresa: 'Grupo Horizonte',
                    cargo: 'Analista de Suprimentos',
                    status: 'Em avaliação',
                    segmento: 'Industrial',
                    origem: 'Evento',
                    ultima_compra: '09/2025',
                    bairro: 'Savassi',
                    rua: 'Rua Pernambuco, 410',
                    endereco: 'Savassi, Belo Horizonte - MG',
                    telefone_residencial: '(31) 3200-7788'
                }
            ]
            const filtered = term
                ? sampleRows.filter(r=>{
                    const hay = [
                        r.name, r.email, r.city, r.uf, r.cpf, r.phone,
                        r.data_nascimento, r.sex, r.score, r.empresa, r.cargo,
                        r.status, r.segmento, r.origem, r.ultima_compra,
                        r.bairro, r.rua, r.endereco, r.telefone_residencial
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase()
                    return hay.includes(termLower)
                })
                : sampleRows
            renderSearchPreview(filtered, filtered.length)
            if(exploreSearchPreviewLoading){
                exploreSearchPreviewLoading.classList.add('d-none')
            }
            return
        }
        if(searchPreviewAbort){
            searchPreviewAbort.abort()
        }
        const controller = new AbortController()
        searchPreviewAbort = controller
        if(exploreSearchPreviewLoading){
            exploreSearchPreviewLoading.classList.remove('d-none')
        }
        try{
            const res = await fetch(`${window.exploreConfig.dbUrl}?${params.toString()}`, {
                signal: controller.signal
            })
            const data = await res.json()
            if(!res.ok){
                renderSearchPreview([], 0)
                return
            }
            const total = typeof data?.total === 'number' ? data.total : null
            renderSearchPreview(Array.isArray(data?.rows) ? data.rows : [], total)
        }catch(e){
            if(e?.name === 'AbortError') return
            renderSearchPreview([], 0)
        }finally{
            if(exploreSearchPreviewLoading){
                exploreSearchPreviewLoading.classList.add('d-none')
            }
        }
    }

    function resetColumnsToDefault(){
        visibleColumns = new Set(DEFAULT_VISIBLE)
        columnOrder = [
            ...baseColumns.map(c=>c.key)
        ]
        persistColumns()
        persistOrder()
        resetColumnWidths()
        clearLayoutSelectionUI()
        rebuildColumnsModal()
        applyColumnVisibility()
        applyColumnOrder()
    }

    const searchFiltersController = createSearchFiltersController({
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
        onBeforeApplySearch: ()=>{
            applyPreviewSelectionToGrid()
        },
        onModalShown: ()=>{
            resetPreviewSelectionState()
        },
        onModalHidden: ()=>{
            resetPreviewSelectionState()
        },
        onReload: ()=>load(true),
        onResetColumns: resetColumnsToDefault,
        onFiltersUpdated: ()=>emptyStateController?.updateEmptyState(),
        getPreviewParams: buildPreviewParams,
        onPreviewSearch: fetchSearchPreview,
        previewElements: {
            wrap: exploreSearchPreviewWrap,
            body: exploreSearchPreviewBody,
            count: exploreSearchPreviewCount,
            empty: exploreSearchPreviewEmpty,
            loading: exploreSearchPreviewLoading
        }
    })
    searchFiltersController.bind()
    exploreSearchPreviewSelectPageBtn?.addEventListener('click', ()=>{
        const ids = previewRowsCache
            .map((row)=>Number(row?.id))
            .filter((id)=>Number.isFinite(id) && id > 0)
        setPreviewSelection(ids)
    })
    exploreSearchPreviewSelectAllBtn?.addEventListener('click', ()=>{
        setPreviewSelectAllMatching(true)
    })
    exploreSearchPreviewClearSelectionBtn?.addEventListener('click', ()=>{
        setPreviewSelection([])
    })
    exploreSearchPreviewBody?.addEventListener('click', (event)=>{
        const target = event.target instanceof Element ? event.target : null
        if(!target) return
        const row = target.closest('[data-preview-row-id]')
        if(!row) return
        if(target.closest('input[data-preview-select-id]')) return
        const leadId = Number(row.getAttribute('data-preview-row-id'))
        if(!Number.isFinite(leadId) || leadId <= 0) return
        if(previewSelectAllMatching){
            return
        }
        if(previewSelectedIds.has(leadId)){
            previewSelectedIds.delete(leadId)
        }else{
            previewSelectedIds.add(leadId)
        }
        renderSearchPreview(previewRowsCache, previewTotalCount)
    })
    exploreSearchPreviewBody?.addEventListener('change', (event)=>{
        const trigger = event.target instanceof Element
            ? event.target.closest('input[data-preview-select-id]')
            : null
        if(!trigger) return
        const leadId = Number(trigger.getAttribute('data-preview-select-id'))
        if(!Number.isFinite(leadId) || leadId <= 0) return
        if(previewSelectAllMatching){
            renderSearchPreview(previewRowsCache, previewTotalCount)
            return
        }
        if(trigger.checked){
            previewSelectedIds.add(leadId)
        }else{
            previewSelectedIds.delete(leadId)
        }
        renderSearchPreview(previewRowsCache, previewTotalCount)
    })
    exploreSearchPreviewBody?.addEventListener('keydown', (event)=>{
        if(event.key !== 'Enter' && event.key !== ' ') return
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-preview-row-id]')
            : null
        if(!trigger) return
        event.preventDefault()
        trigger.click()
    })
    emptyStateController?.bindClear(()=> searchFiltersController.clearFilters(false))
    emptyStateSampleBtn?.addEventListener('click', ()=>{
        sampleMode = true
        const sampleRows = [
            {
                id: -1,
                name: 'Marina Costa',
                email: 'marina@exemplo.com',
                cpf: '12345678901',
                phone: '11987654321',
                sex: 'F',
                score: 84,
                city: 'São Paulo',
                uf: 'SP',
                extras_json: {
                    empresa: 'Construtora Aurora',
                    cargo: 'Coordenadora de Obras',
                    status: 'Ativo',
                    segmento: 'Construção Civil',
                    origem: 'Indicação',
                    ultima_compra: '12/2025'
                    ,data_nascimento: '12/05/1988'
                    ,bairro: 'Vila Olímpia'
                    ,rua: 'Rua das Acácias, 230'
                    ,endereco: 'Vila Olímpia, São Paulo - SP'
                    ,telefone_residencial: '(11) 4000-2100'
                }
            },
            {
                id: -2,
                name: 'Rafael Lima',
                email: 'rafael@exemplo.com',
                cpf: '98765432100',
                phone: '21999887766',
                sex: 'M',
                score: 62,
                city: 'Rio de Janeiro',
                uf: 'RJ',
                extras_json: {
                    empresa: 'Aliança Engenharia',
                    cargo: 'Diretor Comercial',
                    status: 'Prospect',
                    segmento: 'Infraestrutura',
                    origem: 'Inbound',
                    ultima_compra: '—'
                    ,data_nascimento: '03/11/1982'
                    ,bairro: 'Barra da Tijuca'
                    ,rua: 'Av. das Américas, 900'
                    ,endereco: 'Barra da Tijuca, Rio de Janeiro - RJ'
                    ,telefone_residencial: '(21) 3002-2400'
                }
            },
            {
                id: -3,
                name: 'Carolina Alves',
                email: 'carol@exemplo.com',
                cpf: '45678912300',
                phone: '31988776655',
                sex: 'F',
                score: 45,
                city: 'Belo Horizonte',
                uf: 'MG',
                extras_json: {
                    empresa: 'Grupo Horizonte',
                    cargo: 'Analista de Suprimentos',
                    status: 'Em avaliação',
                    segmento: 'Industrial',
                    origem: 'Evento',
                    ultima_compra: '09/2025'
                    ,data_nascimento: '22/01/1991'
                    ,bairro: 'Savassi'
                    ,rua: 'Rua Pernambuco, 410'
                    ,endereco: 'Savassi, Belo Horizonte - MG'
                    ,telefone_residencial: '(31) 3200-7788'
                }
            }
        ]
        try{
        const sampleVisibleExtras = ['data_nascimento']
        const sampleHiddenExtras = [
            'empresa','cargo','status','segmento','origem','ultima_compra',
            'bairro','rua','endereco','telefone_residencial'
        ]
        const sampleExtras = [...sampleVisibleExtras, ...sampleHiddenExtras]
        columnLabelMap.set('data_nascimento', 'Data de nascimento')
        if(headerRow){
            const baseKeys = new Set(baseColumns.map(c=>c.key))
            Array.from(headerRow.children).forEach(cell=>{
                const key = getColKey(cell)
                if(key && key !== 'select' && !baseKeys.has(key)){
                    cell.remove()
                }
            })
        }
        extrasColumns.clear()
        sampleExtras.forEach((k)=>{
            if(!extrasColumns.has(k)){
                extrasColumns.add(k)
            }
        })
        sampleExtras.forEach((k)=> addExtrasHeader(k))
        rebuildColumnsModal()
        visibleColumns = new Set([
            'nome','cpf','email','phone','data_nascimento','sex'
        ])
        persistColumns()
        columnOrder = [
            'nome','cpf','email','phone','data_nascimento','sex'
        ]
        persistOrder()
        body.innerHTML = ''
        next = null
        total = 0
        filteredTotal = null
        clearSelections()
        }catch(err){
            console.warn('Sample mode setup failed, rendering fallback sample rows.', err)
            body.innerHTML = ''
            total = 0
            filteredTotal = null
            sourceTotalRecords = null
            sourceTotalSourceId = null
            syncFooterCounters()
            clearSelections()
        }
        loading = false
        setLoading(false)
        if(emptyState) emptyState.classList.add('d-none')
        render(sampleRows)
        applyColumnVisibility()
        applyColumnOrder()
        filteredTotal = sampleRows.length
        sourceTotalRecords = null
        sourceTotalSourceId = null
        syncFooterCounters()
    })

    exportBtn?.addEventListener('click', ()=>{
        const getExportCols = ()=>{
            if(Array.isArray(columnOrder) && columnOrder.length){
                const ordered = columnOrder.filter(k=>visibleColumns.has(k))
                if(ordered.length) return ordered
            }
            return Array.from(visibleColumns)
        }
        showToast('Exportando CSV...')
        const urlParams = new URLSearchParams(window.location.search || '')
        const sourceId = urlParams.get('source_id') || urlParams.get('lead_source_id') || ''
        const exportCols = getExportCols()
        const params = new URLSearchParams({
            q: search?.value || '',
            min_score: getMinScoreValue(),
            export: 'csv',
            cols: exportCols.join(',')
        })
        if(searchFilters.segment_id) params.set('segment_id', searchFilters.segment_id)
        if(searchFilters.niche_id) params.set('niche_id', searchFilters.niche_id)
        if(searchFilters.origin_id) params.set('origin_id', searchFilters.origin_id)
        ;(searchFilters.cities || []).forEach(city => params.append('cities[]', city))
        ;(searchFilters.states || []).forEach(uf => params.append('states[]', uf))
        if(sourceId) params.set('lead_source_id', sourceId)
        window.location.href = `${window.exploreConfig.dbUrl}?${params.toString()}`
        setTimeout(()=>showToast('Arquivo gerado'), 1400)
    })

    async function loadSearchSemanticFilters(){
        try{
            const url = window.exploreConfig?.semanticOptionsUrl || '/vault/explore/semantic-options'
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            if(!res.ok) return
            const data = await res.json()
            const fill = (selectEl, items, emptyLabel='Todos')=>{
                if(!selectEl) return
                const safeItems = Array.isArray(items) ? items : []
                selectEl.innerHTML = [
                    `<option value="">${emptyLabel}</option>`,
                    ...safeItems.map(item=>`<option value="${item.id}">${escapeHtml(item.name)}</option>`)
                ].join('')
            }
            fill(exploreSearchSegmentSelect, data.segments, 'Todos')
            fill(exploreSearchNicheSelect, data.niches, 'Todos')
            fill(exploreSearchOriginSelect, data.origins, 'Todas')
        }catch(e){
            console.error('Search semantic filters error:', e)
        }
    }

    importController.bind()

    const stopImportAttention = ()=>{
        if(importAttentionTimer){
            clearTimeout(importAttentionTimer)
            importAttentionTimer = null
        }
        importAttentionActive = false
        importAttentionRuns = 0
        openExploreImportBtn?.classList.remove('is-attention-blink')
    }

    const runImportAttentionLoop = ()=>{
        if(!openExploreImportBtn) return
        const hasSource = !!String(sourceSelect?.value || sourceSelectMobile?.value || '').trim()
        if(hasSource){
            stopImportAttention()
            return
        }
        if(importAttentionRuns >= IMPORT_ATTENTION_MAX_RUNS){
            importAttentionActive = false
            return
        }

        importAttentionActive = true
        importAttentionRuns += 1
        openExploreImportBtn.classList.remove('is-attention-blink')
        void openExploreImportBtn.offsetWidth
        openExploreImportBtn.classList.add('is-attention-blink')

        if(importAttentionRuns >= IMPORT_ATTENTION_MAX_RUNS){
            importAttentionActive = false
            return
        }

        importAttentionTimer = setTimeout(()=>{
            if(importAttentionActive){
                runImportAttentionLoop()
            }
        }, IMPORT_ATTENTION_INTERVAL_MS)
    }

    const syncImportAttention = ()=>{
        if(!openExploreImportBtn) return
        const hasSource = !!String(sourceSelect?.value || sourceSelectMobile?.value || '').trim()
        if(hasSource){
            stopImportAttention()
            return
        }
        if(importAttentionActive && importAttentionTimer){
            return
        }
        if(importAttentionRuns >= IMPORT_ATTENTION_MAX_RUNS){
            return
        }
        runImportAttentionLoop()
    }

    async function loadSources(){
        if(!sourceSelect) return
        try{
            const url = window.exploreConfig?.sourcesListUrl || '/vault/explore/sources'
            const r = await fetch(url)
            const d = await r.json()
            const options = [
                `<option value="">Todos os arquivos</option>`,
                ...d.sources.map(s=>`<option value="${s.id}">${s.original_name}</option>`)
            ]
            sourceSelect.innerHTML = options.join('')
            if(sourceSelectMobile){
                sourceSelectMobile.innerHTML = options.join('')
            }
            if(Array.isArray(d.sources) && d.sources.length){
                setHasSources(true)
                setForceImportGate(false)
                importController.setExploreLocked(false)
            }else{
                setHasSources(false)
                importController.setExploreLocked(false)
            }

            if(d.current){
                applyStorageForSource(d.current)
                sourceSelect.value = String(d.current)
                if(sourceSelectMobile) sourceSelectMobile.value = String(d.current)
                lastSelectedSourceValue = String(d.current)
                setDataQualityEnabled(true)
                if(exploreSearchAdvancedFields) exploreSearchAdvancedFields.classList.add('d-none')
                setSemanticEnabled(true)
                loadSemanticState()
                overridesController.loadOverridesSummary()
            } else {
                applyStorageForSource(null)
                lastSelectedSourceValue = ''
                setDataQualityEnabled(false)
                if(exploreSearchAdvancedFields) exploreSearchAdvancedFields.classList.remove('d-none')
                setSemanticEnabled(false)
                if(semanticPills) semanticPills.innerHTML = ''
                if(semanticTopAnchor) semanticTopAnchor.textContent = 'não definida'
                if(semanticTopHoverPills){
                    semanticTopHoverPills.innerHTML = `<span class="semantic-pill semantic-pill--empty">não definida</span>`
                }
                if(semanticTopSummary){
                    semanticTopSummary.classList.add('d-none')
                }
                overridesController.resetOverridesSummary()
            }
            syncImportAttention()
        }catch(e){
            console.error('Sources list error:', e)
        }
    }

    const handleSourceChange = (value, previousValue = '')=>{
        const id = value
        const navigateToTarget = ()=>{
            if(!id){
                setDataQualityEnabled(false)
                setSemanticEnabled(false)
                if(exploreSearchAdvancedFields) exploreSearchAdvancedFields.classList.remove('d-none')
                const url = window.exploreConfig?.sourceClearUrl || '/vault/explore/source/clear'
                window.location.href = url
                return
            }
            if(exploreSearchAdvancedFields) exploreSearchAdvancedFields.classList.add('d-none')
            setDataQualityEnabled(true)
            setSemanticEnabled(true)
            const template = window.exploreConfig?.sourceSelectUrlTemplate || '/vault/explore/source/__ID__'
            window.location.href = template.replace('__ID__', String(id))
        }

        const pendingTotal = Number(overridesController.getPendingTotal?.() || 0)
        if(pendingTotal < 1 || !exploreLeaveConfirmModalEl || !window.bootstrap?.Modal){
            navigateToTarget()
            return
        }

        if(exploreLeaveConfirmCount){
            exploreLeaveConfirmCount.textContent = `${pendingTotal} alteração(ões) pendente(s).`
        }

        const modal = window.bootstrap.Modal.getOrCreateInstance(exploreLeaveConfirmModalEl)
        let resolved = false

        const cleanup = ()=>{
            exploreLeavePublishBtn?.removeEventListener('click', onPublish)
            exploreLeaveKeepPendingBtn?.removeEventListener('click', onLeaveWithoutSave)
            exploreLeaveCancelBtn?.removeEventListener('click', onCancel)
            exploreLeaveConfirmModalEl?.removeEventListener('hidden.bs.modal', onHidden)
        }

        const onPublish = async ()=>{
            if(resolved) return
            resolved = true
            if(exploreLeavePublishBtn) exploreLeavePublishBtn.disabled = true
            const result = await overridesController.publishOverrides({ showMessages: true, navigateToCurrent: false })
            if(exploreLeavePublishBtn) exploreLeavePublishBtn.disabled = false
            if(result?.ok){
                cleanup()
                modal.hide()
                navigateToTarget()
                return
            }
            resolved = false
        }

        const onLeaveWithoutSave = ()=>{
            if(resolved) return
            resolved = true
            cleanup()
            modal.hide()
            navigateToTarget()
        }

        const onCancel = ()=>{
            if(resolved) return
            resolved = true
            cleanup()
            modal.hide()
            if(sourceSelect) sourceSelect.value = previousValue
            if(sourceSelectMobile) sourceSelectMobile.value = previousValue
        }

        const onHidden = ()=>{
            cleanup()
            if(!resolved){
                if(sourceSelect) sourceSelect.value = previousValue
                if(sourceSelectMobile) sourceSelectMobile.value = previousValue
            }
        }

        exploreLeavePublishBtn?.addEventListener('click', onPublish)
        exploreLeaveKeepPendingBtn?.addEventListener('click', onLeaveWithoutSave)
        exploreLeaveCancelBtn?.addEventListener('click', onCancel)
        exploreLeaveConfirmModalEl?.addEventListener('hidden.bs.modal', onHidden)
        modal.show()
    }

    const openSourcesModal = ()=>{
        if(!exploreImportModalEl || !window.bootstrap?.Modal) return
        importController.setImportModalMode('panel')
        window.bootstrap.Modal.getOrCreateInstance(exploreImportModalEl).show()
    }

    sourceSelect?.addEventListener('change', ()=>{
        const previous = String(lastSelectedSourceValue || '')
        if(sourceSelectMobile) sourceSelectMobile.value = sourceSelect.value
        syncImportAttention()
        handleSourceChange(sourceSelect.value, previous)
    })

    sourceSelect?.addEventListener('dblclick', ()=>{
        const current = String(sourceSelect.value || '').trim()
        if(current === ''){
            openSourcesModal()
        }
    })

    sourceSelectMobile?.addEventListener('change', ()=>{
        const previous = String(lastSelectedSourceValue || '')
        if(sourceSelect) sourceSelect.value = sourceSelectMobile.value
        syncImportAttention()
        handleSourceChange(sourceSelectMobile.value, previous)
    })

    window.addEventListener('beforeunload', (event)=>{
        if(window.__exploreSkipBeforeUnload === '1'){
            window.__exploreSkipBeforeUnload = '0'
            return
        }
        const pendingTotal = Number(overridesController.getPendingTotal?.() || 0)
        if(pendingTotal < 1) return
        event.preventDefault()
        event.returnValue = ''
    })

    sourceSelectMobile?.addEventListener('dblclick', ()=>{
        const current = String(sourceSelectMobile.value || '').trim()
        if(current === ''){
            openSourcesModal()
        }
    })

    dataQualityBtn?.addEventListener('click', openDataQualityModal)
    dataQualityBtnMobile?.addEventListener('click', openDataQualityModal)
    openColumnsAdminModalBtn?.addEventListener('click', openColumnsAdminModal)
    exploreColumnsEditBtn?.addEventListener('click', openColumnsAdminModal)

	    body?.addEventListener('change', (e)=>{
	        const target = e.target
	        if(!(target instanceof HTMLInputElement)) return
	        if(target.matches('[data-select-id]')){
	            const id = Number(target.getAttribute('data-select-id'))
	            if(selectionMode === 'manual'){
	                if(target.checked){
	                    selectedIds.add(id)
	                }else{
	                    selectedIds.delete(id)
	                }
	            }else{
	                // all_matching: default selected, unchecking excludes.
	                if(target.checked){
	                    excludedIds.delete(id)
	                }else{
	                    excludedIds.add(id)
	                }
	            }
	            updateSelectionBar()
	        }
	    })

    body?.addEventListener('dblclick', (e)=>{
        const cell = e.target instanceof Element ? e.target.closest('td[data-editable="1"]') : null
        if(!cell) return
        openInlineCellEditor(cell)
    })

    body?.addEventListener('click', (e)=>{
        const target = e.target instanceof Element ? e.target : null
        if(!target) return
        if(target.closest('input,button,a,select,textarea,label')) return
        const cell = target.closest('td[data-editable="1"]')
        if(!cell) return
        openInlineCellEditor(cell)
    })

	    checkAll?.addEventListener('change', ()=>{
	        const checked = checkAll.checked
	        body.querySelectorAll('[data-select-id]').forEach(chk=>{
	            chk.checked = checked
	            const id = Number(chk.getAttribute('data-select-id'))
	            if(selectionMode === 'manual'){
	                if(checked){
	                    selectedIds.add(id)
	                }else{
	                    selectedIds.delete(id)
	                }
	            }else{
	                if(checked){
	                    excludedIds.delete(id)
	                }else{
	                    excludedIds.add(id)
	                }
	            }
	        })
	        updateSelectionBar()
	    })

    selectionClearBtn?.addEventListener('click', clearSelections)

    selectionSelectAllBtn?.addEventListener('click', selectAllMatching)

    async function loadSemanticOptions(){
        if(!semanticSegmentInput) return
        try{
            bindAutocomplete()
            rebuildAnchorOptions()
        }catch(e){
            console.error('Semantic options error:', e)
        }
    }

    function renderSelectedPills(type){
        const map = semanticSelected[type]
        const container = type === 'segment' ? semanticSegmentPill
            : type === 'niche' ? semanticNichePill
            : type === 'city' ? semanticCityPills
            : type === 'state' ? semanticStatePills
            : semanticCountryPills

        if(!container || !map) return
        const html = Array.from(map.entries()).map(([id,label])=>`
            <span class="semantic-select-pill" data-type="${type}" data-id="${id}">
                ${label}
                <button type="button" aria-label="Remover">×</button>
            </span>
        `).join('')
        container.innerHTML = html

        container.querySelectorAll('button').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                const pill = btn.closest('.semantic-select-pill')
                const pid = pill?.getAttribute('data-id')
                if(pid && map.has(Number(pid))){
                    map.delete(Number(pid))
                    renderSelectedPills(type)
                    rebuildAnchorOptions()
                }
            })
        })
    }

    function renderSinglePill(type){
        const container = semanticOriginPill
        if(!container) return
        const data = semanticSelectedSingle[type]
        if(!data){
            container.innerHTML = ''
            return
        }
        container.innerHTML = `
            <span class="semantic-select-pill" data-type="${type}" data-id="${data.id}">
                ${data.label}
                <button type="button" aria-label="Remover">×</button>
            </span>
        `
        container.querySelector('button')?.addEventListener('click', ()=>{
            semanticSelectedSingle[type] = null
            renderSinglePill(type)
            rebuildAnchorOptions()
        })
    }

    function getSelectedLocationLabels(){
        const all = []
        Object.values(semanticSelectedSingle).forEach(item=>{
            if(item?.label) all.push(item.label)
        })
        Object.values(semanticSelected).forEach(map=>{
            map.forEach(label=>all.push(label))
        })
        return all
    }

    function rebuildAnchorOptions(){
        if(!semanticAnchor) return
        const selectedLabels = getSelectedLocationLabels()
        const unique = Array.from(new Set(selectedLabels))
        const options = ['Brasil', ...unique]
        const current = semanticAnchor.value || 'Brasil'

        semanticAnchor.innerHTML = options
            .map(v=>`<option value="${v}">${v}</option>`)
            .join('')

        if(options.includes(current)){
            semanticAnchor.value = current
        }else{
            semanticAnchor.value = 'Brasil'
        }
    }

    function bindAutocomplete(){
        bindAutocompleteInput('segment', semanticSegmentInput, semanticSegmentResults)
        bindAutocompleteInput('niche', semanticNicheInput, semanticNicheResults)
        bindAutocompleteInput('origin', semanticOriginInput, semanticOriginResults)
        bindAutocompleteInput('city', semanticCityInput, semanticCityResults)
        bindAutocompleteInput('state', semanticStateInput, semanticStateResults)
        bindAutocompleteInput('country', semanticCountryInput, semanticCountryResults)
    }

    function bindAutocompleteInput(type, input, results){
        if(!input || !results) return
        let t = null
        input.addEventListener('input', ()=>{
            const q = input.value.trim()
            clearTimeout(t)
            if(q.length < 2){
                results.classList.add('d-none')
                results.innerHTML = ''
                return
            }
            t = setTimeout(()=>fetchAutocomplete(type, q, results), 250)
        })
    }

    async function fetchAutocomplete(type, q, results){
        try{
            const params = new URLSearchParams({ type, q })
            if(type === 'city'){
                const stateIds = Array.from(semanticSelected.state.keys())
                if(stateIds.length){
                    stateIds.forEach(id=>params.append('state_ids[]', id))
                }
            }
            const base = window.exploreConfig?.semanticAutocompleteUrl || '/vault/explore/semantic-autocomplete'
            const r = await fetch(`${base}?${params.toString()}`)
            const d = await r.json()
            const items = Array.isArray(d.items) ? d.items : []
            results.innerHTML = items.map(i=>`<button type="button" data-id="${i.id}" data-name="${i.name}">${i.name}</button>`).join('')
            results.classList.toggle('d-none', items.length === 0)
            results.querySelectorAll('button').forEach(btn=>{
                btn.addEventListener('click', ()=>{
                    const id = Number(btn.getAttribute('data-id'))
                    const name = btn.getAttribute('data-name') || ''
                    if(type === 'origin'){
                        semanticSelectedSingle[type] = { id, label: name }
                        renderSinglePill(type)
                    }else{
                        if(!semanticSelected[type].has(id)){
                            semanticSelected[type].set(id, name)
                            renderSelectedPills(type)
                        }
                    }
                    rebuildAnchorOptions()
                    results.classList.add('d-none')
                    results.innerHTML = ''
                    if(type === 'segment' && semanticSegmentInput) semanticSegmentInput.value = ''
                    if(type === 'niche' && semanticNicheInput) semanticNicheInput.value = ''
                    if(type === 'origin' && semanticOriginInput) semanticOriginInput.value = ''
                    if(type === 'city' && semanticCityInput) semanticCityInput.value = ''
                    if(type === 'state' && semanticStateInput) semanticStateInput.value = ''
                    if(type === 'country' && semanticCountryInput) semanticCountryInput.value = ''
                })
            })
        }catch(e){
            console.error('Semantic autocomplete error:', e)
        }
    }

    function bindStateFilterHandlers(){
        if(!semanticStates) return
        semanticStates.querySelectorAll('input[type="checkbox"]').forEach(chk=>{
            chk.addEventListener('change', applyCityFilter)
        })
    }

    function applyCityFilter(){
        if(!semanticCities || !semanticCitiesHaveState) return
        const selectedStates = Array.from(semanticStates?.querySelectorAll('input[type="checkbox"]:checked') || [])
            .map(chk=>chk.value)

        const hasFilter = selectedStates.length > 0
        const selectedSet = new Set(selectedStates)

        semanticCities.querySelectorAll('label').forEach(label=>{
            const checkbox = label.querySelector('input[type="checkbox"]')
            const stateId = checkbox?.getAttribute('data-state-id')

            if(!hasFilter){
                label.classList.remove('d-none')
                return
            }

            if(!stateId){
                label.classList.add('d-none')
                return
            }

            if(selectedSet.has(stateId)){
                label.classList.remove('d-none')
            }else{
                label.classList.add('d-none')
            }
        })
    }

    function applySemanticState(data){
        if(semanticAnchor){
            semanticAnchor.value = data.anchor || 'Brasil'
        }

        semanticSelectedSingle.origin = data.origin?.[0] ? { id: data.origin[0].id, label: data.origin[0].name } : null

        semanticSelected.segment.clear()
        semanticSelected.niche.clear()
        semanticSelected.city.clear()
        semanticSelected.state.clear()
        semanticSelected.country.clear()

        ;(data.segment || []).forEach(s=>{
            if(!s) return
            const label = (s.label || s.name || '').trim()
            if(!label) return
            semanticSelected.segment.set(Number(s.id), label)
        })

        ;(data.niche || []).forEach(n=>{
            if(!n) return
            const label = (n.label || n.name || '').trim()
            if(!label) return
            semanticSelected.niche.set(Number(n.id), label)
        })

        ;(data.locations || []).forEach(l=>{
            if(!l || !l.type) return
            const label = (l.label || '').trim()
            if(!label) return
            if(semanticSelected[l.type]){
                semanticSelected[l.type].set(Number(l.ref_id), label)
            }
        })

        renderSelectedPills('segment')
        renderSelectedPills('niche')
        renderSinglePill('origin')
        renderSelectedPills('city')
        renderSelectedPills('state')
        renderSelectedPills('country')

        renderSemanticPills(data)
        rebuildAnchorOptions()
    }

    async function loadSemanticState(){
        if(!semanticSegmentInput) return
        try{
            const params = new URLSearchParams()
            if(sourceSelect?.value){
                params.set('source_id', sourceSelect.value)
            }
            const base = window.exploreConfig?.semanticLoadUrl || '/vault/explore/semantic'
            const url = params.toString()
                ? `${base}?${params.toString()}`
                : base
            const r = await fetch(url)
            const d = await r.json()
            const data = d.data || {}

            applySemanticState(data)
        }catch(e){
            console.error('Semantic load error:', e)
        }
    }

    function renderSemanticPills(data){
        const pills = []
        const anchor = String(data?.anchor || 'Brasil').trim()
        const anchorLower = anchor.toLowerCase()
        const locationOrder = { country: 1, state: 2, city: 3 }
        const orderedLocations = [...(data?.locations || [])]
            .sort((a, b) => {
                const aw = locationOrder[String(a?.type || '').toLowerCase()] ?? 99
                const bw = locationOrder[String(b?.type || '').toLowerCase()] ?? 99
                if (aw !== bw) return aw - bw
                return String(a?.label || '').localeCompare(String(b?.label || ''), 'pt-BR')
            })
        const withAnchor = (cls, label) => {
            const safe = String(label || '').trim()
            if(!safe) return ''
            const isAnchor = safe.toLowerCase() === anchorLower
            const anchorIcon = isAnchor ? '<i class="bi bi-star-fill me-1"></i>' : ''
            const anchorClass = isAnchor ? ' semantic-pill--anchor is-anchor' : ''
            return `<span class="semantic-pill ${cls}${anchorClass}">${anchorIcon}${escapeHtml(safe)}</span>`
        }

        orderedLocations.forEach(l=>{
            const label = String(l?.label || '').trim()
            if(!label) return
            pills.push(withAnchor('semantic-pill--location', label))
        })

        ;(data?.segment || []).forEach(item=>{
            if(item?.name){
                pills.push(withAnchor('semantic-pill--segment', `Segmento · ${item.name}`))
            }
        })
        ;(data?.niche || []).forEach(item=>{
            if(item?.name){
                pills.push(withAnchor('semantic-pill--niche', `Nicho · ${item.name}`))
            }
        })
        if(data?.origin?.[0]?.name){
            pills.push(withAnchor('semantic-pill--origin', `Origem · ${data.origin[0].name}`))
        }

        const hasCore = !!(
            (Array.isArray(data?.segment) && data.segment.length) ||
            (Array.isArray(data?.niche) && data.niche.length) ||
            data?.origin?.[0] ||
            orderedLocations.length
        )

        if(!hasCore && anchor === 'Brasil'){
            pills.push(`<span class="semantic-pill semantic-pill--empty">não definida</span>`)
        }

        if(semanticPills){
            semanticPills.innerHTML = pills.join('')
        }

        if(semanticTopSummary){
            const hoverPills = []

            const pushPill = (type, label)=>{
                if(!label) return
                const cls = type === 'segment' ? 'semantic-pill--segment'
                    : type === 'niche' ? 'semantic-pill--niche'
                    : type === 'origin' ? 'semantic-pill--origin'
                    : type === 'location' ? 'semantic-pill--location'
                    : 'semantic-pill'
                const safe = String(label || '').trim()
                if(!safe) return
                const isAnchor = safe.toLowerCase() === anchorLower
                const anchorIcon = isAnchor ? '<i class="bi bi-star-fill me-1"></i>' : ''
                const anchorClass = isAnchor ? ' semantic-pill--anchor is-anchor' : ''
                hoverPills.push(`<span class="semantic-pill ${cls}${anchorClass}">${anchorIcon}${escapeHtml(safe)}</span>`)
            }

            const coreLabels = []
            const orderedCoreLocations = [...(data?.locations || [])]
                .sort((a, b) => {
                    const aw = locationOrder[String(a?.type || '').toLowerCase()] ?? 99
                    const bw = locationOrder[String(b?.type || '').toLowerCase()] ?? 99
                    if (aw !== bw) return aw - bw
                    return String(a?.label || '').localeCompare(String(b?.label || ''), 'pt-BR')
                })

            orderedCoreLocations.forEach((l) => {
                const label = String(l?.label || '').trim()
                if(label) coreLabels.push({type:'location', label})
            })

            ;(data?.segment || []).forEach(item=>{
                const label = item?.label || item?.name
                if(label) coreLabels.push({type:'segment', label})
            })
            ;(data?.niche || []).forEach(item=>{
                const label = item?.label || item?.name
                if(label) coreLabels.push({type:'niche', label})
            })
            if(data?.origin?.[0]){
                const label = data.origin[0].label || data.origin[0].name
                if(label) coreLabels.push({type:'origin', label})
            }

            coreLabels.forEach(item=>{
                pushPill(item.type, item.label)
            })

            if(!coreLabels.length && !anchor){
                hoverPills.push(`<span class="semantic-pill semantic-pill--empty">não definida</span>`)
            }

            if(semanticTopAnchor){
                semanticTopAnchor.textContent = anchor
            }
            if(semanticTopHoverPills){
                semanticTopHoverPills.innerHTML = hoverPills.join('')
            }
            const shouldShowTopSummary = hasCore || (anchorLower !== '' && anchorLower !== 'brasil')
            semanticTopSummary.classList.toggle('d-none', !shouldShowTopSummary)
        }
    }

    semanticSaveBtn?.addEventListener('click', async ()=>{
        const startedAt = Date.now()
        const waitMinSubmitFx = async ()=>{
            const elapsed = Date.now() - startedAt
            const remaining = Math.max(0, 2000 - elapsed)
            if(!remaining) return
            await new Promise((resolve)=>setTimeout(resolve, remaining))
        }
        if(semanticSaveBtn){
            semanticSaveBtn.disabled = true
            semanticSaveBtn.classList.add('is-submitting')
        }

        const locations = []
        semanticSelected.city.forEach((_, id)=>locations.push({ type:'city', ref_id:id }))
        semanticSelected.state.forEach((_, id)=>locations.push({ type:'state', ref_id:id }))
        semanticSelected.country.forEach((_, id)=>locations.push({ type:'country', ref_id:id }))

        const rawSourceId = String(sourceSelect?.value || '').trim()
        const parsedSourceId = Number.parseInt(rawSourceId, 10)
        const sourceId = Number.isFinite(parsedSourceId) && parsedSourceId > 0 ? parsedSourceId : null
        if(!sourceId){
            console.error('Semantic save error: source_id inválido/ausente', { rawSourceId })
            await waitMinSubmitFx()
            if(semanticSaveBtn){
                semanticSaveBtn.disabled = false
                semanticSaveBtn.classList.remove('is-submitting')
            }
            return
        }

        const payload = {
            source_id: sourceId,
            anchor: semanticAnchor?.value ? semanticAnchor.value.trim() : null,
            segment_ids: Array.from(semanticSelected.segment.keys()),
            niche_ids: Array.from(semanticSelected.niche.keys()),
            origin_id: semanticSelectedSingle.origin?.id || null,
            locations
        }

        try{
            const url = window.exploreConfig?.semanticSaveUrl || '/vault/explore/semantic'
            const res = await fetch(url, {
                method:'POST',
                headers:{
                    'Content-Type':'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            })
            const j = await res.json().catch(()=>null)
            if(!res.ok || (j && j.ok === false)){
                console.error('Semantic save error:', j)
                await waitMinSubmitFx()
                return
            }

            if(j?.data){
                applySemanticState(j.data)
            }else{
                await loadSemanticState()
            }
            const modalEl = document.getElementById('semanticModal')
            if(modalEl){
                bootstrap.Modal.getOrCreateInstance(modalEl).hide()
            }
            await waitMinSubmitFx()
        }catch(e){
            console.error('Semantic save error:', e)
            await waitMinSubmitFx()
        }finally{
            if(semanticSaveBtn){
                semanticSaveBtn.disabled = false
                semanticSaveBtn.classList.remove('is-submitting')
            }
        }
    })

    semanticBtn?.addEventListener('click', ()=>{
        loadSemanticState()
    })

    loadSemanticOptions()
    loadSearchSemanticFilters()
    initSemanticTooltips()

	    initExploreTooltips()
	    initExploreOperationsWizard()

	    loadColumnWidths()
	    loadSources()
    if(isForceImportGate){
        setHasSources(false)
        importController.setExploreLocked(false)
    }
    setDataQualityEnabled(!!sourceSelect?.value)
    setSemanticEnabled(!!sourceSelect?.value)
    syncImportAttention()
    applyBaseHeaderLabels()
    bootstrapExtrasHeaders()
    load(true)

    initGuestWelcomeModals()
}

function initSemanticTooltips(){
    const modalEl = document.getElementById('semanticModal')
    if(!modalEl || !window.bootstrap?.Tooltip) return

    const setup = ()=>{
        modalEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el)=>{
            bootstrap.Tooltip.getOrCreateInstance(el)
        })
    }

    setup()
    modalEl.addEventListener('shown.bs.modal', setup)
}

function initExploreTooltips(){
    if(!window.bootstrap?.Tooltip) return
    const nodes = new Set([
        ...document.querySelectorAll('[data-bs-toggle="tooltip"]'),
        ...document.querySelectorAll('[data-grade-tooltip]')
    ])
    nodes.forEach((el)=>{
        bootstrap.Tooltip.getOrCreateInstance(el)
    })
}

function initGuestWelcomeModals(){
    if(!window.exploreConfig?.isGuest || !window.bootstrap?.Modal) return

    const welcomeEl = document.getElementById('guestWelcomeModal')
    const goodbyeEl = document.getElementById('guestGoodbyeModal')
    const params = new URLSearchParams(window.location.search || '')
    const loggedOut = params.get('logged_out') === '1'

    const showModal = (el)=>{
        if(!el) return
        const modal = bootstrap.Modal.getOrCreateInstance(el)
        modal.show()
    }

    if(loggedOut){
        showModal(goodbyeEl)
        params.delete('logged_out')
        const nextUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`
        window.history.replaceState({}, '', nextUrl)
    }else{
        const storageKey = 'grade_guest_welcome_shown'
        if(!sessionStorage.getItem(storageKey)){
            showModal(welcomeEl)
            sessionStorage.setItem(storageKey, '1')
        }
    }

    document.querySelectorAll('[data-guest-import]').forEach((btn)=>{
        btn.addEventListener('click', ()=>{
            const modalEl = document.getElementById('guestWelcomeModal')
            if(modalEl){
                bootstrap.Modal.getOrCreateInstance(modalEl).hide()
            }
            const importModal = document.getElementById('exploreImportModal')
            if(importModal){
                bootstrap.Modal.getOrCreateInstance(importModal).show()
            }
        })
    })

    document.querySelectorAll('[data-guest-auth]').forEach((btn)=>{
        btn.addEventListener('click', ()=>{
            const target = btn.getAttribute('data-guest-auth')
            const targetId = target === 'register' ? 'authRegisterModal' : 'authLoginModal'
            const authEl = document.getElementById(targetId)
            if(!authEl) return
            if(goodbyeEl){
                bootstrap.Modal.getOrCreateInstance(goodbyeEl).hide()
            }
            bootstrap.Modal.getOrCreateInstance(authEl).show()
        })
    })
}
