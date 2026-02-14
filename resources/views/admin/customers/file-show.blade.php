@extends('layouts.app')

@section('title','Admin — Detalhes do Arquivo')
@section('page-title','Admin')

@section('content')
<div class="admin-tenant-users-page">
    <style>
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .file-overview-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }
        .file-overview-header-actions .btn {
            border-radius: 999px;
            white-space: nowrap;
        }
        .overview-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
            border: 1px solid #e7dccb;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 6px 18px rgba(41, 30, 14, 0.06);
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .overview-card-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(41, 30, 14, 0.10);
            border-color: #ceb58e;
        }
        .overview-card-body {
            padding: 14px 14px 12px;
        }
        .overview-card-icon {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-bottom: 10px;
            color: #7b5e32;
            background: #f6efe4;
        }
        .overview-card-value {
            font-size: 32px;
            line-height: 1;
            font-weight: 700;
            color: #2f2413;
        }
        .overview-card-label {
            margin-top: 6px;
            font-size: 12px;
            color: #7a6a52;
        }
        .overview-card-hint {
            margin-top: 8px;
            font-size: 11px;
            color: #9a886d;
        }

        .overview-chart-card {
            border: 1px solid #e7dccb;
            border-radius: 14px;
            background: #fff;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 6px 18px rgba(41, 30, 14, 0.04);
        }
        .overview-chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .overview-chart-title {
            font-size: 15px;
            color: #2f2413;
            font-weight: 700;
            margin: 0;
        }
        .overview-chart-legend {
            font-size: 11px;
            color: #8a7a62;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            display: inline-block;
        }
        .legend-dot.is-line {
            background: #1fa2ff;
        }
        .legend-dot.is-bar {
            background: #18b882;
        }

        .chart-wrap {
            width: 100%;
            overflow-x: auto;
        }
        .chart-svg {
            width: 100%;
            min-width: 760px;
            height: 260px;
            display: block;
            background: linear-gradient(180deg, #fff 0%, #fffaf3 100%);
            border: 1px solid #efe3d1;
            border-radius: 12px;
        }
        .chart-grid {
            stroke: #efe3d1;
            stroke-width: 1;
        }
        .chart-axis {
            stroke: #cfb796;
            stroke-width: 1;
        }
        .chart-line {
            fill: none;
            stroke: #1fa2ff;
            stroke-width: 2.5;
        }
        .chart-point {
            fill: #1fa2ff;
            stroke: #fff;
            stroke-width: 2;
        }
        .chart-label {
            fill: #8a7a62;
            font-size: 11px;
        }
        .chart-value {
            fill: #604b2b;
            font-size: 11px;
            font-weight: 700;
        }
        .chart-bar {
            fill: #18b882;
            rx: 5;
            ry: 5;
        }

        .quality-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 8px;
        }
        .quality-row {
            border: 1px solid #e7dccb;
            border-radius: 10px;
            background: #fffaf2;
            padding: 10px;
        }
        .quality-row-kicker {
            font-size: 11px;
            color: #8a7a62;
            margin-bottom: 4px;
        }
        .quality-row-value {
            font-size: 20px;
            font-weight: 700;
            color: #7b5e32;
        }
        .quality-progress {
            height: 8px;
            border-radius: 999px;
            background: #eee2d2;
            overflow: hidden;
            margin-top: 6px;
        }
        .quality-progress-bar {
            height: 100%;
            background: #7b5e32;
        }

        .file-meta-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
        }
        .meta-col-12 { grid-column: span 12; }
        .meta-col-6 { grid-column: span 6; }
        .meta-col-4 { grid-column: span 4; }
        .file-show-path {
            word-break: break-all;
            overflow-wrap: anywhere;
        }
        .file-show-progress .progress {
            height: 10px;
            border-radius: 999px;
            background: #eee2d2;
        }
        .file-show-progress .progress-bar {
            background: #7b5e32;
        }
        @media (max-width: 1199.98px) {
            .overview-cards {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 991.98px) {
            .overview-cards,
            .quality-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
            .meta-col-6,
            .meta-col-4 {
                grid-column: span 12;
            }
            .chart-svg {
                min-width: 640px;
            }
            .file-overview-header-actions {
                justify-content: flex-start;
                width: 100%;
            }
            .file-overview-header-actions .btn {
                flex: 1 1 calc(50% - 8px);
                min-width: 180px;
                text-align: center;
            }
        }
        @media (max-width: 575.98px) {
            .file-overview-header-actions .btn {
                flex: 1 1 100%;
                min-width: 100%;
            }
        }
    </style>

    @php
        $fileRouteKey = (string) ($file->public_uid ?: $file->id);
        $editTagsDefault = collect((array) ($file->admin_tags_json ?? []))
            ->map(static fn ($tag): string => trim((string) $tag))
            ->filter(static fn (string $tag): bool => $tag !== '')
            ->values()
            ->implode(', ');
        $updateLabels = array_values((array) ($updatesSeries['labels'] ?? []));
        $updateValues = array_values((array) ($updatesSeries['values'] ?? []));
        $growthLabels = array_values((array) ($growthSeries['labels'] ?? []));
        $growthValues = array_values((array) ($growthSeries['values'] ?? []));

        $validPct = $totalRows > 0 ? (int) round(($processedRows / $totalRows) * 100) : 0;
        $invalidPct = max(0, 100 - $validPct);

        $lineW = 760;
        $lineH = 260;
        $linePadL = 44;
        $linePadR = 16;
        $linePadT = 18;
        $linePadB = 34;
        $linePlotW = $lineW - $linePadL - $linePadR;
        $linePlotH = $lineH - $linePadT - $linePadB;
        $lineCount = max(1, count($updateValues));
        $lineStepX = $lineCount > 1 ? ($linePlotW / ($lineCount - 1)) : 0;
        $lineMax = max(1, ...array_map(static fn ($v): int => (int) $v, $updateValues ?: [0]));
        $linePoints = [];
        foreach ($updateValues as $i => $value) {
            $x = $linePadL + ($lineStepX * $i);
            $y = $linePadT + $linePlotH - (((int) $value / $lineMax) * $linePlotH);
            $linePoints[] = ['x' => $x, 'y' => $y, 'value' => (int) $value, 'label' => (string) ($updateLabels[$i] ?? '')];
        }
        $linePath = collect($linePoints)->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L') . round($p['x'], 1) . ' ' . round($p['y'], 1))->implode(' ');

        $barW = 760;
        $barH = 260;
        $barPadL = 44;
        $barPadR = 16;
        $barPadT = 18;
        $barPadB = 34;
        $barPlotW = $barW - $barPadL - $barPadR;
        $barPlotH = $barH - $barPadT - $barPadB;
        $barCount = max(1, count($growthValues));
        $barSlot = $barPlotW / $barCount;
        $barWidth = max(14, min(46, (int) ($barSlot * 0.45)));
        $barMax = max(1, ...array_map(static fn ($v): int => (int) $v, $growthValues ?: [0]));

        $cardsData = [
            ['label' => 'Assinantes', 'value' => (int) ($cards['records'] ?? 0), 'hint' => 'Abrir lista de assinantes', 'icon' => 'bi-people-fill', 'url' => route('admin.customers.files.subscribers', ['id' => $fileRouteKey])],
            ['label' => 'Semânticas', 'value' => (int) ($cards['semantics'] ?? 0), 'hint' => 'Abrir módulo de semântica', 'icon' => 'bi-diagram-3-fill', 'url' => route('admin.semantic.index')],
            ['label' => 'Colunas', 'value' => (int) ($cards['columns'] ?? 0), 'hint' => 'Abrir lista filtrada', 'icon' => 'bi-columns-gap', 'url' => route('admin.customers.files.index', ['tenant_uuid' => $isGlobalSuper ? $tenantUuid : null, 'q' => 'coluna'])],
            ['label' => 'Modelos', 'value' => (int) ($cards['models'] ?? 0), 'hint' => 'Abrir lista filtrada', 'icon' => 'bi-ui-checks-grid', 'url' => route('admin.customers.files.index', ['tenant_uuid' => $isGlobalSuper ? $tenantUuid : null, 'q' => 'modelo'])],
            ['label' => 'Ferramentas', 'value' => (int) ($cards['tools'] ?? 0), 'hint' => 'Abrir integrações', 'icon' => 'bi-tools', 'url' => route('admin.integrations.index')],
        ];
    @endphp

    <x-admin.page-header
        title="Detalhes"
        subtitle="Visão executiva do arquivo: métricas, evolução e qualidade."
    >
        <x-slot:actions>
            <div class="file-overview-header-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#fileEditModal">Editar</button>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm rounded-pill">Voltar para Lista</a>
                <a href="{{ route('admin.customers.files.historyExport', ['id' => $fileRouteKey]) }}"
                   class="btn btn-outline-secondary btn-sm rounded-pill">Exportar updates CSV</a>
                <a href="{{ route('admin.customers.files.errorReport', ['id' => $fileRouteKey]) }}"
                   class="btn btn-outline-secondary btn-sm rounded-pill">Baixar relatório de erros</a>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <div class="alert alert-success admin-tenant-users-alert">{{ session('status') }}</div>
    @endif

    <div class="modal fade" id="fileEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content grade-modal-pattern">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title mb-1">Editar lista</h5>
                        <p class="grade-modal-hint mb-0">Edite metadados administrativos desta lista.</p>
                    </div>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST" action="{{ route('admin.customers.files.update', ['id' => $fileRouteKey]) }}" class="row g-3">
                        @csrf
                        @method('PUT')
                        @if($isGlobalSuper)
                            <input type="hidden" name="tenant_uuid" value="{{ $tenantUuid }}">
                        @endif
                        <div class="col-md-6">
                            <label class="grade-field-box">
                                <span class="grade-field-kicker">Nome da lista</span>
                                <input
                                    type="text"
                                    name="display_name"
                                    class="grade-field-input"
                                    maxlength="255"
                                    placeholder="Opcional"
                                    value="{{ old('display_name', (string) ($file->display_name ?? '')) }}"
                                >
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="grade-field-box">
                                <span class="grade-field-kicker">Tags</span>
                                <input
                                    type="text"
                                    name="admin_tags"
                                    class="grade-field-input"
                                    maxlength="500"
                                    placeholder="Ex: campanha, fevereiro, vip"
                                    value="{{ old('admin_tags', $editTagsDefault) }}"
                                >
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="grade-field-box">
                                <span class="grade-field-kicker">Notas internas</span>
                                <textarea
                                    name="admin_notes"
                                    class="grade-field-input"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="Observações internas"
                                >{{ old('admin_notes', (string) ($file->admin_notes ?? '')) }}</textarea>
                            </label>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-dark">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="overview-cards">
        @foreach($cardsData as $card)
            <a href="{{ $card['url'] }}" class="overview-card-link">
                <div class="overview-card-body">
                    <div class="overview-card-icon"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="overview-card-value">{{ $card['value'] }}</div>
                    <div class="overview-card-label">{{ $card['label'] }}</div>
                    <div class="overview-card-hint">{{ $card['hint'] }}</div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="overview-chart-card">
        <div class="overview-chart-header">
            <h3 class="overview-chart-title">Atualizações dos assinantes nos últimos 7 dias</h3>
            <span class="overview-chart-legend"><span class="legend-dot is-line"></span> Atualizações</span>
        </div>
        <div class="chart-wrap">
            <svg class="chart-svg" viewBox="0 0 {{ $lineW }} {{ $lineH }}" preserveAspectRatio="none" role="img" aria-label="Atividade dos últimos sete dias">
                @for($g = 0; $g <= 4; $g++)
                    @php $gy = $linePadT + (($linePlotH / 4) * $g); @endphp
                    <line x1="{{ $linePadL }}" y1="{{ $gy }}" x2="{{ $lineW - $linePadR }}" y2="{{ $gy }}" class="chart-grid"/>
                @endfor
                <line x1="{{ $linePadL }}" y1="{{ $linePadT + $linePlotH }}" x2="{{ $lineW - $linePadR }}" y2="{{ $linePadT + $linePlotH }}" class="chart-axis"/>
                <line x1="{{ $linePadL }}" y1="{{ $linePadT }}" x2="{{ $linePadL }}" y2="{{ $linePadT + $linePlotH }}" class="chart-axis"/>
                <path d="{{ $linePath }}" class="chart-line"/>
                @foreach($linePoints as $p)
                    <circle cx="{{ round($p['x'], 1) }}" cy="{{ round($p['y'], 1) }}" r="4.5" class="chart-point"/>
                    <text x="{{ round($p['x'], 1) }}" y="{{ round($p['y'] - 8, 1) }}" text-anchor="middle" class="chart-value">{{ $p['value'] }}</text>
                    <text x="{{ round($p['x'], 1) }}" y="{{ $lineH - 12 }}" text-anchor="middle" class="chart-label">{{ $p['label'] }}</text>
                @endforeach
            </svg>
        </div>
    </div>

    <div class="overview-chart-card">
        <div class="overview-chart-header">
            <h3 class="overview-chart-title">List growth (últimos 7 dias)</h3>
            <span class="overview-chart-legend"><span class="legend-dot is-bar"></span> Crescimento acumulado</span>
        </div>
        <div class="chart-wrap">
            <svg class="chart-svg" viewBox="0 0 {{ $barW }} {{ $barH }}" preserveAspectRatio="none" role="img" aria-label="Crescimento da lista nos últimos sete dias">
                @for($g = 0; $g <= 4; $g++)
                    @php $gy = $barPadT + (($barPlotH / 4) * $g); @endphp
                    <line x1="{{ $barPadL }}" y1="{{ $gy }}" x2="{{ $barW - $barPadR }}" y2="{{ $gy }}" class="chart-grid"/>
                @endfor
                <line x1="{{ $barPadL }}" y1="{{ $barPadT + $barPlotH }}" x2="{{ $barW - $barPadR }}" y2="{{ $barPadT + $barPlotH }}" class="chart-axis"/>
                <line x1="{{ $barPadL }}" y1="{{ $barPadT }}" x2="{{ $barPadL }}" y2="{{ $barPadT + $barPlotH }}" class="chart-axis"/>
                @foreach($growthValues as $i => $value)
                    @php
                        $safeVal = (int) $value;
                        $barHeight = ($safeVal / $barMax) * $barPlotH;
                        $x = $barPadL + ($barSlot * $i) + (($barSlot - $barWidth) / 2);
                        $y = $barPadT + $barPlotH - $barHeight;
                        $label = (string) ($growthLabels[$i] ?? '');
                    @endphp
                    <rect x="{{ round($x, 1) }}" y="{{ round($y, 1) }}" width="{{ $barWidth }}" height="{{ max(4, round($barHeight, 1)) }}" class="chart-bar"/>
                    <text x="{{ round($x + ($barWidth / 2), 1) }}" y="{{ round($y - 8, 1) }}" text-anchor="middle" class="chart-value">{{ $safeVal }}</text>
                    <text x="{{ round($x + ($barWidth / 2), 1) }}" y="{{ $barH - 12 }}" text-anchor="middle" class="chart-label">{{ $label }}</text>
                @endforeach
            </svg>
        </div>
    </div>

    <div class="overview-chart-card">
        <div class="overview-chart-header">
            <h3 class="overview-chart-title">Qualidade dos assinantes</h3>
        </div>
        <div class="quality-grid">
            <div class="quality-row">
                <div class="quality-row-kicker">Score de qualidade</div>
                <div class="quality-row-value">{{ $qualityScore }}/100</div>
                <div class="quality-progress"><div class="quality-progress-bar" style="width: {{ max(0, min(100, (int) $qualityScore)) }}%;"></div></div>
            </div>
            <div class="quality-row">
                <div class="quality-row-kicker">Linhas válidas</div>
                <div class="quality-row-value">{{ $processedRows }}</div>
                <div class="quality-progress"><div class="quality-progress-bar" style="width: {{ max(0, min(100, $validPct)) }}%;"></div></div>
            </div>
            <div class="quality-row">
                <div class="quality-row-kicker">Linhas inválidas</div>
                <div class="quality-row-value">{{ $invalidRows }}</div>
                <div class="quality-progress"><div class="quality-progress-bar" style="width: {{ max(0, min(100, $invalidPct)) }}%;"></div></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm admin-tenant-users-table-card mb-3">
        <div class="card-header bg-white"><strong>Resumo técnico do arquivo</strong></div>
        <div class="card-body">
            <div class="file-meta-grid">
                <div class="meta-col-4">
                    <div class="grade-profile-field-box">
                        <span class="grade-profile-field-kicker">Lista</span>
                        <div class="admin-metric-field-value">
                            {{ $fileRouteKey }}
                            <span class="badge bg-light text-dark border rounded-pill ms-2" title="ID interno">#{{ (int) $file->id }}</span>
                        </div>
                    </div>
                </div>
                <div class="meta-col-4">
                    <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Nome do arquivo</span><div class="admin-metric-field-value">{{ $file->display_name ?: $file->original_name ?: '—' }}</div></div>
                </div>
                <div class="meta-col-4">
                    <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Status</span>
                        <div class="admin-metric-field-value">
                            <span class="badge rounded-pill admin-tenant-users-status {{ in_array(strtolower((string) $file->status), ['done'], true) ? 'is-active' : (in_array(strtolower((string) $file->status), ['failed','canceled','cancelled'], true) ? 'is-disabled' : 'is-invited') }}">
                                {{ strtolower((string) $file->status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="meta-col-6">
                    <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Cliente (Tenant UUID)</span><div class="admin-metric-field-value">{{ $tenantUuid ?: '—' }}</div></div>
                </div>
                <div class="meta-col-6">
                    <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Hash</span><div class="admin-metric-field-value file-show-path">{{ $file->file_hash ?: '—' }}</div></div>
                </div>
                <div class="meta-col-12">
                    <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Caminho interno</span><div class="admin-metric-field-value file-show-path">{{ $file->file_path ?: '—' }}</div></div>
                </div>

                <div class="meta-col-12">
                    <div class="grade-profile-field-box file-show-progress">
                        <span class="grade-profile-field-kicker">Progresso</span>
                        <div class="progress mb-2 mt-1">
                            <div class="progress-bar" role="progressbar" style="width:{{ $progressPercent }}%;" aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="admin-metric-field-value">{{ $processedRows }}/{{ $totalRows }} ({{ $progressPercent }}%)</div>
                    </div>
                </div>

                <div class="meta-col-6">
                    <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Data de criação</span><div class="admin-metric-field-value">{{ optional($file->created_at)->format('d/m/Y H:i') ?: '—' }}</div></div>
                </div>
                <div class="meta-col-6">
                    <div class="grade-profile-field-box"><span class="grade-profile-field-kicker">Última atualização</span><div class="admin-metric-field-value">{{ optional($file->updated_at)->format('d/m/Y H:i') ?: '—' }}</div></div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <form method="POST" action="{{ route('admin.customers.files.reprocess', ['id' => $fileRouteKey]) }}" onsubmit="return confirm('Reprocessar este arquivo agora?')">
                @csrf
                @if($isGlobalSuper)
                    <input type="hidden" name="tenant_uuid" value="{{ $tenantUuid }}">
                @endif
                <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Reprocessar</button>
            </form>
            <form method="POST" action="{{ route('admin.customers.files.archive', ['id' => $fileRouteKey]) }}">
                @csrf
                @if($isGlobalSuper)
                    <input type="hidden" name="tenant_uuid" value="{{ $tenantUuid }}">
                @endif
                <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">{{ !empty($file->archived_at) ? 'Desarquivar' : 'Arquivar' }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
