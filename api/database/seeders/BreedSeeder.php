<?php

namespace Database\Seeders;

use App\Modules\Pets\Models\Breed;
use Illuminate\Database\Seeder;

/** Starter breed list; the clinic edits it from the admin panel later. */
class BreedSeeder extends Seeder
{
    public function run(): void
    {
        $breeds = [
            'dog' => [
                ['ژرمن شپرد', 'German Shepherd'], ['گلدن رتریور', 'Golden Retriever'], ['لابرادور رتریور', 'Labrador Retriever'],
                ['هاسکی سیبری', 'Siberian Husky'], ['پامرانین', 'Pomeranian'], ['شیتزو', 'Shih Tzu'],
                ['تریر یورکشایر', 'Yorkshire Terrier'], ['پاپیون', 'Papillon'], ['سرابی', 'Sarabi (Persian Mastiff)'],
                ['دوبرمن', 'Doberman'], ['روتوایلر', 'Rottweiler'], ['مخلوط', 'Mixed breed'],
            ],
            'cat' => [
                ['پرشین', 'Persian'], ['اسکاتیش فولد', 'Scottish Fold'], ['سیامی', 'Siamese'],
                ['بریتیش شورت‌هیر', 'British Shorthair'], ['مین کون', 'Maine Coon'], ['خانگی', 'Domestic shorthair'],
            ],
            'bird' => [
                ['طوطی کاسکو', 'African Grey Parrot'], ['مرغ عشق', 'Lovebird'], ['کاسکو', 'Budgerigar'], ['کوکاتیل', 'Cockatiel'], ['قناری', 'Canary'],
            ],
            'rabbit' => [['هلند لوپ', 'Holland Lop'], ['لاین‌هد', 'Lionhead']],
            'rodent' => [['همستر', 'Hamster'], ['خوکچه هندی', 'Guinea pig']],
        ];

        foreach ($breeds as $species => $list) {
            foreach ($list as [$fa, $en]) {
                Breed::query()->updateOrCreate(['species' => $species, 'name_en' => $en], ['name_fa' => $fa]);
            }
        }
    }
}
