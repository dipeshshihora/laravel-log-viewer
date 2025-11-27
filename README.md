# Dipesh Laravel Log Viewer

Simple Log Viewer for Laravel 9, 10, 11 & 12 and Lumen. Install with composer, create a route to `LogViewerController`. No public assets, optional view/config publishing. 

## Install (Laravel)

Install via composer:

```bash
composer require dipeshshihora/laravel-log-viewer
```

If your app does not use package auto-discovery, add the Service Provider to `config/app.php`:

```php
Dipesh\LaravelLogViewer\LaravelLogViewerServiceProvider::class,
```

Add a route in your `routes/web.php`:

```php
use Dipesh\LaravelLogViewer\LogViewerController;

Route::get('logs', [LogViewerController::class, 'index'])->name('logs.index');
```

Go to `/logs`.

## Install (Lumen)

Install via composer:

```bash
composer require dipeshshihora/laravel-log-viewer
```

Register in `bootstrap/app.php`:

```php
$app->register(Dipesh\LaravelLogViewer\LaravelLogViewerServiceProvider::class);
```

Add route:

```php
$router->get('logs', '\Dipesh\LaravelLogViewer\LogViewerController@index');
```

## Advanced

### Customize view

Publish the Blade view to `resources/views/vendor/laravel-log-viewer/`:

```bash
php artisan vendor:publish --provider="Dipesh\LaravelLogViewer\LaravelLogViewerServiceProvider" --tag=views
```

### Edit configuration

Publish the configuration file `config/logviewer.php`:

```bash
php artisan vendor:publish --provider="Dipesh\LaravelLogViewer\LaravelLogViewerServiceProvider" --tag=config
```

Options:

- `lines_per_page` (default: 200)
- `max_file_size` (bytes; default: 50 MB) — skip rendering if larger
- `pattern` (string or array; glob(s) like `*.log,*.log.gz`)
- `paths` (array or comma/pipe-separated string) — multiple roots
- `storage_path` (fallback when `paths` not set)

### Security note

- This controller reads files from `storage/logs`. Ensure your app's auth/authorization protects the route in production.

Notes:
- The UI is plain HTML+CSS (no frontend framework) and lists nested paths like `logs/api/access.log`.
- Very large files are skipped with a notice; adjust `max_file_size` as needed.

## License

MIT © dipeshshihora


