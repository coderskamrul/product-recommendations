<?php
/**
 * AJAX functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UPSPR_Product_Recommendations_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_upspr_refresh_cart_recommendations', array( $this, 'upspr_refresh_cart_recommendations' ) );
		add_action( 'wp_ajax_nopriv_upspr_refresh_cart_recommendations', array( $this, 'upspr_refresh_cart_recommendations' ) );

		add_action( 'wp_ajax_upspr_rebuild_recommendations', array( $this, 'upspr_rebuild_recommendations' ) );
		add_action( 'wp_ajax_upspr_clear_recommendations', array( $this, 'upspr_clear_recommendations' ) );

		add_action( 'woocommerce_add_to_cart_fragments', array( $this, 'upspr_add_cart_recommendations_fragment' ) );
	}

	/**
	 * Refresh cart recommendations via AJAX
	 */
	public function upspr_refresh_cart_recommendations() {
		check_ajax_referer( 'upspr_product_recommendations_nonce', 'nonce' );

		$settings = get_option( 'upspr_product_recommendations_settings', array() );
		$limit    = isset( $settings['max_recommendations'] ) ? intval( $settings['max_recommendations'] ) : 4;

		$recommendations = UPSPR_Recommendations_Engine::upspr_get_cart_recommendations( $limit );

		if ( empty( $recommendations ) ) {
			wp_send_json_success( array( 'html' => '' ) );
			return;
		}

		ob_start();

		$display    = new UPSPR_Product_Recommendations_Display();
		$reflection = new ReflectionClass( $display );
		$method     = $reflection->getMethod( 'upspr_render_recommendations' );
		$method->setAccessible( true );
		$method->invoke( $display, $recommendations, 'cart' );

		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Rebuild recommendations data
	 */
	public function upspr_rebuild_recommendations() {
		check_ajax_referer( 'upspr_product_recommendations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'upsellsmart-product-recommendations' ) );
			return;
		}

		// Schedule the rebuild
		wp_schedule_single_event( time() + 10, 'upspr_product_recommendations_build_data' );

		wp_send_json_success( __( 'Recommendation data rebuild scheduled. This may take a few minutes.', 'upsellsmart-product-recommendations' ) );
	}

	/**
	 * Clear recommendations data
	 */
	public function upspr_clear_recommendations() {
		check_ajax_referer( 'upspr_product_recommendations_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'upsellsmart-product-recommendations' ) );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'upspr_product_recommendations';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is whitelisted and safe
		$wpdb->query( "TRUNCATE TABLE $table_name" );

		wp_send_json_success( __( 'All recommendation data cleared.', 'upsellsmart-product-recommendations' ) );
	}

	/**
	 * Add cart recommendations to WooCommerce fragments
	 */
	public function upspr_add_cart_recommendations_fragment( $fragments ) {
		$settings = get_option( 'upspr_product_recommendations_settings', array() );

		if ( ! isset( $settings['show_on_cart'] ) || $settings['show_on_cart'] !== 'yes' ) {
			return $fragments;
		}

		$limit           = isset( $settings['max_recommendations'] ) ? intval( $settings['max_recommendations'] ) : 4;
		$recommendations = UPSPR_Recommendations_Engine::upspr_get_cart_recommendations( $limit );

		if ( empty( $recommendations ) ) {
			$fragments['#upspr-product-recommendations-cart'] = '<div id="upspr-product-recommendations-cart"></div>';
			return $fragments;
		}

		ob_start();
		echo '<div id="upspr-product-recommendations-cart">';

		$display    = new UPSPR_Product_Recommendations_Display();
		$reflection = new ReflectionClass( $display );
		$method     = $reflection->getMethod( 'upspr_render_recommendations' );
		$method->setAccessible( true );
		$method->invoke( $display, $recommendations, 'cart' );

		echo '</div>';

		$fragments['#upspr-product-recommendations-cart'] = ob_get_clean();

		return $fragments;
	}
}
