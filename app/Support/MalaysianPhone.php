<?php

namespace App\Support;

/**
 * Malaysian phone helper.
 *
 * Mobile (01x...) numbers become 601x... for wa.me links. Landlines (03-09)
 * get no WhatsApp button: the UI shows "Telefon atau singgah" instead.
 */
final class MalaysianPhone
{
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /** Local format with leading zero, e.g. 0111496842. Null if not a Malaysian number. */
    public static function toLocal(?string $phone): ?string
    {
        $digits = self::digits($phone);

        if (str_starts_with($digits, '60')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! str_starts_with($digits, '0') || strlen($digits) < 9 || strlen($digits) > 11) {
            return null;
        }

        return $digits;
    }

    public static function isMobile(?string $phone): bool
    {
        $local = self::toLocal($phone);

        return $local !== null
            && preg_match('/^01\d{8,9}$/', $local) === 1;
    }

    public static function isLandline(?string $phone): bool
    {
        $local = self::toLocal($phone);

        return $local !== null && preg_match('/^0[3-9]\d{7,9}$/', $local) === 1;
    }

    /** International form used for storage and suppression matching, e.g. 60111496842. */
    public static function canonical(?string $phone): ?string
    {
        $local = self::toLocal($phone);

        return $local === null ? null : '6'.$local;
    }

    /** Number for wa.me (601x...), or null for landlines and unknown numbers. */
    public static function waNumber(?string $phone): ?string
    {
        return self::isMobile($phone) ? '6'.self::toLocal($phone) : null;
    }

    /** wa.me link with the message prefilled. A human still has to press send. */
    public static function waLink(?string $phone, string $message): ?string
    {
        $number = self::waNumber($phone);

        if ($number === null) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }
}
