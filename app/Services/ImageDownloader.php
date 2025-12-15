<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Downloads and saves images from article content.
 */
class ImageDownloader
{
    private const MAX_CONCURRENT = 20;
    private const MAX_SIZE = 10485760; // 10MB
    private const TIMEOUT = 15;
    private const VALID_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    /**
     * Download multiple images.
     *
     * @param array<array{url: string, alt: string|null}> $images
     * @return array{map: array<string, string>, downloaded: array}
     */
    public function downloadImages(array $images, string $directory, string $baseUrl): array
    {
        $map = [];
        $downloaded = [];
        $index = 0;

        // Process in batches for concurrent downloads
        $batches = array_chunk($images, self::MAX_CONCURRENT);

        foreach ($batches as $batch) {
            $multiCurl = curl_multi_init();
            $curlHandles = [];

            foreach ($batch as $imageData) {
                $url = $imageData['url'];
                $absoluteUrl = $this->makeAbsoluteUrl($url, $baseUrl);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $absoluteUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_TIMEOUT => self::TIMEOUT,
                    CURLOPT_USERAGENT => self::USER_AGENT,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER => [
                        'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                    ],
                ]);

                curl_multi_add_handle($multiCurl, $ch);
                $curlHandles[] = [
                    'handle' => $ch,
                    'original_url' => $url,
                    'absolute_url' => $absoluteUrl,
                    'alt' => $imageData['alt'] ?? null,
                    'index' => $index++,
                ];
            }

            // Execute multi curl
            $running = null;
            do {
                curl_multi_exec($multiCurl, $running);
                curl_multi_select($multiCurl);
            } while ($running > 0);

            // Process results
            foreach ($curlHandles as $handleData) {
                $ch = $handleData['handle'];
                $content = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

                curl_multi_remove_handle($multiCurl, $ch);
                curl_close($ch);

                // Validate response
                if ($httpCode !== 200 || empty($content)) {
                    continue;
                }

                // Check content length
                if ($contentLength > self::MAX_SIZE || strlen($content) > self::MAX_SIZE) {
                    Log::info('Skipping large image', ['url' => $handleData['absolute_url']]);
                    continue;
                }

                // Validate MIME type
                $mimeType = $this->detectMimeType($content, $contentType);
                if (!in_array($mimeType, self::VALID_TYPES)) {
                    continue;
                }

                // Generate filename
                $extension = $this->getExtension($mimeType);
                $fileName = sprintf('img_%03d.%s', $handleData['index'] + 1, $extension);
                $localPath = $directory . '/' . $fileName;

                // Save image
                if (Storage::put($localPath, $content)) {
                    // Get dimensions
                    $dimensions = $this->getImageDimensions($content);

                    $imageInfo = [
                        'original_url' => $handleData['original_url'],
                        'local_path' => $localPath,
                        'file_name' => $fileName,
                        'file_size' => strlen($content),
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'mime_type' => $mimeType,
                        'alt_text' => $handleData['alt'],
                    ];

                    $map[$handleData['original_url']] = $localPath;
                    $map[$handleData['absolute_url']] = $localPath;
                    $downloaded[] = $imageInfo;
                }
            }

            curl_multi_close($multiCurl);
        }

        return [
            'map' => $map,
            'downloaded' => $downloaded,
        ];
    }

    /**
     * Download a single image.
     */
    public function downloadSingle(string $url, string $directory, string $filename, string $baseUrl): ?string
    {
        $absoluteUrl = $this->makeAbsoluteUrl($url, $baseUrl);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $absoluteUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content)) {
            return null;
        }

        if (strlen($content) > self::MAX_SIZE) {
            return null;
        }

        $mimeType = $this->detectMimeType($content, $contentType);
        if (!in_array($mimeType, self::VALID_TYPES)) {
            return null;
        }

        $extension = $this->getExtension($mimeType);
        $fullFilename = $filename . '.' . $extension;
        $localPath = $directory . '/' . $fullFilename;

        if (Storage::put($localPath, $content)) {
            return $localPath;
        }

        return null;
    }

    /**
     * Detect MIME type from content.
     */
    private function detectMimeType(string $content, ?string $headerType): string
    {
        // Try to detect from magic bytes
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($content);

        if ($detected && in_array($detected, self::VALID_TYPES)) {
            return $detected;
        }

        // Fall back to header
        if ($headerType) {
            $parts = explode(';', $headerType);
            $type = trim($parts[0]);
            if (in_array($type, self::VALID_TYPES)) {
                return $type;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Get file extension from MIME type.
     */
    private function getExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Get image dimensions.
     */
    private function getImageDimensions(string $content): array
    {
        $dimensions = ['width' => null, 'height' => null];

        // Create temp file to use getimagesize
        $temp = tmpfile();
        if ($temp) {
            fwrite($temp, $content);
            $path = stream_get_meta_data($temp)['uri'];
            
            $size = @getimagesize($path);
            if ($size) {
                $dimensions['width'] = $size[0];
                $dimensions['height'] = $size[1];
            }
            
            fclose($temp);
        }

        return $dimensions;
    }

    /**
     * Make URL absolute.
     */
    private function makeAbsoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $base = $scheme . '://' . $host;

        if (str_starts_with($url, '//')) {
            return $scheme . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $base . $url;
        }

        $path = dirname($parsed['path'] ?? '/');
        return $base . rtrim($path, '/') . '/' . $url;
    }
}
