<?php
namespace CLOUDMEISTER\CMX\Buero;
defined('ABSPATH') || die('Oxytocin!');

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
	if (!$ts) return '—';
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
	if (!preg_match('#^/dav(?:/|$)#', $path)) return;

	require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';

	$sharePath = WP_CONTENT_DIR . '/uploads/misbuero';
	$root      = new DAV\FS\Directory($sharePath);
	$server    = new DAV\Server($root);
	$server->setBaseUri('/dav'); // bewusst ohne Slash am Ende

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

	// Hübsche HTML-Indexseite für Collections + ZIP-Download
	$server->on('method:GET', function(HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($server, $sharePath) {
		$relPath = trim($request->getPath(), '/'); // relativ zur BaseUri
		$tree    = $server->tree;

		$exists  = $tree->nodeExists($request->getPath());
		$node    = $exists ? $tree->getNodeForPath($request->getPath()) : null;

		// Wenn Ordner und ?zip=1: ZIP aller Inhalte (rekursiv) ausliefern
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

				// wp_tempnam aus globalem Namensraum + Fallback
				$tmpZip = (function_exists('\\wp_tempnam') ? \wp_tempnam('davzip_') : \tempnam(\sys_get_temp_dir(), 'davzip_'));
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

				// A) KEIN zusätzlicher Root-Ordner im ZIP.
				cmx_dav_zip_dir($absDirReal, $zip, rtrim($absDirReal, DIRECTORY_SEPARATOR));

				$zip->close();

				$label    = ($relPath === '' ? 'root' : trim($relPath, '/'));
				$filename = $label . '.zip';

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

			$children = iterator_to_array($node->getChildren());
			$base     = rtrim($server->getBaseUri(), '/');

			// Sortierung: Ordner zuerst, dann Dateien – jeweils natürlich nach Name
			usort($children, function($a, $b){
				$isDirA = $a instanceof DAV\ICollection;
				$isDirB = $b instanceof DAV\ICollection;
				if ($isDirA !== $isDirB) return $isDirA ? -1 : 1;
				return strnatcasecmp($a->getName(), $b->getName());
			});

			// Breadcrumbs
			$segments = array_values(array_filter(explode('/', $relPath), fn($s)=>$s!==''));
			$crumbs   = [];
			$accum    = '';
			$crumbs[] = '<a href="'.cmx_dav_h($base).'">/</a>';
			foreach ($segments as $seg) {
				$accum = trim($accum.'/'.$seg, '/');
				$crumbs[] = '<span class="sep">›</span><a href="'.cmx_dav_h(cmx_dav_join_uri($base, $accum)).'/">'.cmx_dav_h($seg).'</a>';
			}

			// ZIP-Button (Icon) vor den Breadcrumbs
			$currentHref = cmx_dav_join_uri($base, $relPath) . '/?zip=1';
			$zipButton = '<a class="zipbtn" href="'.cmx_dav_h($currentHref).'" title="Alles als ZIP herunterladen" aria-label="Alles als ZIP herunterladen">'
				. '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 3v10m0 0 4-4m-4 4-4-4M5 21h14a2 2 0 0 0 2-2v0a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v0a2 2 0 0 0 2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
				. '<span>ZIP</span></a>';

			// Parent-Link (..), sofern nicht Root
			$rows = '';
			if (!empty($segments)) {
				$parent = array_slice($segments, 0, -1);
				$parentHref = cmx_dav_h(cmx_dav_join_uri($base, implode('/', $parent))).'/';
				$rows .= '<tr class="up"><td class="name"><a href="'.$parentHref.'">..</a></td><td class="type">Ordner</td><td class="size">—</td><td class="mtime">—</td><td class="action">—</td></tr>';
			}

			// Tabellenzeilen aufbauen
			foreach ($children as $child) {
				$name  = $child->getName();
				$isDir = $child instanceof DAV\ICollection;
				$href  = cmx_dav_join_uri($base, $relPath, rawurlencode($name)) . ($isDir ? '/' : '');
				$absHref = cmx_dav_abs_url($href); // B) kompletter Pfad für Clipboard

				// Metadaten
				$type  = $isDir ? 'Ordner' : 'Datei';
				$size  = '—';
				$mtime = '—';

				if (method_exists($child, 'getLastModified')) {
					$lm = $child->getLastModified();
					$mtime = cmx_dav_fmt_time($lm);
				}
				if (!$isDir && method_exists($child, 'getSize')) {
					try {
						$size = cmx_dav_human_bytes((int)$child->getSize());
					} catch (\Throwable $e) {
						$size = '—';
					}
				}

				// NEU: Copy-Pfad-Icon (vor Download) – komplette URL + 3s-Mitteilung
				$copyBtn = !$isDir
					? '<button class="btn btn-copy" data-clip="'.cmx_dav_h($absHref).'" title="Pfad kopieren" aria-label="Pfad kopieren">'
						.'<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M9 9h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2zm0-4h9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
					.'</button>'
					: '—';

				$downloadBtn = !$isDir ? '<a class="btn" href="'.cmx_dav_h($href).'" download>Download</a>' : '—';

				$rows .= '<tr>'
					. '<td class="name"><a href="'.cmx_dav_h($href).'">'.cmx_dav_h($name).($isDir?'/':'').'</a></td>'
					. '<td class="type">'.cmx_dav_h($type).'</td>'
					. '<td class="size">'.cmx_dav_h($size).'</td>'
					. '<td class="mtime">'.cmx_dav_h($mtime).'</td>'
					. '<td class="action">'.($isDir ? '—' : $copyBtn.' '.$downloadBtn).'</td>'
					. '</tr>';
			}

			$title = 'Index – ' . (empty($segments) ? '/' : implode('/', $segments).'/');

			$html = '<!doctype html><html lang="de"><head><meta charset="utf-8">'
				.'<meta name="viewport" content="width=device-width,initial-scale=1">'
				.'<title>'.cmx_dav_h($title).'</title>'
				.'<style>
					:root{--bg:#0b1020;--card:#141a2e;--muted:#8891a7;--text:#e6e9f2;--accent:#5b8def;--accent2:#7ed9a0;--border:#253050;}
					*{box-sizing:border-box}html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial}
					.wrap{max-width:980px;margin:32px auto;padding:0 16px}
					h1{font-size:18px;margin:0 0 12px 0;display:flex;align-items:center;gap:10px}
					.toolbar{display:flex;align-items:center;gap:10px;margin:6px 0 8px 0}
					.zipbtn{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border);border-radius:10px;text-decoration:none;color:var(--text);background:rgba(255,255,255,.02)}
					.zipbtn:hover{border-color:var(--accent);text-decoration:none}
					.zipbtn .ico{display:block}
					.breadcrumbs{display:inline; margin-left:6px; color:var(--muted)}
					.breadcrumbs a{color:var(--text);text-decoration:none}
					.breadcrumbs .sep{display:inline-block;margin:0 8px;color:var(--muted)}
					.table{width:100%;border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
					th,td{padding:10px 12px;text-align:left;white-space:nowrap}
					th{font-size:12px;letter-spacing:.02em;text-transform:uppercase;color:var(--muted);background:rgba(255,255,255,.03)}
					tr:not(:last-child) td{border-bottom:1px solid var(--border)}
					td.name{max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
					td.size,td.mtime,td.type,td.action{color:var(--muted)}
					a{color:var(--accent);text-decoration:none}
					a:hover{text-decoration:underline}
					tr:hover td{background:rgba(255,255,255,.02)}
					tr.up td.name a{font-weight:600}
					.btn{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;text-decoration:none;background:transparent;color:var(--text);cursor:pointer}
					.btn:hover{border-color:var(--accent);text-decoration:none}
					.btn-copy.ok{border-color:var(--accent2)}
					/* Toast */
					.toast{position:fixed;right:16px;top:16px;background:var(--card);color:var(--text);border:1px solid var(--border);padding:10px 12px;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.35);z-index:9999;opacity:.98}
				</style></head><body><div class="wrap">'
				.'<h1>Deine Belege</h1>'
				.'<div class="toolbar">'.$zipButton.'<span class="breadcrumbs">'.implode('', $crumbs).'</span></div>'
				.'<table class="table"><thead><tr>'
				.'<th>Name</th><th>Typ</th><th>Größe</th><th>Geändert</th><th>Aktion</th>'
				.'</tr></thead><tbody>'
				.$rows
				.'</tbody></table>'
				.'<div class="footer">Read-only Anzeige · WebDAV Pfad: '.cmx_dav_h('/'.$relPath).'</div>'
				// Script: Clipboard + 3s Toast
				.'<script>
				(function(){
					function showToast(msg){
						var t=document.createElement("div");
						t.className="toast"; t.textContent=msg;
						document.body.appendChild(t);
						setTimeout(function(){ t.remove(); }, 3000);
					}
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
