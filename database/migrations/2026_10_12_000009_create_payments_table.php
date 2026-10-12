<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice Xendit. external_id = order_no. Satu order bisa punya lebih dari
 * satu invoice (mis. invoice pertama kedaluwarsa lalu dibuat ulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('xendit');
            $table->string('external_id', 100)->index();          // = order_no
            $table->string('provider_invoice_id')->nullable()->unique();
            $table->text('invoice_url')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('PENDING');     // PENDING / PAID / SETTLED / EXPIRED
            $table->string('payment_method', 50)->nullable();     // mis. BANK_TRANSFER, QR_CODE
            $table->string('payment_channel', 50)->nullable();    // mis. BCA, QRIS
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
