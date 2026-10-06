# Dyno Leads — Spec v0.1

Pemilik: Bob (DynoPOS Technologies, Pasir Mas)
Status: Fasa 0 belum dibina
Repo: Dynopos/Dynolead (app berasingan dari Dyno Ads)

---

## 1. Masalah dan matlamat

Bob jual beberapa produk kepada SME tempatan (DynoPOS, website RM200 murahwebsite.my,
dan lain-lain). Cari prospek secara manual makan masa: cari kedai di Google Maps, baca
review, fikir sudut jualan, tulis mesej, ingat siapa dah dihubungi.

Dyno Leads buat kerja ini secara separa automatik:

> Pilih produk + jenis bisnes + kawasan → app cari kedai → tapis → AI nilai dan tulis
> mesej custom → Bob tekan butang WhatsApp, semak, hantar sendiri → app jejak status
> dan ingatkan follow-up.

Contoh hasil yang app ini mesti boleh hasilkan (dari kerja manual Okt 2026):

> Salam Kahfi Tomyam Seafood 👋
>
> Saya Bob dari DynoPOS Technologies, Pasir Mas. Kahfi Tomyam dah ada 796 review, ada
> keluarga datang dari JB sebab rasa Thai yang autentik.
>
> Ada pelanggan sebut pasal semak order dan minta resit masa bayar. Dengan POS, setiap
> bil dicetak tepat ikut apa yang dipesan.
>
> DynoPOS ni sistem POS untuk kedai makan: sekali bayar je, takde yuran bulanan atau
> tahunan. [...]
>
> Kalau nak info lanjut, balas je mesej ni atau tengok dynopos.my
>
> Kalau tak berminat, balas STOP, saya tak ganggu lagi 🙏

Struktur mesej: **sapaan → pujian spesifik dari review (hook) → masalah yang review
tunjuk (gap) → apa produk buat → CTA produk → pilihan STOP.**

### Bukan matlamat

- Hantar mesej pertama secara automatik (lihat §8).
- Blast WhatsApp atau e-mel pukal.
- Ganti CRM penuh.

---

## 2. Fasa

| Fasa | Skop | Pengguna |
|---|---|---|
| **0** | Cari → tapis → AI nilai → AI tulis mesej → tracker. Satu akaun, token dalam `.env`. | Bob sahaja |
| 1 | Agen harian (scheduler): cari lead baru setiap pagi, peringatan follow-up, report harian. | Bob sahaja |
| 2 | Login berbilang pengguna, workspace, kuota ikut pelan, bayaran (CHIP / toyyibPay). | Pelanggan berbayar |
| 3 | Balasan prospek: webhook WhatsApp Cloud API, AI draf balasan, manusia luluskan. Hanya untuk prospek yang dah reply. | Pelanggan berbayar |

Claude Code bina **Fasa 0 sahaja** sehingga Bob arahkan fasa seterusnya.

---

## 3. Skrin Fasa 0

Semua skrin mesra telefon (Bob kerja dari phone). Lebar utama 400px mesti selesa.

### 3.1 Produk (`/produk`)

Senarai profil produk + borang tambah/edit. Seed awal: DynoPOS dan murahwebsite.my
(§7). Medan:

- nama, nama pengirim (contoh "Bob"), syarikat
- `pitch_core` — perenggan apa produk buat (ditulis Bob, AI tidak ubah maknanya)
- `cta` — ayat penutup (contoh "Kalau nak info lanjut, balas je mesej ni atau tengok dynopos.my")
- `banned_words` — senarai perkataan dilarang (contoh `demo` untuk DynoPOS)
- `fit_signals` — apa tanda kedai perlukan produk ini (teks bebas, untuk prompt penilaian)
- `filters` — peraturan tapisan: rating minimum, review minimum, `require_no_website` (bool)
- `default_place_types` — jenis bisnes cadangan

### 3.2 Cari (`/cari`)

- Pilih produk
- Jenis bisnes (teks bebas atau pilih dari `default_place_types`), contoh "kedai runcit"
- Kawasan, contoh "Pasir Mas, Kelantan" (boleh lebih dari satu)
- Bilangan maksimum calon (default 20, had keras 60 setiap carian)
- Butang **Cari**. Paparkan anggaran kos sebelum jalan (§9.3) dan minta pengesahan.

Carian berjalan dalam queue. Skrin tunjuk kemajuan: dicari → ditapis → dinilai → siap.

### 3.3 Senarai lead (`/lead`)

- Penapis: produk, jenis, status, kawasan
- Kad setiap lead: nama, rating + bilangan review, jenis, kawasan, nombor, skor AI,
  "Kenapa sesuai", mesej, nota amaran (contoh "Mungkin dah ada sistem, semak dulu")
- Butang **Buka WhatsApp** (wa.me), **Salin mesej**, **Jana semula mesej** (kos AI,
  minta pengesahan), dropdown status, medan nota
- Talian tetap: tiada butang WhatsApp, tunjuk "Telefon atau singgah"
- Atribusi Google pada data Places
- Ringkasan atas: bilangan ikut status

### 3.4 Follow-up (`/follow-up`)

Lead berstatus "Dah hantar" lebih 3 hari tanpa perubahan. Butang jana mesej follow-up
pendek (AI, model murah).

### 3.5 Kos (`/kos`)

- Penggunaan bulan ini: token, anggaran kos RM, bilangan panggilan Places
- Had bulanan (boleh ubah), bar kemajuan
- Senarai 50 panggilan terakhir

---

## 4. Aliran kerja (pipeline)

```
[Cari] ──► Places Text Search (field murah sahaja)
             │  place_id, nama, jenis, rating, userRatingCount, alamat ringkas
             ▼
[Tapis peraturan] ── tiada AI, tiada kos token
             │  buang: suppression, dihubungi < 30 hari (mana-mana produk),
             │  rating < min, review < min, jenis tak padan
             ▼
[Details] ──► Places Place Details untuk calon yang lulus sahaja
             │  telefon, websiteUri, maksimum 5 review
             │  require_no_website=true → buang yang ada websiteUri
             ▼
[Nilai] ──► Claude (CLAUDE_MODEL_SCORE) → JSON {fit, reason, hook, gap, flag}
             │  fit < 50 → simpan sebagai "Tak sesuai", tiada mesej
             ▼
[Tulis] ──► Claude (CLAUDE_MODEL_WRITE) → JSON {message}
             │  semak banned_words, panjang, ada STOP → gagal = jana semula sekali
             ▼
[Tracker] status = Baru
```

Setiap langkah ialah job queue tersendiri supaya boleh diulang tanpa ulang langkah
yang dah siap.

---

## 5. Prompt

Simpan dalam `resources/prompts/`. Setiap fail ada `prompt_version` di baris pertama.
System prompt (profil produk + peraturan) diletak paling atas dan ditanda untuk prompt
caching.

### 5.1 `score.md` (model murah)

Input: profil produk (`fit_signals`), data kedai (nama, jenis, rating, review dipotong).
Output JSON sahaja:

```json
{
  "fit": 0,
  "reason": "satu ayat kenapa sesuai",
  "hook": "pujian spesifik dari review, dalam BM santai",
  "gap": "masalah yang review tunjuk dan produk boleh bantu",
  "flag": null
}
```

Peraturan prompt:
- Hanya guna fakta dari data yang diberi. Jangan reka.
- `flag` diisi bila kedai nampak besar atau berangkai dan mungkin dah ada sistem.
- Review dalam Inggeris boleh dirujuk, tetapi output dalam BM.

### 5.2 `write.md` (model kuat)

Input: profil produk penuh, `hook`, `gap`, nama kedai. Output JSON `{ "message": "..." }`.

Peraturan:
- Ikut struktur mesej §1.
- `pitch_core` dan `cta` dimasukkan seperti ditulis Bob (boleh ubah sedikit untuk
  aliran ayat, maksud tidak berubah).
- Tiada harga, promosi atau janji yang tiada dalam profil produk.
- Maksimum 900 aksara. Mesti berakhir dengan pilihan STOP.

### 5.3 Semakan selepas jana (kod, bukan AI)

- Tiada `banned_words` (case-insensitive)
- Panjang ≤ 900 aksara
- Mengandungi "STOP"
- Gagal → jana semula sekali. Gagal lagi → simpan dengan flag "Semak manual".

---

## 6. Data

### Jadual

- `products` — profil produk (§3.1)
- `searches` — produk, jenis, kawasan, had, status, kos sebenar
- `leads` — `place_id`, `product_id`, `search_id`, `status`
  (`baru|dihantar|reply|deal|tolak|tak_sesuai`), `fit`, `reason`, `hook`, `gap`,
  `flag`, `message`, `prompt_version`, `contacted_at`, `next_followup_at`, `notes`
- `place_cache` — `place_id`, `payload` (JSON), `fetched_at`. Data sementara sahaja.
- `suppressions` — `place_id`, `phone`, `reason`, `created_at`
- `contacts_log` — `place_id`, `product_id`, `contacted_at` (untuk peraturan 30 hari)
- `ai_usage` — `model`, `purpose`, `input_tokens`, `output_tokens`,
  `cache_read_tokens`, `cost_estimate`, `lead_id`, `created_at`
- `settings` — had bulanan dan lain-lain

### Data Google Places

Syarat Google Maps Platform membenarkan `place_id` disimpan tanpa had masa, dan
lat/lng disimpan sementara sehingga 30 hari. Kandungan lain (nama, telefon, review)
tidak dibenarkan secara jelas untuk disimpan. Jadi:

- `leads` simpan `place_id` dan data milik app sendiri (status, nota, hasil AI).
- `place_cache` disimpan pendek (default 24 jam, boleh ubah) supaya paparan tidak
  memanggil API setiap kali. Job harian padam cache yang lama.
- Bila cache tamat, paparan ambil semula Place Details.
- **Sebelum Fasa 2 (dijual), Bob semak syarat semasa Google Maps Platform** dan
  catat keputusan dalam `docs/decisions.md`.

Hasil AI (`reason`, `hook`, `message`) ialah teks app sendiri dan boleh disimpan.

---

## 7. Seed produk

### DynoPOS
- Pengirim: Bob, DynoPOS Technologies, Pasir Mas
- `pitch_core` ikut jenis kedai:
  - runcit: "DynoPOS ni sistem POS untuk kedai kecil & sederhana: sekali bayar je, takde yuran bulanan atau tahunan. Imbas barcode, rekod jualan dan stok, laporan harian boleh tengok dalam telefon."
  - butik: "... Rekod jualan dan stok setiap item, laporan harian boleh tengok dalam telefon."
  - restoran: "DynoPOS ni sistem POS untuk kedai makan: sekali bayar je, takde yuran bulanan atau tahunan. Setiap order dan bil direkod dengan tepat, laporan jualan harian boleh tengok dalam telefon."
- `cta`: "Kalau nak info lanjut, balas je mesej ni atau tengok dynopos.my"
- `banned_words`: demo
- Kontak: 011-1149 6842, 018-792 2844, dynopos.my
- `fit_signals`: kaunter lambat, barang banyak, stok habis, bil/resit tak tepat, banyak
  cawangan, waktu buka panjang, jualan dari beberapa saluran (dine-in + Grab)
- `default_place_types`: kedai runcit, butik pakaian, restoran, kafe

Nota: `pitch_core` perlu sokong varian ikut jenis kedai (medan JSON `pitch_variants`).

### murahwebsite.my
- Pengirim: Bob, murahwebsite.my (DynoPOS Technologies, Pasir Mas)
- `pitch_core`: "Kami boleh siapkan website premium RM200 je (harga asal RM999), siap dalam 2 hari, termasuk domain & hosting, mesra telefon."
- `cta`: "Nak saya hantar contoh website yang kami dah buat? Info: murahwebsite.my"
- Kontak: 018-288 9932
- `filters.require_no_website`: true
- `fit_signals`: review banyak tetapi tiada website, pelancong cari menu/lokasi, perlu
  katalog atau tempahan
- `default_place_types`: restoran, kafe, butik, bengkel, salon, bakeri

---

## 8. WhatsApp

- **Fasa 0–2:** mesej pertama melalui pautan wa.me sahaja. Manusia yang hantar.
- **Fasa 1:** report harian kepada Bob sendiri boleh guna e-mel dahulu. Jika guna
  WhatsApp Cloud API, guna template kategori utility yang diluluskan Meta, ke nombor
  Bob sahaja.
- **Fasa 3:** WhatsApp Cloud API rasmi hanya untuk prospek yang dah reply (dalam
  tetingkap perkhidmatan 24 jam). AI draf balasan, manusia tekan lulus.
- Tidak sekali-kali guna API WhatsApp tidak rasmi.
- Panduan UI: cadangkan 10–15 mesej sehari setiap nombor supaya nombor tidak disekat.

---

## 9. Kos

### 9.1 Pembolehubah `.env`

```
ANTHROPIC_API_KEY=
CLAUDE_MODEL_SCORE=claude-haiku-4-5-20251001
CLAUDE_MODEL_WRITE=claude-sonnet-5-5
CLAUDE_USE_BATCH=false
CLAUDE_WEB_SEARCH=false
AI_MONTHLY_BUDGET_MYR=100
GOOGLE_PLACES_API_KEY=
PLACES_CACHE_HOURS=24
PRICE_TABLE_PATH=config/ai_prices.php
```

Harga token setiap model disimpan dalam `config/ai_prices.php` (Bob kemas kini ikut
harga semasa Anthropic). Jangan tulis harga dalam kod lain.

### 9.2 Penjimatan wajib

1. Tapisan peraturan sebelum Details dan sebelum AI.
2. Field mask Places: Text Search minta field murah sahaja; telefon, website dan review
   hanya dalam Details untuk calon yang lulus tapisan.
3. Model murah untuk nilai, model kuat untuk tulis sahaja.
4. Prompt caching pada system prompt.
5. Review dipotong (5 × 300 aksara).
6. Hasil AI disimpan, jana semula hanya bila pengguna minta.
7. `CLAUDE_USE_BATCH=true` → kerja Fasa 1 (agen malam) guna Message Batches API.

### 9.3 Anggaran sebelum carian

Sebelum carian jalan, tunjuk anggaran:

```
anggaran = (calon × kadar_lulus_tapisan × kos_nilai) + (calon_sesuai × kos_tulis) + kos_places
```

Kadar dan purata token diambil dari purata 30 hari dalam `ai_usage` (default bila tiada
data: lulus 50%, sesuai 60%).

### 9.4 Had

- `AiBudget::assertCanSpend($estimate)` sebelum setiap panggilan. Lebih had → buang
  exception `BudgetExceeded`, job berhenti, UI tunjuk "Had kos AI bulan ini dah
  dicapai. Naikkan had di halaman Kos atau tunggu bulan depan."
- Fasa 2: had ikut workspace dan pelan.

---

## 10. Kriteria siap Fasa 0

- [ ] Seed DynoPOS dan murahwebsite.my
- [ ] Carian "kedai runcit, Pasir Mas" hasilkan senarai lead dengan skor, sebab dan mesej
- [ ] Untuk murahwebsite.my, kedai yang ada `websiteUri` tidak muncul
- [ ] Mesej DynoPOS tidak pernah mengandungi "demo" (test)
- [ ] Butang wa.me betul untuk nombor 01x; tiada butang untuk talian tetap (test)
- [ ] Tolak/STOP → lead hilang untuk semua produk dan tidak dijana semula (test)
- [ ] Kedai yang dihubungi untuk produk A tidak muncul untuk produk B dalam 30 hari (test)
- [ ] Setiap panggilan AI direkod dalam `ai_usage`; had bulanan menghentikan kerja AI (test)
- [ ] Cache Places dipadam selepas `PLACES_CACHE_HOURS` (test)
- [ ] Semua test lulus tanpa panggilan API sebenar
- [ ] Deploy ke Forge, cron scheduler aktif

---

## 11. Soalan terbuka (untuk Bob)

1. Domain app: dynoleads.my atau subdomain dynopro.my?
2. Had kos AI bulanan permulaan (default RM100)?
3. Produk lain untuk seed selepas Fasa 0 (DynoShade, Gubah Bina, Fine Cabinetry)?
4. Harga jualan Fasa 2 dan kuota lead setiap pelan.
