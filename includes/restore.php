<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PhpMyAdmin\SqlParser\Utils\BufferedQuery;

function egj_backup_validate_upload_file( array $file ): true|\WP_Error {
	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		return new WP_Error( 'upload_error', 'Fehler beim Datei-Upload (Code ' . (int) $file['error'] . ').' );
	}
	$name_lower = strtolower( $file['name'] );
	if ( ! str_ends_with( $name_lower, '.sql.gz' ) && ! str_ends_with( $name_lower, '.sql' ) ) {
		return new WP_Error( 'invalid_type', 'Nur .sql und .sql.gz Dateien sind erlaubt.' );
	}
	return true;
}

function egj_backup_check_file_header( string $tmp ): true|\WP_Error {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- WP_Filesystem unavailable for uploaded temp files
	$handle = fopen( $tmp, 'rb' );
	if ( false === $handle ) {
		return new WP_Error( 'file_open_failed', 'Die hochgeladene Datei konnte nicht gelesen werden.' );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread,WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	$header = fread( $handle, 64 );
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	if ( str_contains( $header, '<?php' ) || str_contains( $header, '<?=' ) ) {
		return new WP_Error( 'invalid_content', 'Die Datei enthält ungültigen Inhalt.' );
	}
	return true;
}

function egj_backup_read_file_content( string $tmp, bool $is_gz ): string|\WP_Error {
	if ( $is_gz ) {
		$content = gzdecode( file_get_contents( $tmp ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $content ) {
			return new WP_Error( 'decompress_failed', 'Die .gz-Datei konnte nicht entpackt werden.' );
		}
		return $content;
	}
	return (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

function egj_backup_read_upload_sql( array $file ): string|\WP_Error {
	$validation = egj_backup_validate_upload_file( $file );
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$header_check = egj_backup_check_file_header( $file['tmp_name'] );
	if ( is_wp_error( $header_check ) ) {
		return $header_check;
	}

	$is_gz = str_ends_with( strtolower( $file['name'] ), '.sql.gz' );
	$sql   = egj_backup_read_file_content( $file['tmp_name'], $is_gz );
	if ( is_wp_error( $sql ) ) {
		return $sql;
	}

	if ( empty( $sql ) ) {
		return new WP_Error( 'empty_file', 'Die SQL-Datei ist leer.' );
	}

	return $sql;
}

function egj_backup_execute_sql_statements( array $statements ): array {
	global $wpdb;

	$errors = [];
	foreach ( $statements as $stmt ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $wpdb->query( $stmt ) ) {
			$errors[] = substr( $stmt, 0, 80 ) . '...';
			if ( count( $errors ) >= 5 ) {
				break;
			}
		}
	}
	return $errors;
}

function egj_backup_restore_from_upload( array $file ): true|\WP_Error {
	// phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- restore of large SQL backups requires more than WP_MAX_MEMORY_LIMIT
	ini_set( 'memory_limit', '512M' );

	$sql = egj_backup_read_upload_sql( $file );
	if ( is_wp_error( $sql ) ) {
		return $sql;
	}

	$current_siteurl = get_option( 'siteurl' );
	$current_home    = get_option( 'home' );
	$current_plugins = get_option( 'active_plugins' );

	$bq        = new BufferedQuery();
	$bq->query = $sql;
	unset( $sql );

	$statements = [];
	// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- standard iterator pattern for BufferedQuery
	while ( $stmt = $bq->extract() ) {
		if ( '' !== trim( $stmt ) ) {
			$statements[] = trim( $stmt );
		}
	}

	$errors = egj_backup_execute_sql_statements( $statements );

	update_option( 'siteurl', $current_siteurl );
	update_option( 'home', $current_home );
	update_option( 'active_plugins', $current_plugins );

	wp_cache_flush();
	flush_rewrite_rules();

	if ( ! empty( $errors ) ) {
		return new WP_Error( 'sql_errors', 'Einige SQL-Statements schlugen fehl: ' . implode( '; ', $errors ) );
	}

	return true;
}
