<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Übernimmt offene (nicht abgerechnete) Projekt-Tasks als Belegpositionen,
 * wenn beim Anlegen eines Belegs ein Projekt ausgewählt wurde.
 */

\add_action('save_post_belege', __NAMESPACE__ . '\\cmxbu_add_tasks_to_beleg', 2, 3);
function cmxbu_add_tasks_to_beleg(int $post_id, \WP_Post $post, bool $update): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id)) return;
	if ($post->post_status === 'auto-draft') return;

	// Projekt-ID aus POST oder Meta holen
	$projekt_id = isset($_POST['cmx_projekt_id']) ? (int) $_POST['cmx_projekt_id'] : 0;
	if ($projekt_id <= 0 && function_exists(__NAMESPACE__.'\\cmx_meta_projekt_ids')) {
		foreach (cmx_meta_projekt_ids() as $key) {
			$projekt_id = (int) \get_post_meta($post_id, $key, true);
			if ($projekt_id > 0) break;
		}
	}
	if ($projekt_id <= 0) {
		$projekt_id = (int) \get_post_meta($post_id, 'cmx_projekt_id', true);
		// fallback auf eventuelle alte Keys
		if ($projekt_id <= 0) {
			$projekt_id = (int) \get_post_meta($post_id, '_projekt_id', true);
		}
	}
	if ($projekt_id <= 0) return;

	// Debug: Protokolliere Importversuch (einmal pro Save)
	\error_log("[CMX] Beleg {$post_id}: versuche Tasks von Projekt {$projekt_id} zu importieren.");

	// Falls bereits importiert → abbrechen, um Duplikate zu vermeiden
	$already_imported = (string) \get_post_meta($post_id, '_cmx_beleg_tasks_imported', true);
	if ($already_imported === (string) $projekt_id) {
		\error_log("[CMX] Beleg {$post_id}: Tasks bereits importiert, überspringe.");
		return;
	}

	// Projekt-ID ggf. persistieren
	update_post_meta($post_id, '_cmx_beleg_projekt_id', $projekt_id);

	// Tasks des Projekts laden
	$tasks_raw = \get_post_meta($projekt_id, '_cmx_projekt_tasks', true);
	if (is_string($tasks_raw) && $tasks_raw !== '') {
		$tmp = json_decode($tasks_raw, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$tasks = $tmp;
		} else {
			$tmp = @maybe_unserialize($tasks_raw);
			$tasks = is_array($tmp) ? $tmp : [];
		}
	} elseif (is_array($tasks_raw)) {
		$tasks = $tasks_raw;
	} else {
		$tasks = [];
	}
	if (empty($tasks)) {
		\error_log("[CMX] Beleg {$post_id}: keine Tasks gefunden.");
		return;
	}

	$artikel_vks = [];

	// Bestehende Positionen robust laden (JSON-String oder Array)
	$positionen_raw = \get_post_meta($post_id, '_cmx_beleg_positionen', true);
	if (is_string($positionen_raw) && $positionen_raw !== '') {
		$tmp = json_decode($positionen_raw, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$positionen = $tmp;
		} else {
			$positionen = @maybe_unserialize($positionen_raw);
			if (!is_array($positionen)) $positionen = [];
		}
	} elseif (is_array($positionen_raw)) {
		$positionen = $positionen_raw;
	} else {
		$positionen = [];
	}

	$import_result = cmxbu_collect_task_positionen($tasks, $artikel_vks, false);
	$positionen = array_merge($positionen, $import_result['positionen']);

	if (!$import_result['imported'] && $import_result['skipped_done'] > 0) {
		// Fallback: wenn alles abgerechnet war, dennoch importieren (z. B. zum Nachfassen)
		$import_force = cmxbu_collect_task_positionen($tasks, $artikel_vks, true);
		$positionen = array_merge($positionen, $import_force['positionen']);
		$import_result = [
			'imported'      => $import_force['imported'],
			'total'         => $import_result['total'],
			'skipped_done'  => $import_force['skipped_done'],
			'skipped_empty' => $import_force['skipped_empty'],
			'skipped_no_price' => $import_force['skipped_no_price'],
		];
	}

	if ($import_result['imported']) {
		$positionen = array_values($positionen);
		$_POST['cmx_positionen'] = $positionen;
		// In Request einspeisen, damit andere Hooks (z. B. positionen.php) die Daten wie gewohnt speichern
		\update_post_meta($post_id, '_cmx_beleg_positionen', wp_json_encode($positionen));
		\update_post_meta($projekt_id, '_cmx_projekt_tasks', $tasks); // Tasks aktualisieren (abgerechnet markieren)
		\update_post_meta($post_id, '_cmx_beleg_tasks_imported', (string) $projekt_id);
		\error_log("[CMX] Beleg {$post_id}: ".count($positionen)." Positionen (inkl. Tasks) gesetzt und Tasks als abgerechnet markiert.");
	} else {
		\error_log("[CMX] Beleg {$post_id}: keine neuen Tasks importiert. Tasks gesamt={$import_result['total']}, abgerechnet={$import_result['skipped_done']}, ohne Art/Dauer={$import_result['skipped_empty']}, ohne Preis={$import_result['skipped_no_price']}.");
	}
}

/**
 * Sammle Positionen aus Tasks. Kann optional abgerechnete ignorieren oder erzwingen.
 */
function cmxbu_collect_task_positionen(array &$tasks, array &$artikel_vks, bool $force_import_abgerechnet = false): array {
	$positionen = [];
	$total = $skipped_done = $skipped_empty = $skipped_no_price = 0;
	$imported = false;

	foreach ($tasks as &$t) {
		if (!is_array($t)) continue;
		$total++;
		$flag = $t['abgerechnet'] ?? '';
		$is_done = in_array((string)$flag, ['1','true','yes'], true) || $flag === true;
		if ($is_done && !$force_import_abgerechnet) { $skipped_done++; continue; }

		$art_id = (int) ($t['artikel_id'] ?? 0);
		$dauer  = (float) str_replace(',', '.', (string) ($t['dauer'] ?? 0));
		if ($art_id <= 0 || $dauer <= 0) { $skipped_empty++; continue; }

		// VK laden (gecacht)
		if (!isset($artikel_vks[$art_id])) {
			$vk_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
			$vk_raw = \get_post_meta($art_id, $vk_key, true);
			$artikel_vks[$art_id] = (float) str_replace(',', '.', (string) $vk_raw);
		}
		$vk = $artikel_vks[$art_id];
		if (!is_finite($vk)) $vk = 0.0;
		if ($vk <= 0) { $skipped_no_price++; continue; }

		$positionen[] = [
			'artikel_id'   => $art_id,
			'artikel_name' => \get_the_title($art_id) ?: '',
			'menge'        => (string)$dauer,
			'preis'        => (string)$vk,
			'rabatt'       => '',
			'beschreibung' => $t['info'] ?? '',
		];

		$t['abgerechnet'] = 1;
		$imported = true;
	}
	unset($t);

	return [
		'positionen'      => $positionen,
		'imported'        => $imported,
		'total'           => $total,
		'skipped_done'    => $skipped_done,
		'skipped_empty'   => $skipped_empty,
		'skipped_no_price'=> $skipped_no_price,
	];
}
