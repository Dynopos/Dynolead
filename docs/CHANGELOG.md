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

### Langkah 4 — Skrin
- **Produk:** senarai + borang tambah/edit (varian pitch, perkataan dilarang, tapisan).
- **Cari:** anggaran kos sebelum jalan + butang sahkan, kemajuan dicari → ditapis →
  dinilai → siap, kos sebenar setiap carian.
- **Lead:** ringkasan ikut status, penapis (produk, jenis, status, kawasan), kad lead
  (rating, skor, "Kenapa sesuai", amaran, mesej), Buka WhatsApp (wa.me), Salin mesej,
  Edit, Jana semula (dengan pengesahan kos), dropdown status, nota, atribusi Google.
  Talian tetap: "Telefon atau singgah".
- **Follow-up:** lead "Dah hantar" > 3 hari, jana mesej follow-up (model murah), Dah follow-up.
- **Kos:** token, kos RM, panggilan Places bulan ini, had bulanan boleh ubah, bar kemajuan,
  50 panggilan AI terakhir.
- Tolak → `suppressions`; Dah hantar → `contacts_log`.
- Semua skrin diuji pada lebar 400px (tiada skrol mendatar).

### Langkah 5 — Semakan §10 dan deploy
- `tests/Feature/Phase0AcceptanceTest.php`: satu test untuk setiap kotak §10.
- `tests/Feature/HardRulesTest.php`: peraturan 1 (tiada hantar automatik), 4 (data Google),
  7 (tiada rahsia, model dari `.env`).
- Betulkan: kawasan "Pasir Mas, Kelantan" tidak lagi dipecah dua.
- `ProductSeeder` tidak menimpa suntingan produk.
- `docs/DEPLOY.md`: langkah deploy ke Laravel Forge (env, harga, migrate --seed, queue
  worker, cron scheduler, semakan selepas deploy).
- `docs/fasa0-semakan.md`: pemetaan kotak §10 ke test.
- 129 test lulus tanpa panggilan API sebenar.

### Reka bentuk semula antara muka
- Font Inter (dipasang melalui npm, tiada CDN luar), ikon SVG dalam talian, logo dan favicon.
- Header dengan logo, menu bawah dengan penanda aktif dan ruang selamat iPhone.
- Lead: kad dengan avatar, rating, cip kawasan/telefon/produk, kotak "Kenapa sesuai",
  pratonton mesej gaya WhatsApp (dengan "Baca penuh"), butang WhatsApp utama, butang
  Salin/Edit/Jana semula padat, status dengan titik warna, nota boleh dilipat, rentak
  "Hantar hari ni" (x / 15).
- Cari: pilih produk sebagai kad, cip jenis bisnes, gelangsar bilangan calon, anggaran
  gaya resit, kemajuan carian sebagai langkah (Dicari → Ditapis → Dinilai → Siap).
- Kos: kad utama kos vs had (warna ikut peratus), jubin statistik, senarai panggilan.
- Produk: kad produk, borang berseksyen dengan suis togol. Follow-up: kad selaras Lead.
- Skrin masuk baru. Semua skrin disemak pada 400px tanpa skrol mendatar.

## Fasa 2

### F2.1 — Laravel 12
- Naik taraf ke Laravel 12.69; `composer audit` bersih.

### F2.2 — Akaun dan workspace
- Daftar, masuk dengan e-mel, lupa/tukar kata laluan (e-mel dalam BM), halaman Akaun.
- Workspace setiap pelanggan; semua data lead, produk, STOP, kos diasingkan.
- Arahan `dynoleads:admin` untuk akaun Bob dan produk demo.
- Terma dan Polisi Privasi (draf). Jenama DynoPOS dibuang dari antara muka.

### F2.3 — Pelan, kuota, onboarding
- `config/plans.php` (percubaan, asas, pro, dalaman) dan `PlanService`.
- Had lead sebulan, bilangan produk, calon setiap carian, had AI setiap pelanggan + had
  platform. Akaun tamat tidak boleh cari atau guna AI.
- Wizard produk pertama dengan templat. Halaman Langganan (pelan dan penggunaan).
- Halaman Kuota untuk pelanggan; butiran kos RM untuk admin sahaja.

### F2.4 — Bayaran CHIP dan admin
- `ChipClient`, `BillingService`, jadual `payments`, callback bertandatangan RSA yang
  disahkan semula dengan API CHIP, halaman pulang, job semakan setiap jam.
- Halaman Langganan: langgan/sambung 30 hari, sejarah bayaran.
- Panel admin untuk Bob: pelanggan, hasil, kos platform, bayaran manual, gantung.

### F2.5 — Halaman jualan, dokumentasi
- Halaman utama awam (BM) dengan harga dari `config/plans.php`, soalan lazim, meta SEO,
  JSON-LD, imej Open Graph, `sitemap.xml` dan `robots.txt` dinamik.
- `docs/DEPLOY.md` dikemas kini untuk Fasa 2 (CHIP, SMTP, akaun admin, senarai sebelum jual).
- `docs/fasa2-semakan.md`: pemetaan ciri ke test. CLAUDE.md dan spec dikemas kini.
- 175 test lulus.

### F2.6 — Bayar ikut carian (kredit)
- Kredit prabayar ganti pelan bulanan: 1 kredit setiap 20 calon, dipotong bila carian
  bermula, dipulangkan jika tiada lead. 3 kredit percuma bila daftar.
- Pek kredit melalui CHIP; halaman Kredit (baki + sejarah) dan Tambah kredit.
- Jana semula / follow-up percuma dengan had setiap lead.
- Admin: beri kredit, rekod bayaran manual pek, statistik kredit dijual/diguna.
- Halaman jualan, Terma dan FAQ dikemas kini untuk model kredit.

### F2.7 — Percubaan, aktif sekali, bayar ikut guna
- Ganti model kredit: percubaan 20 lead / 14 hari, yuran aktif RM23.90 sekali bayar,
  baki RM prabayar yang ditolak ikut kos sebenar (AI + Google Places) + 20%.
- `UsageMeter` dan `WalletService`; caj berperingkat, carian berhenti bila baki habis.
- Halaman Bayaran (status percubaan, aktifkan, tambah baki), Baki (sejarah), anggaran RM
  di skrin Cari, admin (yuran aktif, tambah baki, untung guna, lanjut percubaan).
- Halaman jualan, FAQ dan Terma dikemas kini. 187 test lulus.
- Kekalkan caj Google Places (keputusan Bob). Carian berbayar disekat selagi harga Places
  belum diisi, supaya Places tidak dicaj RM0. 188 test lulus.
- Harga Claude diisi dalam `config/ai_prices.php` (Haiku 4.5, Sonnet 5.5). Harga Places dan
  `usd_to_myr` masih untuk Bob isi.
- `usd_to_myr` = 4.70 (Bob). Harga Google Places masih untuk Bob isi.
- Harga Google Places diisi ikut harga senarai Google: Text Search Enterprise $0.035,
  Place Details Enterprise + Atmosphere $0.025, Place Details Enterprise $0.020 setiap panggilan.
- `dynoleads:admin` boleh dijalankan dari Forge Commands (tanpa SSH): daftar dahulu di
  `/daftar`, kemudian command itu jadikan akaun admin dan workspacenya pelan `dalaman`.
- Halaman jualan: hook baru ikut Bob ("Susah cari customer? Pening sales slow? Biar Dyno
  Leads bantu anda cari lead." Kami cari lead, kami sediakan teks, anda cuma tekan hantar).
- Halaman jualan: harga "Bayar ikut lead" (anggaran RM setiap lead dan bilangan lead untuk
  RM20/RM50/RM100), dikira dari jadual harga oleh `PriceGuide`. FAQ harga dikemas kini.
- Logo Dyno Lead (dinosaur) di header, footer, halaman masuk/daftar, favicon dan ikon skrin
  utama telefon. Logo penuh dalam `public/images/logo.png` (JSON-LD).
- WhatsApp syarikat (`COMPANY_WHATSAPP`, lalai 018-288 9932): butang "WhatsApp kami" terapung
  di halaman jualan, nombor di footer, Terma dan Privasi. Pautan `wa.me` dengan mesej awal.
- Nama produk ditukar ke **Dyno Lead**. Warna oren dari logo untuk butang ajakan dan sorotan
  di halaman jualan (`btn-accent`); hijau kekal warna utama app.
- Halaman jualan: voice note Bob (cuba main bila dibuka, atau pada sentuhan pertama), barisan
  "Khas untuk anda yang jual..." bergerak, kotak kuning pada "Susah cari customer?", dan
  bahagian muncul perlahan semasa skrol.
- Halaman jualan: video demo app (data contoh) dalam bingkai telefon. Voice note kini mula
  pada sentuhan jari pertama di telefon (sebelum ini hanya berfungsi dengan klik tetikus).
- Voice note cuba main setiap kali halaman dibuka; jalur "Ketik skrin untuk dengar pesanan Bob"
  semasa pelayar menunggu ketikan.
- Domain `dynolead.my`: www dan domain lain dialih 301 ke `https://dynolead.my`.
- Butang **Batalkan** pada carian yang belum siap (skrin Cari). Status baru "Dibatalkan";
  job dalam giliran melangkau carian itu, job yang sedang berjalan berhenti sebelum panggilan
  berbayar seterusnya. Kerja yang sudah dibuat dicaj seperti biasa (percuma dalam percubaan).
- Butang **Padam** pada carian yang sudah selesai: buang dari senarai "Carian terkini".
  Rekod kos, caj dan lead kekal (lajur `searches.hidden_at`).
- Sandaran giliran: scheduler (setiap minit) juga proses giliran sehingga kosong, supaya carian
  tetap berjalan jika worker Forge mati (`QUEUE_VIA_SCHEDULER`, lalai hidup).
- Skrin Cari: carian yang siap tanpa lead kini terangkan sebabnya (Google tak jumpa apa-apa,
  atau semua calon ditapis) dan senarai "Sebab calon ditapis" dengan bilangan setiap sebab.
- Prompt nilai `score-v2`: "Tanda kedai perlukan produk ini" ialah contoh, bukan syarat; AI tidak
  boleh andaikan kegunaan produk yang tiada dalam profil (contoh "website untuk menu").
- "Jana semula" pada lead Tak sesuai kini menilai semula lead itu; jika sesuai, status kembali
  Baru dan mesej ditulis. Sebelum ini butang itu tidak buat apa-apa pada lead Tak sesuai.
- Profil produk lebih mudah: pelanggan isi **fakta produk** sahaja (apa dijual, harga, promosi).
  AI tulis semula fakta itu menjadi ayat jualan yang menarik untuk setiap kedai (`write-v2`),
  tanpa menambah fakta. Ayat ajakan (CTA) kini pilihan; jika kosong, mesej ditutup dengan
  "Kalau berminat, balas je mesej ni" (dengan kontak jika ada). Tanda kedai, perkataan dilarang
  dan varian dipindah ke "Tetapan lanjutan (pilihan)".
- `dynoleads:admin`: jika e-mel tiada dan tiada kata laluan diberi (contoh dari Forge Commands),
  arahan beritahu "Tiada akaun dengan e-mel ..." dan bukan "Kata laluan terlalu pendek".
