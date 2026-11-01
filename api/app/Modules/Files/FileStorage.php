<?php

declare(strict_types=1);

namespace App\Modules\Files;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Private file storage (local disk in dev, MinIO/ArvanCloud S3 in prod).
 * Files are never public; clients get short-lived signed URLs.
 */
class FileStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('files.disk'));
    }

    /** Store under an unguessable name; the original name is never trusted. */
    public function store(UploadedFile $file, string $directory): string
    {
        $extension = $file->guessExtension() ?? 'bin';
        $path = trim($directory, '/').'/'.now()->format('Y/m').'/'.Str::ulid().'.'.$extension;

        $this->disk()->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function signedUrl(string $route, array $parameters): string
    {
        return URL::temporarySignedRoute($route, now()->addMinutes((int) config('files.url_ttl_minutes')), $parameters);
    }
}
