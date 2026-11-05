# Bar & Restaurant Management System (BMS)

A multi-branch bar and restaurant management system built with Laravel 12,
Blade, Tailwind CSS and Alpine.js. It covers POS, kitchen, inventory, expenses,
rooms, staff, reports and settings.

- **Local development:** copy `.env.example` to `.env`, run `composer install`,
  `npm install`, `npm run dev` and `php artisan migrate`.
- **Production deployment:** follow [DEPLOYMENT.md](DEPLOYMENT.md). The
  production environment template is [.env.production.example](.env.production.example).

---



<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Registration email setup

The registration screen initializes six single-use entry codes and emails them to
`BMS_REGISTRATION_NOTIFICATION_EMAIL` (defaults to `wilsonwincheslaus722@gmail.com`).
After each successful registration, the consumed code is replaced and the system
owner receives the replacement code and registration details. The new
administrator receives their four-digit sign-in code by email.

Configure a working Laravel SMTP mailer in `.env` before using registration in
production. The example environment defaults to the `log` mailer; with that
setting, messages are not delivered and the registration screen reports the
delivery issue. Run `php artisan migrate` to create the entry-code pool tables.

## Inventory setup

Inventory configuration is stored in the database, not browser storage. Apply
pending migrations before opening Inventory or editing its Settings:

```bash
php artisan migrate
```

Inventory categories, counters, and units are managed from Settings. Product
catalog records are stored separately from per-counter stock records, so one
product can have stock at multiple counters. Initial stock, restocks, manual
adjustments, spreadsheet imports, and transfers are committed in database
transactions and recorded in the stock-movement ledger. Products deleted from
Inventory are soft-deleted and can be restored from Settings > Trash; products
with movement history cannot be permanently removed, preserving the audit
trail.

## Kitchen setup

Apply migrations before using Kitchen raw materials, recipes, and food items.
Raw-material categories are managed in Settings and are stored alongside the
Kitchen material catalog. Raw-material stock creation, restocking, manual
adjustments, and spreadsheet imports write movement records transactionally.
Use the Kitchen raw-material template link to download the CSV header format;
imports require Name, Category, Unit, Quantity, and Cost Per Unit columns.
Recipes store ingredient quantities against raw materials, and food items can
link to one or more recipes. Deleted raw materials, recipes, and food items are
soft-deleted and appear in Settings > Trash. Raw materials with movement history
cannot be permanently removed, preserving the stock audit trail.
