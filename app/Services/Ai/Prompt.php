<?php

namespace App\Services\Ai;

final class Prompt
{
    public function __construct(
        public readonly string $version,
        public readonly string $system,
        public readonly string $user,
    ) {}
}
