<?php


add_action('woocommerce_product_options_general_product_data', 'add_linear_meter_pricing_field');
function add_linear_meter_pricing_field() {
    woocommerce_wp_checkbox(array(
        'id' => '_linear_meter_pricing',
        'label' => __('Liczenie ceny za metr bieżący', 'textdomain'),
        'description' => __('Zaznacz, aby włączyć obliczenia za metr bieżący', 'textdomain')
    ));
}
add_action('woocommerce_process_product_meta', 'save_linear_meter_pricing_field');
function save_linear_meter_pricing_field($post_id) {
    $checkbox = isset($_POST['_linear_meter_pricing']) ? 'yes' : 'no';
    update_post_meta($post_id, '_linear_meter_pricing', $checkbox);
}