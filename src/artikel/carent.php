<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_CARENT_BOX_ID')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_CARENT_BOX_ID', 'cmx_artikel_carent');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_CHASSI_NR', '_cmx_artikel_carent_chassi_nr');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KENNZEICHEN', '_cmx_artikel_carent_kennzeichen');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_STAND', '_cmx_artikel_carent_km_stand');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_BEGRENZUNG')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_BEGRENZUNG', '_cmx_artikel_carent_km_begrenzung');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_MEHRPREIS')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KM_MEHRPREIS', '_cmx_artikel_carent_km_mehrpreis');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO', '_cmx_artikel_carent_kasko');
}
if (!\defined(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO_MAX')) {
	\define(__NAMESPACE__ . '\\CMX_ARTIKEL_META_CARENT_KASKO_MAX', '_cmx_artikel_carent_kasko_max');
}
if (!\defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TREIBSTOFF')) {
	\define(__NAMESPACE__ . '\\TAX_ARTIKEL_TREIBSTOFF', cmx_tax_key('artikel', cmx_no_umlaute('Treibstoff')));
}

function cmx_artikel_carent_is_enabled(): bool {
	return \function_exists(__NAMESPACE__ . '\\cmx_system_is_carent_enabled') && cmx_system_is_carent_enabled();
}

function cmx_artikel_carent_fuel_taxonomy(): string {
	return \defined(__NAMESPACE__ . '\\TAX_ARTIKEL_TREIBSTOFF')
		? (string) \constant(__NAMESPACE__ . '\\TAX_ARTIKEL_TREIBSTOFF')
		: cmx_tax_key('artikel', cmx_no_umlaute('Treibstoff'));
}

function cmx_artikel_carent_meta_value(int $post_id, string $meta_key): string {
	return \trim((string) \get_post_meta($post_id, $meta_key, true));
}

function cmx_artikel_carent_normalize_kennzeichen(mixed $value): string {
	$raw = \strtoupper(\trim((string) $value));
	if ($raw === '') {
		return '';
	}

	$letters = (string) \preg_replace('/[^A-Z]+/', '', $raw);
	$digits = (string) \preg_replace('/[^0-9]+/', '', $raw);

	if ($letters !== '') {
		$letters = \substr($letters, 0, 2);
	}
	if ($digits !== '') {
		$digits = \ltrim($digits, '0');
		if ($digits === '') {
			$digits = '0';
		}
	}

	if ($letters !== '' && $digits !== '') {
		return $letters . ' ' . $digits;
	}
	if ($letters !== '') {
		return $letters;
	}

	return $digits;
}

function cmx_artikel_carent_fuel_term_id(int $post_id): int {
	$taxonomy = cmx_artikel_carent_fuel_taxonomy();
	if (!\taxonomy_exists($taxonomy)) {
		return 0;
	}

	$terms = \wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
	if (\is_wp_error($terms) || empty($terms)) {
		return 0;
	}

	return (int) $terms[0];
}

function cmx_artikel_carent_fuel_terms(): array {
	$taxonomy = cmx_artikel_carent_fuel_taxonomy();
	if (!\taxonomy_exists($taxonomy)) {
		return [];
	}

	$terms = \get_terms([
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
	]);

	return \is_wp_error($terms) ? [] : $terms;
}

function cmx_artikel_carent_default_fuel_label(): string {
	return 'Benzin 98';
}

function cmx_artikel_carent_default_fuel_term_id(array $terms = []): int {
	if ($terms === []) {
		$terms = cmx_artikel_carent_fuel_terms();
	}

	$lower = static function (string $value): string {
		return \function_exists('mb_strtolower') ? \mb_strtolower($value) : \strtolower($value);
	};
	$default_label = $lower(cmx_artikel_carent_default_fuel_label());
	$fallback_id = 0;
	foreach ($terms as $term) {
		$term_id = (int) ($term->term_id ?? 0);
		$term_name = $lower(\trim((string) ($term->name ?? '')));
		if ($fallback_id <= 0 && $term_id > 0) {
			$fallback_id = $term_id;
		}
		if ($term_id > 0 && $term_name === $default_label) {
			return $term_id;
		}
	}

	return $fallback_id;
}

function cmx_artikel_carent_normalize_decimal(mixed $value, int $decimals = 2): string {
	$raw = \trim((string) $value);
	if ($raw === '') {
		return '';
	}

	$number = cmx_parse_number($raw);
	if (!\is_finite($number)) {
		return '';
	}

	$number = \max(0, $number);
	return \number_format($number, \max(0, $decimals), '.', '');
}

function cmx_artikel_carent_upsert_meta(int $post_id, string $meta_key, string $value): void {
	if ($value === '') {
		\delete_post_meta($post_id, $meta_key);
		return;
	}

	\update_post_meta($post_id, $meta_key, $value);
}

function cmx_artikel_carent_normal_order_ids(array $ids): array {
	$box_id = (string) CMX_ARTIKEL_CARENT_BOX_ID;
	$ids = \array_values(\array_filter(\array_map('trim', $ids), static fn($id): bool => $id !== '' && $id !== $box_id));

	$ordered = [];
	$inserted = false;
	foreach ($ids as $id) {
		$ordered[] = $id;
		if ($id === 'cmx_artikel_waehrung_preise' && !$inserted) {
			$ordered[] = $box_id;
			$inserted = true;
		}
		if ($id === 'cmx_artikel_lieferanten' && !$inserted) {
			\array_splice($ordered, \count($ordered) - 1, 0, [$box_id]);
			$inserted = true;
		}
	}

	if (!$inserted) {
		$ordered[] = $box_id;
	}

	return \array_values(\array_unique($ordered));
}

function cmx_artikel_carent_reorder_runtime_boxes(): void {
	global $wp_meta_boxes;

	if (!isset($wp_meta_boxes['artikel']['normal']) || !\is_array($wp_meta_boxes['artikel']['normal'])) {
		return;
	}

	$box_id = (string) CMX_ARTIKEL_CARENT_BOX_ID;
	$box = null;
	$normal_boxes = &$wp_meta_boxes['artikel']['normal'];

	foreach (['high', 'sorted', 'core', 'default', 'low'] as $priority) {
		if (!isset($normal_boxes[$priority][$box_id])) {
			continue;
		}
		$box = $normal_boxes[$priority][$box_id];
		unset($normal_boxes[$priority][$box_id]);
		break;
	}

	if (!\is_array($box)) {
		return;
	}

	if (!isset($normal_boxes['default']) || !\is_array($normal_boxes['default'])) {
		$normal_boxes['default'] = [];
	}

	$ordered = [];
	$inserted = false;
	foreach ($normal_boxes['default'] as $id => $entry) {
		$ordered[$id] = $entry;
		if ($id === 'cmx_artikel_waehrung_preise' && !$inserted) {
			$ordered[$box_id] = $box;
			$inserted = true;
		}
		if ($id === 'cmx_artikel_lieferanten' && !$inserted) {
			$ordered = \array_slice($ordered, 0, -1, true) + [$box_id => $box, $id => $entry];
			$inserted = true;
		}
	}

	if (!$inserted) {
		$ordered[$box_id] = $box;
	}

	$normal_boxes['default'] = $ordered;
}

\add_action('init', function (): void {
	if (!cmx_artikel_carent_is_enabled()) {
		return;
	}

	cmx_create_taxo('artikel', 'Treibstoff', 'Treibstoff', false, true);
}, 20);

\add_action('admin_init', function (): void {
	if (!cmx_artikel_carent_is_enabled()) {
		return;
	}

	cmx_seed_taxo('Artikel', 'Treibstoff', 'carent');
});

\add_action('admin_menu', function (): void {
	if (!cmx_artikel_carent_is_enabled()) {
		return;
	}

	$taxonomy = cmx_artikel_carent_fuel_taxonomy();
	foreach (['side', 'normal', 'advanced'] as $context) {
		\remove_meta_box('tagsdiv-' . $taxonomy, 'artikel', $context);
		\remove_meta_box($taxonomy . 'div', 'artikel', $context);
	}
}, 50);

\add_action('add_meta_boxes', function (): void {
	if (!cmx_artikel_carent_is_enabled()) {
		return;
	}

	$title = 'carent';
	if (\post_type_exists('carent')) {
		$title = '<a href="' . \esc_url(\admin_url('edit.php?post_type=carent')) . '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" style="font-size:14px;font-weight:700;line-height:1.3;color:#2271b1;text-decoration:none;cursor:pointer;">carent</a>';
	}

	\add_meta_box((string) CMX_ARTIKEL_CARENT_BOX_ID, $title, __NAMESPACE__ . '\\cmx_artikel_carent_box_html', 'artikel', 'normal', 'default');
});

\add_action('do_meta_boxes', function ($post_type, $context): void {
	if (!cmx_artikel_carent_is_enabled() || (string) $post_type !== 'artikel' || (string) $context !== 'normal') {
		return;
	}

	cmx_artikel_carent_reorder_runtime_boxes();

	$user_id = \get_current_user_id();
	if ($user_id <= 0) {
		return;
	}

	$opt_key = 'meta-box-order_artikel';
	$order = \get_user_option($opt_key);
	if (!\is_array($order)) {
		$order = ['normal' => '', 'advanced' => '', 'side' => ''];
	} else {
		$order += ['normal' => '', 'advanced' => '', 'side' => ''];
	}

	$ids = \array_filter(\array_map('trim', \explode(',', (string) $order['normal'])));
	$order['normal'] = \implode(',', cmx_artikel_carent_normal_order_ids($ids));
	\update_user_option($user_id, $opt_key, $order, true);
}, 95, 2);

function cmx_artikel_carent_box_html(\WP_Post $post): void {
	$chassi_nr = cmx_artikel_carent_meta_value($post->ID, CMX_ARTIKEL_META_CARENT_CHASSI_NR);
	$kennzeichen = cmx_artikel_carent_normalize_kennzeichen(cmx_artikel_carent_meta_value($post->ID, CMX_ARTIKEL_META_CARENT_KENNZEICHEN));
	$km_stand = cmx_artikel_carent_meta_value($post->ID, CMX_ARTIKEL_META_CARENT_KM_STAND);
	$km_begrenzung = cmx_artikel_carent_meta_value($post->ID, CMX_ARTIKEL_META_CARENT_KM_BEGRENZUNG);
	$km_mehrpreis = cmx_artikel_carent_meta_value($post->ID, CMX_ARTIKEL_META_CARENT_KM_MEHRPREIS);
	$kasko_min = cmx_artikel_carent_meta_value($post->ID, CMX_ARTIKEL_META_CARENT_KASKO);
	$kasko_max = cmx_artikel_carent_meta_value($post->ID, CMX_ARTIKEL_META_CARENT_KASKO_MAX);
	$treibstoff_terms = cmx_artikel_carent_fuel_terms();
	$treibstoff_term_id = cmx_artikel_carent_fuel_term_id($post->ID);
	if ($treibstoff_term_id <= 0) {
		$treibstoff_term_id = cmx_artikel_carent_default_fuel_term_id($treibstoff_terms);
	}
	$treibstoff_title = '';
	$treibstoff_items = [];
	foreach ($treibstoff_terms as $term) {
		$term_id = (int) ($term->term_id ?? 0);
		$term_name = \trim((string) ($term->name ?? ''));
		if ($term_id <= 0 || $term_name === '') {
			continue;
		}
		if ($term_id === $treibstoff_term_id) {
			$treibstoff_title = $term_name;
		}
		$treibstoff_items[] = [
			'id' => $term_id,
			'title' => $term_name,
		];
	}
	$treibstoff_items_json = \wp_json_encode($treibstoff_items);

	\wp_nonce_field('cmx_artikel_carent_save', 'cmx_artikel_carent_nonce');

	echo '<input type="hidden" name="cmx_artikel_carent_payload" value="1">';
	echo '<style>
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ',
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .inside{
			overflow:visible !important;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-grid{
			display:grid;
			grid-template-columns:minmax(150px,1.2fr) minmax(135px,.95fr) minmax(189px,1.26fr) minmax(120px,.8fr) minmax(120px,.8fr) minmax(130px,.95fr) minmax(120px,.85fr) minmax(120px,.85fr);
			gap:14px;
			align-items:end;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-field label{
			display:block;
			margin:0 0 6px;
			font-weight:600;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-field input,
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-field select{
			width:100%;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-suggest{
			position:relative;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-input-row{
			display:flex;
			align-items:center;
			gap:6px;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-input-row input[type=text]{
			flex:1 1 auto;
			min-width:0;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-suggest-list{
			position:absolute;
			z-index:100002;
			left:0;
			right:0;
			max-height:240px;
			overflow:auto;
			margin:2px 0 0;
			padding:0;
			border:1px solid #ccd0d4;
			border-radius:4px;
			background:#fff;
			box-shadow:0 10px 24px rgba(0,0,0,.10);
			list-style:none;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-suggest-list li{
			margin:0;
			padding:6px 8px;
			cursor:pointer;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-suggest-list li.active{
			background:#e5f3ff;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-suggest-list li:hover{
			background:#f3f4f5;
		}
		#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-note{
			margin:6px 0 0;
			color:#646970;
		}
		@media (min-width: 1281px) and (max-width: 1700px){
			#post-body.columns-2 #' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-grid{
				grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,.9fr) minmax(0,1fr);
			}
		}
		@media (max-width: 1280px){
			#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-grid{
				grid-template-columns:repeat(3,minmax(0,1fr));
			}
		}
		@media (max-width: 782px){
			#' . \esc_attr((string) CMX_ARTIKEL_CARENT_BOX_ID) . ' .cmx-carent-grid{
				grid-template-columns:minmax(0,1fr);
			}
		}
	</style>';

	echo '<div class="cmx-carent-grid">';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_chassi_nr">Chassi-Nr (VIN)</label>';
	echo '<input type="text" class="widefat" id="cmx_artikel_carent_chassi_nr" name="cmx_artikel_carent_chassi_nr" value="' . \esc_attr($chassi_nr) . '">';
	echo '</div>';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_kennzeichen">Kennzeichen</label>';
	echo '<input type="text" class="widefat" id="cmx_artikel_carent_kennzeichen" name="cmx_artikel_carent_kennzeichen" value="' . \esc_attr($kennzeichen) . '" placeholder="ZH 123" autocapitalize="characters" spellcheck="false">';
	echo '</div>';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_treibstoff">Treibstoff</label>';
	echo '<div class="cmx-carent-suggest">';
	echo '<div class="cmx-carent-input-row">';
	echo '<input type="text" id="cmx_artikel_carent_treibstoff_search" class="widefat" autocomplete="off" aria-label="Treibstoff suchen" placeholder="Treibstoff suchen..." value="' . \esc_attr($treibstoff_title) . '">';
	echo '<input type="hidden" id="cmx_artikel_carent_treibstoff" name="cmx_artikel_carent_treibstoff" value="' . \esc_attr((string) $treibstoff_term_id) . '">';
	echo '</div>';
	echo '<ul id="cmx_artikel_carent_treibstoff_suggest" class="cmx-carent-suggest-list" style="display:none"></ul>';
	echo '</div>';
	if ($treibstoff_terms === []) {
		echo '<p class="cmx-carent-note">Keine Treibstoffe in <code>[carent] Treibstoff</code> definiert.</p>';
	}
	echo '</div>';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_km_stand">KM-Stand</label>';
	echo '<input type="number" min="0" step="1" class="widefat" id="cmx_artikel_carent_km_stand" name="cmx_artikel_carent_km_stand" value="' . \esc_attr($km_stand) . '">';
	echo '</div>';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_km_begrenzung">KM-Begrenzung</label>';
	echo '<input type="number" min="0" step="1" class="widefat" id="cmx_artikel_carent_km_begrenzung" name="cmx_artikel_carent_km_begrenzung" value="' . \esc_attr($km_begrenzung) . '">';
	echo '</div>';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_km_mehrpreis">KM-Mehrpreis</label>';
	echo '<input type="number" min="0" step="0.01" class="widefat" id="cmx_artikel_carent_km_mehrpreis" name="cmx_artikel_carent_km_mehrpreis" value="' . \esc_attr($km_mehrpreis) . '">';
	echo '</div>';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_kasko">Kasko min</label>';
	echo '<input type="number" min="0" step="0.01" class="widefat" id="cmx_artikel_carent_kasko" name="cmx_artikel_carent_kasko" value="' . \esc_attr($kasko_min) . '">';
	echo '</div>';

	echo '<div class="cmx-carent-field">';
	echo '<label for="cmx_artikel_carent_kasko_max">Kasko max</label>';
	echo '<input type="number" min="0" step="0.01" class="widefat" id="cmx_artikel_carent_kasko_max" name="cmx_artikel_carent_kasko_max" value="' . \esc_attr($kasko_max) . '">';
	echo '</div>';

	echo '</div>';
	echo '<script>
	(function(){
		var field = document.getElementById("cmx_artikel_carent_kennzeichen");
		if (!field || field.dataset.cmxKennzeichenBound === "1") return;
		field.dataset.cmxKennzeichenBound = "1";
		var normalize = function(value){
			value = String(value || "").toUpperCase().trim();
			if (!value) return "";
			var letters = value.replace(/[^A-Z]+/g, "").slice(0, 2);
			var digits = value.replace(/[^0-9]+/g, "");
			if (digits) {
				digits = digits.replace(/^0+/, "");
				if (!digits) digits = "0";
			}
			if (letters && digits) return letters + " " + digits;
			return letters || digits;
		};
		var apply = function(){
			var next = normalize(field.value);
			if (field.value !== next) field.value = next;
		};
		field.addEventListener("input", apply);
		field.addEventListener("blur", apply);
		apply();
	})();
	</script>';
	echo '<script>
	(function(){
		var input = document.getElementById("cmx_artikel_carent_treibstoff_search");
		var hidden = document.getElementById("cmx_artikel_carent_treibstoff");
		var list = document.getElementById("cmx_artikel_carent_treibstoff_suggest");
		if (!input || !hidden || !list || input.dataset.cmxFuelBound === "1") return;
		input.dataset.cmxFuelBound = "1";
		var items = ' . ($treibstoff_items_json ?: '[]') . ';
		items.forEach(function(item){
			item.id = parseInt(item.id || 0, 10) || 0;
			item.title = item.title || "";
			item.titleLower = item.title.toLocaleLowerCase();
		});
		items.sort(function(a,b){
			if (a.titleLower < b.titleLower) return -1;
			if (a.titleLower > b.titleLower) return 1;
			return 0;
		});
		var byId = {};
		items.forEach(function(item){
			if (item.id > 0) byId[String(item.id)] = item;
		});
		function esc(s){
			return (s || "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
		}
		function makeNavigator(inputEl, listEl, chooseCb){
			var active = -1, navItems = [];
			function closeList(){
				listEl.style.display = "none";
				listEl.innerHTML = "";
				active = -1;
			}
			function render(arr){
				navItems = arr || [];
				if (!navItems.length){ closeList(); return; }
				listEl.innerHTML = navItems.map(function(it, i){
					return "<li data-index=\\"" + i + "\\">" + esc(it.title) + "</li>";
				}).join("");
				listEl.style.display = "block";
				active = -1;
			}
			function move(delta){
				if (!navItems.length) return;
				active = (active + delta + navItems.length) % navItems.length;
				Array.prototype.forEach.call(listEl.children, function(li, i){
					li.classList.toggle("active", i === active);
				});
			}
			function choose(index){
				if (index < 0 || index >= navItems.length) return;
				chooseCb(navItems[index]);
				closeList();
			}
			listEl.addEventListener("mousedown", function(e){
				var li = e.target.closest("li");
				if (!li) return;
				e.preventDefault();
				choose(parseInt(li.dataset.index || "-1", 10));
			});
			inputEl.addEventListener("keydown", function(e){
				if (listEl.style.display !== "block" && (e.key === "ArrowDown" || e.key === "ArrowUp")) return;
				if (e.key === "ArrowDown"){ e.preventDefault(); move(1); }
				else if (e.key === "ArrowUp"){ e.preventDefault(); move(-1); }
				else if (e.key === "Enter"){ if (active > -1){ e.preventDefault(); choose(active); } }
				else if (e.key === "Escape"){ closeList(); }
			});
			inputEl.addEventListener("blur", function(){
				window.setTimeout(function(){
					var ae = document.activeElement;
					if (ae === inputEl || listEl.contains(ae)) return;
					closeList();
				}, 120);
			});
			document.addEventListener("click", function(e){
				if (!listEl.contains(e.target) && e.target !== inputEl){
					closeList();
				}
			});
			return {
				render: render,
				reset: function(){ navItems = []; active = -1; }
			};
		}
		var navigator = makeNavigator(input, list, chooseItem);
		var timer = null;
		function chooseItem(item, keepFocus){
			hidden.value = item && item.id ? String(item.id) : "0";
			input.value = item && item.title ? item.title : "";
			input.dataset.selectedTitle = item && item.title ? item.title : "";
			hidden.dispatchEvent(new Event("change", {bubbles:true}));
			if (keepFocus !== false) input.focus();
		}
		function exactMatch(query){
			var q = (query || "").trim().toLocaleLowerCase();
			if (!q) return null;
			var matches = items.filter(function(item){ return item.titleLower === q; });
			return matches.length === 1 ? matches[0] : null;
		}
		function matchedItems(query){
			var q = (query || "").trim().toLocaleLowerCase();
			var activeId = parseInt(hidden.value || "0", 10) || 0;
			var matches = items.slice();
			if (q){
				matches = matches.filter(function(item){ return item.titleLower.indexOf(q) !== -1; });
				matches.sort(function(a,b){
					var aStarts = a.titleLower.indexOf(q) === 0 ? 0 : 1;
					var bStarts = b.titleLower.indexOf(q) === 0 ? 0 : 1;
					if (aStarts !== bStarts) return aStarts - bStarts;
					if (a.titleLower < b.titleLower) return -1;
					if (a.titleLower > b.titleLower) return 1;
					return 0;
				});
			} else if (activeId > 0) {
				matches.sort(function(a,b){
					if (a.id === activeId && b.id !== activeId) return -1;
					if (b.id === activeId && a.id !== activeId) return 1;
					if (a.titleLower < b.titleLower) return -1;
					if (a.titleLower > b.titleLower) return 1;
					return 0;
				});
			}
			return matches.slice(0, 50);
		}
		function renderSuggestions(showAll){
			var query = (input.value || "").trim();
			if (!showAll && query.length < 1){
				navigator.reset();
				list.style.display = "none";
				list.innerHTML = "";
				return;
			}
			navigator.render(matchedItems(showAll ? "" : query));
		}
		function syncFieldFromHidden(){
			var item = byId[String(hidden.value || "")] || null;
			if (item){
				input.value = item.title || "";
				input.dataset.selectedTitle = item.title || "";
			} else if ((hidden.value || "") === "" || String(hidden.value || "0") === "0"){
				input.dataset.selectedTitle = "";
			}
		}
		input.addEventListener("input", function(){
			hidden.value = "0";
			if (timer) clearTimeout(timer);
			var query = (input.value || "").trim();
			if (query.length === 0){
				renderSuggestions(true);
				return;
			}
			timer = window.setTimeout(function(){ renderSuggestions(false); }, 120);
		});
		input.addEventListener("focus", function(){
			renderSuggestions((input.value || "").trim() === "");
		});
		input.addEventListener("click", function(){
			renderSuggestions((input.value || "").trim() === "");
		});
		input.addEventListener("blur", function(){
			window.setTimeout(function(){
				if (String(hidden.value || "0") === "0"){
					var match = exactMatch(input.value || "");
					if (match) chooseItem(match, false);
				}
			}, 130);
		});
		hidden.addEventListener("change", syncFieldFromHidden);
		syncFieldFromHidden();
	})();
	</script>';
}

\add_action('save_post_artikel', function (int $post_id, \WP_Post $post): void {
	if (!cmx_artikel_carent_is_enabled()) {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
		return;
	}
	if ($post->post_type !== 'artikel') {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}
	if (!isset($_POST['cmx_artikel_carent_nonce']) || !\wp_verify_nonce((string) $_POST['cmx_artikel_carent_nonce'], 'cmx_artikel_carent_save')) {
		return;
	}
	if (!isset($_POST['cmx_artikel_carent_payload'])) {
		return;
	}

	$chassi_nr = \sanitize_text_field((string) \wp_unslash($_POST['cmx_artikel_carent_chassi_nr'] ?? ''));
	$kennzeichen = cmx_artikel_carent_normalize_kennzeichen(\wp_unslash($_POST['cmx_artikel_carent_kennzeichen'] ?? ''));
	$km_stand_raw = \trim((string) \wp_unslash($_POST['cmx_artikel_carent_km_stand'] ?? ''));
	$km_stand = $km_stand_raw === '' ? '' : (string) \max(0, (int) \round(cmx_parse_number($km_stand_raw)));
	$km_begrenzung_raw = \trim((string) \wp_unslash($_POST['cmx_artikel_carent_km_begrenzung'] ?? ''));
	$km_begrenzung = $km_begrenzung_raw === '' ? '' : (string) \max(0, (int) \round(cmx_parse_number($km_begrenzung_raw)));
	$km_mehrpreis = cmx_artikel_carent_normalize_decimal(\wp_unslash($_POST['cmx_artikel_carent_km_mehrpreis'] ?? ''));
	$kasko_min = cmx_artikel_carent_normalize_decimal(\wp_unslash($_POST['cmx_artikel_carent_kasko'] ?? ''));
	$kasko_max = cmx_artikel_carent_normalize_decimal(\wp_unslash($_POST['cmx_artikel_carent_kasko_max'] ?? ''));

	cmx_artikel_carent_upsert_meta($post_id, CMX_ARTIKEL_META_CARENT_CHASSI_NR, $chassi_nr);
	cmx_artikel_carent_upsert_meta($post_id, CMX_ARTIKEL_META_CARENT_KENNZEICHEN, $kennzeichen);
	cmx_artikel_carent_upsert_meta($post_id, CMX_ARTIKEL_META_CARENT_KM_STAND, $km_stand);
	cmx_artikel_carent_upsert_meta($post_id, CMX_ARTIKEL_META_CARENT_KM_BEGRENZUNG, $km_begrenzung);
	cmx_artikel_carent_upsert_meta($post_id, CMX_ARTIKEL_META_CARENT_KM_MEHRPREIS, $km_mehrpreis);
	cmx_artikel_carent_upsert_meta($post_id, CMX_ARTIKEL_META_CARENT_KASKO, $kasko_min);
	cmx_artikel_carent_upsert_meta($post_id, CMX_ARTIKEL_META_CARENT_KASKO_MAX, $kasko_max);

	$taxonomy = cmx_artikel_carent_fuel_taxonomy();
	if (\taxonomy_exists($taxonomy)) {
		$term_id = (int) \wp_unslash($_POST['cmx_artikel_carent_treibstoff'] ?? 0);
		if ($term_id > 0) {
			$term = \get_term($term_id, $taxonomy);
			$term_id = ($term && !\is_wp_error($term)) ? $term_id : 0;
		}
		\wp_set_post_terms($post_id, $term_id > 0 ? [$term_id] : [], $taxonomy, false);
	}
}, 10, 2);
