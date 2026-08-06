<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_admin_used_meta_ids')) {
	function cmx_buchungen_admin_used_meta_ids(string $meta_key): array {
		$ids = \get_posts([
			'post_type'              => CMX_BUCHUNGEN_CPT,
			'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => $meta_key,
					'value'   => '0',
					'compare' => '>',
					'type'    => 'NUMERIC',
				],
			],
		]);
		$values = [];
		foreach ((array) $ids as $id) {
			$value = (int) \get_post_meta((int) $id, $meta_key, true);
			if ($value > 0) {
				$values[$value] = $value;
			}
		}
		\sort($values, \SORT_NUMERIC);

		return \array_values($values);
	}
}

\add_filter('manage_' . CMX_BUCHUNGEN_CPT . '_posts_columns', function (array $columns): array {
	$new = [];
	foreach ($columns as $key => $label) {
		if ($key === 'title') {
			$label = 'Name';
		}
		$new[$key] = $label;
		if ($key === 'title') {
			$new['cmx_buchung_start'] = 'Start';
			$new['cmx_buchung_kontakt'] = 'Kontakt';
			$new['cmx_buchung_leistung'] = 'Leistung';
			$new['cmx_buchung_status'] = 'Status';
			$new['cmx_buchung_resource'] = 'Ressource';
		}
	}
	unset($new['date']);
	return $new;
});

\add_action('manage_' . CMX_BUCHUNGEN_CPT . '_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'cmx_buchung_start') {
		echo \esc_html(\trim((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_DATE, true) . ' ' . (string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_START_TIME, true)));
		return;
	}
	if ($column === 'cmx_buchung_kontakt') {
		$id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_KONTAKT, true);
		echo $id > 0 ? '<a href="' . \esc_url((string) \get_edit_post_link($id)) . '">' . \esc_html((string) \get_the_title($id)) . '</a>' : '';
		return;
	}
	if ($column === 'cmx_buchung_leistung') {
		$id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_ARTIKEL, true);
		echo $id > 0 ? '<a href="' . \esc_url((string) \get_edit_post_link($id)) . '">' . \esc_html((string) \get_the_title($id)) . '</a>' : '';
		return;
	}
	if ($column === 'cmx_buchung_status') {
		$status = \sanitize_key((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_STATUS, true));
		$options = cmx_buchungen_status_options();
		$status = isset($options[$status]) ? $status : 'angefragt';
		echo '<span class="cmx-buchungen-inline-status" data-post-id="' . (int) $post_id . '">';
		echo '<button type="button" class="button-link cmx-buchungen-inline-status-label" data-status-label>' . \esc_html((string) $options[$status]) . '</button>';
		echo '<select class="cmx-buchungen-inline-status-select" data-status-select hidden>';
		foreach ($options as $value => $label) {
			echo '<option value="' . \esc_attr((string) $value) . '"' . \selected($status, (string) $value, false) . '>' . \esc_html((string) $label) . '</option>';
		}
		echo '</select>';
		echo '<span class="spinner" data-status-spinner></span>';
		echo '</span>';
		return;
	}
	if ($column === 'cmx_buchung_resource') {
		$term_id = (int) \get_post_meta($post_id, CMX_BUCHUNGEN_META_RESSOURCE, true);
		$term = $term_id > 0 ? \get_term($term_id, CMX_BUCHUNGEN_TAX_RESSOURCE) : null;
		if ($term instanceof \WP_Term) {
			echo \esc_html($term->name);
		}
	}
}, 10, 2);

\add_filter('manage_edit-' . CMX_BUCHUNGEN_CPT . '_sortable_columns', function (array $columns): array {
	$columns['cmx_buchung_start'] = 'cmx_buchung_start';
	$columns['cmx_buchung_status'] = 'cmx_buchung_status';
	return $columns;
});

\add_filter('months_dropdown_results', function (array $months, string $post_type): array {
	return $post_type === CMX_BUCHUNGEN_CPT ? [] : $months;
}, 10, 2);

\add_action('restrict_manage_posts', function (string $post_type, string $which = 'top'): void {
	if ($post_type !== CMX_BUCHUNGEN_CPT || $which !== 'top') {
		return;
	}

	$selected_contact = isset($_GET['cmx_buchung_kontakt_id']) ? (int) \wp_unslash($_GET['cmx_buchung_kontakt_id']) : 0;
	$selected_service = isset($_GET['cmx_buchung_leistung_id']) ? (int) \wp_unslash($_GET['cmx_buchung_leistung_id']) : 0;
	$selected_status = isset($_GET['cmx_buchung_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_buchung_status'])) : '';
	$status_options = cmx_buchungen_status_options();
	if ($selected_status !== '' && !isset($status_options[$selected_status])) {
		$selected_status = '';
	}

	echo '<label for="cmx_buchung_kontakt_id" class="screen-reader-text">' . \esc_html__('Nach Kontakt filtern', 'cmx-misbuero') . '</label>';
	echo '<select name="cmx_buchung_kontakt_id" id="cmx_buchung_kontakt_id">';
	echo '<option value="0">' . \esc_html__('Alle Kontakte', 'cmx-misbuero') . '</option>';
	foreach (cmx_buchungen_admin_used_meta_ids(CMX_BUCHUNGEN_META_KONTAKT) as $contact_id) {
		$title = \trim((string) \get_the_title($contact_id));
		if ($title === '') {
			$title = '#' . $contact_id;
		}
		echo '<option value="' . (int) $contact_id . '"' . \selected($selected_contact, $contact_id, false) . '>' . \esc_html($title) . '</option>';
	}
	echo '</select>';

	echo '<label for="cmx_buchung_leistung_id" class="screen-reader-text">' . \esc_html__('Nach Leistung filtern', 'cmx-misbuero') . '</label>';
	echo '<select name="cmx_buchung_leistung_id" id="cmx_buchung_leistung_id">';
	echo '<option value="0">' . \esc_html__('Alle Leistungen', 'cmx-misbuero') . '</option>';
	foreach (cmx_buchungen_admin_used_meta_ids(CMX_BUCHUNGEN_META_ARTIKEL) as $service_id) {
		$title = \trim((string) \get_the_title($service_id));
		if ($title === '') {
			$title = '#' . $service_id;
		}
		echo '<option value="' . (int) $service_id . '"' . \selected($selected_service, $service_id, false) . '>' . \esc_html($title) . '</option>';
	}
	echo '</select>';

	echo '<label for="cmx_buchung_status" class="screen-reader-text">' . \esc_html__('Nach Status filtern', 'cmx-misbuero') . '</label>';
	echo '<select name="cmx_buchung_status" id="cmx_buchung_status">';
	echo '<option value="">' . \esc_html__('Alle Status', 'cmx-misbuero') . '</option>';
	foreach ($status_options as $value => $label) {
		echo '<option value="' . \esc_attr((string) $value) . '"' . \selected($selected_status, (string) $value, false) . '>' . \esc_html((string) $label) . '</option>';
	}
	echo '</select>';
}, 10, 2);

\add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!\is_admin() || !$query->is_main_query() || (string) $query->get('post_type') !== CMX_BUCHUNGEN_CPT) {
		return;
	}
	$contact_id = isset($_GET['cmx_buchung_kontakt_id']) ? (int) \wp_unslash($_GET['cmx_buchung_kontakt_id']) : 0;
	$service_id = isset($_GET['cmx_buchung_leistung_id']) ? (int) \wp_unslash($_GET['cmx_buchung_leistung_id']) : 0;
	$selected_status = isset($_GET['cmx_buchung_status']) ? \sanitize_key((string) \wp_unslash($_GET['cmx_buchung_status'])) : '';
	if ($selected_status !== '' && !isset(cmx_buchungen_status_options()[$selected_status])) {
		$selected_status = '';
	}

	$raw_meta_query = $query->get('meta_query');
	$meta_query = \is_array($raw_meta_query) ? $raw_meta_query : [];
	if (($contact_id > 0 || $service_id > 0 || $selected_status !== '') && !isset($meta_query['relation'])) {
		$meta_query['relation'] = 'AND';
	}

	if ($contact_id > 0) {
		$meta_query[] = [
			'key'     => CMX_BUCHUNGEN_META_KONTAKT,
			'value'   => $contact_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
	}
	if ($service_id > 0) {
		$meta_query[] = [
			'key'     => CMX_BUCHUNGEN_META_ARTIKEL,
			'value'   => $service_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		];
	}
	if ($selected_status !== '') {
		$meta_query[] = [
			'key'     => CMX_BUCHUNGEN_META_STATUS,
			'value'   => $selected_status,
			'compare' => '=',
		];
	}
	if ($meta_query !== []) {
		$query->set('meta_query', $meta_query);
	}

	$orderby = (string) $query->get('orderby');
	if ($orderby === 'cmx_buchung_start') {
		$query->set('meta_key', CMX_BUCHUNGEN_META_START_DATE);
		$query->set('orderby', 'meta_value');
	}
	if ($orderby === 'cmx_buchung_status') {
		$query->set('meta_key', CMX_BUCHUNGEN_META_STATUS);
		$query->set('orderby', 'meta_value');
	}
});

\add_action('wp_ajax_cmx_buchungen_inline_status', function (): void {
	$post_id = isset($_POST['post_id']) ? (int) \wp_unslash($_POST['post_id']) : 0;
	$status = isset($_POST['status']) ? \sanitize_key((string) \wp_unslash($_POST['status'])) : '';
	$options = cmx_buchungen_status_options();

	if (!\check_ajax_referer('cmx_buchungen_inline_status', 'nonce', false)) {
		\wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen.'], 403);
	}
	if ($post_id <= 0 || (string) \get_post_type($post_id) !== CMX_BUCHUNGEN_CPT || !\current_user_can('edit_post', $post_id)) {
		\wp_send_json_error(['message' => 'Keine Berechtigung.'], 403);
	}
	if (!isset($options[$status])) {
		\wp_send_json_error(['message' => 'Ungültiger Status.'], 400);
	}

	$previous_status = \sanitize_key((string) \get_post_meta($post_id, CMX_BUCHUNGEN_META_STATUS, true));
	\update_post_meta($post_id, CMX_BUCHUNGEN_META_STATUS, $status);

	if (\function_exists(__NAMESPACE__ . '\\cmx_buchungen_sync_title')) {
		cmx_buchungen_sync_title($post_id);
	}
	if (\function_exists(__NAMESPACE__ . '\\cmx_buchungen_schedule_reminder')) {
		cmx_buchungen_schedule_reminder($post_id);
	}
	if ($previous_status !== 'bestaetigt' && $status === 'bestaetigt' && \function_exists(__NAMESPACE__ . '\\cmx_buchungen_maybe_send_confirmation')) {
		cmx_buchungen_maybe_send_confirmation($post_id);
	}

	\wp_send_json_success([
		'status' => $status,
		'label'  => (string) $options[$status],
	]);
});

\add_action('admin_footer-edit.php', function (): void {
	$screen = \get_current_screen();
	if (!$screen || (string) $screen->post_type !== CMX_BUCHUNGEN_CPT) {
		return;
	}

	$nonce = \wp_create_nonce('cmx_buchungen_inline_status');
	?>
	<style>
		.column-cmx_buchung_status .cmx-buchungen-inline-status{display:inline-flex;align-items:center;gap:6px;min-height:30px}
		.column-cmx_buchung_status .cmx-buchungen-inline-status-label{color:#1d2327;text-decoration:none}
		.column-cmx_buchung_status .cmx-buchungen-inline-status-label:hover{color:#b32d2e;text-decoration:underline}
		.column-cmx_buchung_status .cmx-buchungen-inline-status-select{max-width:130px}
		.column-cmx_buchung_status .spinner{float:none;margin:0;visibility:hidden}
		.column-cmx_buchung_status .cmx-buchungen-inline-status.is-saving .spinner{visibility:visible}
	</style>
	<script>
	(function(){
		const nonce = <?php echo \wp_json_encode($nonce); ?>;
		const labels = Array.from(document.querySelectorAll("[data-status-label]"));
		function hideSelect(wrap){
			const button = wrap.querySelector("[data-status-label]");
			const select = wrap.querySelector("[data-status-select]");
			if(!button || !select) return;
			select.hidden = true;
			button.hidden = false;
		}
		function showSelect(wrap){
			const button = wrap.querySelector("[data-status-label]");
			const select = wrap.querySelector("[data-status-select]");
			if(!button || !select) return;
			button.hidden = true;
			select.hidden = false;
			select.focus();
		}
		function saveStatus(wrap, status){
			const button = wrap.querySelector("[data-status-label]");
			const select = wrap.querySelector("[data-status-select]");
			if(!button || !select || !wrap.dataset.postId) return;
			const previous = select.dataset.current || select.value;
			wrap.classList.add("is-saving");
			select.disabled = true;
			window.fetch(window.ajaxurl, {
				method: "POST",
				credentials: "same-origin",
				headers: {"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"},
				body: new URLSearchParams({
					action: "cmx_buchungen_inline_status",
					nonce: nonce,
					post_id: wrap.dataset.postId,
					status: status
				})
			}).then(function(response){
				return response.json();
			}).then(function(payload){
				if(!payload || !payload.success){
					throw new Error(payload && payload.data && payload.data.message ? payload.data.message : "Status konnte nicht gespeichert werden.");
				}
				select.dataset.current = payload.data.status;
				select.value = payload.data.status;
				button.textContent = payload.data.label;
				hideSelect(wrap);
			}).catch(function(error){
				select.value = previous;
				window.alert(error && error.message ? error.message : "Status konnte nicht gespeichert werden.");
				hideSelect(wrap);
			}).finally(function(){
				select.disabled = false;
				wrap.classList.remove("is-saving");
			});
		}
		labels.forEach(function(button){
			const wrap = button.closest("[data-post-id]");
			if(!wrap) return;
			const select = wrap.querySelector("[data-status-select]");
			if(select) select.dataset.current = select.value;
			button.addEventListener("click", function(event){
				event.preventDefault();
				showSelect(wrap);
			});
			if(select){
				select.addEventListener("change", function(){
					if(select.value === select.dataset.current){
						hideSelect(wrap);
						return;
					}
					saveStatus(wrap, select.value);
				});
				select.addEventListener("keydown", function(event){
					if(event.key === "Escape"){
						select.value = select.dataset.current || select.value;
						hideSelect(wrap);
					}
				});
				select.addEventListener("blur", function(){
					window.setTimeout(function(){
						if(!wrap.classList.contains("is-saving")){
							select.value = select.dataset.current || select.value;
							hideSelect(wrap);
						}
					}, 150);
				});
			}
		});
	})();
	</script>
	<?php
});
