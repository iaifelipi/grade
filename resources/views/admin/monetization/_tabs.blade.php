<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.monetization.dashboard') }}" class="btn btn-sm rounded-pill {{ request()->routeIs('admin.monetization.dashboard') ? 'btn-dark' : 'btn-outline-secondary' }}">Resumo</a>
    <a href="{{ route('admin.monetization.gateways.index') }}" class="btn btn-sm rounded-pill {{ request()->routeIs('admin.monetization.gateways.*') ? 'btn-dark' : 'btn-outline-secondary' }}">Gateways de Pagamento</a>
    <a href="{{ route('admin.monetization.price-plans.index') }}" class="btn btn-sm rounded-pill {{ request()->routeIs('admin.monetization.price-plans.*') ? 'btn-dark' : 'btn-outline-secondary' }}">Planos de Preço</a>
    <a href="{{ route('admin.monetization.orders.index') }}" class="btn btn-sm rounded-pill {{ request()->routeIs('admin.monetization.orders.*') ? 'btn-dark' : 'btn-outline-secondary' }}">Pedidos</a>
    <a href="{{ route('admin.monetization.promo-codes.index') }}" class="btn btn-sm rounded-pill {{ request()->routeIs('admin.monetization.promo-codes.*') ? 'btn-dark' : 'btn-outline-secondary' }}">Cupons</a>
    <a href="{{ route('admin.monetization.currencies.index') }}" class="btn btn-sm rounded-pill {{ request()->routeIs('admin.monetization.currencies.*') ? 'btn-dark' : 'btn-outline-secondary' }}">Moedas</a>
    <a href="{{ route('admin.monetization.taxes.index') }}" class="btn btn-sm rounded-pill {{ request()->routeIs('admin.monetization.taxes.*') ? 'btn-dark' : 'btn-outline-secondary' }}">Impostos</a>
    <a href="{{ route('admin.customers.subscriptions.index') }}" class="btn btn-sm rounded-pill btn-outline-secondary">Assinaturas</a>
</div>
