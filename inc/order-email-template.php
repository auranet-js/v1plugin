<?php
add_filter('woocommerce_email_order_items_table', 'custom_email_order_items_table', 10, 4);

function custom_email_order_items_table($table_html, $order, $args = array(), $plain_text = false)
{
    ob_start();
?>
    <?php foreach ($order->get_items() as $item_id => $item) :
        $product = $item->get_product();
        $thumbnail = $product ? apply_filters('woocommerce_cart_item_thumbnail', $product->get_image(), $item, $item_id) : '';
        $custom_file_url = $item->get_meta('custom_file');
        $custom_length = $item->get_meta('custom_length');
        $custom_width = $item->get_meta('custom_width');
        $custom_length_obrobka = $item->get_meta('custom_length_obrobka');
        
        // Pobierz wymiary obróbki
        $custom_wymiar = $item->get_meta('custom_wymiar');
        
        // Zbierz wymiary
        $wymiary_obrobki = array();
        
        // Sposób 1: Jeśli jest zapisane jako tablica
        if (is_array($custom_wymiar) && !empty($custom_wymiar)) {
            $wymiary_obrobki = $custom_wymiar;
        } else {
            // Sposób 2: Jeśli zapisane jako osobne meta (A, B, C...)
            foreach (range('A', 'Z') as $letter) {
                $value = $item->get_meta('custom_wymiar_' . $letter);
                if ($value) {
                    $wymiary_obrobki[$letter] = $value;
                }
            }
        }
        
        if ($product instanceof WC_Product_Variation) {
            $tax_rates = WC_Tax::get_rates($product->get_tax_class());
            $vat_rate = !empty($tax_rates) ? reset($tax_rates)['rate'] : 23;
            $vat_multiplier = 1 + ($vat_rate / 100);
            $price_incl_tax = $order->get_line_total($item, true, true);
            $price_excl_tax = $price_incl_tax / $vat_multiplier;
        } else {
            $price_incl_tax = $order->get_line_total($item, true, true);
            $price_excl_tax = $order->get_line_total($item, false, true);
        }
    ?>
        <tr>
        <td class="td" style="text-align:left; vertical-align: middle; padding: 12px; word-wrap: break-word;">
            <div style="margin-bottom: 5px;">
                <?php if ($thumbnail) : ?>
                    <div style="float: left; margin-right: 15px; margin-bottom: 10px;"><?php echo $thumbnail; ?></div>
                <?php endif; ?>
                <div>
                    <strong><?php echo $item->get_name(); ?></strong>
                    
                    <?php if ($custom_length_obrobka || !empty($wymiary_obrobki)) : ?>
                        <!-- Wymiary obróbki blachy -->
                        <div style="font-size: 12px; margin-top: 5px;">
                            <strong>Wymiary obróbki:</strong>
                            <?php 
                            $dims = array();
                            if ($custom_length_obrobka) {
                                $dims[] = 'Długość: ' . esc_html($custom_length_obrobka) . 'mm';
                            }
                            foreach ($wymiary_obrobki as $name => $value) {
                                $dims[] = $name . ': ' . esc_html($value) . 'mm';
                            }
                            echo implode(', ', $dims);
                            ?>
                        </div>
                    <?php elseif ($custom_length || $custom_width) : ?>
                        <!-- Standardowe wymiary (parapety) -->
                        <div style="font-size: 12px; margin-top: 5px;">
                            <strong>Wymiary:</strong>
                            <?php echo $custom_length ? $custom_length . ' mm' : ''; ?>
                            <?php echo ($custom_length && $custom_width) ? ' x ' : ''; ?>
                            <?php echo $custom_width ? $custom_width . ' mm' : ''; ?>
                            <?php
                            if ($custom_length && $custom_width) {
                                $length_in_meters = $custom_length / 1000;
                                $width_in_meters = $custom_width / 1000;
                                $area_in_square_meters = $length_in_meters * $width_in_meters;
                                $base_price = get_base_price($product);
                                echo ' (' . number_format($area_in_square_meters, 2) . getUnit($product) . ' x ' . wc_price($base_price) . ')';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($custom_file_url) : ?>
                        <div style="margin-top: 10px;">
                            <strong>Załączony plik:</strong><br>
                            <a href="<?= esc_url($custom_file_url) ?>" target="_blank" style="text-decoration: none; color: #0073aa;">
                                <img src="<?= esc_url($custom_file_url) ?>"
                                    style="max-width: 100px; max-height: 100px; margin-top: 5px;"
                                    width="100"
                                    height="100">
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="clear: both;"></div>
            </div>
        </td>
        <td class="td" style="text-align:left; vertical-align: middle; padding: 12px;">
            <?php echo $item->get_quantity(); ?>
        </td>
        <td class="td" style="text-align:left; vertical-align: middle; padding: 12px;">
            <?php echo wc_price($price_incl_tax); ?><br>
            <small style="color: #777;"><?php echo wc_price($price_excl_tax); ?> netto</small>
        </td>
        </tr>
    <?php endforeach; ?>
<?php
    return ob_get_clean();
}