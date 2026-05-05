<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    /**
     * Upload un fichier image vers Cloudinary (prod) ou local (dev)
     * Retourne l'URL publique du fichier
     */
    public static function uploadImage(UploadedFile $file, string $folder = 'general'): string
    {
        if (config('app.env') === 'production' && config('cloudinary.cloud_name')) {
            try {
                $result = cloudinary()->upload($file->getRealPath(), [
                    'folder'         => 'digital-marketplace/' . $folder,
                    'resource_type'  => 'image',
                    'transformation' => [['quality' => 'auto', 'fetch_format' => 'auto']],
                ]);
                return $result->getSecurePath();
            } catch (\Exception $e) {
                \Log::error('Cloudinary upload failed: ' . $e->getMessage());
                // Fallback vers stockage local si Cloudinary échoue
            }
        }

        // Développement local ou fallback
        $path = $file->store($folder, 'public');
        return Storage::url($path);
    }

    /**
     * Upload un fichier quelconque (PDF, ZIP, etc.) vers Cloudinary ou local
     * Retourne l'URL publique
     */
    public static function uploadFile(UploadedFile $file, string $folder = 'files'): array
    {
        if (config('app.env') === 'production' && config('cloudinary.cloud_name')) {
            $result = cloudinary()->uploadFile($file->getRealPath(), [
                'folder'        => 'digital-marketplace/' . $folder,
                'resource_type' => 'raw',
                'public_id'     => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                                   . '_' . time(),
            ]);
            return [
                'path' => $result->getPublicId(),
                'url'  => $result->getSecurePath(),
            ];
        }

        // Développement local
        $path = $file->store($folder, 'public');
        return [
            'path' => $path,
            'url'  => Storage::url($path),
        ];
    }

    /**
     * Supprimer un fichier (Cloudinary ou local)
     */
    public static function delete(string $pathOrPublicId): void
    {
        if (config('app.env') === 'production' && config('cloudinary.cloud_name')) {
            try {
                cloudinary()->destroy($pathOrPublicId);
            } catch (\Exception $e) {
                // Silencieux si le fichier n'existe plus
            }
            return;
        }

        Storage::disk('public')->delete($pathOrPublicId);
    }
}
