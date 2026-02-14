/* =========================================================
 * Grade CORE — GLOBAL BOOTSTRAP (FINAL ENTERPRISE)
 * ========================================================= */

import * as bootstrap from 'bootstrap'
window.bootstrap = bootstrap

import initSidebar from './vault/sidebar'
import initSources from './vault/sources'


/* =========================================================
 * BOOT
 * ========================================================= */

/*
|--------------------------------------------------------------------------
| IMPORTANTE:
| Não confiar só em DOMContentLoaded.
| Vite pode carregar depois do DOM pronto.
|--------------------------------------------------------------------------
*/

boot()


async function boot(){

    console.log('⚡ Grade ready')

    /* =====================================================
       GLOBAL
    ===================================================== */

    initSidebar()

    initTheme()
    initTopbar()
    initPremiumModals()


    /* =====================================================
       DISPATCHER (lazy loader por página)
    ===================================================== */

    const has = id => document.getElementById(id)


    /* -----------------------------------------
       SOURCES
    ----------------------------------------- */
    if (has('sourcesBody')) {
        initSources()
        return
    }


    /* -----------------------------------------
       EXPLORE
    ----------------------------------------- */
    if (has('leadsBody')) {

        // lazy import (code splitting)
        const { default: initExplore } = await import('./vault/explore')

        initExplore()
        return
    }


    /* -----------------------------------------
       SEMANTIC (future)
    ----------------------------------------- */
    if (has('semanticModal')) {

        const { default: initSemantic } = await import('./vault/semantic')

        initSemantic()
        return
    }

    /* -----------------------------------------
       AUTOMATION
    ----------------------------------------- */
    if (has('automationPage')) {
        const { default: initAutomation } = await import('./vault/automation')
        initAutomation()
        return
    }

    /* -----------------------------------------
       SEMANTIC TAXONOMY (admin)
    ----------------------------------------- */
    if (has('semanticTaxonomyPage')) {
        const { default: initSemanticTaxonomy } = await import('./vault/semantic-taxonomy')
        initSemanticTaxonomy()
        return
    }

    /* -----------------------------------------
       COLUMNS ADMIN
    ----------------------------------------- */
    if (has('columnsAdminPage')) {
        const { default: initColumnsAdminPage } = await import('./admin/columns')
        initColumnsAdminPage()
        return
    }

    if (has('dataQualityPage')) {
        const { default: initDataQualityPage } = await import('./admin/data-quality')
        initDataQualityPage()
        return
    }

    /* -----------------------------------------
       MONITORING ADMIN
    ----------------------------------------- */
    if (has('monitoringPage')) {
        const { default: initMonitoringPage } = await import('./admin/monitoring')
        initMonitoringPage()
        return
    }

    /* -----------------------------------------
       SECURITY ACCESS (admin)
    ----------------------------------------- */
    if (has('securityAccessPage')) {
        const { default: initSecurityAccessPage } = await import('./admin/security-access')
        initSecurityAccessPage()
        return
    }

    initProfile()
    initPlanAdmin()

    /* -----------------------------------------
       FALLBACK
    ----------------------------------------- */
    if (import.meta?.env?.DEV) {
        console.log('📄 Página sem módulo específico')
    }
}

function initPremiumModals(){
    const applyPremiumClass = (root = document)=>{
        root.querySelectorAll?.('.modal .modal-content')?.forEach((el)=>{
            el.classList.add('grade-modal-premium')
        })
    }

    applyPremiumClass(document)

    if(!window.MutationObserver) return
    const observer = new MutationObserver((mutations)=>{
        for(const mutation of mutations){
            mutation.addedNodes.forEach((node)=>{
                if(!(node instanceof Element)) return
                if(node.matches?.('.modal .modal-content')){
                    node.classList.add('grade-modal-premium')
                }
                if(node.matches?.('.modal')){
                    applyPremiumClass(node)
                }else{
                    applyPremiumClass(node)
                }
            })
        }
    })

    observer.observe(document.body, { childList: true, subtree: true })
}

function initTopbar(){
    const topbar = document.querySelector('.grade-topbar')
    const userBtn = document.querySelector('[data-user-toggle]')
    const userMenu = document.querySelector('[data-user-menu]')
    const userThemeToggle = document.getElementById('userThemeToggle')
    const userConfigPanelToggle = document.getElementById('userConfigPanelToggle')
    const userLanguageSelect = document.getElementById('userLanguageSelect')
    const configRail = document.getElementById('gradeConfigRail')
    const THEME_STORAGE_KEY = 'grade.theme.override'
    const CONFIG_RAIL_STORAGE_KEY = 'grade.config.rail.open'
    const LANG_STORAGE_KEY = 'grade.language'

	    const initSettingsModal = ()=>{
	        const modalEl = document.getElementById('gradeSettingsModal')
	        if(!modalEl) return

	        const navItems = Array.from(modalEl.querySelectorAll('[data-settings-tab]'))
	        const panels = Array.from(modalEl.querySelectorAll('[data-settings-panel]'))
	        if(!navItems.length || !panels.length) return

	        const setActive = (tab)=>{
	            navItems.forEach((btn)=>btn.classList.toggle('is-active', btn.getAttribute('data-settings-tab') === tab))
	            panels.forEach((p)=>p.classList.toggle('is-active', p.getAttribute('data-settings-panel') === tab))
	        }

        navItems.forEach((btn)=>{
            btn.addEventListener('click', ()=>{
                setActive(btn.getAttribute('data-settings-tab'))
            })
        })

	        modalEl.addEventListener('shown.bs.modal', ()=>{
	            // Default to General, but allow external anchors to open a specific tab.
	            const desired = modalEl.dataset?.openTab || 'general'
	            setActive(desired)
	            if(modalEl.dataset) delete modalEl.dataset.openTab
	        })

	        // Anchors outside the modal can request a specific tab before opening.
	        document.addEventListener('click', (event)=>{
	            const btn = event.target.closest?.('[data-settings-open-tab]')
	            if(!btn) return
	            const tab = btn.getAttribute('data-settings-open-tab') || 'general'
	            modalEl.dataset.openTab = tab
	        })

	        modalEl.querySelectorAll('[data-settings-open-modal]').forEach((btn)=>{
	            btn.addEventListener('click', ()=>{
	                const target = btn.getAttribute('data-settings-open-modal')
	                if(!target || !window.bootstrap?.Modal) return
	                const targetEl = document.querySelector(target)
                if(!targetEl) return
                // Close settings first to avoid stacked modals.
                window.bootstrap.Modal.getOrCreateInstance(modalEl).hide()
                setTimeout(()=>{
                    window.bootstrap.Modal.getOrCreateInstance(targetEl).show()
                }, 250)
            })
        })
	    }

	    const initEditProfileModal = ()=>{
	        const modalEl = document.getElementById('editProfileModal')
	        if(!modalEl) return

	        const form = modalEl.querySelector('#editProfileForm')
	        const btn = modalEl.querySelector('#editProfileAvatarBtn')
	        const input = modalEl.querySelector('#editProfileAvatarInput')
	        const preview = modalEl.querySelector('#editProfileAvatarPreview')
	        const submitBtn = modalEl.querySelector('#editProfileSubmitBtn')
	        if(!btn || !input || !preview || !form || !submitBtn) return

	        const renderAvatarNode = (container, avatarUrl, initials)=>{
	            if(!container) return
	            if(avatarUrl){
	                container.innerHTML = ''
	                const img = document.createElement('img')
	                img.src = avatarUrl
	                img.alt = ''
	                container.appendChild(img)
	                container.classList.add('avatar-has-image')
	                return
	            }
	            container.classList.remove('avatar-has-image')
	            container.textContent = initials || 'U'
	        }

	        const avatarContainers = ()=> Array.from(document.querySelectorAll('.grade-user-avatar, .grade-user-profile-avatar, .grade-edit-profile-avatar'))

	        const setAvatarRingColor = (color)=>{
	            avatarContainers().forEach((el)=>{
	                if(color){
	                    el.style.setProperty('--avatar-ring-color', color)
	                }else{
	                    el.style.removeProperty('--avatar-ring-color')
	                }
	            })
	        }

	        const syncAvatarImageClasses = ()=>{
	            avatarContainers().forEach((el)=>{
	                const hasImage = !!el.querySelector('img')
	                el.classList.toggle('avatar-has-image', hasImage)
	            })
	        }

	        const pickDominantColor = (img)=>{
	            try{
	                const w = 18
	                const h = 18
	                const canvas = document.createElement('canvas')
	                canvas.width = w
	                canvas.height = h
	                const ctx = canvas.getContext('2d', { willReadFrequently: true })
	                if(!ctx) return null
	                ctx.drawImage(img, 0, 0, w, h)
	                const { data } = ctx.getImageData(0, 0, w, h)

	                let r = 0, g = 0, b = 0, c = 0
	                for(let i = 0; i < data.length; i += 4){
	                    const a = data[i + 3]
	                    if(a < 30) continue
	                    r += data[i]
	                    g += data[i + 1]
	                    b += data[i + 2]
	                    c++
	                }
	                if(!c) return null

	                r = Math.round(r / c)
	                g = Math.round(g / c)
	                b = Math.round(b / c)

	                // Boost color a bit so the ring remains visible/minimal.
	                const gray = Math.round((r + g + b) / 3)
	                r = Math.max(0, Math.min(255, Math.round(r + (r - gray) * 0.35)))
	                g = Math.max(0, Math.min(255, Math.round(g + (g - gray) * 0.35)))
	                b = Math.max(0, Math.min(255, Math.round(b + (b - gray) * 0.35)))

	                return `rgb(${r}, ${g}, ${b})`
	            }catch(_e){
	                return null
	            }
	        }

	        const applyRingFromImage = (img)=>{
	            if(!img) return
	            const apply = ()=>{
	                const color = pickDominantColor(img)
	                if(color) setAvatarRingColor(color)
	            }
	            if(img.complete){
	                apply()
	            }else{
	                img.addEventListener('load', apply, { once: true })
	            }
	        }

	        btn.addEventListener('click', ()=> input.click())
	        preview.addEventListener('click', ()=> input.click())
	        syncAvatarImageClasses()

	        input.addEventListener('change', ()=>{
	            const file = input.files?.[0]
	            if(!file) return
	            if(!file.type?.startsWith('image/')) return

	            const url = URL.createObjectURL(file)
	            preview.innerHTML = ''
	            const img = document.createElement('img')
	            img.src = url
	            img.alt = ''
	            preview.appendChild(img)
	            applyRingFromImage(img)
	        })

	        // On open, sync ring color with current avatar.
	        modalEl.addEventListener('shown.bs.modal', ()=>{
	            const img = preview.querySelector('img')
	                || document.querySelector('.grade-user-profile-avatar img')
	                || document.querySelector('.grade-user-avatar img')
	            applyRingFromImage(img)
	        })

	        form.addEventListener('submit', async (event)=>{
	            event.preventDefault()

	            const fd = new FormData(form)
	            submitBtn.disabled = true
	            submitBtn.classList.add('is-submitting')
	            let saved = false

	            try{
	                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
	                const res = await fetch(form.action, {
	                    method: 'POST',
	                    headers: {
	                        'X-Requested-With': 'XMLHttpRequest',
	                        'Accept': 'application/json',
	                        'X-CSRF-TOKEN': csrf
	                    },
	                    body: fd
	                })

	                if(!res.ok){
	                    const payload = await res.json().catch(()=>null)
	                    return
	                }

	                const payload = await res.json()
	                const user = payload?.user || {}
	                const name = user?.name || ''
	                const handle = user?.handle || ''
	                const avatarUrl = user?.avatar_url || ''
	                const initials = user?.initials || 'U'

	                document.querySelectorAll('.grade-user-profile-name').forEach((el)=>{ el.textContent = name })
	                document.querySelectorAll('.grade-user-profile-handle').forEach((el)=>{ el.textContent = handle })
	                document.querySelectorAll('.grade-user-avatar').forEach((el)=> renderAvatarNode(el, avatarUrl, initials))
	                document.querySelectorAll('.grade-user-profile-avatar').forEach((el)=> renderAvatarNode(el, avatarUrl, initials))
	                renderAvatarNode(preview, avatarUrl, initials)
	                const mainImg = preview.querySelector('img')
	                    || document.querySelector('.grade-user-profile-avatar img')
	                    || document.querySelector('.grade-user-avatar img')
	                syncAvatarImageClasses()
	                applyRingFromImage(mainImg)
	                saved = true

	                if(window.bootstrap?.Modal){
	                    setTimeout(()=>{
	                        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide()
	                        submitBtn.disabled = false
	                        submitBtn.classList.remove('is-submitting')
	                    }, 2000)
	                }
	            }catch(_e){
	            } finally{
	                if(!saved){
	                    submitBtn.disabled = false
	                    submitBtn.classList.remove('is-submitting')
	                }
	            }
	        })
	    }

	    const initBrandModal = ()=>{
	        const modalEl = document.getElementById('gradeBrandModal')
	        if(!modalEl || !window.bootstrap?.Modal) return
	        const BRAND_SEEN_KEY = 'grade.brand.modal.seen'
	        const links = Array.from(document.querySelectorAll('[data-brand-link]'))
	        if(!links.length) return
	        let singleClickTimer = null

	        const openModal = ()=>{
	            window.bootstrap.Modal.getOrCreateInstance(modalEl).show()
	            try { localStorage.setItem(BRAND_SEEN_KEY, '1') } catch(_e) {}
	        }

	        const isHome = ()=> window.location.pathname === '/'

	        const goHome = (withBrand = false)=>{
	            const url = withBrand ? '/?brand=1' : '/'
	            window.location.href = url
	        }

	        const hasSeen = ()=>{
	            try { return localStorage.getItem(BRAND_SEEN_KEY) === '1' } catch(_e) { return false }
	        }

	        const openOrRedirectModal = ()=>{
	            if(isHome()){
	                openModal()
	            }else{
	                goHome(true)
	            }
	        }

	        const params = new URLSearchParams(window.location.search || '')
	        if(params.get('brand') === '1'){
	            setTimeout(openModal, 80)
	            params.delete('brand')
	            const qs = params.toString()
	            const cleanUrl = `${window.location.pathname}${qs ? `?${qs}` : ''}${window.location.hash || ''}`
	            window.history.replaceState({}, '', cleanUrl)
	        }

	        links.forEach((link)=>{
	            link.addEventListener('click', (event)=>{
	                event.preventDefault()
	                const seen = hasSeen()

	                // First ever click opens modal.
	                if(!seen){
	                    openOrRedirectModal()
	                    return
	                }

	                // Single click after first time only redirects.
	                clearTimeout(singleClickTimer)
	                singleClickTimer = setTimeout(()=>{
	                    goHome(false)
	                }, 320)
	            })

	            link.addEventListener('dblclick', (event)=>{
	                event.preventDefault()
	                clearTimeout(singleClickTimer)
	                openOrRedirectModal()
	            })
	        })
	    }

	    initSettingsModal()
	    initEditProfileModal()
	    initBrandModal()

	    const syncConfigRailOffset = ()=>{
	        if(!topbar) return
	        const rect = topbar.getBoundingClientRect()
        const top = Math.max(0, Math.ceil(rect.bottom + 10))
        document.body?.style.setProperty('--config-rail-top', `${top}px`)
    }

    if(topbar){
        const onScroll = ()=>{
            topbar.classList.toggle('is-scrolled', window.scrollY > 4)
            syncConfigRailOffset()
        }
        onScroll()
        window.addEventListener('scroll', onScroll, { passive: true })
        window.addEventListener('resize', syncConfigRailOffset, { passive: true })
        window.addEventListener('load', syncConfigRailOffset, { passive: true })
        if(window.ResizeObserver){
            const observer = new ResizeObserver(()=>{
                syncConfigRailOffset()
            })
            observer.observe(topbar)
        }
    }

    if(userBtn && userMenu){
        const closeUserMenu = ()=>{
            userMenu.classList.remove('is-open')
            userBtn.setAttribute('aria-expanded', 'false')
        }

        userBtn.addEventListener('click', (e)=>{
            e.stopPropagation()
            const open = userMenu.classList.toggle('is-open')
            userBtn.setAttribute('aria-expanded', open ? 'true' : 'false')
        })

        userMenu.addEventListener('click', (e)=>{
            e.stopPropagation()
        })

        document.addEventListener('click', ()=>{
            closeUserMenu()
        })

        const logoutConfirmModal = document.getElementById('logoutConfirmModal')
        if(logoutConfirmModal){
            logoutConfirmModal.addEventListener('show.bs.modal', closeUserMenu)
        }
    }

    if(userThemeToggle){
        const userThemeToggleText = userThemeToggle.querySelector('.grade-user-switch-text')
        const syncThemeToggle = ()=>{
            const isDark = document.body?.getAttribute('data-theme') === 'dark'
            userThemeToggle.classList.toggle('is-on', isDark)
            if(userThemeToggleText){
                userThemeToggleText.textContent = isDark ? 'On' : 'Off'
            }
            userThemeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false')
        }

        syncThemeToggle()

        userThemeToggle.addEventListener('click', ()=>{
            const currentDark = document.body?.getAttribute('data-theme') === 'dark'
            const next = currentDark ? 'light' : 'dark'
            document.body?.setAttribute('data-theme', next)
            localStorage.setItem(THEME_STORAGE_KEY, next)
            syncThemeToggle()
        })
    }

    if(userLanguageSelect){
        const savedLang = localStorage.getItem(LANG_STORAGE_KEY) || 'pt-BR'
        userLanguageSelect.value = savedLang
        userLanguageSelect.addEventListener('change', ()=>{
            localStorage.setItem(LANG_STORAGE_KEY, userLanguageSelect.value || 'pt-BR')
        })
    }

    const hasConfigRail = document.body?.dataset.configRailAvailable === '1' && configRail
    if(userConfigPanelToggle){
        const syncConfigRail = ()=>{
            const stored = localStorage.getItem(CONFIG_RAIL_STORAGE_KEY)
            const isEnabled = hasConfigRail ? stored === '1' : false
            document.body?.classList.toggle('config-rail-enabled', isEnabled)
            userConfigPanelToggle.classList.toggle('is-on', isEnabled)
            userConfigPanelToggle.setAttribute('aria-pressed', isEnabled ? 'true' : 'false')
            userConfigPanelToggle.disabled = !hasConfigRail
        }

        syncConfigRail()

        userConfigPanelToggle.addEventListener('click', ()=>{
            if(!hasConfigRail) return
            const isEnabled = document.body?.classList.contains('config-rail-enabled')
            localStorage.setItem(CONFIG_RAIL_STORAGE_KEY, isEnabled ? '0' : '1')
            syncConfigRail()
        })
    }

    if(configRail){
        let activeRailButtonTimer = null
        let activeRailPressTimer = null
        let activeRailReleaseTimer = null
        const clickTarget = (id)=>{
            const el = document.getElementById(id)
            if(el){
                el.click()
            }
        }
        const openSearchModal = ()=>{
            const trigger = document.getElementById('openSearchModalBtn')
            if(trigger){
                trigger.click()
                return
            }
            const modalEl = document.getElementById('exploreSearchModal')
            if(modalEl && window.bootstrap?.Modal){
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show()
            }
        }
        const openExecuteAction = ()=>{
            const trigger = document.getElementById('executeBtn')
            if(trigger){
                trigger.click()
                return
            }
            const modalEl = document.getElementById('actionsManualWizardModal')
            if(modalEl && window.bootstrap?.Modal){
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show()
            }
        }
        const triggerFirstAvailable = (ids = [])=>{
            const id = (ids || []).find((candidate)=>{
                const el = document.getElementById(candidate)
                return !!el && !el.disabled
            })
            if(id){
                clickTarget(id)
            }
        }
        configRail.querySelectorAll('[data-config-rail-action]').forEach((btn)=>{
            btn.addEventListener('click', ()=>{
                configRail.querySelectorAll('[data-config-rail-action]').forEach((item)=>{
                    item.classList.remove('is-active')
                    item.classList.remove('is-pressing')
                    item.classList.remove('is-releasing')
                })

                btn.classList.add('is-active')
                btn.classList.add('is-pressing')

                if(activeRailPressTimer){
                    clearTimeout(activeRailPressTimer)
                }
                activeRailPressTimer = setTimeout(()=>{
                    btn.classList.remove('is-pressing')
                }, 220)

                if(activeRailButtonTimer){
                    clearTimeout(activeRailButtonTimer)
                }
                activeRailButtonTimer = setTimeout(()=>{
                    btn.classList.remove('is-active')
                    btn.classList.remove('is-pressing')
                    btn.classList.add('is-releasing')
                    if(activeRailReleaseTimer){
                        clearTimeout(activeRailReleaseTimer)
                    }
                    activeRailReleaseTimer = setTimeout(()=>{
                        btn.classList.remove('is-releasing')
                    }, 240)
                }, 1000)

                const action = btn.getAttribute('data-config-rail-action')
                if(action === 'search'){
                    openSearchModal()
                    return
                }
                if(action === 'clear'){
                    triggerFirstAvailable(['clearFiltersBtn', 'clearFiltersBtnMobile'])
                    return
                }
                if(action === 'columns'){
                    clickTarget('columnsBtn')
                    return
                }
                if(action === 'data'){
                    clickTarget('dataQualityBtn')
                    return
                }
                if(action === 'semantic'){
                    clickTarget('semanticBtn')
                    return
                }
                if(action === 'import'){
                    clickTarget('openExploreImportBtn')
                    return
                }
                if(action === 'execute'){
                    openExecuteAction()
                    return
                }
                if(action === 'export'){
                    clickTarget('exportBtn')
                    return
                }
                if(action === 'save'){
                    triggerFirstAvailable(['publishOverridesBtn', 'publishOverridesBtnMobile', 'saveMenuBtn'])
                }
            })
        })
    }

    document.querySelectorAll('[data-user-action]').forEach((btn)=>{
        btn.addEventListener('click', ()=>{
            const action = btn.getAttribute('data-user-action')
            if(action === 'help'){
                window.open('https://help.openai.com', '_blank', 'noopener')
                return
            }
            if(action === 'news'){
                window.open('https://openai.com/news', '_blank', 'noopener')
                return
            }
            if(action === 'shortcuts'){
                window.alert('Atalhos: "/" buscar, Enter confirmar, Esc fechar modais.')
                return
            }
            if(action === 'desktop'){
                window.open('https://openai.com/chatgpt/desktop/', '_blank', 'noopener')
            }
        })
    })
}

function initTheme(){
    const body = document.body
    const stored = localStorage.getItem('grade.theme.override')
    const pref = body?.getAttribute('data-theme-pref') || 'system'

    const apply = (mode)=>{
        body?.setAttribute('data-theme', mode)
    }

    const resolveSystem = ()=>{
        // Força "system" como claro por padrão.
        apply('light')
    }

    if(stored === 'light' || stored === 'dark'){
        apply(stored)
    }else if(pref === 'system'){
        resolveSystem()
    }else{
        apply(pref)
    }
}

function initProfile(){
    const buttons = document.querySelectorAll('[data-slug]')
    if(!buttons.length) return

    buttons.forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
            const slug = btn.getAttribute('data-slug') || ''
            if(!slug) return

            try{
                await navigator.clipboard.writeText(slug)
                btn.classList.add('is-copied')
                const original = btn.textContent
                btn.textContent = 'Nome de usuário copiado'
                setTimeout(()=>{
                    btn.classList.remove('is-copied')
                    btn.textContent = original
                }, 1600)
            }catch(e){
                // fallback simples
                const input = document.createElement('input')
                input.value = slug
                document.body.appendChild(input)
                input.select()
                document.execCommand('copy')
                document.body.removeChild(input)
            }
        })
    })
}

function initPlanAdmin(){
    const modalEl = document.getElementById('downgradeModal')
    if(!modalEl) return

    const modal = new bootstrap.Modal(modalEl)
    const nameEl = document.getElementById('downgradeTenantName')
    const confirmBtn = document.getElementById('confirmDowngradeBtn')
    let pendingForm = null

    document.querySelectorAll('.plan-form').forEach((form)=>{
        form.addEventListener('submit', (e)=>{
            const select = form.querySelector('select[name="plan"]')
            const current = form.querySelector('input[name="current_plan"]')?.value
            const next = select?.value
            const isDowngrade = (current === 'starter' || current === 'pro') && next === 'free'
            if(isDowngrade){
                e.preventDefault()
                pendingForm = form
                if(nameEl) nameEl.textContent = form.dataset.tenant || 'Conta'
                modal.show()
            }
        })
    })

    confirmBtn?.addEventListener('click', ()=>{
        if(!pendingForm) return
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = 'confirm_downgrade'
        input.value = '1'
        pendingForm.appendChild(input)
        pendingForm.submit()
    })
}
