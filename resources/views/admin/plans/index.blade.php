@extends('layouts.app')

@section('title','Admin — Planos')
@section('page-title','Admin')

@section('topbar-tools')
    <div class="explore-toolbar explore-toolbar--topbar">
        <div class="explore-toolbar-actions ms-auto">
            <div class="explore-chip-group">
                <div class="explore-filter-inline">
                    <div class="source-combo">
                        <div class="source-select-wrap">
                            <select id="plansSourceSelect" class="form-select">
                                <option value="">Todos os arquivos</option>
                                @foreach($topbarSources as $source)
                                    <option value="{{ $source->id }}" @selected((int) $currentSourceId === (int) $source->id)>
                                        {{ $source->original_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <a href="{{ route('home') }}" class="btn btn-primary explore-add-source-btn" title="Abrir Explore" aria-label="Abrir Explore">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="explore-filter-dropdown dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtros
                    </button>
                    <div class="dropdown-menu dropdown-menu-end explore-filter-menu p-3">
                        <label class="form-label small text-muted">Arquivo</label>
                        <select id="plansSourceSelectMobile" class="form-select mb-2">
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
    $totalContas = $contas->count();
    $freeContas = $contas->where('plan', 'free')->count();
    $paidContas = max(0, $totalContas - $freeContas);
@endphp
<div class="container-fluid py-4 admin-plans-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Planos</h2>
            <div class="text-muted small">Gestão de planos por conta com confirmação de downgrade.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Usuários</a>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Perfis</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Contas</div>
                    <div class="fw-bold fs-5">{{ $totalContas }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Plano Free</div>
                    <div class="fw-bold fs-5">{{ $freeContas }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="text-muted small">Planos pagos</div>
                    <div class="fw-bold fs-5">{{ $paidContas }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="fw-semibold">Planos por conta</div>
            <div class="d-flex align-items-center gap-2">
                <input id="plansSearchInput" type="search" class="form-control form-control-sm" placeholder="Buscar por conta, slug ou UUID" style="min-width: 260px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:80px">#</th>
                            <th>Conta</th>
                            <th style="width:220px">Slug</th>
                            <th style="width:320px">UUID</th>
                            <th style="width:190px">Plano</th>
                            <th style="width:140px"></th>
                        </tr>
                    </thead>
                    <tbody id="plansTableBody">
                        @forelse($contas as $conta)
                            <tr
                                data-plan-row="1"
                                data-name="{{ \Illuminate\Support\Str::lower($conta->name) }}"
                                data-slug="{{ \Illuminate\Support\Str::lower($conta->slug ?? '') }}"
                                data-uuid="{{ \Illuminate\Support\Str::lower($conta->uuid ?? '') }}"
                            >
                                <td>#{{ $conta->id }}</td>
                                <td class="fw-semibold">{{ $conta->name }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $conta->slug ?? '—' }}</span></td>
                                <td class="text-muted small">{{ $conta->uuid }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.plans.update', $conta->id) }}" class="d-flex gap-2 align-items-center plan-form" data-conta="{{ $conta->name }}" data-conta-id="{{ $conta->id }}">
                                        @csrf
                                        <input type="hidden" name="current_plan" value="{{ $conta->plan }}">
                                        <select name="plan" class="form-select form-select-sm">
                                            @foreach($plans as $plan)
                                                <option value="{{ $plan }}" @selected($conta->plan === $plan)>{{ ucfirst($plan) }}</option>
                                            @endforeach
                                        </select>
                                </td>
                                <td class="text-end">
                                        <button type="submit" class="btn btn-sm btn-primary plan-save">Salvar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr id="plansEmptyRow">
                                <td colspan="6" class="text-center text-muted py-4">Nenhuma conta encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="downgradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pixip-modal-premium">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar downgrade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                A conta <strong id="downgradeTenantName"></strong> será alterada para <strong>Free</strong>.
                Deseja continuar?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDowngradeBtn">Confirmar downgrade</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const select = document.getElementById('plansSourceSelect')
    const selectMobile = document.getElementById('plansSourceSelectMobile')
    const search = document.getElementById('plansSearchInput')
    const rows = Array.from(document.querySelectorAll('tr[data-plan-row="1"]'))

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

    if (search) {
        search.addEventListener('input', () => {
            const q = String(search.value || '').trim().toLowerCase()
            rows.forEach((row) => {
                const name = String(row.getAttribute('data-name') || '')
                const slug = String(row.getAttribute('data-slug') || '')
                const uuid = String(row.getAttribute('data-uuid') || '')
                row.classList.toggle('d-none', q !== '' && !name.includes(q) && !slug.includes(q) && !uuid.includes(q))
            })
        })
    }
})()
</script>
@endsection
