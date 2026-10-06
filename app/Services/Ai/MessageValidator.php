<?php

namespace App\Services\Ai;

use App\Models\Product;

/**
 * Post-generation check, in code (spec §5.3):
 *  - no banned_words (case-insensitive),
 *  - at most 900 characters,
 *  - contains "STOP",
 *  - no RM amount that is not in the product profile (no invented prices).
 */
class MessageValidator
{
    /** @return array<int, string> problems in BM; empty when the message is OK */
    public function problems(Product $product, ?string $message): array
    {
        $message = (string) $message;
        $problems = [];

        if (trim($message) === '') {
            return ['Mesej kosong'];
        }

        foreach ($product->bannedWords() as $word) {
            if (mb_stripos($message, $word) !== false) {
                $problems[] = "Ada perkataan dilarang \"{$word}\"";
            }
        }

        $max = (int) config('dynoleads.rules.message_max_chars', 900);
        if (mb_strlen($message) > $max) {
            $problems[] = 'Terlalu panjang ('.mb_strlen($message)." aksara, had {$max})";
        }

        if (! str_contains($message, 'STOP')) {
            $problems[] = 'Tiada pilihan STOP';
        }

        $profile = $this->normaliseMoney(implode(' ', array_merge(
            [$product->pitch_core, $product->cta],
            array_column($product->pitch_variants ?? [], 'pitch'),
        )));

        preg_match_all('/RM\s?\d[\d,.]*/iu', $message, $matches);
        foreach (array_unique($matches[0]) as $amount) {
            $clean = $this->normaliseMoney(rtrim($amount, '.,'));
            if (! str_contains($profile, $clean)) {
                $problems[] = "Harga {$amount} tiada dalam profil produk";
            }
        }

        return $problems;
    }

    public function passes(Product $product, ?string $message): bool
    {
        return $this->problems($product, $message) === [];
    }

    private function normaliseMoney(string $text): string
    {
        return preg_replace('/RM\s+/iu', 'RM', mb_strtoupper($text)) ?? '';
    }
}
