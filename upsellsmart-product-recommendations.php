<?php
/**
 * Plugin Name: UpSellSmart – Product Recommendations
 * Description: Local, data-driven UpSellSmart – Product Recommendations with multiple engines and comprehensive admin controls.
 * Version: 1.0.0
 * Author: hmdkamrul
 * Author URI: https://profiles.wordpress.org/hasandev/
 * Text Domain: upsellsmart-product-recommendations
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package          UpSellSmart – Product Recommendations
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'UPSPR_PRODUCT_RECOMMENDATIONS_VERSION', '1.0.0' );
define( 'UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_FILE', __FILE__ );
define( 'UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class UPSPR_Product_Recommendations {

	/**
	 * Single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Main instance
	 */
	public static function upspr_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'upspr_init' ) );
		add_action( 'plugins_loaded', array( $this, 'upspr_plugins_loaded' ) );
		register_activation_hook( __FILE__, array( $this, 'upspr_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'upspr_deactivate' ) );
		// Add compatibility features
		add_action( 'before_woocommerce_init', array( $this, 'upspr_declare_compatibility_features' ) );
	}

	/**
	 * Declare compatibility features
	 * This method is used to declare compatibility with WooCommerce features.
	 * It checks if the FeaturesUtil class exists and declares the features.
	 */
	public function upspr_declare_compatibility_features() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
	/**
	 * Initialize the plugin
	 */
	public function upspr_init() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'upspr_woocommerce_missing_notice' ) );
			return;
		}

		$this->upspr_includes();
		$this->upspr_init_hooks();
	}

	/**
	 * Include required files
	 */
	private function upspr_includes() {
		include_once UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_PATH . 'includes/class-upspr-product-recommendations-admin.php';
		include_once UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_PATH . 'includes/class-upspr-product-recommendations-engine.php';
		include_once UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_PATH . 'includes/class-upspr-product-recommendations-display.php';
		include_once UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_PATH . 'includes/class-upspr-product-recommendations-ajax.php';
		include_once UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_PATH . 'includes/class-upspr-product-recommendations-data.php';
	}

	/**
	 * Initialize hooks
	 */
	private function upspr_init_hooks() {
		// Initialize classes
		new UPSPR_Product_Recommendations_Admin();
		new UPSPR_Product_Recommendations_Display();
		new UPSPR_Product_Recommendations_Ajax();

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'upspr_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'upspr_admin_enqueue_scripts' ) );
	}

	/**
	 * Plugin activation
	 */
	public function upspr_activate() {
		// Create database tables
		$this->upspr_create_tables();

		// Set default options
		$this->upspr_set_default_options();

		// Build initial recommendation data
		wp_schedule_single_event( time() + 60, 'upspr_product_recommendations_build_data' );
	}

	/**
	 * Plugin deactivation
	 */
	public function upspr_deactivate() {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'upspr_product_recommendations_build_data' );
		wp_clear_scheduled_hook( 'upspr_product_recommendations_update_data' );
	}

	/**
	 * Create database tables
	 */
	private function upspr_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table for storing product associations
		$table_name = $wpdb->prefix . 'upspr_product_recommendations';

		$sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            recommended_product_id bigint(20) NOT NULL,
            engine varchar(50) NOT NULL,
            score decimal(10,4) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY recommended_product_id (recommended_product_id),
            KEY engine (engine),
            KEY score (score),
            UNIQUE KEY unique_recommendation (product_id, recommended_product_id, engine)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set default plugin options
	 */
	private function upspr_set_default_options() {
		$defaults = array(
			'enabled'             => 'yes',
			'active_engine'       => 'content',
			'show_on_product'     => 'yes',
			'show_on_cart'        => 'yes',
			'show_on_checkout'    => 'yes',
			'max_recommendations' => 4,
			'content_engine'      => array(
				'match_categories' => 'yes',
				'match_tags'       => 'yes',
				'match_attributes' => 'no',
				'sort_by'          => 'popularity',
				'exclude_current'  => 'yes',
			),
			'association_engine'  => array(
				'min_support'    => 2,
				'use_views'      => 'no',
				'days_back'      => 365,
				'min_confidence' => 0.1,
			),
			'display_settings'    => array(
				'title'       => 'You might also like',
				'columns'     => 4,
				'show_price'  => 'yes',
				'show_rating' => 'yes',
			),
		);

		add_option( 'upspr_product_recommendations_settings', $defaults );
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function upspr_enqueue_scripts() {
		if ( is_product() || is_cart() || is_checkout() ) {
			wp_enqueue_script(
				'upsellsmart-product-recommendations',
				UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_URL . 'assets/js/frontend.js',
				array( 'jquery', 'wc-cart-fragments' ),
				UPSPR_PRODUCT_RECOMMENDATIONS_VERSION,
				true
			);

			wp_localize_script(
				'upsellsmart-product-recommendations',
				'upspr_product_recommendations',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'upspr_product_recommendations_nonce' ),
				)
			);

			wp_enqueue_style(
				'upsellsmart-product-recommendations',
				UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				UPSPR_PRODUCT_RECOMMENDATIONS_VERSION
			);
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function upspr_admin_enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'upsellsmart-product-recommendations' ) !== false ) {
			wp_enqueue_script(
				'upspr-product-recommendations-admin',
				UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				UPSPR_PRODUCT_RECOMMENDATIONS_VERSION,
				true
			);

			wp_enqueue_style(
				'upspr-product-recommendations-admin',
				UPSPR_PRODUCT_RECOMMENDATIONS_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				UPSPR_PRODUCT_RECOMMENDATIONS_VERSION
			);
		}
	}

	/**
	 * WooCommerce missing notice
	 */
	public function upspr_woocommerce_missing_notice() {
		// translators: %s: Plugin name.
		echo '<div class="error"><p><strong>' . sprintf( esc_html__( '%s requires WooCommerce to be installed and active.', 'upsellsmart-product-recommendations' ), 'UpSellSmart – Product Recommendations' ) . '</strong></p></div>';
	}

	/**
	 * Plugins loaded
	 */
	public function upspr_plugins_loaded() {
		// Schedule data updates
		if ( ! wp_next_scheduled( 'upspr_product_recommendations_update_data' ) ) {
			wp_schedule_event( time(), 'daily', 'upspr_product_recommendations_update_data' );
		}

		// Hook into order completion to update data
		add_action( 'woocommerce_order_status_completed', array( $this, 'update_data_on_order' ) );
	}

	/**
	 * Update recommendation data when order is completed
	 */
	public function update_data_on_order( $order_id ) {
		wp_schedule_single_event( time() + 300, 'upspr_product_recommendations_build_data' );
	}
}

// Initialize the plugin.
function UPSPR_Product_Recommendations() {
	return UPSPR_Product_Recommendations::upspr_instance();
}

// Global for backwards compatibility.
$GLOBALS['upspr_product_recommendations'] = UPSPR_Product_Recommendations();

// Schedule data building.
add_action( 'upspr_product_recommendations_build_data', array( 'UPSPR_Recommendations_Data', 'upspr_build_recommendation_data' ) );
add_action( 'upspr_product_recommendations_update_data', array( 'UPSPR_Recommendations_Data', 'upspr_build_recommendation_data' ) );
