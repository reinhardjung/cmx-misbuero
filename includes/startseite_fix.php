<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || exit;

/**
 * Liefert true, wenn die aktuelle Ausgabe die Frontpage "startseite" betrifft.
 */
function cmx_is_startseite_context(): bool {
	if (\is_admin()) {
		return false;
	}
	if (\is_front_page()) {
		return true;
	}
	if (!\is_singular('page')) {
		return false;
	}

	$post = \get_queried_object();
	if (!$post instanceof \WP_Post) {
		return false;
	}

	return (string) ($post->post_name ?? '') === 'startseite';
}

/**
 * Fallback: ersetze den Platzhalter robust, auch wenn der Shortcode nicht registriert ist.
 */
add_filter('the_content', __NAMESPACE__ . '\\cmx_startseite_replace_plesk_shortcode', 4);
function cmx_startseite_replace_plesk_shortcode($content) {
	if (!\is_string($content) || \strpos($content, '[cmx_plesk_telefon_link]') === false) {
		return $content;
	}
	if (!cmx_is_startseite_context()) {
		return $content;
	}

	$replacement = '';
	if (\is_user_logged_in()) {
		$replacement = '<td><br><br><br><a href="/telefonbuch/">Telefonbuch</a></td>';
	}

	return \str_replace('[cmx_plesk_telefon_link]', $replacement, $content);
}

add_filter('the_content', __NAMESPACE__ . '\\cmx_startseite_hide_logged_in_links', 5);
function cmx_startseite_hide_logged_in_links($content) {
	if (!\is_string($content) || $content === '') {
		return $content;
	}
	if (!cmx_is_startseite_context() || !\is_user_logged_in()) {
		return $content;
	}

	$labels = ['Katalog', 'Telefonbuch', 'anmelden'];

	if (\class_exists('\\DOMDocument')) {
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$loaded = @$dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="cmx-startseite-links-root">' . $content . '</div>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		if ($loaded) {
			$xpath = new \DOMXPath($dom);
			foreach ($xpath->query('//a') ?: [] as $anchor) {
				if (!$anchor instanceof \DOMElement) {
					continue;
				}
				$text = \trim((string) $anchor->textContent);
				if (!\in_array($text, $labels, true)) {
					continue;
				}
				$parent = $anchor->parentNode;
				$anchor->parentNode?->removeChild($anchor);
				if ($parent instanceof \DOMElement) {
					$parent_text = \trim((string) $parent->textContent);
					if ($parent_text === '' && \in_array(\strtolower($parent->tagName), ['td', 'div', 'p', 'span'], true)) {
						$parent->parentNode?->removeChild($parent);
					}
				}
			}

			$root = $dom->getElementById('cmx-startseite-links-root');
			if ($root instanceof \DOMElement) {
				$html = '';
				foreach ($root->childNodes as $child) {
					$html .= $dom->saveHTML($child);
				}
				return $html;
			}
		}
	}

	foreach ($labels as $label) {
		$content = (string) \preg_replace('~<a\b[^>]*>\s*' . \preg_quote($label, '~') . '\s*</a>~iu', '', $content);
	}

	return $content;
}

/**
 * Fallback fuer fehlendes Hintergrundbild/Logo auf der Startseite.
 */
add_action('wp_head', __NAMESPACE__ . '\\cmx_startseite_background_fallback', 99);
function cmx_startseite_background_fallback(): void {
	if (!cmx_is_startseite_context()) {
		return;
	}

	$image_url = \esc_url(CMX_PLUGIN_URL . 'assets/favicon.png');
	echo '<style id="cmx-startseite-bg-fallback">body{background-image:url("' . $image_url . '") !important;background-size:40% !important;background-repeat:no-repeat !important;background-position:center center !important;background-attachment:fixed !important;}</style>';
}
