<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

\add_shortcode('cmx_buchungen_slots', function (array $atts = []): string {
	$atts = \shortcode_atts([
		'date' => \wp_date('Y-m-d'),
		'duration' => '60',
		'mitarbeiter' => '0',
		'ressource' => '0',
	], $atts, 'cmx_buchungen_slots');

	$slots = cmx_buchungen_available_slots(
		(string) $atts['date'],
		\max(5, (int) $atts['duration']),
		\max(0, (int) $atts['mitarbeiter']),
		\max(0, (int) $atts['ressource'])
	);
	if ($slots === []) {
		return '<p>Keine freien Termine gefunden.</p>';
	}

	$html = '<ul class="cmx-buchungen-slots">';
	foreach ($slots as $slot) {
		$html .= '<li>' . \esc_html($slot) . '</li>';
	}
	$html .= '</ul>';
	return $html;
});

\add_action('template_redirect', function (): void {
	$token = isset($_GET['cmx_buchung_cancel']) ? \sanitize_text_field((string) \wp_unslash($_GET['cmx_buchung_cancel'])) : '';
	if ($token === '') {
		return;
	}

	$ids = \get_posts([
		'post_type' => CMX_BUCHUNGEN_CPT,
		'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
		'fields' => 'ids',
		'posts_per_page' => 1,
		'no_found_rows' => true,
		'meta_key' => CMX_BUCHUNGEN_META_CANCEL_TOKEN,
		'meta_value' => $token,
	]);
	$post_id = (int) ($ids[0] ?? 0);
	if ($post_id > 0) {
		\update_post_meta($post_id, CMX_BUCHUNGEN_META_STATUS, 'abgesagt');
		\wp_clear_scheduled_hook(CMX_BUCHUNGEN_REMINDER_HOOK, [$post_id]);
		if (\function_exists(__NAMESPACE__ . '\\cmx_buchungen_send_status_mail')) {
			cmx_buchungen_send_status_mail($post_id, 'abgesagt');
		}
	}

	\wp_die($post_id > 0 ? 'Die Buchung wurde abgesagt.' : 'Buchung nicht gefunden.');
});
