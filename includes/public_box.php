<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_post_type_can_create')) {
	function cmx_post_type_can_create(string $post_type): bool {
		$obj = \get_post_type_object($post_type);
		if (!$obj) {
			return false;
		}
		$cap = (string) ($obj->cap->create_posts ?? '');
		if ($cap === '') {
			return \current_user_can('edit_posts');
		}
		return \current_user_can($cap);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_post_type_can_publish')) {
	function cmx_post_type_can_publish(string $post_type): bool {
		$obj = \get_post_type_object($post_type);
		if (!$obj) {
			return false;
		}
		$cap = (string) ($obj->cap->publish_posts ?? '');
		if ($cap === '') {
			return \current_user_can('publish_posts');
		}
		return \current_user_can($cap);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_create_beleg_action_url')) {
	function cmx_kontakt_create_beleg_action_url(int $kontakt_id): string {
		$kontakt_id = (int) $kontakt_id;
		if ($kontakt_id <= 0) {
			return '';
		}

		return (string) \wp_nonce_url(
			\add_query_arg(
				[
					'action'     => 'cmx_kontakt_create_beleg',
					'kontakt_id' => $kontakt_id,
				],
				\admin_url('admin-post.php')
			),
			'cmx_kontakt_create_beleg_' . $kontakt_id
		);
	}
}

\add_action('admin_post_cmx_artikel_create_beleg', __NAMESPACE__ . '\\cmx_artikel_create_beleg_handler');
if (!\function_exists(__NAMESPACE__ . '\\cmx_beleg_position_row_from_artikel')) {
	function cmx_beleg_position_row_from_artikel(int $artikel_id): array {
		$artikel_id = (int) $artikel_id;
		if ($artikel_id <= 0) {
			return [];
		}

		$artikel_name = (string) \get_the_title($artikel_id);
		if (\function_exists(__NAMESPACE__ . '\\cmx_beleg_decode_label_text')) {
			$artikel_name = (string) cmx_beleg_decode_label_text($artikel_name);
		} elseif (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$artikel_name = (string) cmx_normalize_minus_sign($artikel_name);
		}
		$artikel_name = \trim($artikel_name);
		if ($artikel_name === '') {
			$artikel_name = '#' . $artikel_id;
		}

		$vk_meta_key = \defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
		$vk_raw = (string) \get_post_meta($artikel_id, $vk_meta_key, true);
		$vk = 0.0;
		if ($vk_raw !== '') {
			if (\function_exists(__NAMESPACE__ . '\\cmx_norm_decimal')) {
				$vk = (float) cmx_norm_decimal($vk_raw);
			} else {
				$vk = (float) \str_replace(',', '.', $vk_raw);
			}
		}
		if (!\is_finite($vk) || $vk < 0) {
			$vk = 0.0;
		}

		$unit_id = 0;
		$unit_name = '';
		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_default_einheit')) {
			$default_unit = (array) cmx_artikel_default_einheit($artikel_id);
			$unit_id = (int) ($default_unit['id'] ?? 0);
			$unit_name = \trim((string) ($default_unit['name'] ?? ''));
		}

		return [
			'artikel_id'            => $artikel_id,
			'artikel_name'          => $artikel_name,
			'artikel_variant_index' => '',
			'menge'                 => 1,
			'einheit_id'            => $unit_id > 0 ? $unit_id : 0,
			'unit'                  => $unit_name,
			'preis'                 => $vk > 0 ? (string) \round($vk, 2) : '',
			'rabatt'                => '',
			'beschreibung'          => '',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_assign_default_beleg_invoice')) {
	function cmx_artikel_assign_default_beleg_invoice(int $beleg_id): void {
		$beleg_id = (int) $beleg_id;
		if ($beleg_id <= 0) {
			return;
		}

		\update_post_meta($beleg_id, '_cmx_beleg_richtung', 'ausgang');

		$tax = \function_exists(__NAMESPACE__ . '\\cmx_belege_tax')
			? (string) cmx_belege_tax()
			: '';
		if ($tax === '') {
			return;
		}

		$term = \get_term_by('slug', 'rechnung', $tax);
		if ($term instanceof \WP_Term) {
			\wp_set_post_terms($beleg_id, [(int) $term->term_id], $tax, false);
		}
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_requested_items')) {
	function cmx_artikel_create_beleg_requested_items(): array {
		$order = [];
		$quantities = [];

		if (isset($_REQUEST['artikel_ids'])) {
			foreach ((array) \wp_unslash((array) $_REQUEST['artikel_ids']) as $raw_id) {
				$artikel_id = (int) $raw_id;
				if ($artikel_id > 0) {
					$order[] = $artikel_id;
				}
			}
		}

		if (isset($_REQUEST['artikel_mengen']) && \is_array($_REQUEST['artikel_mengen'])) {
			foreach ((array) \wp_unslash((array) $_REQUEST['artikel_mengen']) as $raw_id => $raw_qty) {
				$artikel_id = (int) $raw_id;
				$qty = (int) $raw_qty;
				if ($artikel_id <= 0 || $qty <= 0) {
					continue;
				}
				$quantities[$artikel_id] = $qty;
				if (!\in_array($artikel_id, $order, true)) {
					$order[] = $artikel_id;
				}
			}
		}

		if ($order === []) {
			$artikel_id = isset($_REQUEST['artikel_id']) ? (int) \wp_unslash($_REQUEST['artikel_id']) : 0;
			if ($artikel_id > 0) {
				$order[] = $artikel_id;
			}
		}

		$items = [];
		$seen = [];
		foreach ($order as $artikel_id) {
			$artikel_id = (int) $artikel_id;
			if ($artikel_id <= 0 || isset($seen[$artikel_id])) {
				continue;
			}
			$seen[$artikel_id] = true;
			$qty = isset($quantities[$artikel_id]) ? (int) $quantities[$artikel_id] : 1;
			if ($qty <= 0) {
				continue;
			}
			$items[] = [
				'artikel_id' => $artikel_id,
				'menge'      => $qty,
			];
		}

		return $items;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_position_rows')) {
	function cmx_artikel_create_beleg_position_rows(array $items): array {
		$rows = [];
		foreach ($items as $item) {
			$artikel_id = (int) ($item['artikel_id'] ?? 0);
			$qty = (int) ($item['menge'] ?? 1);
			if ($artikel_id <= 0 || $qty <= 0) {
				continue;
			}

			$artikel_post = \get_post($artikel_id);
			if (
				!$artikel_post instanceof \WP_Post
				|| (string) $artikel_post->post_type !== 'artikel'
				|| !\current_user_can('edit_post', $artikel_id)
			) {
				continue;
			}

			$row = \function_exists(__NAMESPACE__ . '\\cmx_beleg_position_row_from_artikel')
				? (array) cmx_beleg_position_row_from_artikel($artikel_id)
				: [];
			if ($row === []) {
				continue;
			}

			$row['menge'] = $qty;
			$rows[] = $row;
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_action_url')) {
	function cmx_artikel_create_beleg_action_url(int $artikel_id): string {
		$artikel_id = (int) $artikel_id;
		if ($artikel_id <= 0) {
			return '';
		}

		return (string) \wp_nonce_url(
			\add_query_arg(
				[
					'action'     => 'cmx_artikel_create_beleg',
					'artikel_id' => $artikel_id,
				],
				\admin_url('admin-post.php')
			),
			'cmx_artikel_create_beleg_' . $artikel_id
		);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_handler')) {
	function cmx_artikel_create_beleg_handler(): void {
		$requested_items = \function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_requested_items')
			? (array) cmx_artikel_create_beleg_requested_items()
			: [];
		$primary_artikel_id = (int) ($requested_items[0]['artikel_id'] ?? 0);
		$is_cart_request = isset($_REQUEST['artikel_ids']) || (isset($_REQUEST['artikel_mengen']) && \is_array($_REQUEST['artikel_mengen']));

		$redirect_url = (string) (\wp_get_referer() ?: '');
		if ($redirect_url === '') {
			if ($primary_artikel_id > 0 && \function_exists(__NAMESPACE__ . '\\cmx_artikel_detail_url')) {
				$redirect_url = (string) cmx_artikel_detail_url($primary_artikel_id);
			} elseif ($primary_artikel_id > 0) {
				$redirect_url = (string) \get_edit_post_link($primary_artikel_id, '');
			} else {
				$redirect_url = (string) \admin_url('edit.php?post_type=artikel');
			}
		}

		if ($requested_items === []) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		if ($is_cart_request) {
			if (!isset($_REQUEST['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_REQUEST['_wpnonce']), 'cmx_artikel_create_beleg_cart')) {
				\wp_die('Ungültige Anfrage.');
			}
		} else {
			if (!isset($_REQUEST['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_REQUEST['_wpnonce']), 'cmx_artikel_create_beleg_' . $primary_artikel_id)) {
				\wp_die('Ungültige Anfrage.');
			}
		}

		if (
			! \post_type_exists('belege')
			|| !cmx_post_type_can_create('belege')
			|| !cmx_post_type_can_publish('belege')
		) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		$position_rows = \function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_position_rows')
			? (array) cmx_artikel_create_beleg_position_rows($requested_items)
			: [];
		if ($position_rows === []) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		$beleg_id = \wp_insert_post([
			'post_type'   => 'belege',
			'post_status' => 'publish',
			'post_title'  => '',
			'post_author' => (int) \get_current_user_id(),
			'meta_input'  => [
				'_cmx_title_auto'       => 1,
				'_cmx_beleg_richtung'   => 'ausgang',
				'_cmx_beleg_positionen' => $position_rows,
			],
		], true);

		if (\is_wp_error($beleg_id) || (int) $beleg_id <= 0) {
			\wp_safe_redirect($redirect_url);
			exit;
		}
		$beleg_id = (int) $beleg_id;
		if ((string) \get_post_status($beleg_id) !== 'publish') {
			$publish_result = \wp_update_post([
				'ID'          => $beleg_id,
				'post_status' => 'publish',
			], true);
			if (\is_wp_error($publish_result)) {
				\wp_safe_redirect($redirect_url);
				exit;
			}
		}

		if (\function_exists(__NAMESPACE__ . '\\cmx_artikel_assign_default_beleg_invoice')) {
			cmx_artikel_assign_default_beleg_invoice($beleg_id);
		}

		$edit_url = (string) \get_edit_post_link($beleg_id, '');
		if ($edit_url === '') {
			$edit_url = (string) \admin_url('post.php?post=' . (int) $beleg_id . '&action=edit');
		}

		$edit_args = [
			'cmx_focus_contact' => '1',
		];
		if ($primary_artikel_id > 0) {
			$edit_args['cmx_created_from_artikel'] = $primary_artikel_id;
		}
		if ($is_cart_request) {
			$edit_args['cmx_clear_artikel_cart'] = '1';
		}
		$edit_url = (string) \add_query_arg($edit_args, $edit_url);

		\wp_safe_redirect($edit_url);
		exit;
	}
}

\add_action('admin_post_cmx_kontakt_create_beleg', __NAMESPACE__ . '\\cmx_kontakt_create_beleg_handler');
if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakt_create_beleg_handler')) {
	function cmx_kontakt_create_beleg_handler(): void {
		$kontakt_id = isset($_REQUEST['kontakt_id']) ? (int) \wp_unslash($_REQUEST['kontakt_id']) : 0;
		$redirect_url = $kontakt_id > 0
			? (string) \get_edit_post_link($kontakt_id, '')
			: (string) \admin_url('edit.php?post_type=kontakte');

		if ($kontakt_id <= 0) {
			\wp_safe_redirect($redirect_url);
			exit;
		}

		if (!isset($_REQUEST['_wpnonce']) || !\wp_verify_nonce((string) \wp_unslash($_REQUEST['_wpnonce']), 'cmx_kontakt_create_beleg_' . $kontakt_id)) {
			\wp_die('Ungültige Anfrage.');
		}

		$kontakt_post = \get_post($kontakt_id);
		$kontakt_types = ['kontakte', 'kontakt'];
		if (\function_exists(__NAMESPACE__ . '\\cmx_kontakte_cpt')) {
			$kontakt_types[] = (string) cmx_kontakte_cpt();
		}
		$kontakt_types = \array_values(\array_unique(\array_filter(\array_map('strval', $kontakt_types))));
			if (
				!$kontakt_post instanceof \WP_Post
				|| !\in_array((string) $kontakt_post->post_type, $kontakt_types, true)
				|| !\current_user_can('edit_post', $kontakt_id)
				|| !\post_type_exists('belege')
				|| !cmx_post_type_can_create('belege')
				|| !cmx_post_type_can_publish('belege')
			) {
				\wp_safe_redirect($redirect_url);
				exit;
			}

		$kontakt_label = (string) \get_the_title($kontakt_id);
		if (\function_exists(__NAMESPACE__ . '\\cmx_normalize_minus_sign')) {
			$kontakt_label = (string) cmx_normalize_minus_sign($kontakt_label);
		}
		$kontakt_addr = \function_exists(__NAMESPACE__ . '\\cmx_build_kontakt_postanschrift')
			? (string) cmx_build_kontakt_postanschrift($kontakt_id)
			: '';

			$beleg_id = \wp_insert_post([
				'post_type'   => 'belege',
				'post_status' => 'publish',
				'post_title'  => '',
				'post_author' => (int) \get_current_user_id(),
				'meta_input'  => [
					'_cmx_title_auto'         => 1,
				'_cmx_beleg_kontakt_id'   => $kontakt_id,
				'_cmx_beleg_kontakt_label'=> $kontakt_label,
				'_cmx_beleg_kontakt_addr' => $kontakt_addr,
			],
		], true);

			if (\is_wp_error($beleg_id) || (int) $beleg_id <= 0) {
				\wp_safe_redirect($redirect_url);
				exit;
			}
			$beleg_id = (int) $beleg_id;
			if ((string) \get_post_status($beleg_id) !== 'publish') {
				$publish_result = \wp_update_post([
					'ID'          => $beleg_id,
					'post_status' => 'publish',
				], true);
				if (\is_wp_error($publish_result)) {
					\wp_safe_redirect($redirect_url);
					exit;
				}
			}

			$edit_url = (string) \get_edit_post_link($beleg_id, '');
		if ($edit_url === '') {
			$edit_url = (string) \admin_url('post.php?post=' . (int) $beleg_id . '&action=edit');
		}
		$edit_url = (string) \add_query_arg(
			[
				'cmx_focus_article'       => '1',
				'cmx_created_from_kontakt'=> $kontakt_id,
			],
			$edit_url
		);

		\wp_safe_redirect($edit_url);
		exit;
	}
}


add_action('admin_head', function () {
	echo '<style>.screen-reader-shortcut,#wp-admin-bar-search,#wp-admin-bar-command-palette{display:none !important;}.button-full{width:100%;display:block;box-sizing:border-box;text-align:center;}.cmx-savebox-primary-row{display:flex;align-items:center;gap:8px;width:100%;min-width:0;}.cmx-savebox-primary-row .button-full{flex:1 1 auto;min-width:0;width:auto;}.cmx-savebox-icon-button,.button.cmx-savebox-icon-button,[data-cmx-beleg-action-mail="1"].button,[data-cmx-beleg-action-muh="1"].button{position:relative;width:36px !important;min-width:36px !important;height:36px !important;min-height:36px !important;display:inline-flex !important;align-items:center !important;justify-content:center !important;flex:0 0 36px;padding:0 !important;box-sizing:border-box;line-height:36px !important;vertical-align:middle;text-align:center;background:transparent !important;border-color:transparent !important;box-shadow:none !important;color:#b32d2e !important;}.cmx-savebox-icon-button:hover,.cmx-savebox-icon-button:focus,[data-cmx-beleg-action-mail="1"].button:hover,[data-cmx-beleg-action-mail="1"].button:focus,[data-cmx-beleg-action-muh="1"].button:hover,[data-cmx-beleg-action-muh="1"].button:focus{background:#f6f7f7 !important;border-color:transparent !important;color:#8f211b !important;box-shadow:none !important;}.cmx-savebox-icon-button .dashicons,[data-cmx-beleg-action-mail="1"].button .dashicons,[data-cmx-beleg-action-muh="1"].button .dashicons{position:absolute;left:50%;top:50%;width:20px;height:20px;font-size:20px;line-height:20px;margin:0 !important;display:block;transform:translate(-50%,-50%);text-align:center;}.cmx-savebox-icon-button .dashicons:before,[data-cmx-beleg-action-mail="1"].button .dashicons:before,[data-cmx-beleg-action-muh="1"].button .dashicons:before{width:20px;height:20px;font-size:20px;line-height:20px;display:block;text-align:center;}</style>';
});

add_action('admin_footer', function (): void {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || (string) $screen->post_type === '') {
		return;
	}
	$post_type = (string) $screen->post_type;
	$list_url = $post_type === 'post'
		? \admin_url('edit.php')
		: \admin_url('edit.php?post_type=' . \rawurlencode($post_type));
	?>
	<script>
	(function(){
		var currentPostType = <?php echo \wp_json_encode($post_type); ?>;
		var currentPostTypeListUrl = <?php echo \wp_json_encode($list_url); ?>;

		function isSavedNoticeVisible() {
			var notices = Array.prototype.slice.call(document.querySelectorAll('.notice-success, .updated, #message'));
			return notices.some(function (notice) {
				var text = String(notice.textContent || '').toLowerCase();
				return text.indexOf('gespeichert') !== -1 || text.indexOf('updated') !== -1;
			});
		}

		function clickFirst(selector) {
			var el = document.querySelector(selector);
			if (el && typeof el.click === 'function') {
				el.click();
				return true;
			}
			return false;
		}

		function openBelegeList() {
			window.location.href = currentPostTypeListUrl;
		}

		function isEditableTarget(target) {
			if (!target) {
				return false;
			}
			var tag = String(target.tagName || '').toLowerCase();
			return tag === 'input' || tag === 'textarea' || tag === 'select' || !!target.isContentEditable;
		}

		function isPostEditScreen() {
			return !!document.body && document.body.classList.contains('post-php');
		}

		document.addEventListener('keydown', function(event) {
			if (currentPostType !== 'belege') {
				return;
			}
			if (event.key === 'Tab' && !event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey) {
				var contactId = document.getElementById('cmx_kontakt_id');
				var contactSelected = contactId && parseInt(String(contactId.value || ''), 10) > 0;
				var target = event.target;
				var fromPdfAction = target && target.closest && target.closest('[data-cmx-beleg-action-pdf="1"], .cmx-pdf-link, .cmx-btn-download');
				if (fromPdfAction) {
					var saveButton = document.getElementById('publish');
					if (saveButton) {
						event.preventDefault();
						event.stopImmediatePropagation();
						saveButton.focus();
					}
					return;
				}
				var inContactArea = target && (
					target.id === 'cmx_kontakt_search'
					|| target.id === 'cmx_kontakt_addr'
					|| target.id === 'cmx_kontakt_clear'
					|| (target.closest && target.closest('#cmx_kontakt_suggest'))
				);
				if (contactSelected && inContactArea) {
					var articleInput = document.querySelector('#cmx-positionen-table .cmx-artikel-autocomplete');
					if (articleInput) {
						event.preventDefault();
						event.stopImmediatePropagation();
						articleInput.focus();
						try { articleInput.select(); } catch (err) {}
					}
				}
				return;
			}
			if (event.key !== 'Enter' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
				return;
			}
			var target = event.target;
			if (!target || !target.matches || !target.matches('#cmx-positionen-table input[name*="[menge]"]')) {
				return;
			}
			var publish = document.getElementById('publish');
			if (!publish) {
				return;
			}
			event.preventDefault();
			publish.click();
		}, true);

		document.addEventListener('keydown', function(event) {
			if (event.altKey || event.ctrlKey || event.metaKey) {
				return;
			}
			var key = String(event.key || '').toLowerCase();
			if (key === 'l' && isPostEditScreen() && !isEditableTarget(event.target)) {
				event.preventDefault();
				openBelegeList();
				return;
			}
			if (isEditableTarget(event.target) || !isSavedNoticeVisible()) {
				return;
			}
			if (currentPostType !== 'belege') {
				return;
			}
			if (key === 'v') {
				event.preventDefault();
				clickFirst('[data-cmx-beleg-action-pdf="1"], .cmx-pdf-link, .cmx-btn-download');
				return;
			}
			if (key === 'k') {
				event.preventDefault();
				clickFirst('[data-cmx-beleg-action-muh="1"], .cmx-belegeingang-confirm-link');
				return;
			}
			if (key === 'm') {
				event.preventDefault();
				clickFirst('[data-cmx-beleg-action-mail="1"][href]');
				return;
			}
		}, true);
	})();
	</script>
	<?php
});

add_action('admin_footer', function (): void {
	if (!\is_admin()) {
		return;
	}

	$start_target = \function_exists(__NAMESPACE__ . '\\cmx_start_dashboard_hidden_for_current_user') && cmx_start_dashboard_hidden_for_current_user()
		? \admin_url('users.php')
		: \admin_url('index.php?page=cmx-start-dashboard');

	$targets = [
		's' => $start_target,
		'k' => \admin_url('edit.php?post_type=kontakte'),
		'a' => \admin_url('edit.php?post_type=artikel'),
		'b' => \admin_url('edit.php?post_type=belege'),
		'd' => \admin_url('edit.php?post_type=dokumente'),
		'p' => \admin_url('edit.php?post_type=projekte'),
		'u' => \admin_url('edit.php?post_type=buchungen'),
		't' => \admin_url('edit.php?post_type=scanner'),
		'n' => \admin_url('admin.php?page=cmx-camt-import-log'),
		'c' => \admin_url('edit.php?post_type=carent'),
		'g' => \admin_url('edit.php?post_type=budget'),
		'e' => \admin_url('admin.php?page=cmx-einstellungen'),
	];
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	$current_post_type = $screen ? (string) ($screen->post_type ?? '') : '';
	if ($current_post_type === '' && isset($_GET['post_type'])) {
		$current_post_type = \sanitize_key((string) \wp_unslash($_GET['post_type']));
	}
	$current_list_url = $current_post_type === 'post'
		? \admin_url('edit.php')
		: ($current_post_type !== '' ? \admin_url('edit.php?post_type=' . \rawurlencode($current_post_type)) : '');
	?>
	<script>
	(function(){
		var targets = <?php echo \wp_json_encode($targets); ?> || {};
		var currentPostType = <?php echo \wp_json_encode($current_post_type); ?>;
		var currentPostTypeListUrl = <?php echo \wp_json_encode($current_list_url); ?>;
		var lastEmptySearchEscAt = 0;

		function isEditableTarget(target) {
			if (!target) {
				return false;
			}
			var tag = String(target.tagName || '').toLowerCase();
			return tag === 'input' || tag === 'textarea' || tag === 'select' || !!target.isContentEditable;
		}

		function isInteractiveTarget(target) {
			if (isEditableTarget(target)) {
				return true;
			}
			if (!target || !target.closest) {
				return false;
			}
			return !!target.closest(
				'a[href], button, summary, option, [role="button"], [role="link"], '
				+ '[role="option"], [role="listbox"], [role="combobox"], [role="menuitem"], '
				+ '[tabindex]:not([tabindex="-1"])'
			);
		}

		function savedBelegActionHasPriority(key) {
			if (currentPostType !== 'belege') {
				return false;
			}
			if (['v', 'k', 'm', 'l'].indexOf(key) === -1) {
				return false;
			}
			var notices = Array.prototype.slice.call(document.querySelectorAll('.notice-success, .updated, #message'));
			return notices.some(function (notice) {
				var text = String(notice.textContent || '').toLowerCase();
				return text.indexOf('gespeichert') !== -1 || text.indexOf('updated') !== -1;
			});
		}

		function focusCurrentListSearch() {
			var input = document.querySelector('#post-search-input, .search-box input[type="search"], .search-box input[name="s"]');
			if (!input) {
				return false;
			}
			input.focus();
			try { input.select(); } catch (err) {}
			return true;
		}

		function createCurrentListRecord() {
			if (!document.body || !document.body.classList.contains('edit-php')) {
				return false;
			}
			var addButton = document.querySelector('.wrap .page-title-action[href], a.page-title-action[href]');
			if (!addButton) {
				return false;
			}
			var href = String(addButton.getAttribute('href') || '');
			if (!href) {
				return false;
			}
			window.location.href = href;
			return true;
		}

		function isCurrentListSearchTarget(target) {
			if (!target || !target.matches) {
				return false;
			}
			return target.matches('#post-search-input, .search-box input[type="search"], .search-box input[name="s"]');
		}

		function maybeResetCurrentListSearch(event) {
			if (event.key !== 'Escape' || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
				return false;
			}
			if (!currentPostTypeListUrl || !document.body || !document.body.classList.contains('edit-php') || !isCurrentListSearchTarget(event.target)) {
				return false;
			}
			if (String(event.target.value || '').trim() !== '') {
				lastEmptySearchEscAt = 0;
				return false;
			}
			var now = Date.now();
			if (lastEmptySearchEscAt && (now - lastEmptySearchEscAt) <= 650) {
				event.preventDefault();
				event.stopImmediatePropagation();
				window.location.href = currentPostTypeListUrl;
				return true;
			}
			lastEmptySearchEscAt = now;
			return true;
		}

		document.addEventListener('keydown', function(event) {
			if (maybeResetCurrentListSearch(event)) {
				return;
			}
			if (event.key === 'Tab' && !event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey && !isInteractiveTarget(event.target)) {
				if (focusCurrentListSearch()) {
					event.preventDefault();
					event.stopImmediatePropagation();
				}
				return;
			}
			if (event.key === 'Enter' && !event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey && !isInteractiveTarget(event.target)) {
				if (createCurrentListRecord()) {
					event.preventDefault();
					event.stopImmediatePropagation();
				}
				return;
			}
			if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || isInteractiveTarget(event.target)) {
				return;
			}
			var key = String(event.key || '').toLowerCase();
			if (!targets[key] || savedBelegActionHasPriority(key)) {
				return;
			}
			event.preventDefault();
			window.location.href = targets[key];
		}, true);
	})();
	</script>
	<?php
});


/**
 * Minimaler „Speichern“-Button statt „Veröffentlichen“-Metabox (Classic Editor)
 * mit sichtbarem „In den Papierkorb verschieben“-Link nach dem Speichern.
 */
add_action('add_meta_boxes', function() {
	$allowed = ['post', 'page', 'kontakte','artikel','belege','kassenbuch','dokumente','projekte','ausgaben','scanner','carent','budget','infrastruktur','zugangsdaten'];
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, $allowed, true)) return;

	$box_title = ($screen->post_type === 'belege')
		? __('Beleg speichern ...', 'default')
		: __('Aktion', 'default');

	add_meta_box('cmx_savebox', $box_title,
		function($post) use ($screen) {

				$is_new       = ($post->ID === 0 || $post->post_status === 'auto-draft');
				$post_type    = $screen->post_type;
				$is_belege    = ($post_type === 'belege');
				$is_kontakte  = ($post_type === 'kontakte');
				$is_artikel   = ($post_type === 'artikel');
				$is_carent    = ($post_type === 'carent');
				$is_infrastruktur = ($post_type === 'infrastruktur');
				$is_add_screen = (($screen->action ?? '') === 'add');
			$pt_obj       = get_post_type_object($post_type);
			$singular     = $pt_obj->labels->singular_name ?? '';

			// Fallback-Mapping, falls das Label plural ist oder fehlt
			$singular_map = [
				'kontakte'    => 'Kontakt',
				'artikel'     => 'Artikel',
				'belege'      => 'Beleg',
				'kassenbuch'  => 'Kassenbuch',
				'budget'      => 'Budget',
				'dokumente'   => 'Dokument',
				'projekte'    => 'Projekt',
				'scanner'     => 'Scan',
				'carent'      => 'Carent',
				'infrastruktur' => 'Infrastruktur',
				'post'        => __('Beitrag', 'default'),
				'page'        => __('Seite', 'default'),
			];
			if ($singular === '' || strcasecmp($singular, $pt_obj->labels->name ?? '') === 0) {
				$singular = $singular_map[$post_type] ?? ucfirst($post_type);
			}

			$btn_label    = sprintf('%s speichern', $singular);
			$btn_name     = $is_new ? 'publish' : 'save';
			$save_as_opts = [
				'rechnung'     => 'als Rechnung speichern',
				'offerte'      => 'als Offerte speichern',
				'lieferschein' => 'als Lieferschein duplizieren',
				'rechnung_kopie' => 'als Rechnung duplizieren',
			];
			$save_as_val = 'rechnung';
			$send_href = '';
			$download_url = '';
			$kontakt_belege_url = '';
			$kontakt_telefonbuch_url = '';
			$kontakt_zugangsdaten_url = '';
			$beleg_kontakt_muh_url = '';
			$beleg_send_eingang_url = '';
			$has_pdf = false;
			$send_tooltip = 'PDF-Link per Mail versendern';
			if ($is_belege && function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_pdf_paths')) {
				if ((int) $post->ID > 0) {
					$send_href = esc_url(admin_url('admin-post.php?action=cmxbu_beleg_send&post_id='.(int)$post->ID));
					$kontakt_id = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_contact_id')
						? (int) cmxbu_get_beleg_contact_id((int) $post->ID)
						: (int) get_post_meta((int) $post->ID, '_cmx_beleg_kontakt_id', true);
					if ($kontakt_id > 0) {
						$recipient_mail = \function_exists(__NAMESPACE__ . '\\cmx_kommunikation_primary_email')
							? \sanitize_email((string) cmx_kommunikation_primary_email($kontakt_id))
							: \sanitize_email((string) get_post_meta($kontakt_id, '_cmx_email_1', true));
						if (\is_email($recipient_mail)) {
							$send_tooltip .= ' an: ' . $recipient_mail;
						}
						$muh_meta_key = \defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_MUH') ? (string) \constant(__NAMESPACE__ . '\\CMX_KONTAKTE_META_MUH') : '_cmx_kontakte_muh';
						$muh_value = \trim((string) get_post_meta($kontakt_id, $muh_meta_key, true));
						if ($muh_value !== '') {
							$beleg_kontakt_muh_url = \function_exists(__NAMESPACE__ . '\\cmx_normalize_url_for_href')
								? (string) cmx_normalize_url_for_href($muh_value)
								: (\preg_match('~^https?://~i', $muh_value) ? $muh_value : 'https://' . \ltrim($muh_value, '/'));
							$beleg_send_eingang_url = (string) \wp_nonce_url(
								\add_query_arg(['action' => 'cmx_beleg_send_eingang', 'post_id' => (int) $post->ID], \admin_url('admin-post.php')),
								'cmx_beleg_send_eingang_' . (int) $post->ID
							);
						}
					}
				}
				[, $pdf_abs_path] = cmxbu_get_beleg_pdf_paths($post);
				$has_pdf = is_file($pdf_abs_path);
				if ($has_pdf) {
					if (function_exists(__NAMESPACE__ . '\\cmxbu_get_stable_token')) {
						$token = cmxbu_get_stable_token($post->ID);
						$download_url = \function_exists(__NAMESPACE__ . '\\cmxbu_get_beleg_public_url')
							? esc_url(cmxbu_get_beleg_public_url((int) $post->ID, $token))
							: esc_url(add_query_arg('beleg', $token, home_url('/')));
					}
				}
			}
			if ($is_kontakte && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_share_url')) {
				$kontakt_belege_url = (string) cmx_kontakt_belege_share_url((int) $post->ID);
			}
			if ($is_kontakte && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_url')) {
				$kontakt_telefonbuch_url = (string) cmx_telefonbuch_detail_url((int) $post->ID);
			}
			if ($is_kontakte && (int) $post->ID > 0) {
				$kontakt_zugangsdaten_url = (string) \add_query_arg([
					'post_type' => 'zugangsdaten',
					'cmx_zugangsdaten_contact' => (int) $post->ID,
				], \admin_url('edit.php'));
			}
				$new_beleg_url = ($is_kontakte && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kontakt_create_beleg_action_url'))
					? (string) cmx_kontakt_create_beleg_action_url((int) $post->ID)
					: '';
				$new_beleg_from_artikel_url = ($is_artikel && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_action_url'))
					? (string) cmx_artikel_create_beleg_action_url((int) $post->ID)
					: '';
					$new_beleg_from_carent_url = ($is_carent && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_carent_create_beleg_action_url'))
						? (string) cmx_carent_create_beleg_action_url((int) $post->ID)
						: '';
					$existing_carent_beleg_url = '';
					if ($is_carent && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_carent_existing_beleg_id')) {
						$existing_carent_beleg_id = (int) cmx_carent_existing_beleg_id((int) $post->ID);
						if ($existing_carent_beleg_id > 0) {
							$existing_carent_beleg_url = (string) get_edit_post_link($existing_carent_beleg_id, 'raw');
							if ($existing_carent_beleg_url !== '') {
								$new_beleg_from_carent_url = $existing_carent_beleg_url;
							}
						}
					}
					$carent_beleg_enabled = $is_carent && \sanitize_key((string) \get_post_meta((int) $post->ID, '_cmx_carent_status', true)) === 'abgeschlossen';
				$artikel_katalog_icon_html = ($is_artikel && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_artikel_katalog_icon_html'))
					? (string) cmx_artikel_katalog_icon_html((int) $post->ID)
					: '';
				$carent_vertrag_icon_html = ($is_carent && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_live_icon_link'))
					? (string) cmx_carent_vertrag_live_icon_link((int) $post->ID)
					: '';
				$carent_vertrag_pdf_icon_html = ($is_carent && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_carent_vertrag_pdf_icon_link'))
					? (string) cmx_carent_vertrag_pdf_icon_link((int) $post->ID)
					: '';

				echo '<div style="padding:2px 0 8px;">';
			if ($is_belege) {
				$hide_save_as = false;
				$active_slug = '';
				if (function_exists(__NAMESPACE__ . '\\cmx_belege_tax')) {
					$tax = cmx_belege_tax();
					if ($tax) {
						$slugs = wp_get_post_terms($post->ID, $tax, ['fields' => 'slugs']);
						if (!is_wp_error($slugs) && !empty($slugs)) {
							$active_slug = (string) $slugs[0];
						}
					}
				}
				if ($active_slug === 'offerte') {
					$save_as_val = 'offerte';
				} else {
					$save_as_val = 'rechnung';
				}
				wp_nonce_field('cmx_beleg_save_as', 'cmx_beleg_save_as_nonce');
				echo '<div id="cmx_beleg_save_as_wrap" style="margin-bottom:8px;' . ($hide_save_as ? 'display:none;' : '') . '">';
				echo '<select name="cmx_beleg_save_as" id="cmx_beleg_save_as" style="width:100%;">';
				foreach ($save_as_opts as $val => $label) {
					echo '<option value="'.esc_attr($val).'" '.selected($save_as_val, $val, false).'>'.esc_html($label).'</option>';
				}
				echo '</select>';
				echo '</div>';
				echo '<script>(function(){var wrap=document.getElementById("cmx_beleg_save_as_wrap");var sel=document.getElementById("cmx_beleg_save_as");if(!wrap||!sel)return;var optRech=sel.querySelector("option[value=rechnung]");var optOff=sel.querySelector("option[value=offerte]");var optLief=sel.querySelector("option[value=lieferschein]");var optRechCopy=sel.querySelector("option[value=rechnung_kopie]");function getSlug(){var el=document.querySelector("input[name=cmx_beleg_kategorie]:checked");return el?(el.getAttribute("data-slug")||""):"";}function getRichtung(){var el=document.querySelector("input[name=cmx_beleg_richtung]:checked");return el?el.value:"";}function setOpt(opt, show){if(!opt)return;opt.disabled=!show;opt.hidden=!show;}function sync(){var slug=getSlug();var richtung=getRichtung();if((slug==="rechnung"||slug==="offerte")&&richtung==="ausgang"){wrap.style.display="";if(slug==="rechnung"){setOpt(optRech,true);setOpt(optLief,true);setOpt(optOff,false);setOpt(optRechCopy,false);sel.value="rechnung";}else{setOpt(optOff,true);setOpt(optRechCopy,true);setOpt(optRech,false);setOpt(optLief,false);sel.value="offerte";}}else{wrap.style.display="none";}}document.addEventListener("change",function(e){if(e.target&&(e.target.name==="cmx_beleg_kategorie"||e.target.name==="cmx_beleg_richtung")){sync();}});document.addEventListener("DOMContentLoaded",function(){sync();setTimeout(sync,200);});setTimeout(sync,0);})();</script>';
			}
			echo '<div class="cmx-savebox-primary-row">';
			printf(
				'<input type="submit" name="%1$s" id="publish" class="button button-primary button-large button-full" value="%2$s" />',
				esc_attr($btn_name),
				esc_attr($btn_label)
			);
			if ($is_belege && $beleg_send_eingang_url !== '') {
				echo '<a href="' . esc_url($beleg_send_eingang_url) . '" title="' . esc_attr__('Beleg an Muh-Instanz senden', 'default') . '" class="button button-secondary cmx-savebox-icon-button" data-cmx-beleg-action-muh="1"><span class="dashicons dashicons-carrot" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('Beleg an Muh-Instanz senden', 'default') . '</span></a>';
			}
			if ($is_belege && $send_href !== '') {
				echo '<a href="'.$send_href.'" title="' . esc_attr($send_tooltip) . '" class="button button-secondary cmx-savebox-icon-button" data-cmx-beleg-action-mail="1"><span class="dashicons dashicons-email" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('PDF-Link per Mail versenden', 'default') . '</span></a>';
			}
			echo '</div>';
			echo '</div>';

				if ($is_belege && $post->ID) {
					$tax = function_exists(__NAMESPACE__ . '\\cmx_belege_tax') ? cmx_belege_tax() : '';
					$from_id = (int) get_post_meta($post->ID, '_cmx_beleg_copied_from', true);
					$to_id = (int) get_post_meta($post->ID, '_cmx_beleg_copied_to', true);
					$current_slug = '';
					if ($tax) {
						$current_slugs = wp_get_post_terms($post->ID, $tax, ['fields' => 'slugs']);
						if (!is_wp_error($current_slugs) && !empty($current_slugs)) {
							$current_slug = (string) $current_slugs[0];
						}
					}
					$rechnung_slugs = ['rechnung', 'rechnungen'];
					$lieferschein_slugs = ['lieferschein', 'lieferscheine'];
					$is_rechnung = in_array($current_slug, $rechnung_slugs, true);
					$is_lieferschein = in_array($current_slug, $lieferschein_slugs, true);
					$get_beleg_slug = static function (int $beleg_id) use ($tax): string {
						if ($beleg_id <= 0 || $tax === '') {
							return '';
						}
						$slugs = wp_get_post_terms($beleg_id, $tax, ['fields' => 'slugs']);
						if (is_wp_error($slugs) || empty($slugs)) {
							return '';
						}
						return (string) $slugs[0];
					};
					$is_lieferschein_id = static function (int $beleg_id) use ($tax, $get_beleg_slug, $lieferschein_slugs): bool {
						if ($beleg_id <= 0) {
							return false;
						}
						if ($tax === '') {
							return true;
						}
						$slug = $get_beleg_slug($beleg_id);
						return in_array($slug, $lieferschein_slugs, true);
					};
					$sort_liefer_ids = static function (array $ids): array {
						$ids = array_values(array_filter(array_unique(array_map('intval', $ids)), static function (int $id): bool {
							return $id > 0;
						}));
						if (count($ids) < 2) {
							return $ids;
						}
						$order_map = [];
						foreach ($ids as $lid) {
							$p = get_post($lid);
							$ts = 0;
							if ($p) {
								$date_raw = (string) ((isset($p->post_date_gmt) && $p->post_date_gmt !== '0000-00-00 00:00:00')
									? $p->post_date_gmt
									: $p->post_date);
								$ts_val = ($date_raw !== '') ? strtotime($date_raw) : 0;
								$ts = ($ts_val !== false) ? (int) $ts_val : 0;
							}
							$order_map[$lid] = $ts;
						}
						usort($ids, static function (int $a, int $b) use ($order_map): int {
							$ta = (int) ($order_map[$a] ?? 0);
							$tb = (int) ($order_map[$b] ?? 0);
							if ($ta === $tb) {
								return $a <=> $b;
							}
							return $ta <=> $tb;
						});
						return $ids;
					};
					$collect_lieferschein_ids = static function (int $source_id) use ($tax, $lieferschein_slugs, $sort_liefer_ids): array {
						if ($source_id <= 0) {
							return [];
						}
						$queue = [(int) $source_id];
						$seen_sources = [];
						$liefer_ids = [];
						while (!empty($queue)) {
							$current_source = (int) array_shift($queue);
							if ($current_source <= 0 || isset($seen_sources[$current_source])) {
								continue;
							}
							$seen_sources[$current_source] = true;
							$args = [
								'post_type' => 'belege',
								'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
								'fields' => 'ids',
								'posts_per_page' => -1,
								'no_found_rows' => true,
								'suppress_filters' => true,
								'orderby' => ['date' => 'ASC', 'ID' => 'ASC'],
								'order' => 'ASC',
								'meta_query' => [[
									'key' => '_cmx_beleg_copied_from',
									'value' => (int) $current_source,
									'compare' => '=',
								]],
							];
							if ($tax) {
								$args['tax_query'] = [[
									'taxonomy' => $tax,
									'field' => 'slug',
									'terms' => $lieferschein_slugs,
								]];
							}
							$child_ids = get_posts($args);
							if (!is_array($child_ids) || empty($child_ids)) {
								continue;
							}
							foreach ($child_ids as $lid) {
								$lid = (int) $lid;
								if ($lid <= 0) {
									continue;
								}
								if (!in_array($lid, $liefer_ids, true)) {
									$liefer_ids[] = $lid;
								}
								$queue[] = $lid;
							}
						}
						return $sort_liefer_ids($liefer_ids);
					};

					if ($from_id > 0) {
						$from_link = get_edit_post_link($from_id, '');
						$from_cat = '';
						if ($tax) {
							$from_terms = wp_get_post_terms($from_id, $tax, ['fields' => 'names']);
							if (!is_wp_error($from_terms) && !empty($from_terms)) {
								$from_cat = (string) $from_terms[0];
							}
						}
						if ($from_link && $from_cat !== '') {
							echo '<div style="margin-top:0px; font-size:12px;">';
							echo 'von ' . esc_html($from_cat) . ': <a href="' . esc_url($from_link) . '">' . esc_html(get_the_title($from_id)) . '</a>';
							echo '</div>';
						}
					}

					$liefer_ids = [];
					if ($is_rechnung) {
						$liefer_ids = $collect_lieferschein_ids((int) $post->ID);
						if ($to_id > 0 && !in_array((int) $to_id, $liefer_ids, true) && $is_lieferschein_id((int) $to_id)) {
							$liefer_ids[] = (int) $to_id;
							$liefer_ids = $sort_liefer_ids($liefer_ids);
						}
					} elseif ($is_lieferschein) {
						$source_rechnung_id = 0;
						$trace_id = (int) $from_id;
						$trace_seen = [];
						while ($trace_id > 0 && !isset($trace_seen[$trace_id])) {
							$trace_seen[$trace_id] = true;
							$trace_slug = $get_beleg_slug($trace_id);
							if (in_array($trace_slug, $rechnung_slugs, true)) {
								$source_rechnung_id = $trace_id;
								break;
							}
							if (in_array($trace_slug, $lieferschein_slugs, true)) {
								$next_trace = (int) get_post_meta($trace_id, '_cmx_beleg_copied_from', true);
								if ($next_trace <= 0) {
									break;
								}
								$trace_id = $next_trace;
								continue;
							}
							break;
						}
						$source_id = $source_rechnung_id > 0 ? $source_rechnung_id : ((int) $from_id > 0 ? (int) $from_id : 0);
						if ($source_id > 0) {
							$liefer_ids = $collect_lieferschein_ids($source_id);
						}
						if (!in_array((int) $post->ID, $liefer_ids, true)) {
							$liefer_ids[] = (int) $post->ID;
						}
						if ($to_id > 0 && !in_array((int) $to_id, $liefer_ids, true) && $is_lieferschein_id((int) $to_id)) {
							$liefer_ids[] = (int) $to_id;
						}
						$liefer_ids = $sort_liefer_ids($liefer_ids);
					}
					if (!empty($liefer_ids)) {
						$links = [];
						foreach ($liefer_ids as $lid) {
							if ((int) $lid === (int) $post->ID) {
								continue;
							}
							$edit_link = get_edit_post_link($lid, '');
							if (!$edit_link) {
								continue;
							}
							$links[] = '<a href="' . esc_url($edit_link) . '">' . esc_html(get_the_title($lid)) . '</a>';
						}
						if (!empty($links)) {
							echo '<div style="margin-top:0px; font-size:12px;">';
							echo (count($links) > 1 ? 'zu Lieferscheinen: ' : 'zu Lieferschein: ') . implode(', ', $links);
							echo '</div>';
						}
					} elseif ($to_id > 0 && (int) $to_id !== (int) $post->ID) {
						$to_link = get_edit_post_link($to_id, '');
						$to_cat = '';
						if ($tax) {
							$to_terms = wp_get_post_terms($to_id, $tax, ['fields' => 'names']);
							if (!is_wp_error($to_terms) && !empty($to_terms)) {
								$to_cat = (string) $to_terms[0];
							}
						}
						if ($to_link && $to_cat !== '') {
							echo '<div style="margin-top:0px; font-size:12px;">';
							echo 'zu ' . esc_html($to_cat) . ': <a href="' . esc_url($to_link) . '">' . esc_html(get_the_title($to_id)) . '</a>';
							echo '</div>';
						}
					}
			}

			// Icons: Duplizieren + Papierkorb (ohne Text)
			$has_uploads = false;
			if ($is_belege && $post->ID) {
				$meta = (array) get_post_meta($post->ID, '_cmx_belege_uploads', true);
				$meta = array_values(array_filter($meta, function($v){ return $v !== '' && $v !== null; }));
				$has_uploads = !empty($meta);
			}
			$show_actions = ($post->ID && ($post->post_status !== 'auto-draft' || $has_uploads) && ($is_belege || !$is_add_screen));
			if ($show_actions) {
				$delete_link = get_delete_post_link($post->ID);
				$dup_fn = __NAMESPACE__ . '\\cmx_dup_get_action_url';
				$dup_link = is_callable($dup_fn) ? $dup_fn((int)$post->ID) : '';

				$show_pdf_icons = ($is_belege && $has_pdf && $download_url !== '');
				$belegeingang_confirm_url = '';
				$is_pending_belegeingang = $is_belege
					&& (string) $post->post_status === 'pending'
					&& (string) \get_post_meta((int) $post->ID, '_cmx_belegeingang_source', true) === 'rest'
					&& (string) \get_post_meta((int) $post->ID, '_cmx_belegeingang_status', true) === 'pending';
				if ($is_pending_belegeingang) {
					$dup_link = '';
					$belegeingang_confirm_url = (string) \wp_nonce_url(
						\add_query_arg([
							'action' => 'cmx_belegeingang_confirm',
							'post_id' => (int) $post->ID,
							'redirect_to' => \rawurlencode((string) \get_edit_post_link((int) $post->ID, 'raw')),
						], \admin_url('admin-post.php')),
						'cmx_belegeingang_confirm_' . (int) $post->ID
					);
				}
					if ($delete_link || $dup_link !== '' || $is_infrastruktur || $new_beleg_url !== '' || $new_beleg_from_artikel_url !== '' || $new_beleg_from_carent_url !== '' || $artikel_katalog_icon_html !== '' || $carent_vertrag_icon_html !== '' || $carent_vertrag_pdf_icon_html !== '' || $show_pdf_icons || $belegeingang_confirm_url !== '' || $kontakt_belege_url !== '' || $kontakt_telefonbuch_url !== '' || $kontakt_zugangsdaten_url !== '') {
						$justify = $is_belege ? 'space-between' : 'flex-start';
						$action_gap = $is_kontakte ? '12px' : '8px';
							echo '<div style="margin-top:8px; padding-top:0; display:flex; justify-content:'.$justify.'; align-items:center; gap:'.$action_gap.';">';
						if ($dup_link !== '') {
							$dup_contact_style = $is_kontakte ? 'width:20px;height:20px;flex:0 0 20px;' : '';
							echo '<a href="'.esc_url($dup_link).'" class="cmx-dup-link dashicons dashicons-clipboard" style="text-decoration:none;'.$dup_contact_style.'" title="'.esc_attr__('Duplizieren','default').'"><span class="screen-reader-text">'.esc_html__('Duplizieren','default').'</span></a>';
						}
						if ($is_infrastruktur) {
							echo '<button type="button" class="button-link cmx-infrastruktur-instance-toggle" aria-expanded="false" aria-controls="cmx-infrastruktur-instance-action-panel" title="'.esc_attr__('Mis-Büro-Instanz verwalten', 'cmx-misbuero').'" aria-label="'.esc_attr__('Mis-Büro-Instanz verwalten', 'cmx-misbuero').'"><span class="dashicons dashicons-database-add" aria-hidden="true"></span><span class="screen-reader-text">'.esc_html__('Mis-Büro-Instanz verwalten', 'cmx-misbuero').'</span></button>';
						}
						if ($new_beleg_from_artikel_url !== '') {
							echo '<a href="' . esc_url($new_beleg_from_artikel_url) . '" class="cmx-artikel-new-beleg-link dashicons dashicons-media-text" style="text-decoration:none;color:#d63638;" title="Neuen Beleg mit diesem Artikel anlegen"><span class="screen-reader-text">Neuen Beleg mit diesem Artikel anlegen</span></a>';
							}
							if ($new_beleg_from_carent_url !== '') {
								$carent_beleg_enabled_title = $existing_carent_beleg_url !== '' ? 'Zum bestehenden Beleg' : 'Beleg erstellen';
								$carent_beleg_title = $carent_beleg_enabled ? $carent_beleg_enabled_title : 'Beleg erstellen erst bei Status abgeschlossen';
								echo '<a href="' . esc_url($carent_beleg_enabled ? $new_beleg_from_carent_url : '#') . '" data-cmx-carent-beleg-action="1" data-enabled-href="' . esc_url($new_beleg_from_carent_url) . '" data-enabled-title="' . esc_attr($carent_beleg_enabled_title) . '" class="cmx-carent-new-beleg-link dashicons dashicons-media-text' . ($carent_beleg_enabled ? '' : ' is-disabled') . '" style="text-decoration:none;color:' . esc_attr($carent_beleg_enabled ? '#d63638' : '#a7aaad') . ';cursor:' . esc_attr($carent_beleg_enabled ? 'pointer' : 'default') . ';" title="' . esc_attr($carent_beleg_title) . '" aria-label="' . esc_attr($carent_beleg_title) . '"' . ($carent_beleg_enabled ? '' : ' aria-disabled="true" tabindex="-1"') . '><span class="screen-reader-text">Beleg erstellen</span></a>';
								echo '<script>(function(){var link=document.querySelector("[data-cmx-carent-beleg-action]");if(!link)return;function selectedStatus(){var checked=document.querySelector("input[name=cmx_carent_status]:checked");return checked?String(checked.value||""):"";}function sync(){var enabled=selectedStatus()==="abgeschlossen";var href=String(link.getAttribute("data-enabled-href")||"#");var enabledTitle=String(link.getAttribute("data-enabled-title")||"Beleg erstellen");var title=enabled?enabledTitle:"Beleg erstellen erst bei Status abgeschlossen";link.classList.toggle("is-disabled",!enabled);link.style.color=enabled?"#d63638":"#a7aaad";link.style.cursor=enabled?"pointer":"default";link.setAttribute("href",enabled?href:"#");link.setAttribute("title",title);link.setAttribute("aria-label",title);if(enabled){link.removeAttribute("aria-disabled");link.removeAttribute("tabindex");}else{link.setAttribute("aria-disabled","true");link.setAttribute("tabindex","-1");}}document.addEventListener("change",function(event){if(event.target&&event.target.name==="cmx_carent_status"){sync();}});link.addEventListener("click",function(event){if(selectedStatus()!=="abgeschlossen"){event.preventDefault();event.stopPropagation();sync();}});sync();document.addEventListener("DOMContentLoaded",sync,{once:true});window.addEventListener("load",sync,{once:true});window.setTimeout(sync,0);window.setTimeout(sync,250);})();</script>';
							}
						if ($new_beleg_url !== '') {
							echo '<a href="' . esc_url($new_beleg_url) . '" class="cmx-kontakt-new-beleg-link dashicons dashicons-media-text" style="text-decoration:none;color:#d63638;" title="Neuen Beleg anlegen"><span class="screen-reader-text">Neuen Beleg anlegen</span></a>';
						}
						if ($artikel_katalog_icon_html !== '') {
							echo '<span style="display:inline-flex;margin-left:15px;">' . $artikel_katalog_icon_html . '</span>';
						}
						if ($carent_vertrag_icon_html !== '' || $carent_vertrag_pdf_icon_html !== '') {
							echo '<span class="cmx-carent-vertrag-actions">' . $carent_vertrag_icon_html . $carent_vertrag_pdf_icon_html . '</span>';
					}
					if ($kontakt_belege_url !== '') {
						echo '<a href="' . esc_url($kontakt_belege_url) . '" class="cmx-kontakt-belege-link dashicons dashicons-portfolio" style="display:inline-flex;width:20px;height:20px;flex:0 0 20px;margin-left:0;text-decoration:none;" title="Alle Belege dieses Kontakts anzeigen" target="_blank" rel="noopener noreferrer" data-copy-url="' . esc_attr($kontakt_belege_url) . '"><span class="screen-reader-text">Belege dieses Kontakts anzeigen</span></a>';
					}
					if ($kontakt_telefonbuch_url !== '') {
						$kontakt_telefonbuch_icon = \function_exists(__NAMESPACE__ . '\\cmx_book_user_icon_svg') ? cmx_book_user_icon_svg('cmx-book-user-icon', 19) : '';
						echo '<a href="' . esc_url($kontakt_telefonbuch_url) . '" class="cmx-kontakt-telefonbuch-link" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;flex:0 0 20px;text-decoration:none;color:#b45309;" title="Telefonbuch-Detailansicht öffnen" target="_blank" rel="noopener noreferrer">' . $kontakt_telefonbuch_icon . '<span class="screen-reader-text">Telefonbuch-Detailansicht öffnen</span></a>';
					}
					if ($kontakt_zugangsdaten_url !== '') {
						echo '<a href="' . \esc_url($kontakt_zugangsdaten_url) . '" class="cmx-kontakt-zugangsdaten-link dashicons dashicons-admin-network" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;flex:0 0 20px;text-decoration:none;color:#b32d2e;" title="Zugangsdaten dieses Kontakts anzeigen" target="_blank" rel="noopener noreferrer"><span class="screen-reader-text">Zugangsdaten dieses Kontakts anzeigen</span></a>';
					}
						if ($show_pdf_icons) {
							echo '<a href="' . esc_url($download_url) . '" class="cmx-pdf-link" data-cmx-beleg-action-pdf="1" style="text-decoration:none;" title="Anzeigen als PDF (DL/C5/C4)" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-pdf" style="margin-top:5px;"></span></a>';
						}
						if ($belegeingang_confirm_url !== '') {
							echo '<a href="' . esc_url($belegeingang_confirm_url) . '" class="cmx-belegeingang-confirm-link" data-cmx-beleg-action-muh="1" style="text-decoration:none;" title="Als Lieferanten Rechnung übernehmen"><span class="dashicons dashicons-carrot" style="margin-top:5px;"></span><span class="screen-reader-text">Als Lieferanten Rechnung übernehmen</span></a>';
						}
					if ($delete_link) {
						$delete_style = $is_belege
							? 'color:#b32d2e; text-decoration:none;'
							: 'color:#b32d2e; text-decoration:none; margin-left:auto;';
						echo '<a href="'.esc_url($delete_link).'" class="submitdelete deletion dashicons dashicons-trash" style="'.$delete_style.'" title="'.esc_attr__('In den Papierkorb verschieben', 'default').'"><span class="screen-reader-text">'.esc_html__('In den Papierkorb verschieben', 'default').'</span></a>';
					}
					echo '</div>';
						if ($is_infrastruktur) {
							$server_key_meta = \defined(__NAMESPACE__ . '\\CMX_INFRASTRUKTUR_SERVER_SSH_KEY_META')
								? \constant(__NAMESPACE__ . '\\CMX_INFRASTRUKTUR_SERVER_SSH_KEY_META')
								: '_cmx_infrastruktur_server_ssh_key';
							$server_key_id = \sanitize_key((string) \get_post_meta((int) $post->ID, $server_key_meta, true));
							$server_public_key = '';
							$provisioning_ready = \function_exists(__NAMESPACE__ . '\\cmx_infrastruktur_provisioning_is_configured')
								&& cmx_infrastruktur_provisioning_is_configured((int) $post->ID);
							if (\function_exists(__NAMESPACE__ . '\\cmx_get_admin_public_keys') && \function_exists(__NAMESPACE__ . '\\cmx_ssh_public_key_id')) {
								foreach (cmx_get_admin_public_keys() as $public_key_entry) {
									$public_key = (string) ($public_key_entry['key'] ?? '');
									if ($public_key === '') {
										continue;
									}
									$public_key_id = cmx_ssh_public_key_id($public_key);
									if ($server_key_id === '' || \hash_equals($server_key_id, $public_key_id)) {
										$server_key_id = $public_key_id;
										$server_public_key = $public_key;
										break;
									}
								}
							}
							\wp_nonce_field('cmx_infrastruktur_instance_action_' . (int) $post->ID, 'cmx_infrastruktur_instance_action_nonce');
							echo '<div id="cmx-infrastruktur-instance-action-panel" class="cmx-infrastruktur-instance-action-panel" hidden>';
							echo '<input type="hidden" name="cmx_infrastruktur_instance_public_key" value="'.esc_attr($server_public_key).'">';
							echo '<label><span>'.esc_html__('Kundenname', 'cmx-misbuero').'</span><input type="text" name="cmx_infrastruktur_instance_customer_name" class="widefat" placeholder="Musterfirma GmbH" autocomplete="organization"></label>';
							echo '<label><span>'.esc_html__('Domain', 'cmx-misbuero').'</span><input type="text" name="cmx_infrastruktur_instance_domain" class="widefat" placeholder="musterfirma-gmbh.misbuero.ch" inputmode="url" spellcheck="false" autocapitalize="none"></label>';
							echo '<label><span>'.esc_html__('E-Mail', 'cmx-misbuero').'</span><input type="email" name="cmx_infrastruktur_instance_email" class="widefat" placeholder="musterfirma-gmbh@misbuero.ch" autocomplete="email" spellcheck="false" autocapitalize="none"></label>';
							echo '<label><span>'.esc_html__('Aktion', 'cmx-misbuero').'</span><select name="cmx_infrastruktur_instance_action" class="widefat"><option value="create">create</option><option value="update">update</option><option value="pause">pause</option><option value="delete">delete</option></select></label>';
							echo '<p class="description cmx-infrastruktur-instance-key-status">'.($provisioning_ready ? esc_html__('Provisionierungs-API ist verbunden.', 'cmx-misbuero') : esc_html__('Provisionierungs-API ist noch nicht verbunden.', 'cmx-misbuero')).'</p>';
							echo '<button type="button" class="button button-primary button-large cmx-infrastruktur-instance-submit" data-post-id="'.esc_attr((string) $post->ID).'"'.($provisioning_ready ? '' : ' disabled aria-disabled="true"').' title="'.esc_attr($provisioning_ready ? __('Instanzaktion ausführen', 'cmx-misbuero') : __('Provisionierungs-API konfigurieren', 'cmx-misbuero')).'">'.esc_html__('Instanz erstellen', 'cmx-misbuero').'</button>';
							echo '<p class="cmx-infrastruktur-instance-result'.($provisioning_ready ? '' : ' is-visible is-error').'" role="status" aria-live="polite">'.($provisioning_ready ? '' : esc_html__('Provisionierungs-API-Token ist noch nicht konfiguriert.', 'cmx-misbuero')).'</p>';
							echo '</div>';
							echo '<style>.cmx-infrastruktur-instance-toggle,.cmx-infrastruktur-instance-toggle:hover,.cmx-infrastruktur-instance-toggle:focus{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;padding:0;border:0;border-bottom:0;background:transparent;color:#2271b1;text-decoration:none!important;box-shadow:none;cursor:pointer}.cmx-infrastruktur-instance-toggle .dashicons,.cmx-infrastruktur-instance-toggle .dashicons:before{width:20px;height:20px;font-size:20px;line-height:20px;text-decoration:none!important}.cmx-infrastruktur-instance-action-panel{display:grid;gap:10px;margin-top:12px;padding-top:12px;border-top:1px solid #dcdcde}.cmx-infrastruktur-instance-action-panel[hidden]{display:none}.cmx-infrastruktur-instance-action-panel label{display:grid;gap:5px;font-weight:600}.cmx-infrastruktur-instance-action-panel input,.cmx-infrastruktur-instance-action-panel select{min-height:36px;font-weight:400}.cmx-infrastruktur-instance-key-status{margin:0}.cmx-infrastruktur-instance-submit{width:100%;min-height:40px}.cmx-infrastruktur-instance-result{display:none;margin:0;padding:8px;border-radius:4px}.cmx-infrastruktur-instance-result.is-visible{display:block}.cmx-infrastruktur-instance-result.is-success{background:#edfaef;color:#146c2e}.cmx-infrastruktur-instance-result.is-error{background:#fcf0f1;color:#8a2424}</style>';
							echo '<script>(function(){var toggle=document.querySelector(".cmx-infrastruktur-instance-toggle");var panel=document.getElementById("cmx-infrastruktur-instance-action-panel");if(!toggle||!panel)return;var customerName=panel.querySelector("input[name=cmx_infrastruktur_instance_customer_name]");var domain=panel.querySelector("input[name=cmx_infrastruktur_instance_domain]");var email=panel.querySelector("input[name=cmx_infrastruktur_instance_email]");var action=panel.querySelector("select[name=cmx_infrastruktur_instance_action]");var submit=panel.querySelector(".cmx-infrastruktur-instance-submit");var result=panel.querySelector(".cmx-infrastruktur-instance-result");var domainEdited=false;var emailEdited=false;var lastAutoDomain="";var lastAutoEmail="";var actionLabels={create:"Instanz erstellen",update:"Instanz aktualisieren",pause:"Instanz pausieren",delete:"Instanz löschen"};function customerSlug(value){var slug=String(value||"").replace(/Ä/g,"Ae").replace(/Ö/g,"Oe").replace(/Ü/g,"Ue").replace(/ä/g,"ae").replace(/ö/g,"oe").replace(/ü/g,"ue").replace(/ß/g,"ss");if(typeof slug.normalize==="function"){slug=slug.normalize("NFKD").replace(/[\\u0300-\\u036f]/g,"");}return slug.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"").replace(/-+/g,"-").slice(0,63).replace(/-+$/g,"");}function syncCustomerAddress(){var slug=customerSlug(customerName?customerName.value:"");var nextDomain=slug?slug+".misbuero.ch":"";var nextEmail=slug?slug+"@misbuero.ch":"";if(domain&&(!domainEdited||domain.value===""||domain.value===lastAutoDomain)){domain.value=nextDomain;domainEdited=false;}if(email&&(!emailEdited||email.value===""||email.value===lastAutoEmail)){email.value=nextEmail;emailEdited=false;}lastAutoDomain=nextDomain;lastAutoEmail=nextEmail;}function syncActionLabel(){if(submit&&action){submit.textContent=actionLabels[action.value]||"Aktion ausführen";}}function showResult(message,type,editUrl){if(!result)return;result.textContent=String(message||"");if(editUrl){var link=document.createElement("a");link.href=String(editUrl);link.textContent=" CPT öffnen";link.style.marginLeft="6px";result.appendChild(link);}result.className="cmx-infrastruktur-instance-result is-visible "+(type==="success"?"is-success":"is-error");}function finishRequest(){if(!submit)return;submit.disabled=false;submit.removeAttribute("aria-disabled");submit.classList.remove("is-busy");}function responseJson(response){return response.json().catch(function(){throw new Error("Ungültige Serverantwort.");});}function pollJob(jobId,nonce,attempt){var body=new URLSearchParams();body.set("action","cmx_infrastruktur_instance_status");body.set("nonce",nonce);body.set("post_id",submit.getAttribute("data-post-id")||"");body.set("job_id",jobId);window.setTimeout(function(){fetch(window.ajaxurl,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()}).then(responseJson).then(function(payload){if(!payload||payload.success!==true){throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:"Statusabfrage fehlgeschlagen.");}var job=payload.data||{};if(job.status==="succeeded"){showResult("Provisionierung erfolgreich abgeschlossen.","success",job.edit_url||"");finishRequest();return;}if(job.status==="failed"){throw new Error(job.error||job.output||"Provisionierung fehlgeschlagen.");}if(attempt>=180){throw new Error("Die Provisionierung läuft weiter. Bitte den Status später erneut prüfen.");}showResult(job.status==="running"?"Provisionierung läuft …":"Provisionierung wartet …","success");pollJob(jobId,nonce,attempt+1);}).catch(function(error){showResult(error&&error.message?error.message:"Statusabfrage fehlgeschlagen.","error");finishRequest();});},2000);}if(domain){domain.addEventListener("input",function(){domainEdited=domain.value!==lastAutoDomain;});}if(email){email.addEventListener("input",function(){emailEdited=email.value!==lastAutoEmail;});}if(customerName){customerName.addEventListener("input",syncCustomerAddress);customerName.addEventListener("change",syncCustomerAddress);}if(action){action.addEventListener("change",syncActionLabel);}if(submit&&!submit.disabled){submit.addEventListener("click",function(){var nonce=document.querySelector("input[name=cmx_infrastruktur_instance_action_nonce]");var nonceValue=nonce?nonce.value:"";var selectedAction=action?String(action.value||""):"";if(!customerName||!customerName.value.trim()||!domain||!domain.value.trim()||!email||!email.value.trim()){showResult("Bitte Kundenname, Domain und E-Mail ausfüllen.","error");return;}if(selectedAction==="delete"&&!window.confirm("Diese Instanz wirklich vollständig löschen?")){return;}var body=new URLSearchParams();body.set("action","cmx_infrastruktur_instance_action");body.set("nonce",nonceValue);body.set("post_id",submit.getAttribute("data-post-id")||"");body.set("customer_name",customerName.value.trim());body.set("domain",domain.value.trim());body.set("email",email.value.trim());body.set("instance_action",selectedAction);submit.disabled=true;submit.classList.add("is-busy");showResult("Anfrage wird übermittelt …","success");fetch(window.ajaxurl,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()}).then(responseJson).then(function(payload){if(!payload||payload.success!==true){throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:"Anfrage fehlgeschlagen.");}var jobId=payload.data&&payload.data.job_id?String(payload.data.job_id):"";if(!jobId){throw new Error("Die Provisionierungs-API lieferte keine Job-ID.");}showResult("Provisionierung wurde gestartet …","success");pollJob(jobId,nonceValue,0);}).catch(function(error){showResult(error&&error.message?error.message:"Anfrage fehlgeschlagen.","error");finishRequest();});});}toggle.addEventListener("click",function(){var open=toggle.getAttribute("aria-expanded")==="true";toggle.setAttribute("aria-expanded",open?"false":"true");panel.hidden=open;if(!open&&customerName){customerName.focus();}});syncCustomerAddress();syncActionLabel();})();</script>';
						}
					}
				}
		},
		$screen->post_type,
		'side',
		'high'
	);
});



// Classic-Editor: "Website verlassen?" komplett unterdrücken – ohne deinen bestehenden Code zu ändern.
add_action('admin_footer', function () {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->base, ['post','post-new'], true)) return;

	// Falls du einschränken willst, hier Post Types anpassen:
	$targets = ['post','page','kontakte','belege','carent'];
	if (!in_array($screen->post_type, $targets, true)) return;
	?>
	<script>
	(function(){
		function killPrompt(){ try { window.onbeforeunload = null; } catch(e){} }

		// 1) Sofort bestehende Handler entfernen
		killPrompt();

		// 2) Setzen von window.onbeforeunload unterbinden
		try {
			Object.defineProperty(window, 'onbeforeunload', {
				configurable: true,
				get: function(){ return null; },
				set: function(_){ /* block */ }
			});
		} catch(e){}

		// 3) Registrierungen von beforeunload-Listenern blockieren
		(function(){
			var _add = window.addEventListener;
			window.addEventListener = function(type, listener, options){
				if (type === 'beforeunload') return; // ignorieren
				return _add.call(this, type, listener, options);
			};
		})();

		// 4) Falls bereits Listener dran sind: in Capture-Phase davor abfangen
		window.addEventListener('beforeunload', function(ev){
			// Wichtig: KEIN preventDefault aufrufen, sonst provozieren manche Browser den Prompt erst.
			try { delete ev.returnValue; } catch(e){}
			ev.stopImmediatePropagation();
			ev.stopPropagation();
		}, { capture:true });

		// 5) Zusätzliche Absicherung: regelmäßig neutralisieren (falls sehr spätes Setzen passiert)
		var killer = setInterval(killPrompt, 1500);

		// 6) Beim Speichern/Shortcut ebenfalls aufräumen (harmlos, aber sicher)
		document.addEventListener('DOMContentLoaded', function(){
			var form = document.getElementById('post');
			if (form) form.addEventListener('submit', killPrompt, { capture:true });

			document.querySelectorAll('#publish,#save-post').forEach(function(el){
				el.addEventListener('click', killPrompt, { capture:true });
			});

			document.addEventListener('keydown', function(e){
				if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.keyCode === 83)) killPrompt();
			}, { capture:true });
		});

		// 7) Cleanup
		window.addEventListener('pagehide', function(){
			clearInterval(killer);
			killPrompt();
		}, { capture:true });
	})();
	</script>
	<?php
});

add_action('admin_footer', function () {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array((string) $screen->base, ['post', 'post-new'], true)) return;
	if ((string) ($screen->post_type ?? '') !== 'kontakte') return;
	?>
	<script>
	(function(){
		function copyFallback(text){
			var input = document.createElement('textarea');
			input.value = text;
			input.setAttribute('readonly', 'readonly');
			input.style.position = 'fixed';
			input.style.opacity = '0';
			document.body.appendChild(input);
			input.focus();
			input.select();
			var ok = false;
			try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
			document.body.removeChild(input);
			return ok;
		}
		document.addEventListener('click', function(ev){
			var link = ev.target.closest('.cmx-kontakt-belege-link');
			if (!link) return;
			var text = (link.getAttribute('data-copy-url') || link.href || '').trim();
			if (!text) return;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).catch(function(){ copyFallback(text); });
				return;
			}
			copyFallback(text);
		});
	})();
	</script>
	<?php
});




// Originale "Veröffentlichen"- und "Titelform"-Metaboxen für definierte CPTs entfernen
add_action('add_meta_boxes', function () {
	$allowed = ['post', 'page', 'kontakte','artikel','belege','kassenbuch','dokumente','projekte','ausgaben','scanner','carent','budget','infrastruktur','zugangsdaten'];
	$screen  = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || !in_array($screen->post_type, $allowed, true)) return;

	// Entfernt die klassische "Veröffentlichen"-Box
	remove_meta_box('submitdiv', $screen->post_type, 'side');
	remove_meta_box('submitdiv', $screen->post_type, 'normal');
	remove_meta_box('submitdiv', $screen->post_type, 'advanced');

	// Entfernt die Metabox "Titelform" (Slug unterhalb des Titels)
	remove_meta_box('slugdiv', $screen->post_type, 'normal');
}, 100);

// Sicherheitshalber auch beim späteren Rendering (falls Plugins sie reaktivieren)
add_action('do_meta_boxes', function ($post_type) {
	$allowed = ['post', 'page', 'kontakte', 'belege', 'scanner', 'carent', 'budget', 'infrastruktur', 'zugangsdaten'];
	if (!in_array($post_type, $allowed, true)) return;

	remove_meta_box('submitdiv', $post_type, 'side');
	remove_meta_box('submitdiv', $post_type, 'normal');
	remove_meta_box('submitdiv', $post_type, 'advanced');
	remove_meta_box('slugdiv', $post_type, 'normal');
}, 100);
