<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'place_id', 'product_id', 'search_id', 'status', 'business_type', 'area',
        'fit', 'reason', 'hook', 'gap', 'flag', 'message', 'needs_review', 'review_note',
        'score_prompt_version', 'prompt_version', 'followup_message',
        'contacted_at', 'next_followup_at', 'status_changed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'fit' => 'integer',
            'needs_review' => 'boolean',
            'contacted_at' => 'datetime',
            'next_followup_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class);
    }

    public function isScored(): bool
    {
        return $this->fit !== null;
    }

    public function isFit(): bool
    {
        return $this->fit !== null && $this->fit >= (int) config('dynoleads.ai.fit_threshold');
    }
}
