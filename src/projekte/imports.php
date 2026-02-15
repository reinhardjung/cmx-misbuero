<?php
/**
 * Projekte-Import (Listen-Ansicht) – analog zu "Artikel"-Import
 * - Fügt "importieren"-Link in der Projekte-Liste ein
 * - Zeigt Inline-Formular (CSV-Upload) bei ?cmx_import=1
 * - Importiert/aktualisiert Projekte aus CSV (UTF-8; Trenner: ;)
 * - Unterstützt Spalten: post_title (Pflicht), post_name, post_status, post_date
 * - Optionaler Update-Modus aktualisiert nach gleichem Projektnamen (Titel)
 * - meta__* für Metafelder, tax__* für Taxonomien (Komma-getrennt)
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__.'\\CMX_PT_PROJEKTE')) {
	define(__NAMESPACE__.'\\CMX_PT_PROJEKTE', 'projekte');
}

function cmx_import_find_existing_projekt_id_by_title(string $title): int {
	global $wpdb;
	$title = \trim($title);
	if ($title === '') return 0;
	$sql = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
		CMX_PT_PROJEKTE,
		$title
	);
	$id = (int) $wpdb->get_var($sql);
	return $id > 0 ? $id : 0;
}

/**
 * 1) Import-Link oben in der Projekte-Liste
 */
\add_filter('views_edit-' . CMX_PT_PROJEKTE, function(array $views){
	if (!\current_user_can('edit_posts')) return $views;

	$url = \add_query_arg(['cmx_import' => 1]);
	$views['cmx_import_projekte'] = '<a href="' . esc_url($url) . '">importieren</a>';
	return $views;
});

/**
 * 2) Inline-Formular (bei ?cmx_import=1)
 */
\add_action('all_admin_notices', function () {
	global $typenow;
	if ($typenow !== CMX_PT_PROJEKTE || empty($_GET['cmx_import'])) return;
	if (!\current_user_can('edit_posts')) return;
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Projekte Import</h2>
		<p>Wähle Deine Mis Büro <code>CSV-Datei</code> aus.</p>
		<!-- <p>Wähle Deine <code>CSV-Datei</code> aus. Spalten-Beispiele: <code>post_title</code>, <code>post_status</code>, <code>post_date</code>, <code>meta__foo</code>, <code>tax__projekt_kategorie</code>.</p> -->

		<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_projekte_import'); ?>
			<input type="hidden" name="cmx_do_import" value="1">

				<table class="form-table" role="presentation" style="margin-top:1em;">
					<tbody>
						<tr>
							<th scope="row"><label for="cmx_update_mode">Existierende überschreiben?</label></th>
							<td>
								<label>
									<input type="checkbox" id="cmx_update_mode" name="update_mode" value="1">
									Ja, Projekte mit gleichem Namen aktualisieren
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
				<a href="<?php echo esc_url(admin_url('edit.php?post_type=' . CMX_PT_PROJEKTE)); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

/**
 * 3) Import ausführen (beim Laden der Projekte-Liste)
 */
\add_action('load-edit.php', function(){
	global $typenow;
	if ($typenow !== CMX_PT_PROJEKTE) return;
	if (empty($_POST['cmx_do_import']) || !\check_admin_referer('cmx_projekte_import')) return;

	if (empty($_FILES['csv_file']['tmp_name'])) {
		\add_action('admin_notices', function(){
			echo '<div class="notice notice-error"><p>Keine Datei ausgewählt.</p></div>';
		});
		return;
	}

	$file        = $_FILES['csv_file']['tmp_name'];
	$sep         = ';';
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

		$title_raw = \trim((string)($row['post_title'] ?? ($row['Titel'] ?? '')));
		$title = sanitize_text_field($title_raw);
		if (!$title) continue;

		$postarr = [
			'post_type'   => CMX_PT_PROJEKTE,
			'post_title'  => $title,
			'post_name'   => sanitize_title(($row['post_slug'] ?? $row['post_name'] ?? $title)),
			'post_status' => $row['post_status'] ?? 'publish',
			'post_date'   => $row['post_date'] ?? current_time('mysql'),
		];

		$is_update = false;
		if ($update_mode) {
			$existing_id = cmx_import_find_existing_projekt_id_by_title($title);
			if ($existing_id > 0) {
				$postarr['ID'] = $existing_id;
				$is_update = true;
			}
		}

		$post_id = wp_insert_post($postarr, true);
		if (is_wp_error($post_id)) continue;

		// Taxonomien & Metas
		foreach ($row as $key => $val) {
			if (strpos($key, 'tax__') === 0) {
				$tax = substr($key, 5);
				if ($tax) {
					$raw_terms = trim((string) $val);
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
	\fclose($handle);

	\set_transient('cmx_import_notice_projekte', [
		'imported' => $imported,
		'updated'  => $updated,
	], 30);

	\wp_safe_redirect(admin_url('edit.php?post_type=' . CMX_PT_PROJEKTE));
	exit;
});

/**
 * 4) Erfolgsmeldung nach Redirect
 */
\add_action('admin_notices', function () {
	global $typenow;
	if ($typenow !== CMX_PT_PROJEKTE) return;

	$notice = \get_transient('cmx_import_notice_projekte');
	if (!$notice) return;
	\delete_transient('cmx_import_notice_projekte');

	echo '<div class="notice notice-success is-dismissible"><p><strong>Import abgeschlossen:</strong> ' .
		intval($notice['imported']) . ' neu, ' . intval($notice['updated']) . ' aktualisiert.</p></div>';
});
