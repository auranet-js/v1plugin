<?php


add_action('product_cat_add_form_fields', 'kapisony_add_price_field');
function kapisony_add_price_field()
{
?>
    <div class="form-field">
        <label for="kapinosy_price_per_m"><?php _e('Cena kapinosów za mb', 'woocommerce'); ?></label>
        <input type="number" step="0.01" name="kapinosy_price_per_m" id="kapinosy_price_per_m" value="">
        <p class="description"><?php _e('Cena za metr bieżący dla kapinosów w tej kategorii', 'woocommerce'); ?></p>
    </div>

    <div class="form-field">
        <label for="min_price"><?php _e('Minimalna cena elementu', 'woocommerce'); ?></label>
        <input type="number" step="0.01" min="0" name="min_price" id="min_price" value="">
        <p class="description"><?php _e('Minimalna cena pojedynczego elementu', 'woocommerce'); ?></p>
    </div>
<?php
}

add_action('product_cat_edit_form_fields', 'kapisony_edit_price_field', 10, 2);
function kapisony_edit_price_field($term)
{
    $price = get_term_meta($term->term_id, 'kapinosy_price_per_m', true);
    $min_price = get_term_meta($term->term_id, 'min_price', true);
?>
    <tr class="form-field">
        <th scope="row"><label for="kapinosy_price_per_m"><?php _e('Cena kapinosów za mb', 'woocommerce'); ?></label></th>
        <td>
            <input type="number" step="0.01" name="kapinosy_price_per_m" id="kapinosy_price_per_m" value="<?php echo esc_attr($price); ?>">
            <p class="description"><?php _e('Cena za metr bieżący dla kapinosów w tej kategorii', 'woocommerce'); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="min_price"><?php _e('Minimalna cena elementu', 'woocommerce'); ?></label></th>
        <td>
            <input type="number" step="0.01" min="0" name="min_price" id="min_price" value="<?php echo esc_attr($min_price); ?>">
            <p class="description"><?php _e('Minimalna cena pojedynczego elementu', 'woocommerce'); ?></p>
        </td>
    </tr>
<?php
}


add_action('created_product_cat', 'save_kapisony_price');
add_action('edited_product_cat', 'save_kapisony_price');
function save_kapisony_price($term_id)
{
    if (isset($_POST['kapinosy_price_per_m'])) {
        update_term_meta(
            $term_id,
            'kapinosy_price_per_m',
            sanitize_text_field($_POST['kapinosy_price_per_m'])
        );
    }

    if (isset($_POST['min_price'])) {
        update_term_meta(
            $term_id,
            'min_price',
            sanitize_text_field($_POST['min_price'])
        );
    }
}
