<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFiscalProfile extends Model
{
    protected $fillable = ['client_id', 'rfc', 'razon_social'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
