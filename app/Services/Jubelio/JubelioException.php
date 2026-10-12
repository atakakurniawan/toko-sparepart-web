<?php

namespace App\Services\Jubelio;

use RuntimeException;

/**
 * Error dari Jubelio, lengkap dengan status HTTP dan kode error Jubelio
 * (mis. 23505 = nomor order sudah ada, 90000 = email/password salah).
 */
class JubelioException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorCode = null,
        public readonly ?array $responseBody = null,
    ) {
        parent::__construct($message);
    }

    /** Nomor order (salesorder_no) sudah ada di Jubelio. */
    public function isDuplicate(): bool
    {
        return $this->errorCode === '23505';
    }

    /** Kena batas 600 request/menit. Jubelio minta tunggu 1 menit. */
    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }

    /** Berapa detik sebaiknya job menunggu sebelum mencoba lagi. */
    public function retryAfterSeconds(): int
    {
        return $this->isRateLimited() ? 60 : 30;
    }
}
