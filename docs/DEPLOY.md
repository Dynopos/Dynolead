# Deploy Dyno Leads ke Laravel Forge

Panduan ini untuk Fasa 0 (satu akaun, Bob sahaja). Ikut tertib dari atas ke bawah.

## 0. Sebelum mula

Sediakan dua kunci API dan simpan di tempat selamat (jangan masuk repo):

1. **Anthropic API key**
   - Buka <https://console.anthropic.com> → *API Keys* → cipta key baru.
   - *Billing*: tambah kredit.
   - *Limits*: set **had belanja bulanan** di console juga. Ini lapisan kedua selain had
     dalam app (`AI_MONTHLY_BUDGET_MYR`).
2. **Google Places API key**
   - Buka Google Cloud Console → cipta/pilih projek → sambung **billing**.
   - *APIs & Services → Library* → enable **Places API (New)**.
   - *Credentials* → *Create credentials → API key*.
   - Hadkan key itu:
     - *API restrictions*: **Places API (New)** sahaja.
     - *Application restrictions*: **IP addresses**, isi IP pelayan Forge (semua panggilan
       Places dibuat dari pelayan, bukan dari telefon).
   - Pilihan: set *quota* harian dalam *Places API (New) → Quotas* sebagai brek kecemasan.

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
| `APP_LOGIN_PASSWORD` | Kata laluan panjang untuk masuk app (Bob sahaja) |
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
| `AI_MONTHLY_BUDGET_MYR` | `100` (boleh ubah kemudian di halaman Kos) |
| `GOOGLE_PLACES_API_KEY` | Key dari langkah 0 |
| `PLACES_CACHE_HOURS` | `24` |
| `PRICE_TABLE_PATH` | `config/ai_prices.php` |

## 4. Isi harga dalam `config/ai_prices.php` (wajib sebelum carian pertama)

Selagi harga model kosong, app **tidak akan panggil Claude** dan skrin Cari/Kos tunjuk
amaran. Ini sengaja supaya had kos bulanan sentiasa betul.

1. Buka `config/ai_prices.php` dalam repo.
2. Isi harga semasa dari <https://www.anthropic.com/pricing> (USD setiap 1 juta token)
   untuk kedua-dua model: `input`, `output`, `cache_write` (tulis cache 5 minit),
   `cache_read`.
3. Isi harga Places (USD setiap panggilan) dari halaman harga Google Maps Platform:
   `text_search` (Text Search Enterprise), `details` (Place Details Enterprise +
   Atmosphere, sebab ada review), `details_display` (Place Details Enterprise, tanpa review).
4. Isi `usd_to_myr` dengan kadar tukaran semasa.
5. Commit dan push. Deploy semula (langkah 5) supaya `config:cache` ambil harga baru.

Jika tukar model dalam `.env`, tambah baris harga untuk ID model baru juga.

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
$FORGE_PHP artisan db:seed --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan queue:restart
```

Nota:
- `db:seed` selamat dijalankan setiap kali: `ProductSeeder` hanya cipta DynoPOS dan
  murahwebsite.my jika belum wujud (ikut `slug`). Suntingan Bob di skrin Produk tidak
  ditimpa.
- Deploy pertama sama dengan `php artisan migrate --seed --force`.
- Tekan **Deploy Now**. Hidupkan *Quick Deploy* jika mahu deploy automatik bila push.

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
Satu proses sudah cukup untuk Fasa 0.

## 7. Scheduler (cron)

Pelayan → *Scheduler* → *New Scheduled Job*:

- Command: `php /home/forge/DOMAIN-ANDA/current/artisan schedule:run`
  (atau `/home/forge/DOMAIN-ANDA/artisan` jika site tidak guna zero-downtime deploy)
- User: `forge`
- Frequency: **Every Minute**

Jadual sekarang (`routes/console.php`):

- `purge-place-cache` setiap hari 03:15 dan `purge-place-cache-hourly` setiap jam:
  padam data Google dalam `place_cache` yang lebih lama daripada `PLACES_CACHE_HOURS`.

Semak dengan SSH: `php artisan schedule:list`.

## 8. Semakan selepas deploy

1. Buka `https://domain-anda/masuk`, masuk dengan `APP_LOGIN_PASSWORD`.
2. **Produk**: DynoPOS dan murahwebsite.my ada.
3. **Kos**: tiada amaran "Harga belum diisi". Had bulanan betul.
4. **Cari** (test pertama, kecil): produk DynoPOS, jenis `kedai runcit`, kawasan
   `Pasir Mas, Kelantan`, maksimum calon **10**. Tekan *Cari*, baca anggaran, tekan
   *Sahkan & cari*. Tunggu status *Siap* (skrin auto-segar).
5. **Lead**: semak skor, "Kenapa sesuai" dan mesej. Tekan *Buka WhatsApp* untuk satu
   lead dan pastikan nombor `601...` dan mesej betul. **Jangan hantar** jika belum mahu.
6. **Kos**: lihat kos sebenar carian tadi (token, RM, panggilan Places). Bandingkan
   dengan anggaran di skrin Cari.
7. Jika carian tersekat di "Dalam giliran": queue worker tidak berjalan (langkah 6).

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

## 10. Kemas kini

Push ke branch site. Jika Quick Deploy hidup, Forge deploy sendiri; jika tidak, tekan
*Deploy Now*. Skrip deploy sudah jalankan migration dan `queue:restart`.
