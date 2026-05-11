<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

add_action('mis_buero/modules/loaded', static function (): void {
	do_action('mis_buero/modules/payrexx/loaded');
});
