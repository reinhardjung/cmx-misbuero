<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Shortcode: [cmx_artikel_bild id="123" alt="" class=""] → gibt das lokale Artikelbild aus
\add_shortcode('cmx_artikel_bild', function ($atts = []) {
	$atts = shortcode_atts([
		'id'    => 0,
		'alt'   => '',
		'class' => '',
	], $atts, 'cmx_artikel_bild');

	$post_id = (int) ($atts['id'] ?: get_the_ID());
	if (!$post_id || get_post_type($post_id) !== 'artikel') return '';

	$url = (string) get_post_meta($post_id, '_cmx_local_image_artikel_url', true);
	if ($url === '') return '';

	$alt_text = trim($atts['alt']) !== '' ? $atts['alt'] : get_the_title($post_id);
	$extra    = trim(preg_replace('~[^a-z0-9_\\-\\s]+~i', ' ', (string) $atts['class']));
	$class    = trim('cmx-artikel-bild ' . $extra);

	return sprintf(
		'<img src="%s" alt="%s" class="%s" loading="lazy" width="500rem" />',
		esc_url($url),
		esc_attr($alt_text),
		esc_attr($class)
	);
});


// Shortcode: [cmx_artikel_preis id="123" currency="CHF"] → gibt den VK-Preis aus
\add_shortcode('cmx_artikel_preis', function ($atts = []) {
	$atts = shortcode_atts([
		'id'       => 0,
		'currency' => 'CHF',
	], $atts, 'cmx_artikel_preis');

	$post_id = (int) ($atts['id'] ?: get_the_ID());
	if (!$post_id || get_post_type($post_id) !== 'artikel') return '';

	$vk_meta_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
	$raw         = \get_post_meta($post_id, $vk_meta_key, true);
	$value       = (float) str_replace(',', '.', (string) $raw);
	if ($value <= 0) return '';

	$currency = trim((string) $atts['currency']);
	$number   = cmx_format_swiss_number($value, 2);

	return esc_html(trim($number . ' ' . $currency));
});


// Shortcode: [cmx_artikel_einheit id="123"] → zeigt die erste Einheit aus der Taxonomie
\add_shortcode('cmx_artikel_einheit', function ($atts = []) {
	$atts = shortcode_atts([
		'id' => 0,
	], $atts, 'cmx_artikel_einheit');

	$post_id = (int) ($atts['id'] ?: get_the_ID());
	if (!$post_id || get_post_type($post_id) !== 'artikel') return '';

	if (!\defined(__NAMESPACE__.'\\TAX_ARTIKEL_EINHEITEN')) {
		return '';
	}

	$terms = \wp_get_post_terms($post_id, TAX_ARTIKEL_EINHEITEN);
	if (\is_wp_error($terms) || empty($terms) || empty($terms[0]->name)) {
		return '';
	}

	return esc_html((string) $terms[0]->name);
});


// Shortcode: [cmx_artikel_selector placeholder="Artikel wählen"] → Dropdown mit gleicher Sortierung wie Katalog (Titel A–Z)
\add_shortcode('cmx_artikel_selector', function ($atts = []) {
	$atts = shortcode_atts([
		'placeholder' => '— Artikel wählen —',
		'orderby'     => 'title', // title|sku|price
		'order'       => 'ASC',
	], $atts, 'cmx_artikel_selector');

	$current_id = (int) get_the_ID();
	$order      = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';

	$sort = strtolower((string) $atts['orderby']);
	$orderby = 'title';
	$meta_key = null;
	if ($sort === 'sku') {
		$orderby  = 'meta_value';
		$meta_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_SKU') ? CMX_ARTIKEL_META_SKU : '_cmx_artikel_sku';
	} elseif ($sort === 'price') {
		$orderby  = 'meta_value_num';
		$meta_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk';
	}

	// Gleiche Filterung/Sortierung wie im Katalog
	// (nur verkaufbare und im Katalog sichtbare Artikel, Titel A–Z)
	$verkaufbar_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VERKAUFBAR')
		? CMX_ARTIKEL_META_VERKAUFBAR
		: '_cmx_artikel_verkaufbar';
	$katalog_key = \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_KATALOG')
		? CMX_ARTIKEL_META_KATALOG
		: '_cmx_artikel_katalog';

	$q = new \WP_Query([
		'post_type'      => 'artikel',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => $orderby,
		'order'          => $order,
		'meta_key'       => $meta_key,
		'fields'         => 'ids',
		'meta_query'     => [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[
					'key'     => $verkaufbar_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $verkaufbar_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $verkaufbar_key,
					'value'   => '0',
					'compare' => '=',
				],
				[
					'key'     => $verkaufbar_key,
					'value'   => 0,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
			[
				'relation' => 'OR',
				[
					'key'     => $katalog_key,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => $katalog_key,
					'value'   => '',
					'compare' => '=',
				],
				[
					'key'     => $katalog_key,
					'value'   => '1',
					'compare' => '=',
				],
				[
					'key'     => $katalog_key,
					'value'   => 1,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		],
	]);

	if (!$q->have_posts()) {
		return '';
	}

	ob_start();

	static $cmx_selector_js_done = false;

	echo '<div class="cmx-artikel-selector-wrap">';
	echo '<select class="cmx-artikel-selector">';
	$ph = trim($atts['placeholder']);
	if ($ph !== '') {
		echo '<option value="">' . esc_html($ph) . '</option>';
	}
	foreach ($q->posts as $id) {
		$title = get_the_title($id) ?: ('#' . $id);
		printf(
			'<option value="%s"%s>%s</option>',
			esc_url(get_permalink($id)),
			selected($current_id, $id, false),
			esc_html($title)
		);
	}
	echo '</select>';
	echo '</div>';

	if (!$cmx_selector_js_done) {
		$cmx_selector_js_done = true;
		?>
		<script>
		document.addEventListener('change', function(e){
			if (!e.target.classList.contains('cmx-artikel-selector')) return;
			var url = e.target.value;
			if (url) {
				window.location.href = url;
			}
		});
		</script>
		<?php
	}

	wp_reset_postdata();
	return ob_get_clean();
});


// Detail-Prev/Nächste Artikel gemäß ausgewählter Sortierung aus der Liste (cmx_sort/cmz_dir)
function cmx_artikel_adjacent_sort_conf(?\WP_Post $post = null): array {
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}

	$post = $post ?: get_post();
	$field = isset($_GET['cmx_sort']) ? strtolower((string) $_GET['cmx_sort']) : 'title';
	$dir   = strtoupper((string) ($_GET['cmx_dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

	$map = [
		'title' => ['type' => 'title'],
		'sku'   => ['type' => 'meta', 'meta_key' => \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_SKU') ? CMX_ARTIKEL_META_SKU : '_cmx_artikel_sku', 'meta_type' => 'string'],
		'price' => ['type' => 'meta', 'meta_key' => \defined(__NAMESPACE__.'\\CMX_ARTIKEL_META_VK') ? CMX_ARTIKEL_META_VK : '_cmx_artikel_vk', 'meta_type' => 'numeric'],
	];

	if (!isset($map[$field])) {
		$field = 'title';
	}

	$conf = [
		'field'     => $field,
		'dir'       => $dir,
		'type'      => $map[$field]['type'],
		'meta_key'  => $map[$field]['meta_key'] ?? null,
		'meta_type' => $map[$field]['meta_type'] ?? null,
	];

	$cache = $conf;
	return $conf;
}

\add_filter('get_previous_post_join', function ($join, $in_same_term, $excluded_terms, $taxonomy, $post) {
	if (is_admin() || !$post || $post->post_type !== 'artikel') {
		return $join;
	}
	$conf = cmx_artikel_adjacent_sort_conf($post);
	if ($conf['type'] !== 'meta' || !$conf['meta_key']) {
		return $join;
	}
	global $wpdb;
	return $join . $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s ", $conf['meta_key']);
}, 10, 5);

\add_filter('get_next_post_join', function ($join, $in_same_term, $excluded_terms, $taxonomy, $post) {
	if (is_admin() || !$post || $post->post_type !== 'artikel') {
		return $join;
	}
	$conf = cmx_artikel_adjacent_sort_conf($post);
	if ($conf['type'] !== 'meta' || !$conf['meta_key']) {
		return $join;
	}
	global $wpdb;
	return $join . $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s ", $conf['meta_key']);
}, 10, 5);

\add_filter('get_previous_post_where', function ($where, $in_same_term, $excluded_terms, $taxonomy, $post) {
	if (is_admin() || !$post || $post->post_type !== 'artikel') {
		return $where;
	}
	global $wpdb;
	$conf = cmx_artikel_adjacent_sort_conf($post);
	$dir  = $conf['dir'];
	$op   = ($dir === 'ASC') ? '<' : '>';

	if ($conf['type'] === 'meta' && $conf['meta_key']) {
		$current = get_post_meta($post->ID, $conf['meta_key'], true);
		if ($conf['meta_type'] === 'numeric') {
			$current = (float) str_replace(',', '.', (string) $current);
			return $wpdb->prepare(
				"WHERE p.post_type = %s AND p.post_status = 'publish' AND CAST(COALESCE(pm.meta_value, '0') AS DECIMAL(20,6)) {$op} %f",
				$post->post_type,
				$current
			);
		}
		$current = (string) $current;
		return $wpdb->prepare(
			"WHERE p.post_type = %s AND p.post_status = 'publish' AND COALESCE(pm.meta_value, '') {$op} %s",
			$post->post_type,
			$current
		);
	}

	return $wpdb->prepare(
		"WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_title {$op} %s",
		$post->post_type,
		$post->post_title
	);
}, 10, 5);

\add_filter('get_previous_post_sort', function ($sort) {
	if (is_admin()) {
		return $sort;
	}
	global $post;
	if (!$post || $post->post_type !== 'artikel') {
		return $sort;
	}
	$conf = cmx_artikel_adjacent_sort_conf($post);
	$dir  = $conf['dir'] === 'DESC' ? 'ASC' : 'DESC'; // prev goes opposite

	if ($conf['type'] === 'meta') {
		return "ORDER BY pm.meta_value {$dir}, p.ID {$dir} LIMIT 1";
	}

	return "ORDER BY p.post_title {$dir}, p.ID {$dir} LIMIT 1";
}, 10, 1);

\add_filter('get_next_post_where', function ($where, $in_same_term, $excluded_terms, $taxonomy, $post) {
	if (is_admin() || !$post || $post->post_type !== 'artikel') {
		return $where;
	}
	global $wpdb;
	$conf = cmx_artikel_adjacent_sort_conf($post);
	$dir  = $conf['dir'];
	$op   = ($dir === 'ASC') ? '>' : '<';

	if ($conf['type'] === 'meta' && $conf['meta_key']) {
		$current = get_post_meta($post->ID, $conf['meta_key'], true);
		if ($conf['meta_type'] === 'numeric') {
			$current = (float) str_replace(',', '.', (string) $current);
			return $wpdb->prepare(
				"WHERE p.post_type = %s AND p.post_status = 'publish' AND CAST(COALESCE(pm.meta_value, '0') AS DECIMAL(20,6)) {$op} %f",
				$post->post_type,
				$current
			);
		}
		$current = (string) $current;
		return $wpdb->prepare(
			"WHERE p.post_type = %s AND p.post_status = 'publish' AND COALESCE(pm.meta_value, '') {$op} %s",
			$post->post_type,
			$current
		);
	}

	return $wpdb->prepare(
		"WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_title {$op} %s",
		$post->post_type,
		$post->post_title
	);
}, 10, 5);

\add_filter('get_next_post_sort', function ($sort) {
	if (is_admin()) {
		return $sort;
	}
	global $post;
	if (!$post || $post->post_type !== 'artikel') {
		return $sort;
	}
	$conf = cmx_artikel_adjacent_sort_conf($post);
	$dir  = $conf['dir'] === 'DESC' ? 'DESC' : 'ASC';

	if ($conf['type'] === 'meta') {
		return "ORDER BY pm.meta_value {$dir}, p.ID {$dir} LIMIT 1";
	}

	return "ORDER BY p.post_title {$dir}, p.ID {$dir} LIMIT 1";
}, 10, 1);

// CMX_TAX_BELEGE_KATEGORIEN?
// cmx_show_consts(); exit;
