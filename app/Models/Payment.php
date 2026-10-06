<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'user_id', 'pack', 'credits', 'amount_sen', 'currency', 'status', 'chip_purchase_id',
        'checkout_url', 'is_test', 'paid_at', 'period_start', 'period_end', 'note',
    ];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'credits' => 'integer',
            'paid_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): string
    {
        return 'DL-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function amountMyr(): float
    {
        return $this->amount_sen / 100;
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'manual'], true);
    }
}
