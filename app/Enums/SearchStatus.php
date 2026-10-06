<?php

namespace App\Enums;

enum SearchStatus: string
{
    case Pending = 'pending';
    case Searching = 'searching';
    case Filtering = 'filtering';
    case Details = 'details';
    case Scoring = 'scoring';
    case Writing = 'writing';
    case Done = 'done';
    case Failed = 'failed';
    case BudgetExceeded = 'budget_exceeded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Dalam giliran',
            self::Searching => 'Sedang cari',
            self::Filtering => 'Sedang tapis',
            self::Details => 'Ambil butiran kedai',
            self::Scoring => 'Sedang nilai',
            self::Writing => 'Sedang tulis mesej',
            self::Done => 'Siap',
            self::Failed => 'Gagal',
            self::BudgetExceeded => 'Had kos AI dicapai',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Failed, self::BudgetExceeded], true);
    }

    /** Progress step 1..4 for the UI: dicari → ditapis → dinilai → siap. */
    public function step(): int
    {
        return match ($this) {
            self::Pending, self::Searching => 1,
            self::Filtering, self::Details => 2,
            self::Scoring, self::Writing => 3,
            default => 4,
        };
    }
}
