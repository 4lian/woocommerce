<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Order
 *
 * Handles order data and database interaction.
 *
 * @class       WC_Abstract_Order
 * @version     2.6.0
 * @package     WooCommerce/Classes
 * @category    Class
 * @author      WooThemes
 *
 * @todo check date formats are bw compat and consistant
 */
abstract class WC_Abstract_Order implements WC_Data {

    /**
     * Data array, with defaults.
     *
     * @todo when migrating to custom tables, these will be columns
     * @since 2.6.0
     * @var array
     */
    protected $_data = array(
		'order_id'             => 0,
        'parent_id'            => 0,
		'status'               => '',
        /**
         * @todo confusion. Was 'simple'. But this was not the same as post_type, which is shop_order. Other post types in core are shop_order_refund
         * The order type for shop_order_refund is refund.
         * Why do we need two separate variables? This should be unified, especially once this is in a custom table and post_type is redundent.
         * Switching to 'shop_order', and then using this value in the order factory instead of post_type. @thenbrent might have feedback on this.
         */
		'order_type'           => 'shop_order',
		'order_key'            => '',
		'order_currency'       => '',
		'date_created'         => '',
		'date_modified'        => '',
		'customer_id'          => 0,
		'billing_first_name'   => '',
		'billing_last_name'    => '',
		'billing_company'      => '',
		'billing_address_1'    => '',
		'billing_address_2'    => '',
		'billing_city'         => '',
		'billing_state'        => '',
		'billing_postcode'     => '',
		'billing_country'      => '',
		'billing_email'        => '',
		'billing_phone'        => '',
		'shipping_first_name'  => '',
		'shipping_last_name'   => '',
		'shipping_company'     => '',
		'shipping_address_1'   => '',
		'shipping_address_2'   => '',
		'shipping_city'        => '',
		'shipping_state'       => '',
		'shipping_postcode'    => '',
		'shipping_country'     => '',
		'discount_total'       => 0,
		'discount_tax'         => 0,
		'shipping_total'       => 0,
		'shipping_tax'         => 0,
		'cart_tax'             => 0, // cart_tax is the new name for the legacy 'order_tax' which is the tax for items only, not shipping.
		'order_total'          => 0,
		'order_tax'            => 0, // Sum of all taxes.
    );

    /**
     * Stores meta data.
     * @var array
     */
    protected $_meta = array(
        'payment_method'       => '',
		'payment_method_title' => '',
		'transaction_id'       => '',
		'customer_ip_address'  => '',
		'customer_user_agent'  => '',
		'created_via'          => '',
		'order_version'        => '',
		'prices_include_tax'   => false,
		'customer_note'        => '',
		'date_completed'       => '',
		'date_paid'            => '',
    );

    /**
     * Stores data about status changes so relevant hooks can be fired.
     * @var bool|array
     */
    protected $_status_transition = false;

    /**
     * Get the order if ID is passed, otherwise the order is new and empty.
     * This class should NOT be instantiated, but the get_order function or new WC_Order_Factory.
     * should be used. It is possible, but the aforementioned are preferred and are the only.
     * methods that will be maintained going forward.
     *
     * @param  int|object|WC_Order $order Order to init.
     */
    public function __construct( $order = 0 ) {
		if ( is_numeric( $order ) ) {
            $this->read( $order );
        } elseif ( $order instanceof WC_Order ) {
            $this->read( absint( $order->get_id() ) );
        } elseif ( ! empty( $order->ID ) ) {
            $this->read( absint( $order->ID ) );
        }
    }

    /**
     * Change data to JSON format.
     * @return string Data in JSON format.
     */
    public function __toString() {
        return json_encode( $this->get_data() );
    }

    /**
     * Get Meta Data by Key
     * @param  string $key
     * @return mixed
     */
    public function get_meta( $key = null ){
		if ( is_null( $key ) ) {
			return $this->_meta;
		} else {
        	return isset( $this->_meta[ $key ] ) ? $this->_meta[ $key ] : null;
		}
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    |
    | Methods for getting data from the order object.
    |
    */

    /**
     * Get all class data in array format.
     * @since 2.6.0
     * @return array
     */
    public function get_data() {
        return array_merge(
            $this->_data,
            $this->_meta,
            array(
                'line_items'     => $this->get_items( 'line_item' ),
                'tax_lines'      => $this->get_items( 'tax' ),
                'shipping_lines' => $this->get_items( 'shipping' ),
                'fee_lines'      => $this->get_items( 'fee' ),
                'coupon_lines'   => $this->get_items( 'coupon' ),
            )
        );
    }

    /**
     * Get order ID.
     * @since 2.6.0
     * @return integer
     */
    public function get_id() {
        return $this->get_order_id();
    }

    /**
     * Get order ID.
     * @since 2.6.0
     * @return integer
     */
    public function get_order_id() {
        return absint( $this->_data['order_id'] );
    }

    /**
     * Get parent order ID.
     * @since 2.6.0
     * @return integer
     */
    public function get_parent_id() {
        return absint( $this->_data['parent_id'] );
    }

    /**
     * get_order_number function.
     *
     * Gets the order number for display (by default, order ID).
     *
     * @return string
     */
    public function get_order_number() {
        return apply_filters( 'woocommerce_order_number', $this->get_id(), $this );
    }

    /**
     * Get order key.
     * @since 2.6.0
     * @return string
     */
    public function get_order_key() {
        return $this->_data['order_key'];
    }

    /**
     * Gets order currency.
     * @return string
     */
    public function get_order_currency() {
        return apply_filters( 'woocommerce_get_order_currency', $this->_data['order_currency'], $this );
    }

    /**
     * Get Order Type
     * @return string
     */
    public function get_order_type() {
        return $this->_data['order_type'];
    }

    /**
     * Get date_created
     * @return string
     */
    public function get_date_created() {
        return $this->_data['date_created'];
    }

    /**
     * Get date_modified
     * @return string
     */
    public function get_date_modified() {
        return $this->_data['date_modified'];
    }

    /**
     * Get customer_id
     * @return int
     */
    public function get_customer_id() {
        return absint( $this->_data['customer_id'] );
    }

    /**
     * Get billing_first_name
     * @return string
     */
    public function get_billing_first_name() {
        return $this->_data['billing_first_name'];
    }

    /**
     * Get billing_last_name
     * @return string
     */
    public function get_billing_last_name() {
        return $this->_data['billing_last_name'];
    }

    /**
     * Get billing_company
     * @return string
     */
    public function get_billing_company() {
        return $this->_data['billing_company'];
    }

    /**
     * Get billing_address_1
     * @return string
     */
    public function get_billing_address_1() {
        return $this->_data['billing_address_1'];
    }

    /**
     * Get billing_address_2
     * @return string $value
     */
    public function get_billing_address_2() {
        return $this->_data['billing_address_2'];
    }

    /**
     * Get billing_city
     * @return string $value
     */
    public function get_billing_city() {
        return $this->_data['billing_city'];
    }

    /**
     * Get billing_state
     * @return string
     */
    public function get_billing_state() {
        return $this->_data['billing_state'];
    }

    /**
     * Get billing_postcode
     * @return string
     */
    public function get_billing_postcode() {
        return $this->_data['billing_postcode'];
    }

    /**
     * Get billing_country
     * @return string
     */
    public function get_billing_country() {
        return $this->_data['billing_country'];
    }

    /**
     * Get billing_email
     * @return string
     */
    public function get_billing_email() {
        return sanitize_email( $this->_data['billing_email'] );
    }

    /**
     * Get billing_phone
     * @return string
     */
    public function get_billing_phone() {
        return $this->_data['billing_phone'];
    }

    /**
     * Get shipping_first_name
     * @return string
     */
    public function get_shipping_first_name() {
        return $this->_data['shipping_first_name'];
    }

    /**
     * Get shipping_last_name
     * @return string
     */
    public function get_shipping_last_name() {
         return $this->_data['shipping_last_name'];
    }

    /**
     * Get shipping_company
     * @return string
     */
    public function get_shipping_company() {
        return $this->_data['shipping_company'];
    }

    /**
     * Get shipping_address_1
     * @return string
     */
    public function get_shipping_address_1() {
        return $this->_data['shipping_address_1'];
    }

    /**
     * Get shipping_address_2
     * @return string
     */
    public function get_shipping_address_2() {
        return $this->_data['shipping_address_2'];
    }

    /**
     * Get shipping_city
     * @return string
     */
    public function get_shipping_city() {
        return $this->_data['shipping_city'];
    }

    /**
     * Get shipping_state
     * @return string
     */
    public function get_shipping_state() {
        return $this->_data['shipping_state'];
    }

    /**
     * Get shipping_postcode
     * @return string
     */
    public function get_shipping_postcode() {
        return $this->_data['shipping_postcode'];
    }

    /**
     * Get shipping_country
     * @return string
     */
    public function get_shipping_country() {
        return $this->_data['shipping_country'];
    }

    /**
     * Get the payment method.
     * @return string
     */
    public function get_payment_method() {
        return $this->_meta['payment_method'];
    }

    /**
     * Get payment_method_title
     * @return string
     */
    public function get_payment_method_title() {
        return $this->_meta['payment_method_title'];
    }

    /**
     * Get transaction_id
     * @return string
     */
    public function get_transaction_id() {
        return $this->_meta['transaction_id'];
    }

    /**
     * Get customer_ip_address
     * @return string
     */
    public function get_customer_ip_address() {
        return $this->_meta['customer_ip_address'];
    }

    /**
     * Get customer_user_agent
     * @return string
     */
    public function get_customer_user_agent() {
        return $this->_meta['customer_user_agent'];
    }

    /**
     * Get created_via
     * @return string
     */
    public function get_created_via() {
        return $this->_meta['created_via'];
    }

    /**
     * Get order_version
     * @return string
     */
    public function get_order_version() {
        return $this->_meta['order_version'];
    }

    /**
     * Get prices_include_tax
     * @return bool
     */
    public function get_prices_include_tax() {
        return (bool) $this->_meta['prices_include_tax'];
    }

    /**
     * Get customer_note
     * @return string
     */
    public function get_customer_note() {
        return $this->_meta['customer_note'];
    }

	/**
     * Get date_completed
     * @return string
     */
    public function get_date_completed() {
        return $this->_meta['date_completed'];
    }

	/**
     * Get date_paid
     * @return string
     */
    public function get_date_paid() {
        return $this->_meta['date_paid'];
    }

    /**
     * Returns the requested address in raw, non-formatted way.
     * @since  2.4.0
     * @param  string $type Billing or shipping. Anything else besides 'billing' will return shipping address.
     * @return array The stored address after filter.
     */
    public function get_address( $type = 'billing' ) {
        if ( 'billing' === $type ) {
            $address = array(
                'first_name' => $this->get_billing_first_name(),
                'last_name'  => $this->get_billing_last_name(),
                'company'    => $this->get_billing_company(),
                'address_1'  => $this->get_billing_address_1(),
                'address_2'  => $this->get_billing_address_2(),
                'city'       => $this->get_billing_city(),
                'state'      => $this->get_billing_state(),
                'postcode'   => $this->get_billing_postcode(),
                'country'    => $this->get_billing_country(),
                'email'      => $this->get_billing_email(),
                'phone'      => $this->get_billing_phone()
            );
        } else {
            $address = array(
                'first_name' => $this->get_shipping_first_name(),
                'last_name'  => $this->get_shipping_last_name(),
                'company'    => $this->get_shipping_company(),
                'address_1'  => $this->get_shipping_address_1(),
                'address_2'  => $this->get_shipping_address_2(),
                'city'       => $this->get_shipping_city(),
                'state'      => $this->get_shipping_state(),
                'postcode'   => $this->get_shipping_postcode(),
                'country'    => $this->get_shipping_country()
            );
        }
        return apply_filters( 'woocommerce_get_order_address', $address, $type, $this );
    }

    /**
     * Return the order statuses without wc- internal prefix.
     * @return string
     */
    public function get_status() {
        return apply_filters( 'woocommerce_order_get_status', 'wc-' === substr( $this->_data['status'], 0, 3 ) ? substr( $this->_data['status'], 3 ) : $this->_data['status'], $this );
    }

    /**
     * Alias for get_customer_id().
     * @since  2.2
     * @return int
     */
    public function get_user_id() {
        return $this->get_customer_id();
    }

    /**
     * Get the user associated with the order. False for guests.
     *
     * @since  2.2
     * @return WP_User|false
     */
    public function get_user() {
        return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
    }

    /**
     * Get a formatted billing address for the order.
     * @return string
     */
    public function get_formatted_billing_address() {
        return WC()->countries->get_formatted_address( apply_filters( 'woocommerce_order_formatted_billing_address', $this->get_address( 'billing' ), $this ) );
    }

    /**
     * Get a formatted shipping address for the order.
     * @return string
     */
    public function get_formatted_shipping_address() {
        if ( $this->get_shipping_address_1() || $this->get_shipping_address_2() ) {
            return WC()->countries->get_formatted_address( apply_filters( 'woocommerce_order_formatted_shipping_address', $this->get_address( 'shipping' ), $this ) );
        } else {
            return '';
        }
    }

    /**
     * Get a formatted shipping address for the order.
     *
     * @return string
     */
    public function get_shipping_address_map_url() {
        $address = apply_filters( 'woocommerce_shipping_address_map_url_parts', array(
            'address_1' => $this->get_shipping_address_1(),
            'address_2' => $this->get_shipping_address_2(),
            'city'      => $this->get_shipping_city(),
            'state'     => $this->get_shipping_state(),
            'postcode'  => $this->get_shipping_postcode(),
            'country'   => $this->get_shipping_country()
        ), $this );
        return apply_filters( 'woocommerce_shipping_address_map_url', 'http://maps.google.com/maps?&q=' . urlencode( implode( ', ', $address ) ) . '&z=16', $this );
    }

    /**
     * Get a formatted billing full name.
     *
     * @since 2.4.0
     *
     * @return string
     */
    public function get_formatted_billing_full_name() {
        return sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ),  $this->get_billing_first_name(), $this->get_billing_last_name() );
    }

    /**
     * Get a formatted shipping full name.
     *
     * @since 2.4.0
     *
     * @return string
     */
    public function get_formatted_shipping_full_name() {
        return sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ),  $this->get_shipping_first_name(), $this->get_shipping_last_name() );
    }

    /**
     * Get discount_total
     * @return string
     */
    public function get_discount_total() {
        $discount_total = wc_format_decimal( $this->_data['discount_total'] );

        // Backwards compatible total calculation - totals were not stored consistently in old versions.
        if ( ( ! $this->get_order_version() || version_compare( $this->get_order_version(), '2.3.7', '<' ) ) && $this->get_prices_include_tax() ) {
            $discount_total = $discount_total - $this->get_discount_tax();
        }

        return $discount_total;
    }

    /**
     * Get discount_tax
     * @return string
     */
    public function get_discount_tax() {
        return wc_format_decimal( $this->_data['discount_tax'] );
    }

    /**
     * Get shipping_total
     * woocommerce_order_amount_total_shipping filter has been removed to avoid
     * these values being modified and then saved back to the DB. There are
     * other, later hooks available to change totals on display. e.g.
     * woocommerce_get_order_item_totals.
     * @return string
     */
    public function get_shipping_total() {
        return wc_format_decimal( $this->_data['shipping_total'] );
    }

    /**
     * Gets cart tax amount.
     *
     * @since 2.6.0 woocommerce_order_amount_cart_tax filter has been removed to avoid
     * these values being modified and then saved back to the DB or used in
     * calculations. There are other, later hooks available to change totals on
     * display. e.g. woocommerce_get_order_item_totals.
     * @return float
     */
    public function get_cart_tax() {
        return wc_format_decimal( $this->_data['cart_tax'] );
    }

    /**
     * Get shipping_tax.
     *
     * @since 2.6.0 woocommerce_order_amount_shipping_tax filter has been removed to avoid
     * these values being modified and then saved back to the DB or used in
     * calculations. There are other, later hooks available to change totals on
     * display. e.g. woocommerce_get_order_item_totals.
     * @return string
     */
    public function get_shipping_tax() {
        return wc_format_decimal( $this->_data['shipping_tax'] );
    }

    /**
     * Order tax is the sum of all taxes.
     * @return string
     */
    public function get_order_tax() {
        return wc_round_tax_total( $this->_data['order_tax'] );
    }

    /**
     * Get the stored order total. Includes taxes and everything else.
     * @return string
     */
    public function get_order_total() {
        return wc_format_decimal( $this->_data['order_total'], wc_get_price_decimals() );
    }

    /**
     * Gets the total discount amount.
     * @param  bool $ex_tax Show discount excl any tax.
     * @return float
     */
    public function get_total_discount( $ex_tax = true ) {
        if ( $ex_tax ) {
            $total_discount = $this->get_discount_total();
        } else {
            $total_discount = $this->get_discount_total() + $this->get_discount_tax();
        }
        return apply_filters( 'woocommerce_order_amount_total_discount', round( $total_discount, WC_ROUNDING_PRECISION ), $this );
    }

    /**
     * Get total tax amount. Alias for get_order_tax().
     *
     * @since 2.6.0 woocommerce_order_amount_total_tax filter has been removed to avoid
     * these values being modified and then saved back to the DB. There are
     * other, later hooks available to change totals on display. e.g.
     * woocommerce_get_order_item_totals.
     * @return float
     */
    public function get_total_tax() {
        return $this->get_order_tax();
    }

    /**
     * Gets shipping total. Alias of WC_Order::get_shipping_total().
     *
     * @since 2.6.0 woocommerce_order_amount_total_shipping filter has been removed to avoid
     * these values being modified and then saved back to the DB or used in
     * calculations. There are other, later hooks available to change totals on
     * display. e.g. woocommerce_get_order_item_totals.
     * @return float
     */
    public function get_total_shipping() {
        return $this->get_shipping_total();
    }

    /**
     * Gets order grand total. incl. taxes. Used in gateways. Filtered.
     * @return float
     */
    public function get_total() {
        return apply_filters( 'woocommerce_order_amount_total', $this->get_order_total(), $this );
    }

    /**
     * Gets order subtotal.
     * @return float
     */
    public function get_subtotal() {
        $subtotal = 0;

        foreach ( $this->get_items() as $item ) {
            $subtotal += isset( $item['line_subtotal'] ) ? $item['line_subtotal'] : 0;
        }

        return apply_filters( 'woocommerce_order_amount_subtotal', (double) $subtotal, $this );
    }

    /**
     * Get taxes, merged by code, formatted ready for output.
     *
     * @return array
     */
    public function get_tax_totals() {
        $tax_totals = array();

        foreach ( $this->get_items( 'tax' ) as $key => $tax ) {
            $code = $tax[ 'name' ];

            if ( ! isset( $tax_totals[ $code ] ) ) {
                $tax_totals[ $code ] = new stdClass();
                $tax_totals[ $code ]->amount = 0;
            }

            $tax_totals[ $code ]->id                = $key;
            $tax_totals[ $code ]->rate_id           = $tax['rate_id'];
            $tax_totals[ $code ]->is_compound       = $tax[ 'compound' ];
            $tax_totals[ $code ]->label             = isset( $tax[ 'label' ] ) ? $tax[ 'label' ] : $tax[ 'name' ];
            $tax_totals[ $code ]->amount           += $tax[ 'tax_amount' ] + $tax[ 'shipping_tax_amount' ];
            $tax_totals[ $code ]->formatted_amount  = wc_price( wc_round_tax_total( $tax_totals[ $code ]->amount ), array('currency' => $this->get_order_currency()) );
        }

        return apply_filters( 'woocommerce_order_tax_totals', $tax_totals, $this );
    }

    /**
     * Gets formatted shipping method title.
     * @return string
     */
    public function get_shipping_method() {
        $names = array();
        foreach ( $this->get_shipping_methods() as $shipping_method ) {
            $names[] = $shipping_method->get_name();
        }
        return apply_filters( 'woocommerce_order_shipping_method', implode( ', ', $names ), $this );
    }

    /*
    |--------------------------------------------------------------------------
    | Setters
    |--------------------------------------------------------------------------
    |
    | Functions for setting order data. These should not update anything in the
    | database itself and should only change what is stored in the class
    | object. However, for backwards compatibility pre 2.6.0 some of these
    | setters may handle both.
    |
    */

    /**
     * Set order ID.
     * @since 2.6.0
     * @param int $value
     */
    public function set_order_id( $value ) {
        $this->_data['order_id'] = absint( $value );
    }

    /**
     * Set parent order ID.
     * @since 2.6.0
     * @param int $value
     */
    public function set_parent_id( $value ) {
        $this->_data['parent_id'] = absint( $value );
    }

    /**
     * Set order status.
     * @since 2.6.0
     * @param string $new_status Status to change the order to. No internal wc- prefix is required.
     * @param string $note (default: '') Optional note to add.
     * @param bool $manual is this a manual order status change?
     */
    public function set_status( $new_status, $note = '', $manual_update = false ) {
        // Remove prefixes and standardize
        $current_status = $this->get_status();
        $new_status     = 'wc-' === substr( $new_status, 0, 3 ) ? substr( $new_status, 3 ) : $new_status;

        if ( in_array( 'wc-' . $new_status, array_keys( wc_get_order_statuses() ) ) && $new_status !== $current_status ) {
            if ( ! empty( $current_status ) ) {
                $this->_status_transition = array(
                    'original' => ! empty( $this->_status_transition['original'] ) ? $this->_status_transition['original'] : $current_status,
                    'note'     => $note ? $note : '',
                    'manual'   => (bool) $manual_update
                );
				if ( 'completed' === $new_status ) {
					$this->set_date_completed( current_time( 'timestamp' ) );
				}
            }
            $this->_data['status'] = 'wc-' . $new_status;
        }
    }

	/**
     * Updates status of order immediately.
     * @uses WC_Order::set_status()
     */
    public function update_status( $new_status, $note = '', $manual = false ) {
        if ( ! $this->get_id() ) {
            return false;
        }
		$this->set_status( $new_status, $note, $manual );
		$this->save();
        return true;
    }

    /**
     * Set Order Type
     * @param string $value
     */
    public function set_order_type( $value ) {
        $this->_data['order_type'] = $value;
    }

    /**
     * Set order_key
     * @param string $value
     */
    public function set_order_key( $value ) {
        $this->_data['order_key'] = $value;
    }

    /**
     * Set order_currency
     * @param string $value
     */
    public function set_order_currency( $value ) {
        $this->_data['order_currency'] = $value;
    }

    /**
     * Set date_created
     * @param string $timestamp Timestamp
     */
    public function set_date_created( $timestamp ) {
        $this->_data['date_created'] = is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp );
    }

    /**
     * Set date_modified
     * @param string $timestamp
     */
    public function set_date_modified( $timestamp ) {
        $this->_data['date_modified'] = is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp );
    }

    /**
     * Set customer_id
     * @param int $value
     */
    public function set_customer_id( $value ) {
        $this->_data['customer_id'] = absint( $value );
    }

    /**
     * Set billing_first_name
     * @param string $value
     */
    public function set_billing_first_name( $value ) {
        $this->_data['billing_first_name'] = $value;
    }

    /**
     * Set billing_last_name
     * @param string $value
     */
    public function set_billing_last_name( $value ) {
        $this->_data['billing_last_name'] = $value;
    }

    /**
     * Set billing_company
     * @param string $value
     */
    public function set_billing_company( $value ) {
        $this->_data['billing_company'] = $value;
    }

    /**
     * Set billing_address_1
     * @param string $value
     */
    public function set_billing_address_1( $value ) {
        $this->_data['billing_address_1'] = $value;
    }

    /**
     * Set billing_address_2
     * @param string $value
     */
    public function set_billing_address_2( $value ) {
        $this->_data['billing_address_2'] = $value;
    }

    /**
     * Set billing_city
     * @param string $value
     */
    public function set_billing_city( $value ) {
        $this->_data['billing_city'] = $value;
    }

    /**
     * Set billing_state
     * @param string $value
     */
    public function set_billing_state( $value ) {
        $this->_data['billing_state'] = $value;
    }

    /**
     * Set billing_postcode
     * @param string $value
     */
    public function set_billing_postcode( $value ) {
        $this->_data['billing_postcode'] = $value;
    }

    /**
     * Set billing_country
     * @param string $value
     */
    public function set_billing_country( $value ) {
        $this->_data['billing_country'] = $value;
    }

    /**
     * Set billing_email
     * @param string $value
     */
    public function set_billing_email( $value ) {
		$value = sanitize_email( $value );
        $this->_data['billing_email'] = is_email( $value ) ? $value : '';
    }

    /**
     * Set billing_phone
     * @param string $value
     */
    public function set_billing_phone( $value ) {
        $this->_data['billing_phone'] = $value;
    }

    /**
     * Set shipping_first_name
     * @param string $value
     */
    public function set_shipping_first_name( $value ) {
        $this->_data['shipping_first_name'] = $value;
    }

    /**
     * Set shipping_last_name
     * @param string $value
     */
    public function set_shipping_last_name( $value ) {
        $this->_data['shipping_last_name'] = $value;
    }

    /**
     * Set shipping_company
     * @param string $value
     */
    public function set_shipping_company( $value ) {
        $this->_data['shipping_company'] = $value;
    }

    /**
     * Set shipping_address_1
     * @param string $value
     */
    public function set_shipping_address_1( $value ) {
        $this->_data['shipping_address_1'] = $value;
    }

    /**
     * Set shipping_address_2
     * @param string $value
     */
    public function set_shipping_address_2( $value ) {
        $this->_data['shipping_address_2'] = $value;
    }

    /**
     * Set shipping_city
     * @param string $value
     */
    public function set_shipping_city( $value ) {
        $this->_data['shipping_city'] = $value;
    }

    /**
     * Set shipping_state
     * @param string $value
     */
    public function set_shipping_state( $value ) {
        $this->_data['shipping_state'] = $value;
    }

    /**
     * Set shipping_postcode
     * @param string $value
     */
    public function set_shipping_postcode( $value ) {
        $this->_data['shipping_postcode'] = $value;
    }

    /**
     * Set shipping_country
     * @param string $value
     */
    public function set_shipping_country( $value ) {
        $this->_data['shipping_country'] = $value;
    }

    /**
     * Set discount_total
     * @param string $value
     */
    public function set_discount_total( $value ) {
        $this->_data['discount_total'] = wc_format_decimal( $value );
    }

    /**
     * Set discount_tax
     * @param string $value
     */
    public function set_discount_tax( $value ) {
        $this->_data['discount_tax'] = wc_format_decimal( $value );
    }

    /**
     * Set shipping_total
     * @param string $value
     */
    public function set_shipping_total( $value ) {
        $this->_data['shipping_total'] = wc_format_decimal( $value );
    }

    /**
     * Set shipping_tax
     * @param string $value
     */
    public function set_shipping_tax( $value ) {
        $this->_data['shipping_tax'] = wc_format_decimal( $value );
        $this->set_order_tax( $this->get_cart_tax() + $this->get_shipping_tax() );
    }

    /**
     * Set cart tax
     * @param string $value
     */
    public function set_cart_tax( $value ) {
        $this->_data['cart_tax'] = wc_format_decimal( $value );
        $this->set_order_tax( $this->get_cart_tax() + $this->get_shipping_tax() );
    }

    /**
     * Sets order tax (sum of cart and shipping tax). Used internaly only.
     * @access protected
     * @param string $value
     */
    protected function set_order_tax( $value ) {
        $this->_data['order_tax'] = wc_format_decimal( $value );
    }

    /**
     * Set order_total
     * @param string $value
     */
    public function set_order_total( $value ) {
        $this->_data['order_total'] = wc_format_decimal( $value, wc_get_price_decimals() );
    }

    /**
     * Set the payment method ID.
     * @since 2.2.0
     * @param string $value Supports WC_Payment_Gateway for bw compatibility with < 2.6
     */
    public function set_payment_method( $value ) {
        if ( is_object( $value ) ) {
            update_post_meta( $this->get_id(), '_payment_method', $value->id );
            update_post_meta( $this->get_id(), '_payment_method_title', $value->get_title() );
            $this->set_payment_method( $value->id );
            $this->set_payment_method_title( $value->get_title() );
        } else {
            $this->_meta['payment_method'] = $value;
        }
    }

    /**
     * Set payment_method_title
     * @param string $value
     */
    public function set_payment_method_title( $value ) {
        $this->_meta['payment_method_title'] = $value;
    }

    /**
     * Set transaction_id
     * @param string $value
     */
    public function set_transaction_id( $value ) {
        $this->_meta['transaction_id'] = $value;
    }

    /**
     * Set customer_ip_address
     * @param string $value
     */
    public function set_customer_ip_address( $value ) {
        $this->_meta['customer_ip_address'] = $value;
    }

    /**
     * Set customer_user_agent
     * @param string $value
     */
    public function set_customer_user_agent( $value ) {
        $this->_meta['customer_user_agent'] = $value;
    }

    /**
     * Set created_via
     * @param string $value
     */
    public function set_created_via( $value ) {
        $this->_meta['created_via'] = $value;
    }

    /**
     * Set order_version
     * @param string $value
     */
    public function set_order_version( $value ) {
        $this->_meta['order_version'] = $value;
    }

    /**
     * Set prices_include_tax
     * @param bool $value
     */
    public function set_prices_include_tax( $value ) {
        $this->_meta['prices_include_tax'] = (bool) $value;
    }

    /**
     * Set customer_note
     * @param string $value
     */
    public function set_customer_note( $value ) {
        $this->_meta['customer_note'] = $value;
    }

	/**
     * Set date_completed
     * @param string $timestamp
     */
    public function set_date_completed( $timestamp ) {
        $this->_meta['date_completed'] = is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp );
    }

	/**
     * Set date_paid
     * @param string $timestamp
     */
    public function set_date_paid( $timestamp ) {
        $this->_meta['date_paid'] = is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp );
    }

    /**
     * Set the customer address.
     * @since 2.2.0
     * @param array $address Address data.
     * @param string $type billing or shipping.
     */
    public function set_address( $address, $type = 'billing' ) {
        foreach ( $address as $key => $value ) {
            update_post_meta( $this->get_id(), "_{$type}_" . $key, $value );
            if ( method_exists( $this, "set_{$type}_{$key}" ) ) {
                $this->{"set_{$type}_{$key}"}( $value );
            }
        }
    }

    /**
     * Set an order total.
     * @since 2.2.0
     * @param float $amount
     * @param string $total_type
     * @return bool
     */
    public function set_total( $amount, $total_type = 'total' ) {
        if ( ! in_array( $total_type, array( 'shipping', 'tax', 'shipping_tax', 'total', 'cart_discount', 'cart_discount_tax' ) ) ) {
            return false;
        }

        switch ( $total_type ) {
            case 'total' :
                $amount = wc_format_decimal( $amount, wc_get_price_decimals() );
                $this->set_order_total( $amount );
                update_post_meta( $this->get_id(), '_order_total', $amount );
                break;
            case 'cart_discount' :
                $amount = wc_format_decimal( $amount );
                $this->set_discount_total( $amount );
                update_post_meta( $this->get_id(), '_cart_discount', $amount );
                break;
            case 'cart_discount_tax' :
                $amount = wc_format_decimal( $amount );
                $this->set_discount_tax( $amount );
                update_post_meta( $this->get_id(), '_cart_discount_tax', $amount );
                break;
            case 'shipping' :
                $amount = wc_format_decimal( $amount );
                $this->set_shipping_total( $amount );
                update_post_meta( $this->get_id(), '_order_shipping', $amount );
                break;
            case 'shipping_tax' :
                $amount = wc_format_decimal( $amount );
                $this->set_shipping_tax( $amount );
                update_post_meta( $this->get_id(), '_order_shipping_tax', $amount );
                break;
            case 'tax' :
                $amount = wc_format_decimal( $amount );
                $this->set_cart_tax( $amount );
                update_post_meta( $this->get_id(), '_order_tax', $amount );
                break;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD methods
    |--------------------------------------------------------------------------
    |
    | Methods which create, read, update and delete orders from the database.
    | Written in abstract fashion so that the way orders are stored can be
    | changed more easily in the future.
    |
    | A save method is included for convenience (chooses update or create based
    | on if the order exists yet).
    |
    */

    /**
     * Insert data into the database.
     * @since 2.6.0
     * @access protected
     */
    public function create() {
        // Set random key
        $this->set_order_key( uniqid( 'order_' ) );

        $order_id = wp_insert_post( apply_filters( 'woocommerce_new_order_data', array(
            'post_type'     => $this->get_order_type(),
            'post_status'   => 'wc-' . ( $this->get_status() ? $this->get_status() : apply_filters( 'woocommerce_default_order_status', 'pending' ) ),
            'ping_status'   => 'closed',
            'post_author'   => 1,
            'post_title'    => sprintf( __( 'Order &ndash; %s', 'woocommerce' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'woocommerce' ) ) ),
            'post_password' => $this->get_order_key(),
            'post_parent'   => $this->get_parent_id()
        ) ), true );

        if ( $order_id ) {
            $this->set_order_id( $order_id );

            // Set meta data
            update_post_meta( $order_id, '_billing_first_name', $this->get_billing_first_name() );
            update_post_meta( $order_id, '_billing_last_name', $this->get_billing_last_name() );
            update_post_meta( $order_id, '_billing_company', $this->get_billing_company() );
            update_post_meta( $order_id, '_billing_address_1', $this->get_billing_address_1() );
            update_post_meta( $order_id, '_billing_address_2', $this->get_billing_address_2() );
            update_post_meta( $order_id, '_billing_city', $this->get_billing_city() );
            update_post_meta( $order_id, '_billing_state', $this->get_billing_state() );
            update_post_meta( $order_id, '_billing_postcode', $this->get_billing_postcode() );
            update_post_meta( $order_id, '_billing_country', $this->get_billing_country() );
            update_post_meta( $order_id, '_billing_email', $this->get_billing_email() );
            update_post_meta( $order_id, '_billing_phone', $this->get_billing_phone() );
            update_post_meta( $order_id, '_shipping_first_name', $this->get_shipping_first_name() );
            update_post_meta( $order_id, '_shipping_last_name', $this->get_shipping_last_name() );
            update_post_meta( $order_id, '_shipping_company', $this->get_shipping_company() );
            update_post_meta( $order_id, '_shipping_address_1', $this->get_shipping_address_1() );
            update_post_meta( $order_id, '_shipping_address_2', $this->get_shipping_address_2() );
            update_post_meta( $order_id, '_shipping_city', $this->get_shipping_city() );
            update_post_meta( $order_id, '_shipping_state', $this->get_shipping_state() );
            update_post_meta( $order_id, '_shipping_postcode', $this->get_shipping_postcode() );
            update_post_meta( $order_id, '_shipping_country', $this->get_shipping_country() );
            update_post_meta( $order_id, '_order_currency', $this->get_order_currency() );
            update_post_meta( $order_id, '_order_key', $this->get_order_key() );
            update_post_meta( $order_id, '_cart_discount', $this->get_discount_total() );
            update_post_meta( $order_id, '_cart_discount_tax', $this->get_discount_tax() );
            update_post_meta( $order_id, '_order_shipping', $this->get_shipping_total() );
            update_post_meta( $order_id, '_order_shipping_tax', $this->get_shipping_tax() );
            update_post_meta( $order_id, '_order_tax', $this->get_cart_tax() );
            update_post_meta( $order_id, '_order_total', $this->get_order_total() );

			foreach ( $this->get_meta() as $key => $value ) {
				update_post_meta( $order_id, '_' . $key, $value );
			}
        }
    }

    /**
     * Read from the database.
     * @since 2.6.0
     * @access protected
     * @param int $id ID of object to read.
     */
    public function read( $id ) {
        $post_object = get_post( $id );
        $order_id    = absint( $post_object->ID );

        // Map standard post data
        $this->set_order_id( $order_id );
        $this->set_date_created( $post_object->post_date );
        $this->set_date_modified( $post_object->post_modified );
        $this->set_status( $post_object->post_status );
        $this->set_customer_note( $post_object->post_excerpt );

        // Map meta data
        $this->set_customer_id( get_post_meta( $order_id, '_customer_user', true ) );
        $this->set_order_key( get_post_meta( $order_id, '_order_key', true ) );
        $this->set_order_currency( get_post_meta( $order_id, '_order_currency', true ) );
        $this->set_billing_first_name( get_post_meta( $order_id, '_billing_first_name', true ) );
        $this->set_billing_last_name( get_post_meta( $order_id, '_billing_last_name', true ) );
        $this->set_billing_company( get_post_meta( $order_id, '_billing_company', true ) );
        $this->set_billing_address_1( get_post_meta( $order_id, '_billing_address_1', true ) );
        $this->set_billing_address_2( get_post_meta( $order_id, '_billing_address_2', true ) );
        $this->set_billing_city( get_post_meta( $order_id, '_billing_city', true ) );
        $this->set_billing_state( get_post_meta( $order_id, '_billing_state', true ) );
        $this->set_billing_postcode( get_post_meta( $order_id, '_billing_postcode', true ) );
        $this->set_billing_country( get_post_meta( $order_id, '_billing_country', true ) );
        $this->set_billing_email( get_post_meta( $order_id, '_billing_email', true ) );
        $this->set_billing_phone( get_post_meta( $order_id, '_billing_phone', true ) );
        $this->set_shipping_first_name( get_post_meta( $order_id, '_shipping_first_name', true ) );
        $this->set_shipping_last_name( get_post_meta( $order_id, '_shipping_last_name', true ) );
        $this->set_shipping_company( get_post_meta( $order_id, '_shipping_company', true ) );
        $this->set_shipping_address_1( get_post_meta( $order_id, '_shipping_address_1', true ) );
        $this->set_shipping_address_2( get_post_meta( $order_id, '_shipping_address_2', true ) );
        $this->set_shipping_city( get_post_meta( $order_id, '_shipping_city', true ) );
        $this->set_shipping_state( get_post_meta( $order_id, '_shipping_state', true ) );
        $this->set_shipping_postcode( get_post_meta( $order_id, '_shipping_postcode', true ) );
        $this->set_shipping_country( get_post_meta( $order_id, '_shipping_country', true ) );

		// Map totals
        $this->set_discount_total( get_post_meta( $order_id, '_cart_discount', true ) );
        $this->set_discount_tax( get_post_meta( $order_id, '_cart_discount_tax', true ) );
        $this->set_shipping_total( get_post_meta( $order_id, '_order_shipping', true ) );
        $this->set_shipping_tax( get_post_meta( $order_id, '_order_shipping_tax', true ) );
        $this->set_cart_tax( get_post_meta( $order_id, '_order_tax', true ) );
        $this->set_order_total( get_post_meta( $order_id, '_order_total', true ) );

        // Map user data
        if ( empty( $this->get_billing_email() ) && ( $user = $this->get_user() ) ) {
            $this->set_billing_email( $user->user_email );
        }

        // This is meta data, but due to the keys not matching the props, load here
		$this->set_date_completed( get_post_meta( $order_id, '_completed_date', true ) );
		$this->set_date_paid( get_post_meta( $order_id, '_paid_date', true ) );

		// Load meta data, including anything custom set.
		$ignore_keys = array( '_customer_user', '_order_key', '_order_currency', '_billing_first_name', '_billing_last_name', '_billing_company', '_billing_address_1', '_billing_address_2', '_billing_city', '_billing_state', '_billing_postcode', '_billing_country', '_billing_email', '_billing_phone', '_shipping_first_name', '_shipping_last_name', '_shipping_company', '_shipping_address_1', '_shipping_address_2', '_shipping_city', '_shipping_state', '_shipping_postcode', '_shipping_country', '_completed_date', '_paid_date', '_edit_lock', '_cart_discount', '_cart_discount_tax', '_order_shipping', '_order_shipping_tax', '_order_tax', '_order_total', '_order_total' );
		$meta_data   = get_post_meta( $order_id );
		$meta_data   = array_diff_key( $meta_data, array_fill_keys( $ignore_keys, '' ) );

		// Set meta data. Remove _ from key for hidden meta.
		foreach ( $meta_data as $key => $value ) {
			$this->_meta[ ltrim( $key, '_' ) ] = $value[0];
		}

        // Orders store the state of prices including tax when created.
        $this->prices_include_tax = metadata_exists( 'post', $order_id, '_prices_include_tax' ) ? 'yes' === get_post_meta( $order_id, '_prices_include_tax', true ) : 'yes' === get_option( 'woocommerce_prices_include_tax' );
    }

    /**
     * Update data in the database.
     * @since 2.6.0
     * @access protected
     */
    public function update() {
        global $wpdb;

        $order_id = $this->get_id();

        $wpdb->update(
            $wpdb->posts,
            array(
                'post_date'     => date( 'Y-m-d H:i:s', $this->get_date_created() ),
                'post_date_gmt' => get_gmt_from_date( date( 'Y-m-d H:i:s', $this->get_date_created() ) ),
                'post_status'   => 'wc-' . ( $this->get_status() ? $this->get_status() : apply_filters( 'woocommerce_default_order_status', 'pending' ) ),
                'post_parent'   => $this->get_parent_id()
            ),
            array(
                'ID' => $order_id
            )
        );

        // Update meta data
        update_post_meta( $order_id, '_billing_first_name', $this->get_billing_first_name() );
        update_post_meta( $order_id, '_billing_last_name', $this->get_billing_last_name() );
        update_post_meta( $order_id, '_billing_company', $this->get_billing_company() );
        update_post_meta( $order_id, '_billing_address_1', $this->get_billing_address_1() );
        update_post_meta( $order_id, '_billing_address_2', $this->get_billing_address_2() );
        update_post_meta( $order_id, '_billing_city', $this->get_billing_city() );
        update_post_meta( $order_id, '_billing_state', $this->get_billing_state() );
        update_post_meta( $order_id, '_billing_postcode', $this->get_billing_postcode() );
        update_post_meta( $order_id, '_billing_country', $this->get_billing_country() );
        update_post_meta( $order_id, '_billing_email', $this->get_billing_email() );
        update_post_meta( $order_id, '_billing_phone', $this->get_billing_phone() );
        update_post_meta( $order_id, '_shipping_first_name', $this->get_shipping_first_name() );
        update_post_meta( $order_id, '_shipping_last_name', $this->get_shipping_last_name() );
        update_post_meta( $order_id, '_shipping_company', $this->get_shipping_company() );
        update_post_meta( $order_id, '_shipping_address_1', $this->get_shipping_address_1() );
        update_post_meta( $order_id, '_shipping_address_2', $this->get_shipping_address_2() );
        update_post_meta( $order_id, '_shipping_city', $this->get_shipping_city() );
        update_post_meta( $order_id, '_shipping_state', $this->get_shipping_state() );
        update_post_meta( $order_id, '_shipping_postcode', $this->get_shipping_postcode() );
        update_post_meta( $order_id, '_shipping_country', $this->get_shipping_country() );

		update_post_meta( $order_id, '_payment_method', $this->get_payment_method() );
        update_post_meta( $order_id, '_payment_method_title', $this->get_payment_method_title() );
        update_post_meta( $order_id, '_transaction_id', $this->get_transaction_id() );
        update_post_meta( $order_id, '_customer_user', $this->get_customer_id() );
        update_post_meta( $order_id, '_customer_ip_address', $this->get_customer_ip_address() );
        update_post_meta( $order_id, '_customer_user_agent', $this->get_customer_user_agent() );
        update_post_meta( $order_id, '_created_via', $this->get_created_via() );
        update_post_meta( $order_id, '_order_version', $this->get_order_version() );
        update_post_meta( $order_id, '_prices_include_tax', $this->get_prices_include_tax() );
        update_post_meta( $order_id, '_order_currency', $this->get_order_currency() );
        update_post_meta( $order_id, '_order_key', $this->get_order_key() );
        update_post_meta( $order_id, '_cart_discount', $this->get_discount_total() );
        update_post_meta( $order_id, '_cart_discount_tax', $this->get_discount_tax() );
        update_post_meta( $order_id, '_order_shipping', $this->get_shipping_total() );
        update_post_meta( $order_id, '_order_shipping_tax', $this->get_shipping_tax() );
        update_post_meta( $order_id, '_order_tax', $this->get_cart_tax() );
        update_post_meta( $order_id, '_order_total', $this->get_order_total() );

        if ( $this->_status_transition ) {
            if ( ! empty( $this->_status_transition['original'] ) ) {
                $transition_note = sprintf( __( 'Order status changed from %s to %s.', 'woocommerce' ), wc_get_order_status_name( $this->_status_transition['original'] ), wc_get_order_status_name( $this->get_status() ) );

                do_action( 'woocommerce_order_status_' . $this->_status_transition['original'] . '_to_' . $this->get_status(), $this->get_id() );
                do_action( 'woocommerce_order_status_changed', $this->get_id(), $this->_status_transition['original'], $this->get_status() );
            } else {
                $transition_note = sprintf( __( 'Order status set to %s.', 'woocommerce' ), wc_get_order_status_name( $this->get_status() ) );
            }

            do_action( 'woocommerce_order_status_' . $this->get_status(), $this->get_id() );

            // Note the transition occured
            $this->add_order_note( trim( $this->_status_transition['note'] . ' ' . $transition_note ), 0, $this->_status_transition['manual'] );

            // This has ran, so reset status transition variable
            $this->_status_transition = false;
        }
    }

    /**
     * Delete data from the database.
     * @since 2.6.0
     * @access protected
     */
    public function delete() {
        wp_delete_post( $this->get_id() );
    }

    /**
     * Save data to the database.
     * @since 2.6.0
     * @access protected
     */
    public function save() {
        if ( ! $this->get_id() ) {
            $this->create();
        } else {
            $this->update();
        }
        wc_delete_shop_order_transients( $this->get_id() );
    }

    /*
    |--------------------------------------------------------------------------
    | Order Item Handling
    |--------------------------------------------------------------------------
    |
    | Order items are used for products, taxes, shipping, and fees within
    | each order.
    |
    */

    /**
     * Return an array of items/products within this order.
     *
     * @param string|array $type Types of line items to get (array or string).
     * @return Array of WC_Order_item
     */
    public function get_items( $type = 'line_item' ) {
        global $wpdb;

        $type            = ! is_array( $type ) ? array( $type ) : $type;
        $items           = array();
        $get_items_sql   = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d ", $this->get_id() );
        $get_items_sql  .= "AND order_item_type IN ( '" . implode( "','", array_map( 'esc_sql', $type ) ) . "' ) ORDER BY order_item_id;";
        $raw_items       = $wpdb->get_results( $get_items_sql );

        foreach ( $raw_items as $item ) {
            $item                                = $this->get_item( $item );
            $items[ $item->get_order_item_id() ] = $item;
        }

        return apply_filters( 'woocommerce_order_get_items', $items, $this );
    }

    /**
     * Get an order item object, based on it's type.
     * @param  int $item_id
     * @return WC_Order_Item
     */
    public function get_item( $item_id ) {
        return WC_Order_Factory::get_order_item( $item_id );
    }

    /**
     * Display meta data belonging to an item. @todo
     * @param  array $item
     */
    public function display_item_meta( $item ) {
        $product   = $this->get_product_from_item( $item );
        $item_meta = new WC_Order_Item_Meta( $item, $product );
        $item_meta->display();
    }

    /**
     * Return an array of fees within this order.
     *
     * @return array
     */
    public function get_fees() {
        return $this->get_items( 'fee' );
    }

    /**
     * Return an array of taxes within this order.
     *
     * @return array
     */
    public function get_taxes() {
        return $this->get_items( 'tax' );
    }

    /**
     * Return an array of shipping costs within this order.
     *
     * @return array
     */
    public function get_shipping_methods() {
        return $this->get_items( 'shipping' );
    }

    /**
     * Get coupon codes only.
     *
     * @return array
     */
    public function get_used_coupons() {
        return array_map( 'trim', wp_list_pluck( $this->get_items( 'coupon' ), 'name' ) );
    }

    /**
     * Gets the count of order items of a certain type.
     *
     * @param string $item_type
     * @return string
     */
    public function get_item_count( $item_type = '' ) {
        if ( empty( $item_type ) ) {
            $item_type = array( 'line_item' );
        }
        if ( ! is_array( $item_type ) ) {
            $item_type = array( $item_type );
        }

        $items = $this->get_items( $item_type );
        $count = 0;

        foreach ( $items as $item ) {
            $count += $item->get_qty();
        }

        return apply_filters( 'woocommerce_get_item_count', $count, $item_type, $this );
    }

    /**
     * Remove all line items (products, coupons, shipping, taxes) from the order.
     *
     * @param string $type Order item type. Default null.
     */
    public function remove_order_items( $type = null ) {
        global $wpdb;

        if ( ! empty( $type ) ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM itemmeta USING {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items WHERE itemmeta.order_item_id = items.order_item_id AND items.order_id = %d AND items.order_item_type = %s", $this->get_id(), $type ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d AND order_item_type = %s", $this->get_id(), $type ) );
        } else {
            $wpdb->query( $wpdb->prepare( "DELETE FROM itemmeta USING {$wpdb->prefix}woocommerce_order_itemmeta itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items items WHERE itemmeta.order_item_id = items.order_item_id and items.order_id = %d", $this->get_id() ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d", $this->get_id() ) );
        }
    }

    /**
     * Add a product line item to the order.
     * Order must be saved prior to adding items.
     *
     * @since 2.2
     * @param \WC_Product $product
     * @param int $qty Line item quantity.
     * @param array $args
     * @return int updated order item ID
     */
    public function add_product( $product, $qty = 1, $args = array() ) {
        $args = wp_parse_args( $args, array(
            'name'         => $product->get_title(),
            'qty'          => absint( $qty ),
            'tax_class'    => $product->get_tax_class(),
            'product_id'   => $product->id,
            'variation_id' => isset( $product->variation_id ) ? $product->variation_id : 0,
            'variation'    => array(),
            'subtotal'     => $product->get_price_excluding_tax( $qty ),
            'subtotal_tax' => 0,
            'total'        => $product->get_price_excluding_tax( $qty ),
            'total_tax'    => 0,
            'taxes'        => array(
                'subtotal' => array(),
                'total'    => array()
            )
        ) );
        $item = new WC_Order_Item_Product();
        $item_id = $this->update_product( $item, $product, $args );

		do_action( 'woocommerce_order_add_product', $this->get_id(), $item->get_order_item_id(), $product, $qty, $args );

		return $item_id;
    }

    /**
     * Update a line item for the order.
     *
     * Note this does not update order totals.
     *
     * @since 2.2
     * @param object|int $item order item ID or item object.
     * @param WC_Product $product
     * @param array $args data to update.
     * @return int updated order item ID
     */
    public function update_product( $item, $product, $args ) {
        if ( is_numeric( $item ) ) {
            $item = $this->get_item( $item );
        }

        if ( ! is_object( $product ) || ! $item->is_type( 'line_item' ) ) {
            return false;
        }

        if ( ! $this->get_id() ) {
            $this->save();
        }

		$item->set_order_id( $this->get_id() );

        if ( ! $item->get_order_item_id() ) {
            $inserting = true;
        } else {
            $inserting = false;
        }

        if ( isset( $args['name'] ) ) {
            $item->set_name( $args['name'] );
        }

        if ( isset( $args['qty'] ) ) {
            $item->set_qty( $args['qty'] );

            if ( $product->backorders_require_notification() && $product->is_on_backorder( $args['qty'] ) ) {
                $item->add_meta_data( apply_filters( 'woocommerce_backordered_item_meta_name', __( 'Backordered', 'woocommerce' ) ), $args['qty'] - max( 0, $product->get_total_stock() ), true );
            }

            $item->set_line_subtotal( $product->get_price_excluding_tax( $args['qty'] ) );
            $item->set_line_total( $product->get_price_excluding_tax( $args['qty'] ) );
        }

        if ( isset( $args['tax_class'] ) ) {
            $item->set_tax_class( $args['tax_class'] );
        }

        if ( isset( $args['product_id'] ) ) {
            $item->set_product_id( $args['product_id'] );
        }

        if ( isset( $args['variation_id'] ) ) {
            $item->set_variation_id( $args['variation_id'] );
        }

        if ( isset( $args['variation'] ) && is_array( $args['variation'] ) ) {
            foreach ( $args['variation'] as $key => $value ) {
                $item->add_meta_data( str_replace( 'attribute_', '', $key ), $value, true );
            }
        }

        if ( isset( $args['totals'] ) ) {
            // BW compatibility with old args
            if ( isset( $args['totals']['subtotal'] ) ) {
                $args['subtotal'] = $args['totals']['subtotal'];
            }
            if ( isset( $args['totals']['total'] ) ) {
                $args['total'] = $args['totals']['total'];
            }
            if ( isset( $args['totals']['subtotal_tax'] ) ) {
                $args['subtotal_tax'] = $args['totals']['subtotal_tax'];
            }
            if ( isset( $args['totals']['tax'] ) ) {
                $args['total_tax'] = $args['totals']['tax'];
            }
            if ( isset( $args['totals']['tax_data'] ) ) {
                $args['taxes'] = $args['totals']['tax_data'];
            }
        }

        if ( isset( $args['subtotal'] ) ) {
            $item->set_subtotal( $args['subtotal'] );
        }
        if ( isset( $args['total'] ) ) {
            $item->set_total( $args['total'] );
        }
        if ( isset( $args['subtotal_tax'] ) ) {
            $item->set_line_subtotal_tax( $args['subtotal_tax'] );
        }
        if ( isset( $args['total_tax'] ) ) {
            $item->set_total_tax( $args['total_tax'] );
        }
        if ( isset( $args['taxes'] ) ) {
            $item->set_taxes( $args['taxes'] );
        }

        $item->save();

        if ( ! $inserting ) {
            do_action( 'woocommerce_order_edit_product', $this->get_id(), $item->get_order_item_id(), $args, $product );
        }

        return $item->get_order_item_id();
    }

    /**
     * Add coupon code to the order.
     * Order must be saved prior to adding items.
     *
     * @param string $code
     * @param int $discount_amount
     * @param int $discount_amount_tax "Discounted" tax - used for tax inclusive prices.
     * @return int updated order item ID
     */
    public function add_coupon( $code, $discount_amount = 0, $discount_amount_tax = 0 ) {
        $args = wp_parse_args( $args, array(
            'code'                => $code,
            'discount_amount'     => $discount_amount,
            'discount_amount_tax' => $discount_amount_tax
        ) );
        $item = new WC_Order_Item_Coupon();
        $item_id = $this->update_coupon( $item, $args );

		do_action( 'woocommerce_order_add_coupon', $this->get_id(), $item->get_order_item_id(), $code, $discount_amount, $discount_amount_tax );

		return $item_id;
    }

    /**
     * Update coupon for order. Note this does not update order totals.
     * @since 2.2
     * @param object|int $item
     * @param array $args
     * @return int updated order item ID
     */
    public function update_coupon( $item, $args ) {
        if ( is_numeric( $item ) ) {
            $item = $this->get_item( $item );
        }

        if ( ! is_object( $product ) || ! $item->is_type( 'coupon' ) ) {
            return false;
        }

        if ( ! $this->get_id() ) {
            $this->save();
        }

		$item->set_order_id( $this->get_id() );

        if ( ! $item->get_order_item_id() ) {
            $inserting = true;
        } else {
            $inserting = false;
        }

        if ( isset( $args['code'] ) ) {
            $item->set_coupon_code( $args['code'] );
        }
        if ( isset( $args['discount_amount'] ) ) {
            $item->set_discount_amount( $args['discount_amount'] );
        }
        if ( isset( $args['discount_amount_tax'] ) ) {
            $item->set_discount_amount_tax( $args['discount_amount_tax'] );
        }

        $item->save();

        if ( ! $inserting ) {
            do_action( 'woocommerce_order_update_coupon', $this->get_id(), $item->get_order_item_id(), $args );
        }

        return $item->get_order_item_id();
    }

    /**
     * Add a shipping row to the order.
     * Order must be saved prior to adding items.
     *
     * @param WC_Shipping_Rate shipping_rate
     * @return int updated order item ID
     */
    public function add_shipping( $shipping_rate ) {
        $args = wp_parse_args( $args, array(
            'method_title' => $shipping_rate->label,
            'method_id'    => $shipping_rate->id,
            'cost'         => wc_format_decimal( $shipping_rate->cost ),
            'taxes'        => $shipping_rate->taxes,
            'meta'         => $shipping_rate->get_meta_data(),
        ) );

        $item = new WC_Order_Item_Shipping();
        $item_id = $this->update_shipping( $item, $args );

		do_action( 'woocommerce_order_add_shipping', $this->get_id(), $item->get_order_item_id(), $shipping_rate );

		return $item_id;
    }

    /**
     * Update shipping method for order.
     *
     * Note this does not update the order total.
     *
     * @since 2.2
     * @param object|int $item
     * @param array $args
     * @return int updated order item ID
     */
    public function update_shipping( $item, $args ) {
        if ( is_numeric( $item ) ) {
            $item = $this->get_item( $item );
        }

        if ( ! is_object( $product ) || ! $item->is_type( 'shipping' ) ) {
            return false;
        }

        if ( ! $this->get_id() ) {
            $this->save();
        }

		$item->set_order_id( $this->get_id() );

        if ( ! $item->get_order_item_id() ) {
            $inserting = true;
        } else {
            $inserting = false;
        }

        if ( isset( $args['method_title'] ) ) {
            $item->set_method_title( $args['method_title'] );
        }

        if ( isset( $args['method_id'] ) ) {
            $item->set_method_id( $args['method_id'] );
        }

        if ( isset( $args['cost'] ) ) {
            // Get old cost before updating
            $old_cost = $item->get_cost();

            // Update
            $item->set_cost( $args['cost'] );

            // Update total
            $this->set_total( $this->get_total_shipping() - wc_format_decimal( $old_cost ) + $item->get_cost(), 'shipping' );
        }

        if ( isset( $args['taxes'] ) && is_array( $args['taxes'] ) ) {
            $item->set_taxes( $args['taxes'] );
        }

        if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $key => $value ) {
				$item->update_meta_data( $key, $value );
			}
		}

        $item->save();

        if ( ! $inserting ) {
            do_action( 'woocommerce_order_update_shipping', $this->get_id(), $item->get_order_item_id(), $args );
        }

        return $item->get_order_item_id();
    }

    /**
     * Add a fee to the order.
     * Order must be saved prior to adding items.
     * @param object $fee
     * @return int updated order item ID
     */
    public function add_fee( $fee ) {
        $args = wp_parse_args( $args, array(
            'name'      => $fee->name,
            'tax_class' => $fee->taxable ? $fee->tax_class : 0,
            'total'     => $fee->amount,
            'total_tax' => $fee->tax,
            'taxes'     => array(
                'total' => $fee->tax_data
            )
        ) );
        $item = new WC_Order_Item_Fee();
        $item_id = $this->update_fee( $item, $args );

        do_action( 'woocommerce_order_add_fee', $this->get_id(), $item->get_order_item_id(), $fee );

        return $item_id;
    }

    /**
     * Update fee for order.
     *
     * Note this does not update order totals.
     *
     * @since 2.2
     * @param object|int $item
     * @param array $args
     * @return int updated order item ID
     */
    public function update_fee( $item, $args ) {
        if ( is_numeric( $item ) ) {
            $item = $this->get_item( $item );
        }

        if ( ! is_object( $product ) || ! $item->is_type( 'fee' ) ) {
            return false;
        }

        if ( ! $this->get_id() ) {
            $this->save();
        }

		$item->set_order_id( $this->get_id() );

        if ( ! $item->get_order_item_id() ) {
            $inserting = true;
        } else {
            $inserting = false;
        }

        if ( isset( $args['name'] ) ) {
            $item->set_name( $args['name'] );
        }

        if ( isset( $args['tax_class'] ) ) {
            $item->set_tax_class( $args['tax_class'] );
        }

        if ( isset( $args['total'] ) ) {
            $item->set_total( $args['total'] );
        }

        if ( isset( $args['total_tax'] ) ) {
            $item->set_total_tax( $args['total_tax'] );
        }

        if ( isset( $args['taxes'] ) ) {
            $item->set_taxes( $args['taxes'] );
        }

        $item->save();

        if ( ! $inserting ) {
            do_action( 'woocommerce_order_update_fee', $this->get_id(), $item->get_order_item_id(), $args );
        }

        return $item->get_order_item_id();
    }

    /**
     * Add a tax row to the order.
     * Order must be saved prior to adding items.
     * @since 2.2
     * @param int tax_rate_id
     * @return int updated order item ID
     */
    public function add_tax( $tax_rate_id, $tax_amount = 0, $shipping_tax_amount = 0 ) {
        if ( ! $code = WC_Tax::get_rate_code( $tax_rate_id ) ) {
            return false;
        }

        $args = wp_parse_args( $args, array(
            'rate_code'          => $code,
            'rate_id'            => $tax_rate_id,
            'label'              => WC_Tax::get_rate_label( $tax_rate_id ),
            'compound'           => WC_Tax::is_compound( $tax_rate_id ),
            'tax_total'          => $tax_amount,
            'shipping_tax_total' => $shipping_tax_amount
        ) );
        $item = new WC_Order_Item_Tax();
        $item_id = $this->update_tax( $item, $args );

        do_action( 'woocommerce_order_add_tax', $this->get_id(), $item->get_order_item_id(), $tax_rate_id, $tax_amount, $shipping_tax_amount );

        return $item_id;
    }

    /**
     * Update tax line on order.
     * Note this does not update order totals.
     *
     * @since 2.6
     * @param object|int $item
     * @param array $args
     * @return int updated order item ID
     */
    public function update_tax( $item, $args ) {
        if ( is_numeric( $item ) ) {
            $item = $this->get_item( $item );
        }

        if ( ! is_object( $product ) || ! $item->is_type( 'tax' ) ) {
            return false;
        }

        if ( ! $this->get_id() ) {
            $this->save();
        }

		$item->set_order_id( $this->get_id() );

        if ( ! $item->get_order_item_id() ) {
            $inserting = true;
        } else {
            $inserting = false;
        }

        if ( isset( $args['rate_code'] ) ) {
            $item->set_rate_code( $args['rate_code'] );
        }

        if ( isset( $args['rate_id'] ) ) {
            $item->set_rate_id( $args['rate_id'] );
        }

        if ( isset( $args['label'] ) ) {
            $item->set_label( $args['label'] );
        }

        if ( isset( $args['compound'] ) ) {
            $item->set_compound( $args['compound'] );
        }

        if ( isset( $args['tax_total'] ) ) {
            $item->set_tax_total( $args['tax_total'] );
        }

        if ( isset( $args['shipping_tax_total'] ) ) {
            $item->set_shipping_tax_total( $args['shipping_tax_total'] );
        }

        $item->save();

        if ( ! $inserting ) {
            do_action( 'woocommerce_order_update_tax', $this->get_id(), $item->get_order_item_id(), $args );
        }

        return $item->get_order_item_id();
    }

	/**
     * Update tax lines at order level by looking at the line item taxes themselves.
     * @return bool success or fail.
     */
    public function update_taxes() {
        $cart_taxes     = array();
        $shipping_taxes = array();

        foreach ( $this->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
            $taxes = $item->get_taxes();
            if ( isset( $taxes['total'] ) ) {
                foreach ( $taxes['total'] as $tax_rate_id => $tax ) {
                    if ( ! isset( $cart_taxes[ $tax_rate_id ] ) ) {
                        $cart_taxes[ $tax_rate_id ] = 0;
                    }
                    $cart_taxes[ $tax_rate_id ] += $tax;
                }
            }
        }

        foreach ( $this->get_items( array( 'shipping' ) ) as $item_id => $item ) {
			$taxes = $item->get_taxes();
            if ( isset( $taxes['total'] ) ) {
                foreach ( $taxes['total'] as $tax_rate_id => $tax ) {
                    if ( ! isset( $shipping_taxes[ $tax_rate_id ] ) ) {
                        $shipping_taxes[ $tax_rate_id ] = 0;
                    }
                    $shipping_taxes[ $tax_rate_id ] += $tax;
                }
            }
        }

        // Remove old existing tax rows.
        $this->remove_order_items( 'tax' );

        // Now merge to keep tax rows.
        foreach ( array_keys( $cart_taxes + $shipping_taxes ) as $tax_rate_id ) {
            $this->add_tax( $tax_rate_id, isset( $cart_taxes[ $tax_rate_id ] ) ? $cart_taxes[ $tax_rate_id ] : 0, isset( $shipping_taxes[ $tax_rate_id ] ) ? $shipping_taxes[ $tax_rate_id ] : 0 );
        }

        // Save tax totals
        $this->set_total( WC_Tax::round( array_sum( $shipping_taxes ) ), 'shipping_tax' );
        $this->set_total( WC_Tax::round( array_sum( $cart_taxes ) ), 'tax' );

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Calculations.
    |--------------------------------------------------------------------------
    |
    | These methods calculate order totals and taxes based on the current data.
    |
    */

    /**
     * Calculate shipping total.
     *
     * @since 2.2
     * @return float
     */
    public function calculate_shipping() {
        $shipping_total = 0;

        foreach ( $this->get_shipping_methods() as $shipping ) {
            $shipping_total += $shipping->get_total();
        }

        $this->set_total( $shipping_total, 'shipping' );

        return $this->get_shipping_total();
    }

    /**
     * Calculate taxes for all line items and shipping, and store the totals and tax rows.
     *
     * Will use the base country unless customer addresses are set.
     *
     * @return bool success or fail.
     */
    public function calculate_taxes() {
        $cart_tax     = 0;
        $cart_taxes   = array();
        $tax_based_on = get_option( 'woocommerce_tax_based_on' );

        if ( 'billing' === $tax_based_on ) {
            $country  = $this->get_billing_country();
            $state    = $this->get_billing_state();
            $postcode = $this->get_billing_postcode();
            $city     = $this->get_billing_city();
        } elseif ( 'shipping' === $tax_based_on ) {
            $country  = $this->get_shipping_country();
            $state    = $this->get_shipping_state();
            $postcode = $this->get_shipping_postcode();
            $city     = $this->get_shipping_city();
        }

        // Default to base
        if ( 'base' === $tax_based_on || empty( $country ) ) {
            $default  = wc_get_base_location();
            $country  = $default['country'];
            $state    = $default['state'];
            $postcode = '';
            $city     = '';
        }

        // Get items
        foreach ( $this->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
			$tax_class  = $item->get_tax_class();
			$tax_status = $item->get_tax_status();

            if ( '0' !== $tax_class && 'taxable' === $item_tax_status ) {

                $tax_rates = WC_Tax::find_rates( array(
                    'country'   => $country,
                    'state'     => $state,
                    'postcode'  => $postcode,
                    'city'      => $city,
                    'tax_class' => $tax_class
                ) );

				$total     = $item->get_total();
				$taxes     = WC_Tax::calc_tax( $total, $tax_rates, false );
				$total_tax = max( 0, array_sum( $taxes ) );
				$cart_tax += $total_tax;
				$item->set_total_tax( $total_tax );

				if ( $item->is_type( 'line_item' ) ) {
					$subtotal       = $item->get_subtotal();
					$subtotal_taxes = WC_Tax::calc_tax( $subtotal, $tax_rates, false );
					$subtotal_tax   = max( 0, array_sum( $subtotal_taxes ) );
					$item->set_subtotal_tax( $subtotal_tax );
					$item->set_taxes( array( 'total' => $taxes, 'subtotal' => $subtotal_taxes ) );
				} else {
					$item->set_taxes( array( 'total' => $taxes ) );
				}

				$item->save(); //@todo store items to self, don't save right away

                // Sum the item taxes
                foreach ( array_keys( $cart_taxes + $taxes ) as $key ) {
                    $cart_taxes[ $key ] = ( isset( $taxes[ $key ] ) ? $taxes[ $key ] : 0 ) + ( isset( $taxes[ $key ] ) ? $cart_taxes[ $key ] : 0 );
                }
            }
        }

        // Now calculate shipping tax
        $shipping_methods = $this->get_shipping_methods();

        if ( ! empty( $shipping_methods ) ) {
            $matched_tax_rates = array();
            $tax_rates         = WC_Tax::find_rates( array(
                'country'   => $country,
                'state'     => $state,
                'postcode'  => $postcode,
                'city'      => $city,
                'tax_class' => ''
            ) );

            if ( ! empty( $tax_rates ) ) {
                foreach ( $tax_rates as $key => $rate ) {
                    if ( isset( $rate['shipping'] ) && 'yes' === $rate['shipping'] ) {
                        $matched_tax_rates[ $key ] = $rate;
                    }
                }
            }

            $shipping_taxes     = WC_Tax::calc_shipping_tax( $this->order_shipping, $matched_tax_rates );
            $shipping_tax_total = WC_Tax::round( array_sum( $shipping_taxes ) );
        } else {
            $shipping_taxes     = array();
            $shipping_tax_total = 0;
        }

        // Save tax totals
        $this->set_total( $shipping_tax_total, 'shipping_tax' );
        $this->set_total( $tax_total, 'tax' );

        // Tax rows
        $this->remove_order_items( 'tax' );

        // Now merge to keep tax rows
        foreach ( array_keys( $taxes + $shipping_taxes ) as $tax_rate_id ) {
            $this->add_tax( $tax_rate_id, isset( $taxes[ $tax_rate_id ] ) ? $taxes[ $tax_rate_id ] : 0, isset( $shipping_taxes[ $tax_rate_id ] ) ? $shipping_taxes[ $tax_rate_id ] : 0 );
        }

        return true;
    }

    /**
     * Calculate totals by looking at the contents of the order. Stores the totals and returns the orders final total.
     *
     * @since 2.2
     * @param  bool $and_taxes Calc taxes if true.
     * @return float calculated grand total.
     */
    public function calculate_totals( $and_taxes = true ) {
        $cart_subtotal     = 0;
        $cart_total        = 0;
        $fee_total         = 0;
        $cart_subtotal_tax = 0;
        $cart_total_tax    = 0;

        if ( $and_taxes && wc_tax_enabled() ) {
            $this->calculate_taxes();
        }

        // line items
        foreach ( $this->get_items() as $item ) {
            $cart_subtotal     += wc_format_decimal( isset( $item['line_subtotal'] ) ? $item['line_subtotal'] : 0 );
            $cart_total        += wc_format_decimal( isset( $item['line_total'] ) ? $item['line_total'] : 0 );
            $cart_subtotal_tax += wc_format_decimal( isset( $item['line_subtotal_tax'] ) ? $item['line_subtotal_tax'] : 0 );
            $cart_total_tax    += wc_format_decimal( isset( $item['line_tax'] ) ? $item['line_tax'] : 0 );
        }

        $this->calculate_shipping();

        foreach ( $this->get_fees() as $item ) {
            $fee_total += $item['line_total'];
        }

        $this->set_total( $cart_subtotal - $cart_total, 'cart_discount' );
        $this->set_total( $cart_subtotal_tax - $cart_total_tax, 'cart_discount_tax' );

        $grand_total = round( $cart_total + $fee_total + $this->get_total_shipping() + $this->get_cart_tax() + $this->get_shipping_tax(), wc_get_price_decimals() );

        $this->set_total( $grand_total, 'total' );

        return $grand_total;
    }

    /*
    |--------------------------------------------------------------------------
    | Total Getters
    |--------------------------------------------------------------------------
    |
    | Methods for getting totals e.g. for display on the frontend.
    |
    */

    /**
     * Get a product (either product or variation).
     *
     * @param object $item
     * @return WC_Product|bool
     */
    public function get_product_from_item( $item ) {
        if ( is_callable( array( $item, 'get_product' ) ) ) {
            $product = $item->get_product();
        } else {
            $product = false;
        }
        return apply_filters( 'woocommerce_get_product_from_item', $product, $item, $this );
    }

    /**
     * Get item subtotal - this is the cost before discount.
     *
     * @param object $item
     * @param bool $inc_tax (default: false).
     * @param bool $round (default: true).
     * @return float
     */
    public function get_item_subtotal( $item, $inc_tax = false, $round = true ) {
        $subtotal = 0;

        if ( is_callable( array( $item, 'get_subtotal' ) ) ) {
            if ( $inc_tax ) {
                $subtotal = ( $item->get_subtotal() + $item->get_subtotal_tax() ) / max( 1, $item->get_qty() );
            } else {
                $subtotal = ( $item->get_subtotal() / max( 1, $item->get_qty() ) );
            }

            $subtotal = $round ? number_format( (float) $subtotal, wc_get_price_decimals(), '.', '' ) : $subtotal;
        }

        return apply_filters( 'woocommerce_order_amount_item_subtotal', $subtotal, $this, $item, $inc_tax, $round );
    }

    /**
     * Get line subtotal - this is the cost before discount.
     *
     * @param object $item
     * @param bool $inc_tax (default: false).
     * @param bool $round (default: true).
     * @return float
     */
    public function get_line_subtotal( $item, $inc_tax = false, $round = true ) {
        $subtotal = 0;

        if ( is_callable( array( $item, 'get_subtotal' ) ) ) {
            if ( $inc_tax ) {
                $subtotal = $item->get_subtotal() + $item->get_subtotal_tax();
            } else {
                $subtotal = $item->get_subtotal();
            }

            $subtotal = $round ? round( $subtotal, wc_get_price_decimals() ) : $subtotal;
        }

        return apply_filters( 'woocommerce_order_amount_line_subtotal', $subtotal, $this, $item, $inc_tax, $round );
    }

    /**
     * Calculate item cost - useful for gateways.
     *
     * @param object $item
     * @param bool $inc_tax (default: false).
     * @param bool $round (default: true).
     * @return float
     */
    public function get_item_total( $item, $inc_tax = false, $round = true ) {
        $total = 0;

        if ( is_callable( array( $item, 'get_total' ) ) ) {
            if ( $inc_tax ) {
                $total = ( $item->get_total() + $item->get_total_tax() ) / max( 1, $item->get_qty() );
            } else {
                $total = $item->get_total() / max( 1, $item->get_qty() );
            }

            $total = $round ? round( $total, wc_get_price_decimals() ) : $total;
        }

        return apply_filters( 'woocommerce_order_amount_item_total', $total, $this, $item, $inc_tax, $round );
    }

    /**
     * Calculate line total - useful for gateways.
     *
     * @param object $item
     * @param bool $inc_tax (default: false).
     * @param bool $round (default: true).
     * @return float
     */
    public function get_line_total( $item, $inc_tax = false, $round = true ) {
        $total = 0;

        if ( is_callable( array( $item, 'get_total' ) ) ) {
            // Check if we need to add line tax to the line total.
            $total = $inc_tax ? $item->get_total() + $item->get_total_tax() : $item->get_total();

            // Check if we need to round.
            $total = $round ? round( $total, wc_get_price_decimals() ) : $total;
        }

        return apply_filters( 'woocommerce_order_amount_line_total', $total, $this, $item, $inc_tax, $round );
    }

    /**
     * Get item tax - useful for gateways.
     *
     * @param mixed $item
     * @param bool $round (default: true).
     * @return float
     */
    public function get_item_tax( $item, $round = true ) {
        $tax = 0;

        if ( is_callable( array( $item, 'get_total_tax' ) ) ) {
            $tax = $item->get_total_tax() / max( 1, $item->get_qty() );
            $tax = $round ? wc_round_tax_total( $tax ) : $tax;
        }

        return apply_filters( 'woocommerce_order_amount_item_tax', $tax, $item, $round, $this );
    }

    /**
     * Get line tax - useful for gateways.
     *
     * @param mixed $item
     * @return float
     */
    public function get_line_tax( $item ) {
        return apply_filters( 'woocommerce_order_amount_line_tax', is_callable( array( $item, 'get_total_tax' ) ) ? wc_round_tax_total( $item->get_total_tax() ) : 0, $item, $this );
    }

    /**
     * Gets line subtotal - formatted for display.
     *
     * @param array  $item
     * @param string $tax_display
     * @return string
     */
    public function get_formatted_line_subtotal( $item, $tax_display = '' ) {
        $tax_display = $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' );

        if ( ! isset( $item['line_subtotal'] ) || ! isset( $item['line_subtotal_tax'] ) ) {
            return '';
        }

        if ( 'excl' == $tax_display ) {
            $ex_tax_label = $this->get_prices_include_tax() ? 1 : 0;

            $subtotal = wc_price( $this->get_line_subtotal( $item ), array( 'ex_tax_label' => $ex_tax_label, 'currency' => $this->get_order_currency() ) );
        } else {
            $subtotal = wc_price( $this->get_line_subtotal( $item, true ), array('currency' => $this->get_order_currency()) );
        }

        return apply_filters( 'woocommerce_order_formatted_line_subtotal', $subtotal, $item, $this );
    }

    /**
     * Gets order total - formatted for display.
     *
     * @return string
     */
    public function get_formatted_order_total() {
        $formatted_total = wc_price( $this->get_total(), array( 'currency' => $this->get_order_currency() ) );
        return apply_filters( 'woocommerce_get_formatted_order_total', $formatted_total, $this );
    }

    /**
     * Gets subtotal - subtotal is shown before discounts, but with localised taxes.
     *
     * @param bool $compound (default: false).
     * @param string $tax_display (default: the tax_display_cart value).
     * @return string
     */
    public function get_subtotal_to_display( $compound = false, $tax_display = '' ) {
        $tax_display = $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' );
        $subtotal    = 0;

        if ( ! $compound ) {
            foreach ( $this->get_items() as $item ) {
                $subtotal += $item->get_subtotal();

                if ( 'incl' === $tax_display ) {
                    $subtotal += $item->get_subtotal_tax();
                }
            }

            $subtotal = wc_price( $subtotal, array( 'currency' => $this->get_order_currency() ) );

            if ( 'excl' === $tax_display && $this->get_prices_include_tax() ) {
                $subtotal .= ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
            }

        } else {
            if ( 'incl' === $tax_display ) {
                return '';
            }

            foreach ( $this->get_items() as $item ) {
                $subtotal += $item->get_subtotal();
            }

            // Add Shipping Costs.
            $subtotal += $this->get_total_shipping();

            // Remove non-compound taxes.
            foreach ( $this->get_taxes() as $tax ) {
                if ( ! empty( $tax['compound'] ) ) {
                    continue;
                }
                $subtotal = $subtotal + $tax['tax_amount'] + $tax['shipping_tax_amount'];
            }

            // Remove discounts.
            $subtotal = $subtotal - $this->get_total_discount();
            $subtotal = wc_price( $subtotal, array( 'currency' => $this->get_order_currency() ) );
        }

        return apply_filters( 'woocommerce_order_subtotal_to_display', $subtotal, $compound, $this );
    }

    /**
     * Gets shipping (formatted).
     *
     * @return string
     */
    public function get_shipping_to_display( $tax_display = '' ) {
        $tax_display = $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' );

        if ( $this->order_shipping != 0 ) {

            if ( $tax_display == 'excl' ) {

                // Show shipping excluding tax.
                $shipping = wc_price( $this->order_shipping, array('currency' => $this->get_order_currency()) );

                if ( $this->order_shipping_tax != 0 && $this->prices_include_tax ) {
                    $shipping .= apply_filters( 'woocommerce_order_shipping_to_display_tax_label', '&nbsp;<small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>', $this, $tax_display );
                }

            } else {

                // Show shipping including tax.
                $shipping = wc_price( $this->order_shipping + $this->order_shipping_tax, array('currency' => $this->get_order_currency()) );

                if ( $this->order_shipping_tax != 0 && ! $this->prices_include_tax ) {
                    $shipping .= apply_filters( 'woocommerce_order_shipping_to_display_tax_label', '&nbsp;<small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>', $this, $tax_display );
                }

            }

            $shipping .= apply_filters( 'woocommerce_order_shipping_to_display_shipped_via', '&nbsp;<small class="shipped_via">' . sprintf( __( 'via %s', 'woocommerce' ), $this->get_shipping_method() ) . '</small>', $this );

        } elseif ( $this->get_shipping_method() ) {
            $shipping = $this->get_shipping_method();
        } else {
            $shipping = __( 'Free!', 'woocommerce' );
        }

        return apply_filters( 'woocommerce_order_shipping_to_display', $shipping, $this );
    }

    /**
     * Get the discount amount (formatted).
     * @since  2.3.0
     * @return string
     */
    public function get_discount_to_display( $tax_display = '' ) {
        $tax_display = $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' );
        return apply_filters( 'woocommerce_order_discount_to_display', wc_price( $this->get_total_discount( 'excl' === $tax_display && 'excl' === get_option( 'woocommerce_tax_display_cart' ) ), array( 'currency' => $this->get_order_currency() ) ), $this );
    }

    /**
     * Get totals for display on pages and in emails.
     *
     * @param mixed $tax_display
     * @return array
     */
    public function get_order_item_totals( $tax_display = '' ) {
        $tax_display = $tax_display ? $tax_display : get_option( 'woocommerce_tax_display_cart' );
        $total_rows  = array();

        if ( $subtotal = $this->get_subtotal_to_display( false, $tax_display ) ) {
            $total_rows['cart_subtotal'] = array(
                'label' => __( 'Subtotal:', 'woocommerce' ),
                'value'    => $subtotal
            );
        }

        if ( $this->get_total_discount() > 0 ) {
            $total_rows['discount'] = array(
                'label' => __( 'Discount:', 'woocommerce' ),
                'value'    => '-' . $this->get_discount_to_display( $tax_display )
            );
        }

        if ( $this->get_shipping_method() ) {
            $total_rows['shipping'] = array(
                'label' => __( 'Shipping:', 'woocommerce' ),
                'value'    => $this->get_shipping_to_display( $tax_display )
            );
        }

        if ( $fees = $this->get_fees() ) {
            foreach ( $fees as $id => $fee ) {
                if ( apply_filters( 'woocommerce_get_order_item_totals_excl_free_fees', empty( $fee['line_total'] ) && empty( $fee['line_tax'] ), $id ) ) {
                    continue;
                }
                $total_rows[ 'fee_' . $fee->get_order_item_id() ] = array(
                    'label' => $fee->get_name() . ':',
                    'value' => wc_price( 'excl' === $tax_display ? $fee->get_total() : $fee->get_total() + $fee->get_total_tax(), array('currency' => $this->get_order_currency()) )
                );
            }
        }

        // Tax for tax exclusive prices.
        if ( 'excl' === $tax_display ) {

            if ( get_option( 'woocommerce_tax_total_display' ) == 'itemized' ) {

                foreach ( $this->get_tax_totals() as $code => $tax ) {

                    $total_rows[ sanitize_title( $code ) ] = array(
                        'label' => $tax->label . ':',
                        'value'    => $tax->formatted_amount
                    );
                }

            } else {

                $total_rows['tax'] = array(
                    'label' => WC()->countries->tax_or_vat() . ':',
                    'value'    => wc_price( $this->get_total_tax(), array( 'currency' => $this->get_order_currency() ) )
                );
            }
        }

        if ( $this->get_total() > 0 && $this->get_payment_method_title() ) {
            $total_rows['payment_method'] = array(
                'label' => __( 'Payment Method:', 'woocommerce' ),
                'value' => $this->get_payment_method_title()
            );
        }

        if ( $refunds = $this->get_refunds() ) {
            foreach ( $refunds as $id => $refund ) {
                $total_rows[ 'refund_' . $id ] = array(
                    'label' => $refund->get_refund_reason() ? $refund->get_refund_reason() : __( 'Refund', 'woocommerce' ) . ':',
                    'value'    => wc_price( '-' . $refund->get_refund_amount(), array( 'currency' => $this->get_order_currency() ) )
                );
            }
        }

        $total_rows['order_total'] = array(
            'label' => __( 'Total:', 'woocommerce' ),
            'value'    => $this->get_formatted_order_total( $tax_display )
        );

        return apply_filters( 'woocommerce_get_order_item_totals', $total_rows, $this );
    }

    /*
    |--------------------------------------------------------------------------
    | Conditionals
    |--------------------------------------------------------------------------
    |
    | Checks if a condition is true or false.
    |
    */

    /**
     * Checks the order status against a passed in status.
     *
     * @return bool
     */
    public function has_status( $status ) {
        return apply_filters( 'woocommerce_order_has_status', ( is_array( $status ) && in_array( $this->get_status(), $status ) ) || $this->get_status() === $status ? true : false, $this, $status );
    }

    /**
     * has_meta function for order items.
     *
     * @param string $order_item_id
     * @return array of meta data.
     */
    public function has_meta( $order_item_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value, meta_id, order_item_id
            FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d
            ORDER BY meta_id", absint( $order_item_id ) ), ARRAY_A );
    }

    /**
     * Check whether this order has a specific shipping method or not.
     *
     * @param string $method_id
     * @return bool
     */
    public function has_shipping_method( $method_id ) {
        foreach ( $this->get_shipping_methods() as $shipping_method ) {
            if ( $shipping_method->get_method_id() === $method_id ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if an order key is valid.
     *
     * @param mixed $key
     * @return bool
     */
    public function key_is_valid( $key ) {
        return $key === $this->get_order_key();
    }

    /**
     * Returns true if the order contains a free product.
     * @since 2.5.0
     * @return bool
     */
    public function has_free_item() {
        foreach ( $this->get_items() as $item ) {
            if ( ! $item->get_total() ) {
                return true;
            }
        }
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Deprecated methods
    |--------------------------------------------------------------------------
    |
    | Will be removed after 2 major releases, or 1 year.
    |
    */

    /**
     * Magic __isset method for backwards compatibility.
     * @param string $key
     * @return bool
     */
    public function __isset( $key ) {
        // Legacy properties which could be accessed directly in the past.
        $legacy_props = array( 'completed_date', 'id', 'order_type', 'post', 'status', 'post_status', 'customer_note', 'customer_message', 'user_id', 'customer_user', 'prices_include_tax', 'tax_display_cart', 'display_totals_ex_tax', 'display_cart_ex_tax', 'order_date', 'modified_date', 'cart_discount', 'cart_discount_tax', 'order_shipping', 'order_shipping_tax', 'order_total', 'order_tax', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'billing_phone', 'billing_email', 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_country', 'customer_ip_address', 'customer_user_agent', 'payment_method_title', 'payment_method', 'order_currency' );
        return $this->get_id() ? ( in_array( $key, $legacy_props ) || metadata_exists( 'post', $this->get_id(), '_' . $key ) ) : false;
    }

    /**
     * Magic __get method for backwards compatibility.
     * @param string $key
     * @return mixed
     */
    public function __get( $key ) {
        /**
         * Maps legacy vars to new getters.
         */
        if ( 'completed_date' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_date_completed();
		} elseif ( 'paid_date' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_date_paid();
        } elseif ( 'modified_date' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_date_modified();
        } elseif ( 'order_date' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_date_created();
        } elseif ( 'id' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_id();
		} elseif ( 'post' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return get_post( $this->get_id() );
		} elseif ( 'status' === $key || 'post_status' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_status();
		} elseif ( 'customer_message' === $key || 'customer_note' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_customer_note();
		} elseif ( in_array( $key, array( 'user_id', 'customer_user' ) ) ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_customer_id();
		} elseif ( 'tax_display_cart' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
			return get_option( 'woocommerce_tax_display_cart' );
		} elseif ( 'display_totals_ex_tax' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
			return 'excl' === get_option( 'woocommerce_tax_display_cart' );
		} elseif ( 'display_cart_ex_tax' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
			return 'excl' === get_option( 'woocommerce_tax_display_cart' );
        } elseif ( 'cart_discount' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
			return $this->get_discount();
        } elseif ( 'cart_discount_tax' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
			return $this->get_discount_tax();
        } elseif ( 'order_tax' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
			return $this->get_cart_tax();
        } elseif ( 'order_shipping_tax' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_shipping_tax();
        } elseif ( 'order_shipping' === $key ) {
            _doing_it_wrong( $key, 'Order properties should not be accessed directly.', '2.6' );
            return $this->get_shipping();
        /**
         * Map vars to getters with warning.
         */
	 	} elseif ( is_callable( array( $this, "get_{$key}" ) ) ) {
			_doing_it_wrong( $key, 'Order properties should not be accessed directly Use get_' . $key . '().', '2.6' );
            return $this->{"get_{$key}"}();
        /**
         * Handle post meta
         */
        } else {
            _doing_it_wrong( $key, 'Meta should not be accessed directly. Use WC_Order::get_order_meta( $key )', '2.6' );
			$value = get_post_meta( $this->get_id(), '_' . $key, true );
		}

        return $value;
    }

    /**
     * Get order item meta.
     * @deprecated 2.6.0
     * @param mixed $order_item_id
     * @param string $key (default: '')
     * @param bool $single (default: false)
     * @return array|string
     */
    public function get_item_meta( $order_item_id, $key = '', $single = false ) {
        _deprecated_function( 'get_item_meta', '2.6', 'wc_get_order_item_meta' );
        return get_metadata( 'order_item', $order_item_id, $key, $single );
    }

    /**
     * Get all item meta data in array format in the order it was saved. Does not group meta by key like get_item_meta().
     *
     * @param mixed $order_item_id
     * @return array of objects
     */
    public function get_item_meta_array( $order_item_id ) {
        _deprecated_function( 'get_item_meta_array', '2.6', 'WC_Order_Item::get_meta_data()' );
        $item = $this->get_item( $order_item_id );
        return $item->get_meta_data();
    }

    /**
     * Expand item meta into the $item array.
     * @deprecated 2.6.0 Item meta no longer expanded due to new order item
     *        classes. This function now does nothing to avoid data breakage.
     * @since 2.4.0
     * @param array $item before expansion.
     * @return array
     */
    public function expand_item_meta( $item ) {
        _deprecated_function( 'expand_item_meta', '2.6', '' );
        return $item;
    }

	/**
     * Load the order object. Called from the constructor.
     * @deprecated 2.6.0 Logic moved to constructor
     * @param int|object|WC_Order $order Order to init.
     */
    protected function init( $order ) {
		_deprecated_function( 'init', '2.6', 'Logic moved to constructor' );
        if ( is_numeric( $order ) ) {
            $this->read( $order );
        } elseif ( $order instanceof WC_Order ) {
            $this->read( absint( $order->get_id() ) );
        } elseif ( isset( $order->ID ) ) {
            $this->read( absint( $order->ID ) );
        }
    }

    /**
     * Gets an order from the database.
     * @deprecated 2.6
     * @param int $id (default: 0).
     * @return bool
     */
    public function get_order( $id = 0 ) {
        _deprecated_function( 'get_order', '2.6', 'read' );
        if ( ! $id ) {
            return false;
        }
        if ( $result = get_post( $id ) ) {
            $this->populate( $result );
            return true;
        }
        return false;
    }

    /**
     * Populates an order from the loaded post data.
     * @deprecated 2.6
     * @param mixed $result
     */
    public function populate( $result ) {
        _deprecated_function( 'populate', '2.6', 'read' );
        $this->read( $result->ID );
    }

	/**
     * Cancel the order and restore the cart (before payment).
     * @deprecated 2.6.0 Moved to event handler.
     * @param string $note (default: '') Optional note to add.
     */
    public function cancel_order( $note = '' ) {
		_deprecated_function( 'cancel_order', '2.6', 'update_status' );
        WC()->session->set( 'order_awaiting_payment', false );
        $this->update_status( 'cancelled', $note );
    }

	/**
     * Record sales.
     * @deprecated 2.6.0
     */
    public function record_product_sales() {
		_deprecated_function( 'record_product_sales', '2.6', 'wc_update_total_sales_counts' );
		wc_update_total_sales_counts( $this->get_id() );
    }

	/**
     * Increase applied coupon counts.
     * @deprecated 2.6.0
     */
    public function increase_coupon_usage_counts() {
		_deprecated_function( 'increase_coupon_usage_counts', '2.6', 'wc_update_coupon_usage_counts' );
		wc_update_coupon_usage_counts( $this->get_id() );
    }

    /**
     * Decrease applied coupon counts.
     * @deprecated 2.6.0
     */
    public function decrease_coupon_usage_counts() {
		_deprecated_function( 'decrease_coupon_usage_counts', '2.6', 'wc_update_coupon_usage_counts' );
		wc_update_coupon_usage_counts( $this->get_id() );
    }

	/**
     * Reduce stock levels for all line items in the order.
	 * @deprecated 2.6.0
     */
    public function reduce_order_stock() {
        _deprecated_function( 'reduce_order_stock', '2.6', 'wc_reduce_stock_levels' );
		wc_reduce_stock_levels( $this->get_id() );
    }

	/**
     * Send the stock notifications.
	 * @deprecated 2.6.0 No longer needs to be called directly.
     */
    public function send_stock_notifications( $product, $new_stock, $qty_ordered ) {
        _deprecated_function( 'send_stock_notifications', '2.6' );
    }

	/**
	 * Output items for display in html emails.
	 * @deprecated 2.6.0 Moved to template functions.
	 * @param array $args Items args.
	 * @return string
	 */
	public function email_order_items_table( $args = array() ) {
		return wc_get_email_order_items( $this, $args );
	}
}
