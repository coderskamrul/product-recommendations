<?php
/**
 * AJAX functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Product_Recommendations_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_wc_refresh_cart_recommendations', array( $this, 'refresh_cart_recommendations' ) );
		add_action( 'wp_ajax_nopriv_wc_refresh_cart_recommendations', array( $this, 'refresh_cart_recommendations' ) );

		add_action( 'wp_ajax_wc_rebuild_recommendations', array( $this, 'rebuild_recommendations' ) );
		add_action( 'wp_ajax_wc_clear_recommendations', array( $this, 'clear_recommendations' ) );

		add_action( 'woocommerce_add_to_cart_fragments', array( $this, 'add_cart_recommendations_fragment' ) );
	}

	/**
	 * Refresh cart recommendations via AJAX
	 */
	public function refresh_cart_recommendations() {
		check_ajax_referer( 'wc_product_recommendations_nonce', 'nonce' );

		$settings = get_option( 'wc_product_recommendations_settings', array() );
		$limit    = isset( $settings['max_recommendations'] ) ? intval( $settings['max_recommendations'] ) : 4;

		$recommendations = WC_Product_Recommendations_Engine::get_cart_recommendations( $limit );

		if ( empty( $recommendations ) ) {
			wp_send_json_success( array( 'html' => '' ) );
			return;
		}

		ob_start();

		$display    = new WC_Product_Recommendations_Display();
		$reflection = new ReflectionClass( $display );
		$method     = $reflection->getMethod( 'render_recommendations' );
		$method->setAccessible( true );
		$method->invoke( $display, $recommendations, 'cart' );

		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Rebuild recommendations data
	 */
	public function rebuild_recommendations() {
		check_ajax_referer( 'wc_product_recommendations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'product-recommendations' ) );
			return;
		}

		// Schedule the rebuild
		wp_schedule_single_event( time() + 10, 'wc_product_recommendations_build_data' );

		wp_send_json_success( __( 'Recommendation data rebuild scheduled. This may take a few minutes.', 'product-recommendations' ) );
	}

	/**
	 * Clear recommendations data
	 */
	public function clear_recommendations() {
		check_ajax_referer( 'wc_product_recommendations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'product-recommendations' ) );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_product_recommendations';
		$wpdb->query( "TRUNCATE TABLE $table_name" );

		wp_send_json_success( __( 'All recommendation data cleared.', 'product-recommendations' ) );
	}

	/**
	 * Add cart recommendations to WooCommerce fragments
	 */
	public function add_cart_recommendations_fragment( $fragments ) {
		$settings = get_option( 'wc_product_recommendations_settings', array() );

		if ( ! isset( $settings['show_on_cart'] ) || $settings['show_on_cart'] !== 'yes' ) {
			return $fragments;
		}

		$limit           = isset( $settings['max_recommendations'] ) ? intval( $settings['max_recommendations'] ) : 4;
		$recommendations = WC_Product_Recommendations_Engine::get_cart_recommendations( $limit );

		if ( empty( $recommendations ) ) {
			$fragments['#wc-product-recommendations-cart'] = '<div id="wc-product-recommendations-cart"></div>';
			return $fragments;
		}

		ob_start();
		echo '<div id="wc-product-recommendations-cart">';

		$display    = new WC_Product_Recommendations_Display();
		$reflection = new ReflectionClass( $display );
		$method     = $reflection->getMethod( 'render_recommendations' );
		$method->setAccessible( true );
		$method->invoke( $display, $recommendations, 'cart' );

		echo '</div>';

		$fragments['#wc-product-recommendations-cart'] = ob_get_clean();

		return $fragments;
	}
}
