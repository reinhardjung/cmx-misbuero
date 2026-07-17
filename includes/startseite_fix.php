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
add_filter('the_content', __NAMESPACE__ . '\\cmx_startseite_replace_telefon_shortcode', 4);
function cmx_startseite_replace_telefon_shortcode($content) {
	if (!\is_string($content) || (\strpos($content, '[cmx_telefon_link]') === false && \strpos($content, '[cmx_plesk_telefon_link]') === false)) {
		return $content;
	}
	if (!\is_user_logged_in()) {
		$content = \str_replace('[cmx_plesk_telefon_link]', '', $content);
	}
	if (!cmx_is_startseite_context()) {
		return $content;
	}

	$replacement = '';
	if (\is_user_logged_in()) {
		$replacement = '<td><br><br><br><a href="/telefonbuch/">Telefonbuch</a></td>';
	}

	return \str_replace(['[cmx_telefon_link]', '[cmx_plesk_telefon_link]'], $replacement, $content);
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

add_filter('the_content', __NAMESPACE__ . '\\cmx_startseite_style_guest_links', 6);
function cmx_startseite_style_guest_links($content) {
	if (!\is_string($content) || $content === '') {
		return $content;
	}
	if (!cmx_is_startseite_context() || \is_user_logged_in()) {
		return $content;
	}

	$labels = [
		'Katalog' => 'cmx-startseite-cta',
		'anmelden' => 'cmx-startseite-cta cmx-startseite-cta--primary',
	];

	if (\class_exists('\\DOMDocument')) {
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$loaded = @$dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="cmx-startseite-guest-links-root">' . $content . '</div>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		if ($loaded) {
			$xpath = new \DOMXPath($dom);
			foreach ($xpath->query('//a') ?: [] as $anchor) {
				if (!$anchor instanceof \DOMElement) {
					continue;
				}
				$text = \trim((string) $anchor->textContent);
				if (!isset($labels[$text])) {
					continue;
				}
				$existing = \trim((string) $anchor->getAttribute('class'));
				$new = \trim($existing . ' ' . $labels[$text]);
				$anchor->setAttribute('class', $new);
			}

			$root = $dom->getElementById('cmx-startseite-guest-links-root');
			if ($root instanceof \DOMElement) {
				$html = '';
				foreach ($root->childNodes as $child) {
					$html .= $dom->saveHTML($child);
				}
				return $html;
			}
		}
	}

	foreach ($labels as $label => $class_name) {
		$content = (string) \preg_replace(
			'~<a\b([^>]*)>\s*' . \preg_quote($label, '~') . '\s*</a>~iu',
			'<a$1 class="' . \esc_attr($class_name) . '">' . $label . '</a>',
			$content
		);
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
	echo '<style id="cmx-startseite-bg-fallback">body{background-image:url("' . $image_url . '") !important;background-size:40% !important;background-repeat:no-repeat !important;background-position:center center !important;background-attachment:fixed !important;}';
	if (!\is_user_logged_in()) {
		echo '.cmx-startseite-cta{display:inline-flex;align-items:center;justify-content:center;min-width:92px;padding:7px 11px;border-radius:10px;border:1px solid rgba(164,44,36,.18);background:rgba(255,255,255,.92);box-shadow:0 8px 18px rgba(76,23,18,.10);color:#7d231d !important;font-size:18px;font-weight:700;line-height:1.1;text-decoration:none !important;transition:transform .15s ease, box-shadow .15s ease, background .15s ease;}';
		echo '.cmx-startseite-cta:hover,.cmx-startseite-cta:focus{transform:translateY(-1px);background:#fff;box-shadow:0 10px 22px rgba(76,23,18,.16);outline:none;}';
		echo '.cmx-startseite-cta--primary{background:#a42c24;color:#fff !important;border-color:#a42c24;box-shadow:0 10px 22px rgba(164,44,36,.24);}';
		echo '.cmx-startseite-cta--primary:hover,.cmx-startseite-cta--primary:focus{background:#8f261f;color:#fff !important;}';
	}
	echo '</style>';
}
