@props(['icon' => 'info', 'title'])
<div class="card flex flex-col items-center px-6 py-10 text-center">
    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <p class="mt-3 font-semibold text-slate-800">{{ $title }}</p>
    <div class="mt-1 text-sm text-slate-500">{{ $slot }}</div>
</div>
