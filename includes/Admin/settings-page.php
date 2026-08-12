<?php
/**
 * Template partial for SettingsPage::render(). Expects (from the calling
 * scope): $categories, $selected_category, $selected_category_name,
 * $discovered, $completeness, $current_map, $template_map, $usage_counts,
 * $has_custom_questions, $question_rows, $attribute_labels.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Product Finder', 'product-finder' ); ?></h1>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence-only check on a redirect flag this screen sets itself after a nonce-verified save; it changes nothing. ?>
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
		<p class="description">
			<?php
			printf(
				/* translators: %s: the selected WooCommerce product category's real name. */
				esc_html__( 'These fields come from the Outdoor Gear Finder starter template and are the same for every category — they don\'t adapt to "%s" specifically. Map whichever of this category\'s attributes fit best, or leave a field unmapped to use the template default.', 'product-finder' ),
				esc_html( $selected_category_name )
			);
			?>
		</p>
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

			<h2><?php esc_html_e( 'Questions', 'product-finder' ); ?></h2>
			<p class="description">
				<?php if ( $has_custom_questions ) : ?>
					<?php esc_html_e( 'This category has its own custom questions. Clear a row\'s attribute to remove that question, or change it to reassign the slot.', 'product-finder' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Pre-filled with the Outdoor Gear Finder starter template\'s questions — edit freely and save to make them custom to this category. Answer choices are the real values found on this category\'s products; save again after adding products or attribute values to refresh them.', 'product-finder' ); ?>
				<?php endif; ?>
			</p>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'product-finder' ); ?></th>
						<th><?php esc_html_e( 'Question text', 'product-finder' ); ?></th>
						<th><?php esc_html_e( 'Short label', 'product-finder' ); ?></th>
						<th><?php esc_html_e( 'Filter type', 'product-finder' ); ?></th>
						<th><?php esc_html_e( 'Comparator', 'product-finder' ); ?></th>
						<th><?php esc_html_e( 'Weight', 'product-finder' ); ?></th>
						<th><?php esc_html_e( 'Answer input', 'product-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $question_rows as $row_index => $row ) : ?>
						<?php
						$row_attribute   = $row['attribute'] ?? '';
						$row_rule_type   = $row['ruleType'] ?? 'soft';
						$row_comparator  = $row['comparator'] ?? 'equals';
						$row_input_type  = $row['input']['type'] ?? 'select';
						$row_options     = $row['input']['options'] ?? array();
						$field           = static fn( string $name ) => "questions[{$row_index}][{$name}]";
						?>
						<tr>
							<td>
								<select name="<?php echo esc_attr( $field( 'attribute' ) ); ?>">
									<option value=""><?php esc_html_e( '— Not used —', 'product-finder' ); ?></option>
									<?php foreach ( $attribute_labels as $attribute_key => $attribute_label ) : ?>
										<option value="<?php echo esc_attr( $attribute_key ); ?>" <?php selected( $row_attribute, $attribute_key ); ?>>
											<?php echo esc_html( $attribute_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<input type="text" class="regular-text" name="<?php echo esc_attr( $field( 'label' ) ); ?>" value="<?php echo esc_attr( $row['label'] ?? '' ); ?>" />
							</td>
							<td>
								<input type="text" name="<?php echo esc_attr( $field( 'shortLabel' ) ); ?>" value="<?php echo esc_attr( $row['shortLabel'] ?? '' ); ?>" />
							</td>
							<td>
								<label>
									<input type="radio" name="<?php echo esc_attr( $field( 'ruleType' ) ); ?>" value="hard" <?php checked( $row_rule_type, 'hard' ); ?> />
									<?php esc_html_e( 'Hard filter', 'product-finder' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( $field( 'ruleType' ) ); ?>" value="soft" <?php checked( $row_rule_type, 'soft' ); ?> />
									<?php esc_html_e( 'Soft preference', 'product-finder' ); ?>
								</label>
							</td>
							<td>
								<select name="<?php echo esc_attr( $field( 'comparator' ) ); ?>">
									<option value="gte" <?php selected( $row_comparator, 'gte' ); ?>><?php esc_html_e( 'At least', 'product-finder' ); ?></option>
									<option value="lte" <?php selected( $row_comparator, 'lte' ); ?>><?php esc_html_e( 'At most', 'product-finder' ); ?></option>
									<option value="equals" <?php selected( $row_comparator, 'equals' ); ?>><?php esc_html_e( 'Exactly', 'product-finder' ); ?></option>
								</select>
							</td>
							<td>
								<input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( $field( 'weight' ) ); ?>" value="<?php echo esc_attr( $row['weight'] ?? 1 ); ?>" />
							</td>
							<td>
								<label>
									<input type="radio" name="<?php echo esc_attr( $field( 'inputType' ) ); ?>" value="select" <?php checked( $row_input_type, 'select' ); ?> />
									<?php esc_html_e( 'Choices', 'product-finder' ); ?>
								</label>
								<label>
									<input type="radio" name="<?php echo esc_attr( $field( 'inputType' ) ); ?>" value="toggle" <?php checked( $row_input_type, 'toggle' ); ?> />
									<?php esc_html_e( 'Yes/no toggle', 'product-finder' ); ?>
								</label>
								<?php if ( 'toggle' === $row_input_type ) : ?>
									<br />
									<input type="number" step="any" class="small-text" name="<?php echo esc_attr( $field( 'toggleThreshold' ) ); ?>" value="<?php echo esc_attr( $row['input']['value'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Threshold', 'product-finder' ); ?>" />
								<?php elseif ( ! empty( $row_options ) ) : ?>
									<br />
									<small>
										<?php echo esc_html( implode( ', ', wp_list_pluck( $row_options, 'label' ) ) ); ?>
									</small>
								<?php elseif ( '' !== $row_attribute ) : ?>
									<br />
									<small><?php esc_html_e( 'No real values found yet for this attribute in this category.', 'product-finder' ); ?></small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save mapping and questions', 'product-finder' ) ); ?>
		</form>
	<?php endif; ?>
</div>
