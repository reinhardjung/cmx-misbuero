<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

require_once \dirname(__DIR__) . '/belege/woocommerce.php';

/**
 * WooCommerce-Tab innerhalb der Mis-Buero-Einstellungen.
 *
 * Die Datei verwaltet nur vorbereitende Einstellungen fuer eine externe
 * WooCommerce-Quelle. Lokales WooCommerce ist auf der Instanz nicht noetig und
 * wird hier bewusst nicht vorausgesetzt.
 */
\add_action('admin_init', __NAMESPACE__ . '\\cmx_register_woocommerce_tab');
function cmx_register_woocommerce_tab(): void {
	$page = 'cmx_tab_woocommerce';

	\add_settings_section(
		'cmx_sec_woocommerce_connection',
		__('WooCommerce', 'cmx-misbuero'),
		static function (): void {
			echo '<p class="description">';
			echo \esc_html__(
				'Einstellungen für die externe WooCommerce-Anbindung (also auf der Website mit Deinem WooCommerce Online Shop)',
				'cmx-misbuero'
			);


			// fixme rju 2026-03-16: Überall verbessern
echo '<p>';
echo '<code>' . esc_html__( 'WP-Admin → WooCommerce → Einstellungen → Erweitert → Webhooks', 'cmx-misbuero' ) . '</code>';
echo '</p>';

// fixme rju 2026-03-16: Überall verbessern
// echo '</p>';
// 			echo '<p class="description">';
// 			echo \esc_html__(
// 				'<code>WP-Admin → WooCommerce → Einstellungen → Webhooks</code>',
// 				'cmx-misbuero'
// 			);
// 			echo '</p>';

			},
		$page
	);

	\add_settings_field(
		'cmx_woocommerce_webhook_secret',
		__('Secret Key', 'cmx-misbuero'),
		static function (): void {
			$value = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_ensure_webhook_secret')
				? (string) cmx_woocommerce_ensure_webhook_secret()
				: (string) cmx_woocommerce_get_setting('misbuero_webhook_secret', '');
			$field_name = CMX_SETTINGS_MAIN . '[misbuero_webhook_secret]';
			$rotate_name = CMX_SETTINGS_MAIN . '[misbuero_rotate_webhook_secret]';
			$copy_label = __('Secret Key kopieren', 'cmx-misbuero');
			$copied_label = __('Kopiert', 'cmx-misbuero');
			$error_label = __('Fehler', 'cmx-misbuero');
			$rotate_label = __('Secret neu erzeugen', 'cmx-misbuero');
			$rotate_title = __('Secret Key erneuern', 'cmx-misbuero');
			$rotate_ok = __('Neu erzeugen', 'cmx-misbuero');
			$rotate_cancel = __('Abbrechen', 'cmx-misbuero');
			$rotate_confirm = __('Jetzt einen neuen Secret Key erzeugen? Der bisherige Key bleibt danach noch 15 Minuten gültig.', 'cmx-misbuero');
			$copy_onclick = '(function(btn){var input=btn.parentNode.querySelector("input[name=\"' . \esc_js($field_name) . '\"]");var status=btn.parentNode.querySelector("[data-copy-feedback]");var resetTitle=function(){btn.title=' . \wp_json_encode($copy_label) . ';};var resetStatus=function(){if(status){status.textContent="";status.style.display="none";}};try{var value=input?input.value:"";if(window.navigator&&navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(value);}else{var ta=document.createElement("textarea");ta.value=value;document.body.appendChild(ta);ta.select();document.execCommand("copy");document.body.removeChild(ta);}btn.title=' . \wp_json_encode($copied_label) . ';if(status){status.textContent=' . \wp_json_encode($copied_label) . ';status.style.display="inline";}setTimeout(function(){resetTitle();resetStatus();},1200);}catch(e){if(status){status.textContent=' . \wp_json_encode($error_label) . ';status.style.display="inline";}setTimeout(resetStatus,1200);resetTitle();}})(this);return false;';
			$previous_until = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_previous_secret_expires_at')
				? (int) cmx_woocommerce_webhook_previous_secret_expires_at()
				: 0;
			echo '<div style="display:flex;align-items:center;gap:8px;max-width:100%;flex-wrap:nowrap;">';
			echo '<button type="button" class="button button-secondary" aria-label="' . \esc_attr($copy_label) . '" title="' . \esc_attr($copy_label) . '" onclick="' . \esc_attr($copy_onclick) . '"><span class="dashicons dashicons-admin-page" aria-hidden="true" style="margin-top:3px;"></span></button>';
			echo '<span data-copy-feedback="1" aria-live="polite" style="display:none;font-size:12px;color:#2271b1;white-space:nowrap;"></span>';
			echo '<div class="code" style="flex:1 1 auto;min-width:0;padding:0 8px;overflow:hidden;">';
			echo '<input type="text" style="width:100%;min-width:0;border:0;background:transparent;box-shadow:none;padding:6px 0;font-family:monospace;overflow:hidden;text-overflow:ellipsis;" name="' . \esc_attr($field_name) . '" value="' . \esc_attr($value) . '" autocomplete="off">';
			echo '</div>';
			echo '<input type="hidden" name="' . \esc_attr($rotate_name) . '" value="0" data-cmx-woo-rotate-secret-input="1">';
			echo '<button type="button" class="button button-secondary" data-cmx-woo-rotate-secret-button="1" data-confirm-title="' . \esc_attr($rotate_title) . '" data-confirm-message="' . \esc_attr($rotate_confirm) . '" data-confirm-ok="' . \esc_attr($rotate_ok) . '" data-confirm-cancel="' . \esc_attr($rotate_cancel) . '">' . \esc_html($rotate_label) . '</button>';
			echo '</div>';
			echo '<p class="description">' . \esc_html__('Muss mit dem Secret des externen WooCommerce-Webhooks übereinstimmen.', 'cmx-misbuero') . '</p>';
			if ($previous_until > 0) {
				// echo '<p class="description" style="color:#b32d2e;">' . \esc_html(\sprintf(__('Der vorherige Secret Key bleibt noch bis %s gültig. Danach wird im externen WooCommerce nur noch der neue (dort dann dieser neu eingetragener) Key verwendet.', 'cmx-misbuero'), \wp_date('d.m.Y H:i', $previous_until))) . '</p>';
			}
			static $dialog_script_printed = false;
			if (!$dialog_script_printed) {
				$dialog_script_printed = true;
				echo '<script>';
				echo '(function(){';
				echo 'if(window.cmxWooSecretRotateDialogBound){return;}window.cmxWooSecretRotateDialogBound=true;';
				echo 'function openDialog(options){return new Promise(function(resolve){var overlay=document.createElement("div");overlay.style.position="fixed";overlay.style.inset="0";overlay.style.background="rgba(15,23,42,0.35)";overlay.style.zIndex="100000";overlay.style.display="flex";overlay.style.alignItems="center";overlay.style.justifyContent="center";overlay.style.padding="16px";var dialog=document.createElement("div");dialog.style.background="#fff";dialog.style.border="1px solid #c3c4c7";dialog.style.borderRadius="10px";dialog.style.boxShadow="0 18px 40px rgba(15,23,42,0.22)";dialog.style.width="100%";dialog.style.maxWidth="460px";dialog.style.padding="20px 22px";dialog.style.boxSizing="border-box";dialog.setAttribute("role","dialog");dialog.setAttribute("aria-modal","true");var title=document.createElement("div");title.style.fontSize="16px";title.style.fontWeight="600";title.style.marginBottom="10px";title.textContent=options.title||"";var text=document.createElement("div");text.style.fontSize="14px";text.style.lineHeight="1.55";text.style.color="#1d2327";text.textContent=options.message||"";var note=document.createElement("div");note.style.marginTop="12px";note.style.padding="10px 12px";note.style.background="#f6f7f7";note.style.borderRadius="8px";note.style.fontSize="12px";note.style.lineHeight="1.5";note.style.color="#50575e";note.textContent=' . \wp_json_encode(__('Du musst den neuen Key danach auch im externen WooCommerce-WebHook aktualisieren.', 'cmx-misbuero')) . ';var buttons=document.createElement("div");buttons.style.display="flex";buttons.style.justifyContent="flex-end";buttons.style.gap="8px";buttons.style.marginTop="18px";var cancelBtn=document.createElement("button");cancelBtn.type="button";cancelBtn.className="button button-secondary";cancelBtn.textContent=options.cancelLabel||"Abbrechen";var okBtn=document.createElement("button");okBtn.type="button";okBtn.className="button button-primary";okBtn.textContent=options.okLabel||"OK";function close(result){document.removeEventListener("keydown",onKeyDown,true);overlay.remove();resolve(result);}function onKeyDown(event){if(event.key==="Escape"){event.preventDefault();close(false);}}cancelBtn.addEventListener("click",function(){close(false);});okBtn.addEventListener("click",function(){close(true);});overlay.addEventListener("click",function(event){if(event.target===overlay){close(false);}});document.addEventListener("keydown",onKeyDown,true);buttons.appendChild(cancelBtn);buttons.appendChild(okBtn);dialog.appendChild(title);dialog.appendChild(text);dialog.appendChild(note);dialog.appendChild(buttons);overlay.appendChild(dialog);document.body.appendChild(overlay);okBtn.focus();});}';
				echo 'document.addEventListener("click",function(event){var button=event.target&&event.target.closest?event.target.closest("[data-cmx-woo-rotate-secret-button=\\"1\\"]"):null;if(!button){return;}event.preventDefault();var form=button.closest("form");var hidden=form?form.querySelector("[data-cmx-woo-rotate-secret-input=\\"1\\"]"):null;openDialog({title:button.getAttribute("data-confirm-title")||"",message:button.getAttribute("data-confirm-message")||"",okLabel:button.getAttribute("data-confirm-ok")||"",cancelLabel:button.getAttribute("data-confirm-cancel")||""}).then(function(confirmed){if(!confirmed||!form||!hidden){return;}hidden.value="1";button.disabled=true;if(form.requestSubmit){form.requestSubmit();return;}form.submit();});});';
				echo '})();';
				echo '</script>';
			}
		},
		$page,
		'cmx_sec_woocommerce_connection'
	);

	\add_settings_field(
		'cmx_woocommerce_webhook_url',
		__('Webhook URL', 'cmx-misbuero'),
		static function (): void {
			$url = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_delivery_url')
				? (string) cmx_woocommerce_webhook_delivery_url()
				: (\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_webhook_url')
					? (string) cmx_woocommerce_webhook_url()
					: '')
			;
			echo '<div style="display:flex;align-items:center;gap:8px;max-width:100%;flex-wrap:nowrap;">';
			echo '<button type="button" class="button button-secondary" aria-label="' . \esc_attr__('Webhook URL kopieren', 'cmx-misbuero') . '" title="' . \esc_attr__('Webhook URL kopieren', 'cmx-misbuero') . '" onclick=\'(function(btn,url){var status=btn.parentNode.querySelector("[data-copy-feedback]");var resetTitle=function(){btn.title="Webhook URL kopieren";};var resetStatus=function(){if(status){status.textContent="";status.style.display="none";}};try{if(window.navigator&&navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(url);}else{var ta=document.createElement("textarea");ta.value=url;document.body.appendChild(ta);ta.select();document.execCommand("copy");document.body.removeChild(ta);}btn.title="Kopiert";if(status){status.textContent="Kopiert";status.style.display="inline";}setTimeout(function(){resetTitle();resetStatus();},1200);}catch(e){if(status){status.textContent="Fehler";status.style.display="inline";}setTimeout(resetStatus,1200);resetTitle();}})(this,' . \wp_json_encode($url) . ');return false;\'><span class="dashicons dashicons-admin-page" aria-hidden="true" style="margin-top:3px;"></span></button>';
			echo '<span data-copy-feedback="1" aria-live="polite" style="display:none;font-size:12px;color:#2271b1;white-space:nowrap;"></span>';
			echo '<div class="code" style="flex:1 1 auto;min-width:0;padding:6px 0;overflow-x:auto;white-space:nowrap;">' . \esc_html($url) . '</div>';
			echo '</div>';
			echo '<p class="description">';
			echo wp_kses_post(__('Diese URL im externen WooCommerce-WebHook als <strong>Auslieferungs-URL (Delivery URL)</strong> eintragen.<br/>Thema: <strong>Bestellung erstellt (Order Created)</strong>.<br/>Die URL enthält bereits einen Kompatibilitäts-Token für Hosts, die WooCommerce-Header entfernen.', 'cmx-misbuero'));
			echo '</p>';

			// echo '<p class="description">' . \esc_html__('Diese URL im externen WooCommerce-WebHook als <strong>Auslieferungs-URL (Delivery URL)</strong> eintragen. Topic: Order Created.', 'cmx-misbuero') . '</p>';
		},
		$page,
		'cmx_sec_woocommerce_connection'
	);

	\add_settings_field(
		'cmx_woocommerce_order_example_url',
		__('Beispiel-URL einer Bestellung', 'cmx-misbuero'),
		static function (): void {
			$value = (string) cmx_woocommerce_get_setting('misbuero_order_example_url', '');
			$field_name = CMX_SETTINGS_MAIN . '[misbuero_order_example_url]';
			$link_data = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_order_link_data_from_example_url')
				? (array) cmx_woocommerce_order_link_data_from_example_url($value)
				: ['recognized' => false, 'mode' => '', 'template' => ''];
			$recognized = !empty($link_data['recognized']);
			$mode_label = \function_exists(__NAMESPACE__ . '\\cmx_woocommerce_order_link_mode_label')
				? (string) cmx_woocommerce_order_link_mode_label((string) ($link_data['mode'] ?? ''))
				: '';
			$template = (string) ($link_data['template'] ?? '');
			echo '<input type="text" class="regular-text code" style="width:100%;max-width:860px;" name="' . \esc_attr($field_name) . '" value="' . \esc_attr($value) . '" autocomplete="off" spellcheck="false">';
			echo '<p class="description">';
			echo \esc_html__('Öffne im externen WooCommerce-Shop irgendeine Bestellung im Backend und kopiere die komplette URL aus dem Browser hier hinein.', 'cmx-misbuero');
			echo '<br>';
			echo \esc_html__('Mis Büro erkennt daraus automatisch HPOS oder Classic und verlinkt danach die Bestellnummer in den internen Notizen.', 'cmx-misbuero');
			echo '</p>';
			if ($recognized && $mode_label !== '' && $template !== '') {
				echo '<p class="description" style="color:#2271b1;">';
				echo '<strong>' . \esc_html__('Erkannt:', 'cmx-misbuero') . '</strong> ' . \esc_html($mode_label);
				echo '<br>';
				echo '<strong>' . \esc_html__('Verwendeter Bestell-Link:', 'cmx-misbuero') . '</strong> ';
				echo '<code>' . \esc_html($template) . '</code>';
				echo '</p>';
			} elseif ($value !== '') {
				echo '<p class="description" style="color:#b32d2e;">';
				echo \esc_html__('Die URL wurde gespeichert, konnte aber nicht als WooCommerce-Bestell-URL erkannt werden. Bitte direkt eine geöffnete Bestellung aus dem Shop-Backend kopieren.', 'cmx-misbuero');
				echo '<br><code>' . \esc_html('Classic: https://shop.example.com/wp-admin/post.php?post=4161&action=edit') . '</code>';
				echo '<br><code>' . \esc_html('HPOS: https://shop.example.com/wp-admin/admin.php?page=wc-orders&action=edit&id=4161') . '</code>';
				echo '</p>';
			}
		},
		$page,
		'cmx_sec_woocommerce_connection'
	);

	\add_settings_field(
		'cmx_woocommerce_auto_mail',
		__('Automatischer Mailversand', 'cmx-misbuero'),
		static function (): void {
			$checked = cmx_woocommerce_get_setting('misbuero_auto_mail', '0') === '1';
			echo '<input type="hidden" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[misbuero_auto_mail]" value="0">';
			echo '<label>';
			echo '<input type="checkbox" name="' . \esc_attr(CMX_SETTINGS_MAIN) . '[misbuero_auto_mail]" value="1" ' . \checked($checked, true, false) . '> ';
			// echo \esc_html__('Rechnung autom. per E-Mail versenden <strong>(sobald die Bestellung von Deinem Woo Shop hier eingetroffen ist)</strong>', 'cmx-misbuero');
			printf( wp_kses_post(__('Rechnung autom. per E-Mail versenden <br/><i>%s</i>', 'cmx-misbuero')), __('(sobald die Bestellung von Deinem Woo Shop hier eingetroffen ist)', 'cmx-misbuero') );
			echo '</label>';
		},
		$page,
		'cmx_sec_woocommerce_connection'
	);
}

\add_action('all_admin_notices', __NAMESPACE__ . '\\cmx_render_woocommerce_webhook_notice');
function cmx_render_woocommerce_webhook_notice(): void {
	if (!\defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')) {
		return;
	}

	$page = isset($_GET['page']) ? \sanitize_key((string) \wp_unslash($_GET['page'])) : '';
	$tab = isset($_GET['tab']) ? \sanitize_key((string) \wp_unslash($_GET['tab'])) : '';
	if ($page !== CMX_SETTINGS_SLUG || $tab !== 'woocommerce') {
		return;
	}

	if (!\function_exists(__NAMESPACE__ . '\\cmx_woocommerce_get_webhook_notice')) {
		return;
	}

	$notice = (array) cmx_woocommerce_get_webhook_notice();
	if ($notice === []) {
		return;
	}

	$captured_at = \absint($notice['captured_at'] ?? 0);
	$code = \sanitize_text_field((string) ($notice['code'] ?? ''));
	$message = \sanitize_text_field((string) ($notice['message'] ?? ''));
	$hint = \sanitize_text_field((string) ($notice['hint'] ?? ''));
	$http_status = \absint($notice['http_status'] ?? 0);
	$debug = \is_array($notice['debug'] ?? null) ? $notice['debug'] : [];

	$debug_parts = [];
	$topic = \sanitize_text_field((string) ($debug['topic'] ?? ''));
	if ($topic !== '') {
		$debug_parts[] = 'Topic: ' . $topic;
	}
	$signature_present = !empty($debug['signature_present']);
	$debug_parts[] = 'Signature-Header: ' . ($signature_present ? 'vorhanden' : 'fehlt');
	$body_length = \absint($debug['body_length'] ?? 0);
	$debug_parts[] = 'Body: ' . $body_length . ' Bytes';
	$token_present = !empty($debug['token_present']);
	$debug_parts[] = 'URL-Token: ' . ($token_present ? 'vorhanden' : 'fehlt');
	$auth_method = \sanitize_text_field((string) ($debug['auth_method'] ?? ''));
	if ($auth_method !== '') {
		$debug_parts[] = 'Auth: ' . $auth_method;
	}
	$delivery_id = \sanitize_text_field((string) ($debug['delivery_id'] ?? ''));
	if ($delivery_id !== '') {
		$debug_parts[] = 'Delivery-ID: ' . $delivery_id;
	}
	$webhook_id = \sanitize_text_field((string) ($debug['webhook_id'] ?? ''));
	if ($webhook_id !== '') {
		$debug_parts[] = 'Webhook-ID: ' . $webhook_id;
	}
	$header_names = \sanitize_text_field((string) ($debug['header_names'] ?? ''));
	if ($header_names !== '') {
		$debug_parts[] = 'Header: ' . $header_names;
	}

	echo '<div class="notice notice-error">';
	echo '<p><strong>' . \esc_html__('Letzter WooCommerce-WebHook-Fehler', 'cmx-misbuero') . '</strong>';
	if ($captured_at > 0) {
		echo ' <span style="color:#646970;">(' . \esc_html(\wp_date('d.m.Y H:i:s', $captured_at)) . ')</span>';
	}
	echo '</p>';
	echo '<p>' . \esc_html($message !== '' ? $message : $code) . '</p>';
	if ($code !== '' || $http_status > 0) {
		$code_line = [];
		if ($http_status > 0) {
			$code_line[] = 'HTTP ' . $http_status;
		}
		if ($code !== '') {
			$code_line[] = 'Code: ' . $code;
		}
		echo '<p><code>' . \esc_html(\implode(' | ', $code_line)) . '</code></p>';
	}
	if ($debug_parts !== []) {
		echo '<p><code>' . \esc_html(\implode(' | ', $debug_parts)) . '</code></p>';
	}
	if ($hint !== '') {
		echo '<p>' . \esc_html__('Hinweis:', 'cmx-misbuero') . ' ' . \esc_html($hint) . '</p>';
	}
	echo '</div>';
}

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, __NAMESPACE__ . '\\cmx_sanitize_woocommerce_settings', 20, 2);
function cmx_sanitize_woocommerce_settings($new_value, $old_value) {
	if (!\is_array($new_value)) {
		return $new_value;
	}

	$rotate_requested = $new_value['misbuero_rotate_webhook_secret'] ?? '0';

	unset(
		$new_value['misbuero_api_key'],
		$new_value['misbuero_rotate_webhook_secret'],
		$new_value['misbuero_mail_sender_name'],
		$new_value['misbuero_mail_sender_email']
	);

	$old_value = \is_array($old_value) ? $old_value : [];
	$settings = [
		'misbuero_webhook_secret' => $new_value['misbuero_webhook_secret'] ?? ($old_value['misbuero_webhook_secret'] ?? ''),
		'misbuero_rotate_webhook_secret' => $rotate_requested,
		'misbuero_order_example_url' => $new_value['misbuero_order_example_url'] ?? ($old_value['misbuero_order_example_url'] ?? ''),
		'misbuero_auto_mail'      => $new_value['misbuero_auto_mail'] ?? ($old_value['misbuero_auto_mail'] ?? '0'),
	];

	return \array_merge($new_value, cmx_woocommerce_sanitize_settings($settings, $old_value));
}
