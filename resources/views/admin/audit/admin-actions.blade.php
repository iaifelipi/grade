@extends('layouts.app')

@section('title', 'Admin — Auditoria (Admin)')
@section('page-title', 'Admin')

@section('content')
<div class="admin-audit-page">
    <x-admin.page-header
        title="Auditoria de acoes do Admin"
        subtitle="Alteracoes de usuarios, perfis, impersonacao e convites."
    />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Filtros</div>
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="search" class="form-control" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por email, evento ou IP">
                </div>
                <div class="col-md-4">
                    <select name="event_type" class="form-select">
                        <option value="">Tipo: todos</option>
                        @foreach($eventTypes as $t)
                            <option value="{{ $t }}" @selected($filters['event_type'] === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="tenant_uuid" class="form-select">
                        <option value="">Tenant: todos</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t }}" @selected($filters['tenant_uuid'] === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Eventos</div>
            <div class="text-muted small">{{ method_exists($rows, 'total') ? $rows->total() : $rows->count() }} registros</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px">#</th>
                            <th style="width:200px">Quando</th>
                            <th style="min-width:260px">Evento</th>
                            <th style="min-width:240px">Ator</th>
                            <th style="min-width:240px">Alvo</th>
                            <th style="width:160px">IP</th>
                            <th style="min-width:240px">Tenant</th>
                            <th style="min-width:260px">Payload</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $payload = null;
                                try { $payload = $row->payload_json ? json_decode($row->payload_json, true) : null; } catch (\Throwable $e) { $payload = null; }
                            @endphp
                            <tr>
                                <td>#{{ $row->id }}</td>
                                <td class="text-muted small">{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('d/m/Y H:i:s') }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $row->event_type }}</span></td>
                                <td>
                                    <div class="fw-semibold">{{ $row->actor_name ?? '—' }}</div>
                                    <div class="text-muted small">{{ $row->actor_email ?? ($row->actor_user_id ? ('user#'.$row->actor_user_id) : '—') }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $row->target_name ?? '—' }}</div>
                                    <div class="text-muted small">{{ $row->target_email ?? ($row->target_user_id ? ('user#'.$row->target_user_id) : '—') }}</div>
                                </td>
                                <td><code>{{ $row->ip_address ?? '—' }}</code></td>
                                <td class="text-muted small"><code>{{ $row->tenant_uuid ?? '—' }}</code></td>
                                <td class="text-muted small">
                                    @if(is_array($payload) && count($payload))
                                        <code>{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}</code>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Sem eventos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(method_exists($rows, 'links'))
            <div class="card-footer bg-white border-0">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

