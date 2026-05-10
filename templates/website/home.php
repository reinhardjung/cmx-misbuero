<?php
use CLOUDMEISTER\CMX\Buero\Website\Icons;
use CLOUDMEISTER\CMX\Buero\Website\Renderer;

defined('ABSPATH') || exit;

$company = \trim((string) ($settings['company_name'] ?? ''));
$company = $company !== '' ? $company : (string) \get_bloginfo('name');
$phone = \trim((string) ($settings['phone'] ?? ''));
$email = \trim((string) ($settings['email'] ?? ''));
$hero = (array) ($settings['hero'] ?? []);
$services = (array) ($settings['services'] ?? []);
$process = (array) ($settings['process'] ?? []);
$about = (array) ($settings['about'] ?? []);
$advantages = (array) ($settings['advantages'] ?? []);
$faq = (array) ($settings['faq'] ?? []);
$contact = (array) ($settings['contact'] ?? []);
$legal = (array) ($settings['legal'] ?? []);
$login_url = \is_user_logged_in() ? \wp_logout_url(\home_url('/')) : \wp_login_url(\home_url('/'));
$login_label = \is_user_logged_in() ? __('Abmelden', 'cmx-misbuero') : __('Anmelden', 'cmx-misbuero');
$show_powered_by = \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_powered_by_enabled')
	? \CLOUDMEISTER\CMX\Buero\cmx_powered_by_enabled()
	: true;
?>
<!doctype html>
<html <?php \language_attributes(); ?>>
<head>
	<meta charset="<?php echo \esc_attr((string) \get_bloginfo('charset')); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo \esc_html($company); ?></title>
	<link rel="stylesheet" href="<?php echo \esc_url($css_url); ?>?ver=<?php echo \esc_attr($css_version); ?>">
	<style>
		:root{
			--mib-website-primary:<?php echo \esc_html($colors['primary']); ?>;
			--mib-website-primary-dark:<?php echo \esc_html($colors['dark']); ?>;
			--mib-website-primary-soft:<?php echo \esc_html($colors['soft']); ?>;
			--mib-website-primary-rgb:<?php echo \esc_html($colors['rgb']); ?>;
		}
	</style>
	<?php \wp_site_icon(); ?>
</head>
<body class="mib-website-page">
	<header class="mib-site-header">
		<div class="mib-site-container mib-site-nav">
			<a class="mib-site-brand" href="#start" aria-label="<?php echo \esc_attr($company); ?>">
				<?php
				$logo_html = Renderer::asset_image((string) ($settings['logo_file'] ?? ''), 'mib-site-logo', $company);
				if ($logo_html !== '') {
					echo $logo_html;
				} else {
					echo '<span class="mib-site-logo-mark">M</span>';
				}
				?>
				<span><?php echo \esc_html($company); ?></span>
			</a>
			<nav class="mib-site-menu" aria-label="<?php echo \esc_attr__('Hauptnavigation', 'cmx-misbuero'); ?>">
				<?php if (\is_user_logged_in()): ?>
					<a href="<?php echo \esc_url(\admin_url('/')); ?>"><?php echo \esc_html__('Start', 'cmx-misbuero'); ?></a>
				<?php endif; ?>
				<a href="#leistungen"><?php echo \esc_html__('Leistungen', 'cmx-misbuero'); ?></a>
				<a href="#ablauf"><?php echo \esc_html__('Ablauf', 'cmx-misbuero'); ?></a>
				<a href="#ueber-uns"><?php echo \esc_html__('Über uns', 'cmx-misbuero'); ?></a>
				<a href="#kontakt"><?php echo \esc_html__('Kontakt', 'cmx-misbuero'); ?></a>
			</nav>
		</div>
	</header>

	<main>
		<section id="start" class="mib-hero">
			<div class="mib-hero-copy">
				<p class="mib-kicker"><?php echo \esc_html((string) ($hero['kicker'] ?? '')); ?></p>
				<h1><?php echo \wp_kses_post((string) ($hero['title'] ?? '')); ?></h1>
				<div class="mib-hero-text"><?php echo \wp_kses_post(\wpautop((string) ($hero['text'] ?? ''))); ?></div>
				<div class="mib-hero-actions">
					<a class="mib-button mib-button-primary" href="<?php echo \esc_url(Renderer::link_url((string) ($hero['primary_url'] ?? ''), '#kontakt')); ?>"><?php echo Icons::render('rocket'); ?><?php echo \esc_html((string) ($hero['primary_text'] ?? __('Anfrage senden', 'cmx-misbuero'))); ?></a>
					<a class="mib-button mib-button-secondary" href="<?php echo \esc_url(Renderer::link_url((string) ($hero['secondary_url'] ?? ''), $phone !== '' ? Renderer::phone_url($phone) : '#kontakt')); ?>"><?php echo Icons::render('phone'); ?><?php echo \esc_html((string) ($hero['secondary_text'] ?? ($phone !== '' ? $phone : __('Kontakt', 'cmx-misbuero')))); ?></a>
				</div>
				<div class="mib-hero-points">
					<span><?php echo Icons::render('shield'); ?><?php echo \esc_html__('Erfahren & kompetent', 'cmx-misbuero'); ?></span>
					<span><?php echo Icons::render('check'); ?><?php echo \esc_html__('Zuverlässig & termintreu', 'cmx-misbuero'); ?></span>
					<span><?php echo Icons::render('star'); ?><?php echo \esc_html__('Individuelle Lösungen', 'cmx-misbuero'); ?></span>
				</div>
			</div>
			<div class="mib-hero-media">
				<?php
				$hero_image = Renderer::asset_image((string) ($settings['header_image_file'] ?? ''), 'mib-hero-image', $company);
				echo $hero_image !== '' ? $hero_image : '<div class="mib-hero-placeholder">' . Icons::render('chart') . '</div>';
				?>
			</div>
		</section>

		<?php if (!empty($services['enabled'])): ?>
			<section id="leistungen" class="mib-section">
				<div class="mib-site-container">
					<div class="mib-section-head">
						<p class="mib-kicker"><?php echo \esc_html((string) ($services['kicker'] ?? '')); ?></p>
						<h2><?php echo \esc_html((string) ($services['title'] ?? '')); ?></h2>
						<div><?php echo \wp_kses_post(\wpautop((string) ($services['subtitle'] ?? ''))); ?></div>
					</div>
					<div class="mib-card-grid mib-card-grid-three">
						<?php foreach (\array_slice((array) ($services['items'] ?? []), 0, 3) as $item): ?>
							<article class="mib-service-card">
								<div class="mib-card-icon"><?php echo Icons::render((string) ($item['icon'] ?? 'star')); ?></div>
								<h3><?php echo \esc_html((string) ($item['title'] ?? '')); ?></h3>
								<div><?php echo \wp_kses_post(\wpautop((string) ($item['text'] ?? ''))); ?></div>
								<a href="#kontakt"><?php echo \esc_html__('Mehr erfahren', 'cmx-misbuero'); ?> →</a>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if (!empty($process['enabled'])): ?>
			<section id="ablauf" class="mib-process">
				<div class="mib-site-container">
					<div class="mib-section-head">
						<p class="mib-kicker"><?php echo \esc_html((string) ($process['kicker'] ?? '')); ?></p>
						<h2><?php echo \esc_html((string) ($process['title'] ?? '')); ?></h2>
						<div><?php echo \wp_kses_post(\wpautop((string) ($process['subtitle'] ?? ''))); ?></div>
					</div>
					<div class="mib-process-grid">
						<?php foreach (\array_slice((array) ($process['items'] ?? []), 0, 4) as $index => $item): ?>
							<article class="mib-process-step">
								<div class="mib-step-icon"><?php echo Icons::render((string) ($item['icon'] ?? 'star')); ?><span><?php echo \esc_html((string) ($index + 1)); ?></span></div>
								<h3><?php echo \esc_html((string) ($item['title'] ?? '')); ?></h3>
								<div><?php echo \wp_kses_post(\wpautop((string) ($item['text'] ?? ''))); ?></div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if (!empty($about['enabled'])): ?>
			<section id="ueber-uns" class="mib-section mib-about-section">
				<div class="mib-site-container mib-about">
					<div>
						<p class="mib-kicker"><?php echo \esc_html((string) ($about['kicker'] ?? '')); ?></p>
						<h2><?php echo \esc_html((string) ($about['title'] ?? '')); ?></h2>
						<div class="mib-subtitle"><?php echo \wp_kses_post(\wpautop((string) ($about['subtitle'] ?? ''))); ?></div>
						<div><?php echo \wp_kses_post(\wpautop((string) ($about['text'] ?? ''))); ?></div>
					</div>
					<div class="mib-about-media">
						<?php
						$about_image = Renderer::asset_image((string) ($about['image_file'] ?? ''), 'mib-about-image', $company);
						echo $about_image !== '' ? $about_image : '<div class="mib-about-placeholder">' . Icons::render('users') . '</div>';
						?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section class="mib-advantages">
			<div class="mib-site-container">
				<div class="mib-section-head">
					<p class="mib-kicker"><?php echo \esc_html((string) ($advantages['kicker'] ?? '')); ?></p>
					<h2><?php echo \esc_html((string) ($advantages['title'] ?? '')); ?></h2>
				</div>
				<div class="mib-advantage-grid">
					<?php foreach (\array_slice((array) ($advantages['items'] ?? []), 0, 4) as $item): ?>
						<article class="mib-advantage-card">
							<?php echo Icons::render((string) ($item['icon'] ?? 'star')); ?>
							<h3><?php echo \esc_html((string) ($item['title'] ?? '')); ?></h3>
							<div><?php echo \wp_kses_post(\wpautop((string) ($item['text'] ?? ''))); ?></div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<?php if (!empty($faq['enabled'])): ?>
			<section class="mib-section mib-faq">
				<div class="mib-site-container">
					<div class="mib-section-head">
						<p class="mib-kicker"><?php echo \esc_html((string) ($faq['kicker'] ?? '')); ?></p>
						<h2><?php echo \esc_html((string) ($faq['title'] ?? '')); ?></h2>
						<div><?php echo \wp_kses_post(\wpautop((string) ($faq['subtitle'] ?? ''))); ?></div>
					</div>
					<div class="mib-faq-list">
						<?php foreach ((array) ($faq['items'] ?? []) as $item): ?>
							<details>
								<summary><?php echo \esc_html((string) ($item['question'] ?? '')); ?></summary>
								<div><?php echo \wp_kses_post(\wpautop((string) ($item['answer'] ?? ''))); ?></div>
							</details>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if (!empty($contact['enabled'])): ?>
			<section id="kontakt" class="mib-contact">
				<div class="mib-site-container">
					<div class="mib-contact-panel">
						<div>
							<p class="mib-kicker"><?php echo \esc_html((string) ($contact['kicker'] ?? '')); ?></p>
							<h2><?php echo \esc_html((string) ($contact['title'] ?? '')); ?></h2>
							<div><?php echo \wp_kses_post(\wpautop((string) ($contact['subtitle'] ?? ''))); ?></div>
							<?php if ((string) ($contact['address'] ?? '') !== ''): ?>
								<div class="mib-contact-address"><?php echo \wp_kses_post(\wpautop((string) ($contact['address'] ?? ''))); ?></div>
							<?php endif; ?>
						</div>
						<div class="mib-contact-actions">
							<a class="mib-button mib-button-light" href="<?php echo \esc_url(Renderer::link_url((string) ($contact['button_url'] ?? ''), Renderer::link_url((string) ($settings['contact_link'] ?? ''), '#kontakt'))); ?>"><?php echo Icons::render('mail'); ?><?php echo \esc_html((string) ($contact['button_text'] ?? __('Kontakt aufnehmen', 'cmx-misbuero'))); ?></a>
							<?php if ($phone !== ''): ?>
								<a class="mib-button mib-button-secondary" href="<?php echo \esc_url(Renderer::phone_url($phone)); ?>"><?php echo Icons::render('phone'); ?><?php echo \esc_html($phone); ?></a>
							<?php endif; ?>
							<?php if ($email !== ''): ?>
								<a class="mib-email-link" href="<?php echo \esc_url('mailto:' . $email); ?>"><?php echo \esc_html($email); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</main>

	<footer class="mib-site-footer">
		<div class="mib-site-container">
			<div class="mib-footer-left">
				<?php if ($show_powered_by): ?>
					<span><?php echo \wp_kses_post(__('powered by <a href="https://misbuero.ch/" target="_blank" rel="noopener noreferrer">MisBüro</a>', 'cmx-misbuero')); ?></span>
				<?php endif; ?>
				<nav aria-label="<?php echo \esc_attr__('Rechtliches', 'cmx-misbuero'); ?>">
					<?php if (!empty($legal['impressum_url'])): ?><a href="<?php echo \esc_url((string) $legal['impressum_url']); ?>"><?php echo \esc_html__('Impressum', 'cmx-misbuero'); ?></a><?php endif; ?>
					<?php if (!empty($legal['privacy_url'])): ?><a href="<?php echo \esc_url((string) $legal['privacy_url']); ?>"><?php echo \esc_html__('Datenschutz', 'cmx-misbuero'); ?></a><?php endif; ?>
					<?php if (!empty($legal['agb_url'])): ?><a href="<?php echo \esc_url((string) $legal['agb_url']); ?>"><?php echo \esc_html__('AGB', 'cmx-misbuero'); ?></a><?php endif; ?>
				</nav>
			</div>
			<a class="mib-login-link" href="<?php echo \esc_url($login_url); ?>"><?php echo \esc_html($login_label); ?></a>
		</div>
	</footer>
</body>
</html>
