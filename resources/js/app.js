/* =========================================================
 * PIXIP CORE — GLOBAL BOOTSTRAP (FINAL ENTERPRISE)
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

    console.log('⚡ PIXIP ready')

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
            el.classList.add('pixip-modal-premium')
        })
    }

    applyPremiumClass(document)

    if(!window.MutationObserver) return
    const observer = new MutationObserver((mutations)=>{
        for(const mutation of mutations){
            mutation.addedNodes.forEach((node)=>{
                if(!(node instanceof Element)) return
                if(node.matches?.('.modal .modal-content')){
                    node.classList.add('pixip-modal-premium')
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
    const topbar = document.querySelector('.pixip-topbar')
    const userBtn = document.querySelector('[data-user-toggle]')
    const userMenu = document.querySelector('[data-user-menu]')
    const userThemeToggle = document.getElementById('userThemeToggle')
    const userConfigPanelToggle = document.getElementById('userConfigPanelToggle')
    const userLanguageSelect = document.getElementById('userLanguageSelect')
    const configRail = document.getElementById('pixipConfigRail')
    const THEME_STORAGE_KEY = 'pixip.theme.override'
    const CONFIG_RAIL_STORAGE_KEY = 'pixip.config.rail.open'
    const LANG_STORAGE_KEY = 'pixip.language'

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
        userBtn.addEventListener('click', (e)=>{
            e.stopPropagation()
            const open = userMenu.classList.toggle('is-open')
            userBtn.setAttribute('aria-expanded', open ? 'true' : 'false')
        })

        userMenu.addEventListener('click', (e)=>{
            e.stopPropagation()
        })

        document.addEventListener('click', ()=>{
            userMenu.classList.remove('is-open')
            userBtn.setAttribute('aria-expanded', 'false')
        })
    }

    if(userThemeToggle){
        const userThemeToggleText = userThemeToggle.querySelector('.pixip-user-switch-text')
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
    const stored = localStorage.getItem('pixip.theme.override')
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
