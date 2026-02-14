const toast = (message)=>{
    try {
        window.bootstrap?.Toast?.getOrCreateInstance(document.getElementById('securityAccessToast'))?.show()
    } catch(_e) {}
    if (message) {
        // eslint-disable-next-line no-alert
        window.alert(message)
    }
}

async function jsonFetch(url, options = {}){
    const res = await fetch(url, {
        ...options,
        headers:{
            'X-Requested-With':'XMLHttpRequest',
            ...(options.headers || {})
        },
        cache:'no-store'
    })
    return { res, json: await res.json().catch(()=>null) }
}

function incidentLevelBadge(level){
    const v = String(level || 'info')
    if (v === 'critical') return '<span class="badge text-bg-danger">crítico</span>'
    if (v === 'warning') return '<span class="badge text-bg-warning">warning</span>'
    return '<span class="badge text-bg-secondary">info</span>'
}

export default function initSecurityAccessPage(){
    const page = document.getElementById('securityAccessPage')
    if(!page) return

    const healthUrl = String(page.dataset.healthUrl || '').trim()
    const ingestUrl = String(page.dataset.ingestUrl || '').trim()
    const evalUrl = String(page.dataset.evaluateUrl || '').trim()
    const blockIpUrl = String(page.dataset.blockIpUrl || '').trim()
    const challengeIpUrl = String(page.dataset.challengeIpUrl || '').trim()
    const unblockIpUrl = String(page.dataset.unblockIpUrl || '').trim()
    const ackUrlTemplate = String(page.dataset.incidentsAckUrlTemplate || '').trim()

    const checkedAtEl = document.getElementById('securityAccessCheckedAt')
    const alertEl = document.getElementById('securityAccessAlert')
    const refreshBtn = document.getElementById('securityAccessRefreshBtn')
    const ingestBtn = document.getElementById('securityAccessIngestBtn')
    const evaluateBtn = document.getElementById('securityAccessEvaluateBtn')

    const eventsTotalEl = document.getElementById('securityAccessEventsTotal')
    const windowEl = document.getElementById('securityAccessWindow')
    const failedLoginsEl = document.getElementById('securityAccessFailedLogins')
    const blockedIpsEl = document.getElementById('securityAccessBlockedIps')
    const openIncidentsEl = document.getElementById('securityAccessOpenIncidents')
    const topIpsTableEl = document.getElementById('securityAccessTopIpsTable')
    const incidentsTableEl = document.getElementById('securityAccessIncidentsTable')
    const attachIpActions = (rootEl)=>{
        if(!rootEl) return
        rootEl.addEventListener('click', async (event)=>{
            const target = event.target
            if(!(target instanceof Element)) return
            const ipBtn = target.closest('[data-ip-action]')
            if(!ipBtn) return
            const action = String(ipBtn.getAttribute('data-ip-action') || '')
            const ip = String(ipBtn.getAttribute('data-ip') || '').trim()
            if(!ip) return
            const url = action === 'block' ? blockIpUrl : (action === 'challenge' ? challengeIpUrl : unblockIpUrl)
            if(!url) return
            ipBtn.setAttribute('disabled', 'disabled')
            try{
                const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
                const { res, json } = await jsonFetch(url, {
                    method:'POST',
                    headers:{ 'X-CSRF-TOKEN': token, 'Content-Type':'application/json' },
                    body: JSON.stringify({ ip })
                })
                if(!res.ok){
                    toast(json?.message || `Falha na ação: HTTP ${res.status}`)
                    return
                }
                toast(json?.message || 'Ação executada.')
            }finally{
                ipBtn.removeAttribute('disabled')
                refresh()
            }
        })
    }

    const setAlert = (msg, level = 'info')=>{
        if(!alertEl) return
        if(!msg){
            alertEl.classList.add('d-none')
            alertEl.textContent = ''
            return
        }
        alertEl.classList.remove('d-none')
        alertEl.classList.remove('alert-danger','alert-warning','alert-info')
        alertEl.classList.add(level === 'danger' ? 'alert-danger' : (level === 'warning' ? 'alert-warning' : 'alert-info'))
        alertEl.textContent = String(msg)
    }

    const render = (payload)=>{
        const checkedAt = payload?.checked_at || null
        if(checkedAtEl) checkedAtEl.textContent = checkedAt ? new Date(checkedAt).toLocaleString() : '—'
        const kpis = payload?.kpis || {}
        if(eventsTotalEl) eventsTotalEl.textContent = String(kpis.events_total ?? 0)
        if(windowEl) windowEl.textContent = `janela: ${kpis.window_minutes ?? 15} min`
        if(failedLoginsEl) failedLoginsEl.textContent = String(kpis.failed_logins ?? 0)
        if(blockedIpsEl) blockedIpsEl.textContent = String(kpis.blocked_ips_24h ?? 0)
        if(openIncidentsEl) openIncidentsEl.textContent = String(kpis.open_incidents ?? 0)

        const topIps = Array.isArray(payload?.top_ips) ? payload.top_ips : []
        if(topIpsTableEl){
            topIpsTableEl.innerHTML = topIps.length
                ? topIps.map(row=>{
                    const ip = String(row.ip || '-')
                    const btns = (blockIpUrl && challengeIpUrl && unblockIpUrl && ip !== '-')
                        ? `<div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-danger" data-ip-action="block" data-ip="${ip}">Block</button>
                            <button type="button" class="btn btn-outline-warning" data-ip-action="challenge" data-ip="${ip}">Challenge</button>
                            <button type="button" class="btn btn-outline-secondary" data-ip-action="unblock" data-ip="${ip}">Unblock</button>
                           </div>`
                        : ''
                    return `<tr>
                        <td><code>${ip}</code></td>
                        <td class="text-end">${Number(row.total || 0)}</td>
                        <td class="text-end">${btns}</td>
                    </tr>`
                }).join('')
                : '<tr><td colspan="3" class="text-muted">Sem dados.</td></tr>'
        }

        const incidents = Array.isArray(payload?.incidents) ? payload.incidents : []
        if(incidentsTableEl){
            incidentsTableEl.innerHTML = incidents.length
                ? incidents.map(row=>{
                    const id = Number(row.id || 0)
                    const canAck = String(row.status || '') === 'open'
                    const btn = canAck && id > 0
                        ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-ack-id="${id}">ACK</button>`
                        : ''
                    return `<tr>
                        <td>#${id || '—'}</td>
                        <td>${incidentLevelBadge(row.level)}</td>
                        <td class="text-truncate" style="max-width:320px">${String(row.title || '-')}</td>
                        <td><span class="badge text-bg-light border">${String(row.status || '-')}</span></td>
                        <td class="text-end">${Number(row.event_count || 0)}</td>
                        <td class="text-end">${btn}</td>
                    </tr>`
                }).join('')
                : '<tr><td colspan="6" class="text-muted">Sem incidentes.</td></tr>'
        }

        const cfEnabled = !!payload?.integrations?.cloudflare_enabled
        if(ingestBtn) ingestBtn.disabled = !cfEnabled
        if(!cfEnabled){
            setAlert('Cloudflare não configurado (CLOUDFLARE_API_TOKEN + CLOUDFLARE_ZONE_ID).', 'warning')
        }else{
            setAlert(null)
        }
    }

    const refresh = async ()=>{
        if(!healthUrl) return
        try{
            const { res, json } = await jsonFetch(healthUrl)
            if(!res.ok){
                setAlert(`Falha ao atualizar segurança: HTTP ${res.status}`, 'danger')
                return
            }
            render(json || {})
        }catch(_e){
            setAlert('Falha de rede ao atualizar segurança.', 'danger')
        }
    }

    refreshBtn?.addEventListener('click', refresh)

    ingestBtn?.addEventListener('click', async ()=>{
        if(!ingestUrl) return
        ingestBtn.disabled = true
        try{
            const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
            const { res, json } = await jsonFetch(ingestUrl, {
                method:'POST',
                headers:{ 'X-CSRF-TOKEN': token }
            })
            if(!res.ok){
                toast(json?.message || `Falha ao ingerir: HTTP ${res.status}`)
                return
            }
            toast(json?.message || 'Ingestão agendada.')
        }finally{
            ingestBtn.disabled = false
            refresh()
        }
    })

    evaluateBtn?.addEventListener('click', async ()=>{
        if(!evalUrl) return
        evaluateBtn.disabled = true
        try{
            const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
            const { res, json } = await jsonFetch(evalUrl, {
                method:'POST',
                headers:{ 'X-CSRF-TOKEN': token }
            })
            if(!res.ok){
                toast(json?.message || `Falha ao avaliar: HTTP ${res.status}`)
                return
            }
            toast(json?.message || 'Avaliação agendada.')
        }finally{
            evaluateBtn.disabled = false
            refresh()
        }
    })

    attachIpActions(topIpsTableEl)

    incidentsTableEl?.addEventListener('click', async (event)=>{
        const target = event.target
        if(!(target instanceof Element)) return

        const btn = target.closest('[data-ack-id]')
        if(!btn) return
        const id = Number(btn.getAttribute('data-ack-id') || 0)
        if(!id || !ackUrlTemplate) return
        const url = ackUrlTemplate.replace('__ID__', String(id))
        btn.setAttribute('disabled', 'disabled')
        try{
            const token = document.querySelector('meta[name="csrf-token"]')?.content || ''
            const { res, json } = await jsonFetch(url, {
                method:'POST',
                headers:{ 'X-CSRF-TOKEN': token }
            })
            if(!res.ok){
                toast(json?.message || `Falha no ACK: HTTP ${res.status}`)
                return
            }
            toast(json?.message || 'ACK registrado.')
        }finally{
            btn.removeAttribute('disabled')
            refresh()
        }
    })

    refresh()
}
