<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JubelioLocation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\WebhookEvent;
use Database\Seeders\SparepartDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_products_variants_and_stock(): void
    {
        $this->seed(SparepartDemoSeeder::class);

        $this->assertSame(8, Product::count());
        $this->assertSame(13, ProductVariant::count());
        $this->assertNotNull(JubelioLocation::website());

        $product = Product::where('slug', 'roller-set-matic-13-gram')->firstOrFail();
        $this->assertCount(3, $product->variants);
        $this->assertSame('Ukuran', $product->variation_labels[0]['label']);
    }

    public function test_sellable_qty_subtracts_active_reservations_only(): void
    {
        $this->seed(SparepartDemoSeeder::class);

        $location = JubelioLocation::website()->jubelio_location_id;
        $variant = ProductVariant::where('variant_label', 'Vario 125 (2015-2018)')->firstOrFail();
        $this->assertSame(25, $variant->sellableQty($location));

        $order = $this->makeOrder();

        // Reservasi aktif mengurangi stok.
        StockReservation::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'jubelio_location_id' => $location,
            'qty' => 5,
            'expires_at' => now()->addDay(),
        ]);

        // Reservasi yang sudah kedaluwarsa atau dilepas tidak dihitung.
        StockReservation::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'jubelio_location_id' => $location,
            'qty' => 3,
            'expires_at' => now()->subMinute(),
        ]);
        StockReservation::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'jubelio_location_id' => $location,
            'qty' => 4,
            'expires_at' => now()->addDay(),
            'released_at' => now(),
        ]);

        $this->assertSame(20, $variant->sellableQty($location));
    }

    public function test_sellable_qty_never_goes_below_zero(): void
    {
        $this->seed(SparepartDemoSeeder::class);

        $location = JubelioLocation::website()->jubelio_location_id;
        $variant = ProductVariant::where('variant_label', 'NMAX 155 New')->firstOrFail();

        $this->assertSame(0, $variant->sellableQty($location));
    }

    public function test_order_no_must_be_unique(): void
    {
        $this->makeOrder('WEB-TEST-0001');

        $this->expectException(QueryException::class);
        $this->makeOrder('WEB-TEST-0001');
    }

    public function test_same_webhook_cannot_be_stored_twice(): void
    {
        $data = [
            'source' => 'jubelio',
            'event_type' => 'update-qty',
            'event_key' => hash('sha256', '{"action":"update-qty"}'),
            'payload' => ['action' => 'update-qty'],
        ];

        WebhookEvent::create($data);

        $this->expectException(QueryException::class);
        WebhookEvent::create($data);
    }

    private function makeOrder(string $orderNo = 'WEB-TEST-0002'): Order
    {
        $customer = Customer::create(['name' => 'Pembeli Uji', 'phone' => '080000000000']);

        return Order::create([
            'order_no' => $orderNo,
            'customer_id' => $customer->id,
            'customer_name' => 'Pembeli Uji',
            'customer_phone' => '080000000000',
            'shipping_name' => 'Pembeli Uji',
            'shipping_phone' => '080000000000',
            'shipping_address' => 'Jl. Uji No. 1',
            'grand_total' => 100000,
        ]);
    }
}
