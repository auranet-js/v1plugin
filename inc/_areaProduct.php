<?php


function custom_wc_display_dimensions_fields()
{

    if (!is_product())
        return;
    global $product;
    $is_area = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';
    if (!$is_area)
        return;

    if (get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes') {


        $min_width = get_post_meta($product->get_id(), '_min_width', true);
        $max_width = get_post_meta($product->get_id(), '_max_width', true);
        $width_fixed = ($min_width && $max_width && $min_width == $max_width);

        $price_incl = wc_get_price_including_tax($product);
        $price_excl = wc_get_price_excluding_tax($product);
        // error_log('Price incl tax: ' . $price_incl);
        // error_log('Price excl tax: ' . $price_excl);

        $discounted_price = $product->get_price();
        $discounted_incl_tax = wc_get_price_including_tax($product, array('price' => $discounted_price));
        //$discounted_excl_tax = wc_get_price_excluding_tax( $product, array('price' => $discounted_price) );
        if ($product instanceof WC_Product_Variable) {
            $product = wc_get_product($product->get_id());
            $tax_class = $product->get_tax_class();
            $tax_rates = WC_Tax::get_rates_for_tax_class($tax_class);
            $vat_rate = 23;
            if (!empty($tax_rates)) {
                $vat_rate = reset($tax_rates)->tax_rate;
            }
            $vat_multiplier = 1 + ($vat_rate / 100);
            $discounted_excl_tax = $discounted_incl_tax / $vat_multiplier;
        } else {
            $discounted_excl_tax = wc_get_price_excluding_tax($product, array('price' => $discounted_price));
        }
        $discounted_brutto = wc_price($discounted_incl_tax);
        $discounted_netto = wc_price($discounted_excl_tax);
        $attributes = getCartItemData();
        $custom_length = isset($attributes['custom_length']) ? $attributes['custom_length'] : '';
        $custom_width = isset($attributes['custom_width']) ? $attributes['custom_width'] : '';

        if ($product->is_type('variable')) {
            $retail_price = $product->get_variation_regular_price('min', true);
        } else {
            $retail_price = $product->get_regular_price();
        }
        $retail_incl_tax = wc_get_price_including_tax($product, array('price' => $retail_price));
        if ($product instanceof WC_Product_Variable) {
            $product = wc_get_product($product->get_id());
            $tax_class = $product->get_tax_class();
            $tax_rates = WC_Tax::get_rates_for_tax_class($tax_class);
            $vat_rate = 23;
            if (!empty($tax_rates)) {
                $vat_rate = reset($tax_rates)->tax_rate;
            }
            $vat_multiplier = 1 + ($vat_rate / 100);
            $retail_excl_tax = $retail_incl_tax / $vat_multiplier;
        } else {
            $retail_excl_tax = wc_get_price_excluding_tax($product, array('price' => $retail_price));
        }
        //$retail_excl_tax = wc_get_price_excluding_tax( $product, array('price' => $retail_price) );
        $retail_brutto = wc_price($retail_incl_tax);
        $retail_netto = wc_price($retail_excl_tax);

        // Sprawdzamy rolę użytkownika (czy jest hurtownikiem)
        $show_retail = false;
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('wholesale_buyer', (array) $user->roles)) {
                $show_retail = true;
            }
        }
        // Wyświetlenie nowego stylu cen
        if ($show_retail) {
            ?>
            <div style="margin-bottom: 20px;">

                <div
                    style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f5f5f5; border-radius: 6px; margin-bottom: 10px;">
                    <div style="font-weight: 600;">Cena brutto za 1 <?= getUnit($product) ?>:</div>
                    <div style="font-size: 22px; font-weight: bold; color: #222;"><?= $discounted_brutto . getUnit($product) ?>
                        <span style="font-size: 14px;"></span>
                    </div>
                </div>

                <div
                    style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f5f5f5; border-radius: 6px; margin-bottom: 10px;">
                    <div style="font-weight: 600;">Cena detaliczna brutto za 1 <?= getUnit($product) ?>:</div>
                    <div style="font-size: 18px; font-weight: 600; color: #555;"><?= $retail_brutto . getUnit($product) ?> <span
                            style="font-size: 14px;"></span></div>
                </div>

                <div
                    style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #fafafa; border-radius: 6px; margin-bottom: 10px;">
                    <div style="font-weight: 500;">Cena netto za 1 <?= getUnit($product) ?>:</div>
                    <div style="font-size: 16px; font-weight: 500; color: #666;"><?= $discounted_netto . getUnit($product) ?> <span
                            style="font-size: 14px;"></span></div>
                </div>

                <div
                    style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #fafafa; border-radius: 6px;">
                    <div style="font-weight: 500;">Cena detaliczna netto za 1 <?= getUnit($product) ?>:</div>
                    <div style="font-size: 16px; font-weight: 500; color: #666;"><?= $retail_netto . getUnit($product) ?><span
                            style="font-size: 14px;"></span></div>
                </div>

            </div>
            <?php
        } else {
            echo '<div style="margin-bottom: 20px;">';

            $gross_price = wc_get_price_including_tax(
                $product,
                array('price' => $product->get_price()) // ← tu trafia NETTO
            );
            echo '  <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f5f5f5; border-radius: 6px; margin-bottom: 10px;">';
            echo '    <div style="font-weight: 600;">Cena brutto za 1 ' . getUnit($product) . ':</div>';
            echo '    <div style="font-size: 24px; font-weight: bold; color: #222;">'
                . '<span id="gross_price_display" data-default-gross="' . esc_attr( $gross_price ) . '">'
                . wc_price( $gross_price )
                . '</span>'
                . ' <span style="font-size: 14px;"></span></div>';            echo '  </div>';


            echo '</div>';
        }



        echo '<div style="margin-bottom: 20px;">';

        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';

        echo '<div style="flex: 1; min-width: 150px;">';
        echo '<label for="custom_length" style="display: block; font-weight: 600; margin-bottom: 4px;">Długość (mm):</label>';
        echo '<input type="number" id="custom_length" value="' . $custom_length . '" name="custom_length" required style="width: 100%;">';
        echo '<small id="length_error" style="color:red; display:block; margin-top: 4px;"></small>';
        echo '</div>';


        echo '<div style="flex: 1; min-width: 150px;">';
        echo '<label for="custom_width" style="display: block; font-weight: 600; margin-bottom: 4px;">Szerokość (mm):</label>';
        if ($width_fixed) {
            echo '<input type="number" id="custom_width" value="' . $min_width . '" name="custom_width" readonly style="width: 100%; background-color: #f5f5f5;">';
            echo '<small id="width_error" style="display:block; margin-top: 4px;"></small>';
        } else {
            echo '<input type="number" id="custom_width" value="' . $custom_width . '" name="custom_width" required style="width: 100%;">';
            echo '<small id="width_error" style="color:red; display:block; margin-top: 4px;"></small>';
        }
        // echo '<input type="number" id="custom_width" value="' . $custom_width . '" name="custom_width" required style="width: 100%;">';
        // echo '<small id="width_error" style="color:red; display:block; margin-top: 4px;"></small>';
        echo '</div>';

        echo '</div>';

        echo '<p id="delivery_info"></p>';
        // Wyświetlenie obliczonej ceny
        echo '<p style="margin-top: 10px;"><strong>Obliczona cena: </strong><span id="calculated_price">0</span></p>';
        echo '</div>';

        
        $type = countertop_get_category_type($product->get_id());
        
        if ($type) {
            $options = get_option($type === 'kitchen' ? 'countertop_kitchen_services' : 'countertop_bathroom_services', []);
        
        echo '<template id="cutout_template">';
        echo '<div class="cutout-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
        echo '<select name="custom_cutouts[]" style="flex:1; max-width: 400px;">';
        echo '<option value="">-- Wybierz usługę --</option>';
        foreach ($options as $option) {
            echo '<option value="' . esc_attr($option['key']) . '" data-price="' . esc_attr($option['price']) . '">'
            . esc_html($option['label']) . ' (+ ' . wc_price($option['price']) . ')</option>';
        }
        echo '</select>';
        echo '<button type="button" class="button remove-cutout">Usuń</button>';
        echo '</div>';
        echo '</template>';
        
            if (!empty($options)) {
                echo '<div id="cutouts_container" style="margin-top: 20px;">';
                echo '<label style="font-weight: 600; display:block; margin-bottom:6px;">Dodaj usługę:</label>';
                echo '<div class="cutout-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
                echo '<select name="custom_cutouts[]" style="flex:1; max-width: 400px;">';
                echo '<option value="">-- Wybierz usługę --</option>';
                foreach ($options as $option) {
                    echo '<option value="' . esc_attr($option['key']) . '" data-price="' . esc_attr($option['price']) . '">'
                    . esc_html($option['label']) . ' (+ ' . wc_price($option['price']) . ')</option>';
                }
                echo '</select>';
                echo '<button type="button" class="button remove-cutout">Usuń</button>';
                echo '</div>';
                echo '<button type="button" id="add_cutout_row" class="button">Dodaj kolejną usługę</button>';
                echo '</div>';
            }
        }
    }
}

add_action('woocommerce_before_add_to_cart_form', 'custom_wc_display_dimensions_fields');

function victorini2025_localize_grubosc_variant_prices() {
    if ( ! is_product() ) {
        return;
    }

    global $product;
    if ( ! $product instanceof WC_Product ) {
        return;
    }
    if ( ! $product->is_type( 'variable' ) ) {
        return;
    }

    $attr_key = 'attribute_pa_grubosc'; // klucz w tablicy variation attributes
    $variant_price_map = array();

    foreach ( $product->get_available_variations() as $v ) {
        $slug = isset( $v['attributes'][ $attr_key ] ) ? $v['attributes'][ $attr_key ] : '';
        if ( '' === $slug ) {
            continue;
        }

        $var_obj = wc_get_product( $v['variation_id'] );
        if ( ! $var_obj ) {
            continue;
        }

        $gross      = wc_get_price_including_tax( $var_obj );
        $gross_html = wc_price( $gross );
        $net        = wc_get_price_excluding_tax( $var_obj );
        $net_html   = wc_price( $net );

        $variant_price_map[ $slug ] = array(
            'gross'      => (float) $gross,
            'gross_html' => $gross_html,
            'net'        => (float) $net,
            'net_html'   => $net_html,
        );
    }
    wp_localize_script( 'victorini2025-script', 'gruboscVariantPrices', $variant_price_map );
}
add_action( 'wp_enqueue_scripts', 'victorini2025_localize_grubosc_variant_prices', 20 );

function countertop_get_category_type($product_id) {
    $kitchen_slugs = [
        'blaty-kuchenne-granitowe',
        'blaty-kuchenne-konglomerat-marmurowy',
        'blaty-kuchenne-marmurowe',
        'blaty-kuchenne-z-konglomeratu-kwarcowego',
        'blaty-kuchenne-z-kwarcytu'
    ];

    $bathroom_slugs = [
        'blaty-lazienkowe-granitowe',
        'blaty-lazienkowe-z-konglomeratu-kwarcowego',
        'blaty-lazienkowe-z-konglomeratu-marmurowego',
        'blaty-lazienkowe-z-kwarcytu'
    ];

    $terms = get_the_terms($product_id, 'product_cat');
    if (!$terms || is_wp_error($terms)) return null;

    foreach ($terms as $term) {
        if (in_array($term->slug, $kitchen_slugs)) return 'kitchen';
        if (in_array($term->slug, $bathroom_slugs)) return 'bathroom';
    }
    return null;
}

/**
 * Zwraca mapę [key] => ['key'=>..,'label'=>..,'price'=>..] dla danego produktu.
 * Uwzględnia czy to kuchenny czy łazienkowy blat.
 */
function countertop_get_service_options_for_product( $product_id ) {
    // jeśli to wariacja, pobierz id rodzica (bo kategorie zwykle na rodzicu)
    $parent_id = 0;
    if ( 'product_variation' === get_post_type( $product_id ) ) {
        $parent_id = wp_get_post_parent_id( $product_id );
    }
    $base_id = $parent_id ?: $product_id;

    $type = countertop_get_category_type( $base_id );
    if ( ! $type ) {
        return array();
    }

    $raw = get_option(
        $type === 'kitchen' ? 'countertop_kitchen_services' : 'countertop_bathroom_services',
        array()
    );

    $map = array();
    if ( is_array( $raw ) ) {
        foreach ( $raw as $srv ) {
            if ( empty( $srv['key'] ) ) {
                continue;
            }
            $map[ sanitize_title( $srv['key'] ) ] = array(
                'key'   => sanitize_title( $srv['key'] ),
                'label' => isset( $srv['label'] ) ? $srv['label'] : $srv['key'],
                'price' => isset( $srv['price'] ) ? floatval( $srv['price'] ) : 0,
            );
        }
    }
    return $map;
}

add_action( 'init', function() {
  remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation', 10 );
} );