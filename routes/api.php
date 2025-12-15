<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Public collection view
Route::get('public/collections/{slug}', [CollectionController::class, 'showPublic']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
        Route::post('api-key', [AuthController::class, 'generateApiKey']);
        Route::delete('api-key', [AuthController::class, 'revokeApiKey']);
    });

    // Dashboard
    Route::get('dashboard/stats', [BookmarkController::class, 'stats']);

    // Bookmarks
    Route::prefix('bookmarks')->group(function () {
        Route::get('/', [BookmarkController::class, 'index']);
        Route::post('/', [BookmarkController::class, 'store']);
        Route::get('stats', [BookmarkController::class, 'stats']);
        Route::post('bulk-delete', [BookmarkController::class, 'bulkDelete']);
        Route::post('bulk-move', [BookmarkController::class, 'bulkMove']);
        Route::post('bulk-tags', [BookmarkController::class, 'bulkAddTags']);
        Route::post('batch-archive', [BookmarkController::class, 'batchArchive']);
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [BookmarkController::class, 'show']);
            Route::put('/', [BookmarkController::class, 'update']);
            Route::delete('/', [BookmarkController::class, 'destroy']);
            Route::post('favorite', [BookmarkController::class, 'toggleFavorite']);
            Route::post('archive', [BookmarkController::class, 'archive']);
            Route::get('archive', [BookmarkController::class, 'getArchive']);
        });
    });

    // Collections
    Route::prefix('collections')->group(function () {
        Route::get('/', [CollectionController::class, 'index']);
        Route::post('/', [CollectionController::class, 'store']);
        Route::post('reorder', [CollectionController::class, 'reorder']);
        
        Route::prefix('{id}')->group(function () {
            Route::get('/', [CollectionController::class, 'show']);
            Route::put('/', [CollectionController::class, 'update']);
            Route::delete('/', [CollectionController::class, 'destroy']);
            Route::post('public-link', [CollectionController::class, 'generatePublicLink']);
            Route::delete('public-link', [CollectionController::class, 'removePublicLink']);
        });
    });

    // Tags
    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index']);
        Route::get('autocomplete', [TagController::class, 'autocomplete']);
        Route::get('cloud', [TagController::class, 'cloud']);
        Route::get('popular', [TagController::class, 'popular']);
        Route::post('/', [TagController::class, 'store']);
        Route::post('merge', [TagController::class, 'merge']);
        
        Route::prefix('{id}')->group(function () {
            Route::put('/', [TagController::class, 'update']);
            Route::delete('/', [TagController::class, 'destroy']);
        });
    });

    // Import/Export
    Route::prefix('import')->group(function () {
        Route::post('/', [ImportExportController::class, 'import']);
        Route::get('jobs', [ImportExportController::class, 'importHistory']);
        Route::get('history', [ImportExportController::class, 'importHistory']);
        Route::get('{id}/status', [ImportExportController::class, 'importStatus']);
        Route::post('{id}/cancel', [ImportExportController::class, 'cancelImport']);
    });

    Route::prefix('export')->group(function () {
        Route::get('/', [ImportExportController::class, 'export']);
        Route::get('preview', [ImportExportController::class, 'exportPreview']);
    });
});
