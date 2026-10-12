<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantStock extends Model
{
    protected $fillable = [
        'product_variant_id', 'jubelio_location_id', 'on_hand', 'on_order',
        'reserved', 'available', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'jubelio_location_id' => 'integer',
            'on_hand' => 'integer',
            'on_order' => 'integer',
            'reserved' => 'integer',
            'available' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
