@extends('layouts.app')

@section('title', 'Admin — Auditoria de Atores')
@section('page-title', 'Admin')

@section('content')
@php
    $eventsTotal = method_exists($events, 'total') ? (int) $events->total() : (int) $events->count();
    $sessionsTotal = method_exists($sessions, 'total') ? (int) $sessions->total() : (int) $sessions->count();
    $accessTotal = method_exists($accessLogs, 'total') ? (int) $accessLogs->total() : (int) $accessLogs->count();
@endphp
<div class="admin-audit-page">
    <x-admin.page-header
        title="Auditoria de atores (Guest e Usuário)"
        subtitle="Sessões, eventos de arquivo e acessos a dados sensíveis."
    />

    @php
        $baseFilters = request()->except(['actor_type', 'events_page', 'sessions_page', 'access_page']);
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('admin.audit.access', array_merge($baseFilters, ['actor_type' => ''])) }}" class="btn btn-sm {{ ($filters['actor_type'] ?? '') === '' ? 'btn-primary' : 'btn-outline-secondary' }}">Todos</a>
        <a href="{{ route('admin.audit.access', array_merge($baseFilters, ['actor_type' => 'guest'])) }}" class="btn btn-sm {{ ($filters['actor_type'] ?? '') === 'guest' ? 'btn-primary' : 'btn-outline-secondary' }}">Guest</a>
        <a href="{{ route('admin.audit.access', array_merge($baseFilters, ['actor_type' => 'user'])) }}" class="btn btn-sm {{ ($filters['actor_type'] ?? '') === 'user' ? 'btn-primary' : 'btn-outline-secondary' }}">Usuário</a>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Eventos de arquivo</div>
                    <div class="fw-bold fs-5">{{ $eventsTotal }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Sessões rastreadas</div>
                    <div class="fw-bold fs-5">{{ $sessionsTotal }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Logs de acesso sensível</div>
                    <div class="fw-bold fs-5">{{ $accessTotal }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Filtros</div>
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="guest_uuid" class="form-control" placeholder="Guest UUID" value="{{ $filters['guest_uuid'] }}">
                </div>
                <div class="col-md-2">
                    <input type="text" name="action" class="form-control" placeholder="Ação (upload, delete...)" value="{{ $filters['action'] }}">
                </div>
                <div class="col-md-2">
                    <select name="actor_type" class="form-select">
                        <option value="">Tipo de ator: todos</option>
                        <option value="guest" @selected($filters['actor_type'] === 'guest')>guest</option>
                        <option value="user" @selected($filters['actor_type'] === 'user')>user</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Eventos de arquivo</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Ator</th>
                        <th>Guest UUID</th>
                        <th>Ação</th>
                        <th>Arquivo</th>
                        <th>IP</th>
                        <th>SO / Browser</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $row)
                        <tr>
                            <td>#{{ $row->id }}</td>
                            <td>{{ $row->actor_type ?? 'guest' }} @if(!empty($row->user_id)) (u#{{ $row->user_id }}) @endif</td>
                            <td><code class="small">{{ $row->guest_uuid }}</code></td>
                            <td><span class="badge bg-secondary">{{ $row->action }}</span></td>
                            <td>{{ $row->file_name ?? '—' }}</td>
                            <td><code class="small">{{ $row->ip_raw ?? '—' }}</code></td>
                            <td>
                                {{ $row->os_family ?? '—' }} {{ $row->os_version ?? '' }}
                                <br>
                                <span class="text-muted small">{{ $row->browser_family ?? '—' }} {{ $row->browser_version ?? '' }} / {{ $row->device_type ?? '—' }}</span>
                            </td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Sem eventos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $events->links('pagination::bootstrap-5') }}</div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Sessões</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Ator</th>
                        <th>Guest UUID</th>
                        <th>IP</th>
                        <th>Rota</th>
                        <th>Último acesso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $row)
                        <tr>
                            <td>#{{ $row->id }}</td>
                            <td>{{ $row->actor_type ?? 'guest' }} @if(!empty($row->user_id)) (u#{{ $row->user_id }}) @endif</td>
                            <td><code class="small">{{ $row->guest_uuid }}</code></td>
                            <td><code class="small">{{ $row->ip_raw ?? '—' }}</code></td>
                            <td>{{ $row->last_route ?? '—' }}</td>
                            <td>{{ optional($row->last_seen_at)->format('d/m/Y H:i:s') ?? $row->last_seen_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Sem sessões.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $sessions->links('pagination::bootstrap-5') }}</div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Logs de visualização sensível</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Rota</th>
                        <th>Método</th>
                        <th>Status</th>
                        <th>Campos sensíveis</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accessLogs as $row)
                        <tr>
                            <td>#{{ $row->id }}</td>
                            <td>{{ $row->user_id ? 'u#'.$row->user_id : '—' }}</td>
                            <td>{{ $row->route_name ?: $row->request_path }}</td>
                            <td>{{ $row->http_method }}</td>
                            <td>{{ $row->response_status }}</td>
                            <td>{{ $row->sensitive_fields_count }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Sem acessos sensíveis registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $accessLogs->links('pagination::bootstrap-5') }}</div>
    </div>
</div>

<script>
(() => {
    const select = document.getElementById('auditSourceSelect')
    const selectMobile = document.getElementById('auditSourceSelectMobile')
    if (!select && !selectMobile) return

    const selectTemplate = @json(route('vault.explore.selectSource', ['id' => '__ID__']))
    const clearUrl = @json(route('vault.explore.clearSource'))

    const navigate = (value) => {
        const id = String(value || '').trim()
        if (!id) {
            window.location.href = clearUrl
            return
        }
        window.location.href = selectTemplate.replace('__ID__', encodeURIComponent(id))
    }

    if (select) {
        select.addEventListener('change', () => {
            if (selectMobile) selectMobile.value = select.value
            navigate(select.value)
        })
    }
    if (selectMobile) {
        selectMobile.addEventListener('change', () => {
            if (select) select.value = selectMobile.value
            navigate(selectMobile.value)
        })
    }
})()
</script>
@endsection
