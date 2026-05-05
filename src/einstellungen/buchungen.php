<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_templates_option_key')) {
	function cmx_buchungen_templates_option_key(): string {
		return 'buchungen_templates';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_colors')) {
	function cmx_buchungen_template_colors(): array {
		return ['#2563eb', '#16a34a', '#f97316', '#06b6d4', '#9333ea', '#dc2626'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_article_options')) {
	function cmx_buchungen_article_options(): array {
		if (!\post_type_exists('artikel')) {
			return [];
		}

		$ids = \get_posts([
			'post_type' => 'artikel',
			'post_status' => 'publish',
			'fields' => 'ids',
			'posts_per_page' => 300,
			'orderby' => 'title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);

		$options = [];
		foreach ((array) $ids as $id) {
			$id = (int) $id;
			$title = \trim((string) \get_the_title($id));
			if ($id > 0 && $title !== '') {
				$options[$id] = $title;
			}
		}

		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_default_template_row')) {
	function cmx_buchungen_default_template_row(int $index = 0): array {
		$colors = cmx_buchungen_template_colors();
		return [
			'enabled' => '0',
			'artikel_id' => 0,
			'label' => '',
			'title' => '',
			'duration' => 60,
			'color' => $colors[$index % \count($colors)],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_sanitize_template_rows')) {
	function cmx_buchungen_sanitize_template_rows($rows): array {
		if (!\is_array($rows)) {
			return [];
		}

		$colors = cmx_buchungen_template_colors();
		$allowed_durations = [15, 30, 60];
		$clean = [];

		foreach ($rows as $index => $row) {
			if (!\is_array($row)) {
				continue;
			}

			$artikel_id = isset($row['artikel_id']) ? \max(0, (int) $row['artikel_id']) : 0;
			$label = isset($row['label']) ? \sanitize_text_field((string) $row['label']) : '';
			$title = isset($row['title']) ? \sanitize_text_field((string) $row['title']) : '';
			$duration = isset($row['duration']) ? (int) $row['duration'] : 60;
			if (!\in_array($duration, $allowed_durations, true)) {
				$duration = 60;
			}

			$color = isset($row['color']) ? (string) $row['color'] : '';
			$color = \sanitize_hex_color($color) ?: $colors[\count($clean) % \count($colors)];
			$enabled = !empty($row['enabled']) ? '1' : '0';

			if ($enabled === '0' && $artikel_id <= 0 && $label === '' && $title === '') {
				continue;
			}

			$clean[] = [
				'enabled' => $enabled,
				'artikel_id' => $artikel_id,
				'label' => $label,
				'title' => $title,
				'duration' => $duration,
				'color' => $color,
			];

			if (\count($clean) >= 3) {
				break;
			}
		}

		return $clean;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_rows')) {
	function cmx_buchungen_template_rows(bool $active_only = false): array {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$rows = cmx_buchungen_sanitize_template_rows($options[cmx_buchungen_templates_option_key()] ?? []);
		if (!$active_only) {
			return $rows;
		}

		return \array_values(\array_filter($rows, static function (array $row): bool {
			$artikel_id = (int) ($row['artikel_id'] ?? 0);
			return !empty($row['enabled'])
				&& $artikel_id > 0
				&& \get_post_type($artikel_id) === 'artikel'
				&& \get_post_status($artikel_id) === 'publish';
		}));
	}
}

\add_action('admin_init', function (): void {
	\add_settings_section(
		'cmx_sec_buchungen_templates',
		'Buchungsvorlagen',
		static function (): void {
			echo '<p>Diese Vorlagen bestimmen, welche Kacheln auf der öffentlichen Buchungsseite auswählbar sind.</p>';
		},
		'cmx_tab_buchungen'
	);

	\add_settings_field(
		'cmx_buchungen_templates',
		'Templates',
		__NAMESPACE__ . '\\cmx_render_buchungen_templates_field',
		'cmx_tab_buchungen',
		'cmx_sec_buchungen_templates'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_buchungen_templates_field')) {
		function cmx_render_buchungen_templates_field(): void {
			$rows = cmx_buchungen_template_rows(false);
			$rows = \array_slice($rows, 0, 3);
			while (\count($rows) < 3) {
				$rows[] = cmx_buchungen_default_template_row(\count($rows));
			}

		$articles = cmx_buchungen_article_options();
		$key = cmx_buchungen_templates_option_key();
		$option = CMX_SETTINGS_MAIN;

		echo '<input type="hidden" name="' . \esc_attr($option) . '[buchungen_templates_present]" value="1">';
		echo '<style>
			.cmx-buchungen-template-table{width:100%;max-width:1180px;border-collapse:separate;border-spacing:0 10px}
			.cmx-buchungen-template-table th{padding:0 8px 4px;text-align:left;color:#1d2327;font-weight:700}
			.cmx-buchungen-template-table td{padding:10px 8px;background:#fff;border-top:1px solid #dcdcde;border-bottom:1px solid #dcdcde;vertical-align:middle}
			.cmx-buchungen-template-table td:first-child{border-left:1px solid #dcdcde;border-radius:8px 0 0 8px}
			.cmx-buchungen-template-table td:last-child{border-right:1px solid #dcdcde;border-radius:0 8px 8px 0}
			.cmx-buchungen-template-table select,.cmx-buchungen-template-table input[type=text]{width:100%;max-width:240px}
			.cmx-buchungen-template-color{width:42px;height:34px;padding:0;border:1px solid #c3c4c7;border-radius:4px;background:#fff}
			.cmx-buchungen-template-note{max-width:760px;color:#646970}
		</style>';
		echo '<table class="cmx-buchungen-template-table"><thead><tr>';
		echo '<th>Aktiv</th><th>Artikel</th><th>Oberzeile</th><th>Kacheltitel</th><th>Dauer</th><th>Farbe</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $index => $row) {
			$name = $option . '[' . $key . '][' . $index . ']';
			$artikel_id = (int) ($row['artikel_id'] ?? 0);
			$duration = (int) ($row['duration'] ?? 60);
			$color = (string) ($row['color'] ?? '#2563eb');
			echo '<tr>';
			echo '<td><input type="hidden" name="' . \esc_attr($name . '[enabled]') . '" value="0"><input type="checkbox" name="' . \esc_attr($name . '[enabled]') . '" value="1" ' . \checked(!empty($row['enabled']), true, false) . '></td>';
			echo '<td><select name="' . \esc_attr($name . '[artikel_id]') . '"><option value="0">Artikel wählen...</option>';
			foreach ($articles as $article_id => $article_title) {
				echo '<option value="' . \esc_attr((string) $article_id) . '" ' . \selected($artikel_id, (int) $article_id, false) . '>' . \esc_html($article_title) . '</option>';
			}
			echo '</select></td>';
			echo '<td><input type="text" name="' . \esc_attr($name . '[label]') . '" value="' . \esc_attr((string) ($row['label'] ?? '')) . '" placeholder="z.B. Beratung"></td>';
			echo '<td><input type="text" name="' . \esc_attr($name . '[title]') . '" value="' . \esc_attr((string) ($row['title'] ?? '')) . '" placeholder="leer = Artikeltitel"></td>';
			echo '<td><select name="' . \esc_attr($name . '[duration]') . '">';
			foreach ([15, 30, 60] as $minutes) {
				echo '<option value="' . \esc_attr((string) $minutes) . '" ' . \selected($duration, $minutes, false) . '>' . \esc_html((string) $minutes) . ' Minuten</option>';
			}
			echo '</select></td>';
			echo '<td><input class="cmx-buchungen-template-color" type="color" name="' . \esc_attr($name . '[color]') . '" value="' . \esc_attr($color) . '"></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="cmx-buchungen-template-note">Nur aktive Zeilen mit ausgewähltem Artikel werden auf <a href="' . \esc_url(\home_url('/buchungen/')) . '" target="_blank" rel="noopener noreferrer">/buchungen/</a> angezeigt.</p>';
	}
}

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($new, $old) {
	$new = \is_array($new) ? $new : [];
	if (!\array_key_exists('buchungen_templates_present', $new) && !\array_key_exists(cmx_buchungen_templates_option_key(), $new)) {
		return $new;
	}

	$new[cmx_buchungen_templates_option_key()] = cmx_buchungen_sanitize_template_rows($new[cmx_buchungen_templates_option_key()] ?? []);
	unset($new['buchungen_templates_present']);

	return $new;
}, 20, 2);
