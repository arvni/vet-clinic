<?php

declare(strict_types=1);

namespace App\Modules\Identity\Sms;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Assert;

/**
 * Local and test driver: records messages instead of sending them.
 * In local dev the OTP shows up in storage/logs/laravel.log.
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{mobile: string, template: string, tokens: array<string, string>}> */
    public array $sent = [];

    public function sendTemplate(string $mobile, string $template, array $tokens): void
    {
        $this->sent[] = compact('mobile', 'template', 'tokens');
        Log::info('[fake-sms] '.$template.' to '.$mobile, $tokens);
    }

    /** @return array<string, string>|null tokens of the last message to $mobile */
    public function lastTo(string $mobile): ?array
    {
        $matches = array_values(array_filter($this->sent, fn (array $m) => $m['mobile'] === $mobile));

        return $matches === [] ? null : end($matches)['tokens'];
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent);
    }
}
