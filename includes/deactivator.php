<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function egj_backup_deactivate(): void {
	egj_backup_unschedule();
}
