<?php
/**
 * Template partial for SettingsPage::render(). Expects (from the calling
 * scope): $categories, $selected_category, $discovered, $completeness,
 * $current_map, $template_map, $usage_counts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Product Finder', 'product-finder' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Mapping saved.', 'product-finder' ); ?></p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="product-finder-settings" />
		<label for="product-finder-category"><?php esc_html_e( 'Category', 'product-finder' ); ?></label>
		<select name="category" id="product-finder-category" onchange="this.form.submit()">
			<?php foreach ( $categories as $category ) : ?>
				<option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $selected_category, $category->slug ); ?>>
					<?php echo esc_html( $category->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<noscript><?php submit_button( __( 'Choose category', 'product-finder' ), '', '', false ); ?></noscript>
	</form>

	<?php if ( $selected_category ) : ?>
		<h2><?php esc_html_e( 'Usage', 'product-finder' ); ?></h2>
		<p>
			<?php
			$views           = $usage_counts['view'] ?? 0;
			$zero_matches    = $usage_counts['zero_match'] ?? 0;
			$zero_match_rate = $views > 0 ? round( ( $zero_matches / $views ) * 100 ) : 0;
			printf(
				/* translators: 1: view count phrase (e.g. "1 view" or "5 views"), 2: zero-match count, 3: zero-match percentage */
				esc_html__( '%1$s, %2$d with no matching products (%3$d%%). Basic local counts only — see the plan for what a future hosted tier could add.', 'product-finder' ),
				esc_html(
					sprintf(
						/* translators: %d: number of views */
						_n( '%d view', '%d views', (int) $views, 'product-finder' ),
						(int) $views
					)
				),
				(int) $zero_matches,
				(int) $zero_match_rate
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( empty( $discovered ) ) : ?>
		<p><?php esc_html_e( 'No products with attributes were found in this category yet.', 'product-finder' ); ?></p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Attribute completeness', 'product-finder' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Attribute', 'product-finder' ); ?></th>
					<th><?php esc_html_e( 'Completeness', 'product-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $discovered as $attribute ) : ?>
					<?php $stats = $completeness[ $attribute['slug'] ] ?? null; ?>
					<tr>
						<td><?php echo esc_html( $attribute['label'] ); ?></td>
						<td>
							<?php
							if ( $stats ) {
								printf(
									/* translators: 1: number of products with this attribute set, 2: total products in category, 3: completeness percentage */
									esc_html__( '%1$d of %2$d products (%3$d%%)', 'product-finder' ),
									(int) $stats['set'],
									(int) $stats['total'],
									(int) $stats['percentage']
								);
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Map attributes', 'product-finder' ); ?></h2>
		<p><?php esc_html_e( 'Choose which of this category\'s WooCommerce attributes each finder attribute should read from. Leave a selection blank to use the starter template\'s default.', 'product-finder' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'product_finder_save_mapping' ); ?>
			<input type="hidden" name="product_finder_save_mapping" value="1" />
			<input type="hidden" name="category" value="<?php echo esc_attr( $selected_category ); ?>" />
			<table class="form-table">
				<?php foreach ( $template_map as $finder_attribute => $config ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $finder_attribute ); ?></th>
						<td>
							<select name="attribute_map[<?php echo esc_attr( $finder_attribute ); ?>]">
								<option value="">
									<?php
									printf(
										/* translators: %s: the template's default WooCommerce attribute slug */
										esc_html__( '— Use template default (%s) —', 'product-finder' ),
										esc_html( $config['slug'] )
									);
									?>
								</option>
								<?php foreach ( $discovered as $attribute ) : ?>
									<option value="<?php echo esc_attr( $attribute['slug'] ); ?>" <?php selected( $current_map[ $finder_attribute ] ?? '', $attribute['slug'] ); ?>>
										<?php echo esc_html( $attribute['label'] ); ?> (<?php echo esc_html( $attribute['slug'] ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Save mapping', 'product-finder' ) ); ?>
		</form>
	<?php endif; ?>
</div>
