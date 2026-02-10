export function createLoadingController(options){
    const { loadingEl, wrap } = options || {}

    return function setLoading(state){
        if(!loadingEl) return
        loadingEl.classList.toggle('d-none', !state)
        loadingEl.setAttribute('aria-hidden', state ? 'false' : 'true')
        if(wrap){
            wrap.setAttribute('aria-busy', state ? 'true' : 'false')
        }
    }
}
