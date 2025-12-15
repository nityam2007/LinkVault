# API Reference

LinkVault provides a RESTful API for managing bookmarks, collections, and tags.

## Table of Contents

- [Authentication](#authentication)
- [Base URL](#base-url)
- [Response Format](#response-format)
- [Endpoints](#endpoints)
  - [Auth](#auth)
  - [Bookmarks](#bookmarks)
  - [Collections](#collections)
  - [Tags](#tags)
  - [Import/Export](#importexport)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)

---

## Authentication

LinkVault uses Laravel Sanctum for API authentication.

### Obtaining a Token

```http
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "your_password"
}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  },
  "token": "1|abc123xyz..."
}
```

### Using the Token

Include the token in the `Authorization` header:

```http
Authorization: Bearer 1|abc123xyz...
```

### Logout

```http
POST /api/logout
Authorization: Bearer {token}
```

---

## Base URL

```
https://your-domain.com/api
```

For local development:
```
http://localhost:8000/api
```

---

## Response Format

### Success Response

```json
{
  "data": { ... },
  "message": "Success message"
}
```

### Paginated Response

```json
{
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200
  }
}
```

### Error Response

```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

---

## Endpoints

### Auth

#### Register

```http
POST /api/register
```

**Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Login

```http
POST /api/login
```

**Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

#### Get Current User

```http
GET /api/user
Authorization: Bearer {token}
```

#### Logout

```http
POST /api/logout
Authorization: Bearer {token}
```

---

### Bookmarks

#### List Bookmarks

```http
GET /api/bookmarks
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Items per page (default: 20, max: 100) |
| `search` | string | Search in title, URL, description |
| `collection_id` | integer | Filter by collection |
| `tags` | string | Comma-separated tag IDs |
| `is_favorite` | boolean | Filter favorites only |
| `sort` | string | Sort field: `created_at`, `title`, `url` |
| `order` | string | Sort order: `asc`, `desc` |

**Example:**
```http
GET /api/bookmarks?search=laravel&collection_id=5&per_page=50&sort=created_at&order=desc
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Laravel Documentation",
      "url": "https://laravel.com/docs",
      "description": "Official Laravel documentation",
      "notes": "Great resource for learning",
      "is_favorite": true,
      "collection_id": 5,
      "collection": {
        "id": 5,
        "name": "Development",
        "color": "#3B82F6"
      },
      "tags": [
        {"id": 1, "name": "php", "color": "#8B5CF6"},
        {"id": 2, "name": "framework", "color": "#10B981"}
      ],
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

#### Get Single Bookmark

```http
GET /api/bookmarks/{id}
Authorization: Bearer {token}
```

#### Create Bookmark

```http
POST /api/bookmarks
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "url": "https://example.com",
  "title": "Example Website",
  "description": "An example bookmark",
  "notes": "Personal notes here",
  "collection_id": 1,
  "tags": [1, 2, 3],
  "is_favorite": false
}
```

#### Update Bookmark

```http
PUT /api/bookmarks/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "title": "Updated Title",
  "description": "Updated description",
  "collection_id": 2,
  "tags": [1, 4],
  "is_favorite": true
}
```

#### Delete Bookmark

```http
DELETE /api/bookmarks/{id}
Authorization: Bearer {token}
```

#### Toggle Favorite

```http
POST /api/bookmarks/{id}/toggle-favorite
Authorization: Bearer {token}
```

#### Bulk Delete

```http
POST /api/bookmarks/bulk-delete
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "ids": [1, 2, 3, 4, 5]
}
```

---

### Collections

#### List Collections (Tree)

```http
GET /api/collections/tree
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Development",
      "color": "#3B82F6",
      "icon": "code",
      "parent_id": null,
      "bookmark_count": 25,
      "total_bookmark_count": 150,
      "children": [
        {
          "id": 2,
          "name": "PHP",
          "color": "#8B5CF6",
          "parent_id": 1,
          "bookmark_count": 50,
          "total_bookmark_count": 50,
          "children": []
        }
      ]
    }
  ]
}
```

#### List Collections (Flat)

```http
GET /api/collections
Authorization: Bearer {token}
```

#### Get Collection

```http
GET /api/collections/{id}
Authorization: Bearer {token}
```

#### Get Collection Bookmarks

```http
GET /api/collections/{id}/bookmarks
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `include_children` | boolean | Include bookmarks from child collections |
| `page` | integer | Page number |
| `per_page` | integer | Items per page |

#### Create Collection

```http
POST /api/collections
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "name": "New Collection",
  "description": "Collection description",
  "color": "#3B82F6",
  "icon": "folder",
  "parent_id": null,
  "is_public": false
}
```

#### Update Collection

```http
PUT /api/collections/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

#### Delete Collection

```http
DELETE /api/collections/{id}
Authorization: Bearer {token}
```

---

### Tags

#### List Tags

```http
GET /api/tags
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "php",
      "color": "#8B5CF6",
      "bookmark_count": 45
    },
    {
      "id": 2,
      "name": "javascript",
      "color": "#F59E0B",
      "bookmark_count": 32
    }
  ]
}
```

#### Create Tag

```http
POST /api/tags
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "name": "new-tag",
  "color": "#10B981"
}
```

#### Update Tag

```http
PUT /api/tags/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

#### Delete Tag

```http
DELETE /api/tags/{id}
Authorization: Bearer {token}
```

---

### Import/Export

#### Import Bookmarks

```http
POST /api/import
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `file` | file | Import file (JSON, HTML, CSV) |
| `format` | string | Format: `linkwarden`, `html`, `csv`, `json` |

**Example (cURL):**
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "file=@backup.json" \
  -F "format=linkwarden" \
  https://your-domain.com/api/import
```

#### Export Bookmarks

```http
GET /api/export?format={format}
Authorization: Bearer {token}
```

**Formats:** `json`, `csv`, `html`

---

## Error Handling

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (successful deletion) |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

### Validation Errors

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "url": ["The url field is required."],
    "title": ["The title must be at least 3 characters."]
  }
}
```

---

## Rate Limiting

API requests are rate limited to prevent abuse:

| Endpoint | Limit |
|----------|-------|
| Authentication | 5 requests/minute |
| General API | 60 requests/minute |
| Import | 5 requests/hour |

Rate limit headers are included in responses:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1642000000
```

---

## Code Examples

### JavaScript (Fetch)

```javascript
const API_URL = 'https://your-domain.com/api';
const TOKEN = 'your_api_token';

// Get bookmarks
const response = await fetch(`${API_URL}/bookmarks`, {
  headers: {
    'Authorization': `Bearer ${TOKEN}`,
    'Accept': 'application/json'
  }
});
const data = await response.json();
```

### Python (Requests)

```python
import requests

API_URL = 'https://your-domain.com/api'
TOKEN = 'your_api_token'

headers = {
    'Authorization': f'Bearer {TOKEN}',
    'Accept': 'application/json'
}

# Get bookmarks
response = requests.get(f'{API_URL}/bookmarks', headers=headers)
bookmarks = response.json()
```

### cURL

```bash
# Get bookmarks
curl -X GET \
  -H "Authorization: Bearer your_api_token" \
  -H "Accept: application/json" \
  https://your-domain.com/api/bookmarks

# Create bookmark
curl -X POST \
  -H "Authorization: Bearer your_api_token" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","title":"Example"}' \
  https://your-domain.com/api/bookmarks
```

---

[← Configuration](CONFIGURATION.md) | [Import/Export →](IMPORT_EXPORT.md)
