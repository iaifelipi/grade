<div class="modal fade" id="columnsModal" tabindex="-1" aria-labelledby="columnsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg columns-modal-lg grade-modal-pattern-dialog">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title" id="columnsModalTitle">Colunas</h5>
                        <span
                            class="semantic-help"
                            role="button"
                            tabindex="0"
                            data-bs-toggle="tooltip"
                            data-bs-placement="right"
                            title="Arraste para reordenar. Desmarque para ocultar no Explore."
                        >?</span>
                    </div>
                    <p class="text-muted small mb-0 columns-help">
                        Organize as colunas que aparecem no Explore. Arraste para reordenar.
                    </p>
                </div>
            </div>

            <div class="modal-body">
                <div class="columns-context">
                <div class="columns-context-title">Como usar</div>
                <ul class="columns-context-list">
                    <li>Marque somente o que precisa ver.</li>
                    <li>Arraste as colunas para priorizar as informações.</li>
                    <li>Restaure o padrão quando quiser.</li>
                </ul>
            </div>
                <div class="columns-actions">
                    <button
                        type="button"
                        id="columnsSelectAll"
                        class="btn btn-outline-primary"
                        data-bs-toggle="tooltip"
                        data-bs-placement="bottom"
                        title="Exibe todas as colunas"
                    >
                        Selecionar todas
                    </button>
                    <button
                        type="button"
                        id="columnsResetDefault"
                        class="btn btn-outline-secondary"
                        data-bs-toggle="tooltip"
                        data-bs-placement="bottom"
                        title="Restaura o conjunto padrão de colunas"
                    >
                        Restaurar padrão
                    </button>
                    <span class="columns-actions-spacer"></span>
                    @if(auth()->user()?->hasPermission('system.settings'))
                        <button
                            type="button"
                            id="exploreColumnsEditBtn"
                            class="btn btn-outline-secondary"
                            data-columns-admin-modal="1"
                            data-modal-url="{{ route('explore.columns.modal') }}"
                        >
                            Editar colunas
                        </button>
                    @endif
                </div>

                <div id="columnsList" class="columns-list"></div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
