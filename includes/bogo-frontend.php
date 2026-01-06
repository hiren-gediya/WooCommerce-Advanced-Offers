<?php
// Include separated BOGO frontend files
require_once plugin_dir_path(__FILE__) . 'bogo-display.php';
require_once plugin_dir_path(__FILE__) . 'bogo-ajax.php';
require_once plugin_dir_path(__FILE__) . 'bogo-cart.php';

add_action('wp_enqueue_scripts', 'enqueue_bogo_frontend_assets');
function enqueue_bogo_frontend_assets()
{
    // Enqueue CSS
    wp_enqueue_style('bogo-frontend-css', plugin_dir_url(__FILE__) . '../assets/css/bogo-frontend.css', array(), '1.0');

    // Enqueue JS
    wp_enqueue_script('bogo-frontend-js', plugin_dir_url(__FILE__) . '../assets/js/bogo-frontend.js', array('jquery'), '1.0', true);

    // Localize script for AJAX
    wp_localize_script('bogo-frontend-js', 'bogo_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bogo_add_nonce')
    ));

}
