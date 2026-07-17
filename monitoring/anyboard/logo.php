<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');
?>

<div class="logo-widget">
    <img src="<?php echo \esc_url(\function_exists(__NAMESPACE__ . '\\cmx_misbuero_favicon_url') ? cmx_misbuero_favicon_url() : ''); ?>" alt="Mis Buero Logo">
</div>

<style>
    .logo-widget {
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
        min-height: 90px;
    }

    .logo-widget img {
        width: 72px;
        height: 72px;
        object-fit: contain;
    }
</style>
