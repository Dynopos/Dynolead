<?php

/*
| CLAUDE.md "Peraturan yang tidak boleh dilanggar" — code-level guards.
| Rules 2, 3, 5 and 6 are covered in detail by Phase0AcceptanceTest, AiTest,
| RuleFilterTest and LeadsScreenTest; this file covers 1, 4 and 7.
*/

use App\Models\Lead;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

function trackedFiles(): array
{
    $process = new Process(['git', 'ls-files'], base_path());
    $process->run();

    return array_filter(explode("\n", trim($process->getOutput())));
}

it('rule 1: never sends WhatsApp messages and uses no unofficial WhatsApp API', function () {
    $code = collect(File::allFiles(app_path()))
        ->map(fn ($f) => strtolower($f->getContents()))
        ->implode("\n");

    foreach (['graph.facebook.com', 'waha', 'z-api', 'api.whatsapp', 'wppconnect', 'baileys', 'whatsapp-web', 'twilio'] as $needle) {
        expect($code)->not->toContain($needle);
    }

    // The only WhatsApp output is a wa.me link for a human to open.
    expect(substr_count($code, 'wa.me'))->toBeGreaterThan(0)
        ->and($code)->not->toContain('/messages\', [\'to');
});

it('rule 4: leads keep only place_id from Google, not names, phones or reviews', function () {
    $columns = Schema::getColumnListing('leads');

    expect($columns)->toContain('place_id')
        ->and(array_intersect($columns, ['name', 'phone', 'address', 'rating', 'reviews', 'website', 'review_count']))->toBe([]);

    expect(Schema::getColumnListing('searches'))
        ->not->toContain('places_payload');
});

it('rule 4: Google attribution is shown wherever Places data is shown', function () {
    foreach (['leads-page', 'followups-page'] as $view) {
        expect(file_get_contents(resource_path("views/livewire/{$view}.blade.php")))->toContain('<x-google-attribution');
    }
});

it('rule 7: no secrets are committed', function () {
    foreach (trackedFiles() as $file) {
        $path = base_path($file);
        if (! is_file($path) || filesize($path) > 2_000_000 || str_ends_with($file, '.lock')) {
            continue;
        }
        $contents = file_get_contents($path);

        expect(preg_match('/sk-ant-[A-Za-z0-9_-]{20,}/', $contents))->toBe(0, "Anthropic key in {$file}")
            ->and(preg_match('/AIza[0-9A-Za-z_-]{35}/', $contents))->toBe(0, "Google key in {$file}");
    }

    expect(trackedFiles())->not->toContain('.env');
});

it('rule 7: model names come from .env, not code', function () {
    $code = collect(File::allFiles(app_path()))->map->getContents()->implode("\n");

    expect($code)->not->toMatch('/claude-(haiku|sonnet|opus|fable)-\d/');
});

it('lead factory still works for the other tests', function () {
    expect(Lead::factory()->make()->place_id)->toStartWith('place_');
});
