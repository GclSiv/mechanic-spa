<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1a1a1a; }

        .page { padding: 18px 22px; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .header-logo-cell { width: 90px; vertical-align: middle; }
        .header-logo-cell img { width: 80px; }
        .header-info-cell { vertical-align: middle; padding-left: 12px; }
        .company-name { font-size: 17px; font-weight: bold; color: #10213E; letter-spacing: 0.5px; }
        .company-sub { font-size: 9px; color: #555; margin-top: 2px; line-height: 1.6; }
        .header-folio-cell { text-align: right; vertical-align: top; width: 160px; }
        .folio-label { font-size: 9px; color: #777; text-transform: uppercase; letter-spacing: 1px; }
        .folio-number { font-size: 26px; font-weight: bold; color: #EE2857; line-height: 1; }
        .folio-date { font-size: 9px; color: #555; margin-top: 4px; }

        hr.divider { border: none; border-top: 2.5px solid #10213E; margin: 8px 0; }

        .section-title {
            background-color: #10213E;
            color: white;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 5px 10px;
        }

        .info-table { width: 100%; border-collapse: collapse; font-size: 9.5px; }
        .info-table td { padding: 5px 8px; border: 1px solid #d0d0d0; }
        .info-table .label { font-weight: bold; color: #10213E; width: 15%; background: #f5f7fa; white-space: nowrap; }
        .info-table .value { width: 35%; }

        .witnesses-table { width: 100%; border-collapse: collapse; }
        .witnesses-table td { width: 20%; text-align: center; padding: 10px 4px 8px; border: 1px solid #d0d0d0; }
        .witness-circle-active {
            display: inline-block; width: 34px; height: 34px; border-radius: 17px;
            background-color: #EE2857; border: 2.5px solid #c0001e;
            line-height: 30px; font-size: 18px; font-weight: bold; color: white; text-align: center;
        }
        .witness-circle-inactive {
            display: inline-block; width: 34px; height: 34px; border-radius: 17px;
            background-color: #e8e8e8; border: 2px solid #ccc;
            line-height: 30px; font-size: 18px; font-weight: bold; color: #bbb; text-align: center;
        }
        .witness-label-active { font-size: 8px; font-weight: bold; color: #EE2857; margin-top: 4px; }
        .witness-label-inactive { font-size: 8px; font-weight: bold; color: #bbb; margin-top: 4px; }

        .service-table { width: 100%; border-collapse: collapse; font-size: 9.5px; }
        .service-table th { background: #f5f7fa; border: 1px solid #d0d0d0; padding: 5px 8px; font-weight: bold; color: #10213E; text-align: left; font-size: 9px; }
        .service-table td { border: 1px solid #d0d0d0; padding: 5px 8px; height: 18px; }

        .footer-legal { font-size: 7.5px; color: #555; text-align: justify; margin-top: 12px; border: 1px solid #d0d0d0; padding: 8px 10px; line-height: 1.5; background: #fafafa; }

        .signature-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .signature-table td { border: none; padding: 0 10px; text-align: center; }
        .sig-line { border-top: 1.5px solid #333; margin: 0 auto; width: 80%; margin-bottom: 4px; }
        .sig-label { font-size: 8px; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
        .checkbox { display: inline-block; width: 9px; height: 9px; border: 1px solid #555; margin-right: 2px; vertical-align: middle; }

        .text-red { color: #EE2857; font-weight: bold; }
        .mt-8 { margin-top: 8px; }
    </style>
</head>
<body>
<div class="page">

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                <img src="{{ public_path('images/Taller.png') }}">
            </td>
            <td class="header-info-cell">
                <div class="company-name">{{ $settings->company_name }}</div>
                <div class="company-sub">
                    {{ $settings->address }}<br>
                    Phone: {{ $settings->phone }} &nbsp;&nbsp; License: #{{ $settings->license_number }}
                </div>
            </td>
<td class="header-folio-cell">
    <div style="font-size:8px; color:#777; text-transform:uppercase; letter-spacing:2px; margin-bottom:2px;">Reception Order</div>
    <div style="font-size:36px; font-weight:bold; color:#EE2857; line-height:1; letter-spacing:-1px;">
        #{{ str_pad($recepcion->id, 4, '0', STR_PAD_LEFT) }}
    </div>
    <div style="border-top:2px solid #EE2857; margin:5px 0;"></div>
    <div style="font-size:9px; color:#555;">
        <strong style="color:#10213E;">Date:</strong> {{ now()->format('m/d/Y') }}<br>
        <strong style="color:#10213E;">Time:</strong> {{ now()->format('H:i') }} hrs
    </div>
    <div style="margin-top:5px; background:#10213E; color:white; padding:3px 6px; font-size:8px; font-weight:bold; letter-spacing:1px; text-align:center;">
        {{ $recepcion->status }}
    </div>
</td>
        </tr>
    </table>

    <hr class="divider">

    {{-- CUSTOMER & VEHICLE --}}
    <div class="section-title">Customer &amp; Vehicle Information</div>
    <table class="info-table">
        <tr>
            <td class="label">Name</td>
            <td class="value">{{ $recepcion->first_name }}</td>
            <td class="label">Make / Model</td>
            <td class="value">{{ $recepcion->brand?->name ?? 'N/A' }} / {{ $recepcion->vehicleModel?->name ?? $recepcion->vehicle_model_id }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td>
            <td class="value">{{ $recepcion->phone ?? 'N/A' }}</td>
            <td class="label">Year</td>
            <td class="value">{{ $recepcion->year ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Address</td>
            <td class="value">{{ $recepcion->address ?? 'N/A' }}</td>
            <td class="label">VIN / Plate</td>
            <td class="value">{{ $recepcion->vin_serial ?? 'N/A' }} / {{ $recepcion->plate ?? 'N/A' }}</td>
        </tr>
       <tr>
    <td class="label">RFC</td>
    <td class="value">{{ $recepcion->rfc ?? 'N/A' }}</td>
    <td class="label">Mileage</td>
    <td class="value">
        @if($recepcion->miles)
            <strong style="font-size:11px;">{{ number_format($recepcion->miles) }}</strong>
            <span style="color:#555;"> km</span>
        @else
            N/A
        @endif
    </td>
</tr>
        <tr>
            <td class="label">Date In</td>
            <td class="value">{{ now()->format('m/d/Y H:i') }}</td>
            <td class="label">Status</td>
            <td class="value"><span class="text-red">{{ $recepcion->status }}</span></td>
        </tr>
    </table>

    {{-- VEHICLE CONDITION --}}
    <div class="mt-8">
        <div class="section-title">Vehicle Condition at Entry</div>
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="width:68%; vertical-align:top; padding:0; border:1px solid #d0d0d0;">
                    <table class="witnesses-table">
                        <tr>
                            @foreach(['engine' => 'ENGINE', 'abs' => 'ABS', 'oil' => 'OIL', 'battery' => 'BATTERY', 'temp' => 'TEMP'] as $key => $label)
                            @php $active = in_array($key, $recepcion->witnesses ?? []); @endphp
                            <td>
                                @if($active)
                                    <div class="witness-circle-active">!</div>
                                    <div class="witness-label-active">{{ $label }}</div>
                                @else
                                    <div class="witness-circle-inactive">-</div>
                                    <div class="witness-label-inactive">{{ $label }}</div>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                    </table>
                </td>

                <td style="width:32%; vertical-align:middle; border:1px solid #d0d0d0; border-left:none; padding:10px 16px;">
                    @php
                        $fuelMap = ['E'=>5,'1/4'=>25,'1/2'=>50,'3/4'=>75,'F'=>100];
                        $fuelPct = $fuelMap[$recepcion->fuel_level] ?? 50;
                        $fuelColor = $fuelPct <= 25 ? '#EE2857' : ($fuelPct <= 50 ? '#f59e0b' : '#16a34a');
                    @endphp
                    <div style="font-size:8px; font-weight:bold; color:#10213E; text-align:center; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">Fuel Level</div>
                    <div style="font-size:20px; font-weight:bold; color:{{ $fuelColor }}; text-align:center; line-height:1; margin-bottom:5px;">{{ $recepcion->fuel_level }}</div>
                    <div style="width:100%; height:12px; background:#e0e0e0; border-radius:6px; border:1px solid #ccc; overflow:hidden; margin-bottom:3px;">
                        <div style="width:{{ $fuelPct }}%; height:12px; background:{{ $fuelColor }}; border-radius:6px;"></div>
                    </div>
                    <table style="width:100%; border:none; border-collapse:collapse;">
                        <tr>
                            <td style="border:none; font-size:8px; color:#EE2857; font-weight:bold; padding:0;">E</td>
                            <td style="border:none; font-size:8px; color:#888; text-align:center; padding:0;">1/4 &nbsp; 1/2 &nbsp; 3/4</td>
                            <td style="border:none; font-size:8px; color:#16a34a; font-weight:bold; text-align:right; padding:0;">F</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- SERVICE --}}
    <div class="mt-8">
        <div class="section-title">Description of Service / Parts</div>
        <table class="service-table">
            <thead>
                <tr>
                    <th style="width:8%;">QTY</th>
                    <th style="width:52%;">Description</th>
                    <th style="width:20%;">Labor</th>
                    <th style="width:20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @for($i = 0; $i < 8; $i++)
                <tr>
                    <td></td>
                    <td>
                        @if($i == 0 && $recepcion->symptoms)
                            <span class="text-red">Symptoms:</span> {{ $recepcion->symptoms }}
                        @endif
                    </td>
                    <td></td>
                    <td></td>
                </tr>
                @endfor
                <tr>
                    <td colspan="2" style="text-align:right; font-weight:bold; color:#10213E;">SUBTOTAL</td>
                    <td></td><td></td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:right; font-weight:bold; color:#10213E;">TAX</td>
                    <td></td><td></td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:right; font-weight:bold; color:#EE2857; font-size:11px;">TOTAL</td>
                    <td></td><td></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- LEGAL --}}
    <div class="footer-legal">
        <strong>Terms &amp; Conditions:</strong>
        {{ $settings->clauses ?? 'You are entitled by law to the return of all parts replaced, except those for which there is a core charge. Storage fees apply for vehicles not picked up within 48 hours of completion. I authorize the above repairs and employees to operate the vehicle for testing.' }}
    </div>

    {{-- SIGNATURES --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-label">Customer Signature</div>
                <div style="font-size:8px; color:#555; margin-top:6px;">
                    Authorized: <span class="checkbox"></span> In person &nbsp;
                    <span class="checkbox"></span> By phone &nbsp;
                    <span class="checkbox"></span> By text
                </div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-label">Service Advisor / Taller</div>
                <div style="font-size:8px; color:#555; margin-top:6px;">Date: ___________________</div>
            </td>
        </tr>
    </table>

    <div style="text-align:center; margin-top:16px; font-size:9px; font-weight:bold; color:#10213E; letter-spacing:2px;">
        THANK YOU FOR YOUR BUSINESS &mdash; GOD BLESS YOU
    </div>

</div>
</body>
</html>