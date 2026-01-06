<?php
// Handle AJAX request to add BOGO product to cart
add_action('wp_ajax_bogo_add_to_cart', 'handle_bogo_ajax_add_to_cart');
add_action('wp_ajax_nopriv_bogo_add_to_cart', 'handle_bogo_ajax_add_to_cart');

function handle_bogo_ajax_add_to_cart()
{
    check_ajax_referer('bogo_add_nonce', 'nonce');

    $product_id = intval($_POST['product_id']);
    $variation_id = intval($_POST['variation_id']);
    $quantity = intval($_POST['quantity']);

    if (!$product_id) {
        wp_send_json_error(['message' => 'Invalid product ID.']);
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => 'Invalid product.']);
    }

    // Validate variation if provided
    if ($variation_id && !wc_get_product($variation_id)) {
        wp_send_json_error(['message' => 'Invalid variation.']);
    }

    // Add product/variation to cart
    $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id);

    if ($added) {
        wp_send_json_success(['message' => 'Product added to cart successfully!']);
    } else {
        wp_send_json_error(['message' => 'Could not add product to cart.']);
    }
}

// Handle AJAX request to load BOGO product form
add_action('wp_ajax_load_bogo_product_form', 'load_bogo_product_form');
add_action('wp_ajax_nopriv_load_bogo_product_form', 'load_bogo_product_form');
function load_bogo_product_form()
{
    if (!isset($_POST['buy_product_id']) || !isset($_POST['get_product_id'])) {
        wp_send_json_error(['message' => 'Missing offer data']);
    }

    $buy_product_id = intval($_POST['buy_product_id']);
    $get_product_id = intval($_POST['get_product_id']);
    $buy_quantity = intval($_POST['buy_quantity'] ?? 1);
    $get_quantity = intval($_POST['get_quantity'] ?? 1);
    $offer_type = sanitize_text_field($_POST['offer_type'] ?? 'buy_x_get_y');
    $discount = floatval($_POST['discount'] ?? 0);

    $buy_product = wc_get_product($buy_product_id);
    $get_product = wc_get_product($get_product_id);

    if (!$buy_product || !$get_product) {
        wp_send_json_error(['message' => 'Invalid products']);
    }

    // Query the full offer record from database for different products offers
    if ($offer_type === 'buy_x_get_y') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bogo_offers';
        $offer_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE buy_product_id = %d AND get_product_id = %d",
            $buy_product_id,
            $get_product_id
        ));

        if (!$offer_record) {
            wp_send_json_error(['message' => 'Offer not found in database']);
        }

        // Use database values for accuracy
        $discount = $offer_record->discount;
        $GLOBALS['bogo_offer_id'] = (int) $offer_record->id;
        $GLOBALS['bogo_discount'] = $discount;
    }



    // Set global BOGO data for custom_display_variable_product
    $GLOBALS['bogo_data'] = array(
        'buy_product_id' => $buy_product_id,
        'get_product_id' => $get_product_id,
        'buy_quantity' => $buy_quantity,
        'get_quantity' => $get_quantity,
        'offer_type' => $offer_type,
        'discount' => $discount
    );

    ob_start();

    echo '<div class="bogo-offer-box">';

    // ========================
    // ✅ OFFER TITLE
    // ========================
    if ($offer_type == 'buy_one_get_one') {
        echo '<h3>Buy One Get One: ' . esc_html($buy_product->get_name()) . '</h3>';
    } else {
        echo '<h3>Buy ' . esc_html($buy_quantity) . ': ' . esc_html($buy_product->get_name()) . '</h3>';
    }

    // ========================
    // ✅ BUY PRODUCT FORM
    // ========================
    global $product, $post;
    $product = $buy_product; // set global

    $buy_post = get_post($buy_product_id);
    if ($buy_post) {
        $post = $buy_post;
        setup_postdata($buy_post);
    }
    $price = $product->get_sale_price() ?: $product->get_regular_price();
    $options = get_option('flash_offers_options');
    $bogo_format = $options['bogo_format'] ?? 'default';

    if ($bogo_format === 'default') {
        if ($buy_product->is_type('simple') || $get_product->is_type('simple')) {
            if ($product->is_on_sale()) {
                // Show regular (strikethrough) and sale price
                echo '<td class="price">
                <del>' . wc_price($product->get_regular_price()) . '</del> 
                <ins>' . wc_price($product->get_sale_price()) . '</ins>
              </td>';
            } else {
                // Show normal price
                echo '<td class="price">' . wc_price($price) . '</td>';
            }
        }
    }
    ob_start();
    display_bogo_product_form($product, true, 'buy');
    $buy_html = ob_get_clean();
    $buy_html = str_replace('<form class="', '<form class="bogo-buy-form ', $buy_html);

    // Add hidden input for simple product add-to-cart
    if ($buy_product->is_type('simple')) {
        $hidden_input = '<input type="hidden" name="add-to-cart" value="' . esc_attr($buy_product->get_id()) . '" />';
        $buy_html = str_replace('</form>', $hidden_input . '</form>', $buy_html);
        // Wrap in div for inline display
        $buy_html = '<div class="bogo-simple-product-inline">' . $buy_html . '</div>';
    }

    // Append variations data inside the form if variable
    if ($buy_product->is_type('variable')) {
        $variations_json = wp_json_encode($buy_product->get_available_variations());
        $attributes      = $buy_product->get_variation_attributes();

        $variations_script = '<div class="variations_form" data-product_id="' . esc_attr($buy_product->get_id()) . '">';
        $variations_script .= '<script type="application/json" class="wc-variations">' . $variations_json . '</script>';
        $variations_script .= '</div>';

        $buy_html = str_replace('</form>', $variations_script . '</form>', $buy_html);
    }


    echo $buy_html;

    // ========================
    // ✅ GET PRODUCT (only if not BOGO 1+1)
    // ========================
    if ($offer_type !== 'buy_one_get_one') {
        echo '<h3>Get ' . esc_html($get_quantity) . ' at ' . esc_html(round($discount)) . '% Off: ' . esc_html($get_product->get_name()) . '</h3>';

        $product = $get_product; // set global

        $get_post = get_post($get_product_id);
        if ($get_post) {
            $post = $get_post;
            setup_postdata($get_post);
        }
        $price = $product->get_sale_price() ?: $product->get_regular_price();
       
        $options = get_option('flash_offers_options');
        $bogo_format = $options['bogo_format'] ?? 'default';

        if ($bogo_format === 'default') {
            if ($get_product->is_type('simple') || $get_product->is_type('simple')) {

                if ($product->is_on_sale()) {
                    // Show regular (strikethrough) and sale price
                    echo '<td class="price">
                <del>' . wc_price($product->get_regular_price()) . '</del> 
                <ins>' . wc_price($product->get_sale_price()) . '</ins>
              </td>';
                } else {
                    // Show normal price
                    echo '<td class="price">' . wc_price($price) . '</td>';
                }
            }
        }

        ob_start();
        display_bogo_product_form($product, true, 'get');
        $get_html = ob_get_clean();
        $get_html = str_replace('<form class="', '<form class="bogo-get-form ', $get_html);
        // Remove id attributes from select tags and for attributes from labels to avoid duplicates
        $get_html = preg_replace('/\sid\s*=\s*["\'][^"\']*["\']/', '', $get_html);
        $get_html = preg_replace('/\sfor\s*=\s*["\'][^"\']*["\']/', '', $get_html);

        // Add hidden input for simple product add-to-cart
        if ($get_product->is_type('simple')) {
            $hidden_input = '<input type="hidden" name="add-to-cart" value="' . esc_attr($get_product->get_id()) . '" />';
            $get_html = str_replace('</form>', $hidden_input . '</form>', $get_html);
            // Wrap in div for inline display
            $get_html = '<div class="bogo-simple-product-inline">' . $get_html . '</div>';
        }

        // Append variations data inside the form if variable
        if ($get_product->is_type('variable')) {
            $variations_json = wp_json_encode($get_product->get_available_variations());
            $attributes      = $get_product->get_variation_attributes();

            $variations_script = '<div class="variations_form" data-product_id="' . esc_attr($get_product->get_id()) . '">';
            $variations_script .= '<script type="application/json" class="wc-variations">' . $variations_json . '</script>';
            $variations_script .= '</div>';

            $get_html = str_replace('</form>', $variations_script . '</form>', $get_html);
        }


        echo $get_html;
    }

    wp_reset_postdata();

    echo '</div>';

    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'offer_type' => $offer_type,
        'buy_quantity' => $buy_quantity,
        'get_quantity' => $get_quantity
    ]);
}