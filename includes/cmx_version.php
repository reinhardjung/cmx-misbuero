<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_misbuero_is_local_env(): bool {
	$env = '';
	if (\function_exists('\\wp_get_environment_type')) {
		$env = \wp_get_environment_type();
	} elseif (\defined('WP_ENVIRONMENT_TYPE')) {
		$env = WP_ENVIRONMENT_TYPE;
	} elseif (\defined('WP_ENV')) {
		$env = WP_ENV;
	}

	return \is_string($env) && \strtolower($env) === 'local';
}

function cmx_misbuero_latest_mtime(string $root): ?int {
	if (!\is_dir($root)) {
		return null;
	}

	$latest = null;
	$dir = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
	$filter = new \RecursiveCallbackFilterIterator($dir, function ($current) {
		$name = $current->getFilename();
		if ($name !== '' && $name[0] === '.') {
			return false;
		}
		if ($current->isDir()) {
			return $name !== 'vendor';
		}
		return true;
	});
	$iter = new \RecursiveIteratorIterator($filter);
	foreach ($iter as $file) {
		if (!$file->isFile()) {
			continue;
		}
		$mtime = $file->getMTime();
		if ($latest === null || $mtime > $latest) {
			$latest = $mtime;
		}
	}

	return $latest;
}

function cmx_misbuero_format_version(int $timestamp): string {
	$tz = null;
	if (\function_exists('\\wp_timezone')) {
		$tz = \wp_timezone();
	} else {
		$tz_name = \date_default_timezone_get();
		$tz = new \DateTimeZone($tz_name ?: 'UTC');
	}

	$dt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($tz);

	$month = (int) $dt->format('n');
	$day = (int) $dt->format('j');
	$time = (int) $dt->format('Gi'); // hour+minute without leading zeros

	return $month . '.' . $day . '.' . $time;
}

function cmx_misbuero_maybe_bump_local_version(): void {
	if (!cmx_misbuero_is_local_env()) {
		return;
	}

	$plugin_file = \dirname(__DIR__) . '/cmx-misbuero.php';
	if (!\is_readable($plugin_file) || !\is_writable($plugin_file)) {
		return;
	}

	$contents = \file_get_contents($plugin_file);
	if ($contents === false) {
		return;
	}

	$latest = cmx_misbuero_latest_mtime(\dirname(__DIR__));
	if ($latest === null) {
		$latest = (int) @\filemtime($plugin_file);
	}
	if ($latest <= 0) {
		$latest = \time();
	}
	$version = cmx_misbuero_format_version($latest);

	if (!\preg_match('/\\/\\*\\*.*?\\*\\//s', $contents, $header_match, PREG_OFFSET_CAPTURE)) {
		return;
	}

	$header = $header_match[0][0];
	$header_offset = $header_match[0][1];
	$header_updated = \preg_replace('/^\\s*v?\\d+(?:\\.[0-9]+){0,3}\\s*(\\r\\n|\\r|\\n)/mi', '', $header, 1);
	if ($header_updated === null) {
		return;
	}

	if (\preg_match('/^\\s*\\*\\s*Version:\\s*([^\\r\\n]+)/m', $header_updated, $match)) {
		$current = \trim((string) $match[1]);
		if ($current === $version) {
			return;
		}

		$header_updated = \preg_replace_callback(
			'/^(\\s*\\*\\s*Version:\\s*)([^\\r\\n]+)/m',
			function (array $m) use ($version): string {
				return $m[1] . $version;
			},
			$header_updated,
			1
		);
	} else {
		$count = 0;
		$header_updated = \preg_replace_callback(
			'/^(\\s*\\*\\s*Description:.*)(\\r\\n|\\r|\\n)/m',
			function (array $m) use ($version): string {
				return $m[1] . $m[2] . ' * Version: ' . $version . $m[2];
			},
			$header_updated,
			1,
			$count
		);
		if ($count === 0) {
			return;
		}
	}

	if ($header_updated === null || $header_updated === $header) {
		return;
	}

	$updated = \substr_replace($contents, $header_updated, $header_offset, \strlen($header));
	if ($updated === $contents) {
		return;
	}

	$tmp = $plugin_file . '.tmp';
	$bytes = \file_put_contents($tmp, $updated, LOCK_EX);
	if ($bytes === false) {
		@\unlink($tmp);
		return;
	}

	$perms = @\fileperms($plugin_file);
	if ($perms) {
		@\chmod($tmp, $perms & 0777);
	}

	if (!@\rename($tmp, $plugin_file)) {
		@\unlink($tmp);
		return;
	}

	// Keep mtime aligned with latest plugin file change to prevent endless bumps.
	@\touch($plugin_file, $latest);
}

cmx_misbuero_maybe_bump_local_version();
