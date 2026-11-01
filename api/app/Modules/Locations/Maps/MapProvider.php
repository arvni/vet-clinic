<?php

declare(strict_types=1);

namespace App\Modules\Locations\Maps;

interface MapProvider
{
    /** Human-readable address for a point, or null when none is known. */
    public function reverse(float $lat, float $lng): ?string;

    /**
     * @return list<array{title: string, address: string, lat: float, lng: float}>
     *
     * @throws MapUnavailableException
     */
    public function search(string $term, float $nearLat, float $nearLng): array;
}
