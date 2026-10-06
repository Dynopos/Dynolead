@props(['label', 'name', 'hint' => null])
<label class="block space-y-1">
    <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
    {{ $slot }}
    @if ($hint)<span class="block text-xs text-slate-500">{{ $hint }}</span>@endif
    @error($name)<span class="block text-xs text-red-600">{{ $message }}</span>@enderror
</label>
