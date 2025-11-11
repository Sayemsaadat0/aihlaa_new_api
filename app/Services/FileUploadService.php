<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileUploadService
{
    public static function uploadToPublicUploads(\Illuminate\Http\UploadedFile $file): string
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $uploadPath = public_path('uploads');

        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        $file->move($uploadPath, $filename);

        $baseUrl = rtrim(config('app.asset_url'), '/');
        return $baseUrl . '/uploads/' . $filename;
    }

    public static function deletePublicFileByAbsoluteUrl(?string $absoluteUrl): void
    {
        if (!$absoluteUrl) {
            return;
        }

        $baseUrl = rtrim(config('app.asset_url'), '/');
        if (str_starts_with($absoluteUrl, $baseUrl)) {
            $relative = ltrim(substr($absoluteUrl, strlen($baseUrl)), '/');
            $fullPath = public_path($relative);
            if (File::exists($fullPath)) {
                @File::delete($fullPath);
            }
        }
    }
}
