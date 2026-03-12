<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class configuration extends Model
{
    /** @use HasFactory<\Database\Factories\ConfigurationFactory> */
    protected $fillable = ['razon_social', 'iva', 'address', 'phone', 'email', 'rfc'];
    use HasFactory;
}
