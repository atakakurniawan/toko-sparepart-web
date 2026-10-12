<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salinan daftar gudang dari Jubelio (GET /locations/).
 * Catatan: location_id Jubelio bisa bernilai negatif (mis. -1 = "Pusat"),
 * jadi kolomnya integer bertanda, bukan unsigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jubelio_locations', function (Blueprint $table) {
            $table->id();
            $table->integer('jubelio_location_id')->unique();
            $table->string('name');
            $table->string('location_code')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('post_code', 10)->nullable();
            $table->boolean('is_warehouse')->default(true);
            $table->boolean('is_active')->default(true);
            // Gudang yang melayani order website (biasanya hanya satu).
            $table->boolean('serves_website')->default(false);
            $table->json('jubelio_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jubelio_locations');
    }
};
