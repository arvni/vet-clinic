<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Models\PetDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams private files behind short-lived signed URLs, so <img> tags and
 * downloads work without bearer headers. Access was checked when the URL
 * was issued (inside an authorized resource response).
 */
class FileController
{
    public function __construct(private readonly FileStorage $files) {}

    public function petPhoto(Pet $pet): StreamedResponse
    {
        abort_if($pet->photo_path === null, 404);

        return $this->stream($pet->photo_path, 'inline');
    }

    public function petDocument(PetDocument $document): StreamedResponse
    {
        abort_if($document->file_path === null, 404);

        return $this->stream($document->file_path, 'inline');
    }

    private function stream(string $path, string $disposition): StreamedResponse
    {
        $disk = $this->files->disk();
        abort_unless($disk->exists($path), 404);

        $response = $disk->response($path, basename($path), [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], $disposition);

        return $response;
    }
}
