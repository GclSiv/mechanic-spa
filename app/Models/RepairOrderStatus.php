<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairOrderStatus extends Model
{
    /** @use HasFactory<\Database\Factories\RepairOrderStatusFactory> */
    use HasFactory;
    protected $fillable = ['name', 'slug', 'color_class'];

    /** Estados que el mecánico puede asignar en sus órdenes asignadas. */
    public const OPERATIVOS_MECANICO = ['diagnostico', 'espera-piezas', 'en-reparacion', 'reparado'];

    /** Estados de cierre: solo admin/owner los asigna, y el mecánico no puede sacar una orden de ellos. */
    public const CERRADOS = ['entregado', 'rechazado'];
}
