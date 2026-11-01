<?php

declare(strict_types=1);

namespace App\Modules\Identity\Sms;

/**
 * Iranian SMS providers filter free-text messages to unregistered numbers,
 * so every message goes through a provider-approved template.
 */
interface SmsSender
{
    /**
     * @param  string  $mobile  E.164 recipient
     * @param  string  $template  Provider template name (one per language)
     * @param  array<string, string>  $tokens  Template placeholders
     *
     * @throws SmsDeliveryException
     */
    public function sendTemplate(string $mobile, string $template, array $tokens): void;
}
