<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


// Define: Custom-Post-Type based on DIR
register_post_type(basename(__DIR__), ['labels' => ['name' => cmx_sani_key(basename(__DIR__), 'title'), 'singular_name' => cmx_sani_key(basename(__DIR__), 'title'), 'add_new_item' => 'Hinzufügen', 'edit_item' => 'Bearbeiten',],
	'menu_position' => 10, 'supports' => ['title'], 'public' => true, 'menu_icon' => 'dashicons-businessman', 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => ['slug' => basename(__DIR__)],
]);


// Define: CONST 4 @ll Taxos
define(__NAMESPACE__ . '\\CMX_TAX_'.strtoupper(basename(__DIR__)),'Kategorien,Telefone,EMails,Stufen,Länder');


// Define: CONST 4 each Taxo
cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), CMX_TAX_KONTAKTE);
// cmx_const_taxos(strtoupper(basename(__DIR__)),basename(__DIR__), constant('CMX_TAX_'.strtoupper(basename(__DIR__))));


// Create: @ll Taxos
\add_action('init', function () {
	cmx_create_taxo(basename(__DIR__), 'Kategorie', 'Kategorien');
	cmx_create_taxo(basename(__DIR__), 'Telefon', 'Telefone', false);
	cmx_create_taxo(basename(__DIR__), 'EMail', 'EMails', false);
	cmx_create_taxo(basename(__DIR__), 'Stufe', 'Stufen', false);
	cmx_create_taxo(basename(__DIR__), 'Land', 'Länder', false);
}, 15);


// Refill: Taxo with defaults if removed
\add_action('admin_init', function () {
	cmx_seed_taxo(cmx_sani_key(basename(__DIR__),'title'),CMX_TAX_KONTAKTE);
});

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_notice_category_taxonomies')) {
	function cmx_kontakte_notice_category_taxonomies(): array {
		$post_type = basename(__DIR__);
		$candidates = ['kontakte_kategorien', 'kontakte_kategorie', 'kundenkategorie', 'kontakt_kategorie'];
		$object_taxes = (array) \get_object_taxonomies($post_type, 'names');
		foreach ($object_taxes as $tax) {
			$tax = (string) $tax;
			if ($tax === '') {
				continue;
			}
			if (\stripos($tax, 'kategorie') !== false) {
				$candidates[] = $tax;
			}
		}
		$candidates = \array_values(\array_unique(\array_filter(\array_map('strval', $candidates))));
		$taxes = [];
		foreach ($candidates as $tax) {
			if (\taxonomy_exists($tax)) {
				$taxes[] = $tax;
			}
		}
		return $taxes;
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_kontakte_has_trustee_contact')) {
	function cmx_kontakte_notice_first_email(int $post_id): string {
		$email = \sanitize_email((string) \get_post_meta($post_id, '_cmx_email_1', true));
		if (\is_email($email)) {
			return $email;
		}
		return '';
	}

	function cmx_kontakte_has_contact_type_with_email(array $queries): bool {
		static $cache = [];

		$key = \md5((string) \wp_json_encode($queries));
		if (isset($cache[$key])) {
			return (bool) $cache[$key];
		}

		$post_type = basename(__DIR__);
		$taxes = cmx_kontakte_notice_category_taxonomies();
		if (empty($taxes)) {
			$cache[$key] = false;
			return false;
		}

		foreach ($taxes as $tax) {
			foreach ($queries as $query) {
				$ids = \get_posts([
					'post_type' => $post_type,
					'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
					'posts_per_page' => 300,
					'fields' => 'ids',
					'no_found_rows' => true,
					'suppress_filters' => true,
					'tax_query' => [[
						'taxonomy' => $tax,
						'field' => (string) ($query['field'] ?? 'slug'),
						'terms' => (array) ($query['terms'] ?? []),
					]],
				]);
				if (empty($ids)) {
					continue;
				}
				foreach ((array) $ids as $post_id) {
					$post_id = (int) $post_id;
					if ($post_id <= 0) {
						continue;
					}
					if (cmx_kontakte_notice_first_email($post_id) !== '') {
						$cache[$key] = true;
						return true;
					}
				}
			}
		}

		$cache[$key] = false;
		return false;
	}

	function cmx_kontakte_has_trustee_contact(): bool {
		return cmx_kontakte_has_contact_type_with_email([
			['field' => 'slug', 'terms' => ['treuhaender', 'treuhander', 'treuhänder']],
			['field' => 'name', 'terms' => ['Treuhänder', 'Treuhaender', 'Treuhander']],
		]);
	}

	function cmx_kontakte_has_das_bin_ich_contact(): bool {
		return cmx_kontakte_has_contact_type_with_email([
			['field' => 'slug', 'terms' => ['das-bin-ich', 'ich']],
			['field' => 'name', 'terms' => ['Das bin ich', 'Ich']],
		]);
	}
}

\add_action('all_admin_notices', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen) {
		return;
	}
	$post_type = basename(__DIR__);
	$screen_post_type = (string) ($screen->post_type ?? '');
	$screen_id = (string) ($screen->id ?? '');
	$get_post_type = isset($_GET['post_type']) ? \sanitize_key((string) $_GET['post_type']) : '';
	$is_kontakte_screen = (
		$screen_post_type === $post_type
		|| $get_post_type === $post_type
		|| \strpos($screen_id, $post_type) !== false
	);
	if (!$is_kontakte_screen) {
		return;
	}

	$needs_trustee = !cmx_kontakte_has_trustee_contact();
	$needs_das_bin_ich = !cmx_kontakte_has_das_bin_ich_contact();

	if ($needs_das_bin_ich) {
		$new_self_url = \add_query_arg(
			[
				'post_type' => basename(__DIR__),
				'cmx_prefill_contact_type' => 'das-bin-ich',
			],
			\admin_url('post-new.php')
		);
		echo '<div class="notice notice-warning"><p><strong>Hinweis:</strong> Bitte mindestens einen Kontakt mit Kategorie <strong>Das bin ich</strong> anlegen. Dein eigener Kontakt muss komplett ausgefüllt werden. Inkl. E-Mail, Adresse und Firmenlogo etc. <a href="' . \esc_url($new_self_url) . '" class="button button-secondary" style="margin-left:8px;">„Das bin ich“-Kontakt anlegen</a></p></div>';
	}

	if ($needs_trustee) {
		$new_trustee_url = \add_query_arg(
			[
				'post_type' => basename(__DIR__),
				'cmx_prefill_contact_type' => 'treuhaender',
			],
			\admin_url('post-new.php')
		);
		echo '<div class="notice notice-warning"><p><strong>Hinweis:</strong> Bitte mindestens einen Kontakt mit Kategorie <strong>Treuhänder</strong> anlegen. Der Treuhänder muss zwingend eine gültige <strong>E-Mail 1</strong> hinterlegt haben. <a href="' . \esc_url($new_trustee_url) . '" class="button button-secondary" style="margin-left:8px;">Treuhänder-Kontakt anlegen</a></p></div>';
	}
});

\add_action('admin_footer-post-new.php', function (): void {
	$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen) {
		return;
	}
	if ((string) ($screen->post_type ?? '') !== basename(__DIR__)) {
		return;
	}
	$prefill_type = isset($_GET['cmx_prefill_contact_type']) ? \sanitize_key((string) $_GET['cmx_prefill_contact_type']) : '';
	if ($prefill_type === '' && !empty($_GET['cmx_prefill_trustee'])) {
		$prefill_type = 'treuhaender';
	}
	if (!\in_array($prefill_type, ['treuhaender', 'das-bin-ich', 'ich'], true)) {
		return;
	}
	?>
	<script>
	(function(){
		var prefillType = <?php echo \wp_json_encode($prefill_type); ?>;
		function normalize(text){
			return String(text || '').toLowerCase().replace(/\s+/g, ' ').trim();
		}
		function isTargetLabel(text){
			if (prefillType === 'treuhaender') {
				return text.indexOf('treuh') !== -1;
			}
			if (prefillType === 'das-bin-ich' || prefillType === 'ich') {
				return text.indexOf('das bin ich') !== -1 || text === 'ich';
			}
			return false;
		}
		function markContactType(){
			var labels = document.querySelectorAll('.categorydiv label, .taxonomydiv label, #side-sortables label, #normal-sortables label');
			var found = false;
			for (var i = 0; i < labels.length; i++) {
				var label = labels[i];
				var text = normalize(label.textContent || '');
				if (!isTargetLabel(text)) continue;
				var cb = label.querySelector('input[type="checkbox"]');
				if (!cb) continue;
				cb.checked = true;
				cb.dispatchEvent(new Event('change', { bubbles: true }));
				found = true;
			}
			return found;
		}

		if (markContactType()) return;
		var tries = 0;
		var timer = setInterval(function(){
			tries++;
			if (markContactType() || tries > 25) {
				clearInterval(timer);
			}
		}, 120);
	})();
	</script>
	<?php
});


// Define: Const 4 @ll CPT Fields
// cmx_define_meta_constants(basename(__DIR__), ['umsatz']);


// Include: @ll metaboxes
cmx_require_files(__DIR__,'stammdaten,kommunikation,adressen,infos,admincolumns,stufen,exports,imports,sichern,vcards,umsatz');
