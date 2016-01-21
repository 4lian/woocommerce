<?php
/**
 * Order Line Item (fee).
 *
 * @class 		WC_Order_Item_Fee
 * @version		2.6.0
 * @since       2.6.0
 * @package		WooCommerce/Classes
 * @author 		WooThemes
 */
class WC_Order_Item_Fee extends WC_Order_Item {

    /**
	 * Data properties of this order item object.
	 * @since 2.6.0
	 * @var array
	 */
    protected $data = array(
        'order_id'        => 0,
		'order_item_id'   => 0,
        'name'            => '',
        'tax_class'       => '',
        'total'           => '',
        'total_tax'       => '',
        'taxes'           => array(
            'total' => array()
        )
    );

    /**
     * Read/populate data properties specific to this order item.
     */
    protected function read() {
        parent::read();
        if ( $this->get_order_item_id() ) {
            $this->set_tax_class( get_metadata( 'order_item', $this->get_order_item_id(), '_tax_class', true ) );
            $this->set_total( get_metadata( 'order_item', $this->get_order_item_id(), '_line_total', true ) );
            $this->set_total_tax( get_metadata( 'order_item', $this->get_order_item_id(), '_line_tax', true ) );
            $this->set_taxes( get_metadata( 'order_item', $this->get_order_item_id(), '_line_tax_data', true ) );
        }
    }

    /**
     * Save properties specific to this order item.
     */
    protected function save() {
        parent::save();
        if ( $this->get_order_item_id() ) {
            wc_update_order_item_meta( $this->get_order_item_id(), '_tax_class', $this->get_tax_class() );
            wc_update_order_item_meta( $this->get_order_item_id(), '_line_total', $this->get_total() );
            wc_update_order_item_meta( $this->get_order_item_id(), '_line_tax', $this->get_total_tax() );
            wc_update_order_item_meta( $this->get_order_item_id(), '_line_tax_data', $this->get_taxes() );
        }
    }

    /*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

    /**
     * Set order item name.
     * @param string $value
     */
    public function set_order_item_name( $value ) {
        $this->data['name'] = wc_clean( $value );
    }

    /**
     * Set item name.
     * @param string $value
     */
    public function set_name( $value ) {
        $this->set_order_item_name( $value );
    }

    /**
     * Set tax class.
     * @param string $value
     */
    public function set_tax_class( $value ) {
        $this->data['tax_class'] = $value;
    }

    /**
     * Set total.
     * @param string $value
     */
    public function set_total( $value ) {
        $this->data['total'] = wc_format_decimal( $value );
    }

    /**
     * Set total tax.
     * @param string $value
     */
    public function set_total_tax( $value ) {
        $this->data['total_tax'] = wc_format_decimal( $value );
    }

    /**
     * Set taxes.
     *
     * This is an array of tax ID keys with total amount values.
     * @param array $raw_tax_data
     */
    public function set_taxes( $raw_tax_data ) {
        $tax_data = array(
            'total'    => array()
        );
        if ( ! empty( $raw_tax_data['total'] ) ) {
            $tax_data['total']    = array_map( 'wc_format_decimal', $raw_tax_data['total'] );
        }
        $this->data['taxes'] = $tax_data;
    }

    /*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

    /**
     * Get order item type.
     * @return string
     */
    public function get_order_item_type() {
        return 'fee';
    }

    /**
     * Get order item name.
     * @return string
     */
    public function get_order_item_name() {
        return $this->data['name'];
    }

    /**
     * Get fee name.
     * @return string
     */
    public function get_name() {
        return $this->get_order_item_name();
    }

    /**
     * Get tax class.
     * @return string
     */
    public function get_tax_class() {
        return $this->data['tax_class'];
    }

    /**
     * Get total fee.
     * @return string
     */
    public function get_total() {
        return wc_format_decimal( $this->data['total'] );
    }

    /**
     * Get total tax.
     * @return string
     */
    public function get_total_tax() {
        return wc_format_decimal( $this->data['total_tax'] );
    }

    /**
     * Get fee taxes.
     * @return array
     */
    public function get_taxes() {
        return $this->data['taxes'];
    }
}
