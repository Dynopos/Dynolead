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

    /** Statuses the user can pick from the dropdown. */
    public static function selectable(): array
    {
        return [self::Baru, self::Dihantar, self::Reply, self::Deal, self::Tolak];
    }
}
