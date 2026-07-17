<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_KONTAKTE_META_STATUS')) {
	\define(__NAMESPACE__ . '\\CMX_KONTAKTE_META_STATUS', '_cmx_kontakte_status');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_status_definitions')) {
	/**
	 * @return array<string,array{label:string,info:string,icon:string,selectable:bool}>
	 */
	function cmx_kontakte_status_definitions(): array {
		return [
			'play' => [
				'label'      => 'Start',
				'info'       => 'Aktuell',
				'icon'       => 'dashicons-controls-play',
				'selectable' => true,
			],
			'pause' => [
				'label'      => 'Pause',
				'info'       => 'Inaktiv',
				'icon'       => 'dashicons-controls-pause',
				'selectable' => false,
			],
			'stop' => [
				'label'      => 'Stop',
				'info'       => 'Kontakt-Stop',
				'icon'       => 'dashicons-minus',
				'selectable' => false,
			],
			'no' => [
				'label'      => 'Deaktiviert',
				'info'       => 'Kontakt-Verbot',
				'icon'       => 'dashicons-no',
				'selectable' => false,
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_normalize_status')) {
	function cmx_kontakte_normalize_status($status): string {
		$status = \sanitize_key((string) $status);
		$definitions = cmx_kontakte_status_definitions();
		return isset($definitions[$status]) ? $status : 'play';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_status_ui_data')) {
	/**
	 * @return array{status:string,label:string,info:string,icon:string,selectable:bool}
	 */
	function cmx_kontakte_status_ui_data(string $status): array {
		$status = cmx_kontakte_normalize_status($status);
		$definitions = cmx_kontakte_status_definitions();
		$current = $definitions[$status];

		return [
			'status'     => $status,
			'label'      => (string) ($current['label'] ?? ''),
			'info'       => (string) ($current['info'] ?? ''),
			'icon'       => (string) ($current['icon'] ?? 'dashicons-controls-play'),
			'selectable' => !empty($current['selectable']),
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_status_post_type_allowed')) {
	function cmx_kontakte_status_post_type_allowed(int $post_id): bool {
		$post_type = (string) \get_post_type($post_id);
		return \in_array($post_type, ['kontakte', 'kontakt'], true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_get_status')) {
	function cmx_kontakte_get_status(int $post_id): string {
		if ($post_id <= 0) {
			return 'play';
		}
		return cmx_kontakte_normalize_status((string) \get_post_meta($post_id, CMX_KONTAKTE_META_STATUS, true));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_store_status')) {
	function cmx_kontakte_store_status(int $post_id, $status): string {
		$normalized = cmx_kontakte_normalize_status($status);
		if ($post_id > 0) {
			\update_post_meta($post_id, CMX_KONTAKTE_META_STATUS, $normalized);
		}
		return $normalized;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_status_is_selectable')) {
	function cmx_kontakte_status_is_selectable(string $status): bool {
		$ui = cmx_kontakte_status_ui_data($status);
		return !empty($ui['selectable']);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_is_selectable_contact')) {
	function cmx_kontakte_is_selectable_contact(int $post_id): bool {
		if ($post_id <= 0) {
			return false;
		}
		return cmx_kontakte_status_is_selectable(cmx_kontakte_get_status($post_id));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_selection_meta_query')) {
	function cmx_kontakte_selection_meta_query(): array {
		return [
			'relation' => 'OR',
			[
				'key'     => CMX_KONTAKTE_META_STATUS,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'   => CMX_KONTAKTE_META_STATUS,
				'value' => 'play',
			],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_apply_selection_query_args')) {
	function cmx_kontakte_apply_selection_query_args(array $args, bool $allow_inactive = false): array {
		if ($allow_inactive) {
			return $args;
		}

		$status_query = cmx_kontakte_selection_meta_query();
		$existing_meta_query = isset($args['meta_query']) && \is_array($args['meta_query']) ? $args['meta_query'] : [];

		if ($existing_meta_query === []) {
			$args['meta_query'] = $status_query;
			return $args;
		}

		$args['meta_query'] = [
			'relation' => 'AND',
			$existing_meta_query,
			$status_query,
		];
		return $args;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_filter_selectable_ids')) {
	function cmx_kontakte_filter_selectable_ids(array $ids, bool $allow_inactive = false): array {
		if ($allow_inactive) {
			return \array_values(\array_filter(\array_map('intval', $ids)));
		}

		return \array_values(\array_filter(\array_map('intval', $ids), static function (int $post_id): bool {
			return $post_id > 0 && cmx_kontakte_is_selectable_contact($post_id);
		}));
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_status_control_html')) {
	function cmx_kontakte_status_control_html(int $post_id, array $args = []): string {
		$args = \wp_parse_args($args, [
			'context'    => 'edit',
			'input_name' => '',
			'input_id'   => '',
		]);

		$context = (string) $args['context'];
		$status = cmx_kontakte_get_status($post_id);
		$current = cmx_kontakte_status_ui_data($status);
		$nonce = \wp_create_nonce('cmx_kontakte_set_status');
		$classes = ['cmx-kontakt-status-control', 'is-' . $status];
		if ($context === 'list') {
			$classes[] = 'is-list';
		}

		$html = '<div class="' . \esc_attr(\implode(' ', $classes)) . '" data-post-id="' . (int) $post_id . '" data-status="' . \esc_attr($status) . '" data-nonce="' . \esc_attr($nonce) . '">';

		$input_name = (string) $args['input_name'];
		if ($input_name !== '') {
			$input_id = (string) $args['input_id'];
			$html .= '<input type="hidden" class="cmx-kontakt-status-value" name="' . \esc_attr($input_name) . '" value="' . \esc_attr($status) . '"' . ($input_id !== '' ? ' id="' . \esc_attr($input_id) . '"' : '') . '>';
		}

		$trigger_label = \trim((string) $current['label'] . ' - ' . (string) $current['info']);
		$html .= '<button type="button" class="button cmx-kontakt-status-trigger" aria-haspopup="true" aria-expanded="false" title="' . \esc_attr($trigger_label) . '" aria-label="' . \esc_attr($trigger_label) . '">';
		$html .= '<span class="dashicons ' . \esc_attr((string) $current['icon']) . ' cmx-kontakt-status-trigger-icon" aria-hidden="true"></span>';
		$html .= '<span class="screen-reader-text cmx-kontakt-status-trigger-text">' . \esc_html($trigger_label) . '</span>';
		$html .= '</button>';
		$html .= '<div class="cmx-kontakt-status-menu" hidden>';

		foreach (cmx_kontakte_status_definitions() as $value => $item) {
			$is_current = $value === $status;
			$html .= '<button type="button" class="cmx-kontakt-status-menu-item' . ($is_current ? ' is-current' : '') . '" data-status="' . \esc_attr($value) . '" data-icon="' . \esc_attr((string) $item['icon']) . '" data-label="' . \esc_attr((string) $item['label']) . '" data-info="' . \esc_attr((string) $item['info']) . '">';
			$html .= '<span class="cmx-kontakt-status-menu-line">';
			$html .= '<span class="dashicons ' . \esc_attr((string) $item['icon']) . '" aria-hidden="true"></span>';
			$html .= '<span class="cmx-kontakt-status-menu-title">' . \esc_html((string) $item['label']) . '</span>';
			$html .= '</span>';
			$html .= '<span class="cmx-kontakt-status-menu-desc">' . \esc_html((string) $item['info']) . '</span>';
			$html .= '</button>';
		}

		$html .= '</div></div>';
		return $html;
	}
}

\add_action('init', __NAMESPACE__ . '\\cmx_register_kontakte_status_meta');
function cmx_register_kontakte_status_meta(): void {
	foreach (['kontakte', 'kontakt'] as $post_type) {
		if ($post_type === 'kontakt' && !\post_type_exists('kontakt')) {
			continue;
		}

		\register_post_meta($post_type, CMX_KONTAKTE_META_STATUS, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\cmx_kontakte_normalize_status',
			'auth_callback'     => static fn() => \current_user_can('edit_posts'),
		]);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_status_screen_active')) {
	function cmx_kontakte_status_screen_active(): bool {
		if (!\function_exists('get_current_screen')) {
			return false;
		}
		$screen = \get_current_screen();
		if (!$screen) {
			return false;
		}

		$post_type = (string) ($screen->post_type ?? '');
		$base = (string) ($screen->base ?? '');
		return \in_array($post_type, ['kontakte', 'kontakt'], true) && \in_array($base, ['edit', 'post', 'post-new'], true);
	}
}

\add_action('admin_head', __NAMESPACE__ . '\\cmx_kontakte_status_admin_assets_head');
function cmx_kontakte_status_admin_assets_head(): void {
	if (!cmx_kontakte_status_screen_active()) {
		return;
	}

	echo '<style>
		.cmx-kontakt-status-control{position:relative;display:inline-flex;min-width:0;z-index:1}
		.cmx-kontakt-status-control.is-open{z-index:1000000}
		.cmx-kontakt-status-control .cmx-kontakt-status-trigger{
			display:inline-flex;
			align-items:center;
			gap:0;
			width:36px;
			min-width:36px;
			min-height:36px;
			padding:0;
			border-radius:8px;
			justify-content:center;
		}
		.cmx-kontakt-status-control.is-list{width:36px}
		.cmx-kontakt-status-control.is-list .cmx-kontakt-status-trigger{width:32px;min-width:32px;min-height:32px}
		.cmx-kontakt-status-control .cmx-kontakt-status-trigger .dashicons,
		.cmx-kontakt-status-control .cmx-kontakt-status-menu .dashicons{
			width:16px;
			height:16px;
			font-size:16px;
			line-height:16px;
		}
		.cmx-kontakt-status-control.is-play .cmx-kontakt-status-trigger{border-color:#b7e4c7;background:#f0fdf4;color:#166534}
		.cmx-kontakt-status-control.is-pause .cmx-kontakt-status-trigger{border-color:#d6bbfb;background:#faf5ff;color:#7c3aed}
		.cmx-kontakt-status-control.is-stop .cmx-kontakt-status-trigger{border-color:#fecaca;background:#fef2f2;color:#b91c1c}
		.cmx-kontakt-status-control.is-no .cmx-kontakt-status-trigger{border-color:#fda4af;background:#fff1f2;color:#b42318}
		.cmx-kontakt-status-control.is-busy .cmx-kontakt-status-trigger{opacity:.65;pointer-events:none}
		.cmx-kontakt-status-menu{
			position:fixed;
			left:-9999px;
			top:-9999px;
			right:auto;
			bottom:auto;
			min-width:190px;
			padding:6px;
			border:1px solid #d0d7de;
			border-radius:10px;
			background:#fff;
			box-shadow:0 12px 28px rgba(15,23,42,.16);
			z-index:1000008;
		}
		.cmx-kontakt-status-menu-item{
			display:block;
			width:100%;
			margin:0;
			padding:8px 10px;
			border:0;
			border-radius:8px;
			background:transparent;
			text-align:left;
			cursor:pointer;
		}
		.cmx-kontakt-status-menu-item:hover,
		.cmx-kontakt-status-menu-item:focus,
		.cmx-kontakt-status-menu-item.is-current{background:#f0f6fc;outline:none}
		.cmx-kontakt-status-menu-line{display:flex;align-items:center;gap:6px;font-weight:600}
		.cmx-kontakt-status-menu-desc{display:block;margin-left:22px;font-size:11px;color:#646970}
		.post-type-kontakte .column-cmx_status,
		.post-type-kontakt .column-cmx_status{width:54px;overflow:visible;position:relative;z-index:1}
		.post-type-kontakte .wp-list-table tr.cmx-status-menu-open,
		.post-type-kontakt .wp-list-table tr.cmx-status-menu-open{position:relative;z-index:1000003}
		.post-type-kontakte .wp-list-table tr.cmx-status-menu-open > td,
		.post-type-kontakt .wp-list-table tr.cmx-status-menu-open > td,
		.post-type-kontakte .wp-list-table tr.cmx-status-menu-open > th,
		.post-type-kontakt .wp-list-table tr.cmx-status-menu-open > th{position:relative;overflow:visible;z-index:1000003}
		.post-type-kontakte .column-cmx_status.is-open,
		.post-type-kontakt .column-cmx_status.is-open{z-index:1000004}
		.post-type-kontakte .column-cmx_status .cmx-kontakt-status-control.is-open,
		.post-type-kontakt .column-cmx_status .cmx-kontakt-status-control.is-open{z-index:1000002}
		.post-type-kontakte .column-cmx_status .cmx-kontakt-status-control,
		.post-type-kontakt .column-cmx_status .cmx-kontakt-status-control{width:32px}
		#cmx-stammdaten .field--status .cmx-kontakt-status-control{display:inline-flex;width:auto}
	</style>';
}

\add_action('admin_footer', __NAMESPACE__ . '\\cmx_kontakte_status_admin_assets_footer');
function cmx_kontakte_status_admin_assets_footer(): void {
	if (!cmx_kontakte_status_screen_active()) {
		return;
	}
	?>
	<script>
	(function(){
		if (window.cmxKontaktStatusInit) return;
		window.cmxKontaktStatusInit = true;
		var ajaxUrl = window.ajaxurl || "";
		var statuses = ["play", "pause", "stop", "no"];

		function allControls(){
			return Array.prototype.slice.call(document.querySelectorAll(".cmx-kontakt-status-control"));
		}

		function statusCell(root){
			return root ? root.closest("td.column-cmx_status, th.column-cmx_status") : null;
		}

		function statusRow(root){
			return root ? root.closest("tr") : null;
		}

		function setOpenState(root, isOpen){
			if (!root) return;
			var menu = root.querySelector(".cmx-kontakt-status-menu");
			var trigger = root.querySelector(".cmx-kontakt-status-trigger");
			var cell = statusCell(root);
			var row = statusRow(root);
			if (menu) menu.hidden = !isOpen;
			root.classList.toggle("is-open", isOpen);
			if (!isOpen && menu) {
				menu.style.left = "-9999px";
				menu.style.top = "-9999px";
				menu.style.visibility = "";
			}
			if (cell) cell.classList.toggle("is-open", isOpen);
			if (row) row.classList.toggle("cmx-status-menu-open", isOpen);
			if (trigger) trigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
		}

		function updateMenuPlacement(root){
			if (!root) return;
			var menu = root.querySelector(".cmx-kontakt-status-menu");
			var trigger = root.querySelector(".cmx-kontakt-status-trigger");
			if (!menu || !trigger) return;
			var previousHidden = menu.hidden;
			if (previousHidden) {
				menu.hidden = false;
				menu.style.visibility = "hidden";
			}
			var triggerRect = trigger.getBoundingClientRect();
			var menuRect = menu.getBoundingClientRect();
			var viewportWidth = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
			var viewportHeight = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
			var left = triggerRect.left;
			var top = triggerRect.bottom + 4;
			var maxLeft = Math.max(8, viewportWidth - menuRect.width - 8);
			if (left > maxLeft) {
				left = maxLeft;
			}
			if (left < 8) {
				left = 8;
			}
			var belowBottom = top + menuRect.height;
			var upwardTop = triggerRect.top - menuRect.height - 4;
			if (belowBottom > (viewportHeight - 8) && upwardTop >= 8) {
				top = upwardTop;
			} else if (belowBottom > (viewportHeight - 8)) {
				top = Math.max(8, viewportHeight - menuRect.height - 8);
			}
			menu.style.left = String(Math.round(left)) + "px";
			menu.style.top = String(Math.round(top)) + "px";
			if (previousHidden) {
				menu.hidden = true;
				menu.style.visibility = "";
			}
		}

		function closeAllMenus(exceptRoot){
			allControls().forEach(function(root){
				if (exceptRoot && root === exceptRoot) return;
				setOpenState(root, false);
			});
		}

		function applyPayload(root, payload){
			if (!root || !payload || !payload.status) return;
			root.dataset.status = payload.status;
			statuses.forEach(function(status){
				root.classList.remove("is-" + status);
			});
			root.classList.add("is-" + payload.status);

			var hidden = root.querySelector(".cmx-kontakt-status-value");
			if (hidden) hidden.value = payload.status;

			var trigger = root.querySelector(".cmx-kontakt-status-trigger");
			if (trigger) {
				var icon = trigger.querySelector(".cmx-kontakt-status-trigger-icon");
				var text = trigger.querySelector(".cmx-kontakt-status-trigger-text");
				var label = String(payload.label || "");
				var info = String(payload.info || "");
				var triggerLabel = (label && info) ? (label + " - " + info) : (label || info);
				if (icon) icon.className = "dashicons " + String(payload.icon || "") + " cmx-kontakt-status-trigger-icon";
				if (text) text.textContent = triggerLabel;
				if (triggerLabel) {
					trigger.setAttribute("title", triggerLabel);
					trigger.setAttribute("aria-label", triggerLabel);
				}
			}

			root.querySelectorAll(".cmx-kontakt-status-menu-item").forEach(function(item){
				item.classList.toggle("is-current", item.getAttribute("data-status") === payload.status);
			});
		}

		function applyPayloadToPost(postId, payload){
			allControls().forEach(function(root){
				if (String(root.getAttribute("data-post-id") || "") === String(postId || "")) {
					applyPayload(root, payload);
				}
			});
		}

		function updateStatus(root, status){
			var postId = parseInt(root.getAttribute("data-post-id") || "0", 10);
			var nonce = root.getAttribute("data-nonce") || "";
			var hidden = root.querySelector(".cmx-kontakt-status-value");
			var previous = root.dataset.status || "play";
			var currentItem = root.querySelector('.cmx-kontakt-status-menu-item[data-status="' + String(status || "") + '"]');

			if (!ajaxUrl || !(postId > 0) || !nonce) {
				var fallbackLabel = currentItem ? String(currentItem.getAttribute("data-label") || "") : "";
				var fallbackInfo = currentItem ? String(currentItem.getAttribute("data-info") || "") : status;
				applyPayload(root, {
					status: status,
					icon: currentItem ? String(currentItem.getAttribute("data-icon") || "") : "",
					label: fallbackLabel,
					info: fallbackInfo
				});
				return;
			}

			root.classList.add("is-busy");
			if (hidden) hidden.value = status;

			var body = new URLSearchParams();
			body.append("action", "cmx_kontakte_set_status");
			body.append("_ajax_nonce", nonce);
			body.append("post_id", String(postId));
			body.append("status", status);

			fetch(ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
				},
				body: body.toString()
			}).then(function(response){
				return response.json();
			}).then(function(json){
				if (!json || !json.success || !json.data || !json.data.control) {
					throw new Error("status_update_failed");
				}
				applyPayloadToPost(postId, json.data.control);
			}).catch(function(){
				if (hidden) hidden.value = previous;
			}).finally(function(){
				root.classList.remove("is-busy");
			});
		}

		document.addEventListener("click", function(event){
			var item = event.target.closest(".cmx-kontakt-status-menu-item");
			if (item) {
				var root = item.closest(".cmx-kontakt-status-control");
				if (!root) return;
				event.preventDefault();
				closeAllMenus();
				updateStatus(root, item.getAttribute("data-status") || "play");
				return;
			}

			var trigger = event.target.closest(".cmx-kontakt-status-trigger");
			if (trigger) {
				var root = trigger.closest(".cmx-kontakt-status-control");
				if (!root) return;
				var menu = root.querySelector(".cmx-kontakt-status-menu");
				if (!menu) return;
				event.preventDefault();
				var willOpen = !!menu.hidden;
				closeAllMenus(root);
				if (willOpen) updateMenuPlacement(root);
				setOpenState(root, willOpen);
				return;
			}

			closeAllMenus();
		});

			document.addEventListener("keydown", function(event){
				if (event.key === "Escape") {
					closeAllMenus();
				}
			});

			window.addEventListener("resize", function(){
				closeAllMenus();
			});

			document.addEventListener("scroll", function(){
				closeAllMenus();
			}, true);
		})();
		</script>
	<?php
}

\add_action('wp_ajax_cmx_kontakte_set_status', __NAMESPACE__ . '\\cmx_kontakte_set_status_ajax');
function cmx_kontakte_set_status_ajax(): void {
	if (!\current_user_can('edit_posts')) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}

	$nonce = isset($_POST['_ajax_nonce']) ? (string) \wp_unslash($_POST['_ajax_nonce']) : '';
	if (!\wp_verify_nonce($nonce, 'cmx_kontakte_set_status')) {
		\wp_send_json_error(['message' => 'bad_nonce'], 403);
	}

	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	if ($post_id <= 0 || !cmx_kontakte_status_post_type_allowed($post_id) || !\current_user_can('edit_post', $post_id)) {
		\wp_send_json_error(['message' => 'invalid_post'], 403);
	}

	$status = isset($_POST['status']) ? (string) \wp_unslash($_POST['status']) : 'play';
	$status = cmx_kontakte_store_status($post_id, $status);

	\wp_send_json_success([
		'post_id' => $post_id,
		'control' => cmx_kontakte_status_ui_data($status),
	]);
}
