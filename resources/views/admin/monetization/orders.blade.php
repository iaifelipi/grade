@extends('layouts.app')

@section('title', 'Admin — Pedidos')
@section('page-title', 'Admin')

@section('content')
@php
    $total = $items->count();
    $paid = $items->whereIn('payment_status', ['paid', 'captured'])->count();
    $pending = $items->where('payment_status', 'unpaid')->count();
@endphp
<div class="admin-monetization-page" data-order-edit-url-template="{{ route('admin.monetization.orders.update', ['id' => '__ID__']) }}">
    <x-admin.page-header title="Pedidos" subtitle="Gestão de pedidos e status de pagamento.">
        <x-slot:actions>
            <button class="btn btn-dark btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#orderCreateModal">Criar pedido</button>
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.monetization._tabs')
    @if(session('status'))<div class="alert alert-success mb-3">{{ session('status') }}</div>@endif

    <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Pedidos</span><div class="admin-metric-field-value">{{ $total }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Pagos</span><div class="admin-metric-field-value">{{ $paid }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Em aberto</span><div class="admin-metric-field-value">{{ $pending }}</div></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 d-flex align-items-center justify-content-between"><strong>Lista de pedidos</strong><span class="badge bg-light text-dark border rounded-pill">{{ $total }} registros</span></div><div class="table-responsive"><table class="table table-sm align-middle mb-0 admin-monetization-table admin-enterprise-table"><thead class="table-light"><tr><th style="width:72px">#</th><th>Pedido</th><th>Total</th><th>Status</th><th>Pagamento</th><th>Relacionamentos</th><th style="width:88px" class="text-end">Ações</th></tr></thead><tbody>
    @forelse($items as $item)
    <tr data-enterprise-row="1">
        <td><span class="admin-table-id-pill">#{{ $item->id }}</span></td><td>{{ $item->order_number }}</td><td>{{ $item->currency_code }} {{ number_format((int)$item->total_minor / 100, 2, ',', '.') }}</td><td><span class="badge bg-light text-dark border rounded-pill">{{ $item->status }}</span></td><td><span class="badge rounded-pill admin-table-status {{ in_array($item->payment_status, ['paid','captured'], true) ? 'is-success' : (in_array($item->payment_status, ['failed','refunded'], true) ? 'is-danger' : 'is-muted') }}">{{ $item->payment_status }}</span></td>
        <td>{{ $item->gateway->name ?? '—' }} / {{ $item->pricePlan->name ?? '—' }}</td>
        <td class="text-end">
            <div class="dropdown admin-table-actions-dropdown">
                <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                    <li>
                        <button
                            class="dropdown-item admin-table-action-item"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#orderCreateModal"
                            data-order-edit="1"
                            data-id="{{ $item->id }}"
                            data-status="{{ e($item->status) }}"
                            data-payment-status="{{ e($item->payment_status) }}"
                        >
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            <span>Editar status</span>
                        </button>
                    </li>
                    <li><form method="POST" action="{{ route('admin.monetization.orders.update', $item->id) }}">@csrf @method('PUT')<input type="hidden" name="status" value="completed"><input type="hidden" name="payment_status" value="paid"><button class="dropdown-item admin-table-action-item" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Marcar como pago</span></button></form></li>
                    <li><form method="POST" action="{{ route('admin.monetization.orders.update', $item->id) }}">@csrf @method('PUT')<input type="hidden" name="status" value="cancelled"><input type="hidden" name="payment_status" value="failed"><button class="dropdown-item admin-table-action-item" type="submit"><i class="bi bi-x-circle" aria-hidden="true"></i><span>Marcar como cancelado</span></button></form></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('admin.monetization.orders.destroy', $item->id) }}" onsubmit="return confirm('Remover pedido?')">@csrf @method('DELETE')<button class="dropdown-item admin-table-action-item is-danger" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i><span>Remover</span></button></form></li>
                </ul>
            </div>
        </td>
    </tr>
    @empty <tr><td colspan="7" class="text-center text-muted py-3">Sem pedidos.</td></tr> @endforelse
    </tbody></table></div></div>
</div>

<div class="modal fade" id="orderCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div><h5 class="modal-title mb-1" id="orderModalTitle">Criar pedido</h5><p class="grade-modal-hint mb-0" id="orderModalHint">Preencha os dados principais do pedido e pagamento.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" id="orderCreateForm" action="{{ route('admin.monetization.orders.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="_method" id="orderFormMethod" value="POST">
                    <div class="col-md-2 order-create-only"><input id="orderFormTenantUuid" name="tenant_uuid" class="form-control form-control-sm" placeholder="tenant_uuid"></div>
                    <div class="col-md-2 order-create-only"><select id="orderFormUserId" name="user_id" class="form-select form-select-sm"><option value="">Usuário</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>@endforeach</select></div>
                    <div class="col-md-2 order-create-only"><select id="orderFormGatewayId" name="gateway_id" class="form-select form-select-sm"><option value="">Gateway</option>@foreach($gateways as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
                    <div class="col-md-2 order-create-only"><select id="orderFormPricePlanId" name="price_plan_id" class="form-select form-select-sm"><option value="">Plano</option>@foreach($pricePlans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div class="col-md-2 order-create-only"><select id="orderFormPromoCodeId" name="promo_code_id" class="form-select form-select-sm"><option value="">Cupom</option>@foreach($promoCodes as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
                    <div class="col-md-2 order-create-only"><select id="orderFormTaxRateId" name="tax_rate_id" class="form-select form-select-sm"><option value="">Imposto</option>@foreach($taxRates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                    <div class="col-md-2 order-create-only"><input id="orderFormCurrencyCode" name="currency_code" class="form-control form-control-sm" value="BRL" required></div>
                    <div class="col-md-2 order-create-only"><input id="orderFormSubtotalMinor" name="subtotal_minor" class="form-control form-control-sm" placeholder="subtotal" required></div>
                    <div class="col-md-2 order-create-only"><input id="orderFormDiscountMinor" name="discount_minor" class="form-control form-control-sm" placeholder="desconto"></div>
                    <div class="col-md-2 order-create-only"><input id="orderFormTaxMinor" name="tax_minor" class="form-control form-control-sm" placeholder="imposto"></div>
                    <div class="col-md-3"><select id="orderFormStatus" name="status" class="form-select form-select-sm"><option value="pending">pending</option><option value="processing">processing</option><option value="completed">completed</option><option value="cancelled">cancelled</option></select></div>
                    <div class="col-md-3"><select id="orderFormPaymentStatus" name="payment_status" class="form-select form-select-sm"><option value="unpaid">unpaid</option><option value="paid">paid</option><option value="failed">failed</option><option value="refunded">refunded</option><option value="captured">captured</option></select></div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" form="orderCreateForm" id="orderModalSubmit">Salvar pedido</button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const root = document.querySelector('[data-order-edit-url-template]');
    if (!root) return;
    const editUrlTemplate = root.getAttribute('data-order-edit-url-template') || '';
    const form = document.getElementById('orderCreateForm');
    const modal = document.getElementById('orderCreateModal');
    if (!form || !modal || !editUrlTemplate) return;

    const storeUrl = @json(route('admin.monetization.orders.store'));
    const method = document.getElementById('orderFormMethod');
    const title = document.getElementById('orderModalTitle');
    const hint = document.getElementById('orderModalHint');
    const submit = document.getElementById('orderModalSubmit');
    const status = document.getElementById('orderFormStatus');
    const paymentStatus = document.getElementById('orderFormPaymentStatus');
    const createOnlyFields = Array.from(form.querySelectorAll('.order-create-only'));

    const setCreateOnlyVisible = (visible) => {
        createOnlyFields.forEach((el) => {
            el.classList.toggle('d-none', !visible);
            el.querySelectorAll('input,select,textarea').forEach((field) => {
                field.disabled = !visible;
            });
        });
    };

    const toCreateMode = () => {
        form.action = storeUrl;
        method.value = 'POST';
        title.textContent = 'Criar pedido';
        hint.textContent = 'Preencha os dados principais do pedido e pagamento.';
        submit.textContent = 'Salvar pedido';
        form.reset();
        status.value = 'pending';
        paymentStatus.value = 'unpaid';
        setCreateOnlyVisible(true);
    };

    document.querySelectorAll('[data-order-edit=\"1\"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            form.action = editUrlTemplate.replace('__ID__', encodeURIComponent(id || ''));
            method.value = 'PUT';
            title.textContent = 'Editar status do pedido';
            hint.textContent = 'Atualize apenas status do pedido e pagamento.';
            submit.textContent = 'Salvar alterações';
            status.value = btn.getAttribute('data-status') || 'pending';
            paymentStatus.value = btn.getAttribute('data-payment-status') || 'unpaid';
            setCreateOnlyVisible(false);
        });
    });

    modal.addEventListener('hidden.bs.modal', toCreateMode);
})();
</script>
@endsection
