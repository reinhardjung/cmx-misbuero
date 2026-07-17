<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

if (\is_admin()) {
	return;
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_render_404_template')) {
	function cmx_render_404_template(): void {
		if (\is_admin() || \wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || !\is_404()) {
			return;
		}

		\status_header(404);
		\nocache_headers();

		$image_url = \plugins_url('assets/404.png', \dirname(__DIR__) . '/cmx-misbuero.php');
		?>
		<!doctype html>
		<html <?php \language_attributes(); ?>>
		<head>
			<meta charset="<?php echo \esc_attr(\get_bloginfo('charset')); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo \esc_html__('Seite nicht gefunden', 'cmx-misbuero'); ?></title>
			<style>
				html,
				body {
					margin: 0;
					min-height: 100%;
				}

				body {
					background: #fff;
					color: #111;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
				}

				.cmx-404-page {
					align-items: center;
					display: flex;
					justify-content: center;
					min-height: 100vh;
					padding: 48px 20px;
					box-sizing: border-box;
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
		</head>
		<body>
			<main class="cmx-404-page" role="main">
				<div class="cmx-404-page__inner">
					<img class="cmx-404-page__image" src="<?php echo \esc_url($image_url); ?>" alt="<?php echo \esc_attr__('Seite nicht gefunden', 'cmx-misbuero'); ?>">
					<a class="cmx-404-page__link" href="<?php echo \esc_url(\home_url('/')); ?>">
						<?php echo \esc_html__('zur Startseite', 'cmx-misbuero'); ?>
					</a>
				</div>
			</main>
		</body>
		</html>

		<?php
		exit;
	}
}

\add_action('template_redirect', __NAMESPACE__ . '\\cmx_render_404_template', 999);
