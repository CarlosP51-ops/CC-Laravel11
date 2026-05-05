<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    private static function url(): ?string   { return env('SUPABASE_URL'); }
    private static function key(): ?string   { return env('SUPABASE_ANON_KEY'); }
    private static function bucket(): string { return env('SUPABASE_BUCKET', 'media'); }
    private static function privateBucket(): string { return env('SUPABASE_PRIVATE_BUCKET', 'private-files'); }

    private static function isProd(): bool
    {
        return config('app.env') === 'production' && self::url();
    }

    // ── Upload image publique ─────────────────────────────────────────────────
    public static function uploadImage(UploadedFile $file, string $folder = 'general'): string
    {
        if (self::isProd()) {
            return self::supabaseUpload($file, $folder, self::bucket());
        }
        $path = $file->store($folder, 'public');
        return Storage::url($path);
    }

    // ── Upload fichier privé (produits digitaux) ──────────────────────────────
    public static function uploadFile(UploadedFile $file, string $folder = 'files'): array
    {
        if (self::isProd()) {
            $path = $folder . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            self::supabaseUploadRaw($file, $path, self::privateBucket());
            return ['path' => $path, 'url' => null]; // URL générée à la demande
        }
        $path = $file->store($folder, 'private');
        return ['path' => $path, 'url' => null];
    }

    // ── Générer URL signée pour téléchargement (60 min) ───────────────────────
    public static function signedUrl(string $path, int $expiresIn = 3600): ?string
    {
        if (self::isProd()) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . self::key(),
                'apikey'        => self::key(),
            ])->post(self::url() . '/storage/v1/object/sign/' . self::privateBucket() . '/' . $path, [
                'expiresIn' => $expiresIn,
            ]);

            if ($response->successful()) {
                return self::url() . '/storage/v1' . $response->json('signedURL');
            }
            return null;
        }
        // Local : retourner null (on sert le fichier directement)
        return null;
    }

    // ── Supprimer un fichier ──────────────────────────────────────────────────
    public static function delete(string $pathOrUrl): void
    {
        if (self::isProd()) {
            $path = self::extractPath($pathOrUrl, self::bucket());
            if ($path) {
                Http::withHeaders([
                    'Authorization' => 'Bearer ' . self::key(),
                    'apikey'        => self::key(),
                ])->delete(self::url() . '/storage/v1/object/' . self::bucket() . '/' . $path);
            }
            return;
        }
        Storage::disk('public')->delete($pathOrUrl);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private static function supabaseUpload(UploadedFile $file, string $folder, string $bucket): string
    {
        $ext      = $file->getClientOriginalExtension();
        $filename = $folder . '/' . Str::uuid() . '.' . $ext;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . self::key(),
            'apikey'        => self::key(),
            'Content-Type'  => $file->getMimeType(),
            'x-upsert'      => 'true',
        ])->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
          ->post(self::url() . '/storage/v1/object/' . $bucket . '/' . $filename);

        if ($response->successful()) {
            return self::url() . '/storage/v1/object/public/' . $bucket . '/' . $filename;
        }

        \Log::error('Supabase upload failed: ' . $response->body());
        // Fallback local
        $path = $file->store(explode('/', $folder)[0], 'public');
        return Storage::url($path);
    }

    private static function supabaseUploadRaw(UploadedFile $file, string $path, string $bucket): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . self::key(),
            'apikey'        => self::key(),
            'Content-Type'  => $file->getMimeType(),
            'x-upsert'      => 'true',
        ])->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
          ->post(self::url() . '/storage/v1/object/' . $bucket . '/' . $path);

        if (!$response->successful()) {
            \Log::error('Supabase private upload failed: ' . $response->body());
            throw new \Exception('Échec de l\'upload du fichier.');
        }
    }

    private static function extractPath(string $url, string $bucket): ?string
    {
        $base = self::url() . '/storage/v1/object/public/' . $bucket . '/';
        if (str_starts_with($url, $base)) {
            return substr($url, strlen($base));
        }
        return null;
    }
}
