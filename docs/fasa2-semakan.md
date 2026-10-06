# Semakan Fasa 2 (versi untuk dijual)

Tarikh: 2026-10-07. `php artisan test`: **175 lulus, 0 gagal** (1164 assertion), tanpa
panggilan API sebenar (Claude, Google Places, CHIP semuanya `Http::fake()`).

Skop spec §2 Fasa 2: login berbilang pengguna, workspace, kuota ikut pelan, bayaran.

| Ciri | Status | Test |
|---|---|---|
| Daftar akaun → workspace baru, percubaan 14 hari | ✅ | `AccountsTest`: *registers a new business...*, *requires terms, a unique e-mail...* |
| Masuk dengan e-mel, had cubaan | ✅ | `AccountsTest`: *logs in with e-mail...*, *locks the login after 5 failed attempts* |
| Lupa / tukar kata laluan | ✅ | `AccountsTest`: *sends a reset link...*, *gives the same answer for unknown e-mails*, *updates business details and password* |
| Data setiap pelanggan terasing | ✅ | `TenancyTest` (6 test), termasuk job queue tanpa konteks dan permintaan web ikut pengguna. Disemak juga dalam pelayar sebenar (permintaan Livewire). |
| STOP dan peraturan 30 hari ikut pelanggan | ✅ | `TenancyTest`: *keeps STOP lists and the 30 day rule per customer* |
| Pelan, kuota lead, had produk, had calon | ✅ | `PlansTest` (11 test) |
| Akses tamat → tiada carian/AI, lead kekal | ✅ | `PlansTest`: *blocks searching and AI once the trial has ended...* |
| Had AI pelanggan + had platform | ✅ | `PlansTest`: *caps each customer’s AI spend...*, *stops all AI calls when the platform-wide budget is reached* |
| Wizard produk pertama | ✅ | `PlansTest`: *walks a new customer through...*, *sends new sign-ups to onboarding* |
| Bayaran CHIP (sen, rujukan, callback) | ✅ | `BillingTest`: *creates a CHIP purchase...*, *refuses plans without a price* |
| Callback disahkan (RSA) + semak semula dengan CHIP | ✅ | `BillingTest`: *activates 30 days after a verified callback...*, *rejects a callback with a bad signature...*, *does not trust a signed payload that CHIP itself says is unpaid*, *refuses a paid purchase whose amount does not match*, *handles duplicate callbacks once* |
| Sambung langganan tanpa hilang hari | ✅ | `BillingTest`: *adds a renewal after the current period...* |
| Callback terlepas | ✅ | `BillingTest`: *confirms payment when the customer returns, and the hourly job catches missed callbacks* |
| Panel admin | ✅ | `BillingTest`: *lets the admin record a manual payment...*, *keeps the admin panel for the admin only* |
| Pelanggan tak nampak kos dalaman | ✅ | `FollowupAndCostScreenTest`: *shows customers their quota, not internal RM costs*; `SearchScreenTest`: *warns the admin... shows customers a plain message* |
| Halaman jualan + SEO | ✅ | `MarketingTest` (6 test) |
| Peraturan Fasa 0 masih berlaku | ✅ | `Phase0AcceptanceTest` §10.1–§10.11, `HardRulesTest` |

## Belum (perlu Bob)

1. Harga jualan `asas`/`pro` dalam `config/plans.php` (dan semak kuota/had AI).
2. Harga model dan Places dalam `config/ai_prices.php`.
3. Akaun merchant CHIP, kunci ujian → live.
4. Peguam semak Terma dan Privasi (bertanda DRAF).
5. Semak syarat Google Maps Platform untuk perkhidmatan dijual semula.
6. Deploy ke Forge (`docs/DEPLOY.md`), termasuk SMTP dan `dynoleads:admin`.

## Belum dibina (cadangan seterusnya)

- Caj kad automatik (recurring token CHIP) dan e-mel peringatan sebelum langganan tamat.
- Ahli pasukan (lebih dari satu pengguna setiap workspace).
- Pengesahan e-mel semasa daftar.
- Fasa 1 (agen harian) dan Fasa 3 (balasan melalui WhatsApp Cloud API rasmi).
