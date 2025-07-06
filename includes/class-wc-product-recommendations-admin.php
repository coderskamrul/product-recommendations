<?php
/**
 * Admin functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PROREEN_Product_Recommendations_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'woocommerce_product_options_related', array( $this, 'add_product_recommendations_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_recommendations_fields' ) );
	}

	/**
	 * Add admin menu
	 */
	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Product Recommendations', 'product-recommendations' ),
			__( 'Recommendations', 'product-recommendations' ),
			'manage_woocommerce',
			'product-recommendations',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Sanitize plugin settings before saving.
	 *
	 * @param array $settings The submitted settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $settings ) {
		$sanitized = array();

		// General settings
		$sanitized['enabled']       = isset( $settings['enabled'] ) && $settings['enabled'] === 'yes' ? 'yes' : 'no';
		$sanitized['active_engine'] = sanitize_text_field( $settings['active_engine'] ?? '' );

		// Display options
		$sanitized['show_on_product']     = $settings['show_on_product'] === 'yes' ? 'yes' : 'no';
		$sanitized['show_on_cart']        = $settings['show_on_cart'] === 'yes' ? 'yes' : 'no';
		$sanitized['show_on_checkout']    = $settings['show_on_checkout'] === 'yes' ? 'yes' : 'no';
		$sanitized['max_recommendations'] = absint( $settings['max_recommendations'] ?? 4 );

		// Content engine settings
		if ( isset( $settings['content_engine'] ) && is_array( $settings['content_engine'] ) ) {
			$sanitized['content_engine'] = array(
				'match_categories' => $settings['content_engine']['match_categories'] === 'yes' ? 'yes' : 'no',
				'match_tags'       => $settings['content_engine']['match_tags'] === 'yes' ? 'yes' : 'no',
				'match_attributes' => $settings['content_engine']['match_attributes'] === 'yes' ? 'yes' : 'no',
				'sort_by'          => sanitize_text_field( $settings['content_engine']['sort_by'] ?? '' ),
				'exclude_current'  => $settings['content_engine']['exclude_current'] === 'yes' ? 'yes' : 'no',
			);
		}

		// Association engine settings
		if ( isset( $settings['association_engine'] ) && is_array( $settings['association_engine'] ) ) {
			$sanitized['association_engine'] = array(
				'min_support'    => absint( $settings['association_engine']['min_support'] ?? 0 ),
				'use_views'      => $settings['association_engine']['use_views'] === 'yes' ? 'yes' : 'no',
				'days_back'      => absint( $settings['association_engine']['days_back'] ?? 365 ),
				'min_confidence' => floatval( $settings['association_engine']['min_confidence'] ?? 0 ),
			);
		}

		// Display settings
		if ( isset( $settings['display_settings'] ) && is_array( $settings['display_settings'] ) ) {
			$sanitized['display_settings'] = array(
				'title'            => sanitize_text_field( $settings['display_settings']['title'] ?? '' ),
				'columns'          => absint( $settings['display_settings']['columns'] ?? 4 ),
				'show_price'       => $settings['display_settings']['show_price'] === 'yes' ? 'yes' : 'no',
				'show_rating'      => $settings['display_settings']['show_rating'] === 'yes' ? 'yes' : 'no',
				'show_add_to_cart' => $settings['display_settings']['show_add_to_cart'] === 'yes' ? 'yes' : 'no',
			);
		}

		return $sanitized;
	}

	/**
	 * Initialize admin settings
	 */
	public function admin_init() {
		register_setting(
			'proreen_product_recommendations_settings',
			'proreen_product_recommendations_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
		// General Settings Section.
		add_settings_section(
			'proreen_product_recommendations_general',
			__( 'Setting Configuration', 'product-recommendations' ),
			array( $this, 'general_section_callback' ),
			'proreen_product_recommendations_settings'
		);

		// Engine Settings Section.
		add_settings_section(
			'proreen_product_recommendations_engines',
			__( 'Recommendation Engines', 'product-recommendations' ),
			array( $this, 'engines_section_callback' ),
			'proreen_product_recommendations_settings'
		);

		// Display Settings Section.
		add_settings_section(
			'proreen_product_recommendations_display',
			__( 'Display Settings', 'product-recommendations' ),
			array( $this, 'display_section_callback' ),
			'proreen_product_recommendations_settings'
		);

		$this->add_settings_fields();
	}

	/**
	 * Add settings fields
	 */
	private function add_settings_fields() {
		// General fields
		add_settings_field(
			'enabled',
			__( 'Enable Recommendations', 'product-recommendations' ),
			array( $this, 'checkbox_field' ),
			'proreen_product_recommendations_settings',
			'proreen_product_recommendations_general',
			array(
				'field'       => 'enabled',
				'description' => __( 'Enable product recommendations globally', 'product-recommendations' ),
			)
		);

		add_settings_field(
			'active_engine',
			__( 'Active Engine', 'product-recommendations' ),
			array( $this, 'select_field' ),
			'proreen_product_recommendations_settings',
			'proreen_product_recommendations_general',
			array(
				'field'       => 'active_engine',
				'options'     => array(
					'content'     => __( 'Content-Based (Categories/Tags)', 'product-recommendations' ),
					'association' => __( 'Association-Based (Frequently Bought Together)', 'product-recommendations' ),
					'hybrid'      => __( 'Hybrid (Both Engines)', 'product-recommendations' ),
				),
				'description' => __( 'Choose which recommendation engine to use', 'product-recommendations' ),
			)
		);

		add_settings_field(
			'show_locations',
			__( 'Display Locations', 'product-recommendations' ),
			array( $this, 'checkbox_group_field' ),
			'proreen_product_recommendations_settings',
			'proreen_product_recommendations_general',
			array(
				'fields' => array(
					'show_on_product'  => __( 'Single Product Page', 'product-recommendations' ),
					'show_on_cart'     => __( 'Cart Page', 'product-recommendations' ),
					'show_on_checkout' => __( 'Checkout Page', 'product-recommendations' ),
				),
			)
		);

		add_settings_field(
			'max_recommendations',
			__( 'Maximum Recommendations', 'product-recommendations' ),
			array( $this, 'number_field' ),
			'proreen_product_recommendations_settings',
			'proreen_product_recommendations_general',
			array(
				'field'       => 'max_recommendations',
				'min'         => 1,
				'max'         => 20,
				'description' => __( 'Maximum number of products to recommend', 'product-recommendations' ),
			)
		);
	}

	/**
	 * Admin page
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="wc-product-recommendations-admin">
				<div class="nav-tab-wrapper">
					<a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'product-recommendations' ); ?></a>
					<a href="#engines" class="nav-tab"><?php esc_html_e( 'Engines', 'product-recommendations' ); ?></a>
					<a href="#display" class="nav-tab"><?php esc_html_e( 'Display', 'product-recommendations' ); ?></a>
					<a href="#tools" class="nav-tab"><?php esc_html_e( 'Tools', 'product-recommendations' ); ?></a>
				</div>
				
				<form method="post" action="options.php">
					<?php settings_fields( 'proreen_product_recommendations_settings' ); ?>
					
					<div id="general" class="tab-content active">
						<h2><?php esc_html_e( 'General Settings', 'product-recommendations' ); ?></h2>
						<?php do_settings_sections( 'proreen_product_recommendations_settings' ); ?>
					</div>
					
					<div id="engines" class="tab-content">
						<h2><?php esc_html_e( 'Engine Configuration', 'product-recommendations' ); ?></h2>
						<?php $this->render_engine_settings(); ?>
					</div>
					
					<div id="display" class="tab-content">
						<h2><?php esc_html_e( 'Display Settings', 'product-recommendations' ); ?></h2>
						<?php $this->render_display_settings(); ?>
					</div>
					
					<div id="tools" class="tab-content">
						<h2><?php esc_html_e( 'Tools & Maintenance', 'product-recommendations' ); ?></h2>
						<?php $this->render_tools(); ?>
					</div>
					
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render engine settings
	 */
	private function render_engine_settings() {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Content Engine Settings', 'product-recommendations' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="proreen_product_recommendations_settings[content_engine][match_categories]" value="yes" <?php checked( isset( $settings['content_engine']['match_categories'] ) ? $settings['content_engine']['match_categories'] : 'yes', 'yes' ); ?>>
							<?php esc_html_e( 'Match by Categories', 'product-recommendations' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="proreen_product_recommendations_settings[content_engine][match_tags]" value="yes" <?php checked( isset( $settings['content_engine']['match_tags'] ) ? $settings['content_engine']['match_tags'] : 'yes', 'yes' ); ?>>
							<?php esc_html_e( 'Match by Tags', 'product-recommendations' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="proreen_product_recommendations_settings[content_engine][match_attributes]" value="yes" <?php checked( isset( $settings['content_engine']['match_attributes'] ) ? $settings['content_engine']['match_attributes'] : 'no', 'yes' ); ?>>
							<?php esc_html_e( 'Match by Attributes', 'product-recommendations' ); ?>
						</label><br>
						<label>
							<?php esc_html_e( 'Sort by:', 'product-recommendations' ); ?>
							<select name="proreen_product_recommendations_settings[content_engine][sort_by]">
								<option value="popularity" <?php selected( isset( $settings['content_engine']['sort_by'] ) ? $settings['content_engine']['sort_by'] : 'popularity', 'popularity' ); ?>><?php esc_html_e( 'Popularity', 'product-recommendations' ); ?></option>
								<option value="rating" <?php selected( isset( $settings['content_engine']['sort_by'] ) ? $settings['content_engine']['sort_by'] : 'popularity', 'rating' ); ?>><?php esc_html_e( 'Rating', 'product-recommendations' ); ?></option>
								<option value="price_low" <?php selected( isset( $settings['content_engine']['sort_by'] ) ? $settings['content_engine']['sort_by'] : 'popularity', 'price_low' ); ?>><?php esc_html_e( 'Price: Low to High', 'product-recommendations' ); ?></option>
								<option value="price_high" <?php selected( isset( $settings['content_engine']['sort_by'] ) ? $settings['content_engine']['sort_by'] : 'popularity', 'price_high' ); ?>><?php esc_html_e( 'Price: High to Low', 'product-recommendations' ); ?></option>
								<option value="date" <?php selected( isset( $settings['content_engine']['sort_by'] ) ? $settings['content_engine']['sort_by'] : 'popularity', 'date' ); ?>><?php esc_html_e( 'Newest First', 'product-recommendations' ); ?></option>
							</select>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Association Engine Settings', 'product-recommendations' ); ?></th>
				<td>
					<fieldset>
						<label>
							<?php esc_html_e( 'Minimum Support (co-purchases):', 'product-recommendations' ); ?>
							<input type="number" name="proreen_product_recommendations_settings[association_engine][min_support]" value="<?php echo esc_attr( isset( $settings['association_engine']['min_support'] ) ? $settings['association_engine']['min_support'] : 2 ); ?>" min="1" max="100">
						</label><br>
						<label>
							<?php esc_html_e( 'Days to look back:', 'product-recommendations' ); ?>
							<input type="number" name="proreen_product_recommendations_settings[association_engine][days_back]" value="<?php echo esc_attr( isset( $settings['association_engine']['days_back'] ) ? $settings['association_engine']['days_back'] : 365 ); ?>" min="30" max="3650">
						</label><br>
						<label>
							<?php esc_html_e( 'Minimum Confidence:', 'product-recommendations' ); ?>
							<input type="number" name="proreen_product_recommendations_settings[association_engine][min_confidence]" value="<?php echo esc_attr( isset( $settings['association_engine']['min_confidence'] ) ? $settings['association_engine']['min_confidence'] : 0.1 ); ?>" min="0.01" max="1" step="0.01">
						</label><br>
						<label>
							<input type="checkbox" name="proreen_product_recommendations_settings[association_engine][use_views]" value="yes" <?php checked( isset( $settings['association_engine']['use_views'] ) ? $settings['association_engine']['use_views'] : 'no', 'yes' ); ?>>
							<?php esc_html_e( 'Include view history (requires tracking)', 'product-recommendations' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render display settings
	 */
	private function render_display_settings() {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Section Title', 'product-recommendations' ); ?></th>
				<td>
					<input type="text" name="proreen_product_recommendations_settings[display_settings][title]" value="<?php echo esc_attr( isset( $settings['display_settings']['title'] ) ? $settings['display_settings']['title'] : 'You might also like' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Title displayed above recommendations', 'product-recommendations' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Columns', 'product-recommendations' ); ?></th>
				<td>
					<select name="proreen_product_recommendations_settings[display_settings][columns]">
						<option value="2" <?php selected( isset( $settings['display_settings']['columns'] ) ? $settings['display_settings']['columns'] : 4, 2 ); ?>>2</option>
						<option value="3" <?php selected( isset( $settings['display_settings']['columns'] ) ? $settings['display_settings']['columns'] : 4, 3 ); ?>>3</option>
						<option value="4" <?php selected( isset( $settings['display_settings']['columns'] ) ? $settings['display_settings']['columns'] : 4, 4 ); ?>>4</option>
						<option value="5" <?php selected( isset( $settings['display_settings']['columns'] ) ? $settings['display_settings']['columns'] : 4, 5 ); ?>>5</option>
						<option value="6" <?php selected( isset( $settings['display_settings']['columns'] ) ? $settings['display_settings']['columns'] : 4, 6 ); ?>>6</option>
					</select>
					<p class="description"><?php esc_html_e( 'Number of columns to display recommendations in', 'product-recommendations' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Display Options', 'product-recommendations' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="proreen_product_recommendations_settings[display_settings][show_price]" value="yes" <?php checked( isset( $settings['display_settings']['show_price'] ) ? $settings['display_settings']['show_price'] : 'yes', 'yes' ); ?>>
							<?php esc_html_e( 'Show Price', 'product-recommendations' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="proreen_product_recommendations_settings[display_settings][show_rating]" value="yes" <?php checked( isset( $settings['display_settings']['show_rating'] ) ? $settings['display_settings']['show_rating'] : 'yes', 'yes' ); ?>>
							<?php esc_html_e( 'Show Rating', 'product-recommendations' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="proreen_product_recommendations_settings[display_settings][show_add_to_cart]" value="yes" <?php checked( isset( $settings['display_settings']['show_add_to_cart'] ) ? $settings['display_settings']['show_add_to_cart'] : 'yes', 'yes' ); ?>>
							<?php esc_html_e( 'Show Add to Cart Button', 'product-recommendations' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render tools section
	 */
	private function render_tools() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'proreen_product_recommendations';
		$cache_key  = 'proreen_product_recommendations_count';
		$count      = wp_cache_get( $cache_key, 'product_recommendations' );

		if ( false === $count ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// Reason: Table name is dynamically constructed from $wpdb->prefix and is safe. Table names cannot be parameterized.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE 1 = %d",
					1
				)
			);
			// phpcs:enable

			wp_cache_set( $cache_key, $count, 'product_recommendations', 300 );
		}
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Recommendation Data', 'product-recommendations' ); ?></th>
				<td>
					<p>
					<?php
					// translators: %d is the number of current recommendations in the database.
					echo esc_html( sprintf( __( 'Current recommendations in database: %d', 'product-recommendations' ), $count ) );
					?>
					</p>
					<button type="button" class="button" id="rebuild-recommendations"><?php esc_html_e( 'Rebuild Recommendation Data', 'product-recommendations' ); ?></button>
					<button type="button" class="button" id="clear-recommendations"><?php esc_html_e( 'Clear All Data', 'product-recommendations' ); ?></button>
					<p class="description"><?php esc_html_e( 'Rebuild data to update recommendations based on recent orders. This may take a few minutes.', 'product-recommendations' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Export/Import', 'product-recommendations' ); ?></th>
				<td>
					<button type="button" class="button"><?php esc_html_e( 'Export Settings', 'product-recommendations' ); ?></button>
					<button type="button" class="button"><?php esc_html_e( 'Import Settings', 'product-recommendations' ); ?></button>
					<p class="description"><?php esc_html_e( 'Export or import plugin settings for backup or migration.', 'product-recommendations' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Add product-specific recommendation fields
	 */
	public function add_product_recommendations_fields() {
		global $post;
		?>
		<div class="options_group">
			<p class="form-field">
				<label for="custom_recommendations"><?php esc_html_e( 'Custom Recommendations', 'product-recommendations' ); ?></label>
				<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="custom_recommendations" name="custom_recommendations[]" data-placeholder="<?php esc_attr_e( 'Search for products&hellip;', 'product-recommendations' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>">
					<?php
					$product_ids = get_post_meta( $post->ID, '_custom_recommendations', true );
					if ( $product_ids ) {
						foreach ( $product_ids as $product_id ) {
							$product = wc_get_product( $product_id );
							if ( is_object( $product ) ) {
								echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
							}
						}
					}
					?>
				</select>
				<?php echo esc_html( wc_help_tip( __( 'Manually select products to recommend for this product. These will override algorithmic recommendations.', 'product-recommendations' ) ) ); ?>
			</p>
			
			<p class="form-field">
				<label for="excluded_recommendations"><?php esc_html_e( 'Exclude from Recommendations', 'product-recommendations' ); ?></label>
				<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="excluded_recommendations" name="excluded_recommendations[]" data-placeholder="<?php esc_attr_e( 'Search for products to exclude&hellip;', 'product-recommendations' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>">
					<?php
					$excluded_ids = get_post_meta( $post->ID, '_excluded_recommendations', true );
					if ( $excluded_ids ) {
						foreach ( $excluded_ids as $product_id ) {
							$product = wc_get_product( $product_id );
							if ( is_object( $product ) ) {
								echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
							}
						}
					}
					?>
				</select>
				<?php echo esc_html( wc_help_tip( __( 'Products that should never be recommended for this product.', 'product-recommendations' ) ) ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save product recommendation fields
	 */
	public function save_product_recommendations_fields( $post_id ) {
		// Check if nonce is valid.
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		// Check if user has permission to edit the post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if this is an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$custom_recommendations   = isset( $_POST['custom_recommendations'] ) ? array_map( 'intval', $_POST['custom_recommendations'] ) : array();
		$excluded_recommendations = isset( $_POST['excluded_recommendations'] ) ? array_map( 'intval', $_POST['excluded_recommendations'] ) : array();

		update_post_meta( $post_id, '_custom_recommendations', $custom_recommendations );
		update_post_meta( $post_id, '_excluded_recommendations', $excluded_recommendations );
	}

	/**
	 * Field callbacks
	 */
	public function checkbox_field( $args ) {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );
		$value    = isset( $settings[ $args['field'] ] ) ? $settings[ $args['field'] ] : 'no';
		?>
		<label>
			<input type="checkbox" name="proreen_product_recommendations_settings[<?php echo esc_attr( $args['field'] ); ?>]" value="yes" <?php checked( $value, 'yes' ); ?>>
			<?php
			if ( isset( $args['description'] ) ) {
				echo esc_html( $args['description'] );}
			?>
		</label>
		<?php
	}

	public function select_field( $args ) {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );
		$value    = isset( $settings[ $args['field'] ] ) ? $settings[ $args['field'] ] : '';
		?>
		<select name="proreen_product_recommendations_settings[<?php echo esc_attr( $args['field'] ); ?>]">
			<?php foreach ( $args['options'] as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function checkbox_group_field( $args ) {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );
		?>
		<fieldset>
			<?php foreach ( $args['fields'] as $field => $label ) : ?>
				<?php $value = isset( $settings[ $field ] ) ? $settings[ $field ] : 'no'; ?>
				<label>
					<input type="checkbox" name="proreen_product_recommendations_settings[<?php echo esc_attr( $field ); ?>]" value="yes" <?php checked( $value, 'yes' ); ?>>
					<?php echo esc_html( $label ); ?>
				</label><br>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Number field callback
	 *
	 * @param array $args Field arguments including field name, min, max, and description.
	 */
	public function number_field( $args ) {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );
		$value    = isset( $settings[ $args['field'] ] ) ? $settings[ $args['field'] ] : '';
		?>
		<input type="number" name="proreen_product_recommendations_settings[<?php echo esc_attr( $args['field'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $args['min'] ); ?>" max="<?php echo esc_attr( $args['max'] ); ?>">
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Section callbacks
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__( 'Configure general recommendation settings.', 'product-recommendations' ) . '</p>';
	}

	/**
	 * Engines section callback
	 */
	public function engines_section_callback() {
		echo '<p>' . esc_html__( 'Configure recommendation engine parameters.', 'product-recommendations' ) . '</p>';
	}

	/**
	 * Display section callback
	 */
	public function display_section_callback() {
		echo '<p>' . esc_html__( 'Configure how recommendations are displayed.', 'product-recommendations' ) . '</p>';
	}
}
