<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

use Sabre\DAV as DAV;
use Sabre\HTTP as HTTP;


/**
 * Kleine Helper für HTML & Darstellung
 */
function cmx_dav_h($str) {
	return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function cmx_dav_join_uri(...$parts) {
	$uri = preg_replace('#/+#','/', implode('/', array_map(fn($p)=>trim((string)$p, '/'), $parts)));
	return '/' . ltrim($uri, '/');
}
function cmx_dav_human_bytes($bytes) {
	$bytes = (int)$bytes;
	if ($bytes < 1024) return $bytes . ' B';
	$units = ['KB','MB','GB','TB','PB'];
	$pow = min(count($units), (int)floor(log($bytes, 1024)));
	return number_format($bytes / (1024 ** $pow), 2, ',', "'") . ' ' . $units[$pow-1];
}

function cmx_dav_fmt_time($ts) {
	if (!$ts) return '';
	$dt = (new \DateTime('@'.$ts))->setTimezone(new \DateTimeZone('Europe/Zurich'));
	return $dt->format('d.m.Y H:i');
}

/** Sicherheits-Helper für Pfade */
function cmx_dav_is_subpath(string $base, string $path): bool {
	$base = rtrim(str_replace('\\','/',$base), '/') . '/';
	$path = rtrim(str_replace('\\','/',$path), '/') . '/';
	return str_starts_with($path, $base);
}
/** Absolute URL aus Pfad erzeugen */
function cmx_dav_abs_url(string $path): string {
	$scheme = (function_exists('\\is_ssl') && \is_ssl()) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	return $scheme . '://' . $host . $path;
}
/** Zip-Helfer (rekursiv) */
function cmx_dav_zip_dir(string $source, \ZipArchive $zip, string $base): void {
	$source = rtrim($source, DIRECTORY_SEPARATOR);
	$base   = rtrim($base, DIRECTORY_SEPARATOR);

	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
		\RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $item) {
		$absPath = $item->getPathname();
		$relPath = ltrim(str_replace($base, '', $absPath), DIRECTORY_SEPARATOR);
		$relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);

		if ($item->isDir()) {
			$zip->addEmptyDir($relPath);
		} else {
			$zip->addFile($absPath, $relPath);
		}
	}
}

add_action('init', function () {
	$req  = $_SERVER['REQUEST_URI'] ?? '';
	$path = parse_url($req, PHP_URL_PATH) ?? '/';
	if (!preg_match('#^/archiv(?:/|$)#', $path)) return;

	require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';

	$sharePath = WP_CONTENT_DIR . '/uploads/misbuero';
	$root      = new DAV\FS\Directory($sharePath);
	$server    = new DAV\Server($root);
	$server->setBaseUri('/archiv'); // bewusst ohne Slash am Ende

	// BasicAuth mit WP-Usern
	$authBackend = new DAV\Auth\Backend\BasicCallBack(function($u,$p){
		$user = \get_user_by('login', $u);
		return $user && \wp_check_password($p, $user->user_pass, $user->ID);
	});
	$server->addPlugin(new DAV\Auth\Plugin($authBackend, 'WP WebDAV'));

	// Read-only erzwingen
	$server->on('beforeMethod', function(HTTP\RequestInterface $r){
		$blocked = ['PUT','POST','MKCOL','DELETE','MOVE','COPY','PROPPATCH','LOCK','UNLOCK','PATCH'];
		if (in_array($r->getMethod(), $blocked, true)) {
			throw new DAV\Exception\MethodNotAllowed('Read-only');
		}
	});

	// PDFs/Images inline anzeigen statt Download
	$server->on('afterMethod:GET', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($sharePath) {
		$path = $request->getPath();
		$q = [];
		parse_str((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $q);
		if (!empty($q['zip'])) return;

		$relPath = ltrim($path, '/');
		$absPath = rtrim($sharePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
		$real = realpath($absPath);
		if (!$real || !is_file($real) || !cmx_dav_is_subpath($sharePath, $real)) {
			return;
		}

		$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
		$mime_map = [
			'pdf'  => 'application/pdf',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
		];
		if (!isset($mime_map[$ext])) return;

		$filename = basename($real);
		$response->setHeader('Content-Type', $mime_map[$ext]);
		$response->setHeader('Content-Disposition', 'inline; filename="'.cmx_dav_h($filename).'"');
	});

	// Hübsche HTML-Indexseite für Collections + ZIP-Download
	$server->on('method:GET', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($server, $sharePath) {
		$relPath = trim($request->getPath(), '/'); // relativ zur BaseUri
		$tree    = $server->tree;

		$exists  = $tree->nodeExists($request->getPath());
		$node    = $exists ? $tree->getNodeForPath($request->getPath()) : null;

		// Wenn Ordner und ?zip=1: ZIP ausliefern
		if ($node instanceof DAV\ICollection) {
			$q = [];
			parse_str((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $q);

			if (!empty($q['zip'])) {
				$absDir = rtrim($sharePath, DIRECTORY_SEPARATOR) . (empty($relPath) ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
				$absDirReal = realpath($absDir);

				if (!$absDirReal || !is_dir($absDirReal) || !cmx_dav_is_subpath($sharePath, $absDirReal)) {
					$response->setStatus(400);
					$response->setBody('Ungültiger Pfad.');
					return false;
				}

				// Temp-ZIP
				$tmpZip = (function_exists('\\wp_tempnam') ? \wp_tempnam('misbuerozip_') : \tempnam(\sys_get_temp_dir(), 'misbuerozip_'));
				if (!$tmpZip) {
					$response->setStatus(500);
					$response->setBody('Temporäre Datei konnte nicht erstellt werden.');
					return false;
				}

				$zip = new \ZipArchive();
				if (true !== $zip->open($tmpZip, \ZipArchive::OVERWRITE)) {
					$response->setStatus(500);
					$response->setBody('ZIP konnte nicht erstellt werden.');
					return false;
				}

				/**
				 * NEU (B): Nur ausgewählte Dateien zippen, wenn sel[]= übergeben wurde.
				 * - Erwartet Dateinamen der aktuellen Ebene (keine Pfade).
				 * - Fällt zurück auf kompletten Ordner (rekursiv), wenn keine Auswahl.
				 */
				$selected = [];
				if (!empty($q['sel'])) {
					$selected = is_array($q['sel']) ? $q['sel'] : [$q['sel']];
					// nur Basename, um Pfad-Tricks zu vermeiden
					$selected = array_values(array_filter(array_map('basename', array_map('strval', $selected))));
				}

				if ($selected) {
					foreach ($selected as $fn) {
						$full = $absDirReal . DIRECTORY_SEPARATOR . $fn;
						$real = realpath($full);
						// nur reguläre Dateien derselben Ebene zulassen
						if (!$real || !cmx_dav_is_subpath($absDirReal, $real) || !is_file($real)) {
							continue;
						}
						$zip->addFile($real, $fn);
					}
				} else {
					// wie bisher: kompletter Ordner rekursiv
					cmx_dav_zip_dir($absDirReal, $zip, rtrim($absDirReal, DIRECTORY_SEPARATOR));
				}

				$zip->close();

				$label    = ($relPath === '' ? 'root' : trim($relPath, '/'));
				$filename = $label . ($selected ? '-auswahl' : '') . '.zip';

				$response->setStatus(200);
				$response->setHeader('Content-Type', 'application/zip');
				$response->setHeader('Content-Disposition', 'attachment; filename="'.cmx_dav_h($filename).'"');
				$response->setHeader('Content-Length', (string)filesize($tmpZip));
				$response->setBody(function() use ($tmpZip) {
					$fh = fopen($tmpZip, 'rb');
					if ($fh) {
						while (!feof($fh)) {
							echo fread($fh, 8192);
						}
						fclose($fh);
					}
					@unlink($tmpZip);
				});
				return false;
			}
		}

		if ($node instanceof DAV\ICollection) {

			$childrenRaw = iterator_to_array($node->getChildren());
			$base        = rtrim($server->getBaseUri(), '/');

			// Sortier-Parameter einlesen
			$sort = isset($q['sort']) && in_array($q['sort'], ['name','size','mtime'], true) ? $q['sort'] : 'name';
			$dir  = isset($q['dir'])  && in_array(strtolower((string)$q['dir']), ['asc','desc'], true) ? strtolower((string)$q['dir']) : 'asc';
			$sortParams = ($sort !== 'name' || $dir !== 'asc') ? ['sort'=>$sort, 'dir'=>$dir] : [];
			$sortQuery  = $sortParams ? '?' . http_build_query($sortParams) : '';

			// Children inkl. Metadaten aufbereiten
			$children = [];
			foreach ($childrenRaw as $child) {
				$isDir = $child instanceof DAV\ICollection;
				$sizeBytes = null;
				$mtimeTs   = null;

				if (!$isDir && method_exists($child, 'getSize')) {
					try { $sizeBytes = (int) $child->getSize(); } catch (\Throwable $e) { $sizeBytes = null; }
				}
				if (method_exists($child, 'getLastModified')) {
					try { $mtimeTs = (int) $child->getLastModified(); } catch (\Throwable $e) { $mtimeTs = null; }
				}

				$children[] = [
					'node'      => $child,
					'isDir'     => $isDir,
					'name'      => $child->getName(),
					'sizeBytes' => $sizeBytes,
					'mtimeTs'   => $mtimeTs,
				];
			}

			usort($children, function($a, $b) use ($sort, $dir) {
				// Ordner immer vor Dateien
				if ($a['isDir'] !== $b['isDir']) return $a['isDir'] ? -1 : 1;

				$mult = ($dir === 'desc') ? -1 : 1;

				if ($sort === 'size') {
					$sa = $a['sizeBytes'] ?? -1;
					$sb = $b['sizeBytes'] ?? -1;
					if ($sa !== $sb) return ($sa <=> $sb) * $mult;
				}
				elseif ($sort === 'mtime') {
					$ma = $a['mtimeTs'] ?? -1;
					$mb = $b['mtimeTs'] ?? -1;
					if ($ma !== $mb) return ($ma <=> $mb) * $mult;
				}

				// Fallback: Name
				return strnatcasecmp($a['name'], $b['name']) * $mult;
			});

			// Breadcrumbs
			$segments = array_values(array_filter(explode('/', $relPath), fn($s)=>$s!==''));
			$crumbs   = [];
			$accum    = '';
			$crumbs[] = '<a href="'.cmx_dav_h($base).'">/</a>';
			foreach ($segments as $seg) {
				$accum = trim($accum.'/'.$seg, '/');
				$crumbs[] = '<span class="sep">›</span><a href="'.cmx_dav_h(cmx_dav_join_uri($base, $accum)).'/'.cmx_dav_h($sortQuery).'">'.cmx_dav_h($seg).'</a>';
			}

			// Aktuelle Ordner-URL (ohne ?zip)
			$currentDirUrl = cmx_dav_join_uri($base, $relPath) . '/';

			/**
			 * NEU (B): ZIP-Button als Formular-Submit,
			 * damit die markierten Dateien (sel[]) übertragen werden.
			 */
			$zipButton = '<button type="submit" form="zipform" class="zipbtn" title="Auswahl als ZIP herunterladen" aria-label="Auswahl als ZIP herunterladen">'
				. '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 3v10m0 0 4-4m-4 4-4-4M5 21h14a2 2 0 0 0 2-2v0a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v0a2 2 0 0 0 2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
				. '<span></span></button>';

			// Parent-Link (..), sofern nicht Root
			$rows = '';
			if (!empty($segments)) {
				$parent = array_slice($segments, 0, -1);
				$parentHref = cmx_dav_h(cmx_dav_join_uri($base, implode('/', $parent))).'/' . cmx_dav_h($sortQuery);
				$rows .= '<tr class="up"><td class="sel"></td><td class="name"><a href="'.$parentHref.'">..</a></td><td class="type">Ordner</td><td class="size"></td><td class="mtime"></td><td class="action"></td></tr>';
			}

			// Anzahl der Dateien (nur aktuelle Ebene, Ordner ausgeschlossen)
			$fileCount = count(array_filter($children, fn($c) => !($c['isDir'])));

			// Helper: Sort-Links aufbauen
			$sortLink = function(string $field) use ($base, $relPath, $sort, $dir): string {
				$nextDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
				$params  = http_build_query(['sort'=>$field, 'dir'=>$nextDir]);
				return cmx_dav_h(cmx_dav_join_uri($base, $relPath).'/'.'?'.$params);
			};
			$arrow = function(string $field) use ($sort, $dir): string {
				if ($sort !== $field) return '';
				return $dir === 'asc' ? ' ▲' : ' ▼';
			};

			// Tabellenzeilen aufbauen (mit Checkboxen für Dateien)
			foreach ($children as $item) {
				/** @var DAV\INode $child */
				$child = $item['node'];
				$name  = $item['name'];
				$isDir = $item['isDir'];
				$href  = cmx_dav_join_uri($base, $relPath, rawurlencode($name)) . ($isDir ? '/' : '') . (!$isDir && $sortQuery ? '' : $sortQuery);
				$absHref = cmx_dav_abs_url($href);

				// Metadaten
				$type  = $isDir ? 'Ordner' : 'Datei';
				$size  = '';
				$mtime = '';

				if ($item['mtimeTs']) $mtime = cmx_dav_fmt_time($item['mtimeTs']);
				if (!$isDir && $item['sizeBytes'] !== null) $size = cmx_dav_human_bytes($item['sizeBytes']);

			// Anzahl der Dateien (nur aktuelle Ebene, Ordner ausgeschlossen)
			$fileCount = count(array_filter($children, fn($c) => !($c['isDir'])));

			// Checkbox nur für Dateien (gleiche Ebene)
				$checkbox = !$isDir
					? '<input type="checkbox" class="cmx-sel" name="sel[]" value="'.cmx_dav_h($name).'" />'
					: '';

				// Copy-Pfad-Icon
				$copyBtn = !$isDir
				? '<button class="btn btn-copy" data-clip="'.cmx_dav_h($absHref).'" title="Pfad in Zwischenablage kopieren" aria-label="Pfad kopieren">'
					.'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
						<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
						<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
					</svg>'
				.'</button>'
				: '';

				$downloadBtn = !$isDir
					? '<a class="btn btn-secondary" href="'.cmx_dav_h($href).'" download>
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
							class="bi bi-download" viewBox="0 0 16 16" style="vertical-align:middle;margin-right:4px;">
						  <path d="M.5 9.9a.5.5 0 0 1 .5.5V14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10.4a.5.5 0 0 1 1 0V14a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V10.4a.5.5 0 0 1 .5-.5z"/>
						  <path d="M7.646 10.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 1 0-.708-.708L8.5 9.293V1.5a.5.5 0 0 0-1 0v7.793L5.354 7.146a.5.5 0 1 0-.708.708l3 3z"/>
						</svg>
					  </a>'
					: '';

				$link_target = $isDir ? '' : ' target="_blank" rel="noopener noreferrer"';
				$rows .= '<tr>'
					. '<td class="sel">'.($checkbox).'</td>'
					. '<td class="name"><a href="'.cmx_dav_h($href).'"'.$link_target.'>'.cmx_dav_h($name).($isDir?'/':'').'</a></td>'
					. '<td class="type">'.cmx_dav_h($type).'</td>'
					. '<td class="size">'.cmx_dav_h($size).'</td>'
					. '<td class="mtime">'.cmx_dav_h($mtime).'</td>'
					. '<td class="action">'.($isDir ? '' : $copyBtn.' '.$downloadBtn).'</td>'
					. '</tr>';
			}

			$title = 'Mis Büro – ' . (empty($segments) ? '/' : implode('/', $segments).'/');

			$html = '<!doctype html><html lang="de"><head><meta charset="utf-8">'
				.'<meta name="viewport" content="width=device-width,initial-scale=1">'
				.'<title>'.cmx_dav_h($title).'</title>'
				.'<style>
					/* === Mis Büro Style === */
					:root{
						--bg:#f7f7f8; --card:#ffffff; --muted:#667085; --text:#0f172a;
						--accent:#e53935; --accent-weak:#fee2e2; --ok:#16a34a; --border:#e5e7eb;
						--radius:16px; --shadow:0 8px 24px rgba(15,23,42,.06);
					}
					*{box-sizing:border-box}
					html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial}
					.wrap{max-width:1060px;margin:32px auto;padding:0 20px}
					h1{font-size:26px;font-weight:800;letter-spacing:-.01em;margin:0 0 10px 0}
					.toolbar{display:flex;align-items:center;gap:12px;margin:8px 0 18px 0}
					.zipbtn{ display:inline-flex;align-items:center;gap:8px;padding:10px 2px 10px 10px;border:1px solid var(--accent);border-radius:calc(var(--radius) - 6px);text-decoration:none;color:#fff;background:var(--accent);box-shadow:var(--shadow);cursor:pointer}
					.zipbtn:hover{filter:saturate(1.05) brightness(1.02)}
					.zipbtn .ico{display:block}
					.breadcrumbs{display:inline-flex;align-items:center;gap:10px;margin-left:6px;color:var(--muted);font-weight:500}
					.breadcrumbs a{color:var(--text);text-decoration:none;border-radius:10px;padding:4px 6px}
					.breadcrumbs a:hover{background:var(--accent-weak)}
					.breadcrumbs .sep{color:var(--muted)}
					.table{width:100%;border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
					th,td{padding:12px 14px;text-align:left;white-space:nowrap}
					th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#fafafa}
					tr:not(:last-child) td{border-bottom:1px solid var(--border)}
					td.sel{width:40px}
					td.name{max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600}
					td.size,td.mtime,td.type,td.action{color:var(--muted)}
					a{color:var(--text);text-decoration:none}
					td.name a{color:var(--text)}
					tr:hover td{background:#fcfcfd}
					tr.up td.name a{font-weight:700}
					.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid var(--border);border-radius:12px;text-decoration:none;background:#fff;color:var(--text);cursor:pointer;box-shadow:0 1px 0 rgba(15,23,42,.03)}
					.btn:hover{border-color:#d1d5db;background:#fff}
					.btn-secondary{background:#fff}
					.btn-copy.ok{border-color:var(--ok)}
					.footer{margin-top:14px;font-size:12px;color:var(--muted)}
					a.link-misbuero { color: black; text-decoration: none; cursor: pointer; transition: color 0.2s ease-in-out; }
					a.link-misbuero:hover { color: red; }
					/* Toast */
					.toast{position:fixed;right:18px;top:18px;background:#fff;color:var(--text);border:1px solid var(--border);padding:12px 14px;border-radius:12px;box-shadow:var(--shadow);z-index:9999;opacity:.98}
					.toast:before{content:"";display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--ok);margin-right:8px;vertical-align:middle}
					/* Master-Checkbox */
					.cmx-master{transform:scale(1.1)}
				</style></head><body><div class="wrap">'

				// Formular für ZIP-Auswahl
				.'<form id="zipform" method="GET" action="'.cmx_dav_h($currentDirUrl).'">'
				.'<input type="hidden" name="zip" value="1" />'
				.'</form>'

				.'<h1><a href="/archiv/">Mis Büro - Archiv</a></h1>'
				.'<div class="toolbar">'.$zipButton.'<span class="breadcrumbs">'.implode('', $crumbs).'</span></div>'
				.'<table class="table"><thead><tr>'
				.'<th><input type="checkbox" class="cmx-master" title="Alle Dateien auswählen" /></th>'
				.'<th><a href="'.$sortLink('name').'">Name'.$arrow('name').'</a></th>'
				.'<th>Typ</th>'
				.'<th><a href="'.$sortLink('size').'">Größe'.$arrow('size').'</a></th>'
				.'<th><a href="'.$sortLink('mtime').'">Geändert'.$arrow('mtime').'</a></th>'
				.'<th>Aktion</th>'
				.'</tr></thead><tbody>'
				.$rows
				.'</tbody></table>'
				// .'managed by S33S<a target="_blank" href="https://misbuero.ch/" class="link-misbuero">mis-buero.ch</a>'
				.'&nbsp;&nbsp;&nbsp;('.cmx_dav_h((string)$fileCount).') managed by <a target="_blank" href="https://misbuero.ch/" class="link-misbuero">mis-buero.ch</a>'


				// Script: Clipboard + 3s Toast + Auswahl-Handling
				.'<script>
				(function(){
					function showToast(msg){
						var t=document.createElement("div");
						t.className="toast";
						t.textContent=msg;
						document.body.appendChild(t);
						setTimeout(function(){ t.remove(); }, 3000);
					}

					// Pfad kopieren
					document.addEventListener("click",function(e){
						var b=e.target.closest(".btn-copy");
						if(!b) return;
						e.preventDefault();
						var txt=b.getAttribute("data-clip")||"";
						if(!txt) return;
						var onOk=function(){ b.classList.add("ok"); showToast("Pfad kopiert: "+txt); setTimeout(function(){b.classList.remove("ok");},800); };
						if(navigator.clipboard && navigator.clipboard.writeText){
							navigator.clipboard.writeText(txt).then(onOk).catch(function(){ alert("Pfad konnte nicht kopiert werden."); });
						}else{
							var ta=document.createElement("textarea"); ta.value=txt; document.body.appendChild(ta); ta.select();
							try{ document.execCommand("copy"); onOk(); }catch(err){ alert("Pfad konnte nicht kopiert werden."); }
							document.body.removeChild(ta);
						}
					});

					// NEU: Master-Checkbox
					var master = document.querySelector(".cmx-master");
					function forEachSel(cb){ document.querySelectorAll(".cmx-sel").forEach(cb); }

					if(master){
						master.addEventListener("change", function(){
							forEachSel(function(el){ el.checked = master.checked; });
						});
					}

					// NEU: Beim ZIP-Submit die ausgewählten Dateien in das GET-Formular übernehmen
					var zipForm = document.getElementById("zipform");
					document.addEventListener("click", function(e){
						var btn = e.target.closest(".zipbtn");
						if(!btn) return;
						// vor dem Submit alte sel[]-Inputs aus dem Formular entfernen
						[].slice.call(zipForm.querySelectorAll(\'input[name="sel[]"]\')).forEach(function(n){ n.remove(); });
						// markierte Dateien als sel[] hinzufügen
						forEachSel(function(el){
							if(el.checked){
								var i=document.createElement("input");
								i.type="hidden"; i.name="sel[]"; i.value=el.value;
								zipForm.appendChild(i);
							}
						});
						// Standard: wenn keine Auswahl -> Formular nur mit zip=1 absenden (kompletter Ordner)
					});

				})();
				</script>'
				.'</div></body></html>';

			$response->setStatus(200);
			$response->setHeader('Content-Type','text/html; charset=UTF-8');
			$response->setBody($html);
			return false; // verhindert Standard-Handling
		}

		// Dateien normal ausliefern lassen
		return null;
	});

	$server->exec();
	exit;
});
