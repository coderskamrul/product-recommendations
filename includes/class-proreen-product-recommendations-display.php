<?php
/**
 * Display functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PROREEN_Product_Recommendations_Display {

	public function __construct() {
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_product_recommendations' ), 25 );
		add_action( 'woocommerce_after_cart_table', array( $this, 'display_cart_recommendations' ) );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'display_checkout_recommendations' ) );

		// Shortcode support
		add_shortcode( 'proreen_product_recommendations', array( $this, 'recommendations_shortcode' ) );
	}

	/**
	 * Display recommendations on single product page
	 */
	public function display_product_recommendations() {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );

		if ( ! isset( $settings['show_on_product'] ) || $settings['show_on_product'] !== 'yes' ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		$limit           = isset( $settings['max_recommendations'] ) ? intval( $settings['max_recommendations'] ) : 4;
		$recommendations = PREProduct_Recommendations_Engine::get_recommendations( $product->get_id(), 'product', $limit );

		if ( empty( $recommendations ) ) {
			return;
		}

		$this->render_recommendations( $recommendations, 'product' );
	}

	/**
	 * Display recommendations on cart page
	 */
	public function display_cart_recommendations() {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );

		if ( ! isset( $settings['show_on_cart'] ) || $settings['show_on_cart'] !== 'yes' ) {
			return;
		}

		$limit           = isset( $settings['max_recommendations'] ) ? intval( $settings['max_recommendations'] ) : 4;
		$recommendations = PREProduct_Recommendations_Engine::get_cart_recommendations( $limit );

		if ( empty( $recommendations ) ) {
			return;
		}

		echo '<div id="wc-product-recommendations-cart">';
		$this->render_recommendations( $recommendations, 'cart' );
		echo '</div>';
	}

	/**
	 * Display recommendations on checkout page
	 */
	public function display_checkout_recommendations() {
		$settings = get_option( 'proreen_product_recommendations_settings', array() );

		if ( ! isset( $settings['show_on_checkout'] ) || $settings['show_on_checkout'] !== 'yes' ) {
			return;
		}

		$limit           = isset( $settings['max_recommendations'] ) ? intval( $settings['max_recommendations'] ) : 4;
		$recommendations = PREProduct_Recommendations_Engine::get_cart_recommendations( $limit );

		if ( empty( $recommendations ) ) {
			return;
		}

		echo '<div id="wc-product-recommendations-checkout">';
		$this->render_recommendations( $recommendations, 'checkout' );
		echo '</div>';
	}

	/**
	 * Render recommendations HTML
	 */
	private function render_recommendations( $product_ids, $context = 'product' ) {
		if ( empty( $product_ids ) ) {
			return;
		}

		$settings         = get_option( 'proreen_product_recommendations_settings', array() );
		$display_settings = isset( $settings['display_settings'] ) ? $settings['display_settings'] : array();

		$title            = isset( $display_settings['title'] ) ? $display_settings['title'] : __( 'You might also like', 'upsellsmart-product-recommendations' );
		$columns          = isset( $display_settings['columns'] ) ? intval( $display_settings['columns'] ) : 4;
		$show_price       = isset( $display_settings['show_price'] ) && $display_settings['show_price'] === 'yes';
		$show_rating      = isset( $display_settings['show_rating'] ) && $display_settings['show_rating'] === 'yes';
		$show_add_to_cart = isset( $display_settings['show_add_to_cart'] ) && $display_settings['show_add_to_cart'] === 'yes';

		?>
		<div class="wc-product-recommendations wc-product-recommendations-<?php echo esc_attr( $context ); ?>">
			<h3 class="wc-product-recommendations-title"><?php echo esc_html( $title ); ?></h3>
			
			<div class="wc-product-recommendations-grid columns-<?php echo esc_attr( $columns ); ?>">
				<?php foreach ( $product_ids as $product_id ) : ?>
					<?php
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						continue;
					}
					?>
					<div class="wc-product-recommendation-item">
						<div class="wc-product-recommendation-image">
							<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
								<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
							</a>
						</div>
						
						<div class="wc-product-recommendation-content">
							<h4 class="wc-product-recommendation-title">
								<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</a>
							</h4>
							
							<?php if ( $show_rating && $product->get_average_rating() ) : ?>
								<div class="wc-product-recommendation-rating">
									<?php echo wp_kses_post( wc_get_rating_html( $product->get_average_rating() ) ); ?>
								</div>
							<?php endif; ?>
							
							<?php if ( $show_price ) : ?>
								<div class="wc-product-recommendation-price">
									<?php echo wp_kses_post( $product->get_price_html() ); ?>
								</div>
							<?php endif; ?>
							
							<?php if ( $show_add_to_cart && $product->is_purchasable() ) : ?>
								<div class="wc-product-recommendation-add-to-cart">
									<?php
									woocommerce_template_loop_add_to_cart(
										array(
											'product' => $product,
										)
									);
									?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode for displaying recommendations
	 */
	public function recommendations_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'limit'      => 4,
				'columns'    => 4,
				'title'      => '',
				'context'    => 'shortcode',
			),
			$atts
		);

		$product_id = intval( $atts['product_id'] );
		if ( ! $product_id ) {
			global $product;
			if ( $product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$recommendations = PREProduct_Recommendations_Engine::get_recommendations( $product_id, $atts['context'], intval( $atts['limit'] ) );

		if ( empty( $recommendations ) ) {
			return '';
		}

		ob_start();

		// Temporarily override display settings for shortcode
		$original_settings = get_option( 'proreen_product_recommendations_settings', array() );
		$temp_settings     = $original_settings;

		if ( ! empty( $atts['title'] ) ) {
			$temp_settings['display_settings']['title'] = $atts['title'];
		}
		$temp_settings['display_settings']['columns'] = intval( $atts['columns'] );

		update_option( 'proreen_product_recommendations_settings', $temp_settings );

		$this->render_recommendations( $recommendations, $atts['context'] );

		// Restore original settings
		update_option( 'proreen_product_recommendations_settings', $original_settings );

		return ob_get_clean();
	}
}
