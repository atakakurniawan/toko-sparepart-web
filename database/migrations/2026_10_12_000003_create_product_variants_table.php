<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Varian = "item" di Jubelio. Produk tanpa varian tetap punya 1 baris di sini,
 * karena order ke Jubelio selalu memakai item_id varian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('jubelio_item_id')->unique();
            $table->string('sku')->index();               // item_code
            $table->string('name');                       // item_name
            $table->string('variant_label')->nullable();  // gabungan nilai varian, mis. "Vario 125, Hitam"
            $table->json('variation_values')->nullable(); // [{label, value}]
            $table->decimal('price', 15, 2)->default(0);  // sell_price
            $table->string('barcode')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_bundle')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('jubelio_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
