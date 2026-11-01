<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaymentUnavailableException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => __('payments.unavailable')], 503);
    }
}
