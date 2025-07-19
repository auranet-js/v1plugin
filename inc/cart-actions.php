<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/* ------------------------------------------------------------------
   Wyświetlanie cen brutto i netto w kolumnach koszyka
------------------------------------------------------------------ */
add_filter('woocommerce_cart_item_price', 'my_cart_item_price_display', 10, 3);
function my_cart_item_price_display($price_html, $cart_item, $cart_item_key)
{
    $product = $cart_item['data'];
    $price_incl_tax = wc_get_price_including_tax($product, array('qty' => 1));
    if ($product instanceof WC_Product_Variation) {
        $product = wc_get_product($product->get_id());
        $tax_class = $product->get_tax_class();
        $tax_rates = WC_Tax::get_rates_for_tax_class($tax_class);
        $vat_rate = 23;
        if (!empty($tax_rates)) {
            $vat_rate = reset($tax_rates)->tax_rate;
        }
        $vat_multiplier = 1 + ($vat_rate / 100);

        $price_excl_tax = $price_incl_tax / $vat_multiplier;
        $price_excl_tax = $price_incl_tax / $vat_multiplier;
    } else {
        $price_excl_tax = wc_get_price_excluding_tax($product, array('qty' => 1));
    }
    $formatted = sprintf(
        '%s z VAT<br>%s netto',
        wc_price($price_incl_tax),
        wc_price($price_excl_tax)
    );
    return $formatted;
}

add_filter('woocommerce_cart_item_subtotal', 'my_cart_item_subtotal_display', 10, 3);
function my_cart_item_subtotal_display($subtotal, $cart_item, $cart_item_key)
{
    $product = $cart_item['data'];
    $qty = $cart_item['quantity'];
    $price_incl_tax = wc_get_price_including_tax($product, array('qty' => $qty));
    if ($product instanceof WC_Product_Variation) {
        $product = wc_get_product($product->get_id());
        $tax_class = $product->get_tax_class();
        $tax_rates = WC_Tax::get_rates_for_tax_class($tax_class);
        $vat_rate = 23;
        if (!empty($tax_rates)) {
            $vat_rate = reset($tax_rates)->tax_rate;
        }
        $vat_multiplier = 1 + ($vat_rate / 100);
        $price_excl_tax = $price_incl_tax / $vat_multiplier;
    } else {
        $price_excl_tax = wc_get_price_excluding_tax($product, array('qty' => $qty));
    }
    $formatted = sprintf(
        '%s z VAT<br>%s netto',
        wc_price($price_incl_tax),
        wc_price($price_excl_tax)
    );
    return $formatted;
}

//funkcja podaje parametry w koszyku

function victorini_add_dimensions_below_cart_item_name($item_name, $cart_item, $cart_item_key)
{
    if (isset($cart_item['custom_length'], $cart_item['custom_width'])) {
        $length = floatval($cart_item['custom_length']);
        $width = floatval($cart_item['custom_width']);

        // Obliczenie powierzchni w m²
        $area = ($length * $width) / 1000000;

        // Pobranie ceny bazowej produktu
        $product = wc_get_product($cart_item['product_id']);
        $base_price = wc_price($product->get_price());

        // Sprawdzenie, czy produkt liczony jest na m²
        $is_calculated_by_area = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';
        $is_linear = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
        $is_pcv = get_post_meta($product->get_id(), '_pcv_product', true) === 'yes';

        if ($is_calculated_by_area) {
            $item_name .= sprintf(
                '<p style="font-size: 14px; color: #555; margin-top: 5px;">
                    <strong>Wymiary:</strong> %d mm x %d mm (%.2f m² x %s)
                </p>',
                $length,
                $width,
                $area,
                $base_price
            );
        } else if ($is_linear) {
            $item_name .= sprintf(
                '<p style="font-size: 14px; color: #555; margin-top: 5px;">
                    <strong>Wymiary:</strong> %d mm x %d mm
                </p>',
                $length,
                $width
            );
        } else if ($is_pcv) {
            //     $item_id = $cart_item['variation_id'];
            //     $item_object_by_id = wc_get_product( $item_id );
            //     $base_price = $item_object_by_id->get_price();
            //     $item_name .= sprintf(
            //     '<p style="font-size: 14px; color: #555; margin-top: 5px;">
            //         <strong>Wymiary:</strong> %d mm x %d mm (%.2f m² x %s)
            //     </p>',
            //     $length,
            //     $width,
            //     $area,
            //     wc_price($base_price)
            // );
            $item_id = $cart_item['variation_id'];
            $item_object_by_id = wc_get_product($item_id);
            $base_price = $item_object_by_id->get_price();
            $item_name .= sprintf(
                '<p style="font-size: 14px; color: #555; margin-top: 5px;">
                    <strong>Wymiary:</strong> %d mm x %d mm
                </p>',
                $length,
                $width,
                // $area,
                // wc_price($base_price)
            );
        }
    }

    return $item_name;
}
add_filter('woocommerce_cart_item_name', 'victorini_add_dimensions_below_cart_item_name', 10, 3);

// Dodajemy przyciski Edytuj | Duplikuj | Usuń przy pozycji koszyka
function victorini_add_cart_item_action_links($cart_item, $cart_item_key)
{
    if (is_cart()) {
        $base_url = wc_get_cart_url();
        $product_id = $cart_item['product_id'];
        $product_url = get_permalink($product_id);

        // Link "Edytuj" – przekierowujemy do strony produktu z parametrem do edycji
        $edit_url = add_query_arg(array(
            'victorini_action' => 'edit_cart_item',
            'cart_item_key' => $cart_item_key,
            '_wpnonce' => wp_create_nonce('victorini_edit_' . $cart_item_key)
        ), $product_url);

        // Link "Duplikuj" – generujemy URL do duplikowania pozycji (przekierowanie na stronę koszyka)
        $duplicate_url = add_query_arg(array(
            'victorini_action' => 'duplicate_cart_item',
            'cart_item_key' => $cart_item_key,
            '_wpnonce' => wp_create_nonce('victorini_duplicate_' . $cart_item_key)
        ), $base_url);

        // Link "Usuń" – generujemy URL do usuwania pozycji (przekierowanie na stronę koszyka)
        $remove_url = add_query_arg(array(
            'victorini_action' => 'remove_cart_item',
            'cart_item_key' => $cart_item_key,
            '_wpnonce' => wp_create_nonce('victorini_remove_' . $cart_item_key)
        ), $base_url);

        echo '<div class="victorini-cart-actions" style="margin-top:5px;">';
        echo '<a class="victorini-edit-cart" href="' . esc_url($edit_url) . '">Edytuj</a> | ';
        echo '<a class="victorini-duplicate-cart" href="' . esc_url($duplicate_url) . '">Duplikuj</a> | ';
        echo '<a class="victorini-remove-cart" href="' . esc_url($remove_url) . '">Usuń</a>';
        echo '</div>';
    }
}
add_action('woocommerce_after_cart_item_name', 'victorini_add_cart_item_action_links', 10, 2);


// Obsługa akcji Duplikuj i Usuń w hooku template_redirect
function victorini_handle_cart_actions()
{
    if (empty($_GET['victorini_action']) || empty($_GET['cart_item_key'])) {
        return;
    }

    $action = sanitize_text_field($_GET['victorini_action']);
    $cart_item_key = sanitize_text_field($_GET['cart_item_key']);
    $cart = WC()->cart;

    if (!isset($cart->cart_contents[$cart_item_key])) {
        return;
    }

    // Przełączamy się w zależności od żądanej akcji
    switch ($action) {
        case 'duplicate_cart_item':
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'victorini_duplicate_' . $cart_item_key)) {
                return;
            }
            // Pobieramy dane pozycji koszyka
            $item = $cart->cart_contents[$cart_item_key];
            // Dodajemy nową pozycję z takimi samymi danymi
            $cart->add_to_cart($item['product_id'], $item['quantity'], isset($item['variation_id']) ? $item['variation_id'] : 0, isset($item['variation']) ? $item['variation'] : array(), $item);
            break;

        case 'remove_cart_item':
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'victorini_remove_' . $cart_item_key)) {
                return;
            }
            // Usuwamy pozycję z koszyka
            $cart->remove_cart_item($cart_item_key);
            break;
    }
}
add_action('template_redirect', 'victorini_handle_cart_actions');

add_action('template_redirect', 'victorini_handle_edit_redirect');
function victorini_handle_edit_redirect()
{
    // Jeżeli nie ma parametru lub to nie jest strona koszyka, nic nie robimy.
    // (albo możesz w ogóle pominąć to sprawdzenie, jeśli wystarczy Ci weryfikacja w następnym kroku)
    if (empty($_GET['victorini_action']) || $_GET['victorini_action'] !== 'edit_cart_item') {
        return;
    }
    // Weryfikacja nonce, klucza koszyka, itp.
    // ...
    // Niczego tu nie usuwamy, nie zmieniamy – tylko sprawdzamy uprawnienia.
    // Po tym użytkownik i tak przejdzie na stronę produktu z parametrami w URL.
}


add_action('wp', 'victorini_maybe_change_add_to_cart_label');
function victorini_maybe_change_add_to_cart_label()
{
    if (!is_product()) {
        return;
    }
    if (
        !empty($_GET['victorini_action'])
        && $_GET['victorini_action'] === 'edit_cart_item'
        && !empty($_GET['cart_item_key'])
    ) {
        // Jesteśmy w trybie edycji
        add_filter('woocommerce_product_single_add_to_cart_text', function ($text) {
            return __('Aktualizuj', 'victorini2025-by-auranet');
        });
    }
}


add_action('wp', 'victorini_prepopulate_custom_fields');
function victorini_prepopulate_custom_fields()
{
    if (!is_product()) {
        return;
    }
    if (
        empty($_GET['victorini_action']) ||
        ($_GET['victorini_action'] !== 'edit_cart_item' && $_GET['victorini_action'] !== 'duplicate_cart_item') ||
        empty($_GET['cart_item_key'])
    ) {
        return;
    }

    $cart_item_key = sanitize_text_field($_GET['cart_item_key']);
    $cart = WC()->cart;
    if (empty($cart->cart_contents[$cart_item_key])) {
        return;
    }
    $item = $cart->cart_contents[$cart_item_key];

    // Opcjonalnie logujemy dane (masz już logi w debug.log)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('== EDYCJA/DUPOLIKACJA POZYCJI W KOSZYKU ==');
        error_log(print_r($item, true));
    }

    // Przekazujemy cały obiekt danych do JS – dzięki temu mamy uniwersalny dostęp do wszystkich parametrów
    add_action('wp_enqueue_scripts', function () use ($item) {
        wp_localize_script('custom-price-calculator', 'victoriniEditData', array(
            'editMode' => true,
            'savedData' => $item,
        ));
    });
}


add_action('wp', 'victorini_add_hidden_input_for_edit');
function victorini_add_hidden_input_for_edit()
{
    if (!is_product())
        return;

    if (
        !empty($_GET['victorini_action']) && $_GET['victorini_action'] === 'edit_cart_item' &&
        !empty($_GET['cart_item_key'])
    ) {
        $cart_item_key = sanitize_text_field($_GET['cart_item_key']);

        // Wpinamy się w hook, żeby wstawić hidden input do formularza
        add_action('woocommerce_before_add_to_cart_button', function () use ($cart_item_key) {
            echo '<input type="hidden" name="victorini_edit" value="' . esc_attr($cart_item_key) . '" />';
        });
    }
}

add_filter('woocommerce_add_cart_item_data', 'victorini_replace_old_cart_item', 10, 3);
function victorini_replace_old_cart_item($cart_item_data, $product_id, $variation_id)
{
    if (empty($_POST['victorini_edit'])) {
        // To nie jest edycja, więc nic nie robimy
        return $cart_item_data;
    }

    $old_cart_item_key = sanitize_text_field($_POST['victorini_edit']);
    $cart = WC()->cart;

    // Usuń starą pozycję z koszyka
    if (isset($cart->cart_contents[$old_cart_item_key])) {
        $cart->remove_cart_item($old_cart_item_key);
    }

    // Teraz normalnie przechwytujemy dane z $_POST (custom_length, custom_width, itp.)
    if (isset($_POST['custom_length'])) {
        $cart_item_data['custom_length'] = sanitize_text_field($_POST['custom_length']);
    }
    if (isset($_POST['custom_width'])) {
        $cart_item_data['custom_width'] = sanitize_text_field($_POST['custom_width']);
    }
    // i tak dalej...

    // Jeśli obsługujesz pliki
    if (!empty($_FILES['custom_attachment']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($_FILES['custom_attachment'], array('test_form' => false));
        if (isset($uploaded['url'])) {
            $cart_item_data['custom_attachment'] = $uploaded['url'];
        }
    } else {
        // Jeśli nie przesłano nowego pliku, można zachować stary (jeśli chcesz)
        if (isset($cart->cart_contents[$old_cart_item_key]['custom_attachment'])) {
            $cart_item_data['custom_attachment'] = $cart->cart_contents[$old_cart_item_key]['custom_attachment'];
        }
    }

    return $cart_item_data;
}


//logowanie
// Funkcja logująca dane produktu podczas dodawania do koszyka
function victorini_log_cart_item_data($cart_item_data, $product_id, $variation_id)
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('== DODAWANIE DO KOSZYKA ==');
        error_log(print_r($cart_item_data, true));
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'victorini_log_cart_item_data', 99, 3);

function victorini_log_edit_cart_item()
{
    if (!empty($_GET['victorini_action']) && $_GET['victorini_action'] === 'edit_cart_item' && !empty($_GET['cart_item_key'])) {
        $cart_item_key = sanitize_text_field($_GET['cart_item_key']);
        $cart = WC()->cart;
        if (isset($cart->cart_contents[$cart_item_key])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('== EDYCJA POZYCJI W KOSZYKU ==');
                error_log(print_r($cart->cart_contents[$cart_item_key], true));
            }
        }
    }
}
add_action('wp', 'victorini_log_edit_cart_item');


/* ------------------------------------------------------------------
   Mini‑koszyk / off‑canvas – pokaż brutto i netto z dopłatą
------------------------------------------------------------------ */
add_filter('woocommerce_widget_cart_item_quantity', 'victorini_widget_cart_price_with_vat', 20, 3);

function victorini_widget_cart_price_with_vat($html, $cart_item, $cart_item_key)
{

    $qty = max(1, $cart_item['quantity']);          // ilość
    $net_unit = $cart_item['line_total'] / $qty;           // netto 1 szt.
    $tax_unit = $cart_item['line_tax'] / $qty;           // VAT 1 szt.
    $gross_unit = $net_unit + $tax_unit;                     // brutto 1 szt.

    // budujemy nowy fragment „X × Cena z VAT / netto”
    $html = sprintf(
        '%1$d × %2$s z VAT<br>%3$s netto',
        $qty,
        wc_price($gross_unit),
        wc_price($net_unit)
    );

    if (!empty($cart_item['cutting_required']) && '1' === $cart_item['cutting_required']) {
        $html .= '<br><small>w tym docięcie 15&nbsp;zł</small>';
    }
    return $html;
}

/* 1) kolumna Price (1 sztuka) */
add_filter('woocommerce_cart_item_price', 'victorini_add_cut_note_price', 20, 3);

function victorini_add_cut_note_price($html, $item, $key)
{

    if (!empty($item['cutting_required']) && '1' === $item['cutting_required']) {
        $html .= '<br><small>w tym docięcie 15&nbsp;zł</small>';
    }
    return $html;
}

/* 2) kolumna Subtotal (qty × cena) */
add_filter('woocommerce_cart_item_subtotal', 'victorini_add_cut_note_subtotal', 20, 3);

function victorini_add_cut_note_subtotal($html, $item, $key)
{

    if (!empty($item['cutting_required']) && '1' === $item['cutting_required']) {
        $html .= '<br><small>w tym docięcie 15&nbsp;zł</small>';
    }
    return $html;
}