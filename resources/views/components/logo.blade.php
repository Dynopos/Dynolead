@props(['size' => 'md'])
@php($px = ['md' => ['h-9 w-9', 36], 'lg' => ['h-12 w-12', 48]][$size] ?? ['h-9 w-9', 36])
<img src="{{ asset('images/logo-mark.webp') }}" alt="Dyno Leads" width="{{ $px[1] }}" height="{{ $px[1] }}" {{ $attributes->merge(['class' => $px[0].' shrink-0 rounded-full drop-shadow-sm']) }}>
