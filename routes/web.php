<?php

use App\Http\Controllers\BillingReturnController;
use App\Http\Controllers\ChipCallbackController;
use App\Http\Controllers\HomeController;
use App\Livewire\AccountPage;
use App\Livewire\AdminPage;
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

Route::get('/', HomeController::class)->name('home');

Route::get('/sitemap.xml', function () {
    $urls = [route('home'), route('terms'), route('privacy')];

    return response()->view('marketing.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
})->name('sitemap');

// Only the public pages are crawlable; the app itself is private.
Route::get('/robots.txt', fn () => response(implode("\n", [
    'User-agent: *',
    'Allow: /$',
    'Allow: /terma',
    'Allow: /privasi',
    'Disallow: /',
    '',
    'Sitemap: '.route('sitemap'),
    '',
]))->header('Content-Type', 'text/plain'))->name('robots');

Route::view('/terma', 'legal.terms')->name('terms');
Route::view('/privasi', 'legal.privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', Login::class)->name('login');
    Route::get('/daftar', Register::class)->name('register');
    Route::get('/lupa-kata-laluan', ForgotPassword::class)->name('password.request');
    Route::get('/tukar-kata-laluan/{token}', ResetPassword::class)->name('password.reset');
});

// CHIP server-to-server callback (signed, no session, no CSRF).
Route::post('/chip/callback', ChipCallbackController::class)->name('chip.callback');

Route::post('/keluar', function (Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware(['auth', 'workspace'])->group(function () {
    Route::get('/mula', OnboardingPage::class)->name('onboarding');
    Route::get('/akaun', AccountPage::class)->name('account');
    Route::get('/bayaran', BillingPage::class)->name('billing');
    Route::get('/bayaran/selesai/{payment}', BillingReturnController::class)->name('billing.return');
});

Route::middleware(['auth', 'workspace', 'onboarded'])->group(function () {
    Route::get('/produk', ProductsPage::class)->name('products');
    Route::get('/cari', SearchPage::class)->name('search');
    Route::get('/lead', LeadsPage::class)->name('leads');
    Route::get('/follow-up', FollowupsPage::class)->name('followups');
    Route::get('/kos', CostsPage::class)->name('costs');
});

Route::middleware(['auth', 'workspace', 'admin'])->group(function () {
    Route::get('/admin', AdminPage::class)->name('admin');
});
