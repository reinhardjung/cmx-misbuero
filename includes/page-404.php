<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

status_header(404);
nocache_headers();

$image_url = plugins_url('assets/404.png', dirname(__DIR__) . '/cmx-misbuero.php');

get_header();
?>

<main id="primary" class="site-main cmx-404-page" role="main">
	<style>
		.cmx-404-page {
			align-items: center;
			display: flex;
			justify-content: center;
			min-height: 70vh;
			padding: 48px 20px;
			text-align: center;
		}

		.cmx-404-page__inner {
			max-width: 720px;
			width: 100%;
		}

		.cmx-404-page__image {
			display: block;
			height: auto;
			margin: 0 auto 32px;
			max-width: min(100%, 520px);
		}

		.cmx-404-page__link {
			color: currentColor;
			display: inline-block;
			font-size: 18px;
			font-weight: 700;
			text-decoration: underline;
			text-underline-offset: 4px;
		}
	</style>

	<div class="cmx-404-page__inner">
		<img class="cmx-404-page__image" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr__('Seite nicht gefunden', 'cmx-misbuero'); ?>">
		<a class="cmx-404-page__link" href="<?php echo esc_url(home_url('/')); ?>">
			<?php echo esc_html__('zur Startseite', 'cmx-misbuero'); ?>
		</a>
	</div>
</main>

<?php
get_footer();
