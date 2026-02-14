@extends('layouts.app')

@section('title','Admin — Relatos')
@section('page-title','Admin')

@section('content')
@php
    $totalReports = method_exists($reports, 'total') ? (int) $reports->total() : (int) $reports->count();
    $reportedUsers = collect($reports->items())->pluck('user_id')->filter()->unique()->count();
    $withEmail = collect($reports->items())->filter(fn($r) => !empty($r->email))->count();
@endphp
<div class="admin-reports-page">
    <x-admin.page-header
        title="Relatos"
        subtitle="Relatos enviados pelos usuários com contexto técnico."
    />

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Total de relatos</div>
                    <div class="fw-bold fs-5">{{ $totalReports }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Usuários com relatos</div>
                    <div class="fw-bold fs-5">{{ $reportedUsers }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Relatos com e-mail</div>
                    <div class="fw-bold fs-5">{{ $withEmail }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Fila de relatos</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-reports-table">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Usuário</th>
                        <th>Email</th>
                        <th>Mensagem</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr class="admin-reports-row">
                            <td><span class="fw-semibold">#{{ $report->id }}</span></td>
                            <td><span class="badge bg-light text-dark border">{{ $report->created_at?->format('d/m/Y H:i') }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $report->name ?: ($report->user_id ? "User #{$report->user_id}" : '—') }}</div>
                            </td>
                            <td class="admin-reports-email-cell">{{ $report->email ?? '—' }}</td>
                            <td class="admin-reports-message-cell">
                                <div class="fw-semibold">{{ \Illuminate\Support\Str::limit((string) $report->message, 140) }}</div>
                                @if($report->steps)
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit((string) $report->steps, 120) }}</div>
                                @endif
                            </td>
                            <td class="admin-reports-url-cell">
                                <span class="badge bg-light text-dark border" title="{{ $report->url ?? '' }}">
                                    {{ \Illuminate\Support\Str::limit((string) ($report->url ?? ''), 60) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted text-center py-4">Nenhum relato enviado ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $reports->links() }}
        </div>
    </div>
</div>

<script>
(() => {
    const select = document.getElementById('reportsSourceSelect')
    const selectMobile = document.getElementById('reportsSourceSelectMobile')
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
