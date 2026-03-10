<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


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
			.mb-monitor-overview-indicator{
				display:none;
				width:0;
				height:0;
				margin-right:8px;
				vertical-align:middle;
				position:relative;
				top:-2px;
			}
			.mb-monitor-overview-indicator.is-up{
				display:inline-block;
				border-left:9px solid transparent;
				border-right:9px solid transparent;
				border-bottom:15px solid #16a34a;
			}
			.mb-monitor-overview-indicator.is-down{
				display:inline-block;
				border-left:9px solid transparent;
				border-right:9px solid transparent;
				border-top:15px solid #b42318;
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
			.mb-monitor-card-head{
				display:flex;
				align-items:center;
				justify-content:space-between;
				gap:12px;
				margin:0 0 8px;
			}
			.mb-monitor-card-head h3{
				margin:0;
				display:inline-flex;
				align-items:center;
				gap:8px;
				cursor:pointer;
			}
			.mb-monitor-cpt-link{
				display:inline-flex;
				align-items:center;
				justify-content:center;
				width:18px;
				height:18px;
				color:#667085;
				text-decoration:none;
			}
			.mb-monitor-cpt-link:hover{
				color:#2271b1;
			}
			.mb-monitor-cpt-link .dashicons{
				font-size:16px;
				width:16px;
				height:16px;
				line-height:16px;
			}
			.mb-monitor-card-toggle{
				display:inline-flex;
				align-items:center;
				justify-content:center;
				width:28px;
				height:28px;
				padding:0;
				border:1px solid #d0d9e5;
				border-radius:8px;
				background:#fff;
				color:#667085;
				cursor:pointer;
			}
			.mb-monitor-card-toggle:hover{
				background:#edf5ff;
				color:#2271b1;
			}
			.mb-monitor-card-toggle .dashicons{
				font-size:16px;
				width:16px;
				height:16px;
				line-height:16px;
				transition:transform .16s ease;
			}
			.mb-monitor-card-toggle[aria-expanded="false"] .dashicons{
				transform:rotate(180deg);
			}
			.mb-monitor-collapsible-body.is-collapsed{
				display:none;
			}
			.mb-monitor-deckungsbeitrag-group{
				display:flex;
				flex-direction:column;
				gap:14px;
			}
			.mb-monitor-nested-card{
				background:rgba(255,255,255,.88);
				border:1px solid #d9e6f6;
				border-radius:12px;
				padding:16px;
				box-shadow:none;
			}
			.mb-monitor-article-table-wrap{
				overflow:auto;
			}
			.mb-monitor-article-table{
				width:100%;
				border-collapse:collapse;
				table-layout:fixed;
			}
			.mb-monitor-article-table col.col-article{
				width:42%;
			}
			.mb-monitor-article-table col.col-num{
				width:14.5%;
			}
			.mb-monitor-article-table th,
			.mb-monitor-article-table td{
				padding:5px 10px;
				border-top:0;
				font-size:13px;
				vertical-align:top;
				transition:background-color .12s ease;
			}
			.mb-monitor-article-table tr:first-child th,
			.mb-monitor-article-table tr:first-child td{
				border-top:0;
			}
			.mb-monitor-article-table th{
				text-align:left;
				font-size:11px;
				letter-spacing:.04em;
				text-transform:uppercase;
				color:#98a2b3;
				font-weight:700;
			}
			.mb-monitor-sort-button{
				display:inline-flex;
				align-items:center;
				gap:6px;
				padding:0;
				border:0;
				background:transparent;
				color:inherit;
				font:inherit;
				letter-spacing:inherit;
				text-transform:inherit;
				cursor:pointer;
			}
			.mb-monitor-sort-button:hover{
				color:#667085;
			}
			.mb-monitor-sort-button.is-active{
				color:#667085;
			}
			.mb-monitor-sort-button .mb-monitor-sort-indicator{
				display:inline-block;
				min-width:10px;
				font-size:10px;
				line-height:1;
			}
			.mb-monitor-article-table td.is-num{
				text-align:right;
				white-space:nowrap;
				font-variant-numeric:tabular-nums;
			}
			.mb-monitor-article-table th.is-num{
				text-align:right;
			}
			.mb-monitor-article-table th.is-num .mb-monitor-sort-button{
				width:100%;
				justify-content:flex-end;
				position:relative;
				left:20px;
			}
			.mb-monitor-article-table td:first-child{
				padding-right:24px;
			}
			.mb-monitor-article-table tbody tr:hover td{
				background:#edf5ff;
			}
			.mb-monitor-article-link{
				color:#162033;
				text-decoration:none;
				font-weight:600;
			}
			.mb-monitor-article-link:hover{
				text-decoration:underline;
			}
			.mb-monitor-article-meta{
				display:block;
				margin-top:2px;
				font-size:11px;
				color:#98a2b3;
			}
			.mb-monitor-article-profit-negative{
				color:#b42318;
			}
			.mb-monitor-article-profit-positive{
				color:#166534;
			}
			.mb-monitor-article-empty{
				padding:10px 0 2px;
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_artikel_unit_price')) {
	function cmx_cockpit_view_monitor_artikel_unit_price(int $artikel_id): float {
		static $cache = [];

		if ($artikel_id <= 0) {
			return 0.0;
		}
		if (isset($cache[$artikel_id])) {
			return (float) $cache[$artikel_id];
		}

		$vk_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK')) {
			$vk_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK');
		}
		$vk_keys[] = '_cmx_artikel_vk';

		foreach (\array_unique($vk_keys) as $meta_key) {
			$raw = \get_post_meta($artikel_id, $meta_key, true);
			if ($raw === '' || $raw === null) {
				continue;
			}
			$cache[$artikel_id] = \round(cmx_cockpit_view_monitor_parse_number($raw), 2);
			return (float) $cache[$artikel_id];
		}

		$cache[$artikel_id] = 0.0;
		return 0.0;
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

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_project_task_timestamp')) {
	function cmx_cockpit_view_monitor_project_task_timestamp(array $task): int {
		$raw = \trim((string) ($task['datum'] ?? ''));
		if ($raw === '') {
			return 0;
		}

		foreach (['Y-m-d', 'd.m.Y', 'Ymd', 'Y-m-d H:i:s'] as $format) {
			$dt = \DateTime::createFromFormat($format, $raw);
			if ($dt instanceof \DateTime) {
				return (int) $dt->getTimestamp();
			}
		}

		$ts = \strtotime($raw);
		return $ts ? (int) $ts : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_position_discount')) {
	function cmx_cockpit_view_monitor_position_discount(float $subtotal, string $discount_raw): float {
		$txt = \strtolower(\trim($discount_raw));
		if ($txt === '') {
			return 0.0;
		}

		if (\preg_match('~([\-−]?\d+[.,]?\d*)\s*%~u', $txt, $matches)) {
			$pct = \abs(cmx_cockpit_view_monitor_parse_number($matches[1] ?? 0));
			return $pct > 0 ? (\abs($subtotal) * ($pct / 100)) : 0.0;
		}

		$clean = (string) \preg_replace('~(chf|fr\.?)~i', '', $txt);
		$amount = \abs(cmx_cockpit_view_monitor_parse_number($clean));
		return \min($amount, \abs($subtotal));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_position_revenue')) {
	function cmx_cockpit_view_monitor_position_revenue(array $row): float {
		$qty = cmx_cockpit_view_monitor_parse_number($row['menge'] ?? 0);
		$price = cmx_cockpit_view_monitor_parse_number($row['preis'] ?? 0);
		$subtotal = $qty * $price;
		$discount = cmx_cockpit_view_monitor_position_discount($subtotal, (string) ($row['rabatt'] ?? ''));
		$line_total = $subtotal >= 0 ? ($subtotal - $discount) : ($subtotal + $discount);
		return \round($line_total, 2);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_article_meta')) {
	function cmx_cockpit_view_monitor_article_meta(int $artikel_id, array $row = []): array {
		$title = '';
		if ($artikel_id > 0) {
			$title = (string) (\get_the_title($artikel_id) ?: '');
		}
		if ($title === '') {
			$title = (string) ($row['artikel_name'] ?? $row['title'] ?? $row['item'] ?? '');
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')) {
			$title = (string) cmx_beleg_decode_label_text($title);
		}

		$number = '';
		if ($artikel_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_get_artikel_nr')) {
			$number = (string) cmx_get_artikel_nr($artikel_id);
		}
		if ($number === '' && $artikel_id > 0) {
			foreach (['cmx_artikel_sku', '_cmx_artikel_sku', '_cmx_artikel_nr', '_sku'] as $meta_key) {
				$number = (string) \get_post_meta($artikel_id, $meta_key, true);
				if ($number !== '') {
					break;
				}
			}
		}

		return [
			'title' => \trim($title),
			'number' => \trim($number),
			'edit_link' => $artikel_id > 0 ? (string) (\get_edit_post_link($artikel_id, '') ?: '') : '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_contact_meta')) {
	function cmx_cockpit_view_monitor_contact_meta(int $post_id): array {
		$kontakt_id_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_id_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_id_keys = \array_merge($kontakt_id_keys, [
			'_cmx_beleg_kontakt_id',
			'cmx_beleg_kontakt_id',
		]);

		$kontakt_id = 0;
		foreach (\array_unique($kontakt_id_keys) as $meta_key) {
			$kontakt_id = (int) \get_post_meta($post_id, $meta_key, true);
			if ($kontakt_id > 0) {
				break;
			}
		}

		if ($kontakt_id > 0 && !cmx_cockpit_view_monitor_post_is_published($kontakt_id)) {
			return [
				'contact_id' => 0,
				'contact_title' => '',
				'edit_link' => '',
			];
		}

		$kontakt_title = '';
		if ($kontakt_id > 0) {
			$kontakt_title = (string) (\get_the_title($kontakt_id) ?: '');
		}

		if ($kontakt_title === '') {
			$kontakt_label_keys = [];
			if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL')) {
				$kontakt_label_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_LABEL');
			}
			$kontakt_label_keys = \array_merge($kontakt_label_keys, [
				'_cmx_beleg_kontakt_label',
				'cmx_beleg_kontakt_label',
			]);

			foreach (\array_unique($kontakt_label_keys) as $meta_key) {
				$kontakt_title = \trim((string) \get_post_meta($post_id, $meta_key, true));
				if ($kontakt_title !== '') {
					break;
				}
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')) {
			$kontakt_title = (string) cmx_beleg_decode_label_text($kontakt_title);
		}

		$kontakt_title = \trim($kontakt_title);

		return [
			'contact_id' => $kontakt_id,
			'contact_title' => $kontakt_title,
			'edit_link' => $kontakt_id > 0 ? (string) (\get_edit_post_link($kontakt_id, '') ?: '') : '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_project_meta')) {
	function cmx_cockpit_view_monitor_project_meta(int $post_id): array {
		$projekt_id_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_ID')) {
			$projekt_id_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_ID');
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_meta_projekt_ids')) {
			$projekt_id_keys = \array_merge($projekt_id_keys, (array) cmx_meta_projekt_ids());
		}
		$projekt_id_keys = \array_merge($projekt_id_keys, [
			'_cmx_beleg_projekt_id',
			'_cmx_projekt_id',
			'_projekt_id',
		]);

		$projekt_id = 0;
		foreach (\array_unique($projekt_id_keys) as $meta_key) {
			$projekt_id = (int) \get_post_meta($post_id, $meta_key, true);
			if ($projekt_id > 0) {
				break;
			}
		}

		if ($projekt_id > 0 && !cmx_cockpit_view_monitor_post_is_published($projekt_id)) {
			return [
				'project_id' => 0,
				'project_title' => '',
				'edit_link' => '',
			];
		}

		$projekt_title = '';
		if ($projekt_id > 0) {
			$projekt_title = (string) (\get_the_title($projekt_id) ?: '');
		}

		if ($projekt_title === '') {
			$projekt_label_keys = [];
			if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_LABEL')) {
				$projekt_label_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_LABEL');
			}
			$projekt_label_keys = \array_merge($projekt_label_keys, [
				'_cmx_beleg_projekt_label',
				'_cmx_beleg_projekt',
			]);

			foreach (\array_unique($projekt_label_keys) as $meta_key) {
				$projekt_title = \trim((string) \get_post_meta($post_id, $meta_key, true));
				if ($projekt_title !== '') {
					break;
				}
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')) {
			$projekt_title = (string) cmx_beleg_decode_label_text($projekt_title);
		}

		return [
			'project_id' => $projekt_id,
			'project_title' => \trim($projekt_title),
			'edit_link' => $projekt_id > 0 ? (string) (\get_edit_post_link($projekt_id, '') ?: '') : '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_cockpit_view_monitor_post_is_published')) {
	function cmx_cockpit_view_monitor_post_is_published(int $post_id): bool {
		return $post_id > 0 && \get_post_status($post_id) === 'publish';
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
		$beleg_rows = [];
		$article_rows = [];
		$contact_rows = [];
		$project_rows = [];
		$project_task_rows = [];
		$post_type = \defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PT_BELEGE')
			: 'belege';

		$query = new \WP_Query([
			'post_type' => $post_type,
			'post_status' => 'publish',
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
			$contact_meta = cmx_cockpit_view_monitor_contact_meta((int) $post->ID);
			$project_meta = cmx_cockpit_view_monitor_project_meta((int) $post->ID);
			$beleg_title = \trim((string) (\get_the_title((int) $post->ID) ?: ''));
			if ($beleg_title === '') {
				$beleg_title = 'Beleg #' . (int) $post->ID;
			}
			if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')) {
				$beleg_title = (string) cmx_beleg_decode_label_text($beleg_title);
			}
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
			$beleg_rows[] = [
				'year' => $year,
				'month' => $month_number,
				'type' => $type_slug,
				'beleg_id' => (int) $post->ID,
				'beleg_title' => $beleg_title,
				'edit_link' => (string) (\get_edit_post_link((int) $post->ID, '') ?: ''),
				'revenue' => (float) $total,
				'cost' => (float) $cost_total,
			];

			if ((int) ($contact_meta['contact_id'] ?? 0) > 0) {
				$contact_rows[] = [
					'year' => $year,
					'month' => $month_number,
					'type' => $type_slug,
					'contact_id' => (int) ($contact_meta['contact_id'] ?? 0),
					'contact_title' => (string) ($contact_meta['contact_title'] ?? ''),
					'edit_link' => (string) ($contact_meta['edit_link'] ?? ''),
					'revenue' => (float) $total,
					'cost' => (float) $cost_total,
				];
			}
			if ((int) ($project_meta['project_id'] ?? 0) > 0) {
				$project_rows[] = [
					'year' => $year,
					'month' => $month_number,
					'type' => $type_slug,
					'project_id' => (int) ($project_meta['project_id'] ?? 0),
					'project_title' => (string) ($project_meta['project_title'] ?? ''),
					'edit_link' => (string) ($project_meta['edit_link'] ?? ''),
					'revenue' => (float) $total,
					'cost' => (float) $cost_total,
				];
			}

			foreach (cmx_cockpit_view_monitor_position_rows((int) $post->ID) as $row) {
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
				if (!cmx_cockpit_view_monitor_post_is_published($artikel_id)) {
					continue;
				}

				$qty = cmx_cockpit_view_monitor_parse_number($row['menge'] ?? 0);
				if ($qty === 0.0) {
					continue;
				}

				$article_meta = cmx_cockpit_view_monitor_article_meta($artikel_id, $row);
				$line_revenue = cmx_cockpit_view_monitor_position_revenue($row);
				$line_cost = \round($qty * cmx_cockpit_view_monitor_artikel_unit_cost($artikel_id), 2);
				$article_rows[] = [
					'year' => $year,
					'month' => $month_number,
					'type' => $type_slug,
					'article_id' => $artikel_id,
					'article_title' => (string) ($article_meta['title'] ?? ''),
					'article_number' => (string) ($article_meta['number'] ?? ''),
					'edit_link' => (string) ($article_meta['edit_link'] ?? ''),
					'revenue' => (float) $line_revenue,
					'cost' => (float) $line_cost,
				];
			}
		}

		\wp_reset_postdata();

		$project_post_type = \defined(__NAMESPACE__ . '\\CMX_PT_PROJEKTE')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PT_PROJEKTE')
			: 'projekte';
		$project_tasks_meta_key = \defined(__NAMESPACE__ . '\\CMX_PROJEKT_TASK_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_PROJEKT_TASK_META')
			: '_cmx_projekt_tasks';

		$project_query = new \WP_Query([
			'post_type' => $project_post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'no_found_rows' => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		]);

		foreach ((array) $project_query->posts as $project_post) {
			if (!$project_post instanceof \WP_Post) {
				continue;
			}
			$project_id = (int) $project_post->ID;
			if (!cmx_cockpit_view_monitor_post_is_published($project_id)) {
				continue;
			}

			$tasks = \get_post_meta($project_id, $project_tasks_meta_key, true);
			if (!\is_array($tasks) || $tasks === []) {
				continue;
			}

			$project_title = (string) (\get_the_title($project_id) ?: '');
			if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')) {
				$project_title = (string) cmx_beleg_decode_label_text($project_title);
			}
			$project_title = \trim($project_title);
			$project_edit_link = (string) (\get_edit_post_link($project_id, '') ?: '');

			foreach ($tasks as $task) {
				if (!\is_array($task)) {
					continue;
				}

				$timestamp = cmx_cockpit_view_monitor_project_task_timestamp($task);
				if ($timestamp <= 0) {
					continue;
				}

				$year = (int) \date('Y', $timestamp);
				$month_number = (int) \date('n', $timestamp);
				if ($month_number < 1 || $month_number > 12) {
					continue;
				}

				$artikel_id = (int) ($task['artikel_id'] ?? 0);
				if ($artikel_id <= 0 || !cmx_cockpit_view_monitor_post_is_published($artikel_id)) {
					continue;
				}

				$dauer = cmx_cockpit_view_monitor_parse_number($task['dauer'] ?? 0);
				if ($dauer <= 0) {
					continue;
				}

				$vk = cmx_cockpit_view_monitor_artikel_unit_price($artikel_id);
				$unit_cost = cmx_cockpit_view_monitor_artikel_unit_cost($artikel_id);
				$task_value = \round($dauer * $vk, 2);
				$task_cost = \round($dauer * $unit_cost, 2);
				$is_billable = \array_key_exists('verrechenbar', $task)
					? (\function_exists(__NAMESPACE__ . '\\cmx_projekt_truthy') ? cmx_projekt_truthy($task['verrechenbar']) : !empty($task['verrechenbar']))
					: true;
				$is_invoiced = \array_key_exists('abgerechnet', $task)
					? (\function_exists(__NAMESPACE__ . '\\cmx_projekt_truthy') ? cmx_projekt_truthy($task['abgerechnet']) : !empty($task['abgerechnet']))
					: false;

				$project_task_rows[] = [
					'year' => $year,
					'month' => $month_number,
					'project_id' => $project_id,
					'project_title' => $project_title,
					'edit_link' => $project_edit_link,
					'billed' => $is_billable && $is_invoiced ? (float) $task_value : 0.0,
					'open' => $is_billable && !$is_invoiced ? (float) $task_value : 0.0,
					'internal' => !$is_billable ? (float) $task_cost : 0.0,
				];
			}
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
			'beleg_rows' => $beleg_rows,
			'article_rows' => $article_rows,
			'contact_rows' => $contact_rows,
			'project_rows' => $project_rows,
			'project_task_rows' => $project_task_rows,
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
							<strong class="mb-kpi-value"><span class="mb-monitor-overview-indicator" id="cmx-monitor-overview-total-indicator"></span><span id="cmx-monitor-overview-total"><?php echo \esc_html(\number_format($selected_total, 2, '.', '\'')); ?></span></strong>
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
							<strong class="mb-kpi-value"><span class="mb-monitor-overview-indicator" id="cmx-monitor-overview-cost-indicator"></span><span id="cmx-monitor-overview-cost"><?php echo \esc_html(\number_format($selected_cost_total, 2, '.', '\'')); ?></span></strong>
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
							<strong class="mb-kpi-value"><span class="mb-monitor-overview-indicator" id="cmx-monitor-overview-profit-indicator"></span><span id="cmx-monitor-overview-profit"><?php echo \esc_html(\number_format($selected_profit_total, 2, '.', '\'')); ?></span></strong>
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
							<strong class="mb-kpi-value"><span class="mb-monitor-overview-indicator" id="cmx-monitor-overview-margin-indicator"></span><span id="cmx-monitor-overview-margin"><?php echo \esc_html(\number_format($selected_margin_total, 2, '.', '\'')); ?>%</span></strong>
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
				<section class="mb-card mb-card--hero mb-span-8">
					<h3>Deckungsbeiträge pro ...</h3>
					<p class="mb-monitor-chart-intro">Alle im gewählten Zeitraum.</p>
					<p class="mb-monitor-chart-intro">&nbsp;</p>
					<div class="mb-monitor-deckungsbeitrag-group">
				<section class="mb-card mb-card--soft mb-monitor-nested-card">
					<div class="mb-monitor-card-head">
						<h3><a class="mb-monitor-cpt-link" href="<?php echo esc_url(\admin_url('edit.php?post_type=artikel&cmx_view=deckungsbeitrag')); ?>" target="_blank" rel="noopener noreferrer" title="Artikel öffnen"><span class="dashicons dashicons-cart" aria-hidden="true"></span></a><span>Artikel</span></h3>
						<button type="button" class="mb-monitor-card-toggle" data-target="cmx-monitor-article-card-body" aria-expanded="false" aria-label="Deckungsbeitrag pro Artikel einklappen">
							<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
						</button>
					</div>
					<div class="mb-monitor-collapsible-body is-collapsed" id="cmx-monitor-article-card-body">
					<!-- <p class="mb-monitor-chart-intro">Artikel im gewählten Zeitraum, sortiert nach Deckungsbeitrag.</p> -->
					<div class="mb-monitor-article-table-wrap">
						<table class="mb-monitor-article-table" id="cmx-monitor-article-table">
							<colgroup>
								<col class="col-article">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
							</colgroup>
							<thead>
								<tr>
									<th><button type="button" class="mb-monitor-sort-button" data-table="article" data-key="title">Artikel<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="article" data-key="revenue">Umsatz<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="article" data-key="cost">Aufwand<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="article" data-key="profit">Deckungsbeitrag<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="article" data-key="margin">Marge %<span class="mb-monitor-sort-indicator"></span></button></th>
								</tr>
							</thead>
							<tbody id="cmx-monitor-article-rows"></tbody>
						</table>
						<p class="mb-monitor-article-empty" id="cmx-monitor-article-empty" hidden>Keine Artikeldaten im aktuellen Filter.</p>
					</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-monitor-nested-card">
					<div class="mb-monitor-card-head">
						<h3><a class="mb-monitor-cpt-link" href="<?php echo esc_url(\admin_url('edit.php?post_type=kontakte&cmx_view=deckungsbeitrag')); ?>" target="_blank" rel="noopener noreferrer" title="Kontakte öffnen"><span class="dashicons dashicons-businessman" aria-hidden="true"></span></a><span>Kunde</span></h3>
						<button type="button" class="mb-monitor-card-toggle" data-target="cmx-monitor-customer-card-body" aria-expanded="false" aria-label="Deckungsbeitrag pro Kunde einklappen">
							<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
						</button>
					</div>
					<div class="mb-monitor-collapsible-body is-collapsed" id="cmx-monitor-customer-card-body">
					<!-- <p class="mb-monitor-chart-intro">Kunden im gewählten Zeitraum, sortiert nach Deckungsbeitrag.</p> -->
					<div class="mb-monitor-article-table-wrap">
						<table class="mb-monitor-article-table" id="cmx-monitor-customer-table">
							<colgroup>
								<col class="col-article">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
							</colgroup>
							<thead>
								<tr>
									<th><button type="button" class="mb-monitor-sort-button" data-table="customer" data-key="title">Kunde<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="customer" data-key="revenue">Umsatz<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="customer" data-key="cost">Aufwand<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="customer" data-key="profit">Deckungsbeitrag<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="customer" data-key="margin">Marge %<span class="mb-monitor-sort-indicator"></span></button></th>
								</tr>
							</thead>
							<tbody id="cmx-monitor-customer-rows"></tbody>
						</table>
						<p class="mb-monitor-article-empty" id="cmx-monitor-customer-empty" hidden>Keine Kundendaten im aktuellen Filter.</p>
					</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-monitor-nested-card">
					<div class="mb-monitor-card-head">
						<h3><a class="mb-monitor-cpt-link" href="<?php echo esc_url(\admin_url('edit.php?post_type=projekte&cmx_view=deckungsbeitrag')); ?>" target="_blank" rel="noopener noreferrer" title="Projekte öffnen"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span></a><span>Projekt</span></h3>
						<button type="button" class="mb-monitor-card-toggle" data-target="cmx-monitor-project-card-body" aria-expanded="false" aria-label="Deckungsbeitrag pro Projekt einklappen">
							<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
						</button>
					</div>
					<div class="mb-monitor-collapsible-body is-collapsed" id="cmx-monitor-project-card-body">
					<!-- <p class="mb-monitor-chart-intro">Projekte im gewählten Zeitraum, sortiert nach Deckungsbeitrag.</p> -->
					<div class="mb-monitor-article-table-wrap">
						<table class="mb-monitor-article-table" id="cmx-monitor-project-table">
							<colgroup>
								<col class="col-article">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
							</colgroup>
							<thead>
								<tr>
									<th><button type="button" class="mb-monitor-sort-button" data-table="project" data-key="title">Projekt<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="project" data-key="revenue">Umsatz<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="project" data-key="cost">Aufwand<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="project" data-key="profit">Deckungsbeitrag<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="project" data-key="margin">Marge %<span class="mb-monitor-sort-indicator"></span></button></th>
								</tr>
							</thead>
							<tbody id="cmx-monitor-project-rows"></tbody>
						</table>
						<p class="mb-monitor-article-empty" id="cmx-monitor-project-empty" hidden>Keine Projektdaten im aktuellen Filter.</p>
					</div>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-monitor-nested-card">
					<div class="mb-monitor-card-head">
						<h3><a class="mb-monitor-cpt-link" href="<?php echo esc_url(\admin_url('edit.php?post_type=belege&cmx_view=deckungsbeitrag')); ?>" target="_blank" rel="noopener noreferrer" title="Belege öffnen"><span class="dashicons dashicons-media-text" aria-hidden="true"></span></a><span>Beleg</span></h3>
						<button type="button" class="mb-monitor-card-toggle" data-target="cmx-monitor-beleg-card-body" aria-expanded="false" aria-label="Deckungsbeitrag pro Beleg einklappen">
							<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
						</button>
					</div>
					<div class="mb-monitor-collapsible-body is-collapsed" id="cmx-monitor-beleg-card-body">
					<div class="mb-monitor-article-table-wrap">
						<table class="mb-monitor-article-table" id="cmx-monitor-beleg-table">
							<colgroup>
								<col class="col-article">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
							</colgroup>
							<thead>
								<tr>
									<th><button type="button" class="mb-monitor-sort-button" data-table="beleg" data-key="title">Beleg<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="beleg" data-key="revenue">Umsatz<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="beleg" data-key="cost">Aufwand<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="beleg" data-key="profit">Deckungsbeitrag<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="beleg" data-key="margin">Marge %<span class="mb-monitor-sort-indicator"></span></button></th>
								</tr>
							</thead>
							<tbody id="cmx-monitor-beleg-rows"></tbody>
						</table>
						<p class="mb-monitor-article-empty" id="cmx-monitor-beleg-empty" hidden>Keine Belegdaten im aktuellen Filter.</p>
					</div>
					</div>
				</section>
					</div>
				</section>
				<section class="mb-card mb-card--soft mb-span-8">
					<h3>Projekt-Tätigkeiten</h3>
					<p class="mb-monitor-chart-intro">Auf Basis der Tätigkeiten im Projekt: bereits fakturiert, noch nicht fakturiert und interner Aufwand.</p>
					<div class="mb-monitor-article-table-wrap">
						<table class="mb-monitor-article-table" id="cmx-monitor-project-task-table">
							<colgroup>
								<col class="col-article">
								<col class="col-num">
								<col class="col-num">
								<col class="col-num">
							</colgroup>
							<thead>
								<tr>
									<th><button type="button" class="mb-monitor-sort-button" data-table="project-task" data-key="title">Projekt<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="project-task" data-key="billed">Bereits fakturiert<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="project-task" data-key="open">Noch nicht fakturiert<span class="mb-monitor-sort-indicator"></span></button></th>
									<th class="is-num"><button type="button" class="mb-monitor-sort-button" data-table="project-task" data-key="internal">Interner Aufwand<span class="mb-monitor-sort-indicator"></span></button></th>
								</tr>
							</thead>
							<tbody id="cmx-monitor-project-task-rows"></tbody>
						</table>
						<p class="mb-monitor-article-empty" id="cmx-monitor-project-task-empty" hidden>Keine Projekt-Tätigkeiten im aktuellen Filter.</p>
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
			var belegRowsEl = document.getElementById("cmx-monitor-beleg-rows");
			var belegEmptyEl = document.getElementById("cmx-monitor-beleg-empty");
			var articleRowsEl = document.getElementById("cmx-monitor-article-rows");
			var articleEmptyEl = document.getElementById("cmx-monitor-article-empty");
			var customerRowsEl = document.getElementById("cmx-monitor-customer-rows");
			var customerEmptyEl = document.getElementById("cmx-monitor-customer-empty");
			var projectRowsEl = document.getElementById("cmx-monitor-project-rows");
			var projectEmptyEl = document.getElementById("cmx-monitor-project-empty");
			var projectTaskRowsEl = document.getElementById("cmx-monitor-project-task-rows");
			var projectTaskEmptyEl = document.getElementById("cmx-monitor-project-task-empty");
			var sortButtons = Array.prototype.slice.call(document.querySelectorAll(".mb-monitor-sort-button"));
			var cardToggles = Array.prototype.slice.call(document.querySelectorAll(".mb-monitor-card-toggle"));
			var collapsibleCards = Array.prototype.slice.call(document.querySelectorAll(".mb-monitor-nested-card"));
			var belegSort = { key: "profit", direction: "desc" };
			var articleSort = { key: "profit", direction: "desc" };
			var customerSort = { key: "profit", direction: "desc" };
			var projectSort = { key: "profit", direction: "desc" };
			var projectTaskSort = { key: "billed", direction: "desc" };
			var overviewTotalEl = document.getElementById("cmx-monitor-overview-total");
			var overviewCostEl = document.getElementById("cmx-monitor-overview-cost");
			var overviewProfitEl = document.getElementById("cmx-monitor-overview-profit");
			var overviewMarginEl = document.getElementById("cmx-monitor-overview-margin");
			var overviewTotalIndicatorEl = document.getElementById("cmx-monitor-overview-total-indicator");
			var overviewCostIndicatorEl = document.getElementById("cmx-monitor-overview-cost-indicator");
			var overviewProfitIndicatorEl = document.getElementById("cmx-monitor-overview-profit-indicator");
			var overviewMarginIndicatorEl = document.getElementById("cmx-monitor-overview-margin-indicator");
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
			var escapeHtml = function(value){
				return String(value == null ? "" : value)
					.replace(/&/g, "&amp;")
					.replace(/</g, "&lt;")
					.replace(/>/g, "&gt;")
					.replace(/"/g, "&quot;")
					.replace(/\'/g, "&#039;");
			};
			var getSortState = function(tableName){
				if (tableName === "beleg") return belegSort;
				if (tableName === "customer") return customerSort;
				if (tableName === "project") return projectSort;
				if (tableName === "project-task") return projectTaskSort;
				return articleSort;
			};
			var updateSortButtons = function(tableName){
				var sortState = getSortState(tableName);
				sortButtons.forEach(function(button){
					if (String(button.getAttribute("data-table") || "") !== tableName) {
						return;
					}
					var isActive = String(button.getAttribute("data-key") || "") === String(sortState.key || "");
					button.classList.toggle("is-active", isActive);
					button.setAttribute("aria-pressed", isActive ? "true" : "false");
					var indicator = button.querySelector(".mb-monitor-sort-indicator");
					if (indicator) {
						indicator.textContent = isActive ? (sortState.direction === "asc" ? "▲" : "▼") : "";
					}
					var th = button.closest("th");
					if (th) {
						th.setAttribute("aria-sort", isActive ? (sortState.direction === "asc" ? "ascending" : "descending") : "none");
					}
				});
			};
			var sortTableRows = function(rows, tableName){
				var sortState = getSortState(tableName);
				var key = String(sortState.key || "profit");
				var direction = String(sortState.direction || "desc") === "asc" ? 1 : -1;
				return rows.slice().sort(function(a, b){
					var valueA;
					var valueB;

					switch (key) {
						case "title":
							valueA = String(a.title || "");
							valueB = String(b.title || "");
							return valueA.localeCompare(valueB, "de") * direction;
						case "revenue":
						case "cost":
						case "profit":
						case "margin":
						case "billed":
						case "open":
						case "internal":
							valueA = Number(a[key] || 0);
							valueB = Number(b[key] || 0);
							if (valueA !== valueB) {
								return (valueA - valueB) * direction;
							}
							break;
					}

					var fallbackTitleA = String(a.title || "");
					var fallbackTitleB = String(b.title || "");
					if (fallbackTitleA !== fallbackTitleB) {
						return fallbackTitleA.localeCompare(fallbackTitleB, "de");
					}
					return Number(b.revenue || 0) - Number(a.revenue || 0);
				});
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
				var indicatorCompareYear = compareYearFor(selectedYear, selectedType);
				var indicatorRevenueSeries = indicatorCompareYear ? (Array.isArray(getSeriesForYear(indicatorCompareYear, selectedType)) ? getSeriesForYear(indicatorCompareYear, selectedType).slice() : []) : [];
				var indicatorCostSeries = indicatorCompareYear ? (Array.isArray(getCostSeriesForYear(indicatorCompareYear, selectedType)) ? getCostSeriesForYear(indicatorCompareYear, selectedType).slice() : []) : [];
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
					indicatorRevenueSeries = indicatorCompareYear ? getDailySeriesForMonth(indicatorCompareYear, monthNumber, selectedType).slice(0, totalDays) : [];
					indicatorCostSeries = indicatorCompareYear ? getDailyCostSeriesForMonth(indicatorCompareYear, monthNumber, selectedType).slice(0, totalDays) : [];
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
					indicatorRevenueSeries = quarterMonths.map(function(monthNumber){
						return Number(indicatorRevenueSeries[monthNumber - 1] || 0);
					});
					indicatorCostSeries = quarterMonths.map(function(monthNumber){
						return Number(indicatorCostSeries[monthNumber - 1] || 0);
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
					selectedQuarter: selectedQuarter,
					selectedMonth: selectedMonth,
					selectedType: selectedType,
					compareYear: compareYear,
					indicatorCompareYear: indicatorCompareYear,
					selectedTypeLabel: selectedTypeLabel,
					revenueSeries: revenueSeries,
					costSeries: costSeries,
					indicatorRevenueSeries: indicatorRevenueSeries,
					indicatorCostSeries: indicatorCostSeries,
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
				var indicatorRevenueTotal = sumSeries(context.indicatorRevenueSeries);
				var indicatorCostTotal = sumSeries(context.indicatorCostSeries);
				var indicatorProfitTotal = indicatorRevenueTotal - indicatorCostTotal;
				var indicatorMarginTotal = indicatorRevenueTotal !== 0 ? ((indicatorProfitTotal / indicatorRevenueTotal) * 100) : 0;
				var indicatorCompareYear = String(context.indicatorCompareYear || "");
				var compareRevenueTotal = sumSeries(context.compareRevenueSeries);
				var compareCostTotal = sumSeries(context.compareCostSeries);
				var compareProfitTotal = compareRevenueTotal - compareCostTotal;
				var compareMarginTotal = compareRevenueTotal !== 0 ? ((compareProfitTotal / compareRevenueTotal) * 100) : 0;
				var compareYear = String(context.compareYear || "");

				if (overviewTotalEl) overviewTotalEl.textContent = formatNumber(revenueTotal);
				if (overviewCostEl) overviewCostEl.textContent = formatNumber(costTotal);
				if (overviewProfitEl) overviewProfitEl.textContent = formatNumber(profitTotal);
				if (overviewMarginEl) overviewMarginEl.textContent = formatPercent(marginTotal);

				var setIndicator = function(indicatorEl, yearText, deltaValue){
					if (!indicatorEl) return;
					indicatorEl.classList.remove("is-up", "is-down");
					if (!yearText || Number(deltaValue) === 0) {
						return;
					}
					indicatorEl.classList.add(Number(deltaValue) > 0 ? "is-up" : "is-down");
				};

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
				setIndicator(overviewTotalIndicatorEl, indicatorCompareYear, revenueTotal - indicatorRevenueTotal);
				setIndicator(overviewCostIndicatorEl, indicatorCompareYear, costTotal - indicatorCostTotal);
				setIndicator(overviewProfitIndicatorEl, indicatorCompareYear, profitTotal - indicatorProfitTotal);
				setIndicator(overviewMarginIndicatorEl, indicatorCompareYear, marginTotal - indicatorMarginTotal);
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
			var renderBelegTable = function(context){
				if (!belegRowsEl || !belegEmptyEl) return;

				var selectedYear = String(context.selectedYear || "");
				var selectedQuarter = String(context.selectedQuarter || "all");
				var selectedMonth = String(context.selectedMonth || "all");
				var selectedType = String(context.selectedType || "all");
				var sourceRows = Array.isArray(payload.beleg_rows) ? payload.beleg_rows : [];
				var belege = {};

				sourceRows.forEach(function(row){
					var rowYear = String(row.year || "");
					var rowMonth = Number(row.month || 0);
					var rowType = String(row.type || "");
					if (rowYear !== selectedYear) return;
					if (selectedType !== "all" && rowType !== selectedType) return;
					if (selectedMonth !== "all") {
						if (rowMonth !== Number(selectedMonth || 0)) return;
					} else if (selectedQuarter !== "all" && getQuarterMonths(selectedQuarter).indexOf(rowMonth) === -1) {
						return;
					}

					var belegId = Number(row.beleg_id || 0);
					var belegTitle = String(row.beleg_title || "").trim();
					var key = belegId > 0 ? ("id:" + String(belegId)) : "";
					if (!key && belegTitle !== "") {
						key = "label:" + belegTitle.toLowerCase();
					}
					if (!key) return;

					if (!belege[key]) {
						belege[key] = {
							id: belegId,
							title: belegTitle,
							editLink: String(row.edit_link || ""),
							revenue: 0,
							cost: 0
						};
					}
					belege[key].revenue += Number(row.revenue || 0);
					belege[key].cost += Number(row.cost || 0);
				});

				var rows = Object.keys(belege).map(function(key){
					var item = belege[key];
					item.profit = Number(item.revenue || 0) - Number(item.cost || 0);
					item.margin = Number(item.revenue || 0) !== 0 ? ((item.profit / item.revenue) * 100) : 0;
					return item;
				});

				rows = sortTableRows(rows, "beleg");
				updateSortButtons("beleg");

				belegRowsEl.innerHTML = "";
				if (!rows.length) {
					belegEmptyEl.hidden = false;
					return;
				}

				belegEmptyEl.hidden = true;
				rows.slice(0, 12).forEach(function(item){
					var tr = document.createElement("tr");
					var profitClass = item.profit < 0 ? "mb-monitor-article-profit-negative" : "mb-monitor-article-profit-positive";
					var belegLabel = item.title || "Unbekannter Beleg";
					var belegInner = item.editLink
						? \'<a class="mb-monitor-article-link" href="\' + escapeHtml(item.editLink) + \'">\' + escapeHtml(belegLabel) + \'</a>\'
						: \'<span class="mb-monitor-article-link">\' + escapeHtml(belegLabel) + \'</span>\';
					tr.innerHTML =
						\'<td>\' + belegInner + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.revenue)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.cost)) + \'</td>\' +
						\'<td class="is-num \' + profitClass + \'">\' + escapeHtml(formatNumber(item.profit)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatPercent(item.margin)) + \'</td>\';
					belegRowsEl.appendChild(tr);
				});
			};
			var renderArticleTable = function(context){
				if (!articleRowsEl || !articleEmptyEl) return;

				var selectedYear = String(context.selectedYear || "");
				var selectedQuarter = String(context.selectedQuarter || "all");
				var selectedMonth = String(context.selectedMonth || "all");
				var selectedType = String(context.selectedType || "all");
				var sourceRows = Array.isArray(payload.article_rows) ? payload.article_rows : [];
				var articles = {};

				sourceRows.forEach(function(row){
					var rowYear = String(row.year || "");
					var rowMonth = Number(row.month || 0);
					var rowType = String(row.type || "");
					if (rowYear !== selectedYear) return;
					if (selectedType !== "all" && rowType !== selectedType) return;
					if (selectedMonth !== "all") {
						if (rowMonth !== Number(selectedMonth || 0)) return;
					} else if (selectedQuarter !== "all" && getQuarterMonths(selectedQuarter).indexOf(rowMonth) === -1) {
						return;
					}

					var key = String(row.article_id || "");
					if (!key) return;
					if (!articles[key]) {
						articles[key] = {
							id: key,
							title: String(row.article_title || ""),
							number: String(row.article_number || ""),
							editLink: String(row.edit_link || ""),
							revenue: 0,
							cost: 0
						};
					}
					articles[key].revenue += Number(row.revenue || 0);
					articles[key].cost += Number(row.cost || 0);
				});

				var rows = Object.keys(articles).map(function(key){
					var item = articles[key];
					item.profit = Number(item.revenue || 0) - Number(item.cost || 0);
					item.margin = Number(item.revenue || 0) !== 0 ? ((item.profit / item.revenue) * 100) : 0;
					return item;
				});

				rows = sortTableRows(rows, "article");
				updateSortButtons("article");

				articleRowsEl.innerHTML = "";
				if (!rows.length) {
					articleEmptyEl.hidden = false;
					return;
				}

				articleEmptyEl.hidden = true;
				rows.slice(0, 12).forEach(function(item){
					var tr = document.createElement("tr");
					var profitClass = item.profit < 0 ? "mb-monitor-article-profit-negative" : "mb-monitor-article-profit-positive";
					var articleLabel = item.number ? (item.number + " - " + item.title) : item.title;
					var articleInner = item.editLink
						? \'<a class="mb-monitor-article-link" href="\' + escapeHtml(item.editLink) + \'">\' + escapeHtml(articleLabel || "Unbekannter Artikel") + \'</a>\'
						: \'<span class="mb-monitor-article-link">\' + escapeHtml(articleLabel || "Unbekannter Artikel") + \'</span>\';
					tr.innerHTML =
						\'<td>\' + articleInner + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.revenue)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.cost)) + \'</td>\' +
						\'<td class="is-num \' + profitClass + \'">\' + escapeHtml(formatNumber(item.profit)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatPercent(item.margin)) + \'</td>\';
					articleRowsEl.appendChild(tr);
				});
			};
			var renderCustomerTable = function(context){
				if (!customerRowsEl || !customerEmptyEl) return;

				var selectedYear = String(context.selectedYear || "");
				var selectedQuarter = String(context.selectedQuarter || "all");
				var selectedMonth = String(context.selectedMonth || "all");
				var selectedType = String(context.selectedType || "all");
				var sourceRows = Array.isArray(payload.contact_rows) ? payload.contact_rows : [];
				var customers = {};

				sourceRows.forEach(function(row){
					var rowYear = String(row.year || "");
					var rowMonth = Number(row.month || 0);
					var rowType = String(row.type || "");
					if (rowYear !== selectedYear) return;
					if (selectedType !== "all" && rowType !== selectedType) return;
					if (selectedMonth !== "all") {
						if (rowMonth !== Number(selectedMonth || 0)) return;
					} else if (selectedQuarter !== "all" && getQuarterMonths(selectedQuarter).indexOf(rowMonth) === -1) {
						return;
					}

					var contactId = Number(row.contact_id || 0);
					var contactTitle = String(row.contact_title || "").trim();
					var key = contactId > 0 ? ("id:" + String(contactId)) : "";
					if (!key && contactTitle !== "") {
						key = "label:" + contactTitle.toLowerCase();
					}
					if (!key) return;

					if (!customers[key]) {
						customers[key] = {
							id: contactId,
							title: contactTitle,
							editLink: String(row.edit_link || ""),
							revenue: 0,
							cost: 0
						};
					}
					customers[key].revenue += Number(row.revenue || 0);
					customers[key].cost += Number(row.cost || 0);
				});

				var rows = Object.keys(customers).map(function(key){
					var item = customers[key];
					item.profit = Number(item.revenue || 0) - Number(item.cost || 0);
					item.margin = Number(item.revenue || 0) !== 0 ? ((item.profit / item.revenue) * 100) : 0;
					return item;
				});

				rows = sortTableRows(rows, "customer");
				updateSortButtons("customer");

				customerRowsEl.innerHTML = "";
				if (!rows.length) {
					customerEmptyEl.hidden = false;
					return;
				}

				customerEmptyEl.hidden = true;
				rows.slice(0, 12).forEach(function(item){
					var tr = document.createElement("tr");
					var profitClass = item.profit < 0 ? "mb-monitor-article-profit-negative" : "mb-monitor-article-profit-positive";
					var customerLabel = item.title || "Unbekannter Kunde";
					var customerInner = item.editLink
						? \'<a class="mb-monitor-article-link" href="\' + escapeHtml(item.editLink) + \'">\' + escapeHtml(customerLabel) + \'</a>\'
						: \'<span class="mb-monitor-article-link">\' + escapeHtml(customerLabel) + \'</span>\';
					tr.innerHTML =
						\'<td>\' + customerInner + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.revenue)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.cost)) + \'</td>\' +
						\'<td class="is-num \' + profitClass + \'">\' + escapeHtml(formatNumber(item.profit)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatPercent(item.margin)) + \'</td>\';
					customerRowsEl.appendChild(tr);
				});
			};
			var renderProjectTable = function(context){
				if (!projectRowsEl || !projectEmptyEl) return;

				var selectedYear = String(context.selectedYear || "");
				var selectedQuarter = String(context.selectedQuarter || "all");
				var selectedMonth = String(context.selectedMonth || "all");
				var selectedType = String(context.selectedType || "all");
				var sourceRows = Array.isArray(payload.project_rows) ? payload.project_rows : [];
				var projects = {};

				sourceRows.forEach(function(row){
					var rowYear = String(row.year || "");
					var rowMonth = Number(row.month || 0);
					var rowType = String(row.type || "");
					if (rowYear !== selectedYear) return;
					if (selectedType !== "all" && rowType !== selectedType) return;
					if (selectedMonth !== "all") {
						if (rowMonth !== Number(selectedMonth || 0)) return;
					} else if (selectedQuarter !== "all" && getQuarterMonths(selectedQuarter).indexOf(rowMonth) === -1) {
						return;
					}

					var projectId = Number(row.project_id || 0);
					var projectTitle = String(row.project_title || "").trim();
					var key = projectId > 0 ? ("id:" + String(projectId)) : "";
					if (!key && projectTitle !== "") {
						key = "label:" + projectTitle.toLowerCase();
					}
					if (!key) return;

					if (!projects[key]) {
						projects[key] = {
							id: projectId,
							title: projectTitle,
							editLink: String(row.edit_link || ""),
							revenue: 0,
							cost: 0
						};
					}
					projects[key].revenue += Number(row.revenue || 0);
					projects[key].cost += Number(row.cost || 0);
				});

				var rows = Object.keys(projects).map(function(key){
					var item = projects[key];
					item.profit = Number(item.revenue || 0) - Number(item.cost || 0);
					item.margin = Number(item.revenue || 0) !== 0 ? ((item.profit / item.revenue) * 100) : 0;
					return item;
				});

				rows = sortTableRows(rows, "project");
				updateSortButtons("project");

				projectRowsEl.innerHTML = "";
				if (!rows.length) {
					projectEmptyEl.hidden = false;
					return;
				}

				projectEmptyEl.hidden = true;
				rows.slice(0, 12).forEach(function(item){
					var tr = document.createElement("tr");
					var profitClass = item.profit < 0 ? "mb-monitor-article-profit-negative" : "mb-monitor-article-profit-positive";
					var projectLabel = item.title || "Unbekanntes Projekt";
					var projectInner = item.editLink
						? \'<a class="mb-monitor-article-link" href="\' + escapeHtml(item.editLink) + \'">\' + escapeHtml(projectLabel) + \'</a>\'
						: \'<span class="mb-monitor-article-link">\' + escapeHtml(projectLabel) + \'</span>\';
					tr.innerHTML =
						\'<td>\' + projectInner + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.revenue)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.cost)) + \'</td>\' +
						\'<td class="is-num \' + profitClass + \'">\' + escapeHtml(formatNumber(item.profit)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatPercent(item.margin)) + \'</td>\';
					projectRowsEl.appendChild(tr);
				});
			};
			var renderProjectTaskTable = function(context){
				if (!projectTaskRowsEl || !projectTaskEmptyEl) return;

				var selectedYear = String(context.selectedYear || "");
				var selectedQuarter = String(context.selectedQuarter || "all");
				var selectedMonth = String(context.selectedMonth || "all");
				var sourceRows = Array.isArray(payload.project_task_rows) ? payload.project_task_rows : [];
				var projects = {};

				sourceRows.forEach(function(row){
					var rowYear = String(row.year || "");
					var rowMonth = Number(row.month || 0);
					if (rowYear !== selectedYear) return;
					if (selectedMonth !== "all") {
						if (rowMonth !== Number(selectedMonth || 0)) return;
					} else if (selectedQuarter !== "all" && getQuarterMonths(selectedQuarter).indexOf(rowMonth) === -1) {
						return;
					}

					var projectId = Number(row.project_id || 0);
					var projectTitle = String(row.project_title || "").trim();
					var key = projectId > 0 ? ("id:" + String(projectId)) : "";
					if (!key && projectTitle !== "") {
						key = "label:" + projectTitle.toLowerCase();
					}
					if (!key) return;

					if (!projects[key]) {
						projects[key] = {
							id: projectId,
							title: projectTitle,
							editLink: String(row.edit_link || ""),
							billed: 0,
							open: 0,
							internal: 0
						};
					}
					projects[key].billed += Number(row.billed || 0);
					projects[key].open += Number(row.open || 0);
					projects[key].internal += Number(row.internal || 0);
				});

				var rows = Object.keys(projects).map(function(key){
					return projects[key];
				});

				rows = sortTableRows(rows, "project-task");
				updateSortButtons("project-task");

				projectTaskRowsEl.innerHTML = "";
				if (!rows.length) {
					projectTaskEmptyEl.hidden = false;
					return;
				}

				projectTaskEmptyEl.hidden = true;
				rows.slice(0, 12).forEach(function(item){
					var tr = document.createElement("tr");
					var projectLabel = item.title || "Unbekanntes Projekt";
					var projectInner = item.editLink
						? \'<a class="mb-monitor-article-link" href="\' + escapeHtml(item.editLink) + \'">\' + escapeHtml(projectLabel) + \'</a>\'
						: \'<span class="mb-monitor-article-link">\' + escapeHtml(projectLabel) + \'</span>\';
					tr.innerHTML =
						\'<td>\' + projectInner + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.billed)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.open)) + \'</td>\' +
						\'<td class="is-num">\' + escapeHtml(formatNumber(item.internal)) + \'</td>\';
					projectTaskRowsEl.appendChild(tr);
				});
			};
			sortButtons.forEach(function(button){
				button.addEventListener("click", function(){
					var tableName = String(button.getAttribute("data-table") || "");
					var key = String(button.getAttribute("data-key") || "");
					if (!tableName || !key) return;
					var sortState = getSortState(tableName);
					if (sortState.key === key) {
						sortState.direction = sortState.direction === "asc" ? "desc" : "asc";
					} else {
						sortState.key = key;
						sortState.direction = key === "title" ? "asc" : "desc";
					}
					updateDashboard();
				});
			});
			var toggleMonitorCard = function(button, forceExpand){
				var targetId = String(button.getAttribute("data-target") || "");
				if (!targetId) return;
				var body = document.getElementById(targetId);
				if (!body) return;
				var card = button.closest(".mb-monitor-nested-card");
				var expanded = button.getAttribute("aria-expanded") !== "false";
				var nextExpanded = typeof forceExpand === "boolean" ? forceExpand : !expanded;
				button.setAttribute("aria-expanded", nextExpanded ? "true" : "false");
				body.classList.toggle("is-collapsed", !nextExpanded);
				if (card) {
					card.classList.toggle("is-collapsed", !nextExpanded);
				}
			};
			cardToggles.forEach(function(button){
				button.addEventListener("click", function(){
					toggleMonitorCard(button);
				});
			});
			collapsibleCards.forEach(function(card){
				var toggleButton = card.querySelector(".mb-monitor-card-toggle");
				var cardTitle = card.querySelector(".mb-monitor-card-head h3");
				if (!toggleButton) return;
				card.classList.toggle("is-collapsed", toggleButton.getAttribute("aria-expanded") === "false");
				if (cardTitle) {
					cardTitle.addEventListener("click", function(event){
						if (event.target.closest(".mb-monitor-cpt-link")) {
							return;
						}
						toggleMonitorCard(toggleButton);
					});
				}
			});
			var updateDashboard = function(){
				var context = buildContextData();
				updateOverview(context);
				updateMainChart(context);
				updateCostChart(context);
				updateProfitChart(context);
				renderBelegTable(context);
				renderArticleTable(context);
				renderCustomerTable(context);
				renderProjectTable(context);
				renderProjectTaskTable(context);
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
