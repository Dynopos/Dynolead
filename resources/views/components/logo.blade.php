@props(['size' => 'md'])
@php($px = ['md' => ['h-10 w-10', 40], 'lg' => ['h-14 w-14', 56]][$size] ?? ['h-10 w-10', 40])
<img src="{{ asset('images/logo-mark.webp') }}" alt="Dyno Lead" width="{{ $px[1] }}" height="{{ $px[1] }}" {{ $attributes->merge(['class' => $px[0].' shrink-0 object-contain drop-shadow-sm']) }}>
