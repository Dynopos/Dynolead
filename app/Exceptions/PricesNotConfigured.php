<?php

namespace App\Exceptions;

use RuntimeException;

class PricesNotConfigured extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("Harga model {$model} belum diisi dalam config/ai_prices.php. Isi harga semasa dahulu.");
    }

    public static function forCurrency(): self
    {
        return new self('Kadar usd_to_myr belum diisi dalam config/ai_prices.php.');
    }
}
