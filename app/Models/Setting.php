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
        'tax_type',     // 🇺🇸/🇲🇽 Nuevo campo de región
        'razon_social', // Nuevo
        'iva',          // Nuevo (tasa 16.00 o 8.75)
        'rfc',          // Nuevo (o EIN para US)
        'address',
        'phone',
        'email',
        'license_number',
        'clauses',
        'logo_path',
        'primary_color',
        'secondary_color',
        'accent_color',
    ];

    protected $casts = [
        'iva' => 'decimal:2', // Asegura cálculos precisos para contabilidad
    ];
}