# Architecture Guide

Technical overview of LinkVault's codebase structure and design decisions.

## Table of Contents

- [Overview](#overview)
- [Directory Structure](#directory-structure)
- [Backend Architecture](#backend-architecture)
- [Frontend Architecture](#frontend-architecture)
- [Database Schema](#database-schema)
- [Key Components](#key-components)
- [Design Patterns](#design-patterns)
- [Extending the Application](#extending-the-application)

---

## Overview

LinkVault is built with:
- **Backend:** Laravel 11 (PHP 8.3)
- **Frontend:** Vue.js 3 + Vite 5
- **Styling:** Tailwind CSS 3
- **Database:** MySQL 8 / MariaDB 10
- **Authentication:** Laravel Sanctum

### Architecture Principles

1. **API-First Design** - Backend provides REST API, frontend is an SPA
2. **Separation of Concerns** - Clear boundaries between layers
3. **Repository Pattern** - Data access abstraction
4. **Service Layer** - Business logic encapsulation
5. **Component-Based UI** - Reusable Vue components

---

## Directory Structure

```
linkvault/
├── app/
│   ├── Http/
│   │   ├── Controllers/         # API Controllers
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── BookmarkController.php
│   │   │   │   ├── CollectionController.php
│   │   │   │   ├── TagController.php
│   │   │   │   └── ImportExportController.php
│   │   │   └── Controller.php
│   │   ├── Middleware/          # HTTP Middleware
│   │   ├── Requests/            # Form Request Validation
│   │   └── Resources/           # API Resources (Transformers)
│   ├── Models/                  # Eloquent Models
│   │   ├── Bookmark.php
│   │   ├── Collection.php
│   │   ├── Tag.php
│   │   └── User.php
│   ├── Services/                # Business Logic Services
│   │   ├── BookmarkService.php
│   │   ├── ImportService.php
│   │   ├── ExportService.php
│   │   └── ArchiveService.php
│   └── Repositories/            # Data Access Layer
│       ├── BookmarkRepository.php
│       └── CollectionRepository.php
├── bootstrap/                   # Framework Bootstrap
├── config/                      # Configuration Files
├── database/
│   ├── migrations/              # Database Migrations
│   ├── seeders/                 # Database Seeders
│   └── factories/               # Model Factories
├── public/                      # Public Assets
│   └── build/                   # Compiled Frontend
├── resources/
│   ├── js/                      # Vue.js Frontend
│   │   ├── components/          # Vue Components
│   │   ├── composables/         # Vue Composables
│   │   ├── layouts/             # Page Layouts
│   │   ├── pages/               # Page Components
│   │   ├── router/              # Vue Router
│   │   ├── stores/              # Pinia Stores
│   │   └── app.js               # Vue Entry Point
│   ├── css/                     # Stylesheets
│   └── views/                   # Blade Templates
├── routes/
│   ├── api.php                  # API Routes
│   └── web.php                  # Web Routes
├── storage/                     # File Storage
└── tests/                       # Test Files
```

---

## Backend Architecture

### Controllers

Controllers handle HTTP requests and delegate to services.

```php
// app/Http/Controllers/Api/BookmarkController.php
class BookmarkController extends Controller
{
    public function __construct(
        private BookmarkService $bookmarkService
    ) {}

    public function index(Request $request)
    {
        $bookmarks = $this->bookmarkService->getFiltered(
            $request->user(),
            $request->validated()
        );
        
        return BookmarkResource::collection($bookmarks);
    }
}
```

### Services

Services contain business logic.

```php
// app/Services/BookmarkService.php
class BookmarkService
{
    public function __construct(
        private BookmarkRepository $repository
    ) {}

    public function getFiltered(User $user, array $filters): LengthAwarePaginator
    {
        return $this->repository->getFiltered($user->id, $filters);
    }

    public function create(User $user, array $data): Bookmark
    {
        // Business logic here
        $bookmark = $this->repository->create([
            'user_id' => $user->id,
            ...$data
        ]);

        // Sync tags
        if (isset($data['tags'])) {
            $bookmark->tags()->sync($data['tags']);
        }

        return $bookmark;
    }
}
```

### Repositories

Repositories abstract database queries.

```php
// app/Repositories/BookmarkRepository.php
class BookmarkRepository
{
    public function getFiltered(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Bookmark::where('user_id', $userId)
            ->with(['collection', 'tags']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                  ->orWhere('url', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['collection_id'])) {
            $query->where('collection_id', $filters['collection_id']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }
}
```

### Models

Eloquent models define relationships and attributes.

```php
// app/Models/Bookmark.php
class Bookmark extends Model
{
    protected $fillable = [
        'user_id', 'collection_id', 'url', 'title',
        'description', 'notes', 'is_favorite', 'archived_at'
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'archived_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

```php
// app/Models/Collection.php
class Collection extends Model
{
    protected $fillable = [
        'user_id', 'parent_id', 'name', 'description',
        'color', 'icon', 'is_public'
    ];

    // Self-referencing relationship for hierarchy
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Collection::class, 'parent_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    // Recursive count of all descendant bookmarks
    public function getTotalBookmarkCountAttribute(): int
    {
        $count = $this->bookmarks()->count();
        foreach ($this->children as $child) {
            $count += $child->total_bookmark_count;
        }
        return $count;
    }
}
```

### API Resources

Transform models for API responses.

```php
// app/Http/Resources/BookmarkResource.php
class BookmarkResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'title' => $this->title,
            'description' => $this->description,
            'notes' => $this->notes,
            'is_favorite' => $this->is_favorite,
            'collection' => new CollectionResource($this->whenLoaded('collection')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

---

## Frontend Architecture

### Vue Components Structure

```
resources/js/
├── app.js                    # Vue app initialization
├── router/
│   └── index.js              # Vue Router configuration
├── stores/
│   ├── auth.js               # Authentication state
│   ├── bookmarks.js          # Bookmarks state
│   ├── collections.js        # Collections state
│   └── tags.js               # Tags state
├── pages/
│   ├── Dashboard.vue         # Main dashboard
│   ├── Bookmarks.vue         # Bookmarks listing
│   ├── CollectionDetail.vue  # Collection view
│   ├── Settings.vue          # User settings
│   └── Auth/
│       ├── Login.vue
│       └── Register.vue
├── components/
│   ├── BookmarkCard.vue      # Bookmark display card
│   ├── BookmarkForm.vue      # Create/edit form
│   ├── CollectionTree.vue    # Sidebar tree
│   ├── CollectionTreeItem.vue
│   ├── TagBadge.vue
│   ├── SearchBar.vue
│   └── ui/                   # Generic UI components
│       ├── Button.vue
│       ├── Modal.vue
│       ├── Dropdown.vue
│       └── Pagination.vue
├── composables/
│   ├── useApi.js             # API calls wrapper
│   ├── useToast.js           # Toast notifications
│   └── useDebounce.js        # Debounce utility
└── layouts/
    ├── AppLayout.vue         # Authenticated layout
    └── GuestLayout.vue       # Public layout
```

### Pinia Stores

State management with Pinia.

```javascript
// stores/bookmarks.js
import { defineStore } from 'pinia';
import api from '@/services/api';

export const useBookmarksStore = defineStore('bookmarks', {
    state: () => ({
        bookmarks: [],
        pagination: null,
        loading: false,
        filters: {
            search: '',
            collection_id: null,
            tags: [],
            is_favorite: null,
        }
    }),

    actions: {
        async fetchBookmarks(page = 1) {
            this.loading = true;
            try {
                const response = await api.get('/bookmarks', {
                    params: { ...this.filters, page }
                });
                this.bookmarks = response.data.data;
                this.pagination = response.data.pagination;
            } finally {
                this.loading = false;
            }
        },

        async createBookmark(data) {
            const response = await api.post('/bookmarks', data);
            this.bookmarks.unshift(response.data.data);
            return response.data.data;
        },

        setFilter(key, value) {
            this.filters[key] = value;
            this.fetchBookmarks(1);
        }
    }
});
```

### API Service

Centralized API handling.

```javascript
// services/api.js
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    }
});

// Add auth token to requests
api.interceptors.request.use(config => {
    const auth = useAuthStore();
    if (auth.token) {
        config.headers.Authorization = `Bearer ${auth.token}`;
    }
    return config;
});

// Handle 401 responses
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            const auth = useAuthStore();
            auth.logout();
        }
        return Promise.reject(error);
    }
);

export default api;
```

---

## Database Schema

### Entity Relationship Diagram

```
┌─────────────┐       ┌─────────────────┐       ┌─────────────┐
│    users    │       │   collections   │       │    tags     │
├─────────────┤       ├─────────────────┤       ├─────────────┤
│ id          │──┐    │ id              │──┐    │ id          │
│ name        │  │    │ user_id     FK──│──│    │ user_id  FK │
│ email       │  │    │ parent_id   FK──│──│    │ name        │
│ password    │  │    │ name            │  │    │ color       │
│ created_at  │  │    │ description     │  │    │ created_at  │
│ updated_at  │  │    │ color           │  │    └─────────────┘
└─────────────┘  │    │ icon            │  │           │
                 │    │ is_public       │  │           │
                 │    │ created_at      │  │           │
                 │    └─────────────────┘  │           │
                 │            │            │           │
                 │            │            │           │
                 │    ┌───────┴───────┐    │    ┌──────┴──────┐
                 │    │   bookmarks   │    │    │bookmark_tag │
                 │    ├───────────────┤    │    ├─────────────┤
                 └───►│ user_id    FK │    │    │ bookmark_id │
                      │ collection FK─│────┘    │ tag_id      │
                      │ url           │◄────────┤             │
                      │ title         │         └─────────────┘
                      │ description   │
                      │ notes         │
                      │ is_favorite   │
                      │ archived_at   │
                      │ created_at    │
                      │ updated_at    │
                      └───────────────┘
```

### Migrations

Key migrations:

```php
// Bookmarks table
Schema::create('bookmarks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('collection_id')->nullable()->constrained()->nullOnDelete();
    $table->string('url', 2048);
    $table->string('title', 500)->nullable();
    $table->text('description')->nullable();
    $table->text('notes')->nullable();
    $table->boolean('is_favorite')->default(false);
    $table->timestamp('archived_at')->nullable();
    $table->timestamps();
    
    // Indexes for performance
    $table->index(['user_id', 'created_at']);
    $table->index(['user_id', 'collection_id']);
    $table->fullText(['title', 'description', 'notes']); // Full-text search
});

// Collections table
Schema::create('collections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('collections')->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('color', 7)->default('#3B82F6');
    $table->string('icon')->nullable();
    $table->boolean('is_public')->default(false);
    $table->timestamps();
    
    $table->index(['user_id', 'parent_id']);
});

// Pivot table for bookmark-tag relationship
Schema::create('bookmark_tag', function (Blueprint $table) {
    $table->foreignId('bookmark_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->primary(['bookmark_id', 'tag_id']);
});
```

---

## Key Components

### Import Service

Handles multiple import formats.

```php
// app/Services/ImportService.php
class ImportService
{
    public function import(User $user, UploadedFile $file, string $format): ImportResult
    {
        return match($format) {
            'linkwarden' => $this->importLinkwarden($user, $file),
            'html' => $this->importHtml($user, $file),
            'csv' => $this->importCsv($user, $file),
            'json' => $this->importJson($user, $file),
            default => throw new InvalidFormatException("Unknown format: {$format}")
        };
    }

    private function importLinkwarden(User $user, UploadedFile $file): ImportResult
    {
        $data = json_decode($file->getContents(), true);
        
        // Build collection map using parentId
        $collectionMap = [];
        foreach ($data['collections'] ?? [] as $col) {
            $collection = $this->getOrCreateCollection($user, $col, $collectionMap, $data['collections']);
            $collectionMap[$col['id']] = $collection->id;
        }
        
        // Import bookmarks
        $imported = 0;
        foreach ($data['links'] ?? [] as $link) {
            $this->createBookmark($user, $link, $collectionMap);
            $imported++;
        }
        
        return new ImportResult(imported: $imported);
    }
}
```

### Collection Tree Builder

Builds hierarchical collection tree.

```php
// In CollectionController
public function tree(Request $request)
{
    $collections = Collection::where('user_id', $request->user()->id)
        ->with('children')
        ->whereNull('parent_id')
        ->withCount('bookmarks')
        ->get();

    return response()->json([
        'data' => $this->buildTree($collections)
    ]);
}

private function buildTree($collections): array
{
    return $collections->map(function ($collection) {
        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'color' => $collection->color,
            'icon' => $collection->icon,
            'bookmark_count' => $collection->bookmarks_count,
            'total_bookmark_count' => $collection->total_bookmark_count,
            'children' => $this->buildTree($collection->children),
        ];
    })->toArray();
}
```

---

## Design Patterns

### Repository Pattern

Abstracts data access, making it easy to swap implementations.

### Service Layer

Business logic is kept in services, keeping controllers thin.

### Resource Transformers

API resources transform models for consistent API responses.

### Observer Pattern

Model observers handle side effects:

```php
// app/Observers/BookmarkObserver.php
class BookmarkObserver
{
    public function created(Bookmark $bookmark): void
    {
        // Clear collection count cache
        Cache::forget("collection_count_{$bookmark->collection_id}");
    }
}
```

---

## Extending the Application

### Adding a New Feature

1. **Create Migration** (if needed)
   ```bash
   php artisan make:migration add_feature_to_bookmarks
   ```

2. **Update Model**
   ```bash
   php artisan make:model Feature
   ```

3. **Create Service**
   ```php
   // app/Services/FeatureService.php
   ```

4. **Create Controller**
   ```bash
   php artisan make:controller Api/FeatureController
   ```

5. **Add Routes**
   ```php
   // routes/api.php
   Route::apiResource('features', FeatureController::class);
   ```

6. **Create Vue Components**
   ```
   resources/js/components/Feature.vue
   ```

7. **Add Store** (if needed)
   ```javascript
   // resources/js/stores/features.js
   ```

### Adding a New Import Format

1. Add format handler in `ImportService`:
   ```php
   private function importNewFormat(User $user, UploadedFile $file): ImportResult
   {
       // Parse and import
   }
   ```

2. Add to format switch in `import()` method

3. Update API validation to accept new format

---

[← Import/Export](IMPORT_EXPORT.md) | [Contributing →](CONTRIBUTING.md)
