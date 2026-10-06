<?php

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;

it('seeds DynoPOS and murahwebsite.my from spec §7', function () {
    $this->seed(DatabaseSeeder::class);

    $dynopos = Product::where('slug', 'dynopos')->firstOrFail();
    $murah = Product::where('slug', 'murahwebsite')->firstOrFail();

    expect($dynopos->name)->toBe('DynoPOS')
        ->and($dynopos->sender_name)->toBe('Bob')
        ->and($dynopos->bannedWords())->toBe(['demo'])
        ->and($dynopos->cta)->toBe('Kalau nak info lanjut, balas je mesej ni atau tengok dynopos.my')
        ->and(collect($dynopos->pitch_variants)->pluck('key')->all())->toBe(['runcit', 'butik', 'restoran'])
        ->and($dynopos->requiresNoWebsite())->toBeFalse();

    expect($murah->requiresNoWebsite())->toBeTrue()
        ->and($murah->cta)->toContain('murahwebsite.my')
        ->and($murah->pitch_core)->toContain('RM200');
});

it('can be seeded twice without duplicates', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(2);
});

it('picks the DynoPOS pitch variant by shop type', function () {
    $this->seed(DatabaseSeeder::class);
    $dynopos = Product::where('slug', 'dynopos')->firstOrFail();

    expect($dynopos->pitchFor('kedai runcit'))->toContain('Imbas barcode')
        ->and($dynopos->pitchFor('butik pakaian'))->toContain('setiap item')
        ->and($dynopos->pitchFor('restoran'))->toContain('kedai makan')
        ->and($dynopos->pitchFor('kafe'))->toContain('kedai makan')
        // Google types for a grocery include "food"; the runcit pitch must still win.
        ->and($dynopos->pitchFor(null, ['grocery_store', 'food', 'store']))->toContain('Imbas barcode')
        ->and($dynopos->pitchFor('kedai hardware'))->toBe($dynopos->pitch_core);
});

it('redirects guests to the login page', function () {
    $this->get('/lead')->assertRedirect(route('login'));
});

it('shows the mobile menu with all five sections', function () {
    actingAsOwner()->get('/lead')
        ->assertOk()
        ->assertSeeInOrder(['Produk', 'Cari', 'Lead', 'Follow-up', 'Kos']);
});

it('logs in with the password from .env', function () {
    Livewire\Livewire::test(App\Livewire\Login::class)
        ->set('password', 'salah')
        ->call('login')
        ->assertHasErrors('password');

    Livewire\Livewire::test(App\Livewire\Login::class)
        ->set('password', 'rahsia-test')
        ->call('login')
        ->assertRedirect(route('leads'));

    expect(session('owner'))->toBeTrue();
});

it('keeps secrets out of .env.example', function () {
    $example = file_get_contents(base_path('.env.example'));

    foreach (['ANTHROPIC_API_KEY', 'GOOGLE_PLACES_API_KEY', 'APP_LOGIN_PASSWORD', 'CLAUDE_MODEL_SCORE', 'CLAUDE_MODEL_WRITE', 'CLAUDE_USE_BATCH', 'CLAUDE_WEB_SEARCH', 'AI_MONTHLY_BUDGET_MYR', 'PLACES_CACHE_HOURS', 'PRICE_TABLE_PATH'] as $name) {
        expect($example)->toContain($name.'=');
    }

    expect($example)->toMatch('/^ANTHROPIC_API_KEY=$/m')
        ->and($example)->toMatch('/^GOOGLE_PLACES_API_KEY=$/m')
        ->and($example)->toMatch('/^APP_LOGIN_PASSWORD=$/m');
});
