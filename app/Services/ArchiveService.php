<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\BookmarkArchive;
use App\Models\BookmarkImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for archiving bookmarks - extracts content, images, and metadata.
 */
class ArchiveService
{
    private const MAX_CONCURRENT_IMAGES = 20;
    private const MAX_IMAGE_SIZE = 10485760; // 10MB
    private const REQUEST_TIMEOUT = 30;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function __construct(
        private MetadataExtractor $metadataExtractor,
        private ImageDownloader $imageDownloader,
        private ArticleExtractor $articleExtractor,
    ) {}

    /**
     * Archive a bookmark - main entry point.
     *
     * @return array{success: bool, image_count: int, word_count: int, error?: string}
     */
    public function archiveBookmark(Bookmark $bookmark): array
    {
        try {
            $bookmark->markArchiveProcessing();

            // Fetch the webpage
            $html = $this->fetchWebpage($bookmark->url);
            if (!$html) {
                throw new \Exception('Failed to fetch webpage');
            }

            // Extract metadata
            $metadata = $this->metadataExtractor->extract($html, $bookmark->url);

            // Extract article content
            $article = $this->articleExtractor->extract($html, $bookmark->url);

            // Setup storage directory
            $storageDir = $bookmark->getArchiveStoragePath();
            $imagesDir = $storageDir . '/images';
            Storage::makeDirectory($imagesDir);

            // Download images from article
            $imageMap = [];
            $downloadedImages = [];
            if (!empty($article['images'])) {
                $result = $this->imageDownloader->downloadImages(
                    $article['images'],
                    $imagesDir,
                    $bookmark->url
                );
                $imageMap = $result['map'];
                $downloadedImages = $result['downloaded'];
            }

            // Update image URLs in article HTML
            $localizedHtml = $article['html'];
            if (!empty($imageMap)) {
                $localizedHtml = $this->localizeImageUrls($article['html'], $imageMap);
            }

            // Save HTML snapshot (compressed)
            $snapshotPath = $storageDir . '/snapshot.html.gz';
            Storage::put($snapshotPath, gzencode($html, 9));

            // Download and save favicon
            $faviconPath = null;
            if (!empty($metadata['favicon'])) {
                $faviconPath = $this->downloadFavicon($metadata['favicon'], $storageDir, $bookmark->url);
                if ($faviconPath) {
                    $bookmark->favicon_path = $faviconPath;
                    $bookmark->save();
                }
            }

            // Download OG image
            $ogImagePath = null;
            if (!empty($metadata['og_image'])) {
                $ogImagePath = $this->imageDownloader->downloadSingle(
                    $metadata['og_image'],
                    $storageDir,
                    'og_image',
                    $bookmark->url
                );
            }

            // Create archive record
            $archive = BookmarkArchive::updateOrCreate(
                ['bookmark_id' => $bookmark->id],
                [
                    'article_text' => $article['text'],
                    'article_html' => $localizedHtml,
                    'html_snapshot_path' => $snapshotPath,
                    'author' => $metadata['author'],
                    'published_date' => $metadata['published_date'],
                    'word_count' => $article['word_count'],
                    'reading_time_minutes' => BookmarkArchive::calculateReadingTime($article['word_count']),
                    'language' => $metadata['language'],
                    'site_name' => $metadata['site_name'],
                    'og_title' => $metadata['og_title'],
                    'og_description' => $metadata['og_description'],
                    'og_image_path' => $ogImagePath,
                    'images_directory' => $imagesDir,
                    'image_count' => count($downloadedImages),
                    'primary_image_path' => $downloadedImages[0]['local_path'] ?? $ogImagePath,
                    'metadata' => $metadata,
                    'archived_at' => now(),
                ]
            );

            // Save image records
            foreach ($downloadedImages as $index => $imageData) {
                BookmarkImage::create([
                    'archive_id' => $archive->id,
                    'original_url' => $imageData['original_url'],
                    'local_path' => $imageData['local_path'],
                    'file_name' => $imageData['file_name'],
                    'file_size' => $imageData['file_size'],
                    'width' => $imageData['width'],
                    'height' => $imageData['height'],
                    'mime_type' => $imageData['mime_type'],
                    'alt_text' => $imageData['alt_text'] ?? null,
                    'is_primary' => $index === 0,
                    'position' => $index,
                ]);
            }

            $bookmark->markArchiveCompleted();

            return [
                'success' => true,
                'image_count' => count($downloadedImages),
                'word_count' => $article['word_count'],
            ];

        } catch (\Exception $e) {
            Log::error('Archive failed', [
                'bookmark_id' => $bookmark->id,
                'url' => $bookmark->url,
                'error' => $e->getMessage(),
            ]);

            $bookmark->markArchiveFailed();

            return [
                'success' => false,
                'image_count' => 0,
                'word_count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch webpage HTML using cURL.
     */
    private function fetchWebpage(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400 && $html) {
            return $html;
        }

        return null;
    }

    /**
     * Update image URLs in HTML to use local paths.
     */
    private function localizeImageUrls(string $html, array $imageMap): string
    {
        if (empty($html) || empty($imageMap)) {
            return $html;
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if (isset($imageMap[$src])) {
                $img->setAttribute('src', '/storage/' . $imageMap[$src]);
            }

            // Handle srcset
            $srcset = $img->getAttribute('srcset');
            if ($srcset) {
                $newSrcset = $this->localizeSrcset($srcset, $imageMap);
                $img->setAttribute('srcset', $newSrcset);
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Localize srcset attribute.
     */
    private function localizeSrcset(string $srcset, array $imageMap): string
    {
        $parts = explode(',', $srcset);
        $newParts = [];

        foreach ($parts as $part) {
            $part = trim($part);
            $segments = preg_split('/\s+/', $part);
            if (!empty($segments[0]) && isset($imageMap[$segments[0]])) {
                $segments[0] = '/storage/' . $imageMap[$segments[0]];
            }
            $newParts[] = implode(' ', $segments);
        }

        return implode(', ', $newParts);
    }

    /**
     * Download favicon.
     */
    private function downloadFavicon(string $faviconUrl, string $storageDir, string $baseUrl): ?string
    {
        try {
            $absoluteUrl = $this->makeAbsoluteUrl($faviconUrl, $baseUrl);
            return $this->imageDownloader->downloadSingle($absoluteUrl, $storageDir, 'favicon', $baseUrl);
        } catch (\Exception $e) {
            Log::warning('Failed to download favicon', ['url' => $faviconUrl, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Make a URL absolute.
     */
    private function makeAbsoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        $parsed = parse_url($baseUrl);
        $base = $parsed['scheme'] . '://' . $parsed['host'];

        if (str_starts_with($url, '//')) {
            return $parsed['scheme'] . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $base . $url;
        }

        $path = dirname($parsed['path'] ?? '/');
        return $base . $path . '/' . $url;
    }

    /**
     * Re-archive a bookmark (delete old archive and create new).
     */
    public function reArchive(Bookmark $bookmark): array
    {
        // Delete existing archive data
        if ($bookmark->archive) {
            $bookmark->archive->images()->delete();
            $bookmark->archive->delete();
        }

        // Delete stored files
        $storageDir = $bookmark->getArchiveStoragePath();
        Storage::deleteDirectory($storageDir);

        // Archive again
        return $this->archiveBookmark($bookmark);
    }
}
