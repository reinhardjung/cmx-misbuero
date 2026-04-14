<?php
namespace CLOUDMEISTER\CMX\Buero;

defined('ABSPATH') || exit;

if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ROWS_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ROWS_META', '_cmx_carent_schadenprotokoll_rows');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ORT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ORT_META', '_cmx_carent_schadenprotokoll_ort');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_DATUM_META', '_cmx_carent_schadenprotokoll_datum');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UHRZEIT_META', '_cmx_carent_schadenprotokoll_uhrzeit');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_BETEILIGTE_META', '_cmx_carent_schadenprotokoll_weitere_beteiligte');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_WEITERE_ANGABEN_META', '_cmx_carent_schadenprotokoll_weitere_angaben');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_UNFALLPROTOKOLL_META', '_cmx_carent_schadenprotokoll_unfallprotokoll');
}
if (!\defined(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META')) {
	\define(__NAMESPACE__ . '\\CMX_CARENT_SCHADENPROTOKOLL_ANERKENNUNG_META', '_cmx_carent_schadenprotokoll_anerkennung');
}

if (!\function_exists(__NAMESPACE__ . '\\cmx_carent_schaden_taxonomy')) {
	function cmx_carent_schaden_taxonomy(): string {
		if (\defined(__NAMESPACE__ . '\\TAX_CARENT_SCHADEN')) {
			return (string) \constant(__NAMESPACE__ . '\\TAX_CARENT_SCHADEN');
		}

		return \function_exists(__NAMESPACE__ . '\\cmx_tax_key')
			? (string) cmx_tax_key('carent', 'Schaden')
			: '';
	}
}
