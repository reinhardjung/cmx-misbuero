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
	$key = isset($_POST['field']) ? (string)$_POST['field'] : '';
	$text = isset($_POST['text']) ? (string)$_POST['text'] : '';
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
	#cmx-help-modal textarea{width:100%;min-height:160px;resize:vertical}
	#cmx-help-modal .cmx-help-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:10px}
	</style>

	<div id="cmx-help-modal" aria-hidden="true">
		<div class="cmx-help-dialog" role="dialog" aria-modal="true">
			<div class="cmx-help-header">
				<div class="cmx-help-title" id="cmx-help-title">Feldhilfe</div>
				<button type="button" class="cmx-help-close" aria-label="Schliessen">×</button>
			</div>
			<textarea id="cmx-help-text" readonly></textarea>
			<?php if ($can_edit): ?>
				<div class="cmx-help-actions">
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
		let currentField = '';
		let currentLabel = '';
		let currentKind = '';
		let currentTag = '';
		let currentType = '';
		let timer = null;

		const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
		if (textarea && canEdit) {
			textarea.removeAttribute('readonly');
		}

		function openModal() {
			if (!modal) return;
			if (titleEl) {
				titleEl.textContent = currentLabel || 'Feldhilfe';
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
			const custom = el.getAttribute && el.getAttribute('data-cmx-help-key');
			if (custom) return custom;
			if (el.classList && el.classList.contains('postbox')) {
				const id = el.getAttribute('id');
				if (id) return 'metabox_' + id;
				const title = el.querySelector('.hndle, h2, h3');
				if (title && title.textContent) return 'metabox_' + title.textContent.trim();
			}
			if (el.tagName && el.tagName.toLowerCase() === 'table') {
				const id = el.getAttribute('id');
				if (id) return 'table_' + id;
				const caption = el.querySelector('caption');
				if (caption && caption.textContent) return 'table_' + caption.textContent.trim();
			}
			if (el.tagName && el.tagName.toLowerCase() === 'label') {
				const f = el.getAttribute('for');
				if (f) return f;
			}
			const name = el.getAttribute && el.getAttribute('name');
			if (name) return name;
			const id = el.getAttribute && el.getAttribute('id');
			if (id) return id;
			const label = el.closest && el.closest('label');
			if (label && label.textContent) return label.textContent.trim();
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
			timer = setTimeout(function(){ loadHelp(key); }, 2000);
		}
		function clearTimer(){
			if (timer) { clearTimeout(timer); timer = null; }
		}

		document.addEventListener('pointerdown', function(e){
			if (e.target.closest && e.target.closest('#cmx-help-modal')) return;
			startTimer(e);
		}, {passive:true});
		document.addEventListener('pointerup', clearTimer);
		document.addEventListener('pointercancel', clearTimer);
		document.addEventListener('mousedown', function(e){
			if (e.target.closest && e.target.closest('#cmx-help-modal')) return;
			startTimer(e);
		});
		document.addEventListener('mouseup', clearTimer);
		document.addEventListener('touchstart', function(e){
			if (e.target.closest && e.target.closest('#cmx-help-modal')) return;
			startTimer(e);
		}, {passive:true});
		document.addEventListener('touchend', clearTimer);
		document.addEventListener('touchcancel', clearTimer);
		document.addEventListener('contextmenu', function(e){
			const active = document.activeElement;
			if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) {
				const start = typeof active.selectionStart === 'number' ? active.selectionStart : 0;
				const end = typeof active.selectionEnd === 'number' ? active.selectionEnd : 0;
				if (end > start) return;
			}
			const sel = window.getSelection ? window.getSelection() : null;
			if (sel && sel.toString && sel.toString().trim() !== '') return;
			const el = resolveTarget(e.target);
			if (!el) return;
			e.preventDefault();
			triggerHelp(el);
		});

		if (closeBtn) closeBtn.addEventListener('click', closeModal);
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
	})();
	</script>
	<?php
});
