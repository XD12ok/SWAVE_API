<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class UploadService
{
    public function upload($file): array
    {
        $cloud = env('CLOUDINARY_CLOUD_NAME');
        $key = env('CLOUDINARY_API_KEY');
        $secret = env('CLOUDINARY_API_SECRET');

        $response = Http::withBasicAuth($key, $secret)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloud}/image/upload");

        if (! $response->successful()) {
            throw new \RuntimeException('Cloudinary upload failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'url' => $data['secure_url'] ?? null,
            'publicId' => $data['public_id'] ?? null,
        ];
    }
}
