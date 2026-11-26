<?php

namespace App\Http\Requests\Concerns;

use App\Support\IdentificadorVehiculo;
use Illuminate\Validation\Validator;

/**
 * Recepción V2: identificador del vehículo, lectura, medida de llanta y elementos recibidos.
 * Lo comparten Store y Update. Normaliza antes de validar y valida el valor según su tipo.
 * El VIN legado (vin) se conserva: si llega sin tipo, se trata como identificador VIN.
 */
trait ValidaRecepcionV2
{
    protected function reglasRecepcionV2(): array
    {
        return [
            'identifier_type'     => ['nullable', 'in:' . implode(',', IdentificadorVehiculo::TIPOS)],
            'identifier_value'    => ['nullable', 'string', 'max:100'],
            'reading_type'        => ['nullable', 'in:km,mi,hr'],
            'reading_value'       => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tire_size'           => ['nullable', 'string', 'max:60'],
            'items'               => ['nullable', 'array', 'max:50'],
            'items.*.name'        => ['required', 'string', 'max:120'],
            'items.*.quantity'    => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.unit'        => ['nullable', 'string', 'max:30'],
            'items.*.observation' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Antes de validar: vacíos a null, VIN legado a identificador, valor normalizado.
     * vin solo se escribe cuando el tipo es VIN; cambiar a otro tipo no borra el VIN guardado.
     */
    protected function normalizarRecepcionV2(): void
    {
        $datos = [];

        if ($this->has('identifier_type') || $this->has('identifier_value') || $this->filled('vin')) {
            $tipo = $this->input('identifier_type');
            $valor = $this->input('identifier_value');

            if (($tipo === null || $tipo === '') && $this->filled('vin')) {
                $tipo = 'vin';
                $valor = $this->input('vin');
            }

            $tipo = ($tipo === '' || $tipo === null) ? null : (string) $tipo;
            $valor = $tipo ? IdentificadorVehiculo::normalizarValor($tipo, $valor === null ? null : (string) $valor) : null;

            $datos['identifier_type'] = $tipo;
            $datos['identifier_value'] = $valor;

            if ($tipo === 'vin') {
                $datos['vin'] = $valor;
            }
        }

        if ($this->has('reading_type') || $this->has('reading_value')) {
            $unidad = $this->input('reading_type') ?: null;
            $lectura = $this->input('reading_value');
            $lectura = ($lectura === '' || $lectura === null) ? null : $lectura;

            // Una unidad sin lectura no se guarda; una lectura sin unidad queda para la validación.
            $datos['reading_type'] = $lectura === null ? null : $unidad;
            $datos['reading_value'] = $lectura;
        }

        if ($this->has('tire_size')) {
            $medida = trim((string) $this->input('tire_size'));
            $datos['tire_size'] = $medida === '' ? null : $medida;
        }

        if ($datos) {
            $this->merge($datos);
        }
    }

    /**
     * @param array{tipo: ?string, valor: ?string}|null $actual Identificador ya guardado (Editar).
     *        Si no cambia, no se revalida: un VIN histórico que no cumple la norma se acepta como antes.
     */
    protected function validarRecepcionV2(Validator $validador, ?array $actual = null): void
    {
        $tipo = $this->input('identifier_type');
        $valor = (string) $this->input('identifier_value');
        $sinCambio = $actual !== null
            && ($actual['tipo'] ?? null) === $tipo
            && strtoupper((string) ($actual['valor'] ?? '')) === strtoupper($valor);

        if ($tipo && $tipo !== 'none' && ! $sinCambio) {
            $error = IdentificadorVehiculo::errorValor($tipo, $valor);
            if ($error) {
                // Un VIN reporta su error en "vin", como siempre; los demás tipos, en identifier_value.
                $validador->errors()->add($tipo === 'vin' ? 'vin' : 'identifier_value', $error);
            }
        }

        if ($this->filled('reading_value') && ! $this->filled('reading_type')) {
            $validador->errors()->add('reading_type', 'Elige la unidad de la lectura (km, mi u hr).');
        }
    }
}
