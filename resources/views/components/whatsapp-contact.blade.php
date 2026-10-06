{{-- Link to the company's own WhatsApp (prospects contacting us). Hidden when no mobile number is set. --}}
@props(['variant' => 'link', 'message' => 'Salam, saya nak tahu tentang Dyno Leads.'])
@php
    $raw = config('dynoleads.company.whatsapp');
    $href = \App\Support\MalaysianPhone::waLink($raw, $message);
    $local = \App\Support\MalaysianPhone::toLocal($raw);
    $display = $local ? substr($local, 0, 3).'-'.substr($local, 3, 3).' '.substr($local, 6) : null;
@endphp
@if ($href)
    @if ($variant === 'float')
        <a href="{{ $href }}" target="_blank" rel="noopener" aria-label="WhatsApp kami di {{ $display }}"
           {{ $attributes->merge(['class' => 'fixed bottom-4 right-4 z-40 inline-flex items-center gap-2 rounded-full bg-[#25d366] px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-[#1ebe5b]']) }}>
            <x-icon name="chat" class="h-5 w-5" /> WhatsApp kami
        </a>
    @else
        <a href="{{ $href }}" target="_blank" rel="noopener" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
            <x-icon name="chat" class="h-4 w-4" /> WhatsApp {{ $display }}
        </a>
    @endif
@endif
