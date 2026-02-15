<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_PT_KONTAKTE')) {
	\define(__NAMESPACE__ . '\\CMX_PT_KONTAKTE', 'kontakte');
}
if (!\defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	\define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_kategorie_taxonomy')) {
	function cmx_kontakt_belege_kategorie_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy')) {
			$tax = (string) cmx_belege_kategorie_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}
		foreach (['belege_kategorien', 'beleg_kategorie', 'beleg_kategorien'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}
		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_allowed_slugs')) {
	function cmx_kontakt_belege_allowed_slugs(string $taxonomy): array {
		if ($taxonomy === '' || !\taxonomy_exists($taxonomy)) {
			return [];
		}
		$terms = \get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}

		$slugs = [];
		foreach ($terms as $term) {
			if (!($term instanceof \WP_Term)) {
				continue;
			}
			$slug = \sanitize_title((string) $term->slug);
			$name = \trim((string) $term->name);
			$name_lc = \function_exists('mb_strtolower')
				? \mb_strtolower($name, 'UTF-8')
				: \strtolower($name);

			$is_rechnung = $slug === 'rechnung'
				|| $slug === 'rechnungen'
				|| \strpos($slug, 'rechnung-') === 0
				|| $name_lc === 'rechnung'
				|| $name_lc === 'rechnungen';
			$is_gutschrift = $slug === 'gutschrift'
				|| $slug === 'gutschriften'
				|| \strpos($slug, 'gutschrift-') === 0
				|| $name_lc === 'gutschrift'
				|| $name_lc === 'gutschriften';

			if ($is_rechnung || $is_gutschrift) {
				$slugs[] = $slug;
			}
		}
		return \array_values(\array_unique(\array_filter($slugs)));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_list_url')) {
	function cmx_kontakt_belege_list_url(int $kontakt_id): string {
		$args = [
			'post_type' => CMX_PT_BELEGE,
		];
		if ($kontakt_id > 0) {
			$args['cmx_kontakt_id'] = $kontakt_id;
		}
		return (string) \add_query_arg($args, \admin_url('edit.php'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_parse_date_ts')) {
	function cmx_kontakt_belege_parse_date_ts(string $raw): int {
		$raw = \trim($raw);
		if ($raw === '') {
			return 0;
		}

		if (\ctype_digit($raw) && \strlen($raw) >= 9 && \strlen($raw) <= 11) {
			return (int) $raw;
		}

		if (\ctype_digit($raw) && \strlen($raw) === 8) {
			$y = \substr($raw, 0, 4);
			$m = \substr($raw, 4, 2);
			$d = \substr($raw, 6, 2);
			$ts = \strtotime($y . '-' . $m . '-' . $d . ' 00:00:00');
			return $ts ? (int) $ts : 0;
		}

		foreach (['Y-m-d', 'd.m.Y', 'Y/m/d', 'd/m/Y', 'Y-m-d H:i:s'] as $fmt) {
			$dt = \DateTime::createFromFormat($fmt, $raw);
			if ($dt instanceof \DateTime) {
				return $dt->getTimestamp();
			}
		}

		$ts = \strtotime($raw);
		return $ts ? (int) $ts : 0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_parse_decimal')) {
	function cmx_kontakt_belege_parse_decimal(string $raw): float {
		$raw = \trim($raw);
		if ($raw === '') {
			return 0.0;
		}
		$txt = \str_replace(["\xc2\xa0", ' '], '', $raw);
		$txt = \preg_replace('/[^0-9,.\-]/', '', $txt);
		if (!\is_string($txt) || $txt === '') {
			return 0.0;
		}

		if (\strpos($txt, ',') !== false && \strpos($txt, '.') !== false) {
			$txt = \str_replace("'", '', $txt);
			$txt = \str_replace(',', '.', \str_replace('.', '', $txt));
		} elseif (\strpos($txt, ',') !== false) {
			$txt = \str_replace(',', '.', $txt);
		} else {
			$txt = \str_replace("'", '', $txt);
		}
		return \is_numeric($txt) ? (float) $txt : 0.0;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_due_label')) {
	function cmx_kontakt_beleg_due_label(int $beleg_id): string {
		$keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
			$keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG');
		}
		$keys = \array_merge($keys, [
			'_cmx_beleg_faelligkeitsdatum',
			'_cmx_beleg_faellig_am',
			'cmx_beleg_faelligkeitsdatum',
			'cmx_beleg_faellig_am',
		]);

		foreach (\array_values(\array_unique($keys)) as $key) {
			$raw = \trim((string) \get_post_meta($beleg_id, $key, true));
			if ($raw === '') {
				continue;
			}
			$ts = cmx_kontakt_belege_parse_date_ts($raw);
			if ($ts > 0) {
				return \date_i18n('d.m.Y', $ts);
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_paid_label')) {
	function cmx_kontakt_beleg_paid_label(int $beleg_id): array {
		$paid_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM')
			: '_cmx_beleg_bezahlt_am';
		$keys = \array_values(\array_unique(\array_filter([$paid_key, \ltrim($paid_key, '_'), '_cmx_beleg_bezahlt_am', 'cmx_beleg_bezahlt_am'])));

		foreach ($keys as $key) {
			$raw = \trim((string) \get_post_meta($beleg_id, $key, true));
			if ($raw === '' || $raw === '0' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
				continue;
			}
			$ts = cmx_kontakt_belege_parse_date_ts($raw);
			if ($ts > 0) {
				return [
					'slug'  => 'bezahlt',
					'label' => '' . \date_i18n('d.m.Y', $ts) . '',
				];
			}
		}

		return [
			'slug'  => 'offen',
			'label' => 'Offen',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_beleg_amount_label')) {
	function cmx_kontakt_beleg_amount_label(int $beleg_id): string {
		$total = null;
		if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_positionen_calc')) {
			$calc = (array) cmxbu_get_beleg_positionen_calc($beleg_id);
			if (isset($calc['total']) && \is_numeric($calc['total'])) {
				$total = (float) $calc['total'];
			}
		}

		$override = \trim((string) \get_post_meta($beleg_id, '_cmx_beleg_summe_override', true));
		if ($override !== '') {
			$total = cmx_kontakt_belege_parse_decimal($override);
		}

		if ($total === null) {
			$raw = \trim((string) \get_post_meta($beleg_id, 'betrag', true));
			if ($raw !== '') {
				$total = cmx_kontakt_belege_parse_decimal($raw);
			}
		}

		if ($total === null) {
			return '';
		}

		$formatted = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
			? (string) cmx_format_swiss_number((float) $total, 2)
			: \number_format((float) $total, 2, '.', "'");

		return $formatted;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_data')) {
	function cmx_kontakt_belege_data(int $kontakt_id): array {
		static $cache = [];
		if ($kontakt_id <= 0) {
			return ['sum' => 0.0, 'rows' => []];
		}
		if (isset($cache[$kontakt_id]) && \is_array($cache[$kontakt_id])) {
			return $cache[$kontakt_id];
		}

		$kontakt_keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID')) {
			$kontakt_keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT_ID');
		}
		$kontakt_keys = \array_merge($kontakt_keys, ['_cmx_beleg_kontakt_id', 'cmx_beleg_kontakt_id']);
		$kontakt_keys = \array_values(\array_unique(\array_filter($kontakt_keys)));

		$meta_or = ['relation' => 'OR'];
		foreach ($kontakt_keys as $key) {
			$meta_or[] = [
				'key'     => $key,
				'value'   => $kontakt_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		$tax = cmx_kontakt_belege_kategorie_taxonomy();
		$allowed_slugs = cmx_kontakt_belege_allowed_slugs($tax);
		if ($tax !== '' && empty($allowed_slugs)) {
			$cache[$kontakt_id] = ['sum' => 0.0, 'rows' => []];
			return $cache[$kontakt_id];
		}

		$query_args = [
			'post_type'               => CMX_PT_BELEGE,
			'post_status'             => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'          => -1,
			'fields'                  => 'ids',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => false,
			'update_post_term_cache'  => false,
			'orderby'                 => 'date',
			'order'                   => 'DESC',
			'meta_query'              => [$meta_or],
		];
		if ($tax !== '' && !empty($allowed_slugs)) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => $allowed_slugs,
					'operator' => 'IN',
				],
			];
		}

		$q = new \WP_Query($query_args);

		$sum = 0.0;
		$rows = [];

		foreach ((array) $q->posts as $beleg_id) {
			$beleg_id = (int) $beleg_id;
			if ($beleg_id <= 0) {
				continue;
			}

			$title = \trim((string) \get_the_title($beleg_id));
			if ($title === '') {
				$title = '#' . $beleg_id;
			}

			$amount_label = cmx_kontakt_beleg_amount_label($beleg_id);
			if ($amount_label !== '') {
				$sum += cmx_kontakt_belege_parse_decimal($amount_label);
			}

				$rows[] = [
					'id'         => $beleg_id,
					'title'      => $title,
					'edit_url'   => (string) \get_edit_post_link($beleg_id, ''),
					'amount'     => $amount_label,
					'state'      => cmx_kontakt_beleg_paid_label($beleg_id),
					'due_label'  => cmx_kontakt_beleg_due_label($beleg_id),
				];
			}

		$cache[$kontakt_id] = [
			'sum'  => $sum,
			'rows' => $rows,
		];
		return $cache[$kontakt_id];
	}
}

\add_action('add_meta_boxes_kontakte', function ($post = null): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$kontakt_id = 0;
	if ($post instanceof \WP_Post) {
		$kontakt_id = (int) $post->ID;
	} elseif (isset($_GET['post'])) {
		$kontakt_id = (int) $_GET['post'];
	}
	$data = cmx_kontakt_belege_data($kontakt_id);
	$sum = (float) ($data['sum'] ?? 0.0);
	$sum_label = \function_exists(__NAMESPACE__ . '\\cmx_format_swiss_number')
		? (string) cmx_format_swiss_number($sum, 2)
		: \number_format($sum, 2, '.', "'");
	$title = 'Belege (' . $sum_label . ')';

	\add_meta_box(
		'cmx_kontakt_belege_umsatz',
		$title,
		__NAMESPACE__ . '\\cmx_render_kontakt_belege_umsatz',
		CMX_PT_KONTAKTE,
		'side',
		'default'
	);
}, 30);

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_kontakt_belege_umsatz')) {
	function cmx_render_kontakt_belege_umsatz(\WP_Post $post): void {
		$kontakt_id = (int) ($post->ID ?? 0);
		if ($kontakt_id <= 0) {
			echo '<p><em>Kontakt nicht gefunden.</em></p>';
			return;
		}
			$data = cmx_kontakt_belege_data($kontakt_id);
			$rows = (array) ($data['rows'] ?? []);
			$list_url = cmx_kontakt_belege_list_url($kontakt_id);

			echo '<style>
				#cmx_kontakt_belege_umsatz .cmx-kb-wrap{width:100%;max-height:320px;overflow:auto}
				#cmx_kontakt_belege_umsatz .cmx-kb-table{width:100%!important;min-width:100%;border-collapse:collapse;table-layout:auto}
				#cmx_kontakt_belege_umsatz .cmx-kb-table th,#cmx_kontakt_belege_umsatz .cmx-kb-table td{padding:4px 2px;vertical-align:top}
				#cmx_kontakt_belege_umsatz .cmx-kb-table thead th{font-size:11px;color:#555;border-bottom:1px solid #e2e2e2;text-align:left}
				#cmx_kontakt_belege_umsatz .cmx-kb-table tbody tr+tr td{border-top:1px solid #f0f0f0}
				#cmx_kontakt_belege_umsatz .cmx-kb-beleg{white-space:nowrap}
				#cmx_kontakt_belege_umsatz .cmx-kb-beleg a{white-space:nowrap;display:inline-block;text-decoration:none}
				#cmx_kontakt_belege_umsatz .cmx-kb-amount{white-space:nowrap;text-align:right}
				#cmx_kontakt_belege_umsatz .cmx-kb-state{white-space:nowrap}
				#cmx_kontakt_belege_umsatz .cmx-kb-open{font-weight:600}
				#cmx_kontakt_belege_umsatz .cmx-kb-paid{color:#2f7d32;font-weight:600}
				#cmx_kontakt_belege_umsatz .cmx-kb-due{display:block;color:#b32d2e;font-weight:600;cursor:pointer}
				#cmx_kontakt_belege_umsatz .cmx-kb-due.is-loading{opacity:.5;pointer-events:none}
			</style>';

		if (empty($rows)) {
			echo '<p><em>Keine Belege für diesen Kontakt.</em></p>';
			echo '<script>(function(){';
			echo 'var box=document.getElementById("cmx_kontakt_belege_umsatz");';
			echo 'if(!box){return;}';
			echo 'var h=box.querySelector(".hndle, .postbox-header h2");';
			echo 'if(!h){return;}';
			echo 'h.style.cursor="pointer";';
			echo 'h.addEventListener("click",function(e){';
			echo 'if(e.target&&e.target.closest&&e.target.closest("a,button,input,select,textarea")){return;}';
			echo 'e.preventDefault(); e.stopPropagation();';
			echo 'window.location.href=' . \wp_json_encode($list_url) . ';';
			echo '});';
			echo '})();</script>';
			return;
		}

		echo '<div class="cmx-kb-wrap"><table class="cmx-kb-table">';
		echo '<thead><tr>';
		echo '<th>Beleg</th>';
		echo '<th style="text-align:right;">Betrag</th>';
		echo '<th>Status</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$beleg_id = (int) ($row['id'] ?? 0);
			$title = (string) ($row['title'] ?? ('#' . $beleg_id));
			$edit_url = (string) ($row['edit_url'] ?? '');
			$amount = (string) ($row['amount'] ?? '');
			$state = (array) ($row['state'] ?? []);
			$due_label = (string) ($row['due_label'] ?? '');
			$state_slug = (string) ($state['slug'] ?? 'offen');
			$state_label = (string) ($state['label'] ?? 'Offen');
			$state_class = $state_slug === 'bezahlt' ? 'cmx-kb-paid' : 'cmx-kb-open';

			echo '<tr>';
			echo '<td class="cmx-kb-beleg">';
			if ($edit_url !== '') {
				echo '<a href="' . \esc_url($edit_url) . '">' . \esc_html($title) . '</a>';
			} else {
				echo \esc_html($title);
			}
			echo '</td>';
			echo '<td class="cmx-kb-amount">' . \esc_html($amount) . '</td>';
				echo '<td class="cmx-kb-state ' . \esc_attr($state_class) . '">';
				if ($state_slug === 'bezahlt') {
					echo \esc_html($state_label);
				}
				if ($state_slug !== 'bezahlt' && $due_label !== '') {
					echo '<span class="cmx-kb-due cmx-kb-due-mark-paid" data-beleg="' . (int) $beleg_id . '" title="Doppelklick: als bezahlt markieren">' . \esc_html($due_label) . '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}

				echo '</tbody></table></div>';

				$paid_nonce = \wp_create_nonce('cmx_mark_paid');
				$ajax_url = \admin_url('admin-ajax.php');
				echo '<script>(function(){';
				echo 'var box=document.getElementById("cmx_kontakt_belege_umsatz");';
				echo 'if(box){var h=box.querySelector(".hndle, .postbox-header h2");if(h){h.style.cursor="pointer";h.addEventListener("click",function(e){if(e.target&&e.target.closest&&e.target.closest("a,button,input,select,textarea")){return;}e.preventDefault();e.stopPropagation();window.location.href=' . \wp_json_encode($list_url) . ';});}}';
				echo 'var root=document.getElementById("cmx_kontakt_belege_umsatz"); if(!root){return;}';
				echo 'root.addEventListener("dblclick", function(e){';
			echo 'var el=e.target&&e.target.closest?e.target.closest(".cmx-kb-due-mark-paid[data-beleg]"):null;';
			echo 'if(!el){return;}';
			echo 'e.preventDefault(); e.stopPropagation();';
			echo 'if(el.classList.contains("is-loading")){return;}';
			echo 'var belegId=parseInt(el.getAttribute("data-beleg")||"0",10);';
			echo 'if(!belegId){return;}';
			echo 'el.classList.add("is-loading");';
			echo 'var body=new URLSearchParams();';
			echo 'body.set("action","cmx_mark_beleg_paid");';
			echo 'body.set("post_id", String(belegId));';
			echo 'body.set("_ajax_nonce", ' . \wp_json_encode($paid_nonce) . ');';
			echo 'fetch(' . \wp_json_encode($ajax_url) . ',{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()})';
			echo '.then(function(resp){return resp.json();})';
			echo '.then(function(resp){if(resp&&resp.success){window.location.reload();return;}throw new Error((resp&&resp.data)?String(resp.data):"Fehler beim Speichern.");})';
			echo '.catch(function(err){alert(err&&err.message?err.message:"Fehler beim Speichern."); el.classList.remove("is-loading");});';
			echo '});';
			echo '})();</script>';
		}
	}
