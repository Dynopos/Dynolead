# Semakan Fasa 2 (versi untuk dijual)

Tarikh: 2026-10-08. `php artisan test`: **182 lulus, 0 gagal** (1175 assertion), tanpa
panggilan API sebenar (Claude, Google Places, CHIP semuanya `Http::fake()`).

Skop spec §2 Fasa 2: login berbilang pengguna, workspace, kuota, bayaran. Model bayaran: **kredit ikut carian** (arahan Bob 2026-10-08), bukan langganan bulanan.

| Ciri | Status | Test |
|---|---|---|
| Daftar akaun → workspace baru, percubaan 14 hari | ✅ | `AccountsTest`: *registers a new business...*, *requires terms, a unique e-mail...* |
| Masuk dengan e-mel, had cubaan | ✅ | `AccountsTest`: *logs in with e-mail...*, *locks the login after 5 failed attempts* |
| Lupa / tukar kata laluan | ✅ | `AccountsTest`: *sends a reset link...*, *gives the same answer for unknown e-mails*, *updates business details and password* |
| Data setiap pelanggan terasing | ✅ | `TenancyTest` (6 test), termasuk job queue tanpa konteks dan permintaan web ikut pengguna. Disemak juga dalam pelayar sebenar (permintaan Livewire). |
| STOP dan peraturan 30 hari ikut pelanggan | ✅ | `TenancyTest`: *keeps STOP lists and the 30 day rule per customer* |
| Bayar ikut carian (kredit ikut saiz) | ✅ | `CreditsTest`: *prices a search by size*, *charges credits when a search starts...*, *refuses a search without enough credits...*, *keeps the ledger equal to the balance* |
| Kredit dipulangkan bila tiada lead / gagal | ✅ | `CreditsTest`: *refunds credits when no shop passes the filters*, *refunds credits when the search fails, once only*, *keeps the credits when leads were found, even if none fit* |
| Pelanggan nampak kredit, bukan RM | ✅ | `CreditsTest`: *shows customers credits, not RM...*, *shows the credit cost on the search screen...*, *shows the balance and ledger...* |
| Jana semula / follow-up percuma dengan had | ✅ | `CreditsTest`: *limits free regenerations and follow-ups per lead*, *tells the customer how many free regenerations are left* |
| Akaun digantung, had produk, had platform | ✅ | `CreditsTest`: *blocks a suspended account...*, *caps products per account*, *still stops all AI calls at the platform-wide budget* |
| Wizard produk pertama, kredit percuma daftar | ✅ | `CreditsTest`: *walks a new customer through...*; `AccountsTest`: *registers a new business... free starter credits*, *sends new sign-ups to onboarding* |
| Beli pek kredit melalui CHIP | ✅ | `BillingTest`: *creates a CHIP purchase...*, *refuses packs without a price*, *adds a second pack on top of the current balance* |
| Callback disahkan (RSA) + semak semula dengan CHIP | ✅ | `BillingTest`: *adds the credits after a verified callback...*, *rejects a callback with a bad signature...*, *does not trust a signed payload that CHIP itself says is unpaid*, *refuses a paid purchase whose amount does not match*, *handles duplicate callbacks once* |
| Callback terlepas | ✅ | `BillingTest`: *confirms payment when the customer returns, and the hourly job catches missed callbacks* |
| Panel admin | ✅ | `BillingTest`: *lets the admin grant credits, record a manual payment and suspend*, *keeps the admin panel for the admin only* |
| Pelanggan tak nampak kos dalaman | ✅ | `FollowupAndCostScreenTest`: *shows customers their credits, not internal RM costs*; `SearchScreenTest`: *warns the admin... shows customers a plain message* |
| Halaman jualan + SEO | ✅ | `MarketingTest` (6 test) |
| Peraturan Fasa 0 masih berlaku | ✅ | `Phase0AcceptanceTest` §10.1–§10.11, `HardRulesTest` |

## Belum (perlu Bob)

1. Harga pek kredit dalam `config/credits.php`.
2. Harga model dan Places dalam `config/ai_prices.php`.
3. Akaun merchant CHIP, kunci ujian → live.
4. Peguam semak Terma dan Privasi (bertanda DRAF).
5. Semak syarat Google Maps Platform untuk perkhidmatan dijual semula.
6. Deploy ke Forge (`docs/DEPLOY.md`), termasuk SMTP dan `dynoleads:admin`.

## Belum dibina (cadangan seterusnya)

- E-mel bila kredit hampir habis.
- Ahli pasukan (lebih dari satu pengguna setiap workspace).
- Pengesahan e-mel semasa daftar.
- Fasa 3 (balasan melalui WhatsApp Cloud API rasmi). Fasa 1 (agen harian) tidak
  diperlukan: carian hanya bila pelanggan minta.
