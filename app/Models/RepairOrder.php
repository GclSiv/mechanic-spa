<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairOrder extends Model
{
    /** @use HasFactory<\Database\Factories\RepairOrderFactory> */
    use HasFactory;
    protected $fillable = ['folio', 'client_id', 'vehicle_id', 'mechanic_id', 'status_id', 'problem_description', 'estimated_cost', 'entry_date'];
}
