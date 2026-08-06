<?php namespace CLOUDMEISTER\CMX\Buero; defined('ABSPATH') || die('Oxytocin!');

const CMX_BELEG_ANHAENGE_META = '_cmx_beleg_pdf_anhaenge';

/**
 * Liefert ausschliesslich eine echte Datei-URL, nie den Admin-Link des Dokuments.
 */
function cmx_beleg_anhang_datei_url(int $document_id): string {
	if ($document_id <= 0 || (string) \get_post_type($document_id) !== 'dokumente') {
		return '';
	}

	$to_url = static function ($entry): string {
		$file_rel = \is_numeric($entry)
			? (string) \get_post_meta((int) $entry, '_wp_attached_file', true)
			: (string) $entry;

		if ($file_rel === '') {
			return '';
		}
		if (\function_exists(__NAMESPACE__ . '\\cmx_dok_admin_upload_url_from_rel')) {
			return (string) cmx_dok_admin_upload_url_from_rel($file_rel);
		}

		$file_rel = \ltrim(\str_replace('\\', '/', $file_rel), '/');
		$uploads_root = \trailingslashit(\wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads')));
		$absolute_path = \wp_normalize_path((string) (\WP_CONTENT_DIR . '/uploads/' . $file_rel));
		if ($file_rel === '' || !\str_starts_with($absolute_path, $uploads_root) || !\is_file($absolute_path)) {
			return '';
		}
		return (string) \content_url('/uploads/' . $file_rel);
	};

	$self_files = (array) \get_post_meta($document_id, '_cmx_dokumente_files', true);
	for ($index = \count($self_files) - 1; $index >= 0; $index--) {
		$url = $to_url($self_files[$index]);
		if ($url !== '') {
			return $url;
		}
	}

	$url = $to_url((string) \get_post_meta($document_id, '_cmx_dokumente_file_path', true));
	if ($url !== '') {
		return $url;
	}

	$attachment_id = (int) \get_post_meta($document_id, '_cmx_dokumente_attachment_id', true);
	return $attachment_id > 0 ? $to_url($attachment_id) : '';
}

function cmx_beleg_pdf_anhaenge(int $beleg_id): array {
	$document_ids = (array) \get_post_meta($beleg_id, CMX_BELEG_ANHAENGE_META, true);
	$document_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $document_ids))));
	$attachments = [];

	foreach ($document_ids as $document_id) {
		if ((string) \get_post_type($document_id) !== 'dokumente') {
			continue;
		}
		$url = cmx_beleg_anhang_datei_url($document_id);
		if ($url === '') {
			continue;
		}
		$title = \trim((string) \get_the_title($document_id));
		$attachments[] = [
			'id'    => $document_id,
			'title' => $title !== '' ? $title : 'Dokument #' . $document_id,
			'url'   => $url,
		];
	}

	return $attachments;
}

\add_action('add_meta_boxes_belege', function (): void {
	\add_meta_box(
		'cmx_beleg_anhaenge_box',
		'Anhänge',
		__NAMESPACE__ . '\\cmx_beleg_anhaenge_metabox',
		'belege',
		'side',
		'high'
	);
}, 20);

function cmx_beleg_anhaenge_metabox(\WP_Post $post): void {
	\wp_nonce_field('cmx_beleg_anhaenge_speichern', 'cmx_beleg_anhaenge_nonce');

	$selected = (array) \get_post_meta($post->ID, CMX_BELEG_ANHAENGE_META, true);
	$selected = \array_values(\array_unique(\array_filter(\array_map('intval', $selected))));
	$query_args = [
		'post_type'              => 'dokumente',
		'post_status'            => ['publish', 'private', 'draft', 'pending', 'future'],
		'numberposts'            => -1,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'suppress_filters'       => false,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => false,
	];
	if (\function_exists(__NAMESPACE__ . '\\cmx_dokumente_admin_visible_meta_query')) {
		$query_args['meta_query'] = cmx_dokumente_admin_visible_meta_query();
	}
	$documents = \get_posts($query_args);
	$documents = \array_values(\array_filter($documents, static function ($document): bool {
		return $document instanceof \WP_Post
			&& \current_user_can('read_post', $document->ID)
			&& cmx_beleg_anhang_datei_url((int) $document->ID) !== '';
	}));
	$items = \array_map(static function (\WP_Post $document): array {
		$title = \trim((string) \get_the_title($document));
		return [
			'id'       => (int) $document->ID,
			'title'    => $title !== '' ? $title : 'Dokument #' . $document->ID,
			'edit_url' => (string) \get_edit_post_link($document->ID, 'raw'),
		];
	}, $documents);
	$available_ids = \array_map(static fn(array $item): int => (int) $item['id'], $items);
	$selected = \array_values(\array_intersect($selected, $available_ids));
	?>
	<style>
		#cmx_beleg_anhaenge_box,
		#cmx_beleg_anhaenge_box .inside { overflow: visible; }
		#cmx_beleg_anhaenge_box .inside { margin-top: 10px; }
		.cmx-beleg-anhaenge-suche-wrap { position: relative; }
		.cmx-beleg-anhaenge-suche { width: 100%; }
		.cmx-beleg-anhaenge-treffer {
			position: absolute;
			z-index: 100002;
			left: 0;
			right: 0;
			display: none;
			max-height: 240px;
			overflow: auto;
			margin: 2px 0 0;
			padding: 0;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			background: #fff;
			box-shadow: 0 10px 24px rgba(0,0,0,.10);
			list-style: none;
		}
		.cmx-beleg-anhaenge-treffer li { margin: 0; padding: 6px 8px; cursor: pointer; }
		.cmx-beleg-anhaenge-treffer li:hover,
		.cmx-beleg-anhaenge-treffer li.active { background: #e5f3ff; }
		.cmx-beleg-anhaenge-treffer li.cmx-keine-treffer { color: #646970; cursor: default; }
		.cmx-beleg-anhaenge-auswahl {
			display: flex;
			flex-direction: column;
			gap: 6px;
			margin: 8px 0 0;
			padding: 0;
			list-style: none;
		}
		.cmx-beleg-anhaenge-auswahl li {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 8px;
			margin: 0;
		}
		.cmx-beleg-anhaenge-auswahl a {
			min-width: 0;
			overflow-wrap: anywhere;
			text-decoration: none;
		}
		.cmx-beleg-anhaenge-entfernen { flex: 0 0 auto; line-height: 1; }
	</style>
	<div class="cmx-beleg-anhaenge-ui">
		<div class="cmx-beleg-anhaenge-suche-wrap">
			<input
				type="search"
				class="widefat cmx-beleg-anhaenge-suche"
				placeholder="Dokument suchen …"
				aria-label="Dokument suchen"
				autocomplete="off"
				<?php \disabled(empty($items)); ?>
			>
			<ul class="cmx-beleg-anhaenge-treffer"></ul>
		</div>
		<div class="cmx-beleg-anhaenge-werte"></div>
		<ul class="cmx-beleg-anhaenge-auswahl"></ul>
		<?php if (empty($items)): ?>
			<p style="margin-bottom:0;"><em>Keine Dokumente mit Datei vorhanden.</em></p>
		<?php endif; ?>
	</div>
	<script>
	(function () {
		const box = document.getElementById('cmx_beleg_anhaenge_box');
		if (!box || box.dataset.cmxAnhaengeReady === '1') return;
		box.dataset.cmxAnhaengeReady = '1';

		const input = box.querySelector('.cmx-beleg-anhaenge-suche');
		const results = box.querySelector('.cmx-beleg-anhaenge-treffer');
		const selectedList = box.querySelector('.cmx-beleg-anhaenge-auswahl');
		const hiddenValues = box.querySelector('.cmx-beleg-anhaenge-werte');
		const items = <?php echo \wp_json_encode($items); ?> || [];
		let selected = <?php echo \wp_json_encode($selected); ?> || [];
		let matches = [];
		let active = -1;

		function escapeHtml(value) {
			return String(value || '').replace(/[&<>"']/g, function (character) {
				return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character];
			});
		}
		function closeResults() {
			results.style.display = 'none';
			results.innerHTML = '';
			matches = [];
			active = -1;
		}
		function renderSelected() {
			hiddenValues.innerHTML = selected.map(function (id) {
				return '<input type="hidden" name="cmx_beleg_anhaenge[]" value="' + Number(id) + '">';
			}).join('');
			selectedList.innerHTML = selected.map(function (id) {
				const item = items.find(function (candidate) { return Number(candidate.id) === Number(id); });
				if (!item) return '';
				const title = escapeHtml(item.title);
				const label = item.edit_url
					? '<a href="' + escapeHtml(item.edit_url) + '" target="_blank" rel="noopener noreferrer">' + title + '</a>'
					: '<span>' + title + '</span>';
				return '<li>' + label
					+ '<button type="button" class="button-link-delete cmx-beleg-anhaenge-entfernen" data-id="' + Number(id) + '" title="Anhang entfernen" aria-label="Anhang entfernen">'
					+ '<span class="dashicons dashicons-trash" style="color:#d63638;"></span></button></li>';
			}).join('');
		}
		function setActive(index) {
			if (!matches.length) {
				active = -1;
				return;
			}
			if (index < 0) index = matches.length - 1;
			if (index >= matches.length) index = 0;
			active = index;
			Array.from(results.children).forEach(function (row, rowIndex) {
				row.classList.toggle('active', rowIndex === active);
				if (rowIndex === active) row.scrollIntoView({block:'nearest'});
			});
		}
		function renderResults() {
			const term = String(input.value || '').trim().toLocaleLowerCase();
			matches = items.filter(function (item) {
				return selected.indexOf(Number(item.id)) === -1
					&& (term === '' || String(item.title || '').toLocaleLowerCase().includes(term));
			});
			if (!matches.length) {
				results.innerHTML = '<li class="cmx-keine-treffer">Keine Treffer.</li>';
				results.style.display = 'block';
				active = -1;
				return;
			}
			results.innerHTML = matches.map(function (item, index) {
				return '<li data-index="' + index + '">' + escapeHtml(item.title) + '</li>';
			}).join('');
			results.style.display = 'block';
			setActive(0);
		}
		function choose(index) {
			if (!matches[index]) return;
			selected.push(Number(matches[index].id));
			input.value = '';
			renderSelected();
			closeResults();
			input.focus();
		}

		if (input && results) {
			input.addEventListener('focus', renderResults);
			input.addEventListener('click', renderResults);
			input.addEventListener('input', renderResults);
			input.addEventListener('keydown', function (event) {
				if (event.key === 'ArrowDown') {
					event.preventDefault();
					if (results.style.display !== 'block') renderResults();
					else setActive(active + 1);
				} else if (event.key === 'ArrowUp') {
					event.preventDefault();
					if (results.style.display !== 'block') renderResults();
					else setActive(active - 1);
				} else if (event.key === 'Enter' && active > -1) {
					event.preventDefault();
					choose(active);
				} else if (event.key === 'Escape') {
					closeResults();
				}
			});
			results.addEventListener('mousedown', function (event) {
				const row = event.target.closest('li[data-index]');
				if (!row) return;
				event.preventDefault();
				choose(Number(row.dataset.index));
			});
		}
		selectedList.addEventListener('click', function (event) {
			const button = event.target.closest('.cmx-beleg-anhaenge-entfernen');
			if (!button) return;
			selected = selected.filter(function (id) { return Number(id) !== Number(button.dataset.id); });
			renderSelected();
			input.focus();
			renderResults();
		});
		document.addEventListener('mousedown', function (event) {
			if (!box.contains(event.target)) closeResults();
		});
		renderSelected();
	}());
	</script>
	<?php
}

\add_action('save_post_belege', function (int $post_id, \WP_Post $post): void {
	if (
		!isset($_POST['cmx_beleg_anhaenge_nonce'])
		|| !\wp_verify_nonce(
			\sanitize_text_field(\wp_unslash((string) $_POST['cmx_beleg_anhaenge_nonce'])),
			'cmx_beleg_anhaenge_speichern'
		)
		|| \wp_is_post_autosave($post_id)
		|| \wp_is_post_revision($post_id)
		|| !\current_user_can('edit_post', $post_id)
	) {
		return;
	}

	$raw_ids = isset($_POST['cmx_beleg_anhaenge']) && \is_array($_POST['cmx_beleg_anhaenge'])
		? \wp_unslash($_POST['cmx_beleg_anhaenge'])
		: [];
	$document_ids = \array_values(\array_unique(\array_filter(\array_map('intval', $raw_ids))));
	$document_ids = \array_values(\array_filter($document_ids, static function (int $document_id): bool {
		return (string) \get_post_type($document_id) === 'dokumente'
			&& \current_user_can('read_post', $document_id)
			&& cmx_beleg_anhang_datei_url($document_id) !== '';
	}));

	if (empty($document_ids)) {
		\delete_post_meta($post_id, CMX_BELEG_ANHAENGE_META);
		return;
	}
	\update_post_meta($post_id, CMX_BELEG_ANHAENGE_META, $document_ids);
}, 40, 2);
