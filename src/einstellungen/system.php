<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_notice_base_url')) {
	function cmx_system_bulk_delete_notice_base_url(): string {
		$page = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
			? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
			: 'cmx-einstellungen';

		return (string) \admin_url('admin.php?page=' . \rawurlencode($page) . '&tab=system&sub=general');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_post_types')) {
	function cmx_system_bulk_delete_post_types(): array {
		$candidates = ['artikel', 'kontakte', 'projekte', 'belege', 'dokumente', 'scanner', 'emails', 'budget', 'carent'];
		$post_types = [];

		foreach ($candidates as $post_type) {
			if (!\post_type_exists($post_type)) {
				continue;
			}

			$object = \get_post_type_object($post_type);
			if (!$object instanceof \WP_Post_Type || empty($object->show_ui)) {
				continue;
			}

			$delete_cap = (string) ($object->cap->delete_posts ?? 'delete_posts');
			if ($delete_cap !== '' && !\current_user_can($delete_cap) && !\current_user_can('delete_posts')) {
				continue;
			}

			$post_types[$post_type] = $object;
		}

		\uasort($post_types, static function (\WP_Post_Type $left, \WP_Post_Type $right): int {
			$left_label = (string) ($left->labels->name ?? $left->label ?? $left->name);
			$right_label = (string) ($right->labels->name ?? $right->label ?? $right->name);
			return \strnatcasecmp($left_label, $right_label);
		});

		return $post_types;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_post_type_counts')) {
	function cmx_system_bulk_delete_post_type_counts(string $post_type): array {
		$counts = \wp_count_posts($post_type);
		$active = 0;
		$trash = 0;

		foreach ((array) \get_object_vars($counts) as $status => $count) {
			$count = (int) $count;
			if ($count <= 0) {
				continue;
			}
			if ($status === 'trash') {
				$trash += $count;
				continue;
			}
			if ($status === 'auto-draft' || $status === 'inherit') {
				continue;
			}
			$active += $count;
		}

		return [
			'active' => $active,
			'trash'  => $trash,
			'total'  => $active + $trash,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_total_count')) {
	function cmx_system_bulk_delete_total_count(string $post_type, bool $delete_permanently): int {
		$counts = cmx_system_bulk_delete_post_type_counts($post_type);
		return $delete_permanently
			? (int) ($counts['total'] ?? 0)
			: (int) ($counts['active'] ?? 0);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_post_type_label')) {
	function cmx_system_bulk_delete_post_type_label(\WP_Post_Type $post_type_object): string {
		$post_type = (string) $post_type_object->name;
		$label = (string) ($post_type_object->labels->name ?? $post_type_object->label ?? $post_type);
		$counts = cmx_system_bulk_delete_post_type_counts($post_type);
		$parts = [];

		if (($counts['active'] ?? 0) > 0) {
			$parts[] = \number_format_i18n((int) $counts['active']) . ' aktiv';
		}
		if (($counts['trash'] ?? 0) > 0) {
			$parts[] = \number_format_i18n((int) $counts['trash']) . ' im Papierkorb';
		}
		if ($parts === []) {
			$parts[] = 'leer';
		}

		return $label . ' (' . \implode(' | ', $parts) . ')';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_process_batch')) {
	function cmx_system_bulk_delete_process_batch(string $post_type, bool $delete_permanently = false, int $after_id = 0, float $max_seconds = 4.0, int $query_limit = 50): array {
		$post_type = \sanitize_key($post_type);
		if ($post_type === '' || !\post_type_exists($post_type)) {
			return [
				'processed' => 0,
				'failed'    => 0,
				'skipped'   => 0,
				'last_id'   => \max(0, $after_id),
				'done'      => true,
			];
		}

		$after_id = \max(0, $after_id);
		$query_limit = \max(1, $query_limit);
		$max_seconds = $max_seconds > 0 ? $max_seconds : 4.0;
		$started_at = \microtime(true);
		$statuses = cmx_system_bulk_delete_query_statuses($delete_permanently);
		$processed = 0;
		$failed = 0;
		$skipped = 0;
		$last_id = $after_id;
		$done = false;

		if (\function_exists('ignore_user_abort')) {
			\ignore_user_abort(true);
		}
		if (\function_exists('set_time_limit')) {
			@\set_time_limit(20);
		}

		$restore_term_counting = \function_exists('wp_defer_term_counting');
		$restore_comment_counting = \function_exists('wp_defer_comment_counting');

		if ($restore_term_counting) {
			\wp_defer_term_counting(true);
		}
		if ($restore_comment_counting) {
			\wp_defer_comment_counting(true);
		}

		try {
			do {
				$ids = cmx_system_bulk_delete_load_post_ids($post_type, $statuses, $last_id, $query_limit);
				if ($ids === []) {
					$done = true;
					break;
				}

				foreach ($ids as $post_id) {
					$post_id = (int) $post_id;
					if ($post_id <= 0) {
						continue;
					}

					$last_id = $post_id;

					if (!\current_user_can('delete_post', $post_id)) {
						$skipped++;
					} else {
						$result = $delete_permanently
							? \wp_delete_post($post_id, true)
							: \wp_trash_post($post_id);

						if ($result) {
							$processed++;
						} else {
							$failed++;
						}
					}

					if ((\microtime(true) - $started_at) >= $max_seconds) {
						break 2;
					}
				}
			} while ((\microtime(true) - $started_at) < $max_seconds);
		} finally {
			if ($restore_comment_counting) {
				\wp_defer_comment_counting(false);
			}
			if ($restore_term_counting) {
				\wp_defer_term_counting(false);
			}
		}

		if (!$done) {
			$done = cmx_system_bulk_delete_load_post_ids($post_type, $statuses, $last_id, 1) === [];
		}

		return [
			'processed' => $processed,
			'failed'    => $failed,
			'skipped'   => $skipped,
			'last_id'   => $last_id,
			'done'      => $done,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_query_statuses')) {
	function cmx_system_bulk_delete_query_statuses(bool $delete_permanently): array {
		$statuses = \array_values(\array_unique(\array_map('strval', \get_post_stati([], 'names'))));
		$statuses = \array_values(\array_diff($statuses, ['auto-draft', 'inherit']));
		if (!$delete_permanently) {
			$statuses = \array_values(\array_diff($statuses, ['trash']));
		}
		return $statuses;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_load_post_ids')) {
	function cmx_system_bulk_delete_load_post_ids(string $post_type, array $statuses, int $after_id = 0, int $limit = 100): array {
		global $wpdb;

		$post_type = \sanitize_key($post_type);
		$after_id = \max(0, (int) $after_id);
		$limit = \max(1, (int) $limit);
		$statuses = \array_values(\array_filter(\array_map('strval', $statuses)));

		if ($post_type === '' || $statuses === []) {
			return [];
		}

		$status_placeholders = \implode(', ', \array_fill(0, \count($statuses), '%s'));
		$sql = "
			SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type = %s
				AND ID > %d
				AND post_status IN ({$status_placeholders})
			ORDER BY ID ASC
			LIMIT %d
		";

		$params = \array_merge([$post_type, $after_id], $statuses, [$limit]);
		$prepared = $wpdb->prepare($sql, $params);
		if (!\is_string($prepared) || $prepared === '') {
			return [];
		}

		return \array_map('intval', (array) $wpdb->get_col($prepared));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_run')) {
	function cmx_system_bulk_delete_run(string $post_type, bool $delete_permanently = false): array {
		$post_type = \sanitize_key($post_type);
		if ($post_type === '' || !\post_type_exists($post_type)) {
			return ['processed' => 0, 'failed' => 0, 'skipped' => 0];
		}

		$processed = 0;
		$failed = 0;
		$skipped = 0;
		$last_id = 0;
		$done = false;

		do {
			$batch = cmx_system_bulk_delete_process_batch($post_type, $delete_permanently, $last_id, 5.0, 100);
			$processed += (int) ($batch['processed'] ?? 0);
			$failed += (int) ($batch['failed'] ?? 0);
			$skipped += (int) ($batch['skipped'] ?? 0);
			$last_id = (int) ($batch['last_id'] ?? $last_id);
			$done = !empty($batch['done']);
		} while (!$done);

		return [
			'processed' => $processed,
			'failed'    => $failed,
			'skipped'   => $skipped,
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_system_bulk_delete_panel')) {
	function cmx_render_system_bulk_delete_panel(): void {
		if (!\current_user_can('manage_options')) {
			return;
		}

		$post_types = cmx_system_bulk_delete_post_types();
		if ($post_types === []) {
			return;
		}

		echo '<div class="cmx-system-bulk-delete-panel" style="margin-top:28px;padding:20px;border:1px solid #dcdcde;border-radius:10px;background:#fff;">';
		echo '<h2 style="margin-top:0;">Alles löschen</h2>';
		echo '<p class="description" style="margin-bottom:16px;">Wähle ein Modul aus und verschiebe alle Einträge gesammelt in den Papierkorb. Optional kannst Du sie direkt endgültig löschen.</p>';
		echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" id="cmx-system-bulk-delete-form">';
		\wp_nonce_field('cmx_system_bulk_delete_post_type');
		echo '<input type="hidden" name="action" value="cmx_system_bulk_delete_post_type">';
		echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
		echo '<label class="screen-reader-text" for="cmx-system-bulk-delete-post-type">Modul auswählen</label>';
		echo '<select name="cmx_bulk_delete_post_type" id="cmx-system-bulk-delete-post-type">';
		echo '<option value="">' . \esc_html__('Modul auswählen', 'cmx-misbuero') . '</option>';
		foreach ($post_types as $post_type => $post_type_object) {
			echo '<option value="' . \esc_attr((string) $post_type) . '">' . \esc_html(cmx_system_bulk_delete_post_type_label($post_type_object)) . '</option>';
		}
		echo '</select>';
		echo '<label for="cmx-system-bulk-delete-force" style="display:inline-flex;align-items:center;gap:6px;">';
		echo '<input type="checkbox" name="cmx_bulk_delete_force" id="cmx-system-bulk-delete-force" value="1">';
		echo '<span>Endgültig löschen</span>';
		echo '</label>';
		echo '<button type="submit" class="button button-secondary" id="cmx-system-bulk-delete-submit">In Papierkorb legen</button>';
		echo '</div>';
		echo '<div id="cmx-system-bulk-delete-status" style="display:none;margin-top:14px;">';
		echo '<progress id="cmx-system-bulk-delete-progress" value="0" max="100" style="width:min(460px,100%);height:16px;"></progress>';
		echo '<p id="cmx-system-bulk-delete-status-text" style="margin:8px 0 0;"></p>';
		echo '</div>';
		echo '<p class="description" style="margin-top:12px;color:#b32d2e;">Diese Aktion betrifft alle Einträge des ausgewählten Moduls.</p>';
		echo '</form>';
		echo '</div>';
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const form = document.getElementById('cmx-system-bulk-delete-form');
			const select = document.getElementById('cmx-system-bulk-delete-post-type');
			const force = document.getElementById('cmx-system-bulk-delete-force');
			const submit = document.getElementById('cmx-system-bulk-delete-submit');
			const statusWrap = document.getElementById('cmx-system-bulk-delete-status');
			const progress = document.getElementById('cmx-system-bulk-delete-progress');
			const statusText = document.getElementById('cmx-system-bulk-delete-status-text');
			if (!form || !select || !force || !submit || !statusWrap || !progress || !statusText) {
				return;
			}

			const ajaxUrl = <?php echo \wp_json_encode((string) \admin_url('admin-ajax.php')); ?>;
			const noticeBaseUrl = <?php echo \wp_json_encode(cmx_system_bulk_delete_notice_base_url()); ?>;
			const nonceField = form.querySelector('input[name="_wpnonce"]');
			const numberFormat = new Intl.NumberFormat(document.documentElement.lang || 'de-CH');
			let isRunning = false;

			const setBusy = function (busy) {
				isRunning = !!busy;
				select.disabled = busy;
				force.disabled = busy;
				submit.disabled = busy;
			};

			const setStatus = function (text, percent) {
				statusWrap.style.display = 'block';
				statusText.textContent = text || '';
				var value = Number(percent || 0);
				if (!Number.isFinite(value)) {
					value = 0;
				}
				value = Math.max(0, Math.min(100, value));
				progress.value = value;
			};

			const updateLabel = function () {
				submit.textContent = force.checked ? 'Endgültig löschen' : 'In Papierkorb legen';
			};

			const runBatch = async function (state) {
				const body = new URLSearchParams();
				body.set('action', 'cmx_system_bulk_delete_post_type_batch');
				body.set('_ajax_nonce', nonceField ? String(nonceField.value || '') : '');
				body.set('cmx_bulk_delete_post_type', state.postType);
				body.set('cmx_bulk_delete_force', state.force ? '1' : '0');
				body.set('after_id', String(state.afterId || 0));

				const response = await fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				});

				let payload = null;
				try {
					payload = await response.json();
				} catch (error) {
					throw new Error('Antwort konnte nicht gelesen werden.');
				}

				if (!response.ok || !payload || payload.success !== true) {
					const message = payload && payload.data && payload.data.message
						? String(payload.data.message)
						: 'Löschen fehlgeschlagen.';
					throw new Error(message);
				}

				return payload.data || {};
			};

			const redirectToNotice = function (state) {
				const url = new URL(noticeBaseUrl);
				url.searchParams.set('cmx_system_bulk_delete_notice', 'success');
				url.searchParams.set('cmx_system_bulk_delete_post_type', state.postType);
				url.searchParams.set('cmx_system_bulk_delete_mode', state.force ? 'delete' : 'trash');
				url.searchParams.set('cmx_system_bulk_delete_count', String(state.processed));
				url.searchParams.set('cmx_system_bulk_delete_failed', String(state.failed));
				url.searchParams.set('cmx_system_bulk_delete_skipped', String(state.skipped));
				window.location.href = url.toString();
			};

			const runDeletion = async function (state) {
				while (true) {
					const data = await runBatch(state);
					if (state.total <= 0) {
						state.total = Number(data.total || 0);
					}
					state.afterId = Number(data.last_id || state.afterId || 0);
					state.processed += Number(data.processed || 0);
					state.failed += Number(data.failed || 0);
					state.skipped += Number(data.skipped || 0);

					const handled = state.processed + state.failed + state.skipped;
					const total = state.total > 0 ? state.total : handled;
					const percent = total > 0 ? (handled / total) * 100 : 100;
					const verb = state.force ? 'gelöscht' : 'verschoben';
					setStatus(numberFormat.format(handled) + ' / ' + numberFormat.format(total) + ' Einträge verarbeitet, ' + numberFormat.format(state.processed) + ' ' + verb + '…', percent);

					if (data.done) {
						setStatus('Vorgang abgeschlossen. Weiterleitung…', 100);
						redirectToNotice(state);
						return;
					}
				}
			};

			form.addEventListener('submit', async function (event) {
				const option = select.options[select.selectedIndex] || null;
				const postType = String(select.value || '').trim();
				if (isRunning) {
					event.preventDefault();
					return;
				}
				if (!postType) {
					event.preventDefault();
					window.alert('Bitte zuerst ein Modul auswählen.');
					return;
				}

				const label = option ? String(option.textContent || postType).trim() : postType;
				const actionLabel = force.checked ? 'endgültig löschen' : 'in den Papierkorb legen';
				if (!window.confirm('Wirklich alle Einträge von "' + label + '" ' + actionLabel + '?')) {
					event.preventDefault();
					return;
				}

				event.preventDefault();
				setBusy(true);
				setStatus('Löschen läuft…', 0);

				try {
					await runDeletion({
						postType: postType,
						force: force.checked,
						afterId: 0,
						total: 0,
						processed: 0,
						failed: 0,
						skipped: 0
					});
				} catch (error) {
					setBusy(false);
					setStatus(error && error.message ? String(error.message) : 'Löschen fehlgeschlagen.', 0);
				}
			});

			force.addEventListener('change', updateLabel);
			updateLabel();
		});
		</script>
		<?php
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_bulk_delete_redirect')) {
	function cmx_system_bulk_delete_redirect(array $args = []): void {
		$base_url = cmx_system_bulk_delete_notice_base_url();
		\wp_safe_redirect(\add_query_arg($args, $base_url));
		exit;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_system_render_copyable_instance_link')) {
	function cmx_system_render_copyable_instance_link(string $base_id, string $url, string $open_label): void {
		$base_id = \sanitize_key($base_id);
		if ($base_id === '') {
			$base_id = 'cmx-system-link';
		}

		$copy_label = 'Link in Zwischenablage kopieren';
		$status_id = $base_id . '-status';
		$copy_id = $base_id . '-copy';
		$link_id = $base_id . '-link';

		echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
		echo '<button type="button" class="button button-secondary" id="' . \esc_attr($copy_id) . '" aria-label="' . \esc_attr($copy_label) . '" title="' . \esc_attr($copy_label) . '" data-copy-url="' . \esc_attr($url) . '" style="display:inline-flex;align-items:center;justify-content:center;min-width:38px;padding:0 10px;">';
		echo '<span class="dashicons dashicons-clipboard" aria-hidden="true" style="margin-top:2px;"></span>';
		echo '</button>';
		echo '<a id="' . \esc_attr($link_id) . '" href="' . \esc_url($url) . '" target="_blank" rel="noopener noreferrer" data-copy-url="' . \esc_attr($url) . '" title="' . \esc_attr($open_label) . '">' . \esc_html($url) . '</a>';
		echo '<span class="description" id="' . \esc_attr($status_id) . '" aria-live="polite" style="min-height:18px;"></span>';
		echo '</div>';
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			const copyButton = document.getElementById('<?php echo \esc_js($copy_id); ?>');
			const link = document.getElementById('<?php echo \esc_js($link_id); ?>');
			const status = document.getElementById('<?php echo \esc_js($status_id); ?>');
			if (!copyButton || !link || !status) {
				return;
			}

			let resetTimer = null;
			const setStatus = function (message, isError) {
				status.textContent = message || '';
				status.style.color = isError ? '#b32d2e' : '#2271b1';
				if (resetTimer) {
					window.clearTimeout(resetTimer);
				}
				if (message) {
					resetTimer = window.setTimeout(function () {
						status.textContent = '';
					}, 1800);
				}
			};

			const copyFallback = function (text) {
				const input = document.createElement('textarea');
				input.value = text;
				input.setAttribute('readonly', 'readonly');
				input.style.position = 'fixed';
				input.style.opacity = '0';
				document.body.appendChild(input);
				input.select();
				input.setSelectionRange(0, input.value.length);
				let ok = false;
				try {
					ok = document.execCommand('copy');
				} catch (error) {
					ok = false;
				}
				document.body.removeChild(input);
				return ok;
			};

			const copyUrl = function (text) {
				if (!text) {
					setStatus('Link fehlt.', true);
					return;
				}
				if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
					navigator.clipboard.writeText(text).then(function () {
						setStatus('Link kopiert.', false);
					}).catch(function () {
						if (copyFallback(text)) {
							setStatus('Link kopiert.', false);
							return;
						}
						setStatus('Link konnte nicht kopiert werden.', true);
					});
					return;
				}
				if (copyFallback(text)) {
					setStatus('Link kopiert.', false);
					return;
				}
				setStatus('Link konnte nicht kopiert werden.', true);
			};

			copyButton.addEventListener('click', function () {
				copyUrl(copyButton.getAttribute('data-copy-url') || '');
			});

			link.addEventListener('click', function () {
				copyUrl(link.getAttribute('data-copy-url') || link.href || '');
			});
		});
		</script>
		<?php
	}
}

\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_system_tab');
function cmx_register_system_tab(): void {
	$general_page = 'cmx_tab_system__general';

	\add_settings_section(
		'cmx_sec_system',
		__('System', 'default'),
		'__return_false',
		$general_page
	);

	\add_settings_field(
		'cmx_system_dokuscan',
		'DokuScan (WebDAV)',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-dokuscan',
				(string) \home_url('/scanner/'),
				'DokuScan in neuem Tab öffnen und Link kopieren'
			);
		},
		$general_page,
		'cmx_sec_system'
	);

	\add_settings_field(
		'cmx_system_archiv',
		'Archiv',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-archiv',
				(string) \home_url('/archiv/'),
				'Archiv in neuem Tab öffnen und Link kopieren'
			);
		},
		$general_page,
		'cmx_sec_system'
	);

	\add_settings_field(
		'cmx_system_nachweise',
		'Nachweise',
		function (): void {
			cmx_system_render_copyable_instance_link(
				'cmx-system-nachweise',
				(string) \home_url('/nachweise/'),
				'Nachweise in neuem Tab öffnen und Link kopieren'
			);
		},
		$general_page,
		'cmx_sec_system'
	);

	\register_setting(
		'cmx_einstellungen',
		'mis_buero_openai_key',
		[
			'type'              => 'string',
			'sanitize_callback' => static function ($value): string {
				if ($value === null) {
					$value = \get_option('mis_buero_openai_key', '');
				}
				return \sanitize_text_field((string) $value);
			},
		]
	);

	\add_settings_field(
		'mis_buero_openai_key',
		'Paddle API Key',
		function (): void {
			$val = (string) \get_option('mis_buero_openai_key', '');
			echo '<input type="text" name="mis_buero_openai_key" class="regular-text" value="' . \esc_attr($val) . '">';
			echo '<p class="description">Wird für OCR und Produkttexte verwendet</p>';
		},
		$general_page,
		'cmx_sec_system'
	);

	\add_settings_field(
		'cmx_system_debug_mode',
		'Debug-Mode',
		__NAMESPACE__ . '\\cmx_field_checkbox',
		$general_page,
		'cmx_sec_system',
		[
			'key'   => \defined(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
				? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
				: 'debug_mode',
			'label' => 'Debug-Modus aktivieren',
		]
	);

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && cmx_system_is_cloudmeister_user()) {
		\add_settings_section(
			'cmx_sec_modules',
			'Module',
			'__return_false',
			$general_page
		);

		\add_settings_field(
			'cmx_system_pro_version',
			'PRO Version',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
					: 'pro_version';
				$options = (array) \get_option($option_name, []);
				$checked = !empty($options[$key]);

				echo '<label>';
				echo '<input type="hidden" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="0">';
				echo '<input type="checkbox" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="1"' . \checked($checked, true, false) . '> ';
				echo 'E-Mail Client, Termine, VideoCalls';
				echo '</label>';
			},
			$general_page,
			'cmx_sec_system'
		);

		\add_settings_field(
			'cmx_system_carent',
			'Carent',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
					: 'carent';
				$options = (array) \get_option($option_name, []);
				$checked = !empty($options[$key]);

				echo '<label>';
				echo '<input type="hidden" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="0">';
				echo '<input type="checkbox" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="1"' . \checked($checked, true, false) . '> ';
				echo \esc_html__('CaRent aktivieren', 'cmx-misbuero');
				echo '</label>';
			},
			$general_page,
			'cmx_sec_modules'
		);

		\add_settings_field(
			'cmx_system_max_workplaces',
			'Max. Arbeitsplätze',
			function (): void {
				$option_name = \function_exists(__NAMESPACE__ . '\\cmx_system_settings_option_name')
					? (string) cmx_system_settings_option_name()
					: 'cmx_einstellungen';
				$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
					? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
					: 'max_workplaces';
				$options = (array) \get_option($option_name, []);
				$value = isset($options[$key]) ? (int) $options[$key] : 1;
				if ($value <= 0) {
					$value = 1;
				}
				echo '<input type="number" min="1" step="1" name="' . \esc_attr($option_name) . '[' . \esc_attr($key) . ']" value="' . \esc_attr((string) $value) . '" class="small-text">';
			},
			$general_page,
			'cmx_sec_system'
		);
	}
}

\add_filter('pre_update_option_' . 'cmx_einstellungen', function ($value, $old_value) {
	$value = \is_array($value) ? $value : [];
	$old_value = \is_array($old_value) ? $old_value : [];
	$key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_MAX_WORKPLACES_KEY')
		: 'max_workplaces';
	$pro_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_PRO_VERSION_KEY')
		: 'pro_version';
	$carent_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_CARENT_KEY')
		: 'carent';
	$debug_key = \defined(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
		? (string) \constant(__NAMESPACE__ . '\\CMX_SYSTEM_DEBUG_MODE_KEY')
		: 'debug_mode';

	if (\function_exists(__NAMESPACE__ . '\\cmx_system_is_cloudmeister_user') && !cmx_system_is_cloudmeister_user()) {
		$value[$key] = isset($old_value[$key]) ? (int) $old_value[$key] : 1;
		$value[$pro_key] = !empty($old_value[$pro_key]) ? '1' : '0';
		$value[$carent_key] = !empty($old_value[$carent_key]) ? '1' : '0';
		$value[$debug_key] = !empty($value[$debug_key]) ? '1' : '0';
		return $value;
	}

	$max = isset($value[$key]) ? (int) $value[$key] : (isset($old_value[$key]) ? (int) $old_value[$key] : 1);
	$value[$key] = $max > 0 ? $max : 1;
	$value[$pro_key] = !empty($value[$pro_key]) ? '1' : '0';
	$value[$carent_key] = !empty($value[$carent_key]) ? '1' : '0';
	$value[$debug_key] = !empty($value[$debug_key]) ? '1' : '0';
	return $value;
}, 10, 2);

\add_action('admin_post_cmx_system_bulk_delete_post_type', __NAMESPACE__ . '\\cmx_system_handle_bulk_delete_post_type');
function cmx_system_handle_bulk_delete_post_type(): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('Keine Berechtigung.');
	}

	\check_admin_referer('cmx_system_bulk_delete_post_type');

	$post_type = isset($_POST['cmx_bulk_delete_post_type']) && !\is_array($_POST['cmx_bulk_delete_post_type'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_bulk_delete_post_type']))
		: '';
	$delete_permanently = !empty($_POST['cmx_bulk_delete_force']);
	$post_types = cmx_system_bulk_delete_post_types();

	if ($post_type === '' || !isset($post_types[$post_type])) {
		cmx_system_bulk_delete_redirect([
			'cmx_system_bulk_delete_notice' => 'error',
			'cmx_system_bulk_delete_message' => 'invalid_post_type',
		]);
	}

	$result = cmx_system_bulk_delete_run($post_type, $delete_permanently);

	cmx_system_bulk_delete_redirect([
		'cmx_system_bulk_delete_notice'  => 'success',
		'cmx_system_bulk_delete_post_type' => $post_type,
		'cmx_system_bulk_delete_mode'    => $delete_permanently ? 'delete' : 'trash',
		'cmx_system_bulk_delete_count'   => (int) ($result['processed'] ?? 0),
		'cmx_system_bulk_delete_failed'  => (int) ($result['failed'] ?? 0),
		'cmx_system_bulk_delete_skipped' => (int) ($result['skipped'] ?? 0),
	]);
}

\add_action('wp_ajax_cmx_system_bulk_delete_post_type_batch', __NAMESPACE__ . '\\cmx_system_handle_bulk_delete_post_type_batch');
function cmx_system_handle_bulk_delete_post_type_batch(): void {
	if (!\current_user_can('manage_options')) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}

	\check_ajax_referer('cmx_system_bulk_delete_post_type');

	$post_type = isset($_POST['cmx_bulk_delete_post_type']) && !\is_array($_POST['cmx_bulk_delete_post_type'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_bulk_delete_post_type']))
		: '';
	$delete_permanently = !empty($_POST['cmx_bulk_delete_force']);
	$after_id = isset($_POST['after_id']) && !\is_array($_POST['after_id'])
		? \max(0, (int) $_POST['after_id'])
		: 0;
	$post_types = cmx_system_bulk_delete_post_types();

	if ($post_type === '' || !isset($post_types[$post_type])) {
		\wp_send_json_error(['message' => 'Bitte zuerst ein gültiges Modul auswählen.'], 400);
	}

	$total = cmx_system_bulk_delete_total_count($post_type, $delete_permanently);
	$batch = cmx_system_bulk_delete_process_batch($post_type, $delete_permanently, $after_id, 5.0, 100);

	\wp_send_json_success([
		'total'     => $total,
		'processed' => (int) ($batch['processed'] ?? 0),
		'failed'    => (int) ($batch['failed'] ?? 0),
		'skipped'   => (int) ($batch['skipped'] ?? 0),
		'last_id'   => (int) ($batch['last_id'] ?? $after_id),
		'done'      => !empty($batch['done']),
	]);
}

\add_action('all_admin_notices', function (): void {
	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
	if ($page !== (\defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') ? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG') : 'cmx-einstellungen') || $tab !== 'system') {
		return;
	}

	$notice = isset($_GET['cmx_system_bulk_delete_notice']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_notice'])) : '';
	if ($notice === '') {
		return;
	}

	if ($notice === 'error') {
		$message_key = isset($_GET['cmx_system_bulk_delete_message']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_message'])) : '';
		$message = $message_key === 'invalid_post_type'
			? 'Bitte zuerst ein gültiges Modul auswählen.'
			: 'Die Aktion konnte nicht ausgeführt werden.';
		echo '<div class="notice notice-error is-dismissible"><p>' . \esc_html($message) . '</p></div>';
		return;
	}

	$post_type = isset($_GET['cmx_system_bulk_delete_post_type']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_post_type'])) : '';
	$mode = isset($_GET['cmx_system_bulk_delete_mode']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_system_bulk_delete_mode'])) : 'trash';
	$processed = isset($_GET['cmx_system_bulk_delete_count']) ? (int) $_GET['cmx_system_bulk_delete_count'] : 0;
	$failed = isset($_GET['cmx_system_bulk_delete_failed']) ? (int) $_GET['cmx_system_bulk_delete_failed'] : 0;
	$skipped = isset($_GET['cmx_system_bulk_delete_skipped']) ? (int) $_GET['cmx_system_bulk_delete_skipped'] : 0;

	$post_type_label = $post_type;
	$post_type_object = $post_type !== '' ? \get_post_type_object($post_type) : null;
	if ($post_type_object instanceof \WP_Post_Type) {
		$post_type_label = (string) ($post_type_object->labels->name ?? $post_type_object->label ?? $post_type);
	}

	$action_label = $mode === 'delete' ? 'endgültig gelöscht' : 'in den Papierkorb verschoben';
	$message = \number_format_i18n($processed) . ' Einträge von "' . $post_type_label . '" wurden ' . $action_label . '.';

	if ($processed === 0 && $failed === 0 && $skipped === 0) {
		$message = 'Für "' . $post_type_label . '" wurden keine Einträge gefunden.';
	}
	if ($failed > 0) {
		$message .= ' Fehler: ' . \number_format_i18n($failed) . '.';
	}
	if ($skipped > 0) {
		$message .= ' Übersprungen: ' . \number_format_i18n($skipped) . '.';
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html($message) . '</p></div>';
});
