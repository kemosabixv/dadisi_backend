<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

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

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

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


## Local development quick start

Follow these steps to run the backend locally on Windows / WSL / Linux.

1. Copy environment example and set values:

   cp .env.example .env

2. Install dependencies (requires Composer):

   composer install

3. Generate app key:

   php artisan key:generate

4. Configure your local database and update `.env` (DB_CONNECTION, DB_DATABASE, DB_USERNAME, DB_PASSWORD).

5. Run migrations and seeders (if any):

   php artisan migrate --seed

6. Run the dev server:

   php artisan serve --host=127.0.0.1 --port=8000

7. Run tests:

   ./vendor/bin/phpunit --configuration phpunit.xml

Notes:

- If you're on Windows, use PowerShell or WSL. For a consistent environment consider using Docker.
- The repository includes `azure-pipelines.yml` for a starter CI pipeline.

## Developer onboarding (detailed)

Follow these steps for a repeatable local developer setup. These are written to work on Windows (PowerShell), WSL, or Linux. Replace values in `.env` as appropriate for your environment.

1. Copy the example environment and update values

   ```powershell
   copy .env.example .env
   # then edit .env with your DB and mail values
   notepad .env  # or use your preferred editor
   ```

2. Install PHP dependencies

   ```powershell
   composer install --no-interaction --prefer-dist
   ```

3. Generate app key

   ```powershell
   php artisan key:generate
   ```

4. Configure the database

   - Update `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`.
   - For a local MySQL setup create the DB before running migrations.

5. Run migrations and seeders

   ```powershell
   php artisan migrate --seed
   ```

   - If you need a fresh start during development:

   ```powershell
   php artisan migrate:fresh --seed
   ```

6. Run the local server

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8000
   ```

7. Run the test suite

   ```powershell
   ./vendor/bin/phpunit --configuration phpunit.xml
   ```

8. Developer utilities in this repository

   - Work item automation (we used it to create the backlog): see `..\Docs\create_ado_workitems_v2.ps1` and the generated log at `..\Docs\created_work_items_log.csv`.
     - To re-check a created work item locally you can run (from `Docs`):

     ```powershell
     .\CheckWorkItems.ps1 <workItemId>
     # example:
     .\CheckWorkItems.ps1 229
     ```

   - If you need to create or update Azure DevOps work items again the scripts live under `Docs/workitems/` — they require the Azure CLI and the Azure DevOps extension.

9. Recommended developer workflow

   - Create a feature branch from `master` named like `feature/<workItemId>-short-description` (e.g. `feature/260-sanctum-auth`).
   - Push and open a pull request back to `master` for review.
   - Make small commits with clear messages and reference the work item ID in the PR description.

10. Notes and troubleshooting

   - If you encounter permission errors when running `php artisan migrate`, verify your DB credentials and that your DB user has CREATE/MIGRATE privileges.
   - Use WSL or Docker if you want a Linux-like environment on Windows.
   - For any CI-related questions see `azure-pipelines.yml` at the repository root.

If you'd like, I can now start implementing one of the created work items (for example: implement Laravel Sanctum auth, story ID 260). Which work item should I start with?
