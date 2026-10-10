<x-auth-card :title="$step === 1 ? 'Apa yang anda jual?' : 'Ceritakan produk anda'" :subtitle="$step === 1 ? 'Pilih yang paling dekat. Anda boleh ubah semua kemudian.' : 'AI guna maklumat ni untuk tulis mesej. Ia takkan reka harga atau fakta lain.'">
    @if ($step === 1)
        <div class="space-y-2">
            @foreach ($templates as $key => $t)
                <button type="button" wire:click="pick('{{ $key }}')" wire:key="tpl-{{ $key }}"
                        class="flex w-full items-center justify-between rounded-xl px-4 py-3.5 text-left ring-1 ring-inset ring-slate-200 transition hover:bg-emerald-50 hover:ring-emerald-400">
                    <span class="font-semibold text-slate-800">{{ $t['label'] }}</span>
                    <x-icon name="chevron-right" class="h-5 w-5 text-slate-400" />
                </button>
            @endforeach
        </div>
        <button type="button" wire:click="skip" class="mt-4 w-full text-center text-sm text-slate-500 underline">Langkau, saya isi sendiri nanti</button>
    @else
        <form wire:submit="finish" class="space-y-4">
            <x-field label="Nama produk" name="name"><input type="text" wire:model="name" class="input" placeholder="cth: KedaiPOS" autofocus></x-field>
            <x-field label="Apa yang anda jual" name="pitch_core" hint="Tulis fakta ringkas sahaja: apa yang dijual, harga, promosi, kelebihan. AI akan tulis ayat yang menarik untuk setiap kedai. Harga dan promosi hanya diambil dari sini.">
                <textarea wire:model="pitch_core" rows="4" class="input" placeholder="cth: Website premium RM200 termasuk domain &amp; hosting. Siap dalam 2 hari. Promosi untuk tempahan 10–13 Oktober."></textarea>
            </x-field>
            <x-field label="Ayat ajakan (pilihan)" name="cta" hint="Pilihan. Jika kosong, mesej ditutup dengan: “Kalau berminat, balas je mesej ni”."><textarea wire:model="cta" rows="2" class="input"></textarea></x-field>
            <x-field label="Tanda kedai perlukan produk ni" name="fit_signals" hint="AI guna ni untuk nilai kedai mana sesuai.">
                <textarea wire:model="fit_signals" rows="2" class="input"></textarea>
            </x-field>
            <x-field label="Jenis bisnes sasaran" name="default_place_types" hint="Asingkan dengan koma."><input type="text" wire:model="default_place_types" class="input"></x-field>
            <x-field label="Perkataan dilarang (pilihan)" name="banned_words" hint="Perkataan yang AI tak boleh guna. Asingkan dengan koma."><input type="text" wire:model="banned_words" class="input"></x-field>
            <div class="rounded-xl bg-slate-50 p-3"><x-toggle label="Hanya kedai tanpa website" hint="Sesuai untuk produk website." wire:model="require_no_website" /></div>
            <div class="flex gap-2">
                <button type="button" wire:click="back" class="btn-soft">Kembali</button>
                <button type="submit" class="btn-primary flex-1 py-3">Simpan &amp; mula cari</button>
            </div>
        </form>
    @endif
</x-auth-card>
