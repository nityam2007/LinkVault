<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Support\Collection as LaravelCollection;

/**
 * Service for exporting bookmarks to various formats.
 */
class ExportService
{
    /**
     * Export bookmarks to specified format.
     */
    public function export(User $user, string $format, ?int $collectionId = null, ?array $bookmarkIds = null): string
    {
        $bookmarks = $this->getBookmarksForExport($user, $collectionId, $bookmarkIds);

        return match ($format) {
            'html' => $this->exportToHtml($bookmarks, $user),
            'json' => $this->exportToJson($bookmarks),
            'csv' => $this->exportToCsv($bookmarks),
            'markdown', 'md' => $this->exportToMarkdown($bookmarks, $user),
            default => throw new \InvalidArgumentException("Unknown export format: $format"),
        };
    }

    /**
     * Get bookmarks for export.
     */
    private function getBookmarksForExport(User $user, ?int $collectionId, ?array $bookmarkIds): LaravelCollection
    {
        $query = Bookmark::with(['tags', 'collection'])
            ->where('user_id', $user->id);

        if ($bookmarkIds) {
            $query->whereIn('id', $bookmarkIds);
        }

        if ($collectionId) {
            // Get all descendant collection IDs
            $collectionIds = $this->getCollectionWithDescendants($collectionId);
            $query->whereIn('collection_id', $collectionIds);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get collection and all descendant IDs.
     */
    private function getCollectionWithDescendants(int $collectionId): array
    {
        $ids = [$collectionId];
        $children = Collection::where('parent_id', $collectionId)->pluck('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getCollectionWithDescendants($childId));
        }

        return $ids;
    }

    /**
     * Export to Netscape HTML format.
     */
    private function exportToHtml(LaravelCollection $bookmarks, User $user): string
    {
        $html = <<<HTML
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<!-- This is an automatically generated file.
     It will be read and overwritten.
     DO NOT EDIT! -->
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
<DL><p>

HTML;

        // Group by collection
        $grouped = $bookmarks->groupBy(fn($b) => $b->collection?->getFullPathAttribute() ?? 'Unsorted');

        foreach ($grouped as $collectionPath => $collectionBookmarks) {
            if ($collectionPath !== 'Unsorted') {
                $html .= $this->buildFolderStructure($collectionPath);
            }

            foreach ($collectionBookmarks as $bookmark) {
                $timestamp = $bookmark->created_at?->timestamp ?? time();
                $title = htmlspecialchars($bookmark->title ?? $bookmark->url, ENT_QUOTES, 'UTF-8');
                $url = htmlspecialchars($bookmark->url, ENT_QUOTES, 'UTF-8');
                $tags = $bookmark->tags->pluck('name')->join(',');

                $html .= "    <DT><A HREF=\"{$url}\" ADD_DATE=\"{$timestamp}\"";
                if ($tags) {
                    $html .= " TAGS=\"" . htmlspecialchars($tags, ENT_QUOTES, 'UTF-8') . "\"";
                }
                $html .= ">{$title}</A>\n";

                if ($bookmark->description) {
                    $desc = htmlspecialchars($bookmark->description, ENT_QUOTES, 'UTF-8');
                    $html .= "    <DD>{$desc}\n";
                }
            }

            if ($collectionPath !== 'Unsorted') {
                $html .= "</DL><p>\n";
            }
        }

        $html .= "</DL><p>\n";

        return $html;
    }

    /**
     * Build folder structure HTML.
     */
    private function buildFolderStructure(string $path): string
    {
        $parts = explode(' / ', $path);
        $html = '';
        
        foreach ($parts as $index => $folder) {
            $indent = str_repeat('    ', $index + 1);
            $folder = htmlspecialchars($folder, ENT_QUOTES, 'UTF-8');
            $html .= "{$indent}<DT><H3>{$folder}</H3>\n";
            $html .= "{$indent}<DL><p>\n";
        }

        return $html;
    }

    /**
     * Export to JSON format.
     */
    private function exportToJson(LaravelCollection $bookmarks): string
    {
        $data = $bookmarks->map(function ($bookmark) {
            return [
                'url' => $bookmark->url,
                'title' => $bookmark->title,
                'description' => $bookmark->description,
                'notes' => $bookmark->notes,
                'tags' => $bookmark->tags->pluck('name')->toArray(),
                'collection' => $bookmark->collection?->getFullPathAttribute(),
                'is_favorite' => $bookmark->is_favorite,
                'is_archived' => $bookmark->is_archived,
                'created_at' => $bookmark->created_at?->toIso8601String(),
                'updated_at' => $bookmark->updated_at?->toIso8601String(),
            ];
        });

        return json_encode([
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'count' => $bookmarks->count(),
            'bookmarks' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export to CSV format.
     */
    private function exportToCsv(LaravelCollection $bookmarks): string
    {
        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, [
            'url',
            'title',
            'description',
            'notes',
            'tags',
            'collection',
            'is_favorite',
            'is_archived',
            'created_at',
        ]);

        // Data rows
        foreach ($bookmarks as $bookmark) {
            fputcsv($output, [
                $bookmark->url,
                $bookmark->title,
                $bookmark->description,
                $bookmark->notes,
                $bookmark->tags->pluck('name')->join(', '),
                $bookmark->collection?->getFullPathAttribute(),
                $bookmark->is_favorite ? 'yes' : 'no',
                $bookmark->is_archived ? 'yes' : 'no',
                $bookmark->created_at?->toDateTimeString(),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export to Markdown format.
     */
    private function exportToMarkdown(LaravelCollection $bookmarks, User $user): string
    {
        $md = "# Bookmarks Export\n\n";
        $md .= "Exported: " . now()->toDateTimeString() . "\n";
        $md .= "Total: " . $bookmarks->count() . " bookmarks\n\n";
        $md .= "---\n\n";

        // Group by collection
        $grouped = $bookmarks->groupBy(fn($b) => $b->collection?->getFullPathAttribute() ?? 'Unsorted');

        foreach ($grouped as $collectionPath => $collectionBookmarks) {
            $depth = substr_count($collectionPath, ' / ') + 2;
            $md .= str_repeat('#', min($depth, 6)) . " {$collectionPath}\n\n";

            foreach ($collectionBookmarks as $bookmark) {
                $title = $bookmark->title ?? $bookmark->url;
                $md .= "- [{$title}]({$bookmark->url})";

                // Add tags
                if ($bookmark->tags->isNotEmpty()) {
                    $tags = $bookmark->tags->map(fn($t) => "`{$t->name}`")->join(' ');
                    $md .= " {$tags}";
                }

                // Add favorite indicator
                if ($bookmark->is_favorite) {
                    $md .= " ⭐";
                }

                $md .= "\n";

                // Add description
                if ($bookmark->description) {
                    $md .= "  > {$bookmark->description}\n";
                }

                $md .= "\n";
            }
        }

        return $md;
    }

    /**
     * Get filename for export.
     */
    public function getFilename(string $format): string
    {
        $date = now()->format('Y-m-d');
        $extension = match ($format) {
            'html' => 'html',
            'json' => 'json',
            'csv' => 'csv',
            'markdown', 'md' => 'md',
            default => 'txt',
        };

        return "bookmarks-export-{$date}.{$extension}";
    }

    /**
     * Get content type for export.
     */
    public function getContentType(string $format): string
    {
        return match ($format) {
            'html' => 'text/html',
            'json' => 'application/json',
            'csv' => 'text/csv',
            'markdown', 'md' => 'text/markdown',
            default => 'text/plain',
        };
    }
}
