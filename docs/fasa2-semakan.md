# Semakan Fasa 2 (versi untuk dijual)

Tarikh: 2026-10-08. `php artisan test`: **187 lulus, 0 gagal** (1203 assertion), tanpa
panggilan API sebenar (Claude, Google Places, CHIP semuanya `Http::fake()`).

Skop spec §2 Fasa 2: login berbilang pengguna, workspace, kuota, bayaran. Model bayaran (arahan Bob 2026-10-08): **percubaan 20 lead / 14 hari, yuran aktif RM23.90 sekali, kemudian bayar ikut guna (kos sebenar + 20%)**.

| Ciri | Status | Test |
|---|---|---|
| Daftar akaun → workspace baru, percubaan 14 hari | ✅ | `AccountsTest`: *registers a new business...*, *requires terms, a unique e-mail...* |
| Masuk dengan e-mel, had cubaan | ✅ | `AccountsTest`: *logs in with e-mail...*, *locks the login after 5 failed attempts* |
| Lupa / tukar kata laluan | ✅ | `AccountsTest`: *sends a reset link...*, *gives the same answer for unknown e-mails*, *updates business details and password* |
| Data setiap pelanggan terasing | ✅ | `TenancyTest` (6 test), termasuk job queue tanpa konteks dan permintaan web ikut pengguna. Disemak juga dalam pelayar sebenar (permintaan Livewire). |
| STOP dan peraturan 30 hari ikut pelanggan | ✅ | `TenancyTest`: *keeps STOP lists and the 30 day rule per customer* |
| Percubaan 20 lead / 14 hari, percuma | ✅ | `UsageBillingTest`: *starts every new account on a free trial...*, *runs trial searches for free*, *ends the trial at 20 leads...*, *ends the trial after 14 days...*, *keeps leads visible after the trial...* |
| Caj = kos sebenar AI + Places + 20% | ✅ | `UsageBillingTest`: *charges a paid search its real AI + Places cost plus 20%*, *prices with the configured markup: RM50 cost becomes RM60*, *can bill AI cost only...* |
| Baki tak cukup / habis | ✅ | `UsageBillingTest`: *refuses a paid search when the balance is below the estimate...*, *stops a paid search when the balance runs out...*, *settles a failed search for what it used, once*, *keeps the ledger equal to the balance* |
| Jana semula / follow-up percuma dan tidak dicaj | ✅ | `UsageBillingTest`: *never bills regenerations, follow-ups or lead-card refreshes*, *limits free regenerations and follow-ups per lead* |
| Skrin: percuma / anggaran RM / Baki | ✅ | `UsageBillingTest`: *shows the trial as free...*, *shows paid customers an RM estimate...*, *shows the balance and history on the Baki page...* |
| Akaun dalaman, had produk, had platform, wizard | ✅ | `UsageBillingTest`: *never charges or limits the internal workspace*, *caps products per account*, *still stops all AI calls...*, *walks a new customer through...* |
| Yuran aktif RM23.90 dan tambah baki melalui CHIP | ✅ | `BillingTest`: *creates a CHIP purchase for the RM23.90 activation fee...*, *only allows top-ups after activation...*, *does not charge activation twice*, *adds a paid top-up to the balance, once* |
| Callback disahkan (RSA) + semak semula dengan CHIP | ✅ | `BillingTest`: *activates the account after a verified callback...*, *rejects a callback with a bad signature...*, *does not trust a signed payload that CHIP itself says is unpaid*, *refuses a paid purchase whose amount does not match*, *handles duplicate callbacks once* |
| Callback terlepas | ✅ | `BillingTest`: *confirms payment when the customer returns, and the hourly job catches missed callbacks* |
| Panel admin | ✅ | `BillingTest`: *lets the admin give balance, record payments, extend trials and suspend*, *keeps the admin panel for the admin only* |
| Pelanggan tak nampak kos dalaman | ✅ | `FollowupAndCostScreenTest`: *shows customers their balance, not internal costs*; `SearchScreenTest`: *warns the admin... shows customers a plain message* |
| Halaman jualan + SEO | ✅ | `MarketingTest` (6 test) |
| Peraturan Fasa 0 masih berlaku | ✅ | `Phase0AcceptanceTest` §10.1–§10.11, `HardRulesTest` |

## Belum (perlu Bob)

1. Harga model dan Places dalam `config/ai_prices.php` (caj pelanggan dikira dari sini).
2. Semak `config/billing.php` (yuran aktif, markup, pilihan tambah baki).
3. Akaun merchant CHIP, kunci ujian → live.
4. Peguam semak Terma dan Privasi (bertanda DRAF).
5. Semak syarat Google Maps Platform untuk perkhidmatan dijual semula.
6. Deploy ke Forge (`docs/DEPLOY.md`), termasuk SMTP dan `dynoleads:admin`.

## Belum dibina (cadangan seterusnya)

- E-mel bila baki hampir habis atau percubaan hampir tamat.
- Ahli pasukan (lebih dari satu pengguna setiap workspace).
- Pengesahan e-mel semasa daftar.
- Fasa 3 (balasan melalui WhatsApp Cloud API rasmi). Fasa 1 (agen harian) tidak
  diperlukan: carian hanya bila pelanggan minta.
