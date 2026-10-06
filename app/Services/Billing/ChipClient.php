<?php

namespace App\Services\Billing;

use App\Exceptions\ChipException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The only class that talks to CHIP Collect (gate.chip-in.asia).
 * Test or live mode depends on the secret key, not the URL.
 */
class ChipClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.chip.key')) && filled(config('services.chip.brand_id'));
    }

    /** POST /purchases/ — returns the Purchase (id, checkout_url, status, ...). */
    public function createPurchase(array $body): array
    {
        $response = $this->request()->post('/purchases/', ['brand_id' => config('services.chip.brand_id')] + $body);

        return $this->json($response, 'cipta pembelian');
    }

    /** GET /purchases/{id}/ */
    public function getPurchase(string $id): array
    {
        return $this->json($this->request()->get('/purchases/'.rawurlencode($id).'/'), 'semak pembelian');
    }

    /** Company public key (PEM) used to sign success_callback payloads. Cached for a day. */
    public function publicKey(): string
    {
        if (filled(config('services.chip.public_key'))) {
            return (string) config('services.chip.public_key');
        }

        return Cache::remember('chip.public_key', now()->addDay(), function () {
            $response = $this->request()->get('/public_key/');
            if ($response->failed()) {
                throw new ChipException('Tak dapat ambil public key CHIP ('.$response->status().').');
            }

            $decoded = json_decode($response->body(), true);

            return is_string($decoded) ? $decoded : (string) ($decoded['public_key'] ?? trim($response->body(), "\" \n\r\t"));
        });
    }

    /** X-Signature: base64 RSA PKCS#1 v1.5 signature of SHA-256 over the raw body. */
    public function verifySignature(string $rawBody, ?string $signatureB64): bool
    {
        $signature = base64_decode((string) $signatureB64, true);
        if ($signature === false || $signature === '') {
            return false;
        }

        $key = openssl_pkey_get_public($this->publicKey());
        if ($key === false) {
            return false;
        }

        return openssl_verify($rawBody, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new ChipException('CHIP_SECRET_KEY dan CHIP_BRAND_ID belum diset dalam .env.');
        }

        return Http::baseUrl(config('services.chip.base_url'))
            ->timeout((int) config('services.chip.timeout', 30))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.chip.key'));
    }

    private function json(Response $response, string $what): array
    {
        if ($response->failed()) {
            $message = $response->json('__all__.message') ?? $response->body();

            throw new ChipException("CHIP gagal {$what} ({$response->status()}): ".mb_substr((string) $message, 0, 300));
        }

        return (array) $response->json();
    }
}
