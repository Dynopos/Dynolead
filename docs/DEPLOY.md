# Deploy Dyno Leads ke Laravel Forge

Panduan ini untuk Fasa 2 (app dijual kepada ramai pelanggan, setiap pelanggan ada
workspace sendiri). Ikut tertib dari atas ke bawah. Senarai "Sebelum mula jual" di
bahagian 11 mesti selesai sebelum terima pelanggan berbayar.

## 0. Sebelum mula

Sediakan kunci berikut dan simpan di tempat selamat (jangan masuk repo). Semua pelanggan
guna kunci pusat ini; kos dikawal oleh had pelan dan had platform.

1. **Anthropic API key**
   - Buka <https://console.anthropic.com> → *API Keys* → cipta key baru.
   - *Billing*: tambah kredit.
   - *Limits*: set **had belanja bulanan** di console juga. Ini lapisan kedua selain had
     platform dalam app (`AI_MONTHLY_BUDGET_MYR`).
2. **Google Places API key**
   - Buka Google Cloud Console → cipta/pilih projek → sambung **billing**.
   - *APIs & Services → Library* → enable **Places API (New)**.
   - *Credentials* → *Create credentials → API key*.
   - Hadkan key itu:
     - *API restrictions*: **Places API (New)** sahaja.
     - *Application restrictions*: **IP addresses**, isi IP pelayan Forge (semua panggilan
       Places dibuat dari pelayan, bukan dari telefon).
   - Pilihan: set *quota* harian dalam *Places API (New) → Quotas* sebagai brek kecemasan.
3. **CHIP (bayaran)**
   - Daftar akaun merchant di <https://portal.chip-in.asia> dan lengkapkan pengesahan
     (CHIP biasanya minta pautan Terma, Privasi dan polisi bayaran balik).
   - *Developers → API keys*: salin **secret key**. Guna kunci **ujian** dahulu; tukar ke
     kunci **live** bila sedia (mod ditentukan oleh kunci, bukan URL).
   - *Developers → Brands*: salin **Brand ID**.
   - Tiada webhook perlu didaftar: setiap pembelian membawa `success_callback` sendiri
     ke `https://domain-anda/chip/callback`.
4. **E-mel keluar (SMTP)** untuk reset kata laluan: contoh Mailgun, Postmark, Amazon SES
   atau SMTP domain anda.

## 1. Pelayan

Dalam Forge, guna pelayan sedia ada atau cipta baru:

- PHP **8.3** (minimum 8.2)
- MySQL 8
- Node 20+ (untuk `npm run build`)

## 2. Cipta site

1. *Sites → New Site*: domain (cth `leads.dynopro.my`, lihat soalan terbuka §11 spec),
   project type **General PHP / Laravel**, web directory `/public`.
2. *Create database*: tanda dan beri nama `dynolead`.
3. Selepas site dicipta: *Git Repository* → provider GitHub → repo **`Dynopos/Dynolead`**,
   branch `main` (atau branch yang sudah di-merge). **Jangan** tanda "Install Composer
   dependencies" jika anda akan ikut skrip deploy di bawah (skrip itu sudah buat).
4. *SSL* → Let's Encrypt. App guna cookie `secure`, jadi HTTPS wajib.

## 3. Isi `.env`

Site → *Environment*. Mula dari `.env.example` dan isi:

| Pembolehubah | Nilai |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://domain-anda` |
| `APP_KEY` | Forge jana sendiri; jika kosong, jalankan `php artisan key:generate --force` sekali |
| `MAIL_*` | Tetapan SMTP dari langkah 0 (`MAIL_FROM_ADDRESS` mesti domain yang disahkan) |
| `COMPANY_NAME`, `COMPANY_REGISTRATION`, `COMPANY_EMAIL`, `COMPANY_ADDRESS` | Butiran penjual (muncul di Terma, Privasi, halaman utama) |
| `CHIP_SECRET_KEY`, `CHIP_BRAND_ID` | Dari langkah 0 (kunci ujian dahulu) |
| `TRIAL_LEADS`, `TRIAL_DAYS`, `ACTIVATION_FEE_MYR`, `USAGE_MARKUP_PERCENT`, `BILL_PLACES_COST` | Lihat 4b |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Dari Forge |
| `QUEUE_CONNECTION` | `database` |
| `DB_QUEUE_RETRY_AFTER` | `900` (mesti lebih besar daripada `--timeout` worker) |
| `SESSION_SECURE_COOKIE` | `true` |
| `ANTHROPIC_API_KEY` | Key dari langkah 0 |
| `CLAUDE_MODEL_SCORE` | `claude-haiku-4-5-20251001` |
| `CLAUDE_MODEL_WRITE` | `claude-sonnet-5-5` |
| `CLAUDE_EFFORT_SCORE` | kosong (Haiku 4.5 tidak sokong `effort`) |
| `CLAUDE_EFFORT_WRITE` | `low` |
| `CLAUDE_USE_BATCH` | `false` |
| `CLAUDE_WEB_SEARCH` | `false` |
| `AI_MONTHLY_BUDGET_MYR` | Had kos AI **semua pelanggan bersama** sebulan (RM). Naikkan bila penggunaan bertambah, dan sentiasa di bawah had di console Anthropic. |
| `GOOGLE_PLACES_API_KEY` | Key dari langkah 0 |
| `PLACES_CACHE_HOURS` | `24` |
| `PRICE_TABLE_PATH` | `config/ai_prices.php` |

## 4. Isi harga dalam `config/ai_prices.php` (wajib sebelum carian pertama)

Selagi harga model kosong, app **tidak akan panggil Claude** dan skrin Cari/Kos tunjuk
amaran. Ini sengaja supaya had kos bulanan sentiasa betul.

1. Buka `config/ai_prices.php` dalam repo.
2. Harga Claude **sudah diisi** (Oktober 2026): Haiku 4.5 $1/$5, Sonnet 5.5 $2/$10 setiap
   1 juta token, dengan cache. Semak semula dengan <https://www.anthropic.com/pricing>
   bila Anthropic ubah harga atau bila tukar model.
3. Isi harga Places (USD setiap panggilan) dari halaman harga Google Maps Platform:
   `text_search` (Text Search Enterprise), `details` (Place Details Enterprise +
   Atmosphere, sebab ada review), `details_display` (Place Details Enterprise, tanpa review).
4. Isi `usd_to_myr` dengan kadar tukaran semasa.
5. Commit dan push. Deploy semula (langkah 5) supaya `config:cache` ambil harga baru.

Jika tukar model dalam `.env`, tambah baris harga untuk ID model baru juga.

### 4b. Model bayaran dalam `config/billing.php` (atau `.env`)

| Tetapan | Default | Maksud |
|---|---|---|
| `TRIAL_LEADS` | 20 | Had lead percubaan percuma |
| `TRIAL_DAYS` | 14 | Had hari percubaan (mana dulu) |
| `ACTIVATION_FEE_MYR` | 23.90 | Yuran aktif, sekali bayar |
| `USAGE_MARKUP_PERCENT` | 20 | Caj = kos sebenar × 1.20 |
| `BILL_PLACES_COST` | true | Masukkan kos Google Places dalam caj (disyorkan). Carian berbayar disekat selagi harga `places` dalam `config/ai_prices.php` kosong |
| `topup_options` | 20, 50, 100 | Pilihan tambah baki (RM), dalam `config/billing.php` |

**Penting:** caj pelanggan dikira dari harga dalam `config/ai_prices.php`. Pastikan harga
di situ sama atau lebih tinggi daripada harga sebenar Anthropic dan Google, termasuk
`usd_to_myr`. Semak Panel admin → "Caj guna / untung" setiap bulan.

## 5. Skrip deploy

Site → *Deployments* → *Deploy Script*. Ganti dengan:

```bash
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan queue:restart
```

Nota:
- Tekan **Deploy Now**. Hidupkan *Quick Deploy* jika mahu deploy automatik bila push.
- Migration Fasa 2 memindah data Fasa 0 (jika ada) ke workspace "DynoPOS Technologies".

### 5b. Cipta akaun admin (sekali sahaja)

SSH ke pelayan, dalam folder site:

```bash
php artisan dynoleads:admin bob@dynopos.my --demo-products
```

Arahan ini tanya kata laluan, cipta akaun Bob (admin platform) dengan workspace pelan
`dalaman` (tanpa had), dan masukkan produk DynoPOS + murahwebsite.my. Jika ada data
Fasa 0 yang dipindah, Bob terus jadi pemilik workspace itu.

## 6. Queue worker

Site → *Queue* → *New Worker*:

| Medan | Nilai |
|---|---|
| Connection | `database` |
| Queue | `default` |
| Maximum Seconds Per Job (timeout) | `660` |
| Rest Seconds When Queue Is Empty (sleep) | `3` |
| Maximum Tries | `2` |
| Processes | `1` |

Bersamaan dengan:

```bash
php artisan queue:work database --queue=default --sleep=3 --timeout=660 --tries=2
```

Kenapa: job nilai/tulis memproses sehingga 60 lead dalam satu job (setiap job ada
`$timeout = 600`). `DB_QUEUE_RETRY_AFTER=900` mesti lebih besar daripada timeout supaya
job yang masih berjalan tidak diambil oleh worker lain (itu akan gandakan kos AI).
Mula dengan 1 proses. Bila ramai pelanggan buat carian serentak, naikkan `Processes`
(2–4) supaya carian tidak beratur lama.

## 7. Scheduler (cron)

Pelayan → *Scheduler* → *New Scheduled Job*:

- Command: `php /home/forge/DOMAIN-ANDA/current/artisan schedule:run`
  (atau `/home/forge/DOMAIN-ANDA/artisan` jika site tidak guna zero-downtime deploy)
- User: `forge`
- Frequency: **Every Minute**

Jadual sekarang (`routes/console.php`):

- `purge-place-cache` setiap hari 03:15 dan `purge-place-cache-hourly` setiap jam:
  padam data Google dalam `place_cache` yang lebih lama daripada `PLACES_CACHE_HOURS`.
- `sync-pending-payments` setiap jam: semak bayaran CHIP yang callbacknya terlepas.

Semak dengan SSH: `php artisan schedule:list`.

## 8. Semakan selepas deploy

1. Buka `https://domain-anda/`: halaman jualan keluar, harga dan Terma/Privasi betul.
2. Buka `/masuk`, masuk dengan akaun admin (langkah 5b).
3. **Kos** (admin): tiada amaran "Harga belum diisi". Akaun → **Panel admin** terbuka.
4. **Cari** (test pertama, kecil): produk DynoPOS, jenis `kedai runcit`, kawasan
   `Pasir Mas, Kelantan`, maksimum calon **10**. Tekan *Cari*, baca anggaran, tekan
   *Sahkan & cari*. Tunggu status *Siap* (skrin auto-segar).
5. **Lead**: semak skor, "Kenapa sesuai" dan mesej. Tekan *Buka WhatsApp* untuk satu
   lead dan pastikan nombor `601...` dan mesej betul. **Jangan hantar** jika belum mahu.
6. **Kos**: lihat kos sebenar carian tadi (token, RM, panggilan Places). Bandingkan
   dengan anggaran di skrin Cari. Guna angka ini untuk tetapkan harga pelan (4b).
7. **Pelanggan ujian**: dalam tetingkap inkognito, daftar akaun baru di `/daftar`,
   lalui wizard, buat satu carian kecil. Pastikan ia tidak nampak lead Bob.
8. **Lupa kata laluan**: cuba dengan akaun ujian, pastikan e-mel sampai.
9. **Bayaran (kunci ujian CHIP)**: dari akaun ujian buka *Bayaran*, tekan *Aktifkan akaun*,
   bayar dengan kad ujian `4444 3333 2222 1111` (CVC `123`). Selepas kembali, akaun aktif.
   Kemudian *Tambah baki RM20*, buat satu carian kecil, dan semak caj di halaman *Baki*
   dan untung di Panel admin. Kemudian tukar ke kunci live.
10. Jika carian tersekat di "Dalam giliran": queue worker tidak berjalan (langkah 6).

## 9. Masalah biasa

| Gejala | Punca / penyelesaian |
|---|---|
| Carian "Gagal": *Harga model ... belum diisi* | Isi `config/ai_prices.php`, push, deploy. |
| Carian "Had kos AI dicapai" | Naikkan had di halaman Kos, atau tunggu bulan depan. |
| Carian "Gagal": *Google Places ... 403* | Places API (New) belum enable, billing belum sambung, atau sekatan IP key salah. |
| Carian "Gagal": *Claude API gagal (401)* | `ANTHROPIC_API_KEY` salah. |
| Carian "Gagal": *Claude API gagal (400)* dengan `effort` | Model dalam `CLAUDE_MODEL_*` tak sokong `effort`; kosongkan `CLAUDE_EFFORT_*`. |
| Status tak bergerak dari "Dalam giliran" | Queue worker mati. Forge → Queue → restart. |
| Ubah `.env` tapi tiada kesan | Jalankan `php artisan optimize` (atau deploy semula). |
| Lead tiada nama ("Nama tak dapat dimuat") | Cache tamat dan Places gagal. Semak log (`storage/logs`). |
| Pelanggan nampak "Perkhidmatan AI belum sedia" | Harga model dalam `config/ai_prices.php` kosong. |
| "Perkhidmatan AI berehat sekejap" | Had platform `AI_MONTHLY_BUDGET_MYR` dicapai. Naikkan (dan had di console Anthropic) atau tunggu bulan depan. |
| Bayar tapi akaun tak aktif / baki tak masuk | Semak log untuk "CHIP". Job `sync-pending-payments` akan cuba lagi setiap jam; admin boleh rekod bayaran manual di Panel admin. |
| "Sistem bayaran tak dapat dihubungi" | `CHIP_SECRET_KEY`/`CHIP_BRAND_ID` salah atau kosong. |
| E-mel reset tak sampai | Semak `MAIL_*` dan log; pastikan domain pengirim disahkan (SPF/DKIM). |

## 10. Kemas kini

Push ke branch site. Jika Quick Deploy hidup, Forge deploy sendiri; jika tidak, tekan
*Deploy Now*. Skrip deploy sudah jalankan migration dan `queue:restart`.

## 11. Sebelum mula jual (bukan kerja kod)

- [ ] **Peguam semak Terma dan Polisi Privasi** (`/terma`, `/privasi` masih bertanda DRAF).
      Buang amaran DRAF dalam `resources/views/legal/*.blade.php` selepas disemak.
- [ ] **Syarat Google Maps Platform** untuk perkhidmatan yang dijual semula (spec §6).
      Catat keputusan dalam `docs/decisions.md`.
- [ ] **Harga AI dan Places tepat** dalam `config/ai_prices.php` (caj pelanggan bergantung padanya).
- [ ] **CHIP live**: akaun merchant disahkan, tukar ke kunci live.
- [ ] **Polisi bayaran balik** jelas (Terma §4: yuran aktif dan tambahan baki tidak
      dikembalikan) dan sepadan dengan apa yang CHIP minta.
- [ ] **Pendaftaran PDPA** jika perlu untuk kategori perniagaan anda.
- [ ] **Google Search Console**: sahkan domain dan hantar `https://domain-anda/sitemap.xml`.
