<?php
// discount offer new price
add_filter('woocommerce_cart_item_price', 'flash_offer_cart_price_only', 10, 3);
function flash_offer_cart_price_only($price_html, $cart_item, $cart_item_key)
{
    return flashoffers_get_price_html($cart_item['data']);
}

