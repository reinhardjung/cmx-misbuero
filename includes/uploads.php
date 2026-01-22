<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


final class MIS_BUERO_BELEG_UPLOAD {

	const CPT          = 'beleg';
	const OPTION_TOKEN = 'mis_buero_upload_token';
	const OPTION_AIKEY = 'mis_buero_openai_key';

	public static function init() : void {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite' ] );
		add_action( 'init', [ __CLASS__, 'rewrite' ] );
		add_action( 'template_redirect', [ __CLASS__, 'frontend' ] );
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
				}
				.field {
					margin-top: 16px;
				}
				label {
					display: block;
					font-weight: 600;
					margin-bottom: 6px;
				}
				input[type="text"],
				input[type="date"],
				input[type="number"],
				textarea {
					width: 100%;
					padding: 8px 10px;
					border: 1px solid var(--wp-border);
					border-radius: 4px;
					font-size: 14px;
					background: #fff;
				}
				textarea {
					resize: vertical;
				}
				.camera {
					display: flex;
					align-items: center;
					justify-content: center;
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
				<h2>Beleg hochladen</h2>

				<form method="post" enctype="multipart/form-data">
					<div class="field">
						<label class="camera">
							📷 Foto oder Datei wählen
							<input type="file" name="beleg_datei"
							       accept="image/*,application/pdf"
							       capture="environment" required>
						</label>
						<div class="hint">Erlaubt: Foto oder PDF.</div>
					</div>

					<div class="field">
						<label for="beleg_datum">Belegdatum</label>
						<input id="beleg_datum" type="date" name="beleg_datum">
					</div>

					<div class="field">
						<label for="betrag">Betrag (Brutto, CHF)</label>
						<input id="betrag" type="number" step="0.01" name="betrag">
					</div>

					<div class="field">
						<label for="mwst">MwSt-Betrag (CHF, optional)</label>
						<input id="mwst" type="number" step="0.01" name="mwst">
					</div>

					<div class="field">
						<label for="info">Kurze Info</label>
						<textarea id="info" name="info" rows="3" required></textarea>
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

		$upload = wp_handle_upload( $_FILES['beleg_datei'], [ 'test_form' => false ] );
		if ( isset( $upload['error'] ) ) {
			wp_die( $upload['error'] );
		}

		$post_id = wp_insert_post( [
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => 'Sonstiger Beleg ' . current_time( 'Y-m-d H:i' ),
		] );

		$ocr = self::ocr_extract( $upload['file'] );

		update_post_meta( $post_id, 'beleg_datum',
			$_POST['beleg_datum'] ?: ( $ocr['datum'] ?? '' )
		);
		update_post_meta( $post_id, 'betrag',
			$_POST['betrag'] ?: ( $ocr['betrag'] ?? '' )
		);
		update_post_meta( $post_id, 'mwst',
			$_POST['mwst'] ?: ( $ocr['mwst'] ?? '' )
		);
		update_post_meta( $post_id, 'info', sanitize_textarea_field( $_POST['info'] ) );
		update_post_meta( $post_id, 'datei_url', esc_url_raw( $upload['url'] ) );

		echo '<p>Beleg gespeichert.</p>';
		exit;
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
