<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaClasificacionVehiculo;
use App\Models\ClientFiscalProfile;
use App\Models\Recepcion;
use App\Models\VehicleModel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la edición de una recepción. Solo cubre los campos que Editar envía.
 * No exige marca/modelo: un vehículo histórico puede tener marca y modelo solo como texto.
 */
class UpdateRecepcionRequest extends FormRequest
{
    use ValidaClasificacionVehiculo;

    private const RFC_GENERICOS = ['XAXX010101000', 'XEXX010101000'];

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Cliente
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'required|string|max:255',
            'phone'            => 'nullable|string|max:20',
            'address'          => 'nullable|string|max:500',
            'rfc'              => 'nullable|string|max:13',
            'country'          => 'nullable|in:MX,US',

            // Vehículo (marca/modelo normalizados son opcionales: pueden seguir en texto)
            'brand_id'         => 'nullable|exists:brands,id',
            'vehicle_model_id' => 'nullable|exists:vehicle_models,id',
            'year'             => 'nullable|numeric|min:1900|max:2099',
            'plate'            => 'nullable|string|max:20',
            // Formato ISO solo si el VIN cambia: registros anteriores pueden tener VIN que no cumplen la norma.
            'vin'              => 'nullable|string|max:17',
            'engine'           => 'nullable|string|max:50',
            ...$this->reglasClasificacion(),

            // Ingreso
            'fuel_level'       => 'nullable|string|max:20',
            'miles'            => 'nullable|numeric|min:0',
            'symptoms'         => 'nullable|string|max:2000',
            'witnesses'        => 'nullable|array',
            'witnesses.*'      => 'in:engine,abs,oil,battery,temp',
            'inventory'        => 'nullable|array',
            'inventory.*'      => 'in:llanta,gato,estereo,tapetes,herramienta',
        ];
    }

    public function messages(): array
    {
        return [
            'vin.size'  => 'El VIN debe tener exactamente 17 caracteres.',
            'vin.regex' => 'El VIN solo puede contener letras (sin I, O ni Q) y números.',
        ];
    }

    public function recepcion(): ?Recepcion
    {
        return Recepcion::with(['client', 'vehicle'])->find($this->route('recepcion'));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $recepcion = $this->recepcion();
            if (! $recepcion) {
                return; // el controlador responderá 404
            }

            // Marca/modelo: solo se revisan cuando el usuario los envía. Un registro histórico sin FK se guarda tal cual.
            if ($this->filled('brand_id') || $this->filled('vehicle_model_id')) {
                $brandId = $this->input('brand_id') ?: $recepcion->vehicle?->brand_id;
                $modelId = $this->input('vehicle_model_id') ?: $recepcion->vehicle?->model_id;

                if ($modelId && ! $brandId) {
                    $v->errors()->add('brand_id', 'Selecciona la marca del vehículo.');
                } elseif ($brandId && ! $modelId) {
                    $v->errors()->add('vehicle_model_id', 'Selecciona el modelo del vehículo.');
                } elseif ($brandId && $modelId && ! VehicleModel::where('id', $modelId)->where('brand_id', $brandId)->exists()) {
                    $v->errors()->add('vehicle_model_id', 'El modelo no pertenece a la marca seleccionada.');
                }
            }

            // Subtipo: se compara contra el tipo que quedará (el enviado, o el actual si no se envió).
            $tipoEfectivo = $this->has('vehicle_type_id') ? $this->input('vehicle_type_id') : $recepcion->vehicle?->vehicle_type_id;
            $this->validarSubtipoCompatible($v, $tipoEfectivo);

            // VIN nuevo o modificado: 17 caracteres sin I, O ni Q (ISO 3779).
            if ($this->filled('vin') && strtoupper(trim((string) $this->input('vin'))) !== strtoupper((string) $recepcion->vehicle?->vin)) {
                $vin = strtoupper(trim((string) $this->input('vin')));
                if (strlen($vin) !== 17) {
                    $v->errors()->add('vin', 'El VIN debe tener exactamente 17 caracteres.');
                } elseif (! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
                    $v->errors()->add('vin', 'El VIN solo puede contener letras (sin I, O ni Q) y números.');
                }
            }

            // RFC: un RFC identifica a una sola empresa; mismo criterio que Store.
            $rfc = strtoupper(trim((string) $this->input('rfc')));
            if ($rfc !== '' && ! in_array($rfc, self::RFC_GENERICOS, true)) {
                $perfil = ClientFiscalProfile::where('rfc', $rfc)->first();
                if ($perfil && (int) $perfil->client_id !== (int) $recepcion->client_id) {
                    $v->errors()->add('rfc', 'Este RFC ya está registrado a otro cliente.');
                }
            }
        });
    }
}
