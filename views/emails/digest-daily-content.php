<h2><?php echo esc_html( $email_title ); ?></h2>
<p>Hello,<br><?php echo esc_html( $intro_text ); ?> <strong><?php echo esc_html( $site_domain ); ?></strong>.</p>
<p><strong>Reporting period:</strong> <?php echo esc_html( $reporting_period ); ?></p>

<h3>Summary</h3>
<ul>
	<?php foreach ( $summary_items as $label => $value ) : ?>
		<li><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( (string) $value ); ?></li>
	<?php endforeach; ?>
</ul>

<p><a href="<?php echo esc_url( $dashboard_url ); ?>">Go to Dashboard</a></p>

<h3>Top IPs blocked</h3>
<ul>
	<?php if ( ! empty( $top_ips_rows ) ) : ?>
		<?php foreach ( $top_ips_rows as $row ) : ?>
			<li>
				<strong><?php echo esc_html( $row['ip'] ); ?></strong><br>
				Lockouts: <?php echo esc_html( (string) $row['lockouts'] ); ?>
				&#8226; Attempts: <?php echo esc_html( (string) $row['attempts'] ); ?>
				&#8226; Last seen: <?php echo esc_html( $row['last_seen'] ); ?>
				&#8226; Login URL: <?php echo esc_html( $row['top_url'] ); ?>
			</li>
		<?php endforeach; ?>
	<?php else : ?>
		<li>No IP activity in this period.</li>
	<?php endif; ?>
</ul>

<h3>Most targeted usernames</h3>
<ul>
	<?php if ( ! empty( $top_usernames_rows ) ) : ?>
		<?php foreach ( $top_usernames_rows as $row ) : ?>
			<li><?php echo esc_html( $row['username'] ); ?> (<?php echo esc_html( (string) $row['attempts'] ); ?>)</li>
		<?php endforeach; ?>
	<?php else : ?>
		<li>No targeted usernames in this period.</li>
	<?php endif; ?>
</ul>

<h3>What you should do next</h3>
<p>If you recognize an IP address or see repeated attempts against a specific username, open your dashboard to review the IP details and adjust allowlist or denylist rules if needed.</p>
<p><strong>Noticing consistent attack patterns?</strong><br>
	<a href="https://www.limitloginattempts.com">Premium</a> gives you deeper visibility and stronger protection with advanced IP intelligence, block by country, detailed login logs and monitoring, and automatic malicious IP detection.
</p>
<p style="font-size:12px;color:#666;">Don't want these notifications? <a href="<?php echo esc_url( $unsubscribe_url ); ?>">Unsubscribe</a>.</p>
