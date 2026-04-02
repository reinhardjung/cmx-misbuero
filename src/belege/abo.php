<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || die('Oxytocin!');

if (!\class_exists(__NAMESPACE__ . '\\CMX_Beleg_Abo')) {
	final class CMX_Beleg_Abo {
		const POST_TYPE = 'belege';

		const CRON_HOOK = 'cmx_beleg_abo_cron_process';
		const CRON_INTERVAL = 'cmx_beleg_abo_five_minutes';

		const META_ENABLED = '_cmx_abo_enabled';
		const META_FREQUENCY = '_cmx_abo_frequency';
		const META_WEEKDAY = '_cmx_abo_weekday';
		const META_MONTHDAY = '_cmx_abo_monthday';
		const META_YEAR_MONTH = '_cmx_abo_year_month';
		const META_YEAR_DAY = '_cmx_abo_year_day';
		const META_TIME = '_cmx_abo_time';
		const META_NEXT_RUN = '_cmx_abo_next_run';
		const META_LAST_RUN = '_cmx_abo_last_run';
		const META_IMMEDIATE_ONCE = '_cmx_abo_immediate_once';

		const NOTICE_TRANSIENT_PREFIX = 'cmx_beleg_abo_notice_';

		public static function init(): void {
			\add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
			\add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta_box'], 20, 3);
			\add_action('admin_notices', [__CLASS__, 'admin_notice']);
			\add_filter('cron_schedules', [__CLASS__, 'register_cron_schedule']);
			\add_action('init', [__CLASS__, 'ensure_cron_event']);
			\add_action(self::CRON_HOOK, [__CLASS__, 'process_due_belege']);
		}

		public static function add_meta_box(): void {
			\add_meta_box(
				'cmx-beleg-abo',
				\__('Wiederkehrender Versand', 'cmx-misbuero'),
				[__CLASS__, 'render_meta_box'],
				self::POST_TYPE,
				'side',
				'default'
			);
		}

		public static function render_meta_box(\WP_Post $post): void {
			\wp_nonce_field('cmx_beleg_abo_save', 'cmx_beleg_abo_nonce');

			$settings = self::get_settings($post->ID);
			$post_id = (int) $post->ID;
			$js_settings = (string) \wp_json_encode([
				'frequencyId' => 'cmx_abo_frequency_' . $post_id,
				'dailyId' => 'cmx-abo-row-daily-' . $post_id,
				'weeklyId' => 'cmx-abo-row-weekly-' . $post_id,
				'monthlyId' => 'cmx-abo-row-monthly-' . $post_id,
				'quarterlyId' => 'cmx-abo-row-quarterly-' . $post_id,
				'yearlyId' => 'cmx-abo-row-yearly-' . $post_id,
			]);
			?>
			<p>
				<label>
					<input type="checkbox" name="cmx_abo_enabled" value="1" <?php \checked($settings['enabled'], '1'); ?>>
					<?php echo \esc_html__('Wiederkehrenden Versand aktivieren', 'cmx-misbuero'); ?>
				</label>
			</p>

			<p>
				<label for="<?php echo \esc_attr('cmx_abo_frequency_' . $post_id); ?>"><strong><?php echo \esc_html__('Rhythmus', 'cmx-misbuero'); ?></strong></label><br>
				<select name="cmx_abo_frequency" id="<?php echo \esc_attr('cmx_abo_frequency_' . $post_id); ?>" style="width:100%;">
					<option value="daily" <?php \selected($settings['frequency'], 'daily'); ?>><?php echo \esc_html__('Täglich', 'cmx-misbuero'); ?></option>
					<option value="weekly" <?php \selected($settings['frequency'], 'weekly'); ?>><?php echo \esc_html__('Wöchentlich', 'cmx-misbuero'); ?></option>
					<option value="monthly" <?php \selected($settings['frequency'], 'monthly'); ?>><?php echo \esc_html__('Monatlich', 'cmx-misbuero'); ?></option>
					<option value="quarterly" <?php \selected($settings['frequency'], 'quarterly'); ?>><?php echo \esc_html__('Quartal', 'cmx-misbuero'); ?></option>
					<option value="yearly" <?php \selected($settings['frequency'], 'yearly'); ?>><?php echo \esc_html__('Jährlich', 'cmx-misbuero'); ?></option>
				</select>
			</p>

			<p id="<?php echo \esc_attr('cmx-abo-row-daily-' . $post_id); ?>" class="cmx-abo-row">
				<?php echo \esc_html__('Wird jeden Tag zur gewählten Uhrzeit ausgeführt.', 'cmx-misbuero'); ?>
			</p>

			<p id="<?php echo \esc_attr('cmx-abo-row-weekly-' . $post_id); ?>" class="cmx-abo-row">
				<label for="<?php echo \esc_attr('cmx_abo_weekday_' . $post_id); ?>"><strong><?php echo \esc_html__('Wochentag', 'cmx-misbuero'); ?></strong></label><br>
				<select name="cmx_abo_weekday" id="<?php echo \esc_attr('cmx_abo_weekday_' . $post_id); ?>" style="width:100%;">
					<option value="1" <?php \selected($settings['weekday'], 1); ?>><?php echo \esc_html__('Montag', 'cmx-misbuero'); ?></option>
					<option value="2" <?php \selected($settings['weekday'], 2); ?>><?php echo \esc_html__('Dienstag', 'cmx-misbuero'); ?></option>
					<option value="3" <?php \selected($settings['weekday'], 3); ?>><?php echo \esc_html__('Mittwoch', 'cmx-misbuero'); ?></option>
					<option value="4" <?php \selected($settings['weekday'], 4); ?>><?php echo \esc_html__('Donnerstag', 'cmx-misbuero'); ?></option>
					<option value="5" <?php \selected($settings['weekday'], 5); ?>><?php echo \esc_html__('Freitag', 'cmx-misbuero'); ?></option>
					<option value="6" <?php \selected($settings['weekday'], 6); ?>><?php echo \esc_html__('Samstag', 'cmx-misbuero'); ?></option>
					<option value="7" <?php \selected($settings['weekday'], 7); ?>><?php echo \esc_html__('Sonntag', 'cmx-misbuero'); ?></option>
				</select>
			</p>

			<p id="<?php echo \esc_attr('cmx-abo-row-monthly-' . $post_id); ?>" class="cmx-abo-row">
				<label for="<?php echo \esc_attr('cmx_abo_monthday_' . $post_id); ?>"><strong><?php echo \esc_html__('Tag im Monat', 'cmx-misbuero'); ?></strong></label><br>
				<input type="number" min="1" max="31" name="cmx_abo_monthday" id="<?php echo \esc_attr('cmx_abo_monthday_' . $post_id); ?>" value="<?php echo \esc_attr((string) $settings['monthday']); ?>" style="width:100%;">
			</p>

			<p id="<?php echo \esc_attr('cmx-abo-row-quarterly-' . $post_id); ?>" class="cmx-abo-row">
				<label for="<?php echo \esc_attr('cmx_abo_quarter_monthday_' . $post_id); ?>"><strong><?php echo \esc_html__('Tag im Quartal', 'cmx-misbuero'); ?></strong></label><br>
				<input type="number" min="1" max="31" name="cmx_abo_monthday" id="<?php echo \esc_attr('cmx_abo_quarter_monthday_' . $post_id); ?>" value="<?php echo \esc_attr((string) $settings['monthday']); ?>" style="width:100%;">
				<span class="description" style="display:block;margin-top:4px;"><?php echo \esc_html__('Ausführung jeweils in Jan / Apr / Jul / Okt.', 'cmx-misbuero'); ?></span>
			</p>

			<p id="<?php echo \esc_attr('cmx-abo-row-yearly-' . $post_id); ?>" class="cmx-abo-row">
				<label for="<?php echo \esc_attr('cmx_abo_year_month_' . $post_id); ?>"><strong><?php echo \esc_html__('Monat', 'cmx-misbuero'); ?></strong></label><br>
				<input type="number" min="1" max="12" name="cmx_abo_year_month" id="<?php echo \esc_attr('cmx_abo_year_month_' . $post_id); ?>" value="<?php echo \esc_attr((string) $settings['year_month']); ?>" style="width:100%;margin-bottom:8px;">

				<label for="<?php echo \esc_attr('cmx_abo_year_day_' . $post_id); ?>"><strong><?php echo \esc_html__('Tag', 'cmx-misbuero'); ?></strong></label><br>
				<input type="number" min="1" max="31" name="cmx_abo_year_day" id="<?php echo \esc_attr('cmx_abo_year_day_' . $post_id); ?>" value="<?php echo \esc_attr((string) $settings['year_day']); ?>" style="width:100%;">
			</p>

			<p>
				<label for="<?php echo \esc_attr('cmx_abo_time_' . $post_id); ?>"><strong><?php echo \esc_html__('Uhrzeit', 'cmx-misbuero'); ?></strong></label><br>
				<input type="time" name="cmx_abo_time" id="<?php echo \esc_attr('cmx_abo_time_' . $post_id); ?>" value="<?php echo \esc_attr($settings['time']); ?>" style="width:100%;">
			</p>

			<hr>

			<p>
				<label>
					<input type="checkbox" name="cmx_abo_immediate_once" value="1">
					<?php echo \esc_html__('Sofort einmalig ausführen', 'cmx-misbuero'); ?>
				</label>
			</p>

			<?php if ($settings['next_run'] !== '') : ?>
				<p>
					<strong><?php echo \esc_html__('Nächste Ausführung', 'cmx-misbuero'); ?></strong><br>
					<?php echo \esc_html(self::format_local_datetime($settings['next_run'])); ?>
				</p>
			<?php endif; ?>

			<?php if ($settings['last_run'] !== '') : ?>
				<p>
					<strong><?php echo \esc_html__('Letzte Ausführung', 'cmx-misbuero'); ?></strong><br>
					<?php echo \esc_html(self::format_local_datetime($settings['last_run'])); ?>
				</p>
			<?php endif; ?>

			<p class="description">
				<?php echo \esc_html__('Die eigentliche Verarbeitung wird über den Hook cmx_beleg_abo_execute angebunden.', 'cmx-misbuero'); ?>
			</p>

			<script>
			(function(config){
				if (!config) {
					return;
				}
				var frequency = document.getElementById(config.frequencyId);
				if (!frequency) {
					return;
				}
				var rows = {
					daily: document.getElementById(config.dailyId),
					weekly: document.getElementById(config.weeklyId),
					monthly: document.getElementById(config.monthlyId),
					quarterly: document.getElementById(config.quarterlyId),
					yearly: document.getElementById(config.yearlyId)
				};
				var sync = function() {
					var mode = frequency.value || 'monthly';
					Object.keys(rows).forEach(function(key) {
						if (!rows[key]) {
							return;
						}
						var active = key === mode;
						rows[key].style.display = active ? '' : 'none';
						Array.prototype.forEach.call(rows[key].querySelectorAll('input, select, textarea'), function(field) {
							field.disabled = !active;
						});
					});
				};
				frequency.addEventListener('change', sync);
				sync();
			})(<?php echo $js_settings; ?>);
			</script>
			<?php
		}

		public static function save_meta_box(int $post_id, \WP_Post $post, bool $update): void {
			unset($update);

			if (!isset($_POST['cmx_beleg_abo_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_beleg_abo_nonce']), 'cmx_beleg_abo_save')) {
				return;
			}
			if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}
			if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id)) {
				return;
			}
			if (!\current_user_can('edit_post', $post_id)) {
				return;
			}
			if ((string) $post->post_type !== self::POST_TYPE) {
				return;
			}

			$settings = self::sanitize_settings_from_post($_POST);

			\update_post_meta($post_id, self::META_ENABLED, $settings['enabled']);
			\update_post_meta($post_id, self::META_FREQUENCY, $settings['frequency']);
			\update_post_meta($post_id, self::META_WEEKDAY, (string) $settings['weekday']);
			\update_post_meta($post_id, self::META_MONTHDAY, (string) $settings['monthday']);
			\update_post_meta($post_id, self::META_YEAR_MONTH, (string) $settings['year_month']);
			\update_post_meta($post_id, self::META_YEAR_DAY, (string) $settings['year_day']);
			\update_post_meta($post_id, self::META_TIME, $settings['time']);
			\update_post_meta($post_id, self::META_IMMEDIATE_ONCE, '0');

			if ($settings['enabled'] === '1') {
				$next_run = self::calculate_next_run($settings);
				if ($next_run !== '') {
					\update_post_meta($post_id, self::META_NEXT_RUN, $next_run);
				} else {
					\delete_post_meta($post_id, self::META_NEXT_RUN);
				}
			} else {
				\delete_post_meta($post_id, self::META_NEXT_RUN);
			}

			if ($settings['immediate_once'] === '1') {
				self::execute_beleg($post_id, true);
				self::queue_notice('success', \__('Der Beleg wurde sofort einmalig verarbeitet.', 'cmx-misbuero'));
			}
		}

		public static function register_cron_schedule(array $schedules): array {
			if (!isset($schedules[self::CRON_INTERVAL])) {
				$schedules[self::CRON_INTERVAL] = [
					'interval' => 300,
					'display' => \__('CMX Beleg-Abo (5 Minuten)', 'cmx-misbuero'),
				];
			}
			return $schedules;
		}

		public static function ensure_cron_event(): void {
			if (!\wp_next_scheduled(self::CRON_HOOK)) {
				\wp_schedule_event(\time() + 120, self::CRON_INTERVAL, self::CRON_HOOK);
			}
		}

		public static function process_due_belege(): void {
			$post_ids = \get_posts([
				'post_type' => self::POST_TYPE,
				'post_status' => ['publish', 'private'],
				'posts_per_page' => 50,
				'fields' => 'ids',
				'meta_query' => [
					'relation' => 'AND',
					[
						'key' => self::META_ENABLED,
						'value' => '1',
					],
					[
						'key' => self::META_NEXT_RUN,
						'value' => \current_time('mysql'),
						'compare' => '<=',
						'type' => 'DATETIME',
					],
				],
			]);

			if ($post_ids === []) {
				return;
			}

			foreach ($post_ids as $post_id) {
				self::execute_beleg((int) $post_id, false);
			}
		}

		public static function execute_beleg(int $post_id, bool $manual = false): bool {
			$post_id = \absint($post_id);
			if ($post_id <= 0 || \get_post_type($post_id) !== self::POST_TYPE) {
				return false;
			}

			$settings = self::get_settings($post_id);
			\do_action('cmx_beleg_abo_execute', $post_id, $manual, $settings);

			$last_run = \current_time('mysql');
			\update_post_meta($post_id, self::META_LAST_RUN, $last_run);
			\update_post_meta($post_id, self::META_IMMEDIATE_ONCE, '0');

			if ($settings['enabled'] === '1') {
				$next_run = self::calculate_next_run($settings);
				if ($next_run !== '') {
					\update_post_meta($post_id, self::META_NEXT_RUN, $next_run);
				} else {
					\delete_post_meta($post_id, self::META_NEXT_RUN);
				}
			} else {
				\delete_post_meta($post_id, self::META_NEXT_RUN);
			}

			return true;
		}

		public static function admin_notice(): void {
			if (!\is_admin()) {
				return;
			}

			$user_id = (int) \get_current_user_id();
			if ($user_id <= 0) {
				return;
			}

			$key = self::NOTICE_TRANSIENT_PREFIX . $user_id;
			$notice = \get_transient($key);
			if (!\is_array($notice) || empty($notice['message'])) {
				return;
			}

			\delete_transient($key);
			$type = \in_array((string) ($notice['type'] ?? ''), ['success', 'error', 'warning', 'info'], true) ? (string) $notice['type'] : 'success';
			echo '<div class="notice notice-' . \esc_attr($type) . ' is-dismissible"><p>' . \esc_html((string) $notice['message']) . '</p></div>';
		}

		private static function queue_notice(string $type, string $message): void {
			$user_id = (int) \get_current_user_id();
			if ($user_id <= 0 || $message === '') {
				return;
			}

			\set_transient(
				self::NOTICE_TRANSIENT_PREFIX . $user_id,
				[
					'type' => $type,
					'message' => $message,
				],
				120
			);
		}

		private static function get_settings(int $post_id): array {
			$frequency = (string) \get_post_meta($post_id, self::META_FREQUENCY, true);
			if (!\in_array($frequency, self::allowed_frequencies(), true)) {
				$frequency = 'monthly';
			}

			$time = self::normalize_time((string) \get_post_meta($post_id, self::META_TIME, true));

			return [
				'enabled' => \get_post_meta($post_id, self::META_ENABLED, true) === '1' ? '1' : '0',
				'frequency' => $frequency,
				'weekday' => self::normalize_weekday((int) \get_post_meta($post_id, self::META_WEEKDAY, true)),
				'monthday' => self::normalize_monthday((int) \get_post_meta($post_id, self::META_MONTHDAY, true)),
				'year_month' => self::normalize_year_month((int) \get_post_meta($post_id, self::META_YEAR_MONTH, true)),
				'year_day' => self::normalize_year_day((int) \get_post_meta($post_id, self::META_YEAR_DAY, true)),
				'time' => $time,
				'next_run' => (string) \get_post_meta($post_id, self::META_NEXT_RUN, true),
				'last_run' => (string) \get_post_meta($post_id, self::META_LAST_RUN, true),
				'immediate_once' => \get_post_meta($post_id, self::META_IMMEDIATE_ONCE, true) === '1' ? '1' : '0',
			];
		}

		private static function sanitize_settings_from_post(array $source): array {
			$frequency = isset($source['cmx_abo_frequency']) ? \sanitize_key((string) \wp_unslash($source['cmx_abo_frequency'])) : 'monthly';
			if (!\in_array($frequency, self::allowed_frequencies(), true)) {
				$frequency = 'monthly';
			}

			return [
				'enabled' => isset($source['cmx_abo_enabled']) ? '1' : '0',
				'frequency' => $frequency,
				'weekday' => self::normalize_weekday(isset($source['cmx_abo_weekday']) ? (int) \wp_unslash($source['cmx_abo_weekday']) : 1),
				'monthday' => self::normalize_monthday(isset($source['cmx_abo_monthday']) ? (int) \wp_unslash($source['cmx_abo_monthday']) : 1),
				'year_month' => self::normalize_year_month(isset($source['cmx_abo_year_month']) ? (int) \wp_unslash($source['cmx_abo_year_month']) : 1),
				'year_day' => self::normalize_year_day(isset($source['cmx_abo_year_day']) ? (int) \wp_unslash($source['cmx_abo_year_day']) : 1),
				'time' => self::normalize_time(isset($source['cmx_abo_time']) ? (string) \wp_unslash($source['cmx_abo_time']) : '08:00'),
				'immediate_once' => isset($source['cmx_abo_immediate_once']) ? '1' : '0',
			];
		}

		public static function calculate_next_run(array $settings = []): string {
			$defaults = [
				'frequency' => 'monthly',
				'weekday' => 1,
				'monthday' => 1,
				'year_month' => 1,
				'year_day' => 1,
				'time' => '08:00',
			];
			$settings = \wp_parse_args($settings, $defaults);

			$timezone = \wp_timezone();
			$now = new \DateTimeImmutable('now', $timezone);
			[$hour, $minute] = self::parse_time_parts((string) $settings['time']);

			switch ((string) $settings['frequency']) {
				case 'daily':
					$candidate = $now->setTime($hour, $minute, 0);
					if ($candidate <= $now) {
						$candidate = $candidate->modify('+1 day');
					}
					break;

				case 'weekly':
					$target_weekday = self::normalize_weekday((int) $settings['weekday']);
					$current_weekday = (int) $now->format('N');
					$days_ahead = $target_weekday - $current_weekday;
					$candidate = $now->setTime($hour, $minute, 0);
					if ($days_ahead < 0 || ($days_ahead === 0 && $candidate <= $now)) {
						$days_ahead += 7;
					}
					if ($days_ahead > 0) {
						$candidate = $candidate->modify('+' . $days_ahead . ' days');
					}
					break;

				case 'quarterly':
					$candidate = self::next_quarterly_datetime($now, self::normalize_monthday((int) $settings['monthday']), $hour, $minute);
					break;

				case 'yearly':
					$candidate = self::next_yearly_datetime(
						$now,
						self::normalize_year_month((int) $settings['year_month']),
						self::normalize_year_day((int) $settings['year_day']),
						$hour,
						$minute
					);
					break;

				case 'monthly':
				default:
					$candidate = self::next_monthly_datetime($now, self::normalize_monthday((int) $settings['monthday']), $hour, $minute);
					break;
			}

			return $candidate instanceof \DateTimeImmutable ? $candidate->format('Y-m-d H:i:s') : '';
		}

		private static function next_monthly_datetime(\DateTimeImmutable $now, int $day, int $hour, int $minute): \DateTimeImmutable {
			$year = (int) $now->format('Y');
			$month = (int) $now->format('n');

			for ($i = 0; $i < 24; $i++) {
				$target_year = $year + (int) \floor(($month - 1 + $i) / 12);
				$target_month = (($month - 1 + $i) % 12) + 1;
				$max_day = (int) \cal_days_in_month(\CAL_GREGORIAN, $target_month, $target_year);
				$target_day = \min($day, $max_day);
				$candidate = new \DateTimeImmutable(
					\sprintf('%04d-%02d-%02d %02d:%02d:00', $target_year, $target_month, $target_day, $hour, $minute),
					\wp_timezone()
				);
				if ($candidate > $now) {
					return $candidate;
				}
			}

			return $now->modify('+1 month')->setTime($hour, $minute, 0);
		}

		private static function next_quarterly_datetime(\DateTimeImmutable $now, int $day, int $hour, int $minute): \DateTimeImmutable {
			$quarter_months = [1, 4, 7, 10];
			$year = (int) $now->format('Y');

			for ($year_offset = 0; $year_offset < 3; $year_offset++) {
				$target_year = $year + $year_offset;
				foreach ($quarter_months as $target_month) {
					$max_day = (int) \cal_days_in_month(\CAL_GREGORIAN, $target_month, $target_year);
					$target_day = \min($day, $max_day);
					$candidate = new \DateTimeImmutable(
						\sprintf('%04d-%02d-%02d %02d:%02d:00', $target_year, $target_month, $target_day, $hour, $minute),
						\wp_timezone()
					);
					if ($candidate > $now) {
						return $candidate;
					}
				}
			}

			return $now->modify('+3 months')->setTime($hour, $minute, 0);
		}

		private static function next_yearly_datetime(\DateTimeImmutable $now, int $month, int $day, int $hour, int $minute): \DateTimeImmutable {
			$year = (int) $now->format('Y');

			for ($i = 0; $i < 3; $i++) {
				$target_year = $year + $i;
				$max_day = (int) \cal_days_in_month(\CAL_GREGORIAN, $month, $target_year);
				$target_day = \min($day, $max_day);
				$candidate = new \DateTimeImmutable(
					\sprintf('%04d-%02d-%02d %02d:%02d:00', $target_year, $month, $target_day, $hour, $minute),
					\wp_timezone()
				);
				if ($candidate > $now) {
					return $candidate;
				}
			}

			return $now->modify('+1 year')->setTime($hour, $minute, 0);
		}

		private static function allowed_frequencies(): array {
			return ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
		}

		private static function normalize_weekday(int $value): int {
			return \max(1, \min(7, $value > 0 ? $value : 1));
		}

		private static function normalize_monthday(int $value): int {
			return \max(1, \min(31, $value > 0 ? $value : 1));
		}

		private static function normalize_year_month(int $value): int {
			return \max(1, \min(12, $value > 0 ? $value : 1));
		}

		private static function normalize_year_day(int $value): int {
			return \max(1, \min(31, $value > 0 ? $value : 1));
		}

		private static function normalize_time(string $value): string {
			if (!\preg_match('/^\d{2}:\d{2}$/', $value)) {
				return '08:00';
			}

			[$hour, $minute] = self::parse_time_parts($value);
			return \sprintf('%02d:%02d', $hour, $minute);
		}

		private static function parse_time_parts(string $value): array {
			$parts = \explode(':', $value);
			$hour = isset($parts[0]) ? (int) $parts[0] : 8;
			$minute = isset($parts[1]) ? (int) $parts[1] : 0;

			$hour = \max(0, \min(23, $hour));
			$minute = \max(0, \min(59, $minute));

			return [$hour, $minute];
		}

		private static function format_local_datetime(string $mysql): string {
			if ($mysql === '') {
				return '';
			}

			$datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysql, \wp_timezone());
			if (!$datetime instanceof \DateTimeImmutable) {
				return $mysql;
			}

			return \wp_date('d.m.Y H:i', $datetime->getTimestamp(), \wp_timezone());
		}
	}

	CMX_Beleg_Abo::init();
}
