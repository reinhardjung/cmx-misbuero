<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_layout_defaults_file_path(): string {
	return \dirname(__DIR__) . '/assets/layout_defaults.json';
}

function cmx_layout_defaults_clean_layout(array $layout): array {
	$out = [];
	foreach ($layout as $key => $value) {
		if (!\is_string($key) || $key === '') {
			continue;
		}
		if (\is_array($value)) {
			$is_list = \array_keys($value) === \range(0, \count($value) - 1);
			if ($is_list) {
				$filtered = \array_values(\array_filter($value, function ($v) {
					return $v !== false && $v !== null && $v !== '';
				}));
				if (empty($filtered) && !empty($value)) {
					// List contains only falsey values -> drop key.
					continue;
				}
				$value = $filtered;
			}
		}
		$out[$key] = $value;
	}
	return $out;
}

function cmx_layout_defaults_load_from_file(): ?array {
	$path = cmx_layout_defaults_file_path();
	if (!\is_file($path) || !\is_readable($path)) {
		return null;
	}
	$raw = \file_get_contents($path);
	if ($raw === false || $raw === '') {
		return null;
	}
	$data = \json_decode($raw, true);
	if (!\is_array($data)) {
		return null;
	}
	$layout = $data['layout'] ?? $data;
	if (!\is_array($layout)) {
		return null;
	}
	$layout = cmx_layout_defaults_clean_layout($layout);
	if (empty($layout)) {
		return null;
	}
	return $layout;
}

function cmx_layout_defaults_builtin(): array {
	return [
		'closedpostboxes_artikel' => [],
		'closedpostboxes_ausgaben' => [],
		'closedpostboxes_belege' => [],
		'closedpostboxes_budget' => [],
		'closedpostboxes_dashboard' => [],
		'closedpostboxes_kontakte' => [],
		'closedpostboxes_projekte' => [],
		'closedpostboxes_dokumente' => [],

		'meta-box-order_artikel' => [
			'side' => 'cmx_savebox,artikel_kategoriendiv,artikel_typendiv,cmx_artikel_farbe_side,cmx_artikel_marke_side,cmx_li_box_artikel,cmx_artikel_qr_box,cmx_dokumente_box',
			'normal' => 'cmx_artikel_waehrung_preise,cmx_artikel_lieferanten,cmx_artikel_belegtext,cmx_artikel_internenotizen',
			'advanced' => '',
		],
		'meta-box-order_ausgaben' => [
			'side' => 'cmx_savebox,cmx_save_metabox_layout,ausgaben_kategoriendiv,cmx_dokumente_box',
			'normal' => '',
			'advanced' => '',
		],
		'meta-box-order_belege' => [
			'side' => 'cmx_savebox,cmx_beleg_summe_box,cmx_beleg_waehrung,cmx_beleg_anzahlungen,cmx_belege_mwst_box,belege_zahlungsgrunddiv,cmx_uploads_box,cmx_dokumente_box',
			'normal' => 'cmx_beleg_details,cmx_beleg_positionen,cmx_belege_internenotizen',
			'advanced' => '',
		],
		'meta-box-order_budget' => [
			'side' => 'cmx_savebox,cmx_budget_kontakt_box,cmx_budget_kosten_side,cmx_dokumente_box',
			'normal' => '',
			'advanced' => '',
		],
		'meta-box-order_dashboard' => [
			'normal' => 'dashboard_site_health,dashboard_right_now,dashboard_activity,cmx_save_dashboard_layout,cmx_cpt_counts_widget',
			'side' => 'dashboard_quick_press,dashboard_primary,cmx_umsatz_widget,cmx_lieferanten_widget,cmx_gutschriften_widget',
			'column3' => 'cmx_kontakt_wichtige_daten',
			'column4' => 'cmx_kuchen_ein_aus,cmx_kuchen_ein_aus_nok',
		],
		'meta-box-order_dokumente' => [
			'side' => 'cmx_dokumente_box,cmx_savebox,dokumente_kategoriendiv,cmx_dokumente_validity,cmx_dokumente_status,cmx_dokumente_modules',
			'normal' => '',
			'advanced' => '',
		],
		'meta-box-order_kontakte' => [
			'side' => 'cmx_savebox,cmx_save_metabox_layout,kontakte_kategoriendiv,cmx_kontakte_stufe_side,cmx_local_image_box_kontakte,cmx_dokumente_box',
			'normal' => 'cmx_kontakte_stammdaten,cmx_kommunikation_box,cmx_adressen_metabox,cmx_projekt_tasks,cmx_kontakte_internenotizen',
			'advanced' => '',
		],
		'meta-box-order_projekte' => [
			'side' => 'cmx_savebox,cmx_save_metabox_layout,cmx_projekt_stammdaten,projekte_kategoriendiv,cmx_projekt_kontakt_box,cmx_projekt_umsatz,cmx_dokumente_box',
			'normal' => 'cmx_projekt_tasks',
			'advanced' => '',
		],

		'metaboxhidden_artikel' => [],
		'metaboxhidden_ausgaben' => [],
		'metaboxhidden_belege' => [
			'cmx_belege_mwst_box',
		],
		'metaboxhidden_budget' => [],
		'metaboxhidden_dashboard' => [],
		'metaboxhidden_dokumente' => [],
		'metaboxhidden_kontakte' => [],
		'metaboxhidden_projekte' => [],

		'screen_layout_artikel' => '2',
		'screen_layout_ausgaben' => '2',
		'screen_layout_belege' => '2',
		'screen_layout_budget' => '2',
		'screen_layout_dokumente' => '2',
		'screen_layout_kontakte' => '2',
		'screen_layout_projekte' => '2',
	];
}

function cmx_layout_defaults_map(): array {
	$from_file = cmx_layout_defaults_load_from_file();
	if (\is_array($from_file) && !empty($from_file)) {
		return $from_file;
	}
	return cmx_layout_defaults_builtin();
}

function cmx_layout_defaults_version(): string {
	static $version = null;
	if ($version !== null) {
		return $version;
	}
	$payload = cmx_layout_defaults_map();
	$json = \wp_json_encode($payload);
	if (!\is_string($json)) {
		$json = '';
	}
	$version = 'v1-' . \substr(\sha1($json), 0, 12);
	return $version;
}

function cmx_layout_defaults_sanitize_value(string $key, $value) {
	if (\strpos($key, 'metaboxhidden_') === 0 || \strpos($key, 'closedpostboxes_') === 0) {
		if (\is_array($value)) {
			$value = \array_values(\array_filter($value, function ($v) {
				return $v !== false && $v !== null && $v !== '';
			}));
		}
		return $value;
	}

	if (\strpos($key, 'screen_layout_') === 0) {
		return (string) $value;
	}

	return $value;
}

function cmx_layout_defaults_apply_to_user(int $user_id, bool $force = false): bool {
	if ($user_id <= 0) return false;

	$defaults = cmx_layout_defaults_map();
	if (empty($defaults)) return false;

	$version = cmx_layout_defaults_version();
	$current = (string) \get_user_meta($user_id, 'cmx_layout_defaults_version', true);
	if (!$force && $current === $version) {
		return false;
	}

	foreach ($defaults as $key => $value) {
		$value = cmx_layout_defaults_sanitize_value((string) $key, $value);
		if ($value === null || $value === false) {
			continue;
		}
		\update_user_option($user_id, (string) $key, $value, true);
	}

	\update_user_meta($user_id, 'cmx_layout_defaults_version', $version);
	return true;
}

function cmx_layout_defaults_user_has_layout(int $user_id): bool {
	if ($user_id <= 0) return false;
	$meta = \get_user_meta($user_id);
	foreach ($meta as $key => $values) {
		if (!\is_string($key) || $key === '') {
			continue;
		}
		if (\preg_match('/(^|_)meta-box-order_|(^|_)metaboxhidden_|(^|_)closedpostboxes_|(^|_)screen_layout_/', $key)) {
			$value = \is_array($values) ? ($values[0] ?? null) : $values;
			if (\is_string($value)) {
				$maybe = \maybe_unserialize($value);
				if ($maybe !== $value) {
					$value = $maybe;
				}
			}
			if (\is_array($value)) {
				$filtered = \array_values(\array_filter($value, function ($v) {
					return $v !== '' && $v !== false && $v !== null;
				}));
				if (!empty($filtered)) {
					return true;
				}
			} elseif ($value !== '' && $value !== false && $value !== null) {
				return true;
			}
		}
	}
	return false;
}

function cmx_layout_defaults_ensure_cloudmeister(): void {
	if (!\is_admin()) return;
	$cloud = \get_user_by('login', 'cloudmeister');
	if (!$cloud instanceof \WP_User) return;
	$applied = cmx_layout_defaults_apply_to_user((int) $cloud->ID);
	if ($applied) {
		$blog_id = \function_exists('get_current_blog_id') ? (int)\get_current_blog_id() : 0;
		$flag_key = 'cmx_layout_copied_' . $blog_id;
		\update_user_meta($cloud->ID, $flag_key, cmx_layout_defaults_version());
	}
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_layout_defaults_ensure_cloudmeister', 20);
