# Vet Clinic Platform

Online veterinary clinic for Iran, launching in Bandar Abbas (Hormozgan). Persian by default, English optional.

| Part | Stack | Folder |
| --- | --- | --- |
| API | Laravel 11, PHP 8.3, PostgreSQL 16, Redis, Sanctum | `api/` |
| Web | React 19 + TypeScript, Vite, Tailwind 4, i18next | `web/` |

Spec and plan: see the *Online Vet Clinic Platform — Spec (Iran)* and *Development Plan* docs. Requirement IDs in code comments (`FR-1.1`) refer to the spec.

## Run locally

With Docker:

```bash
cp api/.env.example api/.env
docker compose up -d
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
```

API on http://localhost:8000, SPA on http://localhost:5173.

Without Docker you need PHP 8.3 (pdo_pgsql, redis, intl, bcmath), PostgreSQL 16, Redis and Node 22:

```bash
cd api && composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan serve
cd web && npm install && npm run dev
```

`SMS_DRIVER=fake` (the default) writes OTP codes to `api/storage/logs/laravel.log` instead of sending SMS.

To create the first admin, set `SEED_ADMIN_MOBILE` (E.164, e.g. `+989121234567`) and `SEED_ADMIN_PASSWORD` in `api/.env` before seeding.

## Tests and checks

```bash
cd api
vendor/bin/pest            # needs a vet_clinic_test PostgreSQL database
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G

cd web
npm test && npm run lint && npm run build
```

CI runs all of the above on every push and pull request.

## Architecture notes

- **Modules** live in `api/app/Modules/<Domain>` (Identity, Audit, then Pets, Catalog, Scheduling, Billing, …). Modules talk through services and events, not each other's tables.
- **Money** is stored as integer rials and formatted by `App\Support\Money`. `MONEY_DISPLAY_MODE` switches display between toman and the redenominated rial.
- **Dates** are stored in UTC; `App\Support\JalaliDate` converts at the edges (Tehran time, Jalali calendar).
- **Input normalization**: Persian/Arabic digits become ASCII, Arabic ي/ك become Persian ی/ک, mobiles become E.164.
- **Providers** sit behind interfaces with fake drivers (`SmsSender` today; payment, maps and e-invoicing next), so tests and local dev never call real services.
- **Audit log** is append-only, enforced by the model and a PostgreSQL trigger.

## Auth

| Who | Flow | Endpoints |
| --- | --- | --- |
| Owners | Mobile + SMS code; first sign-in creates the account | `POST /api/v1/auth/otp/request`, `POST /api/v1/auth/otp/verify` |
| Staff | Password, then SMS code (2FA); accounts created by an admin | `POST /api/v1/auth/staff/login`, `POST /api/v1/auth/staff/verify` |

Tokens are Sanctum bearer tokens with an `owner` or `staff` ability; admin routes require `staff`.

## Production

One server runs everything through `docker-compose.prod.yml`; only nginx publishes a port.

1. Copy `docker/prod.env.example` to `.env` beside the compose file and fill in `APP_KEY`, `APP_URL` and `DB_PASSWORD`.
2. `docker compose -f docker-compose.prod.yml up -d --build`
3. `docker compose -f docker-compose.prod.yml exec api php artisan migrate --force`
4. `docker compose -f docker-compose.prod.yml exec api php artisan db:seed --force`
5. `docker compose -f docker-compose.prod.yml exec api php artisan config:cache`

With `SMS_DRIVER=fake`, login codes go to `storage/logs/laravel.log` in the `api` container instead of SMS.
