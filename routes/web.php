<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\StatsController;
use App\Filament\Pages\VendorProfile;

Route::get('/', [UploadController::class, 'form'])->name('upload.form');
Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');

Route::get('/upload/{upload}/geo-prices', [UploadController::class, 'geoPriceForm'])->name('upload.prices');
Route::post('/upload/{upload}/geo-prices', [UploadController::class, 'applyGeoPrices'])->name('upload.prices.apply');


Route::get('/stats/vendors', [StatsController::class, 'vendorStats'])->name('stats.vendors');
Route::get('/stats/invites', [StatsController::class, 'inviteStats'])->name('stats.invites');

Route::get('/admin/vendor/{vendorId}/profile', VendorProfile::class)->name('vendor.profile');

Route::get('/upload/{id}/profile', \App\Filament\Pages\UploadProfile::class)->name('filament.pages.upload-profile');
Route::get('/temp-vendor/{id}/profile', App\Filament\Pages\TempVendorProfile::class)
    ->name('temp-vendor.profile')
    ->middleware(['auth']);