<?php
/**
 * Active Lockouts table body fragment.
 *
 * Expects $mode ('empty'|'rows') and $rows from ActiveLockoutsPresenter.
 *
 * @package LimitLoginAttempts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( 'empty' === $mode ) :
	?>
	<tr class="empty-row">
		<td colspan="4"
			style="text-align: center"><?php _e( 'No lockouts yet.', 'limit-login-attempts-reloaded' ); ?></td>
	</tr>
	<?php
	return;
endif;

foreach ( $rows as $row ) :
	?>
	<tr>
		<td><?php echo esc_html( $row['ip'] ); ?></td>
		<td><?php echo esc_html( $row['login'] ); ?></td>
		<td><?php echo esc_html( $row['count'] ); ?></td>
		<td><?php echo esc_html( $row['expires_minutes'] ); ?></td>
	</tr>
	<?php
endforeach;
