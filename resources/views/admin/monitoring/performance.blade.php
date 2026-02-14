@extends('layouts.app')

@section('title', 'Admin — Performance Técnico')
@section('page-title', 'Admin')

@section('content')
<div class="container-fluid py-3">
    <x-admin.page-header
        title="Performance Técnico"
        subtitle="Últimos eventos de performance das telas de listas (logs de budget)."
    >
        <x-slot:actions>
            <a href="{{ route('admin.monitoring.index') }}" class="btn btn-outline-secondary btn-sm">Voltar ao monitoramento</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.monitoring.performance') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label form-label-sm mb-1">Tela</label>
                <select name="screen" class="form-select form-select-sm" style="min-width: 170px;">
                    <option value="">Todas</option>
                    <option value="index" @selected(($filters['screen'] ?? '') === 'index')>Listas</option>
                    <option value="overview" @selected(($filters['screen'] ?? '') === 'overview')>Detalhes</option>
                    <option value="subscribers" @selected(($filters['screen'] ?? '') === 'subscribers')>Assinantes</option>
                </select>
            </div>
            <div>
                <label class="form-label form-label-sm mb-1">Rota/Caminho</label>
                <input type="text" name="route" value="{{ $filters['route'] ?? '' }}" class="form-control form-control-sm" style="min-width: 260px;" placeholder="admin.customers.files.subscribers">
            </div>
            <div>
                <label class="form-label form-label-sm mb-1">Limite de leitura</label>
                <select name="limit" class="form-select form-select-sm" style="min-width: 140px;">
                    @foreach ([100, 200, 300, 500] as $opt)
                        <option value="{{ $opt }}" @selected((int) ($filters['limit'] ?? 200) === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-dark btn-sm">Aplicar</button>
                <a href="{{ route('admin.monitoring.performance') }}" class="btn btn-outline-secondary btn-sm">Limpar</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Eventos lidos</div>
                    <div class="fw-bold fs-4">{{ (int) ($totals['events'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Eventos lentos</div>
                    <div class="fw-bold fs-4">{{ (int) ($totals['slow_events'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Rotas encontradas</div>
                    <div class="fw-bold fs-4">{{ (int) ($totals['routes'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Rotas mais lentas (agregado)</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Rota</th>
                        <th>Tela</th>
                        <th class="text-end">Eventos lentos</th>
                        <th class="text-end">Média total (ms)</th>
                        <th class="text-end">Pico total (ms)</th>
                        <th class="text-end">Média query (ms)</th>
                        <th class="text-end">Média queries</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routeSummary as $row)
                        <tr>
                            <td><code>{{ $row['route'] }}</code></td>
                            <td>{{ $row['screen'] ?: '—' }}</td>
                            <td class="text-end">{{ (int) $row['slow_count'] }}</td>
                            <td class="text-end">{{ number_format((float) $row['avg_total_ms'], 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $row['max_total_ms'], 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $row['avg_query_ms'], 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $row['avg_query_count'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">Sem eventos lentos para os filtros atuais.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Últimos eventos lentos</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Data/hora</th>
                        <th>Rota</th>
                        <th>Caminho</th>
                        <th>Tela</th>
                        <th class="text-end">Total (ms)</th>
                        <th class="text-end">Query (ms)</th>
                        <th class="text-end">Queries</th>
                        <th class="text-end">Payload</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($slowEvents as $event)
                        <tr>
                            <td>{{ $event['timestamp'] ?: '—' }}</td>
                            <td><code>{{ $event['route'] ?: '—' }}</code></td>
                            <td><code>{{ $event['path'] ?: '—' }}</code></td>
                            <td>{{ $event['screen'] ?: '—' }}</td>
                            <td class="text-end">{{ number_format((float) $event['total_ms'], 2, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((float) $event['query_ms'], 2, ',', '.') }}</td>
                            <td class="text-end">{{ (int) $event['query_count'] }}</td>
                            <td class="text-end">{{ (int) $event['payload_items'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">Nenhum evento lento encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
