@php($embedded = (bool) ($embedded ?? false))
@extends($embedded ? 'layouts.embed' : 'layouts.vault')

@section('title','Sources')
@section('page-title','Registros Vault')

@section('content')

<div class="container {{ $embedded ? 'py-3' : '' }}" style="max-width:1400px">

    {{-- ======================================================
       HEADER
    ====================================================== --}}
    <div class="vault-standard-header">
        <div>
            <h4 class="vault-standard-header__title">Sources</h4>
            <p class="vault-standard-header__subtitle">
                Importação, processamento e gestão de arquivos de registros.
            </p>
        </div>

        <div class="vault-standard-header__actions">
            @can('leads.import')
                <button id="clearPendingUploadsBtn"
                        class="btn btn-outline-secondary shadow-sm px-3"
                        type="button"
                        style="display:none">
                    Limpar uploads pendentes
                </button>
            @endcan
            @can('leads.delete')
                <button id="purgeSelectedBtn"
                        class="btn btn-outline-danger shadow-sm px-3"
                        data-bs-toggle="modal"
                        data-bs-target="#purgeSelectedModal"
                        disabled>
                    Excluir selecionados
                </button>
            @endcan
            @can('leads.import')
                <button id="openUploadModalBtn" class="btn btn-primary shadow-sm px-4"
                        data-bs-toggle="modal"
                        data-bs-target="#uploadModal">
                    + Importar arquivos
                </button>
            @endcan
        </div>
    </div>

    <div id="sourcesPermissions"
         data-can-view="{{ auth()->user()->hasPermission('leads.view') ? '1' : '0' }}"
         data-can-import="{{ auth()->user()->hasPermission('leads.import') ? '1' : '0' }}"
         data-can-delete="{{ auth()->user()->hasPermission('leads.delete') ? '1' : '0' }}"
         data-can-cancel="{{ auth()->user()->hasPermission('automation.cancel') ? '1' : '0' }}"
         data-can-reprocess="{{ auth()->user()->hasPermission('automation.reprocess') ? '1' : '0' }}"
         style="display:none">
    </div>



    {{-- ======================================================
       KPIs (mais elegantes)
    ====================================================== --}}
    <div class="row g-3 mb-4">

        @php
            $cards = [
                ['id'=>'kpiTotal','label'=>'Total','color'=>'text-dark'],
                ['id'=>'kpiImporting','label'=>'Importando','color'=>'text-warning'],
                ['id'=>'kpiDone','label'=>'Concluídos','color'=>'text-success'],
                ['id'=>'kpiFailed','label'=>'Falhas','color'=>'text-danger'],
            ];
        @endphp

        @foreach($cards as $c)
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body py-3">
                    <small class="text-muted">{{ $c['label'] }}</small>
                    <h4 id="{{ $c['id'] }}" class="fw-bold mb-0 {{ $c['color'] }}">0</h4>
                </div>
            </div>
        </div>
        @endforeach

    </div>



    {{-- ======================================================
       TABLE CARD (estilo SaaS)
    ====================================================== --}}
    <div class="card border-0 shadow-sm rounded-3">

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th style="width:36px">
                                <input type="checkbox" id="sourcesCheckAll">
                            </th>
                            <th style="width:70px">#</th>
                            <th>Arquivo</th>
                            <th style="width:130px">Status</th>
                            <th style="width:240px">Progresso</th>
                            <th style="width:120px">Inseridos</th>
                            <th style="width:180px">Criado em</th>
                            <th style="width:140px"></th>
                        </tr>
                    </thead>

                    <tbody id="sourcesBody">

                        {{-- EMPTY STATE bonito --}}
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">

                                <div class="d-flex flex-column align-items-center gap-2">
                                    <div style="font-size:28px">📂</div>
                                    <div>Nenhum arquivo importado ainda</div>
                                    <small>Use o botão “Importar arquivos” para começar</small>
                                </div>

                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>



{{-- ======================================================
   MODAL — estilo premium
====================================================== --}}
<div class="modal fade" id="uploadModal" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content border-0 shadow rounded-4">

            <form id="uploadForm"
                  action="{{ route('vault.sources.store') }}"
                  method="POST"
                  enctype="multipart/form-data">

                @csrf

                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-semibold mb-0">Importar arquivos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3">

                    {{-- DROPZONE --}}
                    <div id="uploadDropzone"
                         class="border rounded-3 p-4 text-center bg-light"
                         style="cursor:pointer">

                        <input id="uploadInput"
                               type="file"
                               name="files[]"
                               multiple
                               class="form-control mb-2">

                        <small class="text-muted">
                            Arraste e solte aqui ou clique para escolher
                        </small>
                        <div class="text-muted" style="font-size:12px">
                            CSV • XLSX • TXT • múltiplos arquivos permitidos
                        </div>

                    </div>

                    <div id="uploadSelectedList" class="mt-3" style="display:none">
                        <small class="text-muted d-block mb-2">Arquivos selecionados</small>
                        <ul class="list-group list-group-flush"></ul>
                    </div>

                    <div id="uploadInvalidAlert" class="alert alert-warning mt-3 mb-0" style="display:none">
                        Alguns arquivos foram ignorados por extensão inválida.
                    </div>

                    {{-- PROGRESS --}}
                    <div class="progress mt-3" style="height:8px">
                        <div id="uploadBar"
                             class="progress-bar progress-bar-striped progress-bar-animated"
                             style="width:0%">
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-0">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button id="btnUpload"
                            type="submit"
                            class="btn btn-primary px-4">
                        Registrar e Importar
                    </button>

                </div>

            </form>

        </div>

    </div>
</div>

{{-- ======================================================
   MODAL — EXCLUIR SELECIONADOS
====================================================== --}}
<div class="modal fade" id="purgeSelectedModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow rounded-4">

            <div class="modal-header border-0 pb-0">
                <h5 class="fw-semibold mb-0 text-danger">Excluir selecionados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body pt-3">
                <p class="mb-2">
                    Você está prestes a excluir os arquivos selecionados e seus dados relacionados.
                </p>
                <div class="alert alert-warning mt-3 mb-0">
                    Essa ação é <b>irreversível</b>.
                </div>

                <div class="mt-3">
                    <label for="purgeSelectedConfirmInput" class="form-label mb-1">
                        Digite <b>EXCLUIR</b> para confirmar
                    </label>
                    <input id="purgeSelectedConfirmInput"
                           type="text"
                           class="form-control"
                           placeholder="EXCLUIR">
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button id="purgeSelectedConfirmBtn" type="button" class="btn btn-danger">
                    Confirmar exclusão
                </button>
            </div>

        </div>

    </div>
</div>

{{-- ======================================================
   MODAL — LIMPAR UPLOADS PENDENTES
====================================================== --}}
<div class="modal fade" id="clearPendingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-semibold mb-0">Limpar uploads pendentes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0">
                    Isso remove a fila local de uploads pendentes. Essa ação não pode ser desfeita.
                </p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button id="confirmClearPendingBtn" type="button" class="btn btn-secondary">
                    Limpar pendentes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ======================================================
   TOAST — UPLOAD EM ANDAMENTO
====================================================== --}}
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090">
    <div id="uploadToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="polite" aria-atomic="true" data-bs-autohide="false">
        <div class="d-flex">
            <div class="toast-body">
                Upload em andamento...
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
        </div>
    </div>
</div>

@endsection
