<?php

declare(strict_types=1);

namespace App\Modules\Identity\Otp;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class OtpException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status, public readonly ?int $retryAfter = null)
    {
        parent::__construct(__('auth.otp.'.$reason));
    }

    public static function invalid(): self
    {
        return new self('invalid', 422);
    }

    public static function cooldown(int $retryAfter): self
    {
        return new self('cooldown', 429, $retryAfter);
    }

    public static function tooManyRequests(int $retryAfter): self
    {
        return new self('too_many', 429, $retryAfter);
    }

    public static function deliveryFailed(): self
    {
        return new self('delivery_failed', 503);
    }

    public function render(): JsonResponse
    {
        $headers = $this->retryAfter !== null ? ['Retry-After' => (string) $this->retryAfter] : [];

        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'reason' => $this->reason,
            'retry_after' => $this->retryAfter,
        ], fn ($v) => $v !== null), $this->status, $headers);
    }
}
