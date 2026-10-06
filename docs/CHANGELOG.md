# Changelog

## Fasa 0
- Spec v0.1 ditulis.

### Langkah 1 — Asas projek
- Laravel 11, Livewire 3, Tailwind 3 (+ forms), Pest 3.
- Migration: `products`, `searches`, `leads`, `place_cache`, `suppressions`,
  `contacts_log`, `ai_usage`, `places_usage`, `settings`.
- Model + `ProductSeeder` untuk DynoPOS (dengan `pitch_variants` runcit/butik/restoran)
  dan murahwebsite.my.
- `.env.example` ikut spec §9.1, `config/ai_prices.php` (harga kosong), `config/dynoleads.php`.
- Layout mesra telefon dengan menu bawah: Produk, Cari, Lead, Follow-up, Kos.
- Login satu kata laluan (`APP_LOGIN_PASSWORD`).

### Langkah 2 — Places, tapisan peraturan, cache
- `PlacesClient` (Places API New) dengan field mask: Text Search medan murah sahaja,
  Place Details untuk telefon, `websiteUri` dan maksimum 5 review (300 aksara setiap satu).
- `RuleFilter`: suppression (place_id + telefon), dihubungi < 30 hari (mana-mana produk),
  rating/review minimum, kedai tutup, lead sedia ada, `require_no_website`, tiada telefon.
- `place_cache` ikut `PLACES_CACHE_HOURS`, `PurgePlaceCacheJob` dijadual.
- `MalaysianPhone`: 01x → 601x untuk wa.me, talian tetap 03–09 tiada WhatsApp.
- Pipeline sebagai job berasingan: `SearchPlacesJob`, `FilterCandidatesJob`, `FetchDetailsJob`.
- Rekod setiap panggilan Places dalam `places_usage`.

### Langkah 3 — AI (nilai dan tulis)
- `ClaudeClient` (Messages API, model dari `.env`, prompt caching pada system prompt,
  output JSON berstruktur). Tiada tool web search.
- `AiGateway` + `AiBudget::assertCanSpend()` sebelum setiap panggilan; `BudgetExceeded`
  hentikan job dan carian ditanda "Had kos AI dicapai".
- Setiap panggilan direkod dalam `ai_usage` (token masuk/keluar, cache read/write, kos RM).
- `resources/prompts/score.md` dan `write.md` dengan `prompt_version`.
- `MessageValidator`: tiada `banned_words`, ≤ 900 aksara, ada STOP, tiada harga rekaan.
  Gagal → jana semula sekali → "Semak manual".
- Job `ScoreLeadsJob`, `WriteMessagesJob`, `RegenerateLeadJob`.

### Langkah 4 — Skrin
- **Produk:** senarai + borang tambah/edit (varian pitch, perkataan dilarang, tapisan).
- **Cari:** anggaran kos sebelum jalan + butang sahkan, kemajuan dicari → ditapis →
  dinilai → siap, kos sebenar setiap carian.
- **Lead:** ringkasan ikut status, penapis (produk, jenis, status, kawasan), kad lead
  (rating, skor, "Kenapa sesuai", amaran, mesej), Buka WhatsApp (wa.me), Salin mesej,
  Edit, Jana semula (dengan pengesahan kos), dropdown status, nota, atribusi Google.
  Talian tetap: "Telefon atau singgah".
- **Follow-up:** lead "Dah hantar" > 3 hari, jana mesej follow-up (model murah), Dah follow-up.
- **Kos:** token, kos RM, panggilan Places bulan ini, had bulanan boleh ubah, bar kemajuan,
  50 panggilan AI terakhir.
- Tolak → `suppressions`; Dah hantar → `contacts_log`.
- Semua skrin diuji pada lebar 400px (tiada skrol mendatar).

### Langkah 5 — Semakan §10 dan deploy
- `tests/Feature/Phase0AcceptanceTest.php`: satu test untuk setiap kotak §10.
- `tests/Feature/HardRulesTest.php`: peraturan 1 (tiada hantar automatik), 4 (data Google),
  7 (tiada rahsia, model dari `.env`).
- Betulkan: kawasan "Pasir Mas, Kelantan" tidak lagi dipecah dua.
- `ProductSeeder` tidak menimpa suntingan produk.
- `docs/DEPLOY.md`: langkah deploy ke Laravel Forge (env, harga, migrate --seed, queue
  worker, cron scheduler, semakan selepas deploy).
- `docs/fasa0-semakan.md`: pemetaan kotak §10 ke test.
- 129 test lulus tanpa panggilan API sebenar.

### Reka bentuk semula antara muka
- Font Inter (dipasang melalui npm, tiada CDN luar), ikon SVG dalam talian, logo dan favicon.
- Header dengan logo, menu bawah dengan penanda aktif dan ruang selamat iPhone.
- Lead: kad dengan avatar, rating, cip kawasan/telefon/produk, kotak "Kenapa sesuai",
  pratonton mesej gaya WhatsApp (dengan "Baca penuh"), butang WhatsApp utama, butang
  Salin/Edit/Jana semula padat, status dengan titik warna, nota boleh dilipat, rentak
  "Hantar hari ni" (x / 15).
- Cari: pilih produk sebagai kad, cip jenis bisnes, gelangsar bilangan calon, anggaran
  gaya resit, kemajuan carian sebagai langkah (Dicari → Ditapis → Dinilai → Siap).
- Kos: kad utama kos vs had (warna ikut peratus), jubin statistik, senarai panggilan.
- Produk: kad produk, borang berseksyen dengan suis togol. Follow-up: kad selaras Lead.
- Skrin masuk baru. Semua skrin disemak pada 400px tanpa skrol mendatar.
