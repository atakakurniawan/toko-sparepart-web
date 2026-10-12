<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semua webhook yang masuk (Jubelio, Xendit). Jubelio mengirim ulang maksimal
 * 3 kali bila tidak dibalas 200, dan Xendit juga bisa mengirim dobel, jadi
 * event_key unik per sumber dipakai untuk mencegah proses ganda.
 *
 * event_key: Xendit = id event/invoice + status; Jubelio = sha256 body mentah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);                 // jubelio / xendit
            $table->string('event_type', 50)->nullable(); // action Jubelio (update-qty, update-salesorder, ...) atau status Xendit
            $table->string('event_key', 128);
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending / processed / failed / ignored
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'event_key']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
