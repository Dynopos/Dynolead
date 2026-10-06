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
