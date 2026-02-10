<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'PIXIP')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body
    class="pixip-body"
    data-auth-user-id="{{ auth()->id() ?? 'guest' }}"
    data-theme-pref="{{ auth()->check() ? (auth()->user()->theme ?? 'system') : 'system' }}"
    data-config-rail-available="{{ (request()->routeIs('home') || request()->routeIs('guest.explore.*') || request()->routeIs('admin.audit.*') || request()->routeIs('admin.reports.*') || request()->routeIs('admin.monitoring.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.plans.*') || request()->routeIs('admin.semantic.*') || request()->routeIs('vault.sources.*') || request()->routeIs('vault.semantic.*') || request()->routeIs('vault.automation.*')) ? '1' : '0' }}"
>

<div class="pixip-layout">
    <main class="pixip-main">
        @php
            $showTenantTargetBadge = false;
            $tenantTargetLabel = '';

            if (auth()->check() && auth()->user()->isSuperAdmin()) {
                $actorTenantUuid = trim((string) (auth()->user()->tenant_uuid ?? ''));
                $activeTenantUuid = trim((string) (app()->bound('tenant_uuid') ? app('tenant_uuid') : session('tenant_uuid', '')));

                if ($actorTenantUuid !== '' && $activeTenantUuid !== '' && $activeTenantUuid !== $actorTenantUuid) {
                    $tenantTargetName = (string) (\App\Models\Tenant::query()->where('uuid', $activeTenantUuid)->value('name') ?? '');
                    $tenantTargetLabel = $tenantTargetName !== '' ? $tenantTargetName : $activeTenantUuid;
                    $showTenantTargetBadge = true;
                }
            }
        @endphp
        <header class="pixip-topbar @hasSection('topbar-tools') has-tools @endif">
            <div class="pixip-topbar-left"></div>

            <div class="pixip-user-menu">
                @if($showTenantTargetBadge)
                    <span class="pixip-tenant-target-badge" title="Tenant UUID: {{ $activeTenantUuid }}">
                        Tenant alvo: {{ $tenantTargetLabel }}
                    </span>
                @endif
                @auth
                    @php
                        $userDisplayName = trim((string) (auth()->user()->name ?? 'User'));
                        $userParts = preg_split('/\s+/', $userDisplayName) ?: [];
                        $userInitials = collect($userParts)
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->implode('');
                    @endphp
                    <button class="pixip-user-btn" type="button" data-user-toggle aria-expanded="false">
                        <span class="pixip-user-avatar" title="{{ $userDisplayName }}">{{ $userInitials ?: 'U' }}</span>
                    </button>
                    <div class="pixip-user-dropdown" data-user-menu>
                    <div class="pixip-user-brand">PIXIP</div>
                    <div class="pixip-user-divider"></div>

                    <div class="pixip-user-flyout-wrap">
                        <button type="button" class="pixip-user-item pixip-user-item-summary">
                        <i class="bi bi-person-circle"></i>{{ auth()->user()->name ?? 'Conta' }}
                            <i class="bi bi-chevron-right pixip-user-item-arrow" aria-hidden="true"></i>
                        </button>
                        <div class="pixip-user-flyout">
                            <a href="{{ route('vault.sources.index') }}" class="pixip-user-item">
                                <i class="bi bi-gift"></i>
                                Faça um teste grátis
                            </a>
                            <a href="{{ route('profile.edit') }}" class="pixip-user-item">
                                <i class="bi bi-person"></i>
                                Seu perfil
                            </a>
                            <a href="{{ route('profile.edit') }}#profile-preferences" class="pixip-user-item">
                                <i class="bi bi-credit-card"></i>
                                Configurações e cobrança
                            </a>
                            <a href="{{ route('profile.edit') }}" class="pixip-user-item">
                                <i class="bi bi-megaphone"></i>
                                Indique um amigo
                            </a>

                            @if(auth()->check() && auth()->user()->isSuperAdmin())
                                <div class="pixip-user-admin-list">
                                    <div class="pixip-user-subtitle">Admin</div>
                                    <a href="{{ route('admin.users.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-people"></i>Usuários</a>
                                    <a href="{{ route('admin.roles.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-key"></i>Roles & Permissões</a>
                                    <a href="{{ route('admin.plans.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-box-seam"></i>Planos</a>
                                    <a href="{{ route('admin.semantic.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-diagram-3"></i>Semântica</a>
                                    @if(request()->routeIs('home') || request()->routeIs('guest.explore.*'))
                                        <button
                                            type="button"
                                            class="pixip-user-item pixip-user-item-sub"
                                            id="openColumnsAdminModalBtn"
                                            data-columns-admin-modal="1"
                                            data-modal-url="{{ route('explore.columns.modal') }}"
                                        >
                                            <i class="bi bi-columns-gap"></i>Colunas
                                        </button>
                                    @else
                                        <a href="{{ route('explore.columns.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-columns-gap"></i>Colunas</a>
                                    @endif
                                    @if(request()->routeIs('explore.columns.*'))
                                        <button
                                            type="button"
                                            class="pixip-user-item pixip-user-item-sub"
                                            id="adminColumnsDataQualityBtn"
                                            data-data-quality-modal="1"
                                            data-modal-url="{{ route('explore.dataQuality.modal') }}"
                                        >
                                            <i class="bi bi-sliders"></i>Dados
                                        </button>
                                    @else
                                        <a href="{{ route('explore.dataQuality.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-sliders"></i>Dados</a>
                                    @endif
                                    <a href="{{ route('admin.reports.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-bug"></i>Reports</a>
                                    <a href="{{ route('admin.monitoring.index') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-cpu"></i>Monitoramento</a>
                                    @if(auth()->user()->hasPermission('audit.view_sensitive'))
                                        <a href="{{ route('admin.audit.access') }}" class="pixip-user-item pixip-user-item-sub"><i class="bi bi-shield-lock"></i>Auditoria</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <a href="{{ route('dashboard') }}" class="pixip-user-item">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                    <a href="{{ route('home') }}" class="pixip-user-item">
                        <i class="bi bi-house-door"></i>
                        Início
                    </a>
                    <button type="button" class="pixip-user-item" data-user-action="help"><i class="bi bi-question-circle"></i>Central de Ajuda</button>
                    <button type="button" class="pixip-user-item" data-user-action="news"><i class="bi bi-stars"></i>Novidades</button>

                    <div class="pixip-user-divider"></div>
                    <div class="pixip-user-row">
                        <span class="pixip-user-row-label"><i class="bi bi-moon-stars"></i>Tema escuro</span>
                        <button type="button" class="pixip-user-switch" id="userThemeToggle" aria-pressed="false">
                            <span class="pixip-user-switch-knob" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="pixip-user-row">
                        <span class="pixip-user-row-label"><i class="bi bi-sliders2"></i>Painel de controle</span>
                        <button type="button" class="pixip-user-switch" id="userConfigPanelToggle" aria-pressed="false">
                            <span class="pixip-user-switch-knob" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="pixip-user-row">
                        <label class="pixip-user-row-label" for="userLanguageSelect">Idioma</label>
                        <select id="userLanguageSelect" class="pixip-user-select">
                            <option value="pt-BR">Português (BR)</option>
                            <option value="en-US">English</option>
                            <option value="es-ES">Español</option>
                        </select>
                    </div>
                    <button type="button" class="pixip-user-item" data-user-action="shortcuts"><i class="bi bi-keyboard"></i>Atalhos do teclado</button>
                    <button type="button" class="pixip-user-item" data-bs-toggle="modal" data-bs-target="#bugReportModal">
                        <i class="bi bi-bug"></i>
                        Reportar um problema
                    </button>
                    <button type="button" class="pixip-user-item" data-user-action="desktop"><i class="bi bi-laptop"></i>Obter o app para Desktop</button>

                    <div class="pixip-user-divider"></div>
                    @if(auth()->check() && auth()->user()->isSuperAdmin() && isset($impersonated_user) && $impersonated_user)
                        <form method="POST" action="{{ route('admin.impersonate.stop') }}">
                            @csrf
                        <button class="pixip-user-item" type="submit"><i class="bi bi-person-x"></i>Encerrar impersonação</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="pixip-user-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i>Sair</button>
                    </form>
                </div>
                @endauth

                @guest
                    <button class="pixip-user-btn pixip-user-btn--guest" type="button" data-user-toggle aria-expanded="false">
                        <span class="pixip-user-avatar pixip-user-avatar--guest" title="Menu">
                            <i class="bi bi-list"></i>
                        </span>
                    </button>
                    <div class="pixip-user-dropdown" data-user-menu>
                        <div class="pixip-user-brand">PIXIP</div>
                        <div class="pixip-user-divider"></div>
                        <a href="{{ route('home') }}" class="pixip-user-item">
                            <i class="bi bi-house-door"></i>
                            Home
                        </a>
                        <button type="button" class="pixip-user-item" data-user-action="help">
                            <i class="bi bi-question-circle"></i>
                            Central de Ajuda
                        </button>
                        <button type="button" class="pixip-user-item" data-user-action="news">
                            <i class="bi bi-stars"></i>
                            Novidades
                        </button>

                        <div class="pixip-user-divider"></div>
                        <div class="pixip-user-row">
                            <span class="pixip-user-row-label"><i class="bi bi-moon-stars"></i>Tema escuro</span>
                            <button type="button" class="pixip-user-switch" id="userThemeToggle" aria-pressed="false">
                                <span class="pixip-user-switch-knob" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div class="pixip-user-row">
                            <span class="pixip-user-row-label"><i class="bi bi-sliders2"></i>Painel de controle</span>
                            <button type="button" class="pixip-user-switch" id="userConfigPanelToggle" aria-pressed="false">
                                <span class="pixip-user-switch-knob" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div class="pixip-user-row">
                            <label class="pixip-user-row-label" for="userLanguageSelect">Idioma</label>
                            <select id="userLanguageSelect" class="pixip-user-select">
                                <option value="pt-BR">Português (BR)</option>
                                <option value="en-US">English</option>
                                <option value="es-ES">Español</option>
                            </select>
                        </div>
                        <button type="button" class="pixip-user-item" data-user-action="shortcuts">
                            <i class="bi bi-keyboard"></i>
                            Atalhos do teclado
                        </button>
                        <button type="button" class="pixip-user-item" data-bs-toggle="modal" data-bs-target="#bugReportModal">
                            <i class="bi bi-bug"></i>
                            Reportar um problema
                        </button>
                        <button type="button" class="pixip-user-item" data-user-action="desktop">
                            <i class="bi bi-laptop"></i>
                            Obter o app para Desktop
                        </button>

                        <div class="pixip-user-divider"></div>
                        <button type="button" class="pixip-user-item" data-bs-toggle="modal" data-bs-target="#authLoginModal">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Entrar
                        </button>
                    </div>
                @endguest
            </div>

            @hasSection('topbar-tools')
                <div class="pixip-topbar-tools">
                    @yield('topbar-tools')
                </div>
            @endif

            @if(auth()->check() && auth()->user()->isSuperAdmin() && isset($impersonated_user) && $impersonated_user)
                <div class="pixip-impersonation-badge">
                    <span>Impersonando: {{ $impersonated_user->name }}</span>
                    <form method="POST" action="{{ route('admin.impersonate.stop') }}">
                        @csrf
                        <button type="submit" class="pixip-impersonation-stop">Encerrar</button>
                    </form>
                </div>
            @endif

        </header>

        @if(request()->routeIs('home') || request()->routeIs('guest.explore.*') || request()->routeIs('admin.audit.*') || request()->routeIs('admin.reports.*') || request()->routeIs('admin.monitoring.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.plans.*') || request()->routeIs('admin.semantic.*') || request()->routeIs('vault.sources.*') || request()->routeIs('vault.semantic.*') || request()->routeIs('vault.automation.*'))
            <aside class="pixip-config-rail" id="pixipConfigRail" aria-label="Painel de controle">
                <div class="pixip-config-rail-actions">
                    @if(request()->routeIs('admin.audit.*') || request()->routeIs('admin.reports.*') || request()->routeIs('admin.monitoring.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.plans.*') || request()->routeIs('admin.semantic.*'))
                        @if(auth()->check() && auth()->user()->hasPermission('automation.run'))
                            <a href="{{ route('vault.automation.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('vault.automation.*') ? 'is-active' : '' }}" title="Operações">
                                <i class="bi bi-lightning-charge"></i>
                                <span>Operações</span>
                            </a>
                        @else
                            <button type="button" class="pixip-config-rail-btn" title="Operações indisponível" disabled aria-disabled="true">
                                <i class="bi bi-lightning-charge"></i>
                                <span>Operações</span>
                            </button>
                        @endif
                        @if(auth()->check() && auth()->user()->hasPermission('users.manage'))
                            <a href="{{ route('admin.users.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}" title="Usuários">
                                <i class="bi bi-people"></i>
                                <span>Usuários</span>
                            </a>
                            <a href="{{ route('admin.plans.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('admin.plans.*') ? 'is-active' : '' }}" title="Planos">
                                <i class="bi bi-box-seam"></i>
                                <span>Planos</span>
                            </a>
                        @endif
                        @if(auth()->check() && auth()->user()->hasPermission('roles.manage'))
                            <a href="{{ route('admin.roles.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('admin.roles.*') ? 'is-active' : '' }}" title="Roles">
                                <i class="bi bi-key"></i>
                                <span>Roles</span>
                            </a>
                        @endif
                        @if(auth()->check() && auth()->user()->hasPermission('system.settings'))
                            <a href="{{ route('admin.semantic.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('admin.semantic.*') ? 'is-active' : '' }}" title="Semântica">
                                <i class="bi bi-diagram-3"></i>
                                <span>Semântica</span>
                            </a>
                            <a href="{{ route('admin.reports.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}" title="Reports">
                                <i class="bi bi-bug"></i>
                                <span>Reports</span>
                            </a>
                            <a href="{{ route('admin.monitoring.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('admin.monitoring.*') ? 'is-active' : '' }}" title="Monitoramento">
                                <i class="bi bi-cpu"></i>
                                <span>Monitoramento</span>
                            </a>
                        @endif
                        @if(auth()->check() && auth()->user()->hasPermission('audit.view_sensitive'))
                            <a href="{{ route('admin.audit.access') }}" class="pixip-config-rail-btn {{ request()->routeIs('admin.audit.*') ? 'is-active' : '' }}" title="Auditoria">
                                <i class="bi bi-shield-lock"></i>
                                <span>Auditoria</span>
                            </a>
                        @endif
                    @else
                        @php
                            $isVaultUtilityRoute = request()->routeIs('vault.sources.*')
                                || request()->routeIs('vault.semantic.*')
                                || request()->routeIs('vault.automation.*');
                        @endphp
                        @if($isVaultUtilityRoute)
                            <button type="button" class="pixip-config-rail-btn" title="Busca indisponível nesta tela" disabled aria-disabled="true">
                                <i class="bi bi-search"></i>
                                <span>Busca</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" title="Limpar indisponível nesta tela" disabled aria-disabled="true">
                                <i class="bi bi-eraser"></i>
                                <span>Limpar</span>
                            </button>
                            <div class="pixip-config-rail-separator" aria-hidden="true"></div>
                            <button type="button" class="pixip-config-rail-btn" title="Colunas indisponível nesta tela" disabled aria-disabled="true">
                                <i class="bi bi-columns-gap"></i>
                                <span>Colunas</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" title="Dados indisponível nesta tela" disabled aria-disabled="true">
                                <i class="bi bi-database"></i>
                                <span>Dados</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" title="Semântica indisponível nesta tela" disabled aria-disabled="true">
                                <i class="bi bi-diagram-3"></i>
                                <span>Semântica</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" title="Publicar indisponível nesta tela" disabled aria-disabled="true">
                                <i class="bi bi-save"></i>
                                <span>Publicar</span>
                            </button>
                            <div class="pixip-config-rail-separator" aria-hidden="true"></div>
                            @if(auth()->check() && auth()->user()->hasPermission('automation.run'))
                                <a href="{{ route('vault.automation.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('vault.automation.*') ? 'is-active' : '' }}" title="Operações">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </a>
                            @else
                                <button type="button" class="pixip-config-rail-btn" title="Operações indisponível" disabled aria-disabled="true">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </button>
                            @endif
                            <button type="button" class="pixip-config-rail-btn" title="Exportar indisponível nesta tela" disabled aria-disabled="true">
                                <i class="bi bi-download"></i>
                                <span>Exportar</span>
                            </button>
                        @else
                            <button type="button" class="pixip-config-rail-btn" data-config-rail-action="search" title="Busca">
                                <i class="bi bi-search"></i>
                                <span>Busca</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" data-config-rail-action="clear" title="Limpar filtros">
                                <i class="bi bi-eraser"></i>
                                <span>Limpar</span>
                            </button>
                            <div class="pixip-config-rail-separator" aria-hidden="true"></div>
                            <button type="button" class="pixip-config-rail-btn" data-config-rail-action="columns" title="Colunas">
                                <i class="bi bi-columns-gap"></i>
                                <span>Colunas</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" data-config-rail-action="data" title="Dados">
                                <i class="bi bi-database"></i>
                                <span>Dados</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" data-config-rail-action="semantic" title="Mapa semântico">
                                <i class="bi bi-diagram-3"></i>
                                <span>Semântica</span>
                            </button>
                            <button type="button" class="pixip-config-rail-btn" data-config-rail-action="save" title="Publicar alterações" id="railSaveBtn">
                                <i class="bi bi-save"></i>
                                <span>Publicar <span class="pixip-config-rail-badge d-none" id="railSaveBadge">0</span></span>
                            </button>
                            <div class="pixip-config-rail-separator" aria-hidden="true"></div>
                            @if(auth()->check() && auth()->user()->hasPermission('automation.run'))
                                <a href="{{ route('vault.automation.index') }}" class="pixip-config-rail-btn {{ request()->routeIs('vault.automation.*') ? 'is-active' : '' }}" title="Operações">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </a>
                            @else
                                <button type="button" class="pixip-config-rail-btn" title="Operações indisponível" disabled aria-disabled="true">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </button>
                            @endif
                            <button type="button" class="pixip-config-rail-btn" data-config-rail-action="export" title="Exportar">
                                <i class="bi bi-download"></i>
                                <span>Exportar</span>
                            </button>
                        @endif
                    @endif
                </div>
            </aside>
        @endif

        <section class="pixip-content">
            @yield('content')
        </section>
    </main>
</div>
@guest
    <div class="modal fade" id="authLoginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4 pixip-auth-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Entrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="pixip-auth-modal-shell">
                        <div class="pixip-auth-modal-brand">
                            <div class="pixip-auth-logo">PX</div>
                            <div class="pixip-auth-name">PIXIP</div>
                            <div class="pixip-auth-tagline">Inteligência operacional para times de dados.</div>
                        </div>
                        <div class="pixip-auth-modal-card">
                            @include('auth.partials.login-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="authForgotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4 pixip-auth-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Recuperar senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="pixip-auth-modal-shell">
                        <div class="pixip-auth-modal-brand">
                            <div class="pixip-auth-logo">PX</div>
                            <div class="pixip-auth-name">PIXIP</div>
                            <div class="pixip-auth-tagline">Inteligência operacional para times de dados.</div>
                        </div>
                        <div class="pixip-auth-modal-card">
                            @include('auth.partials.forgot-password-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('click', (event)=>{
            const trigger = event.target.closest('[data-auth-switch]')
            if(!trigger) return
            const target = trigger.getAttribute('data-auth-switch')
            const loginEl = document.getElementById('authLoginModal')
            if(!loginEl || !window.bootstrap?.Modal) return
            const loginModal = window.bootstrap.Modal.getOrCreateInstance(loginEl)
            const forgotEl = document.getElementById('authForgotModal')
            const forgotModal = forgotEl ? window.bootstrap.Modal.getOrCreateInstance(forgotEl) : null
            if(target === 'login'){
                forgotModal?.hide()
                loginModal.show()
            }else if(target === 'forgot'){
                loginModal.hide()
                forgotModal?.show()
            }
        })

        document.addEventListener('submit', async (event)=>{
            const form = event.target.closest('form[data-auth-form]')
            if(!form) return
            event.preventDefault()
            const errorsEl = form.querySelector('[data-auth-errors]')
            if(errorsEl){
                errorsEl.classList.add('d-none')
                errorsEl.innerHTML = ''
            }
            const submitBtn = form.querySelector('[data-auth-submit]')
            const originalLabel = submitBtn?.textContent
            if(submitBtn){
                submitBtn.disabled = true
                submitBtn.textContent = 'Enviando...'
            }
            try{
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                const res = await fetch(form.action, {
                    method: form.method || 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    },
                    body: new FormData(form)
                })
                if(res.redirected){
                    window.location.href = res.url
                    return
                }
                if(res.ok){
                    try{
                        const data = await res.json()
                        if(data?.redirect){
                            window.location.href = data.redirect
                            return
                        }
                    }catch(e){}
                    window.location.reload()
                    return
                }
                let message = 'Não foi possível continuar.'
                try{
                    const data = await res.json()
                    if(data?.message){
                        message = data.message
                    }
                    if(data?.errors){
                        message = Object.values(data.errors).flat().join('<br>')
                    }
                }catch(e){}
                if(errorsEl){
                    errorsEl.innerHTML = message
                    errorsEl.classList.remove('d-none')
                }
            }catch(e){
                if(errorsEl){
                    errorsEl.textContent = 'Erro de conexão. Tente novamente.'
                    errorsEl.classList.remove('d-none')
                }
            }finally{
                if(submitBtn){
                    submitBtn.disabled = false
                    submitBtn.textContent = originalLabel || 'Enviar'
                }
            }
        })
    </script>
@endguest

<div class="modal fade" id="bugReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="guest-welcome-kicker">Suporte rápido</div>
                    <h5 class="modal-title">Encontrou algum problema?</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-2">
                <form id="bugReportForm">
                    <div class="alert alert-success d-none" id="bugReportSuccess">Obrigado! Recebemos seu relato.</div>
                    <div class="alert alert-danger d-none" id="bugReportError"></div>

                    @php
                        $isAuth = auth()->check();
                        $contactName = $isAuth ? (auth()->user()->name ?? '') : '';
                        $contactEmail = $isAuth ? (auth()->user()->email ?? '') : '';
                        $contactUuid = session('tenant_uuid') ?? '';
                    @endphp

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Seu nome</label>
                            <input class="form-control" name="name" value="{{ $contactName }}" placeholder="Opcional" {{ $isAuth ? 'readonly' : '' }}>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email de contato</label>
                            <input class="form-control" name="email" value="{{ $contactEmail }}" placeholder="Opcional" {{ $isAuth ? 'readonly' : '' }}>
                        </div>
                        <div class="col-12">
                            <label class="form-label">O que aconteceu?</label>
                            <textarea class="form-control" name="message" rows="3" required placeholder="Descreva o problema com o máximo de detalhes."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Como reproduzir?</label>
                            <textarea class="form-control" name="steps" rows="3" placeholder="Passo a passo (opcional)."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="bugReportSubmitBtn">Enviar relato</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('click', async (event)=>{
        const btn = event.target.closest('#bugReportSubmitBtn')
        if(!btn) return
        const form = document.getElementById('bugReportForm')
        if(!form) return

        const successEl = document.getElementById('bugReportSuccess')
        const errorEl = document.getElementById('bugReportError')
        successEl?.classList.add('d-none')
        if(errorEl){
            errorEl.classList.add('d-none')
            errorEl.textContent = ''
        }

        const data = new FormData(form)
        data.append('url', window.location.href)
        data.append('user_agent', navigator.userAgent || '')

        btn.disabled = true
        const prevText = btn.textContent
        btn.textContent = 'Enviando...'

        try{
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            const res = await fetch("{{ route('reports.store') }}", {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: data
            })

            if(res.ok){
                form.reset()
                if(successEl) successEl.classList.remove('d-none')
                const modalEl = document.getElementById('bugReportModal')
                if(modalEl && window.bootstrap?.Modal){
                    setTimeout(()=>{
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide()
                    }, 900)
                }
                return
            }

            const payload = await res.json().catch(()=>null)
            const msg = payload?.message || 'Não foi possível enviar.'
            if(errorEl){
                errorEl.textContent = msg
                errorEl.classList.remove('d-none')
            }
        }catch(e){
            if(errorEl){
                errorEl.textContent = 'Erro de conexão. Tente novamente.'
                errorEl.classList.remove('d-none')
            }
        }finally{
            btn.disabled = false
            btn.textContent = prevText || 'Enviar relato'
        }
    })
</script>
</body>
</html>
