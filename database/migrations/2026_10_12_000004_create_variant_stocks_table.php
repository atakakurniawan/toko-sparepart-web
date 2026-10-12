<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok per varian per gudang.
 * Sumber: GET /inventory/ (location_stocks) atau POST /inventory/items/all-stocks/.
 * Rumus Jubelio: available = on_hand - on_order - reserved.
 * Website menjual berdasarkan kolom "available" di gudang website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('jubelio_location_id');
            $table->integer('on_hand')->default(0);
            $table->integer('on_order')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->default(0); // bisa negatif di Jubelio
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['product_variant_id', 'jubelio_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_stocks');
    }
};
