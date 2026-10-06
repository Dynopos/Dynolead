<?php

namespace App\Services\Products;

use App\Exceptions\AccountLimitReached;
use App\Models\Product;
use Illuminate\Support\Str;

/** Create and update product profiles (spec §3.1). */
class ProductService
{
    /** Split "a, b, c" or one-per-line text into a clean list. */
    public static function splitList(?string $text): array
    {
        return collect(preg_split('/[\n,]+/', (string) $text))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array  $data  validated form data
     */
    /** @throws AccountLimitReached when a new product would exceed the limit */
    public function save(?Product $product, array $data): Product
    {
        $max = (int) config('billing.max_products', 20);
        if ($product === null && Product::query()->count() >= $max) {
            throw new AccountLimitReached("Had {$max} produk setiap akaun dah dicapai. Edit produk sedia ada atau hubungi kami.");
        }

        $product ??= new Product;

        $variants = collect($data['pitch_variants'] ?? [])
            ->map(fn (array $v) => [
                'key' => Str::slug(trim($v['key'] ?? '')) ?: Str::slug(Str::limit(trim($v['pitch'] ?? ''), 20, '')),
                'match' => self::splitList($v['match'] ?? ''),
                'pitch' => trim($v['pitch'] ?? ''),
            ])
            ->filter(fn (array $v) => $v['pitch'] !== '' && $v['match'] !== [])
            ->values()
            ->all();

        $product->fill([
            'name' => trim($data['name']),
            'sender_name' => trim($data['sender_name']),
            'company' => trim($data['company']),
            'pitch_core' => trim($data['pitch_core']),
            'pitch_variants' => $variants ?: null,
            'cta' => trim($data['cta']),
            'banned_words' => self::splitList($data['banned_words'] ?? ''),
            'fit_signals' => trim((string) ($data['fit_signals'] ?? '')) ?: null,
            'filters' => [
                'min_rating' => (float) ($data['min_rating'] ?? 0),
                'min_reviews' => (int) ($data['min_reviews'] ?? 0),
                'require_no_website' => (bool) ($data['require_no_website'] ?? false),
            ] + array_diff_key($product->filters ?? [], array_flip(['min_rating', 'min_reviews', 'require_no_website'])),
            'default_place_types' => self::splitList($data['default_place_types'] ?? ''),
            'contact_info' => trim((string) ($data['contact_info'] ?? '')) ?: null,
            'active' => (bool) ($data['active'] ?? true),
        ]);

        if (! $product->exists) {
            $product->slug = $this->uniqueSlug($product->name);
        }

        $product->save();

        return $product;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $i = 2;

        // Product query is scoped to the current workspace: slugs are unique per workspace.
        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
