# Dyno Leads

App prospek untuk SME Malaysia dalam ekosistem DYNOPRO (DynoPOS Technologies).
Cari bisnes tempatan, AI nilai dan tulis mesej WhatsApp custom, jejak status lead.

- Spec: [docs/dyno-leads-spec.md](docs/dyno-leads-spec.md)
- Arahan untuk Claude Code: [CLAUDE.md](CLAUDE.md)
- Deploy ke Forge: [docs/DEPLOY.md](docs/DEPLOY.md)
- Semakan Fasa 0 (§10): [docs/fasa0-semakan.md](docs/fasa0-semakan.md)
- Keputusan reka bentuk: [docs/decisions.md](docs/decisions.md)
- Changelog: [docs/CHANGELOG.md](docs/CHANGELOG.md)

Status: Fasa 0 dan Fasa 2 (versi untuk dijual: akaun, workspace, bayar ikut carian dengan kredit CHIP) siap dibina dan diuji. Belum deploy.

## Jalankan secara lokal

```bash
composer install
npm install && npm run build
cp .env.example .env            # isi kunci API, DB, CHIP, SMTP
php artisan key:generate
php artisan migrate
php artisan dynoleads:admin anda@email.com --demo-products   # akaun admin
php artisan serve               # http://127.0.0.1:8000
php artisan queue:work          # tetingkap lain: pipeline carian berjalan dalam queue
```

Isi harga dalam `config/ai_prices.php` sebelum carian pertama (app tidak panggil AI
selagi harga kosong), dan harga pek kredit dalam `config/credits.php`.

## Test

```bash
php artisan test
```

Semua test guna `Http::fake()`; tiada panggilan API sebenar.
