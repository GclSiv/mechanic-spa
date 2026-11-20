<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Seguimiento de cotizaciones enviadas a clientes: aceptadas, rechazadas y pendientes,
 * con botón de WhatsApp y exportación a CSV (compatible con Excel).
 */
class QuoteFollowUpController extends Controller
{
    public function index(Request $request)
    {
        $estado = $request->input('estado', 'todas');
        $buscar = trim((string) $request->input('buscar', ''));

        $base = Quote::query()->whereIn('status', [Quote::ENVIADA, Quote::APROBADA, Quote::RECHAZADA]);

        $enviadas   = (clone $base)->count();
        $aceptadas  = (clone $base)->where('status', Quote::APROBADA)->count();
        $rechazadas = (clone $base)->where('status', Quote::RECHAZADA)->count();
        $pendientes = (clone $base)->where('status', Quote::ENVIADA)->count();

        $resumen = [
            'enviadas'   => $enviadas,
            'pendientes' => $pendientes,
            'aceptadas'  => $aceptadas,
            'rechazadas' => $rechazadas,
            'tasa'       => $enviadas > 0 ? (int) round($aceptadas / $enviadas * 100) : 0,
        ];

        $taller = Setting::current()->company_name ?: 'nuestro taller';

        return Inertia::render('Cotizaciones/Index', [
            'cotizaciones' => $this->filtrar($estado, $buscar)->get()
                ->map(fn (Quote $q) => $this->fila($q, $taller))->values(),
            'resumen'      => $resumen,
            'filtros'      => ['estado' => $estado, 'buscar' => $buscar],
        ]);
    }

    /**
     * Exporta la lista filtrada a CSV. Se abre directamente en Excel.
     */
    public function exportar(Request $request): StreamedResponse
    {
        $estado = $request->input('estado', 'todas');
        $buscar = trim((string) $request->input('buscar', ''));
        $taller = Setting::current()->company_name ?: 'nuestro taller';
        $filas  = $this->filtrar($estado, $buscar)->get()->map(fn (Quote $q) => $this->fila($q, $taller));

        $nombre = 'cotizaciones-seguimiento-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete acentos

            fputcsv($out, ['Folio', 'Tipo', 'Cliente', 'Teléfono', 'Vehículo', 'Placa', 'Monto', 'Enviada', 'Estado', 'Vence', 'Respuesta por', 'Fecha de respuesta', 'Método', 'Motivo de rechazo', 'Orden']);

            foreach ($filas as $f) {
                fputcsv($out, [
                    $f['folio'],
                    $f['kind'] === 'adicional' ? 'Adicional' : 'Principal',
                    $f['cliente'],
                    $f['telefono'],
                    $f['vehiculo'],
                    $f['placa'],
                    number_format($f['total'], 2, '.', ''),
                    $f['enviada'],
                    ['enviada' => 'Pendiente', 'aprobada' => 'Aceptada', 'rechazada' => 'Rechazada'][$f['status']] ?? $f['status'],
                    $f['vence'],
                    $f['respuesta_por'],
                    $f['respondida'],
                    $f['respuesta_metodo'],
                    $f['motivo'],
                    $f['order_id'],
                ]);
            }

            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtrar(string $estado, string $buscar): Builder
    {
        $query = Quote::query()
            ->whereIn('status', [Quote::ENVIADA, Quote::APROBADA, Quote::RECHAZADA])
            ->with([
                'repairOrder.recepcion.client',
                'repairOrder.recepcion.vehicle.brand',
                'repairOrder.recepcion.vehicle.vehicleModel',
            ])
            ->orderByDesc('sent_at')
            ->orderByDesc('id');

        match ($estado) {
            'pendientes' => $query->where('status', Quote::ENVIADA),
            'aceptadas'  => $query->where('status', Quote::APROBADA),
            'rechazadas' => $query->where('status', Quote::RECHAZADA),
            default      => null,
        };

        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->where('folio', 'like', "%{$buscar}%")
                  ->orWhereHas('repairOrder.recepcion.client', function ($c) use ($buscar) {
                      $c->where('first_name', 'like', "%{$buscar}%")
                        ->orWhere('last_name', 'like', "%{$buscar}%")
                        ->orWhere('phone', 'like', "%{$buscar}%");
                  });
            });
        }

        return $query;
    }

    private function fila(Quote $q, string $taller): array
    {
        $order   = $q->repairOrder;
        $client  = $order?->recepcion?->client;
        $vehicle = $order?->recepcion?->vehicle;
        $enviada = $q->sent_at ?? $q->created_at;
        $nombre  = $client->first_name ?? 'cliente';
        $seguimiento = $order?->tracking_token ? url('/seguimiento/' . $order->tracking_token) : null;

        $mensaje = match ($q->status) {
            Quote::RECHAZADA => "Hola {$nombre}, le escribimos de {$taller}. Vimos que no se aceptó la cotización {$q->folio}. ¿Le podemos ayudar a ajustarla?",
            Quote::APROBADA  => "Hola {$nombre}, le escribimos de {$taller}. Gracias por aceptar la cotización {$q->folio}.",
            default          => "Hola {$nombre}, le escribimos de {$taller}. Le recordamos su cotización {$q->folio}" . ($seguimiento ? ", puede revisarla aquí: {$seguimiento}" : '.'),
        };

        return [
            'id'               => $q->id,
            'folio'            => $q->folio,
            'kind'             => $q->kind,
            'status'           => $q->status,
            'total'            => round((float) $q->total, 2),
            'cliente'          => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) ?: '—',
            'telefono'         => $client->phone ?? null,
            'vehiculo'         => trim(($vehicle?->brand?->name ?? '') . ' ' . ($vehicle?->vehicleModel?->name ?? '')) ?: '—',
            'placa'            => $vehicle->plate ?? null,
            'enviada'          => $enviada?->format('d/m/Y'),
            'dias'             => $q->status === Quote::ENVIADA && $enviada ? (int) $enviada->diffInDays(now()) : null,
            'vence'            => $q->valid_until?->format('d/m/Y'),
            'vencida'          => $q->status === Quote::ENVIADA && $q->valid_until && $q->valid_until->isPast(),
            'respuesta_por'    => $q->client_accepted_name ?? $q->approved_by_name,
            'respuesta_metodo' => $q->approval_method,
            'respondida'       => ($q->client_accepted_at ?? $q->approved_at ?? $q->rejected_at)?->format('d/m/Y H:i'),
            'motivo'           => $q->rejection_reason,
            'order_id'         => $order?->id,
            'whatsapp'         => $this->whatsappLink($client->phone ?? null, $mensaje),
        ];
    }

    /** Teléfono de México: 52 + 10 dígitos. */
    private function whatsappLink(?string $telefono, string $mensaje): ?string
    {
        $d = preg_replace('/\D/', '', (string) $telefono);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 10) {
            $d = '52' . $d;
        } elseif (strlen($d) === 13 && str_starts_with($d, '521')) {
            $d = '52' . substr($d, 3);
        }

        return 'https://wa.me/' . $d . '?text=' . rawurlencode($mensaje);
    }
}
