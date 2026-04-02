<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Beleg-Abo / wiederkehrender Versand
 *
 * Metabox im CPT "belege" für:
 * - Aktivieren
 * - Rhythmus: wöchentlich / monatlich / jährlich
 * - Wochentag / Monatstag / Jahresdatum
 * - Uhrzeit
 * - Sofort einmalig ausführen
 *
 * Hinweis:
 * Diese Datei kümmert sich um die UI, Speicherung und next_run-Berechnung.
 * Die eigentliche Versandlogik kannst Du in cmx_beleg_abo_execute() ergänzen
 * oder per Hook cmx_beleg_abo_execute anbinden.
 */

if (!class_exists(__NAMESPACE__ . '\\CMX_Beleg_Abo')) {

	final class CMX_Beleg_Abo {

		const POST_TYPE = 'belege';

		const META_ENABLED        = '_cmx_abo_enabled';
		const META_FREQUENCY      = '_cmx_abo_frequency';
		const META_WEEKDAY        = '_cmx_abo_weekday';
		const META_MONTHDAY       = '_cmx_abo_monthday';
		const META_YEAR_MONTH     = '_cmx_abo_year_month';
		const META_YEAR_DAY       = '_cmx_abo_year_day';
		const META_TIME           = '_cmx_abo_time';
		const META_NEXT_RUN       = '_cmx_abo_next_run';
		const META_LAST_RUN       = '_cmx_abo_last_run';
		const META_IMMEDIATE_ONCE = '_cmx_abo_immediate_once';

		public static function init() {
			add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
			add_action('save_post', array(__CLASS__, 'save_meta_box'), 20, 2);
			add_action('admin_notices', array(__CLASS__, 'admin_notice'));

			/**
			 * Optionaler Cron-Hook:
			 * Falls Du später WP-Cron oder einen echten Server-Job anbindest.
			 */
			add_action('cmx_beleg_abo_cron_process', array(__CLASS__, 'process_due_belege'));
		}

		public static function add_meta_box() {
			add_meta_box(
				'cmx-beleg-abo',
				__('Wiederkehrender Versand', 'cmx-misbuero'),
				array(__CLASS__, 'render_meta_box'),
				self::POST_TYPE,
				'side',
				'default'
			);
		}

		public static function render_meta_box($post) {
			wp_nonce_field('cmx_beleg_abo_save', 'cmx_beleg_abo_nonce');

			$enabled        = get_post_meta($post->ID, self::META_ENABLED, true);
			$frequency      = get_post_meta($post->ID, self::META_FREQUENCY, true);
			$weekday        = get_post_meta($post->ID, self::META_WEEKDAY, true);
			$monthday       = get_post_meta($post->ID, self::META_MONTHDAY, true);
			$year_month     = get_post_meta($post->ID, self::META_YEAR_MONTH, true);
			$year_day       = get_post_meta($post->ID, self::META_YEAR_DAY, true);
			$time           = get_post_meta($post->ID, self::META_TIME, true);
			$next_run       = get_post_meta($post->ID, self::META_NEXT_RUN, true);
			$last_run       = get_post_meta($post->ID, self::META_LAST_RUN, true);
			$immediate_once = get_post_meta($post->ID, self::META_IMMEDIATE_ONCE, true);

			if (empty($frequency)) {
				$frequency = 'monthly';
			}

			if (empty($weekday)) {
				$weekday = '1';
			}

			if (empty($monthday)) {
				$monthday = '1';
			}

			if (empty($year_month)) {
				$year_month = '1';
			}

			if (empty($year_day)) {
				$year_day = '1';
			}

			if (empty($time)) {
				$time = '08:00';
			}

			?>
			<p>
				<label>
					<input type="checkbox" name="cmx_abo_enabled" value="1" <?php checked($enabled, '1'); ?> />
					<?php echo esc_html__('Wiederkehrenden Versand aktivieren', 'cmx-misbuero'); ?>
				</label>
			</p>

			<p>
				<label for="cmx_abo_frequency"><strong><?php echo esc_html__('Rhythmus', 'cmx-misbuero'); ?></strong></label><br />
				<select name="cmx_abo_frequency" id="cmx_abo_frequency" style="width:100%;">
					<option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php echo esc_html__('Wöchentlich', 'cmx-misbuero'); ?></option>
					<option value="monthly" <?php selected($frequency, 'monthly'); ?>><?php echo esc_html__('Monatlich', 'cmx-misbuero'); ?></option>
					<option value="yearly" <?php selected($frequency, 'yearly'); ?>><?php echo esc_html__('Jährlich', 'cmx-misbuero'); ?></option>
				</select>
			</p>

			<p>
				<label for="cmx_abo_weekday"><strong><?php echo esc_html__('Wochentag', 'cmx-misbuero'); ?></strong></label><br />
				<select name="cmx_abo_weekday" id="cmx_abo_weekday" style="width:100%;">
					<option value="1" <?php selected($weekday, '1'); ?>><?php echo esc_html__('Montag', 'cmx-misbuero'); ?></option>
					<option value="2" <?php selected($weekday, '2'); ?>><?php echo esc_html__('Dienstag', 'cmx-misbuero'); ?></option>
					<option value="3" <?php selected($weekday, '3'); ?>><?php echo esc_html__('Mittwoch', 'cmx-misbuero'); ?></option>
					<option value="4" <?php selected($weekday, '4'); ?>><?php echo esc_html__('Donnerstag', 'cmx-misbuero'); ?></option>
					<option value="5" <?php selected($weekday, '5'); ?>><?php echo esc_html__('Freitag', 'cmx-misbuero'); ?></option>
					<option value="6" <?php selected($weekday, '6'); ?>><?php echo esc_html__('Samstag', 'cmx-misbuero'); ?></option>
					<option value="7" <?php selected($weekday, '7'); ?>><?php echo esc_html__('Sonntag', 'cmx-misbuero'); ?></option>
				</select>
			</p>

			<p>
				<label for="cmx_abo_monthday"><strong><?php echo esc_html__('Tag im Monat', 'cmx-misbuero'); ?></strong></label><br />
				<input type="number" min="1" max="31" name="cmx_abo_monthday" id="cmx_abo_monthday" value="<?php echo esc_attr($monthday); ?>" style="width:100%;" />
			</p>

			<p>
				<label for="cmx_abo_year_month"><strong><?php echo esc_html__('Monat im Jahr', 'cmx-misbuero'); ?></strong></label><br />
				<input type="number" min="1" max="12" name="cmx_abo_year_month" id="cmx_abo_year_month" value="<?php echo esc_attr($year_month); ?>" style="width:100%;" />
			</p>

			<p>
				<label for="cmx_abo_year_day"><strong><?php echo esc_html__('Tag im Jahr-Monat', 'cmx-misbuero'); ?></strong></label><br />
				<input type="number" min="1" max="31" name="cmx_abo_year_day" id="cmx_abo_year_day" value="<?php echo esc_attr($year_day); ?>" style="width:100%;" />
			</p>

			<p>
				<label for="cmx_abo_time"><strong><?php echo esc_html__('Uhrzeit', 'cmx-misbuero'); ?></strong></label><br />
				<input type="time" name="cmx_abo_time" id="cmx_abo_time" value="<?php echo esc_attr($time); ?>" style="width:100%;" />
			</p>

			<hr />

			<p>
				<label>
					<input type="checkbox" name="cmx_abo_immediate_once" value="1" <?php checked($immediate_once, '1'); ?> />
					<?php echo esc_html__('Sofort einmalig ausführen', 'cmx-misbuero'); ?>
				</label>
			</p>

			<?php if (!empty($next_run)) : ?>
				<p>
					<strong><?php echo esc_html__('Nächste Ausführung:', 'cmx-misbuero'); ?></strong><br />
					<?php echo esc_html($next_run); ?>
				</p>
			<?php endif; ?>

			<?php if (!empty($last_run)) : ?>
				<p>
					<strong><?php echo esc_html__('Letzte Ausführung:', 'cmx-misbuero'); ?></strong><br />
					<?php echo esc_html($last_run); ?>
				</p>
			<?php endif; ?>

			<p style="font-size:12px;color:#666;">
				<?php echo esc_html__('Hinweis: Beim Speichern mit „Sofort einmalig ausführen“ wird der Beleg sofort verarbeitet und versendet.', 'cmx-misbuero'); ?>
			</p>
			<?php
		}

		public static function save_meta_box($post_id, $post) {
			if (!isset($_POST['cmx_beleg_abo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cmx_beleg_abo_nonce'])), 'cmx_beleg_abo_save')) {
				return;
			}

			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			if (!current_user_can('edit_post', $post_id)) {
				return;
			}

			if (self::POST_TYPE !== $post->post_type) {
				return;
			}

			$enabled        = isset($_POST['cmx_abo_enabled']) ? '1' : '0';
			$frequency      = isset($_POST['cmx_abo_frequency']) ? sanitize_key(wp_unslash($_POST['cmx_abo_frequency'])) : 'monthly';
			$weekday        = isset($_POST['cmx_abo_weekday']) ? absint(wp_unslash($_POST['cmx_abo_weekday'])) : 1;
			$monthday       = isset($_POST['cmx_abo_monthday']) ? absint(wp_unslash($_POST['cmx_abo_monthday'])) : 1;
			$year_month     = isset($_POST['cmx_abo_year_month']) ? absint(wp_unslash($_POST['cmx_abo_year_month'])) : 1;
			$year_day       = isset($_POST['cmx_abo_year_day']) ? absint(wp_unslash($_POST['cmx_abo_year_day'])) : 1;
			$time           = isset($_POST['cmx_abo_time']) ? sanitize_text_field(wp_unslash($_POST['cmx_abo_time'])) : '08:00';
			$immediate_once = isset($_POST['cmx_abo_immediate_once']) ? '1' : '0';

			$allowed_frequencies = array('weekly', 'monthly', 'yearly');
			if (!in_array($frequency, $allowed_frequencies, true)) {
				$frequency = 'monthly';
			}

			$weekday = max(1, min(7, $weekday));
			$monthday = max(1, min(31, $monthday));
			$year_month = max(1, min(12, $year_month));
			$year_day = max(1, min(31, $year_day));

			if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
				$time = '08:00';
			}

			update_post_meta($post_id, self::META_ENABLED, $enabled);
			update_post_meta($post_id, self::META_FREQUENCY, $frequency);
			update_post_meta($post_id, self::META_WEEKDAY, (string) $weekday);
			update_post_meta($post_id, self::META_MONTHDAY, (string) $monthday);
			update_post_meta($post_id, self::META_YEAR_MONTH, (string) $year_month);
			update_post_meta($post_id, self::META_YEAR_DAY, (string) $year_day);
			update_post_meta($post_id, self::META_TIME, $time);

			if ('1' === $enabled) {
				$next_run = self::calculate_next_run(array(
					'frequency'  => $frequency,
					'weekday'    => $weekday,
					'monthday'   => $monthday,
					'year_month' => $year_month,
					'year_day'   => $year_day,
					'time'       => $time,
				));

				if (!empty($next_run)) {
					update_post_meta($post_id, self::META_NEXT_RUN, $next_run);
				}
			} else {
				delete_post_meta($post_id, self::META_NEXT_RUN);
			}

			/**
			 * Sofort einmalig:
			 * - sofort ausführen
			 * - Checkbox wieder entfernen
			 */
			if ('1' === $immediate_once) {
				update_post_meta($post_id, self::META_IMMEDIATE_ONCE, '0');

				self::execute_beleg($post_id, true);

				if (!headers_sent()) {
					$redirect = add_query_arg(
						array(
							'post'                => $post_id,
							'action'              => 'edit',
							'cmx_beleg_sent_once' => '1',
						),
						admin_url('post.php')
					);

					wp_safe_redirect($redirect);
					exit;
				}
			} else {
				update_post_meta($post_id, self::META_IMMEDIATE_ONCE, '0');
			}
		}

		public static function calculate_next_run($args = array()) {
			$defaults = array(
				'frequency'  => 'monthly',
				'weekday'    => 1,
				'monthday'   => 1,
				'year_month' => 1,
				'year_day'   => 1,
				'time'       => '08:00',
			);

			$args = wp_parse_args($args, $defaults);

			$timezone_string = wp_timezone_string();
			$timezone = $timezone_string ? new \DateTimeZone($timezone_string) : new \DateTimeZone('UTC');
			$now = new \DateTime('now', $timezone);

			$parts = explode(':', $args['time']);
			$hour  = isset($parts[0]) ? absint($parts[0]) : 8;
			$min   = isset($parts[1]) ? absint($parts[1]) : 0;

			$hour = max(0, min(23, $hour));
			$min  = max(0, min(59, $min));

			$next = clone $now;

			switch ($args['frequency']) {
				case 'weekly':
					$target_weekday = max(1, min(7, absint($args['weekday'])));
					$current_weekday = (int) $next->format('N');
					$days_ahead = $target_weekday - $current_weekday;

					if ($days_ahead < 0) {
						$days_ahead += 7;
					}

					$next->setTime($hour, $min, 0);

					if (0 === $days_ahead && $next <= $now) {
						$days_ahead = 7;
					}

					if ($days_ahead > 0) {
						$next->modify('+' . $days_ahead . ' days');
					}
					break;

				case 'yearly':
					$target_month = max(1, min(12, absint($args['year_month'])));
					$target_day   = max(1, min(31, absint($args['year_day'])));
					$year         = (int) $next->format('Y');

					$max_day = cal_days_in_month(CAL_GREGORIAN, $target_month, $year);
					$target_day = min($target_day, $max_day);

					$next = new \DateTime(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $target_month, $target_day, $hour, $min), $timezone);

					if ($next <= $now) {
						$year++;
						$max_day = cal_days_in_month(CAL_GREGORIAN, $target_month, $year);
						$target_day = min($target_day, $max_day);

						$next = new \DateTime(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $target_month, $target_day, $hour, $min), $timezone);
					}
					break;

				case 'monthly':
				default:
					$target_day = max(1, min(31, absint($args['monthday'])));
					$year  = (int) $next->format('Y');
					$month = (int) $next->format('n');

					$max_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
					$day = min($target_day, $max_day);

					$next = new \DateTime(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $min), $timezone);

					if ($next <= $now) {
						$month++;

						if ($month > 12) {
							$month = 1;
							$year++;
						}

						$max_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
						$day = min($target_day, $max_day);

						$next = new \DateTime(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $min), $timezone);
					}
					break;
			}

			return $next->format('Y-m-d H:i:s');
		}

		public static function process_due_belege() {
			$args = array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array('publish', 'private'),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::META_ENABLED,
						'value' => '1',
					),
					array(
						'key'     => self::META_NEXT_RUN,
						'value'   => current_time('mysql'),
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
				),
			);

			$post_ids = get_posts($args);

			if (empty($post_ids)) {
				return;
			}

			foreach ($post_ids as $post_id) {
				self::execute_beleg($post_id, false);
			}
		}

		public static function execute_beleg($post_id, $manual = false) {
			$post_id = absint($post_id);

			if (!$post_id || self::POST_TYPE !== get_post_type($post_id)) {
				return false;
			}

			/**
			 * HIER Deine eigentliche Logik anhängen:
			 * - PDF erzeugen
			 * - E-Mail versenden
			 * - Status setzen
			 * - Log schreiben
			 *
			 * Möglichkeit A:
			 * Direkt hier eigene Funktionen aufrufen
			 *
			 * Beispiel:
			 * cmx_generate_beleg_pdf($post_id);
			 * cmx_send_beleg_email($post_id);
			 *
			 * Möglichkeit B:
			 * Über Hook anbinden
			 */
			do_action('cmx_beleg_abo_execute', $post_id, $manual);

			update_post_meta($post_id, self::META_LAST_RUN, current_time('mysql'));

			$enabled = get_post_meta($post_id, self::META_ENABLED, true);

			if ('1' === $enabled) {
				$frequency  = get_post_meta($post_id, self::META_FREQUENCY, true);
				$weekday    = get_post_meta($post_id, self::META_WEEKDAY, true);
				$monthday   = get_post_meta($post_id, self::META_MONTHDAY, true);
				$year_month = get_post_meta($post_id, self::META_YEAR_MONTH, true);
				$year_day   = get_post_meta($post_id, self::META_YEAR_DAY, true);
				$time       = get_post_meta($post_id, self::META_TIME, true);

				$next_run = self::calculate_next_run(array(
					'frequency'  => $frequency,
					'weekday'    => $weekday,
					'monthday'   => $monthday,
					'year_month' => $year_month,
					'year_day'   => $year_day,
					'time'       => $time,
				));

				update_post_meta($post_id, self::META_NEXT_RUN, $next_run);
			} else {
				delete_post_meta($post_id, self::META_NEXT_RUN);
			}

			return true;
		}

		public static function admin_notice() {
			if (!is_admin()) {
				return;
			}

			if (!isset($_GET['cmx_beleg_sent_once'])) {
				return;
			}

			if ('1' !== sanitize_text_field(wp_unslash($_GET['cmx_beleg_sent_once']))) {
				return;
			}

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Der Beleg wurde sofort einmalig verarbeitet.', 'cmx-misbuero') . '</p></div>';
		}
	}

	CMX_Beleg_Abo::init();
}
