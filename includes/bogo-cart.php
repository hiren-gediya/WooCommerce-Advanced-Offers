<?php
// ✅ Ensure minimum quantity for same-product BOGO
add_filter('woocommerce_add_to_cart_quantity', 'handle_bogo_add_to_cart', 10, 2);
function handle_bogo_add_to_cart($quantity, $product_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'bogo_offers';
    $current_date = current_time('mysql');

    $bogo_offers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name
        WHERE buy_product_id = %d
        AND start_date <= %s
        AND end_date >= %s",
        $product_id,
        $current_date,
        $current_date
    ));

    foreach ($bogo_offers as $offer) {
        if ($offer->buy_product_id == $product_id && $quantity < $offer->buy_quantity) {
            wc_add_notice(
                sprintf(__('For this BOGO offer, please add at least %d items.', 'flash-offers'), $offer->buy_quantity),
                'notice'
            );
        }
    }

    return $quantity;
}


// ✅ Apply BOGO discounts to cart
add_action('woocommerce_before_calculate_totals', 'apply_bogo_discount', 20);

function apply_bogo_discount($cart)
{
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (did_action('woocommerce_before_calculate_totals') >= 2) return;

    global $wpdb;
    $table_name   = $wpdb->prefix . 'bogo_offers';
    $current_date = current_time('mysql');

    // Get all product IDs in cart
    $cart_product_ids = array();
    foreach ($cart->get_cart() as $cart_item) {
        $cart_product_ids[] = $cart_item['product_id'];
    }
    $cart_product_ids = array_unique($cart_product_ids);
    if (empty($cart_product_ids)) return;

    $placeholders = implode(',', array_fill(0, count($cart_product_ids), '%d'));
    $bogo_offers  = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name
        WHERE (buy_product_id IN ($placeholders) OR get_product_id IN ($placeholders))
        AND start_date <= %s
        AND end_date >= %s",
        array_merge($cart_product_ids, $cart_product_ids, [$current_date, $current_date])
    ));
    if (empty($bogo_offers)) return;

    // Get global BOGO override type
    $options = get_option('flash_offers_options');
    $bogo_override_type = $options['bogo_override_type'] ?? 'sale';

    // Reset all prices before applying discounts
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product    = $cart_item['data'];
        $product_id = $cart_item['product_id'];

        // Find matching offer
        $offer = null;
        foreach ($bogo_offers as $o) {
            if ($o->buy_product_id == $product_id || $o->get_product_id == $product_id) {
                $offer = $o;
                break;
            }
        }
        if (!$offer) continue;

        // Use override type from offer if available, else global
        $override_type = $offer->override_type ?? $bogo_override_type;

        $product_data = $product->get_data();
        $regular_price = (float) $product_data['regular_price'];
        $sale_price    = (float) $product_data['sale_price'];

        if ($override_type == 'regular') {
            $base_price = $regular_price;
        } elseif ($override_type == 'sale') {
            $base_price = ($sale_price > 0) ? $sale_price : $regular_price;
        } else {
            $base_price = ($sale_price > 0) ? $sale_price : $regular_price;
        }

        $cart->cart_contents[$cart_item_key]['bogo_original_price'] = $base_price;
        $product->set_price($cart->cart_contents[$cart_item_key]['bogo_original_price']);
    }

    // Apply discounts
    foreach ($bogo_offers as $offer) {
        if ($offer->buy_product_id == $offer->get_product_id) {
            apply_bogo_same_product_discount($cart, $offer);
        } else {
            apply_bogo_different_products_discount($cart, $offer);
        }
    }
}

function apply_bogo_same_product_discount($cart, $offer)
{
    $product_id   = $offer->buy_product_id;
    $buy_quantity = $offer->buy_quantity;
    $get_quantity = $offer->get_quantity;
    $discount     = $offer->discount > 0 ? $offer->discount : 100;

    $product_count = 0;
    $product_cart_keys = [];

    // Count total quantity in cart
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if ($cart_item['product_id'] == $product_id) {
            $product_count += $cart_item['quantity'];
            $product_cart_keys[] = $cart_item_key;
        }
    }

    // Apply offer only once
    if ($product_count >= ($buy_quantity + $get_quantity)) {
        $discount_items = $get_quantity; // only once

        foreach ($product_cart_keys as $cart_key) {
            $qty = $cart->cart_contents[$cart_key]['quantity'];
            $original_price = $cart->cart_contents[$cart_key]['bogo_original_price'];

            // Split: free only applies once across all cart items
            $line_free   = min($discount_items, $qty);
            $line_paid   = $qty - $line_free;
            $discount_items -= $line_free;

            // Weighted average
            $line_total = ($line_paid * $original_price) + ($line_free * $original_price * (1 - $discount / 100));
            $line_price = $line_total / $qty;

            $cart->cart_contents[$cart_key]['data']->set_price($line_price);
        }
    }
}

function apply_bogo_different_products_discount($cart, $offer)
{
    $buy_id   = $offer->buy_product_id;
    $get_id   = $offer->get_product_id;
    $buy_qty  = $offer->buy_quantity;
    $get_qty  = $offer->get_quantity;
    $discount = $offer->discount > 0 ? $offer->discount : 100;

    $total_buy_qty = 0;
    $get_key = null;
    $get_quantity_in_cart = 0;

    foreach ($cart->get_cart() as $cart_key => $cart_item) {
        if ($cart_item['product_id'] == $buy_id) {
            $total_buy_qty += $cart_item['quantity'];
        }
        if ($cart_item['product_id'] == $get_id) {
            $get_key = $cart_key;
            $get_quantity_in_cart = $cart_item['quantity'];
        }
    }

    if ($total_buy_qty >= $buy_qty && $get_key !== null) {
        $product = $cart->cart_contents[$get_key]['data'];
        $offer_data    = bogoffers_get_offer_data($product);
        $override_type = isset($offer_data['bogo_override_type']) ? $offer_data['bogo_override_type'] : '';
        // ✅ Apply override
        if ($override_type === 'regular') {
            $original_price = $product->get_regular_price();
        } else {
            $original_price = $product->get_sale_price() ?: $product->get_regular_price();
        }

        if ($get_quantity_in_cart == $get_qty) {
            $new_price = $original_price * (1 - $discount / 100);
            $product->set_price($new_price);
        } elseif ($get_quantity_in_cart > $get_qty) {
            $discounted_items = $get_qty;
            $extra_items = $get_quantity_in_cart - $get_qty;

            $total_price = ($discounted_items * $original_price * (1 - $discount / 100))
                + ($extra_items * $original_price);

            $average_price = $total_price / $get_quantity_in_cart;
            $product->set_price($average_price);
        }
    }
}

function display_bogo_discount_in_cart($price_html, $cart_item, $cart_item_key)
{
    $product     = $cart_item['data'];
    $product_id  = $cart_item['product_id'];
    $offer       = get_offer_for_product($product_id);

    // ✅ If not part of any BOGO offer → use default Woo price display
    if (!$offer) {
        $regular_price  = (float) $product->get_regular_price();
        $sale_price     = (float) $product->get_sale_price();
        $current_price  = (float) $product->get_price();

        if ($sale_price && $sale_price < $regular_price) {
            return '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($sale_price) . '</ins>';
        }
        return wc_price($current_price);
    }

    // ✅ Get offer data
    $offer_data    = bogoffers_get_offer_data($product);
    $override_type = isset($offer_data['bogo_override_type']) ? $offer_data['bogo_override_type'] : '';

    $regular_price = (float) $product->get_regular_price();
    $sale_price    = (float) $product->get_sale_price();
    $current_price = (float) $product->get_price();
    // 🟢 CASE 1: Override = regular → always show regular price without strike-through
    if ($override_type === 'regular') {
        if ($regular_price && $current_price < $regular_price) {
            return '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($current_price) . '</ins>';
        } else {
            return wc_price($regular_price);
        }
    }

    // 🟡 CASE 2: Override = sale → show sale price (if available)
    if ($override_type === 'sale') {
        if ($sale_price && $current_price < $sale_price) {
            return '<del>' . wc_price($sale_price) . '</del> <ins>' . wc_price($current_price) . '</ins>';
        } else {
            return wc_price($sale_price);
        }
    }
}


function get_offer_for_product($product_id)
{
    global $wpdb;
    $table_name   = $wpdb->prefix . 'bogo_offers';
    $current_date = current_time('mysql');

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name
        WHERE (buy_product_id = %d OR get_product_id = %d)
        AND start_date <= %s AND end_date >= %s LIMIT 1",
        $product_id,
        $product_id,
        $current_date,
        $current_date
    ));
}
