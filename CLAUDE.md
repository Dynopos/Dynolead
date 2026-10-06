# CLAUDE.md — Dyno Leads

Arahan untuk Claude Code yang bekerja dalam repo ini. Baca fail ini dan
`docs/dyno-leads-spec.md` sebelum mula apa-apa kerja.

## Apa app ini

Dyno Leads ialah app prospek untuk SME Malaysia, sebahagian daripada ekosistem
DYNOPRO (DynoPOS Technologies, Pasir Mas). App ini:

1. cari bisnes tempatan ikut jenis dan kawasan (Google Places API),
2. tapis calon dengan peraturan murah dahulu, kemudian minta AI (Claude API)
   menilai calon yang lulus tapisan sahaja,
3. tulis mesej WhatsApp custom untuk setiap calon,
4. jejak status setiap lead (Baru, Dah hantar, Reply, Deal, Tolak) dan peringatan
   follow-up.

Pemilik produk: Bob (Borhan Sidqy). Fasa 0 ialah alat untuk satu akaun (Bob
sahaja). Login berbilang pengguna dan bayaran datang kemudian (lihat spec).

## Stack

- Laravel 11, Livewire 3, Blade, Tailwind
- MySQL
- Queue: database driver (Fasa 0), Redis kemudian
- Scheduler: `php artisan schedule:run` melalui cron Forge
- Hosting: Laravel Forge (pelayan PHP sendiri), bukan Netlify atau GitHub Pages
- AI: Anthropic Claude API melalui HTTP client Laravel (`Http::`) atau SDK PHP rasmi
  jika ada. Nama model diambil dari `.env`, jangan tulis terus dalam kod.
- Data tempat: Google Places API (New), dengan field mask.

## Peraturan yang tidak boleh dilanggar

Peraturan ini ditulis dalam kod, bukan diharap AI ingat. Setiap satu perlukan
test.

1. **Mesej pertama tidak pernah dihantar secara automatik.** App hanya bina pautan
   `https://wa.me/<nombor>?text=<mesej>` dan butang salin. Manusia yang tekan dan
   hantar. Tiada blast, tiada API WhatsApp tidak rasmi (WAHA, Z-API, dan seumpamanya).
2. **STOP adalah kekal.** Lead yang ditanda Tolak/STOP masuk senarai `suppressions`
   (ikut `place_id` dan nombor telefon). Lead itu tidak boleh dijana atau dipaparkan
   lagi untuk mana-mana produk.
3. **Satu kedai, satu mesej dalam 30 hari.** Kedai yang sama tidak boleh dapat mesej
   dari dua produk berbeza dalam tempoh 30 hari.
4. **Data Google.** Simpan `place_id` sahaja untuk jangka panjang. Nama, alamat,
   telefon, rating dan review diambil semula dari Places API bila perlu dan hanya
   disimpan sementara (cache pendek, lihat spec §6). Paparkan atribusi Google di mana
   data Places ditunjukkan.
5. **Had kos AI dikuatkuasa sebelum setiap panggilan.** `AiBudget::assertCanSpend()`
   mesti dipanggil sebelum setiap panggilan Claude. Bila had bulanan tercapai, kerja
   AI berhenti dan UI beritahu pengguna. Tiada panggilan AI dalam loop tanpa had.
6. **Ayat produk ikut profil produk.** Mesej mesti guna CTA dan peraturan dalam
   profil produk (contoh: DynoPOS tidak pernah guna perkataan "demo"). AI tidak
   boleh reka harga, promosi atau fakta yang tiada dalam profil produk atau review.
7. **Tiada rahsia dalam repo.** Semua kunci API dalam `.env`. `.env.example` tunjuk
   nama pembolehubah sahaja.

## Kawalan kos (penting)

Kos utama app ini ialah token Claude dan panggilan Places. Ikut susunan ini:

- Tapisan peraturan dahulu (rating, bilangan review, ada website atau tidak, jenis
  bisnes, suppression). AI hanya untuk calon yang lulus.
- Model murah (`CLAUDE_MODEL_SCORE`, contoh Haiku) untuk menilai calon. Model lebih
  kuat (`CLAUDE_MODEL_WRITE`, contoh Sonnet) untuk menulis mesej sahaja.
- Prompt caching untuk system prompt (profil produk + peraturan) yang sama.
- Potong review: maksimum 5 review, 300 aksara setiap satu.
- Simpan hasil AI (skor, sebab, mesej) ikut `place_id + product_id + prompt_version`,
  jangan jana semula tanpa sebab.
- Kerja pukal waktu malam guna Message Batches API (lebih murah, tidak segera).
- Web search tool Claude: OFF secara default.
- Setiap panggilan direkod dalam jadual `ai_usage` (token masuk, token keluar,
  anggaran kos).

## Konvensyen

- Bahasa UI: Bahasa Melayu santai, jelas. Kod, nama jadual dan komen: English.
- Logik dalam service classes (`app/Services/...`), bukan dalam komponen Livewire.
- Semua panggilan luar (Claude, Places, WhatsApp) melalui satu client class setiap
  satu supaya senang di-mock.
- Prompt disimpan dalam `resources/prompts/*.md` dengan `prompt_version`.
- Nombor Malaysia: mudah alih `01x...` ditukar ke `601x...` untuk wa.me. Talian tetap
  (`03`–`09`) tiada butang WhatsApp, tunjuk "Telefon atau singgah".

## Test

- Pest. `php artisan test` mesti lulus sebelum commit.
- Test tidak boleh memanggil API sebenar. Guna `Http::fake()`.
- Setiap peraturan dalam "Peraturan yang tidak boleh dilanggar" ada sekurang-kurangnya
  satu test.

## Cara kerja

- Bina ikut fasa dalam spec. Jangan mula fasa seterusnya tanpa arahan Bob.
- Commit kecil dengan mesej jelas. Kemas kini `docs/CHANGELOG.md` setiap fasa.
- Bila spec tidak jelas, pilih pilihan paling selamat dan paling murah, kemudian
  catat keputusan itu dalam `docs/decisions.md`.
