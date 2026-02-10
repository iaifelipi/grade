export default function initColumnsAdminPage(options = {}){
    const force = !!options?.force
    if(window.__pixipColumnsAdminInit && !force){
        if(import.meta?.env?.DEV){
            console.warn('[columns-admin] init called more than once; skipping.')
        }
        return
    }
    window.__pixipColumnsAdminInit = true
    const dataQualityMenuBtn = document.getElementById('adminColumnsDataQualityBtn')
    const dataQualityModalEl = document.getElementById('adminColumnsDataQualityModal')
    const dataQualityModalBody = document.getElementById('adminColumnsDataQualityModalBody')

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')

    const getSelectedSourceId = ()=>{
        const sourceSelect = document.getElementById('columnsSourceSelect')
        return String(sourceSelect?.value || '').trim()
    }

    const setDataQualityLoading = (message = 'Carregando qualidade de dados...')=>{
        if(!dataQualityModalBody) return
        dataQualityModalBody.innerHTML = `
            <div class="explore-dq-modal-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <span>${escapeHtml(message)}</span>
            </div>
        `
    }

    const buildDataQualityUrl = (sourceId = '')=>{
        const base = dataQualityMenuBtn?.dataset?.modalUrl || '/explore/data-quality/modal'
        const params = new URLSearchParams()
        if(sourceId) params.set('source_id', sourceId)
        return params.toString() ? `${base}?${params.toString()}` : base
    }

    const loadDataQualityModal = async (sourceId = '')=>{
        if(!dataQualityModalBody) return
        try{
            setDataQualityLoading()
            const response = await fetch(buildDataQualityUrl(sourceId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            if(!response.ok){
                throw new Error(`HTTP ${response.status}`)
            }
            const html = await response.text()
            dataQualityModalBody.innerHTML = html
            const { default: initDataQualityPage } = await import('./data-quality')
            initDataQualityPage({
                root: dataQualityModalBody,
                onSourceChange: (id)=>loadDataQualityModal(id)
            })
        }catch(e){
            console.error('Data quality modal load error:', e)
            dataQualityModalBody.innerHTML = `
                <div class="explore-dq-modal-error">
                    <div>Não foi possível abrir Qualidade de Dados.</div>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="adminColumnsDataQualityRetryBtn">
                        Tentar novamente
                    </button>
                </div>
            `
            dataQualityModalBody.querySelector('#adminColumnsDataQualityRetryBtn')?.addEventListener('click', ()=>{
                loadDataQualityModal(sourceId)
            })
        }
    }

    dataQualityMenuBtn?.addEventListener('click', async (event)=>{
        event.preventDefault()
        if(!dataQualityModalEl || !window.bootstrap?.Modal) return
        const modal = window.bootstrap.Modal.getOrCreateInstance(dataQualityModalEl)
        modal.show()
        await loadDataQualityModal(getSelectedSourceId())
    })

    function updateColumnsSourceContext(currentSource){
        const sourceLabel = document.getElementById('columnsSourceLabel')
        const sourceText = document.getElementById('columnsCurrentSourceText')
        const statusLabel = document.getElementById('columnsStatusLabel')
        const statusText = document.getElementById('columnsSourceStatusText')
        const activeBadge = document.getElementById('columnsActiveSourceBadge')
        const focusStatus = document.getElementById('columnsSourceFocusStatus')

        if(currentSource){
            if(sourceText) sourceText.textContent = `${currentSource.name} (ID ${currentSource.id})`
            if(activeBadge) activeBadge.textContent = `Arquivo ativo: ${currentSource.name} (ID ${currentSource.id})`
            sourceLabel?.classList.remove('d-none')
            sourceText?.classList.remove('d-none')
            statusLabel?.classList.add('d-none')
            statusText?.classList.add('d-none')
            activeBadge?.classList.remove('d-none')
            focusStatus?.classList.add('d-none')
        }else{
            sourceLabel?.classList.add('d-none')
            sourceText?.classList.add('d-none')
            statusLabel?.classList.remove('d-none')
            statusText?.classList.remove('d-none')
            if(statusText) statusText.textContent = 'Selecione um arquivo no Explore'
            activeBadge?.classList.add('d-none')
            focusStatus?.classList.remove('d-none')
        }
    }

    async function loadColumnsForSource(sourceId){
        const content = document.getElementById('columnsAdminContent')
        if(!content) return

        const url = new URL(content.dataset.dataUrl || '', window.location.origin)
        if(sourceId) url.searchParams.set('source_id', sourceId)

        content.classList.add('is-loading')
        try{
            const res = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            if(!res.ok) throw new Error('Request failed')
            const data = await res.json()
            content.innerHTML = data.html || ''
            if(typeof data.has_source !== 'undefined'){
                content.dataset.hasSource = data.has_source ? '1' : '0'
            }
            updateColumnsSourceContext(data.current_source)
            initColumnsAdmin()
        }catch(e){
            console.error(e)
            window.location.href = sourceId
                ? `${content.dataset.sourceUrlBase || '/explore/columns/source'}/${sourceId}`
                : (content.dataset.clearUrl || '/explore/columns/source/clear')
        }finally{
            content.classList.remove('is-loading')
        }
    }

    function initColumnsAdmin(){
        const content = document.getElementById('columnsAdminContent')
        const hasSource = content?.dataset?.hasSource === '1'

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            if(window.bootstrap?.Tooltip){
                window.bootstrap.Tooltip.getOrCreateInstance(el)
            }
        })

        document.querySelectorAll('[data-columns-requires-source]').forEach(btn => {
            btn.disabled = !hasSource
        })

        const searchInput = document.querySelector('[data-columns-search]')
        const typeFilter = document.querySelector('[data-columns-type]')
        const visibleFilter = document.querySelector('[data-columns-visible]')
        const rows = Array.from(document.querySelectorAll('.columns-admin-row'))
            .filter(row => !row.classList.contains('columns-admin-row--head'))
        const toolbar = document.querySelector('.columns-admin-toolbar')
        const selectionBar = document.querySelector('[data-columns-selection]')
        const selectionCount = document.querySelector('[data-columns-selection-count]')
        const selectAll = document.querySelector('[data-columns-select-all]')
        const bulkDeleteForm = document.getElementById('bulkDeleteColumnsForm')
        const bulkDeleteModalEl = document.getElementById('bulkDeleteColumnsModal')
        const bulkDeleteList = document.getElementById('bulkDeleteColumnsList')
        const bulkDeleteWarning = document.getElementById('bulkDeleteColumnsWarning')
        const bulkDeleteConfirmBtn = document.querySelector('[data-columns-bulk-confirm]')
        let bulkDeleteSelection = []
        const mergeForm = document.getElementById('mergeColumnsForm')
        const renameModalEl = document.getElementById('renameColumnModal')
        const mergeModalEl = document.getElementById('mergeColumnModal')
        const mergeList = document.getElementById('mergeColumnList')
        const mergeDescription = document.getElementById('mergeColumnDescription')
        const mergeWarning = document.getElementById('mergeColumnsWarning')
        const renameInput = document.getElementById('renameColumnInput')
        const renameKey = document.getElementById('renameColumnKey')
        const confirmRenameBtn = document.getElementById('confirmRenameColumn')
        const confirmMergeBtn = document.getElementById('confirmMergeColumn')
        let renameTargetRow = null
        let mergeSelection = []
        const selectionWarning = document.querySelector('[data-columns-selection-warning]')
        const resetBtn = document.getElementById('columnsResetBtn')
        const resetModalEl = document.getElementById('columnsResetModal')

        function updateFilterState(query, type, visible){
            const hasQuery = query.length > 0
            const hasTypeFilter = type !== 'all'
            const hasVisibleFilter = visible !== 'all'
            const hasAnyFilter = hasQuery || hasTypeFilter || hasVisibleFilter

            searchInput?.classList.toggle('is-active-filter', hasQuery)
            typeFilter?.classList.toggle('is-active-filter', hasTypeFilter)
            visibleFilter?.classList.toggle('is-active-filter', hasVisibleFilter)
            toolbar?.classList.toggle('is-filtering', hasAnyFilter)
        }

        function applyFilters(){
            const query = (searchInput?.value || '').trim().toLowerCase()
            const type = typeFilter?.value || 'all'
            const visible = visibleFilter?.value || 'all'
            let visibleCount = 0

            rows.forEach(row => {
                const key = row.dataset.key || ''
                const label = row.dataset.label || ''
                const group = row.dataset.group || ''
                const rowType = row.dataset.type || 'base'
                const rowVisible = row.dataset.visible === '1' ? 'visible' : 'hidden'

                const matchesQuery = !query || key.includes(query) || label.includes(query) || group.includes(query)
                const matchesType = type === 'all' || rowType === type
                const matchesVisible = visible === 'all' || rowVisible === visible

                const shouldShow = matchesQuery && matchesType && matchesVisible
                row.style.display = shouldShow ? '' : 'none'
                if(shouldShow) visibleCount += 1
            })

            const emptyState = document.querySelector('[data-columns-empty]')
            if(emptyState){
                emptyState.style.display = visibleCount ? 'none' : 'block'
            }

            updateFilterState(query, type, visible)
        }

        searchInput?.addEventListener('input', applyFilters)
        typeFilter?.addEventListener('change', applyFilters)
        visibleFilter?.addEventListener('change', applyFilters)
        applyFilters()

        const actionButtons = document.querySelectorAll('[data-columns-action]')
        actionButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-columns-action')
                rows.forEach(row => {
                    const select = row.querySelector('select[name*="[visible]"]')
                    if(!select) return
                    if(action === 'show-all') select.value = '1'
                    if(action === 'hide-all') select.value = '0'
                    row.dataset.visible = select.value === '1' ? '1' : '0'
                })
                applyFilters()
            })
        })

        rows.forEach(row => {
            const select = row.querySelector('select[name*="[visible]"]')
            if(!select) return
            select.addEventListener('change', () => {
                row.dataset.visible = select.value === '1' ? '1' : '0'
                applyFilters()
            })
        })

        function getSelectedRows(){
            return rows.filter(row => row.querySelector('[data-columns-checkbox]')?.checked)
        }

        function clearFormIds(form){
            if(!form) return
            form.querySelectorAll('input[name="ids[]"], input[name="target_id"]').forEach(input => input.remove())
        }

        function updateSelectionUI(){
            const selectedRows = getSelectedRows()
            const count = selectedRows.length
            if(selectionCount) selectionCount.textContent = count
            if(selectionBar) selectionBar.classList.toggle('is-active', count > 0)
            rows.forEach(row => {
                const checked = row.querySelector('[data-columns-checkbox]')?.checked
                row.classList.toggle('is-selected', Boolean(checked))
            })

            const baseSelected = selectedRows.filter(row => row.dataset.type === 'base')
            if(selectionWarning){
                if(baseSelected.length){
                    selectionWarning.textContent = 'Só colunas extras podem ser mescladas ou excluídas.'
                    selectionWarning.classList.add('is-visible')
                }else{
                    selectionWarning.textContent = ''
                    selectionWarning.classList.remove('is-visible')
                }
            }

            const actionButtons = document.querySelectorAll('[data-columns-selection] [data-columns-action]')
            actionButtons.forEach(btn => {
                const action = btn.getAttribute('data-columns-action')
                if(!hasSource){
                    btn.disabled = true
                    return
                }
                if(action === 'rename'){
                    btn.disabled = count !== 1
                }else if(action === 'merge'){
                    btn.disabled = count < 2 || baseSelected.length > 0
                }else if(action === 'show-selected' || action === 'hide-selected' || action === 'delete'){
                    btn.disabled = count < 1 || (action === 'delete' && baseSelected.length > 0)
                }
            })

            if(selectAll){
                const visibleRows = rows.filter(row => row.style.display !== 'none')
                const allChecked = visibleRows.length && visibleRows.every(row => row.querySelector('[data-columns-checkbox]')?.checked)
                selectAll.checked = allChecked
                selectAll.indeterminate = !allChecked && visibleRows.some(row => row.querySelector('[data-columns-checkbox]')?.checked)
            }
        }

        rows.forEach(row => {
            const checkbox = row.querySelector('[data-columns-checkbox]')
            if(!checkbox) return
            checkbox.addEventListener('change', updateSelectionUI)
        })

        selectAll?.addEventListener('change', () => {
            const shouldCheck = selectAll.checked
            rows.forEach(row => {
                if(row.style.display === 'none') return
                const checkbox = row.querySelector('[data-columns-checkbox]')
                if(checkbox) checkbox.checked = shouldCheck
            })
            updateSelectionUI()
        })

        document.querySelectorAll('[data-columns-selection] [data-columns-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-columns-action')
                const selectedRows = getSelectedRows()
                if(!selectedRows.length && action !== 'clear-selection') return

                if(action === 'clear-selection'){
                    rows.forEach(row => {
                        const checkbox = row.querySelector('[data-columns-checkbox]')
                        if(checkbox) checkbox.checked = false
                    })
                    updateSelectionUI()
                    return
                }

                if(action === 'show-selected' || action === 'hide-selected'){
                    selectedRows.forEach(row => {
                        const select = row.querySelector('select[name*="[visible]"]')
                        if(select){
                            select.value = action === 'show-selected' ? '1' : '0'
                            row.dataset.visible = select.value === '1' ? '1' : '0'
                        }
                    })
                    applyFilters()
                    updateSelectionUI()
                    return
                }

                if(action === 'delete'){
                    const baseSelected = selectedRows.filter(row => row.dataset.type === 'base')
                    if(!bulkDeleteForm || !bulkDeleteList) return
                    bulkDeleteSelection = selectedRows
                    clearFormIds(bulkDeleteForm)
                    if(bulkDeleteWarning){
                        bulkDeleteWarning.classList.toggle('d-none', baseSelected.length === 0)
                    }
                    if(bulkDeleteConfirmBtn){
                        bulkDeleteConfirmBtn.disabled = baseSelected.length > 0
                    }
                    selectedRows.forEach(row => {
                        const checkbox = row.querySelector('[data-columns-checkbox]')
                        if(!checkbox) return
                        const input = document.createElement('input')
                        input.type = 'hidden'
                        input.name = 'ids[]'
                        input.value = checkbox.value
                        bulkDeleteForm.appendChild(input)
                    })
                    bulkDeleteList.innerHTML = selectedRows.map(row => {
                        const key = row.querySelector('.columns-admin-key-main')?.textContent?.trim() || ''
                        const label = row.querySelector('input[name*="[label]"]')?.value || key
                        return `<div class="columns-admin-delete-item"><strong>${label}</strong><span>${key}</span></div>`
                    }).join('')
                    if(window.bootstrap?.Modal && bulkDeleteModalEl){
                        window.bootstrap.Modal.getOrCreateInstance(bulkDeleteModalEl).show()
                    }
                    return
                }

                if(action === 'rename'){
                    renameTargetRow = selectedRows[0] || null
                    if(!renameTargetRow) return
                    const labelInput = renameTargetRow.querySelector('input[name*="[label]"]')
                    const keyText = renameTargetRow.querySelector('.columns-admin-key-main')?.textContent || ''
                    if(renameInput) renameInput.value = labelInput?.value || ''
                    if(renameKey) renameKey.textContent = keyText ? `Chave: ${keyText}` : ''
                    if(window.bootstrap?.Modal && renameModalEl){
                        window.bootstrap.Modal.getOrCreateInstance(renameModalEl).show()
                    }
                    return
                }

                if(action === 'merge'){
                    mergeSelection = selectedRows
                    if(!mergeList) return
                    const baseSelected = selectedRows.filter(row => row.dataset.type === 'base')
                    mergeList.innerHTML = selectedRows.map((row, idx) => {
                        const key = row.querySelector('.columns-admin-key-main')?.textContent?.trim() || ''
                        const label = row.querySelector('input[name*="[label]"]')?.value || ''
                        const checked = idx === 0 ? 'checked' : ''
                        return `
                            <label class="columns-admin-merge-item">
                                <input type="radio" name="mergeTarget" value="${row.querySelector('[data-columns-checkbox]')?.value}" ${checked}>
                                <span>
                                    <strong>${label || key}</strong>
                                    <span class="text-muted small d-block">${key}</span>
                                </span>
                            </label>
                        `
                    }).join('')

                    const updateMergeDescription = () => {
                        if(!mergeDescription) return
                        const targetInput = mergeList.querySelector('input[name="mergeTarget"]:checked')
                        if(!targetInput) return
                        const targetRow = selectedRows.find(row => row.querySelector('[data-columns-checkbox]')?.value === targetInput.value)
                        const targetKey = targetRow?.querySelector('.columns-admin-key-main')?.textContent?.trim() || 'coluna alvo'
                        const targetLabel = targetRow?.querySelector('input[name*="[label]"]')?.value || targetKey
                        mergeDescription.innerHTML = `
                            A mescla usa <strong>fallback</strong>: o primeiro valor preenchido vira o valor final.<br>
                            As colunas antigas serão removidas do <em>extras_json</em>.<br>
                            <strong>Coluna final:</strong> ${targetLabel} (${targetKey})
                        `
                    }

                    mergeList.querySelectorAll('input[name="mergeTarget"]').forEach(input => {
                        input.addEventListener('change', updateMergeDescription)
                    })
                    updateMergeDescription()

                    if(mergeWarning){
                        mergeWarning.classList.toggle('d-none', baseSelected.length === 0)
                    }
                    if(confirmMergeBtn){
                        confirmMergeBtn.disabled = baseSelected.length > 0
                    }

                    if(window.bootstrap?.Modal && mergeModalEl){
                        window.bootstrap.Modal.getOrCreateInstance(mergeModalEl).show()
                    }
                }
            })
        })

        if(window.bootstrap?.Modal && bulkDeleteModalEl){
            bulkDeleteModalEl.addEventListener('hidden.bs.modal', () => {
                clearFormIds(bulkDeleteForm)
                if(bulkDeleteWarning){
                    bulkDeleteWarning.classList.add('d-none')
                }
                if(bulkDeleteConfirmBtn){
                    bulkDeleteConfirmBtn.disabled = false
                }
            })
        }

        if(window.bootstrap?.Modal && mergeModalEl){
            mergeModalEl.addEventListener('hidden.bs.modal', () => {
                if(mergeWarning){
                    mergeWarning.classList.add('d-none')
                }
                if(confirmMergeBtn){
                    confirmMergeBtn.disabled = false
                }
            })
        }

        confirmRenameBtn?.addEventListener('click', () => {
            if(!renameTargetRow) return
            const labelInput = renameTargetRow.querySelector('input[name*="[label]"]')
            if(labelInput && renameInput){
                labelInput.value = renameInput.value
            }
            if(window.bootstrap?.Modal && renameModalEl){
                window.bootstrap.Modal.getOrCreateInstance(renameModalEl).hide()
            }
        })

        confirmMergeBtn?.addEventListener('click', () => {
            if(!mergeForm || !mergeSelection.length) return
            if(confirmMergeBtn?.disabled) return
            const target = mergeList?.querySelector('input[name="mergeTarget"]:checked')?.value
            if(!target){
                alert('Selecione uma coluna alvo.')
                return
            }

            clearFormIds(mergeForm)
            mergeSelection.forEach(row => {
                const checkbox = row.querySelector('[data-columns-checkbox]')
                if(!checkbox) return
                const input = document.createElement('input')
                input.type = 'hidden'
                input.name = 'ids[]'
                input.value = checkbox.value
                mergeForm.appendChild(input)
            })
            const targetInput = document.createElement('input')
            targetInput.type = 'hidden'
            targetInput.name = 'target_id'
            targetInput.value = target
            mergeForm.appendChild(targetInput)

            mergeForm.submit()
        })

        updateSelectionUI()

        resetBtn?.addEventListener('click', () => {
            if(resetBtn.disabled) return
            if(window.bootstrap?.Modal && resetModalEl){
                window.bootstrap.Modal.getOrCreateInstance(resetModalEl).show()
            }
        })
    }

    initColumnsAdmin()

}
