<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_WumDrop  {

	/**
	 * Contructor
	 **/
	public function __construct( $file = '' ) {
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );
		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$this->method_id = 'wd';

		$this->api_url = 'https://api.wumdrop.com/v1/';

		// Load the settings.
		$this->init_settings();

		// Load JS
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Handle delivery placement
		add_action( 'woocommerce_order_status_processing', array( $this, 'order_delivery' ), 10, 1 );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel_order_delivery' ), 10, 1 );
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order_delivery' ), 10, 1 );

        // Display delivery status in the admin
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_delivery_details' ), 10, 1 );

        // Process order actions in the admin and display notices
        add_action( 'admin_notices', array( $this, 'order_delivery_process' ) );

        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'customer_delivery_details' ), 10, 1 );

        // Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ) );
	}

	public function init_settings() {

		$this->settings = get_option( 'woocommerce_' . $this->method_id . '_settings', null );

		if ( $this->settings && is_array( $this->settings ) ) {
			$this->enabled  = isset( $this->settings['enabled'] ) && $this->settings['enabled'] == 'yes' ? 'yes' : 'no';
		}

	}

	public function api( $endpoint = '', $params = array(), $method = 'post' ) {

		// No endpoint = no query
		if( ! $endpoint ) {
			return;
		}

		// Parameters must be an array
		if( ! is_array( $params ) ) {
			return;
		}

		// Only valid methods allowed
		if( ! in_array( $method, array( 'post', 'get', 'delete' ) ) ) {
			return;
		}

		// Set up query URL
		$url = $this->api_url . $endpoint;

		// Set up request arguments
		$args['headers'] = array(
			'Authorization' => 'Basic ' . base64_encode( $this->settings['api_key'] . ':' ),
		);
		$args['sslverify'] = true;
		$args['timeout'] = 60;
		$args['user-agent'] = 'WooCommerce/' . WC()->version;

		// Process request based on method in use
		switch( $method ) {

			case 'post':

				if( ! empty( $params ) ) {
					$params = json_encode( $params );
					$args['body'] = $params;
					$args['headers']['Content-Length'] = strlen( $args['body'] );
				}

				$args['headers']['Content-Type'] = 'application/json';
				$args['headers']['Content-Length'] = strlen( $args['body'] );

				$response = wp_remote_post( $url, $args );

			break;

			case 'get':

				$param_string = '';
				if( ! empty( $params ) ) {
					$params = array_map( 'urlencode', $params );
					$param_string = build_query( $params );
				}

				if( $param_string ) {
					$url = $url . '?' . $param_string;
				}

				$response = wp_remote_get( $url, $args );

			break;

			case 'delete':
				$args['method'] = "DELETE";
				$response = wp_remote_request( $url, $args );
			break;

		}

		// Return null if WP error is generated
		if( is_wp_error( $response ) ) {
			return;
		}

		// Return null if query is not successful
		if( '200' != $response['response']['code'] || ! isset( $response['body'] ) || ! $response['body'] ) {
			return;
		}

		// Return response object
		return json_decode( $response['body'] );
	}

	public function get_address_data ( $address = '' ) {

		if( ! $address ) {
			return;
		}

		$url = 'http://maps.google.com/maps/api/geocode/json?address=' . urlencode( stripslashes( $address ) );

		$response = wp_remote_get( $url, array( 'timeout' => 60, 'user-agent' => 'WooCommerce/' . WC()->version ) );

		if( is_wp_error( $response ) ) {
			return;
		}

		if( '200' != $response['response']['code'] || ! isset( $response['body'] ) || ! $response['body'] ) {
			return;
		}

		$data = json_decode( $response['body'], true );

		if( ! isset( $data['results'][0]['formatted_address'] ) || ! isset( $data['results'][0]['geometry']['location']['lat'] ) || ! isset( $data['results'][0]['geometry']['location']['lng'] ) ) {
			return;
		}

		$address = $data['results'][0]['formatted_address'];

		$coords = array(
			$data['results'][0]['geometry']['location']['lat'],
			$data['results'][0]['geometry']['location']['lng'],
		);

		$location = implode(', ', $coords );

		if( ! $address || ! $location ) {
			return;
		}

		$address_data = array(
			'address' => $address,
			'location' => $location,
		);

		return $address_data;

	}

	public function order_delivery ( $order_id = 0 ) {


		if( ! $order_id || ! $this->settings['pickup_location'] || ! $this->settings['pickup_name'] || ! $this->settings['pickup_phone'] ) {
			return false;
		}

		// Get order object
		$order = new WC_Order( $order_id );

		if( ! $order ) {
			return false;
		}

		// Get customer user ID
		$user = $order->get_user();

		if( ! $user ) {
			return false;
		}

		$delivery_address = str_replace( '<br/>', ', ', $order->get_formatted_shipping_address() );

		if( ! $delivery_address ) {
			return false;
		}

		if( ! $order->billing_phone ) {
			return false;
		}

		$delivery_address_data = $this->get_address_data( $delivery_address );

		$args = array(
			'pickup_address' => (string) $this->settings['pickup_location'],
			'pickup_contact_name' => (string) $this->settings['pickup_name'],
			'pickup_contact_phone' => (string) $this->settings['pickup_phone'],
			'customer_identifier' => (string) $user->user_login,
			'dropoff_contact_name' => (string) $user->display_name,
			'dropoff_contact_phone' => (string) $order->billing_phone,
			'dropoff_address' => (string) $delivery_address_data['address'],
			'dropoff_coordinates' => (string) $delivery_address_data['location'],
		);

		if( $this->settings['pickup_coords'] ) {
			$args['pickup_coordinates'] = (string) $this->settings['pickup_coords'];
		}

		if( $this->settings['pickup_remarks'] ) {
			$args['pickup_remarks'] = (string) $this->settings['pickup_remarks'];
		}

		if( $this->settings['city'] ) {
			$args['city'] = (string) $this->settings['city'];
		}

		$delivery_notes = $order->customer_note;

		if( $delivery_notes ) {
			$args['dropoff_remarks'] = (string) $this->settings['delivery_notes'];
		}

		$delivery = $this->api( 'deliveries', $args, 'post' );

		if( $delivery && isset( $delivery->id ) ) {

			update_post_meta( $order_id, '_wumdrop_delivery_id', $delivery->id );

			if( isset( $delivery->distance_estimate ) ) {
				update_post_meta( $order_id, '_wumdrop_distance_estimate', $delivery->distance_estimate );
			}

			if( isset( $delivery->time_estimate ) ) {
				update_post_meta( $order_id, '_wumdrop_time_estimate', $delivery->time_estimate );
			}

			if( isset( $delivery->message ) ) {
				update_post_meta( $order_id, '_wumdrop_delivery_message', $delivery->message );
			}

			if( isset( $delivery->time_estimate ) ) {
				update_post_meta( $order_id, '_wumdrop_delivery_price', $delivery->price );
			}

			$order_note = __( 'WumDrop delivery order placed.', 'woocommerce-wumdrop' );

			if( 'completed' != $order->get_status() ) {
				$order->update_status( 'completed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}

			return true;

		}

		return false;
	}

	public function cancel_order_delivery ( $order_id = 0 ) {

		if( ! $order_id ) {
			return;
		}

		$delivery_id = get_post_meta( $order_id, '_wumdrop_delivery_id', true );

		if( ! $delivery_id ) {
			return;
		}

		$delivery = $this->api( 'deliveries/' . $delivery_id, array(), 'delete' );

		if( $delivery && isset( $delivery->message ) ) {

			// Add note to order
			$order = new WC_Order( $order_id );
			if( $order ) {
				$order->add_order_note( __( 'WumDrop delivery order cancelled.', 'woocommerce-wumdrop' ) );
			}

			return true;
		}

		return false;
	}

	public function order_delivery_process () {

		if( isset( $_GET['wumdrop_delivery'] ) ) {
			global $post;

			if( ! isset( $post->ID ) ) {
				return;
			}

			// Cancel delivery order
			$cancelled = false;
			if( 'cancel' == $_GET['wumdrop_delivery'] ) {
				$cancelled = $this->cancel_order_delivery( $post->ID );
			}

			// Place delivery order
			$ordered = false;
			if( 'order' == $_GET['wumdrop_delivery'] ) {
				$ordered = $this->order_delivery( $post->ID );
			}

			// Display cancelled admin notice
			if( $cancelled ) {
				?>
				<div class="error">
			        <p><?php _e( 'WumDrop delivery cancelled.', 'woocommerce-wumdrop' ); ?></p>
			    </div>
			    <?php
			}

			// Display ordered admin notice
			if( $ordered ) {
				?>
				<div class="updated">
			        <p><?php _e( 'WumDrop delivery ordered.', 'woocommerce-wumdrop' ); ?></p>
			    </div>
			    <?php
			}
		}

	}

	public function get_order_delivery ( $order_id = 0 ) {

		if( ! $order_id ) {
			return;
		}

		$delivery_id = get_post_meta( $order_id, '_wumdrop_delivery_id', true );

		if( ! $delivery_id ) {
			return;
		}

		$delivery = $this->api( 'deliveries/' . $delivery_id, array(), 'get' );

		return $delivery;
	}

	public function admin_delivery_details ( $order ) {

		if( ! $order ) {
			return;
		}

		if( ! $order->has_shipping_method( 'wd_delivery' ) ) {
			return;
		}

		$html = '<p>';
			$html .= '<strong>' . __( 'WumDrop Delivery Status:', 'woocommerce-wumdrop' ) . '</strong>';

		// Get WumDrop delivery object
		$delivery = $this->get_order_delivery( $order->id );

		// If no object then allow for delivery order to be place otherwise show status and appropriate action
		if( ! $delivery ) {

			$html .= '<br/>' . __( 'No delivery ordered', 'woocommerce-wumdrop' );
			$html .= '<br/><a href="' . add_query_arg( array( 'wumdrop_delivery' => 'order', 'message' => false ) ) . '">' . __( 'Place order', 'woocommerce-wumdrop' ) . '</a>';

		} else {

			if( $delivery->status ) {
				$status = ucfirst( strtolower( $delivery->status ) );
				$html .= '<br/>' . $status;

				switch( $status ) {

					case 'Pending pickup':
					case 'Pending dropoff':
						$html .= '<br/><a class="delete" href="' . add_query_arg( array( 'wumdrop_delivery' => 'cancel', 'message' => false ) ) . '">' . __( 'Cancel', 'woocommerce-wumdrop' ) . '</a>';
					break;

					case 'Delivered':

						if( $delivery->dropoff_timestamp ) {
							$timestamp = strtotime( $delivery->dropoff_timestamp );
							$html .= sprintf( __( ' on %1$s @ %2$s', 'woocommerce-wumdrop' ), '<b>' . date( 'Y-m-d', $timestamp ) . '</b>', '<b>' . date( 'H:i', $timestamp ) . '</b>' );
						}

						if( $delivery->confirmation_photo_url ) {
							$html .= '<br/><a href="' . esc_url( $delivery->confirmation_photo_url ) . '" target="_blank">' . __( 'Delivery confirmation', 'woocommerce-wumdrop' ) . '</a>';
						}

					break;

					case 'Cancelled':
						$html .= '<br/><a href="' . add_query_arg( array( 'wumdrop_delivery' => 'order', 'message' => false ) ) . '">' . __( 'Re-order', 'woocommerce-wumdrop' ) . '</a>';
					break;

				}

			}
		}

		$html .= '</p>';

		echo $html;
	}

	public function customer_delivery_details ( $order ) {

		if( ! $order ) {
			return;
		}

		if( ! $order->has_shipping_method( 'wd_delivery' ) ) {
			return;
		}

		if( $order->has_status( array( 'pending', 'cancelled', 'refunded', 'failed' ) ) ) {
			return;
		}

		// Get WumDrop delivery object
		$delivery = $this->get_order_delivery( $order->id );

		if( ! $delivery ) {
			return;
		}

		$html = '<header>';
			$html .= '<h2>' . __( 'WumDrop delivery details', 'woocommerce-wumdrop' ) . '</h2>';
		$html .= '</header>';

		if( $delivery->status ) {

			$status = ucfirst( strtolower( $delivery->status ) );

			switch( $status ) {

				case 'Pending pickup':
					$status_message = __( 'WumDrop is en route to pick up your package.', 'woocommerce-wumdrop' );
				break;
				case 'Pending dropoff':
					$status_message = __( 'WumDrop is on the way to your door with your package.', 'woocommerce-wumdrop' );
				break;

				case 'Delivered':

					$status_message = __( 'WumDrop successfully delivered your package', 'woocommerce-wumdrop' );

					if( $delivery->dropoff_timestamp ) {
						$timestamp = strtotime( $delivery->dropoff_timestamp );
						$status_message .= sprintf( __( ' on %1$s @ %2$s', 'woocommerce-wumdrop' ), '<b>' . date( 'Y-m-d', $timestamp ) . '</b>', '<b>' . date( 'H:i', $timestamp ) . '</b>' );
					}

					$status_message .= '.';

					if( $delivery->confirmation_photo_url ) {
						$html .= ' <a href="' . esc_url( $delivery->confirmation_photo_url ) . '" target="_blank">' . __( 'Here\'s proof.', 'woocommerce-wumdrop' ) . '</a>';
					}

				break;

				case 'Cancelled':
					$status_message = __( 'Your WumDrop delivery has been cancelled.', 'woocommerce-wumdrop' );
				break;

				default:
					$status_message = '';
				break;

			}

			if( $status_message ) {
				$html .= '<p>' . $status_message . '</p>';
				echo $html;
			}

		}
	}

	public function enqueue_scripts () {

	    if( is_admin() && ( isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] ) && ( isset( $_GET['tab'] ) && 'shipping' == $_GET['tab'] ) && ( isset( $_GET['section'] ) && 'wc_wumdrop_method' == $_GET['section'] ) ) {

	        // Load Google Maps API
	        wp_enqueue_script( 'google-maps-api-places', 'http://maps.googleapis.com/maps/api/js?sensor=false&amp;libraries=places', array( 'jquery' ) );

	        // Load geocomplete library
	        wp_register_script( 'jquery-geocomplete', $this->assets_url . 'js/jquery.geocomplete' . $this->script_suffix . '.js', array( 'jquery', 'google-maps-api-places' ), '1.6.4' );
	        wp_enqueue_script( 'jquery-geocomplete' );

	        // Load custom scripts
	        wp_register_script( 'wc_wumdrop', $this->assets_url . 'js/scripts' . $this->script_suffix . '.js', array( 'jquery', 'google-maps-api-places', 'jquery-geocomplete' ), '1.0.0' );
	        wp_enqueue_script( 'wc_wumdrop' );

	        // Localise custom scripts
	        wp_localize_script( 'wc_wumdrop', 'wc_wumdrop', array( 'no_coords' => __( 'Unable to work out coordinates automatically - please add them manually.', 'woocommerce-wumdrop' ) ) );

	    }
	}

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
	    load_plugin_textdomain( 'woocommerce-wumdrop', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
	    $domain = 'woocommerce-wumdrop';

	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

}
?>