<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!defined(__NAMESPACE__ . '\\CMX_PT_BELEGE')) {
	define(__NAMESPACE__ . '\\CMX_PT_BELEGE', 'belege');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_latest_related_doc_url')) {
	function cmx_beleg_latest_related_doc_url(int $post_id): string {
		if ($post_id <= 0) {
			return '';
		}

		$uploads_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_UPLOADS_META')
			: '_cmx_dokumente_uploads';
		$self_meta_key = \defined(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			? (string) \constant(__NAMESPACE__ . '\\CMX_DOK_SELF_META')
			: '_cmx_dokumente_files';

		$doc_ids = [];
		foreach ((array) \get_post_meta($post_id, $uploads_meta_key, true) as $raw_doc_id) {
			$doc_id = (int) $raw_doc_id;
			if ($doc_id > 0) {
				$doc_ids[] = $doc_id;
			}
		}
		$doc_ids = \array_values(\array_unique($doc_ids));
		if (empty($doc_ids)) {
			return '';
		}

		for ($i = \count($doc_ids) - 1; $i >= 0; $i--) {
			$doc_id = (int) $doc_ids[$i];
			if ($doc_id <= 0 || (string) \get_post_type($doc_id) !== 'dokumente') {
				continue;
			}

			$file_rel = (string) \get_post_meta($doc_id, '_cmx_dokumente_file_path', true);
			if ($file_rel === '') {
				$self_files = (array) \get_post_meta($doc_id, $self_meta_key, true);
				$self_files = \array_values(\array_filter($self_files, static function ($value): bool {
					return \is_string($value) && $value !== '';
				}));
				if (!empty($self_files)) {
					$file_rel = (string) $self_files[\count($self_files) - 1];
				}
			}

			$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
			if ($file_rel !== '') {
				$abs = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel));
				if ($abs !== '' && \is_file($abs)) {
					return (string) \content_url('/uploads/' . $file_rel);
				}
			}

			$edit_url = (string) \get_edit_post_link($doc_id, 'raw');
			if ($edit_url !== '') {
				return $edit_url;
			}
		}

		return '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_has_recurring_run')) {
	function cmx_beleg_has_recurring_run(int $post_id): bool {
		if ($post_id <= 0) {
			return false;
		}

		$source_id = (int) \get_post_meta($post_id, '_cmx_abo_source_id', true);
		if ($source_id > 0) {
			return false;
		}

		$enabled = \get_post_meta($post_id, '_cmx_abo_enabled', true) === '1';
		if (!$enabled) {
			return false;
		}

		$frequency = \sanitize_key((string) \get_post_meta($post_id, '_cmx_abo_frequency', true));

		$allowed_frequencies = ['minutely', 'hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

		return \in_array($frequency, $allowed_frequencies, true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_recurring_stop_url')) {
	function cmx_beleg_recurring_stop_url(int $post_id): string {
		if ($post_id <= 0) {
			return '';
		}

		$redirect_to = \admin_url('edit.php?post_type=' . CMX_PT_BELEGE);
		if (isset($_SERVER['REQUEST_URI'])) {
			$request_uri = (string) \wp_unslash($_SERVER['REQUEST_URI']);
			if ($request_uri !== '' && \strpos($request_uri, '/wp-admin/') === 0) {
				$redirect_to = (string) \home_url($request_uri);
			}
		}

		$url = \admin_url('admin-post.php?action=cmx_beleg_abo_stop&post_id=' . (int) $post_id);
		if ($redirect_to !== '') {
			$url .= '&redirect_to=' . \rawurlencode($redirect_to);
		}

		return (string) \wp_nonce_url($url, 'cmx_beleg_abo_stop_' . $post_id);
	}
}

$add_action_columns = static function (array $columns): array {
	unset($columns['cmx_beleg_repeat_action'], $columns['cmx_beleg_pdf_action'], $columns['cmx_beleg_mail_action']);

	$columns['cmx_beleg_repeat_action'] = 'Abo';
	$columns['cmx_beleg_mail_action'] = 'E-Mail';
	$columns['cmx_beleg_pdf_action'] = 'PDF';

	return $columns;
};

\add_filter('manage_edit-' . CMX_PT_BELEGE . '_columns', $add_action_columns, 999);
\add_filter('manage_' . CMX_PT_BELEGE . '_posts_columns', $add_action_columns, 999);

\add_action('manage_' . CMX_PT_BELEGE . '_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_beleg_repeat_action') {
		if (!cmx_beleg_has_recurring_run($post_id)) {
			echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
			return;
		}

		$stop_url = cmx_beleg_recurring_stop_url($post_id);
		if ($stop_url === '') {
			echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
			return;
		}

		echo '<a href="' . \esc_url($stop_url) . '" class="cmx-beleg-repeat-action" title="Wiederkehrenden Lauf stoppen" aria-label="Wiederkehrenden Lauf stoppen" onclick="return window.confirm(\'Wiederkehrenden Lauf für diesen Beleg stoppen?\');"><span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span></a>';
		return;
	}

	if ($column === 'cmx_beleg_mail_action') {
		if (!\current_user_can('edit_post', $post_id)) {
			echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
			return;
		}

		$is_supplier_invoice = \function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_supplier_invoice_beleg')
			&& cmx_start_dashboard_is_supplier_invoice_beleg($post_id);
		$is_overdue_invoice = !$is_supplier_invoice
			&& \function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_is_overdue_customer_invoice')
			&& cmx_start_dashboard_is_overdue_customer_invoice($post_id);
		$button_class = 'cmx-beleg-mail-action'
			. ($is_supplier_invoice ? ' is-trustee' : '')
			. ($is_overdue_invoice ? ' is-overdue' : '');
		$title = $is_supplier_invoice
			? 'Vorhandenen PDF-Link an den Treuhänder versenden'
			: ($is_overdue_invoice ? 'Versand für überfällige Rechnung auswählen' : 'Vorhandenen PDF-Link per E-Mail versenden');

		echo '<span class="cmx-beleg-mail-wrap">';
		echo '<button type="button" class="' . \esc_attr($button_class) . '" data-cmx-beleg-admin-mail data-post-id="' . $post_id . '" data-send-mode="' . ($is_overdue_invoice ? 'menu' : 'normal') . '" data-default-title="' . \esc_attr($title) . '" title="' . \esc_attr($title) . '" aria-label="' . \esc_attr($title) . '"' . ($is_overdue_invoice ? ' aria-haspopup="menu" aria-expanded="false"' : '') . '><span class="dashicons dashicons-email" aria-hidden="true"></span></button>';
		if ($is_overdue_invoice) {
			echo '<span class="cmx-beleg-mail-menu" role="menu" hidden>';
			echo '<button type="button" role="menuitem" data-cmx-beleg-admin-mail-option data-post-id="' . $post_id . '" data-send-mode="normal">Rechnung nochmals</button>';
			echo '<button type="button" role="menuitem" data-cmx-beleg-admin-mail-option data-post-id="' . $post_id . '" data-send-mode="reminder">Zahlungserinnerung senden</button>';
			echo '</span>';
		}
		echo '</span>';
		return;
	}

	if ($column !== 'cmx_beleg_pdf_action') {
		return;
	}

	$post = \get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== CMX_PT_BELEGE) {
		echo '';
		return;
	}

	$has_pdf = false;
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
		[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
		$has_pdf = \is_file((string) $pdf_abs_path);
	}

	$has_upload = false;
	if (\function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_primary_upload_abs_path')) {
		$upload_abs_path = (string) cmxbu_get_beleg_primary_upload_abs_path($post_id);
		$has_upload = \is_file($upload_abs_path);
	}

	$token = \function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')
		? (string) cmxbu_get_stable_token($post_id)
		: '';
	$pdf_url = $token !== '' ? (string) cmxbu_get_beleg_public_url($post_id, $token) : '';
	$upload_url = $token !== '' ? (string) cmxbu_get_beleg_public_url($post_id, $token, ['quelle' => 'upload']) : '';
	$disabled_title = $has_pdf ? 'PDF nicht verfügbar' : 'PDF nicht vorhanden';
	$related_doc_url = \function_exists(__NAMESPACE__ . '\\cmx_beleg_latest_related_doc_url')
		? (string) cmx_beleg_latest_related_doc_url($post_id)
		: '';
	$is_belegeingang_view = !empty($_GET['cmx_belegeingang']);
	$is_pending_belegeingang = $is_belegeingang_view
		&& (string) $post->post_status === 'pending'
		&& (string) \get_post_meta($post_id, '_cmx_belegeingang_source', true) === 'rest'
		&& (string) \get_post_meta($post_id, '_cmx_belegeingang_status', true) === 'pending';
	$belegeingang_confirm_url = '';
	if ($is_pending_belegeingang) {
		$redirect_to = (string) \add_query_arg(
			[
				'post_type' => CMX_PT_BELEGE,
				'cmx_belegeingang' => 1,
			],
			\admin_url('edit.php')
		);
		$belegeingang_confirm_url = (string) \wp_nonce_url(
			\add_query_arg(
				[
					'action' => 'cmx_belegeingang_confirm',
					'post_id' => $post_id,
					'redirect_to' => \rawurlencode($redirect_to),
				],
				\admin_url('admin-post.php')
			),
			'cmx_belegeingang_confirm_' . $post_id
		);
	}

	echo '<span class="cmx-beleg-action-icons">';
	if ($related_doc_url !== '') {
		echo '<a href="' . \esc_url($related_doc_url) . '" target="_blank" rel="noopener noreferrer" title="Zugeordnetes Dokument anzeigen" class="cmx-beleg-action-icon cmx-beleg-action-related" aria-label="Zugeordnetes Dokument anzeigen"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></a>';
	} else {
		echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
	}

	if ($upload_url !== '' && $has_upload) {
		echo '<a href="' . \esc_url($upload_url) . '" target="_blank" rel="noopener noreferrer" title="Upload-Dokument anzeigen" class="cmx-beleg-action-icon cmx-beleg-action-upload" aria-label="Upload-Dokument anzeigen"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></a>';
	} else {
		echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
	}

	if ($pdf_url !== '' && $has_pdf) {
		echo '<a href="' . \esc_url($pdf_url) . '" target="_blank" rel="noopener noreferrer" title="Anzeigen als PDF (DL/C5/C4)" class="cmx-beleg-action-icon cmx-beleg-action-pdf" aria-label="Anzeigen als PDF (DL/C5/C4)"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></a>';
	} else {
		echo '<span class="cmx-beleg-action-icon cmx-beleg-action-disabled cmx-beleg-action-pdf" title="' . \esc_attr($disabled_title) . '"><span class="dashicons dashicons-pdf" aria-hidden="true"></span></span>';
	}

	if ($belegeingang_confirm_url !== '') {
		echo '<a href="' . \esc_url($belegeingang_confirm_url) . '" title="Als Lieferanten Rechnung übernehmen" class="cmx-beleg-action-icon cmx-belegeingang-confirm-action" aria-label="Als Lieferanten Rechnung übernehmen"><span class="dashicons dashicons-carrot" aria-hidden="true"></span></a>';
	} else {
		echo '<span class="cmx-beleg-action-placeholder" aria-hidden="true"></span>';
	}
	echo '</span>';
}, 20, 2);

\add_action('admin_head-edit.php', function (): void {
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== CMX_PT_BELEGE) {
		return;
	}

	\wp_enqueue_style('dashicons');

		echo '<style>
			.wp-list-table th.column-cmx_beleg_repeat_action {
				width: 46px;
				text-align: center;
			}
			.wp-list-table td.column-cmx_beleg_repeat_action {
				text-align: center;
				vertical-align: top;
			}
			.wp-list-table th.column-cmx_beleg_mail_action {
				width: 54px;
				text-align: center;
			}
			.wp-list-table td.column-cmx_beleg_mail_action {
				position: relative;
				text-align: center;
				vertical-align: top;
				overflow: visible;
			}
			.wp-list-table th.column-cmx_beleg_pdf_action {
				width: 126px;
				text-align: center;
			}
			.wp-list-table td.column-cmx_beleg_pdf_action {
				text-align: center;
				vertical-align: top;
			}
			.cmx-beleg-action-icons {
				display: inline-grid;
				grid-template-columns: 18px 18px 18px 18px;
				column-gap: 6px;
				align-items: start;
				justify-items: center;
			}
			.cmx-beleg-action-icon {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				text-decoration: none;
				min-height: 20px;
				vertical-align: top;
			}
		.cmx-beleg-action-icon .dashicons {
			width: 18px;
			height: 18px;
			font-size: 18px;
			line-height: 18px;
		}
			.cmx-beleg-repeat-action {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				color: #cc4b00;
				text-decoration: none;
				min-height: 20px;
			}
			.cmx-beleg-repeat-action:hover,
			.cmx-beleg-repeat-action:focus {
				color: #8a3200;
			}
			.cmx-beleg-action-pdf {
				color: #a42c24;
			}
			.cmx-beleg-action-upload {
				color: #2271b1;
			}
			.cmx-beleg-action-related {
				color: #111111;
			}
			.cmx-belegeingang-confirm-action {
				color: #cc4b00;
			}
			.cmx-belegeingang-confirm-action:hover,
			.cmx-belegeingang-confirm-action:focus {
				color: #8a3200;
			}
			.cmx-beleg-action-disabled {
				opacity: 0.35;
			}
			.cmx-beleg-action-placeholder {
				display: inline-block;
				width: 18px;
				height: 18px;
			}
			.cmx-beleg-mail-wrap {
				position: relative;
				display: inline-flex;
				justify-content: center;
			}
			.cmx-beleg-mail-action {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 24px;
				height: 24px;
				margin: 0;
				padding: 0;
				border: 0;
				background: transparent;
				color: #2271b1;
				cursor: pointer;
			}
			.cmx-beleg-mail-action:hover,
			.cmx-beleg-mail-action:focus { color: #135e96; }
			.cmx-beleg-mail-action.is-overdue { color: #d63638; }
			.cmx-beleg-mail-action.is-overdue:hover,
			.cmx-beleg-mail-action.is-overdue:focus { color: #b32d2e; }
			.cmx-beleg-mail-action.is-trustee { color: #00a32a; }
			.cmx-beleg-mail-action.is-trustee:hover,
			.cmx-beleg-mail-action.is-trustee:focus { color: #008a20; }
			.cmx-beleg-mail-action[disabled] { cursor: wait; opacity: 0.45; }
			.cmx-beleg-mail-action .dashicons {
				width: 18px;
				height: 18px;
				font-size: 18px;
				line-height: 18px;
			}
			.cmx-beleg-mail-menu {
				position: absolute;
				z-index: 1000;
				top: calc(100% + 5px);
				right: 0;
				width: 210px;
				padding: 6px;
				border: 1px solid #c3c4c7;
				border-radius: 6px;
				background: #fff;
				box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
				text-align: left;
			}
			.cmx-beleg-mail-menu[hidden] { display: none; }
			.cmx-beleg-mail-menu button {
				display: block;
				width: 100%;
				margin: 0;
				padding: 8px 10px;
				border: 0;
				border-radius: 4px;
				background: transparent;
				color: #1d2327;
				cursor: pointer;
				text-align: left;
			}
			.cmx-beleg-mail-menu button:hover,
			.cmx-beleg-mail-menu button:focus { background: #f0f0f1; color: #135e96; }
			.cmx-beleg-mail-menu button[disabled] { cursor: wait; opacity: 0.45; }
		</style>';
});

\add_action('admin_footer-edit.php', function (): void {
	if (!isset($_GET['post_type']) || (string) $_GET['post_type'] !== CMX_PT_BELEGE) {
		return;
	}

	$nonce = (string) \wp_create_nonce('cmx_start_send_existing_beleg');
	echo '<script>
		(function(){
			var nonce = ' . \wp_json_encode($nonce) . ';
			var closeMenus = function(except){
				document.querySelectorAll(".cmx-beleg-mail-menu").forEach(function(menu){
					if (menu === except) return;
					menu.hidden = true;
					var trigger = menu.parentNode ? menu.parentNode.querySelector("[data-cmx-beleg-admin-mail]") : null;
					if (trigger) trigger.setAttribute("aria-expanded", "false");
				});
			};
			var send = function(trigger, postId, mode){
				if (!trigger || !postId || trigger.disabled) return;
				var wrap = trigger.closest(".cmx-beleg-mail-wrap");
				var controls = wrap ? wrap.querySelectorAll("button") : [trigger];
				var defaultTitle = String(trigger.getAttribute("data-default-title") || trigger.getAttribute("title") || "Beleg per E-Mail versenden");
				controls.forEach(function(control){ control.disabled = true; });
				trigger.setAttribute("title", "Beleg wird versendet…");
				closeMenus();
				var body = new URLSearchParams();
				body.set("action", "cmx_start_send_existing_beleg");
				body.set("post_id", String(postId));
				body.set("send_mode", String(mode || "normal"));
				body.set("_ajax_nonce", nonce);
				fetch(String(window.ajaxurl || ""), {
					method: "POST",
					credentials: "same-origin",
					headers: {"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"},
					body: body.toString()
				}).then(function(response){ return response.json(); }).then(function(response){
					if (!response || !response.success) {
						throw new Error(response && response.data ? String(response.data) : "Beleg konnte nicht versendet werden.");
					}
					var recipient = response.data && response.data.to ? String(response.data.to) : "";
					var label = response.data && response.data.action_label ? String(response.data.action_label) : "Beleg";
					window.alert(recipient ? (label + " wurde an " + recipient + " versendet.") : (label + " wurde versendet."));
				}).catch(function(error){
					window.alert(error && error.message ? error.message : "Beleg konnte nicht versendet werden.");
				}).finally(function(){
					trigger.setAttribute("title", defaultTitle);
					controls.forEach(function(control){ control.disabled = false; });
				});
			};
			document.addEventListener("click", function(event){
				var option = event.target && event.target.closest ? event.target.closest("[data-cmx-beleg-admin-mail-option]") : null;
				if (option) {
					event.preventDefault();
					event.stopPropagation();
					var optionWrap = option.closest(".cmx-beleg-mail-wrap");
					var optionTrigger = optionWrap ? optionWrap.querySelector("[data-cmx-beleg-admin-mail]") : null;
					send(optionTrigger, parseInt(option.getAttribute("data-post-id") || "0", 10), String(option.getAttribute("data-send-mode") || "normal"));
					return;
				}
				var trigger = event.target && event.target.closest ? event.target.closest("[data-cmx-beleg-admin-mail]") : null;
				if (!trigger) {
					closeMenus();
					return;
				}
				event.preventDefault();
				event.stopPropagation();
				var mode = String(trigger.getAttribute("data-send-mode") || "normal");
				if (mode !== "menu") {
					send(trigger, parseInt(trigger.getAttribute("data-post-id") || "0", 10), mode);
					return;
				}
				var menu = trigger.parentNode ? trigger.parentNode.querySelector(".cmx-beleg-mail-menu") : null;
				if (!menu) return;
				var opening = menu.hidden;
				closeMenus(menu);
				menu.hidden = !opening;
				trigger.setAttribute("aria-expanded", opening ? "true" : "false");
				if (opening) {
					var first = menu.querySelector("button");
					if (first) first.focus();
				}
			});
			document.addEventListener("keydown", function(event){
				if (event.key === "Escape") closeMenus();
			});
		})();
	</script>';
});
