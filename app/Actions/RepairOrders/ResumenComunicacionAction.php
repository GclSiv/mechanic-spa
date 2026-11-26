<?php

namespace App\Actions\RepairOrders;

use App\Models\Quote;
use App\Models\RepairOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Representación derivada y autoritativa de la comunicación con el cliente (solo owner/admin).
 *
 * Solo lee: no persiste estado nuevo, no modifica la lógica financiera (A2) y no es un valor de
 * repair_orders.status. La "autorización" se deriva de las cotizaciones existentes.
 *
 * Cotización "actual": la más reciente (mayor versión) que corresponde al estado derivado.
 * El historial completo sigue en la pestaña Cotizaciones.
 */
final class ResumenComunicacionAction
{
    public const SIN_PENDIENTE = 'sin_pendiente';
    public const ESPERANDO = 'esperando_autorizacion';
    public const AUTORIZADA = 'autorizada';
    public const RECHAZADA = 'rechazada';

    /** @return array<string, mixed> */
    public function execute(RepairOrder $order): array
    {
        $order->loadMissing(['quotes', 'recepcion.client', 'recepcion.vehicle']);

        // Orden determinista: versión desc y, con empate (principal y adicional comparten v1), la más nueva por id.
        $cotizaciones = $order->quotes->sortByDesc(fn (Quote $q) => [$q->version, $q->id])->values();
        $autorizacion = $this->autorizacion($cotizaciones);
        $actual = $this->cotizacionActual($cotizaciones, $autorizacion);

        $cliente = $order->recepcion?->client;
        $vehiculo = $order->recepcion?->vehicle;

        return [
            'tracking_url'       => route('tracking.show', ['token' => $order->tracking_token]),
            'cliente_nombre'     => trim(($cliente?->first_name ?? '') . ' ' . ($cliente?->last_name ?? '')) ?: null,
            'vehiculo'           => trim(implode(' ', array_filter([
                $vehiculo?->displayBrand(),
                $vehiculo?->displayModel(),
                $vehiculo?->year,
            ]))) ?: null,
            'autorizacion'       => $autorizacion,
            // Nota derivada: hay autorización vigente, pero una adicional fue rechazada (rechazo parcial).
            // Solo aplica a 'autorizada'. No cambia la cotización actual ni la lógica financiera.
            'adicional_rechazado' => $autorizacion === self::AUTORIZADA && $this->hayAdicionalRechazado($cotizaciones),
            'cotizacion_actual'  => $actual ? $this->resumen($actual) : null,
            'total_cotizaciones' => $cotizaciones->count(),
        ];
    }

    /** @param Collection<int, Quote> $cotizaciones */
    private function autorizacion(Collection $cotizaciones): string
    {
        if ($cotizaciones->contains(fn (Quote $q) => $q->status === Quote::ENVIADA)) {
            return self::ESPERANDO;
        }
        if ($cotizaciones->contains(fn (Quote $q) => $q->status === Quote::APROBADA)) {
            return self::AUTORIZADA;
        }
        if ($cotizaciones->contains(fn (Quote $q) => $q->status === Quote::RECHAZADA)) {
            return self::RECHAZADA;
        }

        return self::SIN_PENDIENTE;
    }

    /** @param Collection<int, Quote> $cotizaciones */
    private function hayAdicionalRechazado(Collection $cotizaciones): bool
    {
        return $cotizaciones->contains(fn (Quote $q) => $q->kind === Quote::KIND_ADICIONAL && $q->status === Quote::RECHAZADA);
    }

    /** @param Collection<int, Quote> $cotizaciones */
    private function cotizacionActual(Collection $cotizaciones, string $autorizacion): ?Quote
    {
        $estado = match ($autorizacion) {
            self::ESPERANDO => Quote::ENVIADA,
            self::AUTORIZADA => Quote::APROBADA,
            self::RECHAZADA => Quote::RECHAZADA,
            default => Quote::BORRADOR, // solo para el taller: un borrador nunca se menciona al cliente
        };

        return $cotizaciones->first(fn (Quote $q) => $q->status === $estado);
    }

    /** @return array<string, mixed> */
    private function resumen(Quote $q): array
    {
        return [
            'id'               => $q->id,
            'kind'             => $q->kind,
            'folio'            => $q->folio,
            'version'          => $q->version,
            'total'            => round((float) $q->total, 2),
            'status'           => $q->status,
            'sent_at'          => $this->iso($q->sent_at),
            'responded_at'     => $this->iso($q->approved_at ?? $q->rejected_at),
            'aprobada_por'     => $q->approved_by_name,
            'rejection_reason' => $q->rejection_reason,
        ];
    }

    private function iso(mixed $valor): ?string
    {
        return $valor ? Carbon::parse($valor)->toIso8601String() : null;
    }
}
