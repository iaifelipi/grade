<div class="modal fade" id="semanticIdentityModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">

<div class="modal-content grade-card">

<div class="modal-header">
    <h5 class="modal-title">
        🧠 Identidade Semântica
    </h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

    {{-- 🔎 BUSCA GLOBAL --}}
    <input id="semanticSearch"
           type="text"
           class="form-control mb-3"
           placeholder="Buscar cidade, estado, país, segmento, nicho ou origem…">

    {{-- PILLS --}}
    <div id="semanticPillsArea">

        <div class="semantic-group" data-type="location">
            <label>📍 Localização</label>
            <div class="pills"></div>
        </div>

        <div class="semantic-group" data-type="segment">
            <label>🧩 Segmento</label>
            <div class="pills"></div>
        </div>

        <div class="semantic-group" data-type="niche">
            <label>🎯 Nicho</label>
            <div class="pills"></div>
        </div>

        <div class="semantic-group" data-type="origin">
            <label>🔗 Origem</label>
            <div class="pills"></div>
        </div>

    </div>

    {{-- ÂNCORA --}}
    <div class="mt-4">
        <label>Âncora</label>
        <input id="semanticAnchor" class="form-control" readonly>
    </div>

</div>

<div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button id="btnSaveSemanticIdentity" class="btn btn-primary">
        Salvar
    </button>
</div>

</div>
</div>
</div>
