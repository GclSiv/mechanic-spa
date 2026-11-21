<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1a1a1a; }
        .page { padding: 20px 24px; }
        .row { width: 100%; }
        .row td { vertical-align: top; }
        .brand { font-size: 16px; font-weight: bold; color: #10213E; }
        .muted { color: #6b7280; font-size: 9px; }
        .title { font-size: 20px; font-weight: bold; color: #16a34a; text-align: right; }
        .section { background: #10213E; color: #fff; font-size: 9px; font-weight: bold; text-transform: uppercase; padding: 5px 8px; margin: 12px 0 6px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f5f7fa; border: 1px solid #d0d0d0; padding: 6px; text-align: left; color: #10213E; }
        table.items td { border: 1px solid #d0d0d0; padding: 6px; }
        .num { text-align: right; }
        .totals { width: 42%; margin-left: auto; margin-top: 8px; }
        .totals td { padding: 3px 6px; }
        .grand { font-size: 12px; font-weight: bold; color: #10213E; border-top: 2px solid #10213E; }
        .notes { border: 1px solid #e5e7eb; padding: 8px; margin-top: 10px; min-height: 40px; }
        .sign { width: 100%; margin-top: 45px; }
        .sign td { width: 45%; text-align: center; font-size: 9px; }
        .sign .line { border-top: 1px solid #1a1a1a; padding-top: 4px; }
    </style>
</head>
<body>
<div class="page">
    <table class="row">
        <tr>
            <td style="width:70px">
                <img src="{{ $settings->logoFilePath() }}" alt="Logo" style="width:60px; height:auto;">
            </td>
            <td>
                <div class="brand">{{ $settings->company_name }}</div>
                @if(!empty($settings->razon_social))<div class="muted">{{ $settings->razon_social }}</div>@endif
                @if(!empty($settings->rfc))<div class="muted">RFC: {{ $settings->rfc }}</div>@endif
                @if(!empty($settings->address))<div class="muted">{{ $settings->address }}</div>@endif
                @if(!empty($settings->phone))<div class="muted">Tel. {{ $settings->phone }}</div>@endif
                @if(!empty($settings->email))<div class="muted">{{ $settings->email }}</div>@endif
            </td>
            <td>
                <div class="title">COTIZACIÓN</div>
                <div class="muted" style="text-align:right">Folio: <strong>{{ $quote->folio }}</strong> (versión {{ $quote->version }})</div>
                <div class="muted" style="text-align:right">Fecha: {{ optional($quote->created_at)->format('d/m/Y') }}</div>
                <div class="muted" style="text-align:right">Vigente hasta: {{ optional($quote->valid_until)->format('d/m/Y') ?? 'Sin vigencia' }}</div>
            </td>
        </tr>
    </table>

    <div class="section">Datos del cliente y vehículo</div>
    <table class="row">
        <tr>
            <td>
                <strong>Cliente:</strong> {{ trim(($recepcion?->client?->first_name ?? '') . ' ' . ($recepcion?->client?->last_name ?? '')) }}<br>
                <strong>Teléfono:</strong> {{ $recepcion?->client?->phone ?? '—' }}<br>
                <strong>RFC:</strong> {{ $recepcion?->client?->rfc ?? '—' }}
            </td>
            <td>
                <strong>Vehículo:</strong> {{ $recepcion?->vehicle?->brand?->name ?? $recepcion?->vehicle?->brand ?? '' }} {{ $recepcion?->vehicle?->vehicleModel?->name ?? '' }} {{ $recepcion?->vehicle?->year ?? '' }}<br>
                <strong>Placas:</strong> {{ $recepcion?->vehicle?->plate ?? '—' }}<br>
                <strong>VIN:</strong> {{ $recepcion?->vehicle?->vin ?? '—' }}
            </td>
        </tr>
    </table>

    <div class="section">Conceptos</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width:12%">Tipo</th>
                <th>Descripción</th>
                <th class="num" style="width:10%">Cant.</th>
                <th class="num" style="width:16%">P. unitario</th>
                <th class="num" style="width:16%">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->type === 'labor' ? 'Mano de obra' : 'Refacción' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ \App\Support\Cantidad::texto($item->type, $item->quantity) }}</td>
                    <td class="num">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Refacciones (sin IVA adicional)</td><td class="num">${{ number_format($quote->parts_subtotal, 2) }}</td></tr>
        <tr><td>Mano de obra</td><td class="num">${{ number_format($quote->labor_subtotal, 2) }}</td></tr>
        <tr><td>Subtotal</td><td class="num">${{ number_format($quote->subtotal, 2) }}</td></tr>
        <tr><td>IVA ({{ rtrim(rtrim(number_format($quote->tax_rate, 2), '0'), '.') }}%)</td><td class="num">${{ number_format($quote->tax, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">${{ number_format($quote->total, 2) }} MXN</td></tr>
    </table>

    @if($quote->notes)
        <div class="section">Notas</div>
        <div class="notes">{{ $quote->notes }}</div>
    @endif

    <div class="section">Condiciones</div>
    <div class="muted">
        Precios en pesos mexicanos. Esta cotización es válida hasta la fecha indicada; después de esa fecha los precios pueden cambiar.
        Los trabajos se inician únicamente con la aprobación del cliente. Cualquier trabajo adicional requiere una nueva cotización.
    </div>
    @if(!empty($settings->clauses))
        <div class="section">Políticas del taller</div>
        <div class="notes" style="white-space: pre-line;">{{ $settings->clauses }}</div>
    @endif
    @if(!empty($settings->warranty_notes))
        <div class="section">Garantía</div>
        <div class="notes" style="white-space: pre-line;">{{ $settings->warranty_notes }}</div>
    @endif

    <table class="sign">
        <tr>
            <td><div class="line">Firma de aceptación del cliente</div></td>
            <td></td>
            <td><div class="line">Asesor de servicio</div></td>
        </tr>
    </table>
</div>
</body>
</html>
