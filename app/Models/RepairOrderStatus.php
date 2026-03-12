<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairOrderStatus extends Model
{
    /** @use HasFactory<\Database\Factories\RepairOrderStatusFactory> */
    use HasFactory;
    protected $fillable = ['name', 'slug', 'color_class'];
}
