<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PAID = 'paid';
    public const STATUS_SENT_TO_JUBELIO = 'sent_to_jubelio';
    public const STATUS_PUSH_FAILED = 'push_failed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NEEDS_REFUND = 'needs_refund';

    protected $fillable = [
        'order_no', 'customer_id', 'status',
        'customer_name', 'customer_email', 'customer_phone',
        'shipping_name', 'shipping_phone', 'shipping_address',
        'shipping_subdistrict', 'shipping_district', 'shipping_city',
        'shipping_province', 'shipping_post_code', 'biteship_area_id',
        'jubelio_province_id', 'jubelio_city_id', 'jubelio_district_id',
        'jubelio_subdistrict_id',
        'courier_code', 'courier_service', 'shipper_label', 'total_weight_gram',
        'sub_total', 'discount_total', 'shipping_cost', 'insurance_cost', 'grand_total',
        'note', 'jubelio_location_id', 'jubelio_salesorder_id', 'jubelio_status',
        'tracking_no', 'push_attempts', 'last_push_error',
        'payment_expires_at', 'paid_at', 'sent_to_jubelio_at', 'shipped_at',
        'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_weight_gram' => 'integer',
            'sub_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'insurance_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'jubelio_location_id' => 'integer',
            'jubelio_salesorder_id' => 'integer',
            'push_attempts' => 'integer',
            'payment_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'sent_to_jubelio_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function integrationLogs(): MorphMany
    {
        return $this->morphMany(IntegrationLog::class, 'loggable');
    }

    /** Sudah tercatat di Jubelio? Dipakai untuk mencegah order terkirim dua kali. */
    public function isInJubelio(): bool
    {
        return $this->jubelio_salesorder_id !== null;
    }
}
