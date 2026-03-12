<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientStatus extends Model
{
    /** @use HasFactory<\Database\Factories\ClientStatusFactory> */
    protected $fillable = ['name', 'color'];
    use HasFactory;
}
