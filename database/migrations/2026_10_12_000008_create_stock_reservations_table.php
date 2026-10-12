<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penahanan stok lokal selama order menunggu pembayaran, supaya dua pembeli
 * tidak membayar barang terakhir yang sama. Dilepas saat invoice kedaluwarsa
 * atau saat order sudah masuk Jubelio (stok lalu dipegang Jubelio).
 *
 * Stok yang bisa dibeli = variant_stocks.available - total reservasi aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('jubelio_location_id');
            $table->unsignedInteger('qty');
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
