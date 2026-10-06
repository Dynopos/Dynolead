<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    protected $fillable = ['workspace_id', 'amount_sen', 'balance_after_sen', 'reason', 'cost_sen', 'search_id', 'payment_id', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['amount_sen' => 'integer', 'balance_after_sen' => 'integer', 'cost_sen' => 'integer'];
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class);
    }

    public function label(): string
    {
        return match ($this->reason) {
            'topup' => 'Tambah baki',
            'usage' => 'Carian',
            'admin' => 'Pelarasan',
            default => $this->reason,
        };
    }
}
