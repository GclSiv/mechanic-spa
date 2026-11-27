<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', sans-serif; font-size: 9px; color: #1a1a1a; background: #fff; }
        .page { padding: 16px 20px; }
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
            background-color: #10213E; color: white; font-size: 8.5px; font-weight: bold;
            text-transform: uppercase; letter-spacing: 0.05em; padding: 5px 10px; margin-bottom: 4px;
        }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table thead tr th {
            background: #f0f3f8; border: 1px solid #c8cfd8; padding: 7px 6px; font-weight: bold;
            color: #10213E; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .report-table tbody tr td { border: 1px solid #d8dde5; padding: 6px 6px; font-size: 8px; vertical-align: middle; }
        .report-table tbody tr:nth-child(even) td { background-color: #f9fafc; }
        .col-fecha   { width: 9%;  text-align: center; }
        .col-folio   { width: 10%; text-align: center; font-weight: bold; color: #10213E; }
        .col-cliente { width: 33%; }
        .col-tipo    { width: 13%; text-align: center; }
        .col-metodo  { width: 10%; text-align: center; }
        .col-money   { width: 15%; text-align: right; font-family: 'Courier New', monospace; font-weight: bold; }
        .totals-row td {
            background-color: #10213E !important; color: #ffffff !important; font-weight: bold;
            font-size: 9px; border: 1px solid #10213E; padding: 8px 6px;
        }
        .totals-row .col-money { color: #ffffff !important; font-size: 10px; }
        .empty-row td { text-align: center; padding: 20px; color: #888; font-style: italic; }
        .summary-box {
            margin-top: 14px; border: 1px solid #d0d0d0; padding: 10px 14px; background: #f9fafc;
            display: inline-block; min-width: 260px; float: right;
        }
        .summary-line { margin-bottom: 4px; font-size: 8.5px; overflow: hidden; }
        .summary-line span.v { float: right; font-family: 'Courier New', monospace; }
        .summary-line.grand {
            border-top: 1.5px solid #10213E; padding-top: 5px; margin-top: 5px;
            font-size: 10px; font-weight: bold; color: #10213E;
        }
        .summary-count { float: left; margin-top: 14px; font-size: 8px; color: #555; }
        .footer-note {
            clear: both; margin-top: 18px; font-size: 7px; color: #888;
            border-top: 1px solid #e0e0e0; padding-top: 6px; text-align: center;
        }
    </style>
</head>
<body>
@php
    $en = $lang === 'en';
    $fmt = fn ($n) => '$' . number_format((float) $n, 2);
    $tipos = [
        'general'   => $en ? 'General / advance' : 'General / anticipo',
        'principal' => $en ? 'Main quote' : 'Cotización principal',
        'adicional' => $en ? 'Additional quote' : 'Cotización adicional',
    ];
    $metodos = [
        'Cash' => $en ? 'Cash' : 'Efectivo', 'Efectivo' => $en ? 'Cash' : 'Efectivo',
        'Card' => $en ? 'Card' : 'Tarjeta', 'Tarjeta' => $en ? 'Card' : 'Tarjeta',
        'Transfer' => $en ? 'Transfer' : 'Transferencia', 'Transferencia' => $en ? 'Transfer' : 'Transferencia',
    ];
    $periodoLabel = \Carbon\Carbon::createFromDate($year, $month, 1)->locale($en ? 'en' : 'es')->translatedFormat('F Y');
@endphp
<div class="page">

    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
@if($settings->logoFilePath())
<img src="{{ $settings->logoFilePath() }}" alt="Logo">
@endif
            </td>
            <td class="header-info-cell">
                <div class="company-name">{{ $settings->company_name }}</div>
                <div class="company-sub">
                    @if(!empty($settings->address)){{ $settings->address }}<br>@endif
                    @if(!empty($settings->phone))Tel. {{ $settings->phone }}@endif
                </div>
            </td>
            <td class="header-title-cell">
                <div class="report-title">{{ $en ? 'INCOME REPORT' : 'REPORTE DE INGRESOS' }}</div>
                <div class="report-period">{{ mb_strtoupper($periodoLabel) }}</div>
                <div class="report-date">{{ $en ? 'Generated' : 'Generado' }}: {{ now()->format($en ? 'm/d/Y' : 'd/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <hr class="divider">

    <div class="section-title">
        {{ $en ? 'Payments received in the period (cash collected)' : 'Pagos cobrados en el periodo (ingreso real)' }}
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-fecha">{{ $en ? 'Date' : 'Fecha' }}</th>
                <th class="col-folio">{{ $en ? 'Invoice' : 'Folio' }}</th>
                <th class="col-cliente">{{ $en ? 'Client / Vehicle' : 'Cliente / Vehículo' }}</th>
                <th class="col-tipo">{{ $en ? 'Type' : 'Tipo' }}</th>
                <th class="col-metodo">{{ $en ? 'Method' : 'Método' }}</th>
                <th class="col-money">{{ $en ? 'Amount collected' : 'Monto cobrado' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="col-fecha">{{ $row['fecha'] }}</td>
                    <td class="col-folio">{{ $row['folio'] }}</td>
                    <td class="col-cliente">
                        <strong>{{ $row['cliente'] }}</strong>
                        @if($row['vehiculo'] !== '—')
                            <br><span style="color:#666; font-size:7.5px;">{{ $row['vehiculo'] }}</span>
                        @endif
                    </td>
                    <td class="col-tipo">{{ $tipos[$row['tipo']] ?? $row['tipo'] }}</td>
                    <td class="col-metodo">{{ $metodos[$row['metodo']] ?? $row['metodo'] }}</td>
                    <td class="col-money">{{ $fmt($row['monto']) }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="6">
                        {{ $en ? 'No payments were received in this period.' : 'No se registraron pagos en este periodo.' }}
                    </td>
                </tr>
            @endforelse

            @if($rows->count() > 0)
                <tr class="totals-row">
                    <td colspan="5" style="text-align:left;">
                        {{ $en ? 'TOTAL COLLECTED' : 'TOTAL COBRADO' }}
                        <span style="font-size:7.5px; font-weight:normal; opacity:0.8;">
                            ({{ $totals['pagos'] }} {{ $en ? 'payments' : 'pagos' }} · {{ $totals['ordenes'] }} {{ $en ? 'orders' : 'órdenes' }})
                        </span>
                    </td>
                    <td class="col-money">{{ $fmt($totals['ingresos']) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if($rows->count() > 0)
    <div style="overflow: hidden; margin-top: 14px;">
        <div class="summary-box">
            <div class="summary-line grand">
                <span>{{ $en ? 'Income (collected):' : 'Ingresos cobrados:' }}</span>
                <span class="v">{{ $fmt($totals['ingresos']) }}</span>
            </div>
            <div class="summary-line">
                <span>{{ $en ? 'Number of payments:' : 'Número de pagos:' }}</span>
                <span class="v">{{ $totals['pagos'] }}</span>
            </div>
            <div class="summary-line">
                <span>{{ $en ? 'General payments and advances:' : 'Pagos generales y anticipos:' }}</span>
                <span class="v">{{ $fmt($totals['generales']) }}</span>
            </div>
            <div class="summary-line">
                <span>{{ $en ? 'Payments tied to a quote:' : 'Pagos asociados a cotización:' }}</span>
                <span class="v">{{ $fmt($totals['asociados']) }}</span>
            </div>
        </div>
    </div>
    @endif

    <div class="footer-note">
        {{ $en
            ? 'Income = payments recorded in the period (by payment date). Technical totals, authorized amounts not yet paid and rejected quotes are not income.'
            : 'Ingreso = pagos registrados en el periodo (por fecha de pago). Los totales técnicos, los montos autorizados aún no pagados y las cotizaciones rechazadas no son ingreso.' }}
    </div>
</div>
</body>
</html>
