<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Collection;
use App\Models\ImportJob;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for importing bookmarks from various formats.
 */
class ImportService
{
    /**
     * Process an import job.
     */
    public function processImport(ImportJob $job): void
    {
        $job->markProcessing();

        try {
            $filePath = Storage::path($job->file_path);

            match ($job->file_type) {
                'html' => $this->importHtml($job, $filePath),
                'json' => $this->importJson($job, $filePath),
                'csv' => $this->importCsv($job, $filePath),
                default => throw new \InvalidArgumentException("Unknown file type: {$job->file_type}"),
            };

            $job->markCompleted();

        } catch (\Exception $e) {
            Log::error('Import failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
            $job->markFailed($e->getMessage());
        }
    }

    /**
     * Import from Netscape HTML bookmark format.
     */
    private function importHtml(ImportJob $job, string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read import file');
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);

        // Parse bookmarks
        $bookmarks = $this->parseNetscapeHtml($dom, $job->user_id);
        $job->total_items = count($bookmarks);
        $job->save();

        // Import each bookmark
        foreach ($bookmarks as $bookmarkData) {
            $this->importSingleBookmark($job, $bookmarkData);
        }
    }

    /**
     * Parse Netscape HTML format.
     */
    private function parseNetscapeHtml(\DOMDocument $dom, int $userId): array
    {
        $bookmarks = [];
        $xpath = new \DOMXPath($dom);

        // Get all DT elements (contains either folders or bookmarks)
        $links = $xpath->query('//a[@href]');

        foreach ($links as $link) {
            $url = $link->getAttribute('href');
            
            // Skip javascript and empty URLs
            if (empty($url) || str_starts_with($url, 'javascript:')) {
                continue;
            }

            $bookmarkData = [
                'url' => $url,
                'title' => trim($link->textContent) ?: $url,
                'description' => null,
                'tags' => [],
                'collection_path' => null,
                'created_at' => null,
            ];

            // Get ADD_DATE attribute
            $addDate = $link->getAttribute('add_date');
            if ($addDate && is_numeric($addDate)) {
                $bookmarkData['created_at'] = date('Y-m-d H:i:s', (int) $addDate);
            }

            // Get TAGS attribute (some browsers add this)
            $tags = $link->getAttribute('tags');
            if ($tags) {
                $bookmarkData['tags'] = array_map('trim', explode(',', $tags));
            }

            // Try to find parent folder path
            $bookmarkData['collection_path'] = $this->findParentFolderPath($link);

            $bookmarks[] = $bookmarkData;
        }

        return $bookmarks;
    }

    /**
     * Find parent folder path for a bookmark link.
     */
    private function findParentFolderPath(\DOMElement $link): ?string
    {
        $folders = [];
        $node = $link->parentNode;

        while ($node) {
            if ($node->nodeName === 'dl') {
                // Look for preceding H3 (folder name)
                $prev = $node->previousSibling;
                while ($prev) {
                    if ($prev->nodeName === 'h3') {
                        array_unshift($folders, trim($prev->textContent));
                        break;
                    }
                    $prev = $prev->previousSibling;
                }
            }
            $node = $node->parentNode;
        }

        return !empty($folders) ? implode('/', $folders) : null;
    }

    /**
     * Import from JSON format.
     */
    private function importJson(ImportJob $job, string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read import file');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        // Handle different JSON structures
        $bookmarks = $this->normalizeJsonBookmarks($data);
        $job->total_items = count($bookmarks);
        $job->save();

        foreach ($bookmarks as $bookmarkData) {
            $this->importSingleBookmark($job, $bookmarkData);
        }
    }

    /**
     * Normalize JSON bookmarks from various formats.
     */
    private function normalizeJsonBookmarks(array $data): array
    {
        // Check for Linkwarden export format (has collections array with links inside)
        if (isset($data['collections']) && is_array($data['collections'])) {
            return $this->parseLinkwardenFormat($data);
        }

        // Check if it's an array of bookmarks
        if (isset($data[0]['url']) || isset($data[0]['href'])) {
            return array_map(fn($item) => $this->normalizeJsonItem($item), $data);
        }

        // Check for Pocket export format
        if (isset($data['list'])) {
            return array_map(fn($item) => $this->normalizeJsonItem($item), array_values($data['list']));
        }

        // Check for Raindrop format
        if (isset($data['items'])) {
            return array_map(fn($item) => $this->normalizeJsonItem($item), $data['items']);
        }

        // Single bookmark object
        if (isset($data['url']) || isset($data['href'])) {
            return [$this->normalizeJsonItem($data)];
        }

        return [];
    }

    /**
     * Parse Linkwarden export format.
     * Linkwarden uses parentId to define collection hierarchy, NOT "/" in names.
     */
    private function parseLinkwardenFormat(array $data): array
    {
        $bookmarks = [];

        // Build a mapping of Linkwarden collection IDs to collection names
        // and include the original Linkwarden collection ID for later use
        $collectionMap = [];
        foreach ($data['collections'] ?? [] as $collection) {
            $collectionMap[$collection['id']] = [
                'name' => $collection['name'] ?? 'Imported',
                'parentId' => $collection['parentId'] ?? null,
                'color' => $collection['color'] ?? '#3B82F6',
            ];
        }

        foreach ($data['collections'] ?? [] as $collection) {
            $linkwardenCollectionId = $collection['id'];
            
            foreach ($collection['links'] ?? [] as $link) {
                // Extract tag names from tag objects
                $tags = [];
                foreach ($link['tags'] ?? [] as $tag) {
                    if (is_array($tag) && isset($tag['name'])) {
                        $tags[] = $tag['name'];
                    } elseif (is_string($tag)) {
                        $tags[] = $tag;
                    }
                }

                $bookmarks[] = [
                    'url' => $link['url'] ?? '',
                    'title' => $link['name'] ?? $link['title'] ?? null,
                    'description' => $link['description'] ?? null,
                    'tags' => $tags,
                    // Store Linkwarden collection ID for proper hierarchy import
                    '_linkwarden_collection_id' => $linkwardenCollectionId,
                    '_linkwarden_collection_map' => $collectionMap,
                    'created_at' => $link['createdAt'] ?? $link['importDate'] ?? null,
                    'notes' => $link['description'] ?? null,
                ];
            }
        }

        return $bookmarks;
    }

    /**
     * Normalize a single JSON bookmark item.
     */
    private function normalizeJsonItem(array $item): array
    {
        return [
            'url' => $item['url'] ?? $item['href'] ?? $item['link'] ?? '',
            'title' => $item['title'] ?? $item['name'] ?? null,
            'description' => $item['description'] ?? $item['excerpt'] ?? $item['note'] ?? null,
            'tags' => $item['tags'] ?? [],
            'collection_path' => $item['collection'] ?? $item['folder'] ?? null,
            'created_at' => $item['created'] ?? $item['created_at'] ?? $item['time_added'] ?? null,
            'notes' => $item['notes'] ?? $item['note'] ?? null,
        ];
    }

    /**
     * Import from CSV format.
     */
    private function importCsv(ImportJob $job, string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Failed to open CSV file');
        }

        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectCsvDelimiter($firstLine);

        // Read header
        $header = fgetcsv($handle, 0, $delimiter);
        $header = array_map('strtolower', array_map('trim', $header));

        // Map columns
        $columnMap = $this->mapCsvColumns($header);

        // Count total rows
        $totalRows = 0;
        while (fgetcsv($handle, 0, $delimiter) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle, 0, $delimiter); // Skip header again

        $job->total_items = $totalRows;
        $job->save();

        // Process rows
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $bookmarkData = $this->mapCsvRow($row, $columnMap);
            if (!empty($bookmarkData['url'])) {
                $this->importSingleBookmark($job, $bookmarkData);
            }
        }

        fclose($handle);
    }

    /**
     * Detect CSV delimiter.
     */
    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }

        return array_keys($counts, max($counts))[0];
    }

    /**
     * Map CSV column names to our fields.
     */
    private function mapCsvColumns(array $header): array
    {
        $map = [];
        
        foreach ($header as $index => $column) {
            if (in_array($column, ['url', 'href', 'link'])) {
                $map['url'] = $index;
            } elseif (in_array($column, ['title', 'name'])) {
                $map['title'] = $index;
            } elseif (in_array($column, ['description', 'desc', 'excerpt'])) {
                $map['description'] = $index;
            } elseif (in_array($column, ['tags', 'labels', 'keywords'])) {
                $map['tags'] = $index;
            } elseif (in_array($column, ['collection', 'folder', 'category'])) {
                $map['collection_path'] = $index;
            } elseif (in_array($column, ['notes', 'note', 'comment'])) {
                $map['notes'] = $index;
            } elseif (in_array($column, ['created', 'created_at', 'date'])) {
                $map['created_at'] = $index;
            }
        }

        return $map;
    }

    /**
     * Map CSV row to bookmark data.
     */
    private function mapCsvRow(array $row, array $columnMap): array
    {
        $data = [
            'url' => '',
            'title' => null,
            'description' => null,
            'tags' => [],
            'collection_path' => null,
            'notes' => null,
            'created_at' => null,
        ];

        foreach ($columnMap as $field => $index) {
            if (isset($row[$index])) {
                $value = trim($row[$index]);
                if ($field === 'tags' && !empty($value)) {
                    $data['tags'] = array_map('trim', explode(',', $value));
                } else {
                    $data[$field] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Import a single bookmark.
     */
    private function importSingleBookmark(ImportJob $job, array $data): void
    {
        try {
            if (empty($data['url'])) {
                $job->incrementProcessed(false);
                return;
            }

            // Check for duplicate
            $existingBookmark = Bookmark::findByUrl($job->user_id, $data['url']);

            if ($existingBookmark) {
                switch ($job->duplicate_handling) {
                    case 'skip':
                        $job->incrementProcessed(true, true);
                        return;
                    case 'merge':
                        $this->mergeBookmark($existingBookmark, $data, $job);
                        $job->incrementProcessed(true);
                        return;
                    case 'keep_both':
                        // Continue to create new
                        break;
                }
            }

            // Get or create collection
            $collectionId = $job->target_collection_id;
            
            // Handle Linkwarden format with parentId-based hierarchy
            if (!$collectionId && !empty($data['_linkwarden_collection_id']) && !empty($data['_linkwarden_collection_map'])) {
                $collection = $this->getOrCreateLinkwardenCollection(
                    $job->user_id,
                    $data['_linkwarden_collection_id'],
                    $data['_linkwarden_collection_map']
                );
                $collectionId = $collection?->id;
            }
            // Handle simple path-based hierarchy (non-Linkwarden formats)
            elseif (!$collectionId && !empty($data['collection_path'])) {
                $collection = $this->getOrCreateCollectionPath($job->user_id, $data['collection_path']);
                $collectionId = $collection?->id;
            }

            // Create bookmark
            $bookmark = Bookmark::create([
                'user_id' => $job->user_id,
                'collection_id' => $collectionId,
                'url' => $data['url'],
                'title' => $data['title'] ?? $data['url'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_at' => $data['created_at'] ?? now(),
            ]);

            // Add tags
            if (!empty($data['tags'])) {
                $tags = Tag::findOrCreateManyForUser($job->user_id, $data['tags']);
                $tagIds = array_map(fn($tag) => $tag->id, $tags);
                $bookmark->syncTagsWithCounts($tagIds);
            }

            $job->incrementProcessed(true);

        } catch (\Exception $e) {
            Log::warning('Failed to import bookmark', [
                'job_id' => $job->id,
                'url' => $data['url'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            $job->appendError("Failed to import {$data['url']}: {$e->getMessage()}");
            $job->incrementProcessed(false);
        }
    }

    /**
     * Merge bookmark data into existing bookmark.
     */
    private function mergeBookmark(Bookmark $bookmark, array $data, ImportJob $job): void
    {
        // Update empty fields
        if (empty($bookmark->title) && !empty($data['title'])) {
            $bookmark->title = $data['title'];
        }
        if (empty($bookmark->description) && !empty($data['description'])) {
            $bookmark->description = $data['description'];
        }
        if (empty($bookmark->notes) && !empty($data['notes'])) {
            $bookmark->notes = $data['notes'];
        }
        $bookmark->save();

        // Merge tags
        if (!empty($data['tags'])) {
            $existingTagIds = $bookmark->tags()->pluck('tags.id')->toArray();
            $newTags = Tag::findOrCreateManyForUser($job->user_id, $data['tags']);
            $newTagIds = array_map(fn($tag) => $tag->id, $newTags);
            $mergedTagIds = array_unique(array_merge($existingTagIds, $newTagIds));
            $bookmark->syncTagsWithCounts($mergedTagIds);
        }
    }

    /**
     * Get or create collection from path like "Folder/Subfolder".
     * Used for non-Linkwarden formats only.
     */
    private function getOrCreateCollectionPath(int $userId, string $path): ?Collection
    {
        $parts = explode('/', $path);
        $parentId = null;
        $collection = null;

        foreach ($parts as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            $collection = Collection::firstOrCreate(
                [
                    'user_id' => $userId,
                    'parent_id' => $parentId,
                    'name' => $name,
                ],
                [
                    'color' => '#3B82F6',
                ]
            );

            $parentId = $collection->id;
        }

        return $collection;
    }

    /**
     * Cache for Linkwarden collection ID mappings during import.
     * Maps: Linkwarden collection ID => Our collection ID
     */
    private array $linkwardenCollectionCache = [];

    /**
     * Get or create collection from Linkwarden format, preserving parentId hierarchy.
     * This does NOT split names on "/" - it uses Linkwarden's parentId field.
     */
    private function getOrCreateLinkwardenCollection(int $userId, int $linkwardenId, array $collectionMap): ?Collection
    {
        // Check cache first
        if (isset($this->linkwardenCollectionCache[$linkwardenId])) {
            return Collection::find($this->linkwardenCollectionCache[$linkwardenId]);
        }

        // Get Linkwarden collection info
        if (!isset($collectionMap[$linkwardenId])) {
            return null;
        }

        $info = $collectionMap[$linkwardenId];
        $name = $info['name'];
        $linkwardenParentId = $info['parentId'];
        $color = $info['color'] ?? '#3B82F6';

        // Recursively get/create parent first if it exists
        $ourParentId = null;
        if ($linkwardenParentId !== null && isset($collectionMap[$linkwardenParentId])) {
            $parentCollection = $this->getOrCreateLinkwardenCollection($userId, $linkwardenParentId, $collectionMap);
            $ourParentId = $parentCollection?->id;
        }

        // Create or find the collection
        $collection = Collection::firstOrCreate(
            [
                'user_id' => $userId,
                'parent_id' => $ourParentId,
                'name' => $name,
            ],
            [
                'color' => $color,
            ]
        );

        // Cache the mapping
        $this->linkwardenCollectionCache[$linkwardenId] = $collection->id;

        return $collection;
    }
}
