@extends('layouts.app')

@section('title', 'Admin — Impostos')
@section('page-title', 'Admin')

@section('content')
@php
    $total = $items->count();
    $active = $items->where('is_active', true)->count();
@endphp
<div class="admin-monetization-page" data-tax-edit-url-template="{{ route('admin.monetization.taxes.update', ['id' => '__ID__']) }}">
    <x-admin.page-header title="Impostos" subtitle="Regras tributárias por país/estado/cidade.">
        <x-slot:actions>
            <button class="btn btn-dark btn-sm rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#taxCreateModal">Adicionar imposto</button>
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.monetization._tabs')
    @if(session('status'))<div class="alert alert-success mb-3">{{ session('status') }}</div>@endif

    <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Impostos</span><div class="admin-metric-field-value">{{ $total }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Ativos</span><div class="admin-metric-field-value">{{ $active }}</div></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="grade-profile-field-box admin-metric-field-box"><span class="grade-profile-field-kicker">Inativos</span><div class="admin-metric-field-value">{{ $total - $active }}</div></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 d-flex align-items-center justify-content-between"><strong>Lista de impostos</strong><span class="badge bg-light text-dark border rounded-pill">{{ $total }} registros</span></div><div class="table-responsive"><table class="table table-sm align-middle mb-0 admin-monetization-table admin-enterprise-table"><thead class="table-light"><tr><th style="width:72px">#</th><th>Nome</th><th>Escopo</th><th>Alíquota</th><th>Status</th><th style="width:88px" class="text-end">Ações</th></tr></thead><tbody>
    @forelse($items as $item)
    <tr data-enterprise-row="1">
        <td><span class="admin-table-id-pill">#{{ $item->id }}</span></td><td>{{ $item->name }}</td><td>{{ $item->country_code ?: '—' }} / {{ $item->state_code ?: '—' }} / {{ $item->city ?: '—' }}</td><td>{{ $item->rate_percent }}%</td><td><span class="badge rounded-pill admin-table-status {{ $item->is_active ? 'is-success' : 'is-muted' }}">{{ $item->is_active ? 'ativo' : 'inativo' }}</span></td>
        <td class="text-end">
            <div class="dropdown admin-table-actions-dropdown">
                <button class="btn btn-sm admin-table-actions-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir ações"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end admin-table-actions-menu">
                    <li>
                        <button
                            class="dropdown-item admin-table-action-item"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#taxCreateModal"
                            data-tax-edit="1"
                            data-id="{{ $item->id }}"
                            data-name="{{ e($item->name) }}"
                            data-country-code="{{ e((string) $item->country_code) }}"
                            data-state-code="{{ e((string) $item->state_code) }}"
                            data-city="{{ e((string) $item->city) }}"
                            data-rate-percent="{{ e((string) $item->rate_percent) }}"
                            data-is-active="{{ $item->is_active ? '1' : '0' }}"
                        >
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            <span>Editar</span>
                        </button>
                    </li>
                    <li><form method="POST" action="{{ route('admin.monetization.taxes.update', $item->id) }}">@csrf @method('PUT')
                        <input type="hidden" name="name" value="{{ $item->name }}"><input type="hidden" name="country_code" value="{{ $item->country_code }}"><input type="hidden" name="state_code" value="{{ $item->state_code }}"><input type="hidden" name="city" value="{{ $item->city }}"><input type="hidden" name="rate_percent" value="{{ $item->rate_percent }}"><input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}">
                        <button class="dropdown-item admin-table-action-item" type="submit"><i class="bi bi-toggle-{{ $item->is_active ? 'off' : 'on' }}" aria-hidden="true"></i><span>{{ $item->is_active ? 'Desativar' : 'Ativar' }}</span></button>
                    </form></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('admin.monetization.taxes.destroy', $item->id) }}" onsubmit="return confirm('Remover imposto?')">@csrf @method('DELETE')<button class="dropdown-item admin-table-action-item is-danger" type="submit"><i class="bi bi-trash3" aria-hidden="true"></i><span>Remover</span></button></form></li>
                </ul>
            </div>
        </td>
    </tr>
    @empty <tr><td colspan="6" class="text-center text-muted py-3">Sem impostos.</td></tr> @endforelse
    </tbody></table></div></div>
</div>

<div class="modal fade" id="taxCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content grade-modal-pattern">
            <div class="modal-header border-0 pb-0"><div><h5 class="modal-title mb-1" id="taxModalTitle">Adicionar imposto</h5><p class="grade-modal-hint mb-0" id="taxModalHint">Defina escopo geográfico e alíquota.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body pt-3">
                <form method="POST" id="taxCreateForm" action="{{ route('admin.monetization.taxes.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="_method" id="taxFormMethod" value="POST">
                    <div class="col-md-4"><input id="taxFormName" name="name" class="form-control form-control-sm" placeholder="Nome" required></div>
                    <div class="col-md-2"><input id="taxFormCountryCode" name="country_code" class="form-control form-control-sm" placeholder="BR"></div>
                    <div class="col-md-2"><input id="taxFormStateCode" name="state_code" class="form-control form-control-sm" placeholder="SP"></div>
                    <div class="col-md-2"><input id="taxFormCity" name="city" class="form-control form-control-sm" placeholder="Cidade"></div>
                    <div class="col-md-2"><input id="taxFormRatePercent" name="rate_percent" class="form-control form-control-sm" placeholder="17.5" required></div>
                    <div class="col-12 d-flex align-items-center"><label class="small"><input id="taxFormActive" type="checkbox" name="is_active" value="1" checked> ativo</label></div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary btn-sm" form="taxCreateForm" id="taxModalSubmit">Salvar imposto</button></div>
        </div>
    </div>
</div>
<script>
(() => {
    const root = document.querySelector('[data-tax-edit-url-template]');
    if (!root) return;
    const editUrlTemplate = root.getAttribute('data-tax-edit-url-template') || '';
    const form = document.getElementById('taxCreateForm');
    const modal = document.getElementById('taxCreateModal');
    if (!form || !modal || !editUrlTemplate) return;

    const storeUrl = @json(route('admin.monetization.taxes.store'));
    const method = document.getElementById('taxFormMethod');
    const title = document.getElementById('taxModalTitle');
    const hint = document.getElementById('taxModalHint');
    const submit = document.getElementById('taxModalSubmit');
    const name = document.getElementById('taxFormName');
    const countryCode = document.getElementById('taxFormCountryCode');
    const stateCode = document.getElementById('taxFormStateCode');
    const city = document.getElementById('taxFormCity');
    const ratePercent = document.getElementById('taxFormRatePercent');
    const active = document.getElementById('taxFormActive');

    const toCreateMode = () => {
        form.action = storeUrl;
        method.value = 'POST';
        title.textContent = 'Adicionar imposto';
        hint.textContent = 'Defina escopo geográfico e alíquota.';
        submit.textContent = 'Salvar imposto';
        form.reset();
        active.checked = true;
    };

    document.querySelectorAll('[data-tax-edit=\"1\"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            form.action = editUrlTemplate.replace('__ID__', encodeURIComponent(id || ''));
            method.value = 'PUT';
            title.textContent = 'Editar imposto';
            hint.textContent = 'Atualize os dados do imposto selecionado.';
            submit.textContent = 'Salvar alterações';
            name.value = btn.getAttribute('data-name') || '';
            countryCode.value = btn.getAttribute('data-country-code') || '';
            stateCode.value = btn.getAttribute('data-state-code') || '';
            city.value = btn.getAttribute('data-city') || '';
            ratePercent.value = btn.getAttribute('data-rate-percent') || '';
            active.checked = btn.getAttribute('data-is-active') === '1';
        });
    });

    modal.addEventListener('hidden.bs.modal', toCreateMode);
})();
</script>
@endsection
