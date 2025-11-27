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

## Testing locally

1) Require the package (path repo optional for dev):
```json
// In your Laravel app composer.json
{
  "repositories": [
    { "type": "path", "url": "/absolute/path/to/laravel-read-logs" }
  ]
}
```
```bash
composer require dipeshshihora/laravel-log-viewer:*
```

2) Add route:
```php
use Dipesh\LaravelLogViewer\LogViewerController;
Route::get('logs', [LogViewerController::class, 'index'])->name('logs.index');
```

3) Configure multiple paths and patterns (optional):
```php
// config/logviewer.php (after publishing)
return [
    'paths' => [
        storage_path('logs'),
        base_path('custom-logs'),
    ],
    'pattern' => '*.log,*.log.gz',
    'lines_per_page' => 200,
    'max_file_size' => 50 * 1024 * 1024,
];
```
Or via env:
```env
LOGVIEWER_PATHS=/var/www/app/storage/logs,/var/log/nginx
LOGVIEWER_PATTERN=*.log,*.log.gz
LOGVIEWER_STORAGE_PATH=/var/www/app/storage/logs
```

4) Create nested test logs:
```bash
mkdir -p storage/logs/api storage/logs/jobs
echo "line 1" >> storage/logs/laravel.log
echo "worker ok" >> storage/logs/jobs/worker.log
echo "api call" >> storage/logs/api/access.log
```

5) Visit `/logs` and select files in the left sidebar.

Notes:
- The UI is plain HTML+CSS (no frontend framework) and lists nested paths like `logs/api/access.log`.
- Very large files are skipped with a notice; adjust `max_file_size` as needed.

## License

MIT © dipeshshihora


