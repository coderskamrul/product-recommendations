<?php
/**
 * Data processing and recommendation building
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Product_Recommendations_Data {

	/**
	 * Build recommendation data
	 */
	public static function build_recommendation_data() {
		$settings = get_option( 'wc_product_recommendations_settings', array() );

		// Build association-based recommendations
		self::build_association_recommendations();

		// Clean up old data
		self::cleanup_old_data();

		// Update last build time
		update_option( 'wc_product_recommendations_last_build', time() );
	}

	/**
	 * Build association-based recommendations
	 */
	private static function build_association_recommendations() {
		global $wpdb;

		$settings             = get_option( 'wc_product_recommendations_settings', array() );
		$association_settings = isset( $settings['association_engine'] ) ? $settings['association_engine'] : array();

		$min_support    = isset( $association_settings['min_support'] ) ? intval( $association_settings['min_support'] ) : 2;
		$days_back      = isset( $association_settings['days_back'] ) ? intval( $association_settings['days_back'] ) : 365;
		$min_confidence = isset( $association_settings['min_confidence'] ) ? floatval( $association_settings['min_confidence'] ) : 0.1;

		$date_from = date( 'Y-m-d', strtotime( "-{$days_back} days" ) );

		// Get orders from the specified period
		$orders = wc_get_orders(
			array(
				'status'       => array( 'completed', 'processing' ),
				'date_created' => '>=' . $date_from,
				'limit'        => -1,
			)
		);

		$product_pairs  = array();
		$product_counts = array();

		// Analyze order data
		foreach ( $orders as $order ) {
			$items       = $order->get_items();
			$product_ids = array();

			foreach ( $items as $item ) {
				$product_id = $item->get_product_id();
				if ( $product_id ) {
					$product_ids[] = $product_id;

					if ( ! isset( $product_counts[ $product_id ] ) ) {
						$product_counts[ $product_id ] = 0;
					}
					++$product_counts[ $product_id ];
				}
			}

			// Create pairs
			for ( $i = 0; $i < count( $product_ids ); $i++ ) {
				for ( $j = $i + 1; $j < count( $product_ids ); $j++ ) {
					$pair_key         = $product_ids[ $i ] . '-' . $product_ids[ $j ];
					$reverse_pair_key = $product_ids[ $j ] . '-' . $product_ids[ $i ];

					if ( ! isset( $product_pairs[ $pair_key ] ) ) {
						$product_pairs[ $pair_key ] = 0;
					}
					++$product_pairs[ $pair_key ];

					// Also count reverse pair
					if ( ! isset( $product_pairs[ $reverse_pair_key ] ) ) {
						$product_pairs[ $reverse_pair_key ] = 0;
					}
					++$product_pairs[ $reverse_pair_key ];
				}
			}
		}

		// Clear existing association data
		$table_name = $wpdb->prefix . 'wc_product_recommendations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table_name, array( 'engine' => 'association' ) );
		// Clear cache for product recommendations after deletion
		wp_cache_delete( 'wc_product_recommendations', 'wc_product_recommendations' );

		// Calculate confidence and insert recommendations
		foreach ( $product_pairs as $pair_key => $support ) {
			if ( $support < $min_support ) {
				continue;
			}

			list($product_a, $product_b) = explode( '-', $pair_key );

			// Calculate confidence: P(B|A) = support(A,B) / support(A)
			$confidence = $support / $product_counts[ $product_a ];

			if ( $confidence >= $min_confidence ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert(
					$table_name,
					array(
						'product_id'             => $product_a,
						'recommended_product_id' => $product_b,
						'engine'                 => 'association',
						'score'                  => $confidence,
					),
					array( '%d', '%d', '%s', '%f' )
				);
			}
		}
	}

	/**
	 * Clean up old recommendation data
	 */
	private static function cleanup_old_data() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_product_recommendations';

		// Remove recommendations for products that no longer exist.
		$query = "
			DELETE r FROM {$table_name} r
			LEFT JOIN {$wpdb->posts} p1 ON r.product_id = p1.ID
			LEFT JOIN {$wpdb->posts} p2 ON r.recommended_product_id = p2.ID
			WHERE p1.ID IS NULL OR p2.ID IS NULL 
			OR p1.post_status != 'publish' 
			OR p2.post_status != 'publish'
		";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $query );
		// Clear cache after direct database query.
		wp_cache_delete( 'wc_product_recommendations', 'wc_product_recommendations' );

		// Remove recommendations older than 30 days for content-based.
		$query2 = $wpdb->prepare(
			"
			DELETE FROM {$table_name}
			WHERE engine = %s
			AND updated_at < %s
		",
			'content',
			gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $query2 );
		// Clear cache after direct database query.
		wp_cache_delete( 'wc_product_recommendations', 'wc_product_recommendations' );
	}

	/**
	 * Get recommendation statistics
	 */
	public static function get_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_product_recommendations';

		$stats = array();

		$cache_key = 'wc_product_recommendations_stats';
		$stats     = wp_cache_get( $cache_key, 'wc_product_recommendations' );

		if ( false === $stats ) {
			$stats = array();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['total'] = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name}"
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['content'] = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE engine = %s",
					'content'
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['association'] = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE engine = %s",
					'association'
				)
			);

			$stats['last_build'] = get_option( 'wc_product_recommendations_last_build', 0 );

			wp_cache_set( $cache_key, $stats, 'wc_product_recommendations', 300 );
		}

		return $stats;
	}
}
