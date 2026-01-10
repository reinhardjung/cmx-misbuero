<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

/* =========================================================
 * Cloud-Meister Gate
 * ========================================================= */
function cmx_is_cloud_meister(): bool {
	$user = \wp_get_current_user();
	return $user && $user->exists() && $user->display_name === 'CLOUD Meister';
}

/* =========================================================
 * Button-Metabox (nur Cloud Meister)
 * ========================================================= */
\add_action('add_meta_boxes', function () {
	if (!cmx_is_cloud_meister()) return;
	$screen = \get_current_screen();
	if (!$screen || $screen->base !== 'post') return;
	if (!\post_type_exists($screen->post_type)) return;

	\add_meta_box(
		'cmx_save_metabox_layout',
		'Layout',
		__NAMESPACE__ . '\\cmx_render_save_metabox_layout_box',
		$screen->post_type,
		'side',
		'high'
	);
}, 50);

function cmx_render_save_metabox_layout_box(\WP_Post $post): void {
	$post_type = $post->post_type;
	$nonce = \wp_create_nonce('cmx_save_metabox_layout');

	echo '<button type="button" class="button button-secondary" id="cmx-save-metabox-layout-btn" data-post-type="' . \esc_attr($post_type) . '" data-nonce="' . \esc_attr($nonce) . '">Save box position</button>';

	echo '<script>
	jQuery(function($){
		const $btn = $("#cmx-save-metabox-layout-btn");
		if (!$btn.length) return;

		function ids($root){
			if (!$root.length) return "";
			return $root.find(".postbox").map(function(){ return this.id; }).get().join(",");
		}
		function getOrder(){
			return {
				normal: ids($("#normal-sortables")),
				advanced: ids($("#advanced-sortables")),
				side: ids($("#side-sortables"))
			};
		}
		function hiddenIds(){
			const fromToggles = $(".hide-postbox-tog").filter(":not(:checked)")
				.map(function(){ return this.value; }).get().join(",");
			if (fromToggles) return fromToggles;
			return $(".postbox").filter(function(){
				return $(this).css("display") === "none";
			}).map(function(){ return this.id; }).get().join(",");
		}

		$btn.on("click", function(){
			const order = getOrder();
			const hidden = hiddenIds();
			const $form = $("<form>", { method: "post", action: "' . \esc_url(\admin_url('admin-post.php')) . '" });
			$form.append($("<input>", { type: "hidden", name: "action", value: "cmx_save_metabox_layout" }));
			$form.append($("<input>", { type: "hidden", name: "post_type", value: $btn.data("post-type") }));
			$form.append($("<input>", { type: "hidden", name: "cmx_save_metabox_layout_nonce", value: $btn.data("nonce") }));
			$form.append($("<input>", { type: "hidden", name: "cmx_mb_order_json", value: JSON.stringify(order) }));
			$form.append($("<input>", { type: "hidden", name: "cmx_mb_hidden", value: hidden }));
			$("body").append($form);
			$form.trigger("submit");
		});
	});
	</script>';
}

/* =========================================================
 * Save-Handler (speichert Layout als Default)
 * ========================================================= */
\add_action('admin_post_cmx_save_metabox_layout', function () {
	if (!cmx_is_cloud_meister()) \wp_die('forbidden', 403);
	if (!isset($_POST['cmx_save_metabox_layout_nonce']) || !\wp_verify_nonce($_POST['cmx_save_metabox_layout_nonce'], 'cmx_save_metabox_layout')) {
		\wp_die('bad_nonce', 403);
	}

	$user_id = \get_current_user_id();
	$now = time();

	// Alle CPTs sichern
	$post_types = \get_post_types(['show_ui' => true], 'names');
	$current_pt = isset($_POST['post_type']) ? \sanitize_key((string)$_POST['post_type']) : '';
	$current_screen = isset($_POST['cmx_screen']) ? \sanitize_key((string)$_POST['cmx_screen']) : '';
	$posted_order_json = isset($_POST['cmx_mb_order_json']) ? (string)$_POST['cmx_mb_order_json'] : '';
	$posted_order = [];
	if ($posted_order_json !== '') {
		$tmp = \json_decode($posted_order_json, true);
		if (\json_last_error() === JSON_ERROR_NONE && \is_array($tmp)) $posted_order = $tmp;
	}
	$posted_hidden = isset($_POST['cmx_mb_hidden']) ? (string)$_POST['cmx_mb_hidden'] : '';
	$posted_hidden = \array_values(\array_filter(\array_map('trim', \explode(',', $posted_hidden))));

	foreach ($post_types as $pt) {
		$order_key = 'meta-box-order_' . $pt;
		$hidden_key = 'metaboxhidden_' . $pt;

		if ($pt === $current_pt && !empty($posted_order)) {
			$order = $posted_order;
			$hidden = $posted_hidden;
		} else {
			$order = \get_user_option($order_key, $user_id);
			$hidden = \get_user_option($hidden_key, $user_id);
		}

		$payload = [
			'order'  => \is_array($order) ? $order : (array)$order,
			'hidden' => \is_array($hidden) ? $hidden : (array)$hidden,
			'saved_by' => $user_id,
			'saved_at' => $now,
		];

		\update_option('cmx_metabox_default_' . $pt, $payload, false);
	}

	// Dashboard-Widgets sichern
	if ($current_screen === 'dashboard' && !empty($posted_order)) {
		$dash_order = $posted_order;
		$dash_hidden = $posted_hidden;
	} else {
		$dash_order = \get_user_option('meta-box-order_dashboard', $user_id);
		$dash_hidden = \get_user_option('metaboxhidden_dashboard', $user_id);
	}
	\update_option('cmx_metabox_default_dashboard', [
		'order'  => \is_array($dash_order) ? $dash_order : (array)$dash_order,
		'hidden' => \is_array($dash_hidden) ? $dash_hidden : (array)$dash_hidden,
		'saved_by' => $user_id,
		'saved_at' => $now,
	], false);

	$redirect = \wp_get_referer() ?: \admin_url('index.php');
	$redirect = \add_query_arg(['cmx_metabox_saved' => '1'], $redirect);
	\wp_safe_redirect($redirect);
	exit;
});

/* =========================================================
 * Dashboard-Button (nur Cloud Meister)
 * ========================================================= */
\add_action('wp_dashboard_setup', function () {
	if (!cmx_is_cloud_meister()) return;
	\wp_add_dashboard_widget(
		'cmx_save_dashboard_layout',
		'Layout',
		__NAMESPACE__ . '\\cmx_render_save_dashboard_layout_widget'
	);
});

function cmx_render_save_dashboard_layout_widget(): void {
	$nonce = \wp_create_nonce('cmx_save_metabox_layout');
	echo '<button type="button" class="button button-secondary" id="cmx-save-dashboard-layout-btn" data-nonce="' . \esc_attr($nonce) . '">Save box position</button>';
	echo '<script>
	jQuery(function($){
		const $btn = $("#cmx-save-dashboard-layout-btn");
		if (!$btn.length) return;

		function ids($root){
			if (!$root.length) return "";
			return $root.find(".postbox").map(function(){ return this.id; }).get().join(",");
		}
		function hiddenIds(){
			const fromToggles = $(".hide-postbox-tog").filter(":not(:checked)")
				.map(function(){ return this.value; }).get().join(",");
			if (fromToggles) return fromToggles;
			return $(".postbox").filter(function(){
				return $(this).css("display") === "none";
			}).map(function(){ return this.id; }).get().join(",");
		}

		$btn.on("click", function(){
			const order = {
				normal: ids($("#normal-sortables")),
				side: ids($("#side-sortables")),
				column3: ids($("#column3-sortables")),
				column4: ids($("#column4-sortables"))
			};
			const hidden = hiddenIds();
			const $form = $("<form>", { method: "post", action: "' . \esc_url(\admin_url('admin-post.php')) . '" });
			$form.append($("<input>", { type: "hidden", name: "action", value: "cmx_save_metabox_layout" }));
			$form.append($("<input>", { type: "hidden", name: "cmx_screen", value: "dashboard" }));
			$form.append($("<input>", { type: "hidden", name: "cmx_save_metabox_layout_nonce", value: $btn.data("nonce") }));
			$form.append($("<input>", { type: "hidden", name: "cmx_mb_order_json", value: JSON.stringify(order) }));
			$form.append($("<input>", { type: "hidden", name: "cmx_mb_hidden", value: hidden }));
			$("body").append($form);
			$form.trigger("submit");
		});
	});
	</script>';
}

/* =========================================================
 * Defaults fuer neue User anwenden (nur wenn noch leer)
 * ========================================================= */
\add_action('load-post.php', __NAMESPACE__ . '\\cmx_apply_metabox_defaults_for_user');
\add_action('load-post-new.php', __NAMESPACE__ . '\\cmx_apply_metabox_defaults_for_user');
function cmx_apply_metabox_defaults_for_user(): void {
	$screen = \get_current_screen();
	if (!$screen || $screen->base !== 'post') return;
	$post_type = $screen->post_type;
	if (!$post_type || !\post_type_exists($post_type)) return;

	$defaults = \get_option('cmx_metabox_default_' . $post_type);
	if (!\is_array($defaults)) return;

	$user_id = \get_current_user_id();
	if (!$user_id) return;

	$order_key = 'meta-box-order_' . $post_type;
	$hidden_key = 'metaboxhidden_' . $post_type;

	if (!empty($defaults['order'])) {
		\update_user_option($user_id, $order_key, $defaults['order'], true);
	}

	if (!empty($defaults['hidden'])) {
		\update_user_option($user_id, $hidden_key, $defaults['hidden'], true);
	}
}

/* =========================================================
 * Defaults fuer Dashboard-Widgets (nur wenn noch leer)
 * ========================================================= */
\add_action('load-index.php', __NAMESPACE__ . '\\cmx_apply_dashboard_defaults_for_user');
function cmx_apply_dashboard_defaults_for_user(): void {
	$defaults = \get_option('cmx_metabox_default_dashboard');
	if (!\is_array($defaults)) return;

	$user_id = \get_current_user_id();
	if (!$user_id) return;

	if (!empty($defaults['order'])) {
		\update_user_option($user_id, 'meta-box-order_dashboard', $defaults['order'], true);
	}

	if (!empty($defaults['hidden'])) {
		\update_user_option($user_id, 'metaboxhidden_dashboard', $defaults['hidden'], true);
	}
}

/* =========================================================
 * Immer Cloud-Meister-Default liefern (Filter)
 * ========================================================= */
function cmx_register_metabox_default_filters(): void {
	if (!\is_admin()) return;
	$post_types = \get_post_types(['show_ui' => true], 'names');
	foreach ($post_types as $pt) {
		\add_filter('get_user_option_meta-box-order_' . $pt, function ($value, $option, $user) use ($pt) {
			$defaults = \get_option('cmx_metabox_default_' . $pt);
			if (\is_array($defaults) && !empty($defaults['order'])) {
				return $defaults['order'];
			}
			return $value;
		}, 10, 3);

		\add_filter('get_user_option_metaboxhidden_' . $pt, function ($value, $option, $user) use ($pt) {
			$defaults = \get_option('cmx_metabox_default_' . $pt);
			if (\is_array($defaults) && !empty($defaults['hidden'])) {
				return $defaults['hidden'];
			}
			return $value;
		}, 10, 3);
	}

	\add_filter('get_user_option_meta-box-order_dashboard', function ($value) {
		$defaults = \get_option('cmx_metabox_default_dashboard');
		if (\is_array($defaults) && !empty($defaults['order'])) return $defaults['order'];
		return $value;
	}, 10, 1);

	\add_filter('get_user_option_metaboxhidden_dashboard', function ($value) {
		$defaults = \get_option('cmx_metabox_default_dashboard');
		if (\is_array($defaults) && !empty($defaults['hidden'])) return $defaults['hidden'];
		return $value;
	}, 10, 1);
}
\add_action('init', __NAMESPACE__ . '\\cmx_register_metabox_default_filters', 1000);

/* =========================================================
 * Admin-Notice nach dem Speichern
 * ========================================================= */
\add_action('admin_notices', function () {
	$screen = \get_current_screen();
	if (!$screen || !in_array($screen->base, ['post', 'dashboard'], true)) return;
	if (empty($_GET['cmx_metabox_saved'])) return;
	echo '<div class="notice notice-success is-dismissible"><p>Metabox-Positionen gespeichert.</p></div>';
});
