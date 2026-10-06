<?php

use App\Livewire\CostsPage;
use App\Livewire\FollowupsPage;
use App\Livewire\LeadsPage;
use App\Livewire\Login;
use App\Livewire\ProductsPage;
use App\Livewire\SearchPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/masuk', Login::class)->name('login');

Route::post('/keluar', function (Request $request) {
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware('owner')->group(function () {
    Route::redirect('/', '/lead');
    Route::get('/produk', ProductsPage::class)->name('products');
    Route::get('/cari', SearchPage::class)->name('search');
    Route::get('/lead', LeadsPage::class)->name('leads');
    Route::get('/follow-up', FollowupsPage::class)->name('followups');
    Route::get('/kos', CostsPage::class)->name('costs');
});
