<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_BELEG_META_OFFERTENSTATUS')) {
	\define(__NAMESPACE__ . '\\CMX_BELEG_META_OFFERTENSTATUS', '_cmx_beleg_offertenstatus');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_status_options')) {
	function cmx_beleg_offerte_status_options(): array {
		return [
			'akzeptiert' => 'Akzeptiert',
			'abgelehnt'  => 'Abgelehnt',
			'offen'      => 'Offen',
			'abgelaufen' => 'Abgelaufen',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_due_date')) {
	function cmx_beleg_offerte_due_date(int $post_id): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return '';
		}

		$keys = [];
		if (\defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')) {
			$keys[] = (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG');
		}
		$keys[] = '_cmx_beleg_faelligkeitsdatum';
		$keys[] = '_cmx_beleg_faellig_am';

		foreach (\array_values(\array_unique($keys)) as $key) {
			$value = \sanitize_text_field((string) \get_post_meta($post_id, $key, true));
			if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				return $value;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_sync_expired_status')) {
	function cmx_beleg_offerte_sync_expired_status(int $post_id): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0) {
			return '';
		}

		$slug = \function_exists(__NAMESPACE__ . '\\cmx_beleg_current_category_slug')
			? (string) cmx_beleg_current_category_slug($post_id)
			: '';
		if (!\in_array($slug, ['offerte', 'offerten'], true)) {
			return '';
		}

		$current = \sanitize_key((string) \get_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, true));
		if (\in_array($current, ['akzeptiert', 'abgelehnt'], true)) {
			return $current;
		}

		$due_date = cmx_beleg_offerte_due_date($post_id);
		$today = \current_time('Y-m-d');
		if (
			$due_date !== ''
			&& \preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)
			&& $due_date < $today
		) {
			if ($current !== 'abgelaufen') {
				\update_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, 'abgelaufen');
				\clean_post_cache($post_id);
			}
			return 'abgelaufen';
		}

		return $current;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_accept_url')) {
	function cmx_beleg_offerte_accept_url(int $post_id): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || !\function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')) {
			return '';
		}
		$slug = \function_exists(__NAMESPACE__ . '\\cmx_beleg_current_category_slug')
			? (string) cmx_beleg_current_category_slug($post_id)
			: '';
		if (!\in_array($slug, ['offerte', 'offerten'], true)) {
			return '';
		}
		$status = \function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_sync_expired_status')
			? (string) cmx_beleg_offerte_sync_expired_status($post_id)
			: \sanitize_key((string) \get_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, true));
		if ($status === 'abgelaufen') {
			return '';
		}
		$token = (string) cmxbu_get_stable_token($post_id);
		if ($token === '') {
			return '';
		}
		return (string) \add_query_arg([
			'beleg' => $token,
			'cmx_offerte_action' => 'accept',
		], \home_url('/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_reject_url')) {
	function cmx_beleg_offerte_reject_url(int $post_id): string {
		$post_id = (int) $post_id;
		if ($post_id <= 0 || !\function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')) {
			return '';
		}
		$slug = \function_exists(__NAMESPACE__ . '\\cmx_beleg_current_category_slug')
			? (string) cmx_beleg_current_category_slug($post_id)
			: '';
		if (!\in_array($slug, ['offerte', 'offerten'], true)) {
			return '';
		}
		$status = \function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_sync_expired_status')
			? (string) cmx_beleg_offerte_sync_expired_status($post_id)
			: \sanitize_key((string) \get_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, true));
		if ($status === 'abgelaufen') {
			return '';
		}
		$token = (string) cmxbu_get_stable_token($post_id);
		if ($token === '') {
			return '';
		}
		return (string) \add_query_arg([
			'beleg' => $token,
			'cmx_offerte_action' => 'reject',
		], \home_url('/'));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_current_category_slug')) {
	function cmx_beleg_current_category_slug(int $post_id = 0): string {
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_tax') ? (string) cmx_belege_tax() : '';
		if ($tax === '') {
			return '';
		}

		if (isset($_POST['cmx_beleg_kategorie'])) {
			$term_id = (int) $_POST['cmx_beleg_kategorie'];
			if ($term_id > 0) {
				$term = \get_term($term_id, $tax);
				if ($term && !\is_wp_error($term)) {
					return \sanitize_key((string) $term->slug);
				}
			}
		}

		if ($post_id > 0) {
			$slugs = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
			if (!\is_wp_error($slugs) && !empty($slugs)) {
				return \sanitize_key((string) ($slugs[0] ?? ''));
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_sync_all_expired_statuses')) {
	function cmx_beleg_offerte_sync_all_expired_statuses(): void {
		static $done = false;
		if ($done) {
			return;
		}
		$done = true;

		if ((\defined('DOING_AJAX') && DOING_AJAX) || (\defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}
		if (!\is_admin() && !(\defined('DOING_CRON') && DOING_CRON) && !(\defined('WP_CLI') && WP_CLI)) {
			return;
		}

		$today = \current_time('Y-m-d');
		$last_run = (string) \get_transient('cmx_beleg_offerte_expired_sync_date');
		if ($last_run === $today) {
			return;
		}

		if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
			return;
		}

		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_tax')
			? (string) cmx_belege_tax()
			: '';
		if ($tax === '' || !\taxonomy_exists($tax)) {
			return;
		}

		$due_key = \defined(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_BELEG_META_FAELLIG')
			: '_cmx_beleg_faelligkeitsdatum';

		$post_ids = \get_posts([
			'post_type'              => 'belege',
			'post_status'            => ['publish', 'draft', 'pending', 'future', 'private'],
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => [[
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => ['offerte', 'offerten'],
			]],
			'meta_query'             => [
				'relation' => 'AND',
				[
					'key'     => $due_key,
					'value'   => $today,
					'compare' => '<',
					'type'    => 'DATE',
				],
				[
					'relation' => 'OR',
					[
						'key'     => CMX_BELEG_META_OFFERTENSTATUS,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'   => CMX_BELEG_META_OFFERTENSTATUS,
						'value' => '',
					],
					[
						'key'   => CMX_BELEG_META_OFFERTENSTATUS,
						'value' => 'offen',
					],
				],
			],
		]);

		foreach ($post_ids as $post_id) {
			\update_post_meta((int) $post_id, CMX_BELEG_META_OFFERTENSTATUS, 'abgelaufen');
			\clean_post_cache((int) $post_id);
		}
		\set_transient('cmx_beleg_offerte_expired_sync_date', $today, \DAY_IN_SECONDS);
	}
}
\add_action('init', __NAMESPACE__ . '\\cmx_beleg_offerte_sync_all_expired_statuses', 20);

\add_action('add_meta_boxes_belege', function (): void {
	\add_meta_box(
		'cmx_beleg_offerte',
		'Offerte',
		__NAMESPACE__ . '\\cmx_render_beleg_offerte_metabox',
		'belege',
		'side',
		'default'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_beleg_offerte_metabox')) {
	function cmx_render_beleg_offerte_metabox(\WP_Post $post): void {
		\wp_nonce_field('cmx_save_beleg_offerte', 'cmx_beleg_offerte_nonce');

		$options = cmx_beleg_offerte_status_options();
		$current = \function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_sync_expired_status')
			? (string) cmx_beleg_offerte_sync_expired_status((int) $post->ID)
			: \sanitize_key((string) \get_post_meta($post->ID, CMX_BELEG_META_OFFERTENSTATUS, true));
		if (!isset($options[$current])) {
			$current = 'offen';
		}

		$slug = \function_exists(__NAMESPACE__ . '\\cmx_beleg_current_category_slug')
			? (string) cmx_beleg_current_category_slug((int) $post->ID)
			: '';
		$is_offerte = \in_array($slug, ['offerte', 'offerten'], true);

		if (!$is_offerte) {
			echo '<style>#cmx_beleg_offerte{display:none;}</style>';
		}

		echo '<select name="cmx_beleg_offerte_status" id="cmx_beleg_offerte_status" style="width:100%;" aria-label="Offertenstatus">';
		foreach ($options as $value => $label) {
			echo '<option value="' . \esc_attr($value) . '"' . \selected($current, $value, false) . '>' . \esc_html($label) . '</option>';
		}
		echo '</select>';

		echo '<script>(function(){function getSlug(){var el=document.querySelector("input[name=cmx_beleg_kategorie]:checked");return el?(el.getAttribute("data-slug")||""):"";}function sync(){var box=document.getElementById("cmx_beleg_offerte");if(!box)return;var slug=getSlug();box.style.display=(slug==="offerte"||slug==="offerten")?"block":"none";}document.addEventListener("change",function(e){if(e.target&&e.target.name==="cmx_beleg_kategorie"){sync();}});document.addEventListener("DOMContentLoaded",sync);setTimeout(sync,0);})();</script>';
	}
}

\add_action('save_post_belege', function (int $post_id, \WP_Post $post): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if ($post->post_type !== 'belege') return;
	if (!isset($_POST['cmx_beleg_offerte_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_beleg_offerte_nonce'], 'cmx_save_beleg_offerte')) return;
	if (!\current_user_can('edit_post', $post_id)) return;

	$slug = \function_exists(__NAMESPACE__ . '\\cmx_beleg_current_category_slug')
		? (string) cmx_beleg_current_category_slug($post_id)
		: '';
	if (!\in_array($slug, ['offerte', 'offerten'], true)) {
		return;
	}

	$options = cmx_beleg_offerte_status_options();
	$value = isset($_POST['cmx_beleg_offerte_status'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_beleg_offerte_status']))
		: 'offen';
	if (!isset($options[$value])) {
		$value = 'offen';
	}

	\update_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, $value);
	cmx_beleg_offerte_sync_expired_status($post_id);
}, 10, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_handle_beleg_offerte_public_action')) {
	function cmx_handle_beleg_offerte_public_action(): void {
		$action = isset($_GET['cmx_offerte_action'])
			? \sanitize_key((string) \wp_unslash($_GET['cmx_offerte_action']))
			: '';
		if (!\in_array($action, ['accept', 'reject'], true)) {
			return;
		}

		$token = isset($_GET['beleg'])
			? \sanitize_text_field((string) \wp_unslash($_GET['beleg']))
			: '';
		if ($token === '') {
			\wp_die('Ungültiger Link.');
		}

		$data = \get_option('cmx_beleg_token_data_' . $token);
		$post_id = (int) ($data['post_id'] ?? 0);
		if ($post_id <= 0) {
			\wp_die('Ungültiger Link.');
		}

		$post = \get_post($post_id);
		if (!$post || $post->post_type !== 'belege') {
			\wp_die('Angebot nicht gefunden.');
		}

		$slug = \function_exists(__NAMESPACE__ . '\\cmx_beleg_current_category_slug')
			? (string) cmx_beleg_current_category_slug($post_id)
			: '';
		if (!\in_array($slug, ['offerte', 'offerten'], true)) {
			\wp_die('Angebot nicht gefunden.');
		}

		$current_status = \function_exists(__NAMESPACE__ . '\\cmx_beleg_offerte_sync_expired_status')
			? (string) cmx_beleg_offerte_sync_expired_status($post_id)
			: \sanitize_key((string) \get_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, true));
		if ($current_status === 'abgelaufen') {
			\wp_die('Angebot ist abgelaufen.');
		}

		$new_status = ($action === 'reject') ? 'abgelehnt' : 'akzeptiert';
		$page_title = ($action === 'reject') ? 'Angebot abgelehnt' : 'Angebot akzeptiert';
		$message = ($action === 'reject')
			? ' wurde erfolgreich als abgelehnt gespeichert.'
			: ' wurde erfolgreich als akzeptiert gespeichert.';

		\update_post_meta($post_id, CMX_BELEG_META_OFFERTENSTATUS, $new_status);
		\clean_post_cache($post_id);

		$pdf_url = (string) \add_query_arg('beleg', $token, \home_url('/'));
		$title = \trim((string) \get_the_title($post_id));
		if ($title === '') {
			$title = 'Angebot';
		}

		\status_header(200);
		\nocache_headers();
		echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . \esc_html($page_title) . '</title>';
		echo '<style>body{font-family:Arial,sans-serif;background:#f6f7f7;color:#1d2327;margin:0;padding:32px 18px}.cmx-offerte-accept{max-width:560px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:28px;box-shadow:0 1px 2px rgba(0,0,0,.04)}h1{margin:0 0 12px;font-size:24px}p{margin:0 0 14px;line-height:1.5}.button{display:inline-block;padding:10px 16px;border-radius:4px;background:#1858a8;border:1px solid #134781;color:#fff;text-decoration:none;font-weight:600}</style>';
		echo '</head><body><div class="cmx-offerte-accept">';
		echo '<h1>' . \esc_html($page_title) . '</h1>';
		echo '<p>' . \esc_html($title) . \esc_html($message) . '</p>';
		echo '<p><a class="button" href="' . \esc_url($pdf_url) . '" target="_blank" rel="noopener noreferrer">PDF anzeigen</a></p>';
		echo '</div></body></html>';
		exit;
	}
}
\add_action('template_redirect', __NAMESPACE__ . '\\cmx_handle_beleg_offerte_public_action', 1);
