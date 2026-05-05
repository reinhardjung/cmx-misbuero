<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_templates_option_key')) {
	function cmx_buchungen_templates_option_key(): string {
		return 'buchungen_templates';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_headline_option_key')) {
	function cmx_buchungen_headline_option_key(): string {
		return 'buchungen_headline';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_subline_option_key')) {
	function cmx_buchungen_subline_option_key(): string {
		return 'buchungen_subline';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_default_headline')) {
	function cmx_buchungen_default_headline(): string {
		return 'Online Buchung';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_default_subline')) {
	function cmx_buchungen_default_subline(): string {
		return 'Leistung wählen, freien Termin aussuchen und direkt buchen.';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_setting_text')) {
	function cmx_buchungen_setting_text(string $key, string $default): string {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$value = \trim((string) ($options[$key] ?? ''));
		return $value !== '' ? $value : $default;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_colors')) {
	function cmx_buchungen_template_colors(): array {
		return ['#2563eb', '#16a34a', '#f97316', '#06b6d4', '#9333ea', '#dc2626'];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_period_options')) {
	function cmx_buchungen_template_period_options(): array {
		return [
			'all' => 'Ganzer Tag',
			'morning' => 'Vormittags',
			'afternoon' => 'Nachmittags',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_unit_options')) {
	function cmx_buchungen_template_unit_options(): array {
		return [
			'minutes' => 'Minuten',
			'days' => 'Tage',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_weekday_options')) {
	function cmx_buchungen_template_weekday_options(): array {
		return [
			1 => 'Mo',
			2 => 'Di',
			3 => 'Mi',
			4 => 'Do',
			5 => 'Fr',
			6 => 'Sa',
			7 => 'So',
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_default_weekdays')) {
	function cmx_buchungen_template_default_weekdays(): array {
		return [1, 2, 3, 4, 5];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_sanitize_weekdays')) {
	function cmx_buchungen_template_sanitize_weekdays($weekdays): array {
		if (!\is_array($weekdays)) {
			return cmx_buchungen_template_default_weekdays();
		}

		$clean = [];
		foreach ($weekdays as $weekday) {
			$weekday = (int) $weekday;
			if ($weekday >= 1 && $weekday <= 7) {
				$clean[] = $weekday;
			}
		}

		$clean = \array_values(\array_unique($clean));
		\sort($clean, \SORT_NUMERIC);
		return $clean !== [] ? $clean : cmx_buchungen_template_default_weekdays();
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_allows_date')) {
	function cmx_buchungen_template_allows_date(array $template, string $date): bool {
		$date = \function_exists(__NAMESPACE__ . '\\cmx_buchungen_sanitize_date')
			? cmx_buchungen_sanitize_date($date)
			: \trim($date);
		if ($date === '') {
			return false;
		}

		$weekday = (int) \wp_date('N', \strtotime($date));
		$weekdays = cmx_buchungen_template_sanitize_weekdays($template['weekdays'] ?? []);
		return \in_array($weekday, $weekdays, true);
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_period_allows_time')) {
	function cmx_buchungen_template_period_allows_time(string $period, string $time): bool {
		$period = isset(cmx_buchungen_template_period_options()[$period]) ? $period : 'all';
		if ($period === 'all') {
			return true;
		}

		$parts = \explode(':', $time);
		$hour = isset($parts[0]) ? (int) $parts[0] : -1;
		if ($hour < 0 || $hour > 23) {
			return false;
		}

		return $period === 'morning' ? $hour < 12 : $hour >= 12;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_post_options')) {
	function cmx_buchungen_template_post_options(string $post_type): array {
		if (!\post_type_exists($post_type)) {
			return [];
		}

		$ids = \get_posts([
			'post_type' => $post_type,
			'post_status' => 'publish',
			'fields' => 'ids',
			'posts_per_page' => 300,
			'orderby' => 'title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);

		$options = [];
		foreach ((array) $ids as $id) {
			$id = (int) $id;
			$title = \trim((string) \get_the_title($id));
			if ($id > 0 && $title !== '') {
				$options[$id] = $title;
			}
		}

		return $options;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_article_options')) {
	function cmx_buchungen_article_options(): array {
		return cmx_buchungen_template_post_options('artikel');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_contact_options')) {
	function cmx_buchungen_contact_options(): array {
		return cmx_buchungen_template_post_options('kontakte');
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_search_field')) {
	function cmx_buchungen_template_search_field(string $name, int $current, string $placeholder, array $items, string $type): void {
		$title = $current > 0 ? \trim((string) \get_the_title($current)) : '';
		if ($current > 0 && $title === '') {
			$current = 0;
		}

		echo '<div class="cmx-buchungen-template-search" data-cmx-template-search="' . \esc_attr($type) . '" data-cmx-template-items="' . \esc_attr((string) \wp_json_encode($items, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)) . '">';
		echo '<input type="hidden" name="' . \esc_attr($name) . '" value="' . \esc_attr((string) $current) . '">';
		echo '<input type="search" class="cmx-buchungen-template-search-input" autocomplete="off" placeholder="' . \esc_attr($placeholder) . '" value="' . \esc_attr($title) . '">';
		echo '<ul class="cmx-buchungen-template-search-results" hidden></ul>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_default_template_row')) {
	function cmx_buchungen_default_template_row(int $index = 0): array {
		$colors = cmx_buchungen_template_colors();
		return [
			'enabled' => '0',
			'artikel_id' => 0,
			'kontakt_id' => 0,
			'label' => '',
			'title' => '',
			'unit' => 'minutes',
			'duration' => 60,
			'period' => 'all',
			'weekdays' => cmx_buchungen_template_default_weekdays(),
			'color' => $colors[$index % \count($colors)],
		];
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_sanitize_template_rows')) {
	function cmx_buchungen_sanitize_template_rows($rows): array {
		if (!\is_array($rows)) {
			return [];
		}

		$colors = cmx_buchungen_template_colors();
		$allowed_durations = [15, 30, 60];
		$clean = [];

		foreach ($rows as $index => $row) {
			if (!\is_array($row)) {
				continue;
			}

			$artikel_id = isset($row['artikel_id']) ? \max(0, (int) $row['artikel_id']) : 0;
			$kontakt_id = isset($row['kontakt_id']) ? \max(0, (int) $row['kontakt_id']) : 0;
			$label = isset($row['label']) ? \sanitize_text_field((string) $row['label']) : '';
			$title = isset($row['title']) ? \sanitize_text_field((string) $row['title']) : '';
			$unit = isset($row['unit']) ? \sanitize_key((string) $row['unit']) : 'minutes';
			if (!isset(cmx_buchungen_template_unit_options()[$unit])) {
				$unit = 'minutes';
			}
			$duration = isset($row['duration']) ? (int) $row['duration'] : 60;
			if ($unit === 'minutes' && !\in_array($duration, $allowed_durations, true)) {
				$duration = 60;
			}
			if ($unit === 'days') {
				$duration = \max(1, \min(60, $duration));
			}
			$period = isset($row['period']) ? \sanitize_key((string) $row['period']) : 'all';
			if (!isset(cmx_buchungen_template_period_options()[$period])) {
				$period = 'all';
			}
			$weekdays = cmx_buchungen_template_sanitize_weekdays($row['weekdays'] ?? []);

			$color = isset($row['color']) ? (string) $row['color'] : '';
			$color = \sanitize_hex_color($color) ?: $colors[\count($clean) % \count($colors)];
			$enabled = !empty($row['enabled']) ? '1' : '0';

			if ($enabled === '0' && $artikel_id <= 0 && $label === '' && $title === '') {
				continue;
			}

			$clean[] = [
				'enabled' => $enabled,
				'artikel_id' => $artikel_id,
				'kontakt_id' => $kontakt_id,
				'label' => $label,
				'title' => $title,
				'unit' => $unit,
				'duration' => $duration,
				'period' => $period,
				'weekdays' => $weekdays,
				'color' => $color,
			];

			if (\count($clean) >= 3) {
				break;
			}
		}

		return $clean;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_buchungen_template_rows')) {
	function cmx_buchungen_template_rows(bool $active_only = false): array {
		$options = (array) \get_option(CMX_SETTINGS_MAIN, []);
		$rows = cmx_buchungen_sanitize_template_rows($options[cmx_buchungen_templates_option_key()] ?? []);
		if (!$active_only) {
			return $rows;
		}

		return \array_values(\array_filter($rows, static function (array $row): bool {
			$artikel_id = (int) ($row['artikel_id'] ?? 0);
			return !empty($row['enabled'])
				&& $artikel_id > 0
				&& \get_post_type($artikel_id) === 'artikel'
				&& \get_post_status($artikel_id) === 'publish';
		}));
	}
}

\add_action('admin_init', function (): void {
	\add_settings_section(
		'cmx_sec_buchungen_templates',
		'Buchungsvorlagen',
		static function (): void {
			if (\function_exists(__NAMESPACE__ . '\\cmx_render_buchungen_texts_field')) {
				cmx_render_buchungen_texts_field();
			}
			echo '<p>Diese Vorlagen bestimmen, welche Kacheln auf der öffentlichen Buchungsseite auswählbar sind.</p>';
			if (\function_exists(__NAMESPACE__ . '\\cmx_render_buchungen_templates_field')) {
				cmx_render_buchungen_templates_field();
			}
		},
		'cmx_tab_buchungen'
	);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_buchungen_texts_field')) {
	function cmx_render_buchungen_texts_field(): void {
		$option = CMX_SETTINGS_MAIN;
		$headline_key = cmx_buchungen_headline_option_key();
		$subline_key = cmx_buchungen_subline_option_key();
		$headline = cmx_buchungen_setting_text($headline_key, cmx_buchungen_default_headline());
		$subline = cmx_buchungen_setting_text($subline_key, cmx_buchungen_default_subline());

		echo '<input type="hidden" name="' . \esc_attr($option) . '[buchungen_texts_present]" value="1">';
		echo '<div class="cmx-buchungen-text-fields" style="display:grid;grid-template-columns:minmax(220px,420px) minmax(320px,640px);gap:12px 16px;align-items:end;max-width:1080px;margin:8px 0 22px;">';
		echo '<label style="display:flex;flex-direction:column;gap:6px;font-weight:700;">Titel<input type="text" name="' . \esc_attr($option . '[' . $headline_key . ']') . '" value="' . \esc_attr($headline) . '" placeholder="' . \esc_attr(cmx_buchungen_default_headline()) . '" style="width:100%;"></label>';
		echo '<label style="display:flex;flex-direction:column;gap:6px;font-weight:700;">Unterzeile<input type="text" name="' . \esc_attr($option . '[' . $subline_key . ']') . '" value="' . \esc_attr($subline) . '" placeholder="' . \esc_attr(cmx_buchungen_default_subline()) . '" style="width:100%;"></label>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_buchungen_templates_field')) {
	function cmx_render_buchungen_templates_field(): void {
		$rows = cmx_buchungen_template_rows(false);
		$rows = \array_slice($rows, 0, 3);
		while (\count($rows) < 3) {
			$rows[] = cmx_buchungen_default_template_row(\count($rows));
		}

		$articles = cmx_buchungen_article_options();
		$contacts = cmx_buchungen_contact_options();
		$key = cmx_buchungen_templates_option_key();
		$option = CMX_SETTINGS_MAIN;
		$units = cmx_buchungen_template_unit_options();
		$periods = cmx_buchungen_template_period_options();
		$weekday_options = cmx_buchungen_template_weekday_options();

		echo '<input type="hidden" name="' . \esc_attr($option) . '[buchungen_templates_present]" value="1">';
		echo '<style>
			.cmx-buchungen-template-table{width:100%;max-width:1720px;border-collapse:separate;border-spacing:0 10px}
			.cmx-buchungen-template-table th{padding:0 8px 4px;text-align:left;color:#1d2327;font-weight:700}
			.cmx-buchungen-template-table td{padding:10px 8px;background:#fff;border-top:1px solid #dcdcde;border-bottom:1px solid #dcdcde;vertical-align:middle}
			.cmx-buchungen-template-table td:first-child{border-left:1px solid #dcdcde;border-radius:8px 0 0 8px}
			.cmx-buchungen-template-table td:last-child{border-right:1px solid #dcdcde;border-radius:0 8px 8px 0}
			.cmx-buchungen-template-table select,.cmx-buchungen-template-table input[type=text],.cmx-buchungen-template-table input[type=number]{width:100%;max-width:240px}
			.cmx-buchungen-template-search{position:relative;min-width:180px;max-width:240px}
			.cmx-buchungen-template-search-input{width:100%;max-width:240px;border:1px solid #ccd0d4;border-radius:8px;background:#fff;padding:7px 9px;font:inherit}
			.cmx-buchungen-template-search-results{position:absolute;z-index:100002;left:0;right:0;top:100%;max-height:240px;overflow:auto;margin:2px 0 0;padding:0;border:1px solid #ccd0d4;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(0,0,0,.12);list-style:none}
			.cmx-buchungen-template-search-results[hidden]{display:none}
			.cmx-buchungen-template-search-results li{margin:0;padding:8px 10px;cursor:pointer}
			.cmx-buchungen-template-search-results li.active,.cmx-buchungen-template-search-results li:hover{background:#e5f3ff}
			.cmx-buchungen-template-weekdays{display:flex;flex-wrap:nowrap;gap:8px;min-width:300px;max-width:none;white-space:nowrap}
			.cmx-buchungen-template-weekdays label{display:inline-flex;align-items:center;gap:3px;padding:0;border:0;background:transparent;font-size:12px;line-height:1.2}
			.cmx-buchungen-template-weekdays input{margin:0}
			.cmx-buchungen-template-note{max-width:760px;color:#646970}
		</style>';
		echo '<table class="cmx-buchungen-template-table"><thead><tr>';
		echo '<th>Aktiv</th><th>Artikel</th><th>Kontakt</th><th>Oberzeile</th><th>Kacheltitel</th><th>Einheit</th><th>Dauer</th><th>Zeitraum</th><th>Wochentage</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $index => $row) {
			$name = $option . '[' . $key . '][' . $index . ']';
			$artikel_id = (int) ($row['artikel_id'] ?? 0);
			$kontakt_id = (int) ($row['kontakt_id'] ?? 0);
			$unit = (string) ($row['unit'] ?? 'minutes');
			$duration = (int) ($row['duration'] ?? 60);
			$period = (string) ($row['period'] ?? 'all');
			$weekdays = cmx_buchungen_template_sanitize_weekdays($row['weekdays'] ?? []);
			echo '<tr>';
			echo '<td><input type="hidden" name="' . \esc_attr($name . '[enabled]') . '" value="0"><input type="checkbox" name="' . \esc_attr($name . '[enabled]') . '" value="1" ' . \checked(!empty($row['enabled']), true, false) . '></td>';
			echo '<td>';
			cmx_buchungen_template_search_field($name . '[artikel_id]', $artikel_id, 'Artikel suchen...', $articles, 'artikel');
			echo '</td>';
			echo '<td>';
			cmx_buchungen_template_search_field($name . '[kontakt_id]', $kontakt_id, 'Kontakt suchen...', $contacts, 'kontakt');
			echo '</td>';
			echo '<td><input type="text" name="' . \esc_attr($name . '[label]') . '" value="' . \esc_attr((string) ($row['label'] ?? '')) . '" placeholder="z.B. Beratung"></td>';
			echo '<td><input type="text" name="' . \esc_attr($name . '[title]') . '" value="' . \esc_attr((string) ($row['title'] ?? '')) . '" placeholder="leer = Artikeltitel"></td>';
			echo '<td><select name="' . \esc_attr($name . '[unit]') . '">';
			foreach ($units as $unit_key => $unit_label) {
				echo '<option value="' . \esc_attr($unit_key) . '" ' . \selected($unit, $unit_key, false) . '>' . \esc_html($unit_label) . '</option>';
			}
			echo '</select></td>';
			echo '<td><input type="number" min="1" max="60" step="1" name="' . \esc_attr($name . '[duration]') . '" value="' . \esc_attr((string) $duration) . '"></td>';
			echo '<td><select name="' . \esc_attr($name . '[period]') . '">';
			foreach ($periods as $period_key => $period_label) {
				echo '<option value="' . \esc_attr($period_key) . '" ' . \selected($period, $period_key, false) . '>' . \esc_html($period_label) . '</option>';
			}
			echo '</select></td>';
			echo '<td><div class="cmx-buchungen-template-weekdays">';
			foreach ($weekday_options as $weekday_key => $weekday_label) {
				echo '<label><input type="checkbox" name="' . \esc_attr($name . '[weekdays][]') . '" value="' . \esc_attr((string) $weekday_key) . '" ' . \checked(\in_array((int) $weekday_key, $weekdays, true), true, false) . '> ' . \esc_html($weekday_label) . '</label>';
			}
			echo '</div></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="cmx-buchungen-template-note">Nur aktive Zeilen mit ausgewähltem Artikel werden auf <a href="' . \esc_url(\home_url('/buchungen/')) . '" target="_blank" rel="noopener noreferrer">/buchungen/</a> angezeigt.</p>';
		echo '<script>
			(function(){
				function initSearch(root){
					if(!root || root.dataset.ready === "1") return;
					root.dataset.ready = "1";
					var hidden = root.querySelector("input[type=hidden]");
					var input = root.querySelector(".cmx-buchungen-template-search-input");
					var list = root.querySelector(".cmx-buchungen-template-search-results");
					var items = [];
					var matches = [];
					var active = -1;
					try{ var raw = JSON.parse(root.getAttribute("data-cmx-template-items") || "{}"); Object.keys(raw).forEach(function(id){ items.push({id:String(id), title:String(raw[id] || "")}); }); }catch(err){}
					function close(){
						list.hidden = true;
						list.innerHTML = "";
						matches = [];
						active = -1;
					}
					function choose(item){
						hidden.value = item ? item.id : "0";
						input.value = item ? item.title : "";
						close();
					}
					function clearAndShowChoices(){
						hidden.value = "0";
						input.value = "";
						hidden.dispatchEvent(new Event("change", {bubbles:true}));
						input.dispatchEvent(new Event("change", {bubbles:true}));
						render();
					}
					function setActive(index){
						if(!matches.length) return;
						active = (index + matches.length) % matches.length;
						Array.prototype.forEach.call(list.children, function(li, i){
							var isActive = i === active;
							li.classList.toggle("active", isActive);
							if(isActive && typeof li.scrollIntoView === "function"){
								li.scrollIntoView({block:"nearest"});
							}
						});
					}
					function render(){
						var q = input.value.trim().toLowerCase();
						list.innerHTML = "";
						matches = items.filter(function(item){ return q === "" || item.title.toLowerCase().indexOf(q) !== -1; }).slice(0, 25);
						active = -1;
						matches.forEach(function(item){
							var li = document.createElement("li");
							li.textContent = item.title;
							li.addEventListener("mousedown", function(ev){ ev.preventDefault(); choose(item); });
							list.appendChild(li);
						});
						list.hidden = matches.length === 0;
					}
					input.addEventListener("input", function(){ hidden.value = "0"; render(); });
					input.addEventListener("focus", render);
					input.addEventListener("blur", function(){ window.setTimeout(close, 150); });
					input.addEventListener("keydown", function(ev){
						if(ev.key === "ArrowDown"){
							ev.preventDefault();
							if(list.hidden) render();
							setActive(active + 1);
							return;
						}
						if(ev.key === "ArrowUp"){
							ev.preventDefault();
							if(list.hidden) render();
							setActive(active - 1);
							return;
						}
						if(ev.key === "Enter"){
							if(!list.hidden && active >= 0 && matches[active]){
								ev.preventDefault();
								choose(matches[active]);
							}
							return;
						}
						if(ev.key === "Escape"){
							ev.preventDefault();
							if(hidden.value !== "0" || input.value.trim() !== ""){
								clearAndShowChoices();
							}else{
								close();
								input.blur();
							}
						}
					});
				}
				document.querySelectorAll(".cmx-buchungen-template-search").forEach(initSearch);
			})();
		</script>';
	}
}

\add_filter('pre_update_option_' . CMX_SETTINGS_MAIN, function ($new, $old) {
	$new = \is_array($new) ? $new : [];
	if (!\array_key_exists('buchungen_texts_present', $new) && !\array_key_exists('buchungen_templates_present', $new) && !\array_key_exists(cmx_buchungen_templates_option_key(), $new)) {
		return $new;
	}

	if (\array_key_exists('buchungen_texts_present', $new)) {
		$new[cmx_buchungen_headline_option_key()] = \sanitize_text_field((string) ($new[cmx_buchungen_headline_option_key()] ?? ''));
		$new[cmx_buchungen_subline_option_key()] = \sanitize_text_field((string) ($new[cmx_buchungen_subline_option_key()] ?? ''));
		unset($new['buchungen_texts_present']);
	}

	$new[cmx_buchungen_templates_option_key()] = cmx_buchungen_sanitize_template_rows($new[cmx_buchungen_templates_option_key()] ?? []);
	unset($new['buchungen_templates_present']);

	return $new;
}, 20, 2);
