<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use Illuminate\Support\Str;

/** Local and test driver: every payment succeeds. Never bound in production. */
class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function request(int $amountRials, string $callbackUrl, string $description, string $mobile): array
    {
        $authority = 'FAKE'.Str::random(32);

        return ['authority' => $authority, 'redirect_url' => $callbackUrl.'?Authority='.$authority.'&Status=OK'];
    }

    public function verify(string $authority, int $amountRials): array
    {
        return ['paid' => true, 'ref_id' => 'FAKE-REF', 'card_pan' => null];
    }
}
