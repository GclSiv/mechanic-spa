<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory; // 2. Activar el chip dentro de la clase
    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'address', 'rfc', 'country', 'client_status_id',
    ];

    protected static function booted(): void
    {
        // Asigna el número CLI-0001 justo después de crear el cliente (usa su id)
        static::created(function (Client $client) {
            if (! $client->client_number) {
                $client->forceFill([
                    'client_number' => 'CLI-' . str_pad((string) $client->id, 4, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function fiscalProfiles()
    {
        return $this->hasMany(ClientFiscalProfile::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function repairOrders()
    {
        return $this->hasMany(RepairOrder::class);
    }
}