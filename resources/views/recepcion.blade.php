<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 40px; }
        body { font-family: 'Helvetica', sans-serif; color: #333; line-height: 1.4; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-bg { background-color: #10213E; color: white; padding: 8px; font-weight: bold; }
        .border-box { border: 1px solid #ccc; padding: 10px; }
        .text-red { color: #EE2857; font-weight: bold; }
        .signature { border-top: 1px solid #000; width: 200px; text-align: center; margin-top: 40px; }
        .logo { width: 180px; height: auto; }
    </style>
</head>
<body>

    <table class="table" style="border: none;">
        <tr>
            <td style="width: 50%; border: none;">
                <img src="{{ public_path('images/logo-taller.png') }}" class="logo">
            </td>
            <td style="width: 50%; border: none; text-align: right;">
                <span style="font-size: 24px;" class="text-red">RECEPTION ORDER #{{ str_pad($client->id, 5, '0', STR_PAD_LEFT) }}</span><br>
                <strong>{{ $settings->company_name }}</strong><br>
                {{ $settings->address }}<br>
                Phone: {{ $settings->phone }} | Lic: {{ $settings->license_number }}
            </td>
        </tr>
    </table>

    <div class="header-bg">CUSTOMER & VEHICLE INFORMATION</div>
    <table class="table">
        <tr>
            <td class="border-box"><strong>CLIENT:</strong> {{ $client->first_name }} {{ $client->last_name }}</td>
            <td class="border-box"><strong>DATE:</strong> {{ $client->created_at->format('m/d/Y') }}</td>
        </tr>
        <tr>
            <td class="border-box"><strong>VEHICLE:</strong> {{ $client->brand?->name }} {{ $client->vehicleModel?->name }} ({{ $client->year }})</td>
            <td class="border-box"><strong>PLATE:</strong> {{ $client->plate }}</td>
        </tr>
        <tr>
            <td class="border-box"><strong>VIN:</strong> {{ $client->vin }}</td>
            <td class="border-box"><strong>ODOMETER:</strong> {{ number_format($client->miles) }} mi</td>
        </tr>
    </table>

    <div class="header-bg">SERVICE DESCRIPTION & SYMPTOMS</div>
    <div class="border-box" style="min-height: 150px;">
        <strong>Reported Symptoms:</strong><br>
        {{ $client->technical_symptoms ?? 'No specific symptoms reported.' }}
        <br><br>
        <strong>Fuel Level:</strong> {{ $client->fuel_level }}
    </div>

    <div style="font-size: 9px; margin-top: 20px; text-align: justify; color: #555;">
        <strong>LEGAL DISCLAIMER:</strong> {{ $settings->clauses }}
    </div>

    <table class="table" style="border: none; margin-top: 60px;">
        <tr>
            <td style="border: none;"><div class="signature">Technician Signature</div></td>
            <td style="border: none; text-align: right;"><div class="signature" style="margin-left: auto;">Customer Authorization</div></td>
        </tr>
    </table>

</body>
</html>