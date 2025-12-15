<?php

namespace App\Services;

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

/**
 * Extracts clean article content from HTML using php-readability.
 */
class ArticleExtractor
{
    /**
     * Extract article content from HTML.
     *
     * @return array{text: string, html: string, word_count: int, reading_time: int, images: array}
     */
    public function extract(string $html, string $url): array
    {
        $result = [
            'text' => '',
            'html' => '',
            'word_count' => 0,
            'reading_time' => 0,
            'images' => [],
        ];

        try {
            $configuration = new Configuration();
            $configuration
                ->setFixRelativeURLs(true)
                ->setOriginalURL($url)
                ->setSummonCthulhu(true) // Use more aggressive parsing
                ->setCleanConditionally(true);

            $readability = new Readability($configuration);
            $readability->parse($html);

            // Get article content
            $result['html'] = $readability->getContent() ?? '';
            $result['text'] = $this->htmlToText($result['html']);
            $result['word_count'] = $this->countWords($result['text']);
            $result['reading_time'] = $this->calculateReadingTime($result['word_count']);

            // Extract images from article HTML
            $result['images'] = $this->extractImages($result['html'], $url);

        } catch (ParseException $e) {
            Log::warning('Readability parse failed, using fallback', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            // Fallback: try to extract content manually
            $result = $this->fallbackExtract($html, $url);
        }

        return $result;
    }

    /**
     * Convert HTML to plain text.
     */
    private function htmlToText(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove script and style tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);

        // Convert to text
        $text = strip_tags($html);
        
        // Clean up whitespace
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Count words in text.
     */
    private function countWords(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Handle multiple languages including CJK
        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }

    /**
     * Calculate reading time in minutes.
     */
    private function calculateReadingTime(int $wordCount): int
    {
        // Average reading speed: 225 words per minute
        return max(1, (int) ceil($wordCount / 225));
    }

    /**
     * Extract all images from article HTML.
     *
     * @return array<array{url: string, alt: string|null}>
     */
    private function extractImages(string $html, string $baseUrl): array
    {
        if (empty($html)) {
            return [];
        }

        $images = [];
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        
        $imgTags = $dom->getElementsByTagName('img');
        $seenUrls = [];

        foreach ($imgTags as $img) {
            $src = $img->getAttribute('src');
            
            // Skip data URIs and empty sources
            if (empty($src) || str_starts_with($src, 'data:')) {
                continue;
            }

            // Make absolute URL
            $absoluteUrl = $this->makeAbsoluteUrl($src, $baseUrl);

            // Skip duplicates
            if (isset($seenUrls[$absoluteUrl])) {
                continue;
            }
            $seenUrls[$absoluteUrl] = true;

            $images[] = [
                'url' => $absoluteUrl,
                'alt' => $img->getAttribute('alt') ?: null,
            ];

            // Also check srcset for additional images
            $srcset = $img->getAttribute('srcset');
            if ($srcset) {
                $srcsetImages = $this->parseSrcset($srcset, $baseUrl);
                foreach ($srcsetImages as $srcsetUrl) {
                    if (!isset($seenUrls[$srcsetUrl])) {
                        $seenUrls[$srcsetUrl] = true;
                        $images[] = [
                            'url' => $srcsetUrl,
                            'alt' => $img->getAttribute('alt') ?: null,
                        ];
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Parse srcset attribute.
     */
    private function parseSrcset(string $srcset, string $baseUrl): array
    {
        $urls = [];
        $parts = explode(',', $srcset);

        foreach ($parts as $part) {
            $part = trim($part);
            $segments = preg_split('/\s+/', $part);
            
            if (!empty($segments[0]) && !str_starts_with($segments[0], 'data:')) {
                $urls[] = $this->makeAbsoluteUrl($segments[0], $baseUrl);
            }
        }

        return $urls;
    }

    /**
     * Fallback extraction when Readability fails.
     */
    private function fallbackExtract(string $html, string $url): array
    {
        $result = [
            'text' => '',
            'html' => '',
            'word_count' => 0,
            'reading_time' => 0,
            'images' => [],
        ];

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        // Try to find main content areas
        $contentSelectors = [
            "//article",
            "//*[@role='main']",
            "//*[contains(@class, 'article')]",
            "//*[contains(@class, 'post-content')]",
            "//*[contains(@class, 'entry-content')]",
            "//*[contains(@class, 'content')]",
            "//main",
            "//body",
        ];

        foreach ($contentSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                $result['html'] = $dom->saveHTML($node);
                break;
            }
        }

        // Clean HTML
        $result['html'] = $this->cleanHtml($result['html']);
        $result['text'] = $this->htmlToText($result['html']);
        $result['word_count'] = $this->countWords($result['text']);
        $result['reading_time'] = $this->calculateReadingTime($result['word_count']);
        $result['images'] = $this->extractImages($result['html'], $url);

        return $result;
    }

    /**
     * Clean HTML content.
     */
    private function cleanHtml(string $html): string
    {
        // Remove scripts
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        
        // Remove styles
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        
        // Remove nav elements
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html);
        
        // Remove footer
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html);
        
        // Remove aside
        $html = preg_replace('/<aside[^>]*>.*?<\/aside>/si', '', $html);

        // Remove common ad divs
        $html = preg_replace('/<div[^>]*class="[^"]*ad[^"]*"[^>]*>.*?<\/div>/si', '', $html);

        return $html;
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
