<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>

<div class="notice notice-warning" style="max-width: 800px; margin-left: 0;">
	<p><strong>Achtung:</strong> Das Wiederherstellen überschreibt die aktuelle Datenbank vollständig. Diese Aktion kann nicht rückgängig gemacht werden.</p>
</div>

<p style="max-width: 800px;">
	Lade eine SQL-Backup-Datei hoch (<code>.sql</code> oder <code>.sql.gz</code>), um die Datenbank wiederherzustellen.<br>
	Maximale Upload-Größe: <strong><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></strong>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		enctype="multipart/form-data" style="max-width: 800px;">
	<?php wp_nonce_field( 'egj_backup_action' ); ?>
	<input type="hidden" name="action" value="egj_restore_backup">
	<table class="form-table">
		<tr>
			<th scope="row"><label for="backup_file">Backup-Datei</label></th>
			<td>
				<input type="file" id="backup_file" name="backup_file" accept=".sql,.sql.gz">
			</td>
		</tr>
	</table>
	<p class="submit">
		<button type="submit" class="button button-primary"
				onclick="return confirm('Datenbank wirklich wiederherstellen? Alle aktuellen Daten werden überschrieben.');">
			Datenbank wiederherstellen
		</button>
	</p>
</form>
