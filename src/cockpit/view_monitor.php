<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!in_array(UserDomain, cmx_ini_get_value('vip', 'instanzen'))) return;
// if (!in_array(UserDomain, cmx_ini_get_value('vip', 'instanzen'))) return;


if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_main_slug')) {
	function cmx_cockpit_view_main_slug(): string {
		return 'cmx-cockpit-dashboard';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_main_css')) {
	function cmx_cockpit_view_main_css(): string {
		return '
			.mb-dashboard-wrap{margin:20px 0 0;padding:0 20px 0 0;box-sizing:border-box}
			.mb-dashboard-intro{margin:6px 0 18px;color:#5c6773;font-size:14px}
			.mb-grid{
				display:grid;
				grid-template-columns:repeat(8,minmax(0,1fr));
				gap:18px;
				align-items:start;
			}
			.mb-card{
				background:#fff;
				border:1px solid #d7dce3;
				border-radius:14px;
				padding:18px;
				box-sizing:border-box;
				box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 24px rgba(16,24,40,.04);
			}
			.mb-card h2,
			.mb-card h3{
				margin:0 0 12px;
				font-size:15px;
				line-height:1.35;
				color:#162033;
			}
			.mb-card p{margin:0;color:#4e5968}
			.mb-card--hero{
				background:linear-gradient(135deg,#f6fbff 0%,#ffffff 56%,#f4f8ff 100%);
				border-color:#cfe0f7;
			}
			.mb-card--hero h2{
				font-size:20px;
				margin-bottom:8px;
			}
			.mb-card--hero p{
				max-width:780px;
				font-size:14px;
			}
			.mb-card--kpi{min-height:136px}
			.mb-kpi-value{
				display:block;
				margin-bottom:8px;
				font-size:32px;
				line-height:1.1;
				font-weight:700;
				color:#162033;
				letter-spacing:-.03em;
			}
			.mb-kpi-label{
				display:block;
				font-size:13px;
				color:#667085;
			}
			.mb-card--soft{
				background:linear-gradient(180deg,#ffffff 0%,#fbfcfe 100%);
			}
			.mb-card--example{
				background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
				border-style:dashed;
				border-color:#cfd9e7;
			}
			.mb-card--example p{
				font-size:13px;
				color:#5b6778;
			}
			.mb-card--chart{
				min-height:280px;
			}
			.mb-card--chart .mb-chart-placeholder{
				display:flex;
				align-items:center;
				justify-content:center;
				min-height:210px;
				border:1px dashed #c9d5e6;
				border-radius:12px;
				background:repeating-linear-gradient(
					135deg,
					#f8fbff,
					#f8fbff 14px,
					#f1f6fd 14px,
					#f1f6fd 28px
				);
				color:#56708f;
				font-size:14px;
			}
			.mb-demo-linechart{
				margin-top:18px;
				padding:14px 16px 12px;
				border:1px solid #d9e6f6;
				border-radius:12px;
				background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
			}
			.mb-monitor-shared-filters{
				display:flex;
				align-items:center;
				justify-content:space-between;
				gap:12px;
				flex-wrap:wrap;
				margin:18px 0 0;
				padding:12px 14px;
				border:1px solid #d9e6f6;
				border-radius:12px;
				background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
			}
			.mb-monitor-shared-filters-bottom{
				display:flex;
				justify-content:flex-end;
				margin-top:8px;
			}
			.mb-monitor-reset-link{
				font-size:12px;
				color:#2271b1;
				text-decoration:none;
			}
			.mb-monitor-reset-link:hover{
				text-decoration:underline;
			}
			.mb-demo-linechart-toolbar{
				display:flex;
				align-items:center;
				justify-content:space-between;
				gap:12px;
				flex-wrap:wrap;
				margin:0;
			}
			.mb-demo-linechart-control{
				display:flex;
				align-items:center;
				gap:8px;
				font-size:13px;
				color:#344054;
			}
			.mb-demo-linechart-control select{
				min-width:112px;
				padding:6px 28px 6px 10px;
				border:1px solid #cfd9e7;
				border-radius:8px;
				background:#fff;
				color:#162033;
			}
			.mb-demo-linechart-control input[type="checkbox"]{
				margin:0;
			}
			.mb-demo-linechart-canvas{
				position:relative;
				height:260px;
			}
			.mb-demo-linechart-canvas canvas{
				display:block;
				width:100%;
				height:100%;
			}
			.mb-demo-linechart-stats{
				display:grid;
				grid-template-columns:repeat(4,minmax(0,1fr));
				gap:10px;
				margin-top:12px;
			}
			.mb-monitor-overview-grid{
				display:grid;
				grid-template-columns:repeat(4,minmax(0,1fr));
				gap:18px;
			}
			.mb-monitor-overview-card{
				min-height:122px;
			}
			.mb-monitor-overview-top{
				display:flex;
				align-items:flex-start;
				justify-content:space-between;
				gap:14px;
			}
			.mb-monitor-overview-main{
				min-width:0;
				flex:1 1 auto;
			}
			.mb-monitor-overview-card .mb-kpi-value{
				font-size:28px;
			}
			.mb-monitor-overview-compare{
				display:none;
				flex:0 0 auto;
				min-width:108px;
				text-align:right;
			}
			.mb-monitor-overview-compare.is-active{
				display:block;
			}
			.mb-monitor-overview-compare-year{
				display:block;
				font-size:11px;
				line-height:1.1;
				color:#98a2b3;
				text-transform:uppercase;
				letter-spacing:.04em;
			}
			.mb-monitor-overview-compare-value{
				display:block;
				margin-top:4px;
				font-size:15px;
				line-height:1.15;
				font-weight:700;
				color:#344054;
			}
			.mb-monitor-overview-delta{
				display:block;
				margin-top:4px;
				font-size:12px;
				line-height:1.15;
				color:#667085;
			}
			.mb-monitor-overview-delta.is-positive{
				color:#166534;
			}
			.mb-monitor-overview-delta.is-negative{
				color:#b42318;
			}
			.mb-monitor-chart-intro{
				margin:-2px 0 12px;
				color:#667085;
				font-size:13px;
			}
			.mb-demo-linechart-stats strong{
				display:block;
				font-size:16px;
				line-height:1.15;
				color:#162033;
			}
			.mb-demo-linechart-stats span{
				display:block;
				margin-top:2px;
				font-size:12px;
				color:#667085;
			}
			.mb-list{
				margin:0;
				padding:0;
				list-style:none;
			}
			.mb-list li{
				display:flex;
				align-items:flex-start;
				justify-content:space-between;
				gap:12px;
				padding:10px 0;
				border-top:1px solid #eef2f6;
			}
			.mb-list li:first-child{padding-top:0;border-top:0}
			.mb-list strong{display:block;color:#162033}
			.mb-list span{display:block;color:#667085;font-size:12px}
			.mb-note{
				display:inline-flex;
				align-items:center;
				padding:4px 10px;
				border-radius:999px;
				background:#edf5ff;
				border:1px solid #d5e6fb;
				color:#2b5f9e;
				font-size:12px;
				font-weight:600;
			}
			.mb-meta{
				font-size:12px;
				color:#667085;
			}
			.mb-stat{
				font-size:26px;
				font-weight:700;
				line-height:1.1;
				color:#162033;
				margin:0 0 8px;
			}
			.mb-span-1{grid-column:span 1}
			.mb-span-2{grid-column:span 2}
			.mb-span-3{grid-column:span 3}
			.mb-span-4{grid-column:span 4}
			.mb-span-5{grid-column:span 5}
			.mb-span-6{grid-column:span 6}
			.mb-span-7{grid-column:span 7}
			.mb-span-8{grid-column:span 8}
			@media (max-width: 1600px){
				.mb-grid{grid-template-columns:repeat(6,minmax(0,1fr))}
				.mb-span-7,
				.mb-span-8{grid-column:span 6}
			}
			@media (max-width: 1280px){
				.mb-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 4}
			}
			@media (max-width: 980px){
				.mb-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
				.mb-span-3,
				.mb-span-4,
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 2}
			}
			@media (max-width: 782px){
				.mb-grid{grid-template-columns:1fr}
				.mb-span-2,
				.mb-span-3,
				.mb-span-4,
				.mb-span-5,
				.mb-span-6,
				.mb-span-7,
				.mb-span-8{grid-column:span 1}
			}
			@media (max-width: 640px){
				.mb-monitor-overview-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
				.mb-demo-linechart-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
			}
			@media (max-width: 520px){
				.mb-monitor-overview-grid{grid-template-columns:1fr}
			}
		';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_month_labels')) {
	function cmx_cockpit_view_monitor_month_labels(): array {
		return ['Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_month_options')) {
	function cmx_cockpit_view_monitor_month_options(): array {
		$options = ['all' => 'alle Monate'];
		foreach (cmx_cockpit_view_monitor_month_labels() as $index => $label) {
			$options[(string) ($index + 1)] = (string) $label;
		}
		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_quarter_options')) {
	function cmx_cockpit_view_monitor_quarter_options(): array {
		return [
			'all' => 'alle Quartale',
			'1' => '1. Quartal',
			'2' => '2. Quartal',
			'3' => '3. Quartal',
			'4' => '4. Quartal',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_taxonomy')) {
	function cmx_cockpit_view_monitor_beleg_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')) {
			$tax = (string) cmx_belege_kategorie_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}

		foreach (['belege_kategorien', 'beleg_kategorie', 'beleg_kategorien', 'belege_typ', 'belege_themen'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return (string) $tax;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_type_info')) {
	function cmx_cockpit_view_monitor_beleg_type_info(\WP_Post $post): array {
		$raw_type = '';
		$term_label = '';

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_raw_type')) {
			$raw_type = (string) cmxbu_beleg_export_raw_type($post);
		}

		$tax = cmx_cockpit_view_monitor_beleg_taxonomy();
		if ($tax !== '') {
			$terms = \wp_get_post_terms((int) $post->ID, $tax);
			if (!\is_wp_error($terms) && !empty($terms[0]) && $terms[0] instanceof \WP_Term) {
				$term = $terms[0];
				if ($raw_type === '') {
					$raw_type = (string) ($term->slug ?? '');
				}
				$term_label = \trim((string) ($term->name ?? ''));
			}
		}

		$slug = \strtolower(\sanitize_key($raw_type));
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_beleg_export_normalize_type')) {
			$slug = (string) cmxbu_beleg_export_normalize_type($slug);
		} else {
			$slug = (string) ([
				'rechnungen' => 'rechnung',
				'quittungen' => 'quittung',
				'gutschriften' => 'gutschrift',
			][$slug] ?? $slug);
		}

		if ($slug === '') {
			$slug = '__without_type__';
		}

		$label_map = [
			'rechnung' => 'Rechnung',
			'quittung' => 'Quittung',
			'gutschrift' => 'Gutschrift',
			'offerte' => 'Offerte',
			'lieferschein' => 'Lieferschein',
			'lieferantenrechnung' => 'Lieferantenrechnung',
			'lieferantenquittung' => 'Lieferantenquittung',
			'__without_type__' => 'Ohne Belegtyp',
		];

		$label = (string) ($label_map[$slug] ?? '');
		if ($label === '' && $term_label !== '') {
			$label = $term_label;
		}
		if ($label === '') {
			$label = \str_replace(['_', '-'], ' ', $slug);
			$label = \function_exists('mb_convert_case')
				? (string) \mb_convert_case($label, MB_CASE_TITLE, 'UTF-8')
				: \ucwords($label);
		}

		return [
			'slug' => $slug,
			'label' => $label,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_total')) {
	function cmx_cockpit_view_monitor_beleg_total(int $post_id): float {
		$total = 0.0;

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($post_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				$total = (float) $calc['total'];
			}
		}

		$override_raw = \trim((string) \get_post_meta($post_id, '_cmx_beleg_summe_override', true));
		if ($override_raw !== '') {
			$normalized = \str_replace(['\'', ' '], '', \str_replace(',', '.', $override_raw));
			if (\is_numeric($normalized)) {
				$total = (float) $normalized;
			}
		}

		return \round($total, 2);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_timestamp')) {
	function cmx_cockpit_view_monitor_beleg_timestamp(int $post_id): int {
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_post_date')) {
			$normalized = (string) cmxbu_belege_export_post_date($post_id);
			$ts = $normalized !== '' ? \strtotime($normalized . ' 00:00:00') : false;
			if ($ts) {
				return (int) $ts;
			}
		}

		$date_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_DATUM')) {
			$date_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_DATUM');
		}
		$date_keys = \array_merge($date_keys, [
			'_cmx_beleg_rng_datum',
			'beleg_datum',
			'_cmx_rechnungsdatum',
			'_invoice_date',
			'_date',
		]);

		foreach (\array_unique($date_keys) as $meta_key) {
			$raw = \trim((string) \get_post_meta($post_id, $meta_key, true));
			if ($raw === '') {
				continue;
			}

			foreach (['Y-m-d', 'd.m.Y', 'Ymd', 'Y-m-d H:i:s'] as $format) {
				$dt = \DateTime::createFromFormat($format, $raw);
				if ($dt instanceof \DateTime) {
					return (int) $dt->getTimestamp();
				}
			}

			$ts = \strtotime($raw);
			if ($ts) {
				return (int) $ts;
			}
		}

		$post = \get_post($post_id);
		if ($post instanceof \WP_Post) {
			$ts = \strtotime((string) $post->post_date);
			if ($ts) {
				return (int) $ts;
			}
		}

		return 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_has_paid_date')) {
	function cmx_cockpit_view_monitor_beleg_has_paid_date(int $post_id): bool {
		if (\function_exists(__NAMESPACE__ . '\\cmx_cockpit_paid_date')) {
			return cmx_cockpit_paid_date($post_id) !== '';
		}

		if (\function_exists(__NAMESPACE__ . '\\cmxbu_belege_export_paid_date')) {
			return ((string) cmxbu_belege_export_paid_date($post_id)) !== '';
		}

		$raw = \trim((string) \get_post_meta($post_id, '_cmx_beleg_bezahlt_am', true));
		return (bool) \preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_parse_number')) {
	function cmx_cockpit_view_monitor_parse_number($value): float {
		if (\function_exists(__NAMESPACE__ . '\\cmx_norm_decimal')) {
			return (float) cmx_norm_decimal((string) $value);
		}

		$raw = \trim((string) $value);
		if ($raw === '') {
			return 0.0;
		}

		$raw = \str_replace(["\xE2\x88\x92", '−', '\'', ' '], ['-', '-', '', ''], $raw);
		$raw = \str_replace(',', '.', $raw);

		return \is_numeric($raw) ? (float) $raw : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_position_rows')) {
	function cmx_cockpit_view_monitor_position_rows(int $post_id): array {
		$raw = \get_post_meta($post_id, '_cmx_beleg_positionen', true);

		if (\is_string($raw) && $raw !== '') {
			$tmp = \json_decode($raw, true);
			if (\json_last_error() === JSON_ERROR_NONE && \is_array($tmp)) {
				return $tmp;
			}
			$tmp = @\unserialize($raw);
			return \is_array($tmp) ? $tmp : [];
		}

		return \is_array($raw) ? $raw : [];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_artikel_unit_cost')) {
	function cmx_cockpit_view_monitor_artikel_unit_cost(int $artikel_id): float {
		static $cache = [];

		if ($artikel_id <= 0) {
			return 0.0;
		}
		if (isset($cache[$artikel_id])) {
			return (float) $cache[$artikel_id];
		}

		$selfcost_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SELBSTKOSTEN')) {
			$selfcost_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_SELBSTKOSTEN');
		}
		$selfcost_keys[] = '_cmx_artikel_selbstkosten';
		foreach (\array_unique($selfcost_keys) as $meta_key) {
			$raw = \get_post_meta($artikel_id, $meta_key, true);
			if ($raw === '' || $raw === null) {
				continue;
			}
			$cache[$artikel_id] = \round(cmx_cockpit_view_monitor_parse_number($raw), 2);
			return (float) $cache[$artikel_id];
		}

		$ek_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_EK')) {
			$ek_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_EK');
		}
		$ek_keys[] = '_cmx_artikel_ek';

		$aufwand_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND')) {
			$aufwand_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_AUFWAND');
		}
		$aufwand_keys[] = '_cmx_artikel_aufwand';

		$ek = 0.0;
		foreach (\array_unique($ek_keys) as $meta_key) {
			$raw = \get_post_meta($artikel_id, $meta_key, true);
			if ($raw === '' || $raw === null) {
				continue;
			}
			$ek = cmx_cockpit_view_monitor_parse_number($raw);
			break;
		}

		$aufwand = 0.0;
		foreach (\array_unique($aufwand_keys) as $meta_key) {
			$raw = \get_post_meta($artikel_id, $meta_key, true);
			if ($raw === '' || $raw === null) {
				continue;
			}
			$aufwand = cmx_cockpit_view_monitor_parse_number($raw);
			break;
		}

		$cache[$artikel_id] = \round($ek + $aufwand, 2);
		return (float) $cache[$artikel_id];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_beleg_cost')) {
	function cmx_cockpit_view_monitor_beleg_cost(int $post_id): float {
		$rows = cmx_cockpit_view_monitor_position_rows($post_id);
		$total_cost = 0.0;

		foreach ($rows as $row) {
			if (!\is_array($row)) {
				continue;
			}

			$row_type = \sanitize_key((string) ($row['typ'] ?? $row['row_type'] ?? ''));
			if ($row_type === 'abschnitt') {
				continue;
			}

			$artikel_id = (int) ($row['artikel_id'] ?? 0);
			if ($artikel_id <= 0) {
				continue;
			}

			$qty = cmx_cockpit_view_monitor_parse_number($row['menge'] ?? 0);
			if ($qty === 0.0) {
				continue;
			}

			$total_cost += $qty * cmx_cockpit_view_monitor_artikel_unit_cost($artikel_id);
		}

		return \round($total_cost, 2);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_chart_payload')) {
	function cmx_cockpit_view_monitor_chart_payload(): array {
		$labels = cmx_cockpit_view_monitor_month_labels();
		$years = [];
		$types = [];
		$series = [];
		$daily_series = [];
		$cost_series = [];
		$daily_cost_series = [];
		$counts = [];
		$counts_monthly = [];
		$series_by_type = [];
		$daily_series_by_type = [];
		$cost_series_by_type = [];
		$daily_cost_series_by_type = [];
		$counts_by_type = [];
		$counts_monthly_by_type = [];
		$post_type = \defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PT_BELEGE')
			: 'belege';

		$query = new \WP_Query([
			'post_type' => $post_type,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'ASC',
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		]);

		foreach ((array) $query->posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			if (\in_array((string) $post->post_status, ['trash', 'auto-draft'], true)) {
				continue;
			}
			if (!cmx_cockpit_view_monitor_beleg_has_paid_date((int) $post->ID)) {
				continue;
			}

			$timestamp = cmx_cockpit_view_monitor_beleg_timestamp((int) $post->ID);
			if ($timestamp <= 0) {
				continue;
			}

			$year = (int) \date('Y', $timestamp);
			$month_index = ((int) \date('n', $timestamp)) - 1;
			$month_number = $month_index + 1;
			$day_index = ((int) \date('j', $timestamp)) - 1;
			if ($month_index < 0 || $month_index > 11) {
				continue;
			}
			if ($day_index < 0 || $day_index > 30) {
				continue;
			}

			if (!isset($series[$year])) {
				$series[$year] = \array_fill(0, 12, 0.0);
				$cost_series[$year] = \array_fill(0, 12, 0.0);
				$counts[$year] = 0;
				$counts_monthly[$year] = \array_fill(1, 12, 0);
			}
			if (!isset($daily_series[$year])) {
				$daily_series[$year] = [];
			}
			if (!isset($daily_cost_series[$year])) {
				$daily_cost_series[$year] = [];
			}
			if (!isset($daily_series[$year][$month_number])) {
				$daily_series[$year][$month_number] = \array_fill(0, 31, 0.0);
			}
			if (!isset($daily_cost_series[$year][$month_number])) {
				$daily_cost_series[$year][$month_number] = \array_fill(0, 31, 0.0);
			}

			$total = cmx_cockpit_view_monitor_beleg_total((int) $post->ID);
			$cost_total = cmx_cockpit_view_monitor_beleg_cost((int) $post->ID);
			$type_info = cmx_cockpit_view_monitor_beleg_type_info($post);
			$type_slug = (string) ($type_info['slug'] ?? '__without_type__');
			$type_label = (string) ($type_info['label'] ?? 'Ohne Belegtyp');
			$series[$year][$month_index] += $total;
			$daily_series[$year][$month_number][$day_index] += $total;
			$cost_series[$year][$month_index] += $cost_total;
			$daily_cost_series[$year][$month_number][$day_index] += $cost_total;
			$counts[$year] = (int) ($counts[$year] ?? 0) + 1;
			$counts_monthly[$year][$month_number] = (int) (($counts_monthly[$year][$month_number] ?? 0) + 1);

			if (!isset($types[$type_slug])) {
				$types[$type_slug] = $type_label;
			}
			if (!isset($series_by_type[$type_slug])) {
				$series_by_type[$type_slug] = [];
				$daily_series_by_type[$type_slug] = [];
				$cost_series_by_type[$type_slug] = [];
				$daily_cost_series_by_type[$type_slug] = [];
				$counts_by_type[$type_slug] = [];
				$counts_monthly_by_type[$type_slug] = [];
			}
			if (!isset($series_by_type[$type_slug][$year])) {
				$series_by_type[$type_slug][$year] = \array_fill(0, 12, 0.0);
				$cost_series_by_type[$type_slug][$year] = \array_fill(0, 12, 0.0);
				$counts_by_type[$type_slug][$year] = 0;
				$counts_monthly_by_type[$type_slug][$year] = \array_fill(1, 12, 0);
			}
			if (!isset($daily_series_by_type[$type_slug][$year])) {
				$daily_series_by_type[$type_slug][$year] = [];
			}
			if (!isset($daily_cost_series_by_type[$type_slug][$year])) {
				$daily_cost_series_by_type[$type_slug][$year] = [];
			}
			if (!isset($daily_series_by_type[$type_slug][$year][$month_number])) {
				$daily_series_by_type[$type_slug][$year][$month_number] = \array_fill(0, 31, 0.0);
			}
			if (!isset($daily_cost_series_by_type[$type_slug][$year][$month_number])) {
				$daily_cost_series_by_type[$type_slug][$year][$month_number] = \array_fill(0, 31, 0.0);
			}

			$series_by_type[$type_slug][$year][$month_index] += $total;
			$daily_series_by_type[$type_slug][$year][$month_number][$day_index] += $total;
			$cost_series_by_type[$type_slug][$year][$month_index] += $cost_total;
			$daily_cost_series_by_type[$type_slug][$year][$month_number][$day_index] += $cost_total;
			$counts_by_type[$type_slug][$year] = (int) ($counts_by_type[$type_slug][$year] ?? 0) + 1;
			$counts_monthly_by_type[$type_slug][$year][$month_number] = (int) (($counts_monthly_by_type[$type_slug][$year][$month_number] ?? 0) + 1);
		}

		\wp_reset_postdata();

		if ($series !== []) {
			\krsort($series, \SORT_NUMERIC);
		}

		foreach ($series as $year => $values) {
			$years[] = (int) $year;
			$series[$year] = \array_map(
				static fn($value): float => \round((float) $value, 2),
				(array) $values
			);
			$cost_series[$year] = \array_map(
				static fn($value): float => \round((float) $value, 2),
				(array) ($cost_series[$year] ?? [])
			);
			if (isset($daily_series[$year]) && \is_array($daily_series[$year])) {
				foreach ($daily_series[$year] as $month_number => $day_values) {
					$daily_series[$year][$month_number] = \array_map(
						static fn($value): float => \round((float) $value, 2),
						(array) $day_values
					);
				}
			}
			if (isset($daily_cost_series[$year]) && \is_array($daily_cost_series[$year])) {
				foreach ($daily_cost_series[$year] as $month_number => $day_values) {
					$daily_cost_series[$year][$month_number] = \array_map(
						static fn($value): float => \round((float) $value, 2),
						(array) $day_values
					);
				}
			}
		}

		foreach ($series_by_type as $type_slug => $type_years) {
			foreach ((array) $type_years as $year => $values) {
				$series_by_type[$type_slug][$year] = \array_map(
					static fn($value): float => \round((float) $value, 2),
					(array) $values
				);
				$cost_series_by_type[$type_slug][$year] = \array_map(
					static fn($value): float => \round((float) $value, 2),
					(array) ($cost_series_by_type[$type_slug][$year] ?? [])
				);
			}
			if (isset($daily_series_by_type[$type_slug]) && \is_array($daily_series_by_type[$type_slug])) {
				foreach ($daily_series_by_type[$type_slug] as $year => $months) {
					foreach ((array) $months as $month_number => $day_values) {
						$daily_series_by_type[$type_slug][$year][$month_number] = \array_map(
							static fn($value): float => \round((float) $value, 2),
							(array) $day_values
						);
					}
				}
			}
			if (isset($daily_cost_series_by_type[$type_slug]) && \is_array($daily_cost_series_by_type[$type_slug])) {
				foreach ($daily_cost_series_by_type[$type_slug] as $year => $months) {
					foreach ((array) $months as $month_number => $day_values) {
						$daily_cost_series_by_type[$type_slug][$year][$month_number] = \array_map(
							static fn($value): float => \round((float) $value, 2),
							(array) $day_values
						);
					}
				}
			}
		}

		if ($years === []) {
			$current_year = (int) \wp_date('Y');
			$years = [$current_year];
			$series[$current_year] = \array_fill(0, 12, 0.0);
			$daily_series[$current_year] = [];
			$cost_series[$current_year] = \array_fill(0, 12, 0.0);
			$daily_cost_series[$current_year] = [];
			$counts[$current_year] = 0;
			$counts_monthly[$current_year] = \array_fill(1, 12, 0);
		}

		$type_priority = [
			'rechnung' => 10,
			'quittung' => 20,
			'gutschrift' => 30,
			'offerte' => 40,
			'lieferschein' => 50,
			'lieferantenrechnung' => 60,
			'lieferantenquittung' => 70,
			'__without_type__' => 999,
		];
		if (!empty($types)) {
			\uksort($types, static function (string $slug_a, string $slug_b) use ($types, $type_priority): int {
				$prio_a = (int) ($type_priority[$slug_a] ?? 500);
				$prio_b = (int) ($type_priority[$slug_b] ?? 500);
				if ($prio_a !== $prio_b) {
					return $prio_a <=> $prio_b;
				}
				return \strnatcasecmp((string) ($types[$slug_a] ?? ''), (string) ($types[$slug_b] ?? ''));
			});
		}
		$type_options = [];
		foreach ($types as $slug => $label) {
			$type_options[] = [
				'value' => (string) $slug,
				'label' => (string) $label,
			];
		}

		return [
			'labels' => $labels,
			'years' => $years,
			'types' => $type_options,
			'series' => $series,
			'daily_series' => $daily_series,
			'cost_series' => $cost_series,
			'daily_cost_series' => $daily_cost_series,
			'counts' => $counts,
			'counts_monthly' => $counts_monthly,
			'series_by_type' => $series_by_type,
			'daily_series_by_type' => $daily_series_by_type,
			'cost_series_by_type' => $cost_series_by_type,
			'daily_cost_series_by_type' => $daily_cost_series_by_type,
			'counts_by_type' => $counts_by_type,
			'counts_monthly_by_type' => $counts_monthly_by_type,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_view_main_page')) {
	function cmx_render_view_main_page(): void {
		$chart_payload = cmx_cockpit_view_monitor_chart_payload();
		$years = \array_values((array) ($chart_payload['years'] ?? []));
		$type_options = \array_values((array) ($chart_payload['types'] ?? []));
		$quarter_options = cmx_cockpit_view_monitor_quarter_options();
		$month_options = cmx_cockpit_view_monitor_month_options();
		$selected_type = 'all';
		$selected_quarter = 'all';
		$selected_month = 'all';
		$selected_year = (int) ($years[0] ?? \wp_date('Y'));
		$selected_series = (array) (($chart_payload['series'] ?? [])[$selected_year] ?? \array_fill(0, 12, 0.0));
		$selected_cost_series = (array) (($chart_payload['cost_series'] ?? [])[$selected_year] ?? \array_fill(0, 12, 0.0));
		$selected_total = \array_sum($selected_series);
		$selected_cost_total = \array_sum($selected_cost_series);
		$selected_profit_total = $selected_total - $selected_cost_total;
		$selected_margin_total = $selected_total != 0.0 ? (($selected_profit_total / $selected_total) * 100) : 0.0;
		$selected_count = (int) (($chart_payload['counts'] ?? [])[$selected_year] ?? 0);
		$compare_year = isset($chart_payload['series'][$selected_year - 1]) ? ($selected_year - 1) : 0;
		$compare_total = 0.0;
		?>
		<div class="wrap mb-dashboard-wrap">
			<!-- <h1>Monitor</h1> -->
			<!-- <p class="mb-dashboard-intro">Akt. Daten</p> -->

			<div class="mb-grid">
				<section class="mb-card mb-card--hero mb-span-8">
					<!-- <span class="mb-note">Dashboard</span> -->
					<h2>Meine Umsätze</h2>
					<p>Deine Werte kannst Du auch gegenüber stellen.</p>
					<div class="mb-monitor-shared-filters" aria-label="Monitor-Filter">
						<div class="mb-demo-linechart-toolbar">
							<label class="mb-demo-linechart-control">
								<span>Jahr</span>
								<select id="cmx-monitor-chart-year">
									<?php foreach ($years as $year_option) : ?>
										<option value="<?php echo \esc_attr((string) $year_option); ?>"<?php selected($year_option, $selected_year); ?>><?php echo \esc_html((string) $year_option); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="mb-demo-linechart-control">
								<span>Quartal</span>
								<select id="cmx-monitor-chart-quarter">
									<?php foreach ($quarter_options as $quarter_value => $quarter_label) : ?>
										<option value="<?php echo \esc_attr((string) $quarter_value); ?>"<?php selected((string) $quarter_value, $selected_quarter); ?>><?php echo \esc_html((string) $quarter_label); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="mb-demo-linechart-control">
								<span>Monat</span>
								<select id="cmx-monitor-chart-month">
									<?php foreach ($month_options as $month_value => $month_label) : ?>
										<option value="<?php echo \esc_attr((string) $month_value); ?>"<?php selected((string) $month_value, $selected_month); ?>><?php echo \esc_html((string) $month_label); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="mb-demo-linechart-control">
								<span>Belegtyp</span>
								<select id="cmx-monitor-chart-type">
									<option value="all"<?php selected($selected_type, 'all'); ?>>alle Belegtypen</option>
									<?php foreach ($type_options as $type_option) : ?>
										<option value="<?php echo \esc_attr((string) ($type_option['value'] ?? '')); ?>"<?php selected((string) ($type_option['value'] ?? ''), $selected_type); ?>><?php echo \esc_html((string) ($type_option['label'] ?? '')); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="mb-demo-linechart-control">
								<input type="checkbox" id="cmx-monitor-chart-compare">
								<span>vorheriger Zeitraum</span>
							</label>
						</div>
						<div class="mb-monitor-shared-filters-bottom">
							<a href="#" id="cmx-monitor-reset-filters" class="mb-monitor-reset-link">Filter zurück setzen</a>
						</div>
					</div>
					<div class="mb-demo-linechart" aria-label="Demo-Line-Chart">
						<div class="mb-demo-linechart-canvas">
							<canvas id="cmx-monitor-multi-axis-chart" aria-label="Demo-Multi-Axis-Line-Chart"></canvas>
						</div>
						<div class="mb-demo-linechart-stats">
							<div><strong id="cmx-monitor-stat-total"><?php echo \esc_html(\number_format($selected_total, 2, '.', '\'')); ?></strong><span id="cmx-monitor-stat-total-label">Umsatz <?php echo \esc_html((string) $selected_year); ?></span></div>
							<div><strong id="cmx-monitor-stat-compare"><?php echo \esc_html(\number_format($compare_total, 2, '.', '\'')); ?></strong><span id="cmx-monitor-stat-compare-label">Vergleich</span></div>
							<div><strong id="cmx-monitor-stat-count"><?php echo \esc_html((string) $selected_count); ?></strong><span>Belege</span></div>
							<div><strong id="cmx-monitor-stat-mode">aus</strong><span>Vergleich</span></div>
						</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-card--kpi mb-monitor-overview-card mb-span-2">
					<div class="mb-monitor-overview-top">
						<div class="mb-monitor-overview-main">
							<strong class="mb-kpi-value" id="cmx-monitor-overview-total"><?php echo \esc_html(\number_format($selected_total, 2, '.', '\'')); ?></strong>
							<span class="mb-kpi-label">Umsatz</span>
						</div>
						<div class="mb-monitor-overview-compare" id="cmx-monitor-overview-total-compare-box" hidden>
							<span class="mb-monitor-overview-compare-year" id="cmx-monitor-overview-total-compare-year"></span>
							<strong class="mb-monitor-overview-compare-value" id="cmx-monitor-overview-total-compare"></strong>
							<span class="mb-monitor-overview-delta" id="cmx-monitor-overview-total-delta"></span>
						</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-card--kpi mb-monitor-overview-card mb-span-2">
					<div class="mb-monitor-overview-top">
						<div class="mb-monitor-overview-main">
							<strong class="mb-kpi-value" id="cmx-monitor-overview-cost"><?php echo \esc_html(\number_format($selected_cost_total, 2, '.', '\'')); ?></strong>
							<span class="mb-kpi-label">Aufwand / Einkauf</span>
						</div>
						<div class="mb-monitor-overview-compare" id="cmx-monitor-overview-cost-compare-box" hidden>
							<span class="mb-monitor-overview-compare-year" id="cmx-monitor-overview-cost-compare-year"></span>
							<strong class="mb-monitor-overview-compare-value" id="cmx-monitor-overview-cost-compare"></strong>
							<span class="mb-monitor-overview-delta" id="cmx-monitor-overview-cost-delta"></span>
						</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-card--kpi mb-monitor-overview-card mb-span-2">
					<div class="mb-monitor-overview-top">
						<div class="mb-monitor-overview-main">
							<strong class="mb-kpi-value" id="cmx-monitor-overview-profit"><?php echo \esc_html(\number_format($selected_profit_total, 2, '.', '\'')); ?></strong>
							<span class="mb-kpi-label">Deckungsbeitrag / Gewinn</span>
						</div>
						<div class="mb-monitor-overview-compare" id="cmx-monitor-overview-profit-compare-box" hidden>
							<span class="mb-monitor-overview-compare-year" id="cmx-monitor-overview-profit-compare-year"></span>
							<strong class="mb-monitor-overview-compare-value" id="cmx-monitor-overview-profit-compare"></strong>
							<span class="mb-monitor-overview-delta" id="cmx-monitor-overview-profit-delta"></span>
						</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-card--kpi mb-monitor-overview-card mb-span-2">
					<div class="mb-monitor-overview-top">
						<div class="mb-monitor-overview-main">
							<strong class="mb-kpi-value" id="cmx-monitor-overview-margin"><?php echo \esc_html(\number_format($selected_margin_total, 2, '.', '\'')); ?>%</strong>
							<span class="mb-kpi-label">Marge in %</span>
						</div>
						<div class="mb-monitor-overview-compare" id="cmx-monitor-overview-margin-compare-box" hidden>
							<span class="mb-monitor-overview-compare-year" id="cmx-monitor-overview-margin-compare-year"></span>
							<strong class="mb-monitor-overview-compare-value" id="cmx-monitor-overview-margin-compare"></strong>
							<span class="mb-monitor-overview-delta" id="cmx-monitor-overview-margin-delta"></span>
						</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-span-4">
					<h3>Aufwand vs. Umsatz</h3>
					<p class="mb-monitor-chart-intro">Vergleich von Umsatz und Aufwand auf Basis derselben Filter oben.</p>
					<div class="mb-demo-linechart">
						<div class="mb-demo-linechart-canvas">
							<canvas id="cmx-monitor-cost-chart" aria-label="Aufwand und Umsatz"></canvas>
						</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-span-4">
					<h3>Deckungsbeitrag und Marge</h3>
					<p class="mb-monitor-chart-intro">Deckungsbeitrag in Währung und Marge in Prozent als eigene Auswertung.</p>
					<div class="mb-demo-linechart">
						<div class="mb-demo-linechart-canvas">
							<canvas id="cmx-monitor-profit-chart" aria-label="Deckungsbeitrag und Marge"></canvas>
						</div>
					</div>
				</section>

			</div>
		</div>
		<?php
	}
}

\add_action('admin_menu', function (): void {
	\add_dashboard_page(
		'Wirtschaftlichkeit',
		'Wirtschaftlichkeit',
		'edit_posts',
		cmx_cockpit_view_main_slug(),
		__NAMESPACE__ . '\\cmx_render_view_main_page'
	);
});

\add_action('admin_enqueue_scripts', function (string $hook): void {
	if ($hook !== 'dashboard_page_' . cmx_cockpit_view_main_slug()) {
		return;
	}

	if (\function_exists(__NAMESPACE__ . '\\cmx_enqueue_chartjs')) {
		cmx_enqueue_chartjs();
	}

	$handle = 'cmx-cockpit-view-main';
	\wp_register_style($handle, false, [], '1.0');
	\wp_enqueue_style($handle);
	\wp_add_inline_style($handle, cmx_cockpit_view_main_css());

	$chart_payload = cmx_cockpit_view_monitor_chart_payload();
	$chart_script = '(function(){
		var init = function(){
			var canvas = document.getElementById("cmx-monitor-multi-axis-chart");
			var costCanvas = document.getElementById("cmx-monitor-cost-chart");
			var profitCanvas = document.getElementById("cmx-monitor-profit-chart");
			var yearSelect = document.getElementById("cmx-monitor-chart-year");
			var quarterSelect = document.getElementById("cmx-monitor-chart-quarter");
			var monthSelect = document.getElementById("cmx-monitor-chart-month");
			var typeSelect = document.getElementById("cmx-monitor-chart-type");
			var compareCheckbox = document.getElementById("cmx-monitor-chart-compare");
			var resetLink = document.getElementById("cmx-monitor-reset-filters");
			if (!canvas || !yearSelect || !quarterSelect || !monthSelect || !typeSelect || !compareCheckbox || !resetLink || typeof Chart === "undefined") return;

			var ctx = canvas.getContext("2d");
			if (!ctx) return;

			var costCtx = costCanvas ? costCanvas.getContext("2d") : null;
			var profitCtx = profitCanvas ? profitCanvas.getContext("2d") : null;
			var payload = ' . \wp_json_encode($chart_payload) . ';
			var totalEl = document.getElementById("cmx-monitor-stat-total");
			var totalLabelEl = document.getElementById("cmx-monitor-stat-total-label");
			var compareEl = document.getElementById("cmx-monitor-stat-compare");
			var compareLabelEl = document.getElementById("cmx-monitor-stat-compare-label");
			var countEl = document.getElementById("cmx-monitor-stat-count");
			var modeEl = document.getElementById("cmx-monitor-stat-mode");
			var overviewTotalEl = document.getElementById("cmx-monitor-overview-total");
			var overviewCostEl = document.getElementById("cmx-monitor-overview-cost");
			var overviewProfitEl = document.getElementById("cmx-monitor-overview-profit");
			var overviewMarginEl = document.getElementById("cmx-monitor-overview-margin");
			var overviewTotalCompareBoxEl = document.getElementById("cmx-monitor-overview-total-compare-box");
			var overviewTotalCompareYearEl = document.getElementById("cmx-monitor-overview-total-compare-year");
			var overviewTotalCompareEl = document.getElementById("cmx-monitor-overview-total-compare");
			var overviewTotalDeltaEl = document.getElementById("cmx-monitor-overview-total-delta");
			var overviewCostCompareBoxEl = document.getElementById("cmx-monitor-overview-cost-compare-box");
			var overviewCostCompareYearEl = document.getElementById("cmx-monitor-overview-cost-compare-year");
			var overviewCostCompareEl = document.getElementById("cmx-monitor-overview-cost-compare");
			var overviewCostDeltaEl = document.getElementById("cmx-monitor-overview-cost-delta");
			var overviewProfitCompareBoxEl = document.getElementById("cmx-monitor-overview-profit-compare-box");
			var overviewProfitCompareYearEl = document.getElementById("cmx-monitor-overview-profit-compare-year");
			var overviewProfitCompareEl = document.getElementById("cmx-monitor-overview-profit-compare");
			var overviewProfitDeltaEl = document.getElementById("cmx-monitor-overview-profit-delta");
			var overviewMarginCompareBoxEl = document.getElementById("cmx-monitor-overview-margin-compare-box");
			var overviewMarginCompareYearEl = document.getElementById("cmx-monitor-overview-margin-compare-year");
			var overviewMarginCompareEl = document.getElementById("cmx-monitor-overview-margin-compare");
			var overviewMarginDeltaEl = document.getElementById("cmx-monitor-overview-margin-delta");
			var typeMeta = Array.isArray(payload.types) ? payload.types : [];
			var formatNumber = function(value){
				return new Intl.NumberFormat("de-CH", {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0));
			};
			var formatAxisNumber = function(value){
				return new Intl.NumberFormat("de-CH", {minimumFractionDigits: 0, maximumFractionDigits: 0}).format(Number(value || 0));
			};
			var formatPercent = function(value){
				return new Intl.NumberFormat("de-CH", {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0)) + "%";
			};
			var formatSignedNumber = function(value){
				var numeric = Number(value || 0);
				return (numeric > 0 ? "+" : "") + formatNumber(numeric);
			};
			var formatSignedPercentPoints = function(value){
				var numeric = Number(value || 0);
				return (numeric > 0 ? "+" : "") + new Intl.NumberFormat("de-CH", {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(numeric) + " PP";
			};
			var formatPercentAxis = function(value){
				return new Intl.NumberFormat("de-CH", {minimumFractionDigits: 0, maximumFractionDigits: 1}).format(Number(value || 0));
			};
			var sumSeries = function(series){
				return (Array.isArray(series) ? series : []).reduce(function(sum, value){
					return sum + Number(value || 0);
				}, 0);
			};
			var sumIntegerSeries = function(series){
				return (Array.isArray(series) ? series : []).reduce(function(sum, value){
					return sum + Number(value || 0);
				}, 0);
			};
			var countSeries = function(series){
				return (Array.isArray(series) ? series : []).reduce(function(sum, value){
					return sum + (Number(value || 0) !== 0 ? 1 : 0);
				}, 0);
			};
			var differenceSeries = function(primary, secondary){
				var length = Math.max(Array.isArray(primary) ? primary.length : 0, Array.isArray(secondary) ? secondary.length : 0);
				var out = [];
				for (var i = 0; i < length; i += 1) {
					out.push(Number((primary && primary[i]) || 0) - Number((secondary && secondary[i]) || 0));
				}
				return out;
			};
			var marginSeries = function(revenueSeries, costSeries){
				var length = Math.max(Array.isArray(revenueSeries) ? revenueSeries.length : 0, Array.isArray(costSeries) ? costSeries.length : 0);
				var out = [];
				for (var i = 0; i < length; i += 1) {
					var revenue = Number((revenueSeries && revenueSeries[i]) || 0);
					var cost = Number((costSeries && costSeries[i]) || 0);
					var profit = revenue - cost;
					out.push(revenue !== 0 ? ((profit / revenue) * 100) : 0);
				}
				return out;
			};
			var typeLabelFor = function(type){
				var normalizedType = String(type || "all");
				if (normalizedType === "all") return "";
				for (var i = 0; i < typeMeta.length; i += 1) {
					if (String(typeMeta[i].value || "") === normalizedType) {
						return String(typeMeta[i].label || "");
					}
				}
				return "";
			};
			var getSeriesForYear = function(year, type){
				var selectedYear = String(year || "");
				var selectedType = String(type || "all");
				if (selectedType !== "all") {
					return payload.series_by_type && payload.series_by_type[selectedType] && payload.series_by_type[selectedType][selectedYear]
						? payload.series_by_type[selectedType][selectedYear]
						: [];
				}
				return payload.series && payload.series[selectedYear] ? payload.series[selectedYear] : [];
			};
			var getCostSeriesForYear = function(year, type){
				var selectedYear = String(year || "");
				var selectedType = String(type || "all");
				if (selectedType !== "all") {
					return payload.cost_series_by_type && payload.cost_series_by_type[selectedType] && payload.cost_series_by_type[selectedType][selectedYear]
						? payload.cost_series_by_type[selectedType][selectedYear]
						: [];
				}
				return payload.cost_series && payload.cost_series[selectedYear] ? payload.cost_series[selectedYear] : [];
			};
			var getDailySeriesForMonth = function(year, monthNumber, type){
				var selectedYear = String(year || "");
				var selectedType = String(type || "all");
				var normalizedMonth = Number(monthNumber || 0);
				if (selectedType !== "all") {
					return payload.daily_series_by_type && payload.daily_series_by_type[selectedType] && payload.daily_series_by_type[selectedType][selectedYear] && payload.daily_series_by_type[selectedType][selectedYear][normalizedMonth]
						? payload.daily_series_by_type[selectedType][selectedYear][normalizedMonth]
						: [];
				}
				return payload.daily_series && payload.daily_series[selectedYear] && payload.daily_series[selectedYear][normalizedMonth]
					? payload.daily_series[selectedYear][normalizedMonth]
					: [];
			};
			var getDailyCostSeriesForMonth = function(year, monthNumber, type){
				var selectedYear = String(year || "");
				var selectedType = String(type || "all");
				var normalizedMonth = Number(monthNumber || 0);
				if (selectedType !== "all") {
					return payload.daily_cost_series_by_type && payload.daily_cost_series_by_type[selectedType] && payload.daily_cost_series_by_type[selectedType][selectedYear] && payload.daily_cost_series_by_type[selectedType][selectedYear][normalizedMonth]
						? payload.daily_cost_series_by_type[selectedType][selectedYear][normalizedMonth]
						: [];
				}
				return payload.daily_cost_series && payload.daily_cost_series[selectedYear] && payload.daily_cost_series[selectedYear][normalizedMonth]
					? payload.daily_cost_series[selectedYear][normalizedMonth]
					: [];
			};
			var getCountForYear = function(year, type){
				var selectedYear = String(year || "");
				var selectedType = String(type || "all");
				if (selectedType !== "all") {
					return payload.counts_by_type && payload.counts_by_type[selectedType] && payload.counts_by_type[selectedType][selectedYear]
						? Number(payload.counts_by_type[selectedType][selectedYear])
						: 0;
				}
				return payload.counts && payload.counts[selectedYear] ? Number(payload.counts[selectedYear]) : 0;
			};
			var getCountForMonth = function(year, monthNumber, type){
				var selectedYear = String(year || "");
				var selectedType = String(type || "all");
				var normalizedMonth = Number(monthNumber || 0);
				if (selectedType !== "all") {
					return payload.counts_monthly_by_type && payload.counts_monthly_by_type[selectedType] && payload.counts_monthly_by_type[selectedType][selectedYear] && payload.counts_monthly_by_type[selectedType][selectedYear][normalizedMonth]
						? Number(payload.counts_monthly_by_type[selectedType][selectedYear][normalizedMonth])
						: 0;
				}
				return payload.counts_monthly && payload.counts_monthly[selectedYear] && payload.counts_monthly[selectedYear][normalizedMonth]
					? Number(payload.counts_monthly[selectedYear][normalizedMonth])
					: 0;
			};
			var getQuarterMonths = function(quarter){
				switch (String(quarter || "all")) {
					case "1":
						return [1, 2, 3];
					case "2":
						return [4, 5, 6];
					case "3":
						return [7, 8, 9];
					case "4":
						return [10, 11, 12];
					default:
						return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
				}
			};
			var monthBelongsToQuarter = function(monthNumber, quarter){
				var normalizedMonth = Number(monthNumber || 0);
				if (String(quarter || "all") === "all") return true;
				return getQuarterMonths(quarter).indexOf(normalizedMonth) !== -1;
			};
			var quarterLabelFor = function(quarter){
				switch (String(quarter || "all")) {
					case "1":
						return "1. Quartal";
					case "2":
						return "2. Quartal";
					case "3":
						return "3. Quartal";
					case "4":
						return "4. Quartal";
					default:
						return "";
				}
			};
			var countForQuarter = function(year, quarter, type){
				return sumIntegerSeries(getQuarterMonths(quarter).map(function(monthNumber){
					return getCountForMonth(year, monthNumber, type);
				}));
			};
			var getContextCount = function(year, quarter, month, type){
				var selectedYear = String(year || "");
				var selectedQuarter = String(quarter || "all");
				var selectedMonth = String(month || "all");
				var selectedType = String(type || "all");

				if (selectedMonth !== "all") {
					var normalizedMonth = Number(selectedMonth || 0);
					if (!monthBelongsToQuarter(normalizedMonth, selectedQuarter)) {
						return 0;
					}
					return getCountForMonth(selectedYear, normalizedMonth, selectedType);
				}

				if (selectedQuarter !== "all") {
					return countForQuarter(selectedYear, selectedQuarter, selectedType);
				}

				return getCountForYear(selectedYear, selectedType);
			};
			var formatOptionLabel = function(label, count){
				var normalizedCount = Number(count || 0);
				return normalizedCount > 0 ? (String(label) + " (" + normalizedCount + ")") : String(label);
			};
			var updateYearOptions = function(){
				var selectedQuarter = String(quarterSelect.value || "all");
				var selectedMonth = String(monthSelect.value || "all");
				var selectedType = String(typeSelect.value || "all");
				Array.prototype.forEach.call(yearSelect.options, function(option){
					var year = String(option.value || "");
					var count = getContextCount(year, selectedQuarter, selectedMonth, selectedType);
					option.textContent = formatOptionLabel(year, count);
				});
			};
			var updateQuarterOptions = function(){
				var selectedYear = String(yearSelect.value || "");
				var selectedMonth = String(monthSelect.value || "all");
				var selectedType = String(typeSelect.value || "all");
				Array.prototype.forEach.call(quarterSelect.options, function(option){
					if (option.value === "all") {
						option.textContent = formatOptionLabel("alle Quartale", getContextCount(selectedYear, "all", selectedMonth, selectedType));
						return;
					}
					option.textContent = formatOptionLabel(quarterLabelFor(option.value), getContextCount(selectedYear, option.value, selectedMonth, selectedType));
				});
			};
			var updateTypeOptions = function(){
				var selectedYear = String(yearSelect.value || "");
				var selectedQuarter = String(quarterSelect.value || "all");
				var selectedMonth = String(monthSelect.value || "all");
				var previousValue = String(typeSelect.value || "all");
				typeSelect.innerHTML = "";

				var allOption = document.createElement("option");
				allOption.value = "all";
				allOption.textContent = formatOptionLabel("alle Belegtypen", getContextCount(selectedYear, selectedQuarter, selectedMonth, "all"));
				typeSelect.appendChild(allOption);

				typeMeta.forEach(function(typeItem){
					var option = document.createElement("option");
					var typeValue = String(typeItem.value || "");
					option.value = typeValue;
					option.textContent = formatOptionLabel(String(typeItem.label || typeValue), getContextCount(selectedYear, selectedQuarter, selectedMonth, typeValue));
					typeSelect.appendChild(option);
				});

				var hasValue = Array.prototype.some.call(typeSelect.options, function(option){
					return option.value === previousValue;
				});
				typeSelect.value = hasValue ? previousValue : "all";
			};
			var updateMonthOptions = function(){
				var selectedYear = String(yearSelect.value || "");
				var selectedType = String(typeSelect.value || "all");
				var allowedMonths = getQuarterMonths(quarterSelect.value);
				var previousValue = String(monthSelect.value || "all");
				monthSelect.innerHTML = "";

				var allOption = document.createElement("option");
				allOption.value = "all";
				allOption.textContent = formatOptionLabel("alle Monate", getContextCount(selectedYear, quarterSelect.value, "all", selectedType));
				monthSelect.appendChild(allOption);

				allowedMonths.forEach(function(monthNumber){
					var option = document.createElement("option");
					option.value = String(monthNumber);
					var monthLabel = Array.isArray(payload.labels) && payload.labels[monthNumber - 1]
						? payload.labels[monthNumber - 1]
						: String(monthNumber);
					var monthCount = getCountForMonth(selectedYear, monthNumber, selectedType);
					option.textContent = formatOptionLabel(monthLabel, monthCount);
					monthSelect.appendChild(option);
				});

				var normalizedValue = previousValue === "all" ? "all" : String(Number(previousValue || 0));
				var hasValue = Array.prototype.some.call(monthSelect.options, function(option){
					return option.value === normalizedValue;
				});
				monthSelect.value = hasValue ? normalizedValue : "all";
			};
			var getFilters = function(){
				return {
					year: String(yearSelect.value || ""),
					quarter: String(quarterSelect.value || "all"),
					month: String(monthSelect.value || "all"),
					type: String(typeSelect.value || "all"),
					compare: !!compareCheckbox.checked
				};
			};
			window.cmxMonitorGetFilters = getFilters;
			var emitFiltersChanged = function(){
				document.dispatchEvent(new CustomEvent("cmx-monitor-filters-changed", {
					detail: getFilters()
				}));
			};
			var compareYearFor = function(selectedYear, selectedType){
				var previousYear = String(Number(selectedYear || 0) - 1);
				if (!previousYear || previousYear === "0") return "";
				if (String(selectedType || "all") !== "all") {
					return payload.series_by_type && payload.series_by_type[String(selectedType || "")] && payload.series_by_type[String(selectedType || "")][previousYear]
						? previousYear
						: "";
				}
				return payload.series && payload.series[previousYear] ? previousYear : "";
			};
			var daysInMonth = function(year, month){
				var y = Number(year || 0);
				var m = Number(month || 0);
				if (!y || !m) return 31;
				return new Date(y, m, 0).getDate();
			};
			var rangeLabelsForMonth = function(totalDays){
				var labels = [];
				for (var day = 1; day <= totalDays; day += 1) {
					labels.push(String(day));
				}
				return labels;
			};
			var buildContextData = function(){
				var filters = getFilters();
				var selectedYear = filters.year;
				var selectedQuarter = filters.quarter;
				var selectedMonth = filters.month;
				var selectedType = filters.type;
				var selectedTypeLabel = typeLabelFor(selectedType);
				var revenueSeries = Array.isArray(getSeriesForYear(selectedYear, selectedType)) ? getSeriesForYear(selectedYear, selectedType).slice() : [];
				var costSeries = Array.isArray(getCostSeriesForYear(selectedYear, selectedType)) ? getCostSeriesForYear(selectedYear, selectedType).slice() : [];
				var compareYear = filters.compare ? compareYearFor(selectedYear, selectedType) : "";
				var compareRevenueSeries = compareYear ? (Array.isArray(getSeriesForYear(compareYear, selectedType)) ? getSeriesForYear(compareYear, selectedType).slice() : []) : [];
				var compareCostSeries = compareYear ? (Array.isArray(getCostSeriesForYear(compareYear, selectedType)) ? getCostSeriesForYear(compareYear, selectedType).slice() : []) : [];
				var labels = Array.isArray(payload.labels) ? payload.labels.slice() : [];
				var selectedCount = getCountForYear(selectedYear, selectedType) || countSeries(revenueSeries);
				var totalLabelText = "Umsatz " + (selectedTypeLabel ? selectedTypeLabel + " " : "") + selectedYear;
				var compareRevenueLabelText = compareYear ? ("Umsatz " + (selectedTypeLabel ? selectedTypeLabel + " " : "") + compareYear) : "Vergleich";

				if (selectedMonth !== "all") {
					var monthNumber = Number(selectedMonth || 0);
					var totalDays = daysInMonth(selectedYear, monthNumber);
					if (compareYear) {
						totalDays = Math.max(totalDays, daysInMonth(compareYear, monthNumber));
					}
					labels = rangeLabelsForMonth(totalDays);
					revenueSeries = getDailySeriesForMonth(selectedYear, monthNumber, selectedType).slice(0, totalDays);
					costSeries = getDailyCostSeriesForMonth(selectedYear, monthNumber, selectedType).slice(0, totalDays);
					compareRevenueSeries = compareYear ? getDailySeriesForMonth(compareYear, monthNumber, selectedType).slice(0, totalDays) : [];
					compareCostSeries = compareYear ? getDailyCostSeriesForMonth(compareYear, monthNumber, selectedType).slice(0, totalDays) : [];
					selectedCount = getCountForMonth(selectedYear, monthNumber, selectedType) || countSeries(revenueSeries);
					var monthLabel = Array.isArray(payload.labels) && payload.labels[monthNumber - 1]
						? payload.labels[monthNumber - 1]
						: String(monthNumber);
					totalLabelText = "Umsatz " + (selectedTypeLabel ? selectedTypeLabel + " " : "") + monthLabel + " " + selectedYear;
					compareRevenueLabelText = compareYear ? ("Umsatz " + (selectedTypeLabel ? selectedTypeLabel + " " : "") + monthLabel + " " + compareYear) : "Vergleich";
				} else if (selectedQuarter !== "all") {
					var quarterMonths = getQuarterMonths(selectedQuarter);
					var quarterLabel = quarterLabelFor(selectedQuarter);
					labels = quarterMonths.map(function(monthNumber){
						return Array.isArray(payload.labels) && payload.labels[monthNumber - 1]
							? payload.labels[monthNumber - 1]
							: String(monthNumber);
					});
					revenueSeries = quarterMonths.map(function(monthNumber){
						return Number(revenueSeries[monthNumber - 1] || 0);
					});
					costSeries = quarterMonths.map(function(monthNumber){
						return Number(costSeries[monthNumber - 1] || 0);
					});
					compareRevenueSeries = quarterMonths.map(function(monthNumber){
						return Number(compareRevenueSeries[monthNumber - 1] || 0);
					});
					compareCostSeries = quarterMonths.map(function(monthNumber){
						return Number(compareCostSeries[monthNumber - 1] || 0);
					});
					selectedCount = sumIntegerSeries(quarterMonths.map(function(monthNumber){
						return getCountForMonth(selectedYear, monthNumber, selectedType);
					}));
					totalLabelText = "Umsatz " + (selectedTypeLabel ? selectedTypeLabel + " " : "") + quarterLabel + " " + selectedYear;
					compareRevenueLabelText = compareYear ? ("Umsatz " + (selectedTypeLabel ? selectedTypeLabel + " " : "") + quarterLabel + " " + compareYear) : "Vergleich";
				}

				return {
					labels: labels,
					selectedYear: selectedYear,
					compareYear: compareYear,
					selectedTypeLabel: selectedTypeLabel,
					revenueSeries: revenueSeries,
					costSeries: costSeries,
					compareRevenueSeries: compareRevenueSeries,
					compareCostSeries: compareCostSeries,
					selectedCount: selectedCount,
					totalLabelText: totalLabelText,
					compareRevenueLabelText: compareRevenueLabelText
				};
			};
			var chart = new Chart(ctx, {
				type: "line",
				data: {
					labels: payload.labels,
					datasets: []
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: "index",
						intersect: false
					},
					plugins: {
						legend: {
							position: "top",
							labels: {
								usePointStyle: true,
								boxWidth: 10,
								color: "#344054"
							}
						},
						tooltip: {
							backgroundColor: "rgba(22,32,51,0.92)",
							padding: 10,
							callbacks: {
								label: function(context){
									var label = context.dataset && context.dataset.label ? String(context.dataset.label) : "";
									return label + ": " + formatNumber(context.parsed && typeof context.parsed.y !== "undefined" ? context.parsed.y : 0);
								}
							}
						}
					},
					scales: {
						x: {
							grid: {
								color: "#eef3f8"
							},
							ticks: {
								color: "#667085"
							}
						},
						y: {
							type: "linear",
							position: "left",
							grid: {
								color: "#e7eef7"
							},
							ticks: {
								color: "#4f86c6",
								callback: function(value){
									return formatAxisNumber(value);
								}
							},
							title: {
								display: true,
								text: "Belegsumme"
							}
						}
					}
				}
			});
			var costChart = costCtx ? new Chart(costCtx, {
				type: "line",
				data: {
					labels: payload.labels,
					datasets: []
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: "index",
						intersect: false
					},
					plugins: {
						legend: {
							position: "top",
							labels: {
								usePointStyle: true,
								boxWidth: 10,
								color: "#344054"
							}
						},
						tooltip: {
							backgroundColor: "rgba(22,32,51,0.92)",
							padding: 10,
							callbacks: {
								label: function(context){
									var label = context.dataset && context.dataset.label ? String(context.dataset.label) : "";
									return label + ": " + formatNumber(context.parsed && typeof context.parsed.y !== "undefined" ? context.parsed.y : 0);
								}
							}
						}
					},
					scales: {
						x: {
							grid: {
								color: "#eef3f8"
							},
							ticks: {
								color: "#667085"
							}
						},
						y: {
							type: "linear",
							position: "left",
							grid: {
								color: "#e7eef7"
							},
							ticks: {
								color: "#4f86c6",
								callback: function(value){
									return formatAxisNumber(value);
								}
							},
							title: {
								display: true,
								text: "Betrag"
							}
						}
					}
				}
			}) : null;
			var profitChart = profitCtx ? new Chart(profitCtx, {
				type: "line",
				data: {
					labels: payload.labels,
					datasets: []
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: "index",
						intersect: false
					},
					plugins: {
						legend: {
							position: "top",
							labels: {
								usePointStyle: true,
								boxWidth: 10,
								color: "#344054"
							}
						},
						tooltip: {
							backgroundColor: "rgba(22,32,51,0.92)",
							padding: 10,
							callbacks: {
								label: function(context){
									var label = context.dataset && context.dataset.label ? String(context.dataset.label) : "";
									var value = context.parsed && typeof context.parsed.y !== "undefined" ? context.parsed.y : 0;
									return label + ": " + (String(context.dataset.yAxisID || "") === "y1" ? formatPercent(value) : formatNumber(value));
								}
							}
						}
					},
					scales: {
						x: {
							grid: {
								color: "#eef3f8"
							},
							ticks: {
								color: "#667085"
							}
						},
						y: {
							type: "linear",
							position: "left",
							grid: {
								color: "#e7eef7"
							},
							ticks: {
								color: "#0f766e",
								callback: function(value){
									return formatAxisNumber(value);
								}
							},
							title: {
								display: true,
								text: "Deckungsbeitrag"
							}
						},
						y1: {
							type: "linear",
							position: "right",
							grid: {
								drawOnChartArea: false
							},
							ticks: {
								color: "#7c3aed",
								callback: function(value){
									return formatPercentAxis(value) + "%";
								}
							},
							title: {
								display: true,
								text: "Marge %"
							}
						}
					}
				}
			}) : null;
			var updateOverview = function(context){
				var revenueTotal = sumSeries(context.revenueSeries);
				var costTotal = sumSeries(context.costSeries);
				var profitTotal = revenueTotal - costTotal;
				var marginTotal = revenueTotal !== 0 ? ((profitTotal / revenueTotal) * 100) : 0;
				var compareRevenueTotal = sumSeries(context.compareRevenueSeries);
				var compareCostTotal = sumSeries(context.compareCostSeries);
				var compareProfitTotal = compareRevenueTotal - compareCostTotal;
				var compareMarginTotal = compareRevenueTotal !== 0 ? ((compareProfitTotal / compareRevenueTotal) * 100) : 0;
				var compareYear = String(context.compareYear || "");

				if (overviewTotalEl) overviewTotalEl.textContent = formatNumber(revenueTotal);
				if (overviewCostEl) overviewCostEl.textContent = formatNumber(costTotal);
				if (overviewProfitEl) overviewProfitEl.textContent = formatNumber(profitTotal);
				if (overviewMarginEl) overviewMarginEl.textContent = formatPercent(marginTotal);

				var setCompareMeta = function(boxEl, yearEl, valueEl, deltaEl, yearText, compareValue, deltaValue, isPercent){
					if (!boxEl || !yearEl || !valueEl || !deltaEl) return;

					if (!yearText) {
						boxEl.hidden = true;
						boxEl.classList.remove("is-active");
						yearEl.textContent = "";
						valueEl.textContent = "";
						deltaEl.textContent = "";
						deltaEl.classList.remove("is-positive", "is-negative");
						return;
					}

					boxEl.hidden = false;
					boxEl.classList.add("is-active");
					yearEl.textContent = yearText;
					valueEl.textContent = isPercent ? formatPercent(compareValue) : formatNumber(compareValue);
					deltaEl.textContent = isPercent ? formatSignedPercentPoints(deltaValue) : formatSignedNumber(deltaValue);
					deltaEl.classList.remove("is-positive", "is-negative");
					if (Number(deltaValue) > 0) {
						deltaEl.classList.add("is-positive");
					} else if (Number(deltaValue) < 0) {
						deltaEl.classList.add("is-negative");
					}
				};

				setCompareMeta(
					overviewTotalCompareBoxEl,
					overviewTotalCompareYearEl,
					overviewTotalCompareEl,
					overviewTotalDeltaEl,
					compareYear,
					compareRevenueTotal,
					revenueTotal - compareRevenueTotal,
					false
				);
				setCompareMeta(
					overviewCostCompareBoxEl,
					overviewCostCompareYearEl,
					overviewCostCompareEl,
					overviewCostDeltaEl,
					compareYear,
					compareCostTotal,
					costTotal - compareCostTotal,
					false
				);
				setCompareMeta(
					overviewProfitCompareBoxEl,
					overviewProfitCompareYearEl,
					overviewProfitCompareEl,
					overviewProfitDeltaEl,
					compareYear,
					compareProfitTotal,
					profitTotal - compareProfitTotal,
					false
				);
				setCompareMeta(
					overviewMarginCompareBoxEl,
					overviewMarginCompareYearEl,
					overviewMarginCompareEl,
					overviewMarginDeltaEl,
					compareYear,
					compareMarginTotal,
					marginTotal - compareMarginTotal,
					true
				);
			};
			var updateMainChart = function(context){
				var datasets = [
					{
						label: context.selectedYear,
						data: context.revenueSeries,
						yAxisID: "y",
						borderColor: "#4f86c6",
						backgroundColor: "rgba(79,134,198,0.14)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 4,
						pointHoverRadius: 5
					}
				];

				if (context.compareYear && context.compareRevenueSeries.length) {
					datasets.push({
						label: context.compareYear,
						data: context.compareRevenueSeries,
						yAxisID: "y",
						borderColor: "#ef7d00",
						backgroundColor: "rgba(239,125,0,0.10)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 4,
						pointHoverRadius: 5
					});
				}

				chart.data.labels = context.labels;
				chart.data.datasets = datasets;
				chart.update();

				if (totalEl) totalEl.textContent = formatNumber(sumSeries(context.revenueSeries));
				if (totalLabelEl) totalLabelEl.textContent = context.totalLabelText;
				if (compareEl) compareEl.textContent = context.compareYear ? formatNumber(sumSeries(context.compareRevenueSeries)) : "0.00";
				if (compareLabelEl) compareLabelEl.textContent = context.compareRevenueLabelText;
				if (countEl) countEl.textContent = String(context.selectedCount);
				if (modeEl) modeEl.textContent = context.compareYear ? "aktiv" : "aus";
			};
			var updateCostChart = function(context){
				if (!costChart) return;

				var datasets = [
					{
						label: "Umsatz " + context.selectedYear,
						data: context.revenueSeries,
						borderColor: "#4f86c6",
						backgroundColor: "rgba(79,134,198,0.10)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 3,
						pointHoverRadius: 5
					},
					{
						label: "Aufwand " + context.selectedYear,
						data: context.costSeries,
						borderColor: "#d92d20",
						backgroundColor: "rgba(217,45,32,0.10)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 3,
						pointHoverRadius: 5
					}
				];

				if (context.compareYear) {
					datasets.push({
						label: "Umsatz " + context.compareYear,
						data: context.compareRevenueSeries,
						borderColor: "#9fc5ee",
						backgroundColor: "rgba(159,197,238,0.08)",
						fill: false,
						tension: 0.35,
						borderWidth: 2,
						borderDash: [6, 5],
						pointRadius: 2,
						pointHoverRadius: 4
					});
					datasets.push({
						label: "Aufwand " + context.compareYear,
						data: context.compareCostSeries,
						borderColor: "#f97066",
						backgroundColor: "rgba(249,112,102,0.08)",
						fill: false,
						tension: 0.35,
						borderWidth: 2,
						borderDash: [6, 5],
						pointRadius: 2,
						pointHoverRadius: 4
					});
				}

				costChart.data.labels = context.labels;
				costChart.data.datasets = datasets;
				costChart.update();
			};
			var updateProfitChart = function(context){
				if (!profitChart) return;

				var profitSeries = differenceSeries(context.revenueSeries, context.costSeries);
				var marginValues = marginSeries(context.revenueSeries, context.costSeries);
				var datasets = [
					{
						label: "Deckungsbeitrag " + context.selectedYear,
						data: profitSeries,
						yAxisID: "y",
						borderColor: "#0f766e",
						backgroundColor: "rgba(15,118,110,0.10)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 3,
						pointHoverRadius: 5
					},
					{
						label: "Marge % " + context.selectedYear,
						data: marginValues,
						yAxisID: "y1",
						borderColor: "#7c3aed",
						backgroundColor: "rgba(124,58,237,0.10)",
						fill: false,
						tension: 0.35,
						borderWidth: 3,
						pointRadius: 3,
						pointHoverRadius: 5
					}
				];

				if (context.compareYear) {
					datasets.push({
						label: "Deckungsbeitrag " + context.compareYear,
						data: differenceSeries(context.compareRevenueSeries, context.compareCostSeries),
						yAxisID: "y",
						borderColor: "#6dd3c8",
						backgroundColor: "rgba(109,211,200,0.08)",
						fill: false,
						tension: 0.35,
						borderWidth: 2,
						borderDash: [6, 5],
						pointRadius: 2,
						pointHoverRadius: 4
					});
					datasets.push({
						label: "Marge % " + context.compareYear,
						data: marginSeries(context.compareRevenueSeries, context.compareCostSeries),
						yAxisID: "y1",
						borderColor: "#b692f6",
						backgroundColor: "rgba(182,146,246,0.08)",
						fill: false,
						tension: 0.35,
						borderWidth: 2,
						borderDash: [6, 5],
						pointRadius: 2,
						pointHoverRadius: 4
					});
				}

				profitChart.data.labels = context.labels;
				profitChart.data.datasets = datasets;
				profitChart.update();
			};
			var updateDashboard = function(){
				var context = buildContextData();
				updateOverview(context);
				updateMainChart(context);
				updateCostChart(context);
				updateProfitChart(context);
			};

			yearSelect.addEventListener("change", function(){
				quarterSelect.value = "all";
				monthSelect.value = "all";
				updateTypeOptions();
				updateYearOptions();
				updateQuarterOptions();
				updateMonthOptions();
				updateDashboard();
				emitFiltersChanged();
			});
			quarterSelect.addEventListener("change", function(){
				updateTypeOptions();
				updateYearOptions();
				updateQuarterOptions();
				updateMonthOptions();
				updateDashboard();
				emitFiltersChanged();
			});
			monthSelect.addEventListener("change", function(){
				updateTypeOptions();
				updateYearOptions();
				updateQuarterOptions();
				updateDashboard();
				emitFiltersChanged();
			});
			typeSelect.addEventListener("change", function(){
				updateYearOptions();
				updateQuarterOptions();
				updateMonthOptions();
				updateDashboard();
				emitFiltersChanged();
			});
			compareCheckbox.addEventListener("change", function(){
				updateDashboard();
				emitFiltersChanged();
			});
			resetLink.addEventListener("click", function(event){
				event.preventDefault();
				if (yearSelect.options.length > 0) {
					yearSelect.selectedIndex = 0;
				}
				quarterSelect.value = "all";
				monthSelect.value = "all";
				typeSelect.value = "all";
				compareCheckbox.checked = false;
				updateTypeOptions();
				updateYearOptions();
				updateQuarterOptions();
				updateMonthOptions();
				updateDashboard();
				emitFiltersChanged();
			});
			updateTypeOptions();
			updateYearOptions();
			updateQuarterOptions();
			updateMonthOptions();
			updateDashboard();
			emitFiltersChanged();
		};

		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", init);
			return;
		}
		init();
	})();';
	\wp_add_inline_script('cmx-chartjs', $chart_script, 'after');
});
