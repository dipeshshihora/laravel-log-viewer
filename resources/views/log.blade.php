@php
	$title = 'Log Viewer';
@endphp
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{{ $title }}</title>
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; margin: 0; color: #1f2937; overflow-x: hidden; }
		header { padding: 12px 16px; background: #111827; color: #f9fafb; position: sticky; top: 0; z-index: 20; }
		h1 { font-size: 18px; margin: 0; }
		.container { display: grid; grid-template-columns: 280px 1fr; height: calc(100vh - 54px); }
		.sidebar { border-right: 1px solid #e5e7eb; overflow: auto; display: flex; flex-direction: column; background: #fafafa; }
		.main { overflow: auto; }
		.section-title { padding: 8px 12px; font-size: 12px; color: #6b7280; border-bottom: 1px solid #f3f4f6; background: #f9fafb; position: sticky; top: 0; z-index: 5; }
		.item { display: block; padding: 8px 10px; border-bottom: 1px solid #f3f4f6; color: inherit; text-decoration: none; font-size: 14px; }
		.item.active { background: #eef2ff; font-weight: 600; border-left: 3px solid #4338ca; }
		.item.folder { font-weight: 600; color: #111827; }
		.item .icon { width: 18px; display: inline-block; opacity: .75; }
		.item .name { vertical-align: middle; }
		pre { margin: 0; font: 13px/1.5 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; white-space: pre-wrap; word-wrap: break-word; padding: 12px 16px; }
		.table-wrap { padding: 8px 16px; }
		table.table { width: 100%; border-collapse: collapse; background: white; table-layout: fixed; }
		table.table th, table.table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; word-break: break-word; overflow-wrap: anywhere; }
		table.table thead th { position: sticky; top: 54px; background: #f9fafb; font-weight: 600; font-size: 12px; color: #374151; z-index: 4; }
		.content-cell pre { padding: 0; margin: 0; white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere; }
		.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid transparent; }
		.badge.error { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
		.badge.warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
		.badge.info { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
		.badge.debug { background: #e5e7eb; color: #111827; border-color: #d1d5db; }
		.badge.critical, .badge.alert, .badge.emergency { background: #fecaca; color: #7f1d1d; border-color: #fca5a5; }
		.pagination { display: flex; gap: 8px; padding: 4px 0; }
		.pagination a, .pagination span { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; text-decoration: none; color: inherit; }
		.pagination .active { background: #111827; color: white; border-color: #111827; }
		.meta { padding: 8px 16px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; }
		.toolbar { display: flex; gap: 8px; align-items: center; padding: 10px 16px; border-bottom: 1px solid #e5e7eb; background: #fafafa; position: sticky; top: 0; z-index: 10; }
		.toolbar form { display: inline-flex; gap: 8px; align-items: center; margin: 0; }
		.input { padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; }
		.input.w-56 { width: 14rem; }
		.btn { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; background: white; color: #111827; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
		.btn.primary { background: #111827; color: white; border-color: #111827; }
		.btn.secondary { background: white; color: #111827; }
		.btn-group { display: inline-flex; gap: 6px; }
		.footer { padding: 6px 12px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; position: sticky; bottom: 0; background: white; z-index: 5; display: flex; align-items: center; justify-content: space-between; }
		.breadcrumbs { padding: 6px 12px; font-size: 12px; border-bottom: 1px solid #f3f4f6; }
		.breadcrumbs a { color: inherit; text-decoration: none; }
		.group { margin-top: 8px; }
	</style>
</head>
<body>
<header>
	<h1>{{ $title }}</h1>
</header>
<div class="container">
	<aside class="sidebar">
		<div class="section-title">Roots</div>
		@foreach($roots as $root)
			@php $isRoot = ($currentRootId === $root['id']); @endphp
			<a class="item {{ $isRoot ? 'active' : '' }}"
			   href="{{ request()->fullUrlWithQuery(['root' => $root['id'], 'dir' => base64_encode(''), 'file' => null, 'page' => 1]) }}"
			><span class="icon">🗂</span><span class="name">{{ $root['label'] }}</span></a>
		@endforeach
		<div class="section-title">Folder</div>
		<div class="breadcrumbs">
			@if(!empty($breadcrumbs))
				@foreach($breadcrumbs as $i => $crumb)
					<a href="{{ request()->fullUrlWithQuery(['dir' => $crumb['dirId'], 'file' => null, 'page' => 1]) }}">{{ $crumb['label'] }}</a>
					@if($i < count($breadcrumbs) - 1)
						/
					@endif
				@endforeach
			@endif
		</div>
		<div class="group">
			@foreach($folders as $folder)
				<a class="item folder"
				   href="{{ request()->fullUrlWithQuery(['dir' => $folder['id'], 'file' => null, 'page' => 1]) }}"
				><span class="icon">📁</span><span class="name">{{ $folder['label'] }}</span></a>
			@endforeach
		</div>
		<div class="section-title">Files</div>
		<div class="group">
			@foreach($files as $file)
				@php $isActive = ($selectedId === $file['id']); @endphp
				<a class="item {{ $isActive ? 'active' : '' }}"
				   href="{{ request()->fullUrlWithQuery(['file' => $file['id'], 'page' => 1]) }}"
				><span class="icon">📄</span><span class="name">{{ $file['label'] }}</span></a>
			@endforeach
		</div>
	</aside>
	<main class="main">
		<div class="toolbar">
			@if($selectedId && $selectedLabel)
				<strong style="margin-right: 8px;">{{ $selectedLabel }}</strong>
			@endif
			<form method="get" action="{{ url()->current() }}">
				@if($currentRootId)<input type="hidden" name="root" value="{{ $currentRootId }}">@endif
				@if(isset($dirId))<input type="hidden" name="dir" value="{{ $dirId }}">@endif
				@if($selectedId)<input type="hidden" name="file" value="{{ $selectedId }}">@endif
				<select class="input" name="order">
					<option value="desc" {{ ($order ?? 'desc') === 'desc' ? 'selected' : '' }}>Newest first</option>
					<option value="asc"  {{ ($order ?? 'desc') === 'asc' ? 'selected' : '' }}>Oldest first</option>
				</select>
				<input class="input w-56" type="text" name="q" value="{{ $q ?? '' }}" placeholder="Search in file...">
				<span class="btn-group">
					<button class="btn primary" type="submit">Search</button>
					<a class="btn secondary" href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => 1]) }}">Clear</a>
				</span>
			</form>
			<a class="btn secondary" href="{{ request()->fullUrlWithQuery(['r' => time()]) }}">Reload</a>
		</div>
		<div class="meta">
			@if($selectedId && $selectedLabel)
				Showing: <strong>{{ $selectedLabel }}</strong>
			@else
				Select a file from the left to view its content
			@endif
		</div>
		@if($selectedId)
			@if(!empty($tooLarge))
				<div class="meta">Selected file is too large to display. Increase <code>logviewer.max_file_size</code> to view.</div>
			@else
				<div class="table-wrap">
					<table class="table">
						<thead>
						<tr>
							<th style="width: 120px;">Level</th>
							<th style="width: 180px;">Date</th>
							<th>Content</th>
						</tr>
						</thead>
						<tbody>
						@foreach($entries as $row)
							<tr>
								<td>
									@php
										$lv = $row['level'] ?: 'info';
										$cls = in_array($lv, ['error','critical','alert','emergency']) ? 'error'
											: ($lv === 'warning' ? 'warning'
											: ($lv === 'debug' ? 'debug' : 'info'));
									@endphp
									@if($row['level'])<span class="badge {{ $cls }}">{{ strtoupper($row['level']) }}</span>@endif
								</td>
								<td><code>{{ $row['date'] }}</code></td>
								<td class="content-cell"><pre>{{ $row['content'] }}</pre></td>
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>
			@endif
		@endif
		<div class="footer">
			<span>Powered by Dipesh Shihora</span>
			@if($selectedId && !$tooLarge && method_exists($paginator, 'links'))
				<div class="pagination">
					@php
						$current = $paginator->currentPage();
						$last = $paginator->lastPage();
					@endphp
					@if($current > 1)
						<a href="{{ $paginator->url(1) }}">&laquo; First</a>
						<a href="{{ $paginator->previousPageUrl() }}">&lsaquo; Prev</a>
					@else
						<span>&laquo; First</span>
						<span>&lsaquo; Prev</span>
					@endif
					<span class="active">{{ $current }} / {{ $last }}</span>
					@if($current < $last)
						<a href="{{ $paginator->nextPageUrl() }}">Next &rsaquo;</a>
						<a href="{{ $paginator->url($last) }}">Last &raquo;</a>
					@else
						<span>Next &rsaquo;</span>
						<span>Last &raquo;</span>
					@endif
				</div>
			@endif
		</div>
	</main>
	</div>
</body>
</html>


