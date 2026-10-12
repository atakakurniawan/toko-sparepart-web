<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JubelioLocation extends Model
{
    protected $fillable = [
        'jubelio_location_id', 'name', 'location_code', 'address', 'city',
        'province', 'post_code', 'is_warehouse', 'is_active', 'serves_website',
        'jubelio_payload', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'jubelio_location_id' => 'integer',
            'is_warehouse' => 'boolean',
            'is_active' => 'boolean',
            'serves_website' => 'boolean',
            'jubelio_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** Gudang yang melayani order website. */
    public static function website(): ?self
    {
        return static::where('serves_website', true)->first();
    }
}
