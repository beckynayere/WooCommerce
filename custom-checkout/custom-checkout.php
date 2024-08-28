<?php
/**
 * Plugin Name: Custom Checkout Plugin
 * Plugin URI: https://customcheckout.com
 * Author: Rebecca Nayere
 * Author URI: https://customcheckout.com
 * Description: A custom WooCommerce plugin that customizes the checkout page, fetches data from an external API, and displays it during checkout.
 * Version: 1.0.0
 * License: GPL-2.0+
 * Text Domain: custom-checkout
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Function to fetch API data using cURL
function fetch_amazon_reviews() {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://amazon-product-scrapper-pro.p.rapidapi.com/products/B09JQL8KP9/reviews?api_key=daa2d495ac6f0dd3f4ac6b2be2831ad3",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: amazon-product-scrapper-pro.p.rapidapi.com",
            "x-rapidapi-key: 5433b8fcd9msh26070dbaf56f632p1c408cjsn6019a7bf646d"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return "cURL Error #:" . $err;
    } else {
        $data = json_decode($response, true);

        if (!empty($data) && isset($data['reviews'][0]['text'])) {
            return $data['reviews'][0]['text']; // Display the first review text
        }

        return 'No reviews found.';
    }
}

// Function to customize the WooCommerce checkout page
function custom_checkout_customization() {
    $api_data = fetch_amazon_reviews();
    echo '<div class="custom-checkout-section">';
    echo '<h3>Amazon Product Reviews</h3>';
    echo '<p>' . esc_html($api_data) . '</p>';
    echo '</div>';
}

// Hook to add the custom section to the WooCommerce checkout page
add_action('woocommerce_before_checkout_form', 'custom_checkout_customization');

// Enqueue custom styles for the checkout page
function custom_checkout_enqueue_styles() {
    wp_enqueue_style('custom-checkout-styles', plugin_dir_url(__FILE__) . 'assets/css/custom-checkout.css');
}
add_action('wp_enqueue_scripts', 'custom_checkout_enqueue_styles');

// Add a Function to Send Data to Laravel API
function send_order_data_to_api($order_id) {
    $order = wc_get_order($order_id);
    $custom_field = get_post_meta($order_id, 'Custom Field', true);

    // Prepare the data to send
    $body = array(
        'custom_field' => $custom_field,
        'order_id' => $order_id,
    );

    // Set the API endpoint
    $api_url = 'https://your-laravel-api.com/api/order-data';

    // Send the data to the Laravel API
    $response = wp_remote_post($api_url, array(
        'method'    => 'POST',
        'body'      => json_encode($body),
        'headers'   => array(
            'Authorization' => 'Bearer ' . 'your_api_token', // if your API requires authentication
            'Content-Type'  => 'application/json',
        ),
    ));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('Error sending data to API: ' . $error_message);
    } else {
        $response_body = wp_remote_retrieve_body($response);
        error_log('API Response: ' . $response_body);
    }
}

add_action('woocommerce_thankyou', 'send_order_data_to_api');
