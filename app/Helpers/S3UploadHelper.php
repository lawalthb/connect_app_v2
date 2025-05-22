<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class S3UploadHelper
{
    /**
     * Upload a file to S3 and return the file details
     *
     * @param UploadedFile $file
     * @param string $directory
     * @return array
     */

    public static function uploadFile(UploadedFile $file, string $directory = 'profiles')
    {



















        try {
            // Generate a unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Store the file in S3
            $path = Storage::disk('s3')->putFileAs(
                $directory,
                $file,
                $filename,
                'public'
            );

            // Get the URL
            $url = Storage::disk('s3')->url($path);

            return [
                'filename' => $filename,
                'path' => $path,
                'url' => $url,
            ];
        } catch (\Exception $e) {
            Log::error('S3 upload failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
