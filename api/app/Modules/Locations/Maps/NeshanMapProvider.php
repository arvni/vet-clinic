<?php

declare(strict_types=1);

namespace App\Modules\Locations\Maps;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Neshan reverse geocoding and place search.
 *
 * @see https://platform.neshan.org/docs/api/
 */
class NeshanMapProvider implements MapProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.neshan.org',
        private readonly int $timeoutSeconds = 5,
    ) {}

    public function reverse(float $lat, float $lng): ?string
    {
        $address = $this->get('/v5/reverse', ['lat' => $lat, 'lng' => $lng])->json('formatted_address');

        return is_string($address) && $address !== '' ? $address : null;
    }

    public function search(string $term, float $nearLat, float $nearLng): array
    {
        $items = $this->get('/v1/search', ['term' => $term, 'lat' => $nearLat, 'lng' => $nearLng])->json('items');
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(fn (array $item) => [
            'title' => (string) ($item['title'] ?? ''),
            'address' => (string) ($item['address'] ?? ''),
            'lat' => (float) ($item['location']['y'] ?? 0),
            'lng' => (float) ($item['location']['x'] ?? 0),
        ], $items));
    }

    /** @param  array<string, mixed>  $query */
    private function get(string $path, array $query): Response
    {
        try {
            $response = $this->client()->get($path, $query);
        } catch (ConnectionException $e) {
            throw new MapUnavailableException($e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new MapUnavailableException('Neshan responded '.$response->status());
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['Api-Key' => $this->apiKey])
            ->acceptJson()
            ->timeout($this->timeoutSeconds);
    }
}
