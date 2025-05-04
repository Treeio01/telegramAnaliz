<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\VendorProfile;

Route::get('/', function () {
    return redirect()->route('filament.admin.auth.login');
});

Route::get('/admin/vendor/{vendorId}/profile', VendorProfile::class)->name('vendor.profile');

Route::get('/upload/{id}/profile', \App\Filament\Pages\UploadProfile::class)->name('filament.pages.upload-profile');
Route::get('/upload/{id}/page-invite', \App\Filament\Pages\UploadPageInvite::class)->name('filament.pages.upload-page-invite');
Route::get('/temp-vendor/{id}/profile', App\Filament\Pages\TempVendorProfile::class)
    ->name('temp-vendor.profile')
    ->middleware(['auth']);