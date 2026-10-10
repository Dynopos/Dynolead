<?php

use App\Enums\LeadStatus;
use App\Enums\SearchStatus;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\PricesNotConfigured;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\PlaceCache;
use App\Models\Product;
use App\Services\Ai\AiBudget;
use App\Services\Ai\AiGateway;
use App\Services\Ai\LeadScorer;
use App\Services\Ai\MessageWriter;
use App\Services\Ai\PromptRepository;
use App\Services\Places\PlaceData;
use App\Services\Search\SearchPipeline;
use Database\Seeders\ProductSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    withAiPrices();
    $this->seed(ProductSeeder::class);
    $this->dynopos = Product::where('slug', 'dynopos')->first();
    $this->murah = Product::where('slug', 'murahwebsite')->first();
});

function leadFor(Product $product, array $attrs = []): Lead
{
    $lead = Lead::factory()->for($product)->create(array_merge([
        'fit' => null, 'reason' => null, 'hook' => null, 'gap' => null, 'message' => null,
        'score_prompt_version' => null, 'prompt_version' => null,
    ], $attrs));

    PlaceCache::create([
        'place_id' => $lead->place_id,
        'payload' => PlaceData::fromApi(apiDetails($lead->place_id), true, true),
        'has_details' => true,
        'fetched_at' => now(),
    ]);

    return $lead;
}

// --- ClaudeClient -----------------------------------------------------------

it('uses the models from .env and caches the system prompt', function () {
    config(['dynoleads.ai.model_score' => 'model-score-from-env', 'dynoleads.ai.model_write' => 'model-write-from-env']);
    config(['ai_prices.models.model-score-from-env' => ['input' => 1, 'output' => 1, 'cache_write' => 1, 'cache_read' => 1]]);
    config(['ai_prices.models.model-write-from-env' => ['input' => 1, 'output' => 1, 'cache_write' => 1, 'cache_read' => 1]]);
    fakeClaude();

    $lead = leadFor($this->dynopos);
    app(LeadScorer::class)->score($lead);
    app(MessageWriter::class)->write($lead->refresh());

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'anthropic') && $r['model'] === 'model-score-from-env');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'anthropic') && $r['model'] === 'model-write-from-env');

    Http::assertSent(function (Request $r) {
        return $r->url() === 'https://api.anthropic.com/v1/messages'
            && $r->header('x-api-key')[0] === 'test-anthropic-key'
            && $r->header('anthropic-version')[0] === '2023-06-01'
            && $r['system'][0]['cache_control'] === ['type' => 'ephemeral']
            && $r['output_config']['format']['type'] === 'json_schema'
            && ! isset($r['tools']); // web search tool stays off
    });
});

it('keeps the cached system prompt identical across shops', function () {
    fakeClaude();

    app(LeadScorer::class)->score(leadFor($this->dynopos));
    app(LeadScorer::class)->score(leadFor($this->dynopos));

    $systems = claudeRequests('score')->map(fn ($r) => $r['system'][0]['text'])->unique();
    $users = claudeRequests('score')->map(fn ($r) => $r['messages'][0]['content'])->unique();

    expect($systems)->toHaveCount(1)->and($users)->toHaveCount(2);
});

// --- Budget -----------------------------------------------------------------

it('checks the budget before every call and records every call in ai_usage', function () {
    fakeClaude();
    $lead = leadFor($this->dynopos);

    app(LeadScorer::class)->score($lead);
    app(MessageWriter::class)->write($lead->refresh());

    $usage = AiUsage::orderBy('id')->get();
    expect($usage)->toHaveCount(2)
        ->and($usage->pluck('purpose')->all())->toBe(['score', 'write'])
        ->and($usage[0]->model)->toBe('claude-haiku-4-5-20251001')
        ->and($usage[0]->input_tokens)->toBe(1000)
        ->and($usage[0]->output_tokens)->toBe(200)
        ->and($usage[0]->lead_id)->toBe($lead->id)
        // (1000 × $1 + 200 × $5) / 1M × 4.5 = RM0.009
        ->and($usage[0]->cost_estimate)->toEqualWithDelta(0.009, 0.000001);
});

it('records cache read tokens and prices them', function () {
    fakeClaude([claudeReply(['fit' => 80, 'reason' => 'r', 'hook' => 'h', 'gap' => 'g', 'flag' => null], [
        'input_tokens' => 100, 'output_tokens' => 100, 'cache_read_input_tokens' => 4000, 'cache_creation_input_tokens' => 0,
    ])]);

    app(LeadScorer::class)->score(leadFor($this->dynopos));

    $usage = AiUsage::first();
    // (100 × 1 + 100 × 5 + 4000 × 0.1) / 1M × 4.5
    expect($usage->cache_read_tokens)->toBe(4000)
        ->and($usage->cost_estimate)->toEqualWithDelta(0.0045, 0.000001);
});

it('stops AI work once the monthly limit is reached, without calling Claude', function () {
    fakeClaude();
    app(AiBudget::class)->setLimit(1.00);
    AiUsage::create(['model' => 'x', 'purpose' => 'score', 'cost_estimate' => 1.00]);

    expect(fn () => app(LeadScorer::class)->score(leadFor($this->dynopos)))
        ->toThrow(BudgetExceeded::class, 'Had kos AI bulan ini dah dicapai');

    Http::assertNothingSent();
    expect(AiUsage::count())->toBe(1);
});

it('refuses a call whose worst-case estimate would cross the limit', function () {
    fakeClaude();
    app(AiBudget::class)->setLimit(0.001);

    expect(fn () => app(AiGateway::class)->call('score', 'claude-haiku-4-5-20251001', 'system', 'user', 600))
        ->toThrow(BudgetExceeded::class);
    Http::assertNothingSent();
});

it('starts a fresh budget each month', function () {
    app(AiBudget::class)->setLimit(1.00);
    AiUsage::create(['model' => 'x', 'purpose' => 'score', 'cost_estimate' => 1.00]);
    expect(app(AiBudget::class)->isExhausted())->toBeTrue();

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());

    expect(app(AiBudget::class)->isExhausted())->toBeFalse()
        ->and(app(AiBudget::class)->spentThisMonth())->toBe(0.0);
});

it('does not call Claude while model prices are empty', function () {
    config(['ai_prices.models.claude-haiku-4-5-20251001.input' => null]);
    fakeClaude();

    expect(fn () => app(LeadScorer::class)->score(leadFor($this->dynopos)))
        ->toThrow(PricesNotConfigured::class);
    Http::assertNothingSent();
});

it('stops the search pipeline with the budget message when the limit is hit', function () {
    fakePlaces([apiPlace('a'), apiPlace('b'), apiPlace('c')]);
    fakeClaude();
    // Enough for roughly one score call (worst-case estimate ~RM0.015).
    app(AiBudget::class)->setLimit(0.02);

    $search = app(SearchPipeline::class)->start($this->dynopos, 'kedai runcit', ['Pasir Mas'], 20)->refresh();

    expect($search->status)->toBe(SearchStatus::BudgetExceeded)
        ->and($search->error)->toBe(BudgetExceeded::MESSAGE)
        ->and(Lead::whereNotNull('fit')->count())->toBe(1)
        ->and(Lead::whereNull('fit')->count())->toBe(2)
        ->and(claudeRequests('score'))->toHaveCount(1)
        ->and(claudeRequests('write'))->toHaveCount(0);
});

// --- Scoring ----------------------------------------------------------------

it('scores a lead and saves fit, reason, hook, gap, flag and prompt_version', function () {
    fakeClaude([claudeReply(['fit' => 85, 'reason' => 'Kedai sibuk.', 'hook' => 'Layanan mesra.', 'gap' => 'Kaunter lambat.', 'flag' => 'Mungkin dah ada sistem, semak dulu'])]);

    $lead = app(LeadScorer::class)->score(leadFor($this->dynopos));

    expect($lead->fit)->toBe(85)
        ->and($lead->reason)->toBe('Kedai sibuk.')
        ->and($lead->hook)->toBe('Layanan mesra.')
        ->and($lead->gap)->toBe('Kaunter lambat.')
        ->and($lead->flag)->toBe('Mungkin dah ada sistem, semak dulu')
        ->and($lead->score_prompt_version)->toBe(app(PromptRepository::class)->version('score'))
        ->and($lead->status)->toBe(LeadStatus::Baru);
});

it('marks fit below 50 as Tak sesuai and writes no message', function () {
    fakePlaces([apiPlace('a')]);
    fakeClaude([claudeReply(['fit' => 30, 'reason' => 'Kedai kecil sangat.', 'hook' => '', 'gap' => '', 'flag' => null])]);

    app(SearchPipeline::class)->start($this->dynopos, 'kedai runcit', ['Pasir Mas'], 20);

    $lead = Lead::first();
    expect($lead->status)->toBe(LeadStatus::TakSesuai)
        ->and($lead->message)->toBeNull()
        ->and(claudeRequests('write'))->toHaveCount(0);
});

it('sends at most 5 reviews of 300 characters to the AI', function () {
    $reviews = collect(range(1, 8))->map(fn ($i) => ['rating' => 4, 'originalText' => ['text' => "R{$i} ".str_repeat('x', 500)]])->all();
    fakePlaces([apiPlace('a')], ['a' => apiDetails('a', ['reviews' => $reviews])]);
    fakeClaude([claudeReply(['fit' => 30, 'reason' => 'r', 'hook' => '', 'gap' => '', 'flag' => null])]);

    app(SearchPipeline::class)->start($this->dynopos, 'kedai runcit', ['Pasir Mas'], 20);

    $prompt = claudeRequests('score')->first()['messages'][0]['content'];
    expect($prompt)->toContain('R5 ')->not->toContain('R6 ')
        ->and(substr_count($prompt, str_repeat('x', 297)))->toBe(5)
        ->and($prompt)->not->toContain(str_repeat('x', 298));
});

it('reuses saved AI results and only regenerates when asked', function () {
    fakeClaude();
    $lead = leadFor($this->dynopos);

    app(LeadScorer::class)->score($lead);
    app(LeadScorer::class)->score($lead->refresh());
    app(MessageWriter::class)->write($lead->refresh());
    app(MessageWriter::class)->write($lead->refresh());

    expect(claudeRequests('score'))->toHaveCount(1)
        ->and(claudeRequests('write'))->toHaveCount(1);

    app(MessageWriter::class)->write($lead->refresh(), force: true);
    expect(claudeRequests('write'))->toHaveCount(2);
});

it('flags a lead for review when the score reply is not valid JSON', function () {
    fakeClaude([claudeReply('maaf, saya tak pasti')]);

    $lead = app(LeadScorer::class)->score(leadFor($this->dynopos));

    expect($lead->fit)->toBeNull()
        ->and($lead->needs_review)->toBeTrue()
        ->and(AiUsage::count())->toBe(1);
});

// --- Writing ----------------------------------------------------------------

it('never keeps "demo" in a DynoPOS message: regenerates once, then flags Semak manual', function () {
    $demo = str_replace('POS boleh', 'Boleh buat DEMO, POS boleh', goodMessage());
    fakeClaude([], [claudeReply(['message' => $demo]), claudeReply(['message' => goodMessage()])]);

    $lead = app(MessageWriter::class)->write(leadFor($this->dynopos, ['fit' => 80]));

    expect(mb_stripos($lead->message, 'demo'))->toBeFalse()
        ->and($lead->needs_review)->toBeFalse()
        ->and(claudeRequests('write'))->toHaveCount(2)
        ->and(claudeRequests('write')->last()['messages'][0]['content'])->toContain('perkataan dilarang "demo"');
});

it('flags Semak manual after the second failure, with no third call', function () {
    $demo = str_replace('POS boleh', 'Jom tengok demo, POS boleh', goodMessage());
    fakeClaude([], [claudeReply(['message' => $demo])]);

    $lead = app(MessageWriter::class)->write(leadFor($this->dynopos, ['fit' => 80]));

    expect($lead->needs_review)->toBeTrue()
        ->and($lead->review_note)->toContain('Semak manual')->toContain('demo')
        ->and(claudeRequests('write'))->toHaveCount(2);
});

it('tells the writer that "demo" is banned for DynoPOS', function () {
    fakeClaude();

    app(MessageWriter::class)->write(leadFor($this->dynopos, ['fit' => 80]));

    expect(claudeRequests('write')->first()['system'][0]['text'])
        ->toContain('Perkataan dilarang, jangan guna langsung dalam apa-apa bentuk: demo')
        ->toContain('Kalau nak info lanjut, balas je mesej ni atau tengok dynopos.my');
});

it('uses the DynoPOS pitch variant that matches the shop type', function () {
    fakeClaude();

    app(MessageWriter::class)->write(leadFor($this->dynopos, ['fit' => 80, 'business_type' => 'restoran']));

    expect(claudeRequests('write')->first()['system'][0]['text'])->toContain('sistem POS untuk kedai makan');
});

it('regenerates messages that are too long or have no STOP', function () {
    fakeClaude([], [
        claudeReply(['message' => str_repeat('panjang ', 120).' STOP']),
        claudeReply(['message' => 'Salam, tiada pilihan berhenti di sini']),
    ]);

    $lead = app(MessageWriter::class)->write(leadFor($this->dynopos, ['fit' => 80]));

    expect($lead->needs_review)->toBeTrue()
        ->and(claudeRequests('write'))->toHaveCount(2)
        ->and(claudeRequests('write')->last()['messages'][0]['content'])->toContain('Terlalu panjang');
});

it('rejects prices that are not in the product profile', function () {
    $priced = str_replace('POS boleh', 'Harga cuma RM499 je, POS boleh', goodMessage());
    fakeClaude([], [claudeReply(['message' => $priced])]);

    $lead = app(MessageWriter::class)->write(leadFor($this->dynopos, ['fit' => 80]));

    expect($lead->needs_review)->toBeTrue()
        ->and($lead->review_note)->toContain('RM499');
});

it('allows prices that are in the product profile', function () {
    $msg = "Salam Kedai A 👋\n\nKami boleh siapkan website premium RM200 je (harga asal RM999).\n\nNak saya hantar contoh website yang kami dah buat? Info: murahwebsite.my\n\nKalau tak berminat, balas STOP, saya tak ganggu lagi 🙏";
    fakeClaude([], [claudeReply(['message' => $msg])]);

    $lead = app(MessageWriter::class)->write(leadFor($this->murah, ['fit' => 80]));

    expect($lead->needs_review)->toBeFalse()->and($lead->message)->toBe($msg);
});

it('runs the full pipeline: search to scored leads with messages', function () {
    fakePlaces([apiPlace('a'), apiPlace('b')]);
    fakeClaude();

    $search = app(SearchPipeline::class)->start($this->dynopos, 'kedai runcit', ['Pasir Mas, Kelantan'], 20)->refresh();

    expect($search->status)->toBe(SearchStatus::Done)
        ->and($search->scored_count)->toBe(2)
        ->and($search->written_count)->toBe(2)
        ->and($search->actual_cost_myr)->toBeGreaterThan(0);

    Lead::all()->each(function (Lead $lead) {
        expect($lead->fit)->toBe(80)
            ->and($lead->reason)->not->toBeEmpty()
            ->and($lead->message)->toContain('STOP')
            ->and($lead->prompt_version)->toBe('write-v2')
            ->and($lead->score_prompt_version)->toBe('score-v2');
    });

    expect(AiUsage::where('purpose', 'score')->count())->toBe(2)
        ->and(AiUsage::where('purpose', 'write')->count())->toBe(2)
        ->and(AiUsage::whereNull('search_id')->count())->toBe(0);
});

it('keeps prompt_version on the first line of every prompt file', function () {
    foreach (glob(resource_path('prompts/*.md')) as $file) {
        expect(strtok(file_get_contents($file), "\n"))->toMatch('/^prompt_version: \S+$/');
    }
});

it('treats the product fit signals as examples, not a whitelist of business types', function () {
    fakeClaude();

    app(LeadScorer::class)->score(leadFor($this->murah, ['business_type' => 'construction']));

    Http::assertSent(function (Request $r) {
        $system = json_encode($r['system']);

        return str_contains($r->url(), 'anthropic')
            && str_contains($system, 'contoh sahaja, bukan syarat')
            && str_contains($system, 'Jangan andaikan ciri');
    });
});

it('asks the AI to rewrite the product facts attractively without adding any, and closes with the default CTA when none is set', function () {
    $this->murah->update(['cta' => '', 'contact_info' => null]);
    fakeClaude();

    app(MessageWriter::class)->write(leadFor($this->murah, ['fit' => 80]));

    Http::assertSent(function (Request $r) {
        $system = json_encode($r['system'], JSON_UNESCAPED_UNICODE);

        return str_contains($r->url(), 'anthropic')
            && $r['model'] === config('dynoleads.ai.model_write')
            && str_contains($system, 'Tulis semula')
            && str_contains($system, 'Jangan tambah fakta')
            && str_contains($system, 'Kalau berminat, balas je mesej ni ya.');
    });
});
