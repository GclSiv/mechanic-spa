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
            // Client
            'client_id'        => 'nullable|exists:clients,id',
            'first_name'       => 'required_without:client_id|string|max:255',
            'last_name'        => 'required_without:client_id|string|max:255',
            'phone'            => 'nullable|string|max:20',
            'address'          => 'nullable|string|max:500',
            'rfc'              => 'nullable|string|max:13',
            'country'          => 'nullable|in:MX,US',

            // Vehicle
            'vehicle_id'       => 'nullable|exists:vehicles,id',
            'brand_id'         => 'required_without:vehicle_id|exists:brands,id',
            'vehicle_model_id' => 'required_without:vehicle_id|exists:vehicle_models,id',
            'year'             => 'required_without:vehicle_id|numeric|min:1900|max:2099',
            'plate'            => 'nullable|string|max:20',
            'vin'              => 'nullable|string|max:17',
            'engine'           => 'nullable|string|max:50',

            // Reception
            'fuel_level'       => 'required|string',
            'miles'            => 'nullable|numeric|min:0',
            'symptoms'         => 'nullable|string|max:2000',
            'witnesses'        => 'nullable|array',
            'inventory'        => 'nullable|array',

            // Photos — OWASP strict file validation
            'photos'           => 'nullable|array|max:10',
            'photos.*'         => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}
