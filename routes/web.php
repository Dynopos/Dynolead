<?php

use App\Livewire\AccountPage;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\BillingPage;
use App\Livewire\CostsPage;
use App\Livewire\FollowupsPage;
use App\Livewire\LeadsPage;
use App\Livewire\OnboardingPage;
use App\Livewire\ProductsPage;
use App\Livewire\SearchPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('leads') : redirect()->route('login'))->name('home');

Route::view('/terma', 'legal.terms')->name('terms');
Route::view('/privasi', 'legal.privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', Login::class)->name('login');
    Route::get('/daftar', Register::class)->name('register');
    Route::get('/lupa-kata-laluan', ForgotPassword::class)->name('password.request');
    Route::get('/tukar-kata-laluan/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/keluar', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware(['auth', 'workspace'])->group(function () {
    Route::get('/mula', OnboardingPage::class)->name('onboarding');
    Route::get('/akaun', AccountPage::class)->name('account');
    Route::get('/langganan', BillingPage::class)->name('billing');
});

Route::middleware(['auth', 'workspace', 'onboarded'])->group(function () {
    Route::get('/produk', ProductsPage::class)->name('products');
    Route::get('/cari', SearchPage::class)->name('search');
    Route::get('/lead', LeadsPage::class)->name('leads');
    Route::get('/follow-up', FollowupsPage::class)->name('followups');
    Route::get('/kos', CostsPage::class)->name('costs');
});
