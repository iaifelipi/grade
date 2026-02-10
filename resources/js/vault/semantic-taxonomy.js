import '../../css/vault/semantic-taxonomy.css'

export default function initSemanticTaxonomy(){
    const modalEl = document.getElementById('semanticTaxonomyModal')
    const form = document.getElementById('semanticTaxonomyForm')
    const nameInput = document.getElementById('semanticTaxonomyName')
    const methodInput = document.getElementById('semanticTaxonomyMethod')
    const title = document.getElementById('semanticTaxonomyModalTitle')
    const hint = document.getElementById('semanticTaxonomyModalHint')
    const submit = document.getElementById('semanticTaxonomySubmit')

    if(!modalEl || !form) return

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl)

    const openCreate = (button)=>{
        form.setAttribute('action', button.dataset.action || '')
        methodInput.value = 'POST'
        nameInput.value = ''
        title.textContent = `Novo ${labelType(button.dataset.type)}`
        hint.textContent = 'Adicione um termo claro e específico.'
        submit.textContent = 'Salvar'
        modal.show()
        nameInput.focus()
    }

    const openEdit = (button)=>{
        form.setAttribute('action', button.dataset.action || '')
        methodInput.value = 'PUT'
        nameInput.value = button.dataset.name || ''
        title.textContent = `Editar ${labelType(button.dataset.type)}`
        hint.textContent = 'Atualize o nome com cuidado para manter consistência.'
        submit.textContent = 'Atualizar'
        modal.show()
        nameInput.focus()
        nameInput.select()
    }

    const labelType = (type)=>{
        if(type === 'segments') return 'segmento'
        if(type === 'niches') return 'nicho'
        if(type === 'origins') return 'origem'
        return 'item'
    }

    document.querySelectorAll('[data-semantic-create]').forEach((btn)=>{
        btn.addEventListener('click', ()=> openCreate(btn))
    })

    document.querySelectorAll('[data-semantic-edit]').forEach((btn)=>{
        btn.addEventListener('click', ()=> openEdit(btn))
    })

    bindDeleteModal()
    bindBulkActions()

    bindSearchAndQuickAdd()
}

function bindDeleteModal(){
    const modalEl = document.getElementById('semanticDeleteModal')
    const messageEl = document.getElementById('semanticDeleteMessage')
    const listEl = document.getElementById('semanticDeleteList')
    const confirmBtn = document.getElementById('semanticDeleteConfirm')
    if(!modalEl || !confirmBtn) return

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl)
    let pendingForm = null

    document.querySelectorAll('.semantic-admin-delete').forEach((formEl)=>{
        formEl.addEventListener('submit', (e)=>{
            e.preventDefault()
            pendingForm = formEl
            const name = formEl.getAttribute('data-name') || 'este item'
            const type = formEl.getAttribute('data-type') || ''
            const typeLabel = type === 'segments'
                ? 'segmento'
                : type === 'niches'
                    ? 'nicho'
                    : type === 'origins'
                        ? 'origem'
                        : 'item'
            const article = typeLabel === 'origem' ? 'a' : 'o'
            const msg = `Você está prestes a excluir ${article} ${typeLabel} "${name}". Esta ação não pode ser desfeita.`
            if(messageEl) messageEl.textContent = msg
            if(listEl){
                listEl.innerHTML = `
                    <div class="semantic-delete-group-title">${typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1)}</div>
                    <div class="semantic-delete-row">${name}</div>
                `
            }
            modal.show()
        })
    })

    confirmBtn.addEventListener('click', ()=>{
        if(pendingForm){
            pendingForm.submit()
            pendingForm = null
        }
        if(listEl){
            listEl.innerHTML = ''
        }
        modal.hide()
    })
}

function bindSearchAndQuickAdd(){
    document.querySelectorAll('[data-semantic-search]').forEach((input)=>{
        input.addEventListener('input', ()=>{
            const term = (input.value || '').toLowerCase().trim()
            const card = input.closest('.semantic-admin-card')
            if(!card) return
            const items = card.querySelectorAll('[data-semantic-item]')
            let visible = 0
            items.forEach((row)=>{
                const name = (row.getAttribute('data-name') || '').toLowerCase()
                const match = term === '' || name.includes(term)
                row.classList.toggle('is-hidden', !match)
                if(match) visible += 1
            })
            const empty = card.querySelector('[data-semantic-empty]')
            if(empty){
                empty.classList.toggle('d-none', visible > 0)
            }
        })
    })

    document.querySelectorAll('[data-semantic-quickadd]').forEach((form)=>{
        const input = form.querySelector('input[name="name"]')
        if(!input) return
        input.addEventListener('keydown', (e)=>{
            if(e.key === 'Enter'){
                form.requestSubmit()
            }
        })
    })
}

function bindBulkActions(){
    const cards = Array.from(document.querySelectorAll('.semantic-admin-card'))

    const clearOtherSelections = (currentCard)=>{
        cards.forEach((card)=>{
            if(card === currentCard) return
            card.querySelectorAll('[data-semantic-select]').forEach((chk)=>{
                chk.checked = false
            })
            const bulkbar = card.querySelector('[data-semantic-bulkbar]')
            const selectedCount = card.querySelector('[data-semantic-selected]')
            if(selectedCount) selectedCount.textContent = '0'
            if(bulkbar) bulkbar.classList.add('d-none')
        })
    }

    cards.forEach((card)=>{
        const selects = card.querySelectorAll('[data-semantic-select]')
        const bulkbar = card.querySelector('[data-semantic-bulkbar]')
        const selectedCount = card.querySelector('[data-semantic-selected]')
        const bulkDeleteBtn = card.querySelector('[data-semantic-bulk-delete]')

        const updateBulkbar = ()=>{
            const ids = Array.from(selects)
                .filter((el)=>el.checked)
                .map((el)=>el.getAttribute('data-id'))
                .filter(Boolean)
            if(selectedCount) selectedCount.textContent = String(ids.length)
            if(bulkbar){
                bulkbar.classList.toggle('d-none', ids.length === 0)
            }
            return ids
        }

        selects.forEach((chk)=>{
            chk.addEventListener('change', ()=>{
                if(chk.checked){
                    clearOtherSelections(card)
                }
                updateBulkbar()
            })
        })

        bulkDeleteBtn?.addEventListener('click', ()=>{
            const ids = updateBulkbar()
            if(!ids.length) return
            openBulkDelete(card, ids)
        })
    })
}

function openBulkDelete(card, ids){
    const modalEl = document.getElementById('semanticDeleteModal')
    const messageEl = document.getElementById('semanticDeleteMessage')
    const listEl = document.getElementById('semanticDeleteList')
    const confirmBtn = document.getElementById('semanticDeleteConfirm')
    if(!modalEl || !confirmBtn) return

    const nameMap = new Map()
    card.querySelectorAll('[data-semantic-item]').forEach((row)=>{
        const input = row.querySelector('[data-semantic-select]')
        const id = input?.getAttribute('data-id')
        const name = row.getAttribute('data-name') || ''
        if(id) nameMap.set(id, name)
    })

    const names = ids.map((id)=>nameMap.get(id)).filter(Boolean)
    const preview = names.slice(0, 12)
    const extraCount = names.length - preview.length

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = card.querySelector('[data-semantic-bulkadd]')?.getAttribute('action')?.replace('bulk-add', 'bulk-delete') || ''

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    const tokenInput = document.createElement('input')
    tokenInput.type = 'hidden'
    tokenInput.name = '_token'
    tokenInput.value = csrf
    form.appendChild(tokenInput)

    ids.forEach((id)=>{
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = 'ids[]'
        input.value = id
        form.appendChild(input)
    })

    const typeLabel = card.querySelector('[data-semantic-create]')?.getAttribute('data-type') || ''
    const typeHuman = typeLabel === 'segments'
        ? 'Segmento'
        : typeLabel === 'niches'
            ? 'Nicho'
            : typeLabel === 'origins'
                ? 'Origem'
                : 'Item'

    const msg = `Você está prestes a excluir ${ids.length} itens selecionados. Esta ação não pode ser desfeita.`
    if(messageEl) messageEl.textContent = msg

    if(listEl){
        const rows = preview.map((name)=>`<div class="semantic-delete-row">${name}</div>`)
        if(extraCount > 0){
            rows.push(`<div class="semantic-delete-more">+${extraCount} outros selecionados</div>`)
        }
        listEl.innerHTML = `
            <div class="semantic-delete-group-title">${typeHuman}</div>
            ${rows.join('')}
        `
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl)
    modal.show()

    const onConfirm = ()=>{
        confirmBtn.removeEventListener('click', onConfirm)
        document.body.appendChild(form)
        form.submit()
    }
    confirmBtn.addEventListener('click', onConfirm)
}
