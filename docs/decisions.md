# Keputusan

Catat setiap keputusan reka bentuk di sini (tarikh, keputusan, sebab).

## 2026-10-06 — Langkah 1 (asas projek)

- **Laravel 11 dikekalkan seperti diarah.** `composer audit` tunjuk 3 nasihat keselamatan
  untuk `laravel/framework` 11.x (pembaikan hanya dalam 12.x/13.x): XSS pada halaman debug,
  kekeliruan path URL bertandatangan sementara, dan CRLF dalam peraturan validasi `email`.
  App ini tidak guna URL bertandatangan atau validasi `email`, dan `APP_DEBUG=false` di
  produksi. **Cadangan: naik taraf ke Laravel 12 sebelum Fasa 2.**
- **Login satu kata laluan (`APP_LOGIN_PASSWORD`).** Spec kata login berbilang pengguna
  datang dalam Fasa 2, tetapi app di Forge terdedah kepada internet. Tanpa login, sesiapa
  boleh baca lead dan bakar bajet AI. Pilihan paling murah dan selamat: satu kata laluan
  dalam `.env`, sesi, dan had 5 cubaan seminit.
- **Jadual tambahan `places_usage`.** Halaman Kos perlu bilangan panggilan Places sebulan
  (§3.5). Satu baris setiap panggilan, tiada kandungan Google disimpan.
- **Medan tambahan dalam `leads`:** `business_type`, `area` (istilah carian Bob, untuk
  penapis), `needs_review` + `review_note` (flag "Semak manual" §5.3),
  `score_prompt_version` (versi prompt nilai; `prompt_version` = versi prompt tulis),
  `followup_message`, `status_changed_at`. `place_id + product_id` unik.
- **`pitch_variants` dalam format senarai** `[{key, match[], pitch}]`. Varian dipilih ikut
  istilah carian dahulu, kemudian jenis Google, kemudian `pitch_core`. Ini elak kedai runcit
  (jenis Google ada `food`) tersalah dapat ayat restoran.
- **Ayat butik.** Spec tulis "... Rekod jualan dan stok setiap item". Bahagian "..." diisi
  dengan permulaan yang sama seperti runcit ("sistem POS untuk kedai kecil & sederhana...").
- **Nilai tapisan awal** (spec tiada nombor): DynoPOS rating ≥ 3.5, review ≥ 10;
  murahwebsite.my rating ≥ 3.5, review ≥ 20 ("review banyak tetapi tiada website"),
  `require_no_website = true`. Bob boleh ubah di skrin Produk.
- **Harga dalam `config/ai_prices.php` kosong (null), termasuk kadar USD→RM.** Selagi harga
  model kosong, app tidak panggil Claude (lihat Langkah 3) supaya had kos sentiasa betul.
  Kemas kini Oktober 2026: harga Claude diisi atas arahan Bob (Haiku 4.5: $1 input, $5
  output, $1.25 tulis cache, $0.10 baca cache; Sonnet 5.5: $2, $10, $2.50, $0.20, USD setiap
  1 juta token). Harga Places dan `usd_to_myr` masih kosong untuk Bob isi; tanpa
  `usd_to_myr` Claude masih tidak dipanggil.
- **Zon masa** `Asia/Kuala_Lumpur`, bahasa `ms`.

## 2026-10-06 — Langkah 2 (Places, tapisan, cache)

- **Field mask.** Text Search: `id, displayName, types, primaryType, rating,
  userRatingCount, shortFormattedAddress, businessStatus`. `businessStatus` ditambah
  (SKU sama) supaya kedai yang dah tutup dibuang sebelum Details. Telefon, `websiteUri`
  dan review hanya dalam Place Details untuk calon yang lulus.
  *Nota penjimatan untuk Bob:* `rating`/`userRatingCount` sudah meletakkan Text Search dalam
  SKU Enterprise, jadi menambah `websiteUri` dalam Text Search tidak naikkan harga dan boleh
  jimat panggilan Details untuk murahwebsite.my. Tidak dibuat sekarang sebab arahan Langkah 2
  minta `websiteUri` dalam Details.
- **Paparan lead guna field mask tanpa review** (`details_display`, SKU lebih murah) bila
  cache tamat.
- **Calon tanpa nombor telefon dibuang** sebelum AI (tiada cara hubungi, membazir token).
  Talian tetap dikekalkan (Bob boleh telefon atau singgah).
- **Lead yang sudah wujud untuk produk sama tidak diproses semula** (tiada Details, tiada AI).
- **Nombor dalam `suppressions` disimpan dalam bentuk `60...`** supaya `011-...`,
  `+60 11-...` dan `6011...` dikira nombor yang sama.
- **Cache dipadam setiap jam dan setiap hari (03:15).** Spec minta job harian; job setiap
  jam ditambah supaya data Google tidak kekal lebih lama daripada `PLACES_CACHE_HOURS`
  (dengan job harian sahaja, data boleh kekal hampir 2x tempoh). Paparan juga abaikan cache
  yang tamat walaupun belum dipadam.
- **Pipeline:** `SearchPlacesJob → FilterCandidatesJob → FetchDetailsJob → (AI)`. Setiap job
  idempotent: jika diulang, langkah yang dah siap dilangkau (contoh: Text Search tidak
  dipanggil lagi jika `candidate_place_ids` sudah ada). Hanya `place_id` disimpan dalam
  `searches`.

## 2026-10-06 — Langkah 3 (AI)

- **HTTP client Laravel, bukan SDK PHP rasmi.** CLAUDE.md benarkan kedua-dua; HTTP client
  dipilih supaya semua test boleh guna `Http::fake()` seperti diarah.
- **Satu pintu untuk Claude: `AiGateway`.** Setiap panggilan: semak harga → 
  `AiBudget::assertCanSpend(anggaran_terburuk)` → panggil → rekod `ai_usage`. Anggaran
  terburuk = (aksara prompt ÷ 3) token masuk + `max_tokens` keluar. Test arkitektur
  pastikan `ClaudeClient` hanya digunakan oleh `AiGateway`.
- **Harga kosong = tiada panggilan AI** (`PricesNotConfigured`). Carian berhenti dengan
  status "Gagal" dan mesej jelas supaya Bob isi `config/ai_prices.php`.
- **Output JSON guna structured outputs** (`output_config.format` dengan JSON schema),
  disokong oleh Haiku 4.5 dan Sonnet 5.5. Kod tetap semak JSON sendiri (`fit` diapit 0–100).
- **`effort`:** `CLAUDE_EFFORT_WRITE=low` untuk model tulis (kurangkan token "thinking" yang
  dibilkan sebagai output). Kosong untuk model nilai sebab Haiku 4.5 tidak sokong `effort`.
- **Prompt caching:** system prompt (profil produk + peraturan) ditanda
  `cache_control: ephemeral` dan tidak mengandungi data kedai, jadi ia sama untuk setiap
  kedai bagi produk yang sama. *Nota:* had minimum cache ialah 4096 token untuk Haiku 4.5
  dan 512 untuk Sonnet 5.5. System prompt nilai (~600 token) terlalu pendek untuk cache
  pada Haiku, jadi `cache_read_tokens` untuk `score` mungkin 0. Ini tidak menambah kos.
- **Semakan selepas jana** (§5.3) + satu semakan tambahan: jumlah `RMxxx` dalam mesej mesti
  wujud dalam `pitch_core`/varian/`cta` (peraturan 6, AI tidak boleh reka harga). Percubaan
  kedua diberi senarai masalah percubaan pertama. Gagal lagi → disimpan dengan
  `needs_review` ("Semak manual") dan butang WhatsApp/Salin disembunyikan sehingga Bob
  betulkan mesej (Langkah 4).
- **Hasil AI tidak dijana semula tanpa diminta.** Lead sedia ada untuk `place_id + product_id`
  tidak diproses semula oleh carian baru; skor/mesej yang ada tidak ditimpa melainkan
  `force` (butang Jana semula). `score_prompt_version` dan `prompt_version` direkod.
- **Fit < 50 → status `tak_sesuai`, tiada mesej.** Jika dinilai semula dan fit ≥ 50, status
  kembali `baru`.
- **`DB_QUEUE_RETRY_AFTER=900`.** Default Laravel 90 saat lebih pendek daripada job AI;
  job panjang akan diambil oleh worker lain dan menyebabkan panggilan AI berganda.
- **`CLAUDE_USE_BATCH`** dibaca dalam config tetapi Message Batches untuk agen malam ialah
  kerja Fasa 1, belum dibina.

## 2026-10-06 — Langkah 4 (skrin)

- **Logik dalam service:** `LeadService` (senarai, kad, status, nota, edit mesej, jana
  semula), `FollowupService`, `SearchService`, `CostEstimator`, `CostReport`,
  `ProductService`. Komponen Livewire hanya panggil service. Test arkitektur halang
  Livewire guna `Http`, `ClaudeClient` atau `PlacesClient` terus.
- **Butang "Cari" kira anggaran dahulu**, kemudian "Sahkan & cari". Carian tidak boleh
  dimulakan jika harga AI kosong atau had bulanan dah dicapai (Places pun tidak dipanggil,
  sebab tanpa AI carian itu tak berguna).
- **Anggaran §9.3:** kadar lulus = `lead_count / found_count` carian 30 hari, kadar sesuai =
  fit ≥ 50 / dinilai, kos purata nilai/tulis = purata `ai_usage` setiap lead (termasuk
  jana semula). Data sebenar hanya diguna bila ada ≥ 20 calon / ≥ 10 lead dinilai;
  kalau tidak, default 50% / 60% (sampel kecil terlalu bising).
- **Tolak = STOP kekal.** Suppression dicipta dengan `place_id` dan telefon (bentuk `60...`).
  Lead itu dan lead produk lain untuk kedai sama disembunyikan, tidak boleh diubah status
  dan tidak boleh dijana semula. Kad juga disembunyikan jika nombor telefonnya dalam STOP.
- **Dah hantar** → rekod `contacts_log`, `contacted_at`, follow-up 3 hari lagi. Ditolak
  jika kedai dihubungi untuk produk lain dalam 30 hari. Lead produk lain untuk kedai itu
  disembunyikan selama 30 hari.
- **Mesej yang gagal semakan** tidak dapat butang WhatsApp atau Salin. Bob boleh edit
  mesej; suntingan melalui semakan yang sama.
- **Jana semula** minta pengesahan dengan anggaran kos, berjalan dalam queue; skrin
  `wire:poll` sehingga siap. Follow-up guna model murah (`CLAUDE_MODEL_SCORE`).
- **Lead list 10 setiap halaman** supaya bila cache tamat, paling banyak 10 panggilan
  Place Details (tanpa review) untuk satu halaman.
- **Panduan 10–15 mesej sehari** dipaparkan di atas senarai lead (bilangan "Dah hantar"
  hari ini).
- **Atribusi "Data kedai: Google Maps"** pada setiap kad yang tunjuk data Places
  (Lead dan Follow-up), dengan pautan ke Google Maps bila ada.
- **Bahasa app `ms`** (nama bulan dalam BM).

## 2026-10-06 — Langkah 5 (semakan §10, deploy)

- **Pepijat dibetulkan:** kawasan dipisah ikut koma, jadi "Pasir Mas, Kelantan" jadi dua
  carian ("Pasir Mas" dan "Kelantan"). Kini kawasan dipisah ikut baris atau `;` sahaja.
  Dijumpai oleh test penerimaan §10.2.
- **`ProductSeeder` hanya cipta produk yang belum ada** (`firstOrCreate` ikut `slug`), supaya
  `db:seed` dalam skrip deploy tidak menimpa suntingan Bob di skrin Produk.
- **Test penerimaan §10** dalam `tests/Feature/Phase0AcceptanceTest.php`, satu test setiap
  kotak, dan `tests/Feature/HardRulesTest.php` untuk peraturan 1, 4 dan 7. Pemetaan penuh
  dalam `docs/fasa0-semakan.md`.
- **`Http::preventStrayRequests()`** dihidupkan dalam `tests/TestCase.php`: test yang terlupa
  `Http::fake()` akan gagal, bukan memanggil API sebenar.
- **Queue worker `--timeout=660`, `DB_QUEUE_RETRY_AFTER=900`** (lihat `docs/DEPLOY.md`).
- **Kotak §10.11 (deploy Forge) belum ditanda**: perlu dibuat oleh Bob di Forge.

## 2026-10-06 — Fasa 2 dimulakan (arahan Bob)

Bob jelaskan app ini untuk **dijual kepada SME lain**, bukan untuk kegunaan sendiri sahaja.
Keputusan Bob:
- Mula Fasa 2: ramai pengguna, workspace setiap pelanggan.
- **Kunci API pusat** (Anthropic + Google milik Bob), pelanggan dicaj ikut pelan dan kuota.
- **Bayaran melalui CHIP.**

Peraturan yang **tidak berubah** walaupun dijual: mesej pertama tetap dihantar oleh manusia
melalui wa.me (spec §8: Fasa 0–2), tiada API WhatsApp tidak rasmi.

### F2.1 — Naik taraf Laravel 12
- `laravel/framework` 11.57 → 12.69.3. `composer audit` kini bersih (3 nasihat Laravel 11
  dalam keputusan Langkah 1 sudah tertutup). Tiada perubahan kod diperlukan.

### F2.2 — Akaun dan workspace
- **Satu pengguna, satu workspace** (`users.workspace_id`, `role`). Pasukan berbilang
  pengguna boleh ditambah kemudian; jadual sudah ada `role`.
- **Data diasingkan dengan global scope** (`BelongsToWorkspace`) pada `products`, `searches`,
  `leads`, `suppressions`, `contacts_log`, `ai_usage`, `places_usage`, `settings`. Konteks
  ditetapkan oleh middleware `SetCurrentWorkspace` (juga untuk permintaan Livewire) dan
  oleh setiap job melalui `CurrentWorkspace::runAs()`, yang memulihkan konteks selepas job.
  Worker queue kosongkan konteks pada setiap pusingan.
- **`place_cache` dikongsi** antara pelanggan: ia hanya cache sementara data Google ikut
  `place_id`, dan berkongsi menjimatkan panggilan Places.
- **Senarai STOP dan peraturan 30 hari ikut workspace.** STOP kepada penjual A tidak
  bermakna kedai itu tolak penjual B. Peraturan 30 hari menghalang *satu penjual* hantar
  mesej dua produk berbeza kepada kedai yang sama.
- **Login e-mel + kata laluan** (Livewire): daftar, masuk (had 5 cubaan seminit setiap
  e-mel/IP), lupa dan tukar kata laluan (jawapan sama untuk e-mel yang tiada, elak
  pendedahan akaun). `APP_LOGIN_PASSWORD` dibuang.
- **Admin platform:** `php artisan dynoleads:admin email --demo-products` cipta akaun Bob
  (`is_admin`), workspace pelan `dalaman` dan produk DynoPOS + murahwebsite.my.
  `DatabaseSeeder` tidak lagi seed apa-apa.
- **Jenama DynoPOS dibuang dari antara muka:** header tunjuk nama bisnes pelanggan;
  borang produk guna nama pengirim/bisnes pelanggan. Nama penjual perkhidmatan (Terma,
  Privasi) dari `.env` (`COMPANY_*`).
- **Terma dan Polisi Privasi dalam bentuk DRAF** dengan amaran jelas. Mesti disemak peguam
  sebelum jual (PDPA, Google Maps, WhatsApp).
- Data Fasa 0 sedia ada (jika ada) dipindah ke workspace "DynoPOS Technologies" oleh migration.

### F2.3 — Pelan, kuota, onboarding
- **`config/plans.php`**: `percubaan` (14 hari, 20 lead, 1 produk, 20 calon, had AI RM5),
  `asas`, `pro`, `dalaman` (akaun Bob, tanpa had). **Harga `asas`/`pro` sengaja kosong**
  (spec §11 soalan 4): pelan tanpa harga dipapar "Belum dibuka" dan tidak boleh dibeli.
  Kuota dan had AI ialah cadangan awal; Bob laraskan selepas lihat kos sebenar setiap lead.
- **Kuota lead ikut bulan kalendar** (sama seperti had AI), bukan tempoh bayaran 30 hari.
  Lebih mudah difahami dan diaudit.
- **Kuota disemak sebelum Place Details**, jadi lead yang melebihi kuota tidak menelan kos
  Google atau AI.
- **Dua had AI:** had pelanggan (tetapan sendiri, tidak boleh melebihi `ai_budget_myr` pelan)
  dan had platform `AI_MONTHLY_BUDGET_MYR` (semua pelanggan, melindungi kunci pusat).
  `AI_MONTHLY_BUDGET_MYR` kini bermaksud had **keseluruhan platform**, jadi nilainya perlu
  dinaikkan bila pelanggan bertambah.
- **Akses tamat** (percubaan/langganan tamat atau digantung): tiada carian, jana semula atau
  follow-up AI. Lead sedia ada kekal boleh dilihat dan dibuka dalam WhatsApp (tiada kos).
- **Halaman Kos dipecah:** pelanggan nampak "Kuota" (lead x/y, % AI, bilangan carian).
  Butiran RM, model, token dan amaran harga hanya untuk admin. Mesej "harga belum diisi"
  kepada pelanggan ditukar kepada "Perkhidmatan AI belum sedia".
- **Wizard mula** (`/mula`) dengan templat (POS, website, pemasaran, kosong). Teks dalam
  `[kurungan]` mesti diganti sebelum simpan. Pelanggan baru dihantar ke wizard; boleh langkau.

### F2.4 — Bayaran CHIP dan panel admin
- **Prabayar 30 hari, tiada caj automatik.** Setiap bayaran ialah satu CHIP Purchase
  (FPX, kad, e-wallet). Sebab: FPX paling biasa untuk SME dan tiada token berulang; caj kad
  automatik (recurring token) boleh ditambah kemudian. Pembaharuan disambung selepas
  tempoh semasa tamat, jadi tiada hari hilang. Bayar semasa percubaan: tempoh berbayar
  bermula serta-merta.
- **Bayaran hanya dikira selepas CHIP sahkan:** callback mesti ada `X-Signature` RSA yang
  sah (kunci awam syarikat dari `GET /public_key/`, di-cache sehari, atau `CHIP_PUBLIC_KEY`),
  kemudian pembelian diambil semula dari API CHIP dan jumlah (sen), mata wang dan rujukan
  `DL-xxxxxx` mesti sepadan. Kalau tak sepadan → `failed` + log ralat. Pengendalian idempotent.
- **Halaman pulang dari CHIP** tidak percaya URL; ia semak status dengan CHIP. Job setiap jam
  semak bayaran `created` 48 jam terakhir sekiranya callback terlepas.
- **`/chip/callback` dikecualikan dari CSRF** (panggilan pelayan ke pelayan, dilindungi
  tandatangan).
- **Panel admin** (`/admin`, `is_admin` sahaja): pelanggan, status, lead dan kos AI bulan ini,
  hasil bulan ini, kos AI platform berbanding had, panggilan Places. Tindakan: rekod bayaran
  manual (+30 hari), lanjut percubaan 7 hari, gantung/aktifkan.
- **Tiada data kad disimpan.** Jadual `payments` hanya simpan jumlah, status, ID pembelian CHIP.

## 2026-10-08 — Tukar ke bayar ikut carian (arahan Bob)

Bob: "Lead dicari semasa perlu sahaja. Caj pun berdasarkan carian sahaja."

- **Pelan bulanan, percubaan 14 hari dan kuota lead bulanan dibuang.** Ganti dengan
  **kredit prabayar** (`config/credits.php`, `CreditService`, jadual `credit_transactions`).
- **Kos carian ikut saiz:** `ceil(calon / 20)` kredit (20 → 1, 40 → 2, 60 → 3). Dipapar
  sebelum pelanggan sahkan. Sebab: kos sebenar (Places + AI) naik dengan bilangan calon;
  kadar rata untuk 60 calon akan rugi.
- **Kredit dipotong semasa carian bermula**, dalam satu transaksi DB dengan baris workspace
  dikunci. Baki tidak boleh jadi negatif.
- **Pulangan automatik** (`settleSearch`) bila carian tamat tanpa lead, atau berhenti kerana
  ralat/had platform sebelum ada mesej ditulis. Tiada pulangan bila lead dijumpa tetapi
  semuanya "tak sesuai" (AI dan Places sudah digunakan). Pulangan berlaku sekali sahaja.
- **3 kredit percuma** bila daftar (`SIGNUP_CREDITS`), ganti percubaan.
- **Jana semula dan follow-up AI percuma**, tetapi dihadkan 3 kali setiap lead. Ini kawal kos
  AI tanpa perlu had RM untuk pelanggan.
- **Had AI RM setiap pelanggan dibuang.** Had platform `AI_MONTHLY_BUDGET_MYR` kekal
  sebagai pengaman kunci pusat. Workspace admin masih boleh tetapkan had sendiri.
- **Pek kredit dibeli melalui CHIP** (pembelian sekali, sesuai untuk FPX). Harga pek kosong
  sehingga Bob isi; pek tanpa harga "Belum dibuka". Kredit tidak luput.
- **Workspace `dalaman`** (Bob) tidak dicaj.
- **Pelanggan tidak nampak RM kos dalaman:** skrin Cari tunjuk kredit; RM hanya untuk admin.
- **Fasa 1 (agen harian) tidak dibina**: carian hanya bila pelanggan minta.
- Kolum lama (`trial_ends_at`, `paid_until`, `payments.period_*`) dibiarkan tetapi tidak
  digunakan; boleh dibuang dalam migration kemudian.

## 2026-10-08 — Model bayaran akhir: percubaan, aktif sekali, caj ikut guna (arahan Bob)

Bob: "Demo 20 lead atau 14 hari. Daftar RM23.90 sekali bayar. Caj ikut token Claude,
contoh kos RM50 kita caj RM60." Ini **menggantikan** model kredit (commit sebelum ini).

- **Percubaan:** `trial_leads` (20) lead atau `trial_days` (14) hari, mana dulu. Carian
  percubaan percuma. Had lead disemak sebelum Place Details, jadi tiada kos melebihi had.
- **Aktifkan akaun:** `activation_fee_myr` (RM23.90) sekali bayar melalui CHIP. **Tidak**
  dimasukkan ke baki (yuran sahaja). Boleh dibayar semasa percubaan.
- **Bayar ikut guna:** pelanggan tambah baki RM (`topup_options`: 20/50/100). Setiap carian
  berbayar ditolak `kos sebenar × (1 + markup_percent/100)`, dibundarkan ke atas ke sen.
- **Kos Google Places dimasukkan dalam caj** (`include_places_cost = true`). Sebab: satu
  Place Details (dengan review) ~USD0.025 setiap kedai, selalunya lebih mahal daripada token
  AI untuk kedai yang sama. Caj ikut token Claude sahaja akan rugi. Bob boleh tukar ke
  `false` (`BILL_PLACES_COST=false`). Bob sahkan: kekalkan caj Google Places.
- **Tiada harga Places, tiada carian berbayar.** Jika `include_places_cost` aktif tetapi
  `places.text_search`/`places.details`/`usd_to_myr` kosong, kos Places akan dikira RM0 dan
  pelanggan tak dicaj. Jadi carian berbayar disekat sehingga harga diisi. Percubaan percuma
  dan workspace `dalaman` tidak terjejas kerana tidak dicaj.
- **Meter penggunaan:** setiap peringkat pipeline berjalan dalam `UsageMeter::runFor()`;
  `AiGateway` dan `PlacesClient` tanda baris `ai_usage`/`places_usage` dengan
  `billable_search_id`. Jana semula, follow-up dan paparan kad lead berlaku di luar meter,
  jadi tidak dicaj (percuma, dengan had 3 kali setiap lead).
- **Caj diselesaikan berperingkat** (`WalletService::settle`): sebelum setiap kedai/lead,
  caj penggunaan setakat ini; jika baki ≤ 0, carian berhenti ("Baki habis"). Baki boleh
  jadi negatif sedikit (satu langkah terakhir); tambahan baki seterusnya menampung.
  Tiada caj di depan, tiada pulangan diperlukan.
- **Sebelum carian:** baki mesti ≥ anggaran caj (§9.3 × markup). Pelanggan nampak anggaran
  dalam RM ("≈ RM1.98") dan bahawa caj sebenar ikut penggunaan.
- **`wallet_transactions.cost_sen`** simpan kos mentah di sebalik setiap caj (untuk admin
  kira untung). Pelanggan tidak nampak kos mentah.
- **Ketepatan harga penting:** caj dikira dari `config/ai_prices.php`. Jika harga di situ
  lebih rendah dari harga sebenar Anthropic/Google, Bob rugi.
- Migration kredit (belum pernah dideploy) diganti terus dengan migration baki RM.

## 2026-10-06 — Harga setiap lead di halaman jualan

- Bob: pelanggan tak faham "Kos + 20%". Halaman jualan kini tunjuk **anggaran harga setiap
  lead** dan **berapa lead untuk setiap pilihan tambah baki** (RM20/50/100). Token tidak
  disebut kerana tidak bermakna bagi pelanggan.
- Dikira oleh `PriceGuide` dari `config/ai_prices.php` + `estimate_defaults` sahaja (20 calon,
  lulus 50%, sesuai 60%), tanpa data pelanggan, jadi semua pelawat nampak angka yang sama dan
  tiada query merentas workspace. Harga setiap lead dibundar ke atas; bilangan lead dibundar
  ke bawah ke gandaan 5 supaya contoh tidak berjanji lebih.
- Masih anggaran: caj sebenar tetap ikut kos sebenar + markup (`WalletService::settle`).
  Jika harga belum diisi, halaman kembali ke "Kos + 20%".

## 2026-10-06 — Nama, logo dan warna (Bob)

- Nama produk: **Dyno Lead** (ikut logo), bukan "Dyno Leads". Ditukar dalam UI, e-mel, CHIP,
  SEO, Terma/Privasi, README, DEPLOY dan CLAUDE.md. Kunci config dalaman (`dynoleads.*`) dan
  nama fail spec tidak diubah. `APP_NAME` dalam `.env` Forge perlu ditukar sendiri.
- Warna: hijau zamrud kekal warna utama app; oren dari logo untuk butang ajakan utama
  (`btn-accent`) dan sorotan di halaman jualan. Tiada gradien hijau→oren terus (jadi warna
  zaitun yang kusam); dua warna diletak sebagai blok berasingan.

## 2026-10-07 — Voice note Bob di halaman jualan

- Bob mahu suara terus main bila halaman dibuka. Pelayar (Chrome, Safari, telefon) menyekat
  bunyi automatik sehingga pelawat sentuh halaman, jadi `resources/js/marketing.js` cuba main
  bila halaman dibuka; jika disekat, ia mula pada sentuhan atau tekanan kekunci pertama di
  mana-mana pada halaman. Butang play berdenyut sementara menunggu.
- Main sendiri sekali sahaja setiap sesi (`sessionStorage`), supaya pelawat yang kembali ke
  halaman tidak dengar lagi. Jika pelawat tekan jeda, sentuhan lain tidak memulakannya semula.
- Fail: `public/audio/pesanan-bob.mp3` (mono 96 kbps, kelantangan diseragamkan, ~590 KB).
  Pemain disembunyikan jika fail tiada.
- Animasi (barisan niche bergerak, bahagian muncul semasa skrol, bar bunyi) dimatikan untuk
  pengguna yang pilih "kurangkan gerakan" pada peranti. Kandungan tetap kelihatan tanpa JS.

## 2026-10-07 — Video demo di halaman jualan

- Rakaman skrin Bob memaparkan nama dan nombor telefon kedai sebenar dari Google Maps. Ia
  tidak disiarkan: syarat Google tidak membenarkan data Places disimpan/diguna dalam iklan
  (peraturan 4), dan kedai itu tidak beri izin. Ganti: rakaman app sebenar dengan data kedai
  contoh (`public/video/demo-lead.mp4` + `.webm`, 18 saat, tanpa bunyi), dimain senyap dan
  berulang dalam bingkai telefon.
- Pembetulan voice note: telefon hanya membenarkan bunyi selepas sentuhan lengkap (`touchend`/
  `click`), bukan `touchstart`. Skrip kini terus mencuba pada setiap sentuhan sehingga audio
  benar-benar bermula.

## 2026-10-07 — Voice note: cuba main setiap kali (Bob)

- Bob mahu suara main setiap kali halaman dibuka. Had "sekali setiap sesi" dibuang.
- Chrome/Safari tetap tidak membenarkan bunyi tanpa ketikan (skrol tidak dikira). Semasa
  menunggu, jalur oren "Ketik skrin untuk dengar pesanan Bob" dipaparkan di atas; ia hilang
  bila suara bermula. Jika pelawat tekan jeda, ketikan lain tidak memulakannya semula.

## 2026-10-09 — Domain dynolead.my

- Domain rasmi: `https://dynolead.my` (APP_URL). `lead.dynopro.my` dibuang kerana belum
  diterbitkan di mana-mana dan CHIP belum didaftar.
- `RedirectToCanonicalHost`: permintaan GET/HEAD ke host lain (www, domain ujian Forge) dialih
  301 ke laluan yang sama di APP_URL, supaya Google nampak satu alamat. POST (callback CHIP,
  Livewire) dan `/up` tidak dialih. Tidak aktif untuk localhost/IP; boleh dimatikan dengan
  `CANONICAL_REDIRECT=false`.

## 2026-10-10 — Batalkan carian

- Pengguna boleh batalkan carian yang belum siap dari skrin Cari (sebelum ini perlu arahan
  Forge). Status `cancelled` ("Dibatalkan") dikira sebagai selesai.
- Kerja yang sudah dibuat sebelum batal tetap dicaj (`WalletService::settle`), kerana kos
  Places/AI itu sudah berlaku. Ini pilihan paling selamat untuk kunci pusat.
- Job yang sedang berjalan semak status dari pangkalan data sebelum setiap halaman Text Search,
  setiap Place Details dan setiap panggilan AI, jadi ia berhenti cepat. `Search::markStatus()`
  tidak menulis ganti status Dibatalkan (job yang gagal atau siap selepas itu tidak mengubahnya).
- Query batal guna skop workspace biasa: carian pelanggan lain tidak dijumpai.

## 2026-10-10 — Padam carian

- "Padam" hanya menyembunyikan carian (`hidden_at`), tidak memadam baris. Lead, `ai_usage`,
  `places_usage` dan transaksi baki merujuk `search_id`; padam sebenar akan putuskan pautan
  itu dan mengganggu sejarah caj dan kos. Pilihan paling selamat.
- Hanya carian yang sudah selesai boleh dipadam; yang masih berjalan perlu dibatalkan dahulu.

## 2026-10-10 — Sandaran worker giliran

- Di pelayan, worker Forge tidak mengambil kerja walaupun ditunjukkan "Running", tetapi
  `queue:work --once` berjaya. Supaya carian pelanggan tidak tersangkut "Dalam giliran",
  scheduler juga menjalankan `queue:work --stop-when-empty --max-time=50` setiap minit.
- `withoutOverlapping(15)` menghalang dua salinan sandaran serentak. Jika worker Forge juga
  hidup, kedua-duanya berkongsi giliran dengan selamat: job yang diambil tidak diambil semula
  sebelum `retry_after` (900 saat), jadi tiada kos AI berganda.
- Boleh dimatikan dengan `QUEUE_VIA_SCHEDULER=false` bila pindah ke Redis/Horizon.

## 2026-10-10 — Tanda produk ialah contoh

- Lead "Construction" untuk produk website dinilai 15 kerana tanda produk (dari templat
  murahwebsite: "pelancong cari menu/lokasi") dibaca sebagai syarat. Prompt `score-v2` jelaskan
  tanda ialah contoh; nilaian ikut "Apa produk buat". Templat tanda murahwebsite dibuat umum.
- Jana semula lead yang tidak sesuai menilai semula (satu panggilan model murah tambahan), kerana
  profil produk atau prompt mungkin sudah berubah. Had jana semula setiap lead kekal.

## 2026-10-10 — Pelanggan isi fakta, AI tulis ayat

- Bob: pelanggan tidak patut menulis ayat jualan sendiri. Medan "Apa produk buat" kini "Fakta
  produk"; prompt `write-v2` minta AI tulis semula fakta itu dengan menarik, kekalkan harga,
  tarikh dan syarat tepat, dan tidak menambah fakta (peraturan 6 kekal; `MessageValidator`
  masih tolak harga RM yang tiada dalam profil).
- Fakta tetap wajib: AI tidak boleh reka harga atau promosi, jadi sumbernya mesti pelanggan.
- CTA pilihan. Jika kosong, `Product::ctaText()` beri ayat tetap dalam kod (bukan reka AI).
