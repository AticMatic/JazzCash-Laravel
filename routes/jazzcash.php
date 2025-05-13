<?php

use Illuminate\Support\Facades\Route;
use Aticmatic\JazzCash\Http\Controllers\JazzCashCallbackController;

// This route handles the callback/IPN from JazzCash.
// The path should match the 'return_url' configured in config/jazzcash.php
// and in your JazzCash merchant portal.
// It's crucial to exclude this route from CSRF protection in App\Http\Middleware\VerifyCsrfToken.php.

// Attempt to get the callback path from config dynamically.
// This might be problematic if config is not fully loaded when routes are cached.
// A simpler, fixed path might be more robust for package routes.
// $callbackPath = config('jazzcash.'. config('jazzcash.environment', 'sandbox'). '.return_url', 'jazzcash/callback');
// $callbackPath = ltrim(parse_url($callbackPath, PHP_URL_PATH), '/');

// Using a fixed, conventional path for the package route definition.
// Users should ensure their JazzCash portal return_url and package config match this.
// Or, they can override this route in their application's route files.
Route::post('jazzcash/package-callback', [JazzCashCallbackController::class, 'handle'])
    ->name('jazzcash.package.callback');

// Note: Users are generally advised to define their callback route in their application's
// `routes/web.php` or `routes/api.php` and point it to their own controller method,
// which can then use the JazzCashService::verifyCallbackHash() method.
// This package route is provided as a convenience if they use the package's controller.
// Example user-defined route (recommended):
// Route::post(config('jazzcash.sandbox.return_url'), [MyJazzCashHandler::class, 'handleCallback'])->name('my.jazzcash.callback');