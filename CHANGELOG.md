# Changelog

All notable changes to LinkVault will be documented in this file.

## [1.1.0] - 2025-12-16

### Bug Fixes

#### Critical Fixes
- **Fixed archive job skipping bug** - Archive jobs were incorrectly skipping when bookmark status was `pending`. The job now only skips if status is `processing` (another job is actively working).
- **Fixed queue configuration** - Changed default from `sync` to `database` to prevent blocking HTTP requests during archiving.
- **Added missing dependency** - Installed `andreskrey/readability.php` for article content extraction.

#### Backend Fixes
- **BookmarkController** - Fixed auto_archive flag handling, added metadata auto-fetch on bookmark creation
- **CollectionController** - Fixed hierarchy support with proper `buildTree()` method, supports up to 10 levels deep
- **ArchiveService** - Optimized timeouts (30s → 15s), reduced max image size (10MB → 5MB), limited images to 10 per page
- **ImageDownloader** - Reduced concurrent downloads (20 → 5), shorter timeouts (15s → 8s)
- **Bookmark Model** - Added `og_image`, `site_name`, `author`, `reading_time` accessors from archive relation

#### Frontend Fixes
- **BookmarkCard.vue** - Fixed memory leak (event listener cleanup), improved metadata display
- **BookmarkModal.vue** - Fixed auto_archive checkbox, better error handling
- **ReaderView.vue** - Fixed article_html property access
- **BookmarkDetail.vue** - Added fetchArchive method, improved archive metadata display
- **bookmarks.js store** - Fixed API response handling consistency

### UI Improvements

#### Major Enhancements
- **Sidebar Tags** - Limited to 15 tags with "View All Tags" link for performance with large tag counts (1800+)
- **Tags Page** - Complete rewrite with search, pagination (50/page), cloud view and list view modes
- **Bookmarks Page** - Added bulk selection mode with keyboard shortcuts:
  - `N` - New bookmark
  - `G` - Grid/List view toggle
  - `L` - Grid/List view toggle
  - `Ctrl+Shift+S` - Toggle bulk selection
  - `Ctrl+A` - Select all (in bulk mode)
  - `Escape` - Exit bulk mode
  - `?` - Show keyboard shortcuts
- **Advanced Filters** - Added filter dropdown with archive status filter
- **Collection Hierarchy** - Proper nested display up to 10 levels deep

#### CSS/Styling
- Added proper z-index layering (sidebar z-40, modals z-50)
- Added Vue transitions for modals and dropdowns
- Improved card hover states and shadows
- Better responsive design

### Performance Improvements
- Archive jobs now run in background via queue (not blocking HTTP requests)
- Sidebar tags limited to prevent DOM overload
- Tags page uses pagination instead of loading all at once
- Reduced archive timeouts and image limits for faster processing

### Configuration Changes
- **QUEUE_CONNECTION** - Should be set to `database` or `redis` (not `sync`)
- Queue worker command: `php artisan queue:work --queue=archives,default --tries=3 --timeout=120`

---

## [1.0.0] - Initial Release

- Basic bookmark management (CRUD)
- Collections with hierarchy
- Tagging system
- Import/Export (JSON, HTML, CSV)
- Archive functionality
- Search with filters
- User authentication
