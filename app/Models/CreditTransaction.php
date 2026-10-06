<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    protected $fillable = ['workspace_id', 'amount', 'balance_after', 'reason', 'search_id', 'payment_id', 'user_id', 'note'];

    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class);
    }

    public function label(): string
    {
        return match ($this->reason) {
            'signup_bonus' => 'Kredit percuma',
            'purchase' => 'Beli kredit',
            'search' => 'Carian',
            'refund' => 'Dipulangkan',
            'admin' => 'Pelarasan',
            default => $this->reason,
        };
    }
}
