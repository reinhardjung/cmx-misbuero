<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || exit;

/* ------------------------------------------------------------
 * INI → Placeholder
 * ------------------------------------------------------------ */
function cmx_ini_belege_defaults(): array {

	$out = [
		'mail_angebot'            => '',
		'mail_gutschrift'         => '',
		'mail_lieferschein'       => '',
		'mail_rechnung'           => '',
		'belegfuss_angebot'       => '',
		'belegfuss_gutschrift'    => '',
		'belegfuss_lieferschein'  => '',
		'belegfuss_rechnung'      => '',
	];

	$file = dirname(__DIR__,2).'/includes/globals.ini';

	if (!file_exists($file)) return $out;

	$ini = parse_ini_file($file,true,INI_SCANNER_RAW);

	foreach ($out as $key => $empty) {

		$section = str_starts_with($key,'mail_') ? 'E-Mails' : 'Belegfuss';
		$name    = ucfirst(str_replace(['mail_','belegfuss_'], '', $key));

		if (!empty($ini[$section][$name])) {
			$out[$key] = str_replace(['<br>','<br />'], "\n", $ini[$section][$name]);
		}
	}

	return $out;
}

/* ------------------------------------------------------------
 * FELD-AUSGABE – TEXTAREA
 * ------------------------------------------------------------ */
function cmx_field_textarea_beleg(array $args): void {

	$key  = $args['key'];
	$rows = $args['rows'] ?? 5;
	$ph   = $args['placeholder'] ?? '';

	$opts = get_option('cmx_belege', []);
	$value = $opts[$key] ?? '';

	$display = ($value === '') ? $ph : $value;

	echo '<textarea
		name="cmx_belege['.$key.']"
		rows="'.$rows.'"
		style="width:100%;">'.esc_textarea($display).'</textarea>';
}

/* ------------------------------------------------------------
 * REGISTER BELEGE-FELDER
 * ------------------------------------------------------------ */
add_action('admin_init', function() {

	$ph = cmx_ini_belege_defaults();

	$add = function($page,$section,$label,$key,$rows) use ($ph) {

		add_settings_field(
			$key,
			$label,
			__NAMESPACE__.'\\cmx_field_textarea_beleg',
			$page,
			$section,
			[
				'key' => $key,
				'rows' => $rows,
				'placeholder' => $ph[$key],
			]
		);
	};

	/* BELEG TABS */
	$tabs = [
		'angebot' => 'Angebot',
		'gutschrift' => 'Gutschrift',
		'lieferschein' => 'Lieferschein',
		'rechnung' => 'Rechnung'
	];

	foreach ($tabs as $sub => $label) {

		$page = "cmx_tab_belege__{$sub}";
		add_settings_section("sec_{$sub}", $label, '__return_false', $page);

		$add($page,"sec_{$sub}",'Belegfuss',"belegfuss_{$sub}",4);
		$add($page,"sec_{$sub}",'E-Mail Text',"mail_{$sub}",8);
	}
});
