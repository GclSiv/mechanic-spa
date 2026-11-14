<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_name',
        'tax_type',     // MX (México) por defecto
        'razon_social',
        'iva',          // 16.00 general o 8.00 zona fronteriza
        'rfc',
        'address',
        'phone',
        'email',
        'license_number',
        'clauses',
        'logo_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'warranty_service_enabled',
        'warranty_service_days',
        'warranty_service_km',
        'warranty_parts_enabled',
        'warranty_parts_days',
        'warranty_notes',
    ];

    protected $casts = [
        'iva' => 'decimal:2',
        'warranty_service_enabled' => 'boolean',
        'warranty_parts_enabled'   => 'boolean',
    ];

    /**
     * Devuelve la configuración del taller. Si la tabla está vacía, la crea con
     * valores por defecto para que la aplicación nunca reciba null.
     */
    /**
     * Ruta absoluta del logo para los PDFs. Usa el logo subido por el administrador
     * y, si no hay uno, el logo por defecto del sistema.
     */
    public function logoFilePath(): string
    {
        if ($this->logo_path) {
            $path = storage_path('app/public/' . $this->logo_path);
            if (is_file($path)) {
                return $path;
            }
        }

        return public_path('images/Taller.png');
    }

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'company_name' => 'Mi taller',
            'tax_type'     => 'MX',
            'iva'          => 16.00,
        ]);
    }
}
