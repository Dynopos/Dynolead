<?php

namespace App\Services\Leads;

final class FilterResult
{
    /**
     * @param  array<int, array>  $passed  places that passed
     * @param  array<string, string>  $rejected  place_id => reason
     */
    public function __construct(
        public readonly array $passed = [],
        public readonly array $rejected = [],
    ) {}
}
