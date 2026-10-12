<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan setiap panggilan ke Jubelio, Xendit, dan Biteship.
 * Simpan ringkasan saja, jangan password atau token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 20);               // jubelio / xendit / biteship
            $table->string('method', 10);
            $table->string('endpoint');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 50)->nullable(); // mis. 23505, 90000, E000001
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('request_summary')->nullable();
            $table->text('response_summary')->nullable();
            $table->nullableMorphs('loggable');           // mis. order yang terkait
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service', 'created_at']);
            $table->index('http_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
