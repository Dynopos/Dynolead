@props(['message', 'tone' => 'out'])
{{-- WhatsApp-style preview of the message Bob will send. --}}
@php($long = mb_strlen($message) > 240 || substr_count($message, "\n") > 6)
<div x-data="{ open: false }" class="rounded-xl bg-[#efeae2] p-3">
    <div class="relative ml-6 rounded-lg rounded-tr-none {{ $tone === 'out' ? 'bg-[#d9fdd3]' : 'bg-white' }} px-3 py-2 text-[14px] leading-relaxed text-slate-800 shadow-sm">
        <span class="absolute -right-2 top-0 h-0 w-0 border-l-[10px] border-t-[10px] border-l-transparent {{ $tone === 'out' ? 'border-t-[#d9fdd3]' : 'border-t-white' }}"></span>
        <p class="whitespace-pre-line" :class="open || ! @js($long) ? '' : 'line-clamp-6'">{{ $message }}</p>
        <div class="mt-1 flex items-center justify-between gap-2">
            @if ($long)
                <button type="button" x-on:click="open = ! open" class="text-xs font-semibold text-emerald-700">
                    <span x-show="! open">Baca penuh</span><span x-show="open" x-cloak>Tutup</span>
                </button>
            @else
                <span></span>
            @endif
            <span class="text-[10px] text-slate-500">{{ mb_strlen($message) }}/900</span>
        </div>
    </div>
</div>
