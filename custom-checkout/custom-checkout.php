<?php
/**
 * Plugin Name: Custom Checkout Plugin
 * Plugin URI: https://yourwebsite.com
 * Author: Rebecca Nayere
 * Author URI: https://yourwebsite.com
 * Description: A custom plugin that adds a custom payment gateway, connects to an API, and stores data in a database.
 * Version: 1.0.0
 * License: GPL-2.0+
 * Text Domain: custom-checkout
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

// Initialize the custom payment gateway
add_action( 'plugins_loaded', 'custom_payment_gateway_init', 11 );

function custom_payment_gateway_init() {
    if ( class_exists( 'WC_Payment_Gateway' ) ) {
        class WC_Custom_Payment_Gateway extends WC_Payment_Gateway {
            public function __construct() {
                $this->id = 'custom_payment_gateway';
                $this->icon = ''; // URL to an icon image if you have one
                $this->has_fields = false;
                $this->method_title = __( 'Custom Payment Gateway', 'custom-checkout' );
                $this->method_description = __( 'A custom payment gateway with API integration.', 'custom-checkout' );

                // Load the settings
                $this->init_form_fields();
                $this->init_settings();

                // Get settings
                $this->title = $this->get_option( 'title' );
                $this->description = $this->get_option( 'description' );

                // Save settings
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            }

            // Define form fields in the admin settings
            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'       => __( 'Enable/Disable', 'custom-checkout' ),
                        'type'        => 'checkbox',
                        'label'       => __( 'Enable Custom Payment Gateway', 'custom-checkout' ),
                        'default'     => 'yes',
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'custom-checkout' ),
                        'type'        => 'text',
                        'default'     => __( 'Custom Payment', 'custom-checkout' ),
                        'description' => __( 'This controls the title seen during checkout.', 'custom-checkout' ),
                    ),
                    'description' => array(
                        'title'       => __( 'Description', 'custom-checkout' ),
                        'type'        => 'textarea',
                        'default'     => __( 'Pay with the custom payment gateway.', 'custom-checkout' ),
                    ),
                );
            }

            // Process the payment
            public function process_payment( $order_id ) {
                $order = wc_get_order( $order_id );

                // Example API call during payment processing
                $api_data = $this->fetch_external_api_data();
                
                if ( $api_data == 'API request failed' ) {
                    wc_add_notice( __( 'Payment error: Could not retrieve data from API.', 'custom-checkout' ), 'error' );
                    return;
                }
                
                // Custom logic with the API data
                $this->store_api_data_in_db( $order_id, $api_data );

                // Mark as on-hold (awaiting manual confirmation)
                $order->update_status( 'on-hold', __( 'Awaiting custom payment', 'custom-checkout' ) );

                // Reduce stock levels
                $order->reduce_order_stock();

                // Empty the cart
                WC()->cart->empty_cart();

                // Redirect to the thank you page
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order ),
                );
            }

            // Fetch data from an external API
            public function fetch_external_api_data() {
                $response = wp_remote_get('https://amazon-pricing-and-product-info.p.rapidapi.com/?asin=B07GR5MSKD&domain=de', array(
                    'headers' => array(
                        'x-rapidapi-host' => 'amazon-pricing-and-product-info.p.rapidapi.com',
                        'x-rapidapi-key' => '5433b8fcd9msh26070dbaf56f632p1c408cjsn6019a7bf646d',
                    ),
                ));

                if ( is_wp_error( $response ) ) {
                    return 'API request failed';
                }

                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );

                if ( ! empty( $data ) ) {
                    return $data;
                }

                return 'No data found';
            }

            // Store API data in the database
            public function store_api_data_in_db( $order_id, $api_data ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'custom_checkout_data';

                // Ensure the table exists
                $this->create_custom_table();

                $wpdb->insert(
                    $table_name,
                    array(
                        'order_id'    => $order_id,
                        'api_response' => maybe_serialize( $api_data ),
                        'created_at'  => current_time( 'mysql' ),
                    )
                );
            }

            // Create a custom database table if it doesn't exist
            public function create_custom_table() {
                global $wpdb;
                $table_name = $wpdb->prefix . 'custom_checkout_data';

                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    order_id bigint(20) NOT NULL,
                    api_response longtext NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql );
            }
        }
    }
}

// Add the gateway to WooCommerce payment gateways
add_filter( 'woocommerce_payment_gateways', 'add_custom_payment_gateway' );

function add_custom_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Custom_Payment_Gateway';
    return $gateways;
}

// Display API data on the WooCommerce checkout page
function display_api_data_on_checkout() {
    $api_data = fetch_amazon_product_info();

    if ( is_array( $api_data ) ) {
        echo '<div class="woocommerce-info">';
        echo '<h3>Amazon Product Information</h3>';
        echo '<p><strong>Title:</strong> ' . esc_html( $api_data['title'] ) . '</p>';
        echo '<p><strong>Review:</strong> ' . esc_html( $api_data['review'] ) . '</p>';
        echo '<p><strong>Rating:</strong> ' . esc_html( $api_data['rating'] ) . '/5</p>';
        echo '</div>';
    } else {
        echo '<p>' . esc_html( $api_data ) . '</p>'; // Display error or no data message
    }
}

// Hook to add the custom section to the WooCommerce checkout page
add_action( 'woocommerce_before_checkout_form', 'display_api_data_on_checkout' );

// Fetch product information from the API
function fetch_amazon_product_info() {
    $response = wp_remote_get('https://amazon-pricing-and-product-info.p.rapidapi.com/?asin=B07GR5MSKD&domain=de', array(
        'headers' => array(
            'x-rapidapi-host' => 'amazon-pricing-and-product-info.p.rapidapi.com',
            'x-rapidapi-key' => '5433b8fcd9msh26070dbaf56f632p1c408cjsn6019a7bf646d',
        ),
    ));

    if ( is_wp_error( $response ) ) {
        return 'API request failed';
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! empty( $data ) ) {
        return array(
            'title' => $data['title'] ?? 'N/A',
            'review' => $data['review'] ?? 'No review available',
            'rating' => $data['rating'] ?? 'N/A',
        );
    }

    return 'No data found';
}
