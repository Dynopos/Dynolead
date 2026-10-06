<?php

use App\Support\MalaysianPhone;

it('turns 01x mobile numbers into 601x for wa.me', function (string $input, string $expected) {
    expect(MalaysianPhone::waNumber($input))->toBe($expected)
        ->and(MalaysianPhone::isMobile($input))->toBeTrue();
})->with([
    ['011-1149 6842', '601111496842'],
    ['018-792 2844', '60187922844'],
    ['012 345 6789', '60123456789'],
    ['+60 11-1149 6842', '601111496842'],
    ['60187922844', '60187922844'],
]);

it('gives landlines 03 to 09 no WhatsApp number', function (string $input) {
    expect(MalaysianPhone::waNumber($input))->toBeNull()
        ->and(MalaysianPhone::waLink($input, 'Salam'))->toBeNull()
        ->and(MalaysianPhone::isLandline($input))->toBeTrue();
})->with([
    '03-2345 6789',
    '09-790 1234',
    '+60 9-790 1234',
    '07-123 4567',
    '04-555 1234',
]);

it('rejects numbers that are not Malaysian', function (?string $input) {
    expect(MalaysianPhone::waNumber($input))->toBeNull()
        ->and(MalaysianPhone::toLocal($input))->toBeNull();
})->with([null, '', '+65 6123 4567', '1300-88-1234', '12345']);

it('builds a wa.me link with the message encoded', function () {
    $link = MalaysianPhone::waLink('011-1149 6842', "Salam Kedai A 👋\n\nBalas STOP & saya tak ganggu");

    expect($link)->toStartWith('https://wa.me/601111496842?text=')
        ->and(rawurldecode(substr($link, strpos($link, '=') + 1)))->toBe("Salam Kedai A 👋\n\nBalas STOP & saya tak ganggu");
});

it('normalises phones to one canonical form for suppression matching', function () {
    expect(MalaysianPhone::canonical('011-1149 6842'))->toBe('601111496842')
        ->and(MalaysianPhone::canonical('+60 11-1149 6842'))->toBe('601111496842')
        ->and(MalaysianPhone::canonical('09-790 1234'))->toBe('6097901234');
});
