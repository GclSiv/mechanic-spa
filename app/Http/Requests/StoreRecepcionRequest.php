<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaClasificacionVehiculo;
use App\Http\Requests\Concerns\ValidaRecepcionV2;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\DiagramaDano;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecepcionRequest extends FormRequest
{
    use ValidaClasificacionVehiculo, ValidaRecepcionV2;

    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarRecepcionV2();
    }

    public function rules(): array
    {
        $sinVehiculo = ! $this->filled('vehicle_id');

        return [
            // Cliente
            'client_id'        => 'nullable|exists:clients,id',
            'first_name'       => 'required_without:client_id|string|max:255',
            'last_name'        => 'required_without:client_id|string|max:255',
            'phone'            => 'nullable|string|max:20',
            'address'          => 'nullable|string|max:500',
            'rfc'              => 'nullable|string|max:13',
            'razon_social'     => 'nullable|string|max:255',
            'country'          => 'nullable|in:MX,US',

            // Vehículo
            // El vehículo debe pertenecer al cliente indicado: Vue filtra, pero aquí se valida de verdad.
            'vehicle_id'       => ['nullable', Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('client_id', $this->input('client_id')))],
            // Con vehículo existente estos campos no aplican: pueden llegar nulos o vacíos (el formulario los oculta).
            'brand_id'         => [$sinVehiculo ? 'required' : 'nullable', 'exists:brands,id'],
            'vehicle_model_id' => [$sinVehiculo ? 'required' : 'nullable', 'exists:vehicle_models,id'],
            // Año opcional: maquinaria y vehículos sin año conocido se registran sin inventarlo.
            'year'             => ['nullable', 'numeric', 'min:1900', 'max:2099'],
            'plate'            => 'nullable|string|max:20',
            // Clasificación (Fase B). Los catálogos se validan por existencia; el subtipo, por compatibilidad.
            ...$this->reglasClasificacion(),
            // VIN legado: se deriva del identificador tipo VIN (ver ValidaRecepcionV2). Formato ISO 3779 si llega.
            'vin'              => ['nullable', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
            ...$this->reglasRecepcionV2(),
            'color'            => 'nullable|string|max:30',
            'engine'           => 'nullable|string|max:50',

            // Recepción. Kilometraje (reading_*), medida de llanta e inventario (items) son Recepción V2.
            // miles e inventory ya no se escriben: quedan intactos en registros anteriores.
            'fuel_level'       => 'required|string',
            'symptoms'         => 'nullable|string|max:2000',
            'witnesses'        => 'nullable|array',
            // Catálogo warning_lights: una recepción nueva solo acepta testigos activos.
            'witnesses.*'      => ['string', Rule::exists('warning_lights', 'code')->where('active', true)],

            // Diagrama de daños (coordenadas en porcentaje)
            'damages'          => 'nullable|array|max:30',
            // La zona se valida contra el diagrama de la unidad (ver withValidator): no hay lista fija de automóvil.
            'damages.*.zone'   => 'required|string|max:40',
            'damages.*.note'   => 'nullable|string|max:120',
            'damages.*.severity' => 'nullable|in:leve,medio,grave',

            // Fotos (validación estricta de archivos)
            'photos'           => 'nullable|array|max:10',
            'photos.*'         => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'vin.size'  => 'El VIN debe tener exactamente 17 caracteres.',
            'vin.regex' => 'El VIN solo puede contener letras (sin I, O ni Q) y números.',
            'vehicle_id.exists' => 'El vehículo seleccionado no pertenece al cliente elegido.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('vehicle_id') && ! $this->filled('client_id')) {
                $v->errors()->add('vehicle_id', 'Un vehículo registrado solo puede usarse junto con su cliente.');
            }

            $this->validarSubtipoCompatible($v, $this->input('vehicle_type_id'));
            $this->validarRecepcionV2($v);

            // Cada daño debe pertenecer al diagrama de la unidad. Con vehículo existente se usa su tipo actual.
            $tipo = $this->filled('vehicle_id')
                ? Vehicle::find($this->input('vehicle_id'))?->vehicleType
                : VehicleType::find($this->input('vehicle_type_id'));
            $diagrama = DiagramaDano::paraTipo($tipo);

            foreach ((array) $this->input('damages', []) as $i => $daño) {
                if (! DiagramaDano::zonaValida($diagrama, (string) ($daño['zone'] ?? ''))) {
                    $v->errors()->add("damages.$i.zone", 'La zona no pertenece al diagrama de esta unidad.');
                }
            }
        });
    }
}
