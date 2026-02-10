@extends('layouts.app')

@section('title','Admin — Relatos')
@section('page-title','Admin')

@section('topbar-tools')
    <div class="explore-toolbar explore-toolbar--topbar">
        <div class="explore-toolbar-actions ms-auto">
            <div class="explore-chip-group">
                <div class="explore-filter-inline">
                    <div class="source-combo">
                        <div class="source-select-wrap">
                            <select id="reportsSourceSelect" class="form-select">
                                <option value="">Todos os arquivos</option>
                                @foreach($topbarSources as $source)
                                    <option value="{{ $source->id }}" @selected((int) $currentSourceId === (int) $source->id)>
                                        {{ $source->original_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <a
                            href="{{ route('home') }}"
                            class="btn btn-primary explore-add-source-btn"
                            title="Abrir Explore"
                            aria-label="Abrir Explore"
                        >
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>

                <div class="explore-filter-dropdown dropdown">
                    <button
                        class="btn btn-outline-secondary dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        Filtros
                    </button>
                    <div class="dropdown-menu dropdown-menu-end explore-filter-menu p-3">
                        <label class="form-label small text-muted">Arquivo</label>
                        <select id="reportsSourceSelectMobile" class="form-select mb-2">
                            <option value="">Todos os arquivos</option>
                            @foreach($topbarSources as $source)
                                <option value="{{ $source->id }}" @selected((int) $currentSourceId === (int) $source->id)>
                                    {{ $source->original_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
@php
    $totalReports = method_exists($reports, 'total') ? (int) $reports->total() : (int) $reports->count();
    $reportedUsers = collect($reports->items())->pluck('user_id')->filter()->unique()->count();
    $withEmail = collect($reports->items())->filter(fn($r) => !empty($r->email))->count();
@endphp
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 mb-1">Relatos</h2>
            <div class="text-muted small">Relatos enviados pelos usuários com contexto técnico.</div>
        </div>
    </div>

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
            <table class="table table-sm align-middle mb-0">
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
                        <tr>
                            <td>#{{ $report->id }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $report->created_at?->format('d/m/Y H:i') }}</span></td>
                            <td>{{ $report->name ?: ($report->user_id ? "User #{$report->user_id}" : '—') }}</td>
                            <td>{{ $report->email ?? '—' }}</td>
                            <td style="max-width:360px;">
                                <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($report->message, 140) }}</div>
                                @if($report->steps)
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($report->steps, 120) }}</div>
                                @endif
                            </td>
                            <td style="max-width:260px;">
                                <span class="badge bg-light text-dark border">{{ \Illuminate\Support\Str::limit($report->url ?? '', 60) }}</span>
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
