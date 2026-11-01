<?php

declare(strict_types=1);

namespace App\Modules\Emergencies\Jobs;

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Identity\Sms\SmsDeliveryException;
use App\Modules\Identity\Sms\SmsSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** SMS to every active dispatcher; one bad number must not silence the others. */
class AlertDispatchers implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $ticketId) {}

    public function handle(SmsSender $sms): void
    {
        $dispatchers = User::query()->role(Role::Dispatcher->value)->where('status', User::STATUS_ACTIVE)->get();

        foreach ($dispatchers as $dispatcher) {
            $template = config("sms.templates.emergency.{$dispatcher->locale}") ?? config('sms.templates.emergency.fa');

            try {
                $sms->sendTemplate($dispatcher->mobile, (string) $template, ['ticket' => (string) $this->ticketId]);
            } catch (SmsDeliveryException $e) {
                Log::warning('Emergency alert failed', ['user_id' => $dispatcher->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
