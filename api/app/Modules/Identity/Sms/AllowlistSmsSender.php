<?php

declare(strict_types=1);

namespace App\Modules\Identity\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Staging guard: only allowlisted numbers reach the real provider;
 * everything else is logged.
 */
class AllowlistSmsSender implements SmsSender
{
    /** @param list<string> $allowlist */
    public function __construct(private readonly SmsSender $inner, private readonly array $allowlist) {}

    public function sendTemplate(string $mobile, string $template, array $tokens): void
    {
        if (! in_array($mobile, $this->allowlist, true)) {
            Log::info('[sms-allowlist] suppressed '.$template.' to '.$mobile, $tokens);

            return;
        }

        $this->inner->sendTemplate($mobile, $template, $tokens);
    }
}
