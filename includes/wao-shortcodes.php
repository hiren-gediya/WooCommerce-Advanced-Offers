<?php
// Shortcode to display special offer products
add_shortcode('flash_special_offer', 'display_special_offer_products');

function display_special_offer_products($atts)
{
    $atts = shortcode_atts([
        'id' => 0, // Special Offer post ID
        'columns' => 3,
        'limit' => -1,
    ], $atts, 'flash_special_offer');

    $offer_id = (int)$atts['id'];
    if (!$offer_id) return '<p class="flash-offer-error">Invalid offer ID.</p>';

    global $wpdb;

    // Get offer info
    $offer = $wpdb->get_row($wpdb->prepare(
        "SELECT id, discount, end_date FROM {$wpdb->prefix}flash_offers 
         WHERE post_id = %d AND offer_type = %s",
        $offer_id,
        'special'
    ));

    if (!$offer) return '<p class="flash-offer-error">Invalid or missing special offer.</p>';

    // Check if offer is expired
    if (strtotime($offer->end_date) < current_time('timestamp')) {
        return '<p class="flash-offer-error">This special offer has expired.</p>';
    }

    // Get product IDs linked to this offer
    $product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT product_id FROM {$wpdb->prefix}flash_offer_products WHERE offer_id = %d",
        $offer->id
    ));

    if (empty($product_ids)) return '<p class="flash-offer-notice">No products found in this offer.</p>';

    // Apply limit if set
    if ($atts['limit'] > 0) {
        $product_ids = array_slice($product_ids, 0, $atts['limit']);
    }

    // WP Query to get products
    $args = [
        'post_type' => 'product',
        'post__in' => $product_ids,
        'posts_per_page' => -1,
        'orderby' => 'post__in',
    ];
    $products = new WP_Query($args);

    if (!$products->have_posts()) return '<p class="flash-offer-notice">No valid products found.</p>';

    // Force discount logic to work by setting `from_offer` param
    $_GET['from_offer'] = $offer_id;

    // Count products
    $product_count = $products->post_count;
    $slider_class = $product_count > 4 ? 'flash-offer-slider-enabled' : '';

    // Output
    ob_start();
?>

    <div class="woocommerce flash-special-offer-container <?php echo esc_attr($slider_class); ?>">
        <ul class="products columns-<?php //echo esc_attr($atts['columns']); 
                                    ?>">
            <?php while ($products->have_posts()) : $products->the_post(); ?>
                <?php
                global $product;
                $product_url = add_query_arg('from_offer', $offer_id, get_permalink($product->get_id()));
                ?>

                <li class="product flash-special-offer-product">
                    <a href="<?php echo esc_url($product_url); ?>" class="woocommerce-LoopProduct-link">
                        <?php
                        // Temporarily modify the product link
                        add_filter('post_type_link', function ($link, $post) use ($offer_id) {
                            if ($post->post_type === 'product') {
                                return add_query_arg('from_offer', $offer_id, $link);
                            }
                            return $link;
                        }, 10, 2);

                        // Display product content
                        do_action('woocommerce_before_shop_loop_item');
                        do_action('woocommerce_before_shop_loop_item_title');
                        do_action('woocommerce_shop_loop_item_title');
                        do_action('woocommerce_after_shop_loop_item_title');

                        // Remove the filter immediately
                        remove_filter('post_type_link', function ($link, $post) use ($offer_id) {
                            if ($post->post_type === 'product') {
                                return add_query_arg('from_offer', $offer_id, $link);
                            }
                            return $link;
                        }, 10, 2);
                        ?>
                    </a>

                    <div class="flash-special-offer-actions">
                        <?php
                        // Add to cart button with offer parameter
                        $add_to_cart_url = add_query_arg('from_offer', $offer_id, $product->add_to_cart_url());
                        echo sprintf(
                            '<a href="%s" data-quantity="1" class="button product_type_%s add_to_cart_button" data-product_id="%s" data-product_sku="%s">%s</a>',
                            esc_url($add_to_cart_url),
                            esc_attr($product->get_type()),
                            esc_attr($product->get_id()),
                            esc_attr($product->get_sku()),
                            esc_html($product->add_to_cart_text())
                        );
                        ?>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <?php
    add_action('wp_enqueue_scripts', function () {
        wp_add_inline_style('slick-css', '
        .flash-special-offer-container.flash-offer-slider-enabled ul.products.slick-slider {
            display: flex !important;
            gap: 20px;
            justify-content: start;
        }

        .flash-special-offer-container.flash-offer-slider-enabled ul.products.slick-slider li.product {
            float: none;
            margin: 0 !important;
            width: 100% !important;
            flex: 0 0 auto;
        }

        .flash-special-offer-container ul.products li.product {
            padding: 10px;
            box-sizing: border-box;
        }
    ');
    });
    ?>
    <script>
        jQuery(document).ready(function($) {
            // Redirect fixes
            $(document).on('click', '.flash-special-offer-product .add_to_cart_button', function(e) {
                var url = $(this).attr('href');
                if (url.indexOf('from_offer=') === -1) {
                    e.preventDefault();
                    window.location.href = url + '&from_offer=<?php echo $offer_id; ?>';
                }
            });

            $(document).on('click', '.flash-special-offer-product a.woocommerce-LoopProduct-link', function(e) {
                var url = $(this).attr('href');
                if (url.indexOf('from_offer=') === -1) {
                    e.preventDefault();
                    window.location.href = url + '?from_offer=<?php echo $offer_id; ?>';
                }
            });

            // Auto slider if more than 3 products
            if ($('.flash-special-offer-container.flash-offer-slider-enabled').length) {
                $('.flash-special-offer-container ul.products').slick({
                    slidesToShow: <?php echo (int)$atts['columns']; ?>,
                    slidesToScroll: 1,
                    autoplay: true,
                    autoplaySpeed: 1500,
                    arrows: false,
                    dots: false,
                    infinite: true,
                    responsive: [{
                            breakpoint: 768,
                            settings: {
                                slidesToShow: 2
                            }
                        },
                        {
                            breakpoint: 480,
                            settings: {
                                slidesToShow: 1
                            }
                        }
                    ]
                });
            }
        });
    </script>

<?php
    wp_reset_postdata();
    return ob_get_clean();
}

