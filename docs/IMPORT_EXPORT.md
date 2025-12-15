# Import & Export Guide

Complete guide for migrating your bookmarks to and from LinkVault.

## Table of Contents

- [Supported Formats](#supported-formats)
- [Importing Data](#importing-data)
  - [From Linkwarden](#from-linkwarden)
  - [From Browser (HTML)](#from-browser-html)
  - [From CSV](#from-csv)
  - [From JSON](#from-json)
- [Exporting Data](#exporting-data)
- [Troubleshooting](#troubleshooting)

---

## Supported Formats

### Import Formats

| Format | Extension | Source |
|--------|-----------|--------|
| Linkwarden JSON | `.json` | Linkwarden backup export |
| Browser HTML | `.html` | Chrome, Firefox, Edge, Safari |
| CSV | `.csv` | Spreadsheets, other tools |
| Generic JSON | `.json` | Custom JSON format |

### Export Formats

| Format | Description |
|--------|-------------|
| JSON | Full data with all metadata |
| CSV | Spreadsheet-compatible |
| HTML | Browser-importable bookmarks |

---

## Importing Data

### From Linkwarden

LinkVault has full support for Linkwarden backup files, preserving:
- ✅ Collection hierarchy (nested folders)
- ✅ Tags and colors
- ✅ Bookmark metadata (title, description, notes)
- ✅ Favorites status
- ✅ Timestamps

#### Step 1: Export from Linkwarden

1. Go to Linkwarden Settings
2. Click "Export Data"
3. Download the `backup.json` file

#### Step 2: Import to LinkVault

**Via Web UI:**
1. Go to Settings → Import/Export
2. Select "Linkwarden" format
3. Upload your `backup.json`
4. Click "Import"

**Via API:**
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@backup.json" \
  -F "format=linkwarden" \
  https://your-domain.com/api/import
```

**Via CLI:**
```bash
php artisan bookmarks:import backup.json --format=linkwarden
```

#### Linkwarden JSON Structure

For reference, this is the expected structure:

```json
{
  "collections": [
    {
      "id": 1,
      "name": "Development",
      "description": "Dev resources",
      "color": "#3B82F6",
      "parentId": null,
      "createdAt": "2024-01-01T00:00:00Z"
    },
    {
      "id": 2,
      "name": "PHP",
      "parentId": 1,
      "color": "#8B5CF6"
    }
  ],
  "links": [
    {
      "id": 1,
      "name": "Laravel Docs",
      "url": "https://laravel.com/docs",
      "description": "Official documentation",
      "collectionId": 2,
      "tags": [
        {"id": 1, "name": "php"},
        {"id": 2, "name": "framework"}
      ],
      "createdAt": "2024-01-15T10:30:00Z"
    }
  ],
  "tags": [
    {"id": 1, "name": "php", "color": "#8B5CF6"},
    {"id": 2, "name": "framework", "color": "#10B981"}
  ]
}
```

---

### From Browser (HTML)

Import bookmarks exported from any major browser.

#### Export from Browser

**Chrome:**
1. Menu → Bookmarks → Bookmark Manager
2. Three dots menu → "Export bookmarks"
3. Save as HTML file

**Firefox:**
1. Menu → Bookmarks → Manage Bookmarks
2. Import and Backup → Export Bookmarks to HTML

**Edge:**
1. Menu → Favorites → Manage Favorites
2. Three dots → Export favorites

**Safari:**
1. File → Export Bookmarks
2. Save as HTML file

#### Import HTML

**Via Web UI:**
1. Settings → Import/Export
2. Select "Browser HTML" format
3. Upload the `.html` file
4. Click "Import"

**Via API:**
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@bookmarks.html" \
  -F "format=html" \
  https://your-domain.com/api/import
```

#### HTML Structure Support

LinkVault parses standard Netscape bookmark format:

```html
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<DL>
  <DT><H3>Folder Name</H3>
  <DL>
    <DT><A HREF="https://example.com" ADD_DATE="1234567890">Title</A>
  </DL>
</DL>
```

Preserved data:
- ✅ Folder structure → Collections
- ✅ URLs and titles
- ✅ Add dates (if available)
- ❌ Tags (not in HTML format)

---

### From CSV

Import bookmarks from spreadsheets or other tools.

#### CSV Format

Required columns: `url`

Optional columns: `title`, `description`, `notes`, `tags`, `collection`, `is_favorite`, `created_at`

**Example CSV:**
```csv
url,title,description,tags,collection,is_favorite
https://laravel.com,Laravel,PHP Framework,"php,framework",Development,true
https://vuejs.org,Vue.js,JS Framework,"javascript,frontend",Development,false
https://github.com,GitHub,Code hosting,"git,development",,false
```

#### Import CSV

**Via Web UI:**
1. Settings → Import/Export
2. Select "CSV" format
3. Upload your `.csv` file
4. Map columns if needed
5. Click "Import"

**Via API:**
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@bookmarks.csv" \
  -F "format=csv" \
  https://your-domain.com/api/import
```

#### CSV Column Mapping

| Column | Type | Description |
|--------|------|-------------|
| `url` | string | **Required** - Bookmark URL |
| `title` | string | Bookmark title (fetched if empty) |
| `description` | string | Short description |
| `notes` | string | Personal notes |
| `tags` | string | Comma-separated tags |
| `collection` | string | Collection name or path |
| `is_favorite` | boolean | `true`, `false`, `1`, `0` |
| `created_at` | datetime | ISO 8601 or `Y-m-d H:i:s` |

---

### From JSON

Import from a generic JSON format.

#### JSON Format

```json
{
  "bookmarks": [
    {
      "url": "https://example.com",
      "title": "Example",
      "description": "An example bookmark",
      "notes": "My notes",
      "tags": ["tag1", "tag2"],
      "collection": "Folder/Subfolder",
      "is_favorite": false,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

#### Import JSON

**Via API:**
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@bookmarks.json" \
  -F "format=json" \
  https://your-domain.com/api/import
```

---

## Exporting Data

### Export All Bookmarks

**Via Web UI:**
1. Settings → Import/Export
2. Select export format (JSON, CSV, HTML)
3. Click "Export"
4. Download the file

**Via API:**
```bash
# JSON export
curl -H "Authorization: Bearer {token}" \
  "https://your-domain.com/api/export?format=json" \
  -o bookmarks.json

# CSV export
curl -H "Authorization: Bearer {token}" \
  "https://your-domain.com/api/export?format=csv" \
  -o bookmarks.csv

# HTML export (browser-importable)
curl -H "Authorization: Bearer {token}" \
  "https://your-domain.com/api/export?format=html" \
  -o bookmarks.html
```

### Export Specific Collection

```bash
curl -H "Authorization: Bearer {token}" \
  "https://your-domain.com/api/export?format=json&collection_id=5" \
  -o collection-bookmarks.json
```

### Export Options

| Parameter | Description |
|-----------|-------------|
| `format` | `json`, `csv`, `html` |
| `collection_id` | Export specific collection |
| `include_children` | Include child collections |
| `tags` | Filter by tags (comma-separated) |

---

## Exported Data Structure

### JSON Export

```json
{
  "exported_at": "2024-01-15T10:30:00Z",
  "version": "1.0",
  "collections": [
    {
      "id": 1,
      "name": "Development",
      "color": "#3B82F6",
      "parent_id": null
    }
  ],
  "tags": [
    {"id": 1, "name": "php", "color": "#8B5CF6"}
  ],
  "bookmarks": [
    {
      "id": 1,
      "url": "https://laravel.com",
      "title": "Laravel",
      "description": "PHP Framework",
      "notes": "Great framework",
      "collection_id": 1,
      "tags": [1],
      "is_favorite": true,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

### CSV Export

```csv
id,url,title,description,notes,collection,tags,is_favorite,created_at
1,https://laravel.com,Laravel,PHP Framework,Great framework,Development,php,true,2024-01-15T10:30:00Z
```

---

## Troubleshooting

### Common Import Issues

#### "File too large"

Increase PHP upload limits:

```ini
# php.ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 512M
```

Or use CLI import for large files:
```bash
php artisan bookmarks:import large-file.json --format=linkwarden
```

#### "Invalid JSON format"

- Ensure the file is valid JSON (use a JSON validator)
- Check for encoding issues (should be UTF-8)
- Verify the structure matches expected format

#### "Duplicate bookmarks"

By default, duplicates (same URL) are skipped. To update existing:

```bash
php artisan bookmarks:import file.json --update-existing
```

#### "Collections not nested correctly"

For Linkwarden imports, ensure `parentId` fields are correct. The importer uses `parentId` to build the hierarchy, not folder path separators.

#### "Tags not imported"

- HTML format doesn't support tags
- CSV tags must be comma-separated in a single column
- JSON tags should be an array

### Import Performance

For large imports (10,000+ bookmarks):

1. **Use CLI import:**
   ```bash
   php artisan bookmarks:import file.json --format=linkwarden
   ```

2. **Disable sync processing:**
   ```env
   QUEUE_CONNECTION=database
   ```
   Then run queue worker:
   ```bash
   php artisan queue:work
   ```

3. **Increase memory:**
   ```bash
   php -d memory_limit=1G artisan bookmarks:import file.json
   ```

### Backup Before Import

Always backup your database before large imports:

```bash
# MySQL/MariaDB
mysqldump -u user -p linkvault > backup_$(date +%Y%m%d).sql

# Or export via LinkVault
curl -H "Authorization: Bearer {token}" \
  "https://your-domain.com/api/export?format=json" \
  -o backup_$(date +%Y%m%d).json
```

---

[← API Reference](API.md) | [Architecture →](ARCHITECTURE.md)
