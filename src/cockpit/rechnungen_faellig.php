<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Dashboard-Widget: Faellige Rechnungen (offen)
 * - zeigt max. 5 offene, faellige Rechnungen
 * - Titel enthaelt die Gesamtanzahl aller offenen, faelligen Rechnungen
 * - Klick auf Widget-Titel springt in die Belege-Liste mit aktivem Filter:
 *   offen + Rechnungen
 */

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_taxonomy')) {
	function cmx_cockpit_beleg_taxonomy(): string {
		if (\function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
			$tax = (string) cmx_belege_taxonomy();
			if ($tax !== '' && \taxonomy_exists($tax)) {
				return $tax;
			}
		}
		foreach (['belege_kategorien', 'beleg_kategorie'] as $tax) {
			if (\taxonomy_exists($tax)) {
				return $tax;
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_rechnung_term_slug')) {
	function cmx_cockpit_rechnung_term_slug(string $taxonomy): string {
		foreach (['rechnung', 'rechnungen'] as $slug) {
			$exists = \term_exists($slug, $taxonomy);
			if ($exists !== 0 && $exists !== null) {
				return $slug;
			}
		}
		return 'rechnung';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_is_unpaid_beleg')) {
	function cmx_cockpit_is_unpaid_beleg(int $post_id): bool {
		$paid_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT')
			: '_cmx_beleg_bezahlt_am';
		$keys = \array_values(\array_unique(\array_filter([$paid_key, \ltrim($paid_key, '_')])));

		foreach ($keys as $key) {
			$val = \trim((string) \get_post_meta($post_id, $key, true));
			if ($val === '' || $val === '0' || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') {
				continue;
			}
			// Nur gueltige Datumswerte als "bezahlt" behandeln; kaputte/legacy Werte bleiben offen.
			if (cmx_cockpit_parse_date_to_ts($val) > 0) {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_is_customer_direction')) {
	function cmx_cockpit_is_customer_direction(int $post_id): bool {
		$dir = \sanitize_key((string) \get_post_meta($post_id, '_cmx_beleg_richtung', true));
		// Legacy-Belege ohne Richtung als Ausgang behandeln.
		return ($dir === '' || $dir === 'ausgang');
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_due_raw')) {
	function cmx_cockpit_due_raw(int $post_id): string {
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
			$val = \trim((string) \get_post_meta($post_id, $key, true));
			if ($val !== '') {
				return $val;
			}
		}
		return '';
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_beleg_kontakt_data')) {
	function cmx_cockpit_beleg_kontakt_data(int $post_id): array {
		$meta_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_KONTAKT')
			: '_cmx_beleg_kontakt_id';
		$keys = \array_values(\array_unique(\array_filter([$meta_key, \ltrim($meta_key, '_')])));

		$kontakt_id = 0;
		foreach ($keys as $key) {
			$val = (int) \get_post_meta($post_id, $key, true);
			if ($val > 0) {
				$kontakt_id = $val;
				break;
			}
		}
		if ($kontakt_id <= 0) {
			return ['name' => '', 'url' => ''];
		}

		$name = \trim((string) \get_the_title($kontakt_id));
		if ($name === '') {
			$name = '#' . $kontakt_id;
		}
		$url = (string) \get_edit_post_link($kontakt_id, '');
		return ['name' => $name, 'url' => $url];
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_parse_date_to_ts')) {
	function cmx_cockpit_parse_date_to_ts(string $raw): int {
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

		foreach (['Y-m-d', 'd.m.Y', 'Y/m/d', 'd/m/Y'] as $fmt) {
			$dt = \DateTime::createFromFormat($fmt, $raw);
			if ($dt instanceof \DateTime) {
				return $dt->getTimestamp();
			}
		}

		$ts = \strtotime($raw);
		return $ts ? (int) $ts : 0;
	}
}

if (!function_exists(__NAMESPACE__ . '\\cmx_cockpit_faellige_rechnungen_data')) {
	function cmx_cockpit_faellige_rechnungen_data(): array {
		static $cache = null;
		if (\is_array($cache)) {
			return $cache;
		}

		$tax = cmx_cockpit_beleg_taxonomy();
		$term_slugs = [];
		if ($tax !== '') {
			$terms = \get_terms([
				'taxonomy'   => $tax,
				'hide_empty' => false,
			]);
			if (!\is_wp_error($terms) && \is_array($terms)) {
				foreach ($terms as $term) {
					if (!($term instanceof \WP_Term)) {
						continue;
					}
					$slug = \sanitize_title((string) ($term->slug ?? ''));
					$name = \trim((string) ($term->name ?? ''));
					$name_lc = \function_exists('mb_strtolower')
						? \mb_strtolower($name, 'UTF-8')
						: \strtolower($name);

					$is_invoice_slug = ($slug === 'rechnung' || $slug === 'rechnungen');
					$is_invoice_name = ($name_lc === 'rechnung' || $name_lc === 'rechnungen');
					$is_invoice_variant = (\strpos($slug, 'rechnung-') === 0 && \strpos($slug, 'lieferanten') === false);

					if ($is_invoice_slug || $is_invoice_name || $is_invoice_variant) {
						$term_slugs[] = $slug;
					}
				}
			}
		}
		if (empty($term_slugs)) {
			$term_slugs = ['rechnung', 'rechnungen'];
		} else {
			$term_slugs = \array_values(\array_unique($term_slugs));
		}
		$term_slug_for_link = (string) ($term_slugs[0] ?? 'rechnung');

		$list_args = [
			'post_type'        => 'belege',
			'cmx_bezahlfilter' => 'offen',
			'cmx_richtungfilter' => 'ausgang',
		];
		if ($tax !== '') {
			$list_args[$tax] = $term_slug_for_link;
		}

		$list_url = \add_query_arg($list_args, \admin_url('edit.php'));

		if ($tax === '') {
			$cache = [
				'total'    => 0,
				'items'    => [],
				'list_url' => $list_url,
			];
			return $cache;
		}

		$q = new \WP_Query([
			'post_type'               => 'belege',
			'post_status'             => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'          => -1,
			'fields'                  => 'ids',
			'no_found_rows'           => true,
			'update_post_meta_cache'  => false,
			'update_post_term_cache'  => false,
			'tax_query'               => [
				[
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => $term_slugs,
					'operator' => 'IN',
				],
			],
		]);

		$rows = [];

		foreach ((array) $q->posts as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id <= 0) {
				continue;
			}
			if (!cmx_cockpit_is_unpaid_beleg($post_id)) {
				continue;
			}
			if (!cmx_cockpit_is_customer_direction($post_id)) {
				continue;
			}

			$due_raw = cmx_cockpit_due_raw($post_id);
			$due_ts  = cmx_cockpit_parse_date_to_ts($due_raw);
			$due_sort = $due_ts > 0 ? $due_ts : PHP_INT_MAX;

			$title = \trim((string) \get_the_title($post_id));
			if ($title === '') {
				$title = '#' . $post_id;
			}

			$kontakt_data = cmx_cockpit_beleg_kontakt_data($post_id);

			$rows[] = [
				'id'       => $post_id,
				'title'    => $title,
				'kontakt'  => (string) ($kontakt_data['name'] ?? ''),
				'kontakt_url' => (string) ($kontakt_data['url'] ?? ''),
				'due_sort' => $due_sort,
				'due_ts'   => $due_ts,
				'due_date' => $due_ts > 0 ? \date_i18n('d.m.Y', $due_ts) : '',
				'edit_url' => (string) \get_edit_post_link($post_id, ''),
			];
		}

		\usort($rows, static function (array $a, array $b): int {
			$cmp = ((int) ($a['due_sort'] ?? PHP_INT_MAX)) <=> ((int) ($b['due_sort'] ?? PHP_INT_MAX));
			if ($cmp !== 0) {
				return $cmp;
			}
			return \strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
		});

		$total = \count($rows);
		$items = \array_slice($rows, 0, 5);

		$cache = [
			'total'    => $total,
			'items'    => $items,
			'list_url' => $list_url,
		];
		return $cache;
	}
}

\add_action('wp_dashboard_setup', __NAMESPACE__ . '\\cmx_register_rechnungen_faellig_widget');
function cmx_register_rechnungen_faellig_widget(): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$data = cmx_cockpit_faellige_rechnungen_data();
	$title = 'Fällige Rechnungen (' . (int) ($data['total'] ?? 0) . ')';
	$title_link = '<a href="' . \esc_url((string) ($data['list_url'] ?? '')) . '" style="font-weight:700;font-size:14px;text-decoration:none;">' . \esc_html($title) . '</a>';

	\wp_add_dashboard_widget(
		'cmx_rechnungen_faellig_widget',
		$title_link,
		__NAMESPACE__ . '\\cmx_render_rechnungen_faellig_widget'
	);
}

function cmx_render_rechnungen_faellig_widget(): void {
	if (!\current_user_can('edit_posts')) {
		echo '<p>' . \esc_html__('Keine Berechtigung.', 'default') . '</p>';
		return;
	}

	$data = cmx_cockpit_faellige_rechnungen_data();
	$total = (int) ($data['total'] ?? 0);
	$items = (array) ($data['items'] ?? []);

	if ($total <= 0) {
		echo '<p>Keine fälligen / offenen Rechnungen</p>';
		return;
	}

	echo '<table style="width:100%;border-collapse:collapse;">';
	echo '<thead><tr>';
	echo '<th style="text-align:left;padding:0 0 6px 0;">Rechnung</th>';
	echo '<th style="text-align:left;padding:0 0 6px 0;white-space:nowrap;">Fällig am</th>';
	echo '<th style="text-align:left;padding:0 0 6px 0;">Kontakt</th>';
	// echo '<th style="text-align:center;padding:0 0 6px 0;width:28px;" title="Als bezahlt markieren"><span class="dashicons dashicons-money-alt" style="font-size:16px;line-height:16px;width:16px;height:16px;"></span></th>';
	echo '<th style="text-align:center;padding:0 0 6px 0;width:28px;" title="Als bezahlt markieren"><span style="font-size:16px;line-height:16px;width:16px;height:16px;"></span></th>';
	echo '</tr></thead><tbody>';
	foreach ($items as $row) {
		$post_id = (int) ($row['id'] ?? 0);
			$title   = (string) ($row['title'] ?? ('#' . $post_id));
			$due     = (string) ($row['due_date'] ?? '');
			$kontakt = (string) ($row['kontakt'] ?? '');
			$kontakt_url = (string) ($row['kontakt_url'] ?? '');
			$edit    = (string) ($row['edit_url'] ?? '');

		echo '<tr>';
		echo '<td style="padding:4px 10px 4px 0;vertical-align:top;">';
		if ($edit !== '') {
			echo '<a href="' . \esc_url($edit) . '">' . \esc_html($title) . '</a>';
		} else {
			echo \esc_html($title);
		}
		echo '</td>';
		echo '<td style="padding:4px 10px 4px 0;vertical-align:top;white-space:nowrap;">' . \esc_html($due) . '</td>';
			echo '<td style="padding:4px 0;vertical-align:top;">';
			if ($kontakt !== '' && $kontakt_url !== '') {
				echo '<a href="' . \esc_url($kontakt_url) . '">' . \esc_html($kontakt) . '</a>';
			} else {
				echo \esc_html($kontakt);
				}
				echo '</td>';
			echo '<td style="padding:4px 0;vertical-align:top;text-align:center;">';
			if ($post_id > 0) {
				echo '<button type="button" class="cmx-faellig-mark-paid" data-beleg="' . (int) $post_id . '" title="Als bezahlt markieren" aria-label="Als bezahlt markieren" style="cursor:pointer;border:0;background:transparent;padding:0;line-height:1;">';
				echo '<span class="dashicons dashicons-money-alt" style="font-size:16px;line-height:16px;width:16px;height:16px;"></span>';
				echo '</button>';
			}
			echo '</td>';
				echo '</tr>';
			}
	echo '</tbody></table>';

	if ($total > \count($items)) {
		echo '<p style="margin:8px 0 0; color:#666;">+' . \esc_html((string) ($total - \count($items))) . ' weitere.</p>';
	}
}

\add_action('admin_footer-index.php', function (): void {
	if (!\current_user_can('edit_posts')) {
		return;
	}

	$data = cmx_cockpit_faellige_rechnungen_data();
	$list_url = (string) ($data['list_url'] ?? '');
	if ($list_url === '') {
		return;
	}
	$paid_nonce = \wp_create_nonce('cmx_mark_paid');
	$ajax_url = \admin_url('admin-ajax.php');
	?>
	<script>
	(function(){
		var box = document.getElementById('cmx_rechnungen_faellig_widget');
		if (!box) return;
		var hndle = box.querySelector('.hndle, .postbox-header h2');
		if (!hndle) return;
		hndle.style.cursor = 'pointer';
		hndle.addEventListener('click', function(e){
			if (e.target && e.target.closest('a')) return;
			e.preventDefault();
			e.stopPropagation();
			window.location.href = <?php echo \wp_json_encode($list_url); ?>;
		});

		document.addEventListener('click', function(e){
			var btn = e.target && e.target.closest ? e.target.closest('.cmx-faellig-mark-paid') : null;
			if (!btn) return;
			e.preventDefault();
			e.stopPropagation();

			var belegId = parseInt(btn.getAttribute('data-beleg') || '0', 10);
			if (!belegId || btn.dataset.loading === '1') return;

			btn.dataset.loading = '1';
			btn.disabled = true;

			var body = new URLSearchParams();
			body.set('action', 'cmx_mark_beleg_paid');
			body.set('post_id', String(belegId));
			body.set('_ajax_nonce', <?php echo \wp_json_encode($paid_nonce); ?>);

			fetch(<?php echo \wp_json_encode($ajax_url); ?>, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function(resp){
				return resp.json();
			}).then(function(resp){
				if (resp && resp.success) {
					window.location.reload();
					return;
				}
				throw new Error((resp && resp.data) ? String(resp.data) : 'Fehler beim Speichern.');
			}).catch(function(err){
				alert(err && err.message ? err.message : 'Fehler beim Speichern.');
				btn.dataset.loading = '';
				btn.disabled = false;
			});
		});
	})();
	</script>
	<?php
});
