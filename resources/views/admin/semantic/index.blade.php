@extends('layouts.app')

@section('title','Admin — Semântica')
@section('page-title','Semântica')

@section('topbar-tools')
    <div class="explore-toolbar explore-toolbar--topbar">
        <div class="explore-toolbar-actions ms-auto">
            <div class="explore-chip-group">
                <div class="explore-filter-inline">
                    <div class="source-combo">
                        <div class="source-select-wrap">
                            <select id="semanticSourceSelect" class="form-select">
                                <option value="">Todos os arquivos</option>
                                @foreach($topbarSources as $source)
                                    <option value="{{ $source->id }}" @selected((int) $currentSourceId === (int) $source->id)>
                                        {{ $source->original_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <a href="{{ route('home') }}" class="btn btn-primary explore-add-source-btn" title="Abrir Explore" aria-label="Abrir Explore">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                </div>
                <div class="explore-filter-dropdown dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtros
                    </button>
                    <div class="dropdown-menu dropdown-menu-end explore-filter-menu p-3">
                        <label class="form-label small text-muted">Arquivo</label>
                        <select id="semanticSourceSelectMobile" class="form-select mb-2">
                            <option value="">Todos os arquivos</option>
                            @foreach($topbarSources as $source)
                                <option value="{{ $source->id }}" @selected((int) $currentSourceId === (int) $source->id)>
                                    {{ $source->original_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')

<div class="container-fluid py-4">
@if(session('status'))
    <div class="alert alert-success mb-3">{{ session('status') }}</div>
@endif
<div class="semantic-admin" id="semanticTaxonomyPage">
    <div class="semantic-admin-hero">
        <div>
            <h2 class="semantic-admin-title">Taxonomias de Semântica</h2>
            <p class="semantic-admin-subtitle">
                Gerencie os termos oficiais de segmento, nicho e origem.
                Isso impacta filtros e classificação automática.
            </p>
        </div>
        <div class="semantic-admin-actions">
            <span class="semantic-admin-badge">
                Alterações afetam filtros e relatórios
            </span>
        </div>
    </div>

    <div class="semantic-admin-grid">
        @php
            $blocks = [
                ['key' => 'segments', 'title' => 'Segmentos', 'count' => count($segments), 'items' => $segments],
                ['key' => 'niches', 'title' => 'Nichos', 'count' => count($niches), 'items' => $niches],
                ['key' => 'origins', 'title' => 'Origens', 'count' => count($origins), 'items' => $origins],
            ];
        @endphp

        @foreach ($blocks as $block)
            <div class="semantic-admin-card">
                <div class="semantic-admin-card-header">
                    <div>
                        <div class="semantic-admin-card-title">{{ $block['title'] }}</div>
                        <div class="semantic-admin-card-meta">{{ $block['count'] }} itens</div>
                    </div>
                    <button
                        class="btn btn-primary btn-sm semantic-admin-btn"
                        data-semantic-create
                        data-type="{{ $block['key'] }}"
                        data-action="{{ route('admin.semantic.store', ['type' => $block['key']]) }}"
                    >
                        Novo
                    </button>
                </div>

                <div class="semantic-admin-tools">
                    <div class="semantic-admin-search">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Buscar {{ strtolower($block['title']) }}..."
                            data-semantic-search
                            data-type="{{ $block['key'] }}"
                        >
                    </div>
                    <form
                        method="POST"
                        action="{{ route('admin.semantic.store', ['type' => $block['key']]) }}"
                        class="semantic-admin-quickadd"
                        data-semantic-quickadd
                        data-type="{{ $block['key'] }}"
                    >
                        @csrf
                        <input
                            type="text"
                            name="name"
                            class="form-control form-control-sm"
                            maxlength="120"
                            placeholder="Adicionar rápido..."
                            aria-label="Adicionar {{ strtolower($block['title']) }}"
                            required
                        >
                        <button class="btn btn-outline-primary btn-sm" type="submit">Adicionar</button>
                    </form>
                    <form
                        method="POST"
                        action="{{ route('admin.semantic.bulkAdd', ['type' => $block['key']]) }}"
                        class="semantic-admin-bulkadd"
                        data-semantic-bulkadd
                    >
                        @csrf
                        <textarea
                            name="items"
                            rows="3"
                            class="form-control form-control-sm"
                            placeholder="Adicionar vários (1 por linha)"
                        ></textarea>
                        <div class="semantic-admin-bulkadd-actions">
                            <button class="btn btn-outline-primary btn-sm" type="submit">
                                Adicionar em lote
                            </button>
                        </div>
                    </form>
                </div>

                <div class="semantic-admin-bulkbar d-none" data-semantic-bulkbar>
                    <div class="semantic-admin-bulkinfo">
                        <span data-semantic-selected>0</span> selecionados
                    </div>
                    <div class="semantic-admin-bulkactions">
                        <button class="btn btn-outline-danger btn-sm" type="button" data-semantic-bulk-delete>
                            Remover selecionados
                        </button>
                    </div>
                </div>

                <div class="semantic-admin-list">
                    @foreach ($block['items'] as $item)
                        <div class="semantic-admin-row" data-semantic-item data-name="{{ $item->name }}">
                            <div class="semantic-admin-name">
                                <label class="semantic-admin-check">
                                    <input
                                        type="checkbox"
                                        data-semantic-select
                                        data-id="{{ $item->id }}"
                                    >
                                    <span class="semantic-admin-label">{{ $item->name }}</span>
                                </label>
                            </div>
                            <div class="semantic-admin-row-actions">
                                <button
                                    class="btn btn-outline-secondary btn-sm"
                                    data-semantic-edit
                                    data-type="{{ $block['key'] }}"
                                    data-action="{{ route('admin.semantic.update', ['type' => $block['key'], 'id' => $item->id]) }}"
                                    data-name="{{ $item->name }}"
                                >
                                    Editar
                                </button>
                                <form
                                    method="POST"
                                    action="{{ route('admin.semantic.destroy', ['type' => $block['key'], 'id' => $item->id]) }}"
                                    class="semantic-admin-delete"
                                    data-confirm="Excluir '{{ $item->name }}'?"
                                    data-name="{{ $item->name }}"
                                    data-type-label="{{ $block['title'] }}"
                                    data-type="{{ $block['key'] }}"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Excluir</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                    <div class="semantic-admin-empty d-none" data-semantic-empty>
                        Nenhum item encontrado.
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
</div>

<div class="modal fade" id="semanticTaxonomyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content grade-modal-premium">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="fw-semibold mb-1" id="semanticTaxonomyModalTitle">Novo item</h5>
                    <p class="text-muted small mb-0" id="semanticTaxonomyModalHint">
                        Adicione um termo claro e específico.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <form method="POST" action="" id="semanticTaxonomyForm">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="semanticTaxonomyMethod">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" name="name" id="semanticTaxonomyName" required maxlength="120">
                    </div>
                    <div class="semantic-admin-helper">
                        Dica: mantenha o nome curto e evite duplicidades.
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="semanticTaxonomyForm" class="btn btn-primary" id="semanticTaxonomySubmit">
                    Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="semanticDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content grade-modal-premium">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="fw-semibold mb-1">Confirmar exclusão</h5>
                    <p class="text-muted small mb-2" id="semanticDeleteMessage">
                        Este item será removido permanentemente.
                    </p>
                    <div class="semantic-delete-list" id="semanticDeleteList"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="semanticDeleteConfirm">
                    Excluir item
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const select = document.getElementById('semanticSourceSelect')
    const selectMobile = document.getElementById('semanticSourceSelectMobile')
    if (!select && !selectMobile) return

    const selectTemplate = @json(route('vault.explore.selectSource', ['id' => '__ID__']))
    const clearUrl = @json(route('vault.explore.clearSource'))

    const navigate = (value) => {
        const id = String(value || '').trim()
        if (!id) {
            window.location.href = clearUrl
            return
        }
        window.location.href = selectTemplate.replace('__ID__', encodeURIComponent(id))
    }

    if (select) {
        select.addEventListener('change', () => {
            if (selectMobile) selectMobile.value = select.value
            navigate(select.value)
        })
    }
    if (selectMobile) {
        selectMobile.addEventListener('change', () => {
            if (select) select.value = selectMobile.value
            navigate(selectMobile.value)
        })
    }
})()
</script>
@endsection
