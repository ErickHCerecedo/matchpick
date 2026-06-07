<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private Client $http;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name');
        $this->apiKey    = config('services.cloudinary.api_key');
        $this->apiSecret = config('services.cloudinary.api_secret');
        $this->http      = new Client();
    }

    public function upload(UploadedFile $file, string $publicId, string $folder = 'Matchpick/usuarios'): string
    {
        $timestamp = time();
        $params    = [
            'folder'    => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        ksort($params);
        $paramString = implode('&', array_map(
            fn ($k, $v) => "{$k}={$v}",
            array_keys($params),
            $params
        ));
        $signature = sha1($paramString . $this->apiSecret);

        $response = $this->http->post(
            "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload",
            [
                'multipart' => [
                    ['name' => 'file',      'contents' => fopen($file->getRealPath(), 'r'), 'filename' => $file->getClientOriginalName()],
                    ['name' => 'api_key',   'contents' => $this->apiKey],
                    ['name' => 'timestamp', 'contents' => (string) $timestamp],
                    ['name' => 'folder',    'contents' => $folder],
                    ['name' => 'public_id', 'contents' => $publicId],
                    ['name' => 'signature', 'contents' => $signature],
                ],
            ]
        );

        $body = json_decode($response->getBody()->getContents(), true);
        return $body['secure_url'];
    }

    public function delete(string $publicId): void
    {
        $timestamp   = time();
        $paramString = "public_id={$publicId}&timestamp={$timestamp}";
        $signature   = sha1($paramString . $this->apiSecret);

        $this->http->post(
            "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy",
            [
                'form_params' => [
                    'public_id' => $publicId,
                    'api_key'   => $this->apiKey,
                    'timestamp' => (string) $timestamp,
                    'signature' => $signature,
                ],
            ]
        );
    }

    /**
     * Extracts the Cloudinary public_id from a secure_url.
     * Example URL: https://res.cloudinary.com/cloud/image/upload/v123/Matchpick/usuarios/john_1.jpg
     * Returns: Matchpick/usuarios/john_1
     */
    public function extractPublicId(string $url): ?string
    {
        if (!str_contains($url, 'cloudinary.com')) {
            return null;
        }

        if (preg_match('#/upload/(?:v\d+/)?(.+?)(?:\.[a-zA-Z0-9]+)?$#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
