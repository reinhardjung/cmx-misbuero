<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Side-Metabox "Umsatz" für Projekte
 * Summiert: Dauer (h) * VK des ausgewählten Artikels aus den Tasks (tasks.php)
 */

// Metabox registrieren
\add_action('add_meta_boxes', function() {
	\add_meta_box(
		'cmx_projekt_umsatz',
		'Umsatz',
		__NAMESPACE__ . '\\cmx_render_projekt_umsatz_box',
		'projekte',
		'side',
		'default'
	);
});

function cmx_render_projekt_umsatz_box(\WP_Post $post): void {
	$tasks = \get_post_meta($post->ID, '_cmx_projekt_tasks', true);
	if (!is_array($tasks)) $tasks = [];

	$total = 0.0;
	$rows  = [];

	foreach ($tasks as $row) {
		if (!is_array($row)) continue;
		$dauer = (float) str_replace(',', '.', (string) ($row['dauer'] ?? 0));
		$art_id = (int) ($row['artikel_id'] ?? 0);
		if ($dauer <= 0 || $art_id <= 0) continue;

		$vk_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
		$vk_raw = \get_post_meta($art_id, $vk_key, true);
		$vk     = (float) str_replace(',', '.', (string) $vk_raw);
		if ($vk <= 0) continue;

		$betrag = $dauer * $vk;
		$total += $betrag;

		$rows[] = [
			'artikel' => \get_the_title($art_id) ?: ('#' . $art_id),
			'dauer'   => $dauer,
			'vk'      => $vk,
			'betrag'  => $betrag,
		];
	}

	echo '<div style="line-height:1.6;">';
	if (empty($rows)) {
		echo '<p><em>Keine Aufgaben mit Artikel/VK hinterlegt.</em></p>';
	} else {
		echo '<ul style="margin:0 0 8px 16px;padding:0;list-style:disc;">';
		foreach ($rows as $r) {
			printf(
				'<li>%s: %s h × CHF %s = <strong>CHF %s</strong></li>',
				esc_html($r['artikel']),
				number_format($r['dauer'], 2, ',', '\''),
				number_format($r['vk'], 2, ',', '\''),
				number_format($r['betrag'], 2, ',', '\'')
			);
		}
		echo '</ul>';
	}

	echo '<p style="margin:8px 0 0;"><strong>Gesamt:</strong><br><span style="font-size:18px;">CHF ' . number_format($total, 2, ',', '\'') . '</span></p>';
	echo '</div>';
}
