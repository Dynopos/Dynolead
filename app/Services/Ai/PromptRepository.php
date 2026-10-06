<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Loads resources/prompts/{name}.md.
 *
 * Line 1: "prompt_version: <version>". Then a "## SYSTEM" section (stable
 * per product, cached by Claude) and a "## USER" section (per shop data).
 * Placeholders are {{name}}.
 */
class PromptRepository
{
    public function version(string $name): string
    {
        return $this->parse($name)['version'];
    }

    public function render(string $name, array $systemVars, array $userVars): Prompt
    {
        $parsed = $this->parse($name);

        return new Prompt(
            version: $parsed['version'],
            system: $this->fill($parsed['system'], $systemVars),
            user: $this->fill($parsed['user'], $userVars),
        );
    }

    private function parse(string $name): array
    {
        $path = resource_path("prompts/{$name}.md");

        if (! is_file($path)) {
            throw new RuntimeException("Prompt {$name}.md tidak dijumpai.");
        }

        $raw = str_replace("\r\n", "\n", (string) file_get_contents($path));

        if (preg_match('/\Aprompt_version:\s*(\S+)/', $raw, $m) !== 1) {
            throw new RuntimeException("Baris pertama {$name}.md mesti 'prompt_version: ...'.");
        }

        $parts = preg_split('/^## (SYSTEM|USER)\s*$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
        $sections = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $sections[$parts[$i]] = trim($parts[$i + 1] ?? '');
        }

        if (! isset($sections['SYSTEM'], $sections['USER'])) {
            throw new RuntimeException("{$name}.md perlu bahagian '## SYSTEM' dan '## USER'.");
        }

        return ['version' => $m[1], 'system' => $sections['SYSTEM'], 'user' => $sections['USER']];
    }

    private function fill(string $template, array $vars): string
    {
        $pairs = [];
        foreach ($vars as $key => $value) {
            $pairs['{{'.$key.'}}'] = (string) $value;
        }

        return trim(strtr($template, $pairs));
    }
}
