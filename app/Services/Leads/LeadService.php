<?php

namespace App\Services\Leads;

use App\Enums\LeadStatus;
use App\Exceptions\AccountLimitReached;
use App\Exceptions\BudgetExceeded;
use App\Exceptions\ContactRuleViolation;
use App\Jobs\RegenerateLeadJob;
use App\Models\ContactLog;
use App\Models\Lead;
use App\Models\Suppression;
use App\Services\Ai\AiBudget;
use App\Services\Ai\MessageValidator;
use App\Services\Billing\WalletService;
use App\Services\Costs\PriceTable;
use App\Services\Places\PlaceRepository;
use App\Support\MalaysianPhone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Lead list, lead cards and status changes (spec §3.3). */
class LeadService
{
    public const REGENERATING = 'Sedang jana semula...';

    public function __construct(
        private ContactRules $rules,
        private PlaceRepository $places,
        private MessageValidator $validator,
        private AiBudget $budget,
        private PriceTable $prices,
        private WalletService $wallet,
    ) {}

    /** Leads that may be shown: never suppressed, never contacted for another product < 30 days. */
    public function visibleQuery(): Builder
    {
        return $this->rules->applyVisibility(Lead::query())
            ->where('leads.status', '!=', LeadStatus::Tolak->value);
    }

    /**
     * @param  array{product_id?: ?int, business_type?: ?string, status?: ?string, area?: ?string}  $filters
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;

        return $this->visibleQuery()
            ->with('product')
            ->when($filters['product_id'] ?? null, fn (Builder $q, $id) => $q->where('product_id', $id))
            ->when($filters['business_type'] ?? null, fn (Builder $q, $type) => $q->where('business_type', $type))
            ->when($filters['area'] ?? null, fn (Builder $q, $area) => $q->where('area', $area))
            ->when(
                $status,
                fn (Builder $q) => $q->where('status', $status),
                fn (Builder $q) => $q->where('status', '!=', LeadStatus::TakSesuai->value),
            )
            ->orderByRaw('CASE WHEN fit IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('fit')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Count per status for the summary bar. Tolak is counted from suppressions-aware data. */
    public function summary(?int $productId = null): array
    {
        $counts = $this->rules->applyVisibility(Lead::query())
            ->when($productId, fn (Builder $q) => $q->where('product_id', $productId))
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $tolak = Lead::query()
            ->when($productId, fn (Builder $q) => $q->where('product_id', $productId))
            ->where('status', LeadStatus::Tolak->value)
            ->count();

        return collect(LeadStatus::cases())
            ->mapWithKeys(fn (LeadStatus $s) => [$s->value => $s === LeadStatus::Tolak ? $tolak : (int) ($counts[$s->value] ?? 0)])
            ->all();
    }

    /** Distinct filter values for the dropdowns. */
    public function filterOptions(): array
    {
        return [
            'business_types' => Lead::query()->whereNotNull('business_type')->distinct()->orderBy('business_type')->pluck('business_type')->all(),
            'areas' => Lead::query()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area')->all(),
        ];
    }

    /**
     * Build cards, loading Google data from the short cache (or Places when expired).
     * Shops whose phone is on the STOP list are dropped here.
     *
     * @param  iterable<Lead>  $leads
     * @return Collection<int, LeadCard>
     */
    public function cards(iterable $leads): Collection
    {
        return collect($leads)
            ->map(fn (Lead $lead) => $this->card($lead))
            ->reject(fn (LeadCard $card) => $card->phone !== null && $this->rules->isSuppressed(null, $card->phone))
            ->values();
    }

    public function card(Lead $lead, ?string $message = null): LeadCard
    {
        $place = null;
        try {
            $place = $this->places->forDisplay($lead->place_id);
        } catch (Throwable $e) {
            report($e);
        }

        $message ??= $lead->message;
        $phone = $place['phone'] ?? null;
        $problems = $message === null ? ['Mesej belum dijana'] : $this->validator->problems($lead->product, $message);
        $messageOk = $problems === [] && ! $lead->needs_review;

        return new LeadCard(
            lead: $lead,
            name: $place['name'] ?? null,
            rating: $place['rating'] ?? null,
            reviewCount: (int) ($place['review_count'] ?? 0),
            address: $place['address'] ?? null,
            phone: $phone,
            mapsUri: $place['maps_uri'] ?? null,
            isMobile: MalaysianPhone::isMobile($phone),
            isLandline: ! MalaysianPhone::isMobile($phone),
            // Only a link: a human opens WhatsApp and presses send. Never sent automatically.
            whatsappLink: $messageOk ? MalaysianPhone::waLink($phone, (string) $message) : null,
            messageOk: $messageOk,
            messageProblems: $lead->needs_review && $problems === [] ? [] : $problems,
            placeDataMissing: $place === null,
        );
    }

    /** @throws ContactRuleViolation */
    public function changeStatus(Lead $lead, LeadStatus $status): Lead
    {
        if ($lead->status === LeadStatus::Tolak || $this->rules->isSuppressed($lead->place_id)) {
            throw new ContactRuleViolation('Kedai ni dah dalam senarai STOP. Status tak boleh diubah.');
        }

        if ($status === $lead->status) {
            return $lead;
        }

        return DB::transaction(function () use ($lead, $status) {
            match ($status) {
                LeadStatus::Tolak => $this->suppress($lead),
                LeadStatus::Dihantar => $this->markContacted($lead),
                LeadStatus::Reply, LeadStatus::Deal => $lead->next_followup_at = null,
                default => null,
            };

            $lead->status = $status;
            $lead->status_changed_at = now();
            $lead->save();

            return $lead;
        });
    }

    /** STOP is permanent: suppress by place_id and phone, for every product. */
    private function suppress(Lead $lead): void
    {
        $phone = null;
        try {
            $phone = ($this->places->cached($lead->place_id) ?? $this->places->forDisplay($lead->place_id))['phone'] ?? null;
        } catch (Throwable $e) {
            report($e);
        }

        Suppression::query()->firstOrCreate(
            ['place_id' => $lead->place_id],
            ['phone' => MalaysianPhone::canonical($phone), 'reason' => 'Tolak/STOP ('.$lead->product->name.')'],
        );

        $lead->next_followup_at = null;
    }

    /** One shop, one product in 30 days. */
    private function markContacted(Lead $lead): void
    {
        if ($this->rules->contactedRecently($lead->place_id, exceptProductId: $lead->product_id)) {
            throw new ContactRuleViolation('Kedai ni dah dihubungi untuk produk lain dalam '.$this->rules->windowDays().' hari. Tunggu dulu.');
        }

        ContactLog::query()->create([
            'place_id' => $lead->place_id,
            'product_id' => $lead->product_id,
            'lead_id' => $lead->id,
            'contacted_at' => now(),
        ]);

        $lead->contacted_at = now();
        $lead->next_followup_at = now()->addDays((int) config('dynoleads.rules.followup_after_days', 3));
    }

    public function saveNotes(Lead $lead, ?string $notes): void
    {
        $lead->forceFill(['notes' => filled($notes) ? mb_substr(trim($notes), 0, 2000) : null])->save();
    }

    /**
     * Bob edits the message by hand. Same checks as AI messages.
     *
     * @return array<int, string> problems; empty when saved
     */
    public function saveMessage(Lead $lead, string $message): array
    {
        $message = trim($message);
        $problems = $this->validator->problems($lead->product, $message);

        if ($problems === []) {
            $lead->forceFill(['message' => $message, 'needs_review' => false, 'review_note' => null])->save();
        }

        return $problems;
    }

    /** Estimated MYR cost of regenerating one message (shown before asking to confirm). */
    public function regenerateEstimate(Lead $lead): ?float
    {
        $model = (string) config('dynoleads.ai.model_write');
        if (! $this->prices->isModelConfigured($model)) {
            return null;
        }

        $d = config('dynoleads.estimate_defaults');
        $write = $this->prices->aiCostMyr($model, $d['write_input_tokens'], $d['write_output_tokens']);

        if ($lead->isFit()) {
            return round($write, 4);
        }

        $scoreModel = (string) config('dynoleads.ai.model_score');

        return $this->prices->isModelConfigured($scoreModel)
            ? round($write + $this->prices->aiCostMyr($scoreModel, $d['score_input_tokens'], $d['score_output_tokens']), 4)
            : null;
    }

    /** User pressed "Jana semula". Runs in the queue. */
    public function requestRegenerate(Lead $lead): void
    {
        if ($this->rules->isSuppressed($lead->place_id) || $lead->status === LeadStatus::Tolak) {
            throw new ContactRuleViolation('Kedai ni dalam senarai STOP. Mesej tak boleh dijana.');
        }

        if ($reason = $this->wallet->accessBlocker()) {
            throw new AccountLimitReached($reason);
        }

        if ($this->regenerationsLeft($lead) <= 0) {
            throw new AccountLimitReached('Had jana semula untuk lead ini dah dicapai. Edit mesej secara manual.');
        }

        if ($this->budget->isExhausted()) {
            throw new BudgetExceeded;
        }

        $lead->forceFill(['needs_review' => false, 'review_note' => self::REGENERATING])->save();
        $lead->increment('regenerate_count');

        // A lead judged unfit is scored again (the product profile or prompt may have changed);
        // otherwise only the message is rewritten.
        RegenerateLeadJob::dispatch($lead->id, rescore: ! $lead->isFit());
    }

    /** Free regenerations left for this lead (unlimited for the internal workspace). */
    public function regenerationsLeft(Lead $lead): int
    {
        if ($this->wallet->isUnlimited()) {
            return PHP_INT_MAX;
        }

        return max(0, (int) config('billing.max_regenerations_per_lead', 3) - (int) $lead->regenerate_count);
    }

    /** Leads marked "Dah hantar" today, for the 10–15 per day guidance. */
    public function sentToday(): int
    {
        return ContactLog::query()->where('contacted_at', '>=', now()->startOfDay())->count();
    }
}
