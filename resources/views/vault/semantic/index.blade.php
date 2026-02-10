@extends('layouts.vault')

@section('page-title','Semantic Manager')

@section('content')

<div class="vault-standard-header">
    <div>
        <h4 class="vault-standard-header__title">Semantic</h4>
        <p class="vault-standard-header__subtitle">Classificação e identidade semântica das fontes para segmentações inteligentes.</p>
    </div>
</div>

<div class="semantic-page">

<div class="row g-4 h-100">

    {{-- =======================================================
        LEFT — SOURCES LIST
    ======================================================== --}}
    <div class="col-md-4">

        <div class="vault-card semantic-sources">

            <div class="p-3 border-bottom">
                <input type="search"
                       id="sourceSearch"
                       class="form-control"
                       placeholder="Buscar source...">
            </div>

            <ul id="sourcesList" class="list-group list-group-flush semantic-list">
                {{-- AJAX --}}
            </ul>

        </div>

    </div>



    {{-- =======================================================
        RIGHT — EDITOR
    ======================================================== --}}
    <div class="col-md-8">

        <div class="vault-card p-4 h-100 d-flex flex-column">

            <div id="semanticEmpty" class="semantic-empty">
                <i class="bi bi-diagram-3 fs-1 text-muted"></i>
                <p class="mt-3 text-muted">
                    Selecione uma source para configurar a semântica
                </p>
            </div>


            <div id="semanticEditor" class="d-none flex-grow-1">

                {{-- HEADER --}}
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 id="editorTitle" class="mb-0"></h5>

                    <button id="btnSaveSemantic" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Salvar
                    </button>
                </div>



                {{-- CORE FIELDS --}}
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Segmento</label>
                        <select id="segmentSelect" class="form-select"></select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Nicho</label>
                        <select id="nicheSelect" class="form-select"></select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Origem</label>
                        <select id="originSelect" class="form-select"></select>
                    </div>

                </div>



                {{-- LOCATIONS --}}
                <div class="mt-4">

                    <label class="form-label">Localizações</label>

                    <input id="locationInput"
                           type="text"
                           class="form-control"
                           placeholder="Digite cidade ou estado e pressione Enter">

                    <div id="locationChips" class="semantic-chips mt-2"></div>

                </div>



                {{-- META --}}
                <div class="semantic-meta mt-auto pt-4 text-muted small">
                    <span id="metaUpdated"></span>
                </div>

            </div>

        </div>

    </div>

</div>
</div>

@endsection
