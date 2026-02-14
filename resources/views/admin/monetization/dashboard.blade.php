@extends('layouts.app')

@section('title', 'Admin — Cobranças')
@section('page-title', 'Admin')

@section('content')
<div class="admin-monetization-page">
    <x-admin.page-header
        title="Cobranças"
        subtitle="Visão geral de gateways, planos, pedidos, cupons, moedas e impostos."
    >
        <x-slot:actions>
            <a href="{{ route('admin.monetization.orders.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Pedidos</a>
            <a href="{{ route('admin.monetization.price-plans.index') }}" class="btn btn-dark btn-sm rounded-pill">Novo plano</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.monetization._tabs')

    <div class="row g-2 mb-3">
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Gateways</span><div class="admin-metric-field-value">{{ (int) ($kpis['gateways'] ?? 0) }}</div></div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Planos de Preço</span><div class="admin-metric-field-value">{{ (int) ($kpis['price_plans'] ?? 0) }}</div></div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Pedidos</span><div class="admin-metric-field-value">{{ (int) ($kpis['orders'] ?? 0) }}</div></div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Cupons</span><div class="admin-metric-field-value">{{ (int) ($kpis['promo_codes'] ?? 0) }}</div></div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Moedas</span><div class="admin-metric-field-value">{{ (int) ($kpis['currencies'] ?? 0) }}</div></div></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Impostos</span><div class="admin-metric-field-value">{{ (int) ($kpis['tax_rates'] ?? 0) }}</div></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 d-flex align-items-center justify-content-between">
            <div class="text-muted small">Receita recebida (minor units)</div>
            <div class="fw-bold fs-5">{{ number_format((int) ($kpis['paid_revenue_minor'] ?? 0), 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
            <strong>Pedidos recentes</strong>
            <span class="badge bg-light text-dark border rounded-pill">{{ $recent_orders->count() }} registros</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-enterprise-table">
                <thead class="table-light">
                    <tr><th>#</th><th>Pedido</th><th>Gateway</th><th>Plano</th><th>Total</th><th>Status</th><th>Pagamento</th></tr>
                </thead>
                <tbody>
                @forelse($recent_orders as $order)
                    <tr data-enterprise-row="1">
                        <td><span class="admin-table-id-pill">#{{ $order->id }}</span></td>
                        <td>{{ $order->order_number }}</td>
                        <td><span class="badge bg-light text-dark border rounded-pill">{{ $order->gateway->name ?? '—' }}</span></td>
                        <td><span class="badge bg-light text-dark border rounded-pill">{{ $order->pricePlan->name ?? '—' }}</span></td>
                        <td>{{ $order->currency_code }} {{ number_format((int) $order->total_minor / 100, 2, ',', '.') }}</td>
                        <td><span class="badge rounded-pill admin-table-status is-muted">{{ $order->status }}</span></td>
                        <td><span class="badge rounded-pill admin-table-status {{ in_array($order->payment_status, ['paid','captured'], true) ? 'is-success' : 'is-muted' }}">{{ $order->payment_status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Nenhum pedido encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
