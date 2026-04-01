<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


if (!class_exists(__NAMESPACE__ . '\\CMX_Spams')) {

	class CMX_Spams {

		/**
		 * Prüft eine E-Mail anhand von Headern und Inhalt.
		 *
		 * Erwartete Struktur von $email:
		 * [
		 *     'subject' => '',
		 *     'from' => '',
		 *     'headers_raw' => '',
		 *     'body_text' => '',
		 *     'body_html' => '',
		 * ]
		 *
		 * @param array $email
		 * @return array
		 */
		public static function analyze($email) {
			$email = wp_parse_args(
				is_array($email) ? $email : array(),
				array(
					'subject'     => '',
					'from'        => '',
					'headers_raw' => '',
					'body_text'   => '',
					'body_html'   => '',
				)
			);

			$subject     = self::normalize_text($email['subject']);
			$from        = trim((string) $email['from']);
			$headers_raw = (string) $email['headers_raw'];
			$body_text   = self::normalize_text($email['body_text']);
			$body_html   = (string) $email['body_html'];
			$body_all    = trim($body_text . "\n" . wp_strip_all_tags($body_html));

			$score   = 0;
			$reasons = array();

			$from_email  = self::extract_email_address($from);
			$from_domain = self::extract_domain_from_email($from_email);

			$reply_to = self::extract_header_value($headers_raw, 'Reply-To');
			$reply_to_email = self::extract_email_address($reply_to);
			$reply_to_domain = self::extract_domain_from_email($reply_to_email);

			$return_path = self::extract_header_value($headers_raw, 'Return-Path');
			$return_path_email = self::extract_email_address($return_path);
			$return_path_domain = self::extract_domain_from_email($return_path_email);

			$spf_result   = self::extract_auth_result($headers_raw, 'spf');
			$dkim_result  = self::extract_auth_result($headers_raw, 'dkim');
			$dmarc_result = self::extract_auth_result($headers_raw, 'dmarc');

			$link_domains = self::extract_domains_from_links($body_html . "\n" . $body_text);

			$suspicious_keywords = self::get_suspicious_keywords();
			$brand_keywords      = self::get_sensitive_brand_keywords();
			$official_domains    = self::get_official_domains();

			/*
			 * 1) Grundsätzliche technische Prüfungen
			 */
			if (empty($from_domain)) {
				$score += 35;
				$reasons[] = 'Keine klare Absender-Domain gefunden.';
			}

			if (!empty($reply_to_domain) && !empty($from_domain) && $reply_to_domain !== $from_domain) {
				$score += 20;
				$reasons[] = 'Reply-To-Domain weicht von der Absender-Domain ab.';
			}

			if (!empty($return_path_domain) && !empty($from_domain) && $return_path_domain !== $from_domain) {
				$score += 15;
				$reasons[] = 'Return-Path-Domain weicht von der Absender-Domain ab.';
			}

			if ('fail' === $spf_result) {
				$score += 35;
				$reasons[] = 'SPF-Prüfung fehlgeschlagen.';
			} elseif ('softfail' === $spf_result) {
				$score += 20;
				$reasons[] = 'SPF ist nur softfail.';
			} elseif ('none' === $spf_result) {
				$score += 10;
				$reasons[] = 'SPF fehlt.';
			}

			if ('fail' === $dkim_result) {
				$score += 30;
				$reasons[] = 'DKIM-Prüfung fehlgeschlagen.';
			} elseif ('none' === $dkim_result) {
				$score += 10;
				$reasons[] = 'DKIM fehlt.';
			}

			if ('fail' === $dmarc_result) {
				$score += 35;
				$reasons[] = 'DMARC-Prüfung fehlgeschlagen.';
			} elseif ('none' === $dmarc_result) {
				$score += 20;
				$reasons[] = 'DMARC fehlt.';
			}

			/*
			 * 2) Link-Prüfungen
			 */
			if (!empty($link_domains)) {
				foreach ($link_domains as $link_domain) {
					if (!empty($from_domain) && !self::domains_match($from_domain, $link_domain)) {
						$score += 15;
						$reasons[] = 'Link-Domain passt nicht zur Absender-Domain: ' . $link_domain;
					}

					if (self::is_generic_hosting_domain($link_domain)) {
						$score += 20;
						$reasons[] = 'Link zeigt auf generischen Hosting-/Storage-Dienst: ' . $link_domain;
					}
				}
			}

			/*
			 * 3) Inhaltliche Phishing-Muster
			 */
			$keyword_hits = self::count_keyword_hits($subject . "\n" . $body_all, $suspicious_keywords);

			if ($keyword_hits >= 1) {
				$score += min(30, $keyword_hits * 8);
				$reasons[] = 'Verdächtige Formulierungen erkannt (' . absint($keyword_hits) . ').';
			}

			$has_urgent_pattern = self::has_urgency_pattern($subject . "\n" . $body_all);
			if ($has_urgent_pattern) {
				$score += 15;
				$reasons[] = 'Dringlichkeits- oder Druckmuster erkannt.';
			}

			/*
			 * 4) Missbrauch von bekannten Marken / Behörden
			 */
			$detected_brands = self::detect_brand_keywords($subject . "\n" . $body_all, $brand_keywords);

			if (!empty($detected_brands) && !empty($from_domain)) {
				foreach ($detected_brands as $brand => $allowed_domains) {
					if (!self::domain_in_list($from_domain, $allowed_domains)) {
						$score += 30;
						$reasons[] = 'Marken-/Behördenbezug "' . $brand . '" passt nicht zur Absender-Domain.';
					}

					if (!empty($link_domains)) {
						foreach ($link_domains as $link_domain) {
							if (!self::domain_in_list($link_domain, $allowed_domains)) {
								$score += 20;
								$reasons[] = 'Marken-/Behördenbezug "' . $brand . '" passt nicht zur Link-Domain ' . $link_domain . '.';
							}
						}
					}
				}
			}

			/*
			 * 5) Zusätzliche Heuristik für "offizielle" Begriffe
			 */
			if (self::contains_official_claims($subject . "\n" . $body_all)) {
				if (!empty($from_domain) && !self::domain_in_list($from_domain, $official_domains)) {
					$score += 25;
					$reasons[] = 'Mail behauptet offiziellen/behördlichen Charakter, Domain ist aber nicht offiziell.';
				}
			}

			/*
			 * Score begrenzen
			 */
			$score = max(0, min(100, (int) $score));

			$status = 'clean';

			if ($score >= 70) {
				$status = 'spam';
			} elseif ($score >= 40) {
				$status = 'suspicious';
			}

			return array(
				'status'          => $status,
				'score'           => $score,
				'reasons'         => array_values(array_unique($reasons)),
				'from_email'      => $from_email,
				'from_domain'     => $from_domain,
				'reply_to_domain' => $reply_to_domain,
				'return_path'     => $return_path_email,
				'link_domains'    => array_values(array_unique($link_domains)),
				'auth'            => array(
					'spf'   => $spf_result,
					'dkim'  => $dkim_result,
					'dmarc' => $dmarc_result,
				),
			);
		}

		/**
		 * Schnellprüfung: Ist die Mail Spam?
		 *
		 * @param array $email
		 * @return bool
		 */
		public static function is_spam($email) {
			$result = self::analyze($email);
			return ('spam' === $result['status']);
		}

		/**
		 * Schnellprüfung: Ist die Mail verdächtig?
		 *
		 * @param array $email
		 * @return bool
		 */
		public static function is_suspicious($email) {
			$result = self::analyze($email);
			return in_array($result['status'], array('suspicious', 'spam'), true);
		}

		/**
		 * Optional: Prüft direkt rohe Header + Bodies.
		 *
		 * @param string $headers_raw
		 * @param string $body_text
		 * @param string $body_html
		 * @param string $subject
		 * @param string $from
		 * @return array
		 */
		public static function analyze_raw($headers_raw = '', $body_text = '', $body_html = '', $subject = '', $from = '') {
			return self::analyze(
				array(
					'subject'     => $subject,
					'from'        => $from,
					'headers_raw' => $headers_raw,
					'body_text'   => $body_text,
					'body_html'   => $body_html,
				)
			);
		}

		/**
		 * Extrahiert einen Header-Wert aus dem Raw-Header-Block.
		 *
		 * @param string $headers_raw
		 * @param string $header_name
		 * @return string
		 */
		protected static function extract_header_value($headers_raw, $header_name) {
			$headers_raw = (string) $headers_raw;
			$header_name = preg_quote((string) $header_name, '/');

			if (preg_match('/^' . $header_name . ':\s*(.+)$/mi', $headers_raw, $matches)) {
				return trim(self::unfold_header_lines($matches[1]));
			}

			return '';
		}

		/**
		 * Extrahiert SPF/DKIM/DMARC Resultat aus Authentication-Results.
		 *
		 * @param string $headers_raw
		 * @param string $type
		 * @return string
		 */
		protected static function extract_auth_result($headers_raw, $type) {
			$headers_raw = (string) $headers_raw;
			$type        = strtolower((string) $type);

			if (preg_match('/' . preg_quote($type, '/') . '\s*=\s*([a-z]+)/i', $headers_raw, $matches)) {
				return strtolower(trim($matches[1]));
			}

			return 'unknown';
		}

		/**
		 * Holt Mailadresse aus einem Feld wie:
		 * "Name" <mail@example.com>
		 *
		 * @param string $value
		 * @return string
		 */
		protected static function extract_email_address($value) {
			$value = trim((string) $value);

			if (preg_match('/<([^>]+)>/', $value, $matches)) {
				return sanitize_email(trim($matches[1]));
			}

			if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
				return sanitize_email(trim($matches[0]));
			}

			return '';
		}

		/**
		 * Extrahiert Domain aus E-Mail-Adresse.
		 *
		 * @param string $email
		 * @return string
		 */
		protected static function extract_domain_from_email($email) {
			$email = sanitize_email((string) $email);

			if (empty($email) || false === strpos($email, '@')) {
				return '';
			}

			$parts = explode('@', strtolower($email));
			return isset($parts[1]) ? trim($parts[1]) : '';
		}

		/**
		 * Holt Domains aus Links in Text oder HTML.
		 *
		 * @param string $content
		 * @return array
		 */
		protected static function extract_domains_from_links($content) {
			$content = (string) $content;
			$domains = array();

			if (preg_match_all('/https?:\/\/([^\s\/"\'>]+)/i', $content, $matches)) {
				foreach ($matches[1] as $host) {
					$host = strtolower(trim($host));
					$host = preg_replace('/:\d+$/', '', $host);

					if (!empty($host)) {
						$domains[] = $host;
					}
				}
			}

			return array_values(array_unique($domains));
		}

		/**
		 * Prüft, ob zwei Domains zusammengehören.
		 *
		 * @param string $domain_a
		 * @param string $domain_b
		 * @return bool
		 */
		protected static function domains_match($domain_a, $domain_b) {
			$domain_a = strtolower(trim((string) $domain_a));
			$domain_b = strtolower(trim((string) $domain_b));

			if (empty($domain_a) || empty($domain_b)) {
				return false;
			}

			if ($domain_a === $domain_b) {
				return true;
			}

			if (self::ends_with($domain_a, '.' . $domain_b) || self::ends_with($domain_b, '.' . $domain_a)) {
				return true;
			}

			return false;
		}

		/**
		 * Prüft, ob Domain in erlaubter Liste enthalten ist.
		 *
		 * @param string $domain
		 * @param array  $allowed_domains
		 * @return bool
		 */
		protected static function domain_in_list($domain, $allowed_domains) {
			$domain = strtolower(trim((string) $domain));

			if (empty($domain) || empty($allowed_domains) || !is_array($allowed_domains)) {
				return false;
			}

			foreach ($allowed_domains as $allowed_domain) {
				$allowed_domain = strtolower(trim((string) $allowed_domain));

				if (self::domains_match($domain, $allowed_domain)) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Verdächtige Hosting-Domains.
		 *
		 * @param string $domain
		 * @return bool
		 */
		protected static function is_generic_hosting_domain($domain) {
			$domain = strtolower(trim((string) $domain));

			$patterns = array(
				'amazonaws.com',
				's3.amazonaws.com',
				'dualstack.',
				'storage.googleapis.com',
				'blob.core.windows.net',
				'github.io',
				'pages.dev',
				'netlify.app',
				'firebaseapp.com',
			);

			foreach ($patterns as $pattern) {
				if (false !== strpos($domain, $pattern)) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Zählt Keyword-Treffer.
		 *
		 * @param string $text
		 * @param array  $keywords
		 * @return int
		 */
		protected static function count_keyword_hits($text, $keywords) {
			$text = self::normalize_text($text);
			$hits = 0;

			if (empty($text) || empty($keywords)) {
				return 0;
			}

			foreach ($keywords as $keyword) {
				$keyword = self::normalize_text($keyword);

				if (!empty($keyword) && false !== mb_stripos($text, $keyword)) {
					$hits++;
				}
			}

			return $hits;
		}

		/**
		 * Erkennt Marken-/Behördenbezüge.
		 *
		 * @param string $text
		 * @param array  $brand_keywords
		 * @return array
		 */
		protected static function detect_brand_keywords($text, $brand_keywords) {
			$text     = self::normalize_text($text);
			$detected = array();

			if (empty($text) || empty($brand_keywords) || !is_array($brand_keywords)) {
				return $detected;
			}

			foreach ($brand_keywords as $brand => $data) {
				if (empty($data['keywords']) || empty($data['domains'])) {
					continue;
				}

				foreach ($data['keywords'] as $keyword) {
					$keyword = self::normalize_text($keyword);

					if (!empty($keyword) && false !== mb_stripos($text, $keyword)) {
						$detected[$brand] = $data['domains'];
						break;
					}
				}
			}

			return $detected;
		}

		/**
		 * Prüft typische Druck-/Panikmuster.
		 *
		 * @param string $text
		 * @return bool
		 */
		protected static function has_urgency_pattern($text) {
			$text = self::normalize_text($text);

			$patterns = array(
				'/dringend/i',
				'/sofort/i',
				'/umgehend/i',
				'/innerhalb\s+von\s+\d+\s*(stunden|tagen)/i',
				'/konto.*gesperrt/i',
				'/wurde.*deaktiviert/i',
				'/zahlung.*aktualisieren/i',
				'/best[aä]tigen\s*sie/i',
				'/verifizieren\s*sie/i',
				'/letzte\s*warnung/i',
			);

			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $text)) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Prüft, ob die Mail offiziellen Charakter vortäuscht.
		 *
		 * @param string $text
		 * @return bool
		 */
		protected static function contains_official_claims($text) {
			$text = self::normalize_text($text);

			$claims = array(
				'bundesamt',
				'regierung',
				'steueramt',
				'zoll',
				'verwaltung',
				'amtlich',
				'offiziell',
				'polizei',
				'gericht',
			);

			foreach ($claims as $claim) {
				if (false !== mb_stripos($text, $claim)) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Verdächtige Standard-Begriffe.
		 *
		 * @return array
		 */
		protected static function get_suspicious_keywords() {
			return array(
				'zahlung aktualisieren',
				'konto gesperrt',
				'deaktiviert',
				'bestätigen sie',
				'verifizieren sie',
				'dringend',
				'umgehend',
				'letzte warnung',
				'handlungsbedarf',
				'sicherheitsüberprüfung',
				'abrechnungsproblem',
				'passwort abgelaufen',
				'jetzt bestätigen',
				'jetzt aktualisieren',
				'zahlung fehlgeschlagen',
				'zugang eingeschränkt',
				'e-vignette',
			);
		}

		/**
		 * Marken-/Behörden-Keywords und erlaubte Domains.
		 *
		 * Diese Liste kannst Du später in Deinen Einstellungen dynamisch machen.
		 *
		 * @return array
		 */
		protected static function get_sensitive_brand_keywords() {
			return array(
				'Swiss Government' => array(
					'keywords' => array(
						'bazg',
						'bundesamt für zoll',
						'e-vignette',
						'e vignette',
						'admin.ch',
						'via portal',
					),
					'domains' => array(
						'admin.ch',
						'via.admin.ch',
						'evz.admin.ch',
					),
				),
				'Swiss Post' => array(
					'keywords' => array(
						'die post',
						'post.ch',
						'swiss post',
						'postfinance',
					),
					'domains' => array(
						'post.ch',
						'postfinance.ch',
					),
				),
				'Twint' => array(
					'keywords' => array(
						'twint',
					),
					'domains' => array(
						'twint.ch',
					),
				),
				'PayPal' => array(
					'keywords' => array(
						'paypal',
					),
					'domains' => array(
						'paypal.com',
						'paypalobjects.com',
					),
				),
				'Microsoft' => array(
					'keywords' => array(
						'microsoft',
						'office 365',
						'outlook',
						'onedrive',
					),
					'domains' => array(
						'microsoft.com',
						'outlook.com',
						'office.com',
						'microsoftonline.com',
						'live.com',
					),
				),
			);
		}

		/**
		 * Offizielle Domains für Behörden-/amtliche Claims.
		 *
		 * @return array
		 */
		protected static function get_official_domains() {
			return array(
				'admin.ch',
				'gov.ch',
				'polizei.ch',
				'zh.ch',
				'zg.ch',
				'be.ch',
				'bs.ch',
				'lu.ch',
				'ag.ch',
				'stadt-zuerich.ch',
			);
		}

		/**
		 * Zeilenumbrüche in mehrzeiligen Headern glätten.
		 *
		 * @param string $value
		 * @return string
		 */
		protected static function unfold_header_lines($value) {
			$value = (string) $value;
			return preg_replace("/\r?\n[ \t]+/", ' ', $value);
		}

		/**
		 * Normalisiert Text.
		 *
		 * @param string $text
		 * @return string
		 */
		protected static function normalize_text($text) {
			$text = (string) $text;
			$text = wp_strip_all_tags($text);
			$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
			$text = preg_replace('/\s+/u', ' ', $text);
			return trim($text);
		}

		/**
		 * Einfaches ends_with ohne PHP-8-only Helfer.
		 *
		 * @param string $haystack
		 * @param string $needle
		 * @return bool
		 */
		protected static function ends_with($haystack, $needle) {
			$haystack = (string) $haystack;
			$needle   = (string) $needle;

			if ('' === $needle) {
				return true;
			}

			return substr($haystack, -strlen($needle)) === $needle;
		}
	}
}
