<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


final class MIS_BUERO_BELEG_UPLOAD {

	const CPT          = 'belege';
	const OPTION_TOKEN = 'mis_buero_upload_token';
	const OPTION_AIKEY = 'mis_buero_openai_key';

	public static function init() : void {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite' ] );
		add_action( 'init', [ __CLASS__, 'rewrite' ] );
		add_action( 'template_redirect', [ __CLASS__, 'frontend' ] );
		add_filter( 'upload_size_limit', [ __CLASS__, 'limit_upload_size' ] );
	}

	/* ================================
	 * SETTINGS – ALLGEMEIN
	 * ================================ */
	public static function register_settings() : void {

		register_setting(
			'general',
			self::OPTION_AIKEY,
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		add_settings_field(
			self::OPTION_AIKEY,
			'OpenAI API Key',
			[ __CLASS__, 'render_openai_field' ],
			'general'
		);
	}

	public static function render_openai_field() : void {
		?>
		<input type="text"
		       name="<?php echo esc_attr( self::OPTION_AIKEY ); ?>"
		       value="<?php echo esc_attr( get_option( self::OPTION_AIKEY ) ); ?>"
		       class="regular-text">
		<p class="description">
			Wird für OCR (Belegdatum, Bruttobetrag, MwSt) verwendet.
		</p>
		<?php
	}

	/* ================================
	 * ROUTING
	 * ================================ */
	public static function rewrite() : void {
		add_rewrite_rule( '^mis-upload/?', 'index.php?mis-upload=1', 'top' );
		add_rewrite_tag( '%mis-upload%', '1' );
	}

	public static function maybe_flush_rewrite() : void {
		$rules = get_option( 'rewrite_rules' );
		if ( isset( $rules['^mis-upload/?$'] ) ) {
			return;
		}

		self::rewrite();
		flush_rewrite_rules( false );
	}

	public static function limit_upload_size( $size ) : int {
		$limit = 20 * 1024 * 1024;
		return (int) min( $size, $limit );
	}

	/* ================================
	 * FRONTEND
	 * ================================ */
	public static function frontend() : void {

		if ( get_query_var( 'mis-upload' ) != 1 ) {
			return;
		}

		if ( sanitize_text_field( $_GET['token'] ?? '' ) !== get_option( self::OPTION_TOKEN ) ) {
			wp_die( 'Ungültiger Upload-Link.' );
		}

		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			self::handle_upload();
		}

		self::render_form();
		exit;
	}

	/* ================================
	 * FORM
	 * ================================ */
	private static function render_form() : void {
		$max_mb = 20;
		$zahlungsart_tax = function_exists( __NAMESPACE__ . '\\cmx_beleg_zahlungsart_tax' )
			? cmx_beleg_zahlungsart_tax()
			: null;
		$zahlungsart_terms = $zahlungsart_tax
			? get_terms( [ 'taxonomy' => $zahlungsart_tax, 'hide_empty' => false ] )
			: [];
		if ( is_wp_error( $zahlungsart_terms ) ) {
			$zahlungsart_terms = [];
		}

		$zahlungsgrund_tax = defined( __NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND' )
			? constant( __NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND' )
			: null;
		if ( ! $zahlungsgrund_tax || ! taxonomy_exists( $zahlungsgrund_tax ) ) {
			foreach ( [ 'belege_zahlungsgrund', 'belege_zahlungsgruende' ] as $candidate ) {
				if ( taxonomy_exists( $candidate ) ) {
					$zahlungsgrund_tax = $candidate;
					break;
				}
			}
		}
		$zahlungsgrund_terms = $zahlungsgrund_tax
			? get_terms( [ 'taxonomy' => $zahlungsgrund_tax, 'hide_empty' => false ] )
			: [];
		if ( is_wp_error( $zahlungsgrund_terms ) ) {
			$zahlungsgrund_terms = [];
		}

		?>
		<!doctype html>
		<html>
		<head>
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>Beleg Upload</title>
			<style>
				:root {
					--wp-blue: #2271b1;
					--wp-blue-dark: #135e96;
					--wp-gray-100: #f6f7f7;
					--wp-gray-200: #f0f0f1;
					--wp-gray-700: #3c434a;
					--wp-border: #c3c4c7;
				}
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
					background: var(--wp-gray-100);
					color: var(--wp-gray-700);
					margin: 0;
					padding: 32px 16px;
				}
				.wrap {
					max-width: 560px;
					margin: 0 auto;
				}
				.card {
					background: #fff;
					border: 1px solid var(--wp-border);
					border-radius: 8px;
					padding: 24px;
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
				}
				h2 {
					margin: 0 0 16px;
					font-size: 20px;
					text-align: center;
				}
				.logo {
					display: block;
					max-width: 80px;
					height: auto;
					margin: 0 auto 12px;
				}
				.field {
					margin-top: 16px;
					width: 100%;
				}
				.row {
					display: flex;
					gap: 12px;
					flex-wrap: wrap;
					margin-top: 16px;
				}
				.row .field {
					margin-top: 0;
					flex: 1 1 220px;
					min-width: 0;
				}
				*,
				*::before,
				*::after {
					box-sizing: border-box;
				}
				label {
					display: block;
					font-weight: 600;
					margin-bottom: 6px;
				}
				input[type="text"],
				input[type="date"],
				input[type="number"],
				textarea,
				select {
					width: 100%;
					max-width: 100%;
					min-width: 0;
					padding: 10px 10px;
					border: 1px solid var(--wp-border);
					border-radius: 4px;
					font-size: 14px;
					background: #fff;
				}
				input[type="date"] {
					appearance: none;
					-webkit-appearance: none;
					min-height: 42px;
				}
				textarea {
					resize: vertical;
				}
				.camera {
					display: flex;
					align-items: center;
					justify-content: center;
					gap: 8px;
					border: 1px dashed var(--wp-border);
					background: var(--wp-gray-200);
					color: var(--wp-gray-700);
					padding: 18px;
					border-radius: 6px;
					cursor: pointer;
					font-weight: 600;
				}
				.camera input {
					display: none;
				}
				.hint {
					margin-top: 6px;
					font-size: 12px;
					color: #646970;
					text-align: center;
				}
				button {
					margin-top: 20px;
					width: 100%;
					padding: 10px 14px;
					font-size: 14px;
					font-weight: 600;
					border-radius: 4px;
					border: 1px solid var(--wp-blue);
					background: var(--wp-blue);
					color: #fff;
					cursor: pointer;
				}
				button:hover {
					background: var(--wp-blue-dark);
					border-color: var(--wp-blue-dark);
				}
			</style>
		</head>
		<body>

		<div class="wrap">
			<div class="card">
				<img class="logo" src="https://misbuero.ch/wp-content/uploads/youtube.png" alt="Mis Buero Logo">
				<!-- <h2>Beleg hochladen</h2> -->

				<form method="post" enctype="multipart/form-data">
					<div class="field">
						<label class="camera">Beleg hochladen<input type="file" name="beleg_datei" accept="image/*,application/pdf" required></label>
						<div class="hint">Foto, PNG, JPG oder PDF mit max. <?php echo (int) $max_mb; ?> MB</div>
					</div>

					<div class="row">
						<div class="field">
							<label for="beleg_datum">Belegdatum</label>
							<input id="beleg_datum" type="date" name="beleg_datum">
						</div>

						<div class="field">
							<label for="betrag">Betrag</label>
							<input id="betrag" type="number" step="0.01" name="betrag">
						</div>
					</div>

					<div class="row">
						<div class="field">
							<label for="zahlungsart">Zahlungsart</label>
							<select id="zahlungsart" name="zahlungsart">
								<option value="">Bitte wählen</option>
								<?php foreach ( $zahlungsart_terms as $term ) : ?>
									<option value="<?php echo (int) $term->term_id; ?>">
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="field">
							<label for="zahlungsgrund">Zahlungsgrund</label>
							<select id="zahlungsgrund" name="zahlungsgrund">
								<option value="">Bitte wählen</option>
								<?php foreach ( $zahlungsgrund_terms as $term ) : ?>
									<option value="<?php echo (int) $term->term_id; ?>">
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="field">
						<label for="info">Kurze Info</label>
						<textarea id="info" name="info" rows="2"></textarea>
					</div>

					<button type="submit">Beleg speichern</button>
				</form>
			</div>
		</div>

		</body>
		</html>
		<?php
	}

	/* ================================
	 * HANDLE UPLOAD
	 * ================================ */
	private static function handle_upload() : void {

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$ts = current_time( 'timestamp' );
		$stamp = date( 'ymd-His', $ts );
		$post_title = $stamp;
		$year = date( 'Y', $ts );
		$upload_filter = function( array $dirs ) use ( $year ) : array {
			$base = WP_CONTENT_DIR . '/uploads/misbuero/' . $year . '/belege';
			$url  = content_url( '/uploads/misbuero/' . $year . '/belege' );
			if ( ! is_dir( $base ) ) {
				wp_mkdir_p( $base );
			}
			$dirs['path']   = $base;
			$dirs['basedir']= $base;
			$dirs['url']    = $url;
			$dirs['baseurl']= $url;
			$dirs['subdir'] = '';
			return $dirs;
		};
		add_filter( 'upload_dir', $upload_filter );

		$upload = wp_handle_upload(
			$_FILES['beleg_datei'],
			[
				'test_form' => false,
				'unique_filename_callback' => function( string $dir, string $name, string $ext ) use ( $post_title ) : string {
					$base = sanitize_title( $post_title ) . '_upload';
					$filename = $base . $ext;
					$counter = 1;
					while ( file_exists( $dir . '/' . $filename ) ) {
						$filename = $base . '-' . $counter . $ext;
						$counter++;
					}
					return $filename;
				},
			]
		);
		remove_filter( 'upload_dir', $upload_filter );
		if ( isset( $upload['error'] ) ) {
			wp_die( $upload['error'] );
		}

		$post_id = wp_insert_post( [
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => $post_title,
		] );

		$ocr = self::ocr_extract( $upload['file'] );

		$beleg_datum = sanitize_text_field( $_POST['beleg_datum'] ?? '' );
		$betrag = sanitize_text_field( $_POST['betrag'] ?? '' );
		$info = sanitize_textarea_field( $_POST['info'] ?? '' );

		update_post_meta( $post_id, 'beleg_datum', $beleg_datum ?: ( $ocr['datum'] ?? '' ) );
		update_post_meta( $post_id, 'betrag', $betrag ?: ( $ocr['betrag'] ?? '' ) );
		update_post_meta( $post_id, 'info', $info );
		update_post_meta( $post_id, 'datei_url', esc_url_raw( $upload['url'] ) );

		update_post_meta( $post_id, '_cmx_beleg_rng_datum', $beleg_datum ?: ( $ocr['datum'] ?? '' ) );
		if ( $betrag !== '' ) {
			update_post_meta( $post_id, '_cmx_beleg_summe_override', $betrag );
		}

		if ( function_exists( __NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy' ) ) {
			$tax = cmx_belege_kategorie_taxonomy();
			if ( $tax ) {
				wp_set_post_terms( $post_id, [ 'sonstiges' ], $tax, false );
			}
		}

		$zahlungsart = (int) ( $_POST['zahlungsart'] ?? 0 );
		if ( $zahlungsart ) {
			$zahlungsart_tax = function_exists( __NAMESPACE__ . '\\cmx_beleg_zahlungsart_tax' )
				? cmx_beleg_zahlungsart_tax()
				: null;
			if ( $zahlungsart_tax ) {
				wp_set_post_terms( $post_id, [ $zahlungsart ], $zahlungsart_tax, false );
			}
		}

		$zahlungsgrund = (int) ( $_POST['zahlungsgrund'] ?? 0 );
		if ( $zahlungsgrund ) {
			$zahlungsgrund_tax = defined( __NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND' )
				? constant( __NAMESPACE__ . '\\TAX_BELEGE_ZAHLUNGSGRUND' )
				: null;
			if ( $zahlungsgrund_tax && taxonomy_exists( $zahlungsgrund_tax ) ) {
				wp_set_post_terms( $post_id, [ $zahlungsgrund ], $zahlungsgrund_tax, false );
			}
		}

		self::render_success();
		exit;
	}

	private static function render_success() : void {
		$token = get_option( self::OPTION_TOKEN );
		$upload_url = $token ? home_url( '/mis-upload/?token=' . $token ) : home_url( '/mis-upload/' );
		?>
		<!doctype html>
		<html>
		<head>
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title>Beleg gespeichert</title>
			<style>
				:root {
					--wp-blue: #2271b1;
					--wp-gray-100: #f6f7f7;
					--wp-gray-700: #3c434a;
					--wp-border: #c3c4c7;
				}
				*,
				*::before,
				*::after {
					box-sizing: border-box;
				}
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
					background: var(--wp-gray-100);
					color: var(--wp-gray-700);
					margin: 0;
					padding: 32px 16px;
				}
				.wrap {
					max-width: 560px;
					margin: 0 auto;
				}
				.card {
					background: #fff;
					border: 1px solid var(--wp-border);
					border-radius: 8px;
					padding: 24px;
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
					text-align: center;
				}
				.logo {
					display: block;
					max-width: 200px;
					height: auto;
					margin: 0 auto 16px;
				}
				.check {
					font-size: 16px;
					margin: 8px 0 0;
				}
				.note {
					margin-top: 8px;
					color: #646970;
					font-size: 13px;
				}
				.back-link {
					display: inline-block;
					margin-top: 12px;
					padding: 8px 14px;
					border-radius: 4px;
					border: 1px solid var(--wp-blue);
					background: var(--wp-blue);
					color: #fff;
					text-decoration: none;
					font-weight: 600;
					font-size: 13px;
				}
		</style>
		</head>
		<body>
		<div class="wrap">
			<div class="card">
				<img class="logo" src="https://misbuero.ch/wp-content/uploads/youtube.png" alt="Mis Buero">
				<div class="check"><strong>Beleg wurde gespeichert.</strong></div>
				<div class="note">Du kannst das Fenster jetzt schliessen oder</div>
				<a class="back-link" href="<?php echo esc_url( $upload_url ); ?>">Neuen Beleg hochladen</a>
			</div>
		</div>
		</body>
		</html>
		<?php
	}

	/* ================================
	 * OCR – CH PRODUKTIONS-PROMPT
	 * ================================ */
	private static function ocr_extract( string $file_path ) : array {

		$key = get_option( self::OPTION_AIKEY );
		if ( empty( $key ) ) {
			return [];
		}

		$image = 'data:' . mime_content_type( $file_path ) . ';base64,' . base64_encode( file_get_contents( $file_path ) );

		$prompt = <<<PROMPT
Du siehst einen Beleg aus der Schweiz.

Extrahiere, wenn eindeutig vorhanden:

1. datum – Zahlungs- oder Belegdatum (YYYY-MM-DD)
2. betrag – Bruttobetrag inkl. MwSt (Zahl mit Punkt)
3. mwst – explizit ausgewiesener MwSt-Betrag (Zahl mit Punkt)

Regeln:
- MwSt nur ausgeben, wenn sie explizit ausgewiesen ist
- Keine Berechnung, kein Schätzen
- Wenn unklar → leer lassen
- Antworte ausschließlich als JSON

{
  "datum": "",
  "betrag": "",
  "mwst": ""
}
PROMPT;

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				],
				'body' => wp_json_encode( [
					'model' => 'gpt-4.1-mini',
					'messages' => [
						[
							'role' => 'user',
							'content' => [
								[ 'type' => 'text', 'text' => $prompt ],
								[ 'type' => 'image_url', 'image_url' => [ 'url' => $image ] ],
							],
						],
					],
					'temperature' => 0,
				] ),
				'timeout' => 30,
			]
		);

		$data = json_decode(
			$body = wp_remote_retrieve_body( $response ),
			true
		);

		return json_decode( $data['choices'][0]['message']['content'] ?? '', true ) ?: [];
	}
}

MIS_BUERO_BELEG_UPLOAD::init();
