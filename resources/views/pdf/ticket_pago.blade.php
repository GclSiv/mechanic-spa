<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; margin: 0; padding: 8px; }
  .c { text-align: center; }
  .r { text-align: right; }
  .b { font-weight: bold; }
  hr { border: 0; border-top: 1px dashed #999; margin: 6px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 1px 0; vertical-align: top; }
  .stamp { border: 2px solid #15803d; color: #15803d; font-weight: bold; text-align: center; padding: 4px; margin: 6px 0; font-size: 12px; }
  .pend { border-color: #dc2626; color: #dc2626; }
</style>
</head>
<body>
<div class="c b" style="font-size: 12px;">{{ $settings->company_name ?? 'Mi taller' }}</div>
@if($settings->razon_social)<div class="c">{{ $settings->razon_social }}</div>@endif
@if($settings->rfc)<div class="c">RFC: {{ $settings->rfc }}</div>@endif
@if($settings->address)<div class="c">{{ $settings->address }}</div>@endif
@if($settings->phone)<div class="c">Tel. {{ $settings->phone }}</div>@endif
<hr>
<div class="c b">TICKET DE PAGO</div>
<table>
  <tr><td>Orden:</td><td class="r">{{ $order->folio }}</td></tr>
  <tr><td>Fecha:</td><td class="r">{{ $payment->created_at?->format('d/m/Y H:i') }}</td></tr>
  <tr><td>Cliente:</td><td class="r">{{ $cliente ?: '—' }}</td></tr>
  <tr><td>Vehículo:</td><td class="r">{{ $vehiculo ?: '—' }}</td></tr>
</table>
<hr>
<table>
  <tr><td>Total autorizado:</td><td class="r">${{ number_format($total, 2) }}</td></tr>
  <tr><td>Pago recibido:</td><td class="r b">${{ number_format((float) $payment->amount, 2) }}</td></tr>
  <tr><td>Método:</td><td class="r">{{ $metodo }}</td></tr>
  <tr><td>Total pagado a la fecha:</td><td class="r">${{ number_format($pagadoTotal, 2) }}</td></tr>
  <tr><td>Saldo restante:</td><td class="r b">${{ number_format($saldo, 2) }}</td></tr>
</table>
@if($payment->notes)<div style="margin-top: 4px;">Nota: {{ $payment->notes }}</div>@endif
@if($saldo <= 0)
  <div class="stamp">PAGADO</div>
@else
  <div class="stamp pend">SALDO PENDIENTE</div>
@endif
<hr>
<div class="c">Gracias por su preferencia</div>
</body>
</html>
