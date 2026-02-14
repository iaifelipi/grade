@extends('layouts.app')

@section('title', 'Admin — Cupons')
@section('page-title', 'Admin')

@section('content')
@php
    $total = $items->count();
    $active = $items->where('is_active', true)->count();
@endphp
<div class="admin-monetization-page" data-promo-code-edit-url-template="{{ route('admin.monetization.promo-codes.update', ['id' => '__ID__']) }}">
    <x-admin.page-header title="Cupons" subtitle="Cupons promocionais e regras de expiração.">
        <x-slot:actions>
            <button class="btn btn-dark btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#promoCodeCreateModal">Adicionar cupom</button>
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.monetization._tabs')
    @if(session('status'))<div class="alert alert-success mb-3">{{ session('status') }}</div>@endif

    <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Cupons</span><div class="admin-metric-field-value">{{ $total }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Ativos</span><div class="admin-metric-field-value">{{ $active }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Inativos</span><div class="admin-metric-field-value">{{ $total - $active }}</div></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 d-flex align-items-center justify-content-between"><strong>Lista de cupons</strong><span class="badge bg-light text-dark border rounded-pill">{{ $total }} registros</span></div><div class="table-responsive"><table class="table table-sm align-middle mb-0 admin-monetization-table admin-enterprise-table"><thead class="table-light"><tr><th style="width:72px">#</th><th>Código</th><th>Tipo</th><th>Valor</th><th>Uso</th><th>Status</th><th style="width:88px" class="text-end">Ações</th></tr></thead><tbody>
    @forelse($items as $item)
    <tr data-enterprise-row="1">
        <td><span class="admin-table-id-pill">#{{ $item->id }}</span></td><td>{{ $item->code }}</td><td><span class="badge bg-light text-dark border rounded-pill">{{ $item->discount_type }}</span></td><td>{{ $item->discount_value }} {{ $item->currency_code }}</td><td>{{ $item->redeemed_count }} / {{ $item->max_redemptions ?? '∞' }}</td><td><span class="badge rounded-pill admin-table-status {{ $item->is_active ? 'is-success' : 'is-muted' }}">{{ $item->is_active ? 'ativo' : 'inativo' }}</span></td>
        <td class="text-end">
            <div class="dropdown admin-table-actions-dropdown">
                <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                    <li>
                        <button
                            class="dropdown-item admin-table-action-item"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#promoCodeCreateModal"
                            data-promo-code-edit="1"
                            data-id="{{ $item->id }}"
                            data-code="{{ e($item->code) }}"
                            data-name="{{ e((string) $item->name) }}"
                            data-discount-type="{{ e($item->discount_type) }}"
                            data-discount-value="{{ e((string) $item->discount_value) }}"
                            data-currency-code="{{ e((string) $item->currency_code) }}"
                            data-max-redemptions="{{ e((string) $item->max_redemptions) }}"
                            data-starts-at="{{ optional($item->starts_at)->format('Y-m-d\\TH:i') }}"
                            data-ends-at="{{ optional($item->ends_at)->format('Y-m-d\\TH:i') }}"
                            data-is-active="{{ $item->is_active ? '1' : '0' }}"
                        >
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            <span>Editar</span>
                        </button>
                    </li>
                    <li><form method="POST" action="{{ route('admin.monetization.promo-codes.update', $item->id) }}">@csrf @method('PUT')
                        <input type="hidden" name="code" value="{{ $item->code }}"><input type="hidden" name="name" value="{{ $item->name }}"><input type="hidden" name="discount_type" value="{{ $item->discount_type }}"><input type="hidden" name="discount_value" value="{{ $item->discount_value }}"><input type="hidden" name="currency_code" value="{{ $item->currency_code }}"><input type="hidden" name="max_redemptions" value="{{ $item->max_redemptions }}"><input type="hidden" name="starts_at" value="{{ optional($item->starts_at)->format('Y-m-d H:i:s') }}"><input type="hidden" name="ends_at" value="{{ optional($item->ends_at)->format('Y-m-d H:i:s') }}"><input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}">
                        <button class="dropdown-item admin-table-action-item" type="submit"><i class="bi bi-toggle-{{ $item->is_active ? 'off' : 'on' }}" aria-hidden="true"></i><span>{{ $item->is_active ? 'Desativar' : 'Ativar' }}</span></button>
                    </form></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('admin.monetization.promo-codes.destroy', $item->id) }}" onsubmit="return confirm('Remover cupom?')">@csrf @method('DELETE')<button class="dropdown-item admin-table-action-item is-danger" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i><span>Remover</span></button></form></li>
                </ul>
            </div>
        </td>
    </tr>
    @empty <tr><td colspan="7" class="text-center text-muted py-3">Sem cupons.</td></tr> @endforelse
    </tbody></table></div></div>
</div>

<div class="modal fade" id="promoCodeCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0"><div><h5 class="modal-title mb-1" id="promoCodeModalTitle">Adicionar cupom</h5><p class="grade-modal-hint mb-0" id="promoCodeModalHint">Crie um cupom promocional com tipo e valor de desconto.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body pt-3">
                <form method="POST" id="promoCodeCreateForm" action="{{ route('admin.monetization.promo-codes.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="_method" id="promoCodeFormMethod" value="POST">
                    <div class="col-md-3"><input id="promoCodeFormCode" name="code" class="form-control form-control-sm" placeholder="WELCOME10" required></div>
                    <div class="col-md-3"><input id="promoCodeFormName" name="name" class="form-control form-control-sm" placeholder="Nome"></div>
                    <div class="col-md-2"><select id="promoCodeFormDiscountType" name="discount_type" class="form-select form-select-sm" required><option value="percent">percentual</option><option value="fixed">fixo</option></select></div>
                    <div class="col-md-2"><input id="promoCodeFormDiscountValue" name="discount_value" class="form-control form-control-sm" placeholder="10" required></div>
                    <div class="col-md-2"><input id="promoCodeFormCurrencyCode" name="currency_code" class="form-control form-control-sm" placeholder="BRL"></div>
                    <div class="col-md-3"><input id="promoCodeFormMaxRedemptions" name="max_redemptions" class="form-control form-control-sm" placeholder="Máx usos"></div>
                    <div class="col-md-3"><input id="promoCodeFormStartsAt" name="starts_at" type="datetime-local" class="form-control form-control-sm"></div>
                    <div class="col-md-3"><input id="promoCodeFormEndsAt" name="ends_at" type="datetime-local" class="form-control form-control-sm"></div>
                    <div class="col-12 d-flex align-items-center"><label class="small"><input id="promoCodeFormActive" type="checkbox" name="is_active" value="1" checked> ativo</label></div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary btn-sm" form="promoCodeCreateForm" id="promoCodeModalSubmit">Salvar cupom</button></div>
        </div>
    </div>
</div>
<script>
(() => {
    const root = document.querySelector('[data-promo-code-edit-url-template]');
    if (!root) return;
    const editUrlTemplate = root.getAttribute('data-promo-code-edit-url-template') || '';
    const form = document.getElementById('promoCodeCreateForm');
    const modal = document.getElementById('promoCodeCreateModal');
    if (!form || !modal || !editUrlTemplate) return;

    const storeUrl = @json(route('admin.monetization.promo-codes.store'));
    const method = document.getElementById('promoCodeFormMethod');
    const title = document.getElementById('promoCodeModalTitle');
    const hint = document.getElementById('promoCodeModalHint');
    const submit = document.getElementById('promoCodeModalSubmit');
    const code = document.getElementById('promoCodeFormCode');
    const name = document.getElementById('promoCodeFormName');
    const discountType = document.getElementById('promoCodeFormDiscountType');
    const discountValue = document.getElementById('promoCodeFormDiscountValue');
    const currencyCode = document.getElementById('promoCodeFormCurrencyCode');
    const maxRedemptions = document.getElementById('promoCodeFormMaxRedemptions');
    const startsAt = document.getElementById('promoCodeFormStartsAt');
    const endsAt = document.getElementById('promoCodeFormEndsAt');
    const active = document.getElementById('promoCodeFormActive');

    const toCreateMode = () => {
        form.action = storeUrl;
        method.value = 'POST';
        title.textContent = 'Adicionar cupom';
        hint.textContent = 'Crie um cupom promocional com tipo e valor de desconto.';
        submit.textContent = 'Salvar cupom';
        form.reset();
        active.checked = true;
        discountType.value = 'percent';
    };

    document.querySelectorAll('[data-promo-code-edit=\"1\"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            form.action = editUrlTemplate.replace('__ID__', encodeURIComponent(id || ''));
            method.value = 'PUT';
            title.textContent = 'Editar cupom';
            hint.textContent = 'Atualize as regras do cupom selecionado.';
            submit.textContent = 'Salvar alterações';
            code.value = btn.getAttribute('data-code') || '';
            name.value = btn.getAttribute('data-name') || '';
            discountType.value = btn.getAttribute('data-discount-type') || 'percent';
            discountValue.value = btn.getAttribute('data-discount-value') || '';
            currencyCode.value = btn.getAttribute('data-currency-code') || '';
            maxRedemptions.value = btn.getAttribute('data-max-redemptions') || '';
            startsAt.value = btn.getAttribute('data-starts-at') || '';
            endsAt.value = btn.getAttribute('data-ends-at') || '';
            active.checked = btn.getAttribute('data-is-active') === '1';
        });
    });

    modal.addEventListener('hidden.bs.modal', toCreateMode);
})();
</script>
@endsection
