<?php
/**
 * Plugin Name: CMX – Kontakte CSV-Import (neu, kompatibel zum Export)
 * Description: Robuster CSV-Import für CPT "kontakte". Unterstützt tax__*, meta__*, Kommunikationsspalten {gruppe}_{n}__typ / {gruppe}_{n}__wert → meta___cmx_kommunikation. Inklusive Import-Button in der Liste.
 * Version: 3.0.0
 * Author: CLOUDMEISTER
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/** =========================================================
 *  Konstanten
 * ========================================================= */
if (!defined(__NAMESPACE__.'\\CMX_PT')) {
	define(__NAMESPACE__.'\\CMX_PT', 'kontakte');
}
if (!defined(__NAMESPACE__.'\\CMX_COMM_META_KEY')) {
	define(__NAMESPACE__.'\\CMX_COMM_META_KEY', 'meta___cmx_kommunikation');
}

/** =========================================================
 *  Admin-Menü: Unterseite "Import" unter Kontakte
 * ========================================================= */
\add_action('admin_menu', __NAMESPACE__.'\\cmx_kontakte_import_menu');
function cmx_kontakte_import_menu(): void {
	add_submenu_page(
		'edit.php?post_type='.CMX_PT,
		'Kontakte CSV-Import',
		'Import',
		'edit_posts',
		'cmx-kontakte-import',
		__NAMESPACE__.'\\cmx_render_import_page'
	);
}

/** =========================================================
 *  Sichtbarer Import-Button (ohne JS) in der Liste
 * ========================================================= */
\add_action('restrict_manage_posts', function($post_type) {
	if ($post_type !== CMX_PT) return;
	if (!current_user_can('edit_posts')) return;
	$url = \admin_url('edit.php?post_type='.CMX_PT.'&page=cmx-kontakte-import');
	echo '<a href="'.esc_url($url).'" class="button button-primary" style="margin-left:6px;">Import</a>';
});
\add_filter('views_edit-'.CMX_PT, function(array $views) {
	if (!current_user_can('edit_posts')) return $views;
	$url = \admin_url('edit.php?post_type='.CMX_PT.'&page=cmx-kontakte-import');
	$views['cmx_import'] = '<a href="'.esc_url($url).'">Import</a>';
	return $views;
});
\add_action('admin_bar_menu', function(\WP_Admin_Bar $bar){
	if (!current_user_can('edit_posts')) return;
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if ($screen && $screen->id !== 'edit-'.CMX_PT) return;
	$bar->add_node([
		'id'    => 'cmx_import_kontakte',
		'title' => 'Kontakte: Import',
		'href'  => \admin_url('edit.php?post_type='.CMX_PT.'&page=cmx-kontakte-import'),
	]);
}, 100);

/** =========================================================
 *  Import-Seite
 * ========================================================= */
function cmx_render_import_page(): void {
	if (!current_user_can('edit_posts')) wp_die('Insufficient permissions.');
	$action = admin_url('edit.php?post_type='.CMX_PT.'&page=cmx-kontakte-import');

	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
		&& isset($_POST['_wpnonce'])
		&& wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'cmx_import_csv')) {
		cmx_handle_csv_upload_and_import();
	}

	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Kontakte CSV-Import</h1>
		<hr class="wp-header-end" />
		<form method="post" action="<?php echo esc_url($action); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field('cmx_import_csv'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cmx_csv">CSV-Datei</label></th>
					<td>
						<input type="file" id="cmx_csv" name="cmx_csv" accept=".csv,text/csv,application/vnd.ms-excel" required />
						<p class="description">Kompatibel mit CMX-Export (Semikolon, UTF-8). Delimiter wird automatisch erkannt (Default „;“).</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Update-Strategie</th>
					<td>
						<label><input type="checkbox" name="cmx_update_by_id" value="1" checked /> Per <strong>ID</strong> aktualisieren (falls vorhanden)</label><br/>
						<label><input type="checkbox" name="cmx_update_by_title" value="1" /> Falls ID fehlt: per <strong>post_title</strong> aktualisieren</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Taxonomie-Erstellung</th>
					<td>
						<label><input type="checkbox" name="cmx_create_terms" value="1" checked /> Fehlende Begriffe automatisch anlegen</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Dry-Run</th>
					<td>
						<label><input type="checkbox" name="cmx_dry_run" value="1" /> Nur prüfen (keine Änderungen speichern)</label>
					</td>
				</tr>
			</table>
			<?php submit_button('Import starten'); ?>
		</form>
	</div>
	<?php
}

/** =========================================================
 *  Upload + Import-Trigger
 * ========================================================= */
function cmx_handle_csv_upload_and_import(): void {
	if (empty($_FILES['cmx_csv']['tmp_name'])) {
		cmx_admin_notice('Keine Datei übergeben.', 'error'); return;
	}
	$overrides = [
		'test_form' => false,
		'mimes'     => [
			'csv' => 'text/csv',
			'txt' => 'text/plain',
			// Excel „falscher“ MIME:
			'xls' => 'application/vnd.ms-excel'
		]
	];
	$file = wp_handle_upload($_FILES['cmx_csv'], $overrides);
	if (!empty($file['error'])) {
		cmx_admin_notice('Upload-Fehler: '.$file['error'], 'error'); return;
	}

	$path = $file['file'];
	$res  = cmx_import_csv_file($path, [
		'update_by_id'    => !empty($_POST['cmx_update_by_id']),
		'update_by_title' => !empty($_POST['cmx_update_by_title']),
		'create_terms'    => !empty($_POST['cmx_create_terms']),
		'dry_run'         => !empty($_POST['cmx_dry_run']),
	]);

	@unlink($path);

	$msg = sprintf('Import fertig. Zeilen: %d | erstellt: %d | aktualisiert: %d | übersprungen: %d | Fehler: %d.',
		intval($res['rows']), intval($res['created']), intval($res['updated']), intval($res['skipped']), intval($res['errors']));
	cmx_admin_notice($msg, $res['errors'] ? 'warning' : 'success');

	if (!empty($res['log'])) {
		echo '<div class="notice notice-info"><p><strong>Details:</strong></p><textarea rows="14" style="width:100%;font-family:monospace;">'
			.esc_textarea(implode("\n", $res['log']))
			.'</textarea></div>';
	}
}

/** =========================================================
 *  Kern: CSV importieren (kompatibel zum Export)
 * ========================================================= */
function cmx_import_csv_file(string $path, array $opts): array {
	$defaults = [
		'update_by_id'    => true,
		'update_by_title' => false,
		'create_terms'    => true,
		'dry_run'         => false,
	];
	$opts = array_merge($defaults, $opts);

	$raw = @file_get_contents($path);
	if ($raw === false || $raw === '') {
		return ['rows'=>0,'created'=>0,'updated'=>0,'skipped'=>0,'errors'=>1,'log'=>['Datei leer oder nicht lesbar.']];
	}

	// BOM entfernen + UTF-8 erzwingen
	$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
	if (!mb_check_encoding($raw, 'UTF-8')) { $raw = mb_convert_encoding($raw, 'UTF-8'); }

	// Delimiter auto-detect (erste nicht-leere Zeile), Default ";"
	$lines = preg_split('/\r\n|\r|\n/', $raw);
	$firstLine = '';
	foreach ($lines as $ln) { if (trim($ln) !== '') { $firstLine = $ln; break; } }
	$cands = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t"), '|' => substr_count($firstLine, '|')];
	arsort($cands);
	$delimiter = key($cands);
	if (empty($cands[$delimiter])) $delimiter = ';';

	// In temp stream für fgetcsv
	$fh = fopen('php://temp', 'w+');
	fwrite($fh, $raw);
	rewind($fh);

	// Header lesen & normalisieren
	$header = fgetcsv($fh, 0, $delimiter, '"', '\\');
	if ($header === false) {
		fclose($fh);
		return ['rows'=>0,'created'=>0,'updated'=>0,'skipped'=>0,'errors'=>1,'log'=>['Keine Kopfzeile gefunden.']];
	}
	$normHeader = array_map(function($h){
		$h = trim((string)$h);
		$h = preg_replace('/\s+/', '_', $h);
		return strtolower($h);
	}, $header);

	$stats = ['rows'=>0,'created'=>0,'updated'=>0,'skipped'=>0,'errors'=>0,'log'=>[]];

	// tax__*-Spalten anhand Header erkennen (unabhängig davon, ob Taxonomie registriert ist)
	$tax_cols = [];
	foreach ($normHeader as $i => $col) {
		if (strpos($col, 'tax__') === 0) {
			$tax_cols[$i] = substr($col, 5); // taxonomy name
		}
	}

	while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
		$stats['rows']++;

		// Align
		$colCount = count($normHeader);
		if (count($row) < $colCount) $row = array_pad($row, $colCount, '');
		if (count($row) > $colCount) $row = array_slice($row, 0, $colCount);
		$row = array_map('trim', $row);

		$data = array_combine($normHeader, $row);
		if ($data === false) {
			$stats['errors']++; $stats['log'][] = "Zeile {$stats['rows']}: Header/Datenspalten passen nicht.";
			continue;
		}

		// Kernfelder (wie im Export)
		$id          = isset($data['id']) ? (int)$data['id'] : 0;
		$post_title  = (string)($data['post_title'] ?? '');
		$post_status = (string)($data['post_status'] ?? 'publish');
		$post_name   = (string)($data['post_name'] ?? '');
		$post_date   = (string)($data['post_date'] ?? '');

		$post_arr = ['post_type'=>CMX_PT, 'post_status'=> $post_status ?: 'publish'];
		if ($post_title !== '') $post_arr['post_title'] = $post_title;
		if ($post_name   !== '') $post_arr['post_name']  = sanitize_title($post_name);
		if ($post_date   !== '') $post_arr['post_date']  = $post_date;

		// Ziel ermitteln
		$target_id = 0;
		if ($opts['update_by_id'] && $id > 0 && get_post_type($id) === CMX_PT) {
			$target_id = $id;
		} elseif ($opts['update_by_title'] && $post_title !== '') {
			$found = get_page_by_title($post_title, OBJECT, CMX_PT);
			if ($found) $target_id = (int)$found->ID;
		}
		$is_update = $target_id > 0;

		// Insert/Update
		if ($opts['dry_run']) {
			$stats['log'][] = sprintf('Zeile %d: %s "%s"%s', $stats['rows'], $is_update?'UPDATE':'CREATE', $post_title, $is_update?" (ID $target_id)":'');
			if (!$is_update) $target_id = -$stats['rows']; // simulierte ID
		} else {
			if ($is_update) {
				$post_arr['ID'] = $target_id;
				$target_id = wp_update_post($post_arr, true);
				if (is_wp_error($target_id)) { $stats['errors']++; $stats['log'][] = "Zeile {$stats['rows']}: Update-Fehler – ".$target_id->get_error_message(); continue; }
				$stats['updated']++;
			} else {
				$target_id = wp_insert_post($post_arr, true);
				if (is_wp_error($target_id)) { $stats['errors']++; $stats['log'][] = "Zeile {$stats['rows']}: Insert-Fehler – ".$target_id->get_error_message(); continue; }
				$stats['created']++;
			}
		}


		// Taxonomien setzen (aus den tax__*-Spalten)
		foreach ($tax_cols as $idx => $taxonomy) {
			$col_key = 'tax__'.$taxonomy;
			$val     = (string)($data[$col_key] ?? '');
			if ($val === '') continue;
			$names = array_filter(array_map('trim', explode('|', $val)), 'strlen');

			if ($opts['dry_run']) {
				$stats['log'][] = sprintf('Zeile %d: Tax %s = [%s]', $stats['rows'], $taxonomy, implode(', ', $names));
			} else {
				if ($opts['create_terms']) {
					foreach ($names as $name) { if (!term_exists($name, $taxonomy)) wp_insert_term($name, $taxonomy); }
				}
				wp_set_object_terms($target_id, $names, $taxonomy, false);
			}
		}

		// Kommunikation aus Spalten {gruppe}_{n}__typ / {gruppe}_{n}__wert
		$kbox = []; // ['Label'=>[['typ'=>'..','wert'=>'..'],...]]
		foreach ($data as $col => $val) {
			if (!preg_match('/^([a-z0-9_]+)_(\d+)__(typ|wert)$/', $col, $m)) continue;
			$group_slug = $m[1]; $idx = (int)$m[2]; $kind = $m[3];
			$label = cmx_unslug_for_import($group_slug); // aus slug wieder Label
			if (!isset($kbox[$label])) $kbox[$label] = [];
			if (!isset($kbox[$label][$idx-1])) $kbox[$label][$idx-1] = ['typ'=>'','wert'=>''];
			$kbox[$label][$idx-1][$kind] = (string)$val;
		}
		// leere Einträge filtern
		foreach ($kbox as $label => $items) {
			$clean = [];
			foreach ($items as $it) {
				$typ  = trim((string)($it['typ']  ?? ''));
				$wert = trim((string)($it['wert'] ?? ''));
				if ($typ === '' && $wert === '') continue;
				$clean[] = ['typ'=>$typ, 'wert'=>$wert];
			}
			$kbox[$label] = $clean;
			if ($opts['dry_run']) $stats['log'][] = sprintf('Zeile %d: KBOX %s → %d', $stats['rows'], $label, count($clean));
		}
		// speichern (einmal)
		if (!$opts['dry_run']) {
			$has_items = false; foreach ($kbox as $items) if (!empty($items)) { $has_items = true; break; }
			if ($has_items) {
				update_post_meta($target_id, CMX_COMM_META_KEY, $kbox);
			} else {
				// Wenn in der CSV überhaupt keine Kommunikationsspalten vorhanden waren, NICHT löschen.
				// Nur löschen, wenn explizit alle Spalten vorhanden aber leer?
				// => hier bewusst: nichts tun.
			}
		}

		// meta__* Felder übernehmen (1:1)
		foreach ($data as $col => $val) {
			if (strpos($col, 'meta__') !== 0) continue;
			$meta_key = substr($col, 6);
			if ($meta_key === '') continue;
			if ($opts['dry_run']) {
				$stats['log'][] = sprintf('Zeile %d: META %s = %s', $stats['rows'], $meta_key, (string)$val);
			} else {
				update_post_meta($target_id, $meta_key, $val);
			}
		}
	}

	fclose($fh);
	return $stats;
}

/** =========================================================
 *  Helfer
 * ========================================================= */
function cmx_admin_notice(string $msg, string $type = 'info'): void {
	printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($type), wp_kses_post($msg));
}

/** Slug "mail_schreiben" → Label "Mail Schreiben" (wie Export-Header entstanden sind) */
function cmx_unslug_for_import(string $slug): string {
	$label = str_replace('_', ' ', strtolower($slug));
	return function_exists('mb_convert_case') ? mb_convert_case($label, MB_CASE_TITLE, 'UTF-8') : ucwords($label);
}
