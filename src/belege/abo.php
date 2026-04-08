<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || die('Oxytocin!');

if (!\class_exists(__NAMESPACE__ . '\\CMX_Beleg_Abo')) {
	final class CMX_Beleg_Abo {
		const POST_TYPE = 'belege';

		const CRON_HOOK = 'cmx_beleg_abo_cron_process';
		const CRON_INTERVAL = 'cmx_beleg_abo_every_minute';

		const META_ENABLED = '_cmx_abo_enabled';
		const META_FREQUENCY = '_cmx_abo_frequency';
		const META_WEEKDAY = '_cmx_abo_weekday';
		const META_MONTHDAY = '_cmx_abo_monthday';
		const META_YEAR_MONTH = '_cmx_abo_year_month';
		const META_YEAR_DAY = '_cmx_abo_year_day';
		const META_TIME = '_cmx_abo_time';
		const META_NEXT_RUN = '_cmx_abo_next_run';
		const META_LAST_RUN = '_cmx_abo_last_run';
		const META_SOURCE_ID = '_cmx_abo_source_id';
		const META_RUN_KEY = '_cmx_abo_run_key';
		const META_SCHEDULED_FOR = '_cmx_abo_scheduled_for';
		const META_GENERATED_AT = '_cmx_abo_generated_at';
		const META_MAIL_STATUS = '_cmx_abo_mail_status';
		const META_MAIL_MESSAGE = '_cmx_abo_mail_message';
		const META_MAIL_TO = '_cmx_abo_mail_to';
		const META_MAIL_RESULT_AT = '_cmx_abo_mail_result_at';
		const META_LAST_RUN_KEY = '_cmx_abo_last_run_key';
		const META_LAST_GENERATED_POST_ID = '_cmx_abo_last_generated_post_id';
		const META_LAST_MAIL_STATUS = '_cmx_abo_last_mail_status';
		const META_LAST_MAIL_MESSAGE = '_cmx_abo_last_mail_message';
		const META_LAST_MAIL_TO = '_cmx_abo_last_mail_to';
		const META_LAST_RESULT_AT = '_cmx_abo_last_result_at';
		const META_PROCESSING_LOCK = '_cmx_abo_processing_lock';
		const STOP_ACTION = 'cmx_beleg_abo_stop';
		const NOTICE_QUERY_ARG = 'cmx_beleg_abo_notice';

			public static function init(): void {
				\add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
				\add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta_box'], 20, 3);
				\add_filter('cron_schedules', [__CLASS__, 'register_cron_schedule']);
				\add_filter('get_user_option_metaboxhidden_belege', [__CLASS__, 'filter_hidden_meta_boxes']);
				\add_filter('hidden_meta_boxes', [__CLASS__, 'filter_hidden_meta_boxes_for_screen'], 10, 2);
				\add_filter('cmx_duplicate_meta_blacklist', [__CLASS__, 'filter_duplicate_meta_blacklist']);
				\add_action(self::CRON_HOOK, [__CLASS__, 'process_due_belege']);
				\add_action('admin_post_' . self::STOP_ACTION, [__CLASS__, 'handle_stop_request']);
				\add_action('all_admin_notices', [__CLASS__, 'render_admin_notice']);
				if (\did_action('init')) {
					self::ensure_cron_event();
				} else {
					\add_action('init', [__CLASS__, 'ensure_cron_event']);
				}
			}

		public static function add_meta_box(): void {
			$title = '<a href="' . \esc_url(self::settings_url()) . '" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" style="color:inherit;text-decoration:none;font-size:14px;font-weight:600;line-height:1.4;display:inline-block;">' . \esc_html__('Wiederkehrender Versand', 'cmx-misbuero') . '</a>';
			\add_meta_box(
				'cmx-beleg-abo',
				$title,
				[__CLASS__, 'render_meta_box'],
				self::POST_TYPE,
				'side',
				'default'
			);
		}

		public static function filter_hidden_meta_boxes($hidden): array {
			$hidden = \is_array($hidden) ? $hidden : (array) $hidden;
			return \array_values(\array_diff($hidden, ['cmx-beleg-abo']));
		}

		public static function filter_hidden_meta_boxes_for_screen($hidden, $screen): array {
			if ($screen && (($screen->post_type ?? '') === self::POST_TYPE)) {
				return self::filter_hidden_meta_boxes($hidden);
			}

			return \is_array($hidden) ? $hidden : (array) $hidden;
		}

		public static function filter_duplicate_meta_blacklist(array $keys): array {
			return \array_values(\array_unique(\array_merge($keys, self::recurring_meta_keys())));
		}

		public static function render_meta_box(\WP_Post $post): void {
			\wp_nonce_field('cmx_beleg_abo_save', 'cmx_beleg_abo_nonce');

			$post_id = (int) $post->ID;
			$settings = self::get_settings($post_id);
			$source_post_id = self::get_source_post_id($post_id);
			$is_generated_copy = $source_post_id > 0;
			$last_generated_post_id = $is_generated_copy ? 0 : (int) \get_post_meta($post_id, self::META_LAST_GENERATED_POST_ID, true);
			$last_mail_status = $is_generated_copy
				? \sanitize_key((string) \get_post_meta($post_id, self::META_MAIL_STATUS, true))
				: \sanitize_key((string) \get_post_meta($post_id, self::META_LAST_MAIL_STATUS, true));
			$last_mail_message = $is_generated_copy
				? (string) \get_post_meta($post_id, self::META_MAIL_MESSAGE, true)
				: (string) \get_post_meta($post_id, self::META_LAST_MAIL_MESSAGE, true);
			$last_mail_to = $is_generated_copy
				? \sanitize_email((string) \get_post_meta($post_id, self::META_MAIL_TO, true))
				: \sanitize_email((string) \get_post_meta($post_id, self::META_LAST_MAIL_TO, true));
			$stop_url = (!$is_generated_copy && $settings['enabled'] === '1' && $settings['frequency'] !== 'never')
				? self::build_stop_url($post_id, (string) \get_edit_post_link($post_id, ''))
				: '';
			$js_settings = (string) \wp_json_encode([
				'frequencyId' => 'cmx_abo_frequency_' . $post_id,
				'weeklyId' => 'cmx-abo-row-weekly-' . $post_id,
				'monthlyId' => 'cmx-abo-row-monthly-' . $post_id,
				'quarterlyId' => 'cmx-abo-row-quarterly-' . $post_id,
				'yearlyId' => 'cmx-abo-row-yearly-' . $post_id,
				'timeId' => 'cmx-abo-row-time-' . $post_id,
			]);
			$visible_frequency_labels = self::visible_frequency_labels();
			$all_frequency_labels = self::all_frequency_labels();
			$source_link = $source_post_id > 0 ? self::post_edit_link_html($source_post_id) : '';
			$last_generated_link = $last_generated_post_id > 0 ? self::post_edit_link_html($last_generated_post_id) : '';
			$mail_status_text = self::format_mail_status_text($last_mail_status, $last_mail_message, $last_mail_to);
			?>
			<style>
				#cmx-beleg-abo .inside {
					margin: 0;
					padding: 12px 12px 8px;
				}
				#cmx-beleg-abo .cmx-abo-metabox p {
					margin: 0 0 12px;
				}
				#cmx-beleg-abo .cmx-abo-metabox .cmx-abo-row {
					margin-top: 0;
				}
				#cmx-beleg-abo .cmx-abo-metabox .cmx-abo-status {
					display: flex;
					align-items: baseline;
					justify-content: space-between;
					gap: 10px;
					margin-bottom: 6px;
				}
				#cmx-beleg-abo .cmx-abo-metabox .cmx-abo-status:last-of-type {
					margin-bottom: 0;
				}
				#cmx-beleg-abo .cmx-abo-metabox .cmx-abo-status-label {
					font-weight: 400;
				}
				#cmx-beleg-abo .cmx-abo-metabox .cmx-abo-status-value {
					font-weight: 600;
					text-align: right;
				}
				#cmx-beleg-abo .cmx-abo-metabox .cmx-abo-actions {
					margin-top: 12px;
					padding-top: 10px;
					border-top: 1px solid #dcdcde;
				}
			</style>
			<div class="cmx-abo-metabox">
				<p>
					<label for="<?php echo \esc_attr('cmx_abo_frequency_' . $post_id); ?>"><strong><?php echo \esc_html__('Rhythmus', 'cmx-misbuero'); ?></strong></label><br>
					<select name="cmx_abo_frequency" id="<?php echo \esc_attr('cmx_abo_frequency_' . $post_id); ?>" style="width:100%;">
						<?php if (!isset($visible_frequency_labels[$settings['frequency']]) && isset($all_frequency_labels[$settings['frequency']])) : ?>
							<option value="<?php echo \esc_attr($settings['frequency']); ?>" selected hidden></option>
						<?php endif; ?>
						<?php foreach ($visible_frequency_labels as $frequency_key => $frequency_label) : ?>
							<option value="<?php echo \esc_attr($frequency_key); ?>" <?php \selected($settings['frequency'], $frequency_key); ?>><?php echo \esc_html($frequency_label); ?></option>
						<?php endforeach; ?>
					</select>
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
					<select name="cmx_abo_monthday" id="<?php echo \esc_attr('cmx_abo_monthday_' . $post_id); ?>" style="width:100%;">
						<?php self::render_day_options($settings['monthday']); ?>
					</select>
				</p>

				<p id="<?php echo \esc_attr('cmx-abo-row-quarterly-' . $post_id); ?>" class="cmx-abo-row">
					<label for="<?php echo \esc_attr('cmx_abo_quarter_monthday_' . $post_id); ?>"><strong><?php echo \esc_html__('Tag im Quartal', 'cmx-misbuero'); ?></strong></label><br>
					<select name="cmx_abo_monthday" id="<?php echo \esc_attr('cmx_abo_quarter_monthday_' . $post_id); ?>" style="width:100%;">
						<?php self::render_day_options($settings['monthday']); ?>
					</select>
					<span class="description" style="display:block;margin-top:4px;"><?php echo \esc_html__('Ausführung jeweils in Jan / Apr / Jul / Okt.', 'cmx-misbuero'); ?></span>
				</p>

				<p id="<?php echo \esc_attr('cmx-abo-row-yearly-' . $post_id); ?>" class="cmx-abo-row">
					<label for="<?php echo \esc_attr('cmx_abo_year_month_' . $post_id); ?>"><strong><?php echo \esc_html__('Monat', 'cmx-misbuero'); ?></strong></label><br>
					<select name="cmx_abo_year_month" id="<?php echo \esc_attr('cmx_abo_year_month_' . $post_id); ?>" style="width:100%;margin-bottom:8px;">
						<?php foreach (self::month_labels() as $month_value => $month_label) : ?>
							<option value="<?php echo \esc_attr((string) $month_value); ?>" <?php \selected($settings['year_month'], $month_value); ?>><?php echo \esc_html($month_label); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="<?php echo \esc_attr('cmx_abo_year_day_' . $post_id); ?>"><strong><?php echo \esc_html__('Tag', 'cmx-misbuero'); ?></strong></label><br>
					<select name="cmx_abo_year_day" id="<?php echo \esc_attr('cmx_abo_year_day_' . $post_id); ?>" style="width:100%;">
						<?php self::render_day_options($settings['year_day']); ?>
					</select>
				</p>

				<p id="<?php echo \esc_attr('cmx-abo-row-time-' . $post_id); ?>" class="cmx-abo-row">
					<label for="<?php echo \esc_attr('cmx_abo_time_' . $post_id); ?>"><strong><?php echo \esc_html__('Uhrzeit', 'cmx-misbuero'); ?></strong></label><br>
					<input type="time" name="cmx_abo_time" id="<?php echo \esc_attr('cmx_abo_time_' . $post_id); ?>" value="<?php echo \esc_attr($settings['time']); ?>" style="width:100%;">
				</p>

				<?php if ($source_link !== '') : ?>
					<p class="cmx-abo-status">
						<span class="cmx-abo-status-label"><?php echo \esc_html__('Abo-Quelle', 'cmx-misbuero'); ?></span>
						<span class="cmx-abo-status-value"><?php echo $source_link; ?></span>
					</p>
				<?php endif; ?>

				<?php if ($settings['next_run'] !== '') : ?>
					<p class="cmx-abo-status">
						<span class="cmx-abo-status-label"><?php echo \esc_html__('Nächste Ausführung', 'cmx-misbuero'); ?></span>
						<span class="cmx-abo-status-value"><?php echo \esc_html(self::format_local_datetime($settings['next_run'])); ?></span>
					</p>
				<?php endif; ?>

				<?php if ($settings['last_run'] !== '') : ?>
					<p class="cmx-abo-status">
						<span class="cmx-abo-status-label"><?php echo \esc_html__('Letzte Ausführung', 'cmx-misbuero'); ?></span>
						<span class="cmx-abo-status-value"><?php echo \esc_html(self::format_local_datetime($settings['last_run'])); ?></span>
					</p>
				<?php endif; ?>

				<?php if ($last_generated_link !== '') : ?>
					<p class="cmx-abo-status">
						<span class="cmx-abo-status-label"><?php echo \esc_html__('Letzter Beleg', 'cmx-misbuero'); ?></span>
						<span class="cmx-abo-status-value"><?php echo $last_generated_link; ?></span>
					</p>
				<?php endif; ?>

				<?php if ($mail_status_text !== '') : ?>
					<p class="cmx-abo-status">
						<span class="cmx-abo-status-label"><?php echo \esc_html__('Mailstatus', 'cmx-misbuero'); ?></span>
						<span class="cmx-abo-status-value"><?php echo \esc_html($mail_status_text); ?></span>
					</p>
				<?php endif; ?>

				<?php if ($stop_url !== '') : ?>
					<p class="cmx-abo-actions">
						<a href="<?php echo \esc_url($stop_url); ?>" class="button button-secondary" onclick="return window.confirm('Wiederkehrenden Versand fuer diesen Beleg stoppen?');"><?php echo \esc_html__('Stoppen', 'cmx-misbuero'); ?></a>
					</p>
				<?php endif; ?>

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
						weekly: document.getElementById(config.weeklyId),
						monthly: document.getElementById(config.monthlyId),
						quarterly: document.getElementById(config.quarterlyId),
						yearly: document.getElementById(config.yearlyId),
						time: document.getElementById(config.timeId)
					};
					var sync = function() {
						var mode = frequency.value || 'never';
						['weekly', 'monthly', 'quarterly', 'yearly'].forEach(function(key) {
							if (!rows[key]) {
								return;
							}
							var active = key === mode;
							rows[key].style.display = active ? '' : 'none';
							Array.prototype.forEach.call(rows[key].querySelectorAll('input, select, textarea'), function(field) {
								field.disabled = !active;
							});
						});
						if (rows.time) {
							var showTime = mode !== 'minutely' && mode !== 'hourly' && mode !== 'never';
							rows.time.style.display = showTime ? '' : 'none';
							Array.prototype.forEach.call(rows.time.querySelectorAll('input, select, textarea'), function(field) {
								field.disabled = !showTime;
							});
						}
					};
					frequency.addEventListener('change', sync);
					sync();
				})(<?php echo $js_settings; ?>);
				</script>
			</div>
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
			if (!isset($GLOBALS['cmx_beleg_abo_save_guard']) || !\is_array($GLOBALS['cmx_beleg_abo_save_guard'])) {
				$GLOBALS['cmx_beleg_abo_save_guard'] = [];
			}
			if (!empty($GLOBALS['cmx_beleg_abo_save_guard'][$post_id])) {
				return;
			}
			$GLOBALS['cmx_beleg_abo_save_guard'][$post_id] = true;

			$settings = self::sanitize_settings_from_post($_POST, self::get_settings($post_id));

			\update_post_meta($post_id, self::META_ENABLED, $settings['enabled']);
			\update_post_meta($post_id, self::META_FREQUENCY, $settings['frequency']);
			\update_post_meta($post_id, self::META_WEEKDAY, (string) $settings['weekday']);
			\update_post_meta($post_id, self::META_MONTHDAY, (string) $settings['monthday']);
			\update_post_meta($post_id, self::META_YEAR_MONTH, (string) $settings['year_month']);
			\update_post_meta($post_id, self::META_YEAR_DAY, (string) $settings['year_day']);
			\update_post_meta($post_id, self::META_TIME, $settings['time']);
			\delete_post_meta($post_id, '_cmx_abo_immediate_once');

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
		}

		public static function register_cron_schedule(array $schedules): array {
			if (!isset($schedules[self::CRON_INTERVAL])) {
				$schedules[self::CRON_INTERVAL] = [
					'interval' => 60,
					'display' => \__('CMX Beleg-Abo (1 Minute)', 'cmx-misbuero'),
				];
			}
			return $schedules;
		}

		public static function ensure_cron_event(): void {
			\wp_clear_scheduled_hook('cmx_beleg_abo_single_execute');
			$event = \function_exists('wp_get_scheduled_event') ? \wp_get_scheduled_event(self::CRON_HOOK) : null;
			if ($event && (string) ($event->schedule ?? '') !== self::CRON_INTERVAL) {
				\wp_clear_scheduled_hook(self::CRON_HOOK);
				$event = null;
			}

			if (!$event && !\wp_next_scheduled(self::CRON_HOOK)) {
				\wp_schedule_event(\time() + 60, self::CRON_INTERVAL, self::CRON_HOOK);
			}
		}

		public static function process_due_belege(): void {
			$meta_query = [
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
			];

			$blocked_frequencies = self::blocked_runtime_frequencies();
			if ($blocked_frequencies !== []) {
				$meta_query[] = [
					'key' => self::META_FREQUENCY,
					'value' => $blocked_frequencies,
					'compare' => 'NOT IN',
				];
			}

			$post_ids = \get_posts([
				'post_type' => self::POST_TYPE,
				'post_status' => ['publish', 'private'],
				'posts_per_page' => 50,
				'fields' => 'ids',
				'meta_query' => $meta_query,
			]);

			if ($post_ids === []) {
				return;
			}

			foreach ($post_ids as $post_id) {
				self::execute_beleg((int) $post_id);
			}
		}

		public static function execute_beleg(int $post_id): bool {
			$post_id = \absint($post_id);
			if ($post_id <= 0 || \get_post_type($post_id) !== self::POST_TYPE) {
				return false;
			}

			$settings = self::get_settings($post_id);
			if ($settings['enabled'] !== '1' || $settings['frequency'] === 'never') {
				\delete_post_meta($post_id, self::META_NEXT_RUN);
				return false;
			}
			if (!self::is_runtime_frequency_allowed($settings['frequency'])) {
				self::stop_recurring($post_id);
				return false;
			}
			$scheduled_for = (string) ($settings['next_run'] ?? '');
			$run_key = self::build_run_key($post_id, $scheduled_for);
			if ($run_key === '') {
				return false;
			}
			if (!self::acquire_processing_lock($post_id)) {
				return false;
			}

			$handled = false;
			$generated_post_id = 0;
			$mail_status = '';
			$mail_message = '';
			$mail_to = '';
			$had_internal_write = !empty($GLOBALS['cmx_woocommerce_internal_import']);
			$GLOBALS['cmx_woocommerce_internal_import'] = true;

			try {
				$generated_post_id = self::find_generated_post_by_run_key($run_key);
				if ($generated_post_id > 0) {
					$handled = true;
					[$mail_status, $mail_message, $mail_to] = self::read_generated_mail_snapshot($generated_post_id);
					if ($mail_status === '') {
						$mail_status = 'existing';
						$mail_message = (string) \__('Für diesen Termin existiert bereits ein erzeugter Beleg.', 'cmx-misbuero');
					}
				} else {
					$duplicate_result = self::duplicate_beleg_for_recurring_send($post_id, $run_key, $scheduled_for);
					if (\is_wp_error($duplicate_result)) {
						$mail_status = 'failed';
						$mail_message = \trim((string) $duplicate_result->get_error_message());
					} else {
						$generated_post_id = (int) $duplicate_result;
						$handled = $generated_post_id > 0;
					}
				}

				if ($handled && $generated_post_id > 0 && $mail_status !== 'existing') {
					$mail_result = self::send_generated_beleg_mail($generated_post_id);
					if (\is_wp_error($mail_result)) {
						$mail_status = 'failed';
						$mail_message = \trim((string) $mail_result->get_error_message());
						$mail_to = self::extract_mail_to_from_result($mail_result);
					} else {
						$mail_status = 'sent';
						$mail_message = '';
						$mail_to = \sanitize_email((string) ($mail_result['to'] ?? ''));
					}
					self::store_generated_mail_snapshot($generated_post_id, $mail_status, $mail_message, $mail_to);
				}
			} finally {
				if ($had_internal_write) {
					$GLOBALS['cmx_woocommerce_internal_import'] = true;
				} else {
					unset($GLOBALS['cmx_woocommerce_internal_import']);
				}
				self::release_processing_lock($post_id);
			}

			if ($handled) {
				$last_run = \current_time('mysql');
				\update_post_meta($post_id, self::META_LAST_RUN, $last_run);
				self::store_source_run_snapshot($post_id, $run_key, $generated_post_id, $mail_status, $mail_message, $mail_to);
				$next_run = self::calculate_next_run($settings, \wp_timezone());
				if ($next_run !== '') {
					\update_post_meta($post_id, self::META_NEXT_RUN, $next_run);
				} else {
					\delete_post_meta($post_id, self::META_NEXT_RUN);
				}
			} elseif ($mail_status !== '' || $mail_message !== '') {
				self::store_source_run_snapshot($post_id, $run_key, 0, $mail_status, $mail_message, $mail_to);
			} elseif ($settings['enabled'] !== '1') {
				\delete_post_meta($post_id, self::META_NEXT_RUN);
			}

			return $handled;
		}

		public static function stop_recurring(int $post_id): bool {
			$post_id = \absint($post_id);
			if ($post_id <= 0 || \get_post_type($post_id) !== self::POST_TYPE) {
				return false;
			}

			\update_post_meta($post_id, self::META_ENABLED, '0');
			\update_post_meta($post_id, self::META_FREQUENCY, 'never');
			\delete_post_meta($post_id, self::META_NEXT_RUN);
			\delete_post_meta($post_id, '_cmx_abo_immediate_once');
			\delete_post_meta($post_id, self::META_PROCESSING_LOCK);

			return true;
		}

		public static function handle_stop_request(): void {
			$post_id = isset($_REQUEST['post_id']) ? \absint(\wp_unslash($_REQUEST['post_id'])) : 0;
			$redirect_to = isset($_REQUEST['redirect_to'])
				? \wp_validate_redirect((string) \wp_unslash($_REQUEST['redirect_to']), \admin_url('edit.php?post_type=' . self::POST_TYPE))
				: \admin_url('edit.php?post_type=' . self::POST_TYPE);

			if ($post_id <= 0) {
				\wp_safe_redirect(\add_query_arg(self::NOTICE_QUERY_ARG, 'error', $redirect_to));
				exit;
			}

			\check_admin_referer('cmx_beleg_abo_stop_' . $post_id);

			if (!\current_user_can('edit_post', $post_id)) {
				\wp_die(\__('Keine Berechtigung.', 'cmx-misbuero'));
			}

			$notice = self::stop_recurring($post_id) ? 'stopped' : 'error';
			$args = [self::NOTICE_QUERY_ARG => $notice];
			if ($notice === 'stopped') {
				$args['cmx_beleg_abo_post'] = $post_id;
			}

			\wp_safe_redirect(\add_query_arg($args, $redirect_to));
			exit;
		}

		public static function render_admin_notice(): void {
			$screen = \function_exists('get_current_screen') ? \get_current_screen() : null;
			if (!$screen || $screen->post_type !== self::POST_TYPE || !\in_array((string) $screen->base, ['edit', 'post'], true)) {
				return;
			}

			$notice = isset($_GET[self::NOTICE_QUERY_ARG])
				? \sanitize_key((string) \wp_unslash($_GET[self::NOTICE_QUERY_ARG]))
				: '';
			if ($notice === '') {
				return;
			}

			if ($notice === 'stopped') {
				$post_id = isset($_GET['cmx_beleg_abo_post']) ? \absint(\wp_unslash($_GET['cmx_beleg_abo_post'])) : 0;
				$title = $post_id > 0 ? \get_the_title($post_id) : '';
				$message = $title !== ''
					? \sprintf(\__('Wiederkehrender Lauf wurde für "%s" gestoppt.', 'cmx-misbuero'), $title)
					: \__('Wiederkehrender Lauf wurde gestoppt.', 'cmx-misbuero');
				echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html($message) . '</p></div>';
				return;
			}

			echo '<div class="notice notice-error is-dismissible"><p>' . \esc_html__('Wiederkehrender Lauf konnte nicht gestoppt werden.', 'cmx-misbuero') . '</p></div>';
		}

		private static function get_settings(int $post_id): array {
			$defaults = self::get_default_settings();

			$frequency = \metadata_exists('post', $post_id, self::META_FREQUENCY)
				? (string) \get_post_meta($post_id, self::META_FREQUENCY, true)
				: (string) $defaults['frequency'];
			if (!\in_array($frequency, self::allowed_frequencies(), true)) {
				$frequency = (string) $defaults['frequency'];
			}

			$enabled = \metadata_exists('post', $post_id, self::META_ENABLED)
				? (\get_post_meta($post_id, self::META_ENABLED, true) === '1' ? '1' : '0')
				: (string) $defaults['enabled'];
			if ($enabled !== '1') {
				$frequency = 'never';
			}

			$time = \metadata_exists('post', $post_id, self::META_TIME)
				? self::normalize_time((string) \get_post_meta($post_id, self::META_TIME, true))
				: (string) $defaults['time'];

			$settings = [
				'enabled' => $frequency === 'never' ? '0' : '1',
				'frequency' => $frequency,
				'weekday' => \metadata_exists('post', $post_id, self::META_WEEKDAY)
					? self::normalize_weekday((int) \get_post_meta($post_id, self::META_WEEKDAY, true))
					: (int) $defaults['weekday'],
				'monthday' => \metadata_exists('post', $post_id, self::META_MONTHDAY)
					? self::normalize_monthday((int) \get_post_meta($post_id, self::META_MONTHDAY, true))
					: (int) $defaults['monthday'],
				'year_month' => \metadata_exists('post', $post_id, self::META_YEAR_MONTH)
					? self::normalize_year_month((int) \get_post_meta($post_id, self::META_YEAR_MONTH, true))
					: (int) $defaults['year_month'],
				'year_day' => \metadata_exists('post', $post_id, self::META_YEAR_DAY)
					? self::normalize_year_day((int) \get_post_meta($post_id, self::META_YEAR_DAY, true))
					: (int) $defaults['year_day'],
				'time' => $time,
				'next_run' => (string) \get_post_meta($post_id, self::META_NEXT_RUN, true),
				'last_run' => (string) \get_post_meta($post_id, self::META_LAST_RUN, true),
			];

			$timezone = \wp_timezone();
			$next_dt = self::create_mysql_datetime((string) $settings['next_run'], $timezone);
			$last_dt = self::create_mysql_datetime((string) $settings['last_run'], $timezone);
			if ($next_dt instanceof \DateTimeImmutable && $last_dt instanceof \DateTimeImmutable && $next_dt <= $last_dt) {
				$settings['last_run'] = '';
				\delete_post_meta($post_id, self::META_LAST_RUN);
				$last_dt = null;
			}

			if ($settings['enabled'] === '1' && $settings['frequency'] !== 'never' && !($next_dt instanceof \DateTimeImmutable)) {
				$recalculated_next_run = self::calculate_next_run($settings, $timezone);
				$settings['next_run'] = $recalculated_next_run;
				if ($recalculated_next_run !== '') {
					\update_post_meta($post_id, self::META_NEXT_RUN, $recalculated_next_run);
				} else {
					\delete_post_meta($post_id, self::META_NEXT_RUN);
				}
			}

			return $settings;
		}

		private static function sanitize_settings_from_post(array $source, array $fallback = []): array {
			$fallback = \wp_parse_args($fallback, self::get_default_settings());
			$frequency = isset($source['cmx_abo_frequency']) ? \sanitize_key((string) \wp_unslash($source['cmx_abo_frequency'])) : 'never';
			if (!\in_array($frequency, self::allowed_frequencies(), true)) {
				$frequency = (string) ($fallback['frequency'] ?? 'never');
			}

			return [
				'enabled' => $frequency === 'never' ? '0' : '1',
				'frequency' => $frequency,
				'weekday' => self::normalize_weekday(isset($source['cmx_abo_weekday']) ? (int) \wp_unslash($source['cmx_abo_weekday']) : (int) ($fallback['weekday'] ?? 1)),
				'monthday' => self::normalize_monthday(isset($source['cmx_abo_monthday']) ? (int) \wp_unslash($source['cmx_abo_monthday']) : (int) ($fallback['monthday'] ?? 1)),
				'year_month' => self::normalize_year_month(isset($source['cmx_abo_year_month']) ? (int) \wp_unslash($source['cmx_abo_year_month']) : (int) ($fallback['year_month'] ?? 1)),
				'year_day' => self::normalize_year_day(isset($source['cmx_abo_year_day']) ? (int) \wp_unslash($source['cmx_abo_year_day']) : (int) ($fallback['year_day'] ?? 1)),
				'time' => self::normalize_time(isset($source['cmx_abo_time']) ? (string) \wp_unslash($source['cmx_abo_time']) : (string) ($fallback['time'] ?? '08:00')),
			];
		}

		public static function calculate_next_run(array $settings = [], ?\DateTimeZone $timezone = null): string {
			$defaults = [
				'frequency' => 'monthly',
				'weekday' => 1,
				'monthday' => 1,
				'year_month' => 1,
				'year_day' => 1,
				'time' => '08:00',
			];
			$settings = \wp_parse_args($settings, $defaults);

			$timezone = $timezone instanceof \DateTimeZone ? $timezone : \wp_timezone();
			$now = new \DateTimeImmutable('now', $timezone);
			[$hour, $minute] = self::parse_time_parts((string) $settings['time']);

			switch ((string) $settings['frequency']) {
				case 'never':
					return '';

				case 'minutely':
					$current_minute = (int) $now->format('i');
					$next_minute = ((int) \floor($current_minute / 15) * 15) + 15;
					$candidate = $now->setTime((int) $now->format('G'), 0, 0);
					if ($next_minute >= 60) {
						$candidate = $candidate->modify('+1 hour');
						$next_minute = 0;
					}
					$candidate = $candidate->setTime((int) $candidate->format('G'), $next_minute, 0);
					break;

				case 'hourly':
					$candidate = $now->setTime((int) $now->format('G'), 0, 0);
					if ($candidate <= $now) {
						$candidate = $candidate->modify('+1 hour')->setTime((int) $candidate->format('G'), 0, 0);
					}
					break;

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
						$candidate = self::next_quarterly_datetime($now, self::calendar_day_from_setting((int) $settings['monthday']), $hour, $minute);
						break;

				case 'yearly':
					$candidate = self::next_yearly_datetime(
							$now,
							self::normalize_year_month((int) $settings['year_month']),
							self::calendar_day_from_setting((int) $settings['year_day']),
							$hour,
							$minute
						);
					break;

					case 'monthly':
					default:
						$candidate = self::next_monthly_datetime($now, self::calendar_day_from_setting((int) $settings['monthday']), $hour, $minute);
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
				$target_day = self::resolve_calendar_day($day, $target_month, $target_year);
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
					$target_day = self::resolve_calendar_day($day, $target_month, $target_year);
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
				$target_day = self::resolve_calendar_day($day, $month, $target_year);
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
			return ['minutely', 'hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'never'];
		}

		private static function blocked_runtime_frequencies(): array {
			return self::is_debug_mode_enabled() ? [] : ['minutely', 'hourly'];
		}

		private static function is_runtime_frequency_allowed(string $frequency): bool {
			return !\in_array($frequency, self::blocked_runtime_frequencies(), true);
		}

		public static function visible_frequency_labels(): array {
			$labels = self::all_frequency_labels();
			if (!self::is_debug_mode_enabled()) {
				unset($labels['minutely'], $labels['hourly']);
			}

			return $labels;
		}

		public static function all_frequency_labels(): array {
			return [
				'minutely' => \__('15Minütlich', 'cmx-misbuero'),
				'hourly' => \__('Stündlich', 'cmx-misbuero'),
				'daily' => \__('Täglich', 'cmx-misbuero'),
				'weekly' => \__('Wöchentlich', 'cmx-misbuero'),
				'monthly' => \__('Monatlich', 'cmx-misbuero'),
				'quarterly' => \__('Quartal', 'cmx-misbuero'),
				'yearly' => \__('Jährlich', 'cmx-misbuero'),
				'never' => \__('Nie', 'cmx-misbuero'),
			];
		}

		private static function normalize_weekday(int $value): int {
			return \max(1, \min(7, $value > 0 ? $value : 1));
		}

		private static function normalize_monthday(int $value): int {
			return \max(0, \min(28, $value));
		}

		private static function normalize_year_month(int $value): int {
			return \max(1, \min(12, $value > 0 ? $value : 1));
		}

		private static function normalize_year_day(int $value): int {
			return \max(0, \min(28, $value));
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

		private static function create_mysql_datetime(string $mysql, \DateTimeZone $timezone): ?\DateTimeImmutable {
			if ($mysql === '') {
				return null;
			}

			$datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysql, $timezone);
			return $datetime instanceof \DateTimeImmutable ? $datetime : null;
		}

		private static function build_stop_url(int $post_id, string $redirect_to = ''): string {
			$post_id = \absint($post_id);
			if ($post_id <= 0) {
				return '';
			}

			if ($redirect_to === '') {
				$redirect_to = \admin_url('edit.php?post_type=' . self::POST_TYPE);
			}

			$url = (string) \add_query_arg(
				[
					'action' => self::STOP_ACTION,
					'post_id' => $post_id,
					'redirect_to' => $redirect_to,
				],
				\admin_url('admin-post.php')
			);

			return (string) \wp_nonce_url($url, 'cmx_beleg_abo_stop_' . $post_id);
		}

		private static function get_source_post_id(int $post_id): int {
			$post_id = \absint($post_id);
			if ($post_id <= 0) {
				return 0;
			}

			$source_post_id = (int) \get_post_meta($post_id, self::META_SOURCE_ID, true);
			if ($source_post_id <= 0 || \get_post_type($source_post_id) !== self::POST_TYPE) {
				return 0;
			}

			return $source_post_id;
		}

		private static function post_edit_link_html(int $post_id): string {
			$post = \get_post($post_id);
			if (!$post instanceof \WP_Post || $post->post_type !== self::POST_TYPE || $post->post_status === 'trash') {
				return '';
			}

			$label = \trim((string) $post->post_title);
			if ($label === '') {
				$label = '#' . (int) $post_id;
			}

			$url = \get_edit_post_link($post_id, '');
			if (!\is_string($url) || $url === '') {
				return \esc_html($label);
			}

			return '<a href="' . \esc_url($url) . '">' . \esc_html($label) . '</a>';
		}

		private static function format_mail_status_text(string $status, string $message = '', string $mail_to = ''): string {
			$status = \sanitize_key($status);
			$message = \trim($message);
			$mail_to = \sanitize_email($mail_to);

			switch ($status) {
				case 'sent':
					return $mail_to !== ''
						? \sprintf(\__('Gesendet an %s', 'cmx-misbuero'), $mail_to)
						: (string) \__('Gesendet', 'cmx-misbuero');

				case 'failed':
					return $message !== ''
						? \sprintf(\__('Fehlgeschlagen: %s', 'cmx-misbuero'), $message)
						: (string) \__('Fehlgeschlagen', 'cmx-misbuero');

				case 'existing':
					return (string) \__('Bereits erzeugt, kein zweites Mal versendet.', 'cmx-misbuero');
			}

			return $message;
		}

		private static function build_run_key(int $source_post_id, string $scheduled_for): string {
			$source_post_id = \absint($source_post_id);
			$scheduled_for = \preg_replace('/[^0-9]/', '', \trim($scheduled_for));
			if ($source_post_id <= 0 || !\is_string($scheduled_for) || $scheduled_for === '') {
				return '';
			}

			return 'abo-' . $source_post_id . '-' . $scheduled_for;
		}

		private static function find_generated_post_by_run_key(string $run_key): int {
			$run_key = \sanitize_key($run_key);
			if ($run_key === '') {
				return 0;
			}

			$post_ids = \get_posts([
				'post_type' => self::POST_TYPE,
				'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
				'posts_per_page' => 1,
				'fields' => 'ids',
				'orderby' => 'ID',
				'order' => 'DESC',
				'meta_query' => [
					[
						'key' => self::META_RUN_KEY,
						'value' => $run_key,
					],
				],
			]);

			return isset($post_ids[0]) ? (int) $post_ids[0] : 0;
		}

		private static function acquire_processing_lock(int $post_id): bool {
			$post_id = \absint($post_id);
			if ($post_id <= 0) {
				return false;
			}

			$existing = (string) \get_post_meta($post_id, self::META_PROCESSING_LOCK, true);
			if ($existing !== '') {
				$existing_dt = self::create_mysql_datetime($existing, \wp_timezone());
				$stale_before = new \DateTimeImmutable('-15 minutes', \wp_timezone());
				if ($existing_dt instanceof \DateTimeImmutable && $existing_dt > $stale_before) {
					return false;
				}
				\delete_post_meta($post_id, self::META_PROCESSING_LOCK);
			}

			return (bool) \add_post_meta($post_id, self::META_PROCESSING_LOCK, \current_time('mysql'), true);
		}

		private static function release_processing_lock(int $post_id): void {
			if ($post_id > 0) {
				\delete_post_meta($post_id, self::META_PROCESSING_LOCK);
			}
		}

		private static function store_source_run_snapshot(int $source_post_id, string $run_key, int $generated_post_id, string $mail_status, string $mail_message, string $mail_to): void {
			$source_post_id = \absint($source_post_id);
			if ($source_post_id <= 0) {
				return;
			}

			\update_post_meta($source_post_id, self::META_LAST_RUN_KEY, \sanitize_key($run_key));
			if ($generated_post_id > 0) {
				\update_post_meta($source_post_id, self::META_LAST_GENERATED_POST_ID, $generated_post_id);
			}
			if ($mail_status !== '') {
				\update_post_meta($source_post_id, self::META_LAST_MAIL_STATUS, \sanitize_key($mail_status));
			} else {
				\delete_post_meta($source_post_id, self::META_LAST_MAIL_STATUS);
			}
			if ($mail_message !== '') {
				\update_post_meta($source_post_id, self::META_LAST_MAIL_MESSAGE, $mail_message);
			} else {
				\delete_post_meta($source_post_id, self::META_LAST_MAIL_MESSAGE);
			}
			if ($mail_to !== '') {
				\update_post_meta($source_post_id, self::META_LAST_MAIL_TO, \sanitize_email($mail_to));
			} else {
				\delete_post_meta($source_post_id, self::META_LAST_MAIL_TO);
			}
			\update_post_meta($source_post_id, self::META_LAST_RESULT_AT, \current_time('mysql'));
		}

		private static function read_generated_mail_snapshot(int $post_id): array {
			$post_id = \absint($post_id);
			if ($post_id <= 0) {
				return ['', '', ''];
			}

			return [
				\sanitize_key((string) \get_post_meta($post_id, self::META_MAIL_STATUS, true)),
				(string) \get_post_meta($post_id, self::META_MAIL_MESSAGE, true),
				\sanitize_email((string) \get_post_meta($post_id, self::META_MAIL_TO, true)),
			];
		}

		private static function store_generated_mail_snapshot(int $post_id, string $mail_status, string $mail_message, string $mail_to): void {
			$post_id = \absint($post_id);
			if ($post_id <= 0) {
				return;
			}

			if ($mail_status !== '') {
				\update_post_meta($post_id, self::META_MAIL_STATUS, \sanitize_key($mail_status));
			} else {
				\delete_post_meta($post_id, self::META_MAIL_STATUS);
			}
			if ($mail_message !== '') {
				\update_post_meta($post_id, self::META_MAIL_MESSAGE, $mail_message);
			} else {
				\delete_post_meta($post_id, self::META_MAIL_MESSAGE);
			}
			if ($mail_to !== '') {
				\update_post_meta($post_id, self::META_MAIL_TO, \sanitize_email($mail_to));
			} else {
				\delete_post_meta($post_id, self::META_MAIL_TO);
			}
			\update_post_meta($post_id, self::META_MAIL_RESULT_AT, \current_time('mysql'));
		}

		private static function send_generated_beleg_mail(int $post_id) {
			if (!\function_exists(__NAMESPACE__ . '\\cmxbu_send_beleg_mail')) {
				$send_file = __DIR__ . '/meta_action_send.php';
				if (\is_file($send_file)) {
					require_once $send_file;
				}
			}

			if (!\function_exists(__NAMESPACE__ . '\\cmxbu_send_beleg_mail')) {
				return new \WP_Error('mail_unavailable', \__('E-Mail-Versand ist aktuell nicht verfügbar.', 'cmx-misbuero'));
			}

			return cmxbu_send_beleg_mail($post_id, ['regenerate_pdf' => true]);
		}

		private static function extract_mail_to_from_result($result): string {
			if ($result instanceof \WP_Error) {
				$data = $result->get_error_data();
				if (\is_array($data) && !empty($data['to'])) {
					return \sanitize_email((string) $data['to']);
				}
			}

			if (\is_array($result) && !empty($result['to'])) {
				return \sanitize_email((string) $result['to']);
			}

			return '';
		}

		public static function settings_url(): string {
			$slug = \defined(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
				? (string) \constant(__NAMESPACE__ . '\\CMX_SETTINGS_SLUG')
				: 'cmx-einstellungen';

			return \admin_url('admin.php?page=' . $slug . '&tab=vorgaben&sub=belege');
		}

		private static function get_default_settings(): array {
			return [
				'enabled' => '0',
				'frequency' => 'never',
				'weekday' => 1,
				'monthday' => 1,
				'year_month' => 1,
				'year_day' => 1,
				'time' => '08:00',
				'next_run' => '',
				'last_run' => '',
			];
		}

		private static function is_debug_mode_enabled(): bool {
			return \function_exists(__NAMESPACE__ . '\\cmx_system_is_debug_mode_enabled')
				&& cmx_system_is_debug_mode_enabled();
		}

		private static function calendar_day_from_setting(int $value): int {
			return self::normalize_monthday($value);
		}

		private static function resolve_calendar_day(int $configured_day, int $month, int $year): int {
			$configured_day = self::normalize_monthday($configured_day);
			$max_day = (int) \cal_days_in_month(\CAL_GREGORIAN, $month, $year);
			if ($configured_day === 0) {
				return $max_day;
			}

			return \min($configured_day, $max_day);
		}

		private static function duplicate_beleg_for_recurring_send(int $post_id, string $run_key, string $scheduled_for) {
			if (!\function_exists(__NAMESPACE__ . '\\cmx_duplicate_do')) {
				$duplicate_file = \dirname(__DIR__, 2) . '/includes/dublicate.php';
				if (\is_file($duplicate_file)) {
					require_once $duplicate_file;
				}
			}

			if (!\function_exists(__NAMESPACE__ . '\\cmx_duplicate_do')) {
				return new \WP_Error('duplicate_unavailable', \__('Rechnungskopie ist aktuell nicht verfügbar.', 'cmx-misbuero'));
			}

			$new_post_id = cmx_duplicate_do($post_id);
			if (\is_wp_error($new_post_id)) {
				return $new_post_id;
			}

			$new_post_id = \absint($new_post_id);
			if ($new_post_id <= 0) {
				return new \WP_Error('duplicate_failed', \__('Rechnungskopie konnte nicht erstellt werden.', 'cmx-misbuero'));
			}

			foreach (self::recurring_meta_keys() as $meta_key) {
				\delete_post_meta($new_post_id, $meta_key);
			}

			\update_post_meta($new_post_id, self::META_ENABLED, '0');
			\update_post_meta($new_post_id, self::META_FREQUENCY, 'never');
			\update_post_meta($new_post_id, self::META_SOURCE_ID, $post_id);
			\update_post_meta($new_post_id, self::META_RUN_KEY, \sanitize_key($run_key));
			\update_post_meta($new_post_id, self::META_SCHEDULED_FOR, $scheduled_for);
			\update_post_meta($new_post_id, self::META_GENERATED_AT, \current_time('mysql'));
			\update_post_meta($new_post_id, '_cmx_beleg_copied_from', $post_id);

			return $new_post_id;
		}

		private static function recurring_meta_keys(): array {
			return [
				self::META_ENABLED,
				self::META_FREQUENCY,
				self::META_WEEKDAY,
				self::META_MONTHDAY,
				self::META_YEAR_MONTH,
				self::META_YEAR_DAY,
				self::META_TIME,
				self::META_NEXT_RUN,
				self::META_LAST_RUN,
				self::META_SOURCE_ID,
				self::META_RUN_KEY,
				self::META_SCHEDULED_FOR,
				self::META_GENERATED_AT,
				self::META_MAIL_STATUS,
				self::META_MAIL_MESSAGE,
				self::META_MAIL_TO,
				self::META_MAIL_RESULT_AT,
				self::META_LAST_RUN_KEY,
				self::META_LAST_GENERATED_POST_ID,
				self::META_LAST_MAIL_STATUS,
				self::META_LAST_MAIL_MESSAGE,
				self::META_LAST_MAIL_TO,
				self::META_LAST_RESULT_AT,
				self::META_PROCESSING_LOCK,
				'_cmx_abo_immediate_once',
			];
		}

		private static function render_day_options(int $selected): void {
			$selected = self::normalize_monthday($selected);
			for ($day = 0; $day <= 28; $day++) {
				echo '<option value="' . \esc_attr((string) $day) . '"' . \selected($selected, $day, false) . '>' . \esc_html((string) $day) . '</option>';
			}
		}

		private static function month_labels(): array {
			return [
				1 => \__('Januar', 'cmx-misbuero'),
				2 => \__('Februar', 'cmx-misbuero'),
				3 => \__('März', 'cmx-misbuero'),
				4 => \__('April', 'cmx-misbuero'),
				5 => \__('Mai', 'cmx-misbuero'),
				6 => \__('Juni', 'cmx-misbuero'),
				7 => \__('Juli', 'cmx-misbuero'),
				8 => \__('August', 'cmx-misbuero'),
				9 => \__('September', 'cmx-misbuero'),
				10 => \__('Oktober', 'cmx-misbuero'),
				11 => \__('November', 'cmx-misbuero'),
				12 => \__('Dezember', 'cmx-misbuero'),
			];
		}
	}

	CMX_Beleg_Abo::init();
}
