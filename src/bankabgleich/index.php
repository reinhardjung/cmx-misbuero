<?php
/**
 * Bankabgleich.php
 * Single-file Admin-Modul: Bankabgleich (Teilzahlungen, Beleg-Verknüpfung, Audit-Log, CAMT Auto-Matching)
 */

if ( ! defined('ABSPATH') ) { exit; }

class CM_Bankabgleich {

	const TX_TABLE    = 'cm_bank_transactions';
	const PAY_TABLE   = 'cm_payments';
	const AUDIT_TABLE = 'cm_audit_log';

	const CAPABILITY  = 'manage_options';
	const MENU_SLUG   = 'cm-bankabgleich';

	const UNDO_TTL    = 30; // Sekunden

	public function __construct() {
		add_action('admin_menu', [ $this, 'register_menu' ]);
		add_action('admin_init', [ $this, 'maybe_install' ]);

		add_action('admin_post_cm_bankabgleich_import_camt', [ $this, 'handle_import_camt' ]);
		add_action('admin_post_cm_bankabgleich_save_allocations', [ $this, 'handle_save_allocations' ]);
		add_action('admin_post_cm_bankabgleich_undo', [ $this, 'handle_undo' ]);
		add_action('admin_post_cm_bankabgleich_unassign_payment', [ $this, 'handle_unassign_payment' ]);
		add_action('admin_post_cm_bankabgleich_add_opening', [ $this, 'handle_add_opening' ]);
	}

	/* ------------------------------------------------------------
	 * Install (DB tables)
	 * ------------------------------------------------------------ */
	public function maybe_install() {
		if ( ! current_user_can(self::CAPABILITY) ) return;

		$this->maybe_upgrade_tables();

		$installed = get_option('cm_bankabgleich_installed');
		if ( $installed === '1' ) return;

		$this->install_tables();
		update_option('cm_bankabgleich_installed', '1', false);
	}

	private function maybe_upgrade_tables() {
		global $wpdb;
		$tx_table = $wpdb->prefix . self::TX_TABLE;

		$has_category = $wpdb->get_var($wpdb->prepare(
			"SHOW COLUMNS FROM {$tx_table} LIKE %s",
			'category'
		));

		if (!$has_category) {
			$wpdb->query("ALTER TABLE {$tx_table} ADD COLUMN category VARCHAR(50) NULL");
		}
	}

	private function install_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$tx_table = $wpdb->prefix . self::TX_TABLE;
		$pay_table = $wpdb->prefix . self::PAY_TABLE;
		$audit_table = $wpdb->prefix . self::AUDIT_TABLE;

		$sql1 = "CREATE TABLE {$tx_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(50) NOT NULL DEFAULT 'camt',
			source_uid VARCHAR(191) NULL,
			booking_date DATE NOT NULL,
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			currency CHAR(3) NOT NULL DEFAULT 'CHF',
			text TEXT NULL,
			reference VARCHAR(191) NULL,
			iban VARCHAR(64) NULL,
			category VARCHAR(50) NULL,
			raw_xml LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open', /* open|partial|assigned */
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY booking_date (booking_date),
			KEY source_uid (source_uid(50))
		) {$charset};";

		$sql2 = "CREATE TABLE {$pay_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			transaction_id BIGINT UNSIGNED NOT NULL,
			document_id BIGINT UNSIGNED NOT NULL, /* Beleg (Post-ID) */
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			currency CHAR(3) NOT NULL DEFAULT 'CHF',
			created_at DATETIME NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY transaction_id (transaction_id),
			KEY document_id (document_id)
		) {$charset};";

		$sql3 = "CREATE TABLE {$audit_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(50) NOT NULL,
			object_type VARCHAR(50) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			context LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY action (action),
			KEY object_type (object_type),
			KEY object_id (object_id),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta($sql1);
		dbDelta($sql2);
		dbDelta($sql3);
	}

	/* ------------------------------------------------------------
	 * Admin Menu
	 * ------------------------------------------------------------ */
	public function register_menu() {
		add_menu_page(
			'Bankabgleich',
			'Bankabgleich',
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_page' ],
			'dashicons-bank',
			56
		);
	}

	/* ------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------ */
	public function render_page() {
		if ( ! current_user_can(self::CAPABILITY) ) {
			wp_die('Keine Berechtigung.');
		}

		global $wpdb;

		$tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'transactions';
		$status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'open';
		if ( ! in_array($status, [ 'open', 'partial', 'assigned' ], true) ) {
			$status = 'open';
		}

		$tx_id = isset($_GET['tx_id']) ? absint($_GET['tx_id']) : 0;

		$tx_table  = $wpdb->prefix . self::TX_TABLE;
		$pay_table = $wpdb->prefix . self::PAY_TABLE;

		$transactions = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$tx_table} WHERE status=%s ORDER BY booking_date DESC, id DESC", $status)
		);

		$payments = $wpdb->get_results(
			"SELECT p.*, t.booking_date AS tx_booking_date, t.text AS tx_text, t.status AS tx_status
			 FROM {$pay_table} p
			 LEFT JOIN {$tx_table} t ON t.id = p.transaction_id
			 ORDER BY p.created_at DESC, p.id DESC"
		);

		$selected_tx = null;
		$existing_allocations = [];
		if ( $tx_id ) {
			$selected_tx = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$tx_table} WHERE id=%d", $tx_id)
			);

			if ( $selected_tx ) {
				$existing_allocations = $wpdb->get_results(
					$wpdb->prepare("SELECT * FROM {$pay_table} WHERE transaction_id=%d ORDER BY id ASC", $tx_id)
				);
			}
		}

		$notice = isset($_GET['notice']) ? sanitize_key($_GET['notice']) : '';
		?>
		<div class="wrap">
			<h1>Bankabgleich</h1>

			<?php if ( $notice === 'saved' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						Zahlung(en) gespeichert.
						<a href="<?php echo esc_url( $this->undo_url() ); ?>">Rückgängig machen</a>
						<span class="description"> (<?php echo intval(self::UNDO_TTL); ?>s)</span>
					</p>
				</div>
			<?php elseif ( $notice === 'undo' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Rückgängig gemacht. Die Bankbuchung ist wieder offen.</p>
				</div>
			<?php elseif ( $notice === 'imported' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>CAMT importiert. Matching wurde automatisch ausgeführt.</p>
				</div>
			<?php elseif ( $notice === 'unassigned' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>Zuordnung aufgehoben. Bankbuchung ist wieder offen.</p>
				</div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo $tab==='transactions'?'nav-tab-active':''; ?>"
				   href="<?php echo esc_url( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status='.$status) ); ?>">Bankbuchungen</a>
				<a class="nav-tab <?php echo $tab==='payments'?'nav-tab-active':''; ?>"
				   href="<?php echo esc_url( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=payments') ); ?>">Zahlungen</a>
			</h2>

			<?php if ( $tab === 'payments' ) : ?>
				<?php $this->render_payments($payments); ?>
			<?php else : ?>
				<?php $this->render_transactions($transactions, $status, $selected_tx, $existing_allocations); ?>
			<?php endif; ?>

		</div>
		<?php
	}

	private function render_transactions(array $transactions, string $status, $selected_tx, array $existing_allocations) {
		$categories = $this->get_tx_categories();
		?>
		<h2>Anfangsbestand erfassen</h2>
		<?php $opening_options = $this->get_opening_options(); ?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
			<input type="hidden" name="action" value="cm_bankabgleich_add_opening">
			<?php wp_nonce_field('cm_bankabgleich_add_opening'); ?>
			<label>
				<select name="opening_type">
					<?php foreach ($opening_options as $key => $label) : ?>
						<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label style="margin-left:8px;">
				<input type="date" name="opening_date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
			</label>
			<label style="margin-left:8px;">
				<input type="text" name="opening_amount" placeholder="0.00" required style="width:120px;" class="cmx-select-on-focus">
			</label>
			<label style="margin-left:8px;">
				<select name="opening_currency">
					<option value="CHF">CHF</option>
					<option value="EUR">EUR</option>
				</select>
			</label>
			<button class="button button-secondary" style="margin-left:8px;">Anlegen</button>
		</form>
		<script>
		(function() {
			const input = document.querySelector('input.cmx-select-on-focus');
			if (!input) return;
			const selectAll = () => input.select();
			input.addEventListener('focus', selectAll);
			input.addEventListener('click', selectAll);
		})();
		</script>

		<h2>CAMT-Datei importieren</h2>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="margin-bottom:16px;">
			<input type="hidden" name="action" value="cm_bankabgleich_import_camt">
			<?php wp_nonce_field('cm_bankabgleich_import_camt'); ?>
			<input type="file" name="camt_file" accept=".xml" required>
			<button class="button button-secondary">Importieren</button>
		</form>

		<h2>Status</h2>
		<p class="description">Default ist <strong>offen</strong>. „Erledigt = weg“ gilt durch den Filter.</p>

		<h2 class="nav-tab-wrapper" style="margin-top:10px;">
			<a class="nav-tab <?php echo $status==='open'?'nav-tab-active':''; ?>"
			   href="<?php echo esc_url( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=open') ); ?>">Offen</a>
			<a class="nav-tab <?php echo $status==='partial'?'nav-tab-active':''; ?>"
			   href="<?php echo esc_url( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=partial') ); ?>">Teilweise</a>
			<a class="nav-tab <?php echo $status==='assigned'?'nav-tab-active':''; ?>"
			   href="<?php echo esc_url( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=assigned') ); ?>">Zugeordnet</a>
		</h2>

		<table class="widefat striped" style="margin-top:10px;">
			<thead>
				<tr>
					<th style="width:110px;">Datum</th>
					<th style="width:140px;">Betrag</th>
					<th>Text</th>
					<th style="width:160px;">Kategorie</th>
					<th style="width:220px;">Referenz</th>
					<th style="width:120px;"></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty($transactions) ) : ?>
					<tr><td colspan="6">Keine Einträge.</td></tr>
				<?php else : foreach ( $transactions as $tx ) : ?>
					<tr>
						<td><?php echo esc_html($tx->booking_date); ?></td>
						<td><strong><?php echo esc_html($this->fmt_money($tx->amount, $tx->currency)); ?></strong></td>
						<td><?php echo esc_html($tx->text); ?></td>
						<td><?php echo esc_html($categories[$tx->category] ?? '–'); ?></td>
						<td><?php echo esc_html($tx->reference ?: '–'); ?></td>
						<td>
							<a class="button <?php echo $tx->status==='open'?'button-primary':''; ?>"
							   href="<?php echo esc_url( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status='.$status.'&tx_id='.intval($tx->id)) ); ?>">
								<?php echo $tx->status==='open' ? 'Zuordnen' : 'Details'; ?>
							</a>
						</td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>

		<?php
		if ( $selected_tx ) {
			$this->render_allocation_panel($selected_tx, $existing_allocations);
		}
	}

	private function render_allocation_panel($tx, array $existing_allocations) {
		$tx_amount = (float) $tx->amount;
		$allocated_sum = 0.0;
		$categories = $this->get_tx_categories();

		foreach ( $existing_allocations as $a ) {
			$allocated_sum += (float) $a->amount;
		}

		$rest = $tx_amount - $allocated_sum;

		// Kandidaten (automatisch)
		$candidates = $this->find_document_candidates($tx);
		?>
		<hr>
		<h2>Zuordnung: Bankbuchung #<?php echo intval($tx->id); ?></h2>

		<table class="widefat" style="margin-bottom:12px;">
			<tbody>
				<tr><th style="width:180px;">Datum</th><td><?php echo esc_html($tx->booking_date); ?></td></tr>
				<tr><th>Betrag</th><td><strong><?php echo esc_html($this->fmt_money($tx->amount, $tx->currency)); ?></strong></td></tr>
				<tr><th>Text</th><td><?php echo esc_html($tx->text); ?></td></tr>
				<tr><th>Referenz</th><td><?php echo esc_html($tx->reference ?: '–'); ?></td></tr>
				<tr><th>IBAN</th><td><?php echo esc_html($tx->iban ?: '–'); ?></td></tr>
			</tbody>
		</table>

		<div class="notice notice-info">
			<p>
				Du kannst diese Bankbuchung auf <strong>mehrere Belege</strong> aufteilen (Teilzahlungen / Sammelzahlung).
				Der Status wird automatisch gesetzt:
				<strong>Rest = 0</strong> → zugeordnet, <strong>Rest ≠ 0</strong> → teilweise.
			</p>
		</div>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="cm_bankabgleich_save_allocations">
			<input type="hidden" name="tx_id" value="<?php echo intval($tx->id); ?>">
			<?php wp_nonce_field('cm_bankabgleich_save_allocations'); ?>

			<h3>Vorschläge (automatisch)</h3>
			<p class="description">Du kannst Vorschläge übernehmen oder unten manuell Belege ergänzen.</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:40px;"></th>
						<th>Beleg</th>
						<th style="width:180px;">Vorschlag Betrag</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty($candidates) ) : ?>
					<tr><td colspan="3">Keine Vorschläge gefunden. Du kannst unten manuell Belege hinzufügen.</td></tr>
				<?php else : foreach ( $candidates as $idx => $cand ) : ?>
					<tr>
						<td><input type="checkbox" name="suggest_use[<?php echo intval($idx); ?>]" value="1"></td>
						<td>
							<strong><?php echo esc_html($cand['title']); ?></strong>
							<div class="description">Beleg-ID: <?php echo intval($cand['document_id']); ?></div>
						</td>
						<td>
							<input type="hidden" name="suggest_doc_id[<?php echo intval($idx); ?>]" value="<?php echo intval($cand['document_id']); ?>">
							<input type="text" name="suggest_amount[<?php echo intval($idx); ?>]" value="<?php echo esc_attr($this->num($cand['amount'])); ?>" style="width:140px;">
							<span class="description"><?php echo esc_html($tx->currency); ?></span>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

			<h3 style="margin-top:16px;">Kategorie</h3>
			<p class="description">Die Kategorie wird an dieser Bankbuchung gespeichert.</p>
			<select name="tx_category" style="min-width:220px;">
				<option value="">— auswählen —</option>
				<?php foreach ($categories as $key => $label) : ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected((string)$tx->category, (string)$key); ?>>
						<?php echo esc_html($label); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<h3 style="margin-top:16px;">Bestehende Zuordnungen</h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Beleg</th>
						<th style="width:220px;">Zugeordnet</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty($existing_allocations) ) : ?>
						<tr><td colspan="2">Noch keine Zuordnungen.</td></tr>
					<?php else : foreach ( $existing_allocations as $i => $a ) : ?>
						<tr>
							<td>
								<?php echo esc_html($this->doc_label((int)$a->document_id)); ?>
								<div class="description">Beleg-ID: <?php echo intval($a->document_id); ?></div>
							</td>
							<td>
								<input type="hidden" name="alloc_id[<?php echo intval($i); ?>]" value="<?php echo intval($a->id); ?>">
								<input type="hidden" name="alloc_doc_id[<?php echo intval($i); ?>]" value="<?php echo intval($a->document_id); ?>">
								<input type="text" name="alloc_amount[<?php echo intval($i); ?>]" value="<?php echo esc_attr($this->num($a->amount)); ?>" style="width:140px;">
								<span class="description"><?php echo esc_html($a->currency); ?></span>
								<label style="margin-left:10px;">
									<input type="checkbox" name="alloc_delete[<?php echo intval($i); ?>]" value="1"> Entfernen
								</label>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>

			<h3 style="margin-top:16px;">Manuell hinzufügen</h3>
			<p class="description">
				Trage eine Beleg-ID (Post-ID) ein. Beispiel: WooCommerce Bestellung (Order-ID) oder Dein CPT-Beleg.
			</p>
			<table class="widefat">
				<tbody>
					<tr>
						<th style="width:180px;">Beleg-ID</th>
						<td><input type="number" name="manual_doc_id" value="" min="1" style="width:200px;"></td>
					</tr>
					<tr>
						<th>Betrag</th>
						<td>
							<input type="text" name="manual_amount" value="<?php echo esc_attr($this->num(max(0, $rest))); ?>" style="width:200px;">
							<span class="description"><?php echo esc_html($tx->currency); ?></span>
						</td>
					</tr>
				</tbody>
			</table>

			<?php
			// Totals (aktuell) – rein informativ, final berechnet serverseitig beim Save
			?>
			<div style="margin-top:12px; padding:12px; background:#fff; border:1px solid #c3c4c7; border-radius:6px;">
				<div><strong>Bankbetrag:</strong> <?php echo esc_html($this->fmt_money($tx_amount, $tx->currency)); ?></div>
				<div><strong>Aktuell zugeordnet:</strong> <?php echo esc_html($this->fmt_money($allocated_sum, $tx->currency)); ?></div>
				<div><strong>Rest:</strong> <?php echo esc_html($this->fmt_money($rest, $tx->currency)); ?></div>
			</div>

			<p style="margin-top:12px;">
				<a class="button" href="<?php echo esc_url( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status='.$_GET['status']) ); ?>">Abbrechen</a>
				<button class="button button-primary">Speichern</button>
			</p>
		</form>
		<?php
	}

	private function render_payments(array $payments) {
		?>
		<h2>Zahlungen</h2>
		<p class="description">Hier kannst Du Zuordnungen jederzeit aufheben. Danach ist die Bankbuchung wieder <strong>offen</strong>.</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:160px;">Datum</th>
					<th style="width:140px;">Betrag</th>
					<th>Beleg</th>
					<th style="width:120px;">Bankbuchung</th>
					<th style="width:180px;"></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty($payments) ) : ?>
					<tr><td colspan="5">Noch keine Zahlungen.</td></tr>
				<?php else : foreach ( $payments as $p ) : ?>
					<tr>
						<td><?php echo esc_html($p->created_at); ?></td>
						<td><strong><?php echo esc_html($this->fmt_money($p->amount, $p->currency)); ?></strong></td>
						<td><?php echo esc_html($this->doc_label((int)$p->document_id)); ?> <span class="description">(ID: <?php echo intval($p->document_id); ?>)</span></td>
						<td>#<?php echo intval($p->transaction_id); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
								<input type="hidden" name="action" value="cm_bankabgleich_unassign_payment">
								<input type="hidden" name="payment_id" value="<?php echo intval($p->id); ?>">
								<?php wp_nonce_field('cm_bankabgleich_unassign_payment'); ?>
								<button class="button">Zuordnung aufheben</button>
							</form>
						</td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php
	}

	/* ------------------------------------------------------------
	 * Save Allocations (Teilzahlungen / Sammelzahlungen)
	 * ------------------------------------------------------------ */
	public function handle_save_allocations() {
		if ( ! current_user_can(self::CAPABILITY) ) wp_die('Keine Berechtigung.');
		check_admin_referer('cm_bankabgleich_save_allocations');

		global $wpdb;

		$tx_id = isset($_POST['tx_id']) ? absint($_POST['tx_id']) : 0;
		if ( ! $tx_id ) wp_die('Fehlende tx_id.');

		$tx_table  = $wpdb->prefix . self::TX_TABLE;
		$pay_table = $wpdb->prefix . self::PAY_TABLE;

		$tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tx_table} WHERE id=%d", $tx_id));
		if ( ! $tx ) wp_die('Bankbuchung nicht gefunden.');

		$user_id = get_current_user_id();

		$categories = $this->get_tx_categories();
		$tx_category = isset($_POST['tx_category']) ? sanitize_text_field($_POST['tx_category']) : '';
		if ($tx_category !== '' && !isset($categories[$tx_category])) {
			$tx_category = '';
		}
		$wpdb->update(
			$tx_table,
			[ 'category' => $tx_category ],
			[ 'id' => $tx_id ]
		);

		// Undo Snapshot (für 30s): Status + alle Payment-Records dieser tx
		$snapshot = [
			'tx_id' => (int) $tx_id,
			'tx_status' => (string) $tx->status,
			'payments' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$pay_table} WHERE transaction_id=%d", $tx_id), ARRAY_A),
		];
		$this->set_undo_snapshot($snapshot);

		// 1) Bestehende Allocations updaten/entfernen
		$total_alloc = 0.0;

		if ( ! empty($_POST['alloc_id']) && is_array($_POST['alloc_id']) ) {
			foreach ( $_POST['alloc_id'] as $i => $alloc_id ) {
				$alloc_id = absint($alloc_id);
				$doc_id = isset($_POST['alloc_doc_id'][$i]) ? absint($_POST['alloc_doc_id'][$i]) : 0;

				$delete = ! empty($_POST['alloc_delete'][$i]);

				if ( $alloc_id && $delete ) {
					$wpdb->delete($pay_table, [ 'id' => $alloc_id ]);
					$this->audit('payment_deleted', 'payment', $alloc_id, [
						'transaction_id' => $tx_id,
						'document_id' => $doc_id,
					]);
					continue;
				}

				$amount = isset($_POST['alloc_amount'][$i]) ? $this->parse_amount($_POST['alloc_amount'][$i]) : 0.0;
				$amount = max(0.0, $amount);

				if ( $alloc_id && $doc_id ) {
					$wpdb->update(
						$pay_table,
						[
							'amount' => $amount,
							'currency' => $tx->currency,
						],
						[ 'id' => $alloc_id ]
					);

					$total_alloc += $amount;

					$this->audit('payment_updated', 'payment', $alloc_id, [
						'transaction_id' => $tx_id,
						'document_id' => $doc_id,
						'amount' => $amount,
						'currency' => $tx->currency,
					]);
				}
			}
		}

		// 2) Vorschläge übernehmen
		if ( ! empty($_POST['suggest_doc_id']) && is_array($_POST['suggest_doc_id']) ) {
			foreach ( $_POST['suggest_doc_id'] as $idx => $doc_id ) {
				$use = ! empty($_POST['suggest_use'][$idx]);
				if ( ! $use ) continue;

				$doc_id = absint($doc_id);
				$amount = isset($_POST['suggest_amount'][$idx]) ? $this->parse_amount($_POST['suggest_amount'][$idx]) : 0.0;
				$amount = max(0.0, $amount);

				if ( $doc_id > 0 && $amount > 0 ) {
					$wpdb->insert(
						$pay_table,
						[
							'transaction_id' => $tx_id,
							'document_id'    => $doc_id,
							'amount'         => $amount,
							'currency'       => $tx->currency,
							'created_at'     => current_time('mysql'),
							'created_by'     => $user_id,
						]
					);

					$pid = (int) $wpdb->insert_id;
					$total_alloc += $amount;

					$this->audit('payment_created', 'payment', $pid, [
						'transaction_id' => $tx_id,
						'document_id' => $doc_id,
						'amount' => $amount,
						'currency' => $tx->currency,
						'source' => 'suggestion',
					]);
				}
			}
		}

		// 3) Manuell hinzufügen
		$manual_doc_id = isset($_POST['manual_doc_id']) ? absint($_POST['manual_doc_id']) : 0;
		$manual_amount = isset($_POST['manual_amount']) ? $this->parse_amount($_POST['manual_amount']) : 0.0;
		$manual_amount = max(0.0, $manual_amount);

		if ( $manual_doc_id > 0 && $manual_amount > 0 ) {
			$wpdb->insert(
				$pay_table,
				[
					'transaction_id' => $tx_id,
					'document_id'    => $manual_doc_id,
					'amount'         => $manual_amount,
					'currency'       => $tx->currency,
					'created_at'     => current_time('mysql'),
					'created_by'     => $user_id,
				]
			);

			$pid = (int) $wpdb->insert_id;
			$total_alloc += $manual_amount;

			$this->audit('payment_created', 'payment', $pid, [
				'transaction_id' => $tx_id,
				'document_id' => $manual_doc_id,
				'amount' => $manual_amount,
				'currency' => $tx->currency,
				'source' => 'manual',
			]);
		}

		// 4) Final Totals berechnen (aus DB, damit’s korrekt ist)
		$sum_db = (float) $wpdb->get_var(
			$wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$pay_table} WHERE transaction_id=%d", $tx_id)
		);

		$tx_amount = (float) $tx->amount;
		$rest = $tx_amount - $sum_db;

		// Status setzen
		$new_status = 'open';
		if ( $sum_db <= 0.0 ) {
			$new_status = 'open';
		} elseif ( abs($rest) < 0.01 ) {
			$new_status = 'assigned';
		} else {
			$new_status = 'partial';
		}

		$wpdb->update(
			$tx_table,
			[
				'status' => $new_status,
				'updated_at' => current_time('mysql'),
			],
			[ 'id' => $tx_id ]
		);

		$this->audit('transaction_status_set', 'transaction', (int)$tx_id, [
			'status' => $new_status,
			'sum_alloc' => $sum_db,
			'tx_amount' => $tx_amount,
			'rest' => $rest,
		]);

		wp_safe_redirect( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=open&notice=saved') );
		exit;
	}

	public function handle_add_opening() {
		if ( ! current_user_can(self::CAPABILITY) ) wp_die('Keine Berechtigung.');
		check_admin_referer('cm_bankabgleich_add_opening');

		global $wpdb;
		$tx_table = $wpdb->prefix . self::TX_TABLE;

		$type = isset($_POST['opening_type']) ? sanitize_text_field($_POST['opening_type']) : '';
		$date = isset($_POST['opening_date']) ? sanitize_text_field($_POST['opening_date']) : '';
		$amount = isset($_POST['opening_amount']) ? $this->parse_amount($_POST['opening_amount']) : 0.0;
		$currency = isset($_POST['opening_currency']) ? strtoupper(sanitize_text_field($_POST['opening_currency'])) : 'CHF';

		$categories = $this->get_tx_categories();
		if (!isset($categories[$type])) {
			$type = array_key_first($categories) ?: '';
		}

		$amount = max(0.0, $amount);
		if ($date === '') {
			$date = date('Y-m-d');
		}

		$text = $categories[$type] ?? 'Anfangsbestand';

		$wpdb->insert(
			$tx_table,
			[
				'source' => 'manual',
				'source_uid' => null,
				'booking_date' => $date,
				'amount' => $amount,
				'currency' => $currency,
				'text' => $text,
				'reference' => null,
				'iban' => null,
				'category' => $type,
				'raw_xml' => null,
				'status' => 'open',
				'created_at' => current_time('mysql'),
				'updated_at' => current_time('mysql'),
			]
		);

		wp_safe_redirect( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=open') );
		exit;
	}

	/* ------------------------------------------------------------
	 * Undo
	 * ------------------------------------------------------------ */
	public function handle_undo() {
		if ( ! current_user_can(self::CAPABILITY) ) wp_die('Keine Berechtigung.');
		check_admin_referer('cm_bankabgleich_undo');

		global $wpdb;

		$snapshot = $this->get_undo_snapshot();
		if ( ! $snapshot || empty($snapshot['tx_id']) ) {
			wp_safe_redirect( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=open') );
			exit;
		}

		$tx_id = (int) $snapshot['tx_id'];
		$tx_table  = $wpdb->prefix . self::TX_TABLE;
		$pay_table = $wpdb->prefix . self::PAY_TABLE;

		// Restore tx status
		$wpdb->update(
			$tx_table,
			[
				'status' => sanitize_key($snapshot['tx_status']),
				'updated_at' => current_time('mysql'),
			],
			[ 'id' => $tx_id ]
		);

		// Restore payments: lösche aktuelle, schreibe snapshot neu
		$wpdb->delete($pay_table, [ 'transaction_id' => $tx_id ]);

		if ( ! empty($snapshot['payments']) && is_array($snapshot['payments']) ) {
			foreach ( $snapshot['payments'] as $p ) {
				$wpdb->insert(
					$pay_table,
					[
						'transaction_id' => (int) $p['transaction_id'],
						'document_id'    => (int) $p['document_id'],
						'amount'         => (float) $p['amount'],
						'currency'       => (string) $p['currency'],
						'created_at'     => (string) $p['created_at'],
						'created_by'     => (int) $p['created_by'],
					]
				);
			}
		}

		$this->clear_undo_snapshot();

		$this->audit('undo', 'transaction', $tx_id, [
			'restored_status' => $snapshot['tx_status'],
			'restored_payments' => is_array($snapshot['payments']) ? count($snapshot['payments']) : 0,
		]);

		wp_safe_redirect( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=open&notice=undo') );
		exit;
	}

	private function undo_url() {
		return wp_nonce_url(
			admin_url('admin-post.php?action=cm_bankabgleich_undo'),
			'cm_bankabgleich_undo'
		);
	}

	private function set_undo_snapshot(array $snapshot) {
		$key = $this->undo_key();
		set_transient($key, $snapshot, self::UNDO_TTL);
	}

	private function get_undo_snapshot() {
		return get_transient($this->undo_key());
	}

	private function clear_undo_snapshot() {
		delete_transient($this->undo_key());
	}

	private function undo_key() {
		return 'cm_bankabgleich_undo_' . get_current_user_id();
	}

	/* ------------------------------------------------------------
	 * Unassign (Zahlung aufheben)
	 * ------------------------------------------------------------ */
	public function handle_unassign_payment() {
		if ( ! current_user_can(self::CAPABILITY) ) wp_die('Keine Berechtigung.');
		check_admin_referer('cm_bankabgleich_unassign_payment');

		global $wpdb;
		$pay_table = $wpdb->prefix . self::PAY_TABLE;
		$tx_table  = $wpdb->prefix . self::TX_TABLE;

		$payment_id = isset($_POST['payment_id']) ? absint($_POST['payment_id']) : 0;
		if ( ! $payment_id ) wp_die('payment_id fehlt.');

		$payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pay_table} WHERE id=%d", $payment_id));
		if ( ! $payment ) {
			wp_safe_redirect( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=payments') );
			exit;
		}

		$tx_id = (int) $payment->transaction_id;

		$wpdb->delete($pay_table, [ 'id' => $payment_id ]);

		// Transaction-Status neu berechnen
		$sum_db = (float) $wpdb->get_var(
			$wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$pay_table} WHERE transaction_id=%d", $tx_id)
		);

		$tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tx_table} WHERE id=%d", $tx_id));
		if ( $tx ) {
			$tx_amount = (float) $tx->amount;
			$rest = $tx_amount - $sum_db;

			$new_status = 'open';
			if ( $sum_db <= 0.0 ) {
				$new_status = 'open';
			} elseif ( abs($rest) < 0.01 ) {
				$new_status = 'assigned';
			} else {
				$new_status = 'partial';
			}

			$wpdb->update(
				$tx_table,
				[
					'status' => $new_status,
					'updated_at' => current_time('mysql'),
				],
				[ 'id' => $tx_id ]
			);
		}

		$this->audit('payment_unassigned', 'payment', $payment_id, [
			'transaction_id' => $tx_id,
			'document_id' => (int) $payment->document_id,
			'amount' => (float) $payment->amount,
			'currency' => (string) $payment->currency,
		]);

		wp_safe_redirect( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=open&notice=unassigned') );
		exit;
	}

	/* ------------------------------------------------------------
	 * CAMT Import + Auto-Matching
	 * ------------------------------------------------------------ */
	public function handle_import_camt() {
		if ( ! current_user_can(self::CAPABILITY) ) wp_die('Keine Berechtigung.');
		check_admin_referer('cm_bankabgleich_import_camt');

		if ( empty($_FILES['camt_file']['tmp_name']) ) {
			wp_die('Keine Datei.');
		}

		$xml_raw = file_get_contents($_FILES['camt_file']['tmp_name']);
		if ( ! $xml_raw ) {
			wp_die('Datei konnte nicht gelesen werden.');
		}

		$xml = simplexml_load_string($xml_raw);
		if ( ! $xml ) {
			wp_die('Ungültige CAMT XML.');
		}

		global $wpdb;
		$tx_table = $wpdb->prefix . self::TX_TABLE;

		$inserted_ids = [];

		// Sehr pragmatisch: wir versuchen camt.053/054 ähnliche Strukturen
		$entries = [];

		// camt.053: BkToCstmrStmt->Stmt->Ntry
		if ( isset($xml->BkToCstmrStmt->Stmt->Ntry) ) {
			$entries = $xml->BkToCstmrStmt->Stmt->Ntry;
		}
		// camt.054: BkToCstmrDbtCdtNtfctn->Ntfctn->Ntry
		if ( empty($entries) && isset($xml->BkToCstmrDbtCdtNtfctn->Ntfctn->Ntry) ) {
			$entries = $xml->BkToCstmrDbtCdtNtfctn->Ntfctn->Ntry;
		}

		if ( empty($entries) ) {
			wp_die('Keine Buchungen in der CAMT-Datei gefunden.');
		}

		foreach ( $entries as $entry ) {
			$booking_date = (string) ($entry->BookgDt->Dt ?? '');
			if ( ! $booking_date ) {
				$booking_date = (string) ($entry->BookgDt->DtTm ?? '');
				$booking_date = substr($booking_date, 0, 10);
			}

			$amount = (string) ($entry->Amt ?? '0');
			$currency = (string) ($entry->Amt['Ccy'] ?? 'CHF');

			$text = (string) ($entry->AddtlNtryInf ?? '');
			if ( ! $text && isset($entry->NtryDtls->TxDtls->RmtInf->Ustrd) ) {
				$text = (string) $entry->NtryDtls->TxDtls->RmtInf->Ustrd;
			}

			$ref = '';
			if ( isset($entry->NtryDtls->TxDtls->Refs->EndToEndId) ) {
				$ref = (string) $entry->NtryDtls->TxDtls->Refs->EndToEndId;
			}
			if ( ! $ref && isset($entry->NtryDtls->TxDtls->Refs->UETR) ) {
				$ref = (string) $entry->NtryDtls->TxDtls->Refs->UETR;
			}

			// Duplikat-Schutz: source_uid aus (booking_date|amount|ref|text) gehasht
			$uid_src = $booking_date . '|' . $amount . '|' . $currency . '|' . $ref . '|' . mb_substr($text, 0, 120);
			$source_uid = substr(sha1($uid_src), 0, 24);

			$exists = (int) $wpdb->get_var(
				$wpdb->prepare("SELECT COUNT(1) FROM {$tx_table} WHERE source_uid=%s", $source_uid)
			);
			if ( $exists > 0 ) {
				continue;
			}

			$wpdb->insert(
				$tx_table,
				[
					'source'      => 'camt',
					'source_uid'  => $source_uid,
					'booking_date'=> $booking_date ?: current_time('Y-m-d'),
					'amount'      => $this->parse_amount($amount),
					'currency'    => $currency ?: 'CHF',
					'text'        => $text,
					'reference'   => $ref,
					'iban'        => '',
					'raw_xml'     => $xml_raw,
					'status'      => 'open',
					'created_at'  => current_time('mysql'),
					'updated_at'  => null,
				]
			);

			$tx_id = (int) $wpdb->insert_id;
			if ( $tx_id ) {
				$inserted_ids[] = $tx_id;

				$this->audit('camt_import_tx', 'transaction', $tx_id, [
					'booking_date' => $booking_date,
					'amount' => $amount,
					'currency' => $currency,
					'reference' => $ref,
				]);
			}
		}

		// Auto-Matching direkt nach Import
		foreach ( $inserted_ids as $tx_id ) {
			$tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tx_table} WHERE id=%d", $tx_id));
			if ( $tx ) {
				$this->auto_match_transaction($tx);
			}
		}

		wp_safe_redirect( admin_url('admin.php?page='.self::MENU_SLUG.'&tab=transactions&status=open&notice=imported') );
		exit;
	}

	private function auto_match_transaction($tx) {
		// Matching = Kandidaten suchen, dann wenn genau 1 sehr plausibel → Auto-Allocation setzen
		$candidates = $this->find_document_candidates($tx);

		/**
		 * Filter: Kandidaten-Scoring/Auto-Match deaktivieren/ändern
		 * return: [
		 *   'auto_assign' => bool,
		 *   'allocations' => [ [ 'document_id' => int, 'amount' => float ] ... ],
		 * ]
		 */
		$decision = apply_filters('cm_bankabgleich_auto_match_decision', [
			'auto_assign' => false,
			'allocations' => [],
		], $tx, $candidates);

		// Default-Entscheid: wenn 1 Kandidat und Betrag passt exakt → auto_assign
		if ( empty($decision['allocations']) && count($candidates) === 1 ) {
			$only = $candidates[0];
			$tx_amount = (float) $tx->amount;
			if ( abs($tx_amount - (float)$only['amount']) < 0.01 ) {
				$decision['auto_assign'] = true;
				$decision['allocations'] = [
					[ 'document_id' => (int)$only['document_id'], 'amount' => $tx_amount ],
				];
			}
		}

		if ( empty($decision['auto_assign']) || empty($decision['allocations']) ) {
			return;
		}

		global $wpdb;
		$pay_table = $wpdb->prefix . self::PAY_TABLE;
		$tx_table  = $wpdb->prefix . self::TX_TABLE;

		// Nur wenn noch keine Zuordnungen existieren
		$existing = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(1) FROM {$pay_table} WHERE transaction_id=%d", (int)$tx->id
		));
		if ( $existing > 0 ) return;

		$sum = 0.0;
		foreach ( $decision['allocations'] as $alloc ) {
			$doc_id = isset($alloc['document_id']) ? (int) $alloc['document_id'] : 0;
			$amount = isset($alloc['amount']) ? (float) $alloc['amount'] : 0.0;
			if ( $doc_id <= 0 || $amount <= 0 ) continue;

			$wpdb->insert(
				$pay_table,
				[
					'transaction_id' => (int) $tx->id,
					'document_id'    => $doc_id,
					'amount'         => $amount,
					'currency'       => (string) $tx->currency,
					'created_at'     => current_time('mysql'),
					'created_by'     => 0,
				]
			);

			$pid = (int) $wpdb->insert_id;
			$sum += $amount;

			$this->audit('payment_created', 'payment', $pid, [
				'transaction_id' => (int)$tx->id,
				'document_id' => $doc_id,
				'amount' => $amount,
				'currency' => (string)$tx->currency,
				'source' => 'auto_match',
			]);
		}

		$tx_amount = (float) $tx->amount;
		$rest = $tx_amount - $sum;

		$new_status = (abs($rest) < 0.01) ? 'assigned' : 'partial';

		$wpdb->update(
			$tx_table,
			[
				'status' => $new_status,
				'updated_at' => current_time('mysql'),
			],
			[ 'id' => (int) $tx->id ]
		);

		$this->audit('auto_match', 'transaction', (int)$tx->id, [
			'status' => $new_status,
			'sum_alloc' => $sum,
			'tx_amount' => $tx_amount,
			'rest' => $rest,
			'candidates' => count($candidates),
		]);
	}

	/* ------------------------------------------------------------
	 * Candidate Search (Belege finden)
	 * ------------------------------------------------------------ */
	private function find_document_candidates($tx) {
		/**
		 * Du kannst hier Deine Beleglogik einklinken (CPT, eigene Tabellen etc.)
		 *
		 * Erwartetes Return-Format:
		 * [
		 *   [
		 *     'document_id' => 123,
		 *     'title' => 'R-2026-014 · Müller GmbH',
		 *     'amount' => 1250.00
		 *   ],
		 * ]
		 */
		$candidates = [];

		// Default: WooCommerce Orders (wenn WooCommerce aktiv ist)
		// - match by amount (order total)
		// - zusätzlich: reference im order meta (über Filter)
		$amount = (float) $tx->amount;

		$default_wc = $this->find_wc_order_candidates_by_amount($amount, (string)$tx->currency);

		foreach ( $default_wc as $o ) {
			$candidates[] = [
				'document_id' => (int) $o['document_id'],
				'title' => (string) $o['title'],
				'amount' => (float) $o['amount'],
			];
		}

		// Filter: final candidates
		$candidates = apply_filters('cm_bankabgleich_document_candidates', $candidates, $tx);

		return is_array($candidates) ? $candidates : [];
	}

	private function find_wc_order_candidates_by_amount($amount, $currency) {
		if ( ! function_exists('wc_get_orders') ) {
			return [];
		}

		// Suche: Orders, die nicht abgeschlossen sind (du passt das nach Bedarf an)
		$orders = wc_get_orders([
			'limit'   => 20,
			'status'  => [ 'pending', 'on-hold', 'processing' ],
			'orderby' => 'date',
			'order'   => 'DESC',
		]);

		$result = [];

		foreach ( $orders as $order ) {
			/** @var WC_Order $order */
			$total = (float) $order->get_total();
			if ( abs($total - (float)$amount) < 0.01 ) {
				$result[] = [
					'document_id' => (int) $order->get_id(),
					'title' => sprintf('Order #%d · %s %s', (int)$order->get_id(), $order->get_billing_first_name(), $order->get_billing_last_name()),
					'amount' => $total,
				];
			}
		}

		/**
		 * Filter: WC candidates erweitern (z.B. Reference-Match)
		 */
		return apply_filters('cm_bankabgleich_wc_candidates', $result, $amount, $currency);
	}

	/* ------------------------------------------------------------
	 * Audit Log (append-only)
	 * ------------------------------------------------------------ */
	private function audit($action, $object_type, $object_id, array $context = []) {
		global $wpdb;
		$table = $wpdb->prefix . self::AUDIT_TABLE;

		$wpdb->insert(
			$table,
			[
				'created_at'  => current_time('mysql'),
				'user_id'     => get_current_user_id(),
				'action'      => sanitize_key($action),
				'object_type' => sanitize_key($object_type),
				'object_id'   => (int) $object_id,
				'context'     => wp_json_encode($context, JSON_UNESCAPED_UNICODE),
			]
		);
	}

	/* ------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------ */
	private function parse_amount($value) {
		// akzeptiert "1'250.00" oder "1250.00" oder "1 250.00"
		$v = (string) $value;
		$v = str_replace(["\xc2\xa0", " "], '', $v);
		$v = str_replace("'", '', $v);
		$v = str_replace(',', '.', $v);
		return (float) $v;
	}

	private function fmt_money($amount, $currency) {
		$amount = (float) $amount;
		$currency = $currency ? strtoupper($currency) : 'CHF';
		// Schweizer Schreibweise: Apostroph für Tausender
		$formatted = number_format($amount, 2, '.', "'");
		return $currency . ' ' . $formatted;
	}

	private function get_tx_categories(): array {
		$base = [
			'gutschrift' => 'Gutschrift',
			'lieferantenrechnung' => 'Lieferantenrechnung',
			'rechnung' => 'Rechnung',
		];

		return $this->get_opening_options() + $base;
	}

	private function get_opening_options(): array {
		$accounts = function_exists(__NAMESPACE__ . '\\cmx_ini_get_value')
			? cmx_ini_get_value('Bankabgleich', 'Konten')
			: null;
		$accounts = is_array($accounts) ? $accounts : (array) $accounts;
		$accounts = array_values(array_filter(array_map('trim', $accounts)));

		if (empty($accounts)) {
			$accounts = ['Bank', 'Kasse'];
		}

		$options = [];
		foreach ($accounts as $name) {
			$key = 'anfangsbestand_' . sanitize_title($name);
			$options[$key] = 'Anfangsbestand ' . $name;
		}

		return $options;
	}

	private function num($amount) {
		$amount = (float) $amount;
		return number_format($amount, 2, '.', "'");
	}

	private function doc_label($document_id) {
		$document_id = (int) $document_id;

		if ( function_exists('wc_get_order') ) {
			$order = wc_get_order($document_id);
			if ( $order ) {
				return sprintf('Order #%d', $document_id);
			}
		}

		$post = get_post($document_id);
		if ( $post ) {
			return sprintf('%s #%d', $post->post_type, $document_id);
		}

		return 'Beleg #' . $document_id;
	}
}

new CM_Bankabgleich();

/**
 * OPTIONAL: Beispiel-Filter für Reference-Matching (wenn Du Referenzen in einem Meta-Feld speicherst)
 *
 * add_filter('cm_bankabgleich_document_candidates', function($candidates, $tx){
 *     // Beispiel: CPT "cm_beleg" mit Meta "_cm_ref"
 *     // Hier könntest Du per WP_Query/SQL Belege anhand $tx->reference suchen und Kandidaten hinzufügen.
 *     return $candidates;
 * }, 10, 2);
 */
