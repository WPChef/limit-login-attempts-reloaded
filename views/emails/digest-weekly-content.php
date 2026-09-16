<h2><?php echo esc_html( $email_title ); ?></h2>

<p><?php echo esc_html__( 'Hello', 'limit-login-attempts-reloaded' ); ?>,</p>
<?php if ( ! empty( $intro_text ) ) : ?>
<p><?php echo esc_html( $intro_text ); ?> <strong><?php echo esc_html( $site_domain ); ?></strong>.</p>
<?php endif; ?>

<p><?php echo esc_html( $reporting_period ); ?> <?php esc_html_e( 'from Limit Login Attempts Security for', 'limit-login-attempts-reloaded' ); ?> <strong><?php echo esc_html( $site_domain ); ?></strong></p>

<h3><?php esc_html_e( 'Summary', 'limit-login-attempts-reloaded' ); ?></h3>
<ul>
	<?php foreach ( $summary_items as $label => $value ) : ?>
		<li>
			<strong><?php echo esc_html( $label ); ?>:</strong>
			<?php if ( 'Most attempted IP' === $label ) : ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php echo esc_html( (string) $value ); ?></a>
			<?php else : ?>
				<?php echo esc_html( (string) $value ); ?>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>

<h3><?php esc_html_e( 'Top IPs blocked', 'limit-login-attempts-reloaded' ); ?></h3>
<ul>
	<?php if ( ! empty( $top_ips_rows ) ) : ?>
		<?php foreach ( $top_ips_rows as $row ) : ?>
			<li>
				<strong><?php echo esc_html( $row['ip'] ); ?></strong><br>
				<?php esc_html_e( 'Lockouts', 'limit-login-attempts-reloaded' ); ?>: <?php echo esc_html( (string) $row['lockouts'] ); ?>
				&#8226; <?php esc_html_e( 'Attempts', 'limit-login-attempts-reloaded' ); ?>: <?php echo esc_html( (string) $row['attempts'] ); ?>
				&#8226; <?php esc_html_e( 'Last seen', 'limit-login-attempts-reloaded' ); ?>: <?php echo esc_html( $row['last_seen'] ); ?>
				&#8226; <?php esc_html_e( 'Gateway', 'limit-login-attempts-reloaded' ); ?>: <?php echo esc_html( $row['top_url'] ); ?>
			</li>
		<?php endforeach; ?>
	<?php else : ?>
		<li><?php esc_html_e( 'No IP activity in this period.', 'limit-login-attempts-reloaded' ); ?></li>
	<?php endif; ?>
</ul>

<h3><?php esc_html_e( 'Most targeted usernames', 'limit-login-attempts-reloaded' ); ?></h3>
<ul>
	<?php if ( ! empty( $top_usernames_rows ) ) : ?>
		<?php foreach ( $top_usernames_rows as $row ) : ?>
			<li><?php echo esc_html( $row['username'] ); ?> (<?php echo esc_html( (string) $row['attempts'] ); ?>)</li>
		<?php endforeach; ?>
	<?php else : ?>
		<li><?php esc_html_e( 'No targeted usernames in this period.', 'limit-login-attempts-reloaded' ); ?></li>
	<?php endif; ?>
</ul>

<?php include LLA_PLUGIN_DIR . 'views/emails/digest-next-steps.php'; ?>
