<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', sans-serif; font-size: 9px; color: #1a1a1a; background: #fff; }
        .page { padding: 16px 20px; }

        /* ── HEADER ───────────────────────────────────────────── */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header-logo-cell  { width: 80px; vertical-align: middle; }
        .header-logo-cell img { width: 65px; }
        .header-info-cell  { vertical-align: middle; padding-left: 12px; }
        .company-name      { font-size: 15px; font-weight: bold; color: #10213E; }
        .company-sub       { font-size: 7.5px; color: #555; margin-top: 2px; }
        .header-title-cell { text-align: right; vertical-align: top; width: 200px; }
        .report-title      { font-size: 20px; font-weight: bold; color: #10213E; }
        .report-period     { font-size: 11px; font-weight: bold; color: #555; margin-top: 3px; }
        .report-date       { font-size: 7.5px; color: #888; margin-top: 2px; }

        hr.divider { border: none; border-top: 2.5px solid #10213E; margin: 8px 0; }

        .section-title {
            background-color: #10213E;
            color: white;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 5px 10px;
            margin-bottom: 4px;
        }

        /* ── TABLA PRINCIPAL ──────────────────────────────────── */
        .report-table { width: 100%; border-collapse: collapse; }

        .report-table thead tr th {
            background: #f0f3f8;
            border: 1px solid #c8cfd8;
            padding: 7px 6px;
            font-weight: bold;
            color: #10213E;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .report-table tbody tr td {
            border: 1px solid #d8dde5;
            padding: 6px 6px;
            font-size: 8px;
            vertical-align: middle;
        }

        .report-table tbody tr:nth-child(even) td {
            background-color: #f9fafc;
        }

        .col-folio    { width: 9%;  text-align: center; font-weight: bold; color: #10213E; }
        .col-fecha    { width: 9%;  text-align: center; }
        .col-cliente  { width: 28%; }
        .col-money    { width: 12%; text-align: right; font-family: 'Courier New', monospace; }
        .col-tax      { width: 10%; text-align: right; font-family: 'Courier New', monospace; color: #555; }
        .col-total    { width: 12%; text-align: right; font-family: 'Courier New', monospace; font-weight: bold; }

        /* ── FILA TOTALES ─────────────────────────────────────── */
        .totals-row td {
            background-color: #10213E !important;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #10213E;
            padding: 8px 6px;
        }
        .totals-row .col-money,
        .totals-row .col-tax,
        .totals-row .col-total {
            font-family: 'Courier New', monospace;
            color: #ffffff !important;
        }
        .totals-row .col-total { font-size: 10px; }

        /* ── FILA VACÍA ────────────────────────────────────────── */
        .empty-row td {
            text-align: center;
            padding: 20px;
            color: #888;
            font-style: italic;
        }

        /* ── RESUMEN ──────────────────────────────────────────── */
        .summary-box {
            margin-top: 14px;
            border: 1px solid #d0d0d0;
            padding: 10px 14px;
            background: #f9fafc;
            display: inline-block;
            min-width: 220px;
            float: right;
        }
        .summary-box .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 8.5px;
        }
        .summary-box .summary-line.grand {
            border-top: 1.5px solid #10213E;
            padding-top: 5px;
            margin-top: 5px;
            font-size: 10px;
            font-weight: bold;
            color: #10213E;
        }
        .summary-count {
            float: left;
            margin-top: 14px;
            font-size: 8px;
            color: #555;
        }

        .footer-note {
            clear: both;
            margin-top: 18px;
            font-size: 7px;
            color: #888;
            border-top: 1px solid #e0e0e0;
            padding-top: 6px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ── HEADER ───────────────────────────────────────────── --}}
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                <img src="{{ public_path('images/Taller.png') }}" alt="Logo">
            </td>
            <td class="header-info-cell">
                <div class="company-name">{{ $settings->company_name ?: 'Mi taller' }}</div>
                <div class="company-sub">
                    {{ $settings->address ?? '' }}<br>
                    {{ $settings->phone ?? '' }}
                </div>
            </td>
            <td class="header-title-cell">
                @if($lang === 'en')
                    <div class="report-title">INCOME REPORT</div>
                @else
                    <div class="report-title">REPORTE DE INGRESOS</div>
                @endif
                <div class="report-period">
                    @php
                        $periodoLabel = \Carbon\Carbon::createFromDate($year, $month, 1)
                            ->locale($lang === 'en' ? 'en' : 'es')
                            ->translatedFormat('F Y');
                        echo mb_strtoupper($periodoLabel);
                    @endphp
                </div>
                <div class="report-date">
                    @if($lang === 'en')
                        Generated: {{ now()->format('m/d/Y') }}
                    @else
                        Generado: {{ now()->format('d/m/Y') }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <hr class="divider">

    {{-- ── ENCABEZADO DE SECCIÓN ─────────────────────────────── --}}
    <div class="section-title">
        @if($lang === 'en')
            Completed Orders — Repaired &amp; Delivered
        @else
            Órdenes Completadas — Reparadas y Entregadas
        @endif
    </div>

    {{-- ── TABLA PRINCIPAL ──────────────────────────────────── --}}
    <table class="report-table">
        <thead>
            <tr>
                <th class="col-folio">{{ $lang === 'en' ? 'Invoice' : 'Folio' }}</th>
                <th class="col-fecha">{{ $lang === 'en' ? 'Date' : 'Fecha' }}</th>
                <th class="col-cliente">{{ $lang === 'en' ? 'Client / Vehicle' : 'Cliente / Vehículo' }}</th>
                <th class="col-money">{{ $lang === 'en' ? 'Parts' : 'Partes' }}</th>
                <th class="col-tax">TAX / IVA</th>
                <th class="col-money">{{ $lang === 'en' ? 'Labor' : 'Mano de Obra' }}</th>
                <th class="col-total">{{ $lang === 'en' ? 'Total' : 'Suma Total' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="col-folio">{{ $row['folio'] }}</td>
                    <td class="col-fecha">{{ $row['fecha'] }}</td>
                    <td class="col-cliente">
                        <strong>{{ $row['cliente'] }}</strong>
                        @if($row['vehiculo'] !== '—')
                            <br><span style="color:#666; font-size:7.5px;">{{ $row['vehiculo'] }}</span>
                        @endif
                    </td>
                    <td class="col-money">${{ number_format($row['parts_subtotal'], 2) }}</td>
                    <td class="col-tax">${{ number_format($row['tax'], 2) }}</td>
                    <td class="col-money">${{ number_format($row['labor_subtotal'], 2) }}</td>
                    <td class="col-total">${{ number_format($row['total'], 2) }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="7">
                        {{ $lang === 'en'
                            ? 'No completed orders found for this period.'
                            : 'No se encontraron órdenes completadas para este período.' }}
                    </td>
                </tr>
            @endforelse

            {{-- ── FILA TOTALES GENERALES ─────────────────────── --}}
            @if($rows->count() > 0)
                <tr class="totals-row">
                    <td class="col-folio" colspan="3" style="text-align:left; letter-spacing:0.05em;">
                        {{ $lang === 'en' ? '▶ GRAND TOTALS' : '▶ TOTALES GENERALES' }}
                        <span style="font-size:7.5px; font-weight:normal; opacity:0.8;">
                            ({{ $rows->count() }} {{ $lang === 'en' ? 'orders' : 'órdenes' }})
                        </span>
                    </td>
                    <td class="col-money">${{ number_format($totals['parts_subtotal'], 2) }}</td>
                    <td class="col-tax">${{ number_format($totals['tax'], 2) }}</td>
                    <td class="col-money">${{ number_format($totals['labor_subtotal'], 2) }}</td>
                    <td class="col-total">${{ number_format($totals['total'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ── RESUMEN FLOTANTE ─────────────────────────────────── --}}
    @if($rows->count() > 0)
    <div style="overflow: hidden; margin-top: 14px;">
        <div class="summary-count">
            <strong>{{ $rows->count() }}</strong>
            {{ $lang === 'en' ? 'completed orders in this period' : 'órdenes completadas en este período' }}
        </div>
        <div class="summary-box">
            <div class="summary-line">
                <span>{{ $lang === 'en' ? 'Parts Subtotal:' : 'Subtotal Partes:' }}</span>
                <span>${{ number_format($totals['parts_subtotal'], 2) }}</span>
            </div>
            <div class="summary-line">
                <span>{{ $lang === 'en' ? 'Labor Subtotal:' : 'Subtotal M. de Obra:' }}</span>
                <span>${{ number_format($totals['labor_subtotal'], 2) }}</span>
            </div>
            <div class="summary-line">
                <span>{{ $lang === 'en' ? 'Tax / IVA:' : 'Tax / IVA:' }}</span>
                <span>${{ number_format($totals['tax'], 2) }}</span>
            </div>
            <div class="summary-line grand">
                <span>{{ $lang === 'en' ? 'TOTAL REVENUE:' : 'INGRESO TOTAL:' }}</span>
                <span>${{ number_format($totals['total'], 2) }}</span>
            </div>
        </div>
    </div>
    @endif

    {{-- ── FOOTER ───────────────────────────────────────────── --}}
    <div class="footer-note">
        {{ $settings->company_name ?: 'Mi taller' }} &mdash;
        {{ $lang === 'en'
            ? 'This report includes only orders with status Repaired or Delivered. Generated automatically.'
            : 'Este reporte incluye únicamente órdenes con estatus Reparado o Entregado. Generado automáticamente.' }}
    </div>

</div>
</body>
</html>
