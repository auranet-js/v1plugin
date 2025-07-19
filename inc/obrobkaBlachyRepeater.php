<?php

add_filter('woocommerce_product_data_tabs', 'add_custom_repeater_tab_auto_name');
function add_custom_repeater_tab_auto_name($tabs)
{
    $tabs['sheet_metal_config'] = array(
        'label'    => __('Obróbka Blachy', 'woocommerce'),
        'target'   => 'sheet_metal_config_data',
        'class'    => array('show_if_simple', 'show_if_variable'),
    );
    return $tabs;
}

add_action('woocommerce_product_data_panels', 'add_custom_repeater_fields_panel_auto_name');
function add_custom_repeater_fields_panel_auto_name()
{
    global $post;
    $wymiary_obrobki = get_post_meta($post->ID, '_wymiary_obrobki', true);
    if (!is_array($wymiary_obrobki)) {
        $wymiary_obrobki = array();
    }
?>
    <div id="sheet_metal_config_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <?php
            woocommerce_wp_checkbox(array(
                'id'            => '_obrobka_blachy_enabled',
                'label'         => __('Włącz konfigurator obróbki blachy', 'woocommerce'),
                'description'   => __('Zaznacz, aby umożliwić klientom konfigurację wymiarów tego produktu.', 'woocommerce'),
                'desc_tip'      => true,
            ));
            ?>
        </div>

        <div class="options_group" id="repeater-container">
            <h3>Wymiary do konfiguracji (nazwy A, B, C... nadawane automatycznie)</h3>
            <p class="description">Dodaj kolejne wymiary. Każdy nowy wiersz otrzyma automatycznie kolejną literę alfabetu.</p>
            <div id="repeater-rows">
                <?php
                if (!empty($wymiary_obrobki)) {
                    foreach ($wymiary_obrobki as $wymiar) {
                ?>
                        <div class="repeater-row">
                            <strong class="repeater-label">Wymiar <?php echo esc_html($wymiar['nazwa']); ?></strong>
                            <input type="number" name="wymiar_domyslna[]" placeholder="Wartość domyślna (mm)" value="<?php echo esc_attr($wymiar['domyslna']); ?>" class="repeater-field" step="any" />
                            <input type="number" name="wymiar_min[]" placeholder="Wartość min (mm)" value="<?php echo esc_attr($wymiar['min']); ?>" class="repeater-field" step="any" />
                            <input type="number" name="wymiar_max[]" placeholder="Wartość max (mm)" value="<?php echo esc_attr($wymiar['max']); ?>" class="repeater-field" step="any" />
                            <button type="button" class="button remove-row">- Usuń</button>
                        </div>
                <?php
                    }
                }
                ?>
            </div>
            <button type="button" class="button button-primary add-row">+ Dodaj wymiar</button>
        </div>

        <div id="repeater-template" style="display: none;">
            <div class="repeater-row">
                <strong class="repeater-label"></strong>
                <input type="number" name="wymiar_domyslna[]" placeholder="Wartość domyślna (mm)" class="repeater-field" step="any" />
                <input type="number" name="wymiar_min[]" placeholder="Wartość min (mm)" class="repeater-field" step="any" />
                <input type="number" name="wymiar_max[]" placeholder="Wartość max (mm)" class="repeater-field" step="any" />
                <button type="button" class="button remove-row">- Usuń</button>
            </div>
        </div>
    </div>
    <?php
}


add_action('admin_footer', 'add_repeater_script_auto_name');
function add_repeater_script_auto_name()
{
    global $pagenow, $post_type;
    if (($pagenow == 'post.php' || $pagenow == 'post-new.php') && $post_type == 'product') {
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function updateRowLabels() {
                    $('#repeater-rows .repeater-row').each(function(index) {
                        var letter = String.fromCharCode(65 + index);
                        $(this).find('.repeater-label').text('Wymiar ' + letter);
                    });
                }

                $('#repeater-container').on('click', '.add-row', function() {
                    var row = $('#repeater-template .repeater-row').clone();
                    $('#repeater-rows').append(row);
                    updateRowLabels();
                });

                $('#repeater-rows').on('click', '.remove-row', function() {
                    $(this).closest('.repeater-row').remove();
                    updateRowLabels();
                });

                updateRowLabels();
            });
        </script>
    <?php
    }
}


add_action('woocommerce_process_product_meta', 'save_repeater_fields_data_auto_name');
function save_repeater_fields_data_auto_name($post_id)
{
    $is_enabled = isset($_POST['_obrobka_blachy_enabled']) ? 'yes' : 'no';
    update_post_meta($post_id, '_obrobka_blachy_enabled', $is_enabled);

    $wymiary = array();
    $letters = range('A', 'Z');

    if (isset($_POST['wymiar_domyslna'])) {
        $domyslne  = $_POST['wymiar_domyslna'];
        $miny      = $_POST['wymiar_min'];
        $maxy      = $_POST['wymiar_max'];

        for ($i = 0; $i < count($domyslne); $i++) {
            if (isset($letters[$i]) && $domyslne[$i] !== '') {
                $wymiary[] = array(
                    'nazwa'    => $letters[$i],
                    'domyslna' => wc_clean($domyslne[$i]),
                    'min'      => wc_clean($miny[$i]),
                    'max'      => wc_clean($maxy[$i]),
                );
            }
        }
    }

    update_post_meta($post_id, '_wymiary_obrobki', $wymiary);
}



add_action('woocommerce_before_add_to_cart_button', 'display_sheet_metal_configurator_on_product_page');
function display_sheet_metal_configurator_on_product_page()
{
    global $product;

    if (get_post_meta($product->get_id(), '_obrobka_blachy_enabled', true) !== 'yes') {
        return;
    }

    $attributes = getCartItemData();
    $custom_length = isset($attributes['custom_length']) ? $attributes['custom_length'] : '1000';
    $min_length = get_post_meta($product->get_id(), '_min_length', true) ?: 0;
    $max_length = get_post_meta($product->get_id(), '_max_length', true) ?: 10000;
    $wymiary = get_post_meta($product->get_id(), '_wymiary_obrobki', true);

    if (empty($wymiary) || !is_array($wymiary)) {
        return;
    }
    ?>
    <div class="sheet-metal-configurator" style="margin-bottom: 20px;">
        <div class="configurator-fields" style="display: flex; flex-direction: column; gap: 15px;">
            <div style="flex: 1; min-width: 150px;">
                <label for="custom_length_obrobka" style="display: block; font-weight: 600; margin-bottom: 4px;">
                    Długość (mm):
                </label>
                <input
                    class="calculatePrice"
                    type="number"
                    value="<?= $min_length ?>"
                    id="custom_length_obrobka"
                    value="<?= esc_attr($custom_length) ?>"
                    name="custom_length_obrobka"
                    min="<?= $min_length ?>"
                    max="<?= $max_length ?>"
                    required
                    style="width: 100%;">
            </div>
            <?php foreach ($wymiary as $wymiar) : ?>
                <div class="config-field">
                    <label style=" font-weight: 600;" for="custom_<?php echo esc_attr(sanitize_title($wymiar['nazwa'])); ?>">
                        Wymiar <?php echo esc_html($wymiar['nazwa']); ?> (mm):
                    </label>
                    <input
                        type="number"
                        class="input-text calculatePrice"
                        id="custom_<?php echo esc_attr(sanitize_title($wymiar['nazwa'])); ?>"
                        name="custom_wymiar[<?php echo esc_attr($wymiar['nazwa']); ?>]"
                        value="<?php echo esc_attr($wymiar['domyslna']); ?>"
                        min="<?php echo esc_attr($wymiar['min']); ?>"
                        max="<?php echo esc_attr($wymiar['max']); ?>"
                        step="any"
                        required />
                    <small class="dimension-hint" style="display: block; opacity: 0.7;">
                        (min: <?php echo esc_html($wymiar['min']); ?>, max: <?php echo esc_html($wymiar['max']); ?>)
                    </small>
                </div>
            <?php endforeach; ?>
            <p id="wymiary_error" style="color:red; display:block; margin-top: 4px;"></p>

        </div>
    </div>
<?php
}


add_filter('woocommerce_get_item_data', 'display_sheet_metal_data_in_cart', 10, 2);
function display_sheet_metal_data_in_cart($item_data, $cart_item)
{
    if (isset($cart_item['custom_length_obrobka'])) {
        $item_data[] = array(
            'key'     => __('Długość', 'woocommerce'),
            'value'   => $cart_item['custom_length_obrobka'] . ' mm',
        );
    }
    if (isset($cart_item['custom_wymiar']) && is_array($cart_item['custom_wymiar'])) {
        foreach ($cart_item['custom_wymiar'] as $name => $value) {
            $item_data[] = array(
                'key'     => __('Wymiar', 'woocommerce') . ' ' . esc_html($name),
                'value'   => $value . ' mm',
            );
        }
    }
    return $item_data;
}

add_filter('woocommerce_add_cart_item_data', 'save_sheet_metal_data_to_cart', 10, 3);
function save_sheet_metal_data_to_cart($cart_item_data, $product_id, $variation_id)
{
    if (isset($_POST['custom_length_obrobka']) && isset($_POST['custom_wymiar'])) {
        $cart_item_data['custom_length_obrobka'] = floatval(sanitize_text_field($_POST['custom_length_obrobka']));
        $cart_item_data['custom_wymiar'] = array_map('floatval', $_POST['custom_wymiar']);
        $cart_item_data['unique_key'] = md5(microtime() . rand());
    }

    return $cart_item_data;
}
