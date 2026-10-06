<?php

namespace App\Exceptions;

/** A paid search ran out of balance. The search stops; what was used is charged. */
class WalletEmpty extends AccountLimitReached
{
    public const MESSAGE = 'Baki habis. Carian dihentikan. Tambah baki untuk teruskan.';

    public function __construct(string $message = self::MESSAGE)
    {
        parent::__construct($message);
    }
}
