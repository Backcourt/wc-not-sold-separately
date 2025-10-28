<?php
/*
 * Plugin Name: Not Sold Separately for WooCommerce
 * Plugin URI: https://woocommerce.com/products/woocommerce-mix-and-match-products/
 * Description: Optionally restrict products to sale only as part of Mix and Match Product.
 * Version: 2.5.1
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 * Text Domain: wc-not-sold-separately
 * Domain Path: /languages/
 *
 * Update URI: https://www.backcourt.io
 *
 * Requires PHP: 8.0
 *
 * Requires at least: 6.6.0
 * Tested up to: 6.7.0
 * 
 * WC requires at least: 9.4.0
 * WC tested up to: 9.6.0
 *
 * Copyright: © 2025 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Backcourt\WCNotSoldSeparately\Vendor\Fragen;

 /**
  * Add Git Updater Lite
  */
if ( file_exists( __DIR__ . '/packages/autoload.php' ) ) {
	require_once __DIR__ . '/packages/autoload.php';
	( new Fragen\Git_Updater\Lite( __FILE__ ) )->run();
}

class WC_Not_Sold_Separately {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '2.5.1';

	/**
	 * Props added to child products.
	 *
	 * @var string[]
	 */
	private static $bundled_props = array();

	/**
	 * Functions that test child cart items.
	 *
	 * @var string[]
	 */
	private static $bundled_cart_fn = array();

	/**
	 * Functions that get product upsells.
	 *
	 * @var function[]
	 */
	private static $related_bundle_fn = array();

	/**
	 * Functions that get product properties.
	 *
	 * @var function[]
	 */
	private static $data_getters = array();

	/**
	 * Skip test in cart.
	 *
	 * @var bool
	 */
	private static $cart_loading = false;

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename(__FILE__) );
	}

	/**
	 * Fire in the hole!
	 */
	public static function init() {

		// Bundles.
		if ( class_exists( 'WC_Bundles' ) ) {
			self::$bundled_props[]   = 'bundled_item';
			self::$bundled_props[]   = 'bundled_by';
			self::$bundled_cart_fn[] = 'wc_pb_is_bundled_cart_item';
			// Product Bundles 7.0+ got a product_data class specifically for tracking properties.
			if ( class_exists( 'WC_PB_Product_Data' ) ) {
				self::$data_getters[] = array( WC_PB_Product_Data::instance(), 'get' );
			}
		}

		// Mix and Match.
		if ( class_exists( 'WC_Mix_and_Match' ) ) {
			self::$bundled_props[]     = 'mnm_child_item';
			self::$bundled_cart_fn[]   = 'wc_mnm_is_child_cart_item';
			self::$related_bundle_fn[] = array( WC_Data_Store::load( 'product-mix-and-match' ), 'query_containers_by_product' );

			// Mix and Match 2.7+ got a product_data class specifically for tracking properties.
			if ( class_exists( 'WC_MNM_Product_Data' ) ) {
				self::$data_getters[] = array( WC_MNM_Product_Data::get_instance(), 'get' );
			}
		}

		if ( ! empty( self::$bundled_props ) ) {
			self::add_hooks();
		}

		// Load translation files.
		add_action( 'init', array( __CLASS__, 'load_plugin_textdomain' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Localization.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Make the plugin translation ready.
	 *
	 * Translations should be added in the WordPress language directory:
	 *      - WP_LANG_DIR/plugins/woocommerce-mix-and-match-products-LOCALE.mo
	 *
	 * @since  2.2.0
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'wc-not-sold-separately', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Hooks for plugin support.
	 */
	public static function add_hooks() {

		// Declare WC Feature compatibility.
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_feature_compatibility' ) );

		// Admin
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'product_options' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_meta' ) );

		// Variable Product.
		add_action( 'woocommerce_variation_options', array( __CLASS__, 'product_variations_options' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'save_product_variation' ), 30, 2 );

		// Manipulate product availability.
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'is_purchasable' ), 99, 2 );
		add_filter( 'woocommerce_variation_is_visible', array( __CLASS__, 'is_visible' ), 99, 4 );
		
		// Remove is_purchasable filter in cart session.
		add_action( 'woocommerce_load_cart_from_session', array( __CLASS__, 'set_cart_loading' ) );

		// Restore is_purchasable filter after cart loaded.
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'remove_cart_loading' ) );

		// Change add to cart validation error.
		add_filter( 'woocommerce_cart_product_cannot_be_purchased_message', array( __CLASS__, 'product_cannot_be_purchased_message' ), 10, 2 );  

		// Catch any stray standalone products.
		add_filter( 'woocommerce_pre_remove_cart_item_from_session', array( __CLASS__, 'remove_cart_item_from_session' ), 10, 3 );

		// Display.
		foreach ( wc_get_product_types() as $type => $title ) {
			add_action( 'woocommerce_' . $type . '_add_to_cart', array( __CLASS__, 'display_related_bundles' ), 1 );
		}

	}

	/*-----------------------------------------------------------------------------------*/
	/* Core Compat */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Declare compatibility with WooCommerce features.
	 * 
	 * @since 2.4.0
	 */
	public static function declare_feature_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		// Declare HPOS (Custom Order tables) compatibility.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', plugin_basename( __FILE__ ), true );
	}

	/*-----------------------------------------------------------------------------------*/
	/* Admin Display */
	/*-----------------------------------------------------------------------------------*/


	/**
	 * Adds the sold separately option.
	 */
	public static function product_options() {

		global $product_object;

		// Not sold separately meta.
		woocommerce_wp_checkbox( array(
			'id'          => '_not_sold_separately',
			'label'       => esc_html__( 'Not sold separately', 'wc-not-sold-separately' ),
			'wrapper_class' => 'show_if_simple show_if_variable',
			'value'       => self::is_not_sold_separately( $product_object, 'edit' ) ? 'yes' : 'no',
			'description' => esc_html__( 'Enable this if this product should only be sold as part of a bundle.', 'wc-not-sold-separately' ),
		) );
	}


	/**
	 * Save the meta
	 *
	 * @param  WC_Product  $product
	 */
	public static function save_meta( $product ) {

		if ( $product->is_type( array( 'simple', 'variable' ) ) ) {

			// phpcs:disable WordPress.Security.NonceVerification
			if ( isset( $_POST['_not_sold_separately'] ) ) {
				$product->update_meta_data( '_not_sold_separately', 'yes' );
			} else {
				$product->delete_meta_data( '_not_sold_separately' );
			}

		}
	}

	/**
	 * Add checkbox to each variation
	 *
	 * @since 1.1.0
	 *
	 * @param string  $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 */
	public static function product_variations_options( $loop, $variation_data, $variation ) {

		$not_sold_separately = self::is_not_sold_separately( $variation->ID, 'edit' );
		?>

		<label><input type="checkbox" class="checkbox not_sold_separately" name="not_sold_separately[<?php echo esc_attr( $loop ); ?>]" <?php checked( $not_sold_separately, true ); ?> /> <?php esc_html_e( 'Not sold separately', 'wc-not-sold-separately' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'Enable this if this product should only be sold as part of a bundle.', 'wc-not-sold-separately' ); ?>" href="#">[?]</a></label>

		<?php
	}

	/**
	 * Save extra meta info for variations
	 *
	 * @since 1.1.0
	 *
	 * @param obj WC_Product_Variation $variation
	 * @param int $i
	 */
	public static function save_product_variation( $variation, $i ) {
		// phpcs:disable WordPress.Security.NonceVerification
		$not_sold_separately = wc_bool_to_string( isset( $_POST['not_sold_separately'][ $i ] ) );
		$variation->update_meta_data( '_not_sold_separately', $not_sold_separately );
	}


	/*-----------------------------------------------------------------------------------*/
	/* Front-end Display */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Prevent products from being added to cart if not sold separately.
	 * 
	 * @param bool $is_purchasable
	 * @param WC_Product $product Product object.
	 * @return  bool
	 */
	public static function is_purchasable( $is_purchasable, $product ) {

		if ( ! self::is_cart_loading() ) {

			if ( ! $product->get_parent_id() && self::is_not_sold_separately( $product ) && ! self::is_in_bundled_context( $product ) ) {
				$is_purchasable = false;
			}
		}
		return $is_purchasable;
	}

	/**
	 * Prevent variations from displaying if not sold separately.
	 *
	 * @since  1.1.0
	 * 
	 * @param bool $is_visible
	 * @param int $variation_id
	 * @param int $parent_id
	 * @param WC_Product_Variation $variation Product object.
	 * @return  bool
	 */
	public static function is_visible( $is_visible, $variation_id, $parent_id, $variation ) {

		if ( ! self::is_cart_loading() ) {
		
			if ( self::is_not_sold_separately( $variation ) && ! self::is_in_bundled_context( $variation ) ) { 
				$is_visible = false;
			}
		}
		return $is_visible;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Cart validation.                                                                  */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Set flag to bypass is_purchasable filter while loading the cart from session.
	 */
	public static function set_cart_loading() {
		self::$cart_loading = true;
	}

	/**
	 * Reset cart loading flag.
	 */
	public static function remove_cart_loading() {
		self::$cart_loading = false;
	}

	/**
	 * Filters message about product unable to be purchased.
	 *
	 * @since 2.0.0
	 * @param string     $message Message.
	 * @param WC_Product $product Product data.
	 */
	public static function product_cannot_be_purchased_message( $message, $product ) {
		if ( self::is_not_sold_separately( $product ) ) {
			// Translators: %s is the name of the product being added to the cart.
			$message = sprintf( esc_html__( 'Sorry, %s is not sold separately.', 'wc-not-sold-separately' ), $product->get_name() );
		}
		return $message;
	}


	/**
	 * Prevent products from being added to cart if not sold separately.
	 * 
	 * @param bool $remove If true, the item will not be added to the cart. Default: false.
	 * @param string $key Cart item key.
	 * @param array $values Cart item values e.g. quantity and product_id.
	 * @return  bool
	 */
	public static function remove_cart_item_from_session( $remove, $key, $values ) {

		$is_bundled = self::is_in_bundled_cart_context( $values );

		if ( ! $is_bundled ) {

			$product = wc_get_product( $values['variation_id'] ? $values['variation_id'] : $values['product_id'] );

			$remove = self::is_not_sold_separately( $product );
		
			if ( $remove ) {
				/* translators: %s: product name */
				$message = sprintf( esc_html__( '%s has been removed from your cart because it cannot be purchased separately. Please contact us if you need assistance.', 'wc-not-sold-separately' ), $product->get_name() );
				/**
				 * Filter message about item removed from the cart.
				 *
				 * @since 2.0.0
				 * @param string     $message Message.
				 * @param WC_Product $product Product data.
				 */
				$message = apply_filters( 'wc_not_sold_separately_cart_item_removed_message', $message, $product );
				wc_add_notice( $message, 'error' );
			}
		}
		return $remove;
	}

	/*-----------------------------------------------------------------------------------*/
	/* Front end display */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Register new stock status.
	 * 
	 * @since 2.4.0
	 */
	public static function display_related_bundles() {
		global $product;

		if ( self::is_not_sold_separately( $product ) ) {
			
			$related_bundles = self::get_related_bundles( $product );

			if ( $related_bundles ) {
				echo '<p class="not-sold-separately">' . esc_html__( 'This product is not sold separately, but may be purchased as part of the following products:', 'wc-not-sold-separately' ) . '</p>';
				echo do_shortcode( '[products ids="' . implode( ',', array_values($related_bundles)) . '" limit="3" orderby="rand"]' );
			}
		}

	}

	/*-----------------------------------------------------------------------------------*/
	/* Helpers                                                                           */
	/*-----------------------------------------------------------------------------------*/

	/**
	 * Is cart loading?
	 * 
	 * @since 2.3.0
	 * @return bool
	 */
	public static function is_cart_loading() {
		return true === self::$cart_loading;
	}

	/**
	 * Test if the product is part of a bundle.
	 *
	 * @param mixed int|WC_Product
	 *
	 * @return bool
	 */
	private static function is_not_sold_separately( $product, $context = 'view' ) {

		if ( is_integer( $product ) ) { 
			$product = wc_get_product( $product );
		}

		return $product instanceof WC_Product && wc_string_to_bool( $product->get_meta( '_not_sold_separately', true, $context ) );
	}

	/**
	 * Test if the product is part of a bundle.
	 *
	 * @param mixed int|WC_Product
	 *
	 * @return bool True if has defining prop set on product.
	 */
	private static function is_in_bundled_context( $product ) {

		if ( is_integer( $product ) ) { 
			$product = wc_get_product( $product );
		}

		$is_in_bundled_context = false;

		foreach ( self::$bundled_props as $prop ) {
			if ( self::property_exists( $product, $prop ) ) {
				$is_in_bundled_context = true;
				break;
			}
		}
		
		/**
		 * Filter whether product is in bundled context.
		 *
		 * @since 2.1.0
		 * @param bool       $is_in_bundled_context Bundled by container or standalone.
		 * @param WC_Product $product Product data.
		 */
		return (bool) apply_filters( 'wc_not_sold_separately_is_in_bundled_context', $is_in_bundled_context, $product );
	}

	/**
	 * Test if the cart item is part of a bundle.
	 * 
	 * @since 2.1.0
	 *
	 * @param array $cart_item Cart item values
	 * @return bool True if cart item is bundled by a container.
	 */
	private static function is_in_bundled_cart_context( $cart_item ) {

		$is_in_bundled_cart_context = false;
		
		foreach ( self::$bundled_cart_fn as $fn ) {
			if ( $fn( $cart_item ) ) {
				$is_in_bundled_cart_context = true;
				break;
			}

		}

		/**
		 * Filter whether cart item is in bundled context.
		 *
		 * @since 2.0.2
		 * @param bool  $is_in_bundled_cart_context Bundled by container or standalone.
		 * @param array $cart_item Cart item values. NB: Does not yet include WC_Product on 'data'
		 */
		return (bool) apply_filters( 'wc_not_sold_separately_is_in_bundled_cart_context', $is_in_bundled_cart_context, $cart_item );
	}

	/**
	 * Does the property exist.
	 * 
	 * @since 2.4.0
	 *
	 * @param WC_Product   $product
	 * @param string       $key
	 *
	 * @return bool
	 */
	private static function property_exists( $product, $key ) {

		/**
		 * Mix and Match adds a weakmap in v2.7 to track product data in order to resolve the PHP 8.2 warnings about dynamic object properties.
		 */
		if ( ! empty( self::$data_getters ) ) {
			foreach ( self::$data_getters as $getter ) {

				if ( is_callable( $getter ) ) {
					$exists = ! is_null( call_user_func( $getter, $product, $key ) );
					if ( $exists ) {
						break;
					}
				}
			}
		} else { 
			$exists = property_exists( $product, $key );
		}

		return $exists;

	}

	/**
	 * Query the related bundles.
	 * 
	 * @since 2.4.0
	 * 
	 * @param WC_Product $product
	 * @return array
	 */
	private static function get_related_bundles( $product ) {
		$related_bundles = array();

		foreach( self::$related_bundle_fn as $fn ) {
			$related_bundles = array_merge( $related_bundles, call_user_func( $fn, $product ) );
		}

		return $related_bundles;
	
	}
	
}
add_action( 'plugins_loaded', array( 'WC_Not_Sold_Separately', 'init' ), 20 );