<?php

namespace Dipesh\LaravelLogViewer;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LogViewerController
{
	/**
	 * Display logs browser and selected log content.
	 */
	public function index(Request $request)
	{
		$roots = $this->getConfiguredRoots();
		$rootItems = $this->formatRootItems($roots);
		$patterns = $this->getPatterns();
		$maxFileSize = (int) config('logviewer.max_file_size', 50 * 1024 * 1024);

		// Resolve current root
		$currentRootId = $request->query('root');
		$currentRoot = $this->resolveRootPath($rootItems, $currentRootId);
		$currentRootId = $currentRoot ? $currentRoot['id'] : null;
		$currentRootLabel = $currentRoot ? $currentRoot['label'] : null;

		// Resolve current directory (relative to current root)
		$currentDirRel = $this->decode($request->query('dir')) ?: '';
		$currentDirPath = $currentRoot ? $this->joinPath($currentRoot['path'], $currentDirRel) : null;

		// Directory listing (folders and files for the current dir)
		$folders = [];
		$filesInDir = [];
		if ($currentRoot) {
			$listing = $this->listDirectory($currentRoot['path'], $currentDirRel, $patterns);
			$folders = $listing['folders'];
			$filesInDir = $listing['files'];
		}

		// Selected file handling
		$selectedId = $request->query('file');
		$selectedEntry = $this->resolveSelectedFile($selectedId, $currentRoot ? $currentRoot['path'] : null);
		$content = '';
		$lines = [];
		$page = max((int) $request->query('page', 1), 1);
		$perPage = (int) config('logviewer.lines_per_page', 200);
		$tooLarge = false;
		$query = trim((string) $request->query('q', ''));
		$order = strtolower((string) $request->query('order', 'desc'));
		$order = in_array($order, ['asc','desc'], true) ? $order : 'desc';

		if ($selectedEntry && $currentRoot) {
			$fullPath = $selectedEntry['path']; // absolute
			$tooLarge = file_exists($fullPath) && filesize($fullPath) > $maxFileSize;
			if (!$tooLarge) {
				if (Str::endsWith($fullPath, '.gz')) {
					$content = $this->readGzFile($fullPath);
				} else {
					$content = File::exists($fullPath) ? File::get($fullPath) : '';
				}
				$lines = preg_split("/\\r\\n|\\r|\\n/", (string) $content) ?: [];
				// Search filter
				if ($query !== '') {
					$q = $query; // avoid use() for PHP 7.2 clarity
					$lines = array_values(array_filter($lines, function ($line) use ($q) {
						return stripos($line, $q) !== false;
					}));
				}
				// Order
				if ($order === 'desc') {
					$lines = array_reverse($lines); // newest lines first
				}
			} else {
				$lines = [];
			}
		}

		$total = count($lines);
		$offset = ($page - 1) * $perPage;
		$currentItems = array_slice($lines, $offset, $perPage);

		$paginator = new LengthAwarePaginator(
			$currentItems,
			$total,
			$perPage,
			$page,
			['path' => url()->current(), 'query' => Arr::except($request->query(), 'page')]
		);

		// Transform current lines into table entries (Level / Date / Content)
		$entries = array_map(function ($line) {
			return $this->parseLogLine($line);
		}, $currentItems);

		return view('laravel-log-viewer::log', [
			// roots
			'roots' => $rootItems,
			'currentRootId' => $currentRootId,
			'currentRootLabel' => $currentRootLabel,
			// directory context
			'dirRel' => $currentDirRel,
			'dirId' => $this->encode($currentDirRel),
			'breadcrumbs' => $this->buildBreadcrumbs($currentRoot, $currentDirRel),
			// listing
			'folders' => $folders,
			'files' => $filesInDir,
			// selection
			'selectedId' => $selectedEntry ? $selectedEntry['id'] : null,
			'selectedLabel' => $selectedEntry ? $selectedEntry['label'] : null,
			'tooLarge' => $tooLarge,
			'lines' => $currentItems,
			'entries' => $entries,
			'paginator' => $paginator,
			'q' => $query,
			'order' => $order,
		]);
	}

	/**
	 * Directory listing for a given root and relative dir.
	 */
	protected function listDirectory(string $rootPath, string $dirRel, array $patterns): array
	{
		$abs = $this->joinPath($rootPath, $dirRel);
		$folders = [];
		$files = [];
		if (!File::exists($abs) || !File::isDirectory($abs)) {
			return ['folders' => [], 'files' => []];
		}
		// Folders
		$dirs = File::directories($abs);
		foreach ($dirs as $dir) {
			$rel = ltrim(Str::after($dir, rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
			$folders[] = [
				'id' => $this->encode($rel),
				'label' => basename($dir) . '/',
				'rel' => $rel,
			];
		}
		// Files
		$filesInfo = File::files($abs);
		foreach ($filesInfo as $f) {
			/** @var \SplFileInfo $f */
			$filename = $f->getFilename();
			if (!$this->matchesPatterns($filename, $patterns)) {
				continue;
			}
			$path = $f->getPathname();
			$files[] = [
				'id' => $this->encode($path),
				'label' => $filename,
				'path' => $path,
				'mtime' => $f->getMTime(),
			];
		}
		// Sort folders alphabetically, files by mtime desc
		usort($folders, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
		});
		usort($files, function ($a, $b) {
			return $b['mtime'] <=> $a['mtime'];
		});
		return ['folders' => $folders, 'files' => $files];
	}

	protected function formatRootItems(array $roots): array
	{
		$out = [];
		foreach ($roots as $root) {
			$out[] = [
				'id' => $this->encode($root),
				'label' => basename($root),
				'path' => $root,
			];
		}
		return $out;
	}

	protected function resolveRootPath(array $rootItems, ?string $rootId)
	{
		if ($rootId) {
			$path = $this->decode($rootId);
			foreach ($rootItems as $r) {
				if ($r['path'] === $path) {
					return $r;
				}
			}
		}
		return isset($rootItems[0]) ? $rootItems[0] : null;
	}

	protected function resolveSelectedFile(?string $encodedId, ?string $rootPath)
	{
		if (!$encodedId || !$rootPath) {
			return null;
		}
		$path = $this->decode($encodedId);
		if (!$path || !is_string($path)) {
			return null;
		}
		// Security: ensure inside root
		$normalizedRoot = rtrim(realpath($rootPath) ?: $rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$normalizedPath = realpath($path) ?: $path;
		if (Str::startsWith($normalizedPath, $normalizedRoot) && is_file($normalizedPath)) {
			return [
				'id' => $encodedId,
				'path' => $normalizedPath,
				'label' => basename($normalizedPath),
			];
		}
		return null;
	}

	protected function buildBreadcrumbs($currentRoot, string $dirRel): array
	{
		$crumbs = [];
		if (!$currentRoot) {
			return $crumbs;
		}
		$crumbs[] = [
			'label' => $currentRoot['label'],
			'dirId' => $this->encode(''),
		];
		if ($dirRel === '') {
			return $crumbs;
		}
		$parts = array_values(array_filter(explode('/', str_replace('\\', '/', $dirRel)), function ($p) {
			return $p !== '';
		}));
		$acc = [];
		foreach ($parts as $part) {
			$acc[] = $part;
			$rel = implode('/', $acc);
			$crumbs[] = [
				'label' => $part,
				'dirId' => $this->encode($rel),
			];
		}
		return $crumbs;
	}

	protected function joinPath(string $base, string $rel): string
	{
		if ($rel === '' || $rel === DIRECTORY_SEPARATOR) {
			return $base;
		}
		return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($rel, DIRECTORY_SEPARATOR);
	}

	protected function encode(string $value): string
	{
		return base64_encode($value);
	}

	protected function decode(?string $value)
	{
		if (!$value) {
			return null;
		}
		$decoded = base64_decode($value, true);
		return $decoded === false ? null : $decoded;
	}

	/**
	 * Get configured root paths (supports array, comma or pipe separated string, or single storage_path fallback).
	 */
	protected function getConfiguredRoots(): array
	{
		$pathsConfig = config('logviewer.paths');
		$roots = [];
		if (is_array($pathsConfig)) {
			$roots = $pathsConfig;
		} elseif (is_string($pathsConfig) && strlen(trim($pathsConfig)) > 0) {
			$roots = preg_split('/[,|]/', $pathsConfig) ?: [];
		} else {
			$roots = [config('logviewer.storage_path', storage_path('logs'))];
		}
		$roots = array_map('trim', $roots);
		$roots = array_filter($roots, function ($p) {
			return $p !== '';
		});
		$roots = array_values(array_unique($roots));
		return $roots;
	}

	/**
	 * Get list of filename patterns to include (supports array or comma/pipe separated string).
	 */
	protected function getPatterns(): array
	{
		$pattern = config('logviewer.pattern', '*.log');
		if (is_array($pattern)) {
			$patterns = $pattern;
		} else {
			$patterns = preg_split('/[,|]/', (string) $pattern) ?: ['*.log'];
		}
		$patterns = array_map('trim', $patterns);
		$patterns = array_filter($patterns, function ($p) {
			return $p !== '';
		});
		// Ensure reasonable defaults include .log.gz if not specified explicitly
		if (!in_array('*.log.gz', $patterns, true) && !in_array('*.log*', $patterns, true)) {
			$patterns[] = '*.log.gz';
		}
		return array_values(array_unique($patterns));
	}

	protected function matchesPatterns(string $filename, array $patterns): bool
	{
		foreach ($patterns as $p) {
			if (Str::is($p, $filename)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Best-effort parse of a Laravel log line into [level, date, content].
	 * Supports typical formats like:
	 *   [2024-01-02 12:34:56] local.ERROR: Message here
	 *   [2024-01-02 12:34:56] production.INFO: ...
	 * Falls back gracefully when no match is found.
	 */
	protected function parseLogLine(string $line): array
	{
		$level = '';
		$date = '';
		$content = trim($line);

		$regex = '/\\[(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2})\\].*?(?:\\s[\\w.-]+\\.)?(emergency|alert|critical|error|warning|notice|info|debug)\\b[:]?\\s*(.*)$/i';
		if (preg_match($regex, $line, $m)) {
			$date = isset($m[1]) ? $m[1] : '';
			$level = isset($m[2]) ? strtolower($m[2]) : '';
			$content = isset($m[3]) && $m[3] !== '' ? $m[3] : $content;
		}

		return [
			'level' => $level,
			'date' => $date,
			'content' => $content,
		];
	}

	protected function readGzFile(string $path): string
	{
		if (!function_exists('gzopen') || !file_exists($path)) {
			return '';
		}
		$buffer = '';
		$gz = gzopen($path, 'rb');
		if (!$gz) {
			return '';
		}
		while (!gzeof($gz)) {
			$buffer .= gzread($gz, 8192);
		}
		gzclose($gz);
		return $buffer;
	}
}


