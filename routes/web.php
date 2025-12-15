<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// SPA catch-all - serves the Vue/React frontend
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
