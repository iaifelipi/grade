<div class="modal fade" id="semanticModal" tabindex="-1" aria-labelledby="semanticModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-semibold mb-0" id="semanticModalTitle">ID Semântica</h5>
                        <span
                            class="semantic-help"
                            role="button"
                            tabindex="0"
                            data-bs-toggle="tooltip"
                            data-bs-placement="right"
                            data-bs-html="true"
                            title="Exemplos práticos:<br>• Segmento: Construção<br>• Nicho: Esquadrias<br>• Origem: Facebook Ads<br>• Local: Campinas/SP"
                        >?</span>
                    </div>
                    <p class="semantic-help-text mb-0">
                        Use a semântica para identificar o perfil da base. Isso melhora os filtros,
                        facilita segmentações e deixa o Explore mais inteligente.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="semantic-context">
                    <div class="semantic-context-title">Como usar</div>
                    <ul class="semantic-context-list">
                        <li>Escolha múltiplos segmentos e nichos, e 1 origem para descrever o público.</li>
                        <li>Selecione cidades/estados/países para indicar onde a base é mais forte.</li>
                        <li>Defina a âncora (ex: Brasil) quando não houver localizações específicas.</li>
                    </ul>
                </div>
                <div class="semantic-anchor-row mb-3">
                    <div class="semantic-anchor-badge">
                        Âncora atual
                    </div>
                    <select id="semanticAnchor" class="form-select"></select>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Segmento</label>
                        <input id="semanticSegmentInput" class="form-control mb-2" placeholder="Buscar segmento...">
                        <div id="semanticSegmentResults" class="semantic-results d-none"></div>
                        <div id="semanticSegmentPill" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nicho</label>
                        <input id="semanticNicheInput" class="form-control mb-2" placeholder="Buscar nicho...">
                        <div id="semanticNicheResults" class="semantic-results d-none"></div>
                        <div id="semanticNichePill" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Origem</label>
                        <input id="semanticOriginInput" class="form-control mb-2" placeholder="Buscar origem...">
                        <div id="semanticOriginResults" class="semantic-results d-none"></div>
                        <div id="semanticOriginPill" class="semantic-select-pills"></div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Cidades</label>
                        <input id="semanticCityInput" class="form-control mb-2" placeholder="Buscar cidade...">
                        <div id="semanticCityResults" class="semantic-results d-none"></div>
                        <div id="semanticCityPills" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estados</label>
                        <input id="semanticStateInput" class="form-control mb-2" placeholder="Buscar estado...">
                        <div id="semanticStateResults" class="semantic-results d-none"></div>
                        <div id="semanticStatePills" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Países</label>
                        <input id="semanticCountryInput" class="form-control mb-2" placeholder="Buscar país...">
                        <div id="semanticCountryResults" class="semantic-results d-none"></div>
                        <div id="semanticCountryPills" class="semantic-select-pills"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button id="semanticSaveBtn" type="button" class="btn btn-primary">
                    Salvar
                </button>
            </div>
        </div>
    </div>
</div>
