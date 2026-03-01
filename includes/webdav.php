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

	if (!empty($hiddenRootNames)) {
		$server->on('beforeMethod', function(HTTP\RequestInterface $request) use ($hiddenRootNames): void {
			if (!cmx_dav_path_hits_hidden_root((string) $request->getPath(), $hiddenRootNames)) {
				return;
			}
			throw new DAV\Exception\NotFound('File not found');
		});
	}

	// BasicAuth mit WP-Usern
	$authBackend = new DAV\Auth\Backend\BasicCallBack(function($u,$p){
		$user = \get_user_by('login', $u);
		return $user && \wp_check_password($p, $user->user_pass, $user->ID);
	});
	$server->addPlugin(new DAV\Auth\Plugin($authBackend, 'WP WebDAV'));
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
		$server->on('beforeMethod', function(HTTP\RequestInterface $r) use ($sharePath): void {
			$writeMethods = ['PUT','POST','MKCOL','DELETE','MOVE','COPY','PROPPATCH','LOCK','UNLOCK','PATCH'];
			if (!in_array($r->getMethod(), $writeMethods, true)) {
				return;
			}
			if (!is_dir($sharePath) || !is_writable($sharePath)) {
				throw new DAV\Exception\Forbidden('Scanner-Ordner ist nicht beschreibbar (Server-Rechte).');
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

	// Browser-Upload nur für /scanner (multipart/form-data).
	if (!$readOnly) {
		$server->on('method:POST', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($server, $sharePath, $maxUploadBytes) {
			$contentType = strtolower((string)$request->getHeader('Content-Type'));
			if (strpos($contentType, 'multipart/form-data') === false) {
				return null;
			}

			$relPath = trim($request->getPath(), '/');
			$targetDir = rtrim($sharePath, DIRECTORY_SEPARATOR) . ($relPath === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
			$targetReal = realpath($targetDir);
			if (!$targetReal || !is_dir($targetReal) || !cmx_dav_is_subpath($sharePath, $targetReal)) {
				$response->setStatus(400);
				$response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
				$response->setBody('Ungültiger Upload-Pfad.');
				return false;
			}

			$raw = $_FILES['scanner_upload'] ?? null;
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

			$allowedExt = ['pdf', 'png', 'jpg'];
			$allowedMimes = [
				'pdf'  => 'application/pdf',
				'png'  => 'image/png',
				'jpg'  => 'image/jpeg',
			];
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
						$firstError = 'Erlaubt sind nur PDF, PNG, JPG.';
					}
					continue;
				}

				$fileType = wp_check_filetype_and_ext($tmpName, $safeName, $allowedMimes);
				if (empty($fileType['ext']) || !in_array((string)$fileType['ext'], $allowedExt, true)) {
					if ($firstError === '') {
						$firstError = 'Dateityp nicht erlaubt (nur PDF, PNG, JPG).';
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

				$redirectPath = cmx_dav_join_uri(rtrim((string)$server->getBaseUri(), '/'), $relPath) . '/';
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
		];
		if (!isset($mime_map[$ext])) return;

		$filename = basename($real);
		$response->setHeader('Content-Type', $mime_map[$ext]);
		$response->setHeader('Content-Disposition', 'inline; filename="'.cmx_dav_h($filename).'"');
	});

	// Hübsche HTML-Indexseite für Collections + ZIP-Download
	$server->on('method:GET', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($server, $sharePath, $baseUri, $label, $readOnly, $hiddenRootNames) {
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
					$uploadForm = '<form id="uploadform" method="POST" enctype="multipart/form-data" action="'.cmx_dav_h($currentDirUrl).'" class="uploadform">'
						. '<input type="file" name="scanner_upload[]" accept=".pdf,.png,.jpg,application/pdf,image/png,image/jpeg" multiple required class="upload-input" />'
						. '<button type="submit" class="btn btn-upload">Hochladen</button>'
						. '<span class="upload-hint">Nur PDF/PNG/JPG, max. 100 MB pro Datei</span>'
						. '</form>';
				}
				$uploadNotice = '';
				if (!$readOnly && isset($q['upload'], $q['msg'])) {
					$isOk = ((string)$q['upload'] === 'ok');
					$uploadNotice = '<div class="upload-notice '.($isOk ? 'is-ok' : 'is-err').'">'.cmx_dav_h((string)$q['msg']).'</div>';
				}
				$toolbarClass = $readOnly ? 'toolbar' : 'toolbar toolbar-upload';

			// Parent-Link (..), sofern nicht Root
			$rows = '';
			if (!empty($segments)) {
				$parent = array_slice($segments, 0, -1);
				$parentHref = cmx_dav_h(cmx_dav_join_uri($base, implode('/', $parent))).'/' . cmx_dav_h($sortQuery);
				$rows .= '<tr class="up"><td class="sel"></td><td class="name"><a href="'.$parentHref.'">..</a></td><td class="type">Ordner</td><td class="size"></td><td class="mtime"></td><td class="action"></td></tr>';
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

				$link_target = $isDir ? '' : ' target="_blank" rel="noopener noreferrer"';
				$rows .= '<tr>'
					. '<td class="sel">'.($checkbox).'</td>'
					. '<td class="name"><a href="'.cmx_dav_h($href).'"'.$link_target.'>'.cmx_dav_h($name).($isDir?'/':'').'</a></td>'
					. '<td class="type">'.cmx_dav_h($type).'</td>'
					. '<td class="size">'.cmx_dav_h($size).'</td>'
					. '<td class="mtime">'.cmx_dav_h($mtime).'</td>'
					. '<td class="action">'.($isDir ? '' : $copyBtn.' '.$downloadBtn).'</td>'
					. '</tr>';
			}

			$title = 'Mis Büro – ' . $label . ' ' . (empty($segments) ? '/' : implode('/', $segments).'/');

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
						h1{font-size:26px;font-weight:800;letter-spacing:-.01em;margin:0 0 10px 0}
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

				.'<h1><a href="'.cmx_dav_h($baseUri).'/">Mis Büro - '.cmx_dav_h($label).'</a></h1>'
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
				// .'managed by S33S<a target="_blank" href="https://misbuero.ch/" class="link-misbuero">mis-buero.ch</a>'
				.'&nbsp;&nbsp;&nbsp;('.cmx_dav_h((string)$fileCount).') managed by <a target="_blank" href="https://misbuero.ch/" class="link-misbuero">mis-buero.ch</a>'


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
