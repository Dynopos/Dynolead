<?php

namespace App\Livewire;

use App\Exceptions\AccountLimitReached;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\ClaudeException;
use App\Exceptions\PricesNotConfigured;
use App\Models\Product;
use App\Services\Ai\BusinessAnalyst;
use App\Services\Products\ProductService;
use App\Support\Tenancy\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Produk')]
class ProductsPage extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $sender_name = '';

    public string $company = '';

    public string $pitch_core = '';

    /** @var array<int, array{key: string, match: string, pitch: string}> */
    public array $pitch_variants = [];

    public string $cta = '';

    public string $banned_words = '';

    public string $fit_signals = '';

    public string $min_rating = '0';

    public string $min_reviews = '0';

    public bool $require_no_website = false;

    public string $default_place_types = '';

    public string $contact_info = '';

    public bool $active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'sender_name' => 'required|string|max:60',
            'company' => 'required|string|max:150',
            'pitch_core' => 'required|string|max:600',
            'pitch_variants' => 'array|max:10',
            'pitch_variants.*.key' => 'nullable|string|max:40',
            'pitch_variants.*.match' => 'nullable|string|max:300',
            'pitch_variants.*.pitch' => 'nullable|string|max:600',
            'cta' => 'nullable|string|max:300',
            'banned_words' => 'nullable|string|max:500',
            'fit_signals' => 'nullable|string|max:1000',
            'min_rating' => 'required|numeric|min:0|max:5',
            'min_reviews' => 'required|integer|min:0|max:100000',
            'require_no_website' => 'boolean',
            'default_place_types' => 'nullable|string|max:500',
            'contact_info' => 'nullable|string|max:200',
            'active' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => 'Medan ini wajib diisi.',
            'max' => 'Terlalu panjang.',
            'numeric' => 'Mesti nombor.',
            'integer' => 'Mesti nombor bulat.',
            'min_rating.max' => 'Rating maksimum 5.',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $workspace = app(CurrentWorkspace::class)->get();
        $this->sender_name = (string) ($workspace?->sender_name ?: auth()->user()?->name);
        $this->company = (string) $workspace?->name;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $p = Product::query()->findOrFail($id);
        $this->resetErrorBag();

        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->sender_name = $p->sender_name;
        $this->company = $p->company;
        $this->pitch_core = $p->pitch_core;
        $this->pitch_variants = collect($p->pitch_variants ?? [])->map(fn ($v) => [
            'key' => (string) ($v['key'] ?? ''),
            'match' => implode(', ', $v['match'] ?? []),
            'pitch' => (string) ($v['pitch'] ?? ''),
        ])->all();
        $this->cta = (string) $p->cta;
        $this->banned_words = implode(', ', $p->banned_words ?? []);
        $this->fit_signals = (string) $p->fit_signals;
        $this->min_rating = (string) $p->minRating();
        $this->min_reviews = (string) $p->minReviews();
        $this->require_no_website = $p->requiresNoWebsite();
        $this->default_place_types = implode(', ', $p->default_place_types ?? []);
        $this->contact_info = (string) $p->contact_info;
        $this->active = $p->active;
        $this->showForm = true;
    }

    public function addVariant(): void
    {
        $this->pitch_variants[] = ['key' => '', 'match' => '', 'pitch' => ''];
    }

    public function removeVariant(int $index): void
    {
        unset($this->pitch_variants[$index]);
        $this->pitch_variants = array_values($this->pitch_variants);
    }

    /** Message under a product card after "Analisis bisnes" (errors only). */
    public array $analysisNote = [];

    public function analyse(int $id, BusinessAnalyst $analyst): void
    {
        // Workspace-scoped: another customer's product is simply not found.
        $this->runAnalysis(Product::query()->findOrFail($id), $analyst);
    }

    private function runAnalysis(Product $product, BusinessAnalyst $analyst): void
    {
        unset($this->analysisNote[$product->id]);

        try {
            $analyst->analyse($product);
        } catch (AccountLimitReached|BudgetExceeded|PricesNotConfigured $e) {
            $this->analysisNote[$product->id] = $e->getMessage();
        } catch (ClaudeException $e) {
            report($e);
            $this->analysisNote[$product->id] = 'Analisis AI tak berjaya kali ini. Cuba lagi sebentar.';
        }
    }

    public function save(ProductService $service, BusinessAnalyst $analyst): void
    {
        $data = $this->validate();

        try {
            $product = $service->save($this->editingId ? Product::query()->findOrFail($this->editingId) : null, $data);
        } catch (AccountLimitReached $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->resetForm();

        // First save: analyse the business straight away so Cari can suggest the best targets.
        if ($product->analysis === null) {
            $this->runAnalysis($product, $analyst);
        }

        session()->flash('status', "Produk {$product->name} disimpan.");
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset();
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.products-page', [
            'products' => Product::query()->orderBy('name')->get(),
        ]);
    }
}
