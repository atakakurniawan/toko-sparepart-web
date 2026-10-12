<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembeli website. Sales order Jubelio WAJIB punya contact_id, jadi tiap pembeli
 * dibuat sebagai kontak di Jubelio (POST /contacts/) dan ID-nya disimpan di sini
 * agar pembelian berikutnya tidak membuat kontak ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->index();
            $table->unsignedBigInteger('jubelio_contact_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
