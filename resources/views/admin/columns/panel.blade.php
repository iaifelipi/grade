@php
    $embedded = (bool) ($embedded ?? false);
@endphp
<div class="columns-admin {{ $embedded ? 'columns-admin--embedded' : '' }}" id="columnsAdminPage">
    <div class="columns-admin-hero">
        <div class="columns-admin-hero-text">
            @unless($embedded)
                <h2 class="columns-admin-title">Catálogo de Colunas</h2>
            @endunless
            <p class="columns-admin-subtitle">
                Renomeie, organize e padronize as colunas exibidas no Explore.
                Os ajustes são aplicados ao arquivo ativo.
            </p>
        </div>
        @unless($embedded)
            <div class="columns-admin-actions">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                    Voltar ao Explore
                </a>
            </div>
        @endunless
    </div>

    <div id="columnsAdminContent"
         data-has-source="{{ $sourceId ? '1' : '0' }}"
         data-data-url="{{ route('explore.columns.data') }}"
         data-source-url-base="{{ url('/explore/columns/source') }}"
         data-clear-url="{{ route('explore.columns.clearSource') }}">
        @include('admin.columns.partials.content', ['settings' => $settings, 'currentSource' => $currentSource, 'sourceId' => $sourceId])
    </div>

    <div class="modal fade" id="adminColumnsDataQualityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content explore-dq-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Qualidade de Dados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="adminColumnsDataQualityModalBody" class="explore-dq-modal-body">
                        <div class="explore-dq-modal-loading">
                            <div class="spinner-border text-primary" role="status"></div>
                            <span>Carregando qualidade de dados...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
