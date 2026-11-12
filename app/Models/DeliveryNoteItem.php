<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan 09 §F11 — DeliveryNoteItem model.
 *
 * Línea de albarán. Snapshot del item de catálogo (name, description,
 * unit) más la cantidad entregada (siempre ≥ 0, validada en la
 * request). Los campos comerciales (`price`, `total`, etc.) son
 * opcionales — el albarán por defecto no muestra importes, pero los
 * conservamos para la conversión a Invoice.
 */
class DeliveryNoteItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class, 'delivery_note_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
