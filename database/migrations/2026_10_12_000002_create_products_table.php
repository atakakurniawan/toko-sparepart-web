<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produk induk = "item group" di Jubelio.
 * Sumber: GET /inventory/items/ (daftar) dan GET /inventory/items/group/{id} (detail).
 * Website hanya menyalin; Jubelio tetap sumber kebenaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jubelio_item_group_id')->unique();
            $table->string('name');                       // item_group_name
            $table->string('slug')->unique();
            $table->longText('description')->nullable();  // description (bisa HTML)
            $table->string('brand_name')->nullable();
            $table->unsignedBigInteger('jubelio_category_id')->nullable(); // item_category_id
            $table->string('sell_unit', 50)->nullable();  // sell_unit, mis. "Buah"

            // Data paket untuk ongkir. package_weight di Jubelio dalam gram.
            $table->unsignedInteger('package_weight_gram')->nullable();
            $table->decimal('package_length_cm', 8, 2)->nullable();
            $table->decimal('package_width_cm', 8, 2)->nullable();
            $table->decimal('package_height_cm', 8, 2)->nullable();

            $table->string('thumbnail')->nullable();
            $table->json('images')->nullable();           // [{url, thumbnail, sequence_number}]
            $table->json('variation_labels')->nullable(); // variations: [{label, values[]}]

            // Nonaktif = hilang dari Jubelio atau diarsipkan. Tidak dihapus karena
            // mungkin masih dirujuk order lama.
            $table->boolean('is_active')->default(true);

            $table->timestamp('jubelio_last_modified')->nullable();
            $table->json('jubelio_payload')->nullable();  // respons asli, untuk jaga-jaga
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
