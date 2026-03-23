<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_edit_screen_active')) {
	function cmx_emails_edit_screen_active(): bool {
		if (!\is_admin()) {
			return false;
		}

		$current_script = \basename((string) ($_SERVER['PHP_SELF'] ?? ''));
		if (!\in_array($current_script, ['post.php', 'post-new.php'], true)) {
			return false;
		}

		$post_id = isset($_GET['post']) ? (int) \wp_unslash($_GET['post']) : 0;
		if ($post_id > 0) {
			return (string) \get_post_type($post_id) === CMX_EMAILS_CPT;
		}

		$post_type = isset($_GET['post_type']) ? \sanitize_key((string) \wp_unslash($_GET['post_type'])) : '';
		return $post_type === CMX_EMAILS_CPT;
	}
}

\add_action('add_meta_boxes', function (string $post_type, \WP_Post $post): void {
	if ($post_type !== CMX_EMAILS_CPT) {
		return;
	}

	\add_meta_box(
		'cmx_email_reader',
		'E-Mail-Inhalt',
		__NAMESPACE__ . '\\cmx_emails_render_reader_metabox',
		CMX_EMAILS_CPT,
		'normal',
		'high'
	);

	\add_meta_box(
		'cmx_email_details',
		'E-Mail-Details',
		__NAMESPACE__ . '\\cmx_emails_render_details_metabox',
		CMX_EMAILS_CPT,
		'side',
		'high'
	);

	\add_meta_box(
		'cmx_email_assignment',
		'Zuordnung',
		__NAMESPACE__ . '\\cmx_emails_render_assignment_metabox',
		CMX_EMAILS_CPT,
		'side',
		'default'
	);
}, 10, 2);

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_reader_metabox')) {
	function cmx_emails_render_reader_metabox(\WP_Post $post): void {
		$body_html = (string) \get_post_meta($post->ID, cmx_emails_meta_key('body_html'), true);
		$body_plain = (string) \get_post_meta($post->ID, cmx_emails_meta_key('body_plain'), true);

		echo '<div class="cmx-email-edit-reader">';
		if ($body_html !== '') {
			echo '<div class="cmx-email-edit-body is-html">' . \wp_kses_post($body_html) . '</div>';
		} else {
			$content = $body_plain !== '' ? $body_plain : (string) $post->post_content;
			if ($content === '') {
				echo '<p>Kein gespeicherter Inhalt vorhanden.</p>';
			} else {
				echo '<div class="cmx-email-edit-body is-plain">' . \wpautop(\esc_html($content)) . '</div>';
			}
		}
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_details_metabox')) {
	function cmx_emails_render_details_metabox(\WP_Post $post): void {
		$post_id = (int) $post->ID;
		$sender_label = (string) \get_post_meta($post_id, cmx_emails_meta_key('sender_label'), true);
		$sender_email = \sanitize_email((string) \get_post_meta($post_id, cmx_emails_meta_key('sender_email'), true));
		$subject = (string) \get_post_meta($post_id, cmx_emails_meta_key('subject'), true);
		$account_label = (string) \get_post_meta($post_id, cmx_emails_meta_key('account_label'), true);
		$folder = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('folder'), true));
		$status = \sanitize_key((string) \get_post_meta($post_id, cmx_emails_meta_key('status'), true));
		$ts = (int) \get_post_meta($post_id, cmx_emails_meta_key('received_ts'), true);
		$to = (array) \get_post_meta($post_id, cmx_emails_meta_key('to'), true);
		$cc = (array) \get_post_meta($post_id, cmx_emails_meta_key('cc'), true);
		$bcc = (array) \get_post_meta($post_id, cmx_emails_meta_key('bcc'), true);
		$attachments = cmx_emails_normalize_attachment_list(\get_post_meta($post_id, cmx_emails_meta_key('attachments'), true));
		$reply_url = cmx_emails_mailto_link($sender_email, 'Re:', $subject !== '' ? $subject : (string) $post->post_title);
		$forward_url = cmx_emails_mailto_link('', 'Fwd:', $subject !== '' ? $subject : (string) $post->post_title);
		$import_url = \wp_nonce_url(\add_query_arg([
			'action' => 'cmx_emails_import',
			'post_id' => $post_id,
			'email_id' => $post_id,
		], \admin_url('admin-post.php')), 'cmx_emails_import');

		echo '<div class="cmx-email-edit-meta">';
		echo '<dl class="cmx-email-edit-grid">';
		echo '<dt>Von</dt><dd>' . \esc_html($sender_label !== '' ? $sender_label : $sender_email) . '</dd>';
		if ($sender_email !== '') {
			echo '<dt>E-Mail</dt><dd><a href="mailto:' . \esc_attr($sender_email) . '">' . \esc_html($sender_email) . '</a></dd>';
		}
		echo '<dt>An</dt><dd>' . cmx_emails_render_address_html($to) . '</dd>';
		if ($cc !== []) {
			echo '<dt>CC</dt><dd>' . cmx_emails_render_address_html($cc) . '</dd>';
		}
		if ($bcc !== []) {
			echo '<dt>BCC</dt><dd>' . cmx_emails_render_address_html($bcc) . '</dd>';
		}
		echo '<dt>Datum</dt><dd>' . \esc_html(cmx_emails_date_label_long($ts)) . '</dd>';
		echo '<dt>Konto</dt><dd>' . \esc_html($account_label !== '' ? $account_label : '–') . '</dd>';
		echo '<dt>Ordner</dt><dd>' . \esc_html(cmx_emails_folder_label($folder)) . '</dd>';
		echo '<dt>Status</dt><dd><span class="cmx-email-badge ' . \esc_attr(cmx_emails_status_class($status)) . '">' . \esc_html(cmx_emails_status_label($status)) . '</span></dd>';
		echo '</dl>';

		echo '<div class="cmx-email-edit-actions">';
		if ($reply_url !== '') {
			echo '<a class="button button-primary" href="' . \esc_url($reply_url) . '">Antworten</a>';
		}
		if ($forward_url !== '') {
			echo '<a class="button" href="' . \esc_url($forward_url) . '">Weiterleiten</a>';
		}
		echo '<a class="button" href="' . \esc_url($import_url) . '">Als Beleg uebernehmen</a>';
		echo '</div>';

		echo '<div class="cmx-email-edit-attachments">';
		echo '<strong>Anhaenge</strong>';
		if ($attachments === []) {
			echo '<p>Keine Anhaenge vorhanden.</p>';
		} else {
			echo '<ul>';
			foreach ($attachments as $attachment) {
				$url = (string) ($attachment['url'] ?? '');
				$filename = (string) ($attachment['filename'] ?? 'Anhang');
				$size = (int) ($attachment['size'] ?? 0);
				echo '<li>';
				if ($url !== '') {
					echo '<a href="' . \esc_url($url) . '" download>' . \esc_html($filename) . '</a>';
				} else {
					echo \esc_html($filename);
				}
				if ($size > 0) {
					echo ' <span>(' . \esc_html(\size_format($size, 0)) . ')</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
		echo '</div>';
	}
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_emails_render_assignment_metabox')) {
	function cmx_emails_render_assignment_metabox(\WP_Post $post): void {
		$post_id = (int) $post->ID;
		$contact_id = (int) \get_post_meta($post_id, cmx_emails_meta_key('contact_id'), true);
		$project_id = (int) \get_post_meta($post_id, cmx_emails_meta_key('project_id'), true);
		$contact_options = cmx_emails_assignment_options('contact');
		$project_options = cmx_emails_assignment_options('project');

		\wp_nonce_field('cmx_emails_assignment_save', 'cmx_emails_assignment_nonce');

		echo '<p><label for="cmx-email-contact"><strong>Kunde</strong></label></p>';
		echo '<p><select id="cmx-email-contact" name="cmx_email_contact_id" style="width:100%;">' . cmx_emails_render_assignment_options($contact_options, $contact_id, 'Kunde zuordnen') . '</select></p>';

		echo '<p><label for="cmx-email-project"><strong>Projekt</strong></label></p>';
		echo '<p><select id="cmx-email-project" name="cmx_email_project_id" style="width:100%;">' . cmx_emails_render_assignment_options($project_options, $project_id, 'Projekt zuweisen') . '</select></p>';
		echo '<p>Speichern ueber "Aktualisieren".</p>';
	}
}

\add_action('save_post_' . CMX_EMAILS_CPT, function (int $post_id, \WP_Post $post): void {
	if ((\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || \wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
		return;
	}
	if ($post->post_type !== CMX_EMAILS_CPT) {
		return;
	}
	if (!isset($_POST['cmx_emails_assignment_nonce']) || !\wp_verify_nonce((string) \wp_unslash($_POST['cmx_emails_assignment_nonce']), 'cmx_emails_assignment_save')) {
		return;
	}
	if (!\current_user_can('edit_post', $post_id)) {
		return;
	}

	$contact_id = isset($_POST['cmx_email_contact_id']) ? (int) \wp_unslash($_POST['cmx_email_contact_id']) : 0;
	$project_id = isset($_POST['cmx_email_project_id']) ? (int) \wp_unslash($_POST['cmx_email_project_id']) : 0;

	\update_post_meta($post_id, cmx_emails_meta_key('contact_id'), (string) \max(0, $contact_id));
	\update_post_meta($post_id, cmx_emails_meta_key('project_id'), (string) \max(0, $project_id));
	\update_post_meta($post_id, cmx_emails_meta_key('assignment_manual'), '1');
	cmx_emails_update_assignment_cache($post_id);
}, 10, 2);

\add_action('admin_head', function (): void {
	if (!cmx_emails_edit_screen_active()) {
		return;
	}
	?>
	<style>
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-reader,
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-meta {
			font-size: 14px;
			line-height: 1.6;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-body {
			max-height: 70vh;
			overflow: auto;
			padding: 16px;
			border: 1px solid #d0d5dd;
			border-radius: 12px;
			background: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-grid {
			display: grid;
			grid-template-columns: 72px 1fr;
			gap: 8px 12px;
			margin: 0;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-grid dt {
			font-weight: 700;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-grid dd {
			margin: 0;
			word-break: break-word;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 16px;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 4px 10px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-new {
			background: #1d69d8;
			color: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-read {
			background: #0f766e;
			color: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-badge.is-processed {
			background: #6b7280;
			color: #fff;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-attachments {
			margin-top: 18px;
			padding-top: 14px;
			border-top: 1px solid #e4e7ec;
		}
		.post-type-<?php echo \esc_html(CMX_EMAILS_CPT); ?> .cmx-email-edit-attachments ul {
			margin: 10px 0 0 18px;
		}
	</style>
	<?php
});
