<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ROWS_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ROWS_META', '_cmx_carent_schadenprotokoll_rows');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ORT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ORT_META', '_cmx_carent_schadenprotokoll_ort');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META', '_cmx_carent_schadenprotokoll_datum');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META', '_cmx_carent_schadenprotokoll_uhrzeit');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META', '_cmx_carent_schadenprotokoll_weitere_beteiligte');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META', '_cmx_carent_schadenprotokoll_weitere_angaben');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META', '_cmx_carent_schadenprotokoll_unfallprotokoll');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META', '_cmx_carent_schadenprotokoll_anerkennung');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schaden_taxonomy')) {
	function cmx_carent_schaden_taxonomy(): string {
		$candidates = [];
		if (\defined(__NAMESPACE__ . '\\TAX_CARENT_SCHADEN')) {
			$candidates[] = (string) \constant(__NAMESPACE__ . '\\TAX_CARENT_SCHADEN');
		}
		$candidates[] = 'carent_schaden';
		if (\function_exists(__NAMESPACE__ . '\\cmx_tax_key')) {
			$candidates[] = (string) cmx_tax_key('carent', 'Schaden');
		}

		foreach (\array_values(\array_unique(\array_filter($candidates))) as $taxonomy) {
			if (\taxonomy_exists($taxonomy)) {
				return $taxonomy;
			}
		}

		foreach (\get_object_taxonomies('carent', 'objects') as $taxonomy => $tax_object) {
			if (!$tax_object instanceof \WP_Taxonomy) {
				continue;
			}

			$labels = [
				(string) ($tax_object->label ?? ''),
				(string) ($tax_object->labels->name ?? ''),
				(string) ($tax_object->labels->singular_name ?? ''),
				(string) ($tax_object->labels->menu_name ?? ''),
			];
			foreach ($labels as $label) {
				if (\strcasecmp(\trim($label), 'Schaden') === 0) {
					return (string) $taxonomy;
				}
			}
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_tax_key')
			? (string) cmx_tax_key('carent', 'Schaden')
			: '';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_order_before_rueckgabe')) {
	function cmx_carent_schadenprotokoll_order_before_rueckgabe($value) {
		if (!\is_array($value)) {
			return $value;
		}

		$normal = isset($value['normal']) ? (string) $value['normal'] : '';
		$ids = \array_values(\array_filter(\array_map('trim', \explode(',', $normal))));
		if ($ids === []) {
			return $value;
		}

		$schaden_id = 'cmx_carent_schadenprotokoll_box';
		$rueckgabe_id = 'cmx_carent_rueckgabe_box';
		$schaden_index = \array_search($schaden_id, $ids, true);
		$rueckgabe_index = \array_search($rueckgabe_id, $ids, true);

		if ($rueckgabe_index === false) {
			return $value;
		}

		if ($schaden_index !== false) {
			unset($ids[$schaden_index]);
			$ids = \array_values($ids);
			$rueckgabe_index = \array_search($rueckgabe_id, $ids, true);
		}

		\array_splice($ids, (int) $rueckgabe_index, 0, [$schaden_id]);
		$value['normal'] = \implode(',', $ids);

		return $value;
	}
}

\add_filter('get_user_option_meta-box-order_carent', __NAMESPACE__ . '\\cmx_carent_schadenprotokoll_order_before_rueckgabe');

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_term_options')) {
	function cmx_carent_schadenprotokoll_term_options(): array {
		$taxonomy = cmx_carent_schaden_taxonomy();
		if ($taxonomy === '') {
			return [];
		}

		$terms = \get_terms([
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC',
		]);

		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}

		$options = [];
		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$options[] = [
				'id' => (int) $term->term_id,
				'label' => (string) $term->name,
			];
		}

		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_sanitize_cost')) {
	function cmx_carent_schadenprotokoll_sanitize_cost($value): string {
		$value = \str_replace(',', '.', \trim((string) $value));
		if ($value === '' || !\is_numeric($value)) {
			return '';
		}

		return (string) \max(0, (float) $value);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_rows')) {
	function cmx_carent_schadenprotokoll_rows(int $post_id): array {
		$raw_rows = \get_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_ROWS_META, true);
		$rows = [];

		if (\is_array($raw_rows)) {
			foreach ($raw_rows as $raw_row) {
				if (!\is_array($raw_row)) {
					continue;
				}

				$term_id = isset($raw_row['term_id']) ? (int) $raw_row['term_id'] : 0;
				$note = isset($raw_row['note']) ? \trim((string) $raw_row['note']) : '';
				$fotos_gemacht = !empty($raw_row['fotos_gemacht']);
				$kosten = cmx_carent_schadenprotokoll_sanitize_cost($raw_row['kosten'] ?? '');
				if ($term_id <= 0 && $note === '' && !$fotos_gemacht && $kosten === '') {
					continue;
				}

				$rows[] = [
					'term_id' => $term_id,
					'note' => $note,
					'fotos_gemacht' => $fotos_gemacht,
					'kosten' => $kosten,
				];
			}
		}

		if ($rows !== []) {
			return $rows;
		}

		$taxonomy = cmx_carent_schaden_taxonomy();
		if ($taxonomy === '') {
			return [];
		}

		$terms = \get_the_terms($post_id, $taxonomy);
		if (\is_wp_error($terms) || !\is_array($terms)) {
			return [];
		}

		foreach ($terms as $term) {
			if (!$term instanceof \WP_Term) {
				continue;
			}

			$rows[] = [
				'term_id' => (int) $term->term_id,
				'note' => '',
				'fotos_gemacht' => false,
				'kosten' => '',
			];
		}

		return $rows;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schadenprotokoll_row_markup')) {
	function cmx_carent_schadenprotokoll_row_markup(string $index, array $row, array $term_options): string {
		$term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
		$note = \trim((string) ($row['note'] ?? ''));
		$fotos_gemacht = !empty($row['fotos_gemacht']);
		$kosten = cmx_carent_schadenprotokoll_sanitize_cost($row['kosten'] ?? '');
		$select_id = 'cmx-carent-schadenprotokoll-term-' . $index;
		$note_id = 'cmx-carent-schadenprotokoll-note-' . $index;
		$checkbox_id = 'cmx-carent-schadenprotokoll-fotos-gemacht-' . $index;
		$kosten_id = 'cmx-carent-schadenprotokoll-kosten-' . $index;

		\ob_start();
		?>
		<div class="cmx-carent-schaden-row" data-index="<?php echo \esc_attr($index); ?>">
			<div class="cmx-carent-schaden-row-head">
				<strong><?php echo \esc_html__('Schaden', 'cmx-misbuero'); ?></strong>
				<button type="button" class="button-link-delete cmx-carent-schaden-row-remove"><?php echo \esc_html__('Entfernen', 'cmx-misbuero'); ?></button>
			</div>
			<div class="cmx-carent-schaden-row-grid">
				<div>
					<label for="<?php echo \esc_attr($select_id); ?>"><?php echo \esc_html__('Kategorie', 'cmx-misbuero'); ?></label>
					<select class="widefat" id="<?php echo \esc_attr($select_id); ?>" name="cmx_carent_schadenprotokoll_rows[<?php echo \esc_attr($index); ?>][term_id]">
						<option value="0"><?php echo \esc_html__('Schaden wählen', 'cmx-misbuero'); ?></option>
						<?php foreach ($term_options as $option) : ?>
							<option value="<?php echo (int) ($option['id'] ?? 0); ?>"<?php \selected($term_id, (int) ($option['id'] ?? 0)); ?>>
								<?php echo \esc_html((string) ($option['label'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<label class="cmx-carent-schaden-checkbox" for="<?php echo \esc_attr($checkbox_id); ?>">
						<input type="checkbox" id="<?php echo \esc_attr($checkbox_id); ?>" name="cmx_carent_schadenprotokoll_rows[<?php echo \esc_attr($index); ?>][fotos_gemacht]" value="1"<?php \checked($fotos_gemacht); ?>>
						<span><?php echo \esc_html__('Fotos gemacht/geschickt', 'cmx-misbuero'); ?></span>
					</label>
					<label class="cmx-carent-schaden-kosten-label" for="<?php echo \esc_attr($kosten_id); ?>"><?php echo \esc_html__('Kosten', 'cmx-misbuero'); ?></label>
					<input type="number" min="0" step="0.01" class="widefat cmx-carent-schaden-kosten" id="<?php echo \esc_attr($kosten_id); ?>" name="cmx_carent_schadenprotokoll_rows[<?php echo \esc_attr($index); ?>][kosten]" value="<?php echo \esc_attr($kosten); ?>">
				</div>
				<div>
					<label for="<?php echo \esc_attr($note_id); ?>"><?php echo \esc_html__('Beschreibung', 'cmx-misbuero'); ?></label>
					<textarea class="widefat" id="<?php echo \esc_attr($note_id); ?>" name="cmx_carent_schadenprotokoll_rows[<?php echo \esc_attr($index); ?>][note]" rows="4"><?php echo \esc_textarea($note); ?></textarea>
				</div>
			</div>
		</div>
		<?php

		return (string) \ob_get_clean();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_render_schadenprotokoll_metabox')) {
	function cmx_carent_render_schadenprotokoll_metabox(\WP_Post $post): void {
		$rows = cmx_carent_schadenprotokoll_rows((int) $post->ID);
		if ($rows === []) {
			$rows = [['term_id' => 0, 'note' => '', 'fotos_gemacht' => false]];
		}

		$term_options = cmx_carent_schadenprotokoll_term_options();
		$ort = \trim((string) \get_post_meta($post->ID, CMX_CARENT_SCHADENPROTOKOLL_ORT_META, true));
		$datum = \trim((string) \get_post_meta($post->ID, CMX_CARENT_SCHADENPROTOKOLL_DATUM_META, true));
		$uhrzeit = \trim((string) \get_post_meta($post->ID, CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META, true));
		$weitere_beteiligte = \trim((string) \get_post_meta($post->ID, CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META, true));
		$weitere_angaben = \trim((string) \get_post_meta($post->ID, CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META, true));
		$unfallprotokoll = \trim((string) \get_post_meta($post->ID, CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META, true));
		$anerkennung = \trim((string) \get_post_meta($post->ID, CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META, true));
		$template = cmx_carent_schadenprotokoll_row_markup('__INDEX__', ['term_id' => 0, 'note' => '', 'fotos_gemacht' => false, 'kosten' => ''], $term_options);

		\wp_nonce_field('cmx_carent_schadenprotokoll_save', 'cmx_carent_schadenprotokoll_nonce');
		?>
		<style>
			#cmx-carent-schadenprotokoll{display:grid;gap:14px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-rows{display:grid;gap:12px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-top{display:grid;grid-template-columns:minmax(220px,1.8fr) minmax(150px,1fr) minmax(120px,.8fr);gap:10px;align-items:end}
			#cmx-carent-schadenprotokoll label{display:block;margin:0 0 6px;font-weight:600}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-row{border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-row-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-row-grid{display:grid;grid-template-columns:minmax(120px,.532fr) minmax(220px,1.468fr);gap:12px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-checkbox{display:flex;align-items:center;gap:6px;margin:10px 0 0;font-weight:400}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-kosten-label{margin-top:12px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-kosten{width:50%;min-width:90px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-extra{display:grid;grid-template-columns:1fr 1fr;gap:14px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-radio{display:flex;flex-wrap:wrap;gap:12px;margin:0 0 10px}
			#cmx-carent-schadenprotokoll .cmx-carent-schaden-radio label{display:inline-flex;align-items:center;gap:5px;margin:0;font-weight:400}
			@media (max-width: 782px){
				#cmx-carent-schadenprotokoll .cmx-carent-schaden-top,
				#cmx-carent-schadenprotokoll .cmx-carent-schaden-row-grid,
				#cmx-carent-schadenprotokoll .cmx-carent-schaden-extra{grid-template-columns:1fr}
			}
		</style>
		<div id="cmx-carent-schadenprotokoll" class="cmx-carent-schaden-stack">
			<div class="cmx-carent-schaden-top">
				<div>
					<label for="cmx_carent_schadenprotokoll_ort"><?php echo \esc_html__('Ort', 'cmx-misbuero'); ?></label>
					<input type="text" class="widefat" id="cmx_carent_schadenprotokoll_ort" name="cmx_carent_schadenprotokoll_ort" value="<?php echo \esc_attr($ort); ?>">
				</div>
				<div>
					<label for="cmx_carent_schadenprotokoll_datum"><?php echo \esc_html__('Datum', 'cmx-misbuero'); ?></label>
					<input type="date" class="widefat" id="cmx_carent_schadenprotokoll_datum" name="cmx_carent_schadenprotokoll_datum" value="<?php echo \esc_attr($datum); ?>">
				</div>
				<div>
					<label for="cmx_carent_schadenprotokoll_uhrzeit"><?php echo \esc_html__('Uhrzeit', 'cmx-misbuero'); ?></label>
					<input type="time" class="widefat" id="cmx_carent_schadenprotokoll_uhrzeit" name="cmx_carent_schadenprotokoll_uhrzeit" value="<?php echo \esc_attr($uhrzeit); ?>">
				</div>
			</div>

			<div class="cmx-carent-schaden-rows" id="cmx-carent-schadenprotokoll-rows">
				<?php foreach ($rows as $index => $row) : ?>
					<?php echo cmx_carent_schadenprotokoll_row_markup((string) $index, (array) $row, $term_options); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button" id="cmx-carent-schadenprotokoll-add"><?php echo \esc_html__('Weitere Schäden hinzufügen', 'cmx-misbuero'); ?></button></p>

			<div class="cmx-carent-schaden-extra">
				<div>
					<label><?php echo \esc_html__('Weitere Beteiligte', 'cmx-misbuero'); ?></label>
					<div class="cmx-carent-schaden-radio">
						<label><input type="radio" name="cmx_carent_schadenprotokoll_weitere_beteiligte" value="nein"<?php \checked($weitere_beteiligte !== 'ja'); ?>><?php echo \esc_html__('Nein', 'cmx-misbuero'); ?></label>
						<label><input type="radio" name="cmx_carent_schadenprotokoll_weitere_beteiligte" value="ja"<?php \checked($weitere_beteiligte, 'ja'); ?>><?php echo \esc_html__('Ja', 'cmx-misbuero'); ?></label>
					</div>
					<label for="cmx_carent_schadenprotokoll_weitere_angaben"><?php echo \esc_html__('Weitere Angaben', 'cmx-misbuero'); ?></label>
					<textarea class="widefat" id="cmx_carent_schadenprotokoll_weitere_angaben" name="cmx_carent_schadenprotokoll_weitere_angaben" rows="4"><?php echo \esc_textarea($weitere_angaben); ?></textarea>
				</div>
				<div>
					<label><?php echo \esc_html__('Unfallprotokoll', 'cmx-misbuero'); ?></label>
					<div class="cmx-carent-schaden-radio">
						<label><input type="radio" name="cmx_carent_schadenprotokoll_unfallprotokoll" value=""<?php \checked($unfallprotokoll, ''); ?>><?php echo \esc_html__('Keine Angabe', 'cmx-misbuero'); ?></label>
						<label><input type="radio" name="cmx_carent_schadenprotokoll_unfallprotokoll" value="nein"<?php \checked($unfallprotokoll, 'nein'); ?>><?php echo \esc_html__('Nein', 'cmx-misbuero'); ?></label>
						<label><input type="radio" name="cmx_carent_schadenprotokoll_unfallprotokoll" value="ja"<?php \checked($unfallprotokoll, 'ja'); ?>><?php echo \esc_html__('Ja', 'cmx-misbuero'); ?></label>
					</div>
					<label for="cmx_carent_schadenprotokoll_anerkennung"><?php echo \esc_html__('Anerkennung', 'cmx-misbuero'); ?></label>
					<select class="widefat" id="cmx_carent_schadenprotokoll_anerkennung" name="cmx_carent_schadenprotokoll_anerkennung">
						<option value=""<?php \selected($anerkennung, ''); ?>><?php echo \esc_html__('Keine Angabe', 'cmx-misbuero'); ?></option>
						<option value="anerkenne"<?php \selected($anerkennung, 'anerkenne'); ?>><?php echo \esc_html__('Anerkenne', 'cmx-misbuero'); ?></option>
						<option value="nicht_anerkenne"<?php \selected($anerkennung, 'nicht_anerkenne'); ?>><?php echo \esc_html__('Nicht anerkenne', 'cmx-misbuero'); ?></option>
					</select>
				</div>
			</div>
			<template id="cmx-carent-schadenprotokoll-template"><?php echo $template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		</div>
		<script>
		(function(){
			var root = document.getElementById("cmx-carent-schadenprotokoll");
			if (!root || root.dataset.bound === "1") return;
			root.dataset.bound = "1";
			var rows = document.getElementById("cmx-carent-schadenprotokoll-rows");
			var add = document.getElementById("cmx-carent-schadenprotokoll-add");
			var template = document.getElementById("cmx-carent-schadenprotokoll-template");

			function nextIndex(){
				return String(Date.now()) + String(Math.floor(Math.random() * 1000));
			}

			function bindRemove(row){
				var button = row && row.querySelector ? row.querySelector(".cmx-carent-schaden-row-remove") : null;
				if (!button || button.dataset.bound === "1") return;
				button.dataset.bound = "1";
				button.addEventListener("click", function(event){
					event.preventDefault();
					if (rows && rows.children.length > 1) {
						row.remove();
						return;
					}
					row.querySelectorAll("input, textarea, select").forEach(function(field){
						if (field.type === "checkbox" || field.type === "radio") {
							field.checked = false;
						} else {
							field.value = "";
						}
					});
				});
			}

			root.querySelectorAll(".cmx-carent-schaden-row").forEach(bindRemove);
			if (add && rows && template) {
				add.addEventListener("click", function(event){
					event.preventDefault();
					var index = nextIndex();
					var html = String(template.innerHTML || "").replace(/__INDEX__/g, index);
					var wrap = document.createElement("div");
					wrap.innerHTML = html;
					var row = wrap.firstElementChild;
					if (!row) return;
					rows.appendChild(row);
					bindRemove(row);
				});
			}
		})();
		</script>
		<?php
	}
}

\add_action('add_meta_boxes', function (): void {
	$taxonomy = cmx_carent_schaden_taxonomy();
	if ($taxonomy !== '') {
		\remove_meta_box($taxonomy . 'div', 'carent', 'side');
		\remove_meta_box('tagsdiv-' . $taxonomy, 'carent', 'side');
	}

	\add_meta_box(
		'cmx_carent_schadenprotokoll_box',
		\__('Schadensprotokoll', 'cmx-misbuero'),
		static function (\WP_Post $post): void {
			cmx_carent_render_schadenprotokoll_metabox($post);
		},
		'carent',
		'normal',
		'default'
	);
});

\add_action('save_post_carent', function (int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'carent') {
		return;
	}
	if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!isset($_POST['cmx_carent_schadenprotokoll_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_carent_schadenprotokoll_nonce']), 'cmx_carent_schadenprotokoll_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$ort = isset($_POST['cmx_carent_schadenprotokoll_ort'])
		? \sanitize_text_field((string) \wp_unslash($_POST['cmx_carent_schadenprotokoll_ort']))
		: '';
	$datum = isset($_POST['cmx_carent_schadenprotokoll_datum'])
		? \trim((string) \wp_unslash($_POST['cmx_carent_schadenprotokoll_datum']))
		: '';
	$uhrzeit = isset($_POST['cmx_carent_schadenprotokoll_uhrzeit'])
		? \trim((string) \wp_unslash($_POST['cmx_carent_schadenprotokoll_uhrzeit']))
		: '';
	$weitere_beteiligte = isset($_POST['cmx_carent_schadenprotokoll_weitere_beteiligte'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_carent_schadenprotokoll_weitere_beteiligte']))
		: 'nein';
	$weitere_angaben = isset($_POST['cmx_carent_schadenprotokoll_weitere_angaben'])
		? \trim((string) \sanitize_textarea_field(\wp_unslash($_POST['cmx_carent_schadenprotokoll_weitere_angaben'])))
		: '';
	$unfallprotokoll = isset($_POST['cmx_carent_schadenprotokoll_unfallprotokoll'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_carent_schadenprotokoll_unfallprotokoll']))
		: '';
	$anerkennung = isset($_POST['cmx_carent_schadenprotokoll_anerkennung'])
		? \sanitize_key((string) \wp_unslash($_POST['cmx_carent_schadenprotokoll_anerkennung']))
		: '';

	$datum = \preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) ? $datum : '';
	$uhrzeit = \preg_match('/^\d{2}:\d{2}$/', $uhrzeit) ? $uhrzeit : '';
	$weitere_beteiligte = $weitere_beteiligte === 'ja' ? 'ja' : 'nein';
	$unfallprotokoll = \in_array($unfallprotokoll, ['ja', 'nein'], true) ? $unfallprotokoll : '';
	$anerkennung = \in_array($anerkennung, ['anerkenne', 'nicht_anerkenne'], true) ? $anerkennung : '';

	foreach ([
		CMX_CARENT_SCHADENPROTOKOLL_ORT_META => $ort,
		CMX_CARENT_SCHADENPROTOKOLL_DATUM_META => $datum,
		CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META => $uhrzeit,
	] as $meta_key => $value) {
		if ($value === '') {
			\delete_post_meta($post_id, $meta_key);
		} else {
			\update_post_meta($post_id, $meta_key, $value);
		}
	}

	if ($weitere_beteiligte === 'ja') {
		\update_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META, 'ja');
	} else {
		\delete_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META);
	}

	if ($weitere_beteiligte === 'ja' && $weitere_angaben !== '') {
		\update_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META, $weitere_angaben);
	} else {
		\delete_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META);
	}

	if ($weitere_beteiligte === 'ja' && $unfallprotokoll !== '') {
		\update_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META, $unfallprotokoll);
	} else {
		\delete_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META);
	}

	if ($anerkennung !== '') {
		\update_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META, $anerkennung);
	} else {
		\delete_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META);
	}

	$raw_rows = isset($_POST['cmx_carent_schadenprotokoll_rows']) ? \wp_unslash($_POST['cmx_carent_schadenprotokoll_rows']) : [];
	$taxonomy = cmx_carent_schaden_taxonomy();
	$rows = [];
	$term_ids = [];

	foreach ((array) $raw_rows as $raw_row) {
		if (!\is_array($raw_row)) {
			continue;
		}

		$term_id = isset($raw_row['term_id']) ? (int) $raw_row['term_id'] : 0;
		$note = isset($raw_row['note']) ? \trim((string) \sanitize_textarea_field($raw_row['note'])) : '';
		$fotos_gemacht = !empty($raw_row['fotos_gemacht']);
		$kosten = cmx_carent_schadenprotokoll_sanitize_cost($raw_row['kosten'] ?? '');

		if ($term_id > 0 && $taxonomy !== '') {
			$term = \get_term($term_id, $taxonomy);
			if (!$term || \is_wp_error($term)) {
				$term_id = 0;
			}
		}

		if ($term_id <= 0 && $note === '' && !$fotos_gemacht && $kosten === '') {
			continue;
		}

		$rows[] = [
			'term_id' => $term_id,
			'note' => $note,
			'fotos_gemacht' => $fotos_gemacht,
			'kosten' => $kosten,
		];

		if ($term_id > 0) {
			$term_ids[] = $term_id;
		}
	}

	if ($rows === []) {
		\delete_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_ROWS_META);
	} else {
		\update_post_meta($post_id, CMX_CARENT_SCHADENPROTOKOLL_ROWS_META, $rows);
	}

	if ($taxonomy !== '') {
		$term_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $term_ids))));
		\wp_set_post_terms($post_id, $term_ids, $taxonomy, false);
	}
}, 10, 2);
