<?php
/**
 * Plugin Name: Erfindergeist Backup
 * Description: Monatliches SQL-Backup der Datenbank mit Admin-Download und Restore-Funktion.
 * Version:     1.0.0
 * Author:      Lars 'vreezy' Eschweiler
 * Text Domain: erfindergeist
 * Domain Path: /languages
 *
 * @package Erfindergeist-Backup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vars.php';
require_once __DIR__ . '/includes/activator.php';
require_once __DIR__ . '/includes/deactivator.php';
require_once __DIR__ . '/includes/backup.php';
require_once __DIR__ . '/includes/restore.php';
require_once __DIR__ . '/includes/scheduler.php';
require_once __DIR__ . '/admin/main.php';

register_activation_hook( __FILE__, 'egj_backup_activate' );
register_deactivation_hook( __FILE__, 'egj_backup_deactivate' );
