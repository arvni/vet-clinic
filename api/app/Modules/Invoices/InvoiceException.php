<?php

declare(strict_types=1);

namespace App\Modules\Invoices;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvoiceException extends RuntimeException
{
    public static function notDraft(): self
    {
        return new self('invoices.not_draft');
    }

    public static function empty(): self
    {
        return new self('invoices.empty');
    }

    public static function notPayable(): self
    {
        return new self('invoices.not_payable');
    }

    public static function badAmount(): self
    {
        return new self('invoices.bad_amount');
    }

    public static function notCreditable(): self
    {
        return new self('invoices.not_creditable');
    }

    public static function badCode(): self
    {
        return new self('invoices.bad_code');
    }

    public static function feeNotSet(): self
    {
        return new self('invoices.fee_not_set');
    }

    public static function notConsult(): self
    {
        return new self('invoices.not_consult');
    }

    public static function alreadyCharged(): self
    {
        return new self('invoices.already_charged');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => __($this->getMessage())], 409);
    }
}
