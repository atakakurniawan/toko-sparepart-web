<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'jubelio_item_group_id', 'name', 'slug', 'description', 'brand_name',
        'jubelio_category_id', 'sell_unit', 'package_weight_gram',
        'package_length_cm', 'package_width_cm', 'package_height_cm',
        'thumbnail', 'images', 'variation_labels', 'is_active',
        'jubelio_last_modified', 'jubelio_payload', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'jubelio_item_group_id' => 'integer',
            'package_weight_gram' => 'integer',
            'images' => 'array',
            'variation_labels' => 'array',
            'is_active' => 'boolean',
            'jubelio_last_modified' => 'datetime',
            'jubelio_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
