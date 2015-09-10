<?php
/**
 * WumDrop
 *
 * Provides WumDrop shipping to WooCommerce.
 *
 * @class 		WC_WumDrop
 * @package		WooCommerce
 * @category	Shipping Module
 * @author		Hugh Lashbrooke
 *
 **/

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_WumDrop_Method extends WC_Shipping_Method  {

	/**
	 * Contructor
	 **/
	public function __construct() {

		$this->id           = 'wd';
		$this->method_title = __( 'WumDrop', 'woocommerce-wumdrop' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Only shipping from ZA and ZAR currency supported
		add_action( 'admin_notices', array( $this, 'check_currency' ) );

		$this->enabled        	= $this->settings['enabled'];
		$this->title          	= $this->settings['title'];
		$this->api_key          = $this->settings['api_key'];
		$this->city          	= $this->settings['city'];
		$this->pickup_location  = $this->settings['pickup_location'];
		$this->pickup_coords    = $this->settings['pickup_coords'];
		$this->pickup_name      = $this->settings['pickup_name'];
		$this->pickup_phone     = $this->settings['pickup_phone'];
		$this->pickup_remarks   = $this->settings['pickup_remarks'];

		$this->origin_country 	= WC()->countries->get_base_country();
		$this->origin_province 	= WC()->countries->get_base_state();

		// Save settings
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_shipping_methods', array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialise Form Fields
	 **/
	function init_form_fields() {

		$fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-wumdrop' ),
				'type' => 'checkbox',
				'label' => __( 'Enable WumDrop delivery method.', 'woocommerce-wumdrop' ),
				'default' => 'yes',
			),
			'title' => array(
				'title' => __( 'Method Title', 'woocommerce-wumdrop' ),
				'type' => 'text',
				'description' => __( 'This controls the title that the user sees during checkout.', 'woocommerce-wumdrop' ),
				'default' => __( 'WumDrop', 'woocommerce-wumdrop' ),
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'api_key' => array(
				'title' => __( 'API key', 'woocommerce-wumdrop' ),
				'type' => 'text',
				'description' => __( 'Your WumDrop API key - obtain one for free here: http://nerds.wumdrop.com/ (required).', 'woocommerce-wumdrop' ),
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'city' => array(
				'title' => __( 'City', 'woocommerce-wumdrop' ),
				'type' => 'select',
				'description' => __( 'The city in which you operate - WumDrop currently serves Cape Town and Johannesburg only.', 'woocommerce-wumdrop' ),
				'default' => 'CPT',
				'options' => array(
					'CPT' => __('Cape Town', 'woocommerce-wumdrop'),
					'JHB'  => __('Johannesburg', 'woocommerce-wumdrop')
				)
			),
			'pickup_location' => array(
				'title' => __( 'Pick up location', 'woocommerce-wumdrop' ),
				'type' => 'text',
				'description' => __( 'The full street address where your WumDrop deliveries will be collected (required).', 'woocommerce-wumdrop' ),
				'default' => '',
				'custom_attributes' => array(
					'autocomplete' => 'off',
					'required' => 'required',
				),
			),
			'pickup_coords' => array(
				'title' => __( 'Pick up coordinates', 'woocommerce-wumdrop' ),
				'type' => 'text',
				'description' => __( 'The Google Maps coordinates (longitude & latitude) of your pick up location (required).', 'woocommerce-wumdrop' ),
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'pickup_name' => array(
				'title' => __( 'Pick up contact name', 'woocommerce-wumdrop' ),
				'type' => 'text',
				'description' => __( 'The name of the primary contact at the pick up location (required).', 'woocommerce-wumdrop' ),
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'pickup_phone' => array(
				'title' => __( 'Pick up contact number', 'woocommerce-wumdrop' ),
				'type' => 'number',
				'description' => __( 'The 10-digit SA phone number of the contact at the pick-up location (required).', 'woocommerce-wumdrop' ),
				'default' => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'pickup_remarks' => array(
				'title' => __( 'Pick up remarks', 'woocommerce-wumdrop' ),
				'type' => 'textarea',
				'description' => __( 'Any additional remarks or instructions for the WumDrop driver when arriving at the pick up location (optional).', 'woocommerce-wumdrop' ),
				'default' => '',
			),
		);

		$this->form_fields = apply_filters( 'woocommerce_wumdrop_fields', $fields );

	}

	/**
	 * Check if ZAR is shop currency and base country is ZA as only ZAR and shipping from ZA is supported
	 **/
	function check_currency() {

		if( apply_filters( 'woocommerce_wumdrop_hide_notices', false ) ) {
			return;
		}

		if ( 'ZAR' != get_option( 'woocommerce_currency' ) && 'yes' == $this->enabled ) {
			echo '<div class="error"><p>' . sprintf(__('WumDrop is enabled, but the <a href="%s">currency</a> is not South African Rand (ZAR) - WumDrop only supports ZAR.', 'woocommerce-wumdrop'), admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '</p></div>';
		}

		if ( ( 'ZA' != $this->origin_country || ( 'ZA' == $this->origin_country && ! in_array( $this->origin_province, array( 'WC', 'GP' ) ) ) ) && 'yes' == $this->enabled ) {
			echo '<div class="error"><p>' . sprintf(__('WumDrop is enabled, but your <a href="%s">base location</a> is not Gauteng or the Western Cape.', 'woocommerce-wumdrop'), admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '</p></div>';
		}

	}

	function process_admin_options() {
		parent::process_admin_options();
	}

	/**
	 * Do some checks to see if shipping method is available to customer
	 **/
	function is_available( $package ) {

		// Obviously you cant use this if its not enabled
		if ( $this->enabled == "no" ) {
			return false;
		}

		// Can only ship from South Africa
		if ( $this->origin_country != 'ZA' ) {
			return false;
		}

		// Can only ship to South African customers
		if ( WC()->customer->get_shipping_country() != 'ZA' ) {
			return false;
		}

		return true;
	}

	/**
	 * Calculate the shipping costs
	 **/
	function calculate_shipping( $package = array() ) {

		if( ! $this->pickup_coords ) {
			return;
		}

		$country = $package['destination']['country'];
		$city = trim( $package['destination']['city'] );

		if( 'cape town' == strtolower( $city ) ) {
			$customer_city = 'CPT';
		} elseif( 'johannesburg' == strtolower( $city ) ) {
			$customer_city = 'JHB';
		} else {
			$customer_city = '';
		}

		if( 'ZA' == $country && $customer_city == $this->city ) {

			$delivery_location = $this->get_customer_location( $package );

			$no_address_message = sprintf( __( '%1$sWe could not locate your address - please make sure it is correct to enable WumDrop courier delivery.%2$s', 'woocommerce-wumdrop' ), '<b>', '</b>' );

			if( ! $delivery_location ) {
				// wc_add_notice( $no_address_message, 'success' );
				return;
			}

			$street_address = $package['destination']['address'];

			if( ! isset( $street_address ) || empty( $street_address ) || stripos( $delivery_location['address'], $street_address ) === false ) {
				wc_add_notice( $no_address_message, 'success' );
				return;
			}

			$delivery_cost = $this->get_delivery_cost( $this->pickup_coords, $delivery_location['location'], $customer_city, $package );

			if( $delivery_cost === false ) {
				return;
			}

			// Display formatted delivery address for confirmation
			$notice = sprintf( __( 'Your address for WumDrop courier delivery is:%1$sIf this is not correct, please edit your address before placing your order.%2$s', 'woocommerce-wumdrop' ), '<br/><b>' . $delivery_location['address'] . '</b><br/><small><em>', '</em></small>' );
			wc_add_notice( $notice, 'success' );

			$rate = array(
				'id' => $this->id . '_delivery',
				'label' => $this->title,
				'cost' => $delivery_cost,
				'taxes' => false
			);

			// Register the rate
			$this->add_rate( $rate );

		}

	}

	/**
	 * Build customer location from package
	 * @param  array $package
	 * @return string
	 */
	public function get_customer_location( $package = array() ) {
		global $wc_wumdrop;

		$address = array();
		$address_data = '';

		if ( isset( $package['destination']['address'] ) && ! empty( $package['destination']['address'] ) ) {
			$address[] = $package['destination']['address'];
		} else {
			return $address_data;
		}

		if ( isset( $package['destination']['address_2'] ) && ! empty( $package['destination']['address_2'] ) ) {
			$address[] = $package['destination']['address_2'];
		}

		if ( isset( $package['destination']['city'] ) && ! empty( $package['destination']['city'] ) ) {
			$address[] = $package['destination']['city'];
		} else {
			return $address_data;
		}

		if ( isset( $package['destination']['postcode'] ) && ! empty( $package['destination']['postcode'] ) ) {
			$address[] = $package['destination']['postcode'];
		} else {
			return $address_data;
		}

		if ( isset( $package['destination']['state'] ) && ! empty( $package['destination']['state'] ) ) {
			$address[] = $package['destination']['state'];
		} else {
			return $address_data;
		}

		if ( isset( $package['destination']['country'] ) && ! empty( $package['destination']['country'] ) ) {
			$address[] = $package['destination']['country'];
		} else {
			return $address_data;
		}

		if( ! empty( $address ) ) {
			$address_string = implode( ', ', $address );

			if( $address_string ) {
				$address_data = $wc_wumdrop->get_address_data( $address_string );
			}
		}

		return $address_data;

	} // End get_customer_location()

	public function get_delivery_cost( $pickup = '', $destination = '', $city = '', $package = array() ) {
		global $wc_wumdrop;

		if( ! $pickup || ! $destination ) {
			return false;
		}

		$pickup_location = explode( ',', $pickup );
		$destination_location = explode( ',', $destination );

		$args = array(
			'lat1' => $pickup_location[0],
			'lon1' => $pickup_location[1],
			'lat2' => $destination_location[0],
			'lon2' => $destination_location[1],
		);

		if( $city ) {
			$args['city'] = $city;
		}

		$estimate = $wc_wumdrop->api( 'estimates/quote', $args, 'get' );

		if( $estimate && isset( $estimate->price ) ) {

			// Allow third-party filtering of delivery cost
			$delivery_cost = apply_filters( 'woocommerce_wumdrop_delivery_cost', $estimate->price, $pickup, $destination, $city, $package );

			return $delivery_cost;
		}

		return false;
	}

}
?>