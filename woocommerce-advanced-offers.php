<?php
/*
Plugin Name: Woocommerce Advanced Offers
Description: A powerful WooCommerce plugin to create **Flash Offers, Upcoming Offers, Special Offers, and BOGO Offers** with full control over products, categories, discounts, and display logic.
Version: 1.1
Author: Hiren Gediya
 * Version: 7.97
 * Text Domain: woocommerce_advanced_offers
 * Domain Path: /languages
 * Network: True
 * License: GPLv3
*/

if (!defined('ABSPATH'))
    exit;
define('FLASHOFFERS_PATH', plugin_dir_path(__FILE__));

require_once FLASHOFFERS_PATH . 'includes/wao-functions.php';
require_once FLASHOFFERS_PATH . 'includes/wao-settings.php';
require_once FLASHOFFERS_PATH . 'includes/wao-admin.php';
require_once FLASHOFFERS_PATH . 'includes/wao-cart.php';
require_once FLASHOFFERS_PATH . 'includes/wao-thank-you.php';
require_once FLASHOFFERS_PATH . 'includes/wao-checkout.php';
require_once FLASHOFFERS_PATH . 'includes/wao-shortcodes.php';
require_once FLASHOFFERS_PATH . 'includes/wao-special-cart.php';
require_once FLASHOFFERS_PATH . 'includes/bogo-admin.php';
require_once FLASHOFFERS_PATH . 'includes/bogo-frontend.php';
// Run migration on plugin activation or when admin loads
add_action('admin_init', function () {
    if (current_user_can('activate_plugins')) {
        flashoffers_migrate_categories_table();
    }
});

// Enqueue countdown script on single product pages
add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('flashoffers-timer', plugin_dir_url(__FILE__) . 'assets/js/countdown.js', [], null, true);
    }
    // Get countdown format from settings
    $options = get_option('flash_offers_options');
    $countdown_format = $options['countdown_format'] ?? 'format1';

    wp_localize_script('flashoffers-timer', 'flashoffers_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('flashoffers_nonce'),
        'countdown_format' => $countdown_format
    ]);
    wp_enqueue_style('flashoffers-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], null);
    wp_enqueue_style('slick-css', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
    wp_enqueue_script('slick-js', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', ['jquery'], null, true);
});


// Add admin scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post-new.php' || $hook === 'post.php') {
        global $post;
        if ($post && $post->post_type === 'flash_offer') {
            wp_enqueue_script('flashoffers-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], null, true);
        }
    }

    wp_enqueue_style('flash-offer-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], null);
    wp_enqueue_script('jquery');
    wp_localize_script('flash-offer-product-select', 'FlashOfferAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_add_inline_script('wp-color-picker', '
        jQuery(document).ready(function($) {
            $(".flash-offer-color-field").wpColorPicker();
        });
    ');
});


// Replace the current uninstall hook with:
register_uninstall_hook(__FILE__, 'flashoffers_handle_uninstall');

// Add this function:
function flashoffers_handle_uninstall()
{
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        define('WP_UNINSTALL_PLUGIN', true);
    }

    require_once plugin_dir_path(__FILE__) . 'includes/wao-functions.php';
    flashoffers_uninstall();
}