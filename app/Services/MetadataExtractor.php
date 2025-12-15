<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;

/**
 * Extracts metadata from HTML pages (OpenGraph, Twitter Cards, meta tags, JSON-LD).
 */
class MetadataExtractor
{
    /**
     * Extract all metadata from HTML.
     *
     * @return array<string, mixed>
     */
    public function extract(string $html, string $url): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);

        $metadata = [
            'title' => $this->extractTitle($xpath, $dom),
            'description' => $this->extractDescription($xpath),
            'author' => $this->extractAuthor($xpath),
            'published_date' => $this->extractPublishedDate($xpath),
            'modified_date' => $this->extractModifiedDate($xpath),
            'language' => $this->extractLanguage($xpath, $dom),
            'keywords' => $this->extractKeywords($xpath),
            'favicon' => $this->extractFavicon($xpath, $url),
            'site_name' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_type' => null,
            'og_url' => null,
            'twitter_card' => null,
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'canonical_url' => $this->extractCanonicalUrl($xpath),
        ];

        // Extract OpenGraph tags
        $ogTags = $this->extractOpenGraph($xpath);
        $metadata = array_merge($metadata, $ogTags);

        // Extract Twitter Card tags
        $twitterTags = $this->extractTwitterCard($xpath);
        $metadata = array_merge($metadata, $twitterTags);

        // Extract JSON-LD structured data
        $jsonLd = $this->extractJsonLd($xpath);
        if ($jsonLd) {
            $metadata = $this->mergeJsonLd($metadata, $jsonLd);
        }

        return $metadata;
    }

    /**
     * Extract page title.
     */
    private function extractTitle(DOMXPath $xpath, DOMDocument $dom): ?string
    {
        // Try og:title first
        $ogTitle = $xpath->query("//meta[@property='og:title']/@content");
        if ($ogTitle->length > 0) {
            return trim($ogTitle->item(0)->nodeValue);
        }

        // Then regular title tag
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            return trim($titles->item(0)->nodeValue);
        }

        return null;
    }

    /**
     * Extract page description.
     */
    private function extractDescription(DOMXPath $xpath): ?string
    {
        // Try og:description first
        $ogDesc = $xpath->query("//meta[@property='og:description']/@content");
        if ($ogDesc->length > 0) {
            return trim($ogDesc->item(0)->nodeValue);
        }

        // Then meta description
        $metaDesc = $xpath->query("//meta[@name='description']/@content");
        if ($metaDesc->length > 0) {
            return trim($metaDesc->item(0)->nodeValue);
        }

        return null;
    }

    /**
     * Extract author name.
     */
    private function extractAuthor(DOMXPath $xpath): ?string
    {
        $queries = [
            "//meta[@name='author']/@content",
            "//meta[@property='article:author']/@content",
            "//meta[@name='dc.creator']/@content",
            "//a[@rel='author']/text()",
            "//*[@itemprop='author']//*[@itemprop='name']/text()",
            "//*[@itemprop='author']/text()",
        ];

        foreach ($queries as $query) {
            $result = $xpath->query($query);
            if ($result->length > 0) {
                $value = trim($result->item(0)->nodeValue);
                if (!empty($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Extract published date.
     */
    private function extractPublishedDate(DOMXPath $xpath): ?Carbon
    {
        $queries = [
            "//meta[@property='article:published_time']/@content",
            "//meta[@name='pubdate']/@content",
            "//meta[@name='publishdate']/@content",
            "//meta[@name='dc.date']/@content",
            "//time[@itemprop='datePublished']/@datetime",
            "//time[@pubdate]/@datetime",
        ];

        foreach ($queries as $query) {
            $result = $xpath->query($query);
            if ($result->length > 0) {
                $value = trim($result->item(0)->nodeValue);
                try {
                    return Carbon::parse($value);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract modified date.
     */
    private function extractModifiedDate(DOMXPath $xpath): ?Carbon
    {
        $queries = [
            "//meta[@property='article:modified_time']/@content",
            "//meta[@name='lastmod']/@content",
            "//time[@itemprop='dateModified']/@datetime",
        ];

        foreach ($queries as $query) {
            $result = $xpath->query($query);
            if ($result->length > 0) {
                $value = trim($result->item(0)->nodeValue);
                try {
                    return Carbon::parse($value);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract page language.
     */
    private function extractLanguage(DOMXPath $xpath, DOMDocument $dom): ?string
    {
        // Check html lang attribute
        $htmlTags = $dom->getElementsByTagName('html');
        if ($htmlTags->length > 0) {
            $lang = $htmlTags->item(0)->getAttribute('lang');
            if (!empty($lang)) {
                return substr($lang, 0, 10);
            }
        }

        // Check meta tags
        $metaLang = $xpath->query("//meta[@http-equiv='content-language']/@content");
        if ($metaLang->length > 0) {
            return substr(trim($metaLang->item(0)->nodeValue), 0, 10);
        }

        return null;
    }

    /**
     * Extract keywords.
     */
    private function extractKeywords(DOMXPath $xpath): array
    {
        $result = $xpath->query("//meta[@name='keywords']/@content");
        if ($result->length > 0) {
            $keywords = trim($result->item(0)->nodeValue);
            return array_map('trim', explode(',', $keywords));
        }

        return [];
    }

    /**
     * Extract favicon URL.
     */
    private function extractFavicon(DOMXPath $xpath, string $baseUrl): ?string
    {
        $queries = [
            "//link[@rel='icon']/@href",
            "//link[@rel='shortcut icon']/@href",
            "//link[@rel='apple-touch-icon']/@href",
            "//link[@rel='apple-touch-icon-precomposed']/@href",
        ];

        foreach ($queries as $query) {
            $result = $xpath->query($query);
            if ($result->length > 0) {
                $href = trim($result->item(0)->nodeValue);
                if (!empty($href)) {
                    return $this->makeAbsoluteUrl($href, $baseUrl);
                }
            }
        }

        // Default to /favicon.ico
        $parsed = parse_url($baseUrl);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/favicon.ico';
    }

    /**
     * Extract canonical URL.
     */
    private function extractCanonicalUrl(DOMXPath $xpath): ?string
    {
        $result = $xpath->query("//link[@rel='canonical']/@href");
        if ($result->length > 0) {
            return trim($result->item(0)->nodeValue);
        }

        return null;
    }

    /**
     * Extract OpenGraph tags.
     */
    private function extractOpenGraph(DOMXPath $xpath): array
    {
        $ogTags = [];
        $mappings = [
            'og:site_name' => 'site_name',
            'og:title' => 'og_title',
            'og:description' => 'og_description',
            'og:image' => 'og_image',
            'og:type' => 'og_type',
            'og:url' => 'og_url',
        ];

        foreach ($mappings as $property => $key) {
            $result = $xpath->query("//meta[@property='{$property}']/@content");
            if ($result->length > 0) {
                $ogTags[$key] = trim($result->item(0)->nodeValue);
            }
        }

        return $ogTags;
    }

    /**
     * Extract Twitter Card tags.
     */
    private function extractTwitterCard(DOMXPath $xpath): array
    {
        $twitterTags = [];
        $mappings = [
            'twitter:card' => 'twitter_card',
            'twitter:title' => 'twitter_title',
            'twitter:description' => 'twitter_description',
            'twitter:image' => 'twitter_image',
        ];

        foreach ($mappings as $name => $key) {
            $result = $xpath->query("//meta[@name='{$name}']/@content");
            if ($result->length > 0) {
                $twitterTags[$key] = trim($result->item(0)->nodeValue);
            }
        }

        return $twitterTags;
    }

    /**
     * Extract JSON-LD structured data.
     */
    private function extractJsonLd(DOMXPath $xpath): ?array
    {
        $scripts = $xpath->query("//script[@type='application/ld+json']");
        
        foreach ($scripts as $script) {
            $json = trim($script->nodeValue);
            try {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                
                // Handle array of JSON-LD objects
                if (isset($data[0])) {
                    foreach ($data as $item) {
                        if ($this->isArticleType($item)) {
                            return $item;
                        }
                    }
                    return $data[0];
                }
                
                // Handle @graph structure
                if (isset($data['@graph'])) {
                    foreach ($data['@graph'] as $item) {
                        if ($this->isArticleType($item)) {
                            return $item;
                        }
                    }
                }
                
                return $data;
            } catch (\JsonException $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Check if JSON-LD is article type.
     */
    private function isArticleType(array $data): bool
    {
        $articleTypes = ['Article', 'NewsArticle', 'BlogPosting', 'WebPage'];
        $type = $data['@type'] ?? '';
        
        if (is_array($type)) {
            return count(array_intersect($type, $articleTypes)) > 0;
        }
        
        return in_array($type, $articleTypes);
    }

    /**
     * Merge JSON-LD data into metadata.
     */
    private function mergeJsonLd(array $metadata, array $jsonLd): array
    {
        // Author
        if (empty($metadata['author']) && isset($jsonLd['author'])) {
            $author = $jsonLd['author'];
            if (is_array($author)) {
                $metadata['author'] = $author['name'] ?? ($author[0]['name'] ?? null);
            } else {
                $metadata['author'] = $author;
            }
        }

        // Published date
        if (empty($metadata['published_date']) && isset($jsonLd['datePublished'])) {
            try {
                $metadata['published_date'] = Carbon::parse($jsonLd['datePublished']);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Description
        if (empty($metadata['description']) && isset($jsonLd['description'])) {
            $metadata['description'] = $jsonLd['description'];
        }

        // Site name
        if (empty($metadata['site_name']) && isset($jsonLd['publisher']['name'])) {
            $metadata['site_name'] = $jsonLd['publisher']['name'];
        }

        return $metadata;
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
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        if (str_starts_with($url, '//')) {
            return ($parsed['scheme'] ?? 'https') . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $base . $url;
        }

        return $base . '/' . $url;
    }
}
