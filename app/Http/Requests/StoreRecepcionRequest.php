<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecepcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
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
            'vehicle_id'       => 'nullable|exists:vehicles,id',
            'brand_id'         => 'required_without:vehicle_id|exists:brands,id',
            'vehicle_model_id' => 'required_without:vehicle_id|exists:vehicle_models,id',
            'year'             => 'required_without:vehicle_id|numeric|min:1900|max:2099',
            'plate'            => 'nullable|string|max:20',
            // VIN: 17 caracteres, sin I, O ni Q (norma ISO 3779)
            'vin'              => ['nullable', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
            'color'            => 'nullable|string|max:30',
            'engine'           => 'nullable|string|max:50',

            // Recepción
            'fuel_level'       => 'required|string',
            'miles'            => 'nullable|numeric|min:0',
            'symptoms'         => 'nullable|string|max:2000',
            'witnesses'        => 'nullable|array',
            'witnesses.*'      => 'in:engine,abs,oil,battery,temp',
            'inventory'        => 'nullable|array',
            'inventory.*'      => 'in:llanta,gato,estereo,tapetes,herramienta',

            // Diagrama de daños (coordenadas en porcentaje)
            'damages'          => 'nullable|array|max:30',
            'damages.*.zone'   => 'required|in:capo,parabrisas,luneta,techo,cajuela,defensa_del,defensa_tra,puerta_fi,puerta_ti,puerta_fd,puerta_td,llanta_fi,llanta_fd,llanta_ti,llanta_td,espejo_i,espejo_d',
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
        ];
    }
}
