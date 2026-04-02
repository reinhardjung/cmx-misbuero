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

if (!function_exists(__NAMESPACE__ . '\\cmx_normalize_iban')) {
	function cmx_normalize_iban(string $iban): string {
		$iban = (string) \preg_replace('/[^A-Za-z0-9]/', '', $iban);
		return \strtoupper($iban);
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_is_valid_iban')) {
	function cmx_is_valid_iban(string $iban): bool {
		$iban = cmx_normalize_iban($iban);
		if ($iban === '' || !\preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
			return false;
		}

		$rearranged = \substr($iban, 4) . \substr($iban, 0, 4);
		$remainder = 0;

		for ($i = 0, $len = \strlen($rearranged); $i < $len; $i++) {
			$char = $rearranged[$i];
			$digits = \ctype_alpha($char) ? (string) (\ord($char) - 55) : $char;
			for ($j = 0, $digits_len = \strlen($digits); $j < $digits_len; $j++) {
				$remainder = (($remainder * 10) + (int) $digits[$j]) % 97;
			}
		}

		return $remainder === 1;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_is_valid_qr_iban')) {
	function cmx_is_valid_qr_iban(string $iban): bool {
		$iban = cmx_normalize_iban($iban);
		if (!\preg_match('/^(CH|LI)[0-9A-Z]+$/', $iban) || \strlen($iban) !== 21) {
			return false;
		}
		if (!cmx_is_valid_iban($iban)) {
			return false;
		}

		$iid = (int) \substr($iban, 4, 5);
		return $iid >= 30000 && $iid <= 31999;
	}
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_powered_by_enabled')) {
	function cmx_powered_by_enabled(): bool {
		$opts = (array) \get_option('cmx_einstellungen', []);
		return \array_key_exists('powered_by', $opts)
			? !empty($opts['powered_by'])
			: true;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_theme_presets')) {
	function cmx_email_theme_presets(): array {
		return [
			'rot' => [
				'label' => 'Rot',
				'header_background' => '#b53a30',
				'header_gradient_start' => '#a42c24',
				'header_gradient_end' => '#d84a3a',
				'header_text' => '#ffffff',
				'header_plain' => false,
				'header_border' => 'transparent',
				'button_background' => '#a42c24',
				'button_text' => '#ffffff',
				'button_mode' => 'button',
				'button_accent' => '#d84a3a',
				'link_color' => '#a42c24',
				'logo_badge_border' => '#efcfc7',
			],
			'blau' => [
				'label' => 'Blau',
				'header_background' => '#2b73a6',
				'header_gradient_start' => '#1f5f8e',
				'header_gradient_end' => '#4f97cc',
				'header_text' => '#ffffff',
				'header_plain' => false,
				'header_border' => 'transparent',
				'button_background' => '#1f5f8e',
				'button_text' => '#ffffff',
				'button_mode' => 'button',
				'button_accent' => '#4f97cc',
				'link_color' => '#1f5f8e',
				'logo_badge_border' => '#c8deee',
			],
			'grau' => [
				'label' => 'Grau',
				'header_background' => '#616b78',
				'header_gradient_start' => '#4c5562',
				'header_gradient_end' => '#8f99a7',
				'header_text' => '#ffffff',
				'header_plain' => false,
				'header_border' => 'transparent',
				'button_background' => '#4c5562',
				'button_text' => '#ffffff',
				'button_mode' => 'button',
				'button_accent' => '#8f99a7',
				'link_color' => '#4c5562',
				'logo_badge_border' => '#d7dde4',
			],
			'ohne' => [
				'label' => 'Ohne',
				'header_background' => '#ffffff',
				'header_gradient_start' => '',
				'header_gradient_end' => '',
				'header_text' => '#1f2933',
				'header_plain' => true,
				'header_border' => '#e5e7eb',
				'button_background' => '#1f2933',
				'button_text' => '#1f2933',
				'button_mode' => 'link',
				'button_accent' => '#d1d5db',
				'link_color' => '#1f2933',
				'logo_badge_border' => '#e5e7eb',
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_theme_sanitize')) {
	function cmx_email_theme_sanitize(string $key): string {
		$key = \sanitize_key($key);
		$themes = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_presets')
			? (array) cmx_email_theme_presets()
			: [];

		return isset($themes[$key]) ? $key : 'rot';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')) {
	function cmx_email_theme_palette(string $key = ''): array {
		if ($key === '') {
			$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
				? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
				: 'cmx_einstellungen';
			$options = (array) \get_option($option_name, []);
			$key = \is_scalar($options['email_theme'] ?? null)
				? (string) $options['email_theme']
				: 'rot';
		}

		$themes = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_presets')
			? (array) cmx_email_theme_presets()
			: [];
		$key = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_sanitize')
			? (string) cmx_email_theme_sanitize($key)
			: 'rot';

		return isset($themes[$key]) && \is_array($themes[$key])
			? $themes[$key]
			: (isset($themes['rot']) && \is_array($themes['rot']) ? $themes['rot'] : []);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_theme_button_mode')) {
	function cmx_email_theme_button_mode(): string {
		$theme = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')
			? (array) cmx_email_theme_palette()
			: [];
		$mode = (string) ($theme['button_mode'] ?? 'button');

		return $mode === 'link' ? 'link' : 'button';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_button_block_style')) {
	function cmx_email_button_block_style(string $button_style = 'margin:18px 0 24px 0;', string $link_style = 'margin:18px 0 24px 0;'): string {
		$mode = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_button_mode')
			? (string) cmx_email_theme_button_mode()
			: 'button';

		return $mode === 'link' ? $link_style : $button_style;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_button_outlook_gap_html')) {
	function cmx_email_button_outlook_gap_html(string $height = '16px'): string {
		$height = \trim($height);
		if ($height === '') {
			$height = '16px';
		}
		return '<!--[if mso]><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td height="' . \esc_attr($height) . '" style="height:' . \esc_attr($height) . ';line-height:' . \esc_attr($height) . ';font-size:0;">&nbsp;</td></tr></table><![endif]-->';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_header_logo_enabled')) {
	function cmx_email_header_logo_enabled(): bool {
		$option_name = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_MAIN')
			: 'cmx_einstellungen';
		$options = (array) \get_option($option_name, []);

		return empty($options['email_hide_logo']);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_header_logo_html')) {
	function cmx_email_header_logo_html(string $img_style = '', bool $prefer_outlook_embed = false): string {
		$enabled = \function_exists(__NAMESPACE__ . '\\cmx_email_header_logo_enabled')
			? cmx_email_header_logo_enabled()
			: true;
		if (!$enabled) {
			return '';
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_html')
			? (string) cmx_email_self_logo_html($img_style, $prefer_outlook_embed)
			: '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_id')) {
	function cmx_email_self_contact_id(): int {
		$query = new \WP_Query([
			'post_type'              => 'kontakte',
			'post_status'            => ['publish', 'private'],
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => [[
				'taxonomy' => 'kontakte_kategorien',
				'field'    => 'name',
				'terms'    => ['Das bin ich', 'Ich'],
			]],
		]);

		return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_contact_logo_url')) {
	function cmx_contact_logo_url(int $post_id): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kl_active_gallery_item')) {
			$meta_base = \function_exists(__NAMESPACE__ . '\\cmx_kl_meta_base')
				? (string) cmx_kl_meta_base()
				: '_cmx_local_image_kontakte';
			$active_item = cmx_kl_active_gallery_item($post_id, $meta_base);
			if (\is_array($active_item)) {
				$active_url = \trim((string) ($active_item['url'] ?? ''));
				if ($active_url !== '') {
					return $active_url;
				}
			}
		}

		$local_url = \trim((string) \get_post_meta($post_id, '_cmx_local_image_kontakte_url', true));
		if ($local_url !== '') {
			return $local_url;
		}

		$thumb_id = (int) \get_post_thumbnail_id($post_id);
		if ($thumb_id > 0) {
			$thumb_url = (string) \wp_get_attachment_image_url($thumb_id, 'full');
			if ($thumb_url !== '') {
				return $thumb_url;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_contact_homepage_url')) {
	function cmx_contact_homepage_url(int $post_id): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return '';
		}

		$meta_keys = [
			'_cmx_kontakte_url',
			'cmx_kontakte_meta_url',
			'cmx_kontakte_url',
			'_cmx_url',
			'cmx_url',
			'_website',
			'website',
			'url',
		];
		$url = '';

		foreach ($meta_keys as $meta_key) {
			$candidate = \trim((string) \get_post_meta($post_id, $meta_key, true));
			if ($candidate !== '') {
				$url = $candidate;
				break;
			}
		}

		if ($url === '') {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_url_for_href')) {
			$url = (string) cmx_normalize_url_for_href($url);
		} elseif (!\preg_match('~^https?://~i', $url)) {
			$url = 'https://' . \ltrim($url, '/');
		}

		return \esc_url_raw($url);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')) {
	function cmx_email_self_logo_url(): string {
		$post_id = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_id')
			? (int) cmx_email_self_contact_id()
			: 0;
		if ($post_id <= 0) {
			return '';
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_contact_logo_url')
			? (string) cmx_contact_logo_url($post_id)
			: '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_path')) {
	function cmx_email_self_logo_path(): string {
		$post_id = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_id')
			? (int) cmx_email_self_contact_id()
			: 0;
		if ($post_id <= 0) {
			return '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_kl_active_gallery_item')) {
			$meta_base = \function_exists(__NAMESPACE__ . '\\cmx_kl_meta_base')
				? (string) cmx_kl_meta_base()
				: '_cmx_local_image_kontakte';
			$active_item = cmx_kl_active_gallery_item($post_id, $meta_base);
			if (\is_array($active_item)) {
				$active_path = \trim((string) ($active_item['path'] ?? ''));
				if ($active_path !== '' && \is_readable($active_path)) {
					return $active_path;
				}
			}
		}

		$local_path = \trim((string) \get_post_meta($post_id, '_cmx_local_image_kontakte_path', true));
		if ($local_path !== '' && \is_readable($local_path)) {
			return $local_path;
		}

		$thumb_id = (int) \get_post_thumbnail_id($post_id);
		if ($thumb_id > 0) {
			$thumb_path = (string) \get_attached_file($thumb_id);
			if ($thumb_path !== '' && \is_readable($thumb_path)) {
				return $thumb_path;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_outlook_cid')) {
	function cmx_email_self_logo_outlook_cid(): string {
		return 'cmx-self-logo';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_inline_img_dimension_attributes')) {
	function cmx_email_inline_img_dimension_attributes(string $img_style = ''): string {
		$attrs = '';
		if (\preg_match('/(?:max-width|width)\s*:\s*(\d+)px/i', $img_style, $width_match)) {
			$attrs .= ' width="' . (int) $width_match[1] . '"';
		}
		if (\preg_match('/(?:max-height|height)\s*:\s*(\d+)px/i', $img_style, $height_match)) {
			$attrs .= ' height="' . (int) $height_match[1] . '"';
		}
		return $attrs;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_can_embed_for_outlook')) {
	function cmx_email_self_logo_can_embed_for_outlook(): bool {
		$path = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_path')
			? (string) cmx_email_self_logo_path()
			: '';
		if ($path === '') {
			return false;
		}

		$extension = \strtolower((string) \pathinfo($path, PATHINFO_EXTENSION));
		return \in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'bmp'], true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_embed_self_logo_for_phpmailer')) {
	function cmx_email_embed_self_logo_for_phpmailer($phpmailer): void {
		if (!$phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
			return;
		}
		if (
			!\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_can_embed_for_outlook')
			|| !cmx_email_self_logo_can_embed_for_outlook()
		) {
			return;
		}

		$logo_path = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_path')
			? (string) cmx_email_self_logo_path()
			: '';
		if ($logo_path === '' || !\is_readable($logo_path)) {
			return;
		}

		$filetype = \wp_check_filetype($logo_path);
		$mime = \trim((string) ($filetype['type'] ?? ''));
		if ($mime === '') {
			$mime = 'image/' . \strtolower((string) \pathinfo($logo_path, PATHINFO_EXTENSION));
		}

		$cid = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_outlook_cid')
			? (string) cmx_email_self_logo_outlook_cid()
			: 'cmx-self-logo';

		try {
			$phpmailer->addEmbeddedImage($logo_path, $cid, \basename($logo_path), 'base64', $mime);
		} catch (\Throwable $e) {
			return;
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')) {
	function cmx_email_self_contact_url(): string {
		$post_id = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_id')
			? (int) cmx_email_self_contact_id()
			: 0;
		if ($post_id <= 0) {
			return '';
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_contact_homepage_url')
			? (string) cmx_contact_homepage_url($post_id)
			: '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_header_content_html')) {
	function cmx_email_header_content_html(string $header_kicker_html, string $title_html, string $beleg_date_html, string $preheader_html, string $logo_html = ''): string {
		$theme = \function_exists(__NAMESPACE__ . '\\cmx_email_theme_palette')
			? (array) cmx_email_theme_palette()
			: [];
		$logo_badge_border = (string) ($theme['logo_badge_border'] ?? '#efcfc7');
		$header_text = (string) ($theme['header_text'] ?? '#ffffff');
		$text_html = '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;color:' . \esc_attr($header_text) . ';">' . $header_kicker_html . '</div>'
			. '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:26px;line-height:1.2;margin-top:6px;font-weight:600;color:' . \esc_attr($header_text) . ';">' . $title_html . '</div>'
			. ($beleg_date_html !== '' ? '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:13px;line-height:1.4;margin-top:4px;opacity:0.9;color:' . \esc_attr($header_text) . ';">vom ' . $beleg_date_html . '</div>' : '')
			. '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:12px;opacity:0.85;margin-top:4px;color:' . \esc_attr($header_text) . ';">' . $preheader_html . '</div>';

		if ($logo_html === '') {
			return $text_html;
		}

		$logo_badge_html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="right" style="border-collapse:separate;margin-top:8px;">'
			. '<tr>'
			. '<td style="padding:10px 12px;background:#ffffff;border:1px solid ' . \esc_attr($logo_badge_border) . ';border-radius:12px;text-align:center;">' . $logo_html . '</td>'
			. '</tr>'
			. '</table>';

		return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;border-collapse:collapse;">'
			. '<tr>'
			. '<td valign="top" style="padding:0 18px 0 0;">' . $text_html . '</td>'
			. '<td valign="top" align="right" style="width:188px;text-align:right;">' . $logo_badge_html . '</td>'
			. '</tr>'
			. '</table>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_html')) {
	function cmx_email_self_logo_html(string $img_style = '', bool $prefer_outlook_embed = false): string {
		$logo_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_url')
			? (string) cmx_email_self_logo_url()
			: '';
		if ($logo_url === '') {
			return '';
		}

		if ($img_style === '') {
			$img_style = 'display:block;max-width:180px;width:100%;height:auto;border:0;outline:none;text-decoration:none;';
		}

		$dimension_attrs = \function_exists(__NAMESPACE__ . '\\cmx_email_inline_img_dimension_attributes')
			? (string) cmx_email_inline_img_dimension_attributes($img_style)
			: '';
		$default_img_html = '<img src="' . \esc_url($logo_url) . '" alt="Das bin ich Logo"' . $dimension_attrs . ' style="' . \esc_attr($img_style) . '" border="0">';
		$img_html = $default_img_html;
		if (
			$prefer_outlook_embed
			&& \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_can_embed_for_outlook')
			&& cmx_email_self_logo_can_embed_for_outlook()
		) {
			$outlook_cid = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_outlook_cid')
				? (string) cmx_email_self_logo_outlook_cid()
				: 'cmx-self-logo';
			$outlook_img_html = '<!--[if mso]><img src="cid:' . \esc_attr($outlook_cid) . '" alt="Das bin ich Logo"' . $dimension_attrs . ' style="' . \esc_attr($img_style) . '" border="0"><![endif]-->';
			$img_html = $outlook_img_html . '<!--[if !mso]><!-->' . $default_img_html . '<!--<![endif]-->';
		}

		$link_url = \function_exists(__NAMESPACE__ . '\\cmx_email_self_contact_url')
			? (string) cmx_email_self_contact_url()
			: '';
		if ($link_url === '') {
			return $img_html;
		}

		return '<a href="' . \esc_url($link_url) . '" target="_blank" rel="noopener noreferrer" style="display:block;text-decoration:none;border:0;outline:none;">' . $img_html . '</a>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_block_html')) {
	function cmx_email_self_logo_block_html(string $table_style = 'margin:0 0 16px 0;', string $img_style = '', bool $prefer_outlook_embed = false): string {
		$logo_img = \function_exists(__NAMESPACE__ . '\\cmx_email_self_logo_html')
			? (string) cmx_email_self_logo_html($img_style, $prefer_outlook_embed)
			: '';
		if ($logo_img === '') {
			return '';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" style="' . \esc_attr($table_style) . '"><tr><td>' . $logo_img . '</td></tr></table>';
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
 * Holt Wert(e) aus globales.ini (Section + Key), case-/umlaut-insensitiv.
 * Einzelwert -> string, Mehrwerte (Komma) -> array, nicht gefunden -> null.
 */
function cmx_ini_get_value(string $section, string $key): string|array|null {
	$file = __DIR__ . '/globales.ini';
	if (!file_exists($file)) return null;

	$ini = \parse_ini_file($file, true, INI_SCANNER_TYPED);
	if ($ini === false) return null;

	$normalize = static function (string $value): string {
		$value = \trim($value);
		if ($value === '') return '';
		$value = \function_exists(__NAMESPACE__ . '\\cmx_no_umlaute') ? cmx_no_umlaute($value) : $value;
		return \strtolower($value);
	};
	$sectionNormalized = $normalize($section);
	$keyNormalized = $normalize($key);

	// Section (case-insensitive) finden
	$sectionData = null;
	foreach ($ini as $secName => $data) {
		if (\is_array($data) && (\strcasecmp($secName, $section) === 0 || $normalize((string) $secName) === $sectionNormalized)) {
			$sectionData = $data;
			break;
		}
	}

	// Leere Section => Top-Level-Key
	if ($section === '') {
		foreach ($ini as $k => $v) {
			if (\strcasecmp($k, $key) === 0 || $normalize((string) $k) === $keyNormalized) return cmx_ini_cast_value($v);
		}
		return null;
	}

	if ($sectionData === null) return null;

	// Key (case-insensitive) finden
	foreach ($sectionData as $k => $v) {
		if (\strcasecmp($k, $key) === 0 || $normalize((string) $k) === $keyNormalized) return cmx_ini_cast_value($v);
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
