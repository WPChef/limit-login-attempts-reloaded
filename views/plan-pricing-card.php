<?php
/**
 * Plan pricing card (abstract view).
 *
 * @var string   $price              Current price, e.g. "$1.25/mo".
 * @var string   $price_old          Strikethrough price, e.g. "$2.50".
 * @var string   $billing_label      Billing note, e.g. "Billed Annually".
 * @var string   $save_badge         Savings badge text, e.g. "Save 50%".
 * @var string[] $description_items  Short plan description bullets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>
<div class="llar-plan-pricing">
	<div class="llar-plan-pricing__price-row">
		<span class="llar-plan-pricing__price"><?php echo esc_html( $price ); ?></span>
		<?php
		if ( ! empty( $price_old ) ) {
			?>
			<span class="llar-plan-pricing__price-old"><?php echo esc_html( $price_old ); ?></span>
			<?php
		}
		?>
	</div>
	<?php
	if ( ! empty( $billing_label ) || ! empty( $save_badge ) ) {
		?>
		<div class="llar-plan-pricing__billing">
			<?php
			if ( ! empty( $billing_label ) ) {
				?>
				<span class="llar-plan-pricing__billing-label"><?php echo esc_html( $billing_label ); ?></span>
				<?php
			}
			if ( ! empty( $save_badge ) ) {
				?>
				<span class="llar-plan-pricing__save"><?php echo esc_html( $save_badge ); ?></span>
				<?php
			}
			?>
		</div>
		<?php
	}
	if ( ! empty( $description_items ) && is_array( $description_items ) ) {
		?>
		<ul class="llar-plan-pricing__list">
			<?php
			foreach ( $description_items as $item ) {
				?>
				<li><?php echo esc_html( $item ); ?></li>
				<?php
			}
			?>
		</ul>
		<?php
	}
	?>
</div>
