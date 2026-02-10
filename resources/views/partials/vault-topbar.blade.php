<div class="explore-toolbar explore-toolbar--topbar vault-standard-toolbar">
    <div class="vault-standard-toolbar__actions ms-auto">
        @unless(request()->routeIs('vault.automation.*'))
            <a
                href="{{ route('home') }}"
                class="btn btn-sm {{ request()->routeIs('home') || request()->routeIs('vault.explore.*') ? 'btn-primary' : 'btn-outline-secondary' }}"
            >
                <i class="bi bi-grid-1x2"></i>
                <span>Explore</span>
            </a>

            @auth
                @if(auth()->user()->hasPermission('leads.view'))
                    <a
                        href="{{ route('vault.sources.index') }}"
                        class="btn btn-sm {{ request()->routeIs('vault.sources.*') ? 'btn-primary' : 'btn-outline-secondary' }}"
                    >
                        <i class="bi bi-collection"></i>
                        <span>Sources</span>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('automation.run'))
                    <a
                        href="{{ route('vault.automation.index') }}"
                        class="btn btn-sm {{ request()->routeIs('vault.automation.*') ? 'btn-primary' : 'btn-outline-secondary' }}"
                    >
                        <i class="bi bi-lightning-charge"></i>
                        <span>Operações</span>
                    </a>
                @else
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-disabled="true" title="Operações indisponível">
                        <i class="bi bi-lightning-charge"></i>
                        <span>Operações</span>
                    </button>
                @endif
            @endauth
        @endunless
    </div>
</div>
