<?php
namespace CLOUDMEISTER\CMX\Buero\Website;

defined('ABSPATH') || exit;

final class Presets {
	public static function industries(): array {
		return [
			'dienstleister' => __('Dienstleister', 'cmx-misbuero'),
			'handwerker'   => __('Handwerker', 'cmx-misbuero'),
			'beratung'     => __('Beratung', 'cmx-misbuero'),
			'reinigung'    => __('Reinigung', 'cmx-misbuero'),
			'treuhand'     => __('Treuhand', 'cmx-misbuero'),
			'immobilien'   => __('Immobilien', 'cmx-misbuero'),
			'allgemein'    => __('Allgemein', 'cmx-misbuero'),
		];
	}

	public static function defaults(string $industry = 'dienstleister'): array {
		$industry = isset(self::industries()[$industry]) ? $industry : 'dienstleister';
		$base = self::preset($industry);

		return [
			'enabled' => '1',
			'industry' => $industry,
			'company_name' => (string) \get_bloginfo('name'),
			'logo_id' => 0,
			'primary_color' => '#0F63F6',
			'header_image_id' => 0,
			'phone' => '',
			'email' => (string) \get_option('admin_email', ''),
			'contact_link' => '#kontakt',
			'hero' => $base['hero'],
			'legal' => [
				'impressum_url' => '',
				'privacy_url' => self::privacy_url(),
				'agb_url' => '',
			],
			'services' => $base['services'],
			'process' => $base['process'],
			'about' => $base['about'],
			'advantages' => $base['advantages'],
			'faq' => $base['faq'],
			'contact' => $base['contact'],
		];
	}

	public static function content_preset(string $industry): array {
		$preset = self::preset($industry);
		return [
			'hero' => $preset['hero'],
			'services' => $preset['services'],
			'process' => $preset['process'],
			'about' => $preset['about'],
			'advantages' => $preset['advantages'],
			'faq' => $preset['faq'],
			'contact' => $preset['contact'],
		];
	}

	private static function preset(string $industry): array {
		$labels = self::industries();
		$label = (string) ($labels[$industry] ?? $labels['dienstleister']);
		$service_word = match ($industry) {
			'handwerker' => __('Handwerksleistungen', 'cmx-misbuero'),
			'beratung' => __('Beratung', 'cmx-misbuero'),
			'reinigung' => __('Reinigung', 'cmx-misbuero'),
			'treuhand' => __('Treuhand', 'cmx-misbuero'),
			'immobilien' => __('Immobilien-Service', 'cmx-misbuero'),
			default => __('Dienstleistungen', 'cmx-misbuero'),
		};

		return [
			'hero' => [
				'kicker' => __('Ihr Partner für Erfolg', 'cmx-misbuero'),
				'title' => \sprintf(__('%s, die den Unterschied machen', 'cmx-misbuero'), $service_word),
				'text' => __('Wir bieten individuelle Lösungen und unterstützen Sie dabei, Ihre Ziele effizient und nachhaltig zu erreichen.', 'cmx-misbuero'),
				'primary_text' => __('Anfrage senden', 'cmx-misbuero'),
				'primary_url' => '#kontakt',
				'secondary_text' => __('Anrufen', 'cmx-misbuero'),
				'secondary_url' => '#kontakt',
			],
			'services' => [
				'enabled' => '1',
				'kicker' => __('Leistungen', 'cmx-misbuero'),
				'title' => __('Unsere Leistungen im Überblick', 'cmx-misbuero'),
				'subtitle' => __('Wir bieten ein breites Spektrum an Leistungen, passend zu Ihren Anforderungen.', 'cmx-misbuero'),
				'items' => [
					['icon' => 'message', 'title' => __('Beratung', 'cmx-misbuero'), 'text' => __('Wir analysieren Ihre Situation und entwickeln tragfähige Lösungen.', 'cmx-misbuero')],
					['icon' => 'rocket', 'title' => __('Umsetzung', 'cmx-misbuero'), 'text' => __('Wir setzen Projekte professionell, strukturiert und zuverlässig um.', 'cmx-misbuero')],
					['icon' => 'headset', 'title' => __('Betreuung', 'cmx-misbuero'), 'text' => __('Auch danach bleiben wir Ihr Ansprechpartner für Fragen und Optimierungen.', 'cmx-misbuero')],
				],
			],
			'process' => [
				'enabled' => '1',
				'kicker' => __('Ablauf', 'cmx-misbuero'),
				'title' => __('So arbeiten wir gemeinsam', 'cmx-misbuero'),
				'subtitle' => __('Ein klarer Prozess sorgt für transparente Ergebnisse.', 'cmx-misbuero'),
				'items' => [
					['icon' => 'message', 'title' => __('Kennenlernen', 'cmx-misbuero'), 'text' => __('Wir hören zu und verstehen Ihre Anforderungen.', 'cmx-misbuero')],
					['icon' => 'clipboard', 'title' => __('Planung', 'cmx-misbuero'), 'text' => __('Wir entwickeln eine klare Strategie und einen Plan.', 'cmx-misbuero')],
					['icon' => 'gear', 'title' => __('Umsetzung', 'cmx-misbuero'), 'text' => __('Wir setzen alles professionell und termingerecht um.', 'cmx-misbuero')],
					['icon' => 'check', 'title' => __('Erfolg sichern', 'cmx-misbuero'), 'text' => __('Wir sorgen für nachhaltige Ergebnisse und Betreuung.', 'cmx-misbuero')],
				],
			],
			'about' => [
				'enabled' => '1',
				'kicker' => __('Über uns', 'cmx-misbuero'),
				'title' => \sprintf(__('Ihr zuverlässiger Partner im Bereich %s', 'cmx-misbuero'), $label),
				'subtitle' => __('Persönlich, strukturiert und lösungsorientiert.', 'cmx-misbuero'),
				'text' => __('Wir verbinden Erfahrung mit klarer Kommunikation und begleiten Kunden mit einem hohen Qualitätsanspruch.', 'cmx-misbuero'),
				'image_id' => 0,
			],
			'advantages' => [
				'kicker' => __('Warum wir?', 'cmx-misbuero'),
				'title' => __('Darum entscheiden sich Kunden für uns', 'cmx-misbuero'),
				'items' => [
					['icon' => 'diamond', 'title' => __('Erfahrung', 'cmx-misbuero'), 'text' => __('Fundierte Expertise in unserem Fachgebiet.', 'cmx-misbuero')],
					['icon' => 'user', 'title' => __('Persönlich', 'cmx-misbuero'), 'text' => __('Direkte Ansprechpartner und kurze Wege.', 'cmx-misbuero')],
					['icon' => 'shield', 'title' => __('Zuverlässig', 'cmx-misbuero'), 'text' => __('Wir halten, was wir versprechen.', 'cmx-misbuero')],
					['icon' => 'chart', 'title' => __('Erfolg', 'cmx-misbuero'), 'text' => __('Ihr Erfolg ist unser gemeinsames Ziel.', 'cmx-misbuero')],
				],
			],
			'faq' => [
				'enabled' => '1',
				'kicker' => __('FAQ', 'cmx-misbuero'),
				'title' => __('Häufige Fragen', 'cmx-misbuero'),
				'subtitle' => __('Antworten auf die wichtigsten Fragen vor dem ersten Kontakt.', 'cmx-misbuero'),
				'items' => [
					['question' => __('Wie läuft die Zusammenarbeit ab?', 'cmx-misbuero'), 'answer' => __('Nach einem kurzen Kennenlernen klären wir Ziele, Umfang und die nächsten Schritte.', 'cmx-misbuero')],
					['question' => __('Wie schnell erhalte ich eine Rückmeldung?', 'cmx-misbuero'), 'answer' => __('In der Regel melden wir uns zeitnah mit einer ersten Einschätzung.', 'cmx-misbuero')],
					['question' => __('Ist ein unverbindliches Erstgespräch möglich?', 'cmx-misbuero'), 'answer' => __('Ja, wir besprechen Ihr Anliegen gerne zuerst unverbindlich.', 'cmx-misbuero')],
					['question' => __('Kann die Leistung individuell angepasst werden?', 'cmx-misbuero'), 'answer' => __('Ja, die Umsetzung wird auf Ihre Anforderungen abgestimmt.', 'cmx-misbuero')],
				],
			],
			'contact' => [
				'enabled' => '1',
				'kicker' => __('Kontakt', 'cmx-misbuero'),
				'title' => __('Bereit für den nächsten Schritt?', 'cmx-misbuero'),
				'subtitle' => __('Lassen Sie uns gemeinsam Ihr Projekt starten.', 'cmx-misbuero'),
				'address' => '',
				'button_text' => __('Kontakt aufnehmen', 'cmx-misbuero'),
				'button_url' => 'mailto:' . (string) \get_option('admin_email', ''),
			],
		];
	}

	private static function privacy_url(): string {
		$page_id = (int) \get_option('wp_page_for_privacy_policy', 0);
		return $page_id > 0 ? (string) \get_permalink($page_id) : '';
	}
}
