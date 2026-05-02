<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function egj_backup_menu(): void {
	if ( empty( $GLOBALS['admin_page_hooks']['erfindergeist'] ) ) {
		add_menu_page(
			'Erfindergeist',
			'Erfindergeist',
			'manage_options',
			'erfindergeist',
			'egj_backup_plugin_options',
			'dashicons-shield',
			80
		);
	}
	add_submenu_page(
		'erfindergeist',
		'Backup',
		'Backup',
		'manage_options',
		'egj-backup-submenu-handle',
		'egj_backup_settings_page'
	);
}
add_action( 'admin_menu', 'egj_backup_menu' );

function egj_backup_plugin_options(): void {
	echo '<div class="wrap"><h1>Erfindergeist</h1></div>';
}

function egj_backup_settings_page(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection, no state changes
	$tab          = sanitize_key( $_GET['tab'] ?? 'backups' );
	$allowed_tabs = [ 'backups', 'restore' ];
	if ( ! in_array( $tab, $allowed_tabs, true ) ) {
		$tab = 'backups';
	}
	?>
	<div class="wrap">
		<h1>Erfindergeist Backup</h1>
		<?php egj_backup_show_notice(); ?>
		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=egj-backup-submenu-handle&tab=backups' ) ); ?>"
				class="nav-tab <?php echo 'backups' === $tab ? 'nav-tab-active' : ''; ?>">Backups</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=egj-backup-submenu-handle&tab=restore' ) ); ?>"
				class="nav-tab <?php echo 'restore' === $tab ? 'nav-tab-active' : ''; ?>">Wiederherstellen</a>
		</nav>
		<div class="tab-content" style="margin-top: 20px;">
			<?php
			if ( 'backups' === $tab ) {
				include __DIR__ . '/partials/tab-backups.php';
			} else {
				include __DIR__ . '/partials/tab-restore.php';
			}
			?>
		</div>
	</div>
	<?php
}

function egj_backup_show_notice(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only; values are set by our own redirect
	if ( ! isset( $_GET['msg'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only; values are set by our own redirect
	$msg = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
	$type  = sanitize_key( $_GET['type'] ?? '' ) === 'error' ? 'error' : 'success';
	$class = 'error' === $type ? 'notice-error' : 'notice-success';
	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
}

/** @SuppressWarnings(PHPMD.ExitExpression) */
function egj_backup_redirect( string $tab, string $msg, string $type = 'success' ): never {
	wp_safe_redirect(
		add_query_arg(
			[
				'tab'  => $tab,
				'msg'  => rawurlencode( $msg ),
				'type' => $type,
			],
			admin_url( 'admin.php?page=egj-backup-submenu-handle' )
		)
	);
	exit;
}

add_action( 'admin_post_egj_run_backup', 'egj_backup_handle_run' );
add_action( 'admin_post_egj_delete_backup', 'egj_backup_handle_delete' );
add_action( 'admin_post_egj_download_backup', 'egj_backup_handle_download' );
add_action( 'admin_post_egj_restore_backup', 'egj_backup_handle_restore' );

function egj_backup_handle_run(): void {
	check_admin_referer( 'egj_backup_action' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nicht erlaubt.' );
	}
	$result = egj_backup_create();
	if ( is_wp_error( $result ) ) {
		egj_backup_redirect( 'backups', $result->get_error_message(), 'error' );
	}
	egj_backup_redirect( 'backups', 'Backup erfolgreich erstellt: ' . basename( $result ) );
}

function egj_backup_handle_delete(): void {
	check_admin_referer( 'egj_backup_action' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nicht erlaubt.' );
	}
	$name = sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) );
	if ( ! egj_backup_delete( $name ) ) {
		egj_backup_redirect( 'backups', 'Backup konnte nicht gelöscht werden.', 'error' );
	}
	egj_backup_redirect( 'backups', 'Backup gelöscht.' );
}

/** @SuppressWarnings(PHPMD.ExitExpression) */
function egj_backup_handle_download(): void {
	check_admin_referer( 'egj_backup_action' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nicht erlaubt.' );
	}
	$name = sanitize_file_name( wp_unslash( $_GET['file'] ?? '' ) );
	$path = egj_backup_get_safe_path( $name );
	if ( ! $path || ! file_exists( $path ) ) {
		wp_die( 'Backup-Datei nicht gefunden.' );
	}
	if ( ob_get_level() ) {
		ob_end_clean();
	}
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

function egj_backup_handle_restore(): void {
	check_admin_referer( 'egj_backup_action' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nicht erlaubt.' );
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated and sanitized inside egj_backup_restore_from_upload()
	if ( empty( $_FILES['backup_file'] ) || ! is_array( $_FILES['backup_file'] ) ) {
		egj_backup_redirect( 'restore', 'Keine Datei hochgeladen.', 'error' );
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated and sanitized inside egj_backup_restore_from_upload()
	$result = egj_backup_restore_from_upload( $_FILES['backup_file'] );
	if ( is_wp_error( $result ) ) {
		egj_backup_redirect( 'restore', $result->get_error_message(), 'error' );
	}
	egj_backup_redirect( 'restore', 'Datenbank erfolgreich wiederhergestellt.' );
}
