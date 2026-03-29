<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1a1a1a; background: #fff; }
        /* CSS REPARADO */
.page { 
    padding: 18px 22px; 
    position: relative; 
    /* Eliminamos min-height: 98% aquí porque causa la página extra */
}

.page-break { 
    page-break-before: always; 
}

        /* HEADER */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header-logo-cell { width: 80px; vertical-align: middle; }
        .header-logo-cell img { width: 70px; }
        .header-info-cell { vertical-align: middle; padding-left: 12px; }
        .company-name { font-size: 16px; font-weight: bold; color: #10213E; }
        .company-sub { font-size: 8px; color: #555; line-height: 1.3; }
        .header-folio-cell { text-align: right; vertical-align: top; width: 160px; }
        .folio-number { font-size: 24px; font-weight: bold; color: #EE2857; }

        hr.divider { border: none; border-top: 2.5px solid #10213E; margin: 8px 0; }

        /* SECCIONES */
        .section-title { background-color: #10213E; color: white; font-size: 9px; font-weight: bold; text-transform: uppercase; padding: 5px 10px; margin-bottom: 5px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { padding: 4px 8px; border: 1px solid #d0d0d0; font-size: 9px; }
        .info-table .label { font-weight: bold; color: #10213E; width: 18%; background: #f5f7fa; font-size: 8px; }

        /* TESTIGOS Y COMBUSTIBLE */
        .condition-table { width: 100%; border-collapse: collapse; border: 1px solid #d0d0d0; }
        .witnesses-area { width: 68%; border-right: 1px solid #d0d0d0; padding: 10px 5px; text-align: center; }
        .fuel-area { width: 32%; padding: 10px; text-align: center; }

        .icon-box { display: inline-block; width: 18%; text-align: center; vertical-align: top; }
        .icon-img { width: 30px; height: 30px; margin-bottom: 3px; }
        
        /* Estilo para iconos inactivos (opacidad baja) */
        .icon-inactive { opacity: 0.2; }

        /* SERVICIOS */
        .service-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .service-table th { background: #f5f7fa; border: 1px solid #d0d0d0; padding: 6px; font-weight: bold; color: #10213E; text-align: left; }
        .service-table td { border: 1px solid #d0d0d0; padding: 6px; height: 18px; font-size: 9px; }

        /* FOOTERS */
        .footer-legal { font-size: 7px; color: #555; text-align: justify; margin-top: 8px; border: 1px solid #d0d0d0; padding: 6px; background: #fafafa; }
        .signature-table { width: 100%; margin-top: 20px; }
        .sig-line { border-top: 1px solid #333; width: 80%; margin: 0 auto 4px; }
        
        .photo-container { border: 1px solid #d0d0d0; border-radius: 10px; padding: 10px; margin-top: 10px; }
        .photo-img { width: 260px; height: auto; border-radius: 8px; }

        .page-footer { position: absolute; bottom: 0; width: 100%; border-top: 1px solid #d0d0d0; padding-top: 8px; font-size: 7px; color: #888; }
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
                <div class="company-sub">{{ $settings->address }}<br>Phone: {{ $settings->phone }} | Lic: #{{ $settings->license_number }}</div>
            </td>
            <td class="header-folio-cell">
                <div style="font-size:7px; color:#777; text-transform:uppercase;">Reception Order</div>
                <div class="folio-number">#{{ str_pad($recepcion->id, 4, '0', STR_PAD_LEFT) }}</div>
                <div style="font-size:8px; color:#555;">{{ now()->format('m/d/Y | H:i') }} hrs</div>
            </td>
        </tr>
    </table>

    <hr class="divider">

    <div class="section-title">Customer &amp; Vehicle Information</div>
    <table class="info-table">
        <tr>
            <td class="label">Name</td><td>{{ $recepcion->first_name }}</td>
            <td class="label">Make / Model</td><td>{{ $recepcion->brand?->name }} / {{ $recepcion->vehicleModel?->name }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td><td>{{ $recepcion->phone }}</td>
            <td class="label">Year</td><td>{{ $recepcion->year }}</td>
        </tr>
        <tr>
            <td class="label">Address</td><td>{{ $recepcion->address }}</td>
            <td class="label">VIN / Plate</td><td>{{ $recepcion->vin_serial }} / {{ $recepcion->plate }}</td>
        </tr>
        <tr>
            <td class="label">RFC</td><td>{{ $recepcion->rfc }}</td>
            <td class="label">Mileage</td><td><strong>{{ number_format($recepcion->miles) }}</strong> km</td>
        </tr>
    </table>

    <div class="section-title">Vehicle Condition at Entry</div>
    <table class="condition-table">
        <tr>
            <td class="witnesses-area">
                <div style="font-size: 7px; font-weight: bold; color: #10213E; margin-bottom: 8px;">DASHBOARD WARNING LIGHTS</div>
                @foreach(['engine' => 'MOTOR', 'abs' => 'ABS', 'oil' => 'OIL', 'battery' => 'BATTERY', 'temp' => 'TEMP'] as $key => $label)
                    @php 
                        $active = in_array($key, $recepcion->witnesses ?? []); 
                        $iconPath = public_path('images/icons/' . $key . '.svg');
                    @endphp
                    <div class="icon-box">
                        <img src="{{ $iconPath }}" class="icon-img {{ !$active ? 'icon-inactive' : '' }}">
                        <div style="font-size: 7px; font-weight: bold; color: {{ $active ? '#EE2857' : '#bbb' }}">{{ $label }}</div>
                    </div>
                @endforeach
            </td>
            <td class="fuel-area">
                @php
                    $fuelMap = ['E'=>5,'1/4'=>25,'1/2'=>50,'3/4'=>75,'F'=>100];
                    $fuelPct = $fuelMap[$recepcion->fuel_level] ?? 50;
                @endphp
                <div style="font-size: 7px; font-weight: bold;">FUEL LEVEL</div>
                <div style="font-size: 18px; font-weight: bold; color: #10213E; margin: 3px 0;">{{ $recepcion->fuel_level }}</div>
                <div style="width:100%; height:8px; background:#e8e8e8; border-radius:4px; overflow:hidden;">
                    <div style="width:{{ $fuelPct }}%; height:8px; background:#16a34a;"></div>
                </div>
            </td>
        </tr>
    </table>

    <div class="mt-8">
        <div class="section-title">Description of Service / Parts</div>
        <table class="service-table">
            <thead><tr><th style="width:65%;">Description</th><th style="width:15%;">Labor</th><th style="width:20%;">Amount</th></tr></thead>
            <tbody>
                <tr><td><span style="color:#EE2857; font-weight:bold;">Symptoms:</span> {{ $recepcion->symptoms }}</td><td>—</td><td>—</td></tr>
                @for($i = 0; $i < 10; $i++) <tr><td>&nbsp;</td><td></td><td></td></tr> @endfor
            </tbody>
        </table>
    </div>

    <div class="footer-legal">
        <strong>Terms:</strong> {{ $settings->clauses ?? 'I authorize the repairs. Storage fees apply after 48 hrs.' }}
    </div>

    <table class="signature-table">
        <tr style="text-align: center;">
            <td style="width: 50%;"><div class="sig-line"></div><div style="font-size: 8px; font-weight: bold;">CUSTOMER SIGNATURE</div></td>
            <td><div class="sig-line"></div><div style="font-size: 8px; font-weight: bold;">SERVICE ADVISOR</div></td>
        </tr>
    </table>

    <div style="text-align:center; margin-top:10px; font-size:9px; font-weight:bold; color:#10213E;">THANK YOU — GOD BLESS YOU</div>
</div>

{{-- PÁGINA 2: FOTOS --}}
@php $photos = is_array($recepcion->photos) ? $recepcion->photos : json_decode($recepcion->photos, true); @endphp
@if($photos && count($photos) > 0)
<div class="page page-break">
    <table style="width: 100%; border-bottom: 2px solid #10213E; margin-bottom: 10px;">
        <tr>
            <td style="width: 50%;">
                <div style="font-size: 10px; font-weight: bold; color: #10213E;">PHOTOGRAPHIC EVIDENCE</div>
                <div style="font-size: 8px; color: #555;">CLIENT: {{ strtoupper($recepcion->first_name) }}</div>
            </td>
            <td style="text-align: right; vertical-align: bottom;">
                <div style="font-size: 14px; font-weight: bold; color: #EE2857;">ORDER #{{ str_pad($recepcion->id, 4, '0', STR_PAD_LEFT) }}</div>
                <div style="font-size: 7px; color: #777;">VEHICLE: {{ $recepcion->brand?->name }} / {{ $recepcion->vehicleModel?->name }}</div>
            </td>
        </tr>
    </table>

    <div class="photo-container">
        <table style="width: 100%;">
            @foreach(array_chunk($photos, 2) as $row)
            <tr>
                @foreach($row as $photo)
                <td style="text-align: center; padding: 10px;">
                    <img src="{{ public_path('storage/' . str_replace('\\', '/', $photo)) }}" class="photo-img">
                </td>
                @endforeach
            </tr>
            @endforeach
        </table>
    </div>

    {{-- PIE DE PÁGINA HOJA 2 --}}
    <div class="page-footer">
        <table style="width: 100%;">
            <tr>
                <td><strong>{{ $settings->company_name }}</strong> | {{ $settings->address }}</td>
                <td style="text-align: right;">Anexo Oficial a Orden #{{ str_pad($recepcion->id, 4, '0', STR_PAD_LEFT) }} | Página 2 de 2</td>
            </tr>
        </table>
    </div>
</div>
@endif
</body>
</html>