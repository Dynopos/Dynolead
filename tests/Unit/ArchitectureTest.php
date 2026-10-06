<?php

// Every external call goes through one client class, so it is easy to mock.
arch('only the API clients use the HTTP client')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn([
        'App\Services\Places\PlacesClient',
        'App\Services\Ai\ClaudeClient',
    ]);

// AiGateway checks AiBudget::assertCanSpend() and records ai_usage around every call.
arch('Claude is only called through AiGateway')
    ->expect('App\Services\Ai\ClaudeClient')
    ->toOnlyBeUsedIn('App\Services\Ai\AiGateway');

arch('Livewire components hold no business logic dependencies on clients')
    ->expect('App\Livewire')
    ->not->toUse(['App\Services\Ai\ClaudeClient', 'App\Services\Places\PlacesClient', 'Illuminate\Support\Facades\Http']);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
