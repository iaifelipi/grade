@php
    $normalize = function ($value) {
        $value = strtolower((string) $value);
        return preg_replace('/[^a-z0-9]+/', '', $value);
    };
    $baseAliases = collect([
        'registro','name','nome','fullname','fullnome',
        'email','e-mail','e_mail','mail',
        'cpf','documento','doc',
        'phone','telefone','tel','celular','phonee164',
        'sex','sexo','gender','genero',
        'score','pontuacao'
    ])->map(fn($alias) => $normalize($alias))->unique();
    $settingsMeta = collect($settings)->map(function ($setting) use ($baseAliases, $normalize) {
        $label = $setting->label ?? '';
        $isBase = $baseAliases->contains($normalize($setting->column_key))
            || ($label && $baseAliases->contains($normalize($label)));
        return [
            'setting' => $setting,
            'is_base' => $isBase,
        ];
    });
    $totalCount = $settingsMeta->count();
    $visibleCount = $settingsMeta->where('setting.visible', true)->count();
    $hiddenCount = $totalCount - $visibleCount;
    $extraCount = $settingsMeta->where('is_base', false)->count();
@endphp

<div class="columns-admin-summary">
    <div class="columns-admin-summary-card">
        <div class="columns-admin-summary-label">Total</div>
        <div class="columns-admin-summary-value">{{ $totalCount }}</div>
        <div class="columns-admin-summary-meta">colunas cadastradas</div>
    </div>
    <div class="columns-admin-summary-card">
        <div class="columns-admin-summary-label">Visíveis</div>
        <div class="columns-admin-summary-value">{{ $visibleCount }}</div>
        <div class="columns-admin-summary-meta">aparecem no Explore</div>
    </div>
    <div class="columns-admin-summary-card">
        <div class="columns-admin-summary-label">Ocultas</div>
        <div class="columns-admin-summary-value">{{ $hiddenCount }}</div>
        <div class="columns-admin-summary-meta">ficam fora da lista</div>
    </div>
    <div class="columns-admin-summary-card columns-admin-summary-card--accent">
        <div class="columns-admin-summary-label">Extras</div>
        <div class="columns-admin-summary-value">{{ $extraCount }}</div>
        <div class="columns-admin-summary-meta">colunas personalizadas</div>
    </div>
</div>

<div class="columns-admin-grid">
    <div class="columns-admin-card">
        <div class="columns-admin-card-header">
            <div>
                <div class="columns-admin-card-title">Nova coluna</div>
                <div class="columns-admin-card-meta">Crie colunas personalizadas</div>
            </div>
        </div>

        <form method="POST" action="{{ route('explore.columns.store') }}" class="columns-admin-form">
            @csrf
            @if(empty($sourceId))
                <div class="columns-admin-alert">
                    Selecione um arquivo no Explore para continuar.
                </div>
            @endif
            <fieldset @if(empty($sourceId)) disabled @endif>
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label">Chave</label>
                    <input type="text" name="column_key" class="form-control" placeholder="ex: empresa, cargo, data_compra" required>
                    <div class="form-text">Use uma chave simples, sem espaços e sem acentos.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rótulo</label>
                    <input type="text" name="label" class="form-control" placeholder="Nome exibido">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Grupo</label>
                    <input type="text" name="group_name" class="form-control" placeholder="Identidade, Contato...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Regra de mescla</label>
                    <select name="merge_rule" class="form-select">
                        <option value="">Nenhuma</option>
                        <option value="fallback">Fallback (1º valor válido)</option>
                        <option value="concat">Concatenação</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordem</label>
                    <input type="number" name="sort_order" class="form-control" min="0" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Visível</label>
                    <select name="visible" class="form-select">
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary mt-3" data-columns-requires-source>Adicionar coluna</button>
            <div class="columns-admin-note">Crie a coluna e depois ajuste rótulo e visibilidade na tabela.</div>
            </fieldset>
        </form>
    </div>

    <div class="columns-admin-card columns-admin-card--wide">
        <div class="columns-admin-card-header">
            <div>
                <div class="columns-admin-card-title">Colunas do Explore</div>
                <div class="columns-admin-card-meta">Edite rótulo, grupo, visibilidade e ordem. Extras ficam com destaque.</div>
            </div>
            <div class="columns-admin-card-actions">
                <button type="button"
                        class="columns-admin-help-btn"
                        data-bs-toggle="tooltip"
                        data-bs-html="true"
                        title="
                            <strong>Como usar</strong><br>
                            1) Crie a coluna (se necessário).<br>
                            2) Ajuste rótulo e grupo.<br>
                            3) Defina visibilidade e ordem.<br>
                            4) Clique em <em>Salvar alterações</em>.<br><br>
                            <strong>Extras</strong><br>
                            São colunas do CSV/planilha fora do padrão.<br>
                            Aparecem quando há dados em <em>extras_json</em>.<br><br>
                            <strong>Mescla</strong><br>
                            Fallback = primeiro valor válido.<br>
                            Concat = junta valores em um só.
                        ">
                    ?
                </button>
                <button class="btn btn-success btn-sm" type="submit" form="columnsBulkForm" data-columns-requires-source>
                    Salvar alterações
                </button>
                <button class="btn btn-outline-secondary btn-sm" type="button" id="columnsResetBtn" data-columns-requires-source>
                    Restaurar padrão
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('explore.columns.reset') }}" id="columnsResetForm" class="d-none">
            @csrf
        </form>

        <form method="POST" action="{{ route('explore.columns.save') }}" id="columnsBulkForm">
            @csrf
            @if(empty($sourceId))
                <div class="columns-admin-alert">
                    Selecione um arquivo no Explore para editar as colunas.
                </div>
            @endif
            <fieldset @if(empty($sourceId)) disabled @endif>
            <div class="columns-admin-selection" data-columns-selection>
                <div class="columns-admin-selection-info">
                    <span data-columns-selection-count>0</span> selecionadas
                    <span class="columns-admin-selection-warning" data-columns-selection-warning></span>
                </div>
                <div class="columns-admin-selection-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-columns-action="rename" data-columns-requires-source disabled>
                        Renomear
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-columns-action="merge" data-columns-requires-source disabled>
                        Unir
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-columns-action="show-selected" data-columns-requires-source disabled>
                        Mostrar
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-columns-action="hide-selected" data-columns-requires-source disabled>
                        Ocultar
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-columns-action="delete" data-columns-requires-source disabled>
                        Excluir selecionadas
                    </button>
                    <button type="button" class="btn btn-link btn-sm text-muted" data-columns-action="clear-selection" data-columns-requires-source>
                        Limpar seleção
                    </button>
                </div>
            </div>
            <div class="columns-admin-toolbar">
                <div class="columns-admin-search">
                    <input type="text" class="form-control form-control-sm" placeholder="Buscar por chave, rótulo ou categoria" data-columns-search>
                    <span class="columns-admin-search-icon">⌕</span>
                </div>
                <div class="columns-admin-filters">
                    <select class="form-select form-select-sm" data-columns-type>
                        <option value="all">Todos os tipos</option>
                        <option value="base">Base</option>
                        <option value="extra">Extras</option>
                    </select>
                    <select class="form-select form-select-sm" data-columns-visible>
                        <option value="all">Todas as visibilidades</option>
                        <option value="visible">Visíveis</option>
                        <option value="hidden">Ocultas</option>
                    </select>
                </div>
                <div class="columns-admin-actions-inline">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-columns-action="show-all" data-columns-requires-source>
                        Marcar visíveis
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-columns-action="hide-all" data-columns-requires-source>
                        Ocultar todas
                    </button>
                </div>
            </div>
            <div class="columns-admin-hint">
                As alterações são aplicadas após clicar em <strong>Salvar alterações</strong>.
            </div>
            <div class="columns-admin-empty" data-columns-empty>
                Nenhuma coluna encontrada. Ajuste os filtros ou crie uma nova coluna.
            </div>
            <div class="columns-admin-table">
                <div class="columns-admin-row columns-admin-row--head">
                    <div class="columns-admin-check">
                        <input type="checkbox" class="form-check-input" data-columns-select-all>
                    </div>
                    <div>Chave</div>
                    <div>Rótulo</div>
                    <div>Grupo</div>
                    <div>Mescla</div>
                    <div>Ordem</div>
                    <div>Visível</div>
                </div>
                @foreach($settingsMeta as $item)
                    @php
                        $setting = $item['setting'];
                        $isBase = $item['is_base'];
                    @endphp
                    <div class="columns-admin-row"
                         data-key="{{ strtolower($setting->column_key) }}"
                         data-label="{{ strtolower($setting->label ?? '') }}"
                         data-group="{{ strtolower($setting->group_name ?? '') }}"
                         data-type="{{ $isBase ? 'base' : 'extra' }}"
                         data-visible="{{ $setting->visible ? '1' : '0' }}">
                        <div class="columns-admin-check">
                            <input type="checkbox" class="form-check-input" data-columns-checkbox value="{{ $setting->id }}" name="ids[]" form="bulkDeleteColumnsForm">
                        </div>
                        <div class="columns-admin-key">
                            <div class="columns-admin-key-main">{{ $setting->column_key }}</div>
                            <span class="columns-admin-pill {{ $isBase ? 'columns-admin-pill--base' : 'columns-admin-pill--extra' }}"
                                  @if($isBase) data-bs-toggle="tooltip" title="Coluna base: não pode ser excluída ou mesclada." @endif>
                                {{ $isBase ? 'Base' : 'Extra' }}
                            </span>
                        </div>
                        <div>
                            <input class="form-control form-control-sm" name="items[{{ $setting->id }}][label]" value="{{ $setting->label }}">
                        </div>
                        <div>
                            <input class="form-control form-control-sm" name="items[{{ $setting->id }}][group_name]" value="{{ $setting->group_name }}">
                        </div>
                        <div>
                            <select class="form-select form-select-sm" name="items[{{ $setting->id }}][merge_rule]">
                                <option value="" @selected(!$setting->merge_rule)>Nenhuma</option>
                                <option value="fallback" @selected($setting->merge_rule === 'fallback')>Fallback</option>
                                <option value="concat" @selected($setting->merge_rule === 'concat')>Concat</option>
                            </select>
                        </div>
                        <div>
                            <input class="form-control form-control-sm" type="number" min="0" name="items[{{ $setting->id }}][sort_order]" value="{{ $setting->sort_order }}">
                        </div>
                        <div>
                            <select class="form-select form-select-sm" name="items[{{ $setting->id }}][visible]">
                                <option value="1" @selected($setting->visible)>Sim</option>
                                <option value="0" @selected(!$setting->visible)>Não</option>
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>
            </fieldset>
        </form>
        @foreach($settings as $setting)
            <form id="deleteColumn{{ $setting->id }}" method="POST" action="{{ route('explore.columns.destroy', $setting->id) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
        <form id="bulkDeleteColumnsForm" method="POST" action="{{ route('explore.columns.bulkDelete') }}" class="d-none">
            @csrf
        </form>
        <form id="mergeColumnsForm" method="POST" action="{{ route('explore.columns.merge') }}" class="d-none">
            @csrf
        </form>
    </div>
</div>

<div class="modal fade" id="bulkDeleteColumnsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3">
                    As colunas selecionadas serão removidas permanentemente.
                </div>
                <div class="columns-admin-modal-warning alert alert-warning py-2 px-3 d-none" id="bulkDeleteColumnsWarning">
                    Só colunas extras podem ser excluídas. Remova colunas base da seleção.
                </div>
                <div class="columns-admin-delete-list" id="bulkDeleteColumnsList"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger" type="submit" form="bulkDeleteColumnsForm" data-columns-bulk-confirm>Excluir colunas</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="renameColumnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renomear coluna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3">
                    O novo rótulo será usado no Explore.
                </div>
                <div class="mb-3">
                    <label class="form-label">Novo rótulo</label>
                    <input type="text" class="form-control" id="renameColumnInput" placeholder="Ex: Nome completo">
                </div>
                <div class="small text-muted" id="renameColumnKey"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" type="button" id="confirmRenameColumn">Aplicar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mergeColumnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Unir colunas (mescla física)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3" id="mergeColumnDescription">
                    A mescla usa <strong>fallback</strong>: o primeiro valor preenchido vira o valor final.
                    As colunas antigas serão removidas do <em>extras_json</em>.
                </div>
                <div class="columns-admin-modal-warning alert alert-warning py-2 px-3 d-none" id="mergeColumnsWarning">
                    Só colunas extras podem ser mescladas. Remova colunas base da seleção.
                </div>
                <div class="columns-admin-merge-list" id="mergeColumnList"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" type="button" id="confirmMergeColumn">Unir colunas</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="columnsResetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restaurar padrão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small">
                    O padrão deste arquivo será restaurado. Isso remove rótulo, grupo, visibilidade, ordem e colunas extras.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning" type="submit" form="columnsResetForm" id="columnsResetConfirm">
                    Restaurar padrão
                </button>
            </div>
        </div>
    </div>
</div>
