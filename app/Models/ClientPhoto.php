<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'path', 'label'];

    // La ruta física nunca sale al frontend: la foto se entrega solo por el endpoint autorizado.
    protected $hidden = ['path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        if (! $this->client_id || ! $this->id) {
            return null;
        }

        return route('clients.photos.show', ['client' => $this->client_id, 'photo' => $this->id]);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
