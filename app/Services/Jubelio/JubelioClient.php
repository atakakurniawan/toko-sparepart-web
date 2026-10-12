<?php

namespace App\Services\Jubelio;

use App\Models\IntegrationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

/**
 * Satu-satunya pintu website ke API Jubelio (https://api2.jubelio.com).
 *
 * Aturan dari dokumentasi Jubelio:
 * - Token dari POST /login berlaku 12 jam, dikirim di header "authorization" tanpa "Bearer".
 * - Batas 600 request/menit; kalau kena 429, tunggu 1 menit.
 * - Banyak error bisnis dikembalikan sebagai HTTP 500 dengan field "code"
 *   (mis. 23505 nomor order dobel). Error seperti ini TIDAK di-retry.
 */
class JubelioClient
{
    private const CACHE_KEY = 'jubelio.token';

    private string $baseUrl;

    public function __construct(private readonly array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api2.jubelio.com', '/');
    }

    public static function fromConfig(): self
    {
        return new self(config('services.jubelio', []));
    }

    // ------------------------------------------------------------------
    // Produk & stok
    // ------------------------------------------------------------------

    /** Daftar produk induk beserta varian. Hasil: ['data' => [...], 'totalCount' => n]. */
    public function getProducts(int $page = 1, int $pageSize = 100): array
    {
        return $this->get('/inventory/items/', ['page' => $page, 'pageSize' => min($pageSize, 200)]);
    }

    /** Detail produk induk: deskripsi, berat (gram), gambar, harga per varian. */
    public function getProductGroup(int $itemGroupId): array
    {
        return $this->get("/inventory/items/group/{$itemGroupId}");
    }

    public function getItem(int $itemId): array
    {
        return $this->get("/inventory/items/{$itemId}");
    }

    /** Stok semua produk per lokasi (on_hand, on_order, reserved, available). */
    public function getStocks(int $page = 1, int $pageSize = 100): array
    {
        return $this->get('/inventory/', ['page' => $page, 'pageSize' => min($pageSize, 200)]);
    }

    /** Stok varian tertentu. Dipakai setelah webhook update-qty (yang tidak membawa angka stok). */
    public function getStocksByItemIds(array $itemIds): array
    {
        return $this->post('/inventory/items/all-stocks/', ['ids' => array_values($itemIds)]);
    }

    public function getLocations(): array
    {
        return $this->get('/locations/', ['page' => 1, 'pageSize' => 200]);
    }

    // ------------------------------------------------------------------
    // Pembeli & order
    // ------------------------------------------------------------------

    /**
     * Buat kontak pelanggan baru. Sales order Jubelio wajib punya contact_id.
     * Mengembalikan contact_id.
     */
    public function createContact(array $contact, ?Model $loggable = null): int
    {
        $payload = array_merge([
            'contact_id' => 0,
            'contact_type' => 0, // 0 = customer
            'is_company' => false,
        ], $contact);

        $response = $this->post('/contacts/', $payload, $loggable);

        return (int) ($response['contact_id'] ?? 0);
    }

    /**
     * Kirim sales order. Mengembalikan salesorder_id Jubelio.
     * Kalau nomor order sudah ada, melempar JubelioException dengan isDuplicate() = true.
     */
    public function createSalesOrder(array $payload, ?Model $loggable = null): int
    {
        $response = $this->post('/sales/orders/', $payload, $loggable);

        return (int) ($response['id'] ?? 0);
    }

    public function getSalesOrder(int $salesOrderId): array
    {
        return $this->get("/sales/orders/{$salesOrderId}");
    }

    /** Cari order berdasarkan nomor persis (mis. WEB-20261012-0001). */
    public function findSalesOrderByNo(string $salesOrderNo): ?array
    {
        $result = $this->get('/sales/v2/orders/', [
            'page' => 1,
            'page_size' => 10, // endpoint v2 memakai snake_case
            'q' => $salesOrderNo,
        ]);

        foreach ($result['data'] ?? [] as $order) {
            if (($order['salesorder_no'] ?? null) === $salesOrderNo) {
                return $order;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Inti: token, request, retry, log
    // ------------------------------------------------------------------

    public function get(string $path, array $query = [], ?Model $loggable = null): array
    {
        return $this->request('GET', $path, $query, $loggable);
    }

    public function post(string $path, array $body = [], ?Model $loggable = null): array
    {
        return $this->request('POST', $path, $body, $loggable);
    }

    /** Token dari cache; login hanya kalau belum ada atau sudah dibuang. */
    public function token(): string
    {
        $ttl = now()->addMinutes((int) ($this->config['token_ttl_minutes'] ?? 660));

        return Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->login());
    }

    public function forgetToken(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function login(): string
    {
        $started = microtime(true);

        try {
            $response = $this->http()->post($this->baseUrl.'/login', [
                'email' => $this->config['email'] ?? null,
                'password' => $this->config['password'] ?? null,
            ]);
        } catch (ConnectionException $e) {
            $this->log('POST', '/login', null, $started, ['email' => $this->config['email'] ?? null], $e->getMessage());

            throw new JubelioException('Tidak bisa terhubung ke Jubelio saat login: '.$e->getMessage());
        }

        // Jangan pernah mencatat password.
        $this->log('POST', '/login', $response, $started, ['email' => $this->config['email'] ?? null]);

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new JubelioException(
                'Login Jubelio gagal. Cek JUBELIO_EMAIL dan JUBELIO_PASSWORD.',
                $response->status(),
                $this->errorCode($response),
                $response->json(),
            );
        }

        return $token;
    }

    private function request(string $method, string $path, array $data, ?Model $loggable): array
    {
        $maxAttempts = max(1, (int) ($this->config['retries'] ?? 3));
        $sleepMs = (int) ($this->config['retry_sleep_ms'] ?? 1000);
        $reloggedIn = false;
        $attempt = 0;

        while (true) {
            $attempt++;
            $started = microtime(true);

            try {
                $pending = $this->http()->withHeaders(['authorization' => $this->token()]);
                $url = $this->baseUrl.$path;

                $response = $method === 'GET'
                    ? $pending->get($url, $data)
                    : $pending->send($method, $url, ['json' => $data]);
            } catch (ConnectionException $e) {
                $this->log($method, $path, null, $started, $data, $e->getMessage(), $loggable);

                if ($attempt < $maxAttempts) {
                    Sleep::for($sleepMs * $attempt)->milliseconds();

                    continue;
                }

                throw new JubelioException("Tidak bisa terhubung ke Jubelio ({$method} {$path}): ".$e->getMessage());
            }

            $this->log($method, $path, $response, $started, $data, null, $loggable);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            // Token kedaluwarsa atau tidak valid: login ulang sekali, lalu ulangi.
            if ($response->status() === 401 && ! $reloggedIn) {
                $reloggedIn = true;
                $this->forgetToken();

                continue;
            }

            // Kena rate limit: jangan tidur 1 menit di sini, biar job yang menunda.
            if ($response->status() === 429) {
                throw $this->toException($method, $path, $response);
            }

            // Server bermasalah TANPA kode error bisnis: aman dicoba lagi.
            if ($response->serverError() && $this->errorCode($response) === null && $attempt < $maxAttempts) {
                Sleep::for($sleepMs * $attempt)->milliseconds();

                continue;
            }

            throw $this->toException($method, $path, $response);
        }
    }

    private function http()
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 30));
    }

    private function errorCode(Response $response): ?string
    {
        $code = $response->json('code');

        return ($code === null || $code === '') ? null : (string) $code;
    }

    private function toException(string $method, string $path, Response $response): JubelioException
    {
        $message = $response->json('message') ?? $response->json('error') ?? 'Request gagal';

        return new JubelioException(
            "Jubelio {$method} {$path} gagal (HTTP {$response->status()}): {$message}",
            $response->status(),
            $this->errorCode($response),
            $response->json(),
        );
    }

    private function log(
        string $method,
        string $path,
        ?Response $response,
        float $started,
        array $request = [],
        ?string $error = null,
        ?Model $loggable = null,
    ): void {
        try {
            IntegrationLog::create([
                'service' => 'jubelio',
                'method' => $method,
                'endpoint' => $path,
                'http_status' => $response?->status(),
                'error_code' => $response ? $this->errorCode($response) : null,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'request_summary' => Str::limit(json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 2000),
                'response_summary' => Str::limit($error ?? (string) $response?->body(), 2000),
                'loggable_type' => $loggable?->getMorphClass(),
                'loggable_id' => $loggable?->getKey(),
            ]);
        } catch (Throwable $e) {
            // Gagal menulis log tidak boleh menggagalkan panggilan ke Jubelio.
            Log::warning('Gagal menulis integration_logs: '.$e->getMessage());
        }
    }
}
