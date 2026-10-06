@props(['fit'])
@if ($fit === null)
    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Belum dinilai</span>
@else
    <span @class([
        'shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset',
        'bg-emerald-50 text-emerald-700 ring-emerald-200' => $fit >= 70,
        'bg-amber-50 text-amber-700 ring-amber-200' => $fit >= 50 && $fit < 70,
        'bg-slate-100 text-slate-500 ring-slate-200' => $fit < 50,
    ])>Skor {{ $fit }}</span>
@endif
