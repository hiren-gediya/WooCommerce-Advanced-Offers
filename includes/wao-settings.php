<?php
// Field callback function
function flash_offer_message_field_callback()
{
    $options = get_option('flash_offers_options');
    $message = isset($options['message']) ? $options['message'] : 'Congratulations! You saved {amount} in this order';
?>
    <textarea name="flash_offers_options[message]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($message); ?></textarea>
    <p class="description"><?php _e('Enter the flash offer message to be displayed.', 'flash-offers'); ?></p>
<?php
}
// Display locations field for badge  callback function
function flash_offer_locations_field_callback()
{
    $options = get_option('flash_offers_options');
    $shop_loop = isset($options['locations']['shop_loop']) ? 1 : 0;
    $product_page = isset($options['locations']['product_page']) ? 1 : 0;
    $category_page = isset($options['locations']['category_page']) ? 1 : 0;
    $home_page = isset($options['locations']['home_page']) ? 1 : 0;
    $other_page = isset($options['locations']['other_page']) ? 1 : 0;
?>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][shop_loop]" value="1" <?php checked($shop_loop, 1); ?>>
        <?php _e('Shop/Archive Pages', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][home_page]" value="1" <?php checked($home_page, 1); ?>>
        <?php _e('Home Page', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][category_page]" value="1" <?php checked($category_page, 1); ?>>
        <?php _e('Category Pages', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][product_page]" value="1" <?php checked($product_page, 1); ?>>
        <?php _e('Single Product Pages', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[locations][other_page]" value="1" <?php checked($other_page, 1); ?>>
        <?php _e('Other Pages', 'flash-offers'); ?>
    </label>
<?php
}

// Countdown locations field
function flash_offer_countdown_locations_field_callback()
{
    $options = get_option('flash_offers_options');

    $shop_loop    = isset($options['countdown_locations']['shop_loop']) ? 1 : 0;
    $product_page = isset($options['countdown_locations']['product_page']) ? 1 : 0;
    $category_page = isset($options['countdown_locations']['category_page']) ? 1 : 0;
    $home_page = isset($options['countdown_locations']['home_page']) ? 1 : 0;
    $other_page = isset($options['countdown_locations']['other_page']) ? 1 : 0;
?>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][shop_loop]" value="1" <?php checked($shop_loop, 1); ?>>
        <?php _e('Shop/Archive Pages', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][category_page]" value="1" <?php checked($category_page, 1); ?>>
        <?php _e('Category Pages', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][home_page]" value="1" <?php checked($home_page, 1); ?>>
        <?php _e('Home Page', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][product_page]" value="1" <?php checked($product_page, 1); ?>>
        <?php _e('Single Product Pages', 'flash-offers'); ?>
    </label><br>
    <label>
        <input type="checkbox" name="flash_offers_options[countdown_locations][other_page]" value="1" <?php checked($other_page, 1); ?>>
        <?php _e('Other Pages', 'flash-offers'); ?>
    </label>
<?php
}

// Badge style field callback function
function bogo_offer_badge_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['bogo_offer_badge_text'] ?? '';
?>
    <textarea name="flash_offers_options[bogo_offer_badge_text]" rows="2" cols="50" class="large-text"><?php echo esc_textarea($text); ?></textarea>
    <p class="description"><?php _e('Enter the BOGO offer badge text to be displayed.', 'flash-offers'); ?></p>
<?php
}

// Callback: Active Badge
function flash_offer_active_badge_text_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['active_badge_text'] ?? 'Hot Deal! 🔥';
?>
    <textarea name="flash_offers_options[active_badge_text]" rows="2" cols="50" class="large-text"><?php echo esc_textarea($text); ?></textarea>
<?php
}

// Callback: Upcoming Badge
function flash_offer_upcoming_badge_text_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['upcoming_badge_text'] ?? 'Coming Soon ⏳';
?>
    <textarea name="flash_offers_options[upcoming_badge_text]" rows="2" cols="50" class="large-text"><?php echo esc_textarea($text); ?></textarea>
<?php
}

// Callback: Special Badge
function flash_offer_special_badge_text_callback()
{
    $options = get_option('flash_offers_options');
    $text = $options['special_badge_text'] ?? 'Special Offer ✨';
?>
    <textarea name="flash_offers_options[special_badge_text]" rows="2" cols="50" class="large-text"><?php echo esc_textarea($text); ?></textarea>
<?php
}

// Callback: Selections for override Price
function flash_offer_render_price_type_field()
{
    $options = get_option('flash_offers_options');
    $value = $options['flash_override_type'];    ?>
    <select name="flash_offers_options[flash_override_type]">
        <option value="sale" <?php selected($value, 'sale'); ?>>Sale Price</option>
        <option value="regular" <?php selected($value, 'regular'); ?>>Regular Price</option>
    </select>
    <p class="description">Choose which price the offer should override.</p>
<?php
}

// Callback: Selections for BOGO override Price
function bogo_offer_render_price_type_field()
{
    $options = get_option('flash_offers_options');
    $value = $options['bogo_override_type'];    ?>
    <select name="flash_offers_options[bogo_override_type]">
        <option value="sale" <?php selected($value, 'sale'); ?>>Sale Price</option>
        <option value="regular" <?php selected($value, 'regular'); ?>>Regular Price</option>
    </select>
    <p class="description">Choose which price the offer should override.</p>
<?php
}
// Callback: Countdown Format Selection
function flash_offer_countdown_format_field_callback()
{
    $options = get_option('flash_offers_options');
    $format = $options['countdown_format'] ?? 'format1';
?>
    <select name="flash_offers_options[countdown_format]">
        <option value="format1" <?php selected($format, 'format1'); ?>>d h m s</option>
        <option value="format2" <?php selected($format, 'format2'); ?>>Day Hours Minutes Seconds</option>
        <option value="format3" <?php selected($format, 'format3'); ?>>00:00:00:00</option>
    </select>
    <p class="description">Choose how the countdown timer should be displayed.</p>
<?php
}

// Callback: Countdown Format Selection
function bogo_offer_variation_type_callback()
{
    $options = get_option('flash_offers_options');
    $format = $options['bogo_format'] ?? 'default';
?>
    <select name="flash_offers_options[bogo_format]">
        <option value="default" <?php selected($format, 'default'); ?>>default</option>
        <option value="table" <?php selected($format, 'table'); ?>>Tabel</option>
    </select>
    <p class="description">Choose how the Product Price should be displayed.</p>
<?php
}

/**
 * Register the action hook for flash offer badge
 */
add_action('woocommerce_before_shop_loop_item_title', 'display_flash_offer_badge', 9);
add_action('woocommerce_single_product_summary', 'display_flash_offer_badge', 8);
add_action('display_flash_offer_badge', 'display_flash_offer_badge');

// Display flash offer badge
function display_flash_offer_badge()
{
    global $product;
    if (!$product) return;

    $offer_data = flashoffers_get_offer_data($product);
    if (!$offer_data || empty($offer_data['badge_text'])) return;

    $locations = $offer_data['locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_home = is_front_page() && !empty($locations['home_page']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);

    if ($show_on_shop || $show_on_home || $show_on_category || $is_other_location || $show_on_single) {
        echo '<span class="flash-offer-badge ' . esc_attr($offer_data['status']) . '" style="width: fit-content; background-color:' . esc_attr($offer_data['background_color']) . '">' . esc_html($offer_data['badge_text']) . '</span>';
    }
}

// Helper function to show countdown
add_action('woocommerce_before_shop_loop_item_title', 'flashoffers_show_countdown', 9);
add_action('woocommerce_single_product_summary', 'flashoffers_show_countdown', 8);
function flashoffers_show_countdown()
{
    global $product;
    $offer_data = flashoffers_get_offer_data($product);

    if (!$offer_data || ($offer_data['status'] === 'special' && !isset($_GET['from_offer']))) {
        return;
    }

    $locations = $offer_data['countdown_locations'];
    $show_on_shop = is_shop() && !empty($locations['shop_loop']);
    $show_on_category = is_product_category() && !empty($locations['category_page']);
    $is_other_location = !is_shop() && !is_product_category() && !is_product() && !empty($locations['other_page']);
    $show_on_single = is_product() && !empty($locations['product_page']);
    $wp_timezone = wp_timezone();
    if ($show_on_shop || $show_on_category || $is_other_location || $show_on_single) {
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

// Remove sale badge if flash offer exists
add_filter('woocommerce_sale_flash', 'disable_woocommerce_sale_flash', 10, 3);
function disable_woocommerce_sale_flash($html, $post, $product)
{
    $offer_data = flashoffers_get_offer_data($product);
    if ($offer_data) {
        return ''; // remove the sale badge if flash offer exists
    }
    return $html; // default badge if no flash offer
}

add_action('woocommerce_before_shop_loop_item', 'remove_flash_sale_badge_css');
add_action('woocommerce_before_single_product_summary', 'remove_flash_sale_badge_css');

function remove_flash_sale_badge_css()
{
    global $product;
    if (!$product) return;

    // Get BOGO data
    $offer_data = flashoffers_get_offer_data($product);

    // If variation has no offer, check parent
    if (empty($offer_data) && $product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        if ($parent) {
            $offer_data = flashoffers_get_offer_data($parent);
        }
    }

    // ✅ Only if this product has an offer → hide badge for THIS product only
    if (!empty($offer_data)) {
        echo '<style>.post-' . $product->get_id() . ' .onsale { display: none !important; }</style>';
    }
}
// Color picker field callback function
function flash_offer_render_color_picker()
{
    $options = get_option('flash_offers_options');
    $badge_color = $options['badge_bg_color'] ?? '#ff4d4f';
?>
    <input type="text"
        name="flash_offers_options[badge_bg_color]" id="flash_offer_badge_bg_color" class="flash-offer-color-field" value="<?php echo esc_attr($badge_color); ?>" />
<?php
}

// Settings page
add_action('admin_menu', function () {
    add_submenu_page(
        'offers',  // parent slug (same as we used for the Offers top-level menu)
        __('Offers Settings', 'flash-offers'), // Page title
        __('Settings', 'flash-offers'),        // Menu title
        'manage_options',                      // Capability
        'flash-offers-settings',               // Menu slug
        'flash_offers_settings_page'           // Callback function
    );
});


// Settings page content
function flash_offers_settings_page()
{
?>
    <div class="wrap">
        <h1><?php _e('Flash Offers Settings', 'flash-offers'); ?></h1>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('flash_offers_settings_group');
            do_settings_sections('flash-offers-settings');
            submit_button();
            ?>
        </form>
    </div>
<?php
}
