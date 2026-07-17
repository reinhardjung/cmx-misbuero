<?php
use CLOUDMEISTER\CMX\Buero\Website\Icons;
use CLOUDMEISTER\CMX\Buero\Website\Renderer;

defined('ABSPATH') || exit;

$company = \trim((string) ($settings['company_name'] ?? ''));
$company = $company !== '' ? $company : (string) \get_bloginfo('name');
$site_url = \home_url('/');
$phone = \trim((string) ($settings['phone'] ?? ''));
$email = \trim((string) ($settings['email'] ?? ''));
$hero = (array) ($settings['hero'] ?? []);
$hero_points = (array) ($settings['hero_points'] ?? []);
$site_description = \trim((string) \wp_strip_all_tags((string) ($hero['subtitle'] ?? '')));
if ($site_description === '') {
	$site_description = \trim((string) \get_bloginfo('description'));
}
$site_preview_image = Renderer::preview_image_url((string) ($settings['logo_file'] ?? ''));
$services = (array) ($settings['services'] ?? []);
$articles = (array) ($settings['articles'] ?? []);
$process = (array) ($settings['process'] ?? []);
$about = (array) ($settings['about'] ?? []);
$advantages = (array) ($settings['advantages'] ?? []);
$faq = (array) ($settings['faq'] ?? []);
$contact = (array) ($settings['contact'] ?? []);
$opening_hours = (array) ($settings['opening_hours'] ?? []);
$legal = (array) ($settings['legal'] ?? []);
$is_logged_in = \is_user_logged_in();
$magic_login_url = (!$is_logged_in && \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_magic_login_request_url'))
	? \add_query_arg('cmx_magic_async', '1', \CLOUDMEISTER\CMX\Buero\cmx_magic_login_request_url(true))
	: '';
$login_url = $is_logged_in
	? \wp_logout_url(\home_url('/'))
	: ($magic_login_url !== '' ? '#anmelden' : \wp_login_url(\admin_url('/')));
$login_label = $is_logged_in ? __('Abmelden', 'cmx-misbuero') : __('Anmelden', 'cmx-misbuero');
$login_status = \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_magic_login_status_message')
	? \CLOUDMEISTER\CMX\Buero\cmx_magic_login_status_message()
	: '';
$about_nav_label = \trim((string) \wp_strip_all_tags((string) ($about['kicker'] ?? '')));
if ($about_nav_label === '') {
	$about_nav_label = __('Über uns', 'cmx-misbuero');
}
$articles_nav_label = \trim((string) \wp_strip_all_tags((string) ($articles['kicker'] ?? '')));
if ($articles_nav_label === '') {
	$articles_nav_label = __('Artikel', 'cmx-misbuero');
}
$website_articles = !empty($articles['enabled']) ? Renderer::website_articles(12) : [];
$booking_services = Renderer::booking_services();
$show_booking_services = $booking_services !== [];
$booking_calendar_data = $show_booking_services ? Renderer::booking_calendar_data($booking_services) : ['slots' => [], 'day_dates' => []];
$booking_services_json = $show_booking_services ? (string) \wp_json_encode($booking_services) : '[]';
$booking_slots_json = $show_booking_services ? (string) \wp_json_encode((array) ($booking_calendar_data['slots'] ?? [])) : '{}';
$booking_day_dates_json = $show_booking_services ? (string) \wp_json_encode((array) ($booking_calendar_data['day_dates'] ?? [])) : '{}';
$booking_amount_options_json = $show_booking_services ? (string) \wp_json_encode((array) ($booking_calendar_data['amount_options'] ?? [])) : '{}';
$booking_form_url = \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_url')
	? \CLOUDMEISTER\CMX\Buero\cmx_buchungen_frontend_url()
	: Renderer::booking_url();
$booking_agb_link = \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_buchungen_frontend_agb_link')
	? \CLOUDMEISTER\CMX\Buero\cmx_buchungen_frontend_agb_link()
	: '';
$opening_hour_rows = !empty($opening_hours['enabled']) ? Renderer::opening_hours_rows($opening_hours) : [];
$show_opening_hours = $opening_hour_rows !== [];
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
	<meta property="og:type" content="website">
	<meta property="og:title" content="<?php echo \esc_attr($company); ?>">
	<meta property="og:url" content="<?php echo \esc_url($site_url); ?>">
	<?php if ($site_description !== ''): ?>
		<meta name="description" content="<?php echo \esc_attr($site_description); ?>">
		<meta property="og:description" content="<?php echo \esc_attr($site_description); ?>">
	<?php endif; ?>
	<?php if ($site_preview_image !== ''): ?>
		<meta property="og:image" content="<?php echo \esc_url($site_preview_image); ?>">
		<meta name="twitter:card" content="summary_large_image">
		<meta name="twitter:image" content="<?php echo \esc_url($site_preview_image); ?>">
	<?php endif; ?>
	<?php if ((string) ($css_inline ?? '') !== ''): ?>
		<style id="mib-website-css"><?php echo (string) $css_inline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
	<?php else: ?>
		<link rel="stylesheet" href="<?php echo \esc_url($css_url); ?>?ver=<?php echo \esc_attr($css_version); ?>">
	<?php endif; ?>
	<style>
		:root{
			--mib-website-primary:<?php echo \esc_html($colors['primary']); ?>;
			--mib-website-primary-dark:<?php echo \esc_html($colors['dark']); ?>;
			--mib-website-primary-soft:<?php echo \esc_html($colors['soft']); ?>;
			--mib-website-primary-rgb:<?php echo \esc_html($colors['rgb']); ?>;
		}
	</style>
	<?php echo \function_exists('CLOUDMEISTER\\CMX\\Buero\\cmx_misbuero_favicon_links') ? \CLOUDMEISTER\CMX\Buero\cmx_misbuero_favicon_links() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</head>
<body class="mib-website-page">
	<header class="mib-site-header">
			<div class="mib-site-container mib-site-nav">
			<a class="mib-site-brand" href="<?php echo \esc_url(\home_url('/')); ?>" aria-label="<?php echo \esc_attr($company); ?>">
					<?php
					$logo_html = Renderer::logo_image((string) ($settings['logo_file'] ?? ''), 'mib-site-logo', $company);
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
				<?php if ($website_articles !== []): ?>
					<a href="#artikel"><?php echo \esc_html($articles_nav_label); ?></a>
				<?php endif; ?>
				<?php if ($show_booking_services): ?>
					<a href="#buchungen"><?php echo \esc_html__('Buchungen', 'cmx-misbuero'); ?></a>
				<?php endif; ?>
				<a href="#ablauf"><?php echo \esc_html__('Ablauf', 'cmx-misbuero'); ?></a>
				<a href="#ueber-uns"><?php echo \esc_html($about_nav_label); ?></a>
				<a href="#kontakt"><?php echo \esc_html__('Kontakt', 'cmx-misbuero'); ?></a>
				<?php if ($show_opening_hours): ?>
					<a href="#oeffnungszeiten"><?php echo \esc_html__('Öffnungszeiten', 'cmx-misbuero'); ?></a>
				<?php endif; ?>
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
				<?php
				$visible_hero_points = \array_values(\array_filter(\array_slice($hero_points, 0, 3), static function ($point): bool {
					return \is_array($point) && \trim((string) ($point['title'] ?? '')) !== '';
				}));
				?>
				<?php if ($visible_hero_points !== []): ?>
					<div class="mib-hero-points">
						<?php foreach ($visible_hero_points as $point): ?>
							<span><?php echo Icons::render((string) ($point['icon'] ?? 'star')); ?><?php echo \esc_html((string) ($point['title'] ?? '')); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
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

		<?php if ($website_articles !== []): ?>
			<section id="artikel" class="mib-section mib-article-section">
				<div class="mib-site-container">
					<div class="mib-section-head">
						<p class="mib-kicker"><?php echo \esc_html((string) ($articles['kicker'] ?? '')); ?></p>
						<h2><?php echo \esc_html((string) ($articles['title'] ?? '')); ?></h2>
						<div><?php echo \wp_kses_post(\wpautop((string) ($articles['subtitle'] ?? ''))); ?></div>
					</div>
					<div class="mib-article-grid">
						<?php foreach ($website_articles as $item): ?>
							<?php $dialog_id = \sanitize_html_class((string) ($item['id'] ?? '')); ?>
							<article class="mib-article-card">
								<button type="button" class="mib-article-trigger" data-mib-article-open="<?php echo \esc_attr($dialog_id); ?>" aria-haspopup="dialog">
									<span class="mib-article-media">
										<?php if (!empty($item['image_url'])): ?>
											<img src="<?php echo \esc_url((string) $item['image_url']); ?>" alt="<?php echo \esc_attr((string) ($item['title'] ?? '')); ?>" loading="lazy" decoding="async">
										<?php else: ?>
											<span class="mib-article-placeholder"><?php echo Icons::render('file-text'); ?></span>
										<?php endif; ?>
									</span>
									<span class="mib-article-body">
										<span class="mib-article-meta">
											<?php if ((string) ($item['sku'] ?? '') !== ''): ?><span><?php echo \esc_html((string) $item['sku']); ?></span><?php endif; ?>
											<?php if ((string) ($item['price_label'] ?? '') !== ''): ?><strong><?php echo \esc_html((string) $item['price_label']); ?></strong><?php endif; ?>
										</span>
										<span class="mib-article-title"><?php echo \esc_html((string) ($item['title'] ?? '')); ?></span>
										<?php if ((string) ($item['description'] ?? '') !== ''): ?>
											<span class="mib-article-text"><?php echo \wp_kses(\nl2br(\esc_html((string) $item['description'])), ['br' => []]); ?></span>
										<?php endif; ?>
										<span class="mib-article-more"><?php echo \esc_html__('Details ansehen', 'cmx-misbuero'); ?></span>
									</span>
								</button>
							</article>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="mib-article-dialogs" data-mib-article-root>
					<?php foreach ($website_articles as $item): ?>
						<?php $dialog_id = \sanitize_html_class((string) ($item['id'] ?? '')); ?>
						<div class="mib-article-modal" id="<?php echo \esc_attr($dialog_id); ?>" data-mib-article-modal hidden>
							<div class="mib-article-backdrop" data-mib-article-close></div>
							<div class="mib-article-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo \esc_attr($dialog_id . '-title'); ?>" tabindex="-1">
								<button type="button" class="mib-article-close" data-mib-article-close aria-label="<?php echo \esc_attr__('Schliessen', 'cmx-misbuero'); ?>">&times;</button>
								<?php if (!empty($item['image_url'])): ?>
									<img class="mib-article-dialog-image" src="<?php echo \esc_url((string) $item['image_url']); ?>" alt="<?php echo \esc_attr((string) ($item['title'] ?? '')); ?>" loading="lazy" decoding="async">
								<?php endif; ?>
								<div class="mib-article-dialog-content">
									<div class="mib-article-meta">
										<?php if ((string) ($item['sku'] ?? '') !== ''): ?><span><?php echo \esc_html((string) $item['sku']); ?></span><?php endif; ?>
										<?php if ((string) ($item['price_label'] ?? '') !== ''): ?><strong><?php echo \esc_html((string) $item['price_label']); ?></strong><?php endif; ?>
									</div>
									<h3 id="<?php echo \esc_attr($dialog_id . '-title'); ?>"><?php echo \esc_html((string) ($item['title'] ?? '')); ?></h3>
									<div class="mib-article-detail-text"><?php echo \wp_kses_post(\wpautop(\esc_html((string) ($item['detail'] ?? '')))); ?></div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
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

		<?php if ($show_booking_services): ?>
			<section id="buchungen" class="mib-section mib-booking-section" data-mib-booking-app data-services="<?php echo \esc_attr($booking_services_json); ?>" data-slots="<?php echo \esc_attr($booking_slots_json); ?>" data-day-dates="<?php echo \esc_attr($booking_day_dates_json); ?>" data-amount-options="<?php echo \esc_attr($booking_amount_options_json); ?>">
				<div class="mib-site-container">
					<div class="mib-section-head">
						<p class="mib-kicker"><?php echo \esc_html__('Online buchen', 'cmx-misbuero'); ?></p>
						<h2><?php echo \esc_html__('Buchungsmöglichkeiten', 'cmx-misbuero'); ?></h2>
						<div><?php echo \esc_html__('Wählen Sie die passende Leistung und buchen Sie direkt online einen Termin.', 'cmx-misbuero'); ?></div>
					</div>
					<div class="mib-booking-grid">
						<?php foreach ($booking_services as $service): ?>
							<article class="mib-booking-card" style="--mib-booking-accent:<?php echo \esc_attr((string) ($service['color'] ?? '#2563eb')); ?>" data-mib-booking-card="<?php echo \esc_attr((string) ($service['id'] ?? '')); ?>">
								<div class="mib-booking-media">
									<?php if ((string) ($service['image'] ?? '') !== ''): ?>
										<img src="<?php echo \esc_url((string) $service['image']); ?>" alt="<?php echo \esc_attr((string) ($service['title'] ?? '')); ?>" loading="lazy" decoding="async">
									<?php else: ?>
										<span><?php echo \esc_html(\mb_substr((string) ($service['avatar_label'] ?? '?'), 0, 1)); ?></span>
									<?php endif; ?>
								</div>
								<div class="mib-booking-card-body">
									<?php if ((string) ($service['person'] ?? '') !== ''): ?>
										<p><?php echo \esc_html((string) $service['person']); ?></p>
									<?php endif; ?>
									<h3><?php echo \esc_html((string) ($service['title'] ?? '')); ?></h3>
									<?php if ((string) ($service['duration'] ?? '') !== ''): ?>
										<span><?php echo Icons::render('calendar'); ?><?php echo \esc_html((string) $service['duration']); ?></span>
									<?php endif; ?>
									<button type="button" data-mib-service-id="<?php echo \esc_attr((string) ($service['id'] ?? '')); ?>"><?php echo \esc_html__('Termin buchen', 'cmx-misbuero'); ?></button>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<div class="mib-booking-flow" data-mib-booking-flow hidden>
						<div class="mib-booking-scheduler" data-mib-step="schedule">
							<aside class="mib-booking-side">
								<div data-side-avatar></div>
								<h3 data-side-title></h3>
								<div class="mib-booking-side-row"><?php echo Icons::render('calendar'); ?><span data-side-duration></span></div>
								<div class="mib-booking-side-row" data-side-period-row><?php echo Icons::render('clock'); ?><span data-side-period></span></div>
							</aside>
							<section id="buchung-datum" class="mib-booking-calendar" aria-label="<?php echo \esc_attr__('Datum auswählen', 'cmx-misbuero'); ?>">
								<div class="mib-booking-calendar-head">
									<button type="button" class="mib-booking-nav" data-prev-month aria-label="<?php echo \esc_attr__('Vorheriger Monat', 'cmx-misbuero'); ?>">‹</button>
									<button type="button" class="mib-booking-month" data-month-label data-today-jump></button>
									<button type="button" class="mib-booking-nav is-next" data-next-month aria-label="<?php echo \esc_attr__('Nächster Monat', 'cmx-misbuero'); ?>">›</button>
								</div>
								<div class="mib-booking-weekdays"><span>MO</span><span>DI</span><span>MI</span><span>DO</span><span>FR</span><span>SA</span><span>SO</span></div>
								<div class="mib-booking-days" data-calendar-days></div>
							</section>
							<aside class="mib-booking-slots" data-slots-list></aside>
						</div>
						<form class="mib-booking-form" method="post" enctype="multipart/form-data" action="<?php echo \esc_url($booking_form_url); ?>" data-mib-step="form" hidden>
							<h3><?php echo \esc_html__('Kontaktdaten', 'cmx-misbuero'); ?></h3>
							<input type="hidden" name="cmx_buchungen_frontend_action" value="book">
							<input type="hidden" name="template_id" data-form-template>
							<input type="hidden" name="service_id" data-form-service>
							<input type="hidden" name="date" data-form-date>
							<input type="hidden" name="time" data-form-time>
							<input type="hidden" name="booking_days" data-form-booking-days value="1">
							<?php \wp_nonce_field('cmx_buchungen_frontend_book', 'cmx_buchungen_frontend_nonce'); ?>
							<div class="mib-booking-form-grid">
								<label><?php echo \esc_html__('Name', 'cmx-misbuero'); ?><input name="name" autocomplete="name" required></label>
								<label class="mib-booking-field-email"><?php echo \esc_html__('E-Mail', 'cmx-misbuero'); ?><input type="email" name="email" autocomplete="email" required></label>
								<label class="mib-booking-field-phone"><?php echo \esc_html__('Telefon', 'cmx-misbuero'); ?><input name="phone" autocomplete="tel"></label>
								<label class="mib-booking-field-street"><?php echo \esc_html__('Strasse', 'cmx-misbuero'); ?><input name="street" autocomplete="street-address"></label>
								<label class="mib-booking-field-zip"><?php echo \esc_html__('PLZ', 'cmx-misbuero'); ?><input name="zip" autocomplete="postal-code"></label>
								<label class="mib-booking-field-city"><?php echo \esc_html__('Ort', 'cmx-misbuero'); ?><input name="city" autocomplete="address-level2"></label>
								<label class="is-wide"><?php echo \esc_html__('Termin', 'cmx-misbuero'); ?><input data-form-summary readonly tabindex="-1" aria-readonly="true"></label>
								<label class="is-wide" data-cr-extra hidden><?php echo \esc_html__('Führerausweis', 'cmx-misbuero'); ?><input type="file" name="license_file" accept="image/*" data-cr-field disabled></label>
								<?php if ($booking_agb_link !== ''): ?>
									<label class="mib-booking-agb-label"><input type="checkbox" name="agb_accepted" value="1" required> <span><?php echo \wp_kses_post(\sprintf(__('Ich habe die <a href="%s" target="_blank" rel="noopener noreferrer">AGB gelesen</a>, verstanden und akzeptiert.', 'cmx-misbuero'), \esc_url($booking_agb_link))); ?></span></label>
								<?php endif; ?>
								<label class="is-wide"><?php echo \esc_html__('Notiz', 'cmx-misbuero'); ?><textarea name="note"></textarea></label>
								<p class="mib-booking-form-note"><?php echo \esc_html__('Du erhältst sofort eine Eingangsbestätigung per E-Mail.', 'cmx-misbuero'); ?><br><?php echo \esc_html__('Die Buchung wird erst nach zusätzlicher manueller Bestätigung verbindlich.', 'cmx-misbuero'); ?></p>
							</div>
							<div class="mib-booking-form-actions">
								<button type="button" class="mib-booking-link" data-back-schedule><?php echo \esc_html__('Zurück', 'cmx-misbuero'); ?></button>
								<button type="submit" class="mib-booking-submit"><?php echo \esc_html__('buchen', 'cmx-misbuero'); ?></button>
							</div>
						</form>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section id="warum-wir" class="mib-advantages">
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
			<section id="faq" class="mib-section mib-faq">
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

		<?php if ($show_opening_hours): ?>
			<section id="oeffnungszeiten" class="mib-opening-hours">
				<div class="mib-site-container">
					<div class="mib-section-head">
						<p class="mib-kicker"><?php echo \esc_html((string) ($opening_hours['kicker'] ?? '')); ?></p>
						<h2><?php echo \esc_html((string) ($opening_hours['title'] ?? '')); ?></h2>
						<?php if ((string) ($opening_hours['subtitle'] ?? '') !== ''): ?>
							<div><?php echo \wp_kses_post(\wpautop((string) ($opening_hours['subtitle'] ?? ''))); ?></div>
						<?php endif; ?>
					</div>
					<div class="mib-opening-hours-panel">
						<div class="mib-opening-hours-icon"><?php echo Icons::render('calendar'); ?></div>
						<div class="mib-opening-hours-list">
							<?php foreach ($opening_hour_rows as $row): ?>
								<article class="mib-opening-hours-row">
									<h3><?php echo \esc_html((string) ($row['label'] ?? '')); ?></h3>
									<div class="mib-opening-hours-times">
										<?php foreach ((array) ($row['slots'] ?? []) as $slot): ?>
											<span><?php echo \esc_html((string) $slot); ?></span>
										<?php endforeach; ?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</main>

	<footer class="mib-site-footer" id="anmelden">
		<span id="footer" class="mib-anchor-target" aria-hidden="true"></span>
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
			<div class="mib-login-actions">
				<span class="mib-login-status" data-mib-login-status<?php echo $login_status === '' ? ' hidden' : ''; ?>><?php echo \esc_html($login_status); ?></span>
				<a class="mib-login-link" href="<?php echo \esc_url($login_url); ?>"<?php echo $magic_login_url !== '' ? ' data-mib-magic-login-url="' . \esc_url($magic_login_url) . '" data-mib-magic-login-message="' . \esc_attr('prüfen Sie ihr E-Mail Postfach') . '"' : ''; ?>><?php echo \esc_html($login_label); ?></a>
			</div>
		</div>
	</footer>
	<?php if ($show_booking_services): ?>
		<script>
			(function(){
				var root = document.querySelector('[data-mib-booking-app]');
				if (!root || root.dataset.ready === '1') {
					return;
				}
				root.dataset.ready = '1';
				var services = [];
				var slots = {};
				var dayDates = {};
				var amountOptions = {};
				try { services = JSON.parse(root.getAttribute('data-services') || '[]'); } catch (err) {}
				try { slots = JSON.parse(root.getAttribute('data-slots') || '{}'); } catch (err) {}
				try { dayDates = JSON.parse(root.getAttribute('data-day-dates') || '{}'); } catch (err) {}
				try { amountOptions = JSON.parse(root.getAttribute('data-amount-options') || '{}'); } catch (err) {}
				var byId = {};
				services.forEach(function(service){ byId[String(service.id)] = service; });
				var flow = root.querySelector('[data-mib-booking-flow]');
				var selectedService = null;
				var selectedDate = '';
				var selectedTime = '';
				var selectedDayCount = 1;
				var viewDate = new Date();
				viewDate.setDate(1);
				var todayKey = ymd(new Date());
				var monthNames = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
				function qs(sel){ return root.querySelector(sel); }
				function bookingScrollOffset(extra){
					var header = document.querySelector('.mib-site-header');
					return (header ? header.offsetHeight : 0) + (extra || 0);
				}
				function scrollBookingElementIntoView(element, extraOffset){
					if (!element) {
						return;
					}
					window.requestAnimationFrame(function(){
						var top = element.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop || 0) - bookingScrollOffset(extraOffset);
						window.scrollTo({top: Math.max(0, top), behavior: 'smooth'});
					});
				}
				function showStep(name){
					if (flow) {
						flow.hidden = false;
					}
					root.querySelectorAll('[data-mib-step]').forEach(function(el){
						el.hidden = el.getAttribute('data-mib-step') !== name;
					});
				}
				function ymd(date){
					var y = date.getFullYear();
					var m = String(date.getMonth() + 1).padStart(2, '0');
					var d = String(date.getDate()).padStart(2, '0');
					return y + '-' + m + '-' + d;
				}
				function fmtTime(time){
					var parts = String(time || '').split(':');
					var h = parseInt(parts[0] || '0', 10);
					var m = parts[1] || '00';
					return String(h).padStart(2, '0') + ':' + m;
				}
				function isDayService(){
					return selectedService && String(selectedService.unit || 'minutes') === 'days';
				}
				function isAmountService(){
					return selectedService && ['days','hours'].indexOf(String(selectedService.unit || 'minutes')) !== -1;
				}
				function serviceSlots(){
					return selectedService ? (slots[String(selectedService.id)] || {}) : {};
				}
				function serviceDayDateSource(){
					return selectedService ? (dayDates[String(selectedService.id)] || {}) : {};
				}
				function serviceDayDates(){
					var map = {};
					var source = serviceDayDateSource();
					if (source && !Array.isArray(source)) {
						source = source[String(selectedDayCount)] || [];
					}
					(source || []).forEach(function(dateKey){ map[String(dateKey)] = true; });
					return map;
				}
				function serviceAmountOptions(){
					return selectedService ? (amountOptions[String(selectedService.id)] || {}) : {};
				}
				function dayAmountOptions(dateKey){
					var source = serviceDayDateSource();
					var options = [];
					if (source && !Array.isArray(source)) {
						Object.keys(source).forEach(function(count){
							var dates = Array.isArray(source[count]) ? source[count] : [];
							if (dates.indexOf(String(dateKey)) !== -1) {
								options.push(parseInt(count, 10));
							}
						});
					}
					return options.filter(function(value){ return isFinite(value) && value > 0; }).sort(function(a, b){ return a - b; });
				}
				function hourAmountOptions(dateKey, time){
					var source = serviceAmountOptions();
					var day = source[String(dateKey)] || {};
					var options = Array.isArray(day[String(time)]) ? day[String(time)] : [];
					return options.map(function(value){ return parseInt(value, 10); }).filter(function(value){ return isFinite(value) && value > 0; }).sort(function(a, b){ return a - b; });
				}
					function availableAmounts(){
						if (!selectedService) {
							return [];
						}
					if (isDayService()) {
						return dayAmountOptions(selectedDate);
					}
					if (String(selectedService.unit || 'minutes') === 'hours') {
						return selectedTime ? hourAmountOptions(selectedDate, selectedTime) : [];
						}
						return [];
					}
					function normalizeAmount(value){
						var options = availableAmounts();
						if (!options.length) {
							return 1;
						}
						var requested = parseInt(value || '1', 10);
						if (!isFinite(requested)) {
							requested = 1;
						}
						requested = Math.max(1, Math.min(60, requested));
						if (options.indexOf(requested) !== -1) {
							return requested;
						}
						for (var i = options.length - 1; i >= 0; i--) {
							if (options[i] <= requested) {
								return options[i];
							}
						}
						return options[0];
					}
					function syncSelectedAmount(){
						if (!isAmountService()) {
							selectedDayCount = 1;
							return;
						}
					var options = availableAmounts();
					if (!options.length) {
							selectedDayCount = 1;
							return;
						}
						if (options.indexOf(selectedDayCount) === -1) {
							selectedDayCount = normalizeAmount(selectedService.duration_value || 1);
						}
					}
				function durationLabel(){
					if (!selectedService) {
						return '';
					}
					var unit = String(selectedService.unit || 'minutes');
					var duration = isAmountService() ? selectedDayCount : parseInt(selectedService.duration_value || selectedService.duration_minutes || 60, 10);
					if (isDayService()) {
						return String(duration) + (duration === 1 ? ' Tag' : ' Tage');
					}
					if (unit === 'hours') {
						return String(duration) + (duration === 1 ? ' Stunde' : ' Stunden');
					}
					return selectedService.duration || String(selectedService.duration_minutes || duration) + ' Minuten';
				}
				function amountLabel(){
					if (!selectedService) {
						return '';
					}
					var unit = String(selectedService.unit || 'minutes');
					if (unit === 'days') {
						return selectedDayCount === 1 ? 'Tag' : 'Tage';
					}
					if (unit === 'hours') {
						return selectedDayCount === 1 ? 'Stunde' : 'Stunden';
					}
					return '';
				}
				function bookingModeLabel(){
					if (!selectedService) {
						return '';
					}
					var unit = String(selectedService.unit || 'minutes');
					if (unit === 'days') {
						return 'Tagesbuchung';
					}
					if (unit === 'hours') {
						return 'Auf Stundenbasis';
					}
					return 'Fixe Zeit';
				}
				function slotAllowed(time){
					if (isDayService()) {
						return true;
					}
					var period = selectedService ? String(selectedService.period || 'all') : 'all';
					if (period === 'all') {
						return true;
					}
					var hour = parseInt(String(time || '').split(':')[0] || '-1', 10);
					if (hour < 0) {
						return false;
					}
					return period === 'morning' ? hour < 12 : hour >= 12;
				}
				function weekdayAllowed(dateKey){
					if (!selectedService) {
						return false;
					}
					var allowed = Array.isArray(selectedService.weekdays) ? selectedService.weekdays.map(function(day){ return parseInt(day, 10); }) : [1,2,3,4,5];
					if (!allowed.length) {
						allowed = [1,2,3,4,5];
					}
					var date = new Date(String(dateKey) + 'T00:00:00');
					var weekday = date.getDay();
					weekday = weekday === 0 ? 7 : weekday;
					return allowed.indexOf(weekday) !== -1;
				}
				function filteredSlotsForDate(dateKey){
					if (!weekdayAllowed(dateKey)) {
						return [];
					}
					var filtered = (serviceSlots()[dateKey] || []).filter(slotAllowed);
					if (selectedService && String(selectedService.unit || 'minutes') === 'hours') {
						filtered = filtered.filter(function(time){ return hourAmountOptions(dateKey, time).length > 0; });
					}
					return filtered;
				}
				function firstSlotDate(){
					if (isDayService()) {
						var dates = {};
						var source = serviceDayDateSource();
						if (source && !Array.isArray(source)) {
							Object.keys(source).forEach(function(count){
								(Array.isArray(source[count]) ? source[count] : []).forEach(function(dateKey){
									dates[String(dateKey)] = true;
								});
							});
						}
						var dayKeys = Object.keys(dates).sort();
						return dayKeys.length ? dayKeys[0] : ymd(new Date());
					}
					var keys = Object.keys(serviceSlots()).sort();
					for (var i = 0; i < keys.length; i++) {
						if (filteredSlotsForDate(keys[i]).length) {
							return keys[i];
						}
					}
					return ymd(new Date());
				}
				function renderAvatar(target, className){
					if (!target || !selectedService) {
						return;
					}
					target.innerHTML = '';
					var wrap = document.createElement('div');
					wrap.className = className || 'mib-booking-media';
					if (selectedService.image) {
						var img = document.createElement('img');
						img.src = selectedService.image;
						img.alt = selectedService.title || '';
						wrap.appendChild(img);
					} else {
						var span = document.createElement('span');
						span.textContent = String(selectedService.avatar_label || selectedService.title || '?').charAt(0);
						wrap.appendChild(span);
					}
					target.appendChild(wrap);
				}
				function updateSide(){
					if (!selectedService) {
						return;
					}
					renderAvatar(qs('[data-side-avatar]'), 'mib-booking-media');
					qs('[data-side-title]').textContent = selectedService.title || '';
					qs('[data-side-duration]').textContent = durationLabel();
					var periodRow = qs('[data-side-period-row]');
					if (periodRow) {
						periodRow.hidden = false;
					}
					qs('[data-side-period]').textContent = bookingModeLabel();
				}
				function renderCalendar(){
					var label = qs('[data-month-label]');
					var grid = qs('[data-calendar-days]');
					if (!label || !grid) {
						return;
					}
					label.textContent = monthNames[viewDate.getMonth()] + ' ' + viewDate.getFullYear();
					grid.innerHTML = '';
					var first = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
					var last = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
					var firstOffset = (first.getDay() + 6) % 7;
					for (var blank = 0; blank < firstOffset; blank++) {
						grid.appendChild(document.createElement('span'));
					}
					for (var day = 1; day <= last.getDate(); day++) {
						var date = new Date(viewDate.getFullYear(), viewDate.getMonth(), day);
						var key = ymd(date);
						var btn = document.createElement('button');
						var hasSlots = isDayService() ? dayAmountOptions(key).length > 0 : filteredSlotsForDate(key).length > 0;
						btn.type = 'button';
						btn.className = 'mib-booking-day' + (hasSlots ? ' has-slots' : '') + (key === todayKey ? ' is-today' : '') + (key === selectedDate ? ' is-selected' : '');
						btn.textContent = String(day);
						btn.disabled = !hasSlots;
						btn.addEventListener('click', function(dateKey){
							return function(){
								selectedDate = dateKey;
								selectedTime = '';
								syncSelectedAmount();
								renderCalendar();
								renderSlots();
							};
						}(key));
						grid.appendChild(btn);
					}
				}
				function renderSlots(){
					var list = qs('[data-slots-list]');
					if (!list) {
						return;
					}
					list.innerHTML = '';
					if (isDayService()) {
						if (!dayAmountOptions(selectedDate).length) {
							var unavailable = document.createElement('div');
							unavailable.className = 'mib-booking-slot';
							unavailable.textContent = 'Nicht verfügbar';
							list.appendChild(unavailable);
							return;
						}
						syncSelectedAmount();
						var dayBtn = document.createElement('div');
						dayBtn.className = 'mib-booking-slot is-selected';
						dayBtn.innerHTML = '<span>Anzahl</span>';
						appendNextControls(dayBtn);
						list.appendChild(dayBtn);
						return;
					}
					var daySlots = filteredSlotsForDate(selectedDate);
					if (!daySlots.length) {
						var empty = document.createElement('div');
						empty.className = 'mib-booking-slot';
						empty.textContent = 'Keine freien Termine';
						list.appendChild(empty);
						return;
					}
					daySlots.forEach(function(time){
						var btn = document.createElement(selectedTime === time ? 'div' : 'button');
						if (selectedTime !== time) {
							btn.type = 'button';
						}
						btn.className = 'mib-booking-slot' + (selectedTime === time ? ' is-selected' : '');
						btn.innerHTML = '<span>' + fmtTime(time) + '</span>';
						if (selectedTime === time) {
							syncSelectedAmount();
							appendNextControls(btn);
						} else {
							var spots = document.createElement('small');
							spots.textContent = '1 Platz frei';
							btn.appendChild(spots);
						}
						btn.addEventListener('click', function(){
							if (selectedTime !== time) {
								selectedTime = time;
								syncSelectedAmount();
								renderSlots();
							}
						});
						list.appendChild(btn);
					});
				}
				function appendNextControls(row){
					var controls = document.createElement('span');
						controls.className = 'mib-booking-next-controls';
						if (isAmountService()) {
							syncSelectedAmount();
							var options = availableAmounts();
							var input = document.createElement('input');
							input.type = 'number';
							input.min = '1';
							input.max = String(options.length ? options[options.length - 1] : 1);
							input.step = '1';
							input.value = String(selectedDayCount);
							input.setAttribute('aria-label', 'Anzahl ' + amountLabel());
							input.addEventListener('click', function(event){ event.stopPropagation(); });
							input.addEventListener('input', function(event){
								event.stopPropagation();
								selectedDayCount = normalizeAmount(input.value);
								input.value = String(selectedDayCount);
								label.textContent = amountLabel();
								updateSide();
							});
							input.addEventListener('change', function(event){
								event.stopPropagation();
								selectedDayCount = normalizeAmount(input.value);
								input.value = String(selectedDayCount);
								label.textContent = amountLabel();
								updateSide();
								if (isDayService()) {
									renderCalendar();
								}
							renderSlots();
						});
						var label = document.createElement('span');
						label.className = 'mib-booking-count-label';
						label.textContent = amountLabel();
						controls.appendChild(input);
						controls.appendChild(label);
					}
					var next = document.createElement('button');
					next.type = 'button';
					next.className = 'mib-booking-next';
					next.textContent = 'Weiter';
					next.addEventListener('click', function(event){
						event.stopPropagation();
						openForm();
					});
					controls.appendChild(next);
					row.appendChild(controls);
				}
				function openSchedule(serviceId){
					selectedService = byId[String(serviceId)] || null;
					if (!selectedService) {
						return;
					}
					selectedDayCount = isAmountService() ? Math.max(1, Math.min(60, parseInt(selectedService.duration_value || 1, 10))) : 1;
					selectedDate = firstSlotDate();
					selectedTime = '';
					syncSelectedAmount();
					var first = selectedDate ? new Date(selectedDate + 'T00:00:00') : new Date();
					viewDate = new Date(first.getFullYear(), first.getMonth(), 1);
					root.querySelectorAll('[data-mib-booking-card]').forEach(function(card){
						card.classList.toggle('is-selected', card.getAttribute('data-mib-booking-card') === String(serviceId));
					});
					updateSide();
					renderCalendar();
					renderSlots();
					showStep('schedule');
					if (flow) {
						scrollBookingElementIntoView(root.querySelector('.mib-booking-scheduler') || flow, 68);
					}
				}
					function syncCrFields(){
						var show = !!(selectedService && selectedService.cr);
						root.querySelectorAll('[data-cr-extra]').forEach(function(row){
							row.hidden = !show;
					});
					root.querySelectorAll('[data-cr-field]').forEach(function(field){
						field.disabled = !show;
						if (!show) {
							if (field.type === 'checkbox') {
								field.checked = false;
							} else if (field.type !== 'file') {
								field.value = '';
							}
						}
					});
					root.querySelectorAll('[data-agb-checkbox]').forEach(function(field){
							field.required = show;
						});
					}
					function scrollToBookingForm(){
						var form = root.querySelector('[data-mib-step="form"]');
						scrollBookingElementIntoView(form, 130);
					}
					function openForm(){
						if (!selectedService || !selectedDate || (!selectedTime && !isDayService())) {
							return;
						}
					syncSelectedAmount();
					qs('[data-form-template]').value = String(selectedService.id);
					qs('[data-form-service]').value = String(selectedService.artikel_id || '');
					qs('[data-form-date]').value = selectedDate;
					qs('[data-form-time]').value = isDayService() ? '00:00' : selectedTime;
					qs('[data-form-booking-days]').value = isAmountService() ? String(selectedDayCount) : '1';
					var summaryDate = selectedDate;
					if (isDayService() && selectedDayCount > 1) {
						var endDate = new Date(selectedDate + 'T00:00:00');
						endDate.setDate(endDate.getDate() + selectedDayCount - 1);
						summaryDate = selectedDate + ' - ' + ymd(endDate);
					}
					qs('[data-form-summary]').value = (isDayService() ? summaryDate + ', ' + durationLabel() : fmtTime(selectedTime) + ', ' + selectedDate + (isAmountService() ? ', ' + durationLabel() : '')) + ' - ' + selectedService.title;
						syncCrFields();
						showStep('form');
						var name = root.querySelector('input[name="name"]');
						if (name) {
							try {
								name.focus({preventScroll: true});
							} catch (err) {
								name.focus();
							}
						}
						scrollToBookingForm();
					}
				root.querySelectorAll('[data-mib-service-id]').forEach(function(btn){
					btn.addEventListener('click', function(){
						openSchedule(btn.getAttribute('data-mib-service-id'));
					});
				});
				var prev = qs('[data-prev-month]');
				var next = qs('[data-next-month]');
				if (prev) {
					prev.addEventListener('click', function(){ viewDate.setMonth(viewDate.getMonth() - 1); renderCalendar(); renderSlots(); });
				}
				if (next) {
					next.addEventListener('click', function(){ viewDate.setMonth(viewDate.getMonth() + 1); renderCalendar(); renderSlots(); });
				}
				var todayJump = qs('[data-today-jump]');
				if (todayJump) {
					todayJump.addEventListener('click', function(){
						var today = new Date();
						viewDate = new Date(today.getFullYear(), today.getMonth(), 1);
						if ((isDayService() && dayAmountOptions(todayKey).length) || (!isDayService() && filteredSlotsForDate(todayKey).length)) {
							selectedDate = todayKey;
							selectedTime = '';
							syncSelectedAmount();
						}
						renderCalendar();
						renderSlots();
					});
				}
				var back = qs('[data-back-schedule]');
				if (back) {
					back.addEventListener('click', function(){ showStep('schedule'); });
				}
				syncCrFields();
			})();
		</script>
	<?php endif; ?>
	<?php if ($website_articles !== []): ?>
		<script>
			(function(){
				var active = null;
				var lastTrigger = null;
				function closeActive() {
					if (!active) {
						return;
					}
					active.hidden = true;
					document.body.classList.remove('mib-article-modal-open');
					active = null;
					if (lastTrigger) {
						lastTrigger.focus();
					}
				}
				document.querySelectorAll('[data-mib-article-open]').forEach(function(trigger){
					trigger.addEventListener('click', function(){
						var id = trigger.getAttribute('data-mib-article-open');
						var modal = id ? document.getElementById(id) : null;
						var dialog = modal ? modal.querySelector('.mib-article-dialog') : null;
						if (!modal || !dialog) {
							return;
						}
						closeActive();
						lastTrigger = trigger;
						active = modal;
						modal.hidden = false;
						document.body.classList.add('mib-article-modal-open');
						dialog.focus();
					});
				});
				document.addEventListener('click', function(event){
					if (event.target && event.target.closest('[data-mib-article-close]')) {
						closeActive();
					}
				});
				document.addEventListener('keydown', function(event){
					if (event.key === 'Escape') {
						closeActive();
					}
				});
			})();
		</script>
	<?php endif; ?>
	<?php if ($magic_login_url !== ''): ?>
		<script>
			(function(){
				var link = document.querySelector('[data-mib-magic-login-url]');
				if (!link) {
					return;
				}
				var status = document.querySelector('[data-mib-login-status]');
				var defaultMessage = link.getAttribute('data-mib-magic-login-message') || 'prüfen Sie ihr E-Mail Postfach';
				function showStatus(message) {
					if (!status) {
						return;
					}
					status.textContent = message || defaultMessage;
					status.hidden = false;
				}
				link.addEventListener('click', function(event){
					event.preventDefault();
					if (link.getAttribute('data-mib-loading') === '1') {
						return;
					}
					showStatus(defaultMessage);
					link.setAttribute('data-mib-loading', '1');
					fetch(link.getAttribute('data-mib-magic-login-url'), {
						method: 'GET',
						credentials: 'same-origin',
						headers: {
							'X-Requested-With': 'XMLHttpRequest'
						}
					}).then(function(response){
						return response.json().catch(function(){
							return null;
						});
					}).then(function(payload){
						var data = payload && payload.data ? payload.data : null;
						if (data && data.redirect) {
							window.location.href = data.redirect;
							return;
						}
						showStatus(data && data.message ? data.message : defaultMessage);
					}).catch(function(){
						showStatus(defaultMessage);
					}).finally(function(){
						link.removeAttribute('data-mib-loading');
					});
				});
			})();
		</script>
	<?php endif; ?>
</body>
</html>
