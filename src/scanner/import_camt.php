<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (!\defined(__NAMESPACE__ . '\\CMX_BANK_IMPORT_LOG_FILENAME')) {
	\define(__NAMESPACE__ . '\\CMX_BANK_IMPORT_LOG_FILENAME', 'cmx-bank-import.log');
}

function cmx_bank_import_log_file_path(): string {
	$upload_data = \wp_get_upload_dir();
	$base_dir = \wp_normalize_path((string) ($upload_data['basedir'] ?? ''));
	if ($base_dir === '') {
		return '';
	}

	$dir = \trailingslashit($base_dir) . 'misbuero/scanner';
	if (!\is_dir($dir)) {
		@\wp_mkdir_p($dir);
	}
	if (!\is_dir($dir) || !\is_writable($dir)) {
		return '';
	}

	return \trailingslashit($dir) . (string) \constant(__NAMESPACE__ . '\\CMX_BANK_IMPORT_LOG_FILENAME');
}

function cmx_bank_import_render_log_page(): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('forbidden');
	}

	$log_file_path = cmx_bank_import_log_file_path();
	$open_log_url = \admin_url('admin-post.php?action=cmx_bank_import_open_logfile');
	$log_exists = $log_file_path !== '' && \is_file($log_file_path) && \is_readable($log_file_path);

	echo '<div class="wrap">';
	echo '<h1>Banken Auto-Import</h1>';
	echo '<p>Ein Live-Protokoll wird nur angezeigt, wenn eine Logdatei vorhanden ist.</p>';

	if ($log_exists) {
		echo '<p><a class="button" href="' . \esc_url($open_log_url) . '" target="_blank" rel="noopener noreferrer">Logdatei öffnen</a></p>';
		echo '<p><code>' . \esc_html($log_file_path) . '</code></p>';
	} else {
		echo '<div class="notice notice-info"><p>Keine CAMT-Logdatei gefunden.</p></div>';
	}

	echo '</div>';
}

\add_action('admin_menu', function (): void {
	\add_submenu_page(
		'edit.php?post_type=scanner',
		'Banken Auto-Import',
		'Banken',
		'manage_options',
		'cmx-camt-import-log',
		__NAMESPACE__ . '\\cmx_bank_import_render_log_page'
	);
});

\add_action('admin_post_cmx_bank_import_open_logfile', function (): void {
	if (!\current_user_can('manage_options')) {
		\wp_die('forbidden');
	}

	$path = cmx_bank_import_log_file_path();
	if ($path === '' || !\is_file($path) || !\is_readable($path)) {
		\wp_die('Logdatei nicht verfuegbar');
	}

	@\nocache_headers();
	@header('Content-Type: text/plain; charset=utf-8');
	@header('Content-Disposition: inline; filename="' . \basename($path) . '"');
	@header('X-Content-Type-Options: nosniff');
	@\readfile($path);
	exit;
});
