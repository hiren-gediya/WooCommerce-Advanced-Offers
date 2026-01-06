<?php
// ✅ Unified Saving Notice for BOGO + Flash Offers
add_action('woocommerce_before_thankyou', 'offer_thankyou_total_saving_notice', 5);

// Show total savings on the Thank You page
function offer_thankyou_total_saving_notice($order_id)
{
    global $wpdb;
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $total_saved = 0;
    

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $product_id = $product->get_id();
        $qty        = $item->get_quantity();
        $line_total = (float) $item->get_total(); // Price paid for the line item, after all discounts.
        $actual_price_paid  = $line_total / max(1, $qty);

        $current_date = current_time('mysql');
        $original_price = 0;

        // Get product's base prices
        $data = $product->get_data();
        $regular_price = (float) $data['regular_price'];
        $sale_price    = (float) $data['sale_price'];

        // Use helper functions to check if the product is part of an active offer.
        $flash_offer_data = flashoffers_get_offer_data($product);
        $bogo_offer_data = bogoffers_get_offer_data($product);

        if ($flash_offer_data && 'active' === $flash_offer_data['status']) {
            // For Flash Offers, the original price is based on the 'flash_override_type' setting.
            $override_type = $flash_offer_data['flash_override_type'] ?? 'sale';
            if ('regular' === $override_type) {
                $original_price = $regular_price;
            } else { // 'sale'
                $original_price = ($sale_price > 0) ? $sale_price : $regular_price;
            }
        } elseif ($bogo_offer_data && 'active' === $bogo_offer_data['status']) {
            // For BOGO offers, the original price is based on the 'bogo_override_type' setting.
            $override_type = $bogo_offer_data['bogo_override_type'] ?? 'sale';
            $original_price = ('regular' === $override_type) ? $regular_price : (($sale_price > 0) ? $sale_price : $regular_price);
        } else {
            // This is a standard WooCommerce item, not part of our offers.
            // The original price is its regular price. The `get_total()` on the item already accounts for WC sales.
            $original_price = $regular_price;
        }

        $saving_per_unit = max(0, $original_price - $actual_price_paid);
        $line_saving     = $saving_per_unit * $qty;
        $total_saved    += $line_saving;
        
        // --- DEBUGGING: UNCOMMENT TO SEE ITEM-LEVEL CALCULATIONS ---
        // echo "<pre>--- ITEM: {$product->get_name()} (ID: {$product_id}) ---\n";
        // print_r([
        //     'Quantity' => $qty,
        //     'Regular Price' => $regular_price,
        //     'Sale Price' => $sale_price,
        //     'Original Price (Base for Savings)' => $original_price,
        //     'Actual Price Paid (per unit)' => $actual_price_paid,
        //     'Saving Per Unit' => $saving_per_unit,
        //     'Line Saving' => $line_saving,
        //     'Cumulative Total Saved' => $total_saved,
        // ]);
        // echo "</pre>";
    }

    // ✅ Show message if any saving
    if ($total_saved > 0) {
        $options = get_option('flash_offers_options');
        if (empty($options['message'])) {
            $options = get_option('bogo_offers_options');
        }

        $message = !empty($options['message'])
            ? (strpos($options['message'], '{amount}') === false
                ? $options['message'] . ' {amount}'
                : $options['message'])
            : '🎉 Congratulations! You saved {amount} in this order';

        $formatted_amount = wc_price($total_saved);
        $final_message    = str_replace('{amount}', $formatted_amount, $message);

        echo '<div class="woocommerce-message offer-total-saved" style="margin-bottom: 20px;">';
        echo '<strong>' . wp_kses_post($final_message) . '</strong>';
        echo '</div>';
    }
}

// Display Flash Offer price in order item name
add_filter('woocommerce_order_item_name', 'offer_order_item_price_html', 10, 3);
function offer_order_item_price_html($item_name, $item, $is_visible)
{
    $product = $item->get_product();
    if (!$product) return $item_name;

    $price_html = '';
    $flash_offer_data = flashoffers_get_offer_data($product);
    $bogo_offer_data = bogoffers_get_offer_data($product);

    if ($flash_offer_data && 'active' === $flash_offer_data['status']) {
        $price_html = flashoffers_get_price_html($product);
    } elseif ($bogo_offer_data && 'active' === $bogo_offer_data['status']) {
        $price_html = bogo_offers_price_html_override($product->get_price_html(), $product);
    }

    if ($price_html) {
        $item_name .= '<br><small class="offer-price-breakdown">' . $price_html . '</small>';
    }

    return $item_name;
}
