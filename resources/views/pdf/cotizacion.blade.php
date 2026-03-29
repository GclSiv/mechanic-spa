<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1a1a1a; background: #fff; }
        .page { padding: 18px 22px; position: relative; min-height: 98%; }

        /* HEADER */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header-logo-cell { width: 80px; vertical-align: middle; }
        .header-logo-cell img { width: 70px; }
        .header-info-cell { vertical-align: middle; padding-left: 12px; }
        .company-name { font-size: 16px; font-weight: bold; color: #10213E; }
        .header-folio-cell { text-align: right; vertical-align: top; width: 180px; }
        .quote-title { font-size: 22px; font-weight: bold; color: #16a34a; }

        hr.divider { border: none; border-top: 2.5px solid #10213E; margin: 8px 0; }

        .section-title { background-color: #10213E; color: white; font-size: 9px; font-weight: bold; text-transform: uppercase; padding: 5px 10px; margin-bottom: 5px; }

        /* TABLA DE ITEMS */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .items-table th { background: #f5f7fa; border: 1px solid #d0d0d0; padding: 8px; font-weight: bold; color: #10213E; text-align: left; }
        .items-table td { border: 1px solid #d0d0d0; padding: 8px; height: 22px; font-size: 9px; }
        
        /* TOTALES */
        .totals-table { width: 100%; margin-top: 10px; }
        .total-row td { padding: 5px 8px; font-size: 10px; }
        .grand-total { background: #10213E; color: white; font-weight: bold; font-size: 12px; }

        .footer-note { font-size: 7.5px; color: #555; margin-top: 15px; border: 1px solid #d0d0d0; padding: 8px; background: #fafafa; }
    </style>
</head>
<body>
<div class="page">
    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td class="header-logo-cell"><img src="{{ public_path('images/Taller.png') }}"></td>
            <td class="header-info-cell">
                <div class="company-name">{{ $settings->company_name }}</div>
                <div style="font-size: 8px; color: #555;">{{ $settings->address }}<br>Phone: {{ $settings->phone }}</div>
            </td>
            <td class="header-folio-cell">
                <div class="quote-title">COTIZACIÓN</div>
                <div style="font-size: 14px; font-weight: bold;">#Q-{{ str_pad($orden->id, 4, '0', STR_PAD_LEFT) }}</div>
                <div style="font-size: 8px; color: #555;">Válido hasta: {{ now()->addDays(5)->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <hr class="divider">

    {{-- RESUMEN VEHÍCULO --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
        <tr>
            <td style="width: 50%; padding: 5px; border: 1px solid #d0d0d0;"><strong>CLIENTE:</strong> {{ $recepcion->first_name }}</td>
            <td style="padding: 5px; border: 1px solid #d0d0d0;"><strong>VEHÍCULO:</strong> {{ $recepcion->brand?->name }} {{ $recepcion->vehicleModel?->name }} ({{ $recepcion->year }})</td>
        </tr>
    </table>

    <div class="section-title">Detalle de Reparación y Repuestos</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%; text-align: center;">CANT</th>
                <th style="width: 50%;">DESCRIPCIÓN</th>
                <th style="width: 20%; text-align: right;">PRECIO UNIT.</th>
                <th style="width: 20%; text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            {{-- RECORRIDO DINÁMICO DE ITEMS --}}
            @foreach($orden->items as $item)
                <tr>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td>{{ $item->description }}</td>
                    <td style="text-align: right;">${{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: right;">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach

            {{-- RELLENO PARA MANTENER LA ESTRUCTURA CUADRADA --}}
            @for($i = count($orden->items); $i < 10; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    {{-- BLOQUE DE TOTALES --}}
    <table class="totals-table">
        <tr>
            <td style="width: 60%; vertical-align: top; border: none;">
                <div style="font-size: 8px; color: #777;">
                    <strong>Notas:</strong><br>
                    - Los precios incluyen mano de obra e instalación.<br>
                    - Refacciones sujetas a disponibilidad del proveedor.<br>
                    - Garantía de 30 días en mano de obra.
                </div>
            </td>
            <td style="width: 40%; padding: 0;">
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #d0d0d0;">
                    <tr class="total-row">
                        <td style="font-weight: bold; color: #10213E;">SUBTOTAL</td>
                        <td style="text-align: right;">${{ number_format($subtotal, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td style="font-weight: bold; color: #10213E;">IVA (16%)</td>
                        <td style="text-align: right;">${{ number_format($iva, 2) }}</td>
                    </tr>
                    <tr class="total-row grand-total">
                        <td>TOTAL NETO</td>
                        <td style="text-align: right;">${{ number_format($total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        <strong>Condiciones:</strong> {{ $settings->clauses ?? 'Esta cotización no implica la realización del trabajo hasta ser aceptada por el cliente.' }}
    </div>

    <table style="width: 100%; margin-top: 30px; text-align: center;">
        <tr>
            <td style="width: 50%;"><div style="border-top: 1px solid #333; width: 80%; margin: 0 auto;"></div><strong>ACEPTO COTIZACIÓN</strong></td>
            <td><div style="border-top: 1px solid #333; width: 80%; margin: 0 auto;"></div><strong>ASESOR {{ strtoupper($settings->company_name) }}</strong></td>
        </tr>
    </table>
</div>
</body>
</html>