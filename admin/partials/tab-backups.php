<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>

<p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'egj_backup_action' ); ?>
		<input type="hidden" name="action" value="egj_run_backup">
		<button type="submit" class="button button-primary">Backup jetzt erstellen</button>
	</form>
</p>

<?php
$strategy = egj_backup_get_strategy();
if ( 'mysqldump' === $strategy ) {
	echo '<p>Backup-Methode: <span style="color:#46b450;">&#9679;</span> <strong>mysqldump</strong> <em>(schnell &amp; vollständig)</em></p>';
} else {
	echo '<p>Backup-Methode: <span style="color:#ffb900;">&#9679;</span> <strong>PHP / wpdb</strong> <em>(exec() nicht verfügbar oder mysqldump nicht gefunden)</em></p>';
}
?>

<?php
$next = wp_next_scheduled( 'egj_backup_monthly_event' );
if ( $next ) {
	echo '<p>Nächstes geplantes Backup: <strong>' . esc_html( date_i18n( 'd.m.Y H:i', $next ) ) . '</strong></p>';
}

$backups = egj_backup_list();
?>

<?php if ( empty( $backups ) ) : ?>
	<p>Noch keine Backups vorhanden.</p>
<?php else : ?>
	<table class="widefat striped" style="max-width: 800px;">
		<thead>
			<tr>
				<th>Datei</th>
				<th>Größe</th>
				<th>Erstellt</th>
				<th>Aktionen</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $backups as $backup ) : ?>
				<tr>
					<td><?php echo esc_html( $backup['name'] ); ?></td>
					<td><?php echo esc_html( size_format( $backup['size'] ) ); ?></td>
					<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', $backup['date'] ) ); ?></td>
					<td>
						<a href="
						<?php
						echo esc_url(
							wp_nonce_url(
								admin_url( 'admin-post.php?action=egj_download_backup&file=' . rawurlencode( $backup['name'] ) ),
								'egj_backup_action'
							)
						);
						?>
						" class="button button-small">Herunterladen</a>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								style="display: inline;"
								onsubmit="return confirm('Backup wirklich löschen?');">
							<?php wp_nonce_field( 'egj_backup_action' ); ?>
							<input type="hidden" name="action" value="egj_delete_backup">
							<input type="hidden" name="file" value="<?php echo esc_attr( $backup['name'] ); ?>">
							<button type="submit" class="button button-small button-link-delete">Löschen</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
