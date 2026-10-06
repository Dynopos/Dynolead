<?php

use App\Support\Tenancy\CurrentWorkspace;

beforeEach(fn () => app(CurrentWorkspace::class)->clear());

it('shows the public sales page with one h1, meta tags and schema', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('<title>Cari Prospek dengan AI untuk SME Malaysia | Dyno Leads</title>')
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
    $this->get('/')->assertSee('Anda tekan hantar')->assertSee('Tiada blast');
});

it('shows prices only once Bob sets them', function () {
    $this->get('/')->assertSee('Harga akan diumumkan');

    config(['plans.plans.asas.price_myr' => 79]);
    $html = $this->get('/')->assertSee('RM79')->getContent();
    expect($html)->toContain('"price":"79.00"');
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
