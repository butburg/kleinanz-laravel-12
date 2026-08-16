<?php

use App\Http\Controllers\AdController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? to_route('ads.index')
        : to_route('login');
})->name('home');

Route::get('dashboard', function () {
    return app(AdController::class)->index(request());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::resource('ads', AdController::class)->except(['show', 'create']);
    Route::patch('ads/{ad}/status', [AdController::class, 'updateStatus'])->name('ads.status.update');
    Route::post('ads/{ad}/generate', [AdController::class, 'generate'])->name('ads.generate');
    Route::post('ads/{ad}/images', [AdController::class, 'storeImage'])->name('ads.images.store');
    Route::get('ads/{ad}/images/status', [AdController::class, 'imageStatus'])->name('ads.images.status');
    Route::patch('ads/{ad}/images/{adImage}/title', [AdController::class, 'setTitleImage'])->name('ads.images.set-title');
    Route::post('ads/{ad}/images/{adImage}/toggle-crop', [AdController::class, 'toggleImageCrop'])->name('ads.images.toggle-crop');
    Route::patch('ads/{ad}/images/{adImage}/crop-preference', [AdController::class, 'updateImageCropPreference'])->name('ads.images.crop-preference');
    Route::get('ads/{ad}/images/{adImage}/download', [AdController::class, 'downloadImage'])->name('ads.images.download');
    Route::delete('ads/{ad}/images/{adImage}', [AdController::class, 'destroyImage'])->name('ads.images.destroy');
});

require __DIR__ . '/settings.php';
