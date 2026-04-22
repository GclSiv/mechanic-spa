<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
/* ============================================================
RESET & BASE
============================================================ */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
font-family: 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif;
font-size: 9px;
color: #1a1a2e;
background: #fff;
}
/* ============================================================
PAGE LAYOUT
============================================================ */
.page {
padding: 20px 24px 24px;
position: relative;
}
.page-break {
page-break-before: always;
}
/* ============================================================
HEADER
============================================================ */
.header-wrap { width: 100%; border-collapse: collapse; margin-bottom: 0; }
.header-logo-cell { width: 68px; vertical-align: middle; }
.header-logo-cell img { width: 62px; height: auto; }
.header-info-cell { vertical-align: middle; padding-left: 10px; }
.company-name { font-size: 17px; font-weight: 900; color: #10213E; letter-spacing: -0.3px; line-height: 1.1; }
.company-sub { font-size: 7.5px; color: #555; line-height: 1.55; margin-top: 2px; }
.header-folio-cell { text-align: right; vertical-align: top; width: 155px; }
.folio-label { font-size: 7px; color: #888; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; }
.folio-number { font-size: 28px; font-weight: 900; color: #EE2857; line-height: 1; letter-spacing: -1px; }
.folio-date { font-size: 7.5px; color: #666; margin-top: 2px; }
.divider { border: none; border-top: 2.5px solid #10213E; margin: 8px 0 10px; }
/* ============================================================
SECTION TITLES
============================================================ */
.section-title {
background-color: #10213E; color: #ffffff; font-size: 8px; font-weight: 800;
text-transform: uppercase; letter-spacing: 1px; padding: 5px 10px; margin-bottom: 0;
}
/* ============================================================
INFO TABLES
============================================================ */
.info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.info-table td { padding: 4.5px 8px; border: 1px solid #d5d8de; font-size: 8.5px; vertical-align: middle; }
.info-table .lbl { font-weight: 700; color: #10213E; width: 14%; background-color: #f0f3f8; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.3px; }
.info-table .val { color: #1a1a2e; width: 36%; }
.info-table .val-strong { font-weight: 700; color: #1a1a2e; }
/* ============================================================
VEHICLE CONDITION
============================================================ */
.condition-outer { width: 100%; border-collapse: collapse; border: 1px solid #d5d8de; margin-bottom: 10px; }
.witnesses-cell { width: 65%; border-right: 1px solid #d5d8de; padding: 10px 8px 8px; vertical-align: top; text-align: center; }
.witnesses-subtitle { font-size: 7px; font-weight: 800; color: #10213E; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 10px; }
.icon-box { display: inline-block; width: 18%; text-align: center; vertical-align: top; margin: 0 1%; }
.icon-img { width: 28px; height: 28px; display: block; margin: 0 auto 3px; }
.icon-label-active { font-size: 6.5px; font-weight: 800; color: #EE2857; text-transform: uppercase; }
.icon-label-inactive { font-size: 6.5px; font-weight: 700; color: #c0c4cc; text-transform: uppercase; }
.icon-inactive { opacity: 0.18; }
.fuel-cell { width: 35%; padding: 12px 14px; vertical-align: middle; text-align: center; }
.fuel-subtitle { font-size: 7px; font-weight: 800; color: #10213E; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
.fuel-value { font-size: 26px; font-weight: 900; color: #10213E; line-height: 1; margin-bottom: 8px; }
.fuel-bar-bg { width: 100%; height: 9px; background: #e8eaed; border-radius: 5px; overflow: hidden; }
.fuel-bar-fill { height: 9px; background: linear-gradient(90deg, #16a34a, #22c55e); border-radius: 5px; }
/* ============================================================
SERVICE TABLE
============================================================ */
.service-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.service-table thead tr th { background-color: #f0f3f8; border: 1px solid #d5d8de; padding: 6px 8px; font-size: 8px; font-weight: 800; color: #10213E; text-transform: uppercase; letter-spacing: 0.4px; text-align: left; }
.service-table thead tr th.center { text-align: center; }
.service-table tbody tr td { border: 1px solid #d5d8de; padding: 5px 8px; height: 18px; font-size: 8.5px; color: #1a1a2e; vertical-align: middle; }
.service-table tbody tr td.center { text-align: center; color: #888; }
.symptom-label { color: #EE2857; font-weight: 800; }
.service-table tbody tr:nth-child(even) td { background-color: #fafbfd; }
/* ============================================================
LEGAL TERMS
============================================================ */
.legal-box { border: 1px solid #d5d8de; background: #fafbfd; padding: 7px 10px; font-size: 6.8px; color: #555; text-align: justify; line-height: 1.5; margin-top: 10px; }
.legal-box strong { color: #10213E; font-weight: 800; }
/* ============================================================
SIGNATURES
============================================================ */
.sig-table { width: 100%; border-collapse: collapse; margin-top: 18px; }
.sig-table td { text-align: center; padding: 0 20px; border: none; }
.sig-line { border-top: 1px solid #333; width: 75%; margin: 0 auto 5px; }
.sig-label { font-size: 7.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #10213E; }
/* ============================================================
THANK YOU FOOTER & PÁGINA 2
============================================================ */
.thank-you { text-align: center; margin-top: 12px; font-size: 8.5px; font-weight: 900; color: #10213E; text-transform: uppercase; letter-spacing: 1px; }
.photo-header { width: 100%; border-collapse: collapse; border-bottom: 2.5px solid #10213E; padding-bottom: 8px; margin-bottom: 12px; }
.photo-title { font-size: 11px; font-weight: 900; color: #10213E; }
.photo-client-sub { font-size: 7.5px; color: #555; margin-top: 2px; }
.photo-folio { text-align: right; font-size: 15px; font-weight: 900; color: #EE2857; }
.photo-vehicle-sub { text-align: right; font-size: 7.5px; color: #777; margin-top: 2px; }
.photo-container { border: 1px solid #d5d8de; border-radius: 8px; padding: 10px; }
.photo-img { width: 255px; height: auto; border-radius: 6px; display: block; }
.page-footer-bar { position: absolute; bottom: 10px; left: 24px; right: 24px; border-top: 1px solid #d5d8de; padding-top: 6px; font-size: 6.5px; color: #999; }
</style>
</head>
<body>

{{-- ESCUDO PROTECTOR CONTRA VARIABLES INDEFINIDAS --}}
@php
    $settings = $settings ?? \App\Models\Setting::first();
@endphp

<div class="page">
{{-- ── HEADER ── --}}
<table class="header-wrap">
<tr>
<td class="header-logo-cell">
<img src="{{ public_path('images/Taller.png') }}" alt="Logo">
</td>
<td class="header-info-cell">
<div class="company-name">{{ $settings->company_name ?? 'JK AUTOMOTIVE' }}</div>
<div class="company-sub">
{{ $settings->address ?? '' }}<br>
Phone: {{ $settings->phone ?? '' }} | Lic: #{{ $settings->license_number ?? '' }}
</div>
</td>
<td class="header-folio-cell">
<div class="folio-label">Reception Order</div>
<div class="folio-number">#{{ str_pad($recepcion->id, 4, '0', STR_PAD_LEFT) }}</div>
<div class="folio-date">{{ $recepcion->created_at?->format('m/d/Y | H:i') }} hrs</div>
</td>
</tr>
</table>
<hr class="divider">

{{-- ── CUSTOMER & VEHICLE INFORMATION ── --}}
<div class="section-title">Customer &amp; Vehicle Information</div>
<table class="info-table">
<tr>
<td class="lbl">Name</td>
<td class="val">{{ $recepcion->client?->first_name }} {{ $recepcion->client?->last_name }}</td>
<td class="lbl">Make / Model</td>
<td class="val val-strong">
{{ $recepcion->vehicle?->brand?->name ?? '—' }} /
{{ $recepcion->vehicle?->vehicleModel?->name ?? '—' }}
</td>
</tr>
<tr>
<td class="lbl">Phone</td>
<td class="val">{{ $recepcion->client?->phone ?? '—' }}</td>
<td class="lbl">Year</td>
<td class="val val-strong">{{ $recepcion->vehicle?->year ?? '—' }}</td>
</tr>
<tr>
<td class="lbl">Address</td>
<td class="val">{{ $recepcion->client?->address ?? '—' }}</td>
<td class="lbl">VIN / Plate</td>
<td class="val">
{{ $recepcion->vehicle?->vin ?? 'N/A' }} /
<strong>{{ $recepcion->vehicle?->plate ?? '—' }}</strong>
</td>
</tr>
<tr>
<td class="lbl">RFC</td>
<td class="val">{{ $recepcion->client?->rfc ?? '—' }}</td>
<td class="lbl">Mileage</td>
<td class="val val-strong">
{{ $recepcion->miles ? number_format((float)$recepcion->miles) . ' km' : '—' }}
</td>
</tr>
</table>

{{-- ── VEHICLE CONDITION AT ENTRY ── --}}
<div class="section-title">Vehicle Condition at Entry</div>
<table class="condition-outer">
<tr>
<td class="witnesses-cell">
<div class="witnesses-subtitle">Dashboard Warning Lights</div>
@php
$witnessKeys = is_array($recepcion->witnesses) ? $recepcion->witnesses : json_decode($recepcion->witnesses ?? '[]', true);
$icons = ['engine' => 'MOTOR', 'abs' => 'ABS', 'oil' => 'OIL', 'battery' => 'BATTERY', 'temp' => 'TEMP'];
@endphp
@foreach($icons as $key => $label)
@php $active = in_array($key, $witnessKeys ?? []); @endphp
<div class="icon-box">
<img src="{{ public_path('images/icons/' . $key . '.svg') }}" class="icon-img {{ !$active ? 'icon-inactive' : '' }}" alt="{{ $label }}">
<div class="{{ $active ? 'icon-label-active' : 'icon-label-inactive' }}">{{ $label }}</div>
</div>
@endforeach
</td>
<td class="fuel-cell">
@php
$fuelMap = ['E' => 5, '1/4' => 25, '1/2' => 50, '3/4' => 75, 'F' => 100];
$fuelPct = $fuelMap[$recepcion->fuel_level] ?? 50;
@endphp
<div class="fuel-subtitle">Fuel Level</div>
<div class="fuel-value">{{ $recepcion->fuel_level ?? '—' }}</div>
<div class="fuel-bar-bg">
<div class="fuel-bar-fill" style="width: {{ $fuelPct }}%;"></div>
</div>
</td>
</tr>
</table>

{{-- ── DESCRIPTION OF SERVICE / PARTS ── --}}
<div class="section-title">Description of Service / Parts</div>
<table class="service-table">
<thead>
<tr>
<th style="width: 65%;">Description</th>
<th class="center" style="width: 17%;">Labor</th>
<th class="center" style="width: 18%;">Amount</th>
</tr>
</thead>
<tbody>
<tr>
<td><span class="symptom-label">Symptoms:</span> {{ $recepcion->symptoms ?? 'No symptoms reported.' }}</td>
<td class="center">—</td>
<td class="center">—</td>
</tr>
@for ($i = 0; $i < 10; $i++)
<tr><td>&nbsp;</td><td class="center"></td><td class="center"></td></tr>
@endfor
</tbody>
</table>

{{-- ── TÉRMINOS LEGALES ── --}}
<div class="legal-box">
<strong>Terms:</strong> {{ $settings->clauses ?? 'You are entitled by law to the return of all parts replaced, except those for which there is a core charge. STORAGE FEES: Vehicles not picked up within 48 hours of completion are subject to a $50/day storage fee. I authorize the above repairs and employees to operate the vehicle for testing.' }}
</div>

{{-- ── FIRMAS ── --}}
<table class="sig-table">
<tr>
<td style="width: 50%;"><div class="sig-line"></div><div class="sig-label">Customer Signature</div></td>
<td style="width: 50%;"><div class="sig-line"></div><div class="sig-label">Service Advisor</div></td>
</tr>
</table>
<div class="thank-you">Thank You — God Bless You</div>
</div>

{{-- ================================================================
PÁGINA 2 — EVIDENCIA FOTOGRÁFICA
================================================================ --}}
@php
$photos = is_array($recepcion->photos) ? $recepcion->photos : json_decode($recepcion->photos ?? '[]', true);
@endphp
@if($photos && count($photos) > 0)
<div class="page page-break">
<table class="photo-header">
<tr>
<td>
<div class="photo-title">Photographic Evidence</div>
<div class="photo-client-sub">Client: {{ strtoupper($recepcion->client?->first_name . ' ' . $recepcion->client?->last_name) }}</div>
</td>
<td style="text-align: right; vertical-align: bottom;">
<div class="photo-folio">Order #{{ str_pad($recepcion->id, 4, '0', STR_PAD_LEFT) }}</div>
<div class="photo-vehicle-sub">{{ is_string($recepcion->vehicle?->brand) ? $recepcion->vehicle->brand : ($recepcion->vehicle?->brand?->name ?? '—') }} / {{ is_string($recepcion->vehicle?->model) ? $recepcion->vehicle->model : ($recepcion->vehicle?->vehicleModel?->name ?? '—') }} {{ $recepcion->vehicle?->year }}</div>
</td>
</tr>
</table>
<div class="photo-container">
<table style="width: 100%; border-collapse: collapse;">
@foreach(array_chunk($photos, 2) as $row)
<tr>
@foreach($row as $photo)
<td style="text-align: center; padding: 8px; border: none;">
<img src="{{ public_path('storage/' . str_replace('\\', '/', $photo)) }}" class="photo-img" alt="Vehicle photo">
</td>
@endforeach
@if(count($row) === 1) <td style="width: 50%;"></td> @endif
</tr>
@endforeach
</table>
</div>
<div class="page-footer-bar">
<table style="width: 100%; border-collapse: collapse;">
<tr>
<td><strong>{{ $settings->company_name ?? 'JK AUTOMOTIVE' }}</strong> | {{ $settings->address ?? '' }}</td>
<td style="text-align: right;">Official Attachment to Order #{{ str_pad($recepcion->id, 4, '0', STR_PAD_LEFT) }} | Page 2 of 2</td>
</tr>
</table>
</div>
</div>
@endif
</body>
</html>