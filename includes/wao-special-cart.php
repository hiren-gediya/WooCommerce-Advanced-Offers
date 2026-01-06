<?php
// 1. Add offer data to cart items
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    if (isset($_REQUEST['from_offer'])) {
        // Verify the offer is valid
        global $wpdb;
        $offer_id = (int)$_REQUEST['from_offer'];
        
        $is_valid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}flash_offers o
             JOIN {$wpdb->prefix}flash_offer_products op ON o.id = op.offer_id
             WHERE o.post_id = %d 
             AND o.offer_type = 'special'
             AND op.product_id = %d
             AND o.end_date > %s",
            $offer_id,
            $product_id,
            current_time('mysql')
        ));

        if ($is_valid) {
            $cart_item_data['from_offer'] = $offer_id;
            $cart_item_data['special_offer_source'] = true;
            $cart_item_data['special_offer_original_price'] = wc_get_product($variation_id ? $variation_id : $product_id)->get_regular_price();
        }
    }
    return $cart_item_data;
}, 10, 3);

// 2. Apply special offer pricing in cart
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (did_action('woocommerce_before_calculate_totals') >= 2) return;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        // Only process items with special offer source
        if (empty($cart_item['special_offer_source'])) continue;
        
        $product = $cart_item['data'];
        $product_id = $cart_item['product_id'];
        $offer_id = $cart_item['from_offer'] ?? null;
        
        if (!$offer_id) continue;

        global $wpdb;
        
        // Verify the product is still in this special offer
        $is_valid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}flash_offer_products op
             JOIN {$wpdb->prefix}flash_offers o ON op.offer_id = o.id
             WHERE o.post_id = %d 
             AND o.offer_type = 'special'
             AND op.product_id = %d
             AND o.end_date > %s",
            $offer_id,
            $product_id,
            current_time('mysql')
        ));

        if (!$is_valid) {
            // Remove the offer data if invalid
            unset(WC()->cart->cart_contents[$cart_item_key]['from_offer']);
            unset(WC()->cart->cart_contents[$cart_item_key]['special_offer_source']);
            continue;
        }

        // Get offer discount
        $offer = $wpdb->get_row($wpdb->prepare(
            "SELECT discount FROM {$wpdb->prefix}flash_offers 
             WHERE post_id = %d AND offer_type = 'special'",
            $offer_id
        ));

        if ($offer && $offer->discount > 0) {
            $original_price = $cart_item['special_offer_original_price'] ?? $product->get_regular_price();
            $discounted_price = $original_price - ($original_price * $offer->discount / 100);
            
            // Set the discounted price
            $product->set_price($discounted_price);
            $product->set_sale_price($discounted_price);

            // Store offer info
            $cart_item['data']->update_meta_data('_special_offer_applied', 'yes');
            $cart_item['data']->update_meta_data('_special_offer_discount', $offer->discount);
            $cart_item['data']->update_meta_data('_special_offer_id', $offer_id);
        }
    }
}, 20);


// 6. Preserve the from_offer parameter in redirects
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    if (isset($_REQUEST['from_offer'])) {
        $url = add_query_arg('from_offer', (int)$_REQUEST['from_offer'], $url);
    }
    return $url;
});

// 7. Add JavaScript to handle single product page add-to-cart
add_action('wp_footer', function() {
    if (!is_product()) return;
    if (!isset($_GET['from_offer'])) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Add hidden field to maintain offer parameter
        $('form.cart').append('<input type="hidden" name="from_offer" value="<?php echo (int)$_GET['from_offer']; ?>">');
        
        // Handle AJAX add-to-cart
        $(document).on('added_to_cart', function() {
            window.location.href = '<?php echo add_query_arg('from_offer', (int)$_GET['from_offer'], wc_get_cart_url()); ?>';
        });
    });
    </script>
    <?php
});



