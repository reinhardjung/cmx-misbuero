<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

include_once 'ac_projekte.php';
include_once 'ac_kontakte.php';
include_once 'ac_kategorie.php';
include_once 'ac_konditionen.php';
include_once 'ac_summe.php';

/**
 * Zentraler Filter-Handler für die Belege-Liste.
 * Greift alle Selects ab (Kontakt, Kategorie, Zahlstatus, Projekt) und baut
 * eine konsistente meta_query/tax_query, damit sich die Filter nicht gegenseitig überschreiben.
 */
add_action('pre_get_posts', function(\WP_Query $q) {
	if (!is_admin() || !$q->is_main_query()) {
		return;
	}

	$post_type = $q->get('post_type');
	if ((is_array($post_type) && !in_array('belege', $post_type, true)) || ($post_type !== 'belege')) {
		return;
	}

	// ---- Parameter einsammeln (sanitizen) ----
	$kontakt_id   = isset($_GET['cmx_kontakt_id']) ? (int) $_GET['cmx_kontakt_id'] : 0;
	$proj_id      = isset($_GET['cmx_proj_id']) ? (int) $_GET['cmx_proj_id'] : 0;
	$paid_filter  = isset($_GET['cmx_bezahlfilter']) ? sanitize_text_field($_GET['cmx_bezahlfilter']) : '';

	// Kategorie (Taxonomie kann belege_kategorien oder beleg_kategorie heißen)
	$tax = '';
	if (function_exists(__NAMESPACE__ . '\\cmx_belege_taxonomy')) {
		$tax = cmx_belege_taxonomy();
	}
	$cat_slug = '';
	if ($tax && !empty($_GET[$tax]) && $_GET[$tax] !== '0') {
		$cat_slug = sanitize_text_field(wp_unslash($_GET[$tax]));
	}

	// ---- meta_query aufbauen ----
	$meta_query = ['relation' => 'AND'];

	// Kontakt-Filter
	if ($kontakt_id > 0) {
		$meta_query[] = [
			'key'     => defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT') ? CMX_BELEG_META_KONTAKT : '_cmx_beleg_kontakt_id',
			'value'   => $kontakt_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
	}

	// Projekt-Filter (nutzt Helper falls vorhanden)
	if ($proj_id > 0) {
		if (function_exists(__NAMESPACE__ . '\\cmx_build_project_meta_or')) {
			$meta_query[] = cmx_build_project_meta_or($proj_id);
		} else {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => '_cmx_beleg_projekt_id',
					'value'   => $proj_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
				[
					'key'     => '_cmx_projekt_id',
					'value'   => $proj_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
				[
					'key'     => '_projekt_id',
					'value'   => $proj_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			];
		}
	}

	// Bezahlt-Filter
	if ($paid_filter === 'bezahlt' || $paid_filter === 'offen') {
		$paid_key     = defined(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT') ? CMX_BELEG_META_BEZAHLT : '_cmx_beleg_bezahlt_am';
		$paid_key_alt = ltrim($paid_key, '_'); // falls ohne Unterstrich gespeichert

		if ($paid_filter === 'bezahlt') {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => $paid_key,
					'value'   => '',
					'compare' => '!=',
				],
				[
					'key'     => $paid_key_alt,
					'value'   => '',
					'compare' => '!=',
				],
			];
			$q->set('meta_key', $paid_key);
		} else { // offen
			$meta_query[] = [
				'relation' => 'AND',
				[
					'relation' => 'OR',
					[
						'key'     => $paid_key,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => $paid_key,
						'value'   => '',
						'compare' => '=',
					],
				],
				[
					'relation' => 'OR',
					[
						'key'     => $paid_key_alt,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => $paid_key_alt,
						'value'   => '',
						'compare' => '=',
					],
				],
			];
			$q->set('meta_key', $paid_key);
		}
	}

	// Nur setzen, wenn Bedingungen existieren (mind. eine zusätzliche Klausel)
	if (count($meta_query) > 1) {
		$q->set('meta_query', $meta_query);
	}

	// ---- tax_query für Kategorie ----
	if ($cat_slug && $tax) {
		$tax_query = [
			'relation' => 'AND',
			[
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => [$cat_slug],
				'operator' => 'IN',
			],
		];
		$q->set('tax_query', $tax_query);
	}
}, 20);
