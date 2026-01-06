<?php
// Display Flash Offer Discount with price on product
add_filter('woocommerce_checkout_cart_item_quantity', 'flash_offer_checkout_price_html', 10, 3);
function flash_offer_checkout_price_html($html, $cart_item, $cart_item_key)
{
    $price = flashoffers_get_price_html($cart_item['data']);
    return $price . ' &times; ' . $cart_item['quantity'];
}