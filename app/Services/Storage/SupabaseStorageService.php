<?php

namespace App\Services\Storage;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseStorageService
{
    public function upload(UploadedFile $file, string $objectPath): string
    {
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        $bucket = (string) config('services.supabase.storage_bucket', 'handwriting-submissions');
        $secretKey = (string) config('services.supabase.secret_key');

        if ($baseUrl === '' || $secretKey === '' || $bucket === '') {
            Log::error('Supabase Storage is not configured.', [
                'bucket' => $bucket,
            ]);

            throw new SupabaseStorageException(status: 503);
        }

        $encodedPath = collect(explode('/', trim($objectPath, '/')))
            ->map(fn (string $segment): string => rawurlencode($segment))
            ->implode('/');
        $endpoint = sprintf(
            '%s/storage/v1/object/%s/%s',
            $baseUrl,
            rawurlencode($bucket),
            $encodedPath,
        );

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$secretKey}",
                    'apikey' => $secretKey,
                    'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                    'x-upsert' => 'false',
                ])
                ->withBody($file->get(), $file->getMimeType() ?: 'application/octet-stream')
                ->post($endpoint);
        } catch (ConnectionException|RequestException $exception) {
            Log::error('Supabase Storage upload request failed.', [
                'bucket' => $bucket,
                'path' => $objectPath,
                'exception' => get_class($exception),
            ]);

            throw new SupabaseStorageException(status: 503, previous: $exception);
        }

        if (! $response->successful()) {
            $status = match (true) {
                $response->status() === 409 => 409,
                in_array($response->status(), [408, 429], true) || $response->serverError() => 503,
                default => 502,
            };

            Log::error('Supabase Storage upload was rejected.', [
                'bucket' => $bucket,
                'path' => $objectPath,
                'status' => $response->status(),
            ]);

            throw new SupabaseStorageException(status: $status);
        }

        return $objectPath;
    }

    public function delete(string $objectPath): void
    {
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        $bucket = (string) config('services.supabase.storage_bucket', 'handwriting-submissions');
        $secretKey = (string) config('services.supabase.secret_key');

        if ($baseUrl === '' || $secretKey === '' || $bucket === '') {
            Log::error('Supabase Storage cleanup is not configured.', [
                'bucket' => $bucket,
                'path' => $objectPath,
            ]);

            throw new SupabaseStorageException(status: 503);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$secretKey}",
                    'apikey' => $secretKey,
                ])
                ->delete($this->objectEndpoint($baseUrl, $bucket, $objectPath));
        } catch (ConnectionException|RequestException $exception) {
            Log::error('Supabase Storage cleanup request failed.', [
                'bucket' => $bucket,
                'path' => $objectPath,
                'exception' => get_class($exception),
            ]);

            throw new SupabaseStorageException(status: 503, previous: $exception);
        }

        if (! $response->successful()) {
            Log::error('Supabase Storage cleanup was rejected.', [
                'bucket' => $bucket,
                'path' => $objectPath,
                'status' => $response->status(),
            ]);

            throw new SupabaseStorageException(status: 502);
        }
    }

    /** @return array{bytes: string, mime_type: string} */
    public function retrieve(string $objectPath): array
    {
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        $bucket = (string) config('services.supabase.storage_bucket', 'handwriting-submissions');
        $secretKey = (string) config('services.supabase.secret_key');

        if ($baseUrl === '' || $secretKey === '' || $bucket === '') {
            Log::error('Supabase Storage retrieval is not configured.', [
                'bucket' => $bucket,
                'path' => $objectPath,
            ]);

            throw new SupabaseStorageException(status: 503);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$secretKey}",
                    'apikey' => $secretKey,
                ])
                ->get($this->objectEndpoint($baseUrl, $bucket, $objectPath));
        } catch (ConnectionException|RequestException $exception) {
            Log::error('Supabase Storage retrieval request failed.', [
                'bucket' => $bucket,
                'path' => $objectPath,
                'exception' => get_class($exception),
            ]);

            throw new SupabaseStorageException(status: 503, previous: $exception);
        }

        if (! $response->successful()) {
            Log::error('Supabase Storage retrieval was rejected.', [
                'bucket' => $bucket,
                'path' => $objectPath,
                'status' => $response->status(),
            ]);

            throw new SupabaseStorageException(status: $response->serverError() ? 503 : 502);
        }

        return [
            'bytes' => $response->body(),
            'mime_type' => $response->header('Content-Type') ?: 'application/octet-stream',
        ];
    }

    private function objectEndpoint(string $baseUrl, string $bucket, string $objectPath): string
    {
        $encodedPath = collect(explode('/', trim($objectPath, '/')))
            ->map(fn (string $segment): string => rawurlencode($segment))
            ->implode('/');

        return sprintf(
            '%s/storage/v1/object/%s/%s',
            $baseUrl,
            rawurlencode($bucket),
            $encodedPath,
        );
    }
}
