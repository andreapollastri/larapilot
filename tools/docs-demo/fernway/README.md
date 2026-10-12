# Fernway

Class bookings for independent fitness studios: a timetable members book from in two taps,
a waitlist that fills itself, and class packs paid by card.

Laravel 13 · Livewire 3 · PostgreSQL in production, SQLite locally · Stripe Checkout through Cashier.

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer test
```

The product lives in `.larapilot/`: the PRD, the backlog, the plans, the mockups. Open `/larapilot` on Herd.
