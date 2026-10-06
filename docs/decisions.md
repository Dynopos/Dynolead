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
