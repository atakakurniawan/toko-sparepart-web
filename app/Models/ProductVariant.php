<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'jubelio_item_id', 'sku', 'name', 'variant_label',
        'variation_values', 'price', 'barcode', 'thumbnail', 'images',
        'is_bundle', 'is_active', 'jubelio_payload', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'jubelio_item_id' => 'integer',
            'variation_values' => 'array',
            'price' => 'decimal:2',
            'images' => 'array',
            'is_bundle' => 'boolean',
            'is_active' => 'boolean',
            'jubelio_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(VariantStock::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Stok yang boleh dibeli di gudang tertentu:
     * available dari Jubelio dikurangi reservasi lokal yang masih aktif.
     */
    public function sellableQty(int $jubelioLocationId): int
    {
        $available = (int) $this->stocks()
            ->where('jubelio_location_id', $jubelioLocationId)
            ->value('available');

        $reserved = (int) $this->reservations()
            ->active()
            ->where('jubelio_location_id', $jubelioLocationId)
            ->sum('qty');

        return max(0, $available - $reserved);
    }
}
