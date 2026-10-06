# Semakan kriteria siap Fasa 0 (spec §10)

Tarikh semakan: 2026-10-06. `php artisan test`: **129 lulus, 0 gagal** (800 assertion),
tanpa sebarang panggilan API sebenar.

Test utama untuk setiap kotak ada dalam `tests/Feature/Phase0AcceptanceTest.php`
(dinamakan `§10.1` hingga `§10.11`). Test lain menyemak peraturan yang sama dengan lebih
terperinci.

| # | Kriteria | Status | Test yang membuktikan |
|---|---|---|---|
| 1 | Seed DynoPOS dan murahwebsite.my | ✅ | `Phase0AcceptanceTest` §10.1; `SetupTest`: *seeds DynoPOS and murahwebsite.my from spec §7*, *picks the DynoPOS pitch variant by shop type*, *does not overwrite product edits when seeding again* |
| 2 | Carian "kedai runcit, Pasir Mas" hasilkan lead dengan skor, sebab dan mesej | ✅ (dengan API palsu) | `Phase0AcceptanceTest` §10.2 (melalui skrin Cari → Lead); `AiTest`: *runs the full pipeline*; `SearchScreenTest`: *shows a cost estimate before running and only runs after confirming* |
| 3 | murahwebsite.my: kedai ada `websiteUri` tidak muncul | ✅ | `Phase0AcceptanceTest` §10.3; `SearchPipelineTest`: *hides shops with a websiteUri from murahwebsite.my*; `RuleFilterTest`: *drops shops with a website when require_no_website is true* |
| 4 | Mesej DynoPOS tidak pernah mengandungi "demo" | ✅ | `Phase0AcceptanceTest` §10.4; `AiTest`: *never keeps "demo" in a DynoPOS message*, *flags Semak manual after the second failure*, *tells the writer that "demo" is banned*; `LeadsScreenTest`: *hides WhatsApp and copy for a message flagged Semak manual*, *lets Bob fix a flagged message by hand*; `FollowupAndCostScreenTest`: *does not offer WhatsApp for a follow-up containing a banned word* |
| 5 | wa.me betul untuk 01x; tiada butang untuk talian tetap | ✅ | `Phase0AcceptanceTest` §10.5; `MalaysianPhoneTest` (semua kes); `LeadsScreenTest`: *builds a correct wa.me button for 01x numbers*, *shows "Telefon atau singgah" and no WhatsApp button for landlines* |
| 6 | Tolak/STOP → lead hilang untuk semua produk dan tidak dijana semula | ✅ | `Phase0AcceptanceTest` §10.6; `LeadsScreenTest`: *Tolak adds the shop to suppressions and hides it for every product*; `RuleFilterTest`: *drops suppressed shops*, *drops shops whose phone number is suppressed*; `SearchPipelineTest`: *skips suppressed shops...* |
| 7 | Dihubungi untuk produk A → tidak muncul untuk produk B dalam 30 hari | ✅ | `Phase0AcceptanceTest` §10.7 (hari 29 tiada, hari 31 ada); `LeadsScreenTest`: *Dah hantar records contacts_log and hides the shop from other products for 30 days*, *refuses Dah hantar when the shop was contacted for another product*; `RuleFilterTest`, `SearchPipelineTest` |
| 8 | Setiap panggilan AI direkod dalam `ai_usage`; had bulanan hentikan AI | ✅ | `Phase0AcceptanceTest` §10.8; `AiTest`: *checks the budget before every call and records every call in ai_usage*, *records cache read tokens*, *stops AI work once the monthly limit is reached, without calling Claude*, *refuses a call whose worst-case estimate would cross the limit*, *stops the search pipeline with the budget message*, *does not call Claude while model prices are empty*; `ArchitectureTest`: *Claude is only called through AiGateway* |
| 9 | Cache Places dipadam selepas `PLACES_CACHE_HOURS` | ✅ | `Phase0AcceptanceTest` §10.9; `PlaceCacheTest` (4 test) |
| 10 | Semua test lulus tanpa panggilan API sebenar | ✅ | `tests/TestCase.php` aktifkan `Http::preventStrayRequests()` untuk setiap test; `Phase0AcceptanceTest` §10.10 buktikan panggilan tanpa fake akan gagal |
| 11 | Deploy ke Forge, cron scheduler aktif | ⏳ Belum | Perlu Bob buat di Forge (tiada akses Forge dari sini). Langkah penuh: `docs/DEPLOY.md`. Jadual sudah wujud: `Phase0AcceptanceTest` §10.11 dan `PlaceCacheTest`: *schedules the cache purge job* |

## Peraturan "tidak boleh dilanggar" (CLAUDE.md)

| Peraturan | Test |
|---|---|
| 1. Mesej pertama tidak dihantar automatik | `HardRulesTest`: *rule 1* (tiada API WhatsApp, hanya pautan wa.me); `ArchitectureTest`: hanya `PlacesClient` dan `ClaudeClient` guna HTTP |
| 2. STOP kekal | §10.6 di atas |
| 3. Satu kedai, satu mesej dalam 30 hari | §10.7 di atas |
| 4. Data Google | `HardRulesTest`: *rule 4* (lead simpan `place_id` sahaja, atribusi Google); `SearchPipelineTest`: *never stores Google content in leads*; `PlaceCacheTest` |
| 5. Had kos AI sebelum setiap panggilan | §10.8 di atas |
| 6. Ayat ikut profil produk | `AiTest`: *tells the writer that "demo" is banned*, *uses the DynoPOS pitch variant*, *rejects prices that are not in the product profile*, *allows prices that are in the product profile* |
| 7. Tiada rahsia dalam repo | `HardRulesTest`: *rule 7* (imbas fail dalam git untuk kunci Anthropic/Google, model tidak ditulis dalam kod); `SetupTest`: *keeps secrets out of .env.example* |

## Belum siap / perlu tindakan Bob

1. **Isi harga** dalam `config/ai_prices.php` (model, Places, `usd_to_myr`). Tanpa harga,
   app tidak akan panggil Claude.
2. **Deploy ke Forge** dan hidupkan queue worker + scheduler (`docs/DEPLOY.md`).
3. **Test sebenar pertama**: 10 kedai runcit di Pasir Mas, kemudian bandingkan anggaran
   dengan kos sebenar di halaman Kos.
4. Soalan terbuka spec §11 (domain, had kos permulaan).
5. Sebelum Fasa 2: semak syarat Google Maps Platform (spec §6) dan naik taraf Laravel 12.
