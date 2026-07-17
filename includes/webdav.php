<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Sabre\DAV as DAV;
use Sabre\HTTP as HTTP;


/**
 * Kleine Helper für HTML & Darstellung
 */
function cmx_dav_h($str) {
	return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cmx_dav_hidden_root_names_for_base_uri(string $baseUri): array {
	$baseUri = '/' . trim((string) $baseUri, '/');
	if (strcasecmp($baseUri, '/archiv') === 0) {
		return ['scanner'];
	}
	if (strcasecmp($baseUri, '/scanner') === 0) {
		return ['archiv'];
	}
	return [];
}

function cmx_dav_path_hits_hidden_root(string $path, array $hiddenRootNames): bool {
	if (empty($hiddenRootNames)) {
		return false;
	}
	$path = ltrim((string) $path, '/');
	if ($path === '') {
		return false;
	}
	$first = strtolower((string) strtok($path, '/'));
	if ($first === '') {
		return false;
	}
	$hidden = array_values(array_unique(array_filter(array_map(static function ($name): string {
		return strtolower(trim((string) $name));
	}, $hiddenRootNames), static function ($name): bool {
		return $name !== '';
	})));
	return in_array($first, $hidden, true);
}

/**
 * Root-Filter für WebDAV-Verzeichnisse:
 * blendet definierte Namen nur auf Root-Ebene aus und macht sie unzugreifbar.
 */
class CmxDavRootFilteredDirectory extends DAV\FS\Directory {
	/** @var string[] */
	private array $hiddenRootNames;
	private bool $isRootNode;

	public function __construct(string $path, array $hiddenRootNames = [], bool $isRootNode = true) {
		parent::__construct($path);
		$this->hiddenRootNames = array_values(array_unique(array_filter(array_map(static function ($name): string {
			return strtolower(trim((string) $name));
		}, $hiddenRootNames), static function ($name): bool {
			return $name !== '';
		})));
		$this->isRootNode = $isRootNode;
	}

	private function isHiddenAtRoot(string $name): bool {
		if (!$this->isRootNode) {
			return false;
		}
		$name = strtolower(trim((string) $name));
		if ($name === '') {
			return false;
		}
		return in_array($name, $this->hiddenRootNames, true);
	}

	public function getChildren(): array {
		$out = [];
		$iterator = new \FilesystemIterator(
			$this->path,
			\FilesystemIterator::CURRENT_AS_SELF | \FilesystemIterator::SKIP_DOTS
		);

		foreach ($iterator as $entry) {
			$name = (string) $entry->getFilename();
			if ($this->isHiddenAtRoot($name)) {
				continue;
			}
			$fullPath = (string) ($this->path . '/' . $name);
			if (\is_dir($fullPath)) {
				$out[] = new self($fullPath, $this->hiddenRootNames, false);
				continue;
			}
			$out[] = new DAV\FS\File($fullPath);
		}

		return $out;
	}

	public function getChild($name): DAV\INode {
		$name = (string) $name;
		if ($this->isHiddenAtRoot($name)) {
			throw new DAV\Exception\NotFound('File not found');
		}
		$child = parent::getChild($name);
		if ($child instanceof DAV\FS\Directory) {
			return new self((string) ($this->path . '/' . $name), $this->hiddenRootNames, false);
		}
		return $child;
	}

	public function childExists($name): bool {
		$name = (string) $name;
		if ($this->isHiddenAtRoot($name)) {
			return false;
		}
		return parent::childExists($name);
	}
}

function cmx_dav_join_uri(...$parts) {
	$uri = preg_replace('#/+#','/', implode('/', array_map(fn($p)=>trim((string)$p, '/'), $parts)));
	return '/' . ltrim($uri, '/');
}
function cmx_dav_human_bytes($bytes) {
	$bytes = (int)$bytes;
	if ($bytes < 1024) return $bytes . ' B';
	$units = ['KB','MB','GB','TB','PB'];
	$pow = min(count($units), (int)floor(log($bytes, 1024)));
	return number_format($bytes / (1024 ** $pow), 2, ',', "'") . ' ' . $units[$pow-1];
}

function cmx_dav_fmt_time($ts) {
	if (!$ts) return '';
	$dt = (new \DateTime('@'.$ts))->setTimezone(new \DateTimeZone('Europe/Zurich'));
	return $dt->format('d.m.Y H:i');
}

/** Schlichte HTML-Seite für leeres Verzeichnis. */
function cmx_dav_render_empty_archive_message(string $message = 'Es wurden noch keine Dateien abgelegt.', string $title = 'Archiv'): string {
	$msg = cmx_dav_h($message);
	$title = cmx_dav_h($title);
	return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
		.'<meta name="viewport" content="width=device-width,initial-scale=1">'
		.'<title>'.$title.'</title>'
		.'</head><body style="margin:0;padding:32px;font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">'
		.'<p style="margin:0;">'.$msg.'</p>'
		.'</body></html>';
}

/** Scanner-Share: bevorzugt den lowercase-Ordner, fällt auf Legacy-Struktur zurück. */
function cmx_dav_scanner_share_path(): string {
	$preferred = WP_CONTENT_DIR . '/uploads/misbuero/scanner';
	$legacyUpper = WP_CONTENT_DIR . '/uploads/misbuero/Scanner';
	$legacyCandidates = [$legacyUpper];

	// Bevorzugter Root immer zuerst: wenn er fehlt, wird er später angelegt.
	if (!is_dir($preferred)) {
		return $preferred;
	}
	if (is_writable($preferred)) {
		$real = realpath($preferred);
		return $real !== false ? $real : $preferred;
	}

	// Fallback nur, wenn der bevorzugte Ordner zwar existiert, aber nicht nutzbar ist.
	foreach ($legacyCandidates as $candidate) {
		if (!is_dir($candidate) || !is_writable($candidate)) continue;
		$real = realpath($candidate);
		return $real !== false ? $real : $candidate;
	}
	foreach ($legacyCandidates as $candidate) {
		if (!is_dir($candidate)) continue;
		$real = realpath($candidate);
		return $real !== false ? $real : $candidate;
	}

	return $preferred;
}

/** Archiv-Share: eigener Root neben /scanner, bewusst getrennt. */
function cmx_dav_archiv_share_path(): string {
	return WP_CONTENT_DIR . '/uploads/misbuero/archiv';
}

/** CaRent-Share: alle Dateien des CaRent-Moduls ausserhalb der Mediathek. */
function cmx_dav_carent_share_path(): string {
	return WP_CONTENT_DIR . '/uploads/misbuero/carent';
}

/** Website-Share: alle Dateien des Website-Moduls ausserhalb der Mediathek. */
function cmx_dav_website_share_path(): string {
	return WP_CONTENT_DIR . '/uploads/misbuero/webssite';
}

function cmx_dav_module_root(string $module): string {
	$module = strtolower(trim($module));
	if ($module === 'archiv' || $module === 'archive') {
		return 'archiv';
	}
	if ($module === 'carent') {
		return 'carent';
	}
	if ($module === 'webssite' || $module === 'website') {
		return 'webssite';
	}
	return '';
}

function cmx_dav_module_share_path(string $module): string {
	$root = cmx_dav_module_root($module);
	if ($root === 'archiv') {
		return cmx_dav_archiv_share_path();
	}
	if ($root === 'carent') {
		return cmx_dav_carent_share_path();
	}
	if ($root === 'webssite') {
		return cmx_dav_website_share_path();
	}
	return '';
}

function cmx_dav_normalize_rel_path(string $relPath): string {
	$relPath = str_replace('\\', '/', $relPath);
	$parts = [];
	foreach (explode('/', $relPath) as $part) {
		$part = trim($part);
		if ($part === '' || $part === '.') {
			continue;
		}
		if ($part === '..') {
			array_pop($parts);
			continue;
		}
		$parts[] = sanitize_file_name($part);
	}
	return implode('/', array_filter($parts, static fn($part): bool => $part !== ''));
}

function cmx_dav_module_file_path(string $module, string $relPath): string {
	$sharePath = cmx_dav_module_share_path($module);
	$relPath = cmx_dav_normalize_rel_path($relPath);
	if ($sharePath === '' || $relPath === '') {
		return '';
	}
	return rtrim($sharePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
}

function cmx_dav_module_file_url(string $module, string $relPath): string {
	$root = cmx_dav_module_root($module);
	$relPath = cmx_dav_normalize_rel_path($relPath);
	if ($root === '' || $relPath === '') {
		return '';
	}
	return home_url('/' . $root . '/' . implode('/', array_map('rawurlencode', explode('/', $relPath))));
}

function cmx_dav_archive_rel_path(string $relPath): string {
	$relPath = cmx_dav_normalize_rel_path($relPath);
	if (str_starts_with($relPath, 'misbuero/archiv/')) {
		$relPath = substr($relPath, strlen('misbuero/archiv/'));
	}
	return cmx_dav_normalize_rel_path($relPath);
}

function cmx_dav_archive_file_path(string $relPath): string {
	$relPath = cmx_dav_archive_rel_path($relPath);
	if ($relPath === '') {
		return '';
	}
	return rtrim(cmx_dav_archiv_share_path(), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
}

function cmx_dav_archive_file_url(string $relPath): string {
	$relPath = cmx_dav_archive_rel_path($relPath);
	if ($relPath === '') {
		return '';
	}
	return home_url('/archiv/' . implode('/', array_map('rawurlencode', explode('/', $relPath))));
}

function cmx_dav_store_uploaded_file(string $module, array $file, string $subdir = '', array $allowedMimes = []) {
	$sharePath = cmx_dav_module_share_path($module);
	if ($sharePath === '') {
		return new \WP_Error('cmx_dav_unknown_module', 'Unbekannter WebDAV-Modulordner.');
	}

	$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($error !== UPLOAD_ERR_OK) {
		return new \WP_Error('cmx_dav_upload_error', 'Upload fehlgeschlagen.');
	}

	$tmpName = (string) ($file['tmp_name'] ?? '');
	$origName = (string) ($file['name'] ?? '');
	if ($tmpName === '' || $origName === '' || !is_uploaded_file($tmpName)) {
		return new \WP_Error('cmx_dav_upload_invalid', 'Ungültige Upload-Datei.');
	}

	$safeName = sanitize_file_name($origName);
	if ($safeName === '') {
		return new \WP_Error('cmx_dav_upload_name', 'Ungültiger Dateiname.');
	}

	$allowedMimes = $allowedMimes ?: [
		'pdf'  => 'application/pdf',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'gif'  => 'image/gif',
		'heic' => 'image/heic',
		'heif' => 'image/heif',
		'mp4'  => 'video/mp4',
		'mov'  => 'video/quicktime',
		'webm' => 'video/webm',
	];
	$type = wp_check_filetype_and_ext($tmpName, $safeName, $allowedMimes);
	if (empty($type['ext']) || !isset($allowedMimes[(string) $type['ext']])) {
		return new \WP_Error('cmx_dav_upload_type', 'Dateityp nicht erlaubt.');
	}

	$subdir = cmx_dav_normalize_rel_path($subdir);
	$targetDir = rtrim($sharePath, '/\\') . ($subdir !== '' ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir) : '');
	if (!is_dir($targetDir)) {
		wp_mkdir_p($targetDir);
	}
	if (!is_dir($targetDir) || !is_writable($targetDir)) {
		return new \WP_Error('cmx_dav_upload_target', 'Zielordner ist nicht beschreibbar.');
	}

	$destName = wp_unique_filename($targetDir, $safeName);
	$destAbs = $targetDir . DIRECTORY_SEPARATOR . $destName;
	if (!move_uploaded_file($tmpName, $destAbs)) {
		return new \WP_Error('cmx_dav_upload_move', 'Datei konnte nicht gespeichert werden.');
	}
	@chmod($destAbs, 0666);

	$relPath = trim(($subdir !== '' ? $subdir . '/' : '') . $destName, '/');
	return [
		'rel_path' => $relPath,
		'abs_path' => $destAbs,
		'url' => cmx_dav_module_file_url($module, $relPath),
		'file_name' => $destName,
		'mime' => (string) ($type['type'] ?? ''),
	];
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_contract_folder_rel_dir')) {
	function cmx_carent_contract_folder_rel_dir(int $post_id): string {
		if ($post_id <= 0 || (string) \get_post_type($post_id) !== 'carent') {
			return 'unzugeordnet';
		}

		$title = \trim((string) \get_the_title($post_id));
		$folder = $title !== '' ? \sanitize_file_name($title) : '';
		if ($folder === '') {
			$folder = 'carent-' . (int) $post_id;
		}

		return cmx_dav_normalize_rel_path($folder);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_contract_archive_year')) {
	function cmx_carent_contract_archive_year(int $post_id): int {
		if ($post_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_archive_year')) {
			$year = (int) cmx_carent_vertrag_pdf_archive_year($post_id);
			if ($year > 0) {
				return $year;
			}
		}

		foreach ([
			'_cmx_carent_uebernahme_datum',
			'_cmx_carent_rueckgabe_datum',
		] as $meta_key) {
			$value = $post_id > 0 ? (string) \get_post_meta($post_id, $meta_key, true) : '';
			if (\preg_match('/\b(19|20|21|22)\d{2}\b/', $value, $match) === 1) {
				return (int) $match[0];
			}
		}

		$post = $post_id > 0 ? \get_post($post_id) : null;
		if ($post instanceof \WP_Post && \preg_match('/\b(19|20|21|22)\d{2}\b/', (string) $post->post_date, $match) === 1) {
			return (int) $match[0];
		}

		$year = (int) \wp_date('Y');
		return $year > 0 ? $year : (int) \date('Y');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_contract_archive_rel_dir')) {
	function cmx_carent_contract_archive_rel_dir(int $post_id): string {
		return cmx_dav_archive_rel_path(cmx_carent_contract_archive_year($post_id) . '/carent/' . cmx_carent_contract_folder_rel_dir($post_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_ensure_contract_archive_dir')) {
	function cmx_carent_ensure_contract_archive_dir(int $post_id): string {
		$rel_dir = cmx_carent_contract_archive_rel_dir($post_id);
		$dir = cmx_dav_archive_file_path($rel_dir);
		if ($dir !== '' && !\is_dir($dir)) {
			\wp_mkdir_p($dir);
		}

		return \is_dir($dir) ? $rel_dir : '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_contract_upload_file_name')) {
	function cmx_carent_contract_upload_file_name(int $post_id, string $kind, string $original_name): string {
		$kind = \sanitize_file_name(\sanitize_key($kind));
		$kind = $kind !== '' ? $kind : 'datei';
		$original_name = \sanitize_file_name($original_name);
		$ext = \strtolower((string) \pathinfo($original_name, \PATHINFO_EXTENSION));
		$base = \sanitize_file_name((string) \pathinfo($original_name, \PATHINFO_FILENAME));
		$base = $base !== '' ? $base : 'upload';
		$date = \function_exists('current_time') ? (string) \current_time('Ymd-His') : \gmdate('Ymd-His');

		return $kind . '-' . $date . '-' . $base . ($ext !== '' ? '.' . $ext : '');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_store_contract_uploaded_file')) {
	function cmx_carent_store_contract_uploaded_file(int $post_id, array $file, string $kind, array $allowedMimes = []) {
		$file['name'] = cmx_carent_contract_upload_file_name($post_id, $kind, (string) ($file['name'] ?? ''));
		$stored = cmx_dav_store_uploaded_file('archiv', $file, cmx_carent_contract_archive_rel_dir($post_id), $allowedMimes);
		if (\is_wp_error($stored)) {
			return $stored;
		}

		$archive_rel = cmx_dav_archive_rel_path((string) ($stored['rel_path'] ?? ''));
		$stored['rel_path'] = 'misbuero/archiv/' . $archive_rel;
		$stored['url'] = cmx_dav_archive_file_url($archive_rel);

		return $stored;
	}
}

/**
 * Räumt versehentlich angelegte leere Scanner-Ordner unter /archiv auf.
 */
function cmx_dav_cleanup_empty_archiv_scanner_dirs(string $archivPath): void {
	$archivPath = rtrim((string) $archivPath, '/\\');
	if ($archivPath === '' || !is_dir($archivPath)) {
		return;
	}

	foreach (['scanner', 'Scanner'] as $name) {
		$candidate = $archivPath . DIRECTORY_SEPARATOR . $name;
		if (!is_dir($candidate)) {
			continue;
		}
		$items = @scandir($candidate);
		if (!is_array($items)) {
			continue;
		}
		$entries = array_values(array_filter($items, static function ($entry): bool {
			return $entry !== '.' && $entry !== '..';
		}));
		$allowedHidden = ['.ds_store', 'thumbs.db'];
		foreach ($entries as $entry) {
			$entryLower = strtolower((string) $entry);
			$isMacGhost = str_starts_with((string) $entry, '._');
			if ($isMacGhost || in_array($entryLower, $allowedHidden, true)) {
				$hiddenPath = $candidate . DIRECTORY_SEPARATOR . $entry;
				if (is_file($hiddenPath)) {
					@unlink($hiddenPath);
				}
			}
		}
		$itemsAfter = @scandir($candidate);
		if (is_array($itemsAfter)) {
			$entries = array_values(array_filter($itemsAfter, static function ($entry): bool {
				return $entry !== '.' && $entry !== '..';
			}));
		}
		if (!empty($entries)) {
			continue;
		}
		@rmdir($candidate);
	}
}

/** Für /scanner mindestens Lese-/Schreibrechte am Ordner sicherstellen. */
function cmx_dav_ensure_rw_dir_mode(string $dir): void {
	if ($dir === '' || !is_dir($dir)) {
		return;
	}

	if (!is_readable($dir) || !is_writable($dir)) {
		@chmod($dir, 0775);
		clearstatcache(true, $dir);
	}
	if (!is_readable($dir) || !is_writable($dir)) {
		// Letzter Fallback für lokale Umgebungen mit restriktiven Defaults.
		@chmod($dir, 0777);
		clearstatcache(true, $dir);
	}
}

/** Lock-Ordner für WebDAV ermitteln (Live-tauglich mit OpenBasedir-Fallback). */
function cmx_dav_lock_backend_path(string $sharePath): string {
	$sharePath = rtrim((string) $sharePath, '/\\');
	$base = dirname($sharePath);
	if ($base === '' || $base === '.' || $base === DIRECTORY_SEPARATOR) {
		$base = WP_CONTENT_DIR . '/uploads/misbuero';
	}

	$candidate = rtrim($base, '/\\') . '/.dav-locks';
	if (!is_dir($candidate)) {
		@wp_mkdir_p($candidate);
	}
	if (is_dir($candidate) && is_writable($candidate)) {
		return $candidate;
	}

	$tmp = rtrim((string) \sys_get_temp_dir(), '/\\') . '/cmx-webdav-locks';
	if (!is_dir($tmp)) {
		@wp_mkdir_p($tmp);
	}
	return $tmp;
}

/** SQLite-Datei für WebDAV-PropertyStorage (Finder PROPPATCH) bestimmen. */
function cmx_dav_property_db_path(string $sharePath): string {
	$sharePath = rtrim((string) $sharePath, '/\\');
	$base = dirname($sharePath);
	if ($base === '' || $base === '.' || $base === DIRECTORY_SEPARATOR) {
		$base = WP_CONTENT_DIR . '/uploads/misbuero';
	}

	$candidateDir = rtrim($base, '/\\');
	if (!is_dir($candidateDir)) {
		@wp_mkdir_p($candidateDir);
	}
	if (is_dir($candidateDir) && is_writable($candidateDir)) {
		return $candidateDir . '/.dav-props.sqlite';
	}

	$tmpDir = rtrim((string) \sys_get_temp_dir(), '/\\');
	if (!is_dir($tmpDir)) {
		@wp_mkdir_p($tmpDir);
	}
	return $tmpDir . '/cmx-dav-props.sqlite';
}

/** Linux-only Cleanup für Finder-Metadateien. */
function cmx_dav_should_cleanup_ds_store(): bool {
	return \strtolower((string) (\PHP_OS_FAMILY ?? '')) === 'linux';
}

function cmx_dav_relative_from_base_uri(string $baseUri, string $path): string {
	$base = '/' . \trim((string) $baseUri, '/');
	$path = '/' . \ltrim((string) $path, '/');

	if ($base === '/' || $base === '') {
		return \ltrim($path, '/');
	}
	if (\strcasecmp($path, $base) === 0) {
		return '';
	}
	$prefix = $base . '/';
	if (\stripos($path, $prefix) === 0) {
		return \ltrim((string) \substr($path, \strlen($prefix)), '/');
	}
	return \ltrim($path, '/');
}

function cmx_dav_is_pdf_file_name(string $path): bool {
	$path = \trim((string) $path);
	if ($path === '' || \str_ends_with($path, '/')) {
		return false;
	}

	$name = (string) \basename($path);
	if ($name === '' || $name === '.' || $name === '..') {
		return false;
	}

	$ext = \strtolower((string) \pathinfo($name, \PATHINFO_EXTENSION));
	return $ext === 'pdf';
}

function cmx_dav_cleanup_ds_store_in_dir(string $dir): int {
	if ($dir === '' || !\is_dir($dir)) {
		return 0;
	}
	$file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.DS_Store';
	if (\is_file($file)) {
		return @\unlink($file) ? 1 : 0;
	}
	return 0;
}

function cmx_dav_cleanup_ds_store(string $sharePath, string $relativePath = ''): int {
	if (!cmx_dav_should_cleanup_ds_store()) {
		return 0;
	}
	$shareReal = \realpath($sharePath);
	if ($shareReal === false || !\is_dir($shareReal)) {
		return 0;
	}

	$deleted = cmx_dav_cleanup_ds_store_in_dir($shareReal);

	$rel = \trim((string) $relativePath, '/');
	if ($rel === '') {
		return $deleted;
	}

	$candidate = rtrim($shareReal, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
	$targetDir = \is_dir($candidate) ? $candidate : \dirname($candidate);
	$targetReal = \realpath($targetDir);
	if ($targetReal !== false && \is_dir($targetReal) && cmx_dav_is_subpath($shareReal, $targetReal)) {
		$deleted += cmx_dav_cleanup_ds_store_in_dir($targetReal);
	}

	return $deleted;
}

/**
 * Erzwingt direkten Scanner-Sync nach WebDAV-Schreibvorgängen.
 */
function cmx_dav_force_scanner_sync(string $baseUri): void {
	$base = '/' . trim((string) $baseUri, '/');
	if (strcasecmp($base, '/scanner') !== 0) {
		return;
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_clear_fs_cache')) {
		cmx_scanner_sync_clear_fs_cache();
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_scanner_sync_files_to_cpt')) {
		cmx_scanner_sync_files_to_cpt();
	}
}

/** Ermittelt WebDAV-Konfiguration anhand des Request-Pfads. */
function cmx_dav_get_route_config(string $path): ?array {
	$matchBaseUri = static function (string $requestPath, string $leaf): string {
		$requestPath = '/' . ltrim((string) $requestPath, '/');
		$leaf = strtolower(trim((string) $leaf));
		if ($leaf === '') {
			return '';
		}

		$requestSegments = array_values(array_filter(explode('/', trim($requestPath, '/')), static function ($seg): bool {
			return (string) $seg !== '';
		}));
		$homePath = (string) \parse_url((string) \home_url('/'), \PHP_URL_PATH);
		$homeSegments = array_values(array_filter(explode('/', trim($homePath, '/')), static function ($seg): bool {
			return (string) $seg !== '';
		}));

		if (\count($requestSegments) < \count($homeSegments) + 1) {
			return '';
		}

		$offset = 0;
		foreach ($homeSegments as $segment) {
			if (!isset($requestSegments[$offset]) || \strcasecmp((string) $requestSegments[$offset], (string) $segment) !== 0) {
				return '';
			}
			$offset++;
		}

		if (isset($requestSegments[$offset]) && \strcasecmp((string) $requestSegments[$offset], 'index.php') === 0) {
			$offset++;
		}

		if (!isset($requestSegments[$offset]) || \strcasecmp((string) $requestSegments[$offset], $leaf) !== 0) {
			return '';
		}

		$baseSegments = \array_slice($requestSegments, 0, $offset + 1);
		return '/' . \implode('/', $baseSegments);
	};

	$archivBaseUri = $matchBaseUri($path, 'archiv');
	if ($archivBaseUri !== '') {
		return [
			'base_uri'   => $archivBaseUri,
			'share_path' => cmx_dav_archiv_share_path(),
			'label'      => 'Archiv',
			'read_only'  => true,
			'ensure_dir' => true,
		];
	}

	$scannerBaseUri = $matchBaseUri($path, 'scanner');
	if ($scannerBaseUri !== '') {
		return [
			'base_uri'   => $scannerBaseUri,
			'share_path' => cmx_dav_scanner_share_path(),
			'label'      => 'Scanner',
			'read_only'  => false,
			'ensure_dir' => true,
			'upload_profile' => 'scanner',
		];
	}

	foreach ([
		'carent' => ['path' => cmx_dav_carent_share_path(), 'label' => 'CaRent'],
		'webssite' => ['path' => cmx_dav_website_share_path(), 'label' => 'Website'],
		'website' => ['path' => cmx_dav_website_share_path(), 'label' => 'Website'],
	] as $leaf => $cfg) {
		$baseUri = $matchBaseUri($path, $leaf);
		if ($baseUri === '') {
			continue;
		}
		return [
			'base_uri'   => $baseUri,
			'share_path' => (string) $cfg['path'],
			'label'      => (string) $cfg['label'],
			'read_only'  => false,
			'ensure_dir' => true,
			'upload_profile' => 'media',
		];
	}

	return null;
}

/** Prüft rekursiv, ob im Archiv mindestens eine Datei existiert. */
function cmx_dav_has_any_files(string $dir, array $excludeRootNames = []): bool {
	if (!is_dir($dir)) return false;
	$exclude = array_values(array_unique(array_filter(array_map(static function ($name): string {
		return strtolower(trim((string) $name));
	}, $excludeRootNames), static function ($name): bool {
		return $name !== '';
	})));
	$baseNorm = rtrim(str_replace('\\', '/', (string) $dir), '/');
	try {
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($it as $entry) {
			if ($entry instanceof \SplFileInfo && $entry->isFile()) {
				if (!empty($exclude)) {
					$entryPath = str_replace('\\', '/', (string) $entry->getPathname());
					$rel = ltrim((string) substr($entryPath, strlen($baseNorm)), '/');
					$first = strtolower((string) strtok($rel, '/'));
					if ($first !== '' && in_array($first, $exclude, true)) {
						continue;
					}
				}
				return true;
			}
		}
	} catch (\Throwable $e) {
		return false;
	}
	return false;
}

/** Sicherheits-Helper für Pfade */
function cmx_dav_is_subpath(string $base, string $path): bool {
	$base = rtrim(str_replace('\\','/',$base), '/') . '/';
	$path = rtrim(str_replace('\\','/',$path), '/') . '/';
	if (str_starts_with($path, $base)) {
		return true;
	}
	// Fallback gegen Case-Mismatch (z.B. Scanner/scanner auf case-insensitive Volumes).
	return str_starts_with(strtolower($path), strtolower($base));
}
/** Absolute URL aus Pfad erzeugen */
function cmx_dav_abs_url(string $path): string {
	$scheme = (function_exists('\\is_ssl') && \is_ssl()) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	return $scheme . '://' . $host . $path;
}

function cmx_dav_public_file_response(string $sharePath, string $baseUri, string $requestPath): bool {
	$method = \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
	if ($method !== 'GET' && $method !== 'HEAD') {
		return false;
	}

	$relPath = cmx_dav_relative_from_base_uri($baseUri, $requestPath);
	$relPath = cmx_dav_normalize_rel_path($relPath);
	if ($relPath === '') {
		return false;
	}

	$baseReal = \realpath($sharePath);
	if ($baseReal === false) {
		return false;
	}

	$absPath = \rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . \str_replace('/', DIRECTORY_SEPARATOR, $relPath);
	$realPath = \realpath($absPath);
	if ($realPath === false || !\is_file($realPath) || !\is_readable($realPath) || !cmx_dav_is_subpath($baseReal, $realPath)) {
		return false;
	}

	$filetype = \wp_check_filetype($realPath);
	$mime = \trim((string) ($filetype['type'] ?? ''));
	if ($mime === '') {
		$mime = 'application/octet-stream';
	}

	\status_header(200);
	\header('Content-Type: ' . $mime);
	\header('Content-Length: ' . (string) \filesize($realPath));
	\header('Content-Disposition: inline; filename="' . \str_replace('"', '', \basename($realPath)) . '"');
	\header('X-Content-Type-Options: nosniff');
	if ($method !== 'HEAD') {
		\readfile($realPath);
	}
	exit;
}

function cmx_dav_public_archive_carent_media_response(string $sharePath, string $baseUri, string $requestPath): bool {
	$relPath = cmx_dav_relative_from_base_uri($baseUri, $requestPath);
	$relPath = cmx_dav_normalize_rel_path($relPath);
	if ($relPath === '') {
		return false;
	}

	$parts = \explode('/', $relPath);
	$isCarentArchiveMedia = isset($parts[0], $parts[1])
		&& \preg_match('/^(19|20|21|22)\d{2}$/', (string) $parts[0]) === 1
		&& \strcasecmp((string) $parts[1], 'carent') === 0;
	if (!$isCarentArchiveMedia) {
		return false;
	}

	$ext = \strtolower((string) \pathinfo($relPath, \PATHINFO_EXTENSION));
	$allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'mp4', 'mov', 'webm'];
	if (!\in_array($ext, $allowedExts, true)) {
		return false;
	}

	return cmx_dav_public_file_response($sharePath, $baseUri, $requestPath);
}
/** Zip-Helfer (rekursiv) */
function cmx_dav_zip_dir(string $source, \ZipArchive $zip, string $base, array $excludeRootNames = []): void {
	$source = rtrim($source, DIRECTORY_SEPARATOR);
	$base   = rtrim($base, DIRECTORY_SEPARATOR);
	$exclude = array_values(array_unique(array_filter(array_map(static function ($name): string {
		return strtolower(trim((string) $name));
	}, $excludeRootNames), static function ($name): bool {
		return $name !== '';
	})));

	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
		\RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $item) {
		$absPath = $item->getPathname();
		$relPath = ltrim(str_replace($base, '', $absPath), DIRECTORY_SEPARATOR);
		$relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
		if (!empty($exclude)) {
			$first = strtolower((string) strtok($relPath, '/'));
			if ($first !== '' && in_array($first, $exclude, true)) {
				continue;
			}
		}

		if ($item->isDir()) {
			$zip->addEmptyDir($relPath);
		} else {
			$zip->addFile($absPath, $relPath);
		}
	}
}

add_action('init', function () {
	$req  = $_SERVER['REQUEST_URI'] ?? '';
	$path = parse_url($req, PHP_URL_PATH) ?? '/';
	$route = cmx_dav_get_route_config((string) $path);
	if (!$route) return;

	$baseUri   = (string) ($route['base_uri'] ?? '');
	$sharePath = (string) ($route['share_path'] ?? '');
	$label     = (string) ($route['label'] ?? 'Archiv');
	$readOnly  = !empty($route['read_only']);
	$ensureDir = !empty($route['ensure_dir']);
	$uploadProfile = (string) ($route['upload_profile'] ?? '');
	$maxUploadBytes = 100 * 1024 * 1024; // 100 MB

	if ($ensureDir && $sharePath !== '' && !is_dir($sharePath)) {
		\wp_mkdir_p($sharePath);
	}
	if ($sharePath !== '' && is_dir($sharePath)) {
		$resolved = realpath($sharePath);
		if ($resolved !== false) {
			$sharePath = $resolved;
		}
	}
	if ($readOnly) {
		cmx_dav_cleanup_empty_archiv_scanner_dirs($sharePath);
	}
	if (!$readOnly) {
		cmx_dav_ensure_rw_dir_mode($sharePath);
		if (\function_exists('\\nocache_headers')) {
			\nocache_headers();
		}
		@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		@header('Pragma: no-cache');
		@header('Expires: 0');
	}
	cmx_dav_cleanup_ds_store($sharePath, cmx_dav_relative_from_base_uri($baseUri, (string) $path));

	if ($uploadProfile === 'media' && cmx_dav_public_file_response($sharePath, $baseUri, (string) $path)) {
		exit;
	}
	if ($readOnly && cmx_dav_public_archive_carent_media_response($sharePath, $baseUri, (string) $path)) {
		exit;
	}

	// Wenn das Ziel-Verzeichnis noch nicht existiert: leere Seite statt DAV-XML-Fehler.
	if (!is_dir($sharePath)) {
		$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		if ($method === 'HEAD') {
			status_header(200);
			exit;
		}
		if ($method === 'GET') {
			status_header(200);
			header('Content-Type: text/html; charset=UTF-8');
			echo cmx_dav_render_empty_archive_message('Es wurden noch keine Dateien abgelegt.', $label);
			exit;
		}
		status_header(404);
		exit;
	}

	require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';

	$hiddenRootNames = cmx_dav_hidden_root_names_for_base_uri($baseUri);
	$root      = empty($hiddenRootNames)
		? new DAV\FS\Directory($sharePath)
		: new CmxDavRootFilteredDirectory($sharePath, $hiddenRootNames, true);
	$server    = new DAV\Server($root);
	$server->setBaseUri($baseUri); // bewusst ohne Slash am Ende
	$method = \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

	if (!empty($hiddenRootNames)) {
		$server->on('beforeMethod', function(HTTP\RequestInterface $request) use ($hiddenRootNames): void {
			if (!cmx_dav_path_hits_hidden_root((string) $request->getPath(), $hiddenRootNames)) {
				return;
			}
			throw new DAV\Exception\NotFound('File not found');
		});
	}

	// BasicAuth mit WP-Usern. Normale Browser-Ansichten duerfen die bestehende
	// WordPress-Session nutzen, damit eingebettete WebDAV-Medien im Admin keine
	// zweite Login-Abfrage ausloesen.
	$has_wp_browser_session = \in_array($method, ['GET', 'HEAD'], true)
		&& \function_exists('is_user_logged_in')
		&& \is_user_logged_in();
	if (!$has_wp_browser_session) {
		$authBackend = new DAV\Auth\Backend\BasicCallBack(function($u,$p){
			$user = \get_user_by('login', $u);
			return $user && \wp_check_password($p, $user->user_pass, $user->ID);
		});
		$server->addPlugin(new DAV\Auth\Plugin($authBackend, 'WP WebDAV'));
	}
	$locksPath = cmx_dav_lock_backend_path($sharePath);
	if ($locksPath !== '' && is_dir($locksPath) && is_writable($locksPath)) {
		$server->addPlugin(new DAV\Locks\Plugin(new DAV\Locks\Backend\File($locksPath)));
	}
	// Mac Finder sendet PROPPATCH für eigene Metadaten. Ohne PropertyStorage gibt es oft 100004.
	if (\class_exists(DAV\PropertyStorage\Plugin::class) && \interface_exists(DAV\PropertyStorage\Backend\BackendInterface::class)) {
		$propertyBackend = null;
		if (\class_exists('\\PDO') && \in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
			$propDb = cmx_dav_property_db_path($sharePath);
			try {
				$pdo = new \PDO('sqlite:' . $propDb);
				$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
				$pdo->exec('CREATE TABLE IF NOT EXISTS propertystorage (
					id integer primary key asc,
					path text,
					name text,
					valuetype integer,
					value blob
				)');
				$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS path_property ON propertystorage (path, name)');
				$propertyBackend = new DAV\PropertyStorage\Backend\PDO($pdo);
			} catch (\Throwable $e) {
				$propertyBackend = null;
			}
		}

		// Fallback ohne DB: Properties werden nicht persistiert, PROPPATCH liefert aber kein Finder-Fehlerfenster.
		if ($propertyBackend === null) {
			$propertyBackend = new class implements DAV\PropertyStorage\Backend\BackendInterface {
				public function propFind($path, \Sabre\DAV\PropFind $propFind) {}
				public function propPatch($path, \Sabre\DAV\PropPatch $propPatch) {
					$propPatch->handleRemaining(static function (): bool {
						return true;
					});
				}
				public function delete($path) {}
				public function move($source, $destination) {}
			};
		}

		$server->addPlugin(new DAV\PropertyStorage\Plugin($propertyBackend));
	}

	// Read-only nur für /archiv erzwingen; /scanner bleibt read+write.
	if ($readOnly) {
		$server->on('beforeMethod', function(HTTP\RequestInterface $r){
			$blocked = ['PUT','POST','MKCOL','DELETE','MOVE','COPY','PROPPATCH','LOCK','UNLOCK','PATCH'];
			if (in_array($r->getMethod(), $blocked, true)) {
				throw new DAV\Exception\MethodNotAllowed('Read-only');
			}
		});
	} else {
		$server->on('beforeMethod', function(HTTP\RequestInterface $r) use ($sharePath, $baseUri, $uploadProfile): void {
			$writeMethods = ['PUT','POST','MKCOL','DELETE','MOVE','COPY','PROPPATCH','LOCK','UNLOCK','PATCH'];
			if (!in_array($r->getMethod(), $writeMethods, true)) {
				return;
			}
			if (!is_dir($sharePath) || !is_writable($sharePath)) {
				throw new DAV\Exception\Forbidden('Scanner-Ordner ist nicht beschreibbar (Server-Rechte).');
			}

			$method = \strtoupper((string) $r->getMethod());
			if ($uploadProfile === 'scanner' && $method === 'PUT') {
				$relPath = \ltrim((string) $r->getPath(), '/');
				if (!cmx_dav_is_pdf_file_name($relPath)) {
					throw new DAV\Exception\Forbidden('Im Scanner sind nur PDF-Dateien erlaubt.');
				}
				return;
			}

			if ($uploadProfile === 'scanner' && ($method === 'MOVE' || $method === 'COPY')) {
				$destPath = (string) \parse_url((string) $r->getHeader('Destination'), \PHP_URL_PATH);
				$destRel = cmx_dav_relative_from_base_uri($baseUri, $destPath);
				if ($destRel !== '' && !\str_ends_with($destRel, '/') && !cmx_dav_is_pdf_file_name($destRel)) {
					throw new DAV\Exception\Forbidden('Im Scanner sind nur PDF-Dateien erlaubt.');
				}
			}
		});
			$server->on('afterMethod:PUT', function(HTTP\RequestInterface $request) use ($sharePath, $baseUri): void {
				$relPath = ltrim((string) $request->getPath(), '/');
				$absPath = rtrim($sharePath, DIRECTORY_SEPARATOR) . ($relPath === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
				$real = realpath($absPath);
				if ($real && is_file($real) && cmx_dav_is_subpath($sharePath, $real)) {
					@chmod($real, 0666);
				}
				cmx_dav_cleanup_ds_store($sharePath, $relPath);
				cmx_dav_force_scanner_sync($baseUri);
			});
			$server->on('afterMethod:MOVE', function(HTTP\RequestInterface $request) use ($sharePath, $baseUri): void {
				$relPath = ltrim((string) $request->getPath(), '/');
				$absPath = rtrim($sharePath, DIRECTORY_SEPARATOR) . ($relPath === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
				$real = realpath($absPath);
				if ($real && is_file($real) && cmx_dav_is_subpath($sharePath, $real)) {
					@chmod($real, 0666);
				}
				$destPath = (string) \parse_url((string) $request->getHeader('Destination'), \PHP_URL_PATH);
				$destRel = cmx_dav_relative_from_base_uri($baseUri, $destPath);
				cmx_dav_cleanup_ds_store($sharePath, $relPath);
				cmx_dav_cleanup_ds_store($sharePath, $destRel);
				cmx_dav_force_scanner_sync($baseUri);
			});
			$server->on('afterMethod:DELETE', function(HTTP\RequestInterface $request) use ($sharePath, $baseUri): void {
				$relPath = ltrim((string) $request->getPath(), '/');
				cmx_dav_cleanup_ds_store($sharePath, dirname($relPath));
				cmx_dav_force_scanner_sync($baseUri);
			});
			$server->on('afterMethod:COPY', function(HTTP\RequestInterface $request) use ($sharePath, $baseUri): void {
				$relPath = ltrim((string) $request->getPath(), '/');
				$destPath = (string) \parse_url((string) $request->getHeader('Destination'), \PHP_URL_PATH);
				$destRel = cmx_dav_relative_from_base_uri($baseUri, $destPath);
				cmx_dav_cleanup_ds_store($sharePath, $relPath);
				cmx_dav_cleanup_ds_store($sharePath, $destRel);
				cmx_dav_force_scanner_sync($baseUri);
			});
			$server->on('afterMethod:MKCOL', function(HTTP\RequestInterface $request) use ($sharePath, $baseUri): void {
				$relPath = ltrim((string) $request->getPath(), '/');
				cmx_dav_cleanup_ds_store($sharePath, $relPath);
				cmx_dav_force_scanner_sync($baseUri);
			});
		}

	// Browser-Upload für beschreibbare WebDAV-Bereiche (multipart/form-data).
	if (!$readOnly) {
		$server->on('method:POST', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($server, $sharePath, $maxUploadBytes, $uploadProfile) {
			$contentType = strtolower((string)$request->getHeader('Content-Type'));
			$relPath = trim($request->getPath(), '/');
			$targetDir = rtrim($sharePath, DIRECTORY_SEPARATOR) . ($relPath === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
			$targetReal = realpath($targetDir);
			if (!$targetReal || !is_dir($targetReal) || !cmx_dav_is_subpath($sharePath, $targetReal)) {
				$response->setStatus(400);
				$response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
				$response->setBody('Ungültiger Upload-Pfad.');
				return false;
			}

			$redirectPath = cmx_dav_join_uri(rtrim((string)$server->getBaseUri(), '/'), $relPath) . '/';

			// Browser-Löschen einzelner Dateien aus der Liste.
			$deleteFile = isset($_POST['delete_file']) ? (string) \wp_unslash($_POST['delete_file']) : '';
			if ($deleteFile !== '') {
				$deleteFile = trim($deleteFile);
				$status = 'err';
				$msg = 'Datei konnte nicht gelöscht werden.';

				if ($deleteFile === '' || strpos($deleteFile, '/') !== false || strpos($deleteFile, '\\') !== false || basename($deleteFile) !== $deleteFile) {
					$msg = 'Ungültiger Dateiname.';
				} else {
					$deleteAbs = $targetReal . DIRECTORY_SEPARATOR . $deleteFile;
					$deleteReal = realpath($deleteAbs);
					if (!$deleteReal || !is_file($deleteReal) || !cmx_dav_is_subpath($sharePath, $deleteReal)) {
						$msg = 'Datei nicht gefunden.';
					} elseif (@unlink($deleteReal)) {
						$status = 'ok';
						$msg = 'Datei wurde gelöscht.';
						cmx_dav_cleanup_ds_store($sharePath, $relPath);
						cmx_dav_force_scanner_sync((string) $server->getBaseUri());
					}
				}

				$location = add_query_arg(
					[
						'delete' => $status,
						'msg'    => $msg,
					],
					$redirectPath
				);

				$response->setStatus(303);
				$response->setHeader('Location', (string)$location);
				$response->setBody('');
				return false;
			}

			if (strpos($contentType, 'multipart/form-data') === false) {
				return null;
			}

			$uploadField = $uploadProfile === 'scanner' ? 'scanner_upload' : 'cmx_dav_upload';
			$raw = $_FILES[$uploadField] ?? null;
			$files = [];
			if (is_array($raw) && isset($raw['name'], $raw['tmp_name'], $raw['error'], $raw['size'])) {
				if (is_array($raw['name'])) {
					$count = count($raw['name']);
					for ($i = 0; $i < $count; $i++) {
						$files[] = [
							'name'     => (string)($raw['name'][$i] ?? ''),
							'tmp_name' => (string)($raw['tmp_name'][$i] ?? ''),
							'error'    => (int)($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE),
							'size'     => (int)($raw['size'][$i] ?? 0),
						];
					}
				} else {
					$files[] = [
						'name'     => (string)$raw['name'],
						'tmp_name' => (string)$raw['tmp_name'],
						'error'    => (int)$raw['error'],
						'size'     => (int)$raw['size'],
					];
				}
			}

			if ($uploadProfile === 'scanner') {
				$allowedExt = ['pdf', 'xml'];
				$allowedMimes = [
					'pdf'  => 'application/pdf',
					'xml'  => 'text/xml',
				];
				$typeError = 'Dateityp nicht erlaubt (nur PDF oder XML).';
				$extError = 'Erlaubt sind nur PDF- oder XML-Dateien.';
			} else {
				$allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'mp4', 'mov', 'webm'];
				$allowedMimes = [
					'pdf'  => 'application/pdf',
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
					'webp' => 'image/webp',
					'gif'  => 'image/gif',
					'heic' => 'image/heic',
					'heif' => 'image/heif',
					'mp4'  => 'video/mp4',
					'mov'  => 'video/quicktime',
					'webm' => 'video/webm',
				];
				$typeError = 'Dateityp nicht erlaubt.';
				$extError = 'Erlaubt sind PDF, Bilder und Videos.';
			}
			$okCount = 0;
			$firstError = '';

			foreach ($files as $file) {
				$err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
				if ($err === UPLOAD_ERR_NO_FILE) {
					continue;
				}
				if ($err !== UPLOAD_ERR_OK) {
					if ($firstError === '') {
						$firstError = 'Upload fehlgeschlagen (Serverlimit oder Transferfehler).';
					}
					continue;
				}

				$size = (int)($file['size'] ?? 0);
				if ($size <= 0 || $size > $maxUploadBytes) {
					if ($firstError === '') {
						$firstError = 'Datei muss zwischen 1 Byte und 100 MB liegen.';
					}
					continue;
				}

				$tmpName = (string)($file['tmp_name'] ?? '');
				$origName = (string)($file['name'] ?? '');
				if ($tmpName === '' || $origName === '' || !is_uploaded_file($tmpName)) {
					if ($firstError === '') {
						$firstError = 'Ungültige Upload-Datei.';
					}
					continue;
				}

				$safeName = sanitize_file_name($origName);
				if ($safeName === '') {
					if ($firstError === '') {
						$firstError = 'Ungültiger Dateiname.';
					}
					continue;
				}

				$ext = strtolower((string)pathinfo($safeName, PATHINFO_EXTENSION));
				if (!in_array($ext, $allowedExt, true)) {
					if ($firstError === '') {
						$firstError = $extError;
					}
					continue;
				}

				$fileType = wp_check_filetype_and_ext($tmpName, $safeName, $allowedMimes);
				if (empty($fileType['ext']) || !in_array((string)$fileType['ext'], $allowedExt, true)) {
					if ($firstError === '') {
						$firstError = $typeError;
					}
					continue;
				}

				$destName = wp_unique_filename($targetReal, $safeName);
				$destAbs  = $targetReal . DIRECTORY_SEPARATOR . $destName;
				if (!move_uploaded_file($tmpName, $destAbs)) {
					if ($firstError === '') {
						$firstError = 'Datei konnte nicht gespeichert werden.';
					}
					continue;
				}

				@chmod($destAbs, 0666);
				$okCount++;
			}

			$status = $okCount > 0 ? 'ok' : 'err';
			if ($okCount > 0) {
				$msg = $okCount . ' Datei(en) hochgeladen.';
				if ($firstError !== '') {
					$msg .= ' Hinweis: ' . $firstError;
				}
			} else {
				$msg = $firstError !== '' ? $firstError : 'Keine Datei ausgewählt.';
			}

				cmx_dav_cleanup_ds_store($sharePath, $relPath);
				if ($okCount > 0) {
					cmx_dav_force_scanner_sync((string) $server->getBaseUri());
				}
				$location = add_query_arg(
					[
						'upload' => $status,
					'msg'    => $msg,
				],
				$redirectPath
			);

			$response->setStatus(303);
			$response->setHeader('Location', (string)$location);
			$response->setBody('');
			return false;
		});
	}

	// PDFs/Images inline anzeigen statt Download
	$server->on('afterMethod:GET', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($sharePath) {
		$path = $request->getPath();
		$q = [];
		parse_str((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $q);
		if (!empty($q['zip'])) return;

		$relPath = ltrim($path, '/');
		$absPath = rtrim($sharePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
		$real = realpath($absPath);
		if (!$real || !is_file($real) || !cmx_dav_is_subpath($sharePath, $real)) {
			return;
		}

		$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
		$mime_map = [
			'pdf'  => 'application/pdf',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
			'mp4'  => 'video/mp4',
			'mov'  => 'video/quicktime',
			'webm' => 'video/webm',
		];
		if (!isset($mime_map[$ext])) return;

		$filename = basename($real);
		$response->setHeader('Content-Type', $mime_map[$ext]);
		$response->setHeader('Content-Disposition', 'inline; filename="'.cmx_dav_h($filename).'"');
	});

	// Hübsche HTML-Indexseite für Collections + ZIP-Download
	$server->on('method:GET', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($server, $sharePath, $baseUri, $label, $readOnly, $hiddenRootNames, $uploadProfile) {
		$relPath = trim($request->getPath(), '/'); // relativ zur BaseUri
		cmx_dav_cleanup_ds_store($sharePath, $relPath);
		$tree    = $server->tree;

		$exists  = $tree->nodeExists($request->getPath());
		$node    = $exists ? $tree->getNodeForPath($request->getPath()) : null;

		// Wenn Ordner und ?zip=1: ZIP ausliefern
		if ($node instanceof DAV\ICollection) {
			$q = [];
			parse_str((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $q);

			if (!empty($q['zip'])) {
				$absDir = rtrim($sharePath, DIRECTORY_SEPARATOR) . (empty($relPath) ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
				$absDirReal = realpath($absDir);

				if (!$absDirReal || !is_dir($absDirReal) || !cmx_dav_is_subpath($sharePath, $absDirReal)) {
					$response->setStatus(400);
					$response->setBody('Ungültiger Pfad.');
					return false;
				}

				// Temp-ZIP
				$tmpZip = (function_exists('\\wp_tempnam') ? \wp_tempnam('misbuerozip_') : \tempnam(\sys_get_temp_dir(), 'misbuerozip_'));
				if (!$tmpZip) {
					$response->setStatus(500);
					$response->setBody('Temporäre Datei konnte nicht erstellt werden.');
					return false;
				}

				$zip = new \ZipArchive();
				if (true !== $zip->open($tmpZip, \ZipArchive::OVERWRITE)) {
					$response->setStatus(500);
					$response->setBody('ZIP konnte nicht erstellt werden.');
					return false;
				}

				/**
				 * NEU (B): Nur ausgewählte Dateien zippen, wenn sel[]= übergeben wurde.
				 * - Erwartet Dateinamen der aktuellen Ebene (keine Pfade).
				 * - Fällt zurück auf kompletten Ordner (rekursiv), wenn keine Auswahl.
				 */
				$selected = [];
				if (!empty($q['sel'])) {
					$selected = is_array($q['sel']) ? $q['sel'] : [$q['sel']];
					// nur Basename, um Pfad-Tricks zu vermeiden
					$selected = array_values(array_filter(array_map('basename', array_map('strval', $selected))));
				}

					if ($selected) {
						foreach ($selected as $fn) {
							$full = $absDirReal . DIRECTORY_SEPARATOR . $fn;
							$real = realpath($full);
						// nur reguläre Dateien derselben Ebene zulassen
						if (!$real || !cmx_dav_is_subpath($absDirReal, $real) || !is_file($real)) {
							continue;
							}
							$zip->addFile($real, $fn);
						}
					} else {
						// wie bisher: kompletter Ordner rekursiv
						$zipExcludeRootNames = ($relPath === '') ? $hiddenRootNames : [];
						cmx_dav_zip_dir($absDirReal, $zip, rtrim($absDirReal, DIRECTORY_SEPARATOR), $zipExcludeRootNames);
					}

					$zip->close();

					$zipLabel = ($relPath === '' ? 'root' : trim($relPath, '/'));
					$filename = $zipLabel . ($selected ? '-auswahl' : '') . '.zip';

				$response->setStatus(200);
				$response->setHeader('Content-Type', 'application/zip');
				$response->setHeader('Content-Disposition', 'attachment; filename="'.cmx_dav_h($filename).'"');
				$response->setHeader('Content-Length', (string)filesize($tmpZip));
				$response->setBody(function() use ($tmpZip) {
					$fh = fopen($tmpZip, 'rb');
					if ($fh) {
						while (!feof($fh)) {
							echo fread($fh, 8192);
						}
						fclose($fh);
					}
					@unlink($tmpZip);
				});
				return false;
			}
		}

			if ($node instanceof DAV\ICollection) {
				// Root-Archiv ohne Dateien: komplett leere Seite ausgeben.
				if ($readOnly && $relPath === '' && !cmx_dav_has_any_files($sharePath, $hiddenRootNames)) {
					$response->setStatus(200);
					$response->setHeader('Content-Type', 'text/html; charset=UTF-8');
					$response->setBody(cmx_dav_render_empty_archive_message('Es wurden noch keine Dateien abgelegt.', $label));
					return false;
				}

				$childrenRaw = iterator_to_array($node->getChildren());
				$base        = rtrim($server->getBaseUri(), '/');

			// Sortier-Parameter einlesen
			$sort = isset($q['sort']) && in_array($q['sort'], ['name','size','mtime'], true) ? $q['sort'] : 'name';
			$dir  = isset($q['dir'])  && in_array(strtolower((string)$q['dir']), ['asc','desc'], true) ? strtolower((string)$q['dir']) : 'asc';
			$sortParams = ($sort !== 'name' || $dir !== 'asc') ? ['sort'=>$sort, 'dir'=>$dir] : [];
			$sortQuery  = $sortParams ? '?' . http_build_query($sortParams) : '';

			// Children inkl. Metadaten aufbereiten
			$children = [];
			foreach ($childrenRaw as $child) {
				$isDir = $child instanceof DAV\ICollection;
				$sizeBytes = null;
				$mtimeTs   = null;

				if (!$isDir && method_exists($child, 'getSize')) {
					try { $sizeBytes = (int) $child->getSize(); } catch (\Throwable $e) { $sizeBytes = null; }
				}
				if (method_exists($child, 'getLastModified')) {
					try { $mtimeTs = (int) $child->getLastModified(); } catch (\Throwable $e) { $mtimeTs = null; }
				}

				$children[] = [
					'node'      => $child,
					'isDir'     => $isDir,
					'name'      => $child->getName(),
					'sizeBytes' => $sizeBytes,
					'mtimeTs'   => $mtimeTs,
				];
			}

			usort($children, function($a, $b) use ($sort, $dir) {
				// Ordner immer vor Dateien
				if ($a['isDir'] !== $b['isDir']) return $a['isDir'] ? -1 : 1;

				$mult = ($dir === 'desc') ? -1 : 1;

				if ($sort === 'size') {
					$sa = $a['sizeBytes'] ?? -1;
					$sb = $b['sizeBytes'] ?? -1;
					if ($sa !== $sb) return ($sa <=> $sb) * $mult;
				}
				elseif ($sort === 'mtime') {
					$ma = $a['mtimeTs'] ?? -1;
					$mb = $b['mtimeTs'] ?? -1;
					if ($ma !== $mb) return ($ma <=> $mb) * $mult;
				}

				// Fallback: Name
				return strnatcasecmp($a['name'], $b['name']) * $mult;
			});

			// Breadcrumbs
			$segments = array_values(array_filter(explode('/', $relPath), fn($s)=>$s!==''));
			$crumbs   = [];
			$accum    = '';
			$crumbs[] = '<a href="'.cmx_dav_h($base).'">/</a>';
			foreach ($segments as $seg) {
				$accum = trim($accum.'/'.$seg, '/');
				$crumbs[] = '<span class="sep">›</span><a href="'.cmx_dav_h(cmx_dav_join_uri($base, $accum)).'/'.cmx_dav_h($sortQuery).'">'.cmx_dav_h($seg).'</a>';
			}

			// Aktuelle Ordner-URL (ohne ?zip)
			$currentDirUrl = cmx_dav_join_uri($base, $relPath) . '/';

			/**
			 * NEU (B): ZIP-Button als Formular-Submit,
			 * damit die markierten Dateien (sel[]) übertragen werden.
			 */
				$zipButton = '<button type="submit" form="zipform" class="zipbtn" title="Auswahl als ZIP herunterladen" aria-label="Auswahl als ZIP herunterladen">'
					. '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 3v10m0 0 4-4m-4 4-4-4M5 21h14a2 2 0 0 0 2-2v0a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v0a2 2 0 0 0 2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
					. '<span></span></button>';
				$uploadForm = '';
				if (!$readOnly) {
					$uploadField = $uploadProfile === 'scanner' ? 'scanner_upload' : 'cmx_dav_upload';
					$accept = $uploadProfile === 'scanner'
						? '.pdf,.xml,application/pdf,text/xml,application/xml'
						: '.pdf,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.mp4,.mov,.webm,application/pdf,image/*,video/*';
					$hint = $uploadProfile === 'scanner'
						? 'Nur PDF oder XML, max. 100 MB pro Datei'
						: 'PDF, Bilder oder Videos, max. 100 MB pro Datei';
					$uploadForm = '<form id="uploadform" method="POST" enctype="multipart/form-data" action="'.cmx_dav_h($currentDirUrl).'" class="uploadform">'
						. '<input type="file" name="' . cmx_dav_h($uploadField) . '[]" accept="' . cmx_dav_h($accept) . '" multiple required class="upload-input" />'
						. '<button type="submit" class="btn btn-upload">Hochladen</button>'
						. '<span class="upload-hint">' . cmx_dav_h($hint) . '</span>'
						. '</form>';
				}
				$uploadNotice = '';
				if (!$readOnly && isset($q['upload'], $q['msg'])) {
					$isOk = ((string)$q['upload'] === 'ok');
					$uploadNotice = '<div class="upload-notice '.($isOk ? 'is-ok' : 'is-err').'">'.cmx_dav_h((string)$q['msg']).'</div>';
				}
				if (!$readOnly && $uploadNotice === '' && isset($q['delete'], $q['msg'])) {
					$isOk = ((string)$q['delete'] === 'ok');
					$uploadNotice = '<div class="upload-notice '.($isOk ? 'is-ok' : 'is-err').'">'.cmx_dav_h((string)$q['msg']).'</div>';
				}
				$toolbarClass = $readOnly ? 'toolbar' : 'toolbar toolbar-upload';
				$baseLeaf = strtolower((string) basename(trim((string) $baseUri, '/')));
				$showSearch = in_array($baseLeaf, ['archiv', 'scanner'], true);
				$searchBar = $showSearch
					? '<div class="head-search">'
						. '<label for="cmx-dav-search" class="sr-only">Suche</label>'
						. '<input type="search" id="cmx-dav-search" class="search-input" placeholder="Name filtern..." autocomplete="off" />'
						. '<span id="cmx-dav-search-meta" class="search-meta" aria-live="polite"></span>'
						. '</div>'
					: '';

			// Parent-Link (..), sofern nicht Root
				$rows = '';
				if (!empty($segments)) {
					$parent = array_slice($segments, 0, -1);
					$parentHref = cmx_dav_h(cmx_dav_join_uri($base, implode('/', $parent))).'/' . cmx_dav_h($sortQuery);
					$rows .= '<tr class="cmx-row up" data-search=".."><td class="sel"></td><td class="name"><a href="'.$parentHref.'">..</a></td><td class="type">Ordner</td><td class="size"></td><td class="mtime"></td><td class="action"></td></tr>';
				}

			// Anzahl der Dateien (nur aktuelle Ebene, Ordner ausgeschlossen)
			$fileCount = count(array_filter($children, fn($c) => !($c['isDir'])));

			// Helper: Sort-Links aufbauen
			$sortLink = function(string $field) use ($base, $relPath, $sort, $dir): string {
				$nextDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
				$params  = http_build_query(['sort'=>$field, 'dir'=>$nextDir]);
				return cmx_dav_h(cmx_dav_join_uri($base, $relPath).'/'.'?'.$params);
			};
			$arrow = function(string $field) use ($sort, $dir): string {
				if ($sort !== $field) return '';
				return $dir === 'asc' ? ' ▲' : ' ▼';
			};

			// Tabellenzeilen aufbauen (mit Checkboxen für Dateien)
			foreach ($children as $item) {
				/** @var DAV\INode $child */
				$child = $item['node'];
				$name  = $item['name'];
				$isDir = $item['isDir'];
				if (!$isDir && $name === '.DS_Store') {
					continue;
				}
				$href  = cmx_dav_join_uri($base, $relPath, rawurlencode($name)) . ($isDir ? '/' : '') . (!$isDir && $sortQuery ? '' : $sortQuery);
				$absHref = cmx_dav_abs_url($href);

				// Metadaten
				$type  = $isDir ? 'Ordner' : 'Datei';
				$size  = '';
				$mtime = '';

				if ($item['mtimeTs']) $mtime = cmx_dav_fmt_time($item['mtimeTs']);
				if (!$isDir && $item['sizeBytes'] !== null) $size = cmx_dav_human_bytes($item['sizeBytes']);

			// Anzahl der Dateien (nur aktuelle Ebene, Ordner ausgeschlossen)
			$fileCount = count(array_filter($children, fn($c) => !($c['isDir'])));

			// Checkbox nur für Dateien (gleiche Ebene)
				$checkbox = !$isDir
					? '<input type="checkbox" class="cmx-sel" name="sel[]" value="'.cmx_dav_h($name).'" />'
					: '';

				// Copy-Pfad-Icon
				$copyBtn = !$isDir
				? '<button class="btn btn-copy" data-clip="'.cmx_dav_h($absHref).'" title="Pfad in Zwischenablage kopieren" aria-label="Pfad kopieren">'
					.'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
						<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
						<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
					</svg>'
				.'</button>'
				: '';

				$downloadBtn = !$isDir
					? '<a class="btn btn-secondary" href="'.cmx_dav_h($href).'" download>
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
							class="bi bi-download" viewBox="0 0 16 16" style="vertical-align:middle;margin-right:4px;">
						  <path d="M.5 9.9a.5.5 0 0 1 .5.5V14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10.4a.5.5 0 0 1 1 0V14a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V10.4a.5.5 0 0 1 .5-.5z"/>
						  <path d="M7.646 10.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 1 0-.708-.708L8.5 9.293V1.5a.5.5 0 0 0-1 0v7.793L5.354 7.146a.5.5 0 1 0-.708.708l3 3z"/>
						</svg>
					  </a>'
					: '';
				$deleteBtn = (!$isDir && !$readOnly)
					? '<form method="POST" action="'.cmx_dav_h($currentDirUrl).'" class="inline-delete-form" onsubmit="return confirm(\'Datei wirklich löschen?\');">'
						. '<input type="hidden" name="delete_file" value="'.cmx_dav_h($name).'" />'
						. '<button type="submit" class="btn btn-danger btn-delete" title="Datei löschen" aria-label="Datei löschen">'
							. '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">'
								. '<path d="M5.5 5.5a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>'
								. '<path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 1 1 0-2H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4 4v9a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4H4z"/>'
							. '</svg>'
						. '</button>'
					. '</form>'
					: '';
				$actionCell = $isDir ? '' : trim($copyBtn.' '.$downloadBtn.' '.$deleteBtn);

				$link_target = $isDir ? '' : ' target="_blank" rel="noopener noreferrer"';
				$rows .= '<tr class="cmx-row" data-search="'.cmx_dav_h($name).($isDir ? '/' : '').'">'
					. '<td class="sel">'.($checkbox).'</td>'
					. '<td class="name"><a href="'.cmx_dav_h($href).'"'.$link_target.'>'.cmx_dav_h($name).($isDir?'/':'').'</a></td>'
					. '<td class="type">'.cmx_dav_h($type).'</td>'
					. '<td class="size">'.cmx_dav_h($size).'</td>'
					. '<td class="mtime">'.cmx_dav_h($mtime).'</td>'
					. '<td class="action">'.$actionCell.'</td>'
					. '</tr>';
			}

				$title = 'Mis Büro – ' . $label . ' ' . (empty($segments) ? '/' : implode('/', $segments).'/');
				$footerModeText = $readOnly ? 'nur lesen' : 'lesen, schreiben, löschen';

				$html = '<!doctype html><html lang="de"><head><meta charset="utf-8">'
				.'<meta name="viewport" content="width=device-width,initial-scale=1">'
				.'<title>'.cmx_dav_h($title).'</title>'
				.'<style>
					/* === Mis Büro Style === */
					:root{
						--bg:#f7f7f8; --card:#ffffff; --muted:#667085; --text:#0f172a;
						--accent:#e53935; --accent-weak:#fee2e2; --ok:#16a34a; --border:#e5e7eb;
						--radius:16px; --shadow:0 8px 24px rgba(15,23,42,.06);
					}
					*{box-sizing:border-box}
					html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial}
						.wrap{max-width:1060px;margin:32px auto;padding:0 20px}
							.headbar{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 0 10px 0}
							h1{font-size:26px;font-weight:800;letter-spacing:-.01em;margin:0}
							.toolbar{display:flex;align-items:center;gap:12px;margin:8px 0 18px 0}
							.toolbar-upload{flex-wrap:wrap}
							.zipbtn{ display:inline-flex;align-items:center;gap:8px;padding:10px 2px 10px 10px;border:1px solid var(--accent);border-radius:calc(var(--radius) - 6px);text-decoration:none;color:#fff;background:var(--accent);box-shadow:var(--shadow);cursor:pointer}
							.zipbtn:hover{filter:saturate(1.05) brightness(1.02)}
							.zipbtn .ico{display:block}
						.uploadform{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap}
						.upload-input{max-width:320px}
						.btn-upload{padding:10px 14px;border-color:var(--accent);color:var(--accent);font-weight:600}
						.upload-hint{font-size:12px;color:var(--muted)}
						.upload-notice{margin:10px 0 14px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:#fff}
						.upload-notice.is-ok{border-color:#16a34a33;color:#166534}
						.upload-notice.is-err{border-color:#dc262633;color:#991b1b}
						.head-search{display:inline-flex;align-items:center;gap:10px;max-width:420px;min-width:280px}
						.search-input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff;color:var(--text)}
						.search-input:focus{outline:none;border-color:#d1d5db;box-shadow:0 0 0 3px rgba(229,57,53,.08)}
						.search-meta{font-size:12px;color:var(--muted)}
						.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
						@media (max-width: 780px){
							.headbar{flex-wrap:wrap}
							.head-search{max-width:none;min-width:0;width:100%}
						}
						.breadcrumbs{display:inline-flex;align-items:center;gap:10px;margin-left:6px;color:var(--muted);font-weight:500}
						.breadcrumbs a{color:var(--text);text-decoration:none;border-radius:10px;padding:4px 6px}
						.breadcrumbs a:hover{background:var(--accent-weak)}
					.breadcrumbs .sep{color:var(--muted)}
					.table{width:100%;border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
					th,td{padding:12px 14px;text-align:left;white-space:nowrap}
					th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#fafafa}
					tr:not(:last-child) td{border-bottom:1px solid var(--border)}
					td.sel{width:40px}
					td.name{max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600}
					td.size,td.mtime,td.type,td.action{color:var(--muted)}
					a{color:var(--text);text-decoration:none}
					td.name a{color:var(--text)}
					tr:hover td{background:#fcfcfd}
					tr.up td.name a{font-weight:700}
					.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid var(--border);border-radius:12px;text-decoration:none;background:#fff;color:var(--text);cursor:pointer;box-shadow:0 1px 0 rgba(15,23,42,.03)}
					.btn:hover{border-color:#d1d5db;background:#fff}
					.btn-secondary{background:#fff}
					.inline-delete-form{display:inline}
					.btn-danger{color:#b42318;border-color:#fecaca;background:#fff}
					.btn-danger:hover{border-color:#fca5a5;background:#fff5f5}
					.btn-copy.ok{border-color:var(--ok)}
					.footer{margin-top:14px;font-size:12px;color:var(--muted)}
					a.link-misbuero { color: black; text-decoration: none; cursor: pointer; transition: color 0.2s ease-in-out; }
					a.link-misbuero:hover { color: red; }
					/* Toast */
					.toast{position:fixed;right:18px;top:18px;background:#fff;color:var(--text);border:1px solid var(--border);padding:12px 14px;border-radius:12px;box-shadow:var(--shadow);z-index:9999;opacity:.98}
					.toast:before{content:"";display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--ok);margin-right:8px;vertical-align:middle}
					/* Master-Checkbox */
					.cmx-master{transform:scale(1.1)}
				</style></head><body><div class="wrap">'

				// Formular für ZIP-Auswahl
				.'<form id="zipform" method="GET" action="'.cmx_dav_h($currentDirUrl).'">'
				.'<input type="hidden" name="zip" value="1" />'
				.'</form>'

				.'<div class="headbar"><h1><a href="'.cmx_dav_h($baseUri).'/">Mis Büro - '.cmx_dav_h($label).'</a></h1>'.$searchBar.'</div>'
				.'<div class="'.cmx_dav_h($toolbarClass).'">'.$zipButton.'<span class="breadcrumbs">'.implode('', $crumbs).'</span>'.$uploadForm.'</div>'
				.$uploadNotice
				.'<table class="table"><thead><tr>'
				.'<th><input type="checkbox" class="cmx-master" title="Alle Dateien auswählen" /></th>'
				.'<th><a href="'.$sortLink('name').'">Name'.$arrow('name').'</a></th>'
				.'<th>Typ</th>'
				.'<th><a href="'.$sortLink('size').'">Größe'.$arrow('size').'</a></th>'
				.'<th><a href="'.$sortLink('mtime').'">Geändert'.$arrow('mtime').'</a></th>'
				.'<th>Aktion</th>'
					.'</tr></thead><tbody>'
					.$rows
					.'</tbody></table>'
					.'&nbsp;&nbsp;&nbsp;('.cmx_dav_h((string)$fileCount).') '.cmx_dav_h($footerModeText)


				// Script: Clipboard + 3s Toast + Auswahl-Handling
				.'<script>
				(function(){
					function showToast(msg){
						var t=document.createElement("div");
						t.className="toast";
						t.textContent=msg;
						document.body.appendChild(t);
						setTimeout(function(){ t.remove(); }, 3000);
					}

					// Pfad kopieren
					document.addEventListener("click",function(e){
						var b=e.target.closest(".btn-copy");
						if(!b) return;
						e.preventDefault();
						var txt=b.getAttribute("data-clip")||"";
						if(!txt) return;
						var onOk=function(){ b.classList.add("ok"); showToast("Pfad kopiert: "+txt); setTimeout(function(){b.classList.remove("ok");},800); };
						if(navigator.clipboard && navigator.clipboard.writeText){
							navigator.clipboard.writeText(txt).then(onOk).catch(function(){ alert("Pfad konnte nicht kopiert werden."); });
						}else{
							var ta=document.createElement("textarea"); ta.value=txt; document.body.appendChild(ta); ta.select();
							try{ document.execCommand("copy"); onOk(); }catch(err){ alert("Pfad konnte nicht kopiert werden."); }
							document.body.removeChild(ta);
						}
					});

					// NEU: Master-Checkbox
					var master = document.querySelector(".cmx-master");
					function forEachSel(cb){ document.querySelectorAll(".cmx-sel").forEach(cb); }

					if(master){
						master.addEventListener("change", function(){
							forEachSel(function(el){ el.checked = master.checked; });
						});
					}

						// NEU: Beim ZIP-Submit die ausgewählten Dateien in das GET-Formular übernehmen
						var zipForm = document.getElementById("zipform");
						document.addEventListener("click", function(e){
							var btn = e.target.closest(".zipbtn");
							if(!btn) return;
						// vor dem Submit alte sel[]-Inputs aus dem Formular entfernen
						[].slice.call(zipForm.querySelectorAll(\'input[name="sel[]"]\')).forEach(function(n){ n.remove(); });
						// markierte Dateien als sel[] hinzufügen
						forEachSel(function(el){
							if(el.checked){
								var i=document.createElement("input");
								i.type="hidden"; i.name="sel[]"; i.value=el.value;
								zipForm.appendChild(i);
							}
						});
							// Standard: wenn keine Auswahl -> Formular nur mit zip=1 absenden (kompletter Ordner)
						});

						// Suche (/archiv und /scanner): Zeilen live nach Name filtern
						var searchInput = document.getElementById("cmx-dav-search");
						var searchMeta = document.getElementById("cmx-dav-search-meta");
						if(searchInput){
							var rows = [].slice.call(document.querySelectorAll("table.table tbody tr.cmx-row"));
							var normalize = function(v){ return (v || "").toString().toLowerCase().trim(); };
							var updateFilter = function(){
								var query = normalize(searchInput.value);
								var visible = 0;
								rows.forEach(function(row){
									var hay = normalize(row.getAttribute("data-search"));
									var match = (query === "") || (hay.indexOf(query) !== -1);
									row.style.display = match ? "" : "none";
									if(match) visible++;
								});
								if(searchMeta){
									searchMeta.textContent = query === "" ? "" : (visible + " Treffer");
								}
							};
							searchInput.addEventListener("input", updateFilter);
							updateFilter();
						}

					})();
					</script>'
				.'</div></body></html>';

			$response->setStatus(200);
			$response->setHeader('Content-Type','text/html; charset=UTF-8');
			$response->setBody($html);
			return false; // verhindert Standard-Handling
		}

		// Dateien normal ausliefern lassen
		return null;
	});

	$server->exec();
	exit;
});
