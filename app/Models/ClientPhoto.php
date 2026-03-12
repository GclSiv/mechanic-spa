<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ClientPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'path', 'label'];

    // Esto nos permite enviar la URL completa de la foto al Frontend (Vue)
    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return $this->path ? Storage::url($this->path) : null;
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}