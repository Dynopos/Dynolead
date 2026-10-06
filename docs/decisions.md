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
