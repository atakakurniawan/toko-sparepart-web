<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_variant_id', 'jubelio_item_id', 'sku', 'name',
        'variant_label', 'unit', 'price', 'qty', 'discount', 'line_total',
        'weight_gram', 'jubelio_salesorder_detail_id',
    ];

    protected function casts(): array
    {
        return [
            'jubelio_item_id' => 'integer',
            'price' => 'decimal:2',
            'qty' => 'integer',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'weight_gram' => 'integer',
            'jubelio_salesorder_detail_id' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
