<?php

/**
 * Plugin Name: Victorini2025 by Auranet
 * Description: Plugin przenoszący część funkcji z functions.php do pluginu, łącznie z obsługą kalkulatora cen oraz modyfikacjami WooCommerce.
 * Version: 1.1
 * Author: Auranet
 * Text Domain: victorini2025-by-auranet
 */

// Dodanie pola „Oblicz cenę na podstawie wymiarów” w edycji produktu
function custom_wc_product_add_custom_field()
{
    woocommerce_wp_checkbox(array(
        'id' => '_is_calculated_by_area',
        'label' => __('Oblicz cenę na podstawie wymiarów', 'woocommerce'),
        'description' => __('Jeśli zaznaczone, cena będzie liczona jako długość x szerokość x cena za m².', 'woocommerce'),
    ));
}
add_action('woocommerce_product_options_general_product_data', 'custom_wc_product_add_custom_field');

// Zapisanie wartości pola
function custom_wc_product_save_custom_field($post_id)
{
    $is_calculated = isset($_POST['_is_calculated_by_area']) ? 'yes' : 'no';
    update_post_meta($post_id, '_is_calculated_by_area', $is_calculated);
}
add_action('woocommerce_process_product_meta', 'custom_wc_product_save_custom_field');

// Pobranie wartości min/max dla JS
function custom_wc_add_product_js_variables()
{
    if (!is_product())
        return;

    global $product;
    $is_area = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';
    $is_linear = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
    $is_pcv = get_post_meta($product->get_id(), '_pcv_product', true) === 'yes';
    $obrobka_blachy = get_post_meta($product->get_id(), '_obrobka_blachy_enabled', true) === 'yes';

    if ( ! $is_area && ! $is_linear && ! $is_pcv && ! $obrobka_blachy ) {

        $unit_price = (float) wc_get_price_including_tax( $product );

        $data = array(
            'fallback'      => true,          // możesz użyć w JS: if(productData.fallback) ...
            'unitPrice'     => $unit_price,   // cena za sztukę
            'pricePerM2'    => $unit_price,   // zostawiam, żeby JS się nie wywalił
            'minLength'     => 0,
            'maxLength'     => 0,
            'minWidth'      => 0,
            'maxWidth'      => 0,
            'isPcv'         => false,
            'isLinear'      => false,
            'minPrice'      => 0,
            'kapinosyPrice' => 0,
            'variantPrices' => array(),
        );

        $encoded = wp_json_encode( $data );
        echo '<script>window.productData = ' . $encoded . ';(function(){try{document.dispatchEvent(new CustomEvent("victoriniProductDataReady",{detail:window.productData}));}catch(e){var evt=document.createEvent("CustomEvent");evt.initCustomEvent("victoriniProductDataReady",true,true,window.productData);document.dispatchEvent(evt);}})();</script>';
        return;
    }

    $min_length = get_post_meta($product->get_id(), '_min_length', true) ?: 100;
    $max_length = get_post_meta($product->get_id(), '_max_length', true) ?: 3200;
    $min_width = get_post_meta($product->get_id(), '_min_width', true) ?: 100;
    $max_width = get_post_meta($product->get_id(), '_max_width', true) ?: 1600;

    // Cena m² z uwzględnieniem rabatów, podatków itp.
    $is_linear = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
    $price_per_m2 = floatval(wc_get_price_including_tax($product));


    $variant_prices = [];
    if ($product->is_type('variable')) {
        foreach ($product->get_available_variations() as $v) {
            $variation = wc_get_product($v['variation_id']);

            // slug np. 08mm, 1-mm itp.
            $thickness = $v['attributes']['attribute_pa_grubosc'] ?? '';

            if ($thickness) {
                $variant_prices[$thickness] = wc_get_price_including_tax($variation);
            }
        }
    }

    echo "<script>
    var productData = {
        minLength: $min_length,
        maxLength: $max_length,
        minWidth: $min_width,
        maxWidth: $max_width,
        isPcv: " . (get_post_meta($product->get_id(), '_pcv_product', true) === 'yes' ? 'true' : 'false') . ",
        pricePerM2: $price_per_m2,
        minPrice: " . getMinimumPrice() . ",
        kapinosyPrice: " . get_kapinos_price($product->get_id(), null) . ",
        isLinear: " . ($is_linear ? 'true' : 'false') . ",
        variantPrices: " . json_encode($variant_prices) . "
    };

    (function(){
        try {
            document.dispatchEvent(new CustomEvent('victoriniProductDataReady', { detail: productData }));
        } catch (e) {
            var evt = document.createEvent('CustomEvent');
            evt.initCustomEvent('victoriniProductDataReady', true, true, productData);
            document.dispatchEvent(evt);
        }
    })();
    </script>";
}
add_action('wp_footer', 'custom_wc_add_product_js_variables');

// Dodanie pól długości i szerokości na stronie produktu

// Dodanie wymiarów do danych koszyka
function custom_wc_add_cart_item_data($cart_item_data, $product_id)
{
    $is_calculated = get_post_meta($product_id, '_is_calculated_by_area', true) === 'yes';
    $is_linear = get_post_meta($product_id, '_linear_meter_pricing', true) === 'yes';
    $has_area = ($is_calculated || $is_linear || get_post_meta($product_id, '_pcv_product', true) === 'yes');
    if (isset($_POST['custom_length']) && isset($_POST['custom_width']) && $has_area) {
        $cart_item_data['custom_length'] = (int) $_POST['custom_length'];
        $cart_item_data['custom_width'] = (int) $_POST['custom_width'];
    }
    if (isset($_POST['cutting_required'])) {
        $cart_item_data['cutting_required'] = sanitize_text_field($_POST['cutting_required']);
    }

    $selected_keys = array();
        if ( isset($_POST['countertop_cutouts']) && '' !== $_POST['countertop_cutouts'] ) {
            $selected_keys = array_map( 'trim', explode( ',', wp_unslash( $_POST['countertop_cutouts'] ) ) );
        }
        // fallback: tablica (gdyby markup był wewnątrz form)
        if ( empty($selected_keys) && ! empty($_POST['custom_cutouts']) && is_array($_POST['custom_cutouts']) ) {
            $selected_keys = array_map( 'sanitize_title', (array) $_POST['custom_cutouts'] );
        }

        if ( ! empty( $selected_keys ) ) {
            $services_map = countertop_get_service_options_for_product( $product_id );
            $selected     = array();
            $total        = 0;

        foreach ( $selected_keys as $key ) {
            $key = sanitize_title($key);
            if ( isset( $services_map[ $key ] ) ) {
                $srv   = $services_map[ $key ];
                $price = floatval( $srv['price'] );
                $selected[] = array(
                    'key'   => $srv['key'],
                    'label' => $srv['label'],
                    'price' => $price,
                );
                $total += $price;
            }
        }

        if ( $selected ) {
            // przechowaj listę wybranych usług (snapshot)
            $cart_item_data['countertop_cutouts']       = $selected;
            $cart_item_data['countertop_cutouts_total'] = $total; // kwota na sztukę
            // wymuś unikatowy klucz koszyka
            $cart_item_data['countertop_cutouts_hash']  = md5( wp_json_encode( $selected ) );
        }
    }

    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'custom_wc_add_cart_item_data', 10, 2);


function custom_wc_checkout_create_order_line_item($item, $cart_item_key, $values, $order)
{
    if (isset($values['custom_length'])) {
        $item->add_meta_data('custom_length', $values['custom_length']);
    }
    if (isset($values['custom_width'])) {
        $item->add_meta_data('custom_width', $values['custom_width']);
    }

    // ========== DODAJ DODANO DLA OBROBEK ==========
    if (isset($values['custom_length_obrobka'])) {
        $item->add_meta_data('custom_length_obrobka', $values['custom_length_obrobka']);
    }
    if (isset($values['custom_wymiar']) && is_array($values['custom_wymiar'])) {
        foreach ($values['custom_wymiar'] as $name => $value) {
            $item->add_meta_data('custom_wymiar_' . $name, $value);
        }
    }
    if (isset($values['custom_file'])) {
        $item->add_meta_data('custom_file', $values['custom_file']);
    }

    if (isset($values['cutting_required'])) {
        $item->add_meta_data('cutting_required', $values['cutting_required']);
    }

    if (isset($values['custom_file_path'])) {
        $item->add_meta_data('custom_file_path', $values['custom_file_path']);
    }
    
    if ( ! empty( $values['countertop_cutouts'] ) && is_array( $values['countertop_cutouts'] ) ) {
        // dodaj czytelny zapis
        $lines = array();
        foreach ( $values['countertop_cutouts'] as $srv ) {
            $lines[] = $srv['label'] . ' (+ ' . wc_price( $srv['price'] ) . ')';
        }
        $item->add_meta_data( __( 'Usługi dodatkowe', 'victorini2025-by-auranet' ), implode( '; ', $lines ) );

        // zapis programistyczny (JSON) – przydatny do faktur / ERP
        $item->add_meta_data( '_countertop_cutouts_json', wp_json_encode( $values['countertop_cutouts'] ) );
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'custom_wc_checkout_create_order_line_item', 10, 4);

// Modyfikacja ceny w koszyku na podstawie wymiarów
// function custom_wc_calculate_price($cart_object) {
//     foreach ($cart_object->get_cart() as $cart_item) {
//         if (isset($cart_item['custom_length']) && isset($cart_item['custom_width'])) {
//             $product = wc_get_product($cart_item['product_id']);
//             $discounted_price_per_m2 = $product->get_price();
//             $length = $cart_item['custom_length'] / 1000;
//             $width = $cart_item['custom_width'] / 1000;
//             $area = $length * $width;
//             $new_price = $area * $discounted_price_per_m2;
//             $cart_item['data']->set_price($new_price);
//         }
//     }
// }
// add_action('woocommerce_before_calculate_totals', 'custom_wc_calculate_price');

function custom_wc_calculate_price($cart_object)
{
    if (did_action('woocommerce_before_calculate_totals') > 1) {
        return;
    }
    foreach ($cart_object->get_cart() as $cart_item_key => $cart_item) {

        if (isset($cart_item['custom_length'], $cart_item['custom_width'])) {
            $product = $cart_item['data'];
            $product_id = $product->get_id();
            $parent_id = $product->get_parent_id();
            $is_linear = get_post_meta($product_id, '_linear_meter_pricing', true) === 'yes';
            $is_pcv = get_post_meta($parent_id, '_pcv_product', true) === 'yes';

            $price_per_m2 = $product->get_price();

            $length_mm = (float) $cart_item['custom_length'];
            $width_mm = (float) $cart_item['custom_width'];

            if ($is_linear) {
                $width_mm += 70;
            }

            $length = $length_mm / 1000;
            $width = $width_mm / 1000;

            $area = $length * $width;
            $new_price = $area * $price_per_m2;
            if ($is_pcv) {
                $new_price = $length * $price_per_m2;
            }
            if (!empty($cart_item['cutting_required']) && $cart_item['cutting_required'] == '1') {
                $new_price += 15;
            }
            if (has_kapinosy($cart_item)) {
                $kapinosy_price = get_kapinos_price($product_id, $parent_id);
                $new_price = calculate_kapinos_price($new_price, $length, $width, $kapinosy_price);
            }

            if ( ! empty( $cart_item['countertop_cutouts_total'] ) ) {
                $new_price += (float) $cart_item['countertop_cutouts_total'];
            }

            //$new_price = apply_minimum_price_rule($new_price, $product);

            $cart_item['data']->set_price($new_price);
        } else if (isset($cart_item['custom_wymiar']) && is_array($cart_item['custom_wymiar'])) {
            calculateObrobkaBlachyPrice($cart_item);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'custom_wc_calculate_price', 10, 1);

function calculateObrobkaBlachyPrice($cart_item)
{
    $product = $cart_item['data'];
    $price_per_m2 = $product->get_price();
    $length_mm = (float) $cart_item['custom_length_obrobka'];

    $total_width_mm = 0;
    foreach ($cart_item['custom_wymiar'] as $dimension_value) {
        $total_width_mm += $dimension_value;
    }

    $length_m = $length_mm / 1000;
    $width_m = $total_width_mm / 1000;

    $area = $length_m * $width_m;
    $new_price = $area * $price_per_m2;

    $cart_item['data']->set_price($new_price);
}

/**
 * Wyświetlanie ceny w listingu dla produktów z włączoną „Obróbką blachy”.
 * Liczymy identycznie jak na karcie produktu dla wartości domyślnych:
 *   total = (min_length_mm/1000) * (sum(default_dimensions_mm)/1000) * price_per_m2_gross
 */
function victorini_obrobka_blachy_price_html($price_html, $product) {
    if (is_admin() && !wp_doing_ajax()) {
        return $price_html;
    }

    // Nie ingeruj na karcie produktu
    if (function_exists('is_product') && is_product()) {
        return $price_html;
    }

    if (!$product instanceof WC_Product) {
        return $price_html;
    }

    $check_product = $product;
    if ($product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        if ($parent instanceof WC_Product) {
            $check_product = $parent;
        }
    }

    $enabled = get_post_meta($check_product->get_id(), '_obrobka_blachy_enabled', true) === 'yes';
    if (!$enabled) {
        return $price_html;
    }

    // Cena za m2 – brutto (spójnie z JS na karcie produktu)
    // Dla produktów zmiennych weź min cenę wariantu (brutto)
    if (function_exists('victorini_min_price_incl_tax')) {
        $unit_price = (float) victorini_min_price_incl_tax($check_product);
    } else {
        if ($check_product->is_type('variable')) {
            $unit_price = (float) $check_product->get_variation_price('min', true);
        } else {
            $unit_price = (float) wc_get_price_including_tax($product);
        }
    }

    // Długość: domyślnie min_length z meta
    $min_length_mm = (float) (get_post_meta($check_product->get_id(), '_min_length', true) ?: 0);

    // Suma domyślnych wymiarów z konfiguratora
    $dims = get_post_meta($check_product->get_id(), '_wymiary_obrobki', true);
    $sum_width_mm = 0.0;
    if (is_array($dims)) {
        foreach ($dims as $row) {
            $v = isset($row['domyslna']) ? (float) $row['domyslna'] : 0.0;
            $sum_width_mm += $v;
        }
    }

    // Gdy brakuje danych – nie nadpisuj ceny
    if ($unit_price <= 0 || $min_length_mm <= 0 || $sum_width_mm <= 0) {
        return $price_html;
    }

    $length_m = $min_length_mm / 1000.0;
    $width_m  = $sum_width_mm / 1000.0;
    $total    = $unit_price * $length_m * $width_m;

    if ($total <= 0) {
        return $price_html;
    }

    return wc_price($total);
}

// Wysoki priorytet i różne hooki dla zgodności z motywami
add_filter('woocommerce_get_price_html', 'victorini_obrobka_blachy_price_html', 9999, 2);
add_filter('woocommerce_variable_price_html', 'victorini_obrobka_blachy_price_html', 9999, 2);
add_filter('woocommerce_variable_sale_price_html', 'victorini_obrobka_blachy_price_html', 9999, 2);

function calculate_kapinos_price($price, $length, $width, $price_per_mb)
{
    $kapinos_charge = $length * $price_per_mb;

    return $price + $kapinos_charge;
}
function has_kapinosy($cart_item)
{
    if (isset($cart_item['variation']) && is_array($cart_item['variation'])) {
        if (isset($cart_item['variation']['attribute_pa_kapinosy']) && ($cart_item['variation']['attribute_pa_kapinosy'] === 'tak' || $cart_item['variation']['attribute_pa_kapinosy'] === 'kapinosy-tak')) {
            return true;
        }
    }
    $product = $cart_item['data'];
    if (is_a($product, 'WC_Product') && !$product->is_type('variation')) {
        $kapinosy_value = $product->get_attribute('pa_kapinosy');
        return $kapinosy_value === 'tak' || $kapinosy_value === 'kapinosy-tak';
    }

    return false;
}

function get_kapinos_price($product_id, $parent_id)
{
    $category_product_id = $parent_id ?: $product_id;
    $terms = get_the_terms($category_product_id, 'product_cat');

    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $kapinos_price = get_term_meta($term->term_id, 'kapinosy_price_per_m', true);

            if (is_numeric($kapinos_price) && $kapinos_price > 0) {
                return (float) $kapinos_price;
            }
        }
    }

    return 0;
}

function getMinimumPrice($product_id = false)
{
    global $product;

    if (!$product) {
        $product = wc_get_product($product_id);
        if (!$product)
            return 0;
    }

    $check_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

    $product_categories = get_the_terms($check_id, 'product_cat');
    $max_min_price = 0;

    if ($product_categories && !is_wp_error($product_categories)) {
        foreach ($product_categories as $category) {
            $category_min_price = (float) get_term_meta($category->term_id, 'min_price', true);
            if ($category_min_price > $max_min_price) {
                $max_min_price = $category_min_price;
            }
        }
    }

    return $max_min_price;
}

// function apply_minimum_price_rule($current_price, $product) {
//     $is_calculated = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';

//     if($is_calculated != "yes"){
//         return;
//     }
//     $check_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
//     $product_categories = get_the_terms($check_id, 'product_cat');
//     $max_min_price = 0;

//     if (empty($product_categories) || is_wp_error($product_categories)) {
//         return $current_price;
//     }

//     foreach ($product_categories as $category) {
//         $category_min_price = (float) get_term_meta($category->term_id, 'min_price', true);
//         if ($category_min_price > $max_min_price) {
//             $max_min_price = $category_min_price;
//         }
//     }

//     if ($max_min_price > 0) {
//         $current_gross_price = wc_get_price_including_tax($product, ['price' => $current_price]);

//         if ($current_gross_price < $max_min_price) {
//             wc_add_notice(
//                 sprintf(
//                     __('Cena produktu "%s" została dostosowana do minimalnej wartości %s dla kategorii "%s".', 'victorini2025-by-auranet'),
//                     $product->get_name(),
//                     wc_price($max_min_price),
//                     $category->name
//                 ),
//                 'notice'
//             );
//             return wc_get_price_including_tax($product, ['price' => $max_min_price]);
//         }
//     }

//     return $current_price;
// }


add_filter('woocommerce_add_to_cart_validation', 'waliduj_minimalna_cene', 10, 3);

function waliduj_minimalna_cene($passed, $product_id, $quantity)
{
    $product = wc_get_product($product_id);
    $is_calculated = get_post_meta($product_id, '_is_calculated_by_area', true) === 'yes';
    if (!$is_calculated)
        return $passed;

    $min_price = getMinimumPrice($product_id);
    if ($min_price <= 0)
        return $passed;

    if (!isset($_POST['custom_length']) || !isset($_POST['custom_width'])) {
        wc_add_notice(__('Musisz podać wymiary produktu', 'victorini2025-by-auranet'), 'error');
        return false;
    }

    $length = (float) $_POST['custom_length'] / 1000;
    $width = (float) $_POST['custom_width'] / 1000;
    $cena_za_m2 = (float) $product->get_price();
    $price = $length * $width * $cena_za_m2;
    if (isset($_POST["attribute_pa_kapinosy"]) && ($_POST["attribute_pa_kapinosy"] == "tak" || $_POST["attribute_pa_kapinosy"] == "kapinosy-tak")) {
        $kapinosy_price = get_kapinos_price($product->get_id(), $product->get_parent_id());
        $price = calculate_kapinos_price($price, $length, $width, $kapinosy_price);
    }

    $total = $price * max(1, (int)$quantity); 
    if ($total < $min_price) {
        wc_add_notice(
            sprintf('Minimalna cena dla tego produktu to %s', wc_price($min_price)),
            'error'
        );
        return false;
    }

    return $passed;
}


// Dodanie pól dla minimalnej i maksymalnej długości oraz szerokości
function custom_wc_product_add_dimension_fields()
{
    woocommerce_wp_text_input(array(
        'id' => '_min_length',
        'label' => __('Minimalna długość (mm)', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => array('step' => '1', 'min' => '0'),
    ));
    woocommerce_wp_text_input(array(
        'id' => '_max_length',
        'label' => __('Maksymalna długość (mm)', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => array('step' => '1', 'min' => '1'),
    ));
    woocommerce_wp_text_input(array(
        'id' => '_min_width',
        'label' => __('Minimalna szerokość (mm)', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => array('step' => '1', 'min' => '0'),
    ));
    woocommerce_wp_text_input(array(
        'id' => '_max_width',
        'label' => __('Maksymalna szerokość (mm)', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => array('step' => '1', 'min' => '1'),
    ));
}
add_action('woocommerce_product_options_general_product_data', 'custom_wc_product_add_dimension_fields');

// Zapisanie wartości
function custom_wc_product_save_dimension_fields($post_id)
{
    $fields = ['_min_length', '_max_length', '_min_width', '_max_width'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
add_action('woocommerce_process_product_meta', 'custom_wc_product_save_dimension_fields');


function get_base_price($product)
{

    $show_retail = false;
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('wholesale_buyer', (array) $user->roles)) {
            $show_retail = true;
        }
    }
    if ($show_retail) {
        if ($product->is_type('variable')) {
            $retail_price = $product->get_variation_regular_price('min', true);
            $retail_price = $product->get_price();
        } elseif ($product->is_type('variation')) {
            $parent_product = wc_get_product($product->get_parent_id());
            if ($parent_product->is_type('variable')) {
                $retail_price = $parent_product->get_variation_regular_price('min', true);
                $retail_price = $parent_product->get_price();
            } else {
                $retail_price = $parent_product->get_regular_price();
            }
        } else {
            $retail_price = $product->get_regular_price();
        }
        return wc_get_price_including_tax($product, array('price' => $retail_price));
    } else {
        $discounted_price = $product->get_price();
        return wc_get_price_including_tax($product, array('price' => $discounted_price));
    }
}


function getUnit($product)
{
    $is_linear = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
    $is_area = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';

    $unit = '';
    if ($is_linear) {
        $unit = ' / mb';
    } elseif ($is_area) {
        $unit = ' / m²';
    }
    return $unit;
}



if (!function_exists('victorini_linear_default_price')) {
    function victorini_linear_default_price(WC_Product $product): float
    {
        $base_price = wc_get_price_including_tax($product);
        $min_width = get_post_meta($product->get_id(), '_min_width', true);
        $min_width = floatval($min_width);
        if ($min_width <= 0) {
            $min_width = 100;
        }

        $length_mm = 1000;
        $width_with_allowance = $min_width + 70;

        return ($length_mm / 1000) * ($width_with_allowance / 1000) * $base_price;
    }
}


add_action('woocommerce_before_add_to_cart_button', 'display_price_above_add_to_cart', 1000);
function display_price_above_add_to_cart()
{
    global $product;
    $is_calculated = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';
    $is_linear = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
    $is_pcv = get_post_meta($product->get_id(), '_pcv_product', true) === 'yes';
    $obrobka_blachy = get_post_meta($product->get_id(), '_obrobka_blachy_enabled', true) === 'yes';

    if ($product->is_type("variable") && !$is_calculated && !$is_linear && !$is_pcv && !$obrobka_blachy) {
        $variation_prices = $product->get_variation_prices(true);
        $lowest_price = '';
        if (!empty($variation_prices['price'])) {
            $lowest_price_numeric = min($variation_prices['price']);
            $lowest_price = wc_price($lowest_price_numeric);
        }
        echo '<div style="font-size: 24px; font-weight: bold; margin: 8px 0;">Cena: <span id="final-price">' . $lowest_price . '</span></div>';
    } else if ($product->is_type('simple')) {
        if ($is_linear) {
            $price = victorini_linear_default_price($product);
        } else {
            $price = wc_get_price_including_tax($product);
        }
        $formatted_price = wc_price($price);

        echo '<div style="font-size: 24px; font-weight: bold; margin: 8px 0;">Cena: <span id="final-price">' . $formatted_price . '</span></div>';
    } else {
        if (getMinimumPrice() > 0 && $is_calculated) {
            ?>
                <div id="minimalPrice" style="font-size: 16px; margin: 8px 0; display: none !important">
                    Minimalna kwota produktu na wymiar:
                    <span style="color: red;"><?= getMinimumPrice() ?> zł</span>
                </div>
            <?php
        }
        echo '<div  style="font-size: 24px; font-weight: bold; margin: 8px 0">Cena: <span id="final-price"></span></div>';
    }
}


function hiddenFields()
{
    if (is_product()) {
        global $product;

        $min_width = get_post_meta($product->get_id(), '_min_width', true);
        $max_width = get_post_meta($product->get_id(), '_max_width', true);
        $width_fixed = ($min_width && $max_width && $min_width == $max_width);

        $is_linear = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
        $is_area = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';
        if ($is_area) {
        echo '<input type="hidden" name="custom_length" id="custom_length_inside" required >';
            if ($width_fixed) {
                echo '<input type="hidden" name="custom_width" id="custom_width_inside" readonly value="' . $max_width . '" >';
            } else {
                echo '<input type="hidden" name="custom_width" id="custom_width_inside" required >';
            }
        } else if ($is_linear) {
            // Dla produktów liczonych w mb: długość zawsze 1000 mm, szerokość = minimalna (fallback 100)
            echo '<input type="hidden" name="custom_length" id="custom_length_inside" value="1000" required >';
            $default_w = $width_fixed ? $max_width : ($min_width ? $min_width : 100);
            $readonly  = $width_fixed ? ' readonly' : '';
            echo '<input type="hidden" name="custom_width" id="custom_width_inside" value="' . esc_attr($default_w) . '"' . $readonly . ' >';
        }
        echo '<input type="hidden" name="countertop_cutouts" id="countertop_cutouts_hidden" value="">';
    }
}

add_action('woocommerce_before_add_to_cart_button', 'hiddenFields', 1);
