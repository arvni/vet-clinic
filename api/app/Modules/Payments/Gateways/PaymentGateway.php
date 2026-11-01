<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

interface PaymentGateway
{
    public function name(): string;

    /**
     * @return array{authority: string, redirect_url: string}
     *
     * @throws PaymentUnavailableException
     */
    public function request(int $amountRials, string $callbackUrl, string $description, string $mobile): array;

    /**
     * Asks the gateway whether the money really moved.
     *
     * @return array{paid: bool, ref_id: string|null, card_pan: string|null}
     *
     * @throws PaymentUnavailableException when the answer is unknown
     */
    public function verify(string $authority, int $amountRials): array;
}
