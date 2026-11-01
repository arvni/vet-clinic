<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Service;
use Illuminate\Database\Seeder;

/**
 * The 7 launch services (spec FR-2). Prices are left unset: the clinic
 * enters them in the admin panel. Re-running updates names, never prices.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['general_visit', 'ویزیت عمومی', 'General visit', 'visit', 30, ['clinic', 'home'], false, true],
            ['vaccination', 'واکسیناسیون', 'Vaccination', 'vaccination', 20, ['clinic', 'home'], false, true],
            ['deworming', 'ضد انگل', 'Deworming', 'deworming', 15, ['clinic', 'home'], true, true],
            ['anal_glands_nails', 'تخلیه کیسه مقعدی و گرفتن ناخن', 'Anal glands and nail trim', 'grooming', 15, ['clinic', 'home'], true, true],
            ['health_certificate', 'گواهی سلامت و شناسنامه کلینیک', 'Health certificate and clinic pet ID', 'certificate', 20, ['clinic'], false, false],
            ['online_consult', 'مشاوره آنلاین', 'Online consultation', 'consult', 20, ['online'], false, true],
            ['emergency', 'اورژانس', 'Emergency', 'emergency', 60, ['clinic', 'home'], false, false],
        ];

        foreach ($services as $i => [$code, $fa, $en, $category, $minutes, $locations, $addon, $prepay]) {
            Service::query()->updateOrCreate(['code' => $code], [
                'name_fa' => $fa,
                'name_en' => $en,
                'category' => $category,
                'duration_minutes' => $minutes,
                'locations' => $locations,
                'is_addon' => $addon,
                'requires_prepayment' => $prepay,
                'sort' => ($i + 1) * 10,
            ]);
        }
    }
}
