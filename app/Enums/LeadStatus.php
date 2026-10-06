<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Baru = 'baru';
    case Dihantar = 'dihantar';
    case Reply = 'reply';
    case Deal = 'deal';
    case Tolak = 'tolak';
    case TakSesuai = 'tak_sesuai';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Dihantar => 'Dah hantar',
            self::Reply => 'Reply',
            self::Deal => 'Deal',
            self::Tolak => 'Tolak / STOP',
            self::TakSesuai => 'Tak sesuai',
        };
    }

    /** Tailwind classes: [dot, badge]. */
    public function colors(): array
    {
        return match ($this) {
            self::Baru => ['bg-sky-500', 'bg-sky-50 text-sky-700 ring-sky-200'],
            self::Dihantar => ['bg-violet-500', 'bg-violet-50 text-violet-700 ring-violet-200'],
            self::Reply => ['bg-amber-500', 'bg-amber-50 text-amber-800 ring-amber-200'],
            self::Deal => ['bg-emerald-500', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
            self::Tolak => ['bg-rose-500', 'bg-rose-50 text-rose-700 ring-rose-200'],
            self::TakSesuai => ['bg-slate-400', 'bg-slate-100 text-slate-600 ring-slate-200'],
        };
    }

    /** Statuses the user can pick from the dropdown. */
    public static function selectable(): array
    {
        return [self::Baru, self::Dihantar, self::Reply, self::Deal, self::Tolak];
    }
}
