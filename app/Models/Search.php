<?php

namespace App\Models;

use App\Enums\SearchStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Search extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'product_id', 'business_type', 'areas', 'max_candidates', 'is_trial', 'charged_sen', 'status',
        'candidate_place_ids', 'passed_place_ids', 'found_count', 'passed_count', 'lead_count',
        'scored_count', 'written_count', 'places_calls', 'estimate_myr', 'actual_cost_myr',
        'rejections', 'error', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'areas' => 'array',
            'candidate_place_ids' => 'array',
            'passed_place_ids' => 'array',
            'rejections' => 'array',
            'status' => SearchStatus::class,
            'estimate_myr' => 'float',
            'actual_cost_myr' => 'float',
            'finished_at' => 'datetime',
            'is_trial' => 'boolean',
            'charged_sen' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function markStatus(SearchStatus $status, ?string $error = null): void
    {
        // A cancelled search stays cancelled, even if a job that was already running
        // reaches its next step, finishes or fails afterwards.
        if ($status !== SearchStatus::Cancelled && $this->isCancelled()) {
            $this->status = SearchStatus::Cancelled;

            return;
        }

        $this->forceFill([
            'status' => $status,
            'error' => $error ?? $this->error,
            'finished_at' => $status->isFinished() ? now() : $this->finished_at,
        ])->save();
    }

    /** Read from the database: the user may cancel while a job holds an older copy. */
    public function isCancelled(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return static::query()->withoutGlobalScope('workspace')->whereKey($this->getKey())
            ->toBase()->value('status') === SearchStatus::Cancelled->value;
    }

    public function addRejection(string $placeId, string $reason): void
    {
        $rejections = $this->rejections ?? [];
        $rejections[$placeId] = $reason;
        $this->rejections = $rejections;
    }
}
