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
