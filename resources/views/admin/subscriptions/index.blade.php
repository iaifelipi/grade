@extends('layouts.app')

@section('title','Admin — Assinaturas de Clientes')
@section('page-title','Admin')

@section('content')
<div class="admin-plans-page admin-monetization-page">
    <x-admin.page-header
        title="Assinaturas de Clientes"
        subtitle="Gestão das assinaturas dos clientes e plano comercial aplicado."
    >
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Usuários</a>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Perfis</a>
            <a href="{{ route('admin.monetization.price-plans.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Catálogo de preços</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="alert alert-info mb-3">
        Esta tela gerencia a assinatura dos clientes.
        Os planos disponíveis são lidos de <a href="{{ route('admin.monetization.price-plans.index') }}">Cobranças &gt; Planos de Preço</a>.
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Clientes</span>
                        <div class="admin-metric-field-value">{{ $totalContas }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Com assinatura</span>
                        <div class="admin-metric-field-value">{{ $totalAtivas }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Sem assinatura</span>
                        <div class="admin-metric-field-value">{{ $totalSemAssinatura }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="grade-profile-field-box admin-metric-field-box">
                        <span class="grade-profile-field-kicker">Usuários ativos</span>
                        <div class="admin-metric-field-value">{{ $totalUsuarios }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="fw-semibold">Assinatura por cliente</div>
            <div class="d-flex align-items-center gap-2">
                <input id="subscriptionsSearchInput" type="search" class="form-control form-control-sm" placeholder="Buscar por cliente, slug, UUID ou plano" style="min-width: 320px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 admin-plans-table admin-enterprise-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:80px">#</th>
                            <th>Cliente</th>
                            <th style="width:180px">Slug</th>
                            <th style="width:280px">UUID</th>
                            <th style="width:180px">Plano comercial</th>
                            <th style="width:140px">Usuários ativos</th>
                            <th style="width:88px" class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="subscriptionsTableBody">
                        @forelse($contas as $conta)
                            @php
                                $subscription = $conta->currentSubscription;
                                $pricePlan = $subscription?->pricePlan;
                                $planCode = strtolower((string) ($pricePlan->code ?? $conta->plan ?? ''));
                                $usersCount = (int) ($conta->tenant_users_count ?? 0);
                                $currentPricePlanId = (int) ($pricePlan->id ?? 0);
                                $currentPricePlanLabel = null;
                                foreach ($pricePlanOptions as $option) {
                                    if ((int) $option['id'] === $currentPricePlanId) {
                                        $currentPricePlanLabel = (string) $option['label'];
                                        break;
                                    }
                                }
                            @endphp
                            <tr
                                data-enterprise-row="1"
                                data-subscription-row="1"
                                data-name="{{ \Illuminate\Support\Str::lower($conta->name) }}"
                                data-slug="{{ \Illuminate\Support\Str::lower($conta->slug ?? '') }}"
                                data-uuid="{{ \Illuminate\Support\Str::lower($conta->uuid ?? '') }}"
                                data-plan="{{ \Illuminate\Support\Str::lower($planCode) }}"
                            >
                                <td><span class="admin-table-id-pill">#{{ $conta->id }}</span></td>
                                <td class="fw-semibold">{{ $conta->name }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $conta->slug ?? '—' }}</span></td>
                                <td class="text-muted small"><span class="admin-plans-uuid">{{ $conta->uuid }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.customers.subscriptions.update', $conta->id) }}" id="adminSubscriptionForm{{ $conta->id }}" class="d-flex gap-2 align-items-center subscription-form">
                                        @csrf
                                        <input type="hidden" name="current_price_plan_id" value="{{ (int) ($pricePlan->id ?? 0) }}">
                                        <div class="subscription-plan-picker" data-plan-picker>
                                            <input type="hidden" name="price_plan_id" value="{{ $currentPricePlanId > 0 ? $currentPricePlanId : '' }}" data-plan-input required>
                                            <button
                                                type="button"
                                                class="subscription-plan-trigger"
                                                data-plan-trigger
                                                aria-haspopup="listbox"
                                                aria-expanded="false"
                                            >
                                                <span data-plan-current>{{ $currentPricePlanLabel ?: 'Selecionar' }}</span>
                                                <i class="bi bi-chevron-down subscription-plan-trigger-caret" aria-hidden="true"></i>
                                            </button>
                                            <div class="subscription-plan-menu" data-plan-menu role="listbox">
                                                @foreach($pricePlanOptions as $option)
                                                    <button
                                                        type="button"
                                                        class="subscription-plan-option {{ (int) $option['id'] === $currentPricePlanId ? 'is-selected' : '' }}"
                                                        data-plan-option
                                                        data-value="{{ (int) $option['id'] }}"
                                                        data-label="{{ e((string) $option['label']) }}"
                                                        role="option"
                                                        aria-selected="{{ (int) $option['id'] === $currentPricePlanId ? 'true' : 'false' }}"
                                                    >
                                                        <span>{{ $option['label'] }}</span>
                                                        <i class="bi bi-check2" aria-hidden="true"></i>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill bg-light text-dark border">{{ $usersCount }}</span>
                                </td>
                                <td class="text-end">
                                        <div class="dropdown admin-table-actions-dropdown">
                                            <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open actions">
                                                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                                                <li>
                                                    <button type="submit" form="adminSubscriptionForm{{ $conta->id }}" class="dropdown-item admin-table-action-item">
                                                        <i class="bi bi-save" aria-hidden="true"></i>
                                                        <span>Salvar</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr id="subscriptionsEmptyRow">
                                <td colspan="7" class="text-center py-4">
                                    <div class="admin-table-empty">
                                        <div class="admin-table-empty-title">Nenhum cliente encontrado</div>
                                        <div class="small text-muted">Não há clientes disponíveis para atualização de assinatura.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.subscription-plan-picker {
    position: relative;
    width: 180px;
}

.subscription-plan-trigger {
    width: 100%;
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border-radius: 999px;
    border: 1px solid #d8c9af;
    background: #fffdf8;
    color: #3f3527;
    font-size: .86rem;
    font-weight: 600;
    line-height: 1.2;
    padding: 6px 11px;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
}

.subscription-plan-trigger:hover {
    border-color: #c7ae85;
}

.subscription-plan-picker.is-open .subscription-plan-trigger {
    border-color: #c7ae85;
    box-shadow: 0 0 0 0.12rem rgba(182, 144, 90, 0.22);
    background: #fff9ee;
}

.subscription-plan-trigger-caret {
    color: #8c7759;
    transition: transform .16s ease;
}

.subscription-plan-picker.is-open .subscription-plan-trigger-caret {
    transform: rotate(180deg);
}

.subscription-plan-menu {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    z-index: 50;
    display: none;
    background: #fff;
    border: 1px solid #e4d8c5;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(30, 20, 10, 0.14);
    padding: 6px;
    max-height: 240px;
    overflow-y: auto;
}

.subscription-plan-picker.is-open .subscription-plan-menu {
    display: block;
}

.subscription-plan-option {
    width: 100%;
    border: 0;
    border-radius: 8px;
    background: transparent;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 7px 9px;
    color: #3f3527;
    font-size: .86rem;
    line-height: 1.25;
}

.subscription-plan-option:hover {
    background: #f8f1e6;
}

.subscription-plan-option i {
    opacity: 0;
    color: #7e6544;
}

.subscription-plan-option.is-selected {
    background: #f4eadb;
    font-weight: 600;
}

.subscription-plan-option.is-selected i {
    opacity: 1;
}
</style>

<script>
(() => {
    const search = document.getElementById('subscriptionsSearchInput');
    const rows = Array.from(document.querySelectorAll('tr[data-subscription-row="1"]'));
    if (!search || rows.length === 0) return;

    search.addEventListener('input', () => {
        const q = String(search.value || '').trim().toLowerCase();
        rows.forEach((row) => {
            const name = String(row.getAttribute('data-name') || '');
            const slug = String(row.getAttribute('data-slug') || '');
            const uuid = String(row.getAttribute('data-uuid') || '');
            const plan = String(row.getAttribute('data-plan') || '');
            const visible = q === '' || name.includes(q) || slug.includes(q) || uuid.includes(q) || plan.includes(q);
            row.classList.toggle('d-none', !visible);
        });
    });

    const pickers = Array.from(document.querySelectorAll('[data-plan-picker]'));
    if (pickers.length === 0) return;

    const openPicker = (picker) => {
        const trigger = picker.querySelector('[data-plan-trigger]');
        if (!trigger) return;
        closeAllPickers(picker);
        picker.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
    };

    const closePicker = (picker, focusTrigger = false) => {
        const trigger = picker.querySelector('[data-plan-trigger]');
        picker.classList.remove('is-open');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
            if (focusTrigger) trigger.focus();
        }
    };

    const focusOptionByIndex = (options, index) => {
        if (options.length === 0) return;
        const nextIndex = Math.max(0, Math.min(index, options.length - 1));
        options[nextIndex].focus();
    };

    const selectedIndex = (options) => {
        const idx = options.findIndex((btn) => btn.classList.contains('is-selected'));
        return idx >= 0 ? idx : 0;
    };

    const commitSelection = (picker, option) => {
        const input = picker.querySelector('[data-plan-input]');
        const current = picker.querySelector('[data-plan-current]');
        const options = Array.from(picker.querySelectorAll('[data-plan-option]'));
        if (!input || !current || !option) return;

        const value = String(option.getAttribute('data-value') || '').trim();
        const label = String(option.getAttribute('data-label') || '').trim();
        if (!value) return;

        input.value = value;
        current.textContent = label || 'Selecionar';

        options.forEach((btn) => {
            btn.classList.remove('is-selected');
            btn.setAttribute('aria-selected', 'false');
        });
        option.classList.add('is-selected');
        option.setAttribute('aria-selected', 'true');

        closePicker(picker, true);
    };

    const closeAllPickers = (except = null) => {
        pickers.forEach((picker) => {
            if (except && picker === except) return;
            closePicker(picker, false);
        });
    };

    pickers.forEach((picker) => {
        const trigger = picker.querySelector('[data-plan-trigger]');
        const input = picker.querySelector('[data-plan-input]');
        const current = picker.querySelector('[data-plan-current]');
        const options = Array.from(picker.querySelectorAll('[data-plan-option]'));
        if (!trigger || !input || !current || options.length === 0) return;

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const willOpen = !picker.classList.contains('is-open');
            if (!willOpen) {
                closePicker(picker, false);
                return;
            }
            openPicker(picker);
        });

        trigger.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            openPicker(picker);
            const selected = selectedIndex(options);
            if (event.key === 'ArrowUp') {
                focusOptionByIndex(options, selected > 0 ? selected - 1 : options.length - 1);
                return;
            }
            focusOptionByIndex(options, selected);
        });

        options.forEach((option, index) => {
            option.addEventListener('click', () => {
                commitSelection(picker, option);
            });

            option.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    focusOptionByIndex(options, index === options.length - 1 ? 0 : index + 1);
                    return;
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    focusOptionByIndex(options, index === 0 ? options.length - 1 : index - 1);
                    return;
                }
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    commitSelection(picker, option);
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closePicker(picker, true);
                    return;
                }
                if (event.key === 'Tab') {
                    closePicker(picker, false);
                }
            });
        });
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        if (target.closest('[data-plan-picker]')) return;
        closeAllPickers();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllPickers();
        }
    });
})();
</script>
@endsection
