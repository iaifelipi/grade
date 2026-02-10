/* =========================================================
 * Grade — Top Menu Controller
 * ========================================================= */

let initialized = false

export default function initSidebar() {

    if (initialized) return
    initialized = true

    const menu = document.querySelector('[data-menu-panel]')
    const toggleBtns = document.querySelectorAll('[data-menu-toggle]')
    if (!menu) return

    const links = menu.querySelectorAll('a')

    const isMobile = () => window.innerWidth < 992
    const storageKey = 'grade.menu.open'

    function toggle() {
        if (!isMobile()) return
        menu.classList.toggle('is-open')
        const isOpen = menu.classList.contains('is-open')
        localStorage.setItem(storageKey, isOpen ? '1' : '0')
        toggleBtns.forEach(btn=>{
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false')
        })
    }

    /* ================= listeners ================= */

    toggleBtns.forEach(b => b.addEventListener('click', toggle))

    /* fechar ao clicar link no mobile */
    links.forEach(a =>
        a.addEventListener('click', () => {
            if (isMobile()) menu.classList.remove('is-open')
        })
    )

    /* fechar com ESC */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') menu.classList.remove('is-open')
    })

    /* desktop → garante fechado */
    window.addEventListener('resize', () => {
        if (!isMobile()) {
            menu.classList.remove('is-open')
            toggleBtns.forEach(btn=>btn.setAttribute('aria-expanded','false'))
        }
    })

    if (isMobile()) {
        const saved = localStorage.getItem(storageKey)
        if (saved === '1') {
            menu.classList.add('is-open')
            toggleBtns.forEach(btn=>btn.setAttribute('aria-expanded','true'))
        }
    }
}
