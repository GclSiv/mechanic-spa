<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de Recepción #{{ $recepcion->id }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #1a365d; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #1a365d; }
        .logo span { color: #e53e3e; }
        .folio { font-size: 18px; color: #555; }
        
        .section-title { background-color: #f3f4f6; padding: 5px; font-weight: bold; border-left: 4px solid #1a365d; margin-top: 20px; }
        
        table { w-full; border-collapse: collapse; margin-top: 10px; width: 100%; }
        td, th { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
        th { font-size: 12px; color: #666; text-transform: uppercase; }
        
        .footer { position: fixed; bottom: 30px; left: 0; right: 0; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">JK<span>Automotive</span></div>
        <div>Mecánica Especializada y Diagnóstico Avanzado</div>
        <div class="folio">Orden de Recepción #{{ str_pad($recepcion->id, 5, '0', STR_PAD_LEFT) }}</div>
        <div>Fecha: {{ $recepcion->created_at->format('d/m/Y h:i A') }}</div>
    </div>

    <div class="section-title">DATOS DEL CLIENTE</div>
    <table>
        <tr>
            <th>Nombre:</th> <td>{{ $recepcion->client->first_name }} {{ $recepcion->client->last_name }}</td>
            <th>Teléfono:</th> <td>{{ $recepcion->client->phone ?? 'N/A' }}</td>
        </tr>
    </table>

    <div class="section-title">DATOS DEL VEHÍCULO</div>
    <table>
        <tr>
            <th>Marca:</th> <td>{{ $recepcion->vehicle->brand->name ?? 'S/M' }}</td>
            <th>Modelo:</th> <td>{{ $recepcion->vehicle->vehicleModel->name ?? 'S/M' }}</td>
        </tr>
        <tr>
            <th>Año:</th> <td>{{ $recepcion->vehicle->year }}</td>
            <th>Placas:</th> <td>{{ $recepcion->vehicle->plate }}</td>
        </tr>
        <tr>
            <th>VIN:</th> <td colspan="3">{{ $recepcion->vehicle->vin }}</td>
        </tr>
    </table>

    <div class="section-title">ESTADO DE RECEPCIÓN</div>
    <table>
        <tr>
            <th>Nivel de Combustible:</th> <td>{{ $recepcion->fuel_level }}</td>
            <th>Kilometraje:</th> <td>{{ $recepcion->miles ?? 'No reportado' }}</td>
        </tr>
        <tr>
            <th>Síntomas Reportados:</th>
            <td colspan="3">{{ $recepcion->symptoms ?? 'Revisión general' }}</td>
        </tr>
    </table>

    <div class="footer">
        Firma del Cliente ___________________________<br><br>
        Al firmar este documento, el cliente acepta los términos y condiciones del taller. <br>
        {{ $settings->address ?? 'Taller JK Automotive' }}
    </div>

</body>
</html>