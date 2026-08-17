<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AppendixController;
use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? to_route('ads.index')
        : to_route('login');
})->name('home');

Route::get('dashboard', function () {
    return app(AdController::class)->index(request());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('admin', AdminDashboardController::class)->name('admin.dashboard');
    Route::delete('admin/users/{user}', [AdminDashboardController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('help', fn () => Inertia::render('Help'))->name('help');
    Route::get('support', [SupportController::class, 'create'])->name('support.create');
    Route::post('support', [SupportController::class, 'store'])->name('support.store');
    Route::resource('appendices', AppendixController::class)
        ->parameters(['appendices' => 'appendix'])
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('ads', AdController::class)->except(['show', 'create']);
    Route::patch('ads/{ad}/status', [AdController::class, 'updateStatus'])->name('ads.status.update');
    Route::post('ads/{ad}/generate', [AdController::class, 'generate'])->name('ads.generate');
    Route::post('ads/{ad}/images', [AdController::class, 'storeImage'])->name('ads.images.store');
    Route::get('ads/{ad}/images/status', [AdController::class, 'imageStatus'])->name('ads.images.status');
    Route::patch('ads/{ad}/images/{adImage}/title', [AdController::class, 'setTitleImage'])->name('ads.images.set-title');
    Route::post('ads/{ad}/images/{adImage}/toggle-crop', [AdController::class, 'toggleImageCrop'])->name('ads.images.toggle-crop');
    Route::patch('ads/{ad}/images/{adImage}/crop-preference', [AdController::class, 'updateImageCropPreference'])->name('ads.images.crop-preference');
    Route::get('ads/{ad}/images/download', [AdController::class, 'downloadAllImages'])->name('ads.images.download-all');
    Route::get('ads/{ad}/images/{adImage}/download', [AdController::class, 'downloadImage'])->name('ads.images.download');
    Route::delete('ads/{ad}/images/{adImage}', [AdController::class, 'destroyImage'])->name('ads.images.destroy');
});

require __DIR__.'/settings.php';
