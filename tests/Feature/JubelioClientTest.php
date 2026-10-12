<?php

namespace Tests\Feature;

use App\Models\IntegrationLog;
use App\Services\Jubelio\JubelioClient;
use App\Services\Jubelio\JubelioException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Semua test memakai Jubelio palsu (Http::fake), jadi tidak butuh akun Jubelio.
 * Bentuk respons diambil dari dokumentasi resmi Jubelio.
 */
class JubelioClientTest extends TestCase
{
    use RefreshDatabase;

    private JubelioClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake(); // jeda retry tidak benar-benar menunggu saat test
        Cache::flush();

        $this->client = new JubelioClient([
            'base_url' => 'https://api2.jubelio.com',
            'email' => 'api@toko.test',
            'password' => 'rahasia-123',
            'retries' => 3,
            'retry_sleep_ms' => 10,
        ]);
    }

    public function test_logs_in_once_and_reuses_cached_token(): void
    {
        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'tok-1']),
            'api2.jubelio.com/inventory/items/*' => Http::response(['data' => [], 'totalCount' => 0]),
        ]);

        $this->client->getProducts();
        $this->client->getProducts(2);

        // 1 login + 2 ambil produk.
        Http::assertSentCount(3);

        // Token dikirim di header "authorization" tanpa kata "Bearer".
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/inventory/items/')
            && $r->header('authorization') === ['tok-1']);
    }

    public function test_logs_in_again_when_token_is_rejected(): void
    {
        Cache::put('jubelio.token', 'token-lama', now()->addHour());

        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'token-baru']),
            'api2.jubelio.com/locations/*' => Http::sequence()
                ->push(['message' => 'Unauthorized'], 401)
                ->push(['data' => [['location_id' => -1, 'location_name' => 'Pusat']], 'totalCount' => 1]),
        ]);

        $result = $this->client->getLocations();

        $this->assertSame('Pusat', $result['data'][0]['location_name']);
        $this->assertSame('token-baru', Cache::get('jubelio.token'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/locations/')
            && $r->header('authorization') === ['token-baru']);
    }

    public function test_retries_server_error_without_business_code(): void
    {
        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'tok-1']),
            'api2.jubelio.com/inventory/*' => Http::sequence()
                ->push('Bad Gateway', 502)
                ->push(['data' => [], 'totalCount' => 0]),
        ]);

        $result = $this->client->getStocks();

        $this->assertSame(0, $result['totalCount']);
        Sleep::assertSleptTimes(1);
    }

    public function test_duplicate_order_is_not_retried(): void
    {
        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'tok-1']),
            'api2.jubelio.com/sales/orders/' => Http::response([
                'statusCode' => 500,
                'error' => 'Internal Server Error',
                'message' => 'duplicate key value violates unique constraint',
                'code' => '23505',
            ], 500),
        ]);

        try {
            $this->client->createSalesOrder(['salesorder_no' => 'WEB-TEST-0001']);
            $this->fail('Seharusnya melempar JubelioException');
        } catch (JubelioException $e) {
            $this->assertTrue($e->isDuplicate());
        }

        // 1 login + 1 kirim order. Tidak ada retry.
        Http::assertSentCount(2);
        Sleep::assertNeverSlept();
    }

    public function test_rate_limit_is_thrown_for_the_job_to_handle(): void
    {
        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'tok-1']),
            'api2.jubelio.com/inventory/items/*' => Http::response(['message' => 'Too Many Requests'], 429),
        ]);

        try {
            $this->client->getProducts();
            $this->fail('Seharusnya melempar JubelioException');
        } catch (JubelioException $e) {
            $this->assertTrue($e->isRateLimited());
            $this->assertSame(60, $e->retryAfterSeconds());
        }
    }

    public function test_wrong_password_gives_clear_error(): void
    {
        // Jubelio mengembalikan HTTP 500 (bukan 401) untuk email/password salah.
        Http::fake([
            'api2.jubelio.com/login' => Http::response([
                'statusCode' => 500,
                'error' => 'Internal Server Error',
                'code' => 'Invalid email or password',
            ], 500),
        ]);

        $this->expectException(JubelioException::class);
        $this->expectExceptionMessage('Login Jubelio gagal');

        $this->client->getProducts();
    }

    public function test_every_call_is_logged_without_the_password(): void
    {
        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'tok-1']),
            'api2.jubelio.com/inventory/items/*' => Http::response(['data' => [], 'totalCount' => 0]),
        ]);

        $this->client->getProducts();

        $this->assertSame(2, IntegrationLog::count());
        $this->assertDatabaseHas('integration_logs', ['endpoint' => '/login', 'http_status' => 200]);
        $this->assertDatabaseHas('integration_logs', ['endpoint' => '/inventory/items/', 'http_status' => 200]);

        foreach (IntegrationLog::all() as $log) {
            $this->assertStringNotContainsString('rahasia-123', (string) $log->request_summary);
        }
    }

    public function test_find_sales_order_by_exact_number(): void
    {
        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'tok-1']),
            'api2.jubelio.com/sales/v2/orders/*' => Http::response([
                'data' => [
                    ['salesorder_id' => 11, 'salesorder_no' => 'WEB-TEST-00011'],
                    ['salesorder_id' => 10, 'salesorder_no' => 'WEB-TEST-0001'],
                ],
                'totalCount' => 2,
            ]),
        ]);

        $order = $this->client->findSalesOrderByNo('WEB-TEST-0001');

        $this->assertSame(10, $order['salesorder_id']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'page_size=10')
            && str_contains($r->url(), 'q=WEB-TEST-0001'));
    }

    public function test_create_contact_returns_contact_id(): void
    {
        Http::fake([
            'api2.jubelio.com/login' => Http::response(['token' => 'tok-1']),
            'api2.jubelio.com/contacts/' => Http::response(['status' => 'ok', 'contact_id' => 321]),
        ]);

        $id = $this->client->createContact(['contact_name' => 'Budi', 'phone' => '0812']);

        $this->assertSame(321, $id);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/contacts/')
            && $r['contact_id'] === 0
            && $r['contact_type'] === 0
            && $r['contact_name'] === 'Budi');
    }
}
