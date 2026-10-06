<?php

use App\Services\Billing\PriceGuide;
use App\Support\Tenancy\CurrentWorkspace;

beforeEach(fn () => app(CurrentWorkspace::class)->clear());

it('shows the public sales page with one h1, meta tags and schema', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('<title>Susah Cari Customer? Kami Cari Lead untuk SME | Dyno Leads</title>')
        ->and($html)->toMatch('/<meta name="description" content="[^"]{120,160}">/')
        ->and($html)->toContain('<link rel="canonical" href="'.url('/').'">')
        ->and($html)->toContain('property="og:image"')
        ->and($html)->toContain('"@type":"SoftwareApplication"')
        ->and($html)->toContain('"@type":"FAQPage"')
        ->and($html)->toContain('index, follow')
        ->and($html)->toContain(route('register'));

    // Keyword in the first 100 words of visible copy.
    $text = preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<(script|style|head)[^>]*>.*?<\/\1>/s', '', $html)));
    expect(implode(' ', array_slice(explode(' ', trim($text)), 0, 100)))->toContain('Cari prospek dengan AI');
});

it('says clearly that messages are sent by the user, not automatically', function () {
    $this->get('/')->assertSee('Susah cari customer?')->assertSee('Kami sediakan teks')->assertSee('Anda tekan hantar')->assertSee('Tiada blast');
});

it('explains the pricing: free trial, RM23.90 once, then pay per search', function () {
    $html = $this->get('/')
        ->assertSee('20 lead atau 14 hari')
        ->assertSee('RM23.90')
        ->assertSee('+20% caj perkhidmatan')
        ->assertSee('Tiada yuran bulanan', false)
        ->getContent();

    expect($html)->toContain('"price":"23.90"');
});

it('shows a price per lead and how many leads a top-up buys, once prices are set', function () {
    withAiPrices(1.0, 5.0); // Haiku $1/$5, Sonnet $2/$10, USD→MYR 4.5
    config([
        'ai_prices.places' => ['text_search' => 0.035, 'details' => 0.025, 'details_display' => 0.02],
        'billing.markup_percent' => 20,
        'billing.topup_options' => [20, 50, 100],
    ]);

    // 20 candidates → 10 pass (1 Text Search + 10 Details + 10 scores) → 6 leads written.
    $cost = 4.5 * (0.035 + 10 * 0.025 + 10 * (1500 * 1 + 300 * 5) / 1e6 + 6 * (1500 * 2 + 700 * 10) / 1e6);
    $perLead = (int) ceil((int) ceil(round($cost * 1.2 * 100, 4)) / 6);
    $guide = app(PriceGuide::class)->get();

    expect($guide['per_lead_sen'])->toBe($perLead)
        ->and($guide['topups'][50])->toBe((int) (floor(5000 / $perLead / 5) * 5))
        ->and($guide['topups'][50] * $perLead)->toBeLessThanOrEqual(5000);

    $this->get('/')
        ->assertSee('Bayar ikut lead')
        ->assertSee('RM'.number_format($perLead / 100, 2))
        ->assertSee('± '.$guide['topups'][50].' lead', false)
        ->assertDontSee('Kos + 20%');
});

it('falls back to "cost + markup" while prices are missing', function () {
    config(['ai_prices.usd_to_myr' => null]);

    expect(app(PriceGuide::class)->get())->toBeNull();
    $this->get('/')->assertSee('Kos + 20%');
});

it('shows the Dyno Leads logo and icons', function () {
    $this->get('/')->assertSee('images/logo-mark.webp', false)->assertSee('/favicon.png', false)->assertSee('/apple-touch-icon.png', false);
    $this->get('/masuk')->assertSee('images/logo-mark.webp', false);

    foreach (['images/logo-mark.webp', 'images/logo.png', 'favicon.png', 'favicon.ico', 'apple-touch-icon.png'] as $file) {
        expect(public_path($file))->toBeFile();
    }
});

it('sends signed-in users to their leads', function () {
    $this->actingAs($this->user)->get('/')->assertRedirect(route('leads'));
});

it('publishes a sitemap and a robots.txt that hides the app', function () {
    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(url('/'), false)->assertDontSee('/lead');

    $robots = $this->get('/robots.txt')->assertOk()->getContent();
    expect($robots)->toContain('Allow: /$')->toContain('Disallow: /')->toContain('Sitemap: '.route('sitemap'));
});

it('keeps app pages out of search engines', function () {
    $this->get('/masuk')->assertSee('noindex, nofollow', false);
    $this->actingAs($this->user)->get('/lead')->assertSee('noindex, nofollow', false);
});
