<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function egj_backup_add_monthly_schedule( array $schedules ): array {
	$schedules['egj_backup_monthly'] = [
		'interval' => 30 * DAY_IN_SECONDS,
		'display'  => 'Einmal monatlich',
	];
	return $schedules;
}
add_filter( 'cron_schedules', 'egj_backup_add_monthly_schedule' );

function egj_backup_notify_error( string $message ): void {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only way to report errors from background cron jobs
	error_log( 'EGJ Backup: ' . $message );

	$emails = get_users(
		[
			'role'   => 'administrator',
			'fields' => 'user_email',
		]
	);
	if ( empty( $emails ) ) {
		return;
	}

	wp_mail(
		$emails,
		'[EGJ Backup] Fehler aufgetreten',
		$message
	);
}

function egj_backup_run_scheduled(): void {
	$result = egj_backup_create();
	if ( is_wp_error( $result ) ) {
		egj_backup_notify_error( 'Geplantes Backup fehlgeschlagen — ' . $result->get_error_message() );
	}
}
add_action( 'egj_backup_monthly_event', 'egj_backup_run_scheduled' );

function egj_backup_schedule(): void {
	if ( ! wp_next_scheduled( 'egj_backup_monthly_event' ) ) {
		wp_schedule_event( time(), 'egj_backup_monthly', 'egj_backup_monthly_event' );
	}
}

function egj_backup_unschedule(): void {
	$ts = wp_next_scheduled( 'egj_backup_monthly_event' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'egj_backup_monthly_event' );
	}
}
