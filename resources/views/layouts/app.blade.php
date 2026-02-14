<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Grade')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body
    class="grade-body"
    data-auth-user-id="{{ auth()->id() ?? 'guest' }}"
    data-theme-pref="{{ auth()->check() ? (auth()->user()->theme ?? 'system') : 'system' }}"
    data-is-admin-route="{{ request()->routeIs('admin.*') ? '1' : '0' }}"
    data-config-rail-available="{{ (request()->routeIs('home') || request()->routeIs('guest.explore.*') || request()->routeIs('vault.sources.*') || request()->routeIs('vault.semantic.*') || request()->routeIs('vault.automation.*')) ? '1' : '0' }}"
>

<div class="grade-layout">
    <main class="grade-main">
        @php
            $showTopbarTools = !request()->routeIs('admin.*') && $__env->hasSection('topbar-tools');

            $showTenantTargetBadge = false;
            $tenantTargetLabel = '';
            $isGlobalSuperReadOnly = false;

            if (auth()->check() && auth()->user()->isSuperAdmin()) {
                $isImpersonating = session()->has('impersonate_user_id') || app()->bound('impersonated_user');
                $hasTenantOverride = session()->has('tenant_uuid_override')
                    && trim((string) session('tenant_uuid_override', '')) !== '';
                $isGlobalSuperReadOnly = !$isImpersonating && !$hasTenantOverride && !request()->routeIs('admin.*');

                $actorTenantUuid = trim((string) (auth()->user()->tenant_uuid ?? ''));
                $activeTenantUuid = trim((string) (app()->bound('tenant_uuid') ? app('tenant_uuid') : session('tenant_uuid', '')));

                if ($actorTenantUuid !== '' && $activeTenantUuid !== '' && $activeTenantUuid !== $actorTenantUuid) {
                    $tenantTargetName = (string) (\App\Models\Tenant::query()->where('uuid', $activeTenantUuid)->value('name') ?? '');
                    $tenantTargetLabel = $tenantTargetName !== '' ? $tenantTargetName : $activeTenantUuid;
                    $showTenantTargetBadge = true;
                }
            }
        @endphp
        <header class="grade-topbar {{ $showTopbarTools ? 'has-tools' : '' }}">
            <div class="grade-topbar-left"></div>

            <div class="grade-topbar-prompt-disabled" aria-hidden="true">
                <div class="grade-topbar-prompt-shell" aria-disabled="true">
                    <span class="grade-topbar-prompt-icon">
                        <i class="bi bi-plus-lg"></i>
                    </span>
                    <input
                        type="text"
                        class="grade-topbar-prompt-input"
                        value=""
                        placeholder="Pergunte alguma coisa"
                        disabled
                        tabindex="-1"
                    >
                    <span class="grade-topbar-prompt-icon grade-topbar-prompt-icon--muted">
                        <i class="bi bi-mic"></i>
                    </span>
                    <span class="grade-topbar-prompt-send">
                        <i class="bi bi-soundwave"></i>
                    </span>
                </div>
            </div>

            @if(request()->routeIs('admin.*'))
                @include('partials.admin-topnav')
            @endif

            <div class="grade-user-menu">
                @if($showTenantTargetBadge)
                    <span class="grade-tenant-target-badge" title="Tenant UUID: {{ $activeTenantUuid }}">
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

	                        $userHandle = auth()->user()?->username
	                            ? '@' . auth()->user()->username
	                            : '@u' . auth()->id();

	                        $userAvatarPath = (string) (auth()->user()->avatar_path ?? '');
	                        $userAvatarUrl = $userAvatarPath !== '' ? asset('storage/' . ltrim($userAvatarPath, '/')) : null;
	                    @endphp
	                    <button class="grade-user-btn" type="button" data-user-toggle aria-expanded="false">
	                        <span class="grade-user-avatar" title="{{ $userDisplayName }}">
	                            @if($userAvatarUrl)
	                                <img src="{{ $userAvatarUrl }}" alt="">
	                            @else
	                                {{ $userInitials ?: 'U' }}
	                            @endif
	                        </span>
	                    </button>
			                    <div class="grade-user-dropdown" data-user-menu>
			                    <button type="button" class="grade-user-profile" data-bs-toggle="modal" data-bs-target="#editProfileModal">
			                        <div class="grade-user-profile-avatar" aria-hidden="true">
			                            @if($userAvatarUrl)
			                                <img src="{{ $userAvatarUrl }}" alt="">
			                            @else
			                                {{ $userInitials ?: 'U' }}
			                            @endif
			                        </div>
			                        <div class="grade-user-profile-meta">
			                            <div class="grade-user-profile-name">{{ $userDisplayName }}</div>
			                            <div class="grade-user-profile-handle">{{ $userHandle }}</div>
			                        </div>
			                    </button>
			                    <div class="grade-user-divider"></div>

					                    <button type="button" class="grade-user-item" data-bs-toggle="modal" data-bs-target="#gradeSettingsModal" data-settings-open-tab="account">
					                        <i class="bi bi-rocket-takeoff"></i>
					                        Fazer upgrade do plano
					                    </button>
					                    <button type="button" class="grade-user-item" data-bs-toggle="modal" data-bs-target="#gradeSettingsModal" data-settings-open-tab="personalization">
					                        <i class="bi bi-palette2"></i>
					                        Personalização
					                    </button>
					                    <button type="button" class="grade-user-item" data-bs-toggle="modal" data-bs-target="#gradeSettingsModal" data-settings-open-tab="general">
					                        <i class="bi bi-gear"></i>
					                        Configurações
					                    </button>
					                    @if(auth()->check() && auth()->user()->isSuperAdmin())
					                        <a href="{{ route('admin.users.index') }}" class="grade-user-item">
					                            <i class="bi bi-shield-lock"></i>
					                            Admin
					                        </a>
					                    @endif
			                    <div class="grade-user-divider"></div>
					                    <a href="{{ route('home') }}" class="grade-user-item">
					                        <i class="bi bi-house-door"></i>
					                        Início
					                    </a>
					                    @if(auth()->check() && auth()->user()->isSuperAdmin() && isset($impersonated_user) && $impersonated_user)
					                        <form method="POST" action="{{ route('admin.impersonate.stop') }}">
					                            @csrf
					                        <button class="grade-user-item" type="submit"><i class="bi bi-person-x"></i>Encerrar impersonação</button>
				                    </form>
				                    @endif
				                    <div class="grade-user-flyout-wrap">
				                        <button type="button" class="grade-user-item grade-user-item-summary">
				                            <i class="bi bi-question-circle"></i>
				                            Ajuda
				                            <i class="bi bi-chevron-right grade-user-item-arrow" aria-hidden="true"></i>
				                        </button>
				                        <div class="grade-user-flyout">
				                            <button type="button" class="grade-user-item" data-user-action="help">
				                                <i class="bi bi-question-circle"></i>
				                                Central de Ajuda
				                            </button>
				                            <button type="button" class="grade-user-item" data-user-action="news">
				                                <i class="bi bi-stars"></i>
				                                Novidades
				                            </button>
				                            <a href="{{ route('legal.terms') }}" class="grade-user-item">
				                                <i class="bi bi-file-text"></i>
				                                Termos e Política
				                            </a>
				                            <button type="button" class="grade-user-item" data-bs-toggle="modal" data-bs-target="#bugReportModal">
				                                <i class="bi bi-bug"></i>
				                                Informar bug
				                            </button>
				                            <button type="button" class="grade-user-item" data-user-action="desktop">
				                                <i class="bi bi-laptop"></i>
				                                Baixar aplicativos
				                            </button>
				                            <button type="button" class="grade-user-item" data-user-action="shortcuts">
				                                <i class="bi bi-keyboard"></i>
				                                Atalhos de teclado
				                            </button>
				                        </div>
				                    </div>
					                    <button class="grade-user-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
					                        <i class="bi bi-box-arrow-right"></i>
					                        Sair
					                    </button>
                </div>
                @endauth

	                @guest
	                    <button class="grade-user-btn grade-user-btn--guest" type="button" data-user-toggle aria-expanded="false">
	                        <span class="grade-user-avatar grade-user-avatar--guest" title="Menu">
	                            <i class="bi bi-list"></i>
                        </span>
                    </button>
	                    <div class="grade-user-dropdown" data-user-menu>
		                        <a href="{{ route('home') }}" class="grade-user-brand grade-user-brand--logo" aria-label="Ir para início">
		                            <span class="grade-user-brand-stack">
		                                <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
		                                <span>Grade</span>
		                            </span>
		                        </a>
			                        <div class="grade-user-divider"></div>
				                        <a href="{{ route('home') }}" class="grade-user-item">
				                            <i class="bi bi-house-door"></i>
				                            Home
				                        </a>
			                        <div class="grade-user-flyout-wrap">
			                            <button type="button" class="grade-user-item grade-user-item-summary">
			                                <i class="bi bi-question-circle"></i>
			                                Ajuda
		                                <i class="bi bi-chevron-right grade-user-item-arrow" aria-hidden="true"></i>
		                            </button>
		                            <div class="grade-user-flyout">
		                                <button type="button" class="grade-user-item" data-user-action="help">
		                                    <i class="bi bi-question-circle"></i>
		                                    Central de Ajuda
		                                </button>
		                                <button type="button" class="grade-user-item" data-user-action="news">
		                                    <i class="bi bi-stars"></i>
		                                    Novidades
		                                </button>
		                                <a href="{{ route('legal.terms') }}" class="grade-user-item">
		                                    <i class="bi bi-file-text"></i>
		                                    Termos e Política
		                                </a>
		                                <button type="button" class="grade-user-item" data-bs-toggle="modal" data-bs-target="#bugReportModal">
		                                    <i class="bi bi-bug"></i>
		                                    Informar bug
		                                </button>
		                                <button type="button" class="grade-user-item" data-user-action="desktop">
		                                    <i class="bi bi-laptop"></i>
		                                    Baixar aplicativos
		                                </button>
		                                <button type="button" class="grade-user-item" data-user-action="shortcuts">
		                                    <i class="bi bi-keyboard"></i>
		                                    Atalhos de teclado
		                                </button>
		                            </div>
		                        </div>
		                        <div class="grade-user-divider"></div>
		                        <button type="button" class="grade-user-item" data-bs-toggle="modal" data-bs-target="#authLoginModal">
		                            <i class="bi bi-box-arrow-in-right"></i>
	                            Entrar
	                        </button>
		                        <button type="button" class="grade-user-item" data-bs-toggle="modal" data-bs-target="#authRegisterModal">
		                            <i class="bi bi-person-plus"></i>
	                            Inscreva-se
	                        </button>
                    </div>
                @endguest
            </div>

            @if($showTopbarTools)
                <div class="grade-topbar-tools">
                    @yield('topbar-tools')
                </div>
            @endif

            @if(auth()->check() && auth()->user()->isSuperAdmin() && isset($impersonated_user) && $impersonated_user)
                <div class="grade-impersonation-badge">
                    <span>Impersonando: {{ $impersonated_user->name }}</span>
                    <form method="POST" action="{{ route('admin.impersonate.stop') }}">
                        @csrf
                        <button type="submit" class="grade-impersonation-stop">Encerrar</button>
                    </form>
                </div>
            @endif

	        </header>

	        @auth
	            <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
	                <div class="modal-dialog modal-dialog-centered">
	                    <div class="modal-content border-0 shadow rounded-4 grade-edit-profile-modal">
	                        <div class="modal-header border-0 pb-0">
	                            <h5 class="modal-title">Editar perfil</h5>
	                        </div>
	                        <form id="editProfileForm" method="POST" action="{{ route('profile.modal.update') }}" enctype="multipart/form-data">
	                            @csrf
	                            @method('PATCH')
	                            <div class="modal-body pt-2">
	                                <div class="grade-edit-profile-avatar-wrap">
	                                    <div class="grade-edit-profile-avatar" id="editProfileAvatarPreview">
	                                        @if($userAvatarUrl)
	                                            <img src="{{ $userAvatarUrl }}" alt="">
	                                        @else
	                                            {{ $userInitials ?: 'U' }}
	                                        @endif
	                                    </div>
	                                    <button class="grade-edit-profile-avatar-btn" type="button" id="editProfileAvatarBtn" aria-label="Alterar foto">
	                                        <i class="bi bi-camera"></i>
	                                    </button>
	                                    <input type="file" class="d-none" id="editProfileAvatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/avif">
	                                </div>

	                                <div class="mt-3 grade-profile-field">
	                                    <label class="grade-profile-field-box">
	                                        <span class="grade-profile-field-kicker">Nome de exibição</span>
	                                        <input class="grade-profile-field-input" name="name" value="{{ $userDisplayName }}" maxlength="255" required>
	                                    </label>
	                                </div>

	                                <div class="mt-2 grade-profile-field">
	                                    <label class="grade-profile-field-box">
	                                        <span class="grade-profile-field-kicker">Nome de usuário</span>
	                                        <input class="grade-profile-field-input" name="username" value="{{ ltrim($userHandle, '@') }}" maxlength="64" placeholder="seuusuario">
	                                    </label>
	                                    <div class="form-text">Seu perfil ajuda as pessoas a reconhecerem você. O nome de usuário aparece como <code>@usuario</code>.</div>
	                                </div>
	                            </div>
	                            <div class="modal-footer border-0">
	                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
	                                <button type="submit" class="btn btn-dark" id="editProfileSubmitBtn">Salvar</button>
	                            </div>
	                        </form>
	                    </div>
	                </div>
	            </div>
	        @endauth

	        @if(!request()->routeIs('admin.*') && (request()->routeIs('home') || request()->routeIs('guest.explore.*') || request()->routeIs('vault.sources.*') || request()->routeIs('vault.semantic.*') || request()->routeIs('vault.automation.*')))
	            <aside class="grade-config-rail" id="gradeConfigRail" aria-label="Painel de controle">
	                <div class="grade-config-rail-actions">
	                    @if(request()->routeIs('vault.sources.*') || request()->routeIs('vault.semantic.*') || request()->routeIs('vault.automation.*'))
	                        <button type="button" class="grade-config-rail-btn" title="Busca indisponível nesta tela" disabled aria-disabled="true">
	                            <i class="bi bi-search"></i>
	                            <span>Busca</span>
	                        </button>
                        <button type="button" class="grade-config-rail-btn" title="Limpar indisponível nesta tela" disabled aria-disabled="true">
                            <i class="bi bi-eraser"></i>
                            <span>Limpar</span>
                        </button>
                        <div class="grade-config-rail-separator" aria-hidden="true"></div>
                        <button type="button" class="grade-config-rail-btn" title="Colunas indisponível nesta tela" disabled aria-disabled="true">
                            <i class="bi bi-columns-gap"></i>
                            <span>Colunas</span>
                        </button>
                        <button type="button" class="grade-config-rail-btn" title="Dados indisponível nesta tela" disabled aria-disabled="true">
                            <i class="bi bi-database"></i>
                            <span>Dados</span>
                        </button>
                        <button type="button" class="grade-config-rail-btn" title="Semântica indisponível nesta tela" disabled aria-disabled="true">
                            <i class="bi bi-diagram-3"></i>
                            <span>Semântica</span>
                        </button>
                        <button type="button" class="grade-config-rail-btn" title="Publicar indisponível nesta tela" disabled aria-disabled="true">
                            <i class="bi bi-save"></i>
                            <span>Publicar</span>
                        </button>
                        <div class="grade-config-rail-separator" aria-hidden="true"></div>
                        @if(auth()->check() && auth()->user()->hasPermission('automation.run'))
                            @if(request()->routeIs('home') || request()->routeIs('vault.explore.*') || request()->routeIs('guest.explore.*'))
                                <button type="button" class="grade-config-rail-btn" title="Operações" data-bs-toggle="modal" data-bs-target="#exploreMarketingWizardModal" aria-controls="exploreMarketingWizardModal">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </button>
                            @else
                                <a href="{{ route('vault.automation.index') }}" class="grade-config-rail-btn {{ request()->routeIs('vault.automation.*') ? 'is-active' : '' }}" title="Operações">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </a>
                            @endif
                        @else
                            <button type="button" class="grade-config-rail-btn" title="Operações indisponível" disabled aria-disabled="true">
                                <i class="bi bi-lightning-charge"></i>
                                <span>Operações</span>
                            </button>
                        @endif
                        <button type="button" class="grade-config-rail-btn" title="Exportar indisponível nesta tela" disabled aria-disabled="true">
                            <i class="bi bi-download"></i>
                            <span>Exportar</span>
                        </button>
                    @else
                        <button type="button" class="grade-config-rail-btn" data-config-rail-action="search" title="Busca">
                            <i class="bi bi-search"></i>
                            <span>Busca</span>
                        </button>
                        <button type="button" class="grade-config-rail-btn" data-config-rail-action="clear" title="Limpar filtros">
                            <i class="bi bi-eraser"></i>
                            <span>Limpar</span>
                        </button>
                        <div class="grade-config-rail-separator" aria-hidden="true"></div>
                        <button type="button" class="grade-config-rail-btn" data-config-rail-action="columns" title="Colunas">
                            <i class="bi bi-columns-gap"></i>
                            <span>Colunas</span>
                        </button>
                        <button type="button" class="grade-config-rail-btn" data-config-rail-action="data" title="Dados">
                            <i class="bi bi-database"></i>
                            <span>Dados</span>
                        </button>
                        <button type="button" class="grade-config-rail-btn" data-config-rail-action="semantic" title="Mapa semântico">
                            <i class="bi bi-diagram-3"></i>
                            <span>Semântica</span>
                        </button>
                        <button type="button" class="grade-config-rail-btn" data-config-rail-action="save" title="Publicar alterações" id="railSaveBtn">
                            <i class="bi bi-save"></i>
                            <span>Publicar <span class="grade-config-rail-badge d-none" id="railSaveBadge">0</span></span>
                        </button>
                        <div class="grade-config-rail-separator" aria-hidden="true"></div>
                        @if(auth()->check() && auth()->user()->hasPermission('automation.run'))
                            @if(request()->routeIs('home') || request()->routeIs('vault.explore.*') || request()->routeIs('guest.explore.*'))
                                <button type="button" class="grade-config-rail-btn" title="Operações" data-bs-toggle="modal" data-bs-target="#exploreMarketingWizardModal" aria-controls="exploreMarketingWizardModal">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </button>
                            @else
                                <a href="{{ route('vault.automation.index') }}" class="grade-config-rail-btn {{ request()->routeIs('vault.automation.*') ? 'is-active' : '' }}" title="Operações">
                                    <i class="bi bi-lightning-charge"></i>
                                    <span>Operações</span>
                                </a>
                            @endif
                        @else
                            <button type="button" class="grade-config-rail-btn" title="Operações indisponível" disabled aria-disabled="true">
                                <i class="bi bi-lightning-charge"></i>
                                <span>Operações</span>
                            </button>
                        @endif
                        <button type="button" class="grade-config-rail-btn" data-config-rail-action="export" title="Exportar">
                            <i class="bi bi-download"></i>
                            <span>Exportar</span>
                        </button>
	                    @endif
	                    <div class="grade-config-rail-separator" aria-hidden="true"></div>
	                    <a href="{{ route('home') }}" class="grade-config-rail-brand" aria-label="Ir para início" data-brand-link>
	                        <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
	                        <span>Grade</span>
	                    </a>
	                </div>
	            </aside>
	        @endif

        <section class="grade-content">
            @if(request()->routeIs('admin.*') && $isGlobalSuperReadOnly)
                <div id="adminGlobalReadOnlyBanner" class="grade-admin-global-banner" role="status" aria-live="polite">
                    <div class="grade-admin-global-banner-title">Modo global (somente leitura)</div>
                    <div class="grade-admin-global-banner-body">
                        Para editar dados, selecione uma conta alvo (tenant) ou use impersonacao.
                    </div>
                    <div class="grade-admin-global-banner-actions">
                        <a class="btn btn-sm btn-primary" href="{{ route('admin.users.index') }}">Selecionar conta</a>
                        <a class="btn btn-sm btn-light" href="{{ route('admin.users.index', ['clear_tenant_override' => 1]) }}">Limpar selecao</a>
                    </div>
                </div>
            @endif
            @yield('content')
        </section>
    </main>
</div>

<div class="modal fade" id="gradeBrandModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow rounded-4 grade-brand-modal">
            <div class="modal-body text-center">
                <div class="grade-brand-modal-logo">
                    <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
                </div>
                <div class="grade-brand-modal-name">Grade</div>
                <div class="grade-brand-modal-kicker">Transforme dados em ação.</div>
                <div class="grade-brand-modal-divider"></div>
                <div class="grade-brand-modal-rights">2026, Felipi. Todos os direitos reservados.</div>
            </div>
        </div>
    </div>
</div>

@auth
    <form id="logoutConfirmForm" method="POST" action="{{ route('logout') }}" class="d-none">
        @csrf
    </form>

    <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 logout-confirm-modal">
                <div class="modal-body">
                    <h5 class="logout-confirm-title">Tem certeza de que deseja sair?</h5>
                    <p class="logout-confirm-subtitle">
                        Você sairá da conta<br>{{ auth()->user()->email }}
                    </p>
                    <div class="logout-confirm-actions">
                        <button type="submit" form="logoutConfirmForm" class="btn btn-light">Sair</button>
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endauth

@guest
    <div class="modal fade" id="authLoginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 grade-auth-compact-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Entrar</h5>
                </div>
                <div class="modal-body pt-2">
                    @include('auth.partials.login-form')
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="authRegisterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 grade-auth-compact-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Registrar</h5>
                </div>
                <div class="modal-body pt-2">
                    @include('auth.partials/register-form')
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="authForgotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 grade-auth-compact-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Recuperar senha</h5>
                </div>
                <div class="modal-body pt-2">
                    @include('auth.partials.forgot-password-form')
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
            const registerEl = document.getElementById('authRegisterModal')
            if(!loginEl || !window.bootstrap?.Modal) return
            const loginModal = window.bootstrap.Modal.getOrCreateInstance(loginEl)
            const registerModal = registerEl ? window.bootstrap.Modal.getOrCreateInstance(registerEl) : null
            const forgotEl = document.getElementById('authForgotModal')
            const forgotModal = forgotEl ? window.bootstrap.Modal.getOrCreateInstance(forgotEl) : null
            if(target === 'login'){
                registerModal?.hide()
                forgotModal?.hide()
                loginModal.show()
            }else if(target === 'register'){
                loginModal.hide()
                forgotModal?.hide()
                registerModal?.show()
            }else if(target === 'forgot'){
                loginModal.hide()
                registerModal?.hide()
                forgotModal?.show()
            }
        })

        document.addEventListener('submit', async (event)=>{
            const form = event.target.closest('form[data-auth-form]')
            if(!form) return
            event.preventDefault()
            const startedAt = Date.now()
            const waitMinSubmitFx = async ()=>{
                const elapsed = Date.now() - startedAt
                const remaining = Math.max(0, 2000 - elapsed)
                if(!remaining) return
                await new Promise((resolve)=>setTimeout(resolve, remaining))
            }
            const errorsEl = form.querySelector('[data-auth-errors]')
            if(errorsEl){
                errorsEl.classList.add('d-none')
                errorsEl.innerHTML = ''
            }
            const submitBtn = form.querySelector('[data-auth-submit]')
            if(submitBtn){
                submitBtn.disabled = true
                submitBtn.classList.add('is-submitting')
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
                    await waitMinSubmitFx()
                    window.location.href = res.url
                    return
                }
                if(res.ok){
                    try{
                        const data = await res.json()
                        if(data?.redirect){
                            await waitMinSubmitFx()
                            window.location.href = data.redirect
                            return
                        }
                    }catch(e){}
                    await waitMinSubmitFx()
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
                    submitBtn.classList.remove('is-submitting')
                }
            }
        })
    </script>
	@endguest

	<div class="modal fade" id="gradeSettingsModal" tabindex="-1" aria-hidden="true">
	    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
	        <div class="modal-content border-0 shadow rounded-4 grade-settings-modal">
	            <div class="modal-header border-0 pb-0">
	                <div>
	                    <h5 class="modal-title">Configurações</h5>
	                    @auth
	                        <div class="text-muted small">{{ auth()->user()->name ?? 'Conta' }}</div>
	                    @endauth
	                </div>
	                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
	            </div>
	            <div class="modal-body pt-3">
	                <div class="grade-settings-shell">
	                    <div class="grade-settings-nav" role="navigation" aria-label="Seções">
	                        <button type="button" class="grade-settings-nav-item is-active" data-settings-tab="general">
	                            <i class="bi bi-gear"></i>Geral
	                        </button>
	                        <button type="button" class="grade-settings-nav-item" data-settings-tab="notifications">
	                            <i class="bi bi-bell"></i>Notificações
	                        </button>
	                        <button type="button" class="grade-settings-nav-item" data-settings-tab="personalization">
	                            <i class="bi bi-palette2"></i>Personalização
	                        </button>
	                        <button type="button" class="grade-settings-nav-item" data-settings-tab="apps">
	                            <i class="bi bi-grid"></i>Aplicativos
	                        </button>
	                        <button type="button" class="grade-settings-nav-item" data-settings-tab="schedules">
	                            <i class="bi bi-calendar3"></i>Agendamentos
	                        </button>
	                        <button type="button" class="grade-settings-nav-item" data-settings-tab="data">
	                            <i class="bi bi-database"></i>Controlar dados
	                        </button>
	                        <button type="button" class="grade-settings-nav-item" data-settings-tab="security">
	                            <i class="bi bi-shield-check"></i>Segurança
	                        </button>
	                        <button type="button" class="grade-settings-nav-item" data-settings-tab="account">
	                            <i class="bi bi-person-circle"></i>Conta
	                        </button>
	                    </div>

	                    <div class="grade-settings-content">
	                        <div class="grade-settings-panel is-active" data-settings-panel="general">
	                            <div class="grade-settings-panel-title">Geral</div>

	                            <div class="grade-settings-card">
	                                <div class="grade-settings-card-title">Aparência</div>
	                                <div class="grade-settings-row">
	                                    <div class="grade-settings-row-label">Tema escuro</div>
	                                    <button type="button" class="grade-user-switch" id="userThemeToggle" aria-pressed="false">
	                                        <span class="grade-user-switch-knob" aria-hidden="true"></span>
	                                    </button>
	                                </div>
	                            </div>

	                            <div class="grade-settings-card">
	                                <div class="grade-settings-card-title">Experiência</div>
	                                <div class="grade-settings-row">
	                                    <div class="grade-settings-row-label">Painel de controle</div>
	                                    <button type="button" class="grade-user-switch" id="userConfigPanelToggle" aria-pressed="false">
	                                        <span class="grade-user-switch-knob" aria-hidden="true"></span>
	                                    </button>
	                                </div>
	                                <div class="grade-settings-row">
	                                    <label class="grade-settings-row-label" for="userLanguageSelect">Idioma</label>
	                                    <select id="userLanguageSelect" class="grade-settings-select">
	                                        <option value="pt-BR">Português (BR)</option>
	                                        <option value="en-US">English</option>
	                                        <option value="es-ES">Español</option>
	                                    </select>
	                                </div>
	                                <div class="grade-settings-row">
	                                    <div class="grade-settings-row-label">Atalhos de teclado</div>
	                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-user-action="shortcuts">Ver atalhos</button>
	                                </div>
	                            </div>
	                        </div>

	                        <div class="grade-settings-panel" data-settings-panel="notifications">
	                            <div class="grade-settings-panel-title">Notificações</div>
	                            <div class="text-muted">Em breve.</div>
	                        </div>

	                        <div class="grade-settings-panel" data-settings-panel="personalization">
	                            <div class="grade-settings-panel-title">Personalização</div>
	                            <div class="text-muted">Em breve.</div>
	                        </div>

	                        <div class="grade-settings-panel" data-settings-panel="apps">
	                            <div class="grade-settings-panel-title">Aplicativos</div>
	                            <div class="grade-settings-card">
	                                <div class="grade-settings-card-title">Downloads</div>
	                                <div class="grade-settings-row">
	                                    <div class="grade-settings-row-label">Baixar aplicativos</div>
	                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-user-action="desktop">Abrir</button>
	                                </div>
	                            </div>
	                        </div>

	                        <div class="grade-settings-panel" data-settings-panel="schedules">
	                            <div class="grade-settings-panel-title">Agendamentos</div>
	                            <div class="text-muted">Em breve.</div>
	                        </div>

	                        <div class="grade-settings-panel" data-settings-panel="data">
	                            <div class="grade-settings-panel-title">Controlar dados</div>
	                            <div class="text-muted">Em breve.</div>
	                        </div>

	                        <div class="grade-settings-panel" data-settings-panel="security">
	                            <div class="grade-settings-panel-title">Segurança</div>
	                            <div class="grade-settings-card">
	                                <div class="grade-settings-card-title">Central de Ajuda</div>
	                                <div class="grade-settings-row">
	                                    <div class="grade-settings-row-label">Ajuda</div>
	                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-user-action="help">Abrir</button>
	                                </div>
	                            </div>
	                            <div class="text-muted small">Configurações avançadas de segurança podem ser adicionadas aqui (AMF/MFA, sessões, etc).</div>
	                        </div>

	                        <div class="grade-settings-panel" data-settings-panel="account">
	                            <div class="grade-settings-panel-title">Conta</div>

	                            @auth
	                                <div class="grade-settings-card">
	                                    <div class="grade-settings-card-title">Sua conta</div>
	                                    <div class="grade-settings-row">
	                                        <div class="grade-settings-row-label">Perfil</div>
	                                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('profile.edit') }}">Abrir</a>
	                                    </div>
	                                    <div class="grade-settings-row">
	                                        <div class="grade-settings-row-label">Configurações e cobrança</div>
	                                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('profile.edit') }}#profile-preferences">Abrir</a>
	                                    </div>
	                                </div>

	                                @if(auth()->check() && auth()->user()->isSuperAdmin())
	                                    <div class="grade-settings-card">
	                                        <div class="grade-settings-card-title">Admin</div>
	                                        <div class="grade-settings-actions">
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.users.index') }}">Usuários</a>
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ url('/admin/customers') }}">Clientes</a>
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ url('/admin/customers/user-groups') }}">Grupos de Clientes</a>
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ url('/admin/users/user-groups') }}">Grupos de Usuários</a>
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.customers.subscriptions.index') }}">Assinaturas</a>
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.semantic.index') }}">Semântica</a>
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.monitoring.index') }}">Monitoramento</a>
	                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.security.index') }}">Segurança</a>
	                                        </div>
	                                    </div>
	                                @endif

	                                <div class="grade-settings-card">
	                                    <div class="grade-settings-card-title">Ajuda e legal</div>
	                                    <div class="grade-settings-actions">
	                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-user-action="news">Novidades</button>
	                                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('legal.terms') }}">Termos e Política</a>
	                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-settings-open-modal="#bugReportModal">Informar bug</button>
	                                    </div>
	                                </div>

	                                <div class="grade-settings-card">
	                                    <div class="grade-settings-card-title">Sessão</div>
	                                    <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
	                                        <i class="bi bi-box-arrow-right"></i>
	                                        Sair
	                                    </button>
	                                </div>
	                            @endauth

	                            @guest
	                                <div class="text-muted">Faça login para ver configurações da conta.</div>
	                            @endguest
	                        </div>
	                    </div>
	                </div>
	            </div>
	        </div>
	    </div>
	</div>

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
