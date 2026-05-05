<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    private static function getCloudinary(): ?Cloudinary
    {
        $url = config('cloudinary.cloud_url') ?? env('CLOUDINARY_URL');
        if (!$url) return null;

        try {
            $config = Configuration::instance($url);
            return new Cloudinary($config);
        } catch (\Exception $e) {
            \Log::error('Cloudinary init failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function uploadImage(UploadedFile $file, string $folder = 'general'): string
    {
        if (config('app.env') === 'production') {
            $cloudinary = self::getCloudinary();
            if ($cloudinary) {
                try {
                    $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                        'folder'        => 'digital-marketplace/' . $folder,
                        'resource_type' => 'image',
                        'transformation' => [['quality' => 'auto', 'fetch_format' => 'auto']],
                    ]);
                    return $result['secure_url'];
                } catch (\Exception $e) {
                    \Log::error('Cloudinary upload failed: ' . $e->getMessage());
                }
            }
        }

        $path = $file->store($folder, 'public');
        return Storage::url($path);
    }

    public static function uploadFile(UploadedFile $file, string $folder = 'files'): array
    {
        if (config('app.env') === 'production') {
            $cloudinary = self::getCloudinary();
            if ($cloudinary) {
                try {
                    $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                        'folder'        => 'digital-marketplace/' . $folder,
                        'resource_type' => 'raw',
                        'public_id'     => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '_' . time(),
                    ]);
                    return [
                        'path' => $result['public_id'],
                        'url'  => $result['secure_url'],
                    ];
                } catch (\Exception $e) {
                    \Log::error('Cloudinary file upload failed: ' . $e->getMessage());
                }
            }
        }

        $path = $file->store($folder, 'public');
        return [
            'path' => $path,
            'url'  => Storage::url($path),
        ];
    }

    public static function delete(string $pathOrPublicId): void
    {
        if (config('app.env') === 'production') {
            $cloudinary = self::getCloudinary();
            if ($cloudinary) {
                try {
                    $cloudinary->adminApi()->deleteAssets([$pathOrPublicId]);
                } catch (\Exception $e) {
                    // Silencieux
                }
                return;
            }
        }

        Storage::disk('public')->delete($pathOrPublicId);
    }
}
