<?php

use App\Livewire\ProductsPage;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(ProductSeeder::class));

it('lists the seeded products', function () {
    actingAsOwner()->get('/produk')->assertOk()->assertSee('DynoPOS')->assertSee('murahwebsite.my')->assertSee('Dilarang: demo');
});

it('adds a new product with filters and a pitch variant', function () {
    Livewire::test(ProductsPage::class)
        ->call('create')
        ->set('name', 'DynoShade')
        ->set('company', 'DynoPOS Technologies, Pasir Mas')
        ->set('pitch_core', 'Kanopi dan bidai untuk kedai.')
        ->set('cta', 'Balas je mesej ni untuk sebut harga.')
        ->set('banned_words', 'murah, demo')
        ->set('min_rating', '4')
        ->set('min_reviews', '15')
        ->set('require_no_website', true)
        ->set('default_place_types', 'kafe, restoran')
        ->call('addVariant')
        ->set('pitch_variants.0.key', 'kafe')
        ->set('pitch_variants.0.match', 'kafe, cafe')
        ->set('pitch_variants.0.pitch', 'Kanopi untuk kafe.')
        ->call('save')
        ->assertHasNoErrors();

    $p = Product::where('name', 'DynoShade')->firstOrFail();
    expect($p->slug)->toBe('dynoshade')
        ->and($p->bannedWords())->toBe(['murah', 'demo'])
        ->and($p->minRating())->toBe(4.0)
        ->and($p->minReviews())->toBe(15)
        ->and($p->requiresNoWebsite())->toBeTrue()
        ->and($p->default_place_types)->toBe(['kafe', 'restoran'])
        ->and($p->pitchFor('kafe'))->toBe('Kanopi untuk kafe.');
});

it('edits an existing product and keeps its variants', function () {
    $dynopos = Product::where('slug', 'dynopos')->first();

    Livewire::test(ProductsPage::class)
        ->call('edit', $dynopos->id)
        ->assertSet('banned_words', 'demo')
        ->set('min_reviews', '25')
        ->call('save')
        ->assertHasNoErrors();

    $dynopos->refresh();
    expect($dynopos->minReviews())->toBe(25)
        ->and($dynopos->pitch_variants)->toHaveCount(3)
        ->and($dynopos->bannedWords())->toBe(['demo']);
});

it('validates required fields', function () {
    Livewire::test(ProductsPage::class)
        ->call('create')
        ->set('name', '')
        ->set('min_rating', '9')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'pitch_core' => 'required', 'min_rating' => 'max'])
        ->assertHasNoErrors('cta');
});

it('only needs the product facts: the closing line is optional', function () {
    Livewire::test(ProductsPage::class)
        ->call('create')
        ->set('name', 'Website Premium')
        ->set('pitch_core', 'Website premium RM200 termasuk domain.')
        ->set('cta', '')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Website Premium')->firstOrFail();
    expect($product->ctaText())->toBe('Kalau berminat, balas je mesej ni ya.');

    $product->update(['contact_info' => '018-288 9932']);
    expect($product->ctaText())->toBe('Kalau berminat, balas je mesej ni atau hubungi 018-288 9932.');
});
