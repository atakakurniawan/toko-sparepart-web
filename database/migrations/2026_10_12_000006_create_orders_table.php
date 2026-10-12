<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order website. Hanya order berstatus "paid" yang dikirim ke Jubelio
 * (POST /sales/orders/) dengan salesorder_no = order_no.
 *
 * Alur status:
 * pending_payment -> paid -> sent_to_jubelio -> processing -> shipped -> completed
 * pending_payment -> expired
 * paid -> push_failed (gagal masuk Jubelio setelah retry; admin kirim ulang)
 * sent_to_jubelio/processing -> cancelled / needs_refund
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique();     // WEB-YYYYMMDD-XXXX, dipakai sebagai salesorder_no
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending_payment')->index();

            // Data pembeli saat checkout (disalin agar tetap utuh walau customer berubah).
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30);

            // Alamat kirim. Jubelio memakai ID wilayahnya sendiri (Region API),
            // Biteship memakai area_id sendiri, jadi keduanya disimpan.
            $table->string('shipping_name');
            $table->string('shipping_phone', 30);
            $table->text('shipping_address');
            $table->string('shipping_subdistrict')->nullable(); // kelurahan
            $table->string('shipping_district')->nullable();    // kecamatan (shipping_area di Jubelio)
            $table->string('shipping_city')->nullable();
            $table->string('shipping_province')->nullable();
            $table->string('shipping_post_code', 10)->nullable();
            $table->string('biteship_area_id')->nullable();
            $table->string('jubelio_province_id', 10)->nullable();
            $table->string('jubelio_city_id', 10)->nullable();
            $table->string('jubelio_district_id', 15)->nullable();
            $table->string('jubelio_subdistrict_id', 20)->nullable();

            // Pengiriman pilihan pembeli (dari Biteship).
            $table->string('courier_code', 30)->nullable();      // mis. jne
            $table->string('courier_service', 30)->nullable();   // mis. reg
            $table->string('shipper_label', 100)->nullable();    // mis. "JNE REG", dikirim sebagai "shipper"
            $table->unsignedInteger('total_weight_gram')->default(0);

            // Angka uang (Rupiah).
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('insurance_cost', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->text('note')->nullable();

            // Integrasi Jubelio.
            $table->integer('jubelio_location_id')->nullable();
            $table->unsignedBigInteger('jubelio_salesorder_id')->nullable()->unique();
            $table->string('jubelio_status', 50)->nullable();    // internal_status / wms_status terakhir
            $table->string('tracking_no')->nullable();           // nomor resi dari Jubelio
            $table->unsignedSmallInteger('push_attempts')->default(0);
            $table->text('last_push_error')->nullable();

            // Waktu penting.
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('sent_to_jubelio_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
