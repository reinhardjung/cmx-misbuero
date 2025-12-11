<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');


/**
 * Eigene Admin-Meldung nach dem Speichern oder Veröffentlichen von Beiträgen oder CPTs.
 */
function cmx_custom_post_updated_messages( $messages ) {
	global $post, $post_ID;

	// Hole den aktuellen Post Type
	$post_type = get_post_type( $post_ID );
	$post_type_object = get_post_type_object( $post_type );

	// Verwende das Label des CPTs (z. B. "Kontakt", "Beleg")
	$singular_name = $post_type_object->labels->singular_name ?? 'Beitrag';

	// Überschreibe alle Standard-Meldungen
	$messages[$post_type] = [
		0  => '', // Nicht verwendet
		1  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		2  => __( 'Benutzerdefiniertes Feld aktualisiert.', 'default' ),
		3  => __( 'Benutzerdefiniertes Feld gelöscht.', 'default' ),
		4  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		5  => isset($_GET['revision']) ? sprintf( __( '%s wurde auf Revision %s zurückgesetzt.', 'default' ), $singular_name, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		6  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		7  => sprintf( __( '%s gespeichert.', 'default' ), $singular_name ),
		8  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		9  => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
		10 => sprintf( __( '%s wurde gespeichert.', 'default' ), $singular_name ),
	];

	return $messages;
}
add_filter( 'post_updated_messages', __NAMESPACE__ . '\\cmx_custom_post_updated_messages' );



/**
 * ------------------------------------------------------------
 * Platzhalter „Titel“ anpassen (optional)
 * ------------------------------------------------------------
 */
\add_filter('enter_title_here', __NAMESPACE__ . '\\cmx_cpt_custom_title_placeholder', 10, 2);
function cmx_cpt_custom_title_placeholder(string $placeholder, \WP_Post $post): string {
	// Mapping: CPT => Platzhalter-Text
	$map = [
		'kontakte' => 'Firmenname (optional)',
		'belege'   => 'Belegtitel eingeben',
		'artikel'  => 'Artikelbezeichung',
		// weitere CPTs hier ergänzen …
	];

	$post_type = $post->post_type ?? '';
	if (isset($map[$post_type]) && $map[$post_type] !== '') {
		return $map[$post_type];
	}

	return $placeholder; // Fallback: Standard
}


// \add_filter('enter_title_here', function ($placeholder, $post) {
// 	return ($post && $post->post_type === 'kontakte') ? 'Firmenname (optional)' : $placeholder;
// }, 10, 2);



/**
 * CPT "artikel": Unterdrücke JEDE "Website verlassen?"-Meldung – IMMER (auch bei neuem Artikel).
 * Wir injizieren so früh wie möglich (admin_head) und patchen:
 *  - window.onbeforeunload (read-only auf null)
 *  - addEventListener (ignoriert Typ "beforeunload")
 *  - entfernen jQuery-Listener
 *  - halten das dauerhaft sauber (Interval ohne Timeout)
 */

\add_action('admin_head-post.php',     __NAMESPACE__.'\\cmx_artikel_never_ask_beforeunload', 0);
\add_action('admin_head-post-new.php', __NAMESPACE__.'\\cmx_artikel_never_ask_beforeunload', 0);

function cmx_artikel_never_ask_beforeunload(): void {
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen) return; ?>
	<script>
	(function(){
		// ---------- 1) Sofort bestehende Zuweisungen neutralisieren ----------
		try { window.onbeforeunload = null; } catch(_){}

		// ---------- 2) window.onbeforeunload un-settable machen ----------
		try {
			Object.defineProperty(window, 'onbeforeunload', {
				get: function(){ return null; },
				set: function(){ /* blocken */ },
				configurable: true
			});
		} catch(_){}

		// ---------- 3) addEventListener für beforeunload global ignorieren ----------
		(function(){
			var targets = [];
			try { if (window.EventTarget && EventTarget.prototype) targets.push(EventTarget.prototype); } catch(_){}
			targets.push(window); // Fallback

			for (var i=0;i<targets.length;i++){
				var t = targets[i];
				if (!t || t.__cmxPatchedBeforeUnload) continue;
				var origAdd = t.addEventListener;
				if (typeof origAdd === 'function'){
					t.addEventListener = function(type, listener, options){
						if (type === 'beforeunload') {
							return; // strikt ignorieren
						}
						return origAdd.apply(this, arguments);
					};
				}
				t.__cmxPatchedBeforeUnload = true;
			}
		})();

		// ---------- 4) jQuery-gebundene beforeunload-Handler regelmäßig entfernen ----------
		function nukeJQ(){
			if (window.jQuery) {
				try {
					var $ = window.jQuery, ev = $._data(window, 'events');
					if (ev && ev.beforeunload) $(window).off('beforeunload');
				} catch(_){}
			}
		}
		nukeJQ();

		// ---------- 5) Harte Capture-Sperre (falls etwas doch durchrutscht) ----------
		// Stoppt *alle* weiteren beforeunload-Listener kompromisslos.
		function hardBlocker(e){
			// Keine Prompts zulassen, keine returnValue setzen!
			e.stopImmediatePropagation();
			return undefined;
		}
		window.addEventListener('beforeunload', hardBlocker, {capture:true});

		// ---------- 6) Dauerhaft sauber halten (Plugins können später binden) ----------
		// Kein Timeout – hält die Seite über die gesamte Bearbeitungsdauer sauber.
		setInterval(function(){
			try { if (window.onbeforeunload) window.onbeforeunload = null; } catch(_){}
			nukeJQ();
		}, 500);
	})();
	</script>
<?php }
