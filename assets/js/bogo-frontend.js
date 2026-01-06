jQuery(document).ready(function($) {
    // Insert BOGO modal HTML into body
    const bogoModalHTML = `
        <div id="bogo-offer-modal" style="display:none;">
            <div class="bogo-modal-content">
                <span id="bogo-close-modal">✖</span>
                <div id="bogo-offer-modal-body"></div>
            </div>
        </div>
    `;
    $('body').append(bogoModalHTML);

    // --- Open BOGO modal ---
    $(document).on('click', '.bogo-popup-btn', function(e) {
        e.preventDefault();

        var productId = $(this).data('product-id');
        var quantity = $(this).data('quantity') || 1;
        var buyProductId = $(this).data('buy-product-id');
        var buyQuantity = $(this).data('buy-quantity');
        var getProductId = $(this).data('get-product-id');
        var getQuantity = $(this).data('get-quantity');
        var offerType = $(this).data('offer-type');
        var discount = $(this).data('discount');

        $.ajax({
            url: bogo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'load_bogo_product_form',
                product_id: productId,
                quantity: quantity,
                buy_product_id: buyProductId,
                buy_quantity: buyQuantity,
                get_product_id: getProductId,
                get_quantity: getQuantity,
                offer_type: offerType,
                discount: discount
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#bogo-offer-modal-body').html(response.data.html);
                    $('#bogo-offer-modal').fadeIn(300);

                    // Initialize variation forms for both buy and get products
                    $('.bogo-buy-form.variations_form, .bogo-get-form.variations_form').each(function() {
                        $(this).wc_variation_form();
                    });

                    // Set offer-specific quantity manually using response data (visible for user adjustment)
                    var buyQtyInput = $('.bogo-buy-form input[name="quantity"]');
                    var getQtyInput = $('.bogo-get-form input[name="quantity"]');
                    var offerType = response.data.offer_type;
                    var buyQuantity = response.data.buy_quantity;
                    var getQuantity = response.data.get_quantity;

                    if (offerType === 'buy_one_get_one') {
                        // For BOGO 1+1, set buy form quantity to 2 (buy 1 + get 1)
                        buyQtyInput.val(2);
                    } else {
                        // For buy x get y, set buy and get quantities accordingly
                        buyQtyInput.val(buyQuantity);
                        if (getQtyInput.length) {
                            getQtyInput.val(getQuantity);
                        }
                    }

                    // Re-initialize variation form after setting quantity to ensure functionality
                    setTimeout(function() {
                        $('.bogo-buy-form.variations_form, .bogo-get-form.variations_form').each(function() {
                            $(this).wc_variation_form();
                        });
                    }, 100);
                } else {
                    alert(response.data?.message || 'Failed to load BOGO product.');
                }
            }
        });
    });

    // --- Close modal ---
    $(document).on('click', '#bogo-close-modal', function() {
        $('#bogo-offer-modal').addClass('hide');
        setTimeout(function() {
            $('#bogo-offer-modal').hide().removeClass('hide');
        }, 300);
    });

    // Log WooCommerce cart fragments refresh event for debugging
    $(document.body).on('wc_fragments_refreshed', function() {
        console.log('WooCommerce cart fragments refreshed');
    });

    // --- Handle add to cart for default WooCommerce forms in modal (both simple and variable) ---
    $(document).on('submit', '#bogo-offer-modal form', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $form = $(this);
        var $button = $form.find('.single_add_to_cart_button');
        var is_variable = $form.hasClass('variations_form');
        var productId = $form.find('input[name="add-to-cart"]').val();
        var quantity = parseInt($form.find('input[name="quantity"]').val()) || 1;

        if (!productId) {
            alert('Invalid product.');
            return;
        }

        var variation_id = 0;
        if (is_variable) {
            variation_id = $form.find('input[name="variation_id"]').val();
            if (!variation_id || variation_id === '0') {
                alert('Please select a product variation.');
                return;
            }
        }

        $button.addClass('loading');

        $.ajax({
            url: bogo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bogo_add_to_cart',
                product_id: productId,
                variation_id: variation_id,
                quantity: quantity,
                nonce: bogo_ajax.nonce
            },
            success: function(response) {
                $button.removeClass('loading');
                if (response.success) {
                    alert('Product added to cart successfully!');
                    location.reload();
                } else {
                    alert(response.data.message || 'Something went wrong');
                }
            },
            error: function() {
                $button.removeClass('loading');
                alert('AJAX error occurred.');
            }
        });
    });

    // Re-initialize variation form when quantity changes to maintain dropdown functionality
    $(document).on('change', '.bogo-buy-form input[name="quantity"], .bogo-get-form input[name="quantity"]', function() {
        var $form = $(this).closest('form');
        if ($form.hasClass('variations_form')) {
            setTimeout(function() {
                $form.wc_variation_form();
            }, 50);
        }
    });

    // --- Add to cart button click (for table format) ---
    $(document).on('click', '.bogo-add-to-cart', function(e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.prop('disabled')) return;
        $button.addClass('loading');

        var productId = $button.data('product-id');
        var variationId = $button.data('variation-id') || 0;
        var productType = $button.data('product-type');

        var row = $button.closest('tr');
        var userQty = parseInt(row.find('.qty').val()) || 2;

        console.log('Add to cart clicked:', { productId, variationId, userQty, productType });

        $.ajax({
            url: bogo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bogo_add_to_cart',
                product_id: productId,
                variation_id: variationId,
                quantity: userQty,
                nonce: bogo_ajax.nonce
            },
            success: function(response) {
                $button.removeClass('loading');
                if (response.success) {
                    $('#bogo-offer-modal').hide();
                    $(document.body).trigger('wc_fragment_refresh');
                    alert('Product added to cart successfully!');
                    // Removed page reload to prevent shaking
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert(response.data.message || 'Something went wrong');
                }
            },
            error: function() {
                $button.removeClass('loading');
                alert('AJAX error occurred.');
            }
        });
    });
});
