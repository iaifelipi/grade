const STATUS_CLASS = {
    healthy: 'text-bg-success',
    warning: 'text-bg-warning',
    critical: 'text-bg-danger',
}

const MONITORING_ALERT_MUTE_KEY = 'pixip_monitoring_alert_muted'
const ALERT_AUDIO_COOLDOWN_MS = 30000

function badge(label, type = 'secondary'){
    return `<span class="badge text-bg-${type}">${label}</span>`
}

function workerStatusBadge(status){
    if (status === 'healthy') return badge('Operacional', 'success')
    if (status === 'warning') return badge('Degradado', 'warning')
    return badge('Parado', 'danger')
}

function formatDate(iso){
    if (!iso) return '—'
    const dt = new Date(iso)
    if (Number.isNaN(dt.getTime())) return '—'
    return dt.toLocaleString()
}

function escapeHtml(value){
    const text = String(value ?? '')
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}

function statusPill(raw){
    const status = String(raw || '').trim().toLowerCase()
    if (!status) return badge('—')
    if (status === 'done') return badge('Concluído', 'success')
    if (status === 'failed') return badge('Falhou', 'danger')
    if (status === 'normalizing') return badge('Normalizando', 'warning')
    if (status === 'importing') return badge('Importando', 'warning')
    if (status === 'uploading') return badge('Enviando', 'info')
    if (status === 'queued') return badge('Na fila', 'secondary')
    return badge(status)
}

function incidentAckBadge(row){
    if (row?.acknowledged_at) {
        const who = row?.ack_actor_name ? ` por ${String(row.ack_actor_name)}` : ''
        return `<span class="badge text-bg-success" title="${escapeHtml(`Reconhecido${who}`)}">Reconhecido</span>`
    }
    return '<span class="badge text-bg-warning">Pendente</span>'
}

function incidentPlaybook(row, managedQueues){
    const outcome = String(row?.outcome || '').toLowerCase()
    const action = String(row?.action || '').toLowerCase()
    const queue = String(row?.queue_name || '').trim()
    const isManaged = queue && (managedQueues || []).includes(queue)
    const criticalOutcome = ['error', 'fallback', 'blocked'].includes(outcome)

    if (!criticalOutcome) {
        return { type: 'none', label: '—' }
    }
    if (isManaged) {
        return { type: 'recover', queue, label: 'Executar playbook' }
    }
    if (action === 'queue_restart_all') {
        return { type: 'restart', label: 'Reiniciar filas' }
    }
    return { type: 'none', label: 'Investigar manual' }
}

function renderWorkersRows(workers){
    return renderWorkersRowsWithPriority(workers, {})
}

function renderWorkersRowsWithPriority(workers, priorityMap){
    const entries = Object.entries(workers || {})
        .sort((a, b) => (Number(priorityMap[b[0]] || 0) - Number(priorityMap[a[0]] || 0)))
    if (!entries.length) {
        return '<tr><td colspan="6" class="text-muted text-center py-3">Sem dados de workers.</td></tr>'
    }

    return entries.map(([queue, info]) => {
        const states = Array.isArray(info?.states) ? info.states : []
        const recoverDisabled = String(info?.status || '') === 'healthy' ? 'disabled' : ''
        const recoverTitle = recoverDisabled
            ? 'Fila saudável'
            : `Recuperar fila ${queue}`
        return `<tr>
            <td><code>${queue}</code></td>
            <td>${workerStatusBadge(String(info?.status || 'critical'))}</td>
            <td>${Number(info?.running || 0)}</td>
            <td>${Number(info?.expected || 0)}</td>
            <td><small class="text-muted">${states.length ? states.join(', ') : '—'}</small></td>
            <td>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-warning"
                    data-monitoring-recover="${queue}"
                    title="${recoverTitle}"
                    ${recoverDisabled}
                >Recuperar</button>
            </td>
        </tr>`
    }).join('')
}

function actionBadge(label, type = 'secondary'){
    return `<span class="badge text-bg-${type}">${label}</span>`
}

function healthBadge(status){
    const value = String(status || '').toLowerCase()
    if (value === 'healthy' || value === 'ok') return actionBadge('OK', 'success')
    if (value === 'warning') return actionBadge('Atenção', 'warning')
    if (value === 'critical') return actionBadge('Crítico', 'danger')
    return actionBadge(value || 'info', 'secondary')
}

function normalizeLevel(status){
    const value = String(status || '').toLowerCase()
    if (value === 'critical') return 'critical'
    if (value === 'warning') return 'warning'
    if (value === 'healthy' || value === 'ok') return 'healthy'
    return 'info'
}

function applySeverityCard(cardEl, status){
    if (!(cardEl instanceof HTMLElement)) return
    const level = normalizeLevel(status)
    cardEl.classList.remove('border-0', 'border-success', 'border-warning', 'border-danger', 'border-secondary', 'border-2')
    cardEl.classList.add('border', 'border-2')
    if (level === 'critical') {
        cardEl.classList.add('border-danger')
        return
    }
    if (level === 'warning') {
        cardEl.classList.add('border-warning')
        return
    }
    if (level === 'healthy') {
        cardEl.classList.add('border-success')
        return
    }
    cardEl.classList.add('border-secondary')
}

function formatKilobytes(kb){
    const value = Number(kb || 0)
    if (!Number.isFinite(value) || value <= 0) return '—'
    const mb = value / 1024
    if (mb < 1024) return `${mb.toFixed(1)} MB`
    return `${(mb / 1024).toFixed(2)} GB`
}

function thresholdValue(entry, fallback){
    if (entry && typeof entry === 'object' && 'value' in entry) {
        return Number(entry.value ?? fallback)
    }
    return Number(entry ?? fallback)
}

function thresholdSource(entry){
    if (entry && typeof entry === 'object' && 'source' in entry) {
        return String(entry.source || 'default config')
    }
    return 'default config'
}

function renderQueueRows(pendingByQueue, failedByQueue24h, failedByQueue15m, priorityMap, recommendations, managedQueues){
    return renderQueueRowsWithPriority(pendingByQueue, failedByQueue24h, failedByQueue15m, priorityMap, recommendations, managedQueues)
}

function renderQueueRowsWithPriority(pendingByQueue, failedByQueue24h, failedByQueue15m, priorityMap, recommendations, managedQueues){
    const names = Array.from(new Set([
        ...Object.keys(pendingByQueue || {}),
        ...Object.keys(failedByQueue24h || {}),
        ...Object.keys(failedByQueue15m || {}),
    ])).sort((a, b) => (Number(priorityMap[b] || 0) - Number(priorityMap[a] || 0)) || a.localeCompare(b))

    if (!names.length) {
        return '<tr><td colspan="5" class="text-muted text-center py-3">Sem dados de fila.</td></tr>'
    }

    return names.map((name) => {
        const rec = recommendations?.[name] || {}
        const action = String(rec?.recommended_action || 'none')
        const isManaged = (managedQueues || []).includes(name)
        let actionHtml = actionBadge('Sem ação', 'secondary')
        if (action === 'recover' && isManaged) {
            actionHtml = `<button type="button" class="btn btn-sm btn-outline-warning" data-monitoring-recover="${name}">Recuperar</button>`
        } else if (action === 'view_failures') {
            actionHtml = actionBadge('Ver falhas', 'warning')
        } else if (!isManaged) {
            actionHtml = `
                <div class="d-flex align-items-center gap-2">
                    ${actionBadge('Não gerenciada', 'dark')}
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-command="php artisan queue:work database --queue=${name}">
                        Copiar comando
                    </button>
                </div>
            `
        }

        return `<tr data-queue-row="${name}">
            <td><code>${name}</code></td>
            <td>${Number(pendingByQueue?.[name] || 0)}</td>
            <td>${Number(failedByQueue15m?.[name] || 0)}</td>
            <td>${Number(failedByQueue24h?.[name] || 0)}</td>
            <td>${actionHtml}</td>
        </tr>`
    }).join('')
}

function renderImportRows(rows){
    const list = Array.isArray(rows) ? rows : []
    if (!list.length) {
        return '<tr><td colspan="5" class="text-muted text-center py-3">Nenhum item.</td></tr>'
    }

    return list.map((item) => `<tr>
        <td>#${Number(item?.id || 0)}</td>
        <td class="text-truncate" style="max-width:280px">${String(item?.original_name || '—')}</td>
        <td>${statusPill(item?.status)}</td>
        <td>${Number(item?.progress_percent || 0)}%</td>
        <td><small class="text-muted">${formatDate(item?.updated_at)}</small></td>
    </tr>`).join('')
}

function renderInfraRows(schedulerHealth, dbHealth){
    const scheduler = schedulerHealth || {}
    const db = dbHealth || {}
    const schedulerDetail = scheduler?.schedule_work_running
        ? 'schedule:work ativo'
        : (scheduler?.cron_schedule_run_configured ? 'cron schedule:run configurado' : 'scheduler não detectado')
    const dbMetric = db?.latency_ms != null ? `${Number(db.latency_ms)} ms` : '—'

    return `
        <tr>
            <td>Scheduler</td>
            <td>${healthBadge(scheduler?.status || 'warning')}</td>
            <td>${String(scheduler?.mode || 'unknown')}</td>
            <td>${schedulerDetail}</td>
        </tr>
        <tr>
            <td>Banco</td>
            <td>${healthBadge(db?.status || 'warning')}</td>
            <td>${dbMetric}</td>
            <td>conexões: ${db?.threads_connected ?? '—'} / ${db?.max_connections ?? '—'}</td>
        </tr>
    `
}

function renderExternalRows(services){
    const rows = Array.isArray(services) ? services : []
    if (!rows.length) {
        return '<tr><td colspan="5" class="text-muted text-center py-3">Nenhum serviço configurado.</td></tr>'
    }
    return rows.map((svc) => `<tr>
        <td>${String(svc?.service || '-')}</td>
        <td>${healthBadge(svc?.status || 'info')}</td>
        <td>${String(svc?.target || '—')}</td>
        <td>${svc?.latency_ms != null ? `${Number(svc.latency_ms)} ms` : '—'}</td>
        <td class="text-truncate" style="max-width:220px">${String(svc?.error || '—')}</td>
    </tr>`).join('')
}

function renderThroughputRows(throughput){
    const rows = Array.isArray(throughput?.rows) ? throughput.rows : []
    if (!rows.length) {
        return '<tr><td colspan="5" class="text-muted text-center py-3">Sem dados de throughput.</td></tr>'
    }
    return rows.map((row) => `<tr>
        <td><code>${String(row?.queue || '-')}</code></td>
        <td>${Number(row?.processed_15m || 0)}</td>
        <td>${Number(row?.per_min || 0)}/min</td>
        <td>${Number(row?.pending || 0)}</td>
        <td>${Number(row?.failed_15m || 0)}</td>
    </tr>`).join('')
}

function renderStorageRedisRows(redisHealth, diskHealth){
    const redis = redisHealth || {}
    const disk = diskHealth || {}
    const diskInfo = disk?.disk || {}
    const inodeInfo = disk?.inode || {}
    const sizes = disk?.sizes_kb || {}

    const redisMetric = redis?.memory_used ? String(redis.memory_used) : '—'
    const redisDetail = `driver: ${String(redis?.cache_driver || '—')} | hit: ${redis?.hit_rate != null ? `${Number(redis.hit_rate)}%` : '—'} | evictions: ${redis?.evicted_keys ?? '—'}`
    const diskMetric = diskInfo?.used_percent != null ? `${Number(diskInfo.used_percent)}% usado` : '—'
    const diskDetail = `livre: ${formatKilobytes(diskInfo?.available_kb)} | inode: ${inodeInfo?.used_percent != null ? `${Number(inodeInfo.used_percent)}%` : '—'}`
    const logsMetric = formatKilobytes(sizes?.storage_logs)
    const logsDetail = `private: ${formatKilobytes(sizes?.storage_private)} | tmp: ${formatKilobytes(sizes?.tmp)}`

    return `
        <tr>
            <td>Redis/Cache</td>
            <td>${healthBadge(redis?.status || 'info')}</td>
            <td>${redisMetric}</td>
            <td>${redisDetail}</td>
        </tr>
        <tr>
            <td>Disco</td>
            <td>${healthBadge(disk?.status || 'info')}</td>
            <td>${diskMetric}</td>
            <td>${diskDetail}</td>
        </tr>
        <tr>
            <td>Storage (tamanho)</td>
            <td>${healthBadge('healthy')}</td>
            <td>logs: ${logsMetric}</td>
            <td>${logsDetail}</td>
        </tr>
    `
}

function renderQueueDelayRows(queueDelayRetries){
    const rows = Array.isArray(queueDelayRetries?.rows) ? queueDelayRetries.rows : []
    if (!rows.length) {
        return '<tr><td colspan="5" class="text-muted text-center py-3">Sem dados de delay/retry.</td></tr>'
    }
    return rows.map((row) => `<tr>
        <td><code>${String(row?.queue || '-')}</code></td>
        <td>${Number(row?.pending || 0)}</td>
        <td>${Number(row?.avg_wait_seconds || 0)}s</td>
        <td>${Number(row?.max_attempts || 0)}</td>
        <td>${Number(row?.retrying || 0)}</td>
    </tr>`).join('')
}

function queueDelaySeverity(queueDelayRetries, thresholds){
    const queueThresholds = thresholds?.queue || {}
    const backlogWarning = thresholdValue(queueThresholds?.backlog_warning, 120)
    const backlogCritical = thresholdValue(queueThresholds?.backlog_critical, 500)
    const pending = Number(queueDelayRetries?.pending_total || 0)
    const retrying = Number(queueDelayRetries?.retrying_total || 0)
    const avgWait = Number(queueDelayRetries?.avg_wait_seconds || 0)

    if (pending >= backlogCritical || retrying >= 20 || avgWait >= 300) return 'critical'
    if (pending >= backlogWarning || retrying > 0 || avgWait >= 60) return 'warning'
    return 'healthy'
}

function computeQueuePriorities(workers, pendingByQueue, failedByQueue15m, failedByQueue24h, thresholds){
    const queueThresholds = thresholds?.queue || {}
    const backlogWarning = thresholdValue(queueThresholds?.backlog_warning, 120)
    const backlogCritical = thresholdValue(queueThresholds?.backlog_critical, 500)
    const fail15Warning = thresholdValue(queueThresholds?.fail_15m_warning, 3)
    const fail15Critical = thresholdValue(queueThresholds?.fail_15m_critical, 10)

    const names = Array.from(new Set([
        ...Object.keys(workers || {}),
        ...Object.keys(pendingByQueue || {}),
        ...Object.keys(failedByQueue15m || {}),
        ...Object.keys(failedByQueue24h || {}),
    ]))

    const priorityList = names.map((queue) => {
        const worker = workers?.[queue] || {}
        const status = String(worker?.status || 'healthy')
        const pending = Number(pendingByQueue?.[queue] || 0)
        const failed15 = Number(failedByQueue15m?.[queue] || 0)
        const failed24 = Number(failedByQueue24h?.[queue] || 0)
        const states = Array.isArray(worker?.states) ? worker.states.map((s) => String(s || '').toUpperCase()) : []

        let score = 0
        let level = 'info'

        if (status === 'critical') {
            score += 120
            level = 'critical'
        } else if (status === 'warning') {
            score += 80
            level = 'warning'
        } else {
            score += 10
        }

        if (states.some((s) => ['STOPPED', 'FATAL', 'EXITED', 'BACKOFF'].includes(s))) {
            score += 100
            level = 'critical'
        } else if (states.includes('STOPPING')) {
            score += 60
            if (level !== 'critical') level = 'warning'
        }

        score += Math.min(50, Math.floor(pending / Math.max(1, Math.floor(backlogWarning / 10) || 12)))
        score += Math.min(80, failed15 * 12)
        score += Math.min(20, failed24 * 2)

        if (failed15 >= fail15Critical) {
            level = 'critical'
        } else if (failed15 >= fail15Warning && level !== 'critical') {
            level = 'warning'
        }
        if (pending >= backlogCritical) {
            level = 'critical'
        } else if (pending >= backlogWarning && level !== 'critical') {
            level = 'warning'
        }

        return { queue, score, level, pending, failed15, failed24, status, states }
    }).sort((a, b) => b.score - a.score)

    const priorityMap = {}
    priorityList.forEach((item) => { priorityMap[item.queue] = item.score })
    return { priorityList, priorityMap }
}

function levelBadge(level){
    if (level === 'critical') return badge('Crítica', 'danger')
    if (level === 'warning') return badge('Atenção', 'warning')
    return badge('Estável', 'secondary')
}

export default function initMonitoringPage(){
    const page = document.getElementById('monitoringPage')
    if (!page) return

    const healthUrl = String(page.getAttribute('data-health-url') || '').trim()
    const restartUrl = String(page.getAttribute('data-restart-url') || '').trim()
    const recoverUrl = String(page.getAttribute('data-recover-url') || '').trim()
    const incidentsExportUrl = String(page.getAttribute('data-incidents-export-url') || '').trim()
    const incidentsAckUrlTemplate = String(page.getAttribute('data-incidents-ack-url-template') || '').trim()
    const csrfToken = String(page.getAttribute('data-csrf-token') || '').trim()
    if (!healthUrl) return

    const checkedAtEl = document.getElementById('monitoringCheckedAt')
    const muteBtn = document.getElementById('monitoringMuteBtn')
    const refreshBtn = document.getElementById('monitoringRefreshBtn')
    const activeAlertEl = document.getElementById('monitoringActiveAlert')
    const activeAlertTextEl = document.getElementById('monitoringActiveAlertText')
    const activeAlertBadgeEl = document.getElementById('monitoringActiveAlertBadge')
    const alertEl = document.getElementById('monitoringAlert')
    const workersRunningEl = document.getElementById('monitoringWorkersRunning')
    const workersExpectedEl = document.getElementById('monitoringWorkersExpected')
    const pendingJobsEl = document.getElementById('monitoringPendingJobs')
    const failedJobsEl = document.getElementById('monitoringFailedJobs')
    const activeUsersEl = document.getElementById('monitoringActiveUsers')
    const queueRestartBtn = document.getElementById('monitoringQueueRestartBtn')
    const instructionsEl = document.getElementById('monitoringInstructions')
    const schedulerStatusEl = document.getElementById('monitoringSchedulerStatus')
    const schedulerModeEl = document.getElementById('monitoringSchedulerMode')
    const dbLatencyEl = document.getElementById('monitoringDbLatency')
    const dbStatusEl = document.getElementById('monitoringDbStatus')
    const externalDownEl = document.getElementById('monitoringExternalDown')
    const externalSummaryEl = document.getElementById('monitoringExternalSummary')
    const throughputAvgEl = document.getElementById('monitoringThroughputAvg')
    const throughputWindowEl = document.getElementById('monitoringThroughputWindow')
    const redisStatusEl = document.getElementById('monitoringRedisStatus')
    const redisSummaryEl = document.getElementById('monitoringRedisSummary')
    const diskUsageEl = document.getElementById('monitoringDiskUsage')
    const diskSummaryEl = document.getElementById('monitoringDiskSummary')
    const queueDelayAvgEl = document.getElementById('monitoringQueueDelayAvg')
    const queueRetrySummaryEl = document.getElementById('monitoringQueueRetrySummary')
    const redisCardEl = document.getElementById('monitoringRedisCard')
    const diskCardEl = document.getElementById('monitoringDiskCard')
    const queueDelayCardEl = document.getElementById('monitoringQueueDelayCard')
    const thresholdDbWarningEl = document.getElementById('monitoringThresholdDbWarning')
    const thresholdDbCriticalEl = document.getElementById('monitoringThresholdDbCritical')
    const thresholdQueueBacklogWarningEl = document.getElementById('monitoringThresholdQueueBacklogWarning')
    const thresholdQueueBacklogCriticalEl = document.getElementById('monitoringThresholdQueueBacklogCritical')
    const thresholdQueueFail15WarningEl = document.getElementById('monitoringThresholdQueueFail15Warning')
    const thresholdQueueFail15CriticalEl = document.getElementById('monitoringThresholdQueueFail15Critical')
    const thresholdDbWarningSourceEl = document.getElementById('monitoringThresholdDbWarningSource')
    const thresholdDbCriticalSourceEl = document.getElementById('monitoringThresholdDbCriticalSource')
    const thresholdQueueBacklogWarningSourceEl = document.getElementById('monitoringThresholdQueueBacklogWarningSource')
    const thresholdQueueBacklogCriticalSourceEl = document.getElementById('monitoringThresholdQueueBacklogCriticalSource')
    const thresholdQueueFail15WarningSourceEl = document.getElementById('monitoringThresholdQueueFail15WarningSource')
    const thresholdQueueFail15CriticalSourceEl = document.getElementById('monitoringThresholdQueueFail15CriticalSource')
    const recoverModalEl = document.getElementById('monitoringRecoverModal')
    const recoverQueueNameEl = document.getElementById('monitoringRecoverQueueName')
    const recoverExpectedTextEl = document.getElementById('monitoringRecoverExpectedText')
    const recoverInputEl = document.getElementById('monitoringRecoverInput')
    const recoverCopyBtn = document.getElementById('monitoringRecoverCopyBtn')
    const recoverConfirmBtn = document.getElementById('monitoringRecoverConfirmBtn')
    const criticalPanelEl = document.getElementById('monitoringCriticalPanel')
    const criticalListEl = document.getElementById('monitoringCriticalList')
    const criticalSummaryEl = document.getElementById('monitoringCriticalSummary')
    const recoverTopBtn = document.getElementById('monitoringRecoverTopBtn')
    const workersTableEl = document.getElementById('monitoringWorkersTable')
    const infraTableEl = document.getElementById('monitoringInfraTable')
    const externalTableEl = document.getElementById('monitoringExternalTable')
    const throughputTableEl = document.getElementById('monitoringThroughputTable')
    const storageRedisTableEl = document.getElementById('monitoringStorageRedisTable')
    const queueDelayTableEl = document.getElementById('monitoringQueueDelayTable')
    const queueTableEl = document.getElementById('monitoringQueueTable')
    const incidentTableEl = document.getElementById('monitoringIncidentTable')
    const incidentActionFilterEl = document.getElementById('monitoringIncidentActionFilter')
    const incidentQueueFilterEl = document.getElementById('monitoringIncidentQueueFilter')
    const incidentOutcomeFilterEl = document.getElementById('monitoringIncidentOutcomeFilter')
    const incidentApplyFilterBtn = document.getElementById('monitoringIncidentApplyFilterBtn')
    const incidentExportBtn = document.getElementById('monitoringIncidentExportBtn')
    const incidentPageInfoEl = document.getElementById('monitoringIncidentPageInfo')
    const incidentPrevBtn = document.getElementById('monitoringIncidentPrevBtn')
    const incidentNextBtn = document.getElementById('monitoringIncidentNextBtn')
    const runningImportsTableEl = document.getElementById('monitoringRunningImportsTable')
    const stalledImportsTableEl = document.getElementById('monitoringStalledImportsTable')
    const toastEl = document.getElementById('monitoringToastAlert')
    const toastBodyEl = document.getElementById('monitoringToastBody')
    const ackModalEl = document.getElementById('monitoringAckModal')
    const ackIncidentLabelEl = document.getElementById('monitoringAckIncidentLabel')
    const ackCommentEl = document.getElementById('monitoringAckComment')
    const ackConfirmBtn = document.getElementById('monitoringAckConfirmBtn')

    let timer = null
    let isRestarting = false
    let recoveringQueues = new Set()
    let pendingRecover = null
    let priorityState = []
    let isRefreshing = false
    let healthyStreak = 0
    let incidentPage = 1
    let incidentPerPage = 10
    let incidentLastPage = 1
    let lastOverallLevel = 'healthy'
    let lastAlertAudioAt = 0
    let ackPendingIncidentId = null
    let isAcknowledgeBusy = false
    let isMuted = window.localStorage.getItem(MONITORING_ALERT_MUTE_KEY) === '1'
    let audioCtx = null
    let audioUnlocked = false

    const toastInstance = toastEl && window.bootstrap?.Toast
        ? window.bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4500 })
        : null

    const getIncidentFilters = () => ({
        incident_action: String(incidentActionFilterEl?.value || '').trim(),
        incident_queue: String(incidentQueueFilterEl?.value || '').trim(),
        incident_outcome: String(incidentOutcomeFilterEl?.value || '').trim(),
    })

    const buildIncidentParams = (filters) => {
        const params = new URLSearchParams()
        if (filters.incident_action) params.set('incident_action', filters.incident_action)
        if (filters.incident_queue) params.set('incident_queue', filters.incident_queue)
        if (filters.incident_outcome) params.set('incident_outcome', filters.incident_outcome)
        params.set('incident_page', String(incidentPage))
        params.set('incident_per_page', String(incidentPerPage))
        return params
    }

    const syncIncidentExportUrl = () => {
        if (!incidentExportBtn || !incidentsExportUrl) return
        const url = new URL(incidentsExportUrl, window.location.origin)
        const filters = getIncidentFilters()
        const params = new URLSearchParams()
        if (filters.incident_action) params.set('incident_action', filters.incident_action)
        if (filters.incident_queue) params.set('incident_queue', filters.incident_queue)
        if (filters.incident_outcome) params.set('incident_outcome', filters.incident_outcome)
        params.forEach((value, key) => url.searchParams.set(key, value))
        incidentExportBtn.href = url.toString()
    }

    const incidentAckUrl = (id) => {
        if (!incidentsAckUrlTemplate) return ''
        return incidentsAckUrlTemplate.replace('__ID__', encodeURIComponent(String(id)))
    }

    const setMuteLabel = () => {
        if (!muteBtn) return
        const suffix = !audioUnlocked && !isMuted ? ' (clique para ativar)' : ''
        muteBtn.textContent = isMuted ? 'Som: desligado' : `Som: ligado${suffix}`
        muteBtn.classList.toggle('btn-outline-secondary', !isMuted)
        muteBtn.classList.toggle('btn-outline-danger', isMuted)
    }

    const ensureAudioUnlocked = async () => {
        if (isMuted) return false
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext
            if (!AudioCtx) return false
            if (!audioCtx) {
                audioCtx = new AudioCtx()
            }
            if (audioCtx.state === 'suspended') {
                await audioCtx.resume()
            }
            audioUnlocked = audioCtx.state === 'running'
            return audioUnlocked
        } catch (_error) {
            audioUnlocked = false
            return false
        } finally {
            setMuteLabel()
        }
    }

    const showToast = (message) => {
        if (!toastInstance || !toastBodyEl) return
        toastBodyEl.textContent = String(message || 'Alerta operacional detectado.')
        toastInstance.show()
    }

    const playAlertTone = () => {
        if (isMuted) return
        if (!audioUnlocked) return
        const nowMs = Date.now()
        if ((nowMs - lastAlertAudioAt) < ALERT_AUDIO_COOLDOWN_MS) return
        lastAlertAudioAt = nowMs
        try {
            if (!audioCtx) return
            const osc = audioCtx.createOscillator()
            const gain = audioCtx.createGain()
            osc.type = 'sine'
            osc.frequency.value = 980
            gain.gain.value = 0.05
            osc.connect(gain)
            gain.connect(audioCtx.destination)
            osc.start()
            osc.stop(audioCtx.currentTime + 0.18)
        } catch (_error) {
            // ignore audio failures (browser policy/user gesture)
        }
    }

    const setActiveAlert = (level, text) => {
        if (!activeAlertEl || !activeAlertTextEl || !activeAlertBadgeEl) return
        const normalized = String(level || 'healthy')
        if (normalized === 'healthy' || normalized === 'info') {
            activeAlertEl.classList.add('d-none')
            return
        }

        activeAlertEl.classList.remove('d-none', 'alert-warning', 'alert-danger')
        const isCritical = normalized === 'critical'
        activeAlertEl.classList.add(isCritical ? 'alert-danger' : 'alert-warning')
        activeAlertTextEl.textContent = text || (isCritical ? 'Falha crítica detectada.' : 'Atenção operacional detectada.')
        activeAlertBadgeEl.className = `badge ${isCritical ? 'text-bg-danger' : 'text-bg-warning'}`
        activeAlertBadgeEl.textContent = isCritical ? 'CRÍTICO' : 'ATENÇÃO'
    }

    const setRefreshing = (refreshing) => {
        if (!refreshBtn) return
        refreshBtn.disabled = refreshing
        refreshBtn.textContent = refreshing ? 'Atualizando...' : 'Atualizar'
    }

    const applyAlert = (queueHealth) => {
        if (!alertEl) return
        const level = String(queueHealth?.overall || 'healthy')
        const message = String(queueHealth?.message || '')
        const details = Array.isArray(queueHealth?.details) ? queueHealth.details : []

        alertEl.classList.remove('d-none', 'alert-danger', 'alert-warning', 'alert-success')
        if (level === 'healthy') {
            alertEl.classList.add('alert-success')
            alertEl.textContent = message || 'Workers operacionais.'
            return
        }
        alertEl.classList.add(level === 'critical' ? 'alert-danger' : 'alert-warning')
        alertEl.textContent = details.length ? `${message} (${details.join(' | ')})` : message
    }

    const clearRefreshTimer = () => {
        if (timer) {
            window.clearTimeout(timer)
            timer = null
        }
    }

    const nextIntervalByHealth = (overall) => {
        const level = String(overall || 'healthy')
        if (level === 'warning' || level === 'critical') {
            healthyStreak = 0
            return 5000
        }
        healthyStreak += 1
        return healthyStreak >= 4 ? 30000 : 15000
    }

    const scheduleNextRefresh = (ms) => {
        clearRefreshTimer()
        timer = window.setTimeout(() => {
            refresh()
        }, Math.max(1000, Number(ms || 5000)))
    }

    const refresh = async (manual = false) => {
        if (isRefreshing) return
        isRefreshing = true
        if (manual) {
            clearRefreshTimer()
        }
        setRefreshing(true)
        try {
            const url = new URL(healthUrl, window.location.origin)
            const filterParams = buildIncidentParams(getIncidentFilters())
            filterParams.forEach((value, key) => url.searchParams.set(key, value))
            url.searchParams.set('_ts', String(Date.now()))
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            })
            if (!response.ok) throw new Error(`HTTP ${response.status}`)

            const payload = await response.json()
            const queueHealth = payload?.queue_health || {}
            const pollInterval = nextIntervalByHealth(queueHealth?.overall)
            const thresholds = payload?.thresholds || {}
            const thresholdDb = thresholds?.db || {}
            const thresholdQueue = thresholds?.queue || {}
            const schedulerHealth = payload?.scheduler_health || {}
            const dbHealth = payload?.db_health || {}
            const externalServices = Array.isArray(payload?.external_services) ? payload.external_services : []
            const queueThroughput = payload?.queue_throughput || {}
            const redisHealth = payload?.redis_health || {}
            const diskHealth = payload?.disk_health || {}
            const queueDelayRetries = payload?.queue_delay_retries || {}
            const workers = queueHealth?.workers || {}
            const managedQueues = Array.isArray(payload?.managed_queues) ? payload.managed_queues : []
            const pendingByQueue = payload?.pending_by_queue || {}
            const failedByQueue24h = payload?.failed_by_queue_24h || {}
            const failedByQueue15m = payload?.failed_by_queue_15m || {}
            const queueRecommendations = payload?.queue_recommendations || {}
            const runningImports = payload?.running_imports || []
            const stalledImports = payload?.stalled_imports || []
            const incidentHistory = Array.isArray(payload?.incident_history) ? payload.incident_history : []
            const incidentFilters = payload?.incident_filters || {}
            const incidentPagination = payload?.incident_pagination || {}
            const instructions = Array.isArray(payload?.operational_instructions)
                ? payload.operational_instructions
                : []
            const { priorityList, priorityMap } = computeQueuePriorities(
                workers,
                pendingByQueue,
                failedByQueue15m,
                failedByQueue24h,
                thresholds
            )
            priorityState = priorityList
            const topIssue = priorityList.find((item) => item.level === 'critical' || item.level === 'warning') || null

            applyAlert(queueHealth)

            if (checkedAtEl) {
                checkedAtEl.textContent = `Atualizado em ${formatDate(payload?.checked_at || queueHealth?.checked_at)}`
                const klass = STATUS_CLASS[String(queueHealth?.overall || 'healthy')] || 'text-bg-secondary'
                checkedAtEl.className = `badge ${klass}`
            }

            const workerRows = Object.values(workers)
            const workersRunning = workerRows.reduce((sum, row) => sum + Number(row?.running || 0), 0)
            const workersExpected = workerRows.reduce((sum, row) => sum + Number(row?.expected || 0), 0)
            const pendingJobs = Object.values(pendingByQueue).reduce((sum, value) => sum + Number(value || 0), 0)
            const failedJobs = Object.values(failedByQueue24h).reduce((sum, value) => sum + Number(value || 0), 0)
            const activeUsers = Number(payload?.active_users_5m || 0)

            if (workersRunningEl) workersRunningEl.textContent = String(workersRunning)
            if (workersExpectedEl) workersExpectedEl.textContent = String(workersExpected)
            if (pendingJobsEl) pendingJobsEl.textContent = String(pendingJobs)
            if (failedJobsEl) failedJobsEl.textContent = String(failedJobs)
            if (activeUsersEl) activeUsersEl.textContent = String(activeUsers)

            if (schedulerStatusEl) schedulerStatusEl.textContent = String(schedulerHealth?.status || '—')
            if (schedulerModeEl) schedulerModeEl.textContent = `modo: ${String(schedulerHealth?.mode || '—')}`
            if (dbLatencyEl) dbLatencyEl.textContent = dbHealth?.latency_ms != null ? `${Number(dbHealth.latency_ms)} ms` : '—'
            if (dbStatusEl) dbStatusEl.textContent = `status: ${String(dbHealth?.status || '—')}`

            const externalCriticalCount = externalServices.filter((s) => String(s?.status || '') === 'critical').length
            if (externalDownEl) externalDownEl.textContent = String(externalCriticalCount)
            if (externalSummaryEl) externalSummaryEl.textContent = `serviços monitorados: ${externalServices.length}`

            const throughputRows = Array.isArray(queueThroughput?.rows) ? queueThroughput.rows : []
            const throughputAvg = throughputRows.length
                ? (throughputRows.reduce((sum, row) => sum + Number(row?.per_min || 0), 0) / throughputRows.length)
                : 0
            if (throughputAvgEl) throughputAvgEl.textContent = `${throughputAvg.toFixed(2)}/min`
            if (throughputWindowEl) throughputWindowEl.textContent = `janela: ${Number(queueThroughput?.window_minutes || 15)} min`
            if (redisStatusEl) redisStatusEl.textContent = String(redisHealth?.message || '—')
            if (redisSummaryEl) {
                redisSummaryEl.textContent = `driver: ${String(redisHealth?.cache_driver || '—')} | hit-rate: ${redisHealth?.hit_rate != null ? `${Number(redisHealth.hit_rate)}%` : '—'}`
            }
            if (diskUsageEl) {
                const usedPercent = diskHealth?.disk?.used_percent
                diskUsageEl.textContent = usedPercent != null ? `${Number(usedPercent)}% usado` : '—'
            }
            if (diskSummaryEl) {
                diskSummaryEl.textContent = `logs: ${formatKilobytes(diskHealth?.sizes_kb?.storage_logs)} | private: ${formatKilobytes(diskHealth?.sizes_kb?.storage_private)}`
            }
            if (queueDelayAvgEl) {
                queueDelayAvgEl.textContent = `${Number(queueDelayRetries?.avg_wait_seconds || 0).toFixed(2)}s`
            }
            if (queueRetrySummaryEl) {
                queueRetrySummaryEl.textContent = `retries: ${Number(queueDelayRetries?.retrying_total || 0)} | pendentes: ${Number(queueDelayRetries?.pending_total || 0)}`
            }

            applySeverityCard(redisCardEl, redisHealth?.status || 'info')
            applySeverityCard(diskCardEl, diskHealth?.status || 'info')
            applySeverityCard(queueDelayCardEl, queueDelaySeverity(queueDelayRetries, thresholds))

            if (thresholdDbWarningEl) thresholdDbWarningEl.textContent = `${thresholdValue(thresholdDb?.latency_warning_ms, 0)} ms`
            if (thresholdDbCriticalEl) thresholdDbCriticalEl.textContent = `${thresholdValue(thresholdDb?.latency_critical_ms, 0)} ms`
            if (thresholdQueueBacklogWarningEl) thresholdQueueBacklogWarningEl.textContent = String(thresholdValue(thresholdQueue?.backlog_warning, 0))
            if (thresholdQueueBacklogCriticalEl) thresholdQueueBacklogCriticalEl.textContent = String(thresholdValue(thresholdQueue?.backlog_critical, 0))
            if (thresholdQueueFail15WarningEl) thresholdQueueFail15WarningEl.textContent = String(thresholdValue(thresholdQueue?.fail_15m_warning, 0))
            if (thresholdQueueFail15CriticalEl) thresholdQueueFail15CriticalEl.textContent = String(thresholdValue(thresholdQueue?.fail_15m_critical, 0))

            if (thresholdDbWarningSourceEl) thresholdDbWarningSourceEl.textContent = thresholdSource(thresholdDb?.latency_warning_ms)
            if (thresholdDbCriticalSourceEl) thresholdDbCriticalSourceEl.textContent = thresholdSource(thresholdDb?.latency_critical_ms)
            if (thresholdQueueBacklogWarningSourceEl) thresholdQueueBacklogWarningSourceEl.textContent = thresholdSource(thresholdQueue?.backlog_warning)
            if (thresholdQueueBacklogCriticalSourceEl) thresholdQueueBacklogCriticalSourceEl.textContent = thresholdSource(thresholdQueue?.backlog_critical)
            if (thresholdQueueFail15WarningSourceEl) thresholdQueueFail15WarningSourceEl.textContent = thresholdSource(thresholdQueue?.fail_15m_warning)
            if (thresholdQueueFail15CriticalSourceEl) thresholdQueueFail15CriticalSourceEl.textContent = thresholdSource(thresholdQueue?.fail_15m_critical)

            if (infraTableEl) infraTableEl.innerHTML = renderInfraRows(schedulerHealth, dbHealth)
            if (externalTableEl) externalTableEl.innerHTML = renderExternalRows(externalServices)
            if (throughputTableEl) throughputTableEl.innerHTML = renderThroughputRows(queueThroughput)
            if (storageRedisTableEl) storageRedisTableEl.innerHTML = renderStorageRedisRows(redisHealth, diskHealth)
            if (queueDelayTableEl) queueDelayTableEl.innerHTML = renderQueueDelayRows(queueDelayRetries)
            if (workersTableEl) workersTableEl.innerHTML = renderWorkersRowsWithPriority(workers, priorityMap)
            if (queueTableEl) {
                queueTableEl.innerHTML = renderQueueRows(pendingByQueue, failedByQueue24h, failedByQueue15m, priorityMap, queueRecommendations, managedQueues)
            }
            if (runningImportsTableEl) runningImportsTableEl.innerHTML = renderImportRows(runningImports)
            if (stalledImportsTableEl) stalledImportsTableEl.innerHTML = renderImportRows(stalledImports)
            if (incidentTableEl) {
                incidentTableEl.innerHTML = incidentHistory.length
                    ? incidentHistory.map((row) => `<tr>
                        <td>#${Number(row?.id || 0)}</td>
                        <td>${formatDate(row?.created_at)}</td>
                        <td>${String(row?.action || '-')}</td>
                        <td>${String(row?.queue_name || '—')}</td>
                        <td>${statusPill(row?.outcome || '-')}</td>
                        <td>${incidentAckBadge(row)}</td>
                        <td>${String(row?.actor_name || '—')}</td>
                        <td class="text-truncate" style="max-width:380px">${String(row?.message || '—')}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                ${(() => {
                                    const playbook = incidentPlaybook(row, managedQueues)
                                    if (playbook.type === 'recover') {
                                        return `<button type="button" class="btn btn-sm btn-outline-warning" data-incident-playbook="recover" data-queue="${escapeHtml(playbook.queue || '')}">${playbook.label}</button>`
                                    }
                                    if (playbook.type === 'restart') {
                                        return `<button type="button" class="btn btn-sm btn-outline-warning" data-incident-playbook="restart">${playbook.label}</button>`
                                    }
                                    return '<span class="text-muted small">—</span>'
                                })()}
                                ${row?.acknowledged_at
                                    ? `<span class="text-muted small">${escapeHtml(`ACK ${formatDate(row.acknowledged_at)}`)}</span>`
                                    : `<button type="button" class="btn btn-sm btn-outline-primary" data-incident-ack="${Number(row?.id || 0)}">ACK</button>`}
                            </div>
                        </td>
                    </tr>`).join('')
                    : '<tr><td colspan="9" class="text-muted text-center py-3">Sem incidentes recentes.</td></tr>'
            }
            if (incidentActionFilterEl && typeof incidentFilters.incident_action === 'string') {
                incidentActionFilterEl.value = incidentFilters.incident_action
            }
            if (incidentQueueFilterEl && typeof incidentFilters.incident_queue === 'string') {
                incidentQueueFilterEl.value = incidentFilters.incident_queue
            }
            if (incidentOutcomeFilterEl && typeof incidentFilters.incident_outcome === 'string') {
                incidentOutcomeFilterEl.value = incidentFilters.incident_outcome
            }
            incidentPage = Number(incidentPagination?.page || incidentPage || 1)
            incidentPerPage = Number(incidentPagination?.per_page || incidentPerPage || 10)
            incidentLastPage = Number(incidentPagination?.last_page || 1)
            if (incidentPageInfoEl) {
                const total = Number(incidentPagination?.total || 0)
                incidentPageInfoEl.textContent = `Página ${incidentPage} de ${incidentLastPage} (${total} registros)`
            }
            if (incidentPrevBtn) {
                incidentPrevBtn.disabled = incidentPage <= 1
            }
            if (incidentNextBtn) {
                incidentNextBtn.disabled = incidentPage >= incidentLastPage
            }
            syncIncidentExportUrl()
            if (instructionsEl) {
                instructionsEl.innerHTML = instructions.length
                    ? instructions.map((line) => `<li>${String(line)}</li>`).join('')
                    : '<li class="text-muted">Sem recomendações no momento.</li>'
            }

            const topIssues = priorityList
                .filter((item) => {
                    const rec = queueRecommendations?.[item.queue] || {}
                    return item.level !== 'info' && String(rec?.recommended_action || 'none') !== 'none'
                })
                .slice(0, 2)
            if (criticalPanelEl && criticalListEl && criticalSummaryEl) {
                if (topIssues.length) {
                    criticalPanelEl.classList.remove('d-none')
                    criticalSummaryEl.textContent = `Top ${topIssues.length} fila(s) com maior risco operacional.`
                    criticalListEl.innerHTML = topIssues.map((item) => (
                        `<div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">
                            <div>
                                <strong>${item.queue}</strong> ${levelBadge(item.level)}
                                <span class="text-muted ms-2">score ${item.score}</span>
                            </div>
                            <div class="small text-muted">pendentes: ${item.pending} | falhas15m: ${item.failed15} | falhas24h: ${item.failed24}</div>
                        </div>`
                    )).join('')
                } else {
                    criticalPanelEl.classList.add('d-none')
                    criticalSummaryEl.textContent = 'Sem filas críticas no momento.'
                    criticalListEl.innerHTML = ''
                }
            }

            if (recoverTopBtn) {
                const topQueue = priorityList.find((item) => item.level !== 'info')
                const topRec = topQueue ? (queueRecommendations?.[topQueue.queue] || {}) : null
                const action = String(topRec?.recommended_action || 'none')
                if (!topQueue || action === 'none') {
                    recoverTopBtn.disabled = true
                    recoverTopBtn.textContent = 'Recuperar agora'
                    recoverTopBtn.classList.remove('btn-outline-secondary')
                    recoverTopBtn.classList.add('btn-danger')
                } else if (action === 'recover' && ['imports', 'normalize', 'extras'].includes(topQueue.queue)) {
                    recoverTopBtn.disabled = false
                    recoverTopBtn.textContent = `Recuperar agora (${topQueue.queue})`
                    recoverTopBtn.classList.remove('btn-outline-secondary')
                    recoverTopBtn.classList.add('btn-danger')
                } else {
                    recoverTopBtn.disabled = false
                    recoverTopBtn.textContent = `Ver falhas (${topQueue.queue})`
                    recoverTopBtn.classList.remove('btn-danger')
                    recoverTopBtn.classList.add('btn-outline-secondary')
                }
            }

            const overallLevel = String(queueHealth?.overall || 'healthy')
            const activeText = topIssue
                ? `Fila ${topIssue.queue} com risco elevado (pendentes: ${topIssue.pending}, falhas15m: ${topIssue.failed15}).`
                : (overallLevel === 'critical' ? 'Falha crítica detectada nos workers.' : 'Risco operacional detectado.')
            setActiveAlert(overallLevel, activeText)
            if (overallLevel !== 'healthy' && overallLevel !== 'info' && overallLevel !== lastOverallLevel) {
                playAlertTone()
                showToast(activeText)
            }
            lastOverallLevel = overallLevel
            scheduleNextRefresh(pollInterval)
        } catch (error) {
            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-success', 'alert-warning')
                alertEl.classList.add('alert-danger')
                alertEl.textContent = `Falha ao atualizar monitoramento: ${error?.message || 'erro desconhecido'}`
            }
            scheduleNextRefresh(5000)
        } finally {
            setRefreshing(false)
            isRefreshing = false
        }
    }

    const restartQueues = async () => {
        if (!restartUrl || isRestarting) return
        isRestarting = true
        if (queueRestartBtn) {
            queueRestartBtn.disabled = true
            queueRestartBtn.textContent = 'Reiniciando...'
        }
        try {
            const response = await fetch(restartUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            })
            const payload = await response.json().catch(() => ({}))
            if (!response.ok) {
                throw new Error(payload?.message || `HTTP ${response.status}`)
            }
            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-danger', 'alert-warning')
                alertEl.classList.add('alert-success')
                alertEl.textContent = payload?.message || 'Reinício solicitado com sucesso.'
            }
            await refresh(true)
        } catch (error) {
            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-success', 'alert-warning')
                alertEl.classList.add('alert-danger')
                alertEl.textContent = `Falha ao reiniciar filas: ${error?.message || 'erro desconhecido'}`
            }
        } finally {
            isRestarting = false
            if (queueRestartBtn) {
                queueRestartBtn.disabled = false
                queueRestartBtn.textContent = 'Reiniciar filas'
            }
        }
    }

    const recoverQueue = async (queue, button, typed) => {
        if (!recoverUrl || !queue || recoveringQueues.has(queue)) return

        recoveringQueues.add(queue)
        if (button) {
            button.disabled = true
            button.textContent = 'Recuperando...'
        }

        try {
            const response = await fetch(recoverUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    queue,
                    confirmation_text: typed,
                }),
            })
            const payload = await response.json().catch(() => ({}))
            if (!response.ok) {
                throw new Error(payload?.message || `HTTP ${response.status}`)
            }

            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-danger', 'alert-warning')
                alertEl.classList.add('alert-success')
                alertEl.textContent = payload?.message || `Recuperação da fila ${queue} solicitada.`
            }
            await refresh()
        } catch (error) {
            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-success', 'alert-warning')
                alertEl.classList.add('alert-danger')
                alertEl.textContent = `Falha ao recuperar fila ${queue}: ${error?.message || 'erro desconhecido'}`
            }
        } finally {
            recoveringQueues.delete(queue)
            if (button) {
                button.disabled = false
                button.textContent = 'Recuperar'
            }
        }
    }

    const acknowledgeIncident = async (incidentId, comment) => {
        const url = incidentAckUrl(incidentId)
        if (!url || isAcknowledgeBusy) return
        isAcknowledgeBusy = true
        if (ackConfirmBtn) {
            ackConfirmBtn.disabled = true
            ackConfirmBtn.textContent = 'Confirmando...'
        }
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    comment: String(comment || '').trim(),
                }),
            })
            const payload = await response.json().catch(() => ({}))
            if (!response.ok) {
                throw new Error(payload?.message || `HTTP ${response.status}`)
            }
            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-danger', 'alert-warning')
                alertEl.classList.add('alert-success')
                alertEl.textContent = payload?.message || 'Incidente reconhecido com sucesso.'
            }
            await refresh(true)
        } catch (error) {
            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-success', 'alert-warning')
                alertEl.classList.add('alert-danger')
                alertEl.textContent = `Falha ao reconhecer incidente: ${error?.message || 'erro desconhecido'}`
            }
        } finally {
            isAcknowledgeBusy = false
            if (ackConfirmBtn) {
                ackConfirmBtn.disabled = false
                ackConfirmBtn.textContent = 'Confirmar ACK'
            }
        }
    }

    const expectedRecoverText = (queue) => `RECUPERAR ${String(queue || '').toUpperCase()}`

    const syncRecoverConfirmState = () => {
        if (!recoverConfirmBtn || !recoverInputEl || !pendingRecover) return
        const expected = expectedRecoverText(pendingRecover.queue)
        const typed = String(recoverInputEl.value || '').trim().toUpperCase()
        recoverConfirmBtn.disabled = typed !== expected
    }

    const openRecoverModal = (queue, button) => {
        if (!recoverModalEl || !window.bootstrap?.Modal) {
            return
        }
        pendingRecover = { queue, button }
        const expected = expectedRecoverText(queue)

        if (recoverQueueNameEl) recoverQueueNameEl.textContent = String(queue)
        if (recoverExpectedTextEl) recoverExpectedTextEl.textContent = expected
        if (recoverInputEl) {
            recoverInputEl.value = ''
        }
        if (recoverConfirmBtn) {
            recoverConfirmBtn.disabled = true
        }

        window.bootstrap.Modal.getOrCreateInstance(recoverModalEl).show()
        window.setTimeout(() => {
            recoverInputEl?.focus()
        }, 120)
    }

    queueRestartBtn?.addEventListener('click', restartQueues)
    muteBtn?.addEventListener('click', async () => {
        isMuted = !isMuted
        window.localStorage.setItem(MONITORING_ALERT_MUTE_KEY, isMuted ? '1' : '0')
        if (!isMuted) {
            const unlocked = await ensureAudioUnlocked()
            if (unlocked) {
                playAlertTone()
            }
        }
        setMuteLabel()
    })
    recoverTopBtn?.addEventListener('click', () => {
        const target = priorityState.find((item) => item.level !== 'info')
        if (!target) return

        const queueRow = document.querySelector(`[data-queue-row="${target.queue}"]`)
        const isRecover = ['imports', 'normalize', 'extras'].includes(target.queue)
        if (isRecover && recoverTopBtn?.textContent?.toLowerCase().includes('recuperar')) {
            openRecoverModal(target.queue, null)
            return
        }
        if (queueRow instanceof HTMLElement) {
            queueRow.scrollIntoView({ behavior: 'smooth', block: 'center' })
            queueRow.classList.add('table-warning')
            window.setTimeout(() => queueRow.classList.remove('table-warning'), 1800)
        }
    })
    workersTableEl?.addEventListener('click', (event) => {
        const target = event.target
        if (!(target instanceof HTMLElement)) return
        const button = target.closest('[data-monitoring-recover]')
        if (!(button instanceof HTMLButtonElement)) return
        const queue = String(button.getAttribute('data-monitoring-recover') || '').trim()
        if (!queue) return
        openRecoverModal(queue, button)
    })

    queueTableEl?.addEventListener('click', async (event) => {
        const target = event.target
        if (!(target instanceof HTMLElement)) return

        const copyBtn = target.closest('[data-copy-command]')
        if (copyBtn instanceof HTMLButtonElement) {
            const command = String(copyBtn.getAttribute('data-copy-command') || '').trim()
            if (!command) return
            try {
                await navigator.clipboard.writeText(command)
                if (alertEl) {
                    alertEl.classList.remove('d-none', 'alert-danger', 'alert-warning')
                    alertEl.classList.add('alert-success')
                    alertEl.textContent = 'Comando copiado para área de transferência.'
                }
            } catch (_error) {
                if (alertEl) {
                    alertEl.classList.remove('d-none', 'alert-success', 'alert-warning')
                    alertEl.classList.add('alert-danger')
                    alertEl.textContent = 'Não foi possível copiar o comando.'
                }
            }
            return
        }

        const recoverBtn = target.closest('[data-monitoring-recover]')
        if (recoverBtn instanceof HTMLButtonElement) {
            const queue = String(recoverBtn.getAttribute('data-monitoring-recover') || '').trim()
            if (!queue) return
            openRecoverModal(queue, recoverBtn)
        }
    })

    incidentTableEl?.addEventListener('click', async (event) => {
        const target = event.target
        if (!(target instanceof HTMLElement)) return

        const ackBtn = target.closest('[data-incident-ack]')
        if (ackBtn instanceof HTMLButtonElement) {
            const incidentId = Number(ackBtn.getAttribute('data-incident-ack') || 0)
            if (!incidentId || !ackModalEl || !window.bootstrap?.Modal) return
            ackPendingIncidentId = incidentId
            if (ackIncidentLabelEl) ackIncidentLabelEl.textContent = `#${incidentId}`
            if (ackCommentEl) ackCommentEl.value = ''
            window.bootstrap.Modal.getOrCreateInstance(ackModalEl).show()
            return
        }

        const playbookBtn = target.closest('[data-incident-playbook]')
        if (!(playbookBtn instanceof HTMLButtonElement)) return
        const action = String(playbookBtn.getAttribute('data-incident-playbook') || '').trim()
        if (action === 'recover') {
            const queue = String(playbookBtn.getAttribute('data-queue') || '').trim()
            if (!queue) return
            openRecoverModal(queue, null)
            return
        }
        if (action === 'restart') {
            await restartQueues()
        }
    })

    recoverInputEl?.addEventListener('input', syncRecoverConfirmState)
    recoverCopyBtn?.addEventListener('click', async () => {
        if (!pendingRecover) return
        const expected = expectedRecoverText(pendingRecover.queue)
        try {
            await navigator.clipboard.writeText(expected)
            if (alertEl) {
                alertEl.classList.remove('d-none', 'alert-danger', 'alert-warning')
                alertEl.classList.add('alert-success')
                alertEl.textContent = 'Texto de confirmação copiado.'
            }
        } catch (_error) {
            if (recoverInputEl) {
                recoverInputEl.value = expected
                syncRecoverConfirmState()
            }
        }
    })

    recoverConfirmBtn?.addEventListener('click', async () => {
        if (!pendingRecover || !recoverInputEl || !window.bootstrap?.Modal || !recoverModalEl) return
        const queue = pendingRecover.queue
        const typed = String(recoverInputEl.value || '').trim()
        const button = pendingRecover.button
        const modal = window.bootstrap.Modal.getOrCreateInstance(recoverModalEl)
        modal.hide()
        pendingRecover = null
        await recoverQueue(queue, button, typed)
    })

    recoverModalEl?.addEventListener('hidden.bs.modal', () => {
        pendingRecover = null
        if (recoverInputEl) recoverInputEl.value = ''
        if (recoverConfirmBtn) recoverConfirmBtn.disabled = true
    })

    ackConfirmBtn?.addEventListener('click', async () => {
        if (!ackPendingIncidentId || !ackModalEl || !window.bootstrap?.Modal) return
        const modal = window.bootstrap.Modal.getOrCreateInstance(ackModalEl)
        modal.hide()
        const comment = String(ackCommentEl?.value || '')
        const incidentId = ackPendingIncidentId
        ackPendingIncidentId = null
        await acknowledgeIncident(incidentId, comment)
    })

    ackModalEl?.addEventListener('hidden.bs.modal', () => {
        ackPendingIncidentId = null
        if (ackCommentEl) ackCommentEl.value = ''
    })

    refreshBtn?.addEventListener('click', () => refresh(true))
    incidentApplyFilterBtn?.addEventListener('click', () => {
        incidentPage = 1
        refresh(true)
    })
    incidentActionFilterEl?.addEventListener('change', () => {
        syncIncidentExportUrl()
    })
    incidentOutcomeFilterEl?.addEventListener('change', () => {
        syncIncidentExportUrl()
    })
    incidentQueueFilterEl?.addEventListener('input', () => {
        syncIncidentExportUrl()
    })
    incidentQueueFilterEl?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault()
            incidentPage = 1
            refresh(true)
        }
    })
    incidentPrevBtn?.addEventListener('click', () => {
        if (incidentPage <= 1) return
        incidentPage -= 1
        refresh(true)
    })
    incidentNextBtn?.addEventListener('click', () => {
        if (incidentPage >= incidentLastPage) return
        incidentPage += 1
        refresh(true)
    })
    syncIncidentExportUrl()
    const unlockByGesture = () => {
        ensureAudioUnlocked()
        window.removeEventListener('pointerdown', unlockByGesture)
        window.removeEventListener('keydown', unlockByGesture)
    }
    window.addEventListener('pointerdown', unlockByGesture)
    window.addEventListener('keydown', unlockByGesture)
    setMuteLabel()
    refresh(true)

    window.addEventListener('beforeunload', () => {
        clearRefreshTimer()
    })
}
