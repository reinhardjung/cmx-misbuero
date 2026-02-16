<?php
/**
 * Plugin Name: CMX Belege – Kopfdaten mit Projektsuche & Kontaktsuche (AJAX, Inline-Buttons, Keyboard-Navigation)
 * Description: Metabox für CPT "belege" mit AJAX-Suche (Projekte & Kontakte), Inline-"Löschen", Keyboard-Navigation (↑/↓/Enter/Esc), Auto-Fill: Kontakt/Adresse aus Projekt, Betreff aus Projekt-URL. Titel = nur Rechnungsnummer.
 * Author: CLOUDMEISTER
 */

namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

/* =========================================================
 * Meta Keys (Defensive Definition)
 * ========================================================= */
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF'))        \define(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF', '_cmx_beleg_betreff');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG'))   \define(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG', '_cmx_beleg_beschreibung');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF_MANUAL')) \define(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF_MANUAL', '_cmx_beleg_betreff_manual');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG_MANUAL')) \define(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG_MANUAL', '_cmx_beleg_beschreibung_manual');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID'))     \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID', '_cmx_beleg_kontakt_id');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR'))   \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR', '_cmx_beleg_kontakt_addr');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL'))  \define(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL', '_cmx_beleg_projekt_label');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL'))  \define(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL', '_cmx_beleg_kontakt_label');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID'))     \define(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID', '_cmx_beleg_projekt_id');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_RICHTUNG'))       \define(__NAMESPACE__.'\\CMX_BELEG_META_RICHTUNG', '_cmx_beleg_richtung');
if (!\defined(__NAMESPACE__.'\\CMX_BELEG_META_STATUS'))         \define(__NAMESPACE__.'\\CMX_BELEG_META_STATUS', '_cmx_beleg_status');

/* =========================================================
 * Helpers
 * ========================================================= */
if (!\function_exists(__NAMESPACE__.'\\cmx_belege_tax')) {
	function cmx_belege_tax(): ?string {
		foreach (['belege_kategorien','belege_kategorie','beleg_kategorien','beleg_kategorie','belege_categories','belege_typ','belege_themen'] as $tax) {
			if (\taxonomy_exists($tax)) return $tax;
		}
		return null;
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_beleg_kategorie_allowed_slugs')) {
	function cmx_beleg_kategorie_allowed_slugs(): array {
		return ['rechnung', 'gutschrift', 'quittung', 'offerte', 'lieferschein'];
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_beleg_richtung_options')) {
	function cmx_beleg_richtung_options(): array {
		return [
			'ausgang' => 'Ausgang (für den Kunden)',
			'eingang' => 'Eingang (vom Lieferanten)',
		];
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_strip_url_protocol')) {
	function cmx_strip_url_protocol(string $url): string {
		$url = \trim($url);
		if ($url === '') {
			return '';
		}
		$clean = \preg_replace('~^[a-z][a-z0-9+\-.]*://~i', '', $url);
		if (\is_string($clean)) {
			$url = $clean;
		}
		if (\strncmp($url, '//', 2) === 0) {
			$url = (string) \substr($url, 2);
		}
		$url = (string) \preg_replace('~/+$~', '', $url);
		return \trim($url);
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_get_projekt_url_value')) {
	function cmx_get_projekt_url_value(int $projekt_id): string {
		if ($projekt_id <= 0) {
			return '';
		}
		$url = (string) (
			\get_post_meta($projekt_id, '_cmx_projekt_url', true) ?:
			\get_post_meta($projekt_id, 'projekt_url', true) ?:
			\get_post_meta($projekt_id, '_cmx_url', true)
		);
		return cmx_strip_url_protocol($url);
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_get_projekt_kategorien_value')) {
	function cmx_get_projekt_kategorien_value(int $projekt_id): string {
		if ($projekt_id <= 0) {
			return '';
		}

		$tax = '';
		$detect_fn = __NAMESPACE__ . '\\cmx_projekte_detect_taxonomy';
		if (\is_callable($detect_fn)) {
			$detected = (string) \call_user_func($detect_fn);
			if ($detected !== '' && \taxonomy_exists($detected) && \is_object_in_taxonomy('projekte', $detected)) {
				$tax = $detected;
			}
		}
		if ($tax === '') {
			foreach (['projekt_kategorie', 'projekte_kategorie', 'projekt_kategorien', 'projekte_kategorien'] as $candidate) {
				if (\taxonomy_exists($candidate) && \is_object_in_taxonomy('projekte', $candidate)) {
					$tax = $candidate;
					break;
				}
			}
		}
		if ($tax === '') {
			return '';
		}

		$names = \wp_get_post_terms($projekt_id, $tax, ['fields' => 'names']);
		if (\is_wp_error($names) || empty($names)) {
			return '';
		}
		$clean = [];
		foreach ((array) $names as $name) {
			$name = \trim((string) $name);
			if ($name !== '' && !\in_array($name, $clean, true)) {
				$clean[] = $name;
			}
		}
		return \implode(', ', $clean);
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_get_projekt_beschreibung_value')) {
	function cmx_get_projekt_beschreibung_value(int $projekt_id): string {
		if ($projekt_id <= 0) {
			return '';
		}
		$post = \get_post($projekt_id);
		if (!$post || $post->post_type !== 'projekte') {
			return '';
		}

		$raw = '';
		$candidates = [
			(string) \get_post_meta($projekt_id, '_cmx_projekt_beschreibung', true),
			(string) \get_post_meta($projekt_id, 'projekt_beschreibung', true),
			(string) $post->post_excerpt,
			(string) $post->post_content,
		];
		foreach ($candidates as $candidate) {
			if (\trim($candidate) !== '') {
				$raw = $candidate;
				break;
			}
		}
		if ($raw === '') {
			return '';
		}

		$raw_no_comments = \preg_replace('/<!--[\s\S]*?-->/', '', $raw);
		if (\is_string($raw_no_comments)) {
			$raw = $raw_no_comments;
		}
		$raw = (string) \strip_shortcodes($raw);

		return \trim((string) \wp_strip_all_tags($raw));
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_get_projekt_betreff_value')) {
	function cmx_get_projekt_betreff_value(int $projekt_id): string {
		if ($projekt_id <= 0) {
			return '';
		}

		$url = cmx_get_projekt_url_value($projekt_id);
		$kategorien = cmx_get_projekt_kategorien_value($projekt_id);

		$parts = [];
		if ($url !== '') {
			$parts[] = $url;
		}
		if ($kategorien !== '') {
			$parts[] = $kategorien;
		}

		if (empty($parts)) {
			return 'Projekt:';
		}

		return 'Projekt: ' . \implode(' | ', $parts);
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_sync_beleg_duplicate')) {
	function cmx_sync_beleg_duplicate(int $source_id, int $target_id): void {
		$orig = \get_post($source_id);
		$target = \get_post($target_id);
		if (!$orig || !$target) return;
		if ($orig->post_type !== 'belege' || $target->post_type !== 'belege') return;

		\wp_update_post([
			'ID'             => $target_id,
			'post_content'   => $orig->post_content,
			'post_excerpt'   => $orig->post_excerpt,
			'comment_status' => $orig->comment_status,
			'ping_status'    => $orig->ping_status,
			'menu_order'     => $orig->menu_order,
		]);

		$taxes = \get_object_taxonomies('belege');
		foreach ($taxes as $tax) {
			$terms = \wp_get_object_terms($source_id, $tax, ['fields' => 'ids']);
			if (!\is_wp_error($terms) && !empty($terms)) {
				\wp_set_object_terms($target_id, $terms, $tax, false);
			}
		}

		$blacklist = \function_exists(__NAMESPACE__ . '\\cmx_dup_meta_blacklist')
			? cmx_dup_meta_blacklist()
			: [];
		$blacklist[] = '_cmx_beleg_copied_from';
		$blacklist[] = '_cmx_beleg_copied_to';
		$blacklist[] = '_cmx_beleg_pdf_type';
		$blacklist[] = '_cmx_title_auto';
		$blacklist[] = '_cmx_rechnungsnummer';
		$blacklist[] = '_cmx_beleg_qrr';
		$blacklist = array_unique($blacklist);

		// Für Sync-Duplikate volatile Werte immer verwerfen, damit sie neu entstehen.
		foreach (['_cmx_rechnungsnummer', '_cmx_beleg_qrr', '_cmx_beleg_pdf_type', '_cmx_title_auto'] as $volatile_key) {
			\delete_post_meta($target_id, $volatile_key);
		}

		$all_meta = \get_post_meta($source_id);
		foreach ($all_meta as $key => $values) {
			if (\in_array($key, $blacklist, true)) continue;
			\delete_post_meta($target_id, $key);
			foreach ((array) $values as $val) {
				\add_post_meta($target_id, $key, \maybe_unserialize($val));
			}
		}
		\delete_post_meta($target_id, '_cmx_beleg_pdf_type');

		$thumb_id = \get_post_thumbnail_id($source_id);
		if ($thumb_id) {
			\set_post_thumbnail($target_id, $thumb_id);
		}

		$ensure_fn = __NAMESPACE__ . '\\cmx_ensure_rechnungsnummer';
		if (\is_callable($ensure_fn)) {
			$no = $ensure_fn($target_id);
			if ($no !== '') {
				\update_post_meta($target_id, '_cmx_title_auto', 1);
				\wp_update_post([
					'ID'         => $target_id,
					'post_title' => $no,
					'post_name'  => \sanitize_title($no),
				]);
			}
		}
	}
}
if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_positionen_meta_array')) {
	function cmx_beleg_positionen_meta_array(int $post_id): array {
		$raw = \get_post_meta($post_id, '_cmx_beleg_positionen', true);
		if (\is_array($raw)) {
			return $raw;
		}
		if (\is_string($raw) && $raw !== '') {
			$tmp = \json_decode($raw, true);
			if (\json_last_error() === JSON_ERROR_NONE && \is_array($tmp)) {
				return $tmp;
			}
			$tmp = @\maybe_unserialize($raw);
			if (\is_array($tmp)) {
				return $tmp;
			}
		}
		return [];
	}
}
if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_row_is_abschnitt')) {
	function cmx_beleg_row_is_abschnitt($row): bool {
		if (!\is_array($row)) {
			return false;
		}
		$typ = \sanitize_key((string)($row['typ'] ?? $row['row_type'] ?? ''));
		return $typ === 'abschnitt';
	}
}
if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_row_is_position')) {
	function cmx_beleg_row_is_position($row): bool {
		if (!\is_array($row)) {
			return false;
		}
		if (cmx_beleg_row_is_abschnitt($row)) {
			return false;
		}
		if (isset($row['artikel_id']) && (int)$row['artikel_id'] > 0) {
			return true;
		}
		return isset($row['menge']) || isset($row['preis']) || isset($row['rabatt']) || isset($row['beschreibung']);
	}
}
if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_row_signature')) {
	function cmx_beleg_row_signature(array $row): string {
		$payload = [
			'artikel_id' => (int)($row['artikel_id'] ?? 0),
			'menge' => (float) \CLOUDMEISTER\CMX\Buero\cmx_norm_decimal((string)($row['menge'] ?? 0)),
			'preis' => (float) \CLOUDMEISTER\CMX\Buero\cmx_norm_decimal((string)($row['preis'] ?? 0)),
			'rabatt' => \trim((string)($row['rabatt'] ?? '')),
			'beschreibung' => \trim((string)($row['beschreibung'] ?? '')),
			'task_uid' => \trim((string)($row['task_uid'] ?? '')),
			'task_idx' => isset($row['task_idx']) && \is_numeric($row['task_idx']) ? (int)$row['task_idx'] : null,
			'task_projekt_id' => isset($row['task_projekt_id']) && \is_numeric($row['task_projekt_id']) ? (int)$row['task_projekt_id'] : null,
		];
		return \md5((string)\wp_json_encode($payload));
	}
}
if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_lieferschein_duplicate_ids')) {
	function cmx_beleg_lieferschein_duplicate_ids(int $source_id, int $exclude_id = 0): array {
		$args = [
			'post_type' => 'belege',
			'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
			'fields' => 'ids',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'suppress_filters' => true,
			'meta_query' => [
				[
					'key' => '_cmx_beleg_copied_from',
					'value' => (int)$source_id,
					'compare' => '=',
				],
			],
		];
		if ($exclude_id > 0) {
			$args['post__not_in'] = [(int)$exclude_id];
		}
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_tax') ? cmx_belege_tax() : '';
		if (\is_string($tax) && $tax !== '') {
			$args['tax_query'] = [[
				'taxonomy' => $tax,
				'field' => 'slug',
				'terms' => ['lieferschein', 'lieferscheine'],
			]];
		}
		$ids = \get_posts($args);
		if (!\is_array($ids)) {
			return [];
		}
		return \array_values(\array_filter(\array_map('intval', $ids), static function (int $id) use ($source_id, $exclude_id): bool {
			if ($id <= 0) return false;
			if ($id === $source_id) return false;
			if ($exclude_id > 0 && $id === $exclude_id) return false;
			return true;
		}));
	}
}
if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_prepare_lieferschein_duplicate_positions')) {
	function cmx_beleg_prepare_lieferschein_duplicate_positions(int $source_id, int $target_id): void {
		$source_rows = cmx_beleg_positionen_meta_array($source_id);
		if (empty($source_rows) || !\is_array($source_rows)) {
			\update_post_meta($target_id, '_cmx_beleg_positionen', []);
			return;
		}

		$source_counts = [];
		$source_sigs = [];
		$total_source_positions = 0;
		foreach ($source_rows as $idx => $row) {
			if (!cmx_beleg_row_is_position($row)) {
				continue;
			}
			$sig = cmx_beleg_row_signature((array)$row);
			$source_sigs[(int)$idx] = $sig;
			$source_counts[$sig] = (int)($source_counts[$sig] ?? 0) + 1;
			$total_source_positions++;
		}
		if ($total_source_positions <= 0) {
			\update_post_meta($target_id, '_cmx_beleg_positionen', []);
			return;
		}

		$used_counts = [];
		$existing_ids = cmx_beleg_lieferschein_duplicate_ids($source_id, $target_id);
		foreach ($existing_ids as $lieferschein_id) {
			$rows = cmx_beleg_positionen_meta_array((int)$lieferschein_id);
			foreach ($rows as $row) {
				if (!cmx_beleg_row_is_position($row)) {
					continue;
				}
				$sig = cmx_beleg_row_signature((array)$row);
				$used_counts[$sig] = (int)($used_counts[$sig] ?? 0) + 1;
			}
		}

		$selected_counts = [];
		$include_mask = [];
		$included_positions = 0;
		foreach ($source_rows as $idx => $row) {
			if (!isset($source_sigs[(int)$idx])) {
				continue;
			}
			$sig = $source_sigs[(int)$idx];
			$remaining = (int)($source_counts[$sig] ?? 0)
				- (int)($used_counts[$sig] ?? 0)
				- (int)($selected_counts[$sig] ?? 0);
			if ($remaining > 0) {
				$include_mask[(int)$idx] = true;
				$selected_counts[$sig] = (int)($selected_counts[$sig] ?? 0) + 1;
				$included_positions++;
			}
		}

		// Erster Lieferschein: unverändert alle Zeilen aus der Quelle übernehmen.
		if ($included_positions >= $total_source_positions) {
			return;
		}

		$filtered_rows = [];
		$pending_abschnitt = null;
		foreach ($source_rows as $idx => $row) {
			if (cmx_beleg_row_is_abschnitt($row)) {
				$pending_abschnitt = $row;
				continue;
			}
			if (isset($include_mask[(int)$idx])) {
				if ($pending_abschnitt !== null) {
					$filtered_rows[] = $pending_abschnitt;
					$pending_abschnitt = null;
				}
				$filtered_rows[] = $row;
			}
		}

		\update_post_meta($target_id, '_cmx_beleg_positionen', $filtered_rows);
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_kontakte_cpt')) {
	function cmx_kontakte_cpt(): string {
		if (\post_type_exists('kontakte')) return 'kontakte';
		if (\post_type_exists('kontakt'))  return 'kontakt';
		return 'kontakte';
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_iso2_from_land')) {
	function cmx_iso2_from_land(int $kontakt_id): string {
		$meta_land = \strtoupper(\trim((string)\get_post_meta($kontakt_id, '_cmx_rechnung_land', true)));
		if (\preg_match('/^[A-Z]{2}$/', $meta_land)) return $meta_land;
		return 'CH';
	}
}
if (!\function_exists(__NAMESPACE__.'\\cmx_build_kontakt_postanschrift')) {
	function cmx_build_kontakt_postanschrift(int $kontakt_id): string {
		if ($kontakt_id <= 0) return '';

		$firma = \trim((string) \get_the_title($kontakt_id));
		$firma_lc = \function_exists('mb_strtolower') ? \mb_strtolower($firma) : \strtolower($firma);
		if ($firma_lc === 'firmenname fehlt') {
			$firma = '';
		}

		$vorname  = \trim((string) \get_post_meta($kontakt_id, '_cmx_kontakte_vorname', true));
		$nachname = \trim((string) \get_post_meta($kontakt_id, '_cmx_kontakte_nachname', true));
		$full_name = \trim(\preg_replace('/\s+/', ' ', $vorname . ' ' . $nachname));
		$is_privat = !empty(\get_post_meta($kontakt_id, '_cmx_kontakte_privat', true));

		$norm = static function (string $value): string {
			$value = \trim((string) \preg_replace('/\s+/', ' ', $value));
			return \function_exists('mb_strtolower') ? \mb_strtolower($value) : \strtolower($value);
		};

		$lines = [];
		$firma_norm = $norm($firma);
		$name_norm  = $norm($full_name);

		// Firmenname zuerst, ausser es ist ein Privatkontakt mit identischem Namen.
		if ($firma !== '' && (!$is_privat || $firma_norm !== $name_norm)) {
			$lines[] = $firma;
		}

		// Danach Vorname + Nachname (falls vorhanden) als eigene Zeile.
		if ($full_name !== '') {
			$already_present = false;
			foreach ($lines as $line) {
				if ($norm($line) === $name_norm) {
					$already_present = true;
					break;
				}
			}
			if (!$already_present) {
				$lines[] = $full_name;
			}
		}

		$str  = \trim((string) \get_post_meta($kontakt_id, '_cmx_rechnung_strasse', true));
		$plz  = \trim((string) \get_post_meta($kontakt_id, '_cmx_rechnung_plz', true));
		$ort  = \trim((string) \get_post_meta($kontakt_id, '_cmx_rechnung_ort', true));
		$iso2 = cmx_iso2_from_land($kontakt_id);

		if ($str !== '') {
			$lines[] = $str;
		}

		$city = \trim($plz . ' ' . $ort);
		if ($city !== '') {
			$lines[] = ($iso2 !== '' ? $iso2 . '-' : '') . $city;
		}

		$lines = \array_values(\array_filter(\array_map('trim', $lines), static function ($line) {
			return $line !== '';
		}));

		return \implode("\n", $lines);
	}
}

/** Rechnungsnummer (Format aus INI, externe Helperfunktion vorausgesetzt) */
if (!\function_exists(__NAMESPACE__ . '\\cmx_generate_rechnungsnummer')) {
	function cmx_generate_rechnungsnummer(): string {
		$format = cmx_ini_get_value('Belege','Format');
		return \wp_date($format);
	}
}

/** Sicherstellen, dass _cmx_rechnungsnummer vorhanden ist */
if (!\function_exists(__NAMESPACE__.'\\cmx_ensure_rechnungsnummer')) {
	function cmx_ensure_rechnungsnummer(int $post_id): string {
		$no = (string)\get_post_meta($post_id, '_cmx_rechnungsnummer', true);
		if ($no === '') {
			$no = cmx_generate_rechnungsnummer();
			\update_post_meta($post_id, '_cmx_rechnungsnummer', $no);
		}
		return $no;
	}
}

/* =========================================================
 * Metabox registrieren
 * ========================================================= */
\add_action('add_meta_boxes', function () {
	if (!\post_type_exists('belege')) return;
	\add_meta_box('cmx_beleg_details', 'Beleg', __NAMESPACE__.'\\cmx_render_beleg_metabox', 'belege', 'normal', 'high');
});

/* =========================================================
 * AJAX: Projekte & Kontakte suchen
 * - Projekte liefern zusätzlich: kontakt_id, kontakt_title, kontakt_addr, url (falls vorhanden)
 *   Erweiterung: leere Suche → Liste anzeigen; Projekte zuerst nach zuletzt genutzten (aus letzten Belegen des Users)
 * ========================================================= */
\add_action('wp_ajax_cmx_search_projekte', __NAMESPACE__.'\\cmx_ajax_search_projekte');
function cmx_ajax_search_projekte(): void {
	if (!\current_user_can('edit_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_GET['_ajax_nonce']) ? (string)$_GET['_ajax_nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_search_projekte')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$q = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash($_GET['q'])) : '';

	$proj_ids = [];
	if ($q === '') {
		// 1) IDs aus zuletzt bearbeiteten Belegen (Autor = aktueller User) priorisieren
		$user_id = \get_current_user_id();
		$recent_belege = \get_posts([
			'post_type'      => 'belege',
			'post_status'    => 'any',
			'author'         => $user_id ?: 0,
			'posts_per_page' => 40,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		]);
		$recent_proj = [];
		foreach ($recent_belege as $bid) {
			$pid = (int)\get_post_meta($bid, CMX_BELEG_META_PROJEKT_ID, true);
			if ($pid > 0 && \get_post_status($pid)) {
				$recent_proj[] = $pid;
			}
		}
		// eindeutige Reihenfolge beibehalten
		$recent_proj = \array_values(\array_unique($recent_proj));
		$proj_ids    = \array_slice($recent_proj, 0, 20);

		// 2) Auffüllen mit weiteren Projekten (zuletzt geändert), die noch nicht enthalten sind
		if (\count($proj_ids) < 20) {
			$more = \get_posts([
				'post_type'      => 'projekte',
				'post_status'    => 'any',
				'posts_per_page' => 20 - \count($proj_ids),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'exclude'        => $proj_ids,
			]);
			$proj_ids = \array_merge($proj_ids, $more);
		}
	} else {
		// Normale Textsuche
		$proj_ids = \get_posts([
			'post_type'      => 'projekte',
			'post_status'    => 'any',
			's'              => $q,
			'posts_per_page' => 20,
			'fields'         => 'ids',
		]);
	}

	$out = [];
	foreach ($proj_ids as $id) {
		$title = \get_the_title($id);

		$kontakt_id = (int) (
			\get_post_meta($id, '_cmx_projekt_kontakt_id', true) ?:
			\get_post_meta($id, '_cmx_kontakt_id', true) ?:
			\get_post_meta($id, 'kontakt_id', true)
		);
		$kontakt_title = $kontakt_id ? \get_the_title($kontakt_id) : '';
		$kontakt_addr  = '';
		if ($kontakt_id) {
			$kontakt_addr = cmx_build_kontakt_postanschrift($kontakt_id);
		}

		$url = cmx_get_projekt_url_value((int) $id);
		$description = cmx_get_projekt_beschreibung_value((int) $id);
		$subject = cmx_get_projekt_betreff_value((int) $id);

		$out[] = [
			'id'            => (int)$id,
			'title'         => $title,
			'link'          => \get_edit_post_link($id, ''),
			'kontakt_id'    => $kontakt_id,
			'kontakt_title' => $kontakt_title,
			'kontakt_addr'  => $kontakt_addr,
			'url'           => $url,
			'description'   => $description,
			'subject'       => $subject,
		];
	}
	\wp_send_json_success(['items'=>$out]);
}

\add_action('wp_ajax_cmx_search_kontakte', __NAMESPACE__.'\\cmx_ajax_search_kontakte');
function cmx_ajax_search_kontakte(): void {
	if (!\current_user_can('edit_posts')) \wp_send_json_error(['message'=>'forbidden'], 403);
	$nonce = isset($_GET['_ajax_nonce']) ? (string)$_GET['_ajax_nonce'] : '';
	if (!\wp_verify_nonce($nonce, 'cmx_search_kontakte')) \wp_send_json_error(['message'=>'bad_nonce'], 403);

	$q   = isset($_GET['q']) ? \sanitize_text_field(\wp_unslash($_GET['q'])) : '';
	$cpt = cmx_kontakte_cpt();

	if ($q === '') {
		// Leere Suche: einfach die zuletzt geänderten Kontakte anzeigen
		$ids = \get_posts([
			'post_type'      => $cpt,
			'post_status'    => 'any',
			'posts_per_page' => 20,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		]);
	} else {
		$ids = \get_posts([
			'post_type'      => $cpt,
			'post_status'    => 'any',
			's'              => $q,
			'posts_per_page' => 20,
			'fields'         => 'ids',
		]);
	}

	$out = [];
	foreach ($ids as $id) {
		$title = \get_the_title($id);
		$addr  = cmx_build_kontakt_postanschrift((int) $id);
		$out[] = [
			'id'    => (int)$id,
			'title' => $title,
			'addr'  => $addr,
			'link'  => \get_edit_post_link($id, ''),
		];
	}
	\wp_send_json_success(['items'=>$out]);
}

/* =========================================================
 * Metabox: Status (SIDE)
 * ========================================================= */

/* =========================================================
 * Render-Funktion Metabox
 * ========================================================= */
function cmx_render_beleg_metabox(\WP_Post $post): void {
	$tax            = cmx_belege_tax();
	$betreff        = (string)\get_post_meta($post->ID, CMX_BELEG_META_BETREFF, true);
	$beschreibung   = (string)\get_post_meta($post->ID, CMX_BELEG_META_BESCHREIBUNG, true);
	$kontakt_id     = (int)\get_post_meta($post->ID, CMX_BELEG_META_KONTAKT_ID, true);
	$addr_text      = (string)\get_post_meta($post->ID, CMX_BELEG_META_KONTAKT_ADDR, true);
	$projekt_label  = (string)\get_post_meta($post->ID, CMX_BELEG_META_PROJEKT_LABEL, true);
	$kontakt_label  = (string)\get_post_meta($post->ID, CMX_BELEG_META_KONTAKT_LABEL, true);
	$projekt_id     = (int)\get_post_meta($post->ID, CMX_BELEG_META_PROJEKT_ID, true);
	$richtung       = (string)\get_post_meta($post->ID, CMX_BELEG_META_RICHTUNG, true);
	$betreff_manual = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF_MANUAL') && (int)\get_post_meta($post->ID, CMX_BELEG_META_BETREFF_MANUAL, true) === 1 ? 1 : 0;
	$beschreibung_manual = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG_MANUAL') && (int)\get_post_meta($post->ID, CMX_BELEG_META_BESCHREIBUNG_MANUAL, true) === 1 ? 1 : 0;

	\wp_nonce_field('cmx_beleg_details_save', 'cmx_beleg_details_nonce');

	$ajax_nonce_proj = \wp_create_nonce('cmx_search_projekte');
	$ajax_nonce_kont = \wp_create_nonce('cmx_search_kontakte');

	echo '<style>
		.cmx-grid{display:flex;gap:16px;flex-wrap:wrap}
		.cmx-col{flex:1 1 420px;min-width:320px}
		.cmx-col input[type=text],.cmx-col textarea{width:100%}
		.cmx-radio-inline label{display:inline-block;margin-right:12px}
		.cmx-addr{white-space:pre-wrap}
		.cmx-suggest{position:relative}
		.cmx-input-row{display:flex;align-items:center;gap:6px}
		.cmx-input-row input[type=text]{flex:1 1 auto;min-width:0}
		.cmx-suggest ul{position:absolute;z-index:1000;left:0;right:0;max-height:240px;overflow:auto;margin:2px 0 0;padding:0;border:1px solid #ccd0d4;background:#fff;list-style:none}
		.cmx-suggest li{margin:0;padding:6px 8px;cursor:pointer}
		.cmx-suggest li.active{background:#e5f3ff}
		.cmx-suggest li:hover{background:#f3f4f5}
	</style>';

	echo '<div class="cmx-grid">';

	/* --- linke Spalte --- */
	echo '<div class="cmx-col">';
	if ($tax) {
		$current_terms = \wp_get_post_terms($post->ID, $tax, ['fields'=>'ids']);
		$current_id = $current_terms[0] ?? 0;
		$terms = \get_terms(['taxonomy'=>$tax,'hide_empty'=>false]);
		$allowed_slugs = cmx_beleg_kategorie_allowed_slugs();
		if (!empty($allowed_slugs)) {
			$terms = array_values(array_filter($terms, function($term) use ($allowed_slugs) {
				return in_array($term->slug, $allowed_slugs, true);
			}));
		}
		$allowed_ids = array_map(function($term) { return (int) $term->term_id; }, $terms);
		if (!in_array($current_id, $allowed_ids, true)) {
			$default_term = null;
			foreach ($terms as $term) {
				if ($term->slug === 'rechnung') { $default_term = $term; break; }
			}
			if ($default_term === null) {
				$default_term = $terms[0] ?? null;
			}
			$current_id = $default_term ? (int) $default_term->term_id : 0;
		}
		// echo '<p><strong>Kategorie</strong><br><div class="cmx-radio-inline">';
		echo '<div class="cmx-radio-inline">';
		foreach ($terms as $term) {
			echo '<label><input type="radio" name="cmx_beleg_kategorie" data-slug="'.\esc_attr($term->slug).'" value="'.\esc_attr($term->term_id).'" '.\checked($current_id,$term->term_id,false).'> '.\esc_html($term->name).'</label>';
		}
		echo '</div></p>';
	}

	$richtung_opts = cmx_beleg_richtung_options();
	if (!isset($richtung_opts[$richtung])) {
		$richtung = array_key_first($richtung_opts);
	}
	// echo '<p><strong>Richtung</strong><br><div class="cmx-radio-inline">';
	echo '<div class="cmx-radio-inline">';
	foreach ($richtung_opts as $val => $label) {
		$help_key = $val === 'ausgang' ? 'beleg_richtung_ausgang' : 'beleg_richtung_eingang';
		echo '<label data-cmx-help-key="'.\esc_attr($help_key).'"><input type="radio" name="cmx_beleg_richtung" data-cmx-help-key="'.\esc_attr($help_key).'" value="'.\esc_attr($val).'" '.\checked($richtung,$val,false).'> <span class="cmx-richtung-label" data-cmx-help-key="'.\esc_attr($help_key).'" data-value="'.\esc_attr($val).'">'.\esc_html($label).'</span></label>';
	}
	echo '</div></p>';
	$known_map = [
		'rechnung' => [
			'ausgang' => 'Einnahme (an Kunden)',
			'eingang' => 'Ausgabe (von Lieferanten)',
		],
		'rechnungen' => [
			'ausgang' => 'Einnahme (an Kunden)',
			'eingang' => 'Ausgabe (von Lieferanten)',
		],
		'gutschrift' => [
			'ausgang' => 'Ausgabe (an Kunden)',
			'eingang' => 'Einnahme (von Lieferanten)',
		],
		'gutschriften' => [
			'ausgang' => 'Ausgabe (an Kunden)',
			'eingang' => 'Einnahme (von Lieferanten)',
		],
		'quittung' => [
			'ausgang' => 'Einnahme (an Kunden)',
			'eingang' => 'Ausgabe (von Lieferanten)',
		],
		'quittungen' => [
			'ausgang' => 'Einnahme (an Kunden)',
			'eingang' => 'Ausgabe (von Lieferanten)',
		],
		'offerte' => [
			'ausgang' => 'Ausgang (an Kunden)',
			'eingang' => 'Eingang (von Lieferanten)',
		],
		'offerten' => [
			'ausgang' => 'Ausgang (an Kunden)',
			'eingang' => 'Eingang (von Lieferanten)',
		],
		'lieferschein' => [
			'ausgang' => 'Ausgang (an Kunden)',
			'eingang' => 'Eingang (von Lieferanten)',
		],
		'lieferscheine' => [
			'ausgang' => 'Ausgang (an Kunden)',
			'eingang' => 'Eingang (von Lieferanten)',
		],
	];
	$ini_kategorien = \function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')
		? (array) cmx_ini_get_value('Belege', 'Kategorien')
		: [];
	$richtung_label_map = [];
	foreach ($ini_kategorien as $cat_name) {
		$slug = \sanitize_title((string) $cat_name);
		$ini_labels = \function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')
			? cmx_ini_get_value('BelegeRichtungLabels', (string) $cat_name)
			: null;
		if ($ini_labels === null || $ini_labels === '') {
			$ini_labels = \function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')
				? cmx_ini_get_value('BelegeRichtungLabels', (string) $slug)
				: null;
		}
		if (\is_array($ini_labels) && \count($ini_labels) >= 2) {
			$richtung_label_map[$slug] = [
				'ausgang' => (string) $ini_labels[0],
				'eingang' => (string) $ini_labels[1],
			];
			continue;
		}
		if (isset($known_map[$slug])) {
			$richtung_label_map[$slug] = $known_map[$slug];
		}
	}
	if (empty($richtung_label_map)) {
		$richtung_label_map = $known_map;
	}
	echo '<script>(function(){var map='.\wp_json_encode($richtung_label_map).';var defaults='.\wp_json_encode($richtung_opts).';var defaultDir={rechnung:"ausgang",rechnungen:"ausgang",quittung:"ausgang",quittungen:"ausgang",lieferschein:"ausgang",lieferscheine:"ausgang",offerte:"ausgang",offerten:"ausgang",gutschrift:"eingang",gutschriften:"eingang"};var isAutoDraft=' . (($post->post_status ?? '') === 'auto-draft' ? 'true' : 'false') . ';function slug(){var el=document.querySelector("input[name=cmx_beleg_kategorie]:checked");return el?(el.getAttribute("data-slug")||""):"";}function setDefaultDir(){if(!isAutoDraft) return;var s=slug();var dir=defaultDir[s];if(!dir) return;var r=document.querySelector("input[name=cmx_beleg_richtung][value=\\""+dir+"\\"]");if(r){r.checked=true;r.dispatchEvent(new Event("change",{bubbles:true}));}}function sync(){var s=slug();var labels=(s&&map[s])?map[s]:defaults;document.querySelectorAll(".cmx-richtung-label").forEach(function(node){var val=node.getAttribute("data-value");if(labels&&labels[val]){node.textContent=labels[val];}});setDefaultDir();}document.addEventListener("change",function(e){if(e.target&&e.target.name==="cmx_beleg_kategorie"){sync();}});document.addEventListener("DOMContentLoaded",sync);setTimeout(sync,0);})();</script>';

	// echo '<p><label><strong>Betreff</strong> / Zusätzliche Informationen (auf dem QR-Code)</label><br>';
	echo '<p><label><strong>Betreff</strong></label><br>';
	echo '<input type="text" id="cmx_beleg_betreff" name="cmx_beleg_betreff" value="'.\esc_attr($betreff).'">';
	echo '<input type="hidden" id="cmx_beleg_betreff_manual" name="cmx_beleg_betreff_manual" value="'.\esc_attr((string)$betreff_manual).'"></p>';

	echo '<p><label><strong>Beschreibung</strong></label><br>';
	echo '<textarea id="cmx_beleg_beschreibung" name="cmx_beleg_beschreibung" rows="7">'.\esc_textarea($beschreibung).'</textarea>';
	echo '<input type="hidden" id="cmx_beleg_beschreibung_manual" name="cmx_beleg_beschreibung_manual" value="'.\esc_attr((string)$beschreibung_manual).'"></p>';
	echo '</div>';

	/* --- rechte Spalte --- */
	echo '<div class="cmx-col">';

	/* Projektsuche */
	$current_proj_title = $projekt_id ? \get_the_title($projekt_id) : '';
	$display_proj = $projekt_label ?: $current_proj_title;

	$proj_edit_link = $projekt_id ? \get_edit_post_link($projekt_id, '') : '';
	$proj_list_link = \admin_url('edit.php?post_type=projekte');

echo '<p><label id="cmx_label_projekt" data-edit="'.\esc_attr($proj_edit_link).'" data-list="'.\esc_attr($proj_list_link).'" style="cursor:pointer;color:#2271b1;text-decoration:none; position:relative; top:10px;" title="Zum Projekt-Modul springen oder Eintrag editieren"><strong>Projekt</strong></label><br>';
	echo '<div class="cmx-suggest">';
	echo '  <div class="cmx-input-row">';
	echo '    <input type="text" id="cmx_projekt_search" name="cmx_projekt_search" autocomplete="off" value="'.\esc_attr($display_proj).'" placeholder="Projekt suchen...">';
	echo '    <input type="hidden" id="cmx_projekt_id" name="cmx_projekt_id" value="'.\esc_attr((string)$projekt_id).'">';
	echo '    <input type="hidden" id="cmx_projekt_selected" name="cmx_projekt_selected" value="0">';
	echo '    <button type="button" class="button button-small" id="cmx_projekt_clear" title="Auswahl löschen">X</button>';
	echo '  </div>';
	echo '  <ul id="cmx_projekt_suggest" style="display:none"></ul>';
	echo '</div>';
	echo '</p>';

	/* Kontaktsuche */
	$kontakte_title = $kontakt_id ? \get_the_title($kontakt_id) : '';
	$display_kontakt = $kontakt_label ?: $kontakte_title;

	$cpt = cmx_kontakte_cpt();
	$kontakt_edit_link = $kontakt_id ? \get_edit_post_link($kontakt_id, '') : '';
	$kontakt_list_link = \admin_url('edit.php?post_type='.$cpt);

echo '<p><label id="cmx_label_kontakt" data-edit="'.\esc_attr($kontakt_edit_link).'" data-list="'.\esc_attr($kontakt_list_link).'" style="cursor:pointer;color:#2271b1;text-decoration:none; position:relative; top:10px;" title="Zum Kontakte-Modul springen oder Eintrag editieren"><strong>Kontakt</strong></label><br>';
	echo '<div class="cmx-suggest">';
	echo '  <div class="cmx-input-row">';
	echo '    <input type="text" id="cmx_kontakt_search" name="cmx_kontakt_search" autocomplete="off" value="'.\esc_attr($display_kontakt).'" placeholder="Kontakt suchen...">';
	echo '    <input type="hidden" id="cmx_kontakt_id" name="cmx_kontakt_id" value="'.\esc_attr((string)$kontakt_id).'">';
	echo '    <button type="button" class="button button-small" id="cmx_kontakt_clear" title="Auswahl löschen">X</button>';
	echo '  </div>';
	echo '  <ul id="cmx_kontakt_suggest" style="display:none"></ul>';
	echo '</div>';

	/* Adresse */
	if ($addr_text === '' && $kontakt_id) {
		$addr_text = cmx_build_kontakt_postanschrift($kontakt_id);
	}
	echo '<p><label><strong>Postanschrift</strong></label><br>';
	echo '<textarea id="cmx_kontakt_addr" name="cmx_kontakt_addr" class="cmx-addr" rows="5">'.\esc_textarea($addr_text).'</textarea></p>';

	/* Inline JS */
	$ajax_url = \admin_url('admin-ajax.php');
	echo '<script>
		(function(){
			function esc(s){return (s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}
			function makeNavigator(inputEl, listEl, chooseCb){
				let active=-1, items=[];
				function render(arr){
					items=arr||[];
					if(!items.length){listEl.style.display="none"; listEl.innerHTML=""; active=-1; return;}
					listEl.innerHTML = items.map((it,i)=>`<li data-index="${i}">${esc(it.title)}</li>`).join("");
					listEl.style.display="block"; active=-1;
				}
				function move(d){ if(!items.length) return; active=(active+d+items.length)%items.length; [...listEl.children].forEach((li,i)=>li.classList.toggle("active",i===active)); }
				function choose(i){ if(i<0||i>=items.length) return; chooseCb(items[i]); listEl.style.display="none"; listEl.innerHTML=""; active=-1; }
				listEl.addEventListener("mousedown",(e)=>{ const li=e.target.closest("li"); if(!li) return; e.preventDefault(); choose(parseInt(li.dataset.index,10)); });
				inputEl.addEventListener("keydown",(e)=>{
					if(listEl.style.display!=="block"&&(e.key==="ArrowDown"||e.key==="ArrowUp")) return;
					if(e.key==="ArrowDown"){e.preventDefault();move(1);} else if(e.key==="ArrowUp"){e.preventDefault();move(-1);}
					else if(e.key==="Enter"){ if(active>-1){e.preventDefault();choose(active);} }
					else if(e.key==="Escape"){ listEl.style.display="none"; listEl.innerHTML=""; active=-1; }
				});
				document.addEventListener("click",(e)=>{ if(!listEl.contains(e.target)&&e.target!==inputEl){ listEl.style.display="none"; listEl.innerHTML=""; active=-1; }});
				return {render, reset:()=>{items=[];active=-1;}};
			}

				/* === Labels klickbar: editieren ODER zur Liste springen === */
				function openInNewTab(url){
					if(!url || url==="#") return;
					const w = window.open(url, "_blank", "noopener,noreferrer");
					if(w){ w.opener = null; }
				}
				function wireLabel(lblId, hiddenId, listUrl){
					const lbl=document.getElementById(lblId);
					if(!lbl) return;
					lbl.addEventListener("click", function(e){
						e.preventDefault();
						const h=document.getElementById(hiddenId);
						const id=(h&&h.value)?h.value:"";
						if(id){
							const edit=this.getAttribute("data-edit")||"";
							if(edit){ openInNewTab(edit); return; }
						}
						openInNewTab(this.getAttribute("data-list") || listUrl || "#");
					});
				}
			wireLabel("cmx_label_projekt","cmx_projekt_id","edit.php?post_type=projekte");
			wireLabel("cmx_label_kontakt","cmx_kontakt_id","edit.php?post_type=kontakte");

				const betInput=document.getElementById("cmx_beleg_betreff");
				const beschInput=document.getElementById("cmx_beleg_beschreibung");
				const betManualInput=document.getElementById("cmx_beleg_betreff_manual");
				const beschManualInput=document.getElementById("cmx_beleg_beschreibung_manual");

				/* --- Projekte --- */
			const pI=document.getElementById("cmx_projekt_search");
			const pH=document.getElementById("cmx_projekt_id");
			const pS=document.getElementById("cmx_projekt_selected");
				const pL=document.getElementById("cmx_projekt_suggest");
				const pC=document.getElementById("cmx_projekt_clear");
				let pT=null;
				const projNav=makeNavigator(pI,pL,chooseProject);
				function isManual(el){ return !!el && String(el.value||"0")==="1"; }
				function isEmpty(val){ return !val || String(val).trim()===""; }

				function chooseProject(it){
					pI.value=it.title||""; pH.value=it.id||""; pI.focus();
					if(pS){ pS.value="1"; }
					if(it.kontakt_id){
					const kI=document.getElementById("cmx_kontakt_search");
					const kH=document.getElementById("cmx_kontakt_id");
					const kA=document.getElementById("cmx_kontakt_addr");
						if(kI&&kH){ kI.value=it.kontakt_title||""; kH.value=it.kontakt_id||""; }
						if(kA){ kA.value=it.kontakt_addr||""; }
					}
					const bothEmpty = (!betInput || isEmpty(betInput.value)) && (!beschInput || isEmpty(beschInput.value));
					if (bothEmpty) {
						if(betInput){ betInput.value=(it.subject||"").trim(); }
						if(beschInput){ beschInput.value=(it.description||"").trim(); }
						if(betManualInput){ betManualInput.value="0"; }
						if(beschManualInput){ beschManualInput.value="0"; }
						return;
					}
					if(betInput && !isManual(betManualInput)){
						betInput.value=(it.subject||"").trim();
						if(betManualInput){ betManualInput.value="0"; }
					}
					if(beschInput && !isManual(beschManualInput)){
						beschInput.value=(it.description||"").trim();
						if(beschManualInput){ beschManualInput.value="0"; }
					}
				}
				if(betInput && betManualInput){
					betInput.addEventListener("input", function(){ betManualInput.value="1"; });
				}
				if(beschInput && beschManualInput){
					beschInput.addEventListener("input", function(){ beschManualInput.value="1"; });
				}
				function pSearch(q){
					const url="'.\esc_js($ajax_url).'?action=cmx_search_projekte&_ajax_nonce='.\esc_js($ajax_nonce_proj).'&q="+encodeURIComponent(q);
					fetch(url,{credentials:"same-origin"}).then(r=>r.json()).then(j=>{
					if(!j||!j.success){pL.style.display="none"; pL.innerHTML=""; projNav.reset(); return;}
					projNav.render(j.data.items||[]);
				}).catch(()=>{pL.style.display="none"; pL.innerHTML=""; projNav.reset();});
			}
			if(pI&&pH&&pL){
				// Eingabe → Suche
				pI.addEventListener("input",()=>{
					pH.value=""; if(pT) clearTimeout(pT);
					const q=pI.value.trim();
					if(q.length<2){ pL.style.display="none"; pL.innerHTML=""; projNav.reset(); return; }
					pT=setTimeout(()=>pSearch(q),200);
				});
				// NEU: Focus/Click → komplette Liste mit "zuletzt genutzt zuerst"
				pI.addEventListener("focus", ()=>{ if(pT) clearTimeout(pT); pSearch(""); });
				pI.addEventListener("click",  ()=>{ if(pT) clearTimeout(pT); pSearch(""); });
			}
			if(pC){ pC.addEventListener("click",()=>{ pI.value=""; pH.value=""; if(pS){ pS.value="1"; } pL.style.display="none"; pL.innerHTML=""; projNav.reset(); pI.focus(); }); }

			/* --- Kontakte --- */
			const kI=document.getElementById("cmx_kontakt_search");
			const kH=document.getElementById("cmx_kontakt_id");
			const kL=document.getElementById("cmx_kontakt_suggest");
			const kC=document.getElementById("cmx_kontakt_clear");
			const kA=document.getElementById("cmx_kontakt_addr");
			let kT=null;
			const kontNav=makeNavigator(kI,kL,chooseKontakt);

			function chooseKontakt(it){
				kI.value=it.title||""; kH.value=it.id||""; if(kA){kA.value=it.addr||"";} kI.focus();
			}
			function kSearch(q){
				const url="'.\esc_js($ajax_url).'?action=cmx_search_kontakte&_ajax_nonce='.\esc_js($ajax_nonce_kont).'&q="+encodeURIComponent(q);
				fetch(url,{credentials:"same-origin"}).then(r=>r.json()).then(j=>{
					if(!j||!j.success){kL.style.display="none"; kL.innerHTML=""; kontNav.reset(); return;}
					kontNav.render(j.data.items||[]);
				}).catch(()=>{kL.style.display="none"; kL.innerHTML=""; kontNav.reset();});
			}
			if(kI&&kH&&kL){
				kI.addEventListener("input",()=>{
					kH.value=""; if(kT) clearTimeout(kT);
					const q=kI.value.trim();
					if(q.length<2){ kL.style.display="none"; kL.innerHTML=""; kontNav.reset(); return; }
					kT=setTimeout(()=>kSearch(q),200);
				});
				// NEU: Focus/Click → komplette Kontaktliste (zuletzt geändert)
				kI.addEventListener("focus", ()=>{ if(kT) clearTimeout(kT); kSearch(""); });
				kI.addEventListener("click",  ()=>{ if(kT) clearTimeout(kT); kSearch(""); });
			}
			if(kC){ kC.addEventListener("click",()=>{ kI.value=""; kH.value=""; if(kA) kA.value=""; kL.style.display="none"; kL.innerHTML=""; kontNav.reset(); kI.focus(); }); }
		})();
	</script>';

	echo '</div></div>';
}

/* =========================================================
 * Speichern (inkl. Titel = nur Rechnungsnummer)
 * ========================================================= */
\add_action('save_post_belege', function (int $post_id, \WP_Post $post, bool $update) {
	if ($post->post_type !== 'belege') return;
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if (!empty($GLOBALS['cmx_belege_title_updating'])) return;
	if (!isset($GLOBALS['cmx_belege_dup_guard'])) {
		$GLOBALS['cmx_belege_dup_guard'] = [];
	}

	$inv_no = cmx_ensure_rechnungsnummer($post_id);

	$has_nonce = isset($_POST['cmx_beleg_details_nonce']) && \wp_verify_nonce($_POST['cmx_beleg_details_nonce'], 'cmx_beleg_details_save');
	$has_save_as_nonce = isset($_POST['cmx_beleg_save_as_nonce']) && \wp_verify_nonce($_POST['cmx_beleg_save_as_nonce'], 'cmx_beleg_save_as');
	$current_kategorie_slug = '';

	if ($has_nonce) {
		$tax = \function_exists(__NAMESPACE__.'\\cmx_belege_tax') ? cmx_belege_tax() : '';
		if ($tax && isset($_POST['cmx_beleg_kategorie'])) {
			$term_id = (int) $_POST['cmx_beleg_kategorie'];
			$allowed_ids = [];
			$allowed_slugs = cmx_beleg_kategorie_allowed_slugs();
			$terms = \get_terms(['taxonomy'=>$tax,'hide_empty'=>false]);
			foreach ($terms as $term) {
				if (in_array($term->slug, $allowed_slugs, true)) {
					$allowed_ids[] = (int) $term->term_id;
				}
			}
			if ($term_id > 0 && in_array($term_id, $allowed_ids, true)) {
				\wp_set_post_terms($post_id, [$term_id], $tax, false);
				$term_obj = \get_term($term_id, $tax);
				if ($term_obj && !\is_wp_error($term_obj)) {
					$current_kategorie_slug = (string) $term_obj->slug;
				}
			}
		}

		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_RICHTUNG') && isset($_POST['cmx_beleg_richtung'])) {
			$val = \sanitize_key(\wp_unslash($_POST['cmx_beleg_richtung']));
			$opts = cmx_beleg_richtung_options();
			if (!isset($opts[$val])) {
				$val = array_key_first($opts);
			}
			\update_post_meta($post_id, CMX_BELEG_META_RICHTUNG, $val);
		}

		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF') && isset($_POST['cmx_beleg_betreff'])) {
			\update_post_meta($post_id, CMX_BELEG_META_BETREFF, \sanitize_text_field(\wp_unslash($_POST['cmx_beleg_betreff'])));
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG') && isset($_POST['cmx_beleg_beschreibung'])) {
			\update_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, \wp_kses_post(\wp_unslash($_POST['cmx_beleg_beschreibung'])));
		}

		$betreff_manual = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF_MANUAL')
			? ((isset($_POST['cmx_beleg_betreff_manual']) ? (int)\wp_unslash($_POST['cmx_beleg_betreff_manual']) : (int)\get_post_meta($post_id, CMX_BELEG_META_BETREFF_MANUAL, true)) === 1)
			: false;
		$beschreibung_manual = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG_MANUAL')
			? ((isset($_POST['cmx_beleg_beschreibung_manual']) ? (int)\wp_unslash($_POST['cmx_beleg_beschreibung_manual']) : (int)\get_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG_MANUAL, true)) === 1)
			: false;
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF_MANUAL')) {
			if ($betreff_manual) {
				\update_post_meta($post_id, CMX_BELEG_META_BETREFF_MANUAL, 1);
			} else {
				\delete_post_meta($post_id, CMX_BELEG_META_BETREFF_MANUAL);
			}
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG_MANUAL')) {
			if ($beschreibung_manual) {
				\update_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG_MANUAL, 1);
			} else {
				\delete_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG_MANUAL);
			}
		}

		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ID') && isset($_POST['cmx_kontakt_id'])) {
			$kid = (int) $_POST['cmx_kontakt_id'];
			if ($kid > 0) \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID, $kid);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_ID);
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_ADDR') && isset($_POST['cmx_kontakt_addr'])) {
			\update_post_meta($post_id, CMX_BELEG_META_KONTAKT_ADDR, \wp_kses_post(\wp_unslash($_POST['cmx_kontakt_addr'])));
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_KONTAKT_LABEL') && isset($_POST['cmx_kontakt_search'])) {
			$k_label = \sanitize_text_field(\wp_unslash($_POST['cmx_kontakt_search']));
			if ($k_label !== '') \update_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL, $k_label);
			else \delete_post_meta($post_id, CMX_BELEG_META_KONTAKT_LABEL);
		}

		$previous_projekt_id = \defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID')
			? (int) \get_post_meta($post_id, CMX_BELEG_META_PROJEKT_ID, true)
			: 0;
		$projekt_selection_changed = isset($_POST['cmx_projekt_selected']) && (int) \wp_unslash($_POST['cmx_projekt_selected']) === 1;

		$selected_projekt_id = 0;
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_ID') && isset($_POST['cmx_projekt_id'])) {
			$pid = (int) $_POST['cmx_projekt_id'];
			$selected_projekt_id = $pid;
			if ($pid > 0) \update_post_meta($post_id, CMX_BELEG_META_PROJEKT_ID, $pid);
			else \delete_post_meta($post_id, CMX_BELEG_META_PROJEKT_ID);
		}
		if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_PROJEKT_LABEL') && isset($_POST['cmx_projekt_search'])) {
			$proj_label = \sanitize_text_field(\wp_unslash($_POST['cmx_projekt_search']));
			if ($proj_label !== '') \update_post_meta($post_id, CMX_BELEG_META_PROJEKT_LABEL, $proj_label);
			else \delete_post_meta($post_id, CMX_BELEG_META_PROJEKT_LABEL);
		}
		if ($selected_projekt_id > 0) {
			$current_betreff = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF')
				? (string) \get_post_meta($post_id, CMX_BELEG_META_BETREFF, true)
				: '';
			$current_beschreibung = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG')
				? (string) \get_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, true)
				: '';
			$both_empty = \trim($current_betreff) === '' && \trim(\wp_strip_all_tags($current_beschreibung)) === '';

			if ($projekt_selection_changed && $both_empty) {
				$projekt_betreff = cmx_get_projekt_betreff_value($selected_projekt_id);
				$projekt_beschreibung = cmx_get_projekt_beschreibung_value($selected_projekt_id);

				if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF') && $projekt_betreff !== '') {
					\update_post_meta($post_id, CMX_BELEG_META_BETREFF, $projekt_betreff);
				}
				if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG') && $projekt_beschreibung !== '') {
					\update_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, $projekt_beschreibung);
				}
				if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF_MANUAL')) {
					\delete_post_meta($post_id, CMX_BELEG_META_BETREFF_MANUAL);
					$betreff_manual = false;
				}
				if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG_MANUAL')) {
					\delete_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG_MANUAL);
					$beschreibung_manual = false;
				}

				$current_betreff = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF')
					? (string) \get_post_meta($post_id, CMX_BELEG_META_BETREFF, true)
					: $current_betreff;
				$current_beschreibung = \defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG')
					? (string) \get_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, true)
					: $current_beschreibung;
			}

			if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF')) {
				if (!$betreff_manual && \trim($current_betreff) === '') {
					$projekt_betreff = cmx_get_projekt_betreff_value($selected_projekt_id);
					if ($projekt_betreff !== '') {
						\update_post_meta($post_id, CMX_BELEG_META_BETREFF, $projekt_betreff);
					}
				}
			}
			if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG')) {
				if (!$beschreibung_manual && \trim(\wp_strip_all_tags($current_beschreibung)) === '') {
					$projekt_beschreibung = cmx_get_projekt_beschreibung_value($selected_projekt_id);
					if ($projekt_beschreibung !== '') {
						\update_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG, $projekt_beschreibung);
					}
				}
			}
		}
		if ($selected_projekt_id <= 0 && ($previous_projekt_id > 0 || $projekt_selection_changed)) {
			if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BETREFF') && !$betreff_manual) {
				\delete_post_meta($post_id, CMX_BELEG_META_BETREFF);
			}
			if (\defined(__NAMESPACE__.'\\CMX_BELEG_META_BESCHREIBUNG') && !$beschreibung_manual) {
				\delete_post_meta($post_id, CMX_BELEG_META_BESCHREIBUNG);
			}
		}
		}

	if ($current_kategorie_slug === '') {
		$tax = \function_exists(__NAMESPACE__.'\\cmx_belege_tax') ? cmx_belege_tax() : '';
		if ($tax) {
			$slugs = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
			if (!\is_wp_error($slugs) && !empty($slugs)) {
				$current_kategorie_slug = (string) $slugs[0];
			}
		}
	}

	if ($has_save_as_nonce && isset($_POST['cmx_beleg_save_as'])) {
		$val = \sanitize_key(\wp_unslash($_POST['cmx_beleg_save_as']));
		$copy_map = [
			'rechnung' => 'lieferschein',
			'rechnungen' => 'lieferschein',
			'offerte'  => 'rechnung',
			'offerten' => 'rechnung',
		];
		$normalized_val = $val;
		if ($val === 'rechnung_kopie') {
			$normalized_val = 'rechnung';
		}
		if (!empty($GLOBALS['cmx_belege_duplication_in_progress'])) {
			return;
		}
		if (!empty($GLOBALS['cmx_belege_dup_guard'][$post_id])) {
			return;
		}
		if (isset($copy_map[$current_kategorie_slug]) && $copy_map[$current_kategorie_slug] === $normalized_val) {
			$is_rechnung_to_lieferschein = \in_array($current_kategorie_slug, ['rechnung', 'rechnungen'], true)
				&& \in_array($normalized_val, ['lieferschein', 'lieferscheine'], true);
			$GLOBALS['cmx_belege_dup_guard'][$post_id] = true;
			$GLOBALS['cmx_belege_duplication_in_progress'] = true;
			$dup_fn = __NAMESPACE__ . '\\cmx_duplicate_do';
			$existing_id = 0;
			if (!$is_rechnung_to_lieferschein) {
				$existing_id = (int) \get_post_meta($post_id, '_cmx_beleg_copied_to', true);
				if ($existing_id > 0) {
					$existing_post = \get_post($existing_id);
					if (!$existing_post || $existing_post->post_type !== 'belege' || $existing_post->post_status === 'trash') {
						$existing_id = 0;
					}
				}
			}
			if ($existing_id > 0) {
				$new_id = $existing_id;
				$GLOBALS['cmx_skip_beleg_pdf_generation'] = true;
				cmx_sync_beleg_duplicate($post_id, $new_id);
				unset($GLOBALS['cmx_skip_beleg_pdf_generation']);
			} elseif (\is_callable($dup_fn)) {
				$GLOBALS['cmx_skip_beleg_pdf_generation'] = true;
				$new_id = $dup_fn($post_id);
				unset($GLOBALS['cmx_skip_beleg_pdf_generation']);
			} else {
				$new_id = 0;
			}
			if (!\is_wp_error($new_id) && $new_id > 0) {
					$tax = \function_exists(__NAMESPACE__.'\\cmx_belege_tax') ? cmx_belege_tax() : '';
					if ($tax) {
						$term = \get_term_by('slug', $normalized_val, $tax);
						if (!$term) {
							$term = \get_term_by('name', ucfirst($normalized_val), $tax);
						}
						if ($term && !\is_wp_error($term)) {
							\wp_set_post_terms($new_id, [(int) $term->term_id], $tax, false);
						}
					}
					\delete_post_meta($new_id, '_cmx_beleg_pdf_type');
					if ($is_rechnung_to_lieferschein) {
						$prep_fn = __NAMESPACE__ . '\\cmx_beleg_prepare_lieferschein_duplicate_positions';
						if (\is_callable($prep_fn)) {
							$prep_fn((int)$post_id, (int)$new_id);
						}
					}
					\update_post_meta($new_id, '_cmx_beleg_copied_from', (int) $post_id);
					\update_post_meta($post_id, '_cmx_beleg_copied_to', (int) $new_id);
					$gen_fn = __NAMESPACE__ . '\\cmxbu_generate_document_on_save';
					if (\is_callable($gen_fn)) {
						$new_post = \get_post($new_id);
						if ($new_post) {
							$gen_fn($new_id, $new_post, true);
						}
					}
					$GLOBALS['cmx_belege_dup_redirect_id'] = (int) $new_id;
			}
			unset($GLOBALS['cmx_belege_duplication_in_progress']);
			unset($GLOBALS['cmx_belege_dup_guard'][$post_id]);
		}
	}

	$current = \get_post($post_id);
	$should_set_title = empty($current->post_title) || ((int)\get_post_meta($post_id, '_cmx_title_auto', true) === 1);

	if ($should_set_title) {
		$new_title = $inv_no;
		$GLOBALS['cmx_belege_title_updating'] = true;

		\wp_update_post(['ID' => $post_id,'post_title' => $new_title,'post_name' => \sanitize_title($new_title),]);
		unset($GLOBALS['cmx_belege_title_updating']);
		\update_post_meta($post_id, '_cmx_title_auto', 1);
	} else {
		\delete_post_meta($post_id, '_cmx_title_auto');
	}
// Später ausführen, damit alle anderen Beleg-Metaboxen ihre Werte bereits gespeichert haben,
// bevor "Save as"/Duplizieren den Datensatz kopiert.
}, 200, 3);

add_filter('redirect_post_location', function (string $location, int $post_id): string {
	if (!isset($GLOBALS['cmx_belege_dup_redirect_id'])) {
		return $location;
	}
	$new_id = (int) $GLOBALS['cmx_belege_dup_redirect_id'];
	unset($GLOBALS['cmx_belege_dup_redirect_id']);
	if ($new_id <= 0) {
		return $location;
	}
	$edit_link = \get_edit_post_link($new_id, '');
	return $edit_link ? $edit_link : $location;
}, 10, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_has_tax_slug')) {
	function cmx_beleg_has_tax_slug(int $post_id, array $allowed_slugs): bool {
		$post = \get_post($post_id);
		if (!$post || $post->post_type !== 'belege') {
			return false;
		}
		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_tax') ? cmx_belege_tax() : '';
		if ($tax === '') {
			return false;
		}
		$slugs = \wp_get_post_terms($post_id, $tax, ['fields' => 'slugs']);
		if (\is_wp_error($slugs) || empty($slugs)) {
			return false;
		}
		foreach ((array) $slugs as $slug) {
			if (\in_array((string) $slug, $allowed_slugs, true)) {
				return true;
			}
		}
		return false;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_get_delete_redirect_rechnung_id')) {
	function cmx_beleg_get_delete_redirect_rechnung_id(int $post_id): int {
		$post = \get_post($post_id);
		if (!$post || $post->post_type !== 'belege') {
			return 0;
		}
		// Redirect nur, wenn ein Lieferschein gelöscht wird.
		if (!cmx_beleg_has_tax_slug($post_id, ['lieferschein', 'lieferscheine'])) {
			return 0;
		}
		$rechnung_id = (int) \get_post_meta($post_id, '_cmx_beleg_copied_from', true);
		if ($rechnung_id <= 0) {
			return 0;
		}
		$rechnung_post = \get_post($rechnung_id);
		if (!$rechnung_post || $rechnung_post->post_type !== 'belege' || $rechnung_post->post_status === 'trash') {
			return 0;
		}
		// Falls Kategorie nicht sauber gesetzt ist, trotzdem auf den verknüpften Beleg gehen.
		return $rechnung_id;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_capture_delete_redirect_target')) {
	function cmx_beleg_capture_delete_redirect_target(int $post_id): void {
		if (!\is_admin()) {
			return;
		}
		$rechnung_id = cmx_beleg_get_delete_redirect_rechnung_id($post_id);
		if ($rechnung_id <= 0) {
			return;
		}
		if (!\current_user_can('edit_post', $rechnung_id)) {
			return;
		}
		$GLOBALS['cmx_belege_delete_redirect_id'] = $rechnung_id;
	}
}

// Nach dem Löschen/Trash eines verknüpften Lieferscheins direkt zur Rechnung springen.
\add_filter('wp_redirect', function (string $location, int $status): string {
	if (empty($GLOBALS['cmx_belege_delete_redirect_id'])) {
		return $location;
	}
	$request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
	$path = (string) \parse_url($request_uri, PHP_URL_PATH);
	if (\basename($path) !== 'post.php') {
		return $location;
	}
	$action = isset($_REQUEST['action']) ? \sanitize_key((string) \wp_unslash($_REQUEST['action'])) : '';
	if (!\in_array($action, ['trash', 'delete'], true)) {
		return $location;
	}
	$rechnung_id = (int) $GLOBALS['cmx_belege_delete_redirect_id'];
	unset($GLOBALS['cmx_belege_delete_redirect_id']);
	if ($rechnung_id <= 0) {
		return $location;
	}
	$edit_link = \get_edit_post_link($rechnung_id, '');
	return $edit_link ?: $location;
}, 10, 2);

add_action('before_delete_post', function (int $post_id) {
	$post = \get_post($post_id);
	if (!$post || $post->post_type !== 'belege') {
		return;
	}
	cmx_beleg_capture_delete_redirect_target($post_id);

	$from_id = (int) \get_post_meta($post_id, '_cmx_beleg_copied_from', true);
	if ($from_id > 0) {
		\delete_post_meta($from_id, '_cmx_beleg_copied_to');
	}

	$to_id = (int) \get_post_meta($post_id, '_cmx_beleg_copied_to', true);
	if ($to_id > 0) {
		\delete_post_meta($to_id, '_cmx_beleg_copied_from');
	}
});

add_action('trashed_post', function (int $post_id) {
	$post = \get_post($post_id);
	if (!$post || $post->post_type !== 'belege') {
		return;
	}
	cmx_beleg_capture_delete_redirect_target($post_id);

	$from_id = (int) \get_post_meta($post_id, '_cmx_beleg_copied_from', true);
	if ($from_id > 0) {
		\delete_post_meta($from_id, '_cmx_beleg_copied_to');
	}

	$to_id = (int) \get_post_meta($post_id, '_cmx_beleg_copied_to', true);
	if ($to_id > 0) {
		\delete_post_meta($to_id, '_cmx_beleg_copied_from');
	}
});
