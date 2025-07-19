<?php


add_action('woocommerce_product_options_general_product_data', 'add_linear_meter_pricing_field');
function add_linear_meter_pricing_field()
{
    woocommerce_wp_checkbox(array(
        'id' => '_linear_meter_pricing',
        'label' => __('Liczenie ceny za metr bieżący', 'textdomain'),
        'description' => __('Zaznacz, aby włączyć obliczenia za metr bieżący', 'textdomain')
    ));
}
add_action('woocommerce_process_product_meta', 'save_linear_meter_pricing_field');
function save_linear_meter_pricing_field($post_id)
{
    $checkbox = isset($_POST['_linear_meter_pricing']) ? 'yes' : 'no';
    update_post_meta($post_id, '_linear_meter_pricing', $checkbox);
}
function linear_product_fields()
{



    if (!is_product())
        return;
    global $product;
    $is_area = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
    if (!$is_area)
        return;

    $attributes = getCartItemData();
    $custom_length = isset($attributes['custom_length']) ? $attributes['custom_length'] : '1000';
    $custom_width = isset($attributes['custom_width']) ? $attributes['custom_width'] : '100';

    $min_width = get_post_meta($product->get_id(), '_min_width', true);
    $max_width = get_post_meta($product->get_id(), '_max_width', true);
    $width_fixed = ($min_width && $max_width && $min_width == $max_width);


    if (get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes') {

        echo '<div style="margin-bottom: 20px;">';

        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';



        echo '<div style="flex: 1; min-width: 150px;">';
        echo '<label for="custom_width" style="display: block; font-weight: 600; margin-bottom: 4px;">Szerokość (mm):</label>';
        echo '<input type="number" id="custom_width" value="' . $custom_width . '" name="custom_width" required style="width: 100%;">';
        echo '<small id="width_error" style="color:red; display:block; margin-top: 4px;"></small>';
        echo '</div>';


        echo '<div style="flex: 1; min-width: 150px;">';
        echo '<label for="custom_length" style="display: block; font-weight: 600; margin-bottom: 4px;">Długość (mm):</label>';
        if ($width_fixed) {
            echo '<input type="number" id="custom_width" value="' . $min_width . '" name="custom_width" readonly style="width: 100%; background-color: #f5f5f5;">';
            echo '<small id="width_error" style="display:block; margin-top: 4px;"></small>';
        } else {
            echo '<input type="number" id="custom_length" value="' . $custom_length . '" name="custom_length" required style="width: 100%;">';
            echo '<small id="length_error" style="color:red; display:block; margin-top: 4px;"></small>';
        }
        echo '</div>';

        echo '</div>';

        echo '<p id="delivery_info"></p>';
        echo '<p style="margin-top: 10px;"><strong></strong><span id="calculated_price"></span></p>';
        echo '</div>';
    }
}

//add_action('woocommerce_before_add_to_cart_form', 'linear_product_fields', 1);
add_action('woocommerce_before_add_to_cart_form', 'linear_product_fields');
