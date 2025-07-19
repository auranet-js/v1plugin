<?php
if (!defined('ABSPATH')) {
    exit;
}

function victorini2025_register_settings_page() {
    add_menu_page(
        'Ustawienia Victorini2025',
        'Ustawienia Victorini2025',
        'manage_options',
        'victorini-settings',
        'victorini2025_render_settings_page',
        'dashicons-admin-generic',
        56
    );
}
add_action('admin_menu', 'victorini2025_register_settings_page');

function victorini2025_render_settings_page() {
    if (
        isset($_POST['victorini_settings_nonce']) &&
        wp_verify_nonce($_POST['victorini_settings_nonce'], 'victorini_save_settings')
    ) {
        $options = [
            'victorini_length_limit' => intval($_POST['victorini_length_limit'] ?? 2500),
            'victorini_blocked_method_id' => sanitize_text_field($_POST['victorini_blocked_method_id'] ?? ''),
            'victorini_cart_threshold' => floatval($_POST['victorini_cart_threshold'] ?? 0),
            'victorini_blocked_methods' => sanitize_text_field($_POST['victorini_blocked_methods'] ?? ''),
            'victorini_big_categories' => sanitize_textarea_field($_POST['victorini_big_categories'] ?? ''),
            'victorini_blocked_materials' => sanitize_textarea_field($_POST['victorini_blocked_materials'] ?? ''),
            'victorini_error_email' => sanitize_email($_POST['victorini_error_email'] ?? ''),
            'victorini_upload_limit' => intval($_POST['victorini_upload_limit'] ?? 10),
        ];
        foreach ($options as $key => $value) {
            update_option($key, $value);
        }
        echo '<div class="updated"><p>Ustawienia zapisane.</p></div>';
    }

    $values = [
        'victorini_length_limit' => get_option('victorini_length_limit', 2500),
        'victorini_blocked_method_id' => get_option('victorini_blocked_method_id', 'flat_rate:7'),
        'victorini_cart_threshold' => get_option('victorini_cart_threshold', 1000),
        'victorini_blocked_methods' => get_option('victorini_blocked_methods', 'flat_rate:1,flat_rate:5'),
        'victorini_big_categories' => get_option('victorini_big_categories', 'blaty-kuchenne,blaty-lazienkowe,parapety-wewnetrzne,parapety-zewnetrzne,schody'),
        'victorini_blocked_materials' => get_option('victorini_blocked_materials', 'granit,konglomerat-marmurowy'),
        'victorini_error_email' => get_option('victorini_error_email', get_option('admin_email')),
        'victorini_upload_limit' => get_option('victorini_upload_limit', 10),
    ];
    ?>
    <div class="wrap">
        <h1>Ustawienia Victorini2025</h1>
        <form method="post">
            <?php wp_nonce_field('victorini_save_settings', 'victorini_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="victorini_length_limit">Limit długości (mm)</label></th>
                    <td><input name="victorini_length_limit" id="victorini_length_limit" type="number" value="<?php echo esc_attr($values['victorini_length_limit']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="victorini_blocked_method_id">Blokowane ID metody</label></th>
                    <td><input name="victorini_blocked_method_id" id="victorini_blocked_method_id" type="text" value="<?php echo esc_attr($values['victorini_blocked_method_id']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="victorini_cart_threshold">Próg koszyka (zł)</label></th>
                    <td><input name="victorini_cart_threshold" id="victorini_cart_threshold" type="number" step="0.01" value="<?php echo esc_attr($values['victorini_cart_threshold']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="victorini_blocked_methods">Metody blokowane powyżej progu (CSV)</label></th>
                    <td><input name="victorini_blocked_methods" id="victorini_blocked_methods" type="text" value="<?php echo esc_attr($values['victorini_blocked_methods']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="victorini_big_categories">Duże kategorie (slug, CSV)</label></th>
                    <td><textarea name="victorini_big_categories" id="victorini_big_categories" rows="3" class="large-text"><?php echo esc_textarea($values['victorini_big_categories']); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="victorini_blocked_materials">Blokowane materiały (slug, CSV)</label></th>
                    <td><textarea name="victorini_blocked_materials" id="victorini_blocked_materials" rows="3" class="large-text"><?php echo esc_textarea($values['victorini_blocked_materials']); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="victorini_error_email">E-mail błędów</label></th>
                    <td><input name="victorini_error_email" id="victorini_error_email" type="email" value="<?php echo esc_attr($values['victorini_error_email']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="victorini_upload_limit">Limit uploadu (MB)</label></th>
                    <td><input name="victorini_upload_limit" id="victorini_upload_limit" type="number" value="<?php echo esc_attr($values['victorini_upload_limit']); ?>" class="small-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
