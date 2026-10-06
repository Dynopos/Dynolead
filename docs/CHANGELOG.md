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
