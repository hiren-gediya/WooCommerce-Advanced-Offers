<?php
// Display BOGO offer on single product page
add_action('woocommerce_single_product_summary', 'display_bogo_offer_on_product_page', 20);
function display_bogo_offer_on_product_page()
{
    if (!is_single()) {
        return;
    }

    // Enqueue countdown script for BOGO offer countdown timer
    wp_enqueue_script('flashoffers-timer');

    global $product, $wpdb;

    $product_id   = $product->get_id();
    $table_name   = $wpdb->prefix . 'bogo_offers';
    $current_date = current_time('mysql');

    $bogo_offers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name
         WHERE (buy_product_id = %d OR get_product_id = %d)
         AND start_date <= %s
         AND end_date >= %s",
        $product_id,
        $product_id,
        $current_date,
        $current_date
    ));

    if (!empty($bogo_offers)) {
        foreach ($bogo_offers as $offer) {
            $buy_product = wc_get_product($offer->buy_product_id);
            $get_product = wc_get_product($offer->get_product_id);
            if (!$buy_product || !$get_product) continue;

            $buy_in_cart = false;
            $get_in_cart = false;

            if (WC()->cart) {
                foreach (WC()->cart->get_cart() as $cart_item) {
                    if (intval($cart_item['product_id']) === intval($offer->buy_product_id)) {
                        $buy_in_cart = true;
                    }
                    if (intval($cart_item['product_id']) === intval($offer->get_product_id)) {
                        $get_in_cart = true;
                    }
                }
            }

            if ($buy_in_cart && $get_in_cart) {
                continue;
            }
            do_action('flash_offers_before_bogo_offer_notice', $offer, $buy_product, $get_product);

            echo '<div class="bogo-offer-notice">';
            echo '<h3>' . __('Special BOGO Offer!', 'flash-offers') . '</h3>';

            $offer_type   = $offer->offer_type;
            $discount_text = $offer->discount > 0 ? $offer->discount . '%' : '100%';
            echo '<p>' . sprintf(
                __('Buy %d %s and get %d %s with %s discount!', 'flash-offers'),
                $offer->buy_quantity,
                $buy_product->get_name(),
                $offer->get_quantity,
                $get_product->get_name(),
                $discount_text
            ) . '</p>';

            do_action('flash_offers_before_bogo_offer_box', $offer, $buy_product, $get_product);

            echo '<button type="button" class="button alt bogo-popup-btn"
                    data-buy-product-id="' . esc_attr($offer->buy_product_id) . '"
                    data-buy-quantity="' . esc_attr($offer->buy_quantity) . '"
                    data-get-product-id="' . esc_attr($offer->get_product_id) . '"
                    data-get-quantity="' . esc_attr($offer->get_quantity) . '"
                    data-offer-type="' . esc_attr($offer->offer_type) . '"
                    data-discount="' . esc_attr($offer->discount) . '">' . __('Add BOGO Offer to Cart', 'flash-offers') . '</button>';

            do_action('flash_offers_after_bogo_offer_box', $offer, $buy_product, $get_product);

            echo '</div>';

            do_action('flash_offers_after_bogo_offer_notice', $offer, $buy_product, $get_product);
        }
    }
}

// Custom display for BOGO products in table format
function bogo_custom_display_variable_product($product_type = '')
{
    global $product, $bogo_data;


    if (!isset($bogo_data)) {
        $bogo_data = array();
    }
    $bogo_get_attrs = '';
    if ($product_type === 'get' && isset($GLOBALS['bogo_offer_id']) && isset($GLOBALS['bogo_discount'])) {
        $bogo_get_attrs = ' data-is-bogo-get="1" data-bogo-offer-id="' . esc_attr($GLOBALS['bogo_offer_id']) . '" data-bogo-discount="' . esc_attr($GLOBALS['bogo_discount']) . '"';
    }

    $offer_data = bogoffers_get_offer_data($product);

    if ($product->is_type('simple')) {
        $product_id = $product->get_ID();
        // $price = $product->get_sale_price() ?: $product->get_regular_price();
        $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';
        echo "<pre>";
        print_r($price_override_type);
        echo "</pre>";
        $regular_price = (float) $product->get_regular_price();
        $sale_price    = (float) $product->get_sale_price();
        // $price_per_piece = $price; // assuming 1 piece
        $offer_type = $GLOBALS['bogo_data']['offer_type'];
        $buy_product_id = $GLOBALS['bogo_data']['buy_product_id'];
        $buy_quantity = $GLOBALS['bogo_data']['buy_quantity'];
        $get_quantity = $GLOBALS['bogo_data']['get_quantity'];

        echo '<table class="variable-product-table bogo-' . esc_attr($product_type) . '-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Price/Unit</th>
            <th>Quantity</th>
            <th>Add BOGO</th>
          </tr>
        </thead>';
        echo "<tbody>";
        echo '<tr data-variation-id="0">';
        echo '<td>' . esc_html($product->get_name()) . '</td>';
        if ($price_override_type == 'regular') {
            // ✅ Always show regular price
            echo '<td class="price">' . wc_price($regular_price) . '</td>';
        } else {
            // ✅ Default behavior (sale if exists and valid)
            if ($sale_price && $sale_price < $regular_price) {
                echo '<td class="price"><del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($sale_price) . '</ins></td>';
            } else {
                echo '<td class="price">' . wc_price($regular_price) . '</td>';
            }
        }
      
        if ($price_override_type === 'regular') {
            $price_per_piece = $regular_price;
        } else {
            $price_per_piece = ($sale_price && $sale_price < $regular_price) ? $sale_price : $regular_price;
        }
        echo '<td class="price-unit">' . wc_price($price_per_piece) . ' /Piece</td>';
        if ($offer_type == 'buy_one_get_one') {
            echo '<td class="list-quantity"><input type="number" value="2" min="1" class="qty" id="qty_' . $product->get_ID() . '" name="quantity_' . $product->get_ID() . '"></td>';
        }
        if ($offer_type == 'buy_x_get_y') {

            if ($product_id === $buy_product_id) {
                echo '<td class="list-quantity"><input type="number" value="' . $buy_quantity . '" min="1" class="qty" id="qty_' . $product->get_ID() . '" name="quantity_' . $product->get_ID() . '"></td>';
            } else {
                echo '<td class="list-quantity"><input type="number" value="' . $get_quantity . '" min="1" class="qty" id="qty_' . $product->get_ID() . '" name="quantity_' . $product->get_ID() . '"></td>';
            }
        }
        echo '<td class="add-bogo">';
        $offer_id = $GLOBALS['bogo_offer_id'] ?? 0;
        $offer_data = bogoffers_get_offer_data($product);
        $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';
        echo '<button type="button" class="button alt bogo-add-to-cart" data-product-id="' . esc_attr($product->get_ID()) . '" data-variation-id="0" data-product-type="' . esc_attr($product_type) . '" data-offer-id="' . esc_attr($offer_id) . '" data-price-override-type="' . esc_attr($price_override_type) . '"' . $bogo_get_attrs . '><span>Add ' . ucfirst($product_type) . ' Product</span></button>';
        echo '</td>';
        echo '</tr>';
        echo "</tbody>";
        echo '</table>';
    }
    if ($product->is_type('variable')) {
        $available_variations = $product->get_available_variations();
        echo '<table class="variable-product-table bogo-' . esc_attr($product_type) . '-table">
    <thead>
      <tr>
        <th>Pack Size</th>
        <th>Price</th>
        <th>Price/Unit</th>
        <th>Quantity</th>
        <th>Add BOGO</th>
      </tr>
    </thead>';
        echo "<tbody>";

        // Get override type (default sale)
        $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';

        // Loop through all variations
        foreach ($available_variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            $regular_price = (float) $variation_obj->get_regular_price();
            $sale_price    = (float) $variation_obj->get_sale_price();

            $attributes = $variation['attributes'];
            $buy_quantity = $GLOBALS['bogo_data']['buy_quantity'];
            $get_quantity = $GLOBALS['bogo_data']['get_quantity'];
            $buy_product_id = $GLOBALS['bogo_data']['buy_product_id'];
            $parent_id = $variation_obj->get_parent_id();
            $is_in_stock = $variation_obj && $variation_obj->is_in_stock();
            $offer_type = $GLOBALS['bogo_data']['offer_type'];
            $pack_size = $attributes['attribute_pa_pack-size'];

            // ✅ Decide which price to use
            if ($price_override_type === 'regular') {
                $final_price       = $regular_price;
                $price_display_html = wc_price($regular_price);
            } else {
                if ($sale_price && $sale_price < $regular_price) {
                    $final_price       = $sale_price;
                    $price_display_html = '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($sale_price) . '</ins>';
                } else {
                    $final_price       = $regular_price;
                    $price_display_html = wc_price($regular_price);
                }
            }

            // ✅ Calculate price per piece
            $price_per_piece = !empty($pack_size) ? number_format($final_price / (int) $pack_size, 2) : '-';

            echo '<tr data-variation-id="' . esc_attr($variation['variation_id']) . '">';
            if (isset($pack_size)) {
                $slug = $pack_size;
                $taxonomy = 'pa_pack-size';

                $term = get_term_by('slug', $slug, $taxonomy);
                if ($term) {
                    echo '<td>' . esc_html($term->name) . '</td>'; // Output: e.g., "100 Tablets"
                }
            }

            // ✅ Price cell
            echo '<td class="price">' . $price_display_html . '</td>';

            // ✅ Price per unit cell
            echo '<td class="price-unit">' . ($price_per_piece !== '-' ? wc_price($price_per_piece) . ' /Piece' : '-') . '</td>';

            // ✅ Quantity logic
            if ($offer_type == 'buy_one_get_one') {
                echo '<td class="list-quantity"><input type="number" value="2" min="1" class="qty" id="qty_' . $variation['variation_id'] . '" name="quantity_' . $variation['variation_id'] . '"></td>';
            }
            if ($offer_type == 'buy_x_get_y') {
                if ($parent_id == $buy_product_id) {
                    echo '<td class="list-quantity"><input type="number" value="' . $buy_quantity . '" min="1" class="qty" id="qty_' . $variation['variation_id'] . '" name="quantity_' . $variation['variation_id'] . '"></td>';
                } else {
                    echo '<td class="list-quantity"><input type="number" value="' . $get_quantity . '" min="1" class="qty" id="qty_' . $variation['variation_id'] . '" name="quantity_' . $variation['variation_id'] . '"></td>';
                }
            }

            // ✅ Add to cart button
            echo '<td>';
            $offer_id = $GLOBALS['bogo_offer_id'] ?? 0;
            $offer_data = bogoffers_get_offer_data($product);
            $price_override_type = $offer_data['bogo_override_type'] ?? 'sale';
            echo '<button type="button" class="button alt bogo-add-to-cart" data-product-id="' . esc_attr($product->get_id()) . '" data-variation-id="' . esc_attr($variation['variation_id']) . '" data-product-type="' . esc_attr($product_type) . '" data-offer-id="' . esc_attr($offer_id) . '" data-price-override-type="' . esc_attr($price_override_type) . '" ' . (!$is_in_stock ? 'disabled' : '') . '><span>Add ' . ucfirst($product_type) . ' Product</span></button>';
            echo '</td>';

            echo '</tr>';
        }

        echo "</tbody>";
        echo '</table>';
    }
}

// Display the BOGO product form (table or default Woo)
function display_bogo_product_form($product, $type_label = '', $show_price = false, $product_type = '')
{
    $options = get_option('flash_offers_options');
    $bogo_format = $options['bogo_format'] ?? 'default';

    // Allow theme or plugin to override BOGO product form display
    $override = apply_filters('flash_offers_override_bogo_product_form', false, $product, $type_label, $show_price, $product_type);
    if ($override) {
        echo $override;
        return;
    }

    if ($bogo_format === 'table') {
        bogo_custom_display_variable_product($product_type);
    } else {
        // Set global $product to ensure WooCommerce functions work properly
        global $product;
        $product = $product;

        // Enqueue WooCommerce variation script if product is variable
        if ($product->is_type('variable')) {
            wp_enqueue_script('wc-add-to-cart-variation');
        }

        // Use WooCommerce default add to cart action for proper dropdown support
        do_action('woocommerce_' . $product->get_type() . '_add_to_cart');
    }
}



// Function to get BOGO offer data for a product
function bogoffers_get_offer_data($product)
{
    global $wpdb;

    if (!$product || !is_a($product, 'WC_Product')) {
        return false;
    }

    $product_id = $product->get_id();
    if ($product->is_type('variation')) {
        $product_id = $product->get_parent_id();
    }

    $table_name = $wpdb->prefix . 'bogo_offers';
    $current_date = current_time('mysql');

    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name
         WHERE (buy_product_id = %d OR get_product_id = %d)
         AND start_date <= %s
         AND end_date >= %s
         ORDER BY id DESC
         LIMIT 1",
        $product_id,
        $product_id,
        $current_date,
        $current_date
    ));

    if (!$offer) {
        return false;
    }

    $options = get_option('flash_offers_options');
    $badge_text = $options['bogo_offer_badge_text'] ?? '';
    $locations = $options['locations'] ?? [];
    $countdown_locations = $options['countdown_locations'] ?? [];
    $bogo_override_type = $options['bogo_override_type'] ?? 'sale';

    // Determine status based on current time and offer start/end
    $now = new DateTime('now', wp_timezone());
    $start = new DateTime($offer->start_date, wp_timezone());
    $end = new DateTime($offer->end_date, wp_timezone());

    if ($now < $start) {
        $status = 'upcoming';
    } elseif ($now >= $start && $now <= $end) {
        $status = 'active';
    } else {
        $status = 'expired';
    }

    return [
        'offer' => $offer,
        'badge_text' => $badge_text,
        'bogo_override_type' => $bogo_override_type,
        'locations' => $locations,
        'countdown_locations' => $countdown_locations,
        'status' => $status,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'background_color' => $options['badge_bg_color'] ?? '#ff4d4f',
    ];
}


// Hook display function to WooCommerce actions
add_action('woocommerce_before_shop_loop_item_title', 'display_bogo_offer_badge', 9);
add_action('woocommerce_single_product_summary', 'display_bogo_offer_badge', 8);
add_action('display_bogo_offer_badge', 'display_bogo_offer_badge');
function display_bogo_offer_badge()
{
    global $product;
    if (!$product) return;

    $offer_data = bogoffers_get_offer_data($product);
    if (!$offer_data || empty($offer_data['badge_text'])) return;

    $locations = $offer_data['locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $show_on_home = is_front_page() && !empty($locations['home_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !is_front_page() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);
    $options = get_option('flash_offers_options');
    $badge_color = $options['badge_bg_color'] ?? '#ff4d4f';

    if ($show_on_shop || $show_on_category || $show_on_home || $is_other_location || $show_on_single) {
        echo '<span class="flash-offer-badge ' . esc_attr($offer_data['status']) . '" style="width: fit-content;; background-color:' . esc_attr($badge_color) . ';">' . esc_html($offer_data['badge_text']) . '</span>';
    }
}


add_action('woocommerce_before_shop_loop_item', 'remove_bogo_sale_badge_css');
add_action('woocommerce_before_single_product_summary', 'remove_bogo_sale_badge_css');

function remove_bogo_sale_badge_css()
{
    global $product;
    if (!$product) return;

    // Get BOGO data
    $offer_data = bogoffers_get_offer_data($product);

    // If variation has no offer, check parent
    if (empty($offer_data['offer']) && $product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        if ($parent) {
            $offer_data = bogoffers_get_offer_data($parent);
        }
    }

    // ✅ Only if this product has an offer → hide badge for THIS product only
    if (!empty($offer_data['offer'])) {
        echo '<style>.post-' . $product->get_id() . ' .onsale { display: none !important; }</style>';
    }
}


// Hook countdown display for BOGO offers
add_action('woocommerce_before_shop_loop_item_title', 'display_bogoffers_countdown', 9);
add_action('woocommerce_single_product_summary', 'display_bogoffers_countdown', 8);
function display_bogoffers_countdown()
{
    global $product;
    if (!$product) return;

    $offer_data = bogoffers_get_offer_data($product);
    if (!$offer_data) return;

    $locations = $offer_data['countdown_locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $show_on_home = is_front_page() && !empty($locations['home_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !is_front_page() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);
    $wp_timezone = wp_timezone();

    if ($show_on_shop || $show_on_category || $show_on_home || $is_other_location || $show_on_single) {
        if ($offer_data['status'] === 'upcoming' && !empty($offer_data['start'])) {
            $start = new DateTime($offer_data['start'], $wp_timezone);
            $start->setTimezone(new DateTimeZone('UTC'));
            echo '<div id="flash-offer-countdown" class="upcoming-offer"
                  data-start="' . esc_attr($start->format('Y-m-d\TH:i:s\Z')) . '"
                  data-product-id="' . esc_attr($product->get_id()) . '">Starts soon</div>';
        } elseif (!empty($offer_data['end'])) {
            $end = new DateTime($offer_data['end'], $wp_timezone);
            $end->setTimezone(new DateTimeZone('UTC'));
            echo '<div id="flash-offer-countdown"
                  data-end="' . esc_attr($end->format('Y-m-d\TH:i:s\Z')) . '"
                  data-product-id="' . esc_attr($product->get_id()) . '">Ending soon</div>';
        }
    }
}


// Filter the price HTML to override based on BOGO settings
add_filter('woocommerce_get_price_html', 'bogo_offers_price_html_override', 100, 2);
function bogo_offers_price_html_override($price_html, $product)
{
    // Get BOGO offer data for the product
    $offer_data = bogoffers_get_offer_data($product);

    // If no active BOGO offer, return the default price HTML
    if (!$offer_data || $offer_data['status'] !== 'active') {
        return $price_html;
    }

    // Get the override type from settings, defaulting to 'sale'
    $override_type = $offer_data['bogo_override_type'] ?? 'sale';

    // Handle selected variation price display
    if ($product->is_type('variation')) {
        if ($override_type === 'regular') {
            return wc_price($product->get_regular_price());
        }
        // For 'sale' override, the default behavior is correct.
        return $price_html;
    }


    // Handle variable products
    if ($product->is_type('variable')) {
        $prices = $product->get_variation_prices(true);

        if (empty($prices['price'])) {
            return $price_html;
        }

        if ($override_type == 'regular') {
            $min_reg_price = current($prices['regular_price']);
            $max_reg_price = end($prices['regular_price']);
            return $min_reg_price !== $max_reg_price ? sprintf('%1$s–%2$s', wc_price($min_reg_price), wc_price($max_reg_price)) : wc_price($min_reg_price);
        }

        // For 'sale' override, the default WooCommerce behavior is usually correct,
        // as it shows sale price ranges. We'll just return the original HTML.
        return $price_html;
    }

    // Handle simple products
    if ($product->is_type('simple')) {
        if ($override_type == 'regular') {
            // If override is 'regular', display only the regular price.
            return wc_price($product->get_regular_price());
        } else { // 'sale'
            // If override is 'sale', show sale price with regular price struck out if on sale.
            if ($product->is_on_sale()) {
                return wc_format_sale_price(wc_get_price_to_display($product, array('price' => $product->get_regular_price())), wc_get_price_to_display($product));
            } else {
                return wc_price($product->get_regular_price());
            }
        }
    }

    return $price_html;
}


// Filter the variation data to override the price HTML based on BOGO settings.
add_filter('woocommerce_available_variation', 'bogo_offers_variation_price_html_override', 100, 3);
function bogo_offers_variation_price_html_override($variation_data, $product, $variation)
{
    $offer_data = bogoffers_get_offer_data($product);
    if ($offer_data && ($offer_data['bogo_override_type'] ?? 'sale') === 'regular') {
        $variation_data['price_html'] = '<span class="price">' . wc_price($variation->get_regular_price()) . '</span>';
    }
    return $variation_data;
}
