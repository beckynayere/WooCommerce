<?php
/**
 * Plugin Name: Custom Checkout Plugin
 * Description: A plugin that customizes the WooCommerce checkout page and integrates with an external API.
 * Version: 1.0.0
 * Author: Rebecca Nayere
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
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
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

        if (!empty($data)) {
            // Example: Display the first review text from the API response
            return $data['reviews'][0]['text']; // Adjust based on the actual API response structure
        }

        return 'No reviews found';
    }
}

// Function to customize the WooCommerce checkout page
function custom_checkout_customization() {
    $api_data = fetch_amazon_reviews();
    echo '<h3>Amazon Product Reviews</h3>';
    echo '<p>' . esc_html($api_data) . '</p>';
}

// Hook to add the custom section to the WooCommerce checkout page
add_action('woocommerce_before_checkout_form', 'custom_checkout_customization');
