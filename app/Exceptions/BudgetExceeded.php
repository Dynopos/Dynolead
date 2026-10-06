<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown before a Claude call that would push this month's AI cost over the limit. */
class BudgetExceeded extends RuntimeException
{
    public const MESSAGE = 'Had kos AI bulan ini dah dicapai. Naikkan had di halaman Kos atau tunggu bulan depan.';

    public function __construct(string $message = self::MESSAGE)
    {
        parent::__construct($message);
    }
}
