<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


final class MIS_BUERO_BELEG_UPLOAD {

	const CPT          = 'belege';
	const OPTION_TOKEN = 'mis_buero_upload_token';
	const OPTION_AIKEY = 'mis_buero_openai_key';
	const OPTION_SERVICES_URL = 'mis_buero_services_url';
	const OPTION_SERVICES_KEY = 'mis_buero_services_api_key';

	public static function init() : void {
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite' ] );
		add_action( 'init', [ __CLASS__, 'rewrite' ] );
		add_action( 'template_redirect', [ __CLASS__, 'frontend' ] );
		add_filter( 'upload_size_limit', [ __CLASS__, 'limit_upload_size' ] );
		add_action( 'wp_ajax_mis_buero_ocr', [ __CLASS__, 'handle_ocr' ] );
		add_action( 'wp_ajax_nopriv_mis_buero_ocr', [ __CLASS__, 'handle_ocr' ] );
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

	private static function icon_url() : string {
		$relative = 'src/belege/icon_upload.png';
		$url      = (string) plugins_url( $relative, dirname( __DIR__ ) . '/cmx-misbuero.php' );
		$path     = dirname( __DIR__ ) . '/' . $relative;
		$version  = @filemtime( $path );

		if ( $version ) {
			$url = (string) add_query_arg( 'ver', (string) $version, $url );
		}

		return $url;
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
		$has_ai_key = (bool) get_option( self::OPTION_AIKEY );
		$has_services_key = self::services_api_key() !== '';
		$upload_token = (string) get_option( self::OPTION_TOKEN );
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
		$beleg_kategorie_tax = function_exists( __NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy' )
			? cmx_belege_kategorie_taxonomy()
			: null;
		$beleg_kategorie_terms = $beleg_kategorie_tax
			? get_terms( [ 'taxonomy' => $beleg_kategorie_tax, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ] )
			: [];
		if ( is_wp_error( $beleg_kategorie_terms ) ) {
			$beleg_kategorie_terms = [];
		}
		if ( ! empty( $beleg_kategorie_terms ) && function_exists( __NAMESPACE__ . '\\cmx_beleg_kategorie_allowed_slugs' ) ) {
			$allowed_slugs = (array) cmx_beleg_kategorie_allowed_slugs();
			if ( ! empty( $allowed_slugs ) ) {
				$beleg_kategorie_terms = array_values( array_filter( $beleg_kategorie_terms, function( $term ) use ( $allowed_slugs ) {
					return isset( $term->slug ) && in_array( (string) $term->slug, $allowed_slugs, true );
				} ) );
			}
		}
		$last_beleg_id = 0;
		$last_beleg = get_posts( [
			'post_type'      => 'belege',
			'posts_per_page' => 1,
			'post_status'    => [ 'publish', 'private', 'draft' ],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );
		if ( ! empty( $last_beleg ) ) {
			$last_beleg_id = (int) $last_beleg[0];
		}
		$last_zahlungsart_id = 0;
		if ( $last_beleg_id && $zahlungsart_tax ) {
			$terms = wp_get_post_terms( $last_beleg_id, $zahlungsart_tax, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$last_zahlungsart_id = (int) $terms[0];
			}
		}
		$last_zahlungsgrund_id = 0;
		if ( $last_beleg_id && $zahlungsgrund_tax ) {
			$terms = wp_get_post_terms( $last_beleg_id, $zahlungsgrund_tax, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$last_zahlungsgrund_id = (int) $terms[0];
			}
		}
		$last_beleg_kategorie_id = 0;
		if ( $last_beleg_id && $beleg_kategorie_tax ) {
			$terms = wp_get_post_terms( $last_beleg_id, $beleg_kategorie_tax, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$last_beleg_kategorie_id = (int) $terms[0];
			}
		}
		$last_beleg_richtung = '';
		if ( $last_beleg_id ) {
			$dir = sanitize_key( (string) get_post_meta( $last_beleg_id, '_cmx_beleg_richtung', true ) );
			if ( in_array( $dir, [ 'ausgang', 'eingang' ], true ) ) {
				$last_beleg_richtung = $dir;
			}
		}
		$richtung_opts = function_exists( __NAMESPACE__ . '\\cmx_beleg_richtung_options' )
			? (array) cmx_beleg_richtung_options()
			: [
				'ausgang' => 'Ausgang',
				'eingang' => 'Eingang',
			];
		if ( empty( $richtung_opts['ausgang'] ) ) {
			$richtung_opts['ausgang'] = 'Ausgang';
		}
		if ( empty( $richtung_opts['eingang'] ) ) {
			$richtung_opts['eingang'] = 'Eingang';
		}
		$richtung_label_map = [
			'rechnung' => [
				'ausgang' => 'Einnahme',
				'eingang' => 'Ausgabe',
			],
			'rechnungen' => [
				'ausgang' => 'Einnahme',
				'eingang' => 'Ausgabe',
			],
			'gutschrift' => [
				'ausgang' => 'Ausgabe',
				'eingang' => 'Einnahme',
			],
			'gutschriften' => [
				'ausgang' => 'Ausgabe',
				'eingang' => 'Einnahme',
			],
			'quittung' => [
				'ausgang' => 'Einnahme',
				'eingang' => 'Ausgabe',
			],
			'quittungen' => [
				'ausgang' => 'Einnahme',
				'eingang' => 'Ausgabe',
			],
			'offerte' => [
				'ausgang' => 'Ausgang',
				'eingang' => 'Eingang',
			],
			'offerten' => [
				'ausgang' => 'Ausgang',
				'eingang' => 'Eingang',
			],
			'lieferschein' => [
				'ausgang' => 'Ausgang',
				'eingang' => 'Eingang',
			],
			'lieferscheine' => [
				'ausgang' => 'Ausgang',
				'eingang' => 'Eingang',
			],
		];
		$richtung_default_dir = [
			'rechnung' => 'ausgang',
			'rechnungen' => 'ausgang',
			'quittung' => 'ausgang',
			'quittungen' => 'ausgang',
			'lieferschein' => 'ausgang',
			'lieferscheine' => 'ausgang',
			'offerte' => 'ausgang',
			'offerten' => 'ausgang',
			'gutschrift' => 'eingang',
			'gutschriften' => 'eingang',
		];
		$logo_link = $upload_token !== ''
			? home_url( '/mis-upload/?token=' . rawurlencode( $upload_token ) )
			: home_url( '/mis-upload/' );
		$logo_src = self::icon_url();
		$favicon_src = function_exists( __NAMESPACE__ . '\\cmx_misbuero_favicon_url' ) ? cmx_misbuero_favicon_url() : $logo_src;
		$ini_kategorien = function_exists( __NAMESPACE__ . '\\cmx_ini_get_value' )
			? (array) cmx_ini_get_value( 'Belege', 'Kategorien' )
			: [];
		foreach ( $ini_kategorien as $cat_name ) {
			$slug = sanitize_title( (string) $cat_name );
			if ( $slug === '' ) {
				continue;
			}
			$ini_labels = function_exists( __NAMESPACE__ . '\\cmx_ini_get_value' )
				? cmx_ini_get_value( 'BelegeRichtungLabels', (string) $cat_name )
				: null;
			if ( $ini_labels === null || $ini_labels === '' ) {
				$ini_labels = function_exists( __NAMESPACE__ . '\\cmx_ini_get_value' )
					? cmx_ini_get_value( 'BelegeRichtungLabels', (string) $slug )
					: null;
			}
			if ( is_array( $ini_labels ) && count( $ini_labels ) >= 2 ) {
				$richtung_label_map[ $slug ] = [
					'ausgang' => (string) $ini_labels[0],
					'eingang' => (string) $ini_labels[1],
				];
			}
		}

		$status_opts = function_exists( __NAMESPACE__ . '\\cmx_beleg_status_options' )
			? cmx_beleg_status_options()
			: [
				'offen'    => 'Offen',
				'bezahlt'  => 'Bezahlt',
				'teil'     => 'Teilbezahlt',
				'storniert'=> 'Storniert',
			];

		$proj_beg_key = defined( __NAMESPACE__ . '\\CMX_PROJ_BEG_META' )
			? constant( __NAMESPACE__ . '\\CMX_PROJ_BEG_META' )
			: '_cmx_projekt_beginn';
		$proj_end_key = defined( __NAMESPACE__ . '\\CMX_PROJ_END_META' )
			? constant( __NAMESPACE__ . '\\CMX_PROJ_END_META' )
			: '_cmx_projekt_ende';
		$today = wp_date( 'Y-m-d' );
		$project_posts = get_posts( [
			'post_type'      => 'projekte',
			'posts_per_page' => 300,
			'post_status'    => [ 'publish', 'private', 'draft' ],
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'     => $proj_beg_key,
					'value'   => '',
					'compare' => '!=',
				],
			],
		] );
		$active_projects = [];
		foreach ( $project_posts as $p ) {
			$beg = get_post_meta( $p->ID, $proj_beg_key, true );
			$end = get_post_meta( $p->ID, $proj_end_key, true );
			$beg_ts = $beg ? strtotime( $beg ) : 0;
			$end_ts = $end ? strtotime( $end ) : 0;
			$today_ts = strtotime( $today );
			if ( ! $beg_ts ) {
				continue;
			}
			if ( $end_ts && $today_ts > $end_ts ) {
				continue;
			}
			$active_projects[] = $p;
		}
		$last_project_id = 0;
		$last_project = get_posts( [
			'post_type'      => 'projekte',
			'posts_per_page' => 1,
			'post_status'    => [ 'publish', 'private', 'draft' ],
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );
		if ( ! empty( $last_project ) ) {
			$last_project_id = (int) $last_project[0];
		}

		?>
		<!doctype html>
		<html>
		<head>
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<link rel="icon" href="<?php echo esc_url( $favicon_src ); ?>" type="image/png">
			<link rel="shortcut icon" href="<?php echo esc_url( $favicon_src ); ?>" type="image/png">
			<link rel="apple-touch-icon" href="<?php echo esc_url( $favicon_src ); ?>">
			<meta name="apple-mobile-web-app-capable" content="yes">
			<meta name="apple-mobile-web-app-title" content="PDF Upload">
			<meta name="theme-color" content="#2271b1">
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
				.preview {
					margin-top: 8px;
					padding: 8px;
					border: 1px dashed var(--wp-border);
					border-radius: 4px;
					background: #fff;
					font-size: 12px;
					color: #646970;
					text-align: center;
				}
				.preview img {
					max-width: 100%;
					height: auto;
					display: block;
					margin: 6px auto 0;
					border-radius: 4px;
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
				<a href="<?php echo esc_url( $logo_link ); ?>"><img class="logo" src="<?php echo esc_url( $logo_src ); ?>" alt="Mis Büro"></a>
				<!-- <h2>Beleg hochladen</h2> -->

				<form method="post" enctype="multipart/form-data">
					<div class="field">
						<label class="camera">Beleg hochladen<input type="file" name="beleg_datei" accept="image/*,application/pdf" required></label>
						<div class="hint">Foto, PNG, JPG oder PDF mit max. <?php echo (int) $max_mb; ?> MB</div>
						<div class="preview" id="file_preview">Keine Datei ausgewählt.</div>
					</div>

					<div class="row">
						<div class="field">
							<label for="beleg_kategorie" class="js-select-last" data-target="beleg_kategorie" data-last="<?php echo (int) $last_beleg_kategorie_id; ?>">Belegkategorie</label>
								<select id="beleg_kategorie" name="beleg_kategorie">
									<option value="">Bitte wählen</option>
									<?php foreach ( $beleg_kategorie_terms as $term ) : ?>
										<option value="<?php echo (int) $term->term_id; ?>" data-slug="<?php echo esc_attr( (string) $term->slug ); ?>" <?php selected( (int) $last_beleg_kategorie_id, (int) $term->term_id ); ?>>
											<?php echo esc_html( (string) $term->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
						</div>

						<div class="field">
							<label for="beleg_richtung" class="js-select-last" data-target="beleg_richtung" data-last="<?php echo esc_attr( $last_beleg_richtung ); ?>">Richtung</label>
							<select id="beleg_richtung" name="beleg_richtung">
								<option value="">Bitte wählen</option>
								<option value="ausgang" <?php selected( $last_beleg_richtung, 'ausgang' ); ?>><?php echo esc_html( (string) $richtung_opts['ausgang'] ); ?></option>
								<option value="eingang" <?php selected( $last_beleg_richtung, 'eingang' ); ?>><?php echo esc_html( (string) $richtung_opts['eingang'] ); ?></option>
							</select>
						</div>
					</div>

					<div class="row">
						<div class="field">
							<label for="beleg_datum" class="js-today-label" data-target="beleg_datum">Belegdatum</label>
							<input id="beleg_datum" type="date" name="beleg_datum">
						</div>

						<div class="field">
							<label for="betrag">Betrag</label>
							<input id="betrag" type="number" step="0.01" name="betrag">
						</div>
					</div>

					<div class="field">
						<label for="status" class="js-status-label" data-target="status" data-value="bezahlt">Status</label>
						<select id="status" name="status">
							<?php foreach ( $status_opts as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>">
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="field">
						<label for="bezahlt_am" class="js-today-label" data-target="bezahlt_am">Bezahlt am</label>
						<input id="bezahlt_am" type="date" name="bezahlt_am">
					</div>

					<div class="row">
						<div class="field">
							<label for="zahlungsart" class="js-select-last" data-target="zahlungsart" data-last="<?php echo (int) $last_zahlungsart_id; ?>">Zahlungsart</label>
							<select id="zahlungsart" name="zahlungsart">
								<option value="">Bitte wählen</option>
								<?php foreach ( $zahlungsart_terms as $term ) : ?>
									<?php
									$term_name = strtolower( $term->name );
									$term_key = '';
									if ( strpos( $term_name, 'twint' ) !== false ) {
										$term_key = 'twint';
									} elseif ( strpos( $term_name, 'bar' ) !== false ) {
										$term_key = 'bar';
									} elseif ( strpos( $term_name, 'karte' ) !== false || strpos( $term_name, 'kredit' ) !== false ) {
										$term_key = 'karte';
									} elseif ( strpos( $term_name, 'überweisung' ) !== false || strpos( $term_name, 'ueberweisung' ) !== false || strpos( $term_name, 'bank' ) !== false ) {
										$term_key = 'ueberweisung';
									}
									?>
									<option value="<?php echo (int) $term->term_id; ?>" data-key="<?php echo esc_attr( $term_key ); ?>">
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="field">
							<label for="zahlungsgrund" class="js-select-last" data-target="zahlungsgrund" data-last="<?php echo (int) $last_zahlungsgrund_id; ?>">Zahlungsgrund</label>
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
						<label for="projekt_id" class="js-project-label" data-target="projekt_id" data-last="<?php echo (int) $last_project_id; ?>">Projekt</label>
						<select id="projekt_id" name="projekt_id">
							<option value="">Bitte wählen</option>
							<?php foreach ( $active_projects as $project ) : ?>
								<option value="<?php echo (int) $project->ID; ?>">
									<?php echo esc_html( get_the_title( $project->ID ) ?: ( '#' . $project->ID ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="field">
						<label for="info">Kurze Info</label>
						<textarea id="info" name="info" rows="2"></textarea>
					</div>

					<button type="submit">Beleg speichern</button>
				</form>
			</div>
		</div>

			<script>
			(function(){
				var richtungMap = <?php echo wp_json_encode( $richtung_label_map ); ?>;
				var richtungOptions = <?php echo wp_json_encode( $richtung_opts ); ?>;
				var richtungDefault = <?php echo wp_json_encode( $richtung_default_dir ); ?>;
				var lastKategorieSlug = "";

				function syncRichtungByKategorie(){
					var catSelect = document.getElementById("beleg_kategorie");
					var dirSelect = document.getElementById("beleg_richtung");
					if (!catSelect || !dirSelect) return;
					var selectedOpt = catSelect.options[catSelect.selectedIndex] || null;
					var slug = selectedOpt ? (selectedOpt.getAttribute("data-slug") || "") : "";
					var fallbackLabels = (richtungOptions && typeof richtungOptions === "object") ? richtungOptions : {};
					var labels = (slug && richtungMap && richtungMap[slug]) ? richtungMap[slug] : fallbackLabels;
					var aus = labels && labels.ausgang ? labels.ausgang : (fallbackLabels.ausgang || "Ausgang");
					var ein = labels && labels.eingang ? labels.eingang : (fallbackLabels.eingang || "Eingang");
					var ausOpt = dirSelect.querySelector('option[value="ausgang"]');
					var einOpt = dirSelect.querySelector('option[value="eingang"]');
					if (ausOpt) ausOpt.textContent = aus;
					if (einOpt) einOpt.textContent = ein;
					var defaultDir = (slug && richtungDefault && richtungDefault[slug]) ? richtungDefault[slug] : "";
					var slugChanged = slug !== lastKategorieSlug;
					if (defaultDir && (slugChanged || !dirSelect.value)) {
						dirSelect.value = defaultDir;
						dirSelect.dispatchEvent(new Event("change", { bubbles: true }));
					}
					lastKategorieSlug = slug;
				}

				function isoToday(){
					var d = new Date();
					var y = d.getFullYear();
					var m = String(d.getMonth() + 1).padStart(2, "0");
				var day = String(d.getDate()).padStart(2, "0");
				return y + "-" + m + "-" + day;
			}
			function setSelectByKey(selectId, key){
				if (!key) return;
				var select = document.getElementById(selectId);
				if (!select) return;
				var opt = select.querySelector('option[data-key="' + key + '"]');
				if (opt) {
					select.value = opt.value;
					select.dispatchEvent(new Event("change", { bubbles: true }));
				}
			}
			document.querySelectorAll(".js-today-label").forEach(function(label){
				label.addEventListener("click", function(e){
					e.preventDefault();
					var target = document.getElementById(label.dataset.target || "");
					if (target) {
						target.value = isoToday();
						target.dispatchEvent(new Event("change", { bubbles: true }));
					}
				});
			});
			document.querySelectorAll(".js-status-label").forEach(function(label){
				label.addEventListener("click", function(e){
					e.preventDefault();
					var target = document.getElementById(label.dataset.target || "");
					var value = label.dataset.value || "";
					if (target && value) {
						target.value = value;
						target.dispatchEvent(new Event("change", { bubbles: true }));
					}
				});
			});
			document.querySelectorAll(".js-project-label").forEach(function(label){
				label.addEventListener("click", function(e){
					e.preventDefault();
					var target = document.getElementById(label.dataset.target || "");
					var last = label.dataset.last || "";
					if (target && last) {
						target.value = last;
						target.dispatchEvent(new Event("change", { bubbles: true }));
					}
				});
			});
				document.querySelectorAll(".js-select-last").forEach(function(label){
					label.addEventListener("click", function(e){
						e.preventDefault();
						var target = document.getElementById(label.dataset.target || "");
						var last = label.dataset.last || "";
						if (target && last) {
							target.value = last;
							target.dispatchEvent(new Event("change", { bubbles: true }));
						}
					});
				});
				var catSelect = document.getElementById("beleg_kategorie");
				if (catSelect) {
					catSelect.addEventListener("change", syncRichtungByKategorie);
				}
				syncRichtungByKategorie();

				var hasAi = <?php echo $has_ai_key ? 'true' : 'false'; ?>;
				var hasServices = <?php echo $has_services_key ? 'true' : 'false'; ?>;
				var token = <?php echo wp_json_encode( $upload_token ); ?>;
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var fileInput = document.querySelector('input[name="beleg_datei"]');
			var preview = document.getElementById("file_preview");
			if (fileInput) {
				fileInput.addEventListener("change", function(){
					if (!fileInput.files || !fileInput.files.length) return;
					var file = fileInput.files[0];
					if (preview) {
						preview.textContent = "Datei geladen: " + file.name;
					}
					if (file && file.type && file.type.indexOf("image/") === 0) {
						var reader = new FileReader();
						reader.onload = function(e){
							if (!preview) return;
							preview.innerHTML = "Vorschau:";
							var img = document.createElement("img");
							img.src = e.target.result;
							preview.appendChild(img);
						};
						reader.readAsDataURL(file);
					}
					var isPdf = (file.type === "application/pdf") || /\.pdf$/i.test(file.name || "");
					if (!hasAi && !(hasServices && isPdf)) {
						return;
					}
					if (preview) {
						preview.textContent = "Prüfung läuft...";
					}
					var fd = new FormData();
					fd.append("action", "mis_buero_ocr");
					fd.append("token", token);
					fd.append("file", file);
					fetch(ajaxUrl, { method: "POST", body: fd })
						.then(function(r){ return r.json(); })
						.then(function(res){
							if (!res || !res.success || !res.data) {
								if (preview) {
									var msg = res && res.data && res.data.message ? res.data.message : "OCR fehlgeschlagen.";
									preview.textContent = msg;
								}
								return;
							}
							if (preview) preview.textContent = "Prüfung abgeschlossen.";
							if (res.data.datum) {
								var d = document.getElementById("beleg_datum");
								if (d && !d.value) d.value = res.data.datum;
							}
							if (res.data.betrag) {
								var b = document.getElementById("betrag");
								if (b && !b.value) b.value = res.data.betrag;
							}
							if (res.data.bezahlt_am) {
								var z = document.getElementById("bezahlt_am");
								if (z && !z.value) z.value = res.data.bezahlt_am;
							}
							if (res.data.zahlungsart) {
								setSelectByKey("zahlungsart", res.data.zahlungsart);
							}
						})
						.catch(function(err){
							if (preview) {
								preview.textContent = "Prüfung Fehler: " + (err && err.message ? err.message : "unbekannt");
							}
						});
				});
			}
		})();
		</script>

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
		$stamp = wp_date( 'ymd-His', $ts );
		$post_title = $stamp;
		$year = function_exists( __NAMESPACE__ . '\\cmx_get_beleg_upload_year' )
			? (int) cmx_get_beleg_upload_year( 0 )
			: (int) wp_date( 'Y', $ts );
		$upload_filter = function( array $dirs ) use ( $year ) : array {
			$base = WP_CONTENT_DIR . '/uploads/misbuero/archiv/' . $year . '/belege';
			$url  = content_url( '/uploads/misbuero/archiv/' . $year . '/belege' );
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
		$upload_rel = ltrim( str_replace( trailingslashit( WP_CONTENT_DIR . '/uploads' ), '', $upload['file'] ), '/' );
		$uploads_meta_key = defined( __NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META' )
			? constant( __NAMESPACE__ . '\\CMX_BELEG_UPLOADS_META' )
			: '_cmx_belege_uploads';
		$existing_uploads = (array) get_post_meta( $post_id, $uploads_meta_key, true );
		$existing_uploads = array_values( array_filter( $existing_uploads, function( $v ) { return $v !== '' && $v !== null; } ) );
		if ( $upload_rel !== '' ) {
			$existing_uploads[] = $upload_rel;
			$existing_uploads = array_values( array_unique( $existing_uploads ) );
			update_post_meta( $post_id, $uploads_meta_key, $existing_uploads );
		}
		update_post_meta( $post_id, '_cmx_beleg_upload_prefix', sanitize_title( $post_title ) );
		update_post_meta( $post_id, '_cmx_beleg_import_flag', '1' );
		update_post_meta( $post_id, '_cmx_beleg_imported_at', current_time( 'mysql' ) );

		$ocr = self::ocr_extract( $upload['file'] );

			$beleg_datum = sanitize_text_field( $_POST['beleg_datum'] ?? '' );
			$betrag = sanitize_text_field( $_POST['betrag'] ?? '' );
			$info = sanitize_textarea_field( $_POST['info'] ?? '' );
			$status = sanitize_key( $_POST['status'] ?? '' );
			$bezahlt_am = sanitize_text_field( $_POST['bezahlt_am'] ?? '' );
			$projekt_id = (int) ( $_POST['projekt_id'] ?? 0 );
			$beleg_kategorie_id = (int) ( $_POST['beleg_kategorie'] ?? 0 );
			$beleg_richtung = sanitize_key( (string) ( $_POST['beleg_richtung'] ?? '' ) );
			$effective_beleg_datum = $beleg_datum !== '' ? $beleg_datum : sanitize_text_field( (string) ( $ocr['datum'] ?? '' ) );
			$effective_betrag = $betrag !== '' ? self::normalize_decimal_value( $betrag ) : self::effective_amount_from_ocr( $ocr, $upload['file'] );
			$effective_bezahlt_am = $bezahlt_am !== '' ? $bezahlt_am : sanitize_text_field( (string) ( $ocr['bezahlt_am'] ?? '' ) );

		if ( $effective_beleg_datum !== '' ) {
			update_post_meta( $post_id, 'beleg_datum', $effective_beleg_datum );
		}
		update_post_meta( $post_id, 'betrag', $effective_betrag );
		update_post_meta( $post_id, 'info', $info );
		if ( $info !== '' ) {
			self::append_internal_note( $post_id, $info );
		}
		update_post_meta( $post_id, 'datei_url', esc_url_raw( $upload['url'] ) );

		if ( $effective_beleg_datum !== '' ) {
			update_post_meta( $post_id, '_cmx_beleg_rng_datum', $effective_beleg_datum );
		}
		if ( $effective_betrag !== '' ) {
			update_post_meta( $post_id, '_cmx_beleg_summe_override', $effective_betrag );
			update_post_meta( $post_id, '_cmx_beleg_positionen', [] );
		}
		if ( ! empty( $ocr['faellig_am'] ) ) {
			update_post_meta( $post_id, '_cmx_beleg_faelligkeitsdatum', sanitize_text_field( (string) $ocr['faellig_am'] ) );
		}
		if ( ! empty( $ocr['_services'] ) && is_array( $ocr['_services'] ) ) {
			update_post_meta( $post_id, '_cmx_beleg_services_document', $ocr['_services'] );
		}
		self::append_positions_note_from_ocr( $post_id, $ocr );

		$kontakt = self::resolve_or_create_contact_from_ocr( $ocr );
		$kontakt_id = (int) ( $kontakt['id'] ?? 0 );
		$kontakt_label = sanitize_text_field( (string) ( $kontakt['label'] ?? ( $ocr['kontakt_label'] ?? '' ) ) );
		$kontakt_addr = sanitize_textarea_field( (string) ( $kontakt['addr'] ?? ( $ocr['kontakt_addr'] ?? '' ) ) );
		if ( ! self::is_plausible_contact_address( $kontakt_addr ) ) {
			$kontakt_addr = '';
		}
		if ( $kontakt_id > 0 ) {
			update_post_meta( $post_id, '_cmx_beleg_kontakt_id', $kontakt_id );
			update_post_meta( $post_id, '_cmx_beleg_kontakt_label', $kontakt_label !== '' ? $kontakt_label : trim( (string) get_the_title( $kontakt_id ) ) );
		} elseif ( $kontakt_label !== '' ) {
			delete_post_meta( $post_id, '_cmx_beleg_kontakt_id' );
			update_post_meta( $post_id, '_cmx_beleg_kontakt_label', $kontakt_label );
		}
		if ( $kontakt_addr !== '' ) {
			update_post_meta( $post_id, '_cmx_beleg_kontakt_addr', $kontakt_addr );
		}

			$richtung_key = defined( __NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG' )
				? constant( __NAMESPACE__ . '\\CMX_BELEG_META_RICHTUNG' )
				: '_cmx_beleg_richtung';
			if ( ! in_array( $beleg_richtung, [ 'ausgang', 'eingang' ], true ) ) {
				$beleg_richtung = 'eingang';
				if ( $beleg_kategorie_id > 0 && function_exists( __NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy' ) ) {
					$tax = cmx_belege_kategorie_taxonomy();
					$cat_term = $tax ? get_term( $beleg_kategorie_id, $tax ) : null;
					$cat_slug = ( $cat_term && ! is_wp_error( $cat_term ) ) ? sanitize_title( (string) $cat_term->slug ) : '';
					if ( $cat_slug !== '' ) {
						$default_dir = [
							'rechnung' => 'ausgang',
							'rechnungen' => 'ausgang',
							'quittung' => 'ausgang',
							'quittungen' => 'ausgang',
							'lieferschein' => 'ausgang',
							'lieferscheine' => 'ausgang',
							'offerte' => 'ausgang',
							'offerten' => 'ausgang',
							'gutschrift' => 'eingang',
							'gutschriften' => 'eingang',
						];
						if ( isset( $default_dir[ $cat_slug ] ) ) {
							$beleg_richtung = (string) $default_dir[ $cat_slug ];
						}
					}
				}
			}
			update_post_meta( $post_id, $richtung_key, $beleg_richtung );

		if ( $status !== '' ) {
			$status_key = defined( __NAMESPACE__ . '\\CMX_BELEG_META_STATUS' )
				? constant( __NAMESPACE__ . '\\CMX_BELEG_META_STATUS' )
				: '_cmx_beleg_status';
			update_post_meta( $post_id, $status_key, $status );
		}
		if ( $effective_bezahlt_am !== '' ) {
			$bez_key = defined( __NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM' )
				? constant( __NAMESPACE__ . '\\CMX_BELEG_META_BEZAHLT_AM' )
				: '_cmx_beleg_bezahlt_am';
			update_post_meta( $post_id, $bez_key, $effective_bezahlt_am );
		}
		if ( $projekt_id > 0 ) {
			$proj_id_key = defined( __NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_ID' )
				? constant( __NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_ID' )
				: '_cmx_beleg_projekt_id';
			$proj_label_key = defined( __NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_LABEL' )
				? constant( __NAMESPACE__ . '\\CMX_BELEG_META_PROJEKT_LABEL' )
				: '_cmx_beleg_projekt_label';
			update_post_meta( $post_id, $proj_id_key, $projekt_id );
			update_post_meta( $post_id, $proj_label_key, get_the_title( $projekt_id ) ?: (string) $projekt_id );
		}

		if ( function_exists( __NAMESPACE__ . '\\cmx_belege_kategorie_taxonomy' ) ) {
			$tax = cmx_belege_kategorie_taxonomy();
			if ( $tax ) {
				$set_term_ids = [];
				if ( $beleg_kategorie_id > 0 ) {
					$selected_term = \get_term( $beleg_kategorie_id, $tax );
					if ( $selected_term && ! \is_wp_error( $selected_term ) ) {
						$set_term_ids[] = (int) $selected_term->term_id;
					}
				}
				if ( empty( $set_term_ids ) ) {
					$default_term = \get_term_by( 'slug', 'rechnung', $tax );
					if ( $default_term && ! \is_wp_error( $default_term ) ) {
						$set_term_ids[] = (int) $default_term->term_id;
					}
				}
				if ( ! empty( $set_term_ids ) ) {
					\wp_set_post_terms( $post_id, $set_term_ids, $tax, false );
				}
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
		$logo_link = $upload_url;
		$logo_src = self::icon_url();
		$favicon_src = function_exists( __NAMESPACE__ . '\\cmx_misbuero_favicon_url' ) ? cmx_misbuero_favicon_url() : $logo_src;
		?>
		<!doctype html>
		<html>
		<head>
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<link rel="icon" href="<?php echo esc_url( $favicon_src ); ?>" type="image/png">
			<link rel="shortcut icon" href="<?php echo esc_url( $favicon_src ); ?>" type="image/png">
			<link rel="apple-touch-icon" href="<?php echo esc_url( $favicon_src ); ?>">
			<meta name="apple-mobile-web-app-capable" content="yes">
			<meta name="apple-mobile-web-app-title" content="PDF Upload">
			<meta name="theme-color" content="#2271b1">
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
			<a href="<?php echo esc_url( $logo_link ); ?>"><img class="logo" src="<?php echo esc_url( $logo_src ); ?>" alt="Mis Büro"></a>
				<div class="check"><strong>Beleg wurde gespeichert.</strong></div>
				<div class="note">Du kannst das Fenster jetzt schliessen oder</div>
				<a class="back-link" href="<?php echo esc_url( $upload_url ); ?>">Neuen Beleg hochladen</a>
			</div>
		</div>
		</body>
		</html>
		<?php
	}

	private static function handle_ocr() : void {
		$token = sanitize_text_field( $_POST['token'] ?? '' );
		if ( $token === '' || $token !== get_option( self::OPTION_TOKEN ) ) {
			wp_send_json_error( [ 'message' => 'bad_token' ], 403 );
		}
		if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => 'no_file' ], 400 );
		}
		$tmp_path = $_FILES['file']['tmp_name'];
		$mime = self::file_mime( $tmp_path );
		if ( $mime === 'application/pdf' && self::services_api_key() !== '' ) {
			$data = self::services_process_pdf( $tmp_path, sanitize_file_name( (string) ( $_FILES['file']['name'] ?? 'beleg.pdf' ) ) );
		} else {
			$key = get_option( self::OPTION_AIKEY );
			if ( empty( $key ) ) {
				wp_send_json_error( [ 'message' => 'missing_key' ], 400 );
			}
			$data = self::ocr_extract( $tmp_path );
		}
		if ( empty( $data ) || ! empty( $data['_error'] ) ) {
			$code = (string) ( $data['_error_code'] ?? '' );
			if ( $code === 'data_url_pattern' ) {
				wp_send_json_error( [
					'message' => 'OpenAI erwartet eine öffentliche URL. Bitte Datei öffentlich erreichbar machen oder Serverzugriff erlauben.',
				], 400 );
			}
		}
		if ( empty( $data ) || ! empty( $data['_error'] ) ) {
			$message = ! empty( $data['_error'] ) ? (string) $data['_error'] : 'ocr_failed';
			wp_send_json_error( [ 'message' => $message ], 400 );
		}
		$out = [
			'datum'       => $data['datum'] ?? '',
			'betrag'      => $data['betrag'] ?? '',
			'bezahlt_am'  => $data['bezahlt_am'] ?? '',
			'faellig_am'  => $data['faellig_am'] ?? '',
			'kontakt'     => $data['kontakt_label'] ?? '',
			'dokument_nr' => $data['document_no'] ?? '',
			'zahlungsart' => self::normalize_zahlungsart( $data['zahlungsart'] ?? '' ),
		];
		wp_send_json_success( $out );
	}

	public static function apply_pdf_extraction_to_beleg( int $post_id, string $file_path ) : array {
		if ( $post_id <= 0 || get_post_type( $post_id ) !== self::CPT ) {
			return [ 'success' => false, 'message' => 'Ungültiger Beleg.' ];
		}
		if ( ! is_readable( $file_path ) ) {
			return [ 'success' => false, 'message' => 'PDF konnte nicht gelesen werden.' ];
		}

		$text_fallback = self::ocr_from_pdf_text( $file_path );
		$ocr = self::pdf_text_fallback_is_sufficient( $text_fallback ) ? $text_fallback : self::ocr_extract( $file_path );
		if ( empty( $ocr ) || ! empty( $ocr['_error'] ) ) {
			$ocr_error = is_array( $ocr ) && ! empty( $ocr['_error'] ) ? (string) $ocr['_error'] : 'PDF-Verarbeitung fehlgeschlagen.';
			if ( empty( $text_fallback ) || ! self::has_extracted_beleg_data( $text_fallback ) ) {
				return [
					'success' => false,
					'message' => $ocr_error,
					'data' => is_array( $ocr ) ? $ocr : [],
				];
			}
			$ocr = $text_fallback;
			$ocr['_ocr_error'] = $ocr_error;
		} else {
			$ocr = self::apply_pdf_text_fallback( $ocr, $file_path );
		}

		$applied = [];
		$betrag = self::effective_amount_from_ocr( $ocr, $file_path );
		if ( $betrag !== '' ) {
			update_post_meta( $post_id, 'betrag', $betrag );
			update_post_meta( $post_id, '_cmx_beleg_summe_override', $betrag );
			update_post_meta( $post_id, '_cmx_beleg_positionen', [] );
			$applied['betrag'] = $betrag;
		}

		$beleg_datum = sanitize_text_field( (string) ( $ocr['datum'] ?? '' ) );
		if ( $beleg_datum !== '' ) {
			update_post_meta( $post_id, 'beleg_datum', $beleg_datum );
			update_post_meta( $post_id, '_cmx_beleg_rng_datum', $beleg_datum );
			$applied['datum'] = $beleg_datum;
		}

		$faellig_am = sanitize_text_field( (string) ( $ocr['faellig_am'] ?? '' ) );
		if ( $faellig_am !== '' ) {
			update_post_meta( $post_id, '_cmx_beleg_faelligkeitsdatum', $faellig_am );
			$applied['faellig_am'] = $faellig_am;
		}

		$bezahlt_am = sanitize_text_field( (string) ( $ocr['bezahlt_am'] ?? '' ) );
		if ( $bezahlt_am !== '' ) {
			update_post_meta( $post_id, '_cmx_beleg_bezahlt_am', $bezahlt_am );
			$applied['bezahlt_am'] = $bezahlt_am;
		}

		$waehrung = strtoupper( sanitize_text_field( (string) ( $ocr['waehrung'] ?? '' ) ) );
		if ( preg_match( '/^[A-Z]{3}$/', $waehrung ) ) {
			update_post_meta( $post_id, '_cmx_beleg_waehrung', $waehrung );
			$applied['waehrung'] = $waehrung;
		}

		if ( ! empty( $ocr['_services'] ) && is_array( $ocr['_services'] ) ) {
			update_post_meta( $post_id, '_cmx_beleg_services_document', $ocr['_services'] );
		}
		$positions_note_text = self::positions_note_text_from_ocr( $ocr );
		if ( $positions_note_text !== '' && self::append_internal_note( $post_id, $positions_note_text, '_cmx_upload_pdf_positions_note_hash' ) ) {
			$applied['positionen_notiz'] = '1';
			$applied['positionen_notiz_text'] = $positions_note_text;
		}

		$kontakt = self::resolve_or_create_contact_from_ocr( $ocr );
		$kontakt_id = (int) ( $kontakt['id'] ?? 0 );
		$kontakt_label = sanitize_text_field( (string) ( $kontakt['label'] ?? ( $ocr['kontakt_label'] ?? '' ) ) );
		$kontakt_addr = sanitize_textarea_field( (string) ( $kontakt['addr'] ?? ( $ocr['kontakt_addr'] ?? '' ) ) );
		if ( ! self::is_plausible_contact_address( $kontakt_addr ) ) {
			$kontakt_addr = '';
		}
		if ( $kontakt_id > 0 ) {
			$applied_label = $kontakt_label !== '' ? $kontakt_label : trim( (string) get_the_title( $kontakt_id ) );
			update_post_meta( $post_id, '_cmx_beleg_kontakt_id', $kontakt_id );
			update_post_meta( $post_id, '_cmx_beleg_kontakt_label', $applied_label );
			$applied['kontakt_id'] = $kontakt_id;
			$applied['kontakt_label'] = $applied_label;
		} elseif ( $kontakt_label !== '' ) {
			delete_post_meta( $post_id, '_cmx_beleg_kontakt_id' );
			update_post_meta( $post_id, '_cmx_beleg_kontakt_label', $kontakt_label );
			$applied['kontakt_label'] = $kontakt_label;
		}
		if ( $kontakt_addr !== '' ) {
			update_post_meta( $post_id, '_cmx_beleg_kontakt_addr', $kontakt_addr );
			$applied['kontakt_addr'] = $kontakt_addr;
		}

		return [
			'success' => true,
			'message' => 'PDF wurde geprüft.',
			'data' => $ocr,
			'applied' => $applied,
		];
	}

	private static function effective_amount_from_ocr( array $ocr, string $file_path = '' ) : string {
		$amount = self::normalize_decimal_value( $ocr['betrag'] ?? '' );
		if ( $amount !== '' && (float) $amount > 0.0 ) {
			return $amount;
		}

		$services_data = is_array( $ocr['_services']['data'] ?? null ) ? (array) $ocr['_services']['data'] : [];
		if ( ! empty( $services_data ) ) {
			$amount = self::normalize_decimal_value( self::first_top_level_value_by_keys( $services_data, [
				'gross_total', 'total_amount', 'total', 'amount', 'invoice_total', 'payable_amount', 'grand_total', 'due_payable_total', 'balance_due', 'betrag',
			] ) );
			if ( $amount === '' || (float) $amount <= 0.0 ) {
				$amount = self::normalize_decimal_value( self::first_value_by_keys( $services_data, [
					'gross_total', 'total_amount', 'invoice_total', 'payable_amount', 'grand_total', 'due_payable_total', 'balance_due', 'betrag',
				] ) );
			}
			if ( $amount !== '' && (float) $amount > 0.0 ) {
				return $amount;
			}

			$net = self::normalize_decimal_value( self::first_value_by_keys( $services_data, [
				'net_total', 'subtotal', 'tax_basis_total',
			] ) );
			$tax = self::normalize_decimal_value( self::first_value_by_keys( $services_data, [
				'tax_total', 'vat_total', 'mwst',
			] ) );
			if ( $net !== '' && $tax !== '' && (float) $net > 0.0 && (float) $tax > 0.0 ) {
				return number_format( (float) $net + (float) $tax, 2, '.', '' );
			}
		}

		if ( $file_path !== '' && is_readable( $file_path ) ) {
			$text = self::extract_pdf_text( $file_path );
			if ( $text !== '' ) {
				$amount = self::extract_amount_from_pdf_text( $text );
				if ( $amount !== '' && (float) $amount > 0.0 ) {
					return $amount;
				}
			}
		}

		return '';
	}

	private static function has_extracted_beleg_data( array $ocr ) : bool {
		foreach ( [ 'betrag', 'datum', 'faellig_am', 'kontakt_label', '_positions_note_text' ] as $key ) {
			if ( trim( (string) ( $ocr[ $key ] ?? '' ) ) !== '' ) {
				return true;
			}
		}
		return ! empty( $ocr['positions'] ) && is_array( $ocr['positions'] );
	}

	private static function pdf_text_fallback_is_sufficient( array $ocr ) : bool {
		$amount = self::normalize_decimal_value( $ocr['betrag'] ?? '' );
		if ( $amount === '' || (float) $amount <= 0.0 ) {
			return false;
		}

		$has_contact = trim( (string) ( $ocr['kontakt_label'] ?? '' ) ) !== ''
			|| trim( (string) ( $ocr['kontakt_addr'] ?? '' ) ) !== '';
		$has_positions_note = trim( (string) ( $ocr['_positions_note_text'] ?? '' ) ) !== ''
			|| ( ! empty( $ocr['positions'] ) && is_array( $ocr['positions'] ) );

		return $has_contact && $has_positions_note;
	}

	private static function ocr_from_pdf_text( string $file_path ) : array {
		$text = self::extract_pdf_text( $file_path );
		if ( $text === '' ) {
			return [];
		}

		$out = [
			'betrag' => self::extract_amount_from_pdf_text( $text ),
			'datum' => self::extract_date_from_pdf_text( $text, [
				'Rechnungsdatum',
				'Bestell- / Rechnungsdatum',
				'Bestell-/Rechnungsdatum',
				'Belegdatum',
				'Datum',
			] ),
			'faellig_am' => self::extract_date_from_pdf_text( $text, [
				'Fälligkeitsdatum',
				'Faelligkeitsdatum',
				'Fällig am',
				'Faellig am',
				'Fällig bis',
				'Faellig bis',
				'Zahlbar bis',
			] ),
			'kontakt_label' => self::extract_contact_label_from_pdf_text( $text ),
			'kontakt_addr' => self::extract_contact_address_from_pdf_text( $text ),
			'waehrung' => stripos( $text, 'CHF' ) !== false ? 'CHF' : '',
			'_positions_note_text' => self::extract_positions_note_from_pdf_text( $text ),
			'_source' => 'pdf_text',
		];

		return array_filter( $out, static function ( $value ) : bool {
			return is_array( $value ) ? ! empty( $value ) : trim( (string) $value ) !== '';
		} );
	}

	private static function append_internal_note( int $post_id, string $text, string $hash_key = '' ) : bool {
		$text = trim( $text );
		if ( $post_id <= 0 || $text === '' ) {
			return false;
		}

		if ( $hash_key !== '' ) {
			$hash = md5( $text );
			if ( (string) get_post_meta( $post_id, $hash_key, true ) === $hash ) {
				return false;
			}
		}

		$meta_key = function_exists( __NAMESPACE__ . '\\cmx_notizen_meta_key_for_post_type' )
			? (string) cmx_notizen_meta_key_for_post_type( 'belege' )
			: '_cmx_beleg_intern_notizen';
		$rows = function_exists( __NAMESPACE__ . '\\cmx_notizen_load_rows' )
			? (array) cmx_notizen_load_rows( $post_id, 'belege' )
			: (array) get_post_meta( $post_id, $meta_key, true );
		if ( ! function_exists( __NAMESPACE__ . '\\cmx_notizen_load_rows' ) ) {
			$raw_note = get_post_meta( $post_id, $meta_key, true );
			if ( is_string( $raw_note ) && trim( $raw_note ) !== '' ) {
				$rows = [
					[
						'betreff' => 'Allgemein',
						'datum' => '',
						'zeit' => '',
						'text' => trim( $raw_note ),
						'quelle' => '',
					],
				];
			}
		}
		$rows = array_values( array_filter( $rows, static function ( $row ) : bool {
			return is_array( $row );
		} ) );

		$row = [
			'betreff' => 'Allgemein',
			'datum'   => function_exists( __NAMESPACE__ . '\\cmx_notizen_now_date' ) ? (string) cmx_notizen_now_date() : (string) current_time( 'Y-m-d' ),
			'zeit'    => function_exists( __NAMESPACE__ . '\\cmx_notizen_now_time' ) ? (string) cmx_notizen_now_time() : (string) current_time( 'H:i' ),
			'text'    => $text,
			'quelle'  => '',
		];
		if ( function_exists( __NAMESPACE__ . '\\cmx_notizen_normalize_row' ) ) {
			$normalized = cmx_notizen_normalize_row( $row );
			if ( is_array( $normalized ) ) {
				$row = $normalized;
			}
		}

		$rows[] = $row;
		update_post_meta( $post_id, $meta_key, $rows );
		if ( $hash_key !== '' ) {
			update_post_meta( $post_id, $hash_key, md5( $text ) );
		}
		return true;
	}

	private static function append_positions_note_from_ocr( int $post_id, array $ocr ) : bool {
		$text = self::positions_note_text_from_ocr( $ocr );
		if ( $text === '' ) {
			return false;
		}

		return self::append_internal_note( $post_id, $text, '_cmx_upload_pdf_positions_note_hash' );
	}

	private static function positions_note_text_from_ocr( array $ocr ) : string {
		$text_note = trim( (string) ( $ocr['_positions_note_text'] ?? '' ) );
		if ( $text_note !== '' ) {
			return self::positions_note_html_from_text( $text_note, self::normalize_text_value( $ocr['document_no'] ?? '' ) );
		}

		$positions = self::collect_positions_from_ocr( $ocr );
		if ( empty( $positions ) ) {
			return '';
		}

		$currency = strtoupper( self::normalize_text_value( $ocr['waehrung'] ?? 'CHF' ) );
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = 'CHF';
		}
		$document_no = self::normalize_text_value( $ocr['document_no'] ?? '' );
		return self::positions_note_html_table( $positions, $currency, $document_no );
	}

	private static function positions_note_html_from_text( string $text, string $document_no = '' ) : string {
		$text = trim( $text );
		if ( $text === '' ) {
			return '';
		}

		$lines = preg_split( '/\R+/', str_replace( "\xc2\xa0", ' ', $text ) );
		$lines = is_array( $lines ) ? $lines : [];
		$rows = [];
		foreach ( $lines as $line ) {
			$line = trim( (string) preg_replace( '/\s+/u', ' ', (string) $line ) );
			if ( $line === '' || preg_match( '/^PDF-Positionen:?$/iu', $line ) ) {
				continue;
			}
			$rows[] = $line;
		}
		if ( $rows === [] ) {
			return '';
		}

		$title = 'PDF-Positionen' . ( $document_no !== '' ? ' (' . esc_html( $document_no ) . ')' : '' );
		$html = '<h4>' . $title . '</h4>';
		$html .= '<table class="cmx-pdf-positionen"><thead><tr><th scope="col">#</th><th scope="col">Position</th></tr></thead><tbody>';
		foreach ( $rows as $idx => $row ) {
			$html .= '<tr><td>' . ( (int) $idx + 1 ) . '</td><td>' . esc_html( $row ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		return $html;
	}

	private static function positions_note_html_table( array $positions, string $currency = 'CHF', string $document_no = '' ) : string {
		if ( $positions === [] ) {
			return '';
		}
		$currency = strtoupper( trim( $currency ) );
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = 'CHF';
		}

		$title = 'PDF-Positionen' . ( $document_no !== '' ? ' (' . esc_html( $document_no ) . ')' : '' );
		$html = '<h4>' . $title . '</h4>';
		$html .= '<table class="cmx-pdf-positionen"><thead><tr>';
		foreach ( [ '#', 'Position', 'Beschreibung', 'Menge', 'Einzelpreis', 'Betrag' ] as $heading ) {
			$html .= '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( $positions as $idx => $position ) {
			$position = is_array( $position ) ? $position : [];
			$title_text = trim( (string) ( $position['title'] ?? '' ) );
			$description = trim( (string) ( $position['description'] ?? '' ) );
			$qty = trim( (string) ( $position['qty'] ?? '' ) );
			$unit = trim( (string) ( $position['unit'] ?? '' ) );
			$unit_price = trim( (string) ( $position['unit_price'] ?? '' ) );
			$line_total = trim( (string) ( $position['line_total'] ?? '' ) );
			$html .= '<tr>';
			$html .= '<td>' . ( (int) $idx + 1 ) . '</td>';
			$html .= '<td>' . esc_html( $title_text !== '' ? $title_text : 'Position' ) . '</td>';
			$html .= '<td>' . esc_html( $description !== $title_text ? $description : '' ) . '</td>';
			$html .= '<td>' . esc_html( trim( $qty . ' ' . $unit ) ) . '</td>';
			$html .= '<td>' . esc_html( $unit_price !== '' ? trim( $currency . ' ' . $unit_price ) : '' ) . '</td>';
			$html .= '<td>' . esc_html( $line_total !== '' ? trim( $currency . ' ' . $line_total ) : '' ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		return $html;
	}

	private static function collect_positions_from_ocr( array $ocr ) : array {
		$positions = [];
		foreach ( [ $ocr, is_array( $ocr['_services']['data'] ?? null ) ? (array) $ocr['_services']['data'] : [] ] as $source ) {
			foreach ( self::collect_position_candidates( $source ) as $candidate ) {
				$position = self::normalize_position_candidate( $candidate );
				if ( $position === null ) {
					continue;
				}
				$key = md5( implode( '|', $position ) );
				$positions[ $key ] = $position;
			}
		}
		return array_values( $positions );
	}

	private static function collect_position_candidates( $source ) : array {
		if ( ! is_array( $source ) ) {
			return [];
		}
		$out = [];
		foreach ( $source as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			$key_lc = strtolower( (string) $key );
			if ( in_array( $key_lc, [ 'positions', 'positionen', 'line_items', 'items', 'invoice_items', 'receipt_items', 'artikel', 'products', 'services' ], true ) ) {
				if ( self::is_list_array( $value ) ) {
					foreach ( $value as $row ) {
						if ( is_array( $row ) ) {
							$out[] = $row;
						}
					}
				} else {
					$out[] = $value;
				}
				continue;
			}
			$out = array_merge( $out, self::collect_position_candidates( $value ) );
		}
		return $out;
	}

	private static function normalize_position_candidate( array $row ) : ?array {
		$title = self::normalize_text_value( self::first_value_by_keys( $row, [
			'artikel_name', 'product_name', 'item_name', 'service_name', 'name', 'title', 'item', 'label',
		] ) );
		$description = self::normalize_text_value( self::first_value_by_keys( $row, [
			'beschreibung', 'description', 'details', 'text',
		] ) );
		if ( $title === '' ) {
			$title = $description;
		}
		$qty = self::normalize_decimal_value( self::first_value_by_keys( $row, [
			'menge', 'quantity', 'qty', 'count', 'amount_quantity',
		] ) );
		$unit = self::normalize_text_value( self::first_value_by_keys( $row, [
			'einheit', 'unit', 'unit_code', 'quantity_unit',
		] ) );
		$unit_price = self::normalize_decimal_value( self::first_value_by_keys( $row, [
			'preis', 'unit_price', 'price', 'net_price', 'gross_price',
		] ) );
		$line_total = self::normalize_decimal_value( self::first_value_by_keys( $row, [
			'line_total', 'total', 'amount', 'betrag', 'gross_total', 'net_total', 'subtotal',
		] ) );
		if ( $line_total === '' && $qty !== '' && $unit_price !== '' ) {
			$line_total = number_format( (float) $qty * (float) $unit_price, 2, '.', '' );
		}

		if ( $title === '' && $description === '' && $unit_price === '' && $line_total === '' ) {
			return null;
		}

		return [
			'title' => $title,
			'description' => $description,
			'qty' => $qty,
			'unit' => $unit,
			'unit_price' => $unit_price,
			'line_total' => $line_total,
		];
	}

	private static function is_list_array( array $value ) : bool {
		$expected = 0;
		foreach ( array_keys( $value ) as $key ) {
			if ( $key !== $expected++ ) {
				return false;
			}
		}
		return true;
	}

	private static function normalize_zahlungsart( string $value ) : string {
		$val = strtolower( trim( $value ) );
		if ( $val === '' ) {
			return '';
		}
		if ( strpos( $val, 'twint' ) !== false ) {
			return 'twint';
		}
		if ( strpos( $val, 'bar' ) !== false ) {
			return 'bar';
		}
		if ( strpos( $val, 'karte' ) !== false || strpos( $val, 'kredit' ) !== false ) {
			return 'karte';
		}
		if ( strpos( $val, 'überweisung' ) !== false || strpos( $val, 'ueberweisung' ) !== false || strpos( $val, 'bank' ) !== false ) {
			return 'ueberweisung';
		}
		return '';
	}

	/* ================================
	 * OCR – CH PRODUKTIONS-PROMPT
	 * ================================ */
	private static function ocr_extract( string $file_path ) : array {

		$mime = self::file_mime( $file_path );
		if ( $mime === '' ) {
			return [ '_error' => 'Unbekannter Dateityp.' ];
		}

		if ( $mime === 'application/pdf' ) {
			$text_fallback = self::ocr_from_pdf_text( $file_path );
			if ( self::pdf_text_fallback_is_sufficient( $text_fallback ) ) {
				return $text_fallback;
			}
		}

		if ( $mime === 'application/pdf' && self::services_api_key() !== '' ) {
			return self::services_process_pdf( $file_path, basename( $file_path ) );
		}

		$key = get_option( self::OPTION_AIKEY );
		if ( empty( $key ) ) {
			if ( $mime === 'application/pdf' ) {
				return [ '_error' => 'Services API-Key fehlt. Bitte in Einstellungen > System den Bearer-Token für services.misbuero.ch prüfen.' ];
			}
			return [];
		}

		if ( $mime === 'application/pdf' ) {
			if ( ! class_exists( 'Imagick' ) ) {
				return [ '_error' => 'PDF-OCR benötigt Imagick (PHP-Extension).' ];
			}
			try {
				$imagick = new \Imagick();
				$imagick->setResolution( 200, 200 );
				$imagick->readImage( $file_path . '[0]' );
				$imagick->setImageFormat( 'png' );
				$image_blob = $imagick->getImageBlob();
				$imagick->clear();
				$imagick->destroy();
				if ( ! $image_blob ) {
					return [ '_error' => 'PDF-OCR: Konnte keine Bilddaten erzeugen.' ];
				}
				$image = 'data:image/png;base64,' . base64_encode( $image_blob );
			} catch ( \Exception $e ) {
				return [ '_error' => 'PDF-OCR fehlgeschlagen: ' . $e->getMessage() ];
			}
		} elseif ( strpos( $mime, 'image/' ) === 0 ) {
			$allowed_mimes = [
				'image/png',
				'image/jpeg',
				'image/jpg',
				'image/gif',
				'image/webp',
			];
			if ( ! in_array( $mime, $allowed_mimes, true ) ) {
				return [ '_error' => 'Bildformat nicht unterstützt (bitte JPG/PNG/WebP/GIF).' ];
			}
			$norm_mime = $mime;
			if ( $mime === 'image/jpg' ) {
				$norm_mime = 'image/jpeg';
			}
			if ( $mime === 'image/x-png' ) {
				$norm_mime = 'image/png';
			}
			$blob = file_get_contents( $file_path );
			if ( $blob === false || $blob === '' ) {
				return [ '_error' => 'Bild konnte nicht gelesen werden.' ];
			}
			$image = 'data:' . $norm_mime . ';base64,' . rtrim( base64_encode( $blob ) );
		} else {
			return [ '_error' => 'OCR nur für Bilder oder PDF möglich.' ];
		}

		$prompt = self::ocr_prompt();

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

		if ( is_wp_error( $response ) ) {
			return [ '_error' => $response->get_error_message() ];
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $status < 200 || $status >= 300 ) {
			$err = json_decode( $body, true );
			$msg = is_array( $err ) && isset( $err['error']['message'] ) ? $err['error']['message'] : 'ocr_failed';
			$code = stripos( $msg, 'expected pattern' ) !== false ? 'data_url_pattern' : '';
			return [ '_error' => $msg, '_error_code' => $code ];
		}
		$data = json_decode( $body, true );
		return json_decode( $data['choices'][0]['message']['content'] ?? '', true ) ?: [];
	}

	private static function file_mime( string $file_path ) : string {
		$mime = is_readable( $file_path ) ? mime_content_type( $file_path ) : false;
		if ( ! is_string( $mime ) || $mime === '' ) {
			return '';
		}
		return (string) preg_replace( '/;.*$/', '', $mime );
	}

	private static function services_base_url() : string {
		$url = \defined( 'MIS_BUERO_SERVICES_URL' ) ? (string) \constant( 'MIS_BUERO_SERVICES_URL' ) : '';
		if ( $url === '' ) {
			$url = (string) getenv( 'MIS_BUERO_SERVICES_URL' );
		}
		if ( $url === '' ) {
			$url = (string) get_option( self::OPTION_SERVICES_URL, 'https://services.misbuero.ch' );
		}
		$url = trim( $url );
		if ( $url === '' ) {
			$url = 'https://services.misbuero.ch';
		}
		return rtrim( $url, '/' );
	}

	private static function services_api_key() : string {
		$key = \defined( 'MIS_BUERO_SERVICES_API_KEY' ) ? (string) \constant( 'MIS_BUERO_SERVICES_API_KEY' ) : '';
		if ( $key === '' ) {
			$key = (string) ( getenv( 'MIS_BUERO_SERVICES_API_KEY' ) ?: getenv( 'CMX_MIS_BUERO_SERVICES_API_KEY' ) ?: '' );
		}
		if ( $key === '' ) {
			$key = (string) get_option( self::OPTION_SERVICES_KEY, '' );
		}
		return trim( $key );
	}

	private static function services_process_pdf( string $file_path, string $filename = 'beleg.pdf' ) : array {
		$key = self::services_api_key();
		if ( $key === '' ) {
			return [];
		}
		if ( ! is_readable( $file_path ) ) {
			return [ '_error' => 'PDF konnte nicht gelesen werden.' ];
		}
		$pdf = file_get_contents( $file_path );
		if ( ! is_string( $pdf ) || $pdf === '' || ! str_starts_with( $pdf, '%PDF-' ) ) {
			return [ '_error' => 'Keine gültige PDF-Datei.' ];
		}

		$filename = sanitize_file_name( $filename );
		if ( $filename === '' || ! str_ends_with( strtolower( $filename ), '.pdf' ) ) {
			$filename = 'beleg.pdf';
		}
		$boundary = 'cmx-misbuero-' . wp_generate_uuid4();
		$body = '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="pdf"; filename="' . str_replace( '"', '', $filename ) . '"' . "\r\n"
			. "Content-Type: application/pdf\r\n\r\n"
			. $pdf . "\r\n"
			. '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="cleanup_after_processing"' . "\r\n\r\n"
			. "1\r\n"
			. '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="delete_after_processing"' . "\r\n\r\n"
			. "1\r\n"
			. '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="retain_source"' . "\r\n\r\n"
			. "0\r\n"
			. '--' . $boundary . "--\r\n";

		$endpoint = self::services_base_url() . '/api/v1/document/process';
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => 35,
				'headers' => [
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
					'X-CMX-Cleanup-After-Processing' => '1',
				],
				'body' => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return self::services_error_result( $response->get_error_message(), 0, '', $endpoint );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = (string) wp_remote_retrieve_body( $response );
		$json = json_decode( $raw_body, true );
		$document_id = is_array( $json ) ? self::services_document_id_from_response( $json ) : '';
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $json ) ? (string) ( $json['message'] ?? $json['error'] ?? '' ) : '';
			self::services_cleanup_document( $document_id );
			return self::services_error_result(
				'Services HTTP ' . $status . ': ' . ( $message !== '' ? $message : 'Verarbeitung fehlgeschlagen.' ),
				$status,
				$raw_body,
				$endpoint
			);
		}
		if ( ! is_array( $json ) ) {
			return self::services_error_result( 'Services hat kein gültiges JSON zurückgegeben.', $status, $raw_body, $endpoint );
		}
		if ( isset( $json['success'] ) && ! (bool) $json['success'] ) {
			self::services_cleanup_document( $document_id );
			return self::services_error_result(
				(string) ( $json['message'] ?? $json['error'] ?? 'Services-Verarbeitung fehlgeschlagen.' ),
				$status,
				$raw_body,
				$endpoint
			);
		}

		$normalized = self::normalize_services_response( $json );
		self::services_cleanup_document( $document_id );
		return self::apply_pdf_text_fallback( $normalized, $file_path );
	}

	private static function services_error_result( string $message, int $status = 0, string $body = '', string $endpoint = '' ) : array {
		$message = trim( $message );
		if ( $message === '' ) {
			$message = 'Services-Verarbeitung fehlgeschlagen.';
		}

		return [
			'_error' => $message,
			'_services' => array_filter( [
				'status' => $status,
				'endpoint' => $endpoint,
				'body_excerpt' => self::services_body_excerpt( $body ),
			], static function ( $value ) : bool {
				return $value !== '' && $value !== 0 && $value !== null;
			} ),
		];
	}

	private static function services_body_excerpt( string $body ) : string {
		$body = trim( wp_strip_all_tags( $body ) );
		if ( $body === '' ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $body, 0, 500, 'UTF-8' );
		}
		return substr( $body, 0, 500 );
	}

	private static function services_document_id_from_response( array $response ) : string {
		$document_id = trim( (string) ( $response['document_id'] ?? $response['id'] ?? '' ) );
		if ( $document_id !== '' ) {
			return $document_id;
		}
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return self::services_document_id_from_response( (array) $response['data'] );
		}
		return '';
	}

	private static function services_cleanup_document( string $document_id ) : void {
		$document_id = trim( $document_id );
		if ( $document_id === '' ) {
			return;
		}
		$key = self::services_api_key();
		if ( $key === '' ) {
			return;
		}

		$args = [
			'timeout' => 1,
			'blocking' => false,
			'headers' => [
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			],
		];
		$base_url = self::services_base_url();
		$encoded_id = rawurlencode( $document_id );
		$endpoints = [
			[ 'method' => 'DELETE', 'url' => $base_url . '/api/v1/document/' . $encoded_id ],
			[ 'method' => 'POST', 'url' => $base_url . '/api/v1/document/' . $encoded_id . '/cleanup' ],
			[ 'method' => 'POST', 'url' => $base_url . '/api/v1/document/cleanup', 'body' => wp_json_encode( [ 'document_id' => $document_id ] ) ],
		];

		foreach ( $endpoints as $endpoint ) {
			$request_args = $args;
			$request_args['method'] = (string) $endpoint['method'];
			if ( isset( $endpoint['body'] ) ) {
				$request_args['body'] = (string) $endpoint['body'];
			}
			wp_remote_request( (string) $endpoint['url'], $request_args );
		}
	}

	private static function apply_pdf_text_fallback( array $ocr, string $file_path ) : array {
		if ( ! is_readable( $file_path ) ) {
			return $ocr;
		}

		$current_amount = self::normalize_decimal_value( $ocr['betrag'] ?? '' );
		$needs_amount = $current_amount === '' || (float) $current_amount <= 0.0;
		$needs_date = empty( $ocr['datum'] ) || (string) $ocr['datum'] === '2023-01-01';
		$needs_due_date = empty( $ocr['faellig_am'] ) || (string) $ocr['faellig_am'] === '2023-01-01';
		$needs_contact = empty( $ocr['kontakt_label'] );
		$needs_address = empty( $ocr['kontakt_addr'] ) || ! self::is_plausible_contact_address( (string) $ocr['kontakt_addr'] );
		$needs_positions = empty( $ocr['_positions_note_text'] ) && empty( self::collect_positions_from_ocr( $ocr ) );
		if ( ! $needs_amount && ! $needs_date && ! $needs_due_date && ! $needs_contact && ! $needs_address && ! $needs_positions ) {
			return $ocr;
		}

		$text = self::extract_pdf_text( $file_path );
		if ( $text === '' ) {
			return $ocr;
		}

		if ( $needs_amount ) {
			$fallback_amount = self::extract_amount_from_pdf_text( $text );
			if ( $fallback_amount !== '' ) {
				$ocr['betrag'] = $fallback_amount;
			}
		}

		if ( $needs_date ) {
			$fallback_date = self::extract_date_from_pdf_text( $text, [
				'Rechnungsdatum',
				'Belegdatum',
				'Datum',
			] );
			if ( $fallback_date !== '' ) {
				$ocr['datum'] = $fallback_date;
			}
		}

		if ( $needs_due_date ) {
			$fallback_due_date = self::extract_date_from_pdf_text( $text, [
				'Fälligkeitsdatum',
				'Faelligkeitsdatum',
				'Fällig am',
				'Faellig am',
				'Zahlbar bis',
			] );
			if ( $fallback_due_date !== '' ) {
				$ocr['faellig_am'] = $fallback_due_date;
			}
		}

		if ( $needs_contact ) {
			$fallback_contact = self::extract_contact_label_from_pdf_text( $text );
			if ( $fallback_contact !== '' ) {
				$ocr['kontakt_label'] = $fallback_contact;
			}
		}

		if ( $needs_address ) {
			$fallback_address = self::extract_contact_address_from_pdf_text( $text );
			if ( $fallback_address !== '' ) {
				$ocr['kontakt_addr'] = $fallback_address;
			}
		}

		if ( $needs_positions ) {
			$fallback_positions_note = self::extract_positions_note_from_pdf_text( $text );
			if ( $fallback_positions_note !== '' ) {
				$ocr['_positions_note_text'] = $fallback_positions_note;
			}
		}

		return $ocr;
	}

	private static function extract_pdf_text( string $file_path ) : string {
		if ( \class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			try {
				$parser = new \Smalot\PdfParser\Parser();
				$pdf = $parser->parseFile( $file_path );
				$text = (string) $pdf->getText();
				if ( trim( $text ) !== '' ) {
					return $text;
				}
			} catch ( \Throwable $exception ) {
				return '';
			}
		}

		return '';
	}

	private static function extract_amount_from_pdf_text( string $text ) : string {
		$lines = preg_split( '/\R/u', str_replace( "\xc2\xa0", ' ', $text ) );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$patterns = [
			'/\bRechnungsbetrag\b[^\d\-]*((?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´.,]*)/iu',
			'/\bTotal\s+aller\s+Lieferungen\s+und\s+Leistungen\b[^\d\-]*((?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´.,]*)/iu',
			'/\b(?:Rechnungstotal|Rechnung\s+Total|Total\s+Rechnung|Total\s+inkl\.?\s+MWST|Totalbetrag|Endbetrag|Schlussbetrag|Saldo|Offener\s+Betrag)\b[^\d\-]*((?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´.,]*)/iu',
			'/\bTotal\b[^\d\-]*((?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´.,]*(?:[.,]\d{2}))/iu',
			'/\bZu\s+bezahlen\b[^\d\-]*((?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´.,]*)/iu',
			'/\bZahlbetrag\b[^\d\-]*((?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´.,]*)/iu',
			'/\bBetrag\b[^\d\-]*((?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´.,]*)/iu',
			'/\bGesamtbetrag\b.*\s((?:CHF|Fr\.?)?\s*-?\d[\d\'’`´]*(?:[.,]\d{2}))\s*$/iu',
		];

		foreach ( $patterns as $pattern ) {
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( $line === '' || ! preg_match( $pattern, $line, $matches ) ) {
					continue;
				}
				$amount = self::normalize_decimal_value( (string) $matches[1] );
				if ( $amount !== '' && (float) $amount > 0.0 ) {
					return $amount;
				}
			}
		}

		$candidates = [];
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			$keyword_line = preg_match( '/\b(total|betrag|saldo|zahlbar|bezahlen|payable|amount|balance)\b/iu', $line );
			$exclude_line = preg_match( '/\b(mwst|vat|steuer|tax|zwischensumme|subtotal|rabatt|discount)\b/iu', $line );
			if ( ! $keyword_line || $exclude_line ) {
				continue;
			}
			if ( ! preg_match_all( '/(?:CHF|Fr\.?)?\s*-?\d[\d\s\'’`´]*(?:[.,]\d{2})/iu', $line, $matches ) ) {
				continue;
			}
			foreach ( (array) $matches[0] as $raw_amount ) {
				$amount = self::normalize_decimal_value( (string) $raw_amount );
				if ( $amount !== '' && (float) $amount > 0.0 ) {
					$candidates[] = $amount;
				}
			}
		}
		if ( ! empty( $candidates ) ) {
			return (string) end( $candidates );
		}

		return '';
	}

	private static function extract_date_from_pdf_text( string $text, array $labels ) : string {
		$lines = preg_split( '/\R/u', str_replace( "\xc2\xa0", ' ', $text ) );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		foreach ( $labels as $label ) {
			$quoted = preg_quote( (string) $label, '/' );
			foreach ( $lines as $line ) {
				if ( ! preg_match( '/' . $quoted . '\s*[:\t ]*\s*(\d{1,2}[\.\/-]\d{1,2}[\.\/-]\d{4}|\d{4}-\d{2}-\d{2})/iu', (string) $line, $matches ) ) {
					continue;
				}
				$date = self::normalize_date_value( (string) $matches[1] );
				if ( $date !== '' ) {
					return $date;
				}
			}
		}

		return '';
	}

	private static function extract_contact_label_from_pdf_text( string $text ) : string {
		$lines = preg_split( '/\R/u', str_replace( "\xc2\xa0", ' ', $text ) );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$lines = array_values( array_filter( array_map( static function ( $line ) : string {
			return trim( (string) preg_replace( '/\s+/u', ' ', (string) $line ) );
		}, array_slice( $lines, 0, 80 ) ) ) );

		foreach ( $lines as $line ) {
			if ( preg_match( '/\b([A-ZÄÖÜ][\p{L}0-9&.,\'’\- ]{1,80}\s+(?:AG|GmbH|Sàrl|SA|Ltd|LLC|Inc\.?))\b/u', $line, $matches ) ) {
				return sanitize_text_field( trim( (string) $matches[1] ) );
			}
		}

		foreach ( $lines as $line ) {
			if ( preg_match( '/\b(rechnung|invoice|quittung|receipt|datum|kund|bestell|zahl|total|betrag|mwst|vat|iban|seite|page)\b/iu', $line ) ) {
				continue;
			}
			if ( preg_match( '/\d{1,2}[\.\/-]\d{1,2}[\.\/-]\d{2,4}|\d+[.,]\d{2}|@|www\.|https?:/iu', $line ) ) {
				continue;
			}
			if ( function_exists( 'mb_strlen' ) ) {
				$len = (int) mb_strlen( $line, 'UTF-8' );
			} else {
				$len = strlen( $line );
			}
			if ( $len < 3 || $len > 80 ) {
				continue;
			}
			return sanitize_text_field( $line );
		}

		return '';
	}

	private static function extract_contact_address_from_pdf_text( string $text ) : string {
		$lines = preg_split( '/\R/u', str_replace( "\xc2\xa0", ' ', $text ) );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$clean = array_values( array_filter( array_map( static function ( $line ) : string {
			return trim( (string) preg_replace( '/\s+/u', ' ', (string) $line ) );
		}, array_slice( $lines, 0, 12 ) ) ) );

		$address = [];
		foreach ( $clean as $line ) {
			if ( preg_match( '/(?:www\.|https?:|@|rechnung|invoice|datum|auftrag|kunde|referenz|reference|bestell|zahl|total|betrag|mwst|vat|iban)/iu', $line ) ) {
				if ( count( $address ) >= 2 ) {
					break;
				}
				continue;
			}
			$address[] = $line;
			if ( preg_match( '/\b\d{4,5}\s+\p{L}/u', $line ) ) {
				break;
			}
			if ( count( $address ) >= 4 ) {
				break;
			}
		}

		$address_text = $address ? sanitize_textarea_field( implode( "\n", $address ) ) : '';
		return self::is_plausible_contact_address( $address_text ) ? $address_text : '';
	}

	private static function is_plausible_contact_address( string $address ) : bool {
		$address = trim( $address );
		if ( $address === '' ) {
			return false;
		}
		if ( preg_match( '/^(?:https?:\/\/)?(?:www\.)?[\w.-]+\.[a-z]{2,}(?:\/.*)?$/iu', $address ) ) {
			return false;
		}
		if ( preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]+$/u', $address ) ) {
			return false;
		}
		$noise_only = preg_match( '/\b(?:referenznummer|referenz|reference|rechnungsnummer|invoice\s*number|kundennummer|customer\s*number|bestellnummer|auftrag|zahlungsreferenz)\b/iu', $address )
			&& ! preg_match( '/\b(?:[A-Z]{2}[-\s]*)?\d{4,6}\s+\p{L}/u', $address );
		if ( $noise_only ) {
			return false;
		}

		$has_postal_city = (bool) preg_match( '/\b(?:[A-Z]{2}[-\s]*)?\d{4,6}\s+\p{L}/u', $address );
		$has_street = (bool) preg_match( '/(?:\b\p{L}+str\.?\s*\d|\b(?:strasse|straße|gasse|weg|platz|allee|rue|road|street|avenue|boulevard|lane|drive)\b.*\d|\d+\s*[a-z]?\b.*\b(?:strasse|straße|gasse|weg|platz|allee|rue|road|street|avenue|boulevard|lane|drive)\b)/iu', $address );
		$has_country = (bool) preg_match( '/\b(?:schweiz|suisse|switzerland|deutschland|germany|österreich|oesterreich|austria|frankreich|france|italien|italy|liechtenstein|estland|estonia)\b/iu', $address );

		return $has_postal_city || $has_street || ( $has_country && preg_match( '/\d/u', $address ) );
	}

	private static function extract_positions_note_from_pdf_text( string $text ) : string {
		$lines = preg_split( '/\R/u', str_replace( "\xc2\xa0", ' ', $text ) );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$collecting = false;
		$position_lines = [];
		foreach ( $lines as $line ) {
			$line = trim( (string) preg_replace( '/\s+/u', ' ', (string) $line ) );
			if ( $line === '' ) {
				continue;
			}
			if ( ! $collecting ) {
				if ( preg_match( '/^Beschreibung\b.*\b(?:Menge|Preis|Betrag)\b/iu', $line ) ) {
					$collecting = true;
				}
				continue;
			}
			if ( preg_match( '/^(Gesamtbetrag|Total\s+aller|Rechnungsbetrag|Offener\s+Betrag|Versand|Allgemein|Garantieleistungen|Powered\s+by)\b/iu', $line ) ) {
				break;
			}
			$position_lines[] = $line;
		}

		if ( empty( $position_lines ) ) {
			foreach ( $lines as $line ) {
				$line = trim( (string) preg_replace( '/\s+/u', ' ', (string) $line ) );
				if ( $line === '' ) {
					continue;
				}
				if ( preg_match( '/\b(total|rechnungstotal|gesamtbetrag|rechnungsbetrag|saldo|mwst|vat|steuer|subtotal|zwischensumme|zahlbar|iban|qr-|referenz|seite|page)\b/iu', $line ) ) {
					continue;
				}
				if ( ! preg_match( '/\p{L}/u', $line ) || ! preg_match( '/\d[\d\s\'’`´]*(?:[.,]\d{2})/u', $line ) ) {
					continue;
				}
				$position_lines[] = $line;
				if ( count( $position_lines ) >= 12 ) {
					break;
				}
			}
		}

		if ( empty( $position_lines ) ) {
			return '';
		}

		return 'PDF-Positionen:' . "\n\n" . sanitize_textarea_field( implode( "\n", $position_lines ) );
	}

	private static function services_decode_jsonish_value( $value ) {
		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			if ( $trimmed === '' ) {
				return $value;
			}
			if ( preg_match( '/```(?:json)?\s*(.*?)```/is', $trimmed, $matches ) ) {
				$trimmed = trim( (string) $matches[1] );
			}
			if ( ( str_starts_with( $trimmed, '{' ) && str_ends_with( $trimmed, '}' ) ) || ( str_starts_with( $trimmed, '[' ) && str_ends_with( $trimmed, ']' ) ) ) {
				$decoded = json_decode( $trimmed, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					return self::services_decode_jsonish_value( $decoded );
				}
			}
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$out = [];
		foreach ( $value as $key => $child ) {
			$out[ $key ] = self::services_decode_jsonish_value( $child );
		}
		return $out;
	}

	private static function services_response_payload( array $response ) : array {
		$response = self::services_decode_jsonish_value( $response );
		if ( ! is_array( $response ) ) {
			return [];
		}

		foreach ( [ 'data', 'result', 'extraction', 'fields', 'invoice', 'receipt', 'document', 'payload', 'parsed', 'json', 'output', 'response' ] as $key ) {
			if ( ! isset( $response[ $key ] ) ) {
				continue;
			}
			$value = self::services_decode_jsonish_value( $response[ $key ] );
			if ( is_array( $value ) && $value !== [] ) {
				return $value;
			}
		}

		return $response;
	}

	private static function normalize_services_response( array $response ) : array {
		$data = self::services_response_payload( $response );
		if ( $data === [] ) {
			$data = $response;
		}
		$amount = self::normalize_decimal_value( self::first_top_level_value_by_keys( $data, [
			'gross_total', 'total_amount', 'total', 'amount', 'invoice_total', 'payable_amount', 'grand_total', 'due_payable_total', 'balance_due', 'betrag', 'bruttobetrag', 'total_gross',
		] ) );
		if ( $amount === '' || (float) $amount <= 0.0 ) {
			$amount = self::normalize_decimal_value( self::first_value_by_keys( $data, [
				'gross_total', 'total_amount', 'total', 'amount', 'invoice_total', 'payable_amount', 'grand_total', 'due_payable_total', 'balance_due', 'betrag', 'bruttobetrag', 'total_gross',
			] ) );
		}
		$tax = self::normalize_decimal_value( self::first_value_by_keys( $data, [
			'tax_total', 'vat_total', 'mwst', 'tax', 'vat',
		] ) );
		$emails = self::collect_emails_from_value( $data );
		$contact_label = self::normalize_text_value( self::first_value_by_keys( $data, [
			'supplier_name', 'vendor_name', 'seller_name', 'issuer_name', 'merchant_name', 'counterparty_name', 'contact_name', 'company', 'kontakt_label', 'lieferant', 'rechnungssteller', 'seller',
		] ) );
		$contact_email = (string) ( $emails[0] ?? '' );
		$address = self::normalize_text_value( self::first_value_by_keys( $data, [
			'supplier_address', 'vendor_address', 'seller_address', 'issuer_address', 'merchant_address', 'address', 'kontakt_addr', 'adresse', 'seller_address_lines',
		] ) );
		$positions = [];
		foreach ( self::collect_position_candidates( $data ) as $candidate ) {
			$position = self::normalize_position_candidate( $candidate );
			if ( $position !== null ) {
				$positions[] = $position;
			}
		}

		$out = [
			'datum' => self::normalize_date_value( self::first_value_by_keys( $data, [
				'document_date', 'invoice_date', 'receipt_date', 'date', 'datum', 'issued_at', 'beleg_datum',
			] ) ),
			'betrag' => $amount,
			'bezahlt_am' => self::normalize_date_value( self::first_value_by_keys( $data, [
				'paid_at', 'payment_date', 'bezahlt_am',
			] ) ),
			'faellig_am' => self::normalize_date_value( self::first_value_by_keys( $data, [
				'due_date', 'payment_due_date', 'faellig_am', 'faelligkeitsdatum',
			] ) ),
			'zahlungsart' => self::normalize_zahlungsart( self::normalize_text_value( self::first_value_by_keys( $data, [
				'payment_method', 'payment_type', 'zahlungsart',
			] ) ) ),
			'mwst' => $tax,
			'waehrung' => self::normalize_text_value( self::first_value_by_keys( $data, [
				'currency', 'waehrung',
			] ) ),
			'kontakt_label' => $contact_label,
			'kontakt_email' => $contact_email,
			'kontakt_emails' => $emails,
			'kontakt_addr' => $address,
			'document_no' => self::normalize_text_value( self::first_value_by_keys( $data, [
				'document_no', 'invoice_number', 'receipt_number', 'number', 'belegnummer', 'rechnung_nr', 'rechnungsnummer',
			] ) ),
			'positions' => $positions,
			'_services' => [
				'document_id' => (string) ( $response['document_id'] ?? '' ),
				'processing_method' => (string) ( $response['processing_method'] ?? '' ),
				'data' => $data,
			],
		];

		return array_filter( $out, static function ( $value ) : bool {
			return $value !== '' && $value !== [] && $value !== null;
		} );
	}

	private static function first_top_level_value_by_keys( $source, array $keys ) {
		if ( ! is_array( $source ) ) {
			return null;
		}
		$source_lc = [];
		foreach ( $source as $key => $value ) {
			$source_lc[ strtolower( (string) $key ) ] = $value;
		}
		foreach ( $keys as $key ) {
			$key_lc = strtolower( (string) $key );
			if ( array_key_exists( $key_lc, $source_lc ) ) {
				return $source_lc[ $key_lc ];
			}
		}
		return null;
	}

	private static function first_value_by_keys( $source, array $keys ) {
		if ( ! is_array( $source ) ) {
			return null;
		}
		$wanted = array_fill_keys( array_map( 'strtolower', $keys ), true );
		foreach ( $source as $key => $value ) {
			if ( isset( $wanted[ strtolower( (string) $key ) ] ) ) {
				return $value;
			}
		}
		foreach ( $source as $value ) {
			if ( is_array( $value ) ) {
				$found = self::first_value_by_keys( $value, $keys );
				if ( $found !== null && $found !== '' ) {
					return $found;
				}
			}
		}
		return null;
	}

	private static function normalize_text_value( $value ) : string {
		if ( is_array( $value ) ) {
			foreach ( [ 'name', 'company', 'company_name', 'title', 'label', 'display_name', 'supplier_name', 'vendor_name', 'seller_name', 'issuer_name', 'merchant_name', 'value', 'text' ] as $key ) {
				if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) && trim( (string) $value[ $key ] ) !== '' ) {
					$value = (string) $value[ $key ];
					return sanitize_text_field( trim( $value ) );
				}
			}
			$parts = [];
			foreach ( $value as $part ) {
				if ( is_scalar( $part ) ) {
					$parts[] = trim( (string) $part );
				}
			}
			$value = implode( "\n", array_filter( $parts ) );
		}
		return sanitize_text_field( trim( (string) $value ) );
	}

	private static function normalize_decimal_value( $value ) : string {
		if ( is_array( $value ) ) {
			$value = $value['amount'] ?? $value['value'] ?? reset( $value );
		}
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		$value = str_replace( ["\xc2\xa0", ' ', "'"], '', $value );
		$value = preg_replace( '/[^0-9,\.\-]/', '', $value );
		if ( ! is_string( $value ) || $value === '' ) {
			return '';
		}
		$last_comma = strrpos( $value, ',' );
		$last_dot = strrpos( $value, '.' );
		if ( $last_comma !== false && ( $last_dot === false || $last_comma > $last_dot ) ) {
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		} elseif ( $last_dot !== false ) {
			$value = str_replace( ',', '', $value );
		}
		return is_numeric( $value ) ? number_format( (float) $value, 2, '.', '' ) : '';
	}

	private static function normalize_date_value( $value ) : string {
		if ( is_array( $value ) ) {
			$value = $value['date'] ?? $value['value'] ?? reset( $value );
		}
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(\d{1,2})[\.\/-](\d{1,2})[\.\/-](\d{4})$/', $value, $m ) ) {
			return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}

	private static function collect_emails_from_value( $value ) : array {
		$out = [];
		$walk = static function ( $item ) use ( &$walk, &$out ) : void {
			if ( is_array( $item ) ) {
				foreach ( $item as $sub ) {
					$walk( $sub );
				}
				return;
			}
			$text = (string) $item;
			if ( $text === '' || ! preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches ) ) {
				return;
			}
			foreach ( (array) $matches[0] as $email ) {
				$email = sanitize_email( $email );
				if ( is_email( $email ) ) {
					$out[ strtolower( $email ) ] = $email;
				}
			}
		};
		$walk( $value );
		return array_values( $out );
	}

	private static function contact_emails_from_ocr( array $ocr ) : array {
		$emails = (array) ( $ocr['kontakt_emails'] ?? [] );
		if ( ! empty( $ocr['kontakt_email'] ) ) {
			$emails[] = (string) $ocr['kontakt_email'];
		}
		return self::collect_emails_from_value( $emails );
	}

	private static function resolve_existing_contact_from_ocr( array $ocr ) : int {
		$emails = self::contact_emails_from_ocr( $ocr );
		if ( function_exists( __NAMESPACE__ . '\\cmx_belegeingang_find_kontakt_id_by_emails' ) ) {
			$kontakt_id = (int) cmx_belegeingang_find_kontakt_id_by_emails( $emails );
			if ( $kontakt_id > 0 ) {
				return $kontakt_id;
			}
		}

		return 0;
	}

	private static function normalize_contact_label( string $label ) : string {
		if ( function_exists( __NAMESPACE__ . '\\cmx_normalize_minus_sign' ) ) {
			$label = (string) cmx_normalize_minus_sign( $label );
		}
		$label = sanitize_text_field( $label );
		$label = (string) preg_replace( '/\s+/', ' ', $label );
		return trim( $label );
	}

	private static function is_plausible_contact_label( string $label ) : bool {
		$label = self::normalize_contact_label( $label );
		if ( $label === '' || strlen( $label ) < 2 ) {
			return false;
		}
		$lower = strtolower( $label );
		foreach ( [ 'rechnung', 'quittung', 'beleg', 'total', 'summe', 'zahlbar', 'mwst', 'mehrwertsteuer' ] as $bad ) {
			if ( $lower === $bad ) {
				return false;
			}
		}
		return (bool) preg_match( '/[a-zA-ZÄÖÜäöü]/', $label );
	}

	private static function find_existing_contact_by_post_title( string $label ) : int {
		global $wpdb;

		$label = self::normalize_contact_label( $label );
		if ( ! self::is_plausible_contact_label( $label ) ) {
			return 0;
		}

		$cpt = function_exists( __NAMESPACE__ . '\\cmx_kontakte_cpt' ) ? (string) cmx_kontakte_cpt() : 'kontakte';
		if ( ! post_type_exists( $cpt ) || ! isset( $wpdb->posts ) ) {
			return 0;
		}

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'trash' AND post_title = %s ORDER BY ID ASC LIMIT 5",
			$cpt,
			$label
		) );
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && get_post_type( $id ) === $cpt ) {
				return $id;
			}
		}

		$normalized_label = strtolower( self::normalize_contact_label( $label ) );
		$candidate_ids = get_posts( [
			'post_type' => $cpt,
			'post_status' => 'any',
			'posts_per_page' => 20,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
			'title' => $label,
		] );
		foreach ( (array) $candidate_ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 || get_post_type( $id ) !== $cpt ) {
				continue;
			}
			$title = strtolower( self::normalize_contact_label( (string) get_the_title( $id ) ) );
			if ( $title === $normalized_label ) {
				return $id;
			}
		}

		return 0;
	}

	private static function find_existing_contact_by_label( string $label ) : int {
		$label = self::normalize_contact_label( $label );
		if ( ! self::is_plausible_contact_label( $label ) ) {
			return 0;
		}
		$kontakt_id = self::find_existing_contact_by_post_title( $label );
		if ( $kontakt_id > 0 ) {
			return $kontakt_id;
		}
		if ( function_exists( __NAMESPACE__ . '\\cmx_beleg_find_existing_kontakt_id_from_label' ) ) {
			$kontakt_id = (int) cmx_beleg_find_existing_kontakt_id_from_label( $label );
			if ( $kontakt_id > 0 ) {
				return $kontakt_id;
			}
		}

		$cpt = function_exists( __NAMESPACE__ . '\\cmx_kontakte_cpt' ) ? (string) cmx_kontakte_cpt() : 'kontakte';
		if ( ! post_type_exists( $cpt ) ) {
			return 0;
		}

		$ids = get_posts( [
			'post_type' => $cpt,
			'post_status' => 'any',
			'posts_per_page' => 10,
			'fields' => 'ids',
			'no_found_rows' => true,
			'suppress_filters' => true,
			's' => $label,
		] );
		$needle = strtolower( self::normalize_contact_label( $label ) );
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			$title = strtolower( self::normalize_contact_label( (string) get_the_title( $id ) ) );
			if ( $title === $needle ) {
				return $id;
			}
		}

		return 0;
	}

	private static function contact_address_without_label( string $address, string $label ) : string {
		$address = trim( $address );
		$label = self::normalize_contact_label( $label );
		if ( $address === '' || $label === '' ) {
			return $address;
		}
		$label_norm = strtolower( self::normalize_contact_label( $label ) );
		$lines = preg_split( '/\R+/', $address ) ?: [];
		$out = [];
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			if ( strtolower( self::normalize_contact_label( $line ) ) === $label_norm ) {
				continue;
			}
			$out[] = $line;
		}
		return implode( "\n", $out );
	}

	private static function normalize_country_code( string $country ) : string {
		$country = trim( $country );
		if ( $country === '' ) {
			return '';
		}

		if ( function_exists( __NAMESPACE__ . '\\cmx_kontakte_normalize_country_meta_value' ) ) {
			$country = (string) cmx_kontakte_normalize_country_meta_value( $country );
		}
		$key = function_exists( 'remove_accents' ) ? remove_accents( $country ) : $country;
		$key = strtolower( trim( $key ) );
		$key = preg_replace( '/[^a-z]+/', '', $key ) ?: $key;

		$map = [
			'ch' => 'CH', 'che' => 'CH', 'schweiz' => 'CH', 'switzerland' => 'CH', 'suisse' => 'CH', 'svizzera' => 'CH',
			'de' => 'DE', 'deu' => 'DE', 'deutschland' => 'DE', 'germany' => 'DE',
			'at' => 'AT', 'aut' => 'AT', 'osterreich' => 'AT', 'oesterreich' => 'AT', 'austria' => 'AT',
			'us' => 'US', 'usa' => 'US', 'amerika' => 'US', 'unitedstates' => 'US',
			'gb' => 'GB', 'uk' => 'GB', 'unitedkingdom' => 'GB', 'grossbritannien' => 'GB',
			'it' => 'IT', 'ita' => 'IT', 'italien' => 'IT', 'italy' => 'IT',
			'fr' => 'FR', 'fra' => 'FR', 'frankreich' => 'FR', 'france' => 'FR',
			'li' => 'LI', 'liechtenstein' => 'LI',
			'ee' => 'EE', 'estland' => 'EE', 'estonia' => 'EE',
		];

		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		$upper = strtoupper( $country );
		return preg_match( '/^[A-Z]{2}$/', $upper ) ? $upper : '';
	}

	private static function parse_contact_address( string $address, string $label = '' ) : array {
		$address = sanitize_textarea_field( self::contact_address_without_label( $address, $label ) );
		if ( ! self::is_plausible_contact_address( $address ) ) {
			return [];
		}

		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R+/', $address ) ?: [] ) ) );
		$street = '';
		$zip = '';
		$city = '';
		$country = '';
		$remaining = [];

		foreach ( $lines as $line ) {
			if ( preg_match( '/^(?:([A-Z]{2})[-\s]*)?(\d{4,6})\s+(.+)$/u', $line, $m ) ) {
				$country = self::normalize_country_code( (string) ( $m[1] ?? '' ) );
				$zip = (string) $m[2];
				$city = trim( (string) $m[3] );
				continue;
			}
			$line_country = self::normalize_country_code( $line );
			if ( $line_country !== '' && ! preg_match( '/\d/u', $line ) ) {
				$country = $line_country;
				continue;
			}
			$remaining[] = $line;
		}

		foreach ( $remaining as $idx => $line ) {
			if ( preg_match( '/\d/', $line ) ) {
				$street = $line;
				unset( $remaining[ $idx ] );
				break;
			}
		}
		if ( $street === '' && $remaining !== [] ) {
			$street = implode( "\n", $remaining );
		}

		return [
			'street' => $street,
			'zip' => $zip,
			'city' => $city,
			'country' => $country,
			'raw' => $address,
		];
	}

	private static function update_contact_from_ocr( int $kontakt_id, array $ocr, string $label = '' ) : void {
		if ( $kontakt_id <= 0 ) {
			return;
		}

		$emails = self::contact_emails_from_ocr( $ocr );
		if ( $emails !== [] ) {
			if ( function_exists( __NAMESPACE__ . '\\cmx_belegeingang_merge_contact_emails' ) ) {
				cmx_belegeingang_merge_contact_emails( $kontakt_id, $emails );
			}
			if ( sanitize_email( (string) get_post_meta( $kontakt_id, '_cmx_email_1', true ) ) === '' ) {
				update_post_meta( $kontakt_id, '_cmx_email_1', (string) $emails[0] );
			}
			if ( function_exists( __NAMESPACE__ . '\\cmx_kommunikation_persist_contacts' ) && function_exists( __NAMESPACE__ . '\\cmx_kommunikation_read_contacts' ) ) {
				$rows = array_values( array_filter( (array) cmx_kommunikation_read_contacts( $kontakt_id ), static fn( $row ) : bool => is_array( $row ) ) );
				$known = [];
				foreach ( $rows as $row ) {
					$email = sanitize_email( (string) ( $row['email'] ?? '' ) );
					if ( is_email( $email ) ) {
						$known[ strtolower( $email ) ] = true;
					}
				}
				foreach ( $emails as $email ) {
					$email = sanitize_email( (string) $email );
					if ( ! is_email( $email ) || isset( $known[ strtolower( $email ) ] ) ) {
						continue;
					}
					$rows[] = [
						'vorname' => '',
						'nachname' => '',
						'telefon_label' => '',
						'telefon' => '',
						'email_label' => $rows === [] ? 'E-Mail' : 'Weitere E-Mail',
						'email' => $email,
						'geburtsdatum' => '',
						'anrede' => '',
						'duzis' => '0',
					];
				}
				cmx_kommunikation_persist_contacts( $kontakt_id, $rows );
			}
		}

		$address = sanitize_textarea_field( (string) ( $ocr['kontakt_addr'] ?? '' ) );
		$parsed = self::parse_contact_address( $address, $label );
		if ( $parsed === [] ) {
			return;
		}

		$address_keys = [
			'_cmx_rechnung_strasse' => (string) ( $parsed['street'] ?? '' ),
			'_cmx_rechnung_plz' => (string) ( $parsed['zip'] ?? '' ),
			'_cmx_rechnung_ort' => (string) ( $parsed['city'] ?? '' ),
			'_cmx_rechnung_land' => (string) ( $parsed['country'] ?? '' ),
		];
		foreach ( $address_keys as $meta_key => $value ) {
			$value = trim( $value );
			if ( $value !== '' && trim( (string) get_post_meta( $kontakt_id, $meta_key, true ) ) === '' ) {
				update_post_meta( $kontakt_id, $meta_key, $value );
			}
		}
		if ( trim( (string) get_post_meta( $kontakt_id, '_cmx_belegeingang_import_addr', true ) ) === '' ) {
			update_post_meta( $kontakt_id, '_cmx_belegeingang_import_addr', (string) ( $parsed['raw'] ?? $address ) );
		}
	}

	private static function create_contact_from_ocr( array $ocr, string $label ) : int {
		$label = self::normalize_contact_label( $label );
		if ( ! self::is_plausible_contact_label( $label ) ) {
			return 0;
		}

		$cpt = function_exists( __NAMESPACE__ . '\\cmx_kontakte_cpt' ) ? (string) cmx_kontakte_cpt() : 'kontakte';
		if ( ! post_type_exists( $cpt ) ) {
			return 0;
		}

		$address = sanitize_textarea_field( (string) ( $ocr['kontakt_addr'] ?? '' ) );
		$parsed = self::parse_contact_address( $address, $label );
		$emails = self::contact_emails_from_ocr( $ocr );
		$meta_input = [
			'_cmx_email_1' => (string) ( $emails[0] ?? '' ),
		];
		if ( $parsed !== [] ) {
			$meta_input['_cmx_rechnung_strasse'] = (string) ( $parsed['street'] ?? '' );
			$meta_input['_cmx_rechnung_plz'] = (string) ( $parsed['zip'] ?? '' );
			$meta_input['_cmx_rechnung_ort'] = (string) ( $parsed['city'] ?? '' );
			$meta_input['_cmx_rechnung_land'] = (string) ( $parsed['country'] ?? '' );
			$meta_input['_cmx_belegeingang_import_addr'] = (string) ( $parsed['raw'] ?? '' );
		}

		$inserted = wp_insert_post( [
			'post_type' => $cpt,
			'post_status' => 'publish',
			'post_title' => $label,
			'meta_input' => array_filter( $meta_input, static function ( $value ) : bool {
				return trim( (string) $value ) !== '';
			} ),
		], true );

		if ( is_wp_error( $inserted ) || (int) $inserted <= 0 ) {
			return 0;
		}

		$kontakt_id = (int) $inserted;
		self::update_contact_from_ocr( $kontakt_id, $ocr, $label );
		return $kontakt_id;
	}

	private static function resolve_or_create_contact_from_ocr( array $ocr ) : array {
		$label = self::normalize_contact_label( (string) ( $ocr['kontakt_label'] ?? '' ) );
		$addr = sanitize_textarea_field( (string) ( $ocr['kontakt_addr'] ?? '' ) );
		$kontakt_id = self::resolve_existing_contact_from_ocr( $ocr );
		if ( $kontakt_id <= 0 && $label !== '' ) {
			$kontakt_id = self::find_existing_contact_by_label( $label );
		}
		if ( $kontakt_id <= 0 && $label !== '' ) {
			$kontakt_id = self::create_contact_from_ocr( $ocr, $label );
		}
		if ( $kontakt_id > 0 ) {
			self::update_contact_from_ocr( $kontakt_id, $ocr, $label );
			$title = trim( (string) get_the_title( $kontakt_id ) );
			if ( $title !== '' ) {
				$label = $title;
			}
			if ( $addr === '' && function_exists( __NAMESPACE__ . '\\cmx_build_kontakt_postanschrift' ) ) {
				$addr = (string) cmx_build_kontakt_postanschrift( $kontakt_id );
			}
		}

		return [
			'id' => $kontakt_id,
			'label' => $label,
			'addr' => $addr,
		];
	}

	private static function ocr_prompt() : string {
		return <<<PROMPT
Du siehst einen Beleg aus der Schweiz.

Extrahiere, wenn eindeutig vorhanden:

1. datum – Zahlungs- oder Belegdatum (YYYY-MM-DD)
2. betrag – Bruttobetrag inkl. MwSt (Zahl mit Punkt)
3. bezahlt_am – Zahlungsdatum (YYYY-MM-DD), falls klar erkennbar
4. zahlungsart – nur einer dieser Werte: bar, twint, karte, ueberweisung
5. mwst – explizit ausgewiesener MwSt-Betrag (Zahl mit Punkt)
6. kontakt_label – Name/Firma des Rechnungsstellers oder Lieferanten
7. kontakt_addr – Postadresse des Rechnungsstellers, falls klar lesbar
8. document_no – Rechnungsnummer oder Belegnummer
9. positions – alle klar erkennbaren Rechnungspositionen

Regeln:
- MwSt nur ausgeben, wenn sie explizit ausgewiesen ist
- Keine Berechnung, kein Schätzen
- Wenn unklar → leer lassen
- Positionen nicht als Artikel interpretieren, nur aus dem PDF abschreiben
- Antworte ausschließlich als JSON

{
  "datum": "",
  "betrag": "",
  "bezahlt_am": "",
  "zahlungsart": "",
  "mwst": "",
  "kontakt_label": "",
  "kontakt_addr": "",
  "document_no": "",
  "positions": [
    {
      "title": "",
      "description": "",
      "qty": "",
      "unit": "",
      "unit_price": "",
      "line_total": ""
    }
  ]
}
PROMPT;
	}
}

MIS_BUERO_BELEG_UPLOAD::init();
