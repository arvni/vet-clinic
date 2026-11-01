<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Zarinpal REST v4. Amounts are sent in rials.
 *
 * @see https://www.zarinpal.com/docs/paymentGateway/
 */
class ZarinpalGateway implements PaymentGateway
{
    private const OK = 100;

    private const ALREADY_VERIFIED = 101;

    public function __construct(
        private readonly string $merchantId,
        private readonly string $baseUrl = 'https://payment.zarinpal.com',
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function name(): string
    {
        return 'zarinpal';
    }

    public function request(int $amountRials, string $callbackUrl, string $description, string $mobile): array
    {
        $response = $this->post('/pg/v4/payment/request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amountRials,
            'currency' => 'IRR',
            'callback_url' => $callbackUrl,
            'description' => $description,
            'metadata' => ['mobile' => $mobile],
        ]);

        $authority = $response->json('data.authority');
        if ((int) $response->json('data.code') !== self::OK || ! is_string($authority)) {
            throw new PaymentUnavailableException('Zarinpal refused the request: '.$response->body());
        }

        return ['authority' => $authority, 'redirect_url' => rtrim($this->baseUrl, '/').'/pg/StartPay/'.$authority];
    }

    public function verify(string $authority, int $amountRials): array
    {
        $response = $this->post('/pg/v4/payment/verify.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amountRials,
            'authority' => $authority,
        ]);

        $code = (int) $response->json('data.code');
        $paid = $code === self::OK || $code === self::ALREADY_VERIFIED;

        return [
            'paid' => $paid,
            'ref_id' => $paid ? (string) $response->json('data.ref_id') : null,
            'card_pan' => $paid ? $response->json('data.card_pan') : null,
        ];
    }

    /** @param  array<string, mixed>  $body */
    private function post(string $path, array $body): Response
    {
        try {
            $response = Http::baseUrl($this->baseUrl)->acceptJson()->asJson()->timeout($this->timeoutSeconds)->post($path, $body);
        } catch (ConnectionException $e) {
            throw new PaymentUnavailableException($e->getMessage(), previous: $e);
        }

        // Zarinpal reports refusals as 4xx with an errors body: the payment simply did not happen.
        if ($response->serverError()) {
            throw new PaymentUnavailableException('Zarinpal responded '.$response->status());
        }

        return $response;
    }
}
