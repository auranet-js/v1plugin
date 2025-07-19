<?php
// 1. Dodanie pola wyboru "Kupujesz jako"
add_action('woocommerce_before_checkout_billing_form', function () {
    ?>
    <div class="kupujacy-jako-wrapper">
        <h3>Kupujesz jako</h3>
        <label><input type="radio" name="typ_klienta" value="osoba" checked> Osoba prywatna</label>
        <label><input type="radio" name="typ_klienta" value="firma"> Firma</label>
    </div>
    <?php
});

// 2. Dodanie pól: NIP i Nazwa firmy
add_filter('woocommerce_checkout_fields', function ($fields) {
    // Pole NIP (dla firmy)
    $fields['billing']['billing_nip'] = [
        'label'       => 'NIP',
        'required'    => false,
        'class'       => ['form-row-wide'],
        'priority'    => 10,
    ];

    // Pole Nazwa firmy (dla firmy)
    $fields['billing']['billing_company']['label'] = 'Nazwa firmy';
    $fields['billing']['billing_company']['priority'] = 11;

    return $fields;
}, 20);

// 3. Zapisujemy dodatkowe pole (NIP) do zamówienia
add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    if (!empty($_POST['billing_nip'])) {
        update_post_meta($order_id, '_billing_nip', sanitize_text_field($_POST['billing_nip']));
    }
    if (!empty($_POST['typ_klienta'])) {
        update_post_meta($order_id, 'typ_klienta', sanitize_text_field($_POST['typ_klienta']));
    }
});

// 4. Wyświetlamy NIP w szczegółach zamówienia (panel admina)
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $nip = get_post_meta($order->get_id(), '_billing_nip', true);
    if ($nip) {
        echo '<p><strong>NIP:</strong> ' . esc_html($nip) . '</p>';
    }
});

// 5. JavaScript do dynamicznego ukrywania/pokazywania pól
add_action('wp_footer', function () {
    if (!is_checkout()) return;
    ?>
    <script>
        jQuery(function($){
            function toggleFields() {
                const selected = $('input[name="typ_klienta"]:checked').val();
                if (selected === 'firma') {
                    $('#billing_nip_field, #billing_company_field').show();
                    $('.woocommerce-billing-fields h3:contains("Dane do faktury")').show();
                } else {
                    $('#billing_nip_field, #billing_company_field').hide();
                    $('.woocommerce-billing-fields h3:contains("Dane do faktury")').hide();
                }
            }

            toggleFields(); // pierwsze uruchomienie
            $('input[name="typ_klienta"]').on('change', toggleFields);
        });
    </script>
    <style>
        .kupujacy-jako-wrapper {
            margin-bottom: 20px;
        }
        .kupujacy-jako-wrapper label {
            display: inline-block;
            margin-right: 20px;
        }
    </style>
    <?php
});
