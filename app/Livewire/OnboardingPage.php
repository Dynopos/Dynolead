<?php

namespace App\Livewire;

use App\Exceptions\AccountLimitReached;
use App\Services\Ai\BusinessAnalyst;
use App\Services\Products\ProductService;
use App\Support\Tenancy\CurrentWorkspace;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** First-run wizard: create the first product profile, then go search. */
#[Layout('components.layouts.guest')]
#[Title('Mula')]
class OnboardingPage extends Component
{
    public int $step = 1;

    public string $template = '';

    public string $name = '';

    public string $pitch_core = '';

    public string $cta = '';

    public string $fit_signals = '';

    public string $default_place_types = '';

    public string $banned_words = '';

    public bool $require_no_website = false;

    public function pick(string $key): void
    {
        $t = config("product_templates.{$key}");
        abort_if($t === null, 404);

        $this->template = $key;
        $this->pitch_core = $t['pitch_core'];
        $this->cta = $t['cta'];
        $this->fit_signals = $t['fit_signals'];
        $this->default_place_types = implode(', ', $t['default_place_types']);
        $this->require_no_website = $t['require_no_website'];
        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function finish(ProductService $products, CurrentWorkspace $current, BusinessAnalyst $analyst)
    {
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'pitch_core' => ['required', 'string', 'max:600', 'not_regex:/\[[^\]]+\]/'],
            'cta' => 'nullable|string|max:300',
            'fit_signals' => 'nullable|string|max:1000',
            'default_place_types' => 'nullable|string|max:500',
            'banned_words' => 'nullable|string|max:500',
            'require_no_website' => 'boolean',
        ], [
            'required' => 'Medan ini wajib diisi.',
            'pitch_core.not_regex' => 'Gantikan teks dalam [kurungan] dengan maklumat produk anda.',
        ]);

        $workspace = $current->get();

        try {
            $product = $products->save(null, $data + [
                'sender_name' => $workspace->sender_name ?: auth()->user()->name,
                'company' => $workspace->name,
                // Loose rule filter: the business analysis and AI scoring do the ranking.
                'min_rating' => 3.0,
                'min_reviews' => 3,
                'active' => true,
            ]);
        } catch (AccountLimitReached $e) {
            $this->addError('name', $e->getMessage());

            return null;
        }

        // Analyse the business before the first search. Never blocks onboarding.
        try {
            $analyst->analyse($product);
        } catch (\Throwable $e) {
            report($e);
        }

        $workspace->forceFill(['onboarded_at' => now()])->save();

        return $this->redirectRoute('search');
    }

    public function skip(CurrentWorkspace $current)
    {
        $current->get()->forceFill(['onboarded_at' => now()])->save();

        return $this->redirectRoute('products');
    }

    public function render()
    {
        return view('livewire.onboarding-page', [
            'templates' => config('product_templates'),
        ]);
    }
}
