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
	echo '<style>.button-full { width:100%; display:block; box-sizing:border-box; text-align:center; } </style>';
});


/**
 * Minimaler „Speichern“-Button statt „Veröffentlichen“-Metabox (Classic Editor)
 * mit sichtbarem „In den Papierkorb verschieben“-Link nach dem Speichern.
 */
add_action('add_meta_boxes', function() {
	$allowed = ['post', 'page', 'kontakte','artikel','belege','kassenbuch','dokumente','projekte','ausgaben','scanner','carent','budget'];
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
						$download_url = esc_url(add_query_arg('beleg', $token, home_url('/')));
					}
				}
			}
			if ($is_kontakte && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kontakt_belege_share_url')) {
				$kontakt_belege_url = (string) cmx_kontakt_belege_share_url((int) $post->ID);
			}
			if ($is_kontakte && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_telefonbuch_detail_url')) {
				$kontakt_telefonbuch_url = (string) cmx_telefonbuch_detail_url((int) $post->ID);
			}
				$new_beleg_url = ($is_kontakte && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_kontakt_create_beleg_action_url'))
					? (string) cmx_kontakt_create_beleg_action_url((int) $post->ID)
					: '';
				$new_beleg_from_artikel_url = ($is_artikel && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_artikel_create_beleg_action_url'))
					? (string) cmx_artikel_create_beleg_action_url((int) $post->ID)
					: '';
				$artikel_katalog_icon_html = ($is_artikel && (int) $post->ID > 0 && \function_exists(__NAMESPACE__ . '\\cmx_artikel_katalog_icon_html'))
					? (string) cmx_artikel_katalog_icon_html((int) $post->ID)
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
			echo '<div style="display:flex; align-items:center; gap:8px;">';
			printf(
				'<input type="submit" name="%1$s" id="publish" class="button button-primary button-large button-full" value="%2$s" />',
				esc_attr($btn_name),
				esc_attr($btn_label)
			);
			if ($is_belege && $beleg_send_eingang_url !== '') {
				echo '<a href="' . esc_url($beleg_send_eingang_url) . '" title="' . esc_attr__('Beleg an Muh-Instanz senden', 'default') . '" class="button button-secondary" style="height:36px; display:inline-flex; align-items:center; justify-content:center;"><span class="dashicons dashicons-carrot" style="margin-top:2px;"></span></a>';
			}
			if ($is_belege && $send_href !== '') {
				echo '<a href="'.$send_href.'" title="' . esc_attr($send_tooltip) . '" class="button button-secondary" style="height:36px; display:inline-flex; align-items:center; justify-content:center;"><span class="dashicons dashicons-email" style="margin-top:2px;"></span></a>';
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
					} elseif ($to_id > 0) {
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
					if ($delete_link || $dup_link !== '' || $new_beleg_url !== '' || $new_beleg_from_artikel_url !== '' || $artikel_katalog_icon_html !== '' || $show_pdf_icons || $kontakt_belege_url !== '' || $kontakt_telefonbuch_url !== '') {
						$justify = $is_belege ? 'space-between' : 'flex-start';
							echo '<div style="margin-top:8px; padding-top:0; display:flex; justify-content:'.$justify.'; align-items:center; gap:8px;">';
						if ($dup_link !== '') {
							echo '<a href="'.esc_url($dup_link).'" class="cmx-dup-link dashicons dashicons-clipboard" style="text-decoration:none;" title="'.esc_attr__('Duplizieren','default').'"><span class="screen-reader-text">'.esc_html__('Duplizieren','default').'</span></a>';
						}
						if ($new_beleg_from_artikel_url !== '') {
							echo '<a href="' . esc_url($new_beleg_from_artikel_url) . '" class="cmx-artikel-new-beleg-link dashicons dashicons-media-text" style="text-decoration:none;color:#d63638;" title="Neuen Beleg mit diesem Artikel anlegen"><span class="screen-reader-text">Neuen Beleg mit diesem Artikel anlegen</span></a>';
						}
						if ($new_beleg_url !== '') {
							echo '<a href="' . esc_url($new_beleg_url) . '" class="cmx-kontakt-new-beleg-link dashicons dashicons-media-text" style="text-decoration:none;color:#d63638;" title="Neuen Beleg anlegen"><span class="screen-reader-text">Neuen Beleg anlegen</span></a>';
						}
						if ($artikel_katalog_icon_html !== '') {
							echo '<span style="display:inline-flex;margin-left:15px;">' . $artikel_katalog_icon_html . '</span>';
						}
					if ($kontakt_belege_url !== '') {
						echo '<a href="' . esc_url($kontakt_belege_url) . '" class="cmx-kontakt-belege-link dashicons dashicons-portfolio" style="text-decoration:none;" title="Alle Belege dieses Kontakts anzeigen" target="_blank" rel="noopener noreferrer" data-copy-url="' . esc_attr($kontakt_belege_url) . '"><span class="screen-reader-text">Belege dieses Kontakts anzeigen</span></a>';
					}
					if ($kontakt_telefonbuch_url !== '') {
						$kontakt_telefonbuch_icon = \function_exists(__NAMESPACE__ . '\\cmx_book_user_icon_svg') ? cmx_book_user_icon_svg('cmx-book-user-icon', 19) : '';
						echo '<a href="' . esc_url($kontakt_telefonbuch_url) . '" class="cmx-kontakt-telefonbuch-link" style="display:inline-block;width:19px;height:19px;text-decoration:none;color:#b45309; position:relative; left:15px;" title="Telefonbuch-Detailansicht öffnen" target="_blank" rel="noopener noreferrer">' . $kontakt_telefonbuch_icon . '<span class="screen-reader-text">Telefonbuch-Detailansicht öffnen</span></a>';
					}
						if ($show_pdf_icons) {
							echo '<a href="' . esc_url($download_url) . '" class="cmx-pdf-link" style="text-decoration:none;" title="Anzeigen als PDF (DL/C5/C4)" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-pdf" style="margin-top:5px;"></span></a>';
						}
					if ($delete_link) {
						$delete_style = $is_belege
							? 'color:#b32d2e; text-decoration:none;'
							: 'color:#b32d2e; text-decoration:none; margin-left:auto;';
						echo '<a href="'.esc_url($delete_link).'" class="submitdelete deletion dashicons dashicons-trash" style="'.$delete_style.'" title="'.esc_attr__('In den Papierkorb verschieben', 'default').'"><span class="screen-reader-text">'.esc_html__('In den Papierkorb verschieben', 'default').'</span></a>';
					}
					echo '</div>';
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
	$allowed = ['post', 'page', 'kontakte','artikel','belege','kassenbuch','dokumente','projekte','ausgaben','scanner','carent','budget'];
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
	$allowed = ['post', 'page', 'kontakte', 'belege', 'scanner', 'carent', 'budget'];
	if (!in_array($post_type, $allowed, true)) return;

	remove_meta_box('submitdiv', $post_type, 'side');
	remove_meta_box('submitdiv', $post_type, 'normal');
	remove_meta_box('submitdiv', $post_type, 'advanced');
	remove_meta_box('slugdiv', $post_type, 'normal');
}, 100);
