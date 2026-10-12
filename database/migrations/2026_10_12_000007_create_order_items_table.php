<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item order. Nama, SKU, dan harga disalin saat beli agar riwayat order tidak
 * berubah walau produk diubah atau dinonaktifkan di Jubelio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('jubelio_item_id');   // item_id saat dikirim ke Jubelio
            $table->string('sku');
            $table->string('name');
            $table->string('variant_label')->nullable();
            $table->string('unit', 50)->default('Buah');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('qty');
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->unsignedInteger('weight_gram')->default(0); // berat per unit
            $table->unsignedBigInteger('jubelio_salesorder_detail_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
