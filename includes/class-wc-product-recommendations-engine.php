<?php
/**
 * Recommendation Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Product_Recommendations_Engine {

	/**
	 * Get recommendations for a product
	 */
	public static function get_recommendations( $product_id, $context = 'product', $limit = 4 ) {
		$settings = get_option( 'wc_product_recommendations_settings', array() );

		if ( ! isset( $settings['enabled'] ) || $settings['enabled'] !== 'yes' ) {
			return array();
		}

		// Check for custom recommendations first
		$custom_recommendations = get_post_meta( $product_id, '_custom_recommendations', true );
		if ( ! empty( $custom_recommendations ) ) {
			return array_slice( $custom_recommendations, 0, $limit );
		}

		$engine          = isset( $settings['active_engine'] ) ? $settings['active_engine'] : 'content';
		$recommendations = array();

		switch ( $engine ) {
			case 'content':
				$recommendations = self::get_content_based_recommendations( $product_id, $limit );
				break;
			case 'association':
				$recommendations = self::get_association_based_recommendations( $product_id, $limit );
				break;
			case 'hybrid':
				$content_recs     = self::get_content_based_recommendations( $product_id, $limit / 2 );
				$association_recs = self::get_association_based_recommendations( $product_id, $limit / 2 );
				$recommendations  = array_merge( $content_recs, $association_recs );
				$recommendations  = array_unique( $recommendations );
				$recommendations  = array_slice( $recommendations, 0, $limit );
				break;
		}

		// Filter out excluded products
		$excluded = get_post_meta( $product_id, '_excluded_recommendations', true );
		if ( ! empty( $excluded ) ) {
			$recommendations = array_diff( $recommendations, $excluded );
		}

		// Filter out out-of-stock products if needed
		$recommendations = self::filter_available_products( $recommendations );

		return array_values( $recommendations );
	}

	/**
	 * Get recommendations for cart
	 */
	public static function get_cart_recommendations( $limit = 4 ) {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return array();
		}

		$cart_product_ids = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$cart_product_ids[] = $cart_item['product_id'];
		}

		$settings = get_option( 'wc_product_recommendations_settings', array() );
		$engine   = isset( $settings['active_engine'] ) ? $settings['active_engine'] : 'content';

		$all_recommendations = array();

		foreach ( $cart_product_ids as $product_id ) {
			$recommendations     = self::get_recommendations( $product_id, 'cart', $limit * 2 );
			$all_recommendations = array_merge( $all_recommendations, $recommendations );
		}

		// Remove duplicates and cart items
		$all_recommendations = array_unique( $all_recommendations );
		$all_recommendations = array_diff( $all_recommendations, $cart_product_ids );

		// Score and sort recommendations
		$scored_recommendations = self::score_recommendations( $all_recommendations, $cart_product_ids );

		return array_slice( $scored_recommendations, 0, $limit );
	}

	/**
	 * Content-based recommendations
	 */
	private static function get_content_based_recommendations( $product_id, $limit ) {
		$settings         = get_option( 'wc_product_recommendations_settings', array() );
		$content_settings = isset( $settings['content_engine'] ) ? $settings['content_engine'] : array();

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $limit * 3, // Get more to filter.
			'post_status'    => 'publish',
			// Note: Using 'post__not_in' and 'meta_query' may impact performance. See https://wpvip.com/documentation/performance-improvements-by-removing-usage-of-post__not_in/
			'post__not_in'   => array( $product_id ),
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
			$args['tax_query'] = $tax_query;
		}

		// Sort by preference
		$sort_by = isset( $content_settings['sort_by'] ) ? $content_settings['sort_by'] : 'popularity';
		switch ( $sort_by ) {
			case 'popularity':
				$args['meta_key'] = 'total_sales';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'rating':
				$args['meta_key'] = '_wc_average_rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'price_low':
				$args['meta_key'] = '_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;
			case 'price_high':
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
	private static function get_association_based_recommendations( $product_id, $limit ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_product_recommendations';
		$cache_key  = 'assoc_recs_' . $product_id . '_' . $limit;
		$cache_group = 'wc_product_recommendations';

		$product_ids = wp_cache_get( $cache_key, $cache_group );
		if ( false === $product_ids ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql = $wpdb->prepare(
				"SELECT recommended_product_id, score 
				FROM {$table_name}
				WHERE product_id = %d 
				AND engine = %s 
				ORDER BY score DESC 
				LIMIT %d",
				$product_id,
				'association',
				$limit
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$recommendations = $wpdb->get_results( $sql );

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
	private static function score_recommendations( $product_ids, $context_products = array() ) {
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
			$rating = get_post_meta( $product_id, '_wc_average_rating', true );
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
	private static function filter_available_products( $product_ids ) {
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
