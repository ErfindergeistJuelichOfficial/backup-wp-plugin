<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function egj_backup_activate(): void {
	if ( ! file_exists( EGJ_BACKUP_DIR ) ) {
		wp_mkdir_p( EGJ_BACKUP_DIR );
	}

	$htaccess = EGJ_BACKUP_DIR . '.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions
			$htaccess,
			"<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
			"<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
		);
	}

	$silence = EGJ_BACKUP_DIR . 'index.php';
	if ( ! file_exists( $silence ) ) {
		file_put_contents( $silence, '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	egj_backup_schedule();
}
