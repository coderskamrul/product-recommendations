<?php
/**
 * Recommendation Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UPSPR_Recommendations_Engine {

	/**
	 * Get recommendations for a product
	 *
	 * @param int    $product_id Product ID to get recommendations for.
	 * @param string $context Context for recommendations (e.g., 'product', 'cart').
	 * @param int    $limit Number of recommendations to return.
	 * @return array List of recommended product IDs
	 * @throws Exception If the product ID is invalid or no recommendations are found.
	 * @since 1.0.0
	 */
	public static function upspr_get_recommendations( $product_id, $context = 'product', $limit = 4 ) {
		$settings = get_option( 'upspr_product_recommendations_settings', array() );

		if ( ! isset( $settings['enabled'] ) || $settings['enabled'] !== 'yes' ) {
			return array();
		}

		// Check for custom recommendations first
		$upspr_custom_recommendations = get_post_meta( $product_id, '_upspr_custom_recommendations', true );
		if ( ! empty( $upspr_custom_recommendations ) ) {
			return array_slice( $upspr_custom_recommendations, 0, $limit );
		}

		$engine          = isset( $settings['active_engine'] ) ? $settings['active_engine'] : 'content';
		$recommendations = array();

		switch ( $engine ) {
			case 'content':
				$recommendations = self::upspr_get_content_based_recommendations( $product_id, $limit );
				break;
			case 'association':
				$recommendations = self::upspr_get_association_based_recommendations( $product_id, $limit );
				break;
			case 'hybrid':
				$content_recs     = self::upspr_get_content_based_recommendations( $product_id, $limit / 2 );
				$association_recs = self::upspr_get_association_based_recommendations( $product_id, $limit / 2 );
				$recommendations  = array_merge( $content_recs, $association_recs );
				$recommendations  = array_unique( $recommendations );
				$recommendations  = array_slice( $recommendations, 0, $limit );
				break;
		}

		// Filter out excluded products
		$excluded = get_post_meta( $product_id, '_upspr_excluded_recommendations', true );
		if ( ! empty( $excluded ) ) {
			$recommendations = array_diff( $recommendations, $excluded );
		}

		// Filter out out-of-stock products if needed
		$recommendations = self::upspr_filter_available_products( $recommendations );

		return array_values( $recommendations );
	}

	/**
	 * Get recommendations for cart
	 */
	public static function upspr_get_cart_recommendations( $limit = 4 ) {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return array();
		}

		$cart_product_ids = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$cart_product_ids[] = $cart_item['product_id'];
		}

		$settings = get_option( 'upspr_product_recommendations_settings', array() );
		$engine   = isset( $settings['active_engine'] ) ? $settings['active_engine'] : 'content';

		$all_recommendations = array();

		foreach ( $cart_product_ids as $product_id ) {
			$recommendations     = self::upspr_get_recommendations( $product_id, 'cart', $limit * 2 );
			$all_recommendations = array_merge( $all_recommendations, $recommendations );
		}

		// Remove duplicates and cart items
		$all_recommendations = array_unique( $all_recommendations );
		$all_recommendations = array_diff( $all_recommendations, $cart_product_ids );

		// Score and sort recommendations
		$scored_recommendations = self::upspr_score_recommendations( $all_recommendations, $cart_product_ids );

		return array_slice( $scored_recommendations, 0, $limit );
	}

	/**
	 * Content-based recommendations
	 */
	private static function upspr_get_content_based_recommendations( $product_id, $limit ) {
		$settings         = get_option( 'upspr_product_recommendations_settings', array() );
		$content_settings = isset( $settings['content_engine'] ) ? $settings['content_engine'] : array();

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $limit * 3,
			'post_status'    => 'publish',
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Safe to exclude a single product by ID
			'post__not_in'   => array( $product_id ),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed to filter only in-stock products
			'meta_query'     => array(
				array(
					'key'     => '_stock_status',
					'value'   => 'instock', // WooCommerce uses 'instock' as the value for in-stock products.
					'compare' => '=',
				),
			),
		);

		$tax_query = array( 'relation' => 'OR' );

		// Match by categories
		if ( isset( $content_settings['match_categories'] ) && $content_settings['match_categories'] === 'yes' ) {
			$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! empty( $categories ) ) {
				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $categories,
					'operator' => 'IN',
				);
			}
		}

		// Match by tags
		if ( isset( $content_settings['match_tags'] ) && $content_settings['match_tags'] === 'yes' ) {
			$tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
			if ( ! empty( $tags ) ) {
				$tax_query[] = array(
					'taxonomy' => 'product_tag',
					'field'    => 'term_id',
					'terms'    => $tags,
					'operator' => 'IN',
				);
			}
		}

		if ( count( $tax_query ) > 1 ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required to match related categories/tags
			$args['tax_query'] = $tax_query;
		}

		// Sort by preference
		$sort_by = isset( $content_settings['sort_by'] ) ? $content_settings['sort_by'] : 'popularity';
		switch ( $sort_by ) {
			case 'popularity':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by meta_key is expected
				$args['meta_key'] = 'total_sales';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'rating':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by rating (meta_key)
				$args['meta_key'] = '_upspr_average_rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'price_low':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by price (meta_key)
				$args['meta_key'] = '_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;
			case 'price_high':
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by price (meta_key)
				$args['meta_key'] = '_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}
		$query           = new WP_Query( $args );
		$recommendations = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$recommendations[] = get_the_ID();
			}
			wp_reset_postdata();
		}

		// Ensure compatibility with latest WP_Query and WooCommerce standards.
		return array_slice( $recommendations, 0, ceil( $limit ) );
	}

	/**
	 * Association-based recommendations
	 */
	private static function upspr_get_association_based_recommendations( $product_id, $limit ) {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'upspr_product_recommendations';
		$cache_key   = 'assoc_recs_' . $product_id . '_' . $limit;
		$cache_group = 'upspr_product_recommendations';

		$product_ids = wp_cache_get( $cache_key, $cache_group );
		if ( false === $product_ids ) {
			// Escape the table name (since it cannot be used as a placeholder).
			$table_name_escaped = esc_sql( $table_name );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$recommendations = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT recommended_product_id, score
					FROM {$table_name_escaped}
					WHERE product_id = %d
					AND engine = %s
					ORDER BY score DESC
					LIMIT %d
					",
					$product_id,
					'association',
					$limit
				),
				OBJECT
			);
			// phpcs:enable
			$product_ids = array();
			foreach ( $recommendations as $rec ) {
				$product_ids[] = $rec->recommended_product_id;
			}

			wp_cache_set( $cache_key, $product_ids, $cache_group, 300 ); // Cache for 5 minutes
		}

		return $product_ids;
	}

	/**
	 * Score recommendations based on multiple factors
	 */
	private static function upspr_score_recommendations( $product_ids, $context_products = array() ) {
		$scored = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$score = 0;

			// Base score from sales
			$sales  = get_post_meta( $product_id, 'total_sales', true );
			$score += intval( $sales ) * 0.1;

			// Rating score
			$rating = get_post_meta( $product_id, '_upspr_average_rating', true );
			$score += floatval( $rating ) * 10;

			// Price factor (middle-priced products get slight boost)
			$price = floatval( $product->get_price() );
			if ( $price > 0 && $price < 1000 ) {
				$score += 5;
			}

			// Stock status
			if ( $product->is_in_stock() ) {
				$score += 20;
			}

			$scored[ $product_id ] = $score;
		}

		// Sort by score
		arsort( $scored );

		return array_keys( $scored );
	}

	/**
	 * Filter out unavailable products
	 */
	private static function upspr_filter_available_products( $product_ids ) {
		$available = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
				$available[] = $product_id;
			}
		}

		return $available;
	}
}
