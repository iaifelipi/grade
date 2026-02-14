<div class="modal fade" id="semanticModal" tabindex="-1" aria-labelledby="semanticModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title mb-0" id="semanticModalTitle">ID Semântica</h5>
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
                    <label class="grade-field-box grade-field-box--compact w-100 mb-0">
                        <span class="grade-field-kicker">Referência âncora</span>
                        <select id="semanticAnchor" class="grade-field-input grade-field-input--select"></select>
                    </label>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="grade-field-box grade-field-box--compact mb-2">
                            <span class="grade-field-kicker">Segmento</span>
                            <input id="semanticSegmentInput" class="grade-field-input" placeholder="Buscar segmento...">
                        </label>
                        <div id="semanticSegmentResults" class="semantic-results d-none"></div>
                        <div id="semanticSegmentPill" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box grade-field-box--compact mb-2">
                            <span class="grade-field-kicker">Nicho</span>
                            <input id="semanticNicheInput" class="grade-field-input" placeholder="Buscar nicho...">
                        </label>
                        <div id="semanticNicheResults" class="semantic-results d-none"></div>
                        <div id="semanticNichePill" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box grade-field-box--compact mb-2">
                            <span class="grade-field-kicker">Origem</span>
                            <input id="semanticOriginInput" class="grade-field-input" placeholder="Buscar origem...">
                        </label>
                        <div id="semanticOriginResults" class="semantic-results d-none"></div>
                        <div id="semanticOriginPill" class="semantic-select-pills"></div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="grade-field-box grade-field-box--compact mb-2">
                            <span class="grade-field-kicker">Cidades</span>
                            <input id="semanticCityInput" class="grade-field-input" placeholder="Buscar cidade...">
                        </label>
                        <div id="semanticCityResults" class="semantic-results d-none"></div>
                        <div id="semanticCityPills" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box grade-field-box--compact mb-2">
                            <span class="grade-field-kicker">Estados</span>
                            <input id="semanticStateInput" class="grade-field-input" placeholder="Buscar estado...">
                        </label>
                        <div id="semanticStateResults" class="semantic-results d-none"></div>
                        <div id="semanticStatePills" class="semantic-select-pills"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="grade-field-box grade-field-box--compact mb-2">
                            <span class="grade-field-kicker">Países</span>
                            <input id="semanticCountryInput" class="grade-field-input" placeholder="Buscar país...">
                        </label>
                        <div id="semanticCountryResults" class="semantic-results d-none"></div>
                        <div id="semanticCountryPills" class="semantic-select-pills"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button id="semanticSaveBtn" type="button" class="btn btn-dark">
                    Salvar
                </button>
            </div>
        </div>
    </div>
</div>
