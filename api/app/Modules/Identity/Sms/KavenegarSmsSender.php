<?php

declare(strict_types=1);

namespace App\Modules\Identity\Sms;

use App\Support\IranMobile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Kavenegar "verify/lookup" endpoint: sends a provider-approved template
 * with up to three tokens (token, token2, token3).
 *
 * @see https://kavenegar.com/rest.html
 */
class KavenegarSmsSender implements SmsSender
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.kavenegar.com/v1',
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function sendTemplate(string $mobile, string $template, array $tokens): void
    {
        $query = ['receptor' => IranMobile::toLocal($mobile), 'template' => $template];

        foreach (array_values($tokens) as $i => $value) {
            // Kavenegar tokens may not contain spaces; token10/token20 allow them but are not needed here.
            $query[$i === 0 ? 'token' : 'token'.($i + 1)] = str_replace(' ', '‌', $value);
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->retry(2, 500, throw: false)
                ->asForm()
                ->post("{$this->baseUrl}/{$this->apiKey}/verify/lookup.json", $query);
        } catch (ConnectionException $e) {
            throw new SmsDeliveryException('SMS provider unreachable: '.$e->getMessage(), previous: $e);
        }

        $status = $response->json('return.status');
        if (! $response->successful() || $status !== 200) {
            throw new SmsDeliveryException('SMS provider rejected message: '.($response->json('return.message') ?? $response->status()));
        }
    }
}
