<?php

use App\Models\PlaceCache;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Places\PlaceData;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests run against an in-memory SQLite database, inside one customer
| workspace ($this->workspace, owned by $this->user). No test may call a real
| external API: every HTTP call must be faked with Http::fake().
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->workspace = Workspace::factory()->create(['plan' => 'dalaman']);
        $this->user = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'owner']);
        app(CurrentWorkspace::class)->set($this->workspace);
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/** Sign in as the owner of the test workspace. */
function actingAsOwner(): TestCase
{
    return test()->actingAs(test()->user);
}

/** A second, unrelated customer with its own user. */
function otherWorkspace(): array
{
    $workspace = Workspace::factory()->create(['plan' => 'dalaman']);
    $user = User::factory()->create(['workspace_id' => $workspace->id]);

    return [$workspace, $user];
}
/** A Places API (New) place object, as Google returns it. */
function apiPlace(string $id, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'displayName' => ['text' => 'Kedai '.$id, 'languageCode' => 'ms'],
        'types' => ['grocery_store', 'food', 'store'],
        'primaryType' => 'grocery_store',
        'rating' => 4.4,
        'userRatingCount' => 120,
        'shortFormattedAddress' => 'Jalan Pasar, Pasir Mas',
        'businessStatus' => 'OPERATIONAL',
    ], $overrides);
}

/** Place Details payload: summary fields plus phone, website and reviews. */
function apiDetails(string $id, array $overrides = []): array
{
    return array_replace(apiPlace($id), [
        'nationalPhoneNumber' => '011-1234 5678',
        'internationalPhoneNumber' => '+60 11-1234 5678',
        'googleMapsUri' => 'https://maps.google.com/?cid='.$id,
        'reviews' => [
            ['rating' => 5, 'text' => ['text' => 'Layanan mesra, barang lengkap.'], 'originalText' => ['text' => 'Layanan mesra, barang lengkap.']],
            ['rating' => 2, 'text' => ['text' => 'Kaunter lambat waktu petang.'], 'originalText' => ['text' => 'Kaunter lambat waktu petang.']],
        ],
    ], $overrides);
}

/**
 * Fake Google Places: Text Search returns $places (one page) and Place Details
 * returns $details[place_id] (or a default details payload).
 */
function fakePlaces(array $places, array $details = []): void
{
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => $places]),
        'places.googleapis.com/v1/places/*' => function (Request $request) use ($details) {
            $id = rawurldecode(Str::of(parse_url($request->url(), PHP_URL_PATH))->afterLast('/')->toString());

            return Http::response($details[$id] ?? apiDetails($id));
        },
    ]);
}

/** Fill in test prices so the AI budget can be calculated (real prices live in config/ai_prices.php). */
function withAiPrices(float $input = 1.0, float $output = 5.0): void
{
    config([
        'ai_prices.usd_to_myr' => 4.5,
        'ai_prices.models' => [
            'claude-haiku-4-5-20251001' => ['input' => $input, 'output' => $output, 'cache_write' => $input * 1.25, 'cache_read' => $input / 10],
            'claude-sonnet-5-5' => ['input' => $input * 2, 'output' => $output * 2, 'cache_write' => $input * 2.5, 'cache_read' => $input / 5],
        ],
    ]);
}

/** A Messages API response whose text block is $data as JSON. */
function claudeReply(array|string $data, array $usage = []): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'test-model',
        'content' => [['type' => 'text', 'text' => is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE)]],
        'stop_reason' => 'end_turn',
        'usage' => array_replace([
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'cache_read_input_tokens' => 0,
            'cache_creation_input_tokens' => 0,
        ], $usage),
    ];
}

function goodMessage(string $shop = 'Kedai Contoh'): string
{
    return "Salam {$shop} 👋\n\nSaya Bob dari DynoPOS Technologies, Pasir Mas. Ramai puji layanan mesra.\n\nKaunter selalu panjang waktu petang, POS boleh percepatkan.\n\nKalau nak info lanjut, balas je mesej ni atau tengok dynopos.my\n\nKalau tak berminat, balas STOP, saya tak ganggu lagi 🙏";
}

/**
 * Fake Claude: score calls (CLAUDE_MODEL_SCORE) and write calls (CLAUDE_MODEL_WRITE)
 * get their own reply queues. The last reply repeats when a queue runs out.
 */
function fakeClaude(array $scoreReplies = [], array $writeReplies = [], array $extra = []): void
{
    $queues = ['score' => $scoreReplies, 'write' => $writeReplies];

    Http::fake(array_merge($extra, [
        'api.anthropic.com/v1/messages' => function (Request $request) use (&$queues) {
            $key = $request['model'] === config('dynoleads.ai.model_score') ? 'score' : 'write';
            $reply = count($queues[$key]) > 1 ? array_shift($queues[$key]) : ($queues[$key][0] ?? null);
            $reply ??= $key === 'score'
                ? claudeReply(['fit' => 80, 'reason' => 'Kedai sibuk.', 'hook' => 'Layanan mesra.', 'gap' => 'Kaunter lambat.', 'flag' => null])
                : claudeReply(['message' => goodMessage()]);

            return Http::response($reply);
        },
    ]));
}

/** Requests sent to Claude for one purpose ("score" or "write"). */
function claudeRequests(string $purpose): Collection
{
    $model = config($purpose === 'score' ? 'dynoleads.ai.model_score' : 'dynoleads.ai.model_write');

    return Http::recorded(fn ($request) => str_contains($request->url(), 'api.anthropic.com') && $request['model'] === $model)
        ->map(fn ($pair) => $pair[0]);
}

/** Put place details in the short cache so screens do not call Google. */
function cachePlace(string $placeId, array $overrides = []): void
{
    PlaceCache::query()->updateOrCreate(['place_id' => $placeId], [
        'payload' => PlaceData::fromApi(apiDetails($placeId, $overrides), true, true),
        'has_details' => true,
        'fetched_at' => now(),
    ]);
}
