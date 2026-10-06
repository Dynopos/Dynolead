# Dyno Leads

App prospek untuk SME Malaysia dalam ekosistem DYNOPRO (DynoPOS Technologies).
Cari bisnes tempatan, AI nilai dan tulis mesej WhatsApp custom, jejak status lead.

- Spec: [docs/dyno-leads-spec.md](docs/dyno-leads-spec.md)
- Arahan untuk Claude Code: [CLAUDE.md](CLAUDE.md)
- Deploy ke Forge: [docs/DEPLOY.md](docs/DEPLOY.md)
- Semakan Fasa 0 (§10): [docs/fasa0-semakan.md](docs/fasa0-semakan.md)
- Keputusan reka bentuk: [docs/decisions.md](docs/decisions.md)
- Changelog: [docs/CHANGELOG.md](docs/CHANGELOG.md)

Status: Fasa 0 siap dibina dan diuji. Belum deploy.

## Jalankan secara lokal

```bash
composer install
npm install && npm run build
cp .env.example .env            # isi APP_LOGIN_PASSWORD, kunci API, DB
php artisan key:generate
php artisan migrate --seed
php artisan serve               # http://127.0.0.1:8000/masuk
php artisan queue:work          # tetingkap lain: pipeline carian berjalan dalam queue
```

Isi harga dalam `config/ai_prices.php` sebelum carian pertama (app tidak panggil AI
selagi harga kosong).

## Test

```bash
php artisan test
```

Semua test guna `Http::fake()`; tiada panggilan API sebenar.
