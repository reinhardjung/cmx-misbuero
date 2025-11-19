<?php namespace CLOUDMEISTER\CMX\LoginManager; defined('ABSPATH') or die('Oxytocin!');


class SettingsPage {

	/** @var string Menü-Slug / Screen-ID-Suffix */
	private const PAGE_SLUG = 'cmx-login-manager';

	/** @var string Settings-Group-Name (bleibt wie bei dir: cmx-settings-group) */
	private const SETTINGS_GROUP = 'cmx-settings-group';

	/** @var string|null */
	private $page_hook = null;

	public function __construct() {
		add_action('admin_menu', [$this, 'addSettingsPage']);
		add_action('admin_init', [$this, 'registerSettingsAndFields']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);   // Media + Color Picker
		add_action('login_enqueue_scripts', [$this, 'customizeLoginPage']);   // Login-Styles
		add_filter('login_headerurl', [$this, 'customLoginUrl']);             // Logo-Link


				// Optional redirect.php includieren, wenn Checkbox aktiv
		if (get_option('cmx_no_website')) {
			add_action('template_redirect', function() {
				if (is_front_page()) {
					wp_logout();
					wp_redirect(wp_login_url());
					exit;
				}
			});
		}

	}

	/* ---------------------------------
	 * Admin-Seite registrieren
	 * --------------------------------- */
	public function addSettingsPage(): void {
		$this->page_hook = add_options_page(
			'Login Manager',
			'Login Manager',
			'manage_options',
			self::PAGE_SLUG,
			[$this, 'renderSettingsPage']
		);
	}

	/* ---------------------------------
	 * Settings + Felder (Settings API)
	 * --------------------------------- */
	public function registerSettingsAndFields(): void {
		// Optionen registrieren (mit Sanitizern)
		register_setting(self::SETTINGS_GROUP, 'cmx_no_website', [
			'type' => 'boolean',
			'sanitize_callback' => function($v){ return $v ? 1 : 0; },
			'default' => 0,
		]);
		register_setting(self::SETTINGS_GROUP, 'cmx_color', [
			'type' => 'string',
			'sanitize_callback' => [$this, 'sanitizeHexColor'],
			'default' => '#15adcb',
		]);
		register_setting(self::SETTINGS_GROUP, 'cmx_focus_color', [
			'type' => 'string',
			'sanitize_callback' => [$this, 'sanitizeHexColor'],
			'default' => '#8d44ac',
		]);
		register_setting(self::SETTINGS_GROUP, 'cmx_logo_url', [
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => 'https://cloudmeister.ch/wp-content/uploads/cloudmeister.png',
		]);
		register_setting(self::SETTINGS_GROUP, 'cmx_logo_link', [
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => 'https://cloudmeister.ch/',
		]);

		// Sektion
		add_settings_section(
			'cmx_section_main',
			'Einstellungen',
			function(){ echo '<p>Login-Seite und Verhalten konfigurieren.</p>'; },
			self::PAGE_SLUG
		);

		// Felder
		add_settings_field(
			'cmx_no_website',
			'Ohne Website',
			[$this, 'fieldCheckboxNoWebsite'],
			self::PAGE_SLUG,
			'cmx_section_main'
		);

		add_settings_field(
			'cmx_color',
			'Primary Color',
			[$this, 'fieldColorPrimary'],
			self::PAGE_SLUG,
			'cmx_section_main'
		);

		add_settings_field(
			'cmx_focus_color',
			'Focus Color',
			[$this, 'fieldColorFocus'],
			self::PAGE_SLUG,
			'cmx_section_main'
		);

		add_settings_field(
			'cmx_logo_url',
			'URL',
			[$this, 'fieldLogoUrl'],
			self::PAGE_SLUG,
			'cmx_section_main'
		);

		add_settings_field(
			'cmx_logo_link',
			'Link',
			[$this, 'fieldLogoLink'],
			self::PAGE_SLUG,
			'cmx_section_main'
		);
	}

	/* ---------------------------------
	 * Feld-Renderer
	 * --------------------------------- */
	public function fieldCheckboxNoWebsite(): void {
		$val = (int) get_option('cmx_no_website', 0);
		?>
		<label>
			<input type="checkbox" name="cmx_no_website" value="1" <?php checked(1, $val, true); ?> />
			Ja / Nein
		</label>
		<p class="description">Wenn aktiv, wird zum <code>/login/</code> weitergeleitet (weg von der Homepage).</p>
		<?php
	}

	public function fieldColorPrimary(): void {
		$val = esc_attr(get_option('cmx_color', '#15adcb'));
		echo '<input type="text" class="cmx-color-field regular-text" name="cmx_color" value="'.$val.'" />';
	}

	public function fieldColorFocus(): void {
		$val = esc_attr(get_option('cmx_focus_color', '#8d44ac'));
		echo '<input type="text" class="cmx-color-field regular-text" name="cmx_focus_color" value="'.$val.'" />';
	}

	public function fieldLogoUrl(): void {
		$val = esc_attr(get_option('cmx_logo_url', ''));
		?>
		<input type="text" id="cmx_logo_url" name="cmx_logo_url" class="regular-text cmx-long-text-field" placeholder="https://cloudmeister.ch/wp-content/uploads/cloudmeister.png" value="<?php echo $val; ?>" />
		<button type="button" class="button" id="cmx_logo_button">Select Image</button>
		<p class="description">Wähle ein Bild aus der Mediathek oder füge eine URL ein.</p>
		<?php
	}

	public function fieldLogoLink(): void {
		$val = esc_attr(get_option('cmx_logo_link', 'https://cloudmeister.ch/'));
		echo '<input type="text" class="regular-text cmx-long-text-field" name="cmx_logo_link" placeholder="https://cloudmeister.ch/" value="'.$val.'" />';
	}

	/* ---------------------------------
	 * Assets nur auf eigener Settings-Seite
	 * --------------------------------- */
	public function enqueueAdminAssets($hook_suffix): void {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		$is_our_page = false;

		if ($this->page_hook && $hook_suffix === $this->page_hook) {
			$is_our_page = true;
		} elseif ($screen && isset($screen->id) && $screen->id === 'settings_page_'.self::PAGE_SLUG) {
			$is_our_page = true;
		}
		if (!$is_our_page) return;

		// jQuery, Media, Color Picker
		wp_enqueue_script('jquery');
		wp_enqueue_media();
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');

		// Inline-JS: Media-Frame + Color-Picker initialisieren
		$inline_js = <<<JS
(function($){
	var frame;
	var \$btn  = $('#cmx_logo_button');
	var \$inpt = $('#cmx_logo_url');

	if (\$btn.length && \$inpt.length) {
		\$btn.on('click', function(e){
			e.preventDefault();
			if (frame) { frame.open(); return; }

			frame = wp.media({
				title: 'Logo auswählen',
				button: { text: 'Übernehmen' },
				library: { type: ['image'] },
				multiple: false
			});

			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				if (att && att.url) {
					\$inpt.val(att.url).trigger('change');
				}
			});

			frame.open();
		});
	}

	$('.cmx-color-field').each(function(){
		var \$f = $(this);
		if (!\$f.val()) { \$f.val('#15adcb'); }
		if (typeof \$f.wpColorPicker === 'function') {
			\$f.wpColorPicker();
		}
	});
})(jQuery);
JS;
		wp_add_inline_script('wp-color-picker', $inline_js);

		// Kleines CSS für breite Inputs
		$inline_css = '.cmx-long-text-field{width:100%;max-width:600px}';
		wp_register_style('cmx-login-manager-inline', false);
		wp_enqueue_style('cmx-login-manager-inline');
		wp_add_inline_style('cmx-login-manager-inline', $inline_css);
	}


	/* ---------------------------------
	 * Render der Seite
	 * --------------------------------- */
	public function renderSettingsPage(): void {
		?>
		<div class="wrap">
			<h1>Login Manager</h1>

			<form method="post" action="options.php">
				<?php
				settings_fields(self::SETTINGS_GROUP);
				do_settings_sections(self::PAGE_SLUG);
				submit_button();
				?>
			</form>

			<hr />

			<form method="post">
				<?php submit_button('Zeige Benutzer, die ihr Passwort noch nicht geändert haben', 'primary', 'show_unupdated_users', false); ?>
			</form>

			<?php if (isset($_POST['show_unupdated_users'])): ?>
				<div class="wrap">
					<ul>
						<?php
						$users = get_users();
						foreach ($users as $user) {
							$password_changed = (bool) get_user_meta($user->ID, 'password_changed', true);
							if (!$password_changed) {
								echo '<li><b>' . (int)$user->ID . ' ' . esc_html($user->user_login) . '</b> <a href="' .
									esc_url(admin_url('user-edit.php?user_id=' . (int)$user->ID)) . '" target="_blank">' .
									esc_html($user->display_name) . '</a> (<a href="mailto:' .
									esc_attr($user->user_email) . '?subject=Bitte aktualisieren Sie ihr Passwort!&body=Bitte aktualisieren Sie -dringend- ihr Passwort! Fragen unter 0787 222 444 an Reinhard Jung (CLOUD Meister)">' .
									esc_html($user->user_email) . '</a>)</li>';
							}
						}
						?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------
	 * Frontend: Login anpassen
	 * --------------------------------- */
	public function customizeLoginPage(): void {
		$login_color = esc_html(get_option('cmx_color', '#15adcb'));
		$focus_color = esc_html(get_option('cmx_focus_color', '#8d44ac'));
		$logo_url    = esc_url(get_option('cmx_logo_url', 'https://cloudmeister.ch/wp-content/uploads/cloudmeister.png'));
		?>
		<style>
			.language-switcher, .privacy-policy-page-link { display: none; }
			.forgetmenot { position: relative; top: 5px; }
			.login #nav { text-align: right; }
			.login form, #loginform {
				position: relative; margin-left: auto; margin-right: auto; box-shadow: none;
				border-width: 2px; border-style: solid; border-color: <?php echo $focus_color; ?>;
				border-radius: 20px; padding-top: 20px; padding-bottom: 20px;
			}
			.login h1 a { background-image: url('<?php echo $logo_url; ?>') !important; }
			#login form input[type=text], #login form input[type=password] {
				border-color: <?php echo $login_color; ?> !important; box-shadow: 0 0 0 1px <?php echo $login_color; ?> !important; border-radius: 10px;
			}
			#login form input[type=text]:focus, #login form input[type=password]:focus {
				border-color: <?php echo $focus_color; ?> !important; box-shadow: 0 0 0 1px <?php echo $focus_color; ?> !important; border-radius: 5px;
			}
			#login form p.submit #wp-submit {
				background-color: <?php echo $login_color; ?> !important; border-color: <?php echo $login_color; ?> !important; border-radius: 5px;
			}
			#login form p.submit #wp-submit:hover {
				background-color: <?php echo $focus_color; ?> !important; border-color: <?php echo $focus_color; ?> !important;
			}
		</style>
		<?php
	}

	public function customLoginUrl(): string {
		return esc_url(get_option('cmx_logo_link', 'https://cloudmeister.ch/'));
	}

	/* ---------------------------------
	 * Utils
	 * --------------------------------- */
	public function sanitizeHexColor($color): string {
		$color = trim((string)$color);
		// Erlaubt #RGB, #RRGGBB
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
			return strtoupper($color);
		}
		// Fallback-Default
		return '#15ADCB';
	}
}

/* Bootstrap */
add_action('plugins_loaded', function () {
	new \CLOUDMEISTER\CMX\LoginManager\SettingsPage();
});
