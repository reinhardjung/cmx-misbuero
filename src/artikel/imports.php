<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!defined(__NAMESPACE__.'\\CMX_PT_ARTIKEL')) {
	define(__NAMESPACE__.'\\CMX_PT_ARTIKEL', 'artikel');
}

function cmx_import_find_existing_artikel_id_by_title(string $title): int {
	global $wpdb;
	$title = \trim($title);
	if ($title === '') return 0;
	$sql = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
		CMX_PT_ARTIKEL,
		$title
	);
	$id = (int) $wpdb->get_var($sql);
	return $id > 0 ? $id : 0;
}

function cmx_import_clear_lieferanten_meta(int $post_id): void {
	foreach (\array_keys((array)\get_post_meta($post_id)) as $meta_key) {
		if (\preg_match('/^_cmx_art_lieferant_\d+_(id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand)$/', (string)$meta_key)) {
			\delete_post_meta($post_id, $meta_key);
		}
	}
	\delete_post_meta($post_id, '_cmx_art_lieferanten_count');
	\delete_post_meta($post_id, '_cmx_art_lieferanten_liste');
}

/**
 * 1. Import-Link oben in der Liste einfügen
 */
\add_filter('views_edit-' . CMX_PT_ARTIKEL, function(array $views){
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	$url = \add_query_arg(['cmx_import' => 1]);
	$views['cmx_import_artikel'] = '<a href="' . esc_url($url) . '">importieren</a>';

	return $views;
});

/**
 * 2. Inline-Formular oberhalb der Tabelle einblenden (wenn ?cmx_import=1)
 */
\add_action('all_admin_notices', function() {
	global $typenow;
	if ($typenow !== CMX_PT_ARTIKEL || empty($_GET['cmx_import'])) {
		return;
	}

	if (!\current_user_can('edit_posts')) {
		return;
	}

	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Artikel Import</h2>
		<p>Wähle Deine Mis Büro <code>CSV-Datei</code> aus, welche Du zuvor exportiert hast. Achte darauf das Du bereits alle zugewiesenen Lieferanten hast!</p>
		<p><code>DEMO-Daten</code> <a href="https://misbuero.ch/wp-content/uploads/demo_artikel.csv">https://misbuero.ch/wp-content/uploads/demo_artikel.csv</a> runterladen (danach diese CSV-Datei auswählen zum importieren)</p>

<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_artikel_import'); ?>
			<input type="hidden" name="cmx_do_import" value="1">

			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_update_mode">Existierende überschreiben?</label></th>
						<td>
							<label>
								<input type="checkbox" id="cmx_update_mode" name="update_mode" value="1">
								Ja, Artikel mit gleichem Namen aktualisieren
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx_csv_file">CSV-Datei</label></th>
						<td><input type="file" id="cmx_csv_file" name="csv_file" accept=".csv" required></td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">Import starten</button>
				<a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CMX_PT_ARTIKEL)); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

/**
 * 3. Import ausführen und nach Erfolg zurück zur Artikelliste leiten
 */
\add_action('load-edit.php', function() {
	global $typenow;
	if ($typenow !== CMX_PT_ARTIKEL) return;

	if (empty($_POST['cmx_do_import']) || !\check_admin_referer('cmx_artikel_import')) return;

	if (empty($_FILES['csv_file']['tmp_name'])) {
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Keine Datei ausgewählt.</p></div>';
		});
		return;
	}

	$file = $_FILES['csv_file']['tmp_name'];
	$sep  = ';';
	$update_mode = !empty($_POST['update_mode']);

	$handle = @\fopen($file, 'r');
	if (!$handle) {
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Datei konnte nicht gelesen werden.</p></div>';
		});
		return;
	}

	$header = \fgetcsv($handle, 0, $sep);
	if (!$header) {
		\fclose($handle);
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Leere oder ungültige CSV.</p></div>';
		});
		return;
	}
	$header = array_map('trim', $header);
	$has_lieferanten_meta_in_csv = false;
	foreach ($header as $h) {
		if (\preg_match('/^meta__(?:_cmx_art_lieferant_\d+_(?:id|nr|ek|bezugsquelle|lieferzeit_tage|lagerbestand)|_cmx_art_lieferanten_count|_cmx_art_lieferanten_liste)$/', (string)$h)) {
			$has_lieferanten_meta_in_csv = true;
			break;
		}
	}

	$imported = 0;
	$updated  = 0;

	while (($line = \fgetcsv($handle, 0, $sep)) !== false) {
		if (!array_filter($line)) continue;

		$row = @array_combine($header, $line);
		if (!$row) continue;

		$title_raw = \trim((string)($row['post_title'] ?? ''));
		if ($title_raw === '') continue;
		$title = \sanitize_text_field($title_raw);
		if ($title === '') continue;

		$postarr = [
			'post_type'   => CMX_PT_ARTIKEL,
			'post_title'  => $title,
			'post_name'   => sanitize_title(($row['post_slug'] ?? $row['post_name'] ?? $title)),
			'post_status' => $row['post_status'] ?? 'publish',
			'post_date'   => $row['post_date'] ?? current_time('mysql'),
		];

		$is_update = false;
		if ($update_mode) {
			$existing_id = cmx_import_find_existing_artikel_id_by_title($title);
			if ($existing_id > 0) {
				$postarr['ID'] = $existing_id;
				$is_update = true;
			}
		}

		$post_id = wp_insert_post($postarr, true);
		if (is_wp_error($post_id)) continue;

		if ($is_update && $has_lieferanten_meta_in_csv) {
			cmx_import_clear_lieferanten_meta((int)$post_id);
		}

		foreach ($row as $key => $val) {
			if (strpos($key, 'tax__') === 0) {
				$tax = substr($key, 5);
				if ($tax) {
					$raw_terms = \trim((string)$val);
					if ($raw_terms !== '') {
						$terms = array_map('trim', explode(',', $raw_terms));
						wp_set_object_terms($post_id, $terms, $tax, false);
					} elseif ($is_update) {
						wp_set_object_terms($post_id, [], $tax, false);
					}
				}
			}
			if (strpos($key, 'meta__') === 0) {
				$meta_key = substr($key, 6);
				$meta_val = is_string($val) ? trim($val) : $val;
				if ($meta_val === '') {
					delete_post_meta($post_id, $meta_key);
				} else {
					update_post_meta($post_id, $meta_key, $meta_val);
				}
			}
		}

		if ($is_update) $updated++; else $imported++;
	}

	fclose($handle);

	// Ergebnis speichern, um nach Redirect anzuzeigen
	\set_transient('cmx_import_notice_artikel', [
		'imported' => $imported,
		'updated'  => $updated,
	], 30);

	// Nach Erfolg zur Artikelliste zurück
	\wp_safe_redirect(admin_url('edit.php?post_type=' . CMX_PT_ARTIKEL));
	exit;
});

/**
 * 4. Notice nach Redirect anzeigen
 */
\add_action('admin_notices', function() {
	global $typenow;
	if ($typenow !== CMX_PT_ARTIKEL) return;

	$notice = \get_transient('cmx_import_notice_artikel');
	if (!$notice) return;

	\delete_transient('cmx_import_notice_artikel');

	echo '<div class="notice notice-success is-dismissible"><p><strong>Import abgeschlossen:</strong> ' .
		intval($notice['imported']) . ' neu, ' . intval($notice['updated']) . ' aktualisiert.</p></div>';
});
