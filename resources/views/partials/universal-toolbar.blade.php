@php($inTopbar = (bool) ($inTopbar ?? false))
{{-- ======================================================
   UNIVERSAL TOOLBAR
   Reutilizável nas views que precisarem de busca/filtros/contadores.
======================================================= --}}
<div class="explore-toolbar {{ $inTopbar ? 'explore-toolbar--topbar' : '' }}">

    <input
        id="searchInput"
        type="hidden"
        value=""
    >


    <div class="explore-toolbar-actions ms-auto">
        <div class="explore-chip-group">
            <div class="explore-filter-inline">
                {{-- SOURCE FILTER --}}
                <div class="source-combo">
                    <div class="source-select-wrap grade-field-box grade-field-box--compact source-select-field">
                        <select
                            id="sourceSelect"
                            class="form-select grade-field-input grade-field-input--select source-select-input"
                        >
                            <option value="">Todos os arquivos</option>
                        </select>
                    </div>

                    @if((auth()->check() && auth()->user()?->hasPermission('leads.import')) || ($isGuest ?? false))
                        <button
                            id="openExploreImportBtn"
                            type="button"
                            class="btn btn-primary explore-add-source-btn"
                            data-grade-tooltip="1"
                            data-bs-placement="bottom"
                            data-bs-custom-class="explore-top-tooltip"
                            title="Abrir painel de arquivos e importação"
                            aria-label="Importar arquivos"
                            data-bs-toggle="modal"
                            data-bs-target="#exploreImportModal"
                        >
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    @endif
                </div>

            </div>

            <div class="explore-filter-dropdown dropdown">
                <button
                    class="btn btn-outline-secondary dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    Filtros
                </button>
                <div class="dropdown-menu dropdown-menu-end explore-filter-menu p-3 grade-menu-pattern">
                    <label class="grade-field-box grade-field-box--compact mb-3">
                        <select id="sourceSelectMobile" class="form-select grade-field-input grade-field-input--select">
                            <option value="">Todos os arquivos</option>
                        </select>
                    </label>

                    <button
                        id="clearFiltersBtnMobile"
                        class="btn btn-outline-secondary w-100"
                        type="button"
                        data-bs-toggle="tooltip"
                        data-bs-placement="bottom"
                        title="Limpa busca/filtros e restaura colunas"
                    >
                        Limpar
                    </button>
                </div>
            </div>

            {{-- CONFIGURAÇÃO --}}
            <div class="explore-config-group">
                <span class="explore-config-label">Configuração</span>
                <div class="explore-config-actions">
                    <div class="dropdown">
                        <button
                            id="saveMenuBtn"
                            class="btn btn-outline-success dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Salvar
                            <span class="badge text-bg-light ms-1 d-none" id="saveMenuBadge">0</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end grade-menu-pattern">
                            <li><button class="dropdown-item" type="button" id="viewOverridesBtn">Ver alterações</button></li>
                            <li><button class="dropdown-item" type="button" id="publishOverridesBtn">Publicar alterações</button></li>
                            <li><button class="dropdown-item text-danger" type="button" id="discardOverridesBtn">Descartar alterações</button></li>
                        </ul>
                    </div>
                    <button
                        id="columnsBtn"
                        class="btn btn-outline-secondary"
                        type="button"
                        data-grade-tooltip="1"
                        data-bs-placement="bottom"
                        data-bs-custom-class="explore-top-tooltip"
                        title="Escolher colunas visíveis"
                    >
                        Colunas
                    </button>
                    <button
                        class="btn btn-outline-secondary"
                        id="dataQualityBtn"
                        data-data-quality-modal="1"
                        data-modal-url="{{ route('explore.dataQuality.modal') }}"
                        data-grade-tooltip="1"
                        data-bs-placement="bottom"
                        data-bs-custom-class="explore-top-tooltip"
                        title="Abrir qualidade de dados"
                        type="button"
                    >
                        Dados
                    </button>
                    <button
                        id="clearFiltersBtn"
                        class="btn btn-outline-secondary"
                        type="button"
                        data-grade-tooltip="1"
                        data-bs-placement="bottom"
                        data-bs-custom-class="explore-top-tooltip"
                        title="Limpar filtros e restaurar visual padrão"
                    >
                        Limpar
                    </button>
                <div class="explore-semantic-inline-wrap">
                    <div class="explore-semantic-inline">
                        <button
                            id="semanticBtn"
                            class="btn btn-outline-secondary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#semanticModal"
                            aria-label="Abrir ID semântica"
                        >
                            ID
                        </button>
                        <span class="semantic-pill semantic-pill--anchor d-none">não definida</span>
                    </div>
                    <div class="explore-semantic-top-hover">
                        <div class="explore-semantic-top-hover-title">Mapa semântico do arquivo</div>
                        <div class="explore-semantic-top-hover-list"></div>
                    </div>
                </div>
            </div>
            </div>

            <div class="explore-config-dropdown dropdown">
                <button
                    class="btn btn-outline-secondary dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    Configuração
                </button>
                <ul class="dropdown-menu dropdown-menu-end grade-menu-pattern">
                    <li>
                        <button class="dropdown-item" type="button" id="columnsBtnMobile">
                            Colunas
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" type="button" id="viewOverridesBtnMobile">
                            Ver alterações
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" type="button" id="publishOverridesBtnMobile">
                            Publicar alterações
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item text-danger" type="button" id="discardOverridesBtnMobile">
                            Descartar alterações
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#semanticModal">
                            ID Semântica
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" type="button" id="dataQualityBtnMobile" data-data-quality-modal="1" data-modal-url="{{ route('explore.dataQuality.modal') }}">
                            Dados
                        </button>
                    </li>
                </ul>
            </div>

            <div class="explore-action-divider" aria-hidden="true"></div>


            <div class="explore-actions-group">
                <span class="explore-actions-label">Ações</span>
                <div class="dropdown explore-actions-top">
                    <button
                        id="executeBtn"
                        class="btn btn-success explore-export-btn dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="Abrir disparos"
                    >
                        <i class="bi bi-lightning-charge"></i>
                        Executar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end grade-menu-pattern">
                        <li>
                            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#actionsManualWizardModal">
                                <i class="bi bi-pencil-square"></i>
                                Manual
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#actionsAutoWizardModal">
                                <i class="bi bi-robot"></i>
                                Automatizado
                            </button>
                        </li>
                    </ul>
                </div>
                {{-- EXPORT CSV --}}
                <button
                    id="exportBtn"
                    class="btn btn-success explore-export-btn"
                    data-bs-toggle="tooltip"
                    data-bs-placement="bottom"
                    data-bs-custom-class="explore-top-tooltip"
                    title="Exportar resultado atual"
                    aria-label="Exportar resultado atual"
                >
                    <i class="bi bi-download"></i>
                </button>
            </div>
        </div>

    </div>

</div>
