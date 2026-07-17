<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_PT_BUDGET')) {
	\define(__NAMESPACE__ . '\\CMX_PT_BUDGET', 'budget');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_notice_key')) {
	function cmx_budget_import_notice_key(): string {
		return 'cmx_import_notice_budget';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_insert_view_after')) {
	function cmx_budget_import_insert_view_after(array $views, string $after_key, string $new_key, string $html): array {
		$out = [];
		$inserted = false;

		foreach ($views as $key => $value) {
			$out[$key] = $value;
			if ($key === $after_key) {
				$out[$new_key] = $html;
				$inserted = true;
			}
		}

		if (!$inserted) {
			$out[$new_key] = $html;
		}

		return $out;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_taxonomy')) {
	function cmx_budget_import_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_budget_admin_taxonomy')) {
			return (string) cmx_budget_admin_taxonomy();
		}
		if (\defined(__NAMESPACE__ . '\\CMX_TAX_BUDGET')) {
			return (string) \constant(__NAMESPACE__ . '\\CMX_TAX_BUDGET');
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_tax_key')) {
			return (string) cmx_tax_key('budget', 'Kategorien');
		}
		return 'budget_kategorien';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_find_existing_id_by_title')) {
	function cmx_budget_import_find_existing_id_by_title(string $title): int {
		global $wpdb;
		$title = \trim($title);
		if ($title === '') {
			return 0;
		}

		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
			CMX_PT_BUDGET,
			$title
		);

		$id = (int) $wpdb->get_var($sql);
		return $id > 0 ? $id : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_find_contact_id_by_title')) {
	function cmx_budget_import_find_contact_id_by_title(string $title): int {
		global $wpdb;

		$title = \trim($title);
		if ($title === '') {
			return 0;
		}

		$post_types = \function_exists(__NAMESPACE__ . '\\cmx_budget_kontakt_post_types')
			? (array) cmx_budget_kontakt_post_types()
			: ['kontakte', 'kontakt', 'contact'];

		foreach ($post_types as $post_type) {
			if (!\post_type_exists($post_type)) {
				continue;
			}

			$sql = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1",
				$post_type,
				$title
			);
			$id = (int) $wpdb->get_var($sql);
			if ($id > 0) {
				return $id;
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_redirect_notice')) {
	function cmx_budget_import_redirect_notice(array $notice): void {
		\update_user_meta(\get_current_user_id(), cmx_budget_import_notice_key(), $notice);
		\wp_safe_redirect(\add_query_arg([
			'post_type'                => CMX_PT_BUDGET,
			'cmx_import_notice_budget' => 1,
		], \admin_url('edit.php')));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_notice_lines')) {
	function cmx_budget_import_notice_lines(array $notice): array {
		$lines = ['<strong>Budget Import:</strong>'];
		foreach ([
			'imported' => 'Importiert',
			'updated'  => 'Aktualisiert',
			'skipped'  => 'Übersprungen',
			'failed'   => 'Fehlgeschlagen',
		] as $key => $label) {
			$items = \array_values(\array_filter(\array_map('trim', (array) ($notice[$key] ?? []))));
			$lines[] = '<strong>' . \esc_html($label . ' (' . \count($items) . '):') . '</strong> ' . ($items === [] ? '-' : \esc_html(\implode(', ', $items)));
		}
		return $lines;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_budget_import_header_is_valid')) {
	function cmx_budget_import_header_is_valid(array $header): bool {
		$normalized = [];
		foreach ($header as $column) {
			$key = \function_exists('\\mb_strtolower')
				? (string) \mb_strtolower(\trim((string) $column), 'UTF-8')
				: (string) \strtolower(\trim((string) $column));
			if ($key !== '') {
				$normalized[$key] = true;
			}
		}

		foreach (['post_title', 'kategorien', 'kontakt', 'kosten_typ', 'betrag', 'anteil', 'zahlbar_pro'] as $required) {
			if (!isset($normalized[$required])) {
				return false;
			}
		}

		return true;
	}
}

\add_filter('views_edit-' . CMX_PT_BUDGET, function (array $views): array {
	if (!\current_user_can('edit_posts')) {
		return $views;
	}

	$url = \add_query_arg([
		'post_type'          => CMX_PT_BUDGET,
		'cmx_import_budget'  => 1,
	], \admin_url('edit.php'));

	$link = '<a href="' . \esc_url($url) . '">importieren</a>';
	return cmx_budget_import_insert_view_after($views, 'all', 'cmx_import_budget', $link);
}, 20);

\add_action('all_admin_notices', function (): void {
	global $typenow;
	if ($typenow !== CMX_PT_BUDGET || empty($_GET['cmx_import_budget'])) {
		return;
	}
	if (!\current_user_can('edit_posts')) {
		return;
	}
	?>
	<div class="notice notice-info" style="padding:20px;margin-top:15px;">
		<h2>Budget Import</h2>
		<p>Wähle eine Budget-CSV-Datei aus dem Export.</p>
		<form method="post" enctype="multipart/form-data" action="">
			<?php \wp_nonce_field('cmx_budget_import'); ?>
			<input type="hidden" name="cmx_do_import_budget" value="1">
			<table class="form-table" role="presentation" style="margin-top:1em;">
				<tbody>
					<tr>
						<th scope="row"><label for="cmx_budget_update_mode">Existierende überschreiben?</label></th>
						<td>
							<label>
								<input type="checkbox" id="cmx_budget_update_mode" name="update_mode" value="1">
								Ja, Budget-Einträge mit gleichem Namen aktualisieren
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cmx_budget_csv_file">CSV-Datei</label></th>
						<td><input type="file" id="cmx_budget_csv_file" name="csv_file" accept=".csv" required></td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary">Import starten</button>
				<a href="<?php echo \esc_url(\admin_url('edit.php?post_type=' . CMX_PT_BUDGET)); ?>" class="button">Abbrechen</a>
			</p>
		</form>
	</div>
	<?php
});

\add_action('load-edit.php', function (): void {
	global $typenow;
	if ($typenow !== CMX_PT_BUDGET) {
		return;
	}
	if (empty($_POST['cmx_do_import_budget']) || !\check_admin_referer('cmx_budget_import')) {
		return;
	}

	if (empty($_FILES['csv_file']['tmp_name'])) {
		\add_action('admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>Keine Datei ausgewählt.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		});
		return;
	}

	$handle = @\fopen((string) $_FILES['csv_file']['tmp_name'], 'r');
	if (!$handle) {
		\add_action('admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>Datei konnte nicht gelesen werden.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		});
		return;
	}

	$first = \fread($handle, 3);
	if ($first !== "\xEF\xBB\xBF") {
		\fseek($handle, 0);
	}

	$header = \fgetcsv($handle, 0, ';', '"', '\\');
	if (!$header) {
		\fclose($handle);
		\add_action('admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>Leere oder ungültige CSV.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		});
		return;
	}

	$header = \array_map('trim', (array) $header);
	if (!cmx_budget_import_header_is_valid($header)) {
		\fclose($handle);
		cmx_budget_import_redirect_notice([
			'type'    => 'error',
			'message' => 'Falsches Format',
		]);
	}

	$update_mode = !empty($_POST['update_mode']);
	$taxonomy = cmx_budget_import_taxonomy();
	$notice = [
		'imported' => [],
		'updated'  => [],
		'skipped'  => [],
		'failed'   => [],
	];
	$row_number = 1;

	while (($line = \fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
		$row_number++;
		if (!\array_filter($line, static function ($value): bool {
			return $value !== null && $value !== '';
		})) {
			continue;
		}

		$row = @\array_combine($header, $line);
		if (!$row) {
			$notice['skipped'][] = 'Zeile ' . $row_number;
			continue;
		}
		$row_l = \array_change_key_case($row, CASE_LOWER);

		$title = \sanitize_text_field(\trim((string) ($row['post_title'] ?? ($row_l['post_title'] ?? ''))));
		if ($title === '') {
			$notice['skipped'][] = 'Zeile ' . $row_number;
			continue;
		}

		$postarr = [
			'post_type'    => CMX_PT_BUDGET,
			'post_title'   => $title,
			'post_status'  => \sanitize_key((string) ($row['post_status'] ?? ($row_l['post_status'] ?? 'publish'))),
			'post_date'    => (string) ($row['post_date'] ?? ($row_l['post_date'] ?? \current_time('mysql'))),
			'post_content' => (string) ($row['post_content'] ?? ($row_l['post_content'] ?? '')),
		];
		if ($postarr['post_status'] === '') {
			$postarr['post_status'] = 'publish';
		}

		$is_update = false;
		if ($update_mode) {
			$existing_id = cmx_budget_import_find_existing_id_by_title($title);
			if ($existing_id > 0) {
				$postarr['ID'] = $existing_id;
				$is_update = true;
			}
		}

		$post_id = \wp_insert_post($postarr, true);
		if (\is_wp_error($post_id)) {
			$notice['failed'][] = $title;
			continue;
		}

		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_TYP_META, \sanitize_key((string) ($row['kosten_typ'] ?? ($row_l['kosten_typ'] ?? 'einnahme'))) === 'ausgabe' ? 'ausgabe' : 'einnahme');
		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_BETRAG_META, cmx_budget_kosten_normalize_decimal((string) ($row['betrag'] ?? ($row_l['betrag'] ?? ''))));
		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_META, \trim((string) ($row['anteil'] ?? ($row_l['anteil'] ?? '100%'))));

		$anteil_betrag = \trim((string) ($row['anteil_betrag'] ?? ($row_l['anteil_betrag'] ?? '')));
		if ($anteil_betrag === '') {
			$anteil_betrag = cmx_budget_kosten_calculate_anteil_betrag(
				(string) ($row['betrag'] ?? ($row_l['betrag'] ?? '')),
				(string) ($row['anteil'] ?? ($row_l['anteil'] ?? '100%'))
			);
		} else {
			$anteil_betrag = cmx_budget_kosten_normalize_decimal($anteil_betrag);
		}
		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_ANTEIL_BETRAG_META, $anteil_betrag);

		$zahlbar_pro = \sanitize_key((string) ($row['zahlbar_pro'] ?? ($row_l['zahlbar_pro'] ?? 'monat')));
		if (!isset(cmx_budget_kosten_zahlbar_pro_options()[$zahlbar_pro])) {
			$zahlbar_pro = 'monat';
		}
		\update_post_meta($post_id, CMX_BUDGET_KOSTEN_ZAHLBAR_PRO_META, $zahlbar_pro);

		$contact_label = \trim((string) ($row['kontakt'] ?? ($row_l['kontakt'] ?? '')));
		\update_post_meta($post_id, CMX_BUDGET_KONTAKT_META, cmx_budget_import_find_contact_id_by_title($contact_label));

		$raw_categories = \trim((string) ($row['kategorien'] ?? ($row_l['kategorien'] ?? '')));
		if ($taxonomy !== '' && \taxonomy_exists($taxonomy)) {
			$terms = $raw_categories !== '' ? \array_values(\array_filter(\array_map('trim', \preg_split('/[|,]/', $raw_categories)))) : [];
			\wp_set_object_terms($post_id, $terms, $taxonomy, false);
		}

		$notice[$is_update ? 'updated' : 'imported'][] = $title;
	}

	\fclose($handle);
	cmx_budget_import_redirect_notice($notice);
});

\add_action('all_admin_notices', function (): void {
	if (empty($_GET['cmx_import_notice_budget'])) {
		return;
	}

	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	$current_post_type = '';
	if ($screen && !empty($screen->post_type)) {
		$current_post_type = (string) $screen->post_type;
	} elseif (!empty($_GET['post_type'])) {
		$current_post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
	}
	if ($current_post_type !== CMX_PT_BUDGET) {
		return;
	}

	$notice = \get_user_meta(\get_current_user_id(), cmx_budget_import_notice_key(), true);
	if (!$notice) {
		return;
	}
	\delete_user_meta(\get_current_user_id(), cmx_budget_import_notice_key());

	$notice_type = \sanitize_key((string) ($notice['type'] ?? 'success'));
	if (!empty($notice['message'])) {
		echo '<div class="notice notice-' . \esc_attr($notice_type === 'error' ? 'error' : 'success') . ' is-dismissible"><p>' . \esc_html((string) $notice['message']) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	echo '<div class="notice notice-success is-dismissible">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	foreach (cmx_budget_import_notice_lines((array) $notice) as $line) {
		echo '<p>' . \wp_kses_post($line) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
});
