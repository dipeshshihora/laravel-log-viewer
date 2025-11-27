<?php

namespace Dipesh\LaravelLogViewer;

use Illuminate\Support\ServiceProvider;

class LaravelLogViewerServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		// Views
		$this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-log-viewer');
		$this->publishes([
			__DIR__ . '/../resources/views' => base_path('resources/views/vendor/laravel-log-viewer'),
		], 'views');

		// Config
		$this->publishes([
			__DIR__ . '/../config/logviewer.php' => config_path('logviewer.php'),
		], 'config');
	}

	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		$this->mergeConfigFrom(__DIR__ . '/../config/logviewer.php', 'logviewer');
	}
}


