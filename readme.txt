=== UpSellSmart – Product Recommendations ===
Contributors: hasandev
Tags: personalized products, product recommendations, upsell, cross-sell, frequently bought together
Requires at least: 5.0    
Tested up to: 6.8
Requires PHP: 7.3
Stable tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Local, data-driven UpSellSmart – Product Recommendations with multiple engines and comprehensive admin controls.

== Description ==

UpSellSmart – Product Recommendations provides intelligent product suggestions based on your store's data. The plugin analyzes customer behavior and purchase patterns to recommend relevant products, helping increase sales and improve customer experience.

**Key Features:**

* **Two Recommendation Engines**: Content-based (categories/tags) and Association-based (frequently bought together)
* **Local Data Processing**: All recommendations are generated using your store's data - no external APIs required
* **Multiple Display Locations**: Show recommendations on product pages, cart, and checkout
* **AJAX Updates**: Cart and checkout recommendations update dynamically without page reload
* **Comprehensive Admin Interface**: Easy-to-use settings panel with detailed configuration options
* **Manual Overrides**: Set custom recommendations or exclude specific products per product
* **Performance Optimized**: Uses caching and background processing to ensure fast page loads

**Recommendation Engines:**

1. **Content-Based Engine**: Recommends products that share categories, tags, or attributes with the current product or cart items. Perfect for showing related products within the same category.

2. **Association Engine**: Uses purchase history to find products frequently bought together. Implements market-basket analysis to identify strong product associations.

**Display Options:**

✅ Single product pages
✅ Cart page (updates when cart changes)
✅ Checkout page
✅ Configurable number of columns and products
✅ Show/hide price, ratings, and add-to-cart buttons

**Admin Features:**

✅ Choose active recommendation engine
✅ Configure engine parameters (minimum support, confidence levels, etc.)
✅ Set display preferences (title, columns, styling options)
✅ Manual product recommendations per product
✅ Exclude specific products from recommendations
✅ Tools for rebuilding and managing recommendation data
✅ Statistics and performance monitoring

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-product-recommendations` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WooCommerce > Recommendations to configure the plugin
4. The plugin will automatically start building recommendation data based on your existing orders

== Frequently Asked Questions ==

= Does this plugin require external services? =

No, all recommendation processing happens locally on your server using only your WooCommerce data. No external APIs or cloud services are used.

= How long does it take to build recommendation data? =

Initial data building depends on your store size. For most stores, it completes within a few minutes. The process runs in the background and won't affect your site performance.

= Can I customize which products are recommended? =

Yes, you can set custom recommendations for individual products or exclude specific products from being recommended. The plugin also provides various filtering and sorting options.

= Will this slow down my website? =

No, the plugin is designed for performance. Recommendations are pre-calculated and cached, so displaying them doesn't require heavy processing during page loads.

== Screenshots ==

1. Admin settings page with comprehensive configuration options
2. Product recommendations displayed on single product page
3. Cart page recommendations that update dynamically
4. Individual product recommendation settings
5. Tools and statistics dashboard

== Changelog ==

= 1.0.0 =
* Initial release
* Content-based recommendation engine
* Association-based recommendation engine
* Admin interface with comprehensive settings
* Display on product, cart, and checkout pages
* AJAX updates for cart/checkout recommendations
* Manual recommendation overrides
* Performance optimization with caching

== Upgrade Notice ==

= 1.0.0 =
Initial release of UpSellSmart – Product Recommendations plugin.

== Technical Details ==

**Database Tables:**
The plugin creates one custom table (`wp_upspr_product_recommendations`) to store recommendation data efficiently.

**Hooks and Filters:**
The plugin provides various hooks and filters for developers to customize functionality:

* `upspr_product_recommendations_get_recommendations` - Filter recommendations before display
* `upspr_product_recommendations_display_args` - Modify display arguments
* `upspr_product_recommendations_engine_settings` - Customize engine parameters

**Performance:**
* Uses WordPress transients for caching
* Background processing for data building
* Optimized database queries
* Minimal frontend JavaScript

**Compatibility:**
* WooCommerce 5.0+
* WordPress 5.0+
* PHP 7.4+
* Works with most WooCommerce themes
* Compatible with major caching plugins

== Support ==

For support, feature requests, or bug reports, please visit our support forum or contact us directly.
