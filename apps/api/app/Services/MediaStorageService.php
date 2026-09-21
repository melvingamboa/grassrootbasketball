<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function replace(?string $currentPath, UploadedFile $image, string $directory): string
    {
        $disk = Storage::disk(config('media.disk'));
        $newPath = $image->storePublicly($directory, ['disk' => config('media.disk')]);

        if ($currentPath) {
            $disk->delete($currentPath);
        }

        return $newPath;
    }
}
