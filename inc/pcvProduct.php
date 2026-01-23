<?php



add_action('woocommerce_product_options_general_product_data', 'add_pcv_fields');
function add_pcv_fields()
{
    woocommerce_wp_checkbox(array(
        'id' => '_pcv_product',
        'label' => __('Produkt parapet pcv', 'textdomain'),
        'description' => __('Produkt parapet pcv', 'textdomain')
    ));
}
add_action('woocommerce_process_product_meta', 'save_pcv_fields');
function save_pcv_fields($post_id)
{
    $checkbox = isset($_POST['_pcv_product']) ? 'yes' : 'no';
    update_post_meta($post_id, '_pcv_product', $checkbox);
}
function pcv_fields()
{
    if (!is_product())
        return;
    global $product;
    $is_area = get_post_meta($product->get_id(), '_pcv_product', true) === 'yes';
    if (!$is_area)
        return;

    $attributes = getCartItemData();
    $custom_length = isset($attributes['custom_length']) ? $attributes['custom_length'] : '1000';
    $custom_width = isset($attributes['custom_width']) ? $attributes['custom_width'] : '';

    $min_width = get_post_meta($product->get_id(), '_min_width', true);
    $max_width = get_post_meta($product->get_id(), '_max_width', true);
    $width_fixed = ($min_width && $max_width && $min_width == $max_width);



    if (get_post_meta($product->get_id(), '_pcv_product', true) === 'yes') {
        ?>
        <style>
            .variations {
                display: none !important;
            }

            .woocommerce-variation-price {
                display: none !important;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                if ($('.variations select').length) {
                    $('.variations select').val($('.variations select option:eq(1)').val()).trigger('change');
                    $('.variations select').prop('disabled', true);
                }

                $('#custom_width').on('input', function () {
                    let customWidth = parseFloat($(this).val());
                    let selectValues = [];
                    let $select = $('.variations select');

                    $select.find('option').each(function () {
                        selectValues.push($(this).val());
                    });

                    let smallestGreaterThan = Infinity;
                    selectValues.forEach(valStr => {
                        let val = parseFloat(valStr);
                        if (val >= customWidth) {
                            if (val < smallestGreaterThan) {
                                smallestGreaterThan = val;
                            }
                        }
                    });
                    if (!isFinite(smallestGreaterThan)) {
                        return;
                    }

                    productData.variation = smallestGreaterThan;

                    $select.val(smallestGreaterThan).trigger('change');
                    window.calculatePrice();
                });
            });
        </script>

        <?php

        echo '<input type="hidden" id="cutting_required" name="cutting_required" value="0">';

        echo '<div style="margin-bottom: 20px;">';
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';

        echo '<div style="flex: 1; min-width: 150px;">';
        echo '<label for="custom_length" style="display: block; font-weight: 600; margin-bottom: 4px;">Długość (mm):</label>';
        echo '<input type="number" id="custom_length" value="' . $custom_length . '" name="custom_length" required style="width: 100%;">';
        echo '<small id="length_error" style="color:red; display:block; margin-top: 4px;"></small>';
        echo '</div>';

        echo '<div style="flex: 1; min-width: 150px;">';
        echo '<label for="custom_width" style="display: block; font-weight: 600; margin-bottom: 4px;">Szerokość (mm):</label>';
        echo '<input type="number" id="custom_width" value="' . $custom_width . '" name="custom_width" required style="width: 100%;">';
        echo '<small id="width_error" style="color:red; display:block; margin-top: 4px;"></small>';
        echo '</div>';

        echo '</div>';

        echo '<p id="delivery_info"></p>';
        echo '<p style="margin-top: 10px;"><strong></strong><span id="calculated_price"></span></p>';
        echo '<p id="price_info" style="color:red; font-weight:bold"></p>';

        echo '</div>';
    }
}

//add_action('woocommerce_before_add_to_cart_form', 'linear_product_fields', 1);
add_action('woocommerce_before_add_to_cart_button', 'pcv_fields');




add_action('wp_footer', 'force_subscription_variant');
function force_subscription_variant()
{
    if (!is_product())
        return;
    ?>


    <?php
}

add_filter('woocommerce_add_to_cart_validation', 'change_variation_id_before_add_to_cart', 0, 5);
function change_variation_id_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array())
{
    if (get_post_meta($product_id, '_pcv_product', true) !== 'yes')
        return $passed;

    $custom_width = (float) ($_POST['custom_width'] ?? 100);
    $product = wc_get_product($product_id);

    if ($variation_id > 0) {
        $variation = wc_get_product($variation_id);
        $variation_width = (float) ($variation->get_attribute('wymiar') ?? 0);

        if ($variation_width < 0 || $variation_width < $custom_width) {
            wc_add_notice(sprintf('Wybrany wariant ma mniejszy wymiar (%s) niż podana szerokość (%s).', $variation_width, $custom_width), 'error');
            return false;
        }
        $min_width = get_post_meta($product_id, '_min_width', true);
        if ($custom_width < $min_width) {
            wc_add_notice(sprintf('Produkt nie spełnia minimalnej szerokości '), 'error');
            return false;
        }
    }

    return $passed;
}



add_action('wp_footer', 'add_variation_dimension_data');
function add_variation_dimension_data()
{
    if (!is_product())
        return;

    global $product;
    if (!$product->is_type('variable'))
        return;

    $variations = $product->get_available_variations();
    $variation_dimensions = [];

    foreach ($variations as $variation) {
        $variation_id = $variation['variation_id'];
        $variation_obj = wc_get_product($variation_id);

        $dimension = $variation_obj->get_attribute('wymiar');


        $variation_dimensions[$dimension] = $variation_obj->get_price();
    }

    echo '<script>
        window.wcVariationDimensions = ' . json_encode($variation_dimensions) . ';
        window.pcvCuttingCost = ' . floatval( get_option('pcv_cutting_cost', 15) ) . ';
    </script>';
}

add_filter('woocommerce_get_cart_item_from_session', function ($item, $values) {
    if (isset($values['cutting_required'])) {
        $item['cutting_required'] = $values['cutting_required'];
    }
    return $item;
}, 20, 2);