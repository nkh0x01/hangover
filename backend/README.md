# Hangover Backend

Laravel 11 monolith hosting the API, Filament admin panel, queue workers and Reverb WebSocket broker.

See top-level `README.md` for the Docker-based dev workflow and the
`/docs/architecture/` tree for the design contract.

## Local quick start

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Through Docker (preferred):

```bash
# from repo root
make up
make install
```

## Module layout

Business code lives under `app/Modules/<Module>/`. Each module owns:

- `Models/`, `Actions/`, `Services/`, `Repositories/`, `Dto/`
- `Http/Controllers`, `Http/Requests`, `Http/Resources`
- `Events/`, `Listeners/`, `Jobs/`, `Notifications/`
- `routes/api.php`, `routes/channels.php`
- `Providers/<Module>ServiceProvider.php` (registered via `config/modules.php`)
- `Filament/Resources/` for admin panel surfaces

Cross-module access is via events or published service contracts —
never by reaching across `Models` directly.

## Quality gates

```bash
./vendor/bin/pint           # format
./vendor/bin/phpstan        # static analysis (level 6 in Phase 0)
./vendor/bin/pest           # tests
```
