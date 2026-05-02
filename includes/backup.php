<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @SuppressWarnings(PHPMD.UnusedLocalVariable) */
function egj_backup_get_strategy(): string {
	if ( ! function_exists( 'exec' ) ) {
		return 'wpdb';
	}
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- capability check only, no output used
	exec( 'which mysqldump 2>/dev/null', $_, $code );
	return 0 === $code ? 'mysqldump' : 'wpdb';
}

function egj_backup_create(): string|\WP_Error {
	$result = egj_backup_try_mysqldump();
	if ( false !== $result ) {
		return $result;
	}
	return egj_backup_try_wpdb();
}

function egj_backup_parse_db_host( string $host ): array {
	if ( str_contains( $host, ':' ) ) {
		[ $hostname, $port ] = explode( ':', $host, 2 );
		return [
			'host' => $hostname,
			'port' => (int) $port,
		];
	}
	return [
		'host' => $host,
		'port' => 3306,
	];
}

/** @SuppressWarnings(PHPMD.UnusedLocalVariable) */
function egj_backup_try_mysqldump(): string|false {
	if ( ! function_exists( 'exec' ) ) {
		return false;
	}

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- exec() needed for mysqldump; guarded by function_exists() check
	exec( 'which mysqldump 2>/dev/null', $_, $code );
	if ( 0 !== $code ) {
		return false;
	}

	$filename = 'backup_' . gmdate( 'Y-m-d_His' ) . '_' . bin2hex( random_bytes( 4 ) ) . '.sql.gz';
	$filepath = EGJ_BACKUP_DIR . $filename;
	$db       = egj_backup_parse_db_host( DB_HOST );

	$cmd = sprintf(
		'mysqldump --single-transaction --routines --triggers --host=%s --port=%d -u %s -p%s %s 2>/dev/null | gzip -9 > %s',
		escapeshellarg( $db['host'] ),
		(int) $db['port'],
		escapeshellarg( DB_USER ),
		escapeshellarg( DB_PASSWORD ),
		escapeshellarg( DB_NAME ),
		escapeshellarg( $filepath )
	);

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- exec() needed for mysqldump pipeline
	exec( $cmd, $_, $return_code );

	if ( 0 !== $return_code || ! file_exists( $filepath ) || filesize( $filepath ) < 20 ) {
		if ( file_exists( $filepath ) ) {
			wp_delete_file( $filepath );
		}
		return false;
	}

	return $filepath;
}

function egj_backup_escape_value( mixed $val ): string {
	if ( null === $val ) {
		return 'NULL';
	}
	return "'" . str_replace(
		[ '\\',   "'",    "\0",   "\r",   "\n",   "\x1a" ],
		[ '\\\\', "\\'",  "\\0",  "\\r",  "\\n",  '\\Z' ],
		(string) $val
	) . "'";
}

function egj_backup_build_insert_rows( array $rows, array $col_names ): array {
	$values = [];
	foreach ( $rows as $row ) {
		$vals     = array_map(
			fn( string $col ) => egj_backup_escape_value( $row[ $col ] ),
			$col_names
		);
		$values[] = '(' . implode( ', ', $vals ) . ')';
	}
	return $values;
}

/**
 * @param \wpdb    $wpdb  WordPress database object.
 * @param resource $gz    Open gzip file handle from gzopen().
 * @param string   $table Table name.
 */
function egj_backup_dump_table( \wpdb $wpdb, $gz, string $table ): void {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
	if ( ! $create ) {
		return;
	}

	gzwrite( $gz, "DROP TABLE IF EXISTS `{$table}`;\n" );
	gzwrite( $gz, $create[1] . ";\n\n" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$columns   = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" ) ?? [];
	$col_names = array_column( (array) $columns, 'Field' );
	$col_list  = '`' . implode( '`, `', $col_names ) . '`';

	$offset = 0;
	$batch  = 100;
	do {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT * FROM `{$table}` LIMIT " . (int) $batch . ' OFFSET ' . (int) $offset,
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $rows ) ) {
			break;
		}
		$values = egj_backup_build_insert_rows( $rows, $col_names );
		gzwrite( $gz, "INSERT INTO `{$table}` ({$col_list}) VALUES\n" . implode( ",\n", $values ) . ";\n" );
		$offset   += $batch;
		$row_count = count( $rows );
	} while ( $row_count === $batch );

	gzwrite( $gz, "\n" );
}

function egj_backup_try_wpdb(): string|\WP_Error {
	global $wpdb;

	$filename = 'backup_' . gmdate( 'Y-m-d_His' ) . '_' . bin2hex( random_bytes( 4 ) ) . '.sql.gz';
	$filepath = EGJ_BACKUP_DIR . $filename;

	$gz = gzopen( $filepath, 'wb9' );
	if ( ! $gz ) {
		return new WP_Error( 'gzopen_failed', 'Backup-Datei konnte nicht erstellt werden.' );
	}

	gzwrite( $gz, "-- Erfindergeist Backup\n" );
	gzwrite( $gz, '-- Erstellt: ' . gmdate( 'Y-m-d H:i:s' ) . "\n" );
	gzwrite( $gz, '-- Datenbank: ' . DB_NAME . "\n\n" );
	gzwrite( $gz, "SET NAMES utf8mb4;\n" );
	gzwrite( $gz, "SET FOREIGN_KEY_CHECKS=0;\n\n" );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$tables = $wpdb->get_col( 'SHOW TABLES' ) ?? [];

	foreach ( $tables as $table ) {
		egj_backup_dump_table( $wpdb, $gz, $table );
	}

	gzwrite( $gz, "SET FOREIGN_KEY_CHECKS=1;\n" );
	gzclose( $gz );

	if ( ! file_exists( $filepath ) || filesize( $filepath ) < 20 ) {
		return new WP_Error( 'backup_empty', 'Das Backup ist leer oder konnte nicht erstellt werden.' );
	}

	return $filepath;
}

function egj_backup_list(): array {
	$files = glob( EGJ_BACKUP_DIR . '*.sql.gz' );
	if ( ! $files ) {
		return [];
	}
	usort( $files, fn( $a, $b ) => filemtime( $b ) <=> filemtime( $a ) );
	$result = [];
	foreach ( $files as $file ) {
		$result[] = [
			'name' => basename( $file ),
			'path' => $file,
			'size' => filesize( $file ),
			'date' => filemtime( $file ),
		];
	}
	return $result;
}

function egj_backup_get_safe_path( string $name ): string|false {
	$name = basename( sanitize_file_name( $name ) );
	if ( ! preg_match( '/^backup_\d{4}-\d{2}-\d{2}_\d{6}_[0-9a-f]{8}\.sql\.gz$/', $name ) ) {
		return false;
	}
	return EGJ_BACKUP_DIR . $name;
}

function egj_backup_delete( string $name ): bool {
	$path = egj_backup_get_safe_path( $name );
	if ( ! $path || ! file_exists( $path ) ) {
		return false;
	}
	wp_delete_file( $path );
	// @phpstan-ignore-next-line
	return ! file_exists( $path );
}
