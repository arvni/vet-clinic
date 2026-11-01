<?php

declare(strict_types=1);

namespace App\Modules\Locations\Maps;

/** Local and test driver: returns a fixed result without calling any service. */
class FakeMapProvider implements MapProvider
{
    public function reverse(float $lat, float $lng): ?string
    {
        return 'بندرعباس، بلوار امام خمینی';
    }

    public function search(string $term, float $nearLat, float $nearLng): array
    {
        return [['title' => $term, 'address' => 'بندرعباس', 'lat' => $nearLat, 'lng' => $nearLng]];
    }
}
