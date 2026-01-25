<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

function cmx_help_is_cloud_meister(): bool {
	$user = \wp_get_current_user();
	if (!$user || !$user->exists()) return false;
	if ($user->display_name === 'CLOUD Meister') return true;
	if ($user->user_login === 'cloudmeister') return true;
	return \current_user_can('manage_options');
}

function cmx_help_option_key(string $raw): string {
	$key = \sanitize_key($raw);
	if ($key === '') {
		$key = 'field';
	}
	return 'cmx_help_field_' . $key;
}

function cmx_help_export_allowed(?string $token): bool {
	return true;
}

add_action('wp_ajax_cmx_help_get', function () {
	if (!\is_admin()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	$key = isset($_POST['field']) ? (string)$_POST['field'] : '';
	$opt_key = cmx_help_option_key($key);
	$text = (string)\get_option($opt_key, '');
	\wp_send_json_success(['text' => $text]);
});

add_action('wp_ajax_cmx_help_save', function () {
	if (!\is_admin() || !cmx_help_is_cloud_meister()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	\check_ajax_referer('cmx_help_save', 'nonce');
	$key = isset($_POST['field']) ? (string)\wp_unslash($_POST['field']) : '';
	$text = isset($_POST['text']) ? (string)\wp_unslash($_POST['text']) : '';
	$opt_key = cmx_help_option_key($key);
	\update_option($opt_key, $text, false);
	\wp_send_json_success(['saved' => true]);
});

add_action('admin_footer', function () {
	$can_edit = cmx_help_is_cloud_meister();
	$nonce = \wp_create_nonce('cmx_help_save');
	?>
	<style>
	#cmx-help-modal{display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.45)}
	#cmx-help-modal .cmx-help-dialog{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:640px;max-width:90vw;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.25);padding:16px 18px}
	#cmx-help-modal .cmx-help-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
	#cmx-help-modal .cmx-help-title{font-weight:600}
	#cmx-help-modal .cmx-help-close{cursor:pointer;border:0;background:transparent;font-size:18px;line-height:1}
	#cmx-help-modal textarea{width:100%;min-height:160px;max-height:60vh;overflow:auto;resize:vertical}
	#cmx-help-modal .cmx-help-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:10px}
	#cmx-help-modal .cmx-help-actions .cmx-help-reload{margin-right:auto;text-decoration:underline}
	</style>

	<div id="cmx-help-modal" aria-hidden="true">
		<div class="cmx-help-dialog" role="dialog" aria-modal="true">
			<div class="cmx-help-header">
				<div class="cmx-help-title" id="cmx-help-title">Feldhilfe</div>
				<button type="button" class="cmx-help-close" aria-label="Schliessen">×</button>
			</div>
			<textarea id="cmx-help-text" readonly></textarea>
			<?php if ($can_edit): ?>
				<div class="cmx-help-actions" id="cmx-help-actions">
					<button type="button" class="button button-primary" id="cmx-help-save">Speichern</button>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<script>
	(function(){
		const modal = document.getElementById('cmx-help-modal');
		const textarea = document.getElementById('cmx-help-text');
		const titleEl = document.getElementById('cmx-help-title');
		const closeBtn = modal ? modal.querySelector('.cmx-help-close') : null;
		const saveBtn = document.getElementById('cmx-help-save');
		const actions = document.getElementById('cmx-help-actions');
		let currentField = '';
		let currentLabel = '';
		let currentKind = '';
		let currentTag = '';
		let currentType = '';
		let timer = null;
		let pressStart = 0;
		let pressTarget = null;

		const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
		if (textarea && canEdit) {
			textarea.removeAttribute('readonly');
		}

		function openModal() {
			if (!modal) return;
			if (titleEl) {
				titleEl.textContent = currentLabel || currentField || 'Feldhilfe';
			}
			const empty = (textarea.value || '').trim() === '';
			const reloadLink = '<?php echo \esc_js(\admin_url('admin.php?page=cmx-einstellungen&tab=general')); ?>';
			if (actions) {
				let link = actions.querySelector('.cmx-help-reload');
				if (!link) {
					link = document.createElement('a');
					link.href = reloadLink;
					link.className = 'cmx-help-reload';
					link.textContent = 'Hilfetexte neu laden';
					link.style.marginRight = 'auto';
					actions.prepend(link);
				}
			}
			modal.style.display = 'block';
			textarea.focus();
		}
		function closeModal() {
			if (!modal) return;
			modal.style.display = 'none';
			textarea.value = '';
			if (titleEl) titleEl.textContent = 'Feldhilfe';
			currentField = '';
			currentLabel = '';
			currentKind = '';
			currentTag = '';
			currentType = '';
		}
		function getFieldKey(el){
			if (!el) return '';
			const context = getContextPrefix();
			const custom = el.getAttribute && el.getAttribute('data-cmx-help-key');
			if (custom) return context + custom;
			if (el.classList && el.classList.contains('postbox')) {
				const id = el.getAttribute('id');
				if (id) return context + 'metabox_' + id;
				const title = el.querySelector('.hndle, h2, h3');
				if (title && title.textContent) return context + 'metabox_' + title.textContent.trim();
			}
			if (el.tagName && el.tagName.toLowerCase() === 'table') {
				const id = el.getAttribute('id');
				if (id) return context + 'table_' + id;
				const caption = el.querySelector('caption');
				if (caption && caption.textContent) return context + 'table_' + caption.textContent.trim();
			}
			if (el.tagName && el.tagName.toLowerCase() === 'label') {
				const f = el.getAttribute('for');
				if (f) return context + f;
			}
			const name = el.getAttribute && el.getAttribute('name');
			if (name) return context + name;
			const id = el.getAttribute && el.getAttribute('id');
			if (id) return context + id;
			const label = el.closest && el.closest('label');
			if (label && label.textContent) return context + label.textContent.trim();
			return '';
		}
		function getContextPrefix(){
			const body = document.body;
			if (body && body.className) {
				const match = body.className.match(/\bpost-type-([a-z0-9_-]+)\b/i);
				if (match && match[1]) return match[1] + '__';
			}
			const pt = document.getElementById('post_type');
			if (pt && pt.value) return pt.value + '__';
			const params = new URLSearchParams(window.location.search);
			const page = params.get('page');
			const tab = params.get('tab');
			if (page) return page + (tab ? '__' + tab : '') + '__';
			return '';
		}
		function getFieldLabel(el){
			if (!el) return '';
			if (el.classList && el.classList.contains('postbox')) {
				const title = el.querySelector('.hndle, h2, h3');
				return title && title.textContent ? title.textContent.trim() : '';
			}
			if (el.tagName && el.tagName.toLowerCase() === 'table') {
				const caption = el.querySelector('caption');
				if (caption && caption.textContent) return caption.textContent.trim();
			}
			if (el.tagName && el.tagName.toLowerCase() === 'button') {
				return el.textContent ? el.textContent.trim() : '';
			}
			if (el.getAttribute) {
				const aria = el.getAttribute('aria-label');
				if (aria) return aria.trim();
				const placeholder = el.getAttribute('placeholder');
				if (placeholder) return placeholder.trim();
			}
			if (el.tagName && el.tagName.toLowerCase() === 'label') {
				return el.textContent ? el.textContent.trim() : '';
			}
			const id = el.getAttribute && el.getAttribute('id');
			if (id) {
				const lbl = document.querySelector('label[for="' + id + '"]');
				if (lbl && lbl.textContent) return lbl.textContent.trim();
			}
			return '';
		}
		function getFieldKind(el){
			if (!el) return '';
			if (el.classList && el.classList.contains('postbox')) return 'metabox';
			if (el.tagName && el.tagName.toLowerCase() === 'table') return 'table';
			if (el.tagName && el.tagName.toLowerCase() === 'button') return 'button';
			if (el.matches && (el.matches('a.button') || el.matches('.button'))) return 'button';
			return 'field';
		}
		function resolveTarget(el){
			if (!el) return null;
			const modal = el.closest && el.closest('#cmx-help-modal');
			if (modal) return null;
			const custom = el.closest && el.closest('[data-cmx-help-key]');
			if (custom) return custom;
			if (el.matches && el.matches('input,select,textarea,label')) return el;
			const btn = el.closest && el.closest('button, a.button, .button');
			if (btn) return btn;
			const postbox = el.closest && el.closest('.postbox');
			if (postbox) return postbox;
			const table = el.closest && el.closest('table');
			if (table) return table;
			return el;
		}
		function loadHelp(key){
			if (!key) return;
			currentField = key;
			const form = new URLSearchParams();
			form.append('action','cmx_help_get');
			form.append('field', key);
			form.append('label', currentLabel || '');
			form.append('kind', currentKind || '');
			form.append('tag', currentTag || '');
			form.append('type', currentType || '');
			fetch(ajaxurl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:form.toString()})
				.then(r => r.json())
				.then(data => {
					textarea.value = (data && data.data && data.data.text) ? data.data.text : '';
					openModal();
				});
		}
		function triggerHelp(el){
			if (!el) return;
			const key = getFieldKey(el);
			if (!key) return;
			currentLabel = getFieldLabel(el);
			currentKind = getFieldKind(el);
			currentTag = el.tagName ? el.tagName.toLowerCase() : '';
			currentType = el.getAttribute ? (el.getAttribute('type') || '') : '';
			loadHelp(key);
		}
		function startTimer(e){
			if (timer) return;
			if (typeof e.button !== 'undefined' && e.button !== 0) return;
			const el = resolveTarget(e.target);
			if (!el) return;
			const key = getFieldKey(el);
			if (!key) return;
			currentLabel = getFieldLabel(el);
			currentKind = getFieldKind(el);
			currentTag = el.tagName ? el.tagName.toLowerCase() : '';
			currentType = el.getAttribute ? (el.getAttribute('type') || '') : '';
			pressStart = Date.now();
			pressTarget = { el: el, key: key };
			timer = setTimeout(function(){ loadHelp(key); }, 2000);
		}
		function clearTimer(){
			if (timer) { clearTimeout(timer); timer = null; }
			pressStart = 0;
			pressTarget = null;
		}

		document.addEventListener('pointerdown', function(e){
			if (e.target.closest && e.target.closest('#cmx-help-modal')) return;
			startTimer(e);
		}, {passive:true});
		document.addEventListener('pointerup', clearTimer);
		document.addEventListener('pointercancel', clearTimer);
		document.addEventListener('contextmenu', function(e){
			if (!timer || !pressTarget) return;
			e.preventDefault();
			clearTimer();
			triggerHelp(pressTarget.el);
		});

		if (closeBtn) closeBtn.addEventListener('click', closeModal);
		modal.addEventListener('click', function(e){
			if (e.target === modal) closeModal();
		});
		document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

		if (saveBtn) {
			saveBtn.addEventListener('click', function(){
				if (!currentField) return;
				const form = new URLSearchParams();
				form.append('action','cmx_help_save');
				form.append('nonce','<?php echo \esc_js($nonce); ?>');
				form.append('field', currentField);
				form.append('text', textarea.value || '');
				fetch(ajaxurl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:form.toString()})
					.then(r => r.json())
					.then(data => {
						if (data && data.success) {
							closeModal();
						}
					});
			});
		}
		if (textarea) {
			textarea.addEventListener('keydown', function(e){
				if (!(e.key === 'Enter' && (e.metaKey || e.ctrlKey))) return;
				if (!canEdit) return;
				e.preventDefault();
				if (saveBtn) saveBtn.click();
			});
		}
	})();
	</script>
	<?php
});

// REST Export für Vorlage
\add_action('rest_api_init', function () {
	\register_rest_route('cmx-misbuero/v1', '/help-texts', [
		'methods'  => 'GET',
		'callback' => function (\WP_REST_Request $request) {
			$token = $request->get_param('token');
			if (!cmx_help_export_allowed($token)) {
				return new \WP_REST_Response(['message' => 'forbidden'], 403);
			}
			global $wpdb;
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
					'cmx_help_field_%'
				),
				ARRAY_A
			);
			$out = [];
			foreach ($rows as $row) {
				$out[$row['option_name']] = (string)$row['option_value'];
			}
			return new \WP_REST_Response([
				'exported_at' => \current_time('mysql'),
				'options' => $out,
			], 200);
		},
		'permission_callback' => '__return_true',
	]);
});

// Admin Sync (AJAX)
\add_action('wp_ajax_cmx_help_sync', function () {
	if (!cmx_help_is_cloud_meister()) {
		\wp_send_json_error(['message' => 'forbidden'], 403);
	}
	if (!isset($_POST['nonce']) || !\wp_verify_nonce((string)$_POST['nonce'], 'cmx_help_sync')) {
		\wp_send_json_error(['message' => 'invalid_nonce'], 400);
	}
	$source_url = 'https://vorlage.misbuero.ch/wp-json/cmx-misbuero/v1/help-texts';
	$response = \wp_remote_get($source_url, [
		'timeout' => 12,
		'redirection' => 3,
	]);
	if (\is_wp_error($response)) {
		\wp_send_json_error(['message' => 'request_failed'], 500);
	}
	$code = (int)\wp_remote_retrieve_response_code($response);
	$body = (string)\wp_remote_retrieve_body($response);
	if ($code !== 200 || $body === '') {
		\wp_send_json_error(['message' => 'bad_response'], 500);
	}
	$data = \json_decode($body, true);
	if (!\is_array($data) || empty($data['options']) || !\is_array($data['options'])) {
		\wp_send_json_error(['message' => 'invalid_payload'], 500);
	}
	$keys = [];
	foreach ($data['options'] as $name => $value) {
		if (\strpos((string)$name, 'cmx_help_field_') !== 0) continue;
		\update_option((string)$name, (string)$value, false);
		$keys[] = (string)$name;
	}
	\wp_send_json_success(['keys' => $keys]);
});
