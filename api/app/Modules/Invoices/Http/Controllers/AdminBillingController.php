<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Http\Controllers;

use App\Modules\Invoices\Models\DiscountCode;
use App\Modules\Invoices\Models\TaxRate;
use App\Modules\Invoices\PrescriptionFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** VAT rates and discount codes are admin data, never hard-coded. */
class AdminBillingController
{
    public function taxRates(): JsonResponse
    {
        Gate::authorize('manage-catalog');

        return response()->json(['data' => TaxRate::query()->orderBy('category')->orderByDesc('effective_from')->get()
            ->map(fn (TaxRate $r) => $this->rateData($r))]);
    }

    /** A new row with a date; old rates stay for old invoices. */
    public function storeTaxRate(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $data = $request->validate([
            'category' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/'],
            'percent' => ['required', 'numeric', 'between:0,100'],
            'effective_from' => ['required', 'date_format:Y-m-d', Rule::unique('tax_rates')->where('category', $request->input('category'))],
        ]);

        $rate = TaxRate::create([
            'category' => $data['category'],
            'basis_points' => (int) round((float) $data['percent'] * 100),
            'effective_from' => $data['effective_from'],
        ]);

        return response()->json(['data' => $this->rateData($rate)], 201);
    }

    public function prescriptionFee(PrescriptionFee $fee): JsonResponse
    {
        Gate::authorize('manage-catalog');

        return response()->json(['data' => ['rials' => $fee->rials() === null ? null : (string) $fee->rials()]]);
    }

    public function updatePrescriptionFee(Request $request, PrescriptionFee $fee): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $data = $request->validate(['toman' => ['required', 'integer', 'min:0']]);
        $fee->save((int) $data['toman'] * 10);

        return $this->prescriptionFee($fee);
    }

    public function discountCodes(): JsonResponse
    {
        Gate::authorize('manage-catalog');

        return response()->json(['data' => DiscountCode::query()->latest('id')->get()->map(fn (DiscountCode $c) => $this->codeData($c))]);
    }

    public function storeDiscountCode(Request $request): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', 'unique:discount_codes,code'],
            'percent' => ['required_without:toman', 'prohibits:toman', 'nullable', 'numeric', 'gt:0', 'max:100'],
            'toman' => ['required_without:percent', 'nullable', 'integer', 'min:1'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
        ]);

        $code = DiscountCode::create([
            'code' => $data['code'],
            'basis_points' => isset($data['percent']) ? (int) round((float) $data['percent'] * 100) : null,
            'fixed_rials' => isset($data['toman']) ? (int) $data['toman'] * 10 : null,
            'max_uses' => $data['max_uses'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        return response()->json(['data' => $this->codeData($code)], 201);
    }

    public function updateDiscountCode(Request $request, DiscountCode $code): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $code->update($request->validate([
            'active' => ['sometimes', 'boolean'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]));

        return response()->json(['data' => $this->codeData($code)]);
    }

    /** @return array<string, mixed> */
    private function rateData(TaxRate $rate): array
    {
        return [
            'id' => $rate->id,
            'category' => $rate->category,
            'percent' => $rate->basis_points / 100,
            'effective_from' => $rate->effective_from->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function codeData(DiscountCode $code): array
    {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'percent' => $code->basis_points === null ? null : $code->basis_points / 100,
            'fixed_rials' => $code->fixed_rials === null ? null : (string) $code->fixed_rials,
            'max_uses' => $code->max_uses,
            'used_count' => $code->used_count,
            'starts_at' => $code->starts_at?->toIso8601ZuluString(),
            'ends_at' => $code->ends_at?->toIso8601ZuluString(),
            'active' => $code->active,
        ];
    }
}
