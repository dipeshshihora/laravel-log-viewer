<?php

return [
	// Number of lines per page when showing a log file
	'lines_per_page' => 200,
	'max_file_size' => 52428800,
	'pattern'       => env('LOGVIEWER_PATTERN', '*.log'),
	// Optional: support multiple root paths (comma-separated in env). If not set, falls back to storage_path.
	'paths'         => env('LOGVIEWER_PATHS', null),
    'storage_path'  => env('LOGVIEWER_STORAGE_PATH', storage_path('logs')),
];


