<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Elemento recibido al ingreso (llanta de refacción, gato, cualquier otro). Separado de refacciones y almacén.
 * El código solo existe en elementos migrados desde el inventario fijo anterior; los nuevos no lo llevan.
 */
class RecepcionItem extends Model
{
    protected $table = 'recepcion_items';

    protected $fillable = [
        'recepcion_id', 'code', 'name', 'quantity', 'unit', 'observation', 'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'sort_order' => 'integer',
    ];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class);
    }
}
