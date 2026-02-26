<?php

declare(strict_types=1);

use BoxinCode\FilamentSiteKit\OAuth\GoogleOAuthController;
use BoxinCode\FilamentSiteKit\Http\SiteKitAccountController;
use BoxinCode\FilamentSiteKit\Http\UpgradeUiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->prefix('filament-sitekit/google')->group(function (): void {
    Route::get('/connect', [GoogleOAuthController::class, 'connect'])
        ->name('filament-sitekit.google.connect');

    Route::get('/callback', [GoogleOAuthController::class, 'callback'])
        ->name('filament-sitekit.google.callback');

    Route::get('/disconnect', [GoogleOAuthController::class, 'disconnect'])
        ->name('filament-sitekit.google.disconnect');
});

Route::middleware(['web'])->prefix('filament-sitekit/accounts')->group(function (): void {
    Route::get('/switch', [SiteKitAccountController::class, 'switch'])
        ->name('filament-sitekit.accounts.switch');
});

Route::middleware(['web'])->prefix('filament-sitekit/upgrade')->group(function (): void {
    Route::post('/dismiss-banner', [UpgradeUiController::class, 'dismiss'])
        ->name('filament-sitekit.upgrade-banner.dismiss');
});
