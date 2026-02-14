export default function initExploreOperationsWizard(){
    const config = window.exploreOpsWizardConfig || null
    if(!config) return

    const modalEl = document.getElementById('exploreMarketingWizardModal')
    if(!modalEl) return

    const stepIndexEl = document.getElementById('opsWizardStepIndex')
    const stepTotalEl = document.getElementById('opsWizardStepTotal')
    const stepTitleEl = document.getElementById('opsWizardStepTitle')

    const errorEl = document.getElementById('opsWizardError')
    const successEl = document.getElementById('opsWizardSuccess')

    const backBtn = document.getElementById('opsWizardBackBtn')
    const nextBtn = document.getElementById('opsWizardNextBtn')
    const dispatchBtn = document.getElementById('opsWizardDispatchBtn')
    const selectedLiveCountEl = document.getElementById('opsWizSelectedCountLive')

    const stepEls = {
        channels: modalEl.querySelector('[data-ops-step="channels"]'),
        email: modalEl.querySelector('[data-ops-step="email"]'),
        sms: modalEl.querySelector('[data-ops-step="sms"]'),
        whatsapp: modalEl.querySelector('[data-ops-step="whatsapp"]'),
        review: modalEl.querySelector('[data-ops-step="review"]'),
    }

    const channelToggles = Array.from(modalEl.querySelectorAll('[data-ops-wiz-channel]'))
    const reasonEls = {
        email: modalEl.querySelector('[data-ops-wiz-reason="email"]'),
        sms: modalEl.querySelector('[data-ops-wiz-reason="sms"]'),
        whatsapp: modalEl.querySelector('[data-ops-wiz-reason="whatsapp"]'),
    }

    const getSelection = () => {
        try{
            const sel = window.exploreSelection?.getSelection?.()
            if(sel && typeof sel === 'object') return sel
        }catch(e){}
        return { mode: 'manual', ids: [], filters: {}, excluded_ids: [], selected_count: 0, total_count: null }
    }

    const selectionPayload = () => {
        const sel = getSelection()
        if(sel.mode === 'all_matching'){
            return {
                selection_mode: 'all_matching',
                filters: sel.filters || {},
                excluded_ids: Array.isArray(sel.excluded_ids) ? sel.excluded_ids : [],
            }
        }
        return {
            selection_mode: 'manual',
            lead_ids: Array.isArray(sel.ids) ? sel.ids : [],
        }
    }

    const selectedCountLabel = () => {
        const sel = getSelection()
        if(typeof sel.selected_count === 'number') return String(sel.selected_count)
        if(sel.mode === 'all_matching') return 'todos'
        return '0'
    }

    const getSelectedChannels = () => channelToggles
        .filter(t => t.checked)
        .map(t => String(t.getAttribute('data-ops-wiz-channel') || ''))
        .filter(Boolean)

    const variantContainer = (key) => modalEl.querySelector(`[data-ops-wiz-variants="${key}"]`)

    const getVariants = (key) => {
        const wrap = variantContainer(key)
        if(!wrap) return []
        const fields = Array.from(wrap.querySelectorAll('input,textarea'))
        return fields
            .map((el) => String(el.value || '').trim())
            .filter(Boolean)
    }

    const addVariant = (key) => {
        const wrap = variantContainer(key)
        if(!wrap) return
        const first = wrap.querySelector('input,textarea')
        if(!first) return
        const clone = first.cloneNode(true)
        clone.value = ''
        wrap.appendChild(clone)
        clone.focus?.()
        if(key === 'email_message'){
            refreshEmailHtmlPreview()
        }
    }

    const fillFirstVariant = (key, value) => {
        const wrap = variantContainer(key)
        if(!wrap) return
        const first = wrap.querySelector('input,textarea')
        if(!first) return
        first.value = String(value || '')
        if(key === 'email_message'){
            refreshEmailHtmlPreview()
        }
    }

    const generateDraft = (kind) => {
        if(kind === 'email_message'){
            return [
                `Oi {nome}, tudo bem?`,
                ``,
                `Quero te mostrar uma oportunidade que pode fazer sentido para voce.`,
                `Se tiver interesse, me responda por aqui e eu te envio os detalhes.`,
                ``,
                `Abraços,`,
                `Equipe`,
            ].join('\n')
        }
        if(kind === 'sms_message'){
            return `Oi {nome}, tenho uma oportunidade rapida para voce. Responda SIM para receber os detalhes.`
        }
        if(kind === 'whatsapp_message'){
            return `Oi {nome}! Tudo bem? Posso te enviar uma informacao rapida?`
        }
        return `Oi {nome}!`
    }

    const emailFormatText = document.getElementById('opsWizEmailFormatText')
    const emailFormatHtml = document.getElementById('opsWizEmailFormatHtml')
    const emailHtmlPreviewWrap = document.getElementById('opsWizEmailHtmlPreviewWrap')
    const emailHtmlPreview = document.getElementById('opsWizEmailHtmlPreview')

    const isEmailHtml = () => !!emailFormatHtml?.checked

    const refreshEmailHtmlPreview = () => {
        if(!emailHtmlPreviewWrap || !emailHtmlPreview) return
        const show = isEmailHtml()
        emailHtmlPreviewWrap.classList.toggle('d-none', !show)
        if(!show) return

        const html = getVariants('email_message')[0] || ''
        // Use srcdoc + sandbox to avoid executing scripts in admin context.
        emailHtmlPreview.srcdoc = String(html)
    }

    let availability = {
        email: { available: true, reason: null },
        sms: { available: true, reason: null },
        whatsapp: { available: true, reason: null },
    }

    const hideAlerts = () => {
        if(errorEl){ errorEl.classList.add('d-none'); errorEl.textContent = '' }
        if(successEl){ successEl.classList.add('d-none'); successEl.textContent = '' }
    }
    const showError = (msg) => {
        hideAlerts()
        if(errorEl){
            errorEl.innerHTML = String(msg || 'Não foi possível continuar.')
            errorEl.classList.remove('d-none')
        }
    }
    const showSuccess = (msg) => {
        hideAlerts()
        if(successEl){
            successEl.textContent = String(msg || 'OK.')
            successEl.classList.remove('d-none')
        }
    }

    let stepOrder = ['channels']
    let stepPos = 0

    const applyAvailabilityUi = () => {
        channelToggles.forEach((t) => {
            const ch = String(t.getAttribute('data-ops-wiz-channel') || '')
            const state = availability[ch]
            const ok = !!state?.available

            t.disabled = !ok
            if(t.disabled && t.checked) t.checked = false

            const label = t.closest('label')
            if(label){
                label.classList.toggle('disabled', t.disabled)
            }

            const r = reasonEls[ch]
            if(r){
                r.textContent = ok ? '' : ('(' + String(state?.reason || 'indisponível') + ')')
            }
        })
    }

    let availabilityTimer = null
    const refreshAvailability = async () => {
        if(selectedLiveCountEl) selectedLiveCountEl.textContent = selectedCountLabel()

        const sel = getSelection()
        const hasAnySelected = sel.mode === 'all_matching'
            ? (sel.selected_count === null ? true : sel.selected_count > 0)
            : Array.isArray(sel.ids) && sel.ids.length > 0

        if(!hasAnySelected){
            availability = {
                email: { available: false, reason: 'Selecione registros.' },
                sms: { available: false, reason: 'Selecione registros.' },
                whatsapp: { available: false, reason: 'Selecione registros.' },
            }
            applyAvailabilityUi()
            rebuildOrder()
            renderStep()
            return
        }

        try{
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            const res = await fetch(String(config.availabilityUrl || ''), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(selectionPayload()),
            })
            const data = await res.json().catch(() => null)
            if(!res.ok){
                availability = {
                    email: { available: true, reason: null },
                    sms: { available: true, reason: null },
                    whatsapp: { available: true, reason: null },
                }
                applyAvailabilityUi()
                return
            }
            availability = {
                email: { available: !!data?.channels?.email?.available, reason: data?.channels?.email?.reason || null },
                sms: { available: !!data?.channels?.sms?.available, reason: data?.channels?.sms?.reason || null },
                whatsapp: { available: !!data?.channels?.whatsapp?.available, reason: data?.channels?.whatsapp?.reason || null },
            }
            applyAvailabilityUi()
            rebuildOrder()
            renderStep()
        }catch(e){
            // If backend check fails, keep UI usable; backend still enforces on submit.
        }
    }

    const scheduleAvailabilityRefresh = () => {
        if(availabilityTimer) clearTimeout(availabilityTimer)
        availabilityTimer = setTimeout(() => {
            if(!modalEl.classList.contains('show')) return
            refreshAvailability()
        }, 150)
    }

    const titleForStep = (s) => {
        if(s === 'channels') return 'Canais'
        if(s === 'email') return 'E-mail'
        if(s === 'sms') return 'SMS'
        if(s === 'whatsapp') return 'Zap'
        if(s === 'review') return 'Revisão'
        return '—'
    }

    const rebuildOrder = () => {
        const channels = getSelectedChannels()
        stepOrder = ['channels', ...channels, 'review']
        stepPos = Math.min(stepPos, stepOrder.length - 1)
    }

    const renderStep = () => {
        hideAlerts()
        const current = stepOrder[stepPos] || 'channels'

        Object.entries(stepEls).forEach(([k, el]) => {
            if(!el) return
            el.classList.toggle('d-none', k !== current)
        })

        if(stepIndexEl) stepIndexEl.textContent = String(stepPos + 1)
        if(stepTotalEl) stepTotalEl.textContent = String(stepOrder.length)
        if(stepTitleEl) stepTitleEl.textContent = titleForStep(current)

        const isFirst = stepPos === 0
        const isLast = stepPos === stepOrder.length - 1
        if(backBtn) backBtn.disabled = isFirst
        if(nextBtn) nextBtn.classList.toggle('d-none', isLast)
        if(dispatchBtn) dispatchBtn.classList.toggle('d-none', !isLast)

        if(current === 'review'){
            const sel = getSelection()
            const channels = getSelectedChannels()
            const countEl = document.getElementById('opsWizSelectedCount')
            const channelsEl = document.getElementById('opsWizChannelsLabel')
            if(countEl) countEl.textContent = selectedCountLabel()
            if(channelsEl) channelsEl.textContent = channels.length ? channels.join(', ') : '—'
            // If total is known, show count consistent.
            if(selectedLiveCountEl){
                selectedLiveCountEl.textContent = selectedCountLabel()
            }
        }
    }

    const validateCurrent = () => {
        const current = stepOrder[stepPos] || 'channels'
        if(current === 'channels'){
            const sel = getSelection()
            const count = sel.mode === 'manual'
                ? (Array.isArray(sel.ids) ? sel.ids.length : 0)
                : (typeof sel.selected_count === 'number' ? sel.selected_count : 1)
            if(count < 1){
                showError('Selecione registros na tabela antes de disparar.')
                return false
            }
            const channels = getSelectedChannels()
            if(!channels.length){
                showError('Selecione pelo menos um canal.')
                return false
            }
            for(const ch of channels){
                if(!availability?.[ch]?.available){
                    const reason = availability?.[ch]?.reason ? ` (${availability[ch].reason})` : ''
                    showError(`Canal indisponível: ${ch}${reason}`)
                    return false
                }
            }
            rebuildOrder()
            return true
        }
        if(current === 'email'){
            const msgs = getVariants('email_message')
            if(!msgs.length){
                showError('Mensagem de e-mail é obrigatória.')
                return false
            }
        }
        if(current === 'sms'){
            const msgs = getVariants('sms_message')
            if(!msgs.length){
                showError('Mensagem de SMS é obrigatória.')
                return false
            }
        }
        if(current === 'whatsapp'){
            const msgs = getVariants('whatsapp_message')
            if(!msgs.length){
                showError('Mensagem de WhatsApp é obrigatória.')
                return false
            }
        }
        return true
    }

    backBtn?.addEventListener('click', () => {
        hideAlerts()
        stepPos = Math.max(0, stepPos - 1)
        renderStep()
    })
    nextBtn?.addEventListener('click', () => {
        if(!validateCurrent()) return
        stepPos = Math.min(stepOrder.length - 1, stepPos + 1)
        renderStep()
    })

    dispatchBtn?.addEventListener('click', async () => {
        const sel = getSelection()
        const channels = getSelectedChannels()
        const count = sel.mode === 'manual'
            ? (Array.isArray(sel.ids) ? sel.ids.length : 0)
            : (typeof sel.selected_count === 'number' ? sel.selected_count : 1)
        if(count < 1){ showError('Selecione registros na tabela antes de disparar.'); return }
        if(!channels.length){ showError('Selecione pelo menos um canal.'); return }

        const payload = {
            ...selectionPayload(),
            channels,
            email_from_name: String(document.getElementById('opsWizEmailFromName')?.value || ''),
            email_from_email: String(document.getElementById('opsWizEmailFromEmail')?.value || ''),
            email_reply_to: String(document.getElementById('opsWizEmailReplyTo')?.value || ''),
            email_format: isEmailHtml() ? 'html' : 'text',
            email_subject_variants: getVariants('email_subject'),
            email_message_variants: getVariants('email_message'),

            sms_message_variants: getVariants('sms_message'),
            whatsapp_message_variants: getVariants('whatsapp_message'),
        }

        if(dispatchBtn){ dispatchBtn.disabled = true; dispatchBtn.textContent = 'Disparando...' }
        try{
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            const res = await fetch(String(config.dispatchUrl || ''), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            })
            const data = await res.json().catch(() => null)
            if(!res.ok){
                showError(data?.message || 'Falha ao disparar.')
                return
            }
            showSuccess(`Disparo enfileirado. Flow #${data?.flow_id} | Run #${data?.run_id}`)
        }catch(e){
            showError('Erro de conexão. Tente novamente.')
        }finally{
            if(dispatchBtn){ dispatchBtn.disabled = false; dispatchBtn.textContent = 'Disparar' }
        }
    })

    channelToggles.forEach((t) => t.addEventListener('change', () => {
        if(stepOrder[stepPos] !== 'channels') return
        rebuildOrder()
        renderStep()
    }))

    modalEl.addEventListener('shown.bs.modal', () => {
        stepOrder = ['channels']
        stepPos = 0
        hideAlerts()
        renderStep()
        scheduleAvailabilityRefresh()
        refreshEmailHtmlPreview()
    })

    window.addEventListener('explore:selection:changed', () => {
        scheduleAvailabilityRefresh()
        if(selectedLiveCountEl){
            selectedLiveCountEl.textContent = selectedCountLabel()
        }
    })

    // UI actions: add variants + "IA" draft button
    modalEl.querySelectorAll('[data-ops-wiz-add-variant]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = String(btn.getAttribute('data-ops-wiz-add-variant') || '')
            if(!key) return
            addVariant(key)
        })
    })

    modalEl.querySelectorAll('[data-ops-wiz-ai]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = String(btn.getAttribute('data-ops-wiz-ai') || '')
            if(!key) return
            const draft = generateDraft(key)
            fillFirstVariant(key, draft)
        })
    })

    emailFormatText?.addEventListener('change', refreshEmailHtmlPreview)
    emailFormatHtml?.addEventListener('change', refreshEmailHtmlPreview)
    variantContainer('email_message')?.addEventListener('input', () => {
        refreshEmailHtmlPreview()
    })
}
