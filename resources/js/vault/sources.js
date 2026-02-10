export default function initSources(){

    const body = document.getElementById('sourcesBody')
    const form = document.getElementById('uploadForm')
    const bar  = document.getElementById('uploadBar')
    const btnUpload = document.getElementById('btnUpload')
    const uploadInput = document.getElementById('uploadInput')
    const uploadToastEl = document.getElementById('uploadToast')
    const openUploadModalBtn = document.getElementById('openUploadModalBtn')
    const clearPendingUploadsBtn = document.getElementById('clearPendingUploadsBtn')
    const confirmClearPendingBtn = document.getElementById('confirmClearPendingBtn')
    const clearPendingModalEl = document.getElementById('clearPendingModal')
    const uploadDropzone = document.getElementById('uploadDropzone')
    const uploadSelectedList = document.getElementById('uploadSelectedList')
    const uploadInvalidAlert = document.getElementById('uploadInvalidAlert')
    const permsEl = document.getElementById('sourcesPermissions')

    const perms = {
        canView: permsEl?.dataset.canView === '1',
        canImport: permsEl?.dataset.canImport === '1',
        canDelete: permsEl?.dataset.canDelete === '1',
        canCancel: permsEl?.dataset.canCancel === '1',
        canReprocess: permsEl?.dataset.canReprocess === '1',
    }
    const purgeSelectedBtn = document.getElementById('purgeSelectedBtn')
    const purgeSelectedConfirmBtn = document.getElementById('purgeSelectedConfirmBtn')
    const purgeSelectedConfirmInput = document.getElementById('purgeSelectedConfirmInput')
    const checkAll = document.getElementById('sourcesCheckAll')

    if(!body) return

    const PENDING_STORAGE_KEY = 'vault_sources_pending_uploads'
    const ORDER_STORAGE_KEY = 'vault_sources_upload_order'
    const PENDING_MAX_AGE_MS = 2 * 60 * 60 * 1000

    let pollingTimer = null
    let lastSources = []
    let pendingUploads = []
    let uploadSeq = 0
    let uploadOrder = []
    const selectedIds = new Set()


    /* ======================================================
       HTTP helper (Grade padrão)
    ====================================================== */

    async function http(url, opts = {}) {

        const res = await fetch(url, {
            headers:{
                'X-Requested-With':'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            ...opts
        })

        if(!res.ok) throw new Error('HTTP '+res.status)

        return res.json()
    }


    /* ======================================================
       LOAD STATUS
    ====================================================== */

    function renderList(){
        const realByName = new Map(lastSources.map(s=>[s.original_name, s]))
        const pendingByName = new Map(pendingUploads.map(p=>[p.original_name, p]))

        const list = []

        // 1) Use the upload order to build the top of the list (first upload on top).
        uploadOrder.forEach(name=>{
            if(realByName.has(name)){
                list.push(realByName.get(name))
                realByName.delete(name)
            } else if(pendingByName.has(name)){
                list.push(pendingByName.get(name))
                pendingByName.delete(name)
            }
        })

        // 2) Append remaining pending (keep original sequence).
        const pendingRemainder = Array.from(pendingByName.values()).sort((a,b)=>{
            const aStart = Number(a.startedAt || 0)
            const bStart = Number(b.startedAt || 0)
            if(aStart !== bStart) return aStart - bStart
            const aSeq = Number(a.seq || 0)
            const bSeq = Number(b.seq || 0)
            return aSeq - bSeq
        })

        // 3) Append remaining real sources (fallback by newest).
        const realRemainder = Array.from(realByName.values()).sort((a,b)=>{
            return new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
        })

        list.push(...pendingRemainder, ...realRemainder)

        if(clearPendingUploadsBtn){
            clearPendingUploadsBtn.style.display = pendingUploads.length ? '' : 'none'
        }

        if(!list.length){
            body.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        Nenhum source importado ainda
                    </td>
                </tr>`
            return
        }

        body.innerHTML = list.map(renderRow).join('')
        bindRowActions()
        syncSelectionState()
    }

    async function load(){

        try {

            const {sources} = await http('/vault/sources/status')

            lastSources = sources
            updateKPIs(sources)
            syncPendingWithSources(sources)
            renderList()

        } catch(err){
            console.error(err)
        }
    }


    /* ======================================================
       ROW
    ====================================================== */

    function renderRow(s){

        const isPending = !!s.__pending

        return `
        <tr>
            <td>
                ${isPending ? '' : `
                <input type="checkbox"
                       class="source-check"
                       data-id="${s.id}"
                       ${selectedIds.has(String(s.id)) ? 'checked' : ''}>`}
            </td>

            <td>${isPending ? '—' : `#${s.id}`}</td>

            <td>
                ${isPending
                    ? `<span class="text-muted">${escape(s.original_name)}</span>`
                    : `<a href="/vault/explore/source/${s.id}" class="text-decoration-none">
                        ${escape(s.original_name)}
                    </a>`}
            </td>

            <td>
                <span class="badge bg-${statusColor(s.status)}">
                    ${s.status === 'uploading' ? 'enviando' : s.status}
                </span>
            </td>

            <td>
                <div class="progress" style="height:8px">
                    <div id="progress-${s.id}" class="progress-bar"
                         style="width:${Number(s.progress_percent)||0}%">
                    </div>
                </div>
            </td>

            <td>${s.inserted_rows || 0}</td>

            <td>${isPending ? '—' : new Date(s.created_at).toLocaleString()}</td>

            <td class="text-end">
                ${renderActions(s)}
            </td>
        </tr>`
    }


    function renderActions(s){

        if(s.__pending || s.status === 'uploading'){
            return ''
        }

        if(s.status === 'importing'){
            if(!perms.canCancel) return ''
            return `
                <button class="btn btn-sm btn-outline-danger btn-cancel"
                        data-id="${s.id}">
                    Cancelar
                </button>`
        }

        if(['failed','done','cancelled'].includes(s.status)){
            if(!perms.canReprocess) return ''
            return `
                <button class="btn btn-sm btn-outline-secondary btn-reprocess"
                        data-id="${s.id}">
                    Reprocessar
                </button>`
        }

        return ''
    }


    /* ======================================================
       ACTIONS (cancel / reprocess)
    ====================================================== */

    function bindRowActions(){

        body.querySelectorAll('.btn-cancel').forEach(btn=>{
            btn.onclick = () => action(`/vault/sources/${btn.dataset.id}/cancel`)
        })

        body.querySelectorAll('.btn-reprocess').forEach(btn=>{
            btn.onclick = () => action(`/vault/sources/${btn.dataset.id}/reprocess`)
        })

        body.querySelectorAll('.source-check').forEach(chk=>{
            chk.onchange = () => {
                const id = chk.dataset.id
                if(chk.checked) selectedIds.add(String(id))
                else selectedIds.delete(String(id))
                syncSelectionState()
            }
        })
    }


    async function action(url){
        await http(url, { method:'POST' })
        load()
    }

    function syncSelectionState(){
        const count = selectedIds.size
        if(purgeSelectedBtn){
            purgeSelectedBtn.disabled = count === 0
        }
        if(checkAll){
            const total = body.querySelectorAll('.source-check').length
            checkAll.checked = total > 0 && count === total
            checkAll.indeterminate = count > 0 && count < total
        }
    }

    // Excluir todos removido (mantemos apenas "Excluir selecionados")

    function canPurgeSelected(){
        return (purgeSelectedConfirmInput?.value || '').trim().toUpperCase() === 'EXCLUIR'
    }

    function syncPurgeSelectedButton(){
        if(!purgeSelectedConfirmBtn) return
        purgeSelectedConfirmBtn.disabled = !canPurgeSelected()
    }

    syncPurgeSelectedButton()
    purgeSelectedConfirmInput?.addEventListener('input', syncPurgeSelectedButton)

    purgeSelectedConfirmBtn?.addEventListener('click', async ()=>{
        if(!canPurgeSelected()) return
        if(selectedIds.size === 0) return

        purgeSelectedConfirmBtn.disabled = true
        purgeSelectedConfirmBtn.textContent = 'Excluindo...'
        try{
            await http('/vault/sources/purge-selected', {
                method:'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ ids: Array.from(selectedIds) })
            })
            const modalEl = document.getElementById('purgeSelectedModal')
            if(modalEl){
                bootstrap.Modal.getOrCreateInstance(modalEl).hide()
            }
            selectedIds.clear()
            if(purgeSelectedConfirmInput) purgeSelectedConfirmInput.value = ''
            load()
        } finally {
            purgeSelectedConfirmBtn.textContent = 'Confirmar exclusão'
            syncPurgeSelectedButton()
        }
    })


    /* ======================================================
       HELPERS
    ====================================================== */

    function statusColor(s){
        return {
            queued:'secondary',
            uploading:'info',
            importing:'warning',
            done:'success',
            failed:'danger',
            cancelled:'dark'
        }[s] || 'secondary'
    }

    function escape(v){
        return String(v ?? '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
    }

    function renderSelectedFiles(){
        if(!uploadSelectedList || !uploadInput) return
        const listEl = uploadSelectedList.querySelector('ul')
        if(!listEl) return
        const files = Array.from(uploadInput.files || [])
        if(!files.length){
            uploadSelectedList.style.display = 'none'
            listEl.innerHTML = ''
            return
        }
        uploadSelectedList.style.display = ''
        listEl.innerHTML = files.map(f=>`
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span class="text-truncate" style="max-width:60%">${escape(f.name)}</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted" style="font-size:12px">${(f.size/1024/1024).toFixed(2)} MB</span>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-file-btn" data-name="${escape(f.name)}">Remover</button>
                </div>
            </li>
        `).join('')

        if(btnUpload){
            btnUpload.disabled = files.length === 0
        }
    }

    function showInvalidAlert(count){
        if(!uploadInvalidAlert) return
        if(count > 0){
            uploadInvalidAlert.textContent = `${count} arquivo(s) ignorado(s) por extensão inválida.`
            uploadInvalidAlert.style.display = ''
        } else {
            uploadInvalidAlert.style.display = 'none'
        }
    }

    function filterAllowedFiles(files){
        const allowed = new Set(['csv','xlsx','xls','txt'])
        const valid = []
        let invalidCount = 0
        files.forEach(f=>{
            const name = String(f.name || '')
            const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : ''
            if(allowed.has(ext)) valid.push(f)
            else invalidCount++
        })
        showInvalidAlert(invalidCount)
        return valid
    }

    function setInputFiles(files){
        if(!uploadInput) return
        const dt = new DataTransfer()
        files.forEach(f=>dt.items.add(f))
        uploadInput.files = dt.files
    }

    function persistPending(){
        try{
            if(pendingUploads.length){
                localStorage.setItem(PENDING_STORAGE_KEY, JSON.stringify(pendingUploads))
            } else {
                localStorage.removeItem(PENDING_STORAGE_KEY)
            }
            if(clearPendingUploadsBtn){
                clearPendingUploadsBtn.style.display = pendingUploads.length ? '' : 'none'
            }
        } catch(err){
            // ignore storage errors
        }
    }

    function persistOrder(){
        try{
            if(uploadOrder.length){
                localStorage.setItem(ORDER_STORAGE_KEY, JSON.stringify(uploadOrder))
            } else {
                localStorage.removeItem(ORDER_STORAGE_KEY)
            }
        } catch(err){
            // ignore storage errors
        }
    }

    function restoreOrder(){
        try{
            const raw = localStorage.getItem(ORDER_STORAGE_KEY)
            if(!raw) return
            const parsed = JSON.parse(raw)
            if(!Array.isArray(parsed)) return
            uploadOrder = parsed.filter(x=>typeof x === 'string')
        } catch(err){
            // ignore storage errors
        }
    }

    function sortSourcesByUploadOrder(list){
        if(!uploadOrder.length) return list
        const index = new Map(uploadOrder.map((name, i)=>[name, i]))
        return [...list].sort((a,b)=>{
            const ai = index.has(a.original_name) ? index.get(a.original_name) : Infinity
            const bi = index.has(b.original_name) ? index.get(b.original_name) : Infinity
            if(ai !== bi) return ai - bi
            return new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
        })
    }

    function restorePending(){
        try{
            const raw = localStorage.getItem(PENDING_STORAGE_KEY)
            if(!raw) return
            const parsed = JSON.parse(raw)
            if(!Array.isArray(parsed)) return
            const now = Date.now()
            let maxSeq = uploadSeq
            pendingUploads = parsed.filter(p=>{
                const startedAt = Number(p.startedAt || 0)
                return startedAt > 0 && (now - startedAt) <= PENDING_MAX_AGE_MS
            }).map((p, idx)=>({
                ...p,
                id: p.id || `pending-${Date.now()}-${idx}`,
                seq: typeof p.seq === 'number' ? p.seq : (uploadSeq + idx),
                status: 'uploading',
                progress_percent: p.progress_percent || 0,
                inserted_rows: 0,
                __pending: true,
            }))
            pendingUploads.forEach(p=>{
                const s = Number(p.seq || 0)
                if(s > maxSeq) maxSeq = s
            })
            uploadSeq = maxSeq + 1

            if(pendingUploads.length){
                if(openUploadModalBtn) openUploadModalBtn.disabled = true
                if(uploadToastEl){
                    bootstrap.Toast.getOrCreateInstance(uploadToastEl).show()
                }
                renderList()
            }
        } catch(err){
            // ignore storage errors
        }
    }

    function syncPendingWithSources(sources){
        if(!pendingUploads.length) return

        const names = new Set(sources.map(s=>s.original_name))
        const remaining = pendingUploads.filter(p=>!names.has(p.original_name))

        if(remaining.length !== pendingUploads.length){
            pendingUploads = remaining
            persistPending()
        }

        if(!pendingUploads.length){
            if(uploadOrder.length){
                uploadOrder = []
                persistOrder()
            }

            if(openUploadModalBtn) openUploadModalBtn.disabled = false
            if(uploadToastEl){
                bootstrap.Toast.getOrCreateInstance(uploadToastEl).hide()
            }

            if(clearPendingUploadsBtn){
                clearPendingUploadsBtn.style.display = 'none'
            }
        } else {
            if(openUploadModalBtn) openUploadModalBtn.disabled = true
        }
    }

    /* ======================================================
       KPIs
    ====================================================== */

    function updateKPIs(list){

        const set = (id,val)=>{
            const el=document.getElementById(id)
            if(el) el.textContent = val
        }

        set('kpiTotal', list.length)
        set('kpiImporting', list.filter(x=>x.status==='importing').length)
        set('kpiDone', list.filter(x=>x.status==='done').length)
        set('kpiFailed', list.filter(x=>x.status==='failed').length)
    }


    /* ======================================================
       UPLOAD (XHR progress)
    ====================================================== */

    form?.addEventListener('submit', e => {

        e.preventDefault()

        if(!perms.canImport){
            return
        }

        if(!uploadInput || !uploadInput.files || uploadInput.files.length === 0){
            showInvalidAlert(1)
            return
        }

        const fd = new FormData(form)

        const files = Array.from(uploadInput?.files || [])
        const now = Date.now()
        uploadOrder = [...uploadOrder, ...files.map(f=>f.name)]
        uploadOrder = Array.from(new Set(uploadOrder))
        persistOrder()
        pendingUploads = files.map((file, idx)=>({
            id: `pending-${now}-${idx}`,
            original_name: file.name,
            status: 'uploading',
            progress_percent: 0,
            inserted_rows: 0,
            created_at: new Date().toISOString(),
            startedAt: now,
            seq: uploadSeq++,
            __pending: true,
        }))
        persistPending()
        renderList()
        renderSelectedFiles()

        const modalEl = document.getElementById('uploadModal')
        if(modalEl){
            bootstrap.Modal.getOrCreateInstance(modalEl).hide()
        }

        if(btnUpload){
            btnUpload.dataset.originalText = btnUpload.textContent || ''
            btnUpload.textContent = 'Enviando...'
            btnUpload.disabled = true
        }

        if(openUploadModalBtn){
            openUploadModalBtn.disabled = true
        }

        if(uploadInput){
            uploadInput.disabled = true
        }

        if(uploadToastEl){
            bootstrap.Toast.getOrCreateInstance(uploadToastEl).show()
        }

        const xhr = new XMLHttpRequest()

        xhr.open('POST', form.action)

        xhr.setRequestHeader(
            'X-CSRF-TOKEN',
            document.querySelector('meta[name="csrf-token"]').content
        )

        xhr.upload.onprogress = e => {
            if(e.lengthComputable && bar){
                const pct = (e.loaded/e.total*100)
                bar.style.width = pct+'%'
                pendingUploads.forEach(p=>{
                    p.progress_percent = pct
                    const el = document.getElementById(`progress-${p.id}`)
                    if(el) el.style.width = pct+'%'
                })
            }
        }

        const finishUpload = () => {

            if(bar) bar.style.width='0%'

            form.reset()
            renderSelectedFiles()
            showInvalidAlert(0)

            if(btnUpload){
                btnUpload.textContent = btnUpload.dataset.originalText || 'Registrar e Importar'
                btnUpload.disabled = false
            }

            if(openUploadModalBtn){
                openUploadModalBtn.disabled = false
            }

            if(uploadInput){
                uploadInput.disabled = false
            }

            if(uploadToastEl){
                bootstrap.Toast.getOrCreateInstance(uploadToastEl).hide()
            }

            pendingUploads = []
            persistPending()

            load()
        }

        xhr.onload = () => {
            finishUpload()
        }

        xhr.onerror = () => {
            finishUpload()
        }

        xhr.send(fd)
    })


    /* ======================================================
       POLLING SAFE
    ====================================================== */

    restoreOrder()
    restorePending()
    load()

    if(openUploadModalBtn){
        openUploadModalBtn.disabled = !perms.canImport || openUploadModalBtn.disabled
    }

    // Allow deep-link from Explore to open import modal directly.
    const url = new URL(window.location.href)
    if(url.searchParams.get('open_import') === '1' && perms.canImport){
        const modalEl = document.getElementById('uploadModal')
        if(modalEl && window.bootstrap?.Modal){
            requestAnimationFrame(()=>{
                bootstrap.Modal.getOrCreateInstance(modalEl).show()
            })
        }
        url.searchParams.delete('open_import')
        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`)
    }

    if(uploadDropzone && uploadInput){
        const highlight = (on)=>{
            if(on) uploadDropzone.classList.add('border-primary')
            else uploadDropzone.classList.remove('border-primary')
        }

        uploadDropzone.addEventListener('click', (e)=>{
            if(e.target === uploadInput) return
            uploadInput.click()
        })

        ;['dragenter','dragover'].forEach(evt=>{
            uploadDropzone.addEventListener(evt, (e)=>{
                e.preventDefault()
                e.stopPropagation()
                highlight(true)
            })
        })

        ;['dragleave','drop'].forEach(evt=>{
            uploadDropzone.addEventListener(evt, (e)=>{
                e.preventDefault()
                e.stopPropagation()
                highlight(false)
            })
        })

        uploadDropzone.addEventListener('drop', (e)=>{
            const files = Array.from(e.dataTransfer?.files || [])
            if(!files.length) return
            const valid = filterAllowedFiles(files)
            if(!valid.length) return
            setInputFiles(valid)
            renderSelectedFiles()
        })
    }

    uploadInput?.addEventListener('change', ()=>{
        const files = Array.from(uploadInput.files || [])
        const valid = filterAllowedFiles(files)
        if(valid.length !== files.length){
            setInputFiles(valid)
        }
        renderSelectedFiles()
    })

    uploadSelectedList?.addEventListener('click', (e)=>{
        const btn = e.target.closest('.remove-file-btn')
        if(!btn || !uploadInput) return
        const name = btn.dataset.name
        const files = Array.from(uploadInput.files || []).filter(f=>f.name !== name)
        setInputFiles(files)
        renderSelectedFiles()
        showInvalidAlert(0)
    })

    clearPendingUploadsBtn?.addEventListener('click', ()=>{
        if(clearPendingModalEl){
            bootstrap.Modal.getOrCreateInstance(clearPendingModalEl).show()
        }
    })

    confirmClearPendingBtn?.addEventListener('click', ()=>{
        pendingUploads = []
        persistPending()
        uploadOrder = []
        persistOrder()
        if(openUploadModalBtn) openUploadModalBtn.disabled = false
        if(uploadToastEl){
            bootstrap.Toast.getOrCreateInstance(uploadToastEl).hide()
        }
        if(clearPendingModalEl){
            bootstrap.Modal.getOrCreateInstance(clearPendingModalEl).hide()
        }
        renderList()
    })

    if(!pollingTimer){
        pollingTimer = setInterval(()=>{
            if(!document.hidden) load()
        }, 2000)
    }

    checkAll?.addEventListener('change', ()=>{
        const checked = checkAll.checked
        body.querySelectorAll('.source-check').forEach(chk=>{
            chk.checked = checked
            const id = chk.dataset.id
            if(checked) selectedIds.add(String(id))
            else selectedIds.delete(String(id))
        })
        syncSelectionState()
    })
}
