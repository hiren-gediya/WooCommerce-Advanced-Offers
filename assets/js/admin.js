jQuery(document).ready(function ($) {

    const selectedProducts = new Map();
    let allProductsCache = new Map();

    // Initialize select2
    if ($('.wc-enhanced-select').length) {
        $('.wc-enhanced-select').select2();
    }

    /** =====================
     * Toggle Flash Offer Start Fields
     * ===================== */
    $('#offer_type').change(function () {
        if ($(this).val() === 'flash') {
            $('.flash_offer_start_fields').hide();
        } else {
            $('.flash_offer_start_fields').show();
        }
    });

    /** =====================
     * Load Products on Category Change
     * ===================== */
    $('#flash_offer_category_selector').on('change', function () {
        const selectedCategories = $(this).val() || [];
        const postId = $('#post_ID').val();

        if (selectedCategories.length === 0) {
            $('#category-product-preview').html('<p>Please select at least one category.</p>');
            return;
        }

        allProductsCache = new Map();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_products_by_categories',
                categories: selectedCategories,
                post_id: postId
            },
            beforeSend: function () {
                $('#category-product-preview').html('<p>Loading products...</p>');
            },
            success: function (response) {
                $('#category-product-preview').html(response);

                // Store all products in cache
                $('input[name="offer_products[]"]').each(function () {
                    const productId = String($(this).val());
                    const productElement = $(this).closest('label');
                    const name = productElement.find('strong').text();
                    const price = productElement.find('span').html();
                    const image = productElement.find('img').attr('src') || '';
                    allProductsCache.set(productId, { name, price, image });
                });

                // Sync with selected products
                allProductsCache.forEach((_, productId) => {
                    const isSelected = selectedProducts.has(productId);
                    $(`input[name="offer_products[]"][value="${productId}"]`).prop('checked', isSelected);
                });

                updateSelectedBox();
                updateHiddenInputs();
            }
        });
    });

    /** =====================
     * Update Hidden Inputs
     * ===================== */
    function updateHiddenInputs() {
        const container = $('#hidden-offer-products');
        container.empty();

        selectedProducts.forEach((_, id) => {
            const hiddenInput = $('<input>', {
                type: 'hidden',
                name: 'offer_products[]',
                value: id
            });
            container.append(hiddenInput);
        });
    }

    /** =====================
     * Checkbox Change Handler
     * ===================== */
    $(document).on('change', 'input[name="offer_products[]"]', function () {
        const checkbox = $(this);
        const productId = String(checkbox.val());
        const name = checkbox.closest('label').find('strong').text();

        if (checkbox.is(':checked')) {
            selectedProducts.set(productId, name);
        } else {
            selectedProducts.delete(productId);
        }

        updateSelectedBox();
        updateHiddenInputs();
    });

    /** =====================
     * Render Selected Product Box
     * ===================== */
    function updateSelectedBox() {
        const selectedBox = $('.selected-product-box');
        selectedBox.empty();

        selectedProducts.forEach((name, productId) => {
            const productDiv = $(`
                <div class="selected-product" data-product-id="${productId}"
                     style="border:1px solid #ddd; padding:10px; text-align:left; width:250px; display:flex; justify-content:space-between; align-items:center;">
                    <span>${name}</span>
                    <a href="#" class="remove-product" data-product-id="${productId}" style="color:#a00; text-decoration:none;">Remove</a>
                </div>
            `);

            selectedBox.append(productDiv);
        });
    }


    /** =====================
     * Remove Product from Box
     * ===================== */
    $(document).on('click', '.remove-product', function (e) {
        e.preventDefault();
        const productId = String($(this).data('product-id'));

        // Remove from selectedProducts Map
        selectedProducts.delete(productId);

        // Uncheck the corresponding checkbox
        $(`input[name="offer_products[]"][value="${productId}"]`).prop('checked', false);

        // Update the UI
        updateSelectedBox();
        updateHiddenInputs();
    });

    /** =====================
     * Initial Scan of Preselected Products
     * ===================== */
    $('input[name="offer_products[]"]:checked').each(function () {
        const productId = String($(this).val());
        const name = $(this).closest('label').find('strong').text();
        selectedProducts.set(productId, name);
    });

    updateSelectedBox();

    const btn = document.querySelector('.copy-shortcode');
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const text = this.getAttribute('data-clipboard-text');
            navigator.clipboard.writeText(text).then(function () {
                btn.textContent = 'Copied!';
                setTimeout(() => btn.innerHTML = 'Copy', 1500);
            });
        });
    }
});