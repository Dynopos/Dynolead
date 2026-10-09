<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['app.url' => 'https://dynolead.my', 'dynoleads.canonical_redirect' => true]);
});

it('sends www and other hosts to the same page on dynolead.my', function () {
    $this->get('http://www.dynolead.my/terma?ref=fb')
        ->assertStatus(301)
        ->assertRedirect('https://dynolead.my/terma?ref=fb');

    $this->get('https://dynolead.on-forge.com/')
        ->assertStatus(301)
        ->assertRedirect('https://dynolead.my/');
});

it('serves the canonical host normally', function () {
    $this->get('https://dynolead.my/terma')->assertOk();
});

it('never redirects posts, the CHIP callback or the health check', function () {
    Http::fake();

    expect($this->post('http://www.dynolead.my/chip/callback')->status())->not->toBe(301)
        ->and($this->post('http://www.dynolead.my/masuk')->status())->not->toBe(301);

    $this->get('http://www.dynolead.my/up')->assertOk();
});

it('can be turned off', function () {
    config(['dynoleads.canonical_redirect' => false]);

    $this->get('http://www.dynolead.my/terma')->assertOk();
});
