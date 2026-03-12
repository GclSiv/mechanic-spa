<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .table th, .table td { border: 1px solid #000; padding: 5px; }
        .no-border { border: none !important; }
        .header-title { font-size: 18px; font-weight: bold; color: #10213E; }
        .text-red { color: #EE2857; font-weight: bold; }
        .logo { width: 120px; }
        .footer-legal { font-size: 8.5px; text-align: justify; margin-top: 15px; border: 1px solid #000; padding: 10px; }
        .signature-section { margin-top: 30px; }
        .check-box { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 5px; }
    </style>
</head>
<body>
    <table class="table no-border">
        <tr>
            <td class="no-border" style="width: 60%;">
                <img src="{{ public_path('images/logo-taller.png') }}" class="logo"><br>
                <span class="header-title">{{ $settings->company_name }}</span><br>
                {{ $settings->address }}<br>
                Phone: {{ $settings->phone }}<br>
                <strong>License: #{{ $settings->license_number }}</strong>
            </td>
            <td class="no-border" style="text-align: right; vertical-align: top;">
                <span style="font-size: 14px;">DATE: __________________</span><br><br>
                <span class="text-red" style="font-size: 20px;">NO. 0197</span>
            </td>
        </tr>
    </table>

    <table class="table">
        <tr style="background: #f0f0f0;">
            <th colspan="2">CUSTOMER INFORMATION</th>
            <th colspan="2">VEHICLE INFORMATION</th>
        </tr>
        <tr>
            <td style="width: 15%;"><strong>NAME:</strong></td>
            <td style="width: 35%;">{{ $client->first_name }} {{ $client->last_name }}</td>
            <td style="width: 15%;"><strong>MAKE/MODEL:</strong></td>
            <td>{{ $client->brand?->name }} {{ $client->vehicleModel?->name }}</td>
        </tr>
        <tr>
            <td><strong>PHONE:</strong></td>
            <td>{{ $client->phone }}</td>
            <td><strong>VIN/PLATE:</strong></td>
            <td>{{ $client->vin }} / {{ $client->plate }}</td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr style="background: #f0f0f0;">
                <th style="width: 10%;">QTY</th>
                <th style="width: 50%;">DESCRIPTION OF SERVICE / PARTS</th>
                <th style="width: 20%;">LABOR</th>
                <th style="width: 20%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @for($i = 0; $i < 10; $i++)
            <tr>
                <td style="height: 20px;"></td>
                <td>{{ $i == 0 ? ($client->technical_symptoms ?? '') : '' }}</td>
                <td></td>
                <td></td>
            </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer-legal">
        You are entitled by law to the return of all parts replaced, except those for which there is a core charge, unless you agree otherwise by initialing the following: <br>
        ______ I do not desire the return of any of the parts that are replaced during the authorized repairs. <br><br>
        Estimate good for 30 days. Not responsible for damage caused by theft, fire, or acts of nature. I authorize the above repairs, along with any necessary materials. I authorize you and your employees to operate my vehicle for the purpose of testing, inspection, and delivery at my risk. An express mechanic's lien is hereby acknowledged on the above vehicle to secure the amount of the repairs thereto. If I cancel repairs prior to their completion for any reason, a tear-down and reassembly fee will be applied.
    </div>

    <div class="signature-section">
        <table class="table no-border">
            <tr>
                <td class="no-border">
                    <strong>SIGNED:</strong> ____________________________________<br>
                    <strong>DATE:</strong> ________________
                </td>
                <td class="no-border" style="text-align: right;">
                    <strong>Authorized by:</strong><br>
                    <span class="check-box"></span> in person | <span class="check-box"></span> by phone | <span class="check-box"></span> by text
                </td>
            </tr>
        </table>
    </div>

    <div style="text-align: center; margin-top: 20px; font-weight: bold;">
        THANK YOU FOR YOUR BUSINESS! GOD BLESS YOU
    </div>
</body>
</html>