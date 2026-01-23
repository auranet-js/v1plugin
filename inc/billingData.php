<?php 
add_action('show_user_profile', 'vc_add_company_nip_fields');
add_action('edit_user_profile',  'vc_add_company_nip_fields');
function vc_add_company_nip_fields( WP_User $user ) {
    $company = get_user_meta($user->ID, 'billing_company', true);
    $nip     = get_user_meta($user->ID, 'billing_nip', true);
    ?>
    <h2><?php esc_html_e('Dane rozliczeniowe', 'your-textdomain'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="billing_company"><?php esc_html_e('Nazwa firmy', 'your-textdomain'); ?></label></th>
            <td>
                <input type="text"
                       name="billing_company"
                       id="billing_company"
                       class="regular-text"
                       value="<?php echo esc_attr($company); ?>"
                       autocomplete="organization" />
            </td>
        </tr>
        <tr>
            <th><label for="billing_nip"><?php esc_html_e('NIP', 'your-textdomain'); ?></label></th>
            <td>
                <input type="text"
                       name="billing_nip"
                       id="billing_nip"
                       class="regular-text"
                       value="<?php echo esc_attr($nip); ?>"
                       autocomplete="off" />
            </td>
        </tr>
    </table>
    <?php
}

add_action('personal_options_update', 'vc_save_company_nip_fields');
add_action('edit_user_profile_update', 'vc_save_company_nip_fields');
function vc_save_company_nip_fields( $user_id ) {
    if ( ! current_user_can('edit_user', $user_id) ) {
        return;
    }
    if ( isset($_POST['billing_company']) ) {
        update_user_meta($user_id, 'billing_company', sanitize_text_field($_POST['billing_company']));
    }
    if ( isset($_POST['billing_nip']) ) {
        $nip = preg_replace('/[^0-9A-Za-z]/', '', wp_unslash($_POST['billing_nip']));
        update_user_meta($user_id, 'billing_nip', $nip);
    }
}
/**
 * Pola firma + NIP w edycji konta WooCommerce
 */

// 1) Wyświetlenie pól nad formularzem zmiany hasła
add_action('woocommerce_edit_account_form_start', function () {
    $uid     = get_current_user_id();
    $company = get_user_meta($uid, 'billing_company', true);
    $nip     = get_user_meta($uid, 'billing_nip', true);
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="billing_company"><?php esc_html_e('Nazwa firmy','your-td'); ?></label>
        <input type="text"
               class="woocommerce-Input input-text"
               name="billing_company"
               id="billing_company"
               value="<?php echo esc_attr($company); ?>">
    </p>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="billing_nip"><?php esc_html_e('NIP','your-td'); ?></label>
        <input type="text"
               class="woocommerce-Input input-text"
               name="billing_nip"
               id="billing_nip"
               value="<?php echo esc_attr($nip); ?>">
    </p>
    <?php
});

// 2) Walidacja (opcjonalna – możesz pominąć)
add_action('woocommerce_save_account_details_errors', function ( $errors, $user ) {
    if ( isset($_POST['billing_nip']) && $_POST['billing_nip'] !== '' ) {
        $nip = preg_replace('/\D+/', '', wp_unslash($_POST['billing_nip']));
        if ( strlen($nip) !== 10 ) {
            $errors->add('billing_nip_error', __('Podaj poprawny NIP (10 cyfr).','your-td'));
        }
    }
}, 10, 2);

// 3) Zapis pól po submit
add_action('woocommerce_save_account_details', function ( $user_id ) {
    if ( isset($_POST['billing_company']) ) {
        update_user_meta(
            $user_id,
            'billing_company',
            sanitize_text_field(wp_unslash($_POST['billing_company']))
        );
    }
    if ( isset($_POST['billing_nip']) ) {
        $nip = preg_replace('/\D+/', '', wp_unslash($_POST['billing_nip']));
        update_user_meta($user_id, 'billing_nip', $nip);
    }
});

/** 1) Pola w CHECKOUT (po firmie) */
add_filter('woocommerce_checkout_fields', function ($fields) {
    // Upewnij się, że firma jest dostępna
    if (empty($fields['billing']['billing_company'])) {
        $fields['billing']['billing_company'] = [
            'label'    => __('Nazwa firmy', 'victorini2025-by-auranet'),
            'required' => false,
            'class'    => ['form-row-wide'],
            'priority' => 40,
        ];
    } else {
        $fields['billing']['billing_company']['priority'] = 40;
    }

    // NIP po firmie
    $fields['billing']['billing_nip'] = [
        'label'       => __('NIP', 'victorini2025-by-auranet'),
        'required'    => false,
        'class'       => ['form-row-wide'],
        'priority'    => 45,
        'clear'       => true,
        'autocomplete'=> 'off',
    ];

    return $fields;
}, 10);

add_action('woocommerce_checkout_create_order', function ($order, $data) {
    if (isset($data['billing_company'])) {
        $order->update_meta_data('_billing_company', sanitize_text_field($data['billing_company']));
    }
    if (isset($data['billing_nip'])) {
        $nip = preg_replace('/\D+/', '', (string)$data['billing_nip']);
        $order->update_meta_data('_billing_nip', $nip);
    }
}, 10, 2);

add_action('woocommerce_checkout_update_user_meta', function ($customer_id, $posted) {
    if (!$customer_id) return;
    if (isset($posted['billing_company'])) {
        update_user_meta($customer_id, 'billing_company', sanitize_text_field($posted['billing_company']));
    }
    if (isset($posted['billing_nip'])) {
        $nip = preg_replace('/\D+/', '', (string)$posted['billing_nip']);
        update_user_meta($customer_id, 'billing_nip', $nip);
    }
}, 10, 2);

add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $company = $order->get_meta('_billing_company');
    $nip     = $order->get_meta('_billing_nip');
    if ($company) {
        echo '<p><strong>' . esc_html__('Nazwa firmy', 'victorini2025-by-auranet') . ':</strong> ' . esc_html($company) . '</p>';
    }
    if ($nip) {
        echo '<p><strong>' . esc_html__('NIP', 'victorini2025-by-auranet') . ':</strong> ' . esc_html($nip) . '</p>';
    }
});

add_filter('woocommerce_email_customer_details_fields', function ($fields, $sent_to_admin, $order) {
    $company = $order->get_meta('_billing_company');
    $nip     = $order->get_meta('_billing_nip');

    if ($company) {
        $fields['billing_company'] = [
            'label' => __('Nazwa firmy', 'victorini2025-by-auranet'),
            'value' => $company,
        ];
    }
    if ($nip) {
        $fields['billing_nip'] = [
            'label' => __('NIP', 'victorini2025-by-auranet'),
            'value' => $nip,
        ];
    }
    return $fields;
}, 10, 3);

add_filter('woocommerce_billing_fields', function ($fields) {
    // jeśli ktoś edytuje adres: pokaż NIP po firmie
    $fields['billing_nip'] = [
        'label'       => __('NIP', 'victorini2025-by-auranet'),
        'required'    => false,
        'class'       => ['form-row-wide'],
        'priority'    => 45,
        'clear'       => true,
    ];
    // firma ma być przed NIP
    if (isset($fields['billing_company'])) {
        $fields['billing_company']['priority'] = 40;
    }
    return $fields;
}, 10);