<?php

namespace App\Services\Ai;

final class ClaudeResponse
{
    public function __construct(
        public readonly string $model,
        public readonly string $text,
        public readonly ?string $stopReason,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheReadTokens,
        public readonly int $cacheWriteTokens,
    ) {}

    /** Decoded JSON object from the text, or null when the model did not return one. */
    public function json(): ?array
    {
        $text = trim($this->text);

        // Tolerate a ```json fence or text around the object.
        if (preg_match('/\{.*\}/s', $text, $m) === 1) {
            $text = $m[0];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
