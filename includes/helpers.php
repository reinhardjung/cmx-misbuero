<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// cmx_sani_key('Meine Farben', 'slug');
// camel=artikelFarben, save=artikelfarben studly/pascal=ArtikelFarben, upper=ARTIKEL_FARBEN, lower=artikel_farben, snake=artikel_farben, kebab/slug=artikel-farben, dot=artikel.farben, title=Artikel Farben
function cmx_sani_key(string $s, string $mode = 'lower'): string {
	$s = strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss']);

	$s = preg_replace('~[^A-Za-z0-9]+~', ' ', trim($s));
	if ($s === '') return '';

	$parts = preg_split('~\s+~', $s);
	$lower = array_map('strtolower', $parts);
	$studl = array_map('ucfirst', $lower);

	switch (strtolower($mode)) {
		case 'dot':    return implode('.', $lower);
		case 'safe':   return implode('', $lower);
		case 'slug':
		case 'kebab':  return implode('-', $lower);
		case 'camel':  return lcfirst(implode('', $studl));
		case 'upper':  return strtoupper(implode('_', $parts));
		case 'snake':
		case 'lower':  return implode('_', $lower);
		case 'title':  return implode(' ', $studl);
		case 'studly':
		case 'pascal': return implode('', $studl);
		default:       return implode('_', $lower);
	}
}


function cmx_tax_key(string $cpt, string $singular): string {
	$base = strtolower(trim($cpt . '_' . $singular));
	$base = preg_replace('~[^a-z0-9_]+~', '_', $base);
	return trim($base, '_');
}


// cmx_require_files(__DIR__, 'stammdaten, lieferanten');
function cmx_require_files(string $dir, string|array $files): void {
	if (is_string($files)) {
		$files = array_map('trim', explode(',', $files)); // "stammdaten, lieferanten" → ['stammdaten', 'lieferanten']
	}

	foreach ($files as $file) {
		if ($file === '') continue;

		// Erweiterung sicherstellen
		$filename = str_ends_with($file, '.php') ? $file : "{$file}.php";
		// var_dump($files); exit;
		// var_dump($filename);
		$path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
// var_dump($path); exit;
		if (is_file($path) && is_readable($path)) {
			require_once $path;
		} else {
			error_log("[CMX] Datei nicht gefunden oder nicht lesbar: {$path}");
		}
	}
}


// cmx_require_dir(__DIR__. '/includes', false); // Oder ohne Ausschluss der aktuellen Datei (selten nötig)
function cmx_require_dir(?string $dir = null, bool $self_exclude = true): void {
	$dir = $dir ?: __DIR__;
	if (!is_dir($dir)) return;

	$self = $self_exclude ? basename(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']) : null;

	$handle = opendir($dir);
	if (!$handle) return;

	while (($file = readdir($handle)) !== false) {
		// Nur PHP-Dateien, keine Verzeichnisse oder versteckte Dateien
		if (substr($file, -4) === '.php' && $file !== $self) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_file($path) && is_readable($path)) {
				require_once $path;
			}
		}
	}
	closedir($handle);
}


// Zeigt alle Konstanten, die "CMX_" enthalten, in einer sortierbaren HTML-Tabelle an: cmx_show_consts();
function cmx_show_consts(): void {
	$consts = get_defined_constants(true)['user'] ?? [];

	$cmx_consts = array_filter($consts,fn($v, $k) => str_contains($k, 'CMX_'),ARRAY_FILTER_USE_BOTH);
	echo '<div style="font-family: monospace; background: #111; color: #0f0; padding: 16px; border-radius: 8px;">';
	if (empty($cmx_consts)) { echo '<p><strong>Keine CMX_-Konstanten gefunden.</strong></p></div>'; return; }
	krsort($cmx_consts);

	echo '<style>
		table.cmx-table { width:100%; border-collapse:collapse; color:#fff; }
		table.cmx-table th, table.cmx-table td { padding:6px 10px; border-bottom:1px solid #333; text-align:left; }
		table.cmx-table th { background:#222; color:#6ff; cursor:pointer; user-select:none; }
		table.cmx-table tr:hover td { background:#1a1a1a; }
		th.sorted-asc::after  { content:" ▲"; color:#6ff; }
		th.sorted-desc::after { content:" ▼"; color:#6ff; }
	</style>

	<script>
	document.addEventListener("DOMContentLoaded", function() {
		document.querySelectorAll(".cmx-table th").forEach(function(header, index) {
			header.addEventListener("click", function() {
				const table = header.closest("table");
				const rows = Array.from(table.querySelectorAll("tbody tr"));
				const asc = !header.classList.contains("sorted-asc");

				table.querySelectorAll("th").forEach(th => th.classList.remove("sorted-asc", "sorted-desc"));
				header.classList.add(asc ? "sorted-asc" : "sorted-desc");

				rows.sort((a, b) => {
					const A = a.children[index].textContent.trim().toLowerCase();
					const B = b.children[index].textContent.trim().toLowerCase();
					return asc ? A.localeCompare(B) : B.localeCompare(A);
				});

				const tbody = table.querySelector("tbody");
				tbody.innerHTML = "";
				rows.forEach(row => tbody.appendChild(row));
			});
		});
	});
	</script>';

	echo '<h3 style="color:#6ff;">📘 Gefundene CMX_-Konstanten (' . count($cmx_consts) . ')</h3>';
	echo '<table class="cmx-table">';
	echo '<thead><tr>
					<th>Konstante</th>
					<th>Wert</th>
				</tr></thead><tbody>';

	foreach ($cmx_consts as $name => $value) {
		if (is_array($value)) {
			$value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} elseif (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		} elseif ($value === null) {
			$value = 'null';
		} else {
			$value = htmlspecialchars((string)$value);
		}

		echo '<tr>
						<td style="color:#0f0;">' . esc_html($name) . '</td>
						<td>' . $value . '</td>
					</tr>';
	}

	echo '</tbody></table></div>';
	// exit;
}


function cmx_define_meta_constants(string $cpt, array|string $names): void {
	$cpt_upper = strtoupper($cpt);
	$cpt_lower = strtolower($cpt);

	// Wenn CSV-String übergeben → in Array umwandeln
	if (is_string($names)) {
		$names = array_map('trim', explode(',', $names));
	}

	foreach ($names as $name) {
		if ($name === '') continue;

		$upper = strtoupper($name);
		$lower = strtolower($name);

		$const_name  = __NAMESPACE__ . '\\CMX_' . $cpt_upper . '_META_' . $upper;
		$const_value = '_cmx_' . $cpt_lower . '_' . $lower;

		if (!defined($const_name)) {
			define($const_name, $const_value);
		}
	}
}


function cmx_no_umlaute(string $text): string {
	return strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue','Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue','ß' => 'ss',]);
}

if (!function_exists(__NAMESPACE__ . '\\cmx_export_slugify')) {
	function cmx_export_slugify(string $value, string $fallback = 'misbuero'): string {
		$value = \strtolower(\trim($value));
		$value = (string) \preg_replace('~[^a-z0-9_-]+~', '-', $value);
		$value = \trim($value, '-_');
		if ($value === '') {
			$value = \strtolower(\trim($fallback));
			$value = (string) \preg_replace('~[^a-z0-9_-]+~', '-', $value);
			$value = \trim($value, '-_');
		}
		return $value !== '' ? $value : 'misbuero';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_export_host_prefix')) {
	function cmx_export_host_prefix(): string {
		$host = \strtolower((string) \wp_parse_url(\home_url('/'), PHP_URL_HOST));
		if ($host === '') return '';

		$prefix = '';
		$suffix = '.misbuero.ch';
		if (\str_ends_with($host, $suffix)) {
			$left = \substr($host, 0, -\strlen($suffix));
			if ($left !== '') {
				$parts = \array_values(\array_filter(\explode('.', $left)));
				if (!empty($parts)) {
					$prefix = (string) \end($parts);
				}
			}
		}
		if ($prefix === '') {
			$parts = \array_values(\array_filter(\explode('.', $host)));
			$prefix = (string) ($parts[0] ?? '');
		}
		if (\in_array($prefix, ['www', 'localhost'], true)) {
			$prefix = '';
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
			? cmx_export_slugify($prefix, '')
			: $prefix;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_export_user_prefix')) {
	function cmx_export_user_prefix(): string {
		$user = \wp_get_current_user();
		if (!$user || !($user instanceof \WP_User) || !(int) $user->ID) {
			return '';
		}

		$raw = (string) ($user->user_login ?? '');
		if ($raw === '') $raw = (string) ($user->display_name ?? '');
		return \function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
			? cmx_export_slugify($raw, '')
			: $raw;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_export_actor_prefix')) {
	function cmx_export_actor_prefix(): string {
		$host_prefix = \function_exists(__NAMESPACE__ . '\\cmx_export_host_prefix')
			? cmx_export_host_prefix()
			: '';
		$user_prefix = \function_exists(__NAMESPACE__ . '\\cmx_export_user_prefix')
			? cmx_export_user_prefix()
			: '';

		if ($host_prefix !== '' && $user_prefix !== '' && $host_prefix !== $user_prefix) {
			return $host_prefix . '-' . $user_prefix;
		}
		if ($host_prefix !== '') return $host_prefix;
		if ($user_prefix !== '') return $user_prefix;
		return 'misbuero';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_export_now_stamp')) {
	function cmx_export_now_stamp(): string {
		if (\function_exists('wp_date')) {
			return (string) \wp_date('Ymd-His');
		}
		return (string) \date_i18n('Ymd-His');
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_export_filename')) {
	function cmx_export_filename(string $base, string $ext = 'csv'): string {
		$base = \function_exists(__NAMESPACE__ . '\\cmx_export_slugify')
			? cmx_export_slugify($base, 'export')
			: 'export';
		$ext = \strtolower(\trim($ext, ". \t\n\r\0\x0B"));
		if ($ext === '') $ext = 'dat';

		$prefix = \function_exists(__NAMESPACE__ . '\\cmx_export_actor_prefix')
			? cmx_export_actor_prefix()
			: 'misbuero';
		$stamp = \function_exists(__NAMESPACE__ . '\\cmx_export_now_stamp')
			? cmx_export_now_stamp()
			: (string) \gmdate('Ymd-His');

		return $prefix . '-' . $base . '-' . $stamp . '.' . $ext;
	}
}


/**
 * Holt Wert(e) aus globales.ini (Section + Key), case-insensitiv.
 * Einzelwert -> string, Mehrwerte (Komma) -> array, nicht gefunden -> null.
 */
function cmx_ini_get_value(string $section, string $key): string|array|null {
	$file = __DIR__ . '/globales.ini';
	if (!file_exists($file)) return null;

	$ini = \parse_ini_file($file, true, INI_SCANNER_TYPED);
	if ($ini === false) return null;

	// Section (case-insensitive) finden
	$sectionData = null;
	foreach ($ini as $secName => $data) {
		if (\is_array($data) && \strcasecmp($secName, $section) === 0) {
			$sectionData = $data;
			break;
		}
	}

	// Leere Section => Top-Level-Key
	if ($section === '') {
		foreach ($ini as $k => $v) {
			if (\strcasecmp($k, $key) === 0) return cmx_ini_cast_value($v);
		}
		return null;
	}

	if ($sectionData === null) return null;

	// Key (case-insensitive) finden
	foreach ($sectionData as $k => $v) {
		if (\strcasecmp($k, $key) === 0) return cmx_ini_cast_value($v);
	}

	return null;
}


function cmx_ini_cast_value(mixed $v): string|array|null {
	if (\is_string($v)) {
		$v = \trim($v);
		if ($v === '') return '';
		if (\strpos($v, ',') !== false) {
			$parts = \array_map('trim', \explode(',', $v));
			return \array_values(\array_filter($parts, 'strlen'));
		}
		return $v;
	}
	if (\is_array($v)) return $v;
	if (\is_scalar($v)) return (string) $v;
	return null;
}


if (!function_exists(__NAMESPACE__ . '\\cmx_parse_number')) {
	/**
	 * Parse number strings with comma or dot decimals and optional apostrophe thousand separators.
	 */
	function cmx_parse_number(mixed $value): float {
		if (is_int($value) || is_float($value)) return (float) $value;
		if (!is_scalar($value)) return 0.0;

		$s = trim((string) $value);
		if ($s === '') return 0.0;

		// Entferne normale/geschützte/schmale Leerzeichen und verschiedene Apostroph-Zeichen.
		$s = (string) preg_replace('/[\x{00A0}\x{202F}\s]+/u', '', $s);
		$s = str_replace(["'", "’", "‘", "`", "´", "′"], '', $s);
		$has_comma = strpos($s, ',') !== false;
		$has_dot = strpos($s, '.') !== false;

		if ($has_comma && $has_dot) {
			if (strrpos($s, ',') > strrpos($s, '.')) {
				$s = str_replace('.', '', $s);
				$s = str_replace(',', '.', $s);
			} else {
				$s = str_replace(',', '', $s);
			}
		} else {
			$s = str_replace(',', '.', $s);
		}

		return is_numeric($s) ? (float) $s : 0.0;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')) {
	/**
	 * Swiss number formatting using core PHP number_format.
	 * Example: 1234.5 => 1'234,50
	 */
	function cmx_format_swiss_number(mixed $value, int $decimals = 2): string {
		$decimals = max(0, $decimals);
		return number_format(cmx_parse_number($value), $decimals, '.', "'");
	}
}
