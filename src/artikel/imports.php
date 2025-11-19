<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!defined(__NAMESPACE__.'\\CMX_PT_ARTIKEL')) {
	define(__NAMESPACE__.'\\CMX_PT_ARTIKEL', 'artikel');
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
		<p>Wähle Deine <code>CSV-Datei</code> aus. Achte darauf das Du bereits alle zugewiesenen Lieferanten hast!</p>

		<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_artikel_import'); ?>
			<input type="hidden" name="cmx_do_import" value="1">

			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_csv_file">CSV-Datei</label></th>
						<td><input type="file" id="cmx_csv_file" name="csv_file" accept=".csv" required></td>
					</tr>
					<!-- <tr>
						<th scope="row"><label for="cmx_update_mode">Update-Modus</label></th>
						<td>
							<label>
								<input type="checkbox" id="cmx_update_mode" name="update_mode" value="1" checked>
								Wenn <code>ID</code> vorhanden ist, bestehenden Artikel aktualisieren
							</label>
						</td>
					</tr> -->
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

	$imported = 0;
	$updated  = 0;

	while (($line = \fgetcsv($handle, 0, $sep)) !== false) {
		if (!array_filter($line)) continue;

		$row = @array_combine($header, $line);
		if (!$row) continue;

		$title = sanitize_text_field($row['post_title'] ?? '');
		if (!$title) continue;

		$postarr = [
			'post_type'   => CMX_PT_ARTIKEL,
			'post_title'  => $title,
			'post_name'   => sanitize_title($row['post_name'] ?? $title),
			'post_status' => $row['post_status'] ?? 'publish',
			'post_date'   => $row['post_date'] ?? current_time('mysql'),
		];

		$is_update = false;
		if ($update_mode && !empty($row['ID'])) {
			$postarr['ID'] = (int)$row['ID'];
			$is_update = true;
		}

		$post_id = wp_insert_post($postarr, true);
		if (is_wp_error($post_id)) continue;

		foreach ($row as $key => $val) {
			if (strpos($key, 'tax__') === 0) {
				$tax = substr($key, 5);
				if ($tax && $val) {
					$terms = array_map('trim', explode(',', $val));
					wp_set_object_terms($post_id, $terms, $tax, false);
				}
			}
			if (strpos($key, 'meta__') === 0) {
				update_post_meta($post_id, substr($key, 6), $val);
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
