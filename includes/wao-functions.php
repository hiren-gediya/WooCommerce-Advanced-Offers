<?php
// In flash-functions.php
register_activation_hook(__FILE__, 'flashoffers_activate_plugin');

// Activation hook function
function flashoffers_activate_plugin()
{
    // Create tables immediately
    flashoffers_create_database_tables();

    // Schedule a verification for next page load
    update_option('flashoffers_check_tables', 1);
}

add_action('init', 'flashoffers_check_table_creation');

// Check if tables need to be created
function flashoffers_check_table_creation()
{
    if (get_option('flashoffers_check_tables')) {
        flashoffers_create_database_tables();
        delete_option('flashoffers_check_tables');
    }
}

// create tables in database
function flashoffers_create_database_tables()
{
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Check if we need to migrate existing flash_offer_categories table
    flashoffers_migrate_categories_table();

    $tables = [
        'flash_offers' => "
            CREATE TABLE {$wpdb->prefix}flash_offers (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) NOT NULL,
                offer_type varchar(50) NOT NULL DEFAULT 'flash',
                start_date datetime DEFAULT NULL,
                end_date datetime NOT NULL,
                discount decimal(5,2) NOT NULL,
                PRIMARY KEY (id),
                KEY post_id (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'flash_offer_products' => "
            CREATE TABLE {$wpdb->prefix}flash_offer_products (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                offer_id bigint(20) NOT NULL,
                product_id bigint(20) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY offer_product (offer_id,product_id),
                KEY product_id (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'flash_offer_categories' => "
            CREATE TABLE {$wpdb->prefix}flash_offer_categories (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                offer_id bigint(20) NOT NULL,
                category_id bigint(20) NOT NULL,
                product_id bigint(20) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY offer_category_product (offer_id,category_id,product_id),
                KEY product_id (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        'bogo_offers' => "
            CREATE TABLE {$wpdb->prefix}bogo_offers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            offer_type varchar(20) NOT NULL DEFAULT 'buy_x_get_y',
            buy_product_id bigint(20) NOT NULL,
            get_product_id bigint(20) NOT NULL,
            buy_quantity int(11) NOT NULL DEFAULT 1,
            get_quantity int(11) NOT NULL DEFAULT 1,
            discount decimal(5,2) NOT NULL,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_bogo (post_id, buy_product_id, get_product_id),
            KEY buy_product_id (buy_product_id),
            KEY get_product_id (get_product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($tables as $table_name => $sql) {
        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table_name}'") != $wpdb->prefix . $table_name) {
            $result = dbDelta($sql);

            // Table creation handled by dbDelta
        }
    }
}

// Migrate existing flash_offer_categories table to include product_id
function flashoffers_migrate_categories_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'flash_offer_categories';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return; // Table doesn't exist yet, will be created fresh
    }

    // Check if product_id column already exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'product_id'");

    if (empty($column_exists)) {
        // Add product_id column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN product_id bigint(20) NOT NULL DEFAULT 0 AFTER category_id");

        // Add index for product_id
        $wpdb->query("ALTER TABLE $table_name ADD KEY product_id (product_id)");

        // Drop old unique key if it exists
        $wpdb->query("ALTER TABLE $table_name DROP INDEX offer_category");

        // Add new unique key to include product_id
        $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY offer_category_product (offer_id,category_id,product_id)");

        // Clear existing data since it won't have product_id values
        $wpdb->query("DELETE FROM $table_name WHERE product_id = 0");
    }
}

/**
 * Get the primary category ID for a product
 * Priority: 1. Yoast SEO primary category, 2. First assigned category
 */
function flashoffers_get_product_primary_category($product_id)
{
    // Method 1: Check if Yoast SEO primary category is set
    if (class_exists('WPSEO_Primary_Term')) {
        $primary_term = new WPSEO_Primary_Term('product_cat', $product_id);
        $primary_category_id = $primary_term->get_primary_term();

        if ($primary_category_id && $primary_category_id > 0) {
            return (int)$primary_category_id;
        }
    }

    // Method 2: Check for RankMath primary category
    $rankmath_primary = get_post_meta($product_id, 'rank_math_primary_product_cat', true);
    if ($rankmath_primary && $rankmath_primary > 0) {
        return (int)$rankmath_primary;
    }

    // Method 3: Get the first assigned category (fallback)
    $categories = wp_get_post_terms($product_id, 'product_cat', [
        'fields' => 'ids',
        'orderby' => 'term_order',
        'order' => 'ASC',
        'number' => 1
    ]);

    if (!is_wp_error($categories) && !empty($categories)) {
        $primary_category_id = (int)$categories[0];
        return $primary_category_id;
    }

    return 0;
}

/**
 * Consolidated table verification function
 * Replaces flashoffers_verify_tables_exist() and flashoffers_tables_exist()
 */
function flashoffers_ensure_tables_exist($force_check = false)
{
    global $wpdb;

    static $tables_verified = null;

    // Skip if already verified and not forcing
    if ($tables_verified === true && !$force_check) {
        return true;
    }

    $required_tables = [
        'flash_offers',
        'flash_offer_products',
        'flash_offer_categories',
        'bogo_offers'
    ];

    $missing_tables = [];

    foreach ($required_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'") != $wpdb->prefix . $table) {
            $missing_tables[] = $table;
        }
    }

    // If tables are missing, attempt to create them
    if (!empty($missing_tables)) {
        flashoffers_create_database_tables();

        // Verify creation was successful
        foreach ($missing_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'") != $wpdb->prefix . $table) {
                $tables_verified = false;
                return false;
            }
        }
    }

    $tables_verified = true;
    return true;
}

// Legacy function for backward compatibility
function flashoffers_tables_exist()
{
    return flashoffers_ensure_tables_exist();
}

// In flash-offers-plugin.php
add_action('plugins_loaded', 'flashoffers_emergency_table_check');

// Simplified emergency table check using consolidated function
function flashoffers_emergency_table_check()
{
    // Use the consolidated function with force check
    if (!flashoffers_ensure_tables_exist(true)) {
        if (is_admin()) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Flash Offers:</strong> Database tables could not be created automatically. ';
                echo 'Please deactivate and reactivate the plugin.';
                echo '</p></div>';
            });
        }
    }
}

function flashoffers_uninstall()
{
    // Only run if explicitly requested
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }

    global $wpdb;

    // 1. FIRST: Delete all flash_offer posts and their meta
    $posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'flash_offer'");

    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true); // true = force delete (bypass trash)
    }

    // 2. Delete plugin tables
    $tables = [
        'flash_offers',
        'flash_offer_products',
        'flash_offer_categories',
        'bogo_offers'
    ];

    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    // 3. Delete options
    delete_option('flash_offers_options');
    delete_option('flashoffers_check_tables');

    // 4. Clean up any remaining postmeta (optional but recommended)
    $wpdb->query("
        DELETE FROM {$wpdb->postmeta} 
        WHERE post_id IN (
            SELECT ID FROM {$wpdb->posts} WHERE post_type = 'flash_offer'
        )
    ");
}
add_action('admin_menu', function () {
    add_menu_page(
        'Offers',          // Page title
        'Offers',          // Menu title
        'manage_options',  // Capability
        'offers',          // Menu slug (used as parent slug above)
        '',                // No callback, because CPTs handle their own screens
        'dashicons-clock', // Icon
        25                 // Position
    );
});

// Register Flash Offer post type
add_action('init', function () {
    register_post_type('flash_offer', [
        'labels' => [
            'name' => 'Flash Offers',
            'singular_name' => 'Flash Offer',
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-clock', // will be ignored since we're grouping
        'show_in_menu' => 'offers',       // attach under Offers menu
    ]);
});

// Register BOGO Offer post type
add_action('init', function () {
    register_post_type('bogo_offer', [
        'labels' => [
            'name' => 'BOGO Offers',
            'singular_name' => 'BOGO Offer',
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-clock',
        'show_in_menu' => 'offers',       // same parent
    ]);
});


// Register settings
add_action('admin_init', function () {
    register_setting('flash_offers_settings_group', 'flash_offers_options');

    add_settings_section(
        'flash_offers_main_section',
        '',
        null,
        'flash-offers-settings'
    );

    add_settings_field(
        'flash_offer_message',
        __('Flash Offer Message', 'flash-offers'),
        'flash_offer_message_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Display locations
    add_settings_field(
        'flash_offer_locations',
        __('Badge Display Locations', 'flash-offers'),
        'flash_offer_locations_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Add countdown locations to settings
    add_settings_field(
        'flash_offer_countdown_locations',
        __('Countdown Display Locations', 'flash-offers'),
        'flash_offer_countdown_locations_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Badge style
    add_settings_field(
        'flash_offer_active_badge_text',
        __('Active Badge Text', 'flash-offers'),
        'flash_offer_active_badge_text_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_upcoming_badge_text',
        __('Upcoming Badge Text', 'flash-offers'),
        'flash_offer_upcoming_badge_text_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_special_badge_text',
        __('Special Badge Text', 'flash-offers'),
        'flash_offer_special_badge_text_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_badge_bg_color',
        'Badge Background Color',
        'flash_offer_render_color_picker',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    add_settings_field(
        'flash_offer_override_price_type',
        'Override Price Type For Flash Offers',
        'flash_offer_render_price_type_field',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

    // Add countdown format selection field
    add_settings_field(
        'flash_offer_countdown_format',
        __('Countdown Display Format', 'flash-offers'),
        'flash_offer_countdown_format_field_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

     add_settings_field(
        'bogo_offer_variation_type',
        __('BOGO Offer Modal Format Type', 'flash-offers'),
        'bogo_offer_variation_type_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );

     add_settings_field(
        'bogo_offer_badge',
        __('BOGO Offer Badge Text', 'flash-offers'),
        'bogo_offer_badge_callback',
        'flash-offers-settings',
        'flash_offers_main_section'
    );
    add_settings_field(
        'bogo_offer_override_price_type',
        'Override Price Type For BOGO Offers',
        'bogo_offer_render_price_type_field',
        'flash-offers-settings',
        'flash_offers_main_section'
    );
});


function flashoffers_get_offer_data($product)
{
    global $wpdb;

    if (!$product || !is_a($product, 'WC_Product')) {
        return false;
    }

    $product_id = $product->get_id();
    if ($product->is_type('variation')) {
        $product_id = $product->get_parent_id();
    }

    // Verify tables exist
    if (
        !$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}flash_offers'") ||
        !$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}flash_offer_products'")
    ) {
        return false;
    }

    // Get offer assigned to this product
    $offer = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT o.* FROM {$wpdb->prefix}flash_offers o
             JOIN {$wpdb->prefix}flash_offer_products p ON o.id = p.offer_id
             WHERE p.product_id = %d
             AND o.end_date > %s
             ORDER BY o.discount DESC
             LIMIT 1",
            $product_id,
            current_time('mysql')
        )
    );

    if (!$offer) return false;

    // Validate 'special' offers must come from URL
    if ($offer->offer_type === 'special') {
        if (!isset($_GET['from_offer']) || (int)$_GET['from_offer'] !== (int)$offer->post_id) {
            return false;
        }

        $is_in_offer = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}flash_offer_products 
             WHERE offer_id = %d AND product_id = %d",
            $offer->id,
            $product_id
        ));

        if (!$is_in_offer) return false;
    }

    // Get settings once
    $options = get_option('flash_offers_options');
    $bg_color = $options['badge_bg_color'] ?? '#ff4d4f';
    $locations = $options['locations'] ?? [];
    $countdown_locations = $options['countdown_locations'] ?? [];
    $flash_override_type = $options['flash_override_type'] ?? 'sale';
    $countdown_format = $options['countdown_format'] ?? 'format1';
    $bogo_format = $options['bogo_format'] ?? 'defualt';

    // Badge texts
    $active_badge_text   = $options['active_badge_text'] ?? '';
    $upcoming_badge_text = $options['upcoming_badge_text'] ?? '';
    $special_badge_text  = $options['special_badge_text'] ?? '';

    // Time checks
    $now        = current_time('timestamp');
    $start_time = strtotime($offer->start_date);
    $end_time   = strtotime($offer->end_date);

    // Determine status
    $status = 'expired';
    if ($now < $start_time) {
        $status = 'upcoming';
    } elseif ($now <= $end_time) {
        $status = 'active';
    }

    if ($status === 'expired') {
        return false;
    }

    // Build badge text
    $discount   = (float) $offer->discount;
    $badge_text = '';
    if ($status === 'upcoming' && $now < $start_time) {
        $badge_text = "{$upcoming_badge_text} {$discount}% Off";
    } elseif ($status === 'active' && $offer->offer_type === 'special') {
        $badge_text = "{$special_badge_text} {$discount}% Off";
    } elseif ($status === 'active' && $now >= $start_time && $now <= $end_time) {
        $badge_text = "{$active_badge_text} {$discount}% Off";
    }

    // Return everything in one array
    return [
        'status'             => $status,
        'offer_type'         => $offer->offer_type,
        'start'              => $offer->start_date,
        'end'                => $offer->end_date,
        'discount'           => $discount,
        'background_color'   => $bg_color,
        'badge_text'         => $badge_text,
        'locations'          => $locations,
        'countdown_locations' => $countdown_locations,
        'flash_override_type'      => $flash_override_type,
        'countdown_format'   => $countdown_format,
        'bogo_format'       => $bogo_format,
    ];
}


// Calculate discount amount
function flashoffers_calculate_discount($regular_price, $discount)
{
    if ($regular_price <= 0 || $discount <= 0) return $regular_price;
    return round($regular_price - ($regular_price * ($discount / 100)), wc_get_price_decimals());
}

// Apply flash offer discount to all price types
add_filter('woocommerce_product_get_price', 'flashoffers_discount_price', 20, 2);
add_filter('woocommerce_product_get_sale_price', 'flashoffers_discount_price', 20, 2);
add_filter('woocommerce_product_variation_get_price', 'flashoffers_discount_price', 20, 2);
add_filter('woocommerce_product_variation_get_sale_price', 'flashoffers_discount_price', 20, 2);

// Apply flash offer discount to sale price
function flashoffers_discount_price($price, $product)
{
    $offer_data = flashoffers_get_offer_data($product);
    if (!$offer_data || $offer_data['status'] !== 'active') return $price;
    $flash_override_type = $offer_data['flash_override_type'];

    $data = $product->get_data();
    $sale_price = $data['sale_price'];
    $regular_price = $data['regular_price'];
    $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;
    if ($base_price <= 0) return $price;

    return flashoffers_calculate_discount($base_price, $offer_data['discount']);
}

add_filter('woocommerce_product_variation_get_regular_price', 'flashoffers_discount_regular_price', 20, 2);
add_filter('woocommerce_product_get_regular_price', 'flashoffers_discount_regular_price', 20, 2);
// Apply flash offer discount to regular price
function flashoffers_discount_regular_price($price, $product)
{
    return $price;
}

// Get price HTML for a product, including flash offer discount
function flashoffers_get_price_html($product)
{
    $offer_data = flashoffers_get_offer_data($product);

    if (!$offer_data || $offer_data['status'] !== 'active') {
        return $product->get_price_html();
    }
    $flash_override_type = $offer_data['flash_override_type'] ?? 'sale';
    $data = $product->get_data();
    $sale_price = $data['sale_price'];
    $regular_price = $data['regular_price'];
    $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;
    $discounted_price = $product->get_price();

    if ($base_price && $discounted_price < $base_price) {
        return '<del>' . wc_price($base_price) . '</del> <ins>' . wc_price($discounted_price) . '</ins>';
    }

    return $product->get_price_html();
}



// Helper function to get current offer ID
function flashoffers_get_current_offer_id()
{
    if (isset($_GET['from_offer'])) {
        return (int)$_GET['from_offer'];
    }

    // Check if in cart context
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['from_offer'])) {
            return $cart_item['from_offer'];
        }
    }

    return false;
}


add_filter('woocommerce_get_price_html', 'flashoffers_show_discounted_price_html', 20, 2);

// Helper function to show discounted price HTML
function flashoffers_show_discounted_price_html($price_html, $product)
{
    if (!$product->is_type(['simple', 'variable', 'variation'])) {
        return $price_html;
    }
    $offer_data = flashoffers_get_offer_data($product);
    if (!$offer_data || ($offer_data['status'] === 'special' && !isset($_GET['from_offer']))) {
        return $price_html;
    }

    $flash_override_type = $offer_data['flash_override_type'] ?? 'sale';
    // 🔷 ACTIVE — Show discounted price
    if ($offer_data['status'] === 'active') {
        if ($product->is_type('variable')) {
            $reg_prices = [];
            $disc_prices = [];

            foreach ($product->get_available_variations() as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                if (!$variation_obj) continue;

                $data = $variation_obj->get_data();
                $sale_price = (float) $data['sale_price'];
                $regular_price = (float) $data['regular_price'];

                $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;

                if ($base_price <= 0) continue;

                $reg_prices[] = $base_price;
                $disc_prices[] = flashoffers_calculate_discount($base_price, $offer_data['discount']);
            }

            if (!empty($reg_prices) && !empty($disc_prices)) {
                $min_reg = min($reg_prices);
                $max_reg = max($reg_prices);
                $min_disc = min($disc_prices);
                $max_disc = max($disc_prices);

                $price_html = '<span class="price-regular"><del>' . wc_price($min_reg) . ' – ' . wc_price($max_reg) . '</del></span> ';
                $price_html .= '<span class="price-discount"><ins>' . wc_price($min_disc) . ' – ' . wc_price($max_disc) . '</ins></span>';
            }
        } else {
            $data = $product->get_data();
            $sale_price = (float) $data['sale_price'];
            $regular_price = (float) $data['regular_price'];

            $base_price = ($flash_override_type === 'sale' && $sale_price > 0) ? $sale_price : $regular_price;

            if ($base_price > 0) {
                $discounted_price = flashoffers_calculate_discount($base_price, $offer_data['discount']);

                $price_html = '<span class="price-regular"><del>' . wc_price($base_price) . '</del></span> ';
                $price_html .= '<span class="price-discount"><ins>' . wc_price($discounted_price) . '</ins></span>';
            }
        }
    }

    return $price_html;
}


// Add hidden field to maintain offer parameter
add_action('woocommerce_before_add_to_cart_button', function () {
    if (isset($_GET['from_offer'])) {
        echo '<input type="hidden" name="from_offer" value="' . esc_attr((int)$_GET['from_offer']) . '">';
    }
});

// bogo offer

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('wc-add-to-cart-variation'); 
});

