@php
    $user = auth()->user();
    $canSystem = $user && $user->hasPermission('system.settings');
    $canUsers = $user && $user->hasPermission('users.manage');
    $canRoles = $user && $user->hasPermission('roles.manage');
    $canAudit = $user && $user->hasPermission('audit.view_sensitive');
    $canIntegrations = $user && $user->isSuperAdmin() && !session()->has('impersonate_user_id');
    $adminDashboardUrl = route('admin.dashboard');

    $isMonitoring = request()->routeIs('admin.monitoring.*');
    $isSecurity = request()->routeIs('admin.security.*');
    $isAudit = request()->routeIs('admin.audit.*');
    $isAdminMgmt = request()->routeIs('admin.users.*')
        || request()->routeIs('admin.dashboard')
        || request()->routeIs('admin.tenantUsers.*')
        || request()->routeIs('admin.tenantUserGroups.*')
        || request()->routeIs('admin.monetization.*')
        || request()->routeIs('admin.customers.subscriptions.*')
        || request()->routeIs('admin.customers.files.*')
        || request()->routeIs('admin.customers.imports.*')
        || request()->routeIs('admin.customers.plans.*')
        || request()->routeIs('admin.roles.*')
        || request()->routeIs('admin.plans.*')
        || request()->routeIs('admin.integrations.*')
        || request()->routeIs('admin.semantic.*')
        || request()->routeIs('admin.reports.*')
        || request()->is('admin/customers')
        || request()->is('admin/files')
        || request()->is('admin/files/*')
        || request()->is('admin/customers/files')
        || request()->is('admin/customers/files/*')
        || request()->is('admin/customers/imports')
        || request()->is('admin/customers/user-groups')
        || request()->is('admin/customers-user-groups')
        || request()->is('admin/users/user-groups');
@endphp

<nav class="grade-topnav grade-topnav-center grade-admin-topnav" aria-label="Admin">
    <a href="{{ route('admin.dashboard') }}" class="grade-toplink {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" title="Dashboard">
        <i class="bi bi-house-door"></i>
        <span>Dashboard</span>
    </a>

    @if($canSystem)
        <div class="grade-topnav-dropdown">
            <a href="{{ route('admin.monitoring.index') }}" class="grade-toplink {{ $isMonitoring ? 'is-active' : '' }}" title="Serviços">
                <i class="bi bi-cpu"></i>
                <span>Serviços</span>
                <i class="bi bi-chevron-down grade-admin-topnav-caret" aria-hidden="true"></i>
            </a>
            <div class="grade-topnav-menu" role="menu" aria-label="Serviços">
                <a href="{{ route('admin.monitoring.index') }}" class="grade-topnav-item" role="menuitem">
                    Monitoramento
                </a>
                <a href="{{ route('admin.monitoring.incidentsExport') }}" class="grade-topnav-item" role="menuitem">
                    Exportar incidentes (CSV)
                </a>
            </div>
        </div>

        <div class="grade-topnav-dropdown">
            <a href="{{ route('admin.security.index') }}" class="grade-toplink {{ $isSecurity ? 'is-active' : '' }}" title="Segurança">
                <i class="bi bi-shield-check"></i>
                <span>Segurança</span>
                <i class="bi bi-chevron-down grade-admin-topnav-caret" aria-hidden="true"></i>
            </a>
            <div class="grade-topnav-menu" role="menu" aria-label="Segurança">
                <a href="{{ route('admin.security.index') }}" class="grade-topnav-item" role="menuitem">
                    Segurança de acesso
                </a>
                <a href="{{ route('admin.security.incidentsExport') }}" class="grade-topnav-item" role="menuitem">
                    Exportar incidentes (CSV)
                </a>
            </div>
        </div>
    @endif

    @if($canAudit)
        <div class="grade-topnav-dropdown">
            <a href="{{ route('admin.audit.access') }}" class="grade-toplink {{ $isAudit ? 'is-active' : '' }}" title="Auditoria">
                <i class="bi bi-shield-lock"></i>
                <span>Auditoria</span>
                <i class="bi bi-chevron-down grade-admin-topnav-caret" aria-hidden="true"></i>
            </a>
            <div class="grade-topnav-menu" role="menu" aria-label="Auditoria">
                <a href="{{ route('admin.audit.access') }}" class="grade-topnav-item" role="menuitem">
                    Acesso sensível
                </a>
                @if($canUsers)
                    <a href="{{ route('admin.audit.adminActions') }}" class="grade-topnav-item" role="menuitem">
                        Ações de admin
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if($canUsers || $canRoles || $canSystem || $canIntegrations)
        <div class="grade-topnav-dropdown">
            <a href="{{ $adminDashboardUrl }}"
               class="grade-toplink {{ $isAdminMgmt ? 'is-active' : '' }}"
               title="Admin">
                <i class="bi bi-gear-wide-connected"></i>
                <span>Admin</span>
                <i class="bi bi-chevron-down grade-admin-topnav-caret" aria-hidden="true"></i>
            </a>
            <div class="grade-topnav-menu" role="menu" aria-label="Admin">
                @if($canUsers || $canRoles)
                    <div class="grade-user-flyout-wrap" role="none">
                        <button type="button" class="grade-user-item grade-user-item-summary">
                            <span><i class="bi bi-people"></i> Usuários</span>
                            <i class="bi bi-chevron-right grade-user-item-arrow" aria-hidden="true"></i>
                        </button>
                        <div class="grade-user-flyout" role="menu" aria-label="Usuários">
                            @if($canUsers)
                                <a href="{{ route('admin.users.index') }}" class="grade-user-item" role="menuitem">Usuários</a>
                            @endif
                            @if($canRoles)
                                <a href="{{ url('/admin/users/user-groups') }}" class="grade-user-item" role="menuitem">Grupos de Usuários</a>
                            @endif
                        </div>
                    </div>
                @endif
                @if($canUsers)
                    <div class="grade-topnav-divider"></div>
                    <div class="grade-user-flyout-wrap" role="none">
                        <button type="button" class="grade-user-item grade-user-item-summary">
                            <span><i class="bi bi-buildings"></i> Clientes</span>
                            <i class="bi bi-chevron-right grade-user-item-arrow" aria-hidden="true"></i>
                        </button>
                        <div class="grade-user-flyout" role="menu" aria-label="Clientes">
                            <a href="{{ url('/admin/customers') }}" class="grade-user-item {{ request()->routeIs('admin.tenantUsers.*') || request()->is('admin/customers') ? 'is-active' : '' }}" role="menuitem">Clientes</a>
                            <a href="{{ route('admin.customers.files.index') }}" class="grade-user-item {{ request()->routeIs('admin.customers.files.*') || request()->routeIs('admin.customers.imports.*') || request()->is('admin/files') || request()->is('admin/files/*') || request()->is('admin/customers/files') || request()->is('admin/customers/imports') ? 'is-active' : '' }}" role="menuitem">Lista de Arquivos</a>
                            <a href="{{ url('/admin/customers/user-groups') }}" class="grade-user-item {{ request()->routeIs('admin.tenantUserGroups.*') || request()->is('admin/customers/user-groups') || request()->is('admin/customers-user-groups') ? 'is-active' : '' }}" role="menuitem">Grupos de Clientes</a>
                            <a href="{{ route('admin.customers.subscriptions.index') }}" class="grade-user-item {{ request()->routeIs('admin.customers.subscriptions.*') || request()->routeIs('admin.customers.plans.*') || request()->routeIs('admin.plans.*') ? 'is-active' : '' }}" role="menuitem">Assinaturas</a>
                        </div>
                    </div>
                    <div class="grade-user-flyout-wrap" role="none">
                        <button type="button" class="grade-user-item grade-user-item-summary">
                            <span><i class="bi bi-cash-stack"></i> Cobranças</span>
                            <i class="bi bi-chevron-right grade-user-item-arrow" aria-hidden="true"></i>
                        </button>
                        <div class="grade-user-flyout" role="menu" aria-label="Cobranças">
                            <a href="{{ route('admin.monetization.dashboard') }}" class="grade-user-item {{ request()->routeIs('admin.monetization.dashboard') ? 'is-active' : '' }}" role="menuitem">Resumo</a>
                            <a href="{{ route('admin.monetization.gateways.index') }}" class="grade-user-item {{ request()->routeIs('admin.monetization.gateways.*') ? 'is-active' : '' }}" role="menuitem">Gateways de Pagamento</a>
                            <a href="{{ route('admin.monetization.price-plans.index') }}" class="grade-user-item {{ request()->routeIs('admin.monetization.price-plans.*') ? 'is-active' : '' }}" role="menuitem">Planos de Preço</a>
                            <a href="{{ route('admin.monetization.orders.index') }}" class="grade-user-item {{ request()->routeIs('admin.monetization.orders.*') ? 'is-active' : '' }}" role="menuitem">Pedidos</a>
                            <a href="{{ route('admin.monetization.promo-codes.index') }}" class="grade-user-item {{ request()->routeIs('admin.monetization.promo-codes.*') ? 'is-active' : '' }}" role="menuitem">Cupons</a>
                            <a href="{{ route('admin.monetization.currencies.index') }}" class="grade-user-item {{ request()->routeIs('admin.monetization.currencies.*') ? 'is-active' : '' }}" role="menuitem">Moedas</a>
                            <a href="{{ route('admin.monetization.taxes.index') }}" class="grade-user-item {{ request()->routeIs('admin.monetization.taxes.*') ? 'is-active' : '' }}" role="menuitem">Impostos</a>
                        </div>
                    </div>
                @endif
                @if($canIntegrations)
                    <div class="grade-topnav-divider"></div>
                    <a href="{{ route('admin.integrations.index') }}" class="grade-topnav-item" role="menuitem">Integrações</a>
                @endif
                @if($canSystem)
                    <a href="{{ route('admin.semantic.index') }}" class="grade-topnav-item" role="menuitem">Semântica</a>
                    <a href="{{ route('admin.reports.index') }}" class="grade-topnav-item" role="menuitem">Reports</a>
                @endif
            </div>
        </div>
    @endif
</nav>
