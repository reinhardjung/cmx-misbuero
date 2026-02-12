<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/**
 * Übernimmt offene (nicht abgerechnete) Projekt-Tasks als Belegpositionen,
 * wenn beim Anlegen eines Belegs ein Projekt ausgewählt wurde.
 */

\add_action('save_post_belege', __NAMESPACE__ . '\\cmxbu_add_tasks_to_beleg', 2, 3);
\add_action('save_post_belege', __NAMESPACE__ . '\\cmxbu_mark_project_tasks_paid', 30, 3);
\add_action('save_post_belege', __NAMESPACE__ . '\\cmxbu_sync_task_billing_flags', 50, 3);

function cmxbu_meta_array(int $post_id, string $meta_key): array {
	$raw = \get_post_meta($post_id, $meta_key, true);
	if (\is_string($raw) && $raw !== '') {
		$tmp = \json_decode($raw, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($tmp)) return $tmp;
		$tmp = @\maybe_unserialize($raw);
		return \is_array($tmp) ? $tmp : [];
	}
	return \is_array($raw) ? $raw : [];
}

function cmxbu_truthy($value): bool {
	if ($value === true) return true;
	if ($value === 1 || $value === '1') return true;
	$s = \strtolower(\trim((string) $value));
	return \in_array($s, ['true', 'yes', 'on'], true);
}

function cmxbu_normalize_task_uid($raw = ''): string {
	$uid = (string) $raw;
	$uid = (string) \preg_replace('/[^A-Za-z0-9_-]/', '', $uid);
	$uid = \substr($uid, 0, 80);
	return $uid;
}

function cmxbu_create_task_uid(): string {
	$seed = '';
	if (\function_exists('\\wp_generate_uuid4')) {
		$seed = (string) \wp_generate_uuid4();
	}
	if ($seed === '') {
		$seed = \uniqid('', true);
	}
	$seed = (string) \preg_replace('/[^A-Za-z0-9]/', '', $seed);
	if ($seed === '') {
		$seed = (string) \mt_rand(100000, 999999) . (string) \time();
	}
	return 'tsk_' . \substr($seed, 0, 64);
}

function cmxbu_add_tasks_to_beleg(int $post_id, \WP_Post $post, bool $update): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id)) return;
	if ($post->post_status === 'auto-draft') return;

	// Import nur dann auslösen, wenn im Formular aktiv eine Projektauswahl gemacht wurde.
	$selection_raw = isset($_POST['cmx_projekt_selected']) ? \wp_unslash($_POST['cmx_projekt_selected']) : '';
	$project_selection_made = \function_exists(__NAMESPACE__ . '\\cmxbu_truthy')
		? cmxbu_truthy($selection_raw)
		: (\trim((string) $selection_raw) === '1');
	if (!$project_selection_made) return;

	// Projekt-ID nur aus der aktuellen Formularauswahl übernehmen.
	$projekt_id = isset($_POST['cmx_projekt_id']) ? (int) $_POST['cmx_projekt_id'] : 0;
	if ($projekt_id <= 0) return;

	// Debug: Protokolliere Importversuch (einmal pro Save)
	\error_log("[CMX] Beleg {$post_id}: versuche Tasks von Projekt {$projekt_id} zu importieren.");

	// Projekt-ID ggf. persistieren
	\update_post_meta($post_id, '_cmx_beleg_projekt_id', $projekt_id);

	// Tasks des Projekts laden
	$tasks = \function_exists(__NAMESPACE__ . '\\cmxbu_meta_array')
		? cmxbu_meta_array($projekt_id, '_cmx_projekt_tasks')
		: [];
	if (empty($tasks)) {
		\error_log("[CMX] Beleg {$post_id}: keine Tasks gefunden.");
		return;
	}

	$artikel_vks = [];

	// Bestehende Positionen robust laden (JSON-String oder Array)
	$positionen = \function_exists(__NAMESPACE__ . '\\cmxbu_meta_array')
		? cmxbu_meta_array($post_id, '_cmx_beleg_positionen')
		: [];

	$import_result = cmxbu_collect_task_positionen($tasks, $artikel_vks);
	$positionen = array_merge($positionen, $import_result['positionen']);

	$project_tasks_changed = !empty($import_result['uids_assigned']);
	if ($import_result['imported']) {
		$positionen = array_values($positionen);
		$_POST['cmx_positionen'] = $positionen;
		// In Request einspeisen, damit andere Hooks (z. B. positionen.php) die Daten wie gewohnt speichern
		\update_post_meta($post_id, '_cmx_beleg_positionen', wp_json_encode($positionen));
		\update_post_meta($post_id, '_cmx_beleg_tasks_imported', (string) $projekt_id);
		\update_post_meta($post_id, '_cmx_beleg_tasks_imported_keys', wp_json_encode($import_result['imported_keys']));
		\update_post_meta($post_id, '_cmx_beleg_tasks_imported_uids', wp_json_encode($import_result['imported_uids']));
		// Tasks im Projekt sofort als abgerechnet markieren, damit sie nicht erneut importiert werden
		$uid_set = [];
		foreach (($import_result['imported_uids'] ?? []) as $uid) {
			$uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid') ? cmxbu_normalize_task_uid($uid) : (string) $uid;
			if ($uid !== '') $uid_set[$uid] = true;
		}
		foreach ($tasks as &$task_row) {
			if (!\is_array($task_row)) continue;
			$uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid')
				? cmxbu_normalize_task_uid($task_row['uid'] ?? '')
				: (string) ($task_row['uid'] ?? '');
			if ($uid !== '' && isset($uid_set[$uid])) {
				$task_row['abgerechnet'] = 1;
				$project_tasks_changed = true;
			}
		}
		unset($task_row);
		\error_log("[CMX] Beleg {$post_id}: ".count($import_result['positionen'])." Task-Positionen ergänzt (Projekt {$projekt_id}).");
	} else {
		\error_log("[CMX] Beleg {$post_id}: keine neuen Tasks importiert. Tasks gesamt={$import_result['total']}, abgerechnet={$import_result['skipped_done']}, ohne Art/Dauer={$import_result['skipped_empty']}, ohne Preis={$import_result['skipped_no_price']}.");
	}

	if ($project_tasks_changed) {
		\update_post_meta($projekt_id, '_cmx_projekt_tasks', $tasks);
	}
}

/**
 * Wenn Beleg bezahlt wurde, markiere alle Tasks des zugeordneten Projekts als abgerechnet.
 */
function cmxbu_mark_project_tasks_paid(int $post_id, \WP_Post $post, bool $update): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id)) return;
	if ($post->post_status === 'auto-draft') return;

	// Bezahlt-Datum aus POST oder Meta
	$paid_date = isset($_POST['cmx_beleg_bezahlt_am']) ? trim((string) $_POST['cmx_beleg_bezahlt_am']) : '';
	if ($paid_date === '') {
		$paid_date = (string) \get_post_meta($post_id, \defined(__NAMESPACE__.'\\CMX_BELEG_META_BEZAHLT_AM') ? CMX_BELEG_META_BEZAHLT_AM : '_cmx_beleg_bezahlt_am', true);
	}
	if ($paid_date === '') return;

	// Wurden Tasks importiert? Nur dann weiter
	$projekt_imported = (string) \get_post_meta($post_id, '_cmx_beleg_tasks_imported', true);
	if ($projekt_imported === '') return;
	$imported_uids_json = (string) \get_post_meta($post_id, '_cmx_beleg_tasks_imported_uids', true);
	$imported_uids = $imported_uids_json ? json_decode($imported_uids_json, true) : [];
	$imported_keys_json = (string) \get_post_meta($post_id, '_cmx_beleg_tasks_imported_keys', true);
	$imported_keys = $imported_keys_json ? json_decode($imported_keys_json, true) : [];
	if (!is_array($imported_keys)) $imported_keys = [];
	if (!is_array($imported_uids)) $imported_uids = [];
	if (empty($imported_keys) && empty($imported_uids)) return;

	// Projekt-ID ermitteln
	$projekt_id = (int) \get_post_meta($post_id, '_cmx_beleg_projekt_id', true);
	if ($projekt_id <= 0 && function_exists(__NAMESPACE__.'\\cmx_meta_projekt_ids')) {
		foreach (cmx_meta_projekt_ids() as $key) {
			$projekt_id = (int) \get_post_meta($post_id, $key, true);
			if ($projekt_id > 0) break;
		}
	}
	if ($projekt_id <= 0) return;

	$tasks = \function_exists(__NAMESPACE__ . '\\cmxbu_meta_array')
		? cmxbu_meta_array($projekt_id, '_cmx_projekt_tasks')
		: [];

	if (empty($tasks)) return;

	$uid_set = [];
	foreach ($imported_uids as $uid) {
		$uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid') ? cmxbu_normalize_task_uid($uid) : (string) $uid;
		if ($uid !== '') $uid_set[$uid] = true;
	}
	$key_set = [];
	foreach ($imported_keys as $idx) {
		if ($idx === '' || $idx === null || !\is_numeric((string) $idx)) continue;
		$key_set[(int) $idx] = true;
	}

	$updated = false;
	foreach ($tasks as $idx => &$task_row) {
		if (!\is_array($task_row)) continue;
		$task_uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid')
			? cmxbu_normalize_task_uid($task_row['uid'] ?? '')
			: (string) ($task_row['uid'] ?? '');
		$match = ($task_uid !== '' && isset($uid_set[$task_uid])) || isset($key_set[(int) $idx]);
		if (!$match) continue;

		if (!\function_exists(__NAMESPACE__ . '\\cmxbu_truthy') || !cmxbu_truthy($task_row['abgerechnet'] ?? 0)) {
			$task_row['abgerechnet'] = 1;
			$updated = true;
		}
	}
	unset($task_row);

	if ($updated) {
		\update_post_meta($projekt_id, '_cmx_projekt_tasks', $tasks);
		\error_log("[CMX] Beleg {$post_id}: Projekt-Tasks von {$projekt_id} als abgerechnet markiert (Beleg bezahlt am {$paid_date}).");
	}
}

/**
 * Synchronisiere abgerechnet-Status mit aktuellen Positionen.
 * Wenn importierte Task-Positionen entfernt wurden, wird der Task wieder auf "nicht abgerechnet" gesetzt.
 */
function cmxbu_sync_task_billing_flags(int $post_id, \WP_Post $post, bool $update): void {
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (\wp_is_post_revision($post_id)) return;
	if ($post->post_status === 'auto-draft') return;
	if (!\current_user_can('edit_post', $post_id)) return;
	if ($post->post_type !== 'belege') return;

	$projekt_imported = (string) \get_post_meta($post_id, '_cmx_beleg_tasks_imported', true);
	if ($projekt_imported === '') return;
	$imported_uids_json = (string) \get_post_meta($post_id, '_cmx_beleg_tasks_imported_uids', true);
	$imported_uids = $imported_uids_json ? json_decode($imported_uids_json, true) : [];
	$imported_keys_json = (string) \get_post_meta($post_id, '_cmx_beleg_tasks_imported_keys', true);
	$imported_keys = $imported_keys_json ? json_decode($imported_keys_json, true) : [];
	if (!is_array($imported_keys)) $imported_keys = [];
	if (!is_array($imported_uids)) $imported_uids = [];
	if (empty($imported_keys) && empty($imported_uids)) return;

	$projekt_id = (int) \get_post_meta($post_id, '_cmx_beleg_projekt_id', true);
	if ($projekt_id <= 0 && function_exists(__NAMESPACE__.'\\cmx_meta_projekt_ids')) {
		foreach (cmx_meta_projekt_ids() as $key) {
			$projekt_id = (int) \get_post_meta($post_id, $key, true);
			if ($projekt_id > 0) break;
		}
	}
	if ($projekt_id <= 0) return;

	$tasks = \function_exists(__NAMESPACE__ . '\\cmxbu_meta_array')
		? cmxbu_meta_array($projekt_id, '_cmx_projekt_tasks')
		: [];
	if (empty($tasks)) return;

	// Aktuelle Positionen laden und Task-Referenzen sammeln
	$positionen = \function_exists(__NAMESPACE__ . '\\cmxbu_meta_array')
		? cmxbu_meta_array($post_id, '_cmx_beleg_positionen')
		: [];
	$present_uid = [];
	$present_idx = [];
	foreach ($positionen as $row) {
		if (!\is_array($row)) continue;
		$row_uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid')
			? cmxbu_normalize_task_uid($row['task_uid'] ?? '')
			: (string) ($row['task_uid'] ?? '');
		if ($row_uid !== '') {
			$present_uid[$row_uid] = true;
		}
		if (isset($row['task_idx']) && $row['task_idx'] !== '' && \is_numeric((string) $row['task_idx'])) {
			$present_idx[(int)$row['task_idx']] = true;
		}
	}

	$import_uid_set = [];
	foreach ($imported_uids as $uid) {
		$uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid') ? cmxbu_normalize_task_uid($uid) : (string) $uid;
		if ($uid !== '') $import_uid_set[$uid] = true;
	}
	$import_key_set = [];
	foreach ($imported_keys as $idx) {
		if ($idx === '' || $idx === null || !\is_numeric((string) $idx)) continue;
		$import_key_set[(int) $idx] = true;
	}

	// Legacy-Migration: wenn nur idx bekannt ist, importierte UIDs einmalig nachtragen.
	$tasks_uid_migrated = false;
	if (empty($import_uid_set) && !empty($import_key_set)) {
		$migrated_uids = [];
		foreach ($import_key_set as $idx => $_) {
			if (!isset($tasks[$idx]) || !\is_array($tasks[$idx])) continue;
			$uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid')
				? cmxbu_normalize_task_uid($tasks[$idx]['uid'] ?? '')
				: (string) ($tasks[$idx]['uid'] ?? '');
			if ($uid === '' && \function_exists(__NAMESPACE__ . '\\cmxbu_create_task_uid')) {
				$uid = cmxbu_create_task_uid();
				$tasks[$idx]['uid'] = $uid;
				$tasks_uid_migrated = true;
			}
			if ($uid !== '') {
				$migrated_uids[$uid] = true;
			}
		}
		if (!empty($migrated_uids)) {
			$import_uid_set = $migrated_uids;
			\update_post_meta($post_id, '_cmx_beleg_tasks_imported_uids', \wp_json_encode(\array_keys($migrated_uids)));
		}
	}

	$updated = false;
	foreach ($tasks as $idx => &$task_row) {
		if (!\is_array($task_row)) continue;
		$task_uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid')
			? cmxbu_normalize_task_uid($task_row['uid'] ?? '')
			: (string) ($task_row['uid'] ?? '');

		$should = null;
		if ($task_uid !== '' && isset($import_uid_set[$task_uid])) {
			$should = isset($present_uid[$task_uid]) ? 1 : 0;
		} elseif (isset($import_key_set[(int) $idx])) {
			$should = isset($present_idx[(int) $idx]) ? 1 : 0;
		}
		if ($should === null) continue;

		$current = \function_exists(__NAMESPACE__ . '\\cmxbu_truthy') && cmxbu_truthy($task_row['abgerechnet'] ?? 0) ? 1 : 0;
		if ($current !== $should) {
			$task_row['abgerechnet'] = $should;
			$updated = true;
		}
	}
	unset($task_row);

	if ($updated || $tasks_uid_migrated) {
		\update_post_meta($projekt_id, '_cmx_projekt_tasks', $tasks);
	}
}

/**
 * Sammle Positionen aus Tasks. Kann optional abgerechnete ignorieren oder erzwingen.
 */
function cmxbu_collect_task_positionen(array &$tasks, array &$artikel_vks): array {
	$positionen = [];
	$total = $skipped_done = $skipped_empty = $skipped_no_price = 0;
	$imported = false;
	$imported_keys = [];
	$imported_uids = [];
	$uids_assigned = false;

	foreach ($tasks as $idx => &$t) {
		if (!is_array($t)) continue;
		$uid_before = isset($t['uid']) ? (string) $t['uid'] : '';
		$uid = \function_exists(__NAMESPACE__ . '\\cmxbu_normalize_task_uid')
			? cmxbu_normalize_task_uid($uid_before)
			: $uid_before;
		if ($uid === '' && \function_exists(__NAMESPACE__ . '\\cmxbu_create_task_uid')) {
			$uid = cmxbu_create_task_uid();
		}
		if ($uid !== $uid_before) {
			$uids_assigned = true;
		}
		$t['uid'] = $uid;

		$total++;
		$flag = $t['abgerechnet'] ?? '';
		$is_done = \function_exists(__NAMESPACE__ . '\\cmxbu_truthy')
			? cmxbu_truthy($flag)
			: (\in_array((string)$flag, ['1','true','yes'], true) || $flag === true);
		if ($is_done) { $skipped_done++; continue; }

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
			'task_idx'     => $idx,
			'task_uid'     => $uid,
		];

		$imported_keys[] = $idx;
		$imported_uids[] = $uid;
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
		'imported_keys'   => $imported_keys,
		'imported_uids'   => $imported_uids,
		'uids_assigned'   => $uids_assigned,
	];
}
