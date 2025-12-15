# Contributing Guide

Thank you for your interest in contributing to LinkVault! This guide will help you get started.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Making Changes](#making-changes)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Issue Guidelines](#issue-guidelines)

---

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment. All contributors are expected to:

- Be respectful and considerate
- Accept constructive criticism gracefully
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment, trolling, or personal attacks
- Discrimination of any kind
- Publishing others' private information
- Any conduct inappropriate in a professional setting

---

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer 2.x
- Node.js 18+
- MySQL 8.x / MariaDB 10.x
- Git

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/LinkVault.git
   cd LinkVault
   ```

3. Add upstream remote:
   ```bash
   git remote add upstream https://github.com/Nityam2007/LinkVault.git
   ```

---

## Development Setup

### Install Dependencies

```bash
# PHP dependencies
composer install

# Node dependencies
npm install
```

### Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your local database credentials.

### Database Setup

```bash
# Run migrations
php artisan migrate

# (Optional) Seed sample data
php artisan db:seed
```

### Start Development Servers

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server (hot reload)
npm run dev
```

### Useful Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild autoloader
composer dump-autoload

# Fresh database
php artisan migrate:fresh --seed

# Run tests
php artisan test

# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
```

---

## Making Changes

### Branch Naming

Use descriptive branch names:

```
feature/add-dark-mode
fix/bookmark-pagination
docs/update-api-reference
refactor/collection-service
```

### Workflow

1. **Sync with upstream:**
   ```bash
   git fetch upstream
   git checkout main
   git merge upstream/main
   ```

2. **Create feature branch:**
   ```bash
   git checkout -b feature/your-feature
   ```

3. **Make changes and commit:**
   ```bash
   git add .
   git commit -m "feat: add dark mode toggle"
   ```

4. **Push to your fork:**
   ```bash
   git push origin feature/your-feature
   ```

5. **Open Pull Request** on GitHub

### Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Formatting (no code change)
- `refactor`: Code restructuring
- `test`: Adding tests
- `chore`: Maintenance

**Examples:**
```
feat(bookmarks): add bulk delete functionality
fix(collections): resolve nested count calculation
docs(api): add rate limiting documentation
refactor(services): extract import logic to dedicated service
```

---

## Coding Standards

### PHP (Backend)

We follow PSR-12 and Laravel conventions.

**Use Laravel Pint for formatting:**
```bash
./vendor/bin/pint
```

**Key conventions:**
- Type hints for parameters and return types
- DocBlocks for public methods
- Dependency injection over facades (when practical)
- Repository pattern for data access
- Service classes for business logic

```php
// Good
class BookmarkService
{
    public function __construct(
        private readonly BookmarkRepository $repository,
        private readonly TagService $tagService,
    ) {}

    /**
     * Create a new bookmark for the user.
     */
    public function create(User $user, array $data): Bookmark
    {
        $bookmark = $this->repository->create([
            'user_id' => $user->id,
            ...$data,
        ]);

        if (isset($data['tags'])) {
            $this->tagService->syncTags($bookmark, $data['tags']);
        }

        return $bookmark;
    }
}
```

### JavaScript/Vue (Frontend)

**Use ESLint for linting:**
```bash
npm run lint
```

**Key conventions:**
- Vue 3 Composition API with `<script setup>`
- Pinia for state management
- Composables for reusable logic
- PascalCase for components
- camelCase for variables and functions

```vue
<!-- Good -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useBookmarksStore } from '@/stores/bookmarks';

const store = useBookmarksStore();
const searchQuery = ref('');

const filteredBookmarks = computed(() => 
    store.bookmarks.filter(b => 
        b.title.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
);

onMounted(() => {
    store.fetchBookmarks();
});
</script>

<template>
    <div class="bookmarks-list">
        <input v-model="searchQuery" placeholder="Search...">
        <BookmarkCard 
            v-for="bookmark in filteredBookmarks"
            :key="bookmark.id"
            :bookmark="bookmark"
        />
    </div>
</template>
```

### CSS/Tailwind

- Use Tailwind utility classes
- Extract components for repeated patterns
- Follow mobile-first responsive design

```vue
<!-- Good -->
<template>
    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-md">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ title }}
        </h2>
    </div>
</template>
```

---

## Testing

### Running Tests

```bash
# All tests
php artisan test

# Specific test file
php artisan test tests/Feature/BookmarkTest.php

# With coverage
php artisan test --coverage
```

### Writing Tests

**Feature Tests** - Test HTTP endpoints:

```php
// tests/Feature/BookmarkTest.php
public function test_user_can_create_bookmark(): void
{
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/bookmarks', [
            'url' => 'https://example.com',
            'title' => 'Example',
        ]);
    
    $response->assertStatus(201)
        ->assertJsonPath('data.url', 'https://example.com');
    
    $this->assertDatabaseHas('bookmarks', [
        'user_id' => $user->id,
        'url' => 'https://example.com',
    ]);
}
```

**Unit Tests** - Test individual classes:

```php
// tests/Unit/BookmarkServiceTest.php
public function test_bookmark_service_creates_with_tags(): void
{
    $user = User::factory()->create();
    $tags = Tag::factory()->count(3)->create(['user_id' => $user->id]);
    
    $service = new BookmarkService(new BookmarkRepository());
    
    $bookmark = $service->create($user, [
        'url' => 'https://example.com',
        'title' => 'Example',
        'tags' => $tags->pluck('id')->toArray(),
    ]);
    
    $this->assertCount(3, $bookmark->tags);
}
```

---

## Pull Request Process

### Before Submitting

1. ✅ Tests pass: `php artisan test`
2. ✅ Code style: `./vendor/bin/pint --test`
3. ✅ No console errors in browser
4. ✅ Build succeeds: `npm run build`
5. ✅ Documentation updated (if applicable)

### PR Template

```markdown
## Description
Brief description of changes.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Refactoring
- [ ] Other (describe)

## Testing
- [ ] Tests added/updated
- [ ] Manual testing performed

## Screenshots (if UI changes)
[Add screenshots here]

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes (or documented)
```

### Review Process

1. Maintainers will review your PR
2. Address any requested changes
3. Once approved, PR will be merged

---

## Issue Guidelines

### Bug Reports

Include:
- Clear description of the bug
- Steps to reproduce
- Expected vs actual behavior
- Environment (OS, PHP version, browser)
- Error messages/logs

### Feature Requests

Include:
- Clear description of the feature
- Use case / problem it solves
- Proposed implementation (optional)
- Mockups/wireframes (if UI-related)

### Questions

For general questions:
- Check existing documentation
- Search closed issues
- Open a discussion (if enabled) or issue

---

## Need Help?

- 📖 Read the [documentation](./README.md)
- 🐛 Search [existing issues](https://github.com/Nityam2007/LinkVault/issues)
- 💬 Open a new issue with your question

Thank you for contributing! 🎉

---

[← Architecture](ARCHITECTURE.md) | [Deployment →](DEPLOYMENT.md)
