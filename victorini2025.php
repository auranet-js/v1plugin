<?php
/**
 * Plugin Name: Victorini2025 by Auranet
 * Description: Plugin przenoszący część funkcji z functions.php do pluginu, łącznie z obsługą kalkulatora cen oraz modyfikacjami WooCommerce.
 * Version: 1.2
 * Author: Auranet
 * Text Domain: victorini2025-by-auranet
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/* ------------------------------------------------------------------
 * 1.  ŁADOWANIE PLIKÓW POMOCNICZYCH
 * ------------------------------------------------------------------ */

$plugin_dir = plugin_dir_path(__FILE__);

// Kalkulator cen i pola wymiarowe
$custom_pricing = $plugin_dir . 'woocommerce/custom-pricing.php';
if (file_exists($custom_pricing)) {
    require_once $custom_pricing;
}

require_once $plugin_dir . 'inc/includedProducts.php';
require_once $plugin_dir . 'inc/linearPriceProduct.php';
require_once $plugin_dir . 'inc/areaProduct.php';
require_once $plugin_dir . 'inc/generate-product-pdf.php';
require_once $plugin_dir . 'inc/variation-price-html.php';
require_once $plugin_dir . 'inc/order-email-template.php';
require_once $plugin_dir . 'inc/cart-actions.php';
require_once $plugin_dir . 'inc/wholesale-discounts.php';
require_once $plugin_dir . 'inc/upload-file.php';
require_once $plugin_dir . 'inc/pcvProduct.php';
require_once $plugin_dir . 'inc/kapinosyAndMinimalPricing.php';
require_once $plugin_dir . 'inc/obrobkaBlachyRepeater.php';
require_once $plugin_dir . 'inc/countertopPersonalization.php';
// require_once $plugin_dir . 'inc/edit-cart-item.php';

/* ------------------------------------------------------------------
 * 2.  STYLE + OGÓLNY JS (ŁADOWANE WSZĘDZIE)
 * ------------------------------------------------------------------ */

function victorini2025_enqueue_assets()
{

    $url = plugin_dir_url(__FILE__);

    // CSS
    wp_enqueue_style(
        'victorini2025-style',
        $url . 'style/style.css',
        [],
        '1.0.0',
        'all'
    );

    // Ogólny skrypt front-endu
    wp_enqueue_script(
        'victorini2025-script',
        $url . 'js/script.js',
        ['jquery'],
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'victorini2025_enqueue_assets');
add_action('admin_enqueue_scripts', 'victorini2025_enqueue_assets'); // jeśli potrzebujesz w kokpicie

/* ------------------------------------------------------------------
 * 3.  BLOCK-SHIPPING.JS – ŁADUJEMY TYLKO W CHECKOUT i CART
 * ------------------------------------------------------------------ */

function victorini2025_enqueue_block_shipping()
{

    if (!is_checkout() && !is_cart()) {
        return;                                           // tylko strona zamówienia
    }

    $handle = 'victorini-block-shipping';

    wp_register_script(
        $handle,
        plugin_dir_url(__FILE__) . 'js/block-shipping.js',
        ['jquery'],
        '2.1',
        true
    );

    wp_localize_script(
        $handle,
        'victoriniBlock',
        [
            'hasOversize' => victorini_cart_has_oversize(),
            'hasRestrictCat' => victorini_cart_has_restricted_material(),
            'blockedId' => 'flat_rate:7',
            'cartValue' => WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax(),
        ]
    );

    wp_enqueue_script($handle);
}
add_action('wp_enqueue_scripts', 'victorini2025_enqueue_block_shipping');

/* ------------------------------------------------------------------
 * 4.  ATRYBUT KOLOR (HEX W OPISIE)
 * ------------------------------------------------------------------ */

add_filter('your_taxonomy_filter_item_html', function ($html, $term) {
    $hex = $term->description; // HEX w opisie
    return str_replace(
        '<a ',
        '<a data-color="' . esc_attr($hex) . '" ',
        $html
    );
}, 10, 2);

/* ------------------------------------------------------------------
 * 5.  POMOCNICZE – KOSZYK
 * ------------------------------------------------------------------ */

/** Czy w koszyku jest produkt > 2500 mm? */
function victorini_cart_has_oversize(): bool
{
    foreach (WC()->cart->get_cart() as $item) {
        $itemLength = $item['custom_length'] ?? $item['custom_length_obrobka'] ?? '';
        if (!empty($itemLength) && (int) $itemLength > 2500) {
            return true;
        }
    }
    return false;
}

/* ------------------------------------------------------------------
 * 6.  FILTR STAWEK WYSYŁKI
 * ------------------------------------------------------------------ */

/**
 * Czy w koszyku jest produkt z “wrażliwej” kategorii?
 */
function victorini_cart_has_restricted_material(): bool
{

    /* slug-i kategorii „dużych” */
    $root_cats = [
        'blaty-kuchenne',
        'blaty-lazienkowe',
        'parapety-wewnetrzne',
        'parapety-zewnetrzne',
        'schody',
    ];

    /* slug-i blokowanych materiałów */
    $blocked_mat = ['granit', 'konglomerat-marmurowy'];

    foreach (WC()->cart->get_cart() as $item) {

        $parent_id = $item['product_id'];                 // rodzic zawsze ma kategorie
        $terms = get_the_terms($parent_id, 'product_cat');

        if (!$terms || is_wp_error($terms)) {
            continue;
        }

        /* -------- 1. sprawdź kategorie + ich przodków -------- */
        $in_restricted_cat = false;

        foreach ($terms as $term) {

            // sprawdzamy sam term
            if (in_array($term->slug, $root_cats, true)) {
                $in_restricted_cat = true;
                break;
            }

            // …i wszystkich przodków
            $ancestors = get_ancestors($term->term_id, 'product_cat');
            foreach ($ancestors as $aid) {
                $ancestor = get_term($aid, 'product_cat');
                if ($ancestor && in_array($ancestor->slug, $root_cats, true)) {
                    $in_restricted_cat = true;
                    break 2;   // mamy dopasowanie – wychodzimy
                }
            }
        }

        if (!$in_restricted_cat) {
            continue;   // produkt nie w “dużej” kategorii → następny
        }

        /* -------- 2. sprawdź materiał -------- */
        // a) atrybut wariacji w pozycji koszyka
        $attr = $item['variation']['attribute_pa_material']
            ?? $item['variation']['pa_material']
            ?? null;

        if ($attr && in_array(wc_sanitize_taxonomy_name($attr), $blocked_mat, true)) {
            return true;
        }

        // b) term pa_material przypięty do produktu
        if (has_term($blocked_mat, 'pa_material', $parent_id)) {
            return true;
        }
    }
    return false;   // żadna pozycja nie spełniła obu wymagań
}

add_filter('woocommerce_package_rates', function ($rates, $package) {

    /* --- 1. Długi towar (> 2500 mm) --- */
    $needs_manual_shipping = victorini_cart_has_oversize() || victorini_cart_has_restricted_material();

    if ($needs_manual_shipping && isset($rates['flat_rate:7'])) {
        $rate = $rates['flat_rate:7'];

        // dopisek w labelu
        $rate->set_label($rate->get_label() . '');
    }

    /* --- 2. Wartość koszyka > 1000 zł (same produkty) --- */
    if ((WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax()) > 1000) {
        foreach (['flat_rate:1', 'flat_rate:5'] as $blocked) {
            unset($rates[$blocked]);
        }
    }

    return $rates;
}, 20, 2);

/* ------------------------------------------------------------------
 * 7.  ENDPOINT: /?wc-ajax=victorini_get_cart_items
 * ------------------------------------------------------------------ */

add_action('wc_ajax_victorini_get_cart_items', 'victorini_get_cart_items');
add_action('wc_ajax_nopriv_victorini_get_cart_items', 'victorini_get_cart_items');

function victorini_get_cart_items()
{

    $items = [];

    foreach (WC()->cart->get_cart() as $cart_item) {
        /** @var WC_Product $product */
        $product = $cart_item['data'];

        $items[] = [
            'product_id' => $product->get_id(),
            'name' => $product->get_name(),
            'qty' => $cart_item['quantity'],
            'length' => $cart_item['custom_length'] ?? $cart_item['custom_length_obrobka'] ?? null,
        ];
    }

    wp_send_json($items);
}

/* ------------------------------------------------------------------
 * 8.  ENDPOINT: /?wc-ajax=victorini_get_shipping_methods
 * ------------------------------------------------------------------ */

add_action('wc_ajax_victorini_get_shipping_methods', 'victorini_get_shipping_methods');
add_action('wc_ajax_nopriv_victorini_get_shipping_methods', 'victorini_get_shipping_methods');

function victorini_get_shipping_methods()
{

    WC()->cart->calculate_totals();
    WC()->shipping->calculate_shipping(WC()->cart->get_shipping_packages());

    $packages = WC()->shipping->get_packages();
    $out = [];

    foreach ($packages as $index => $package) {

        $rates_out = [];

        foreach ($package['rates'] as $rate_id => $rate) {
            /** @var WC_Shipping_Rate $rate */
            $rates_out[] = [
                'rate_id' => $rate_id,
                'label' => $rate->get_label(),
                'cost' => wc_format_localized_price($rate->get_cost()),
                'method_id' => $rate->get_method_id(),
                'instance_id' => $rate->get_instance_id(),
                'taxable' => $rate->get_tax_status(),
            ];
        }

        $out[] = [
            'package_index' => $index,
            'destination' => $package['destination'],
            'rates' => $rates_out,
        ];
    }

    wp_send_json($out);
}

/* ------------------------------------------------------------------
 * 9.  GETCARTITEMDATA – UŻYWASZ W INNYM MIEJSCU
 * ------------------------------------------------------------------ */

function getCartItemData()
{
    $current_file_url = '';
    $attributes = [];

    if (
        isset($_GET['victorini_action'], $_GET['cart_item_key'])
        && $_GET['victorini_action'] === 'edit_cart_item'
    ) {

        $cart_item_key = sanitize_text_field($_GET['cart_item_key']);
        $cart = WC()->cart->get_cart();

        if (isset($cart[$cart_item_key])) {
            $cart_item = $cart[$cart_item_key];
            $custom_length = $cart_item['custom_length'] ?? $cart_item['custom_length_obrobka'] ?? '';
            $custom_width = $cart_item['custom_width'] ?? '';
            $attributes = $cart_item['variation'] ?? [];
        }
    }

    return [
        'file_url' => $current_file_url,
        'custom_length' => $custom_length ?? $cart_item['custom_length_obrobka'] ?? '',
        'custom_width' => $custom_width ?? '',
        'attributes' => $attributes,
    ];
}

/* ------------------------------------------------------------------
 * 10.  REDIRECT PO UDANEJ PŁATNOŚCI
 * ------------------------------------------------------------------ */

add_filter('woocommerce_payment_successful_result', 'debug_payment_redirect', 99999, 2);
function debug_payment_redirect($result, $order_id)
{
    error_log('Payment redirect URL: ' . $result['redirect']);
    $result['redirect'] = str_replace('amex-png/', 'zamowienie/', $result['redirect']);
    return $result;
}

/* ------------------------------------------------------------------
 * 11.  META-DANE PRODUKTÓW PRZEZ REST API
 * ------------------------------------------------------------------ */

add_action('woocommerce_rest_insert_product_object', 'add_custom_product_meta', 10, 3);
function add_custom_product_meta($product, $request, $creating)
{

    $body = json_decode($request->get_body(), true) ?: [];

    foreach ($body['meta_data'] ?? [] as $meta) {

        $key = $meta['key'];
        $value = filter_var($meta['value'], FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';

        if (in_array($key, ['_is_calculated_by_area', '_linear_meter_pricing', '_pcv_product'], true)) {
            $product->update_meta_data($key, $value);
            $product->save();
        }
    }

    /* atrybut koloru */
    if (isset($body['taxonomies']['pa_kolor'])) {

        $color_names = (array) $body['taxonomies']['pa_kolor'];
        $color_slugs = [];

        foreach ($color_names as $color_name) {

            $term = get_term_by('name', $color_name, 'pa_kolor');

            if (!$term || is_wp_error($term)) {
                $created = wp_insert_term(
                    $color_name,
                    'pa_kolor',
                    ['slug' => sanitize_title($color_name)]
                );
                if (!is_wp_error($created)) {
                    $term = get_term($created['term_id']);
                }
            }

            if ($term && !is_wp_error($term)) {
                $color_slugs[] = $term->slug;
            }
        }

        if ($color_slugs) {
            wp_set_object_terms($product->get_id(), $color_slugs, 'pa_kolor', false);
        }
    }
}

/* ------------------------------------------------------------------
 * 12.  WYSYŁKA LOGÓW BŁĘDÓW NA MAILA
 * ------------------------------------------------------------------ */

function send_error_to_email($error, $email = 'dominik.chyziak@septemonline.com')
{

    $subject = 'Błąd na stronie: ' . $_SERVER['HTTP_HOST'];
    $message = "Wystąpił błąd:\n\n" . print_r($error, true) . "\n\n";
    $message .= 'Data: ' . date('Y-m-d H:i:s') . "\n";
    $message .= 'URL: ' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
        . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n";
    $message .= 'IP użytkownika: ' . $_SERVER['REMOTE_ADDR'] . "\n";

    wp_mail($email, $subject, $message, ['Content-Type: text/plain; charset=UTF-8']);
}

set_exception_handler(function ($e) {
    send_error_to_email([
        'type' => 'Exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        send_error_to_email($error);
    }
});

/**
 * Zawsze wyświetlaj ceny BRUTTO (z doliczonym VAT‑em) w katalogu
 * – niezależnie od ustawień WooCommerce.
 */
add_filter('woocommerce_get_price_html', 'my_force_price_html_gross', 10, 2);

function my_force_price_html_gross($price_html, $product)
{

    // 1) PRODUKTY PROSTE
    if ($product->is_type('simple')) {
        $net = $product->get_price();                                          // netto
        $gross = wc_get_price_including_tax($product, ['price' => $net]);  // brutto
        return wc_price($gross);
    }

    // 2) PRODUKTY PROSTE NA PROMOCJI
    if ($product->is_on_sale() && $product->is_type('simple')) {
        $regular_gross = wc_get_price_including_tax(
            $product,
            ['price' => $product->get_regular_price()]
        );
        $sale_gross = wc_get_price_including_tax(
            $product,
            ['price' => $product->get_sale_price()]
        );
        return wc_format_sale_price(
            wc_price($regular_gross),
            wc_price($sale_gross)
        );
    }

    // 3) PRODUKTY VARIABLE (zakres cen)
    if ($product->is_type('variable')) {
        $min = wc_get_price_including_tax(
            $product,
            ['price' => $product->get_variation_price('min', false)] // false = netto
        );
        $max = wc_get_price_including_tax(
            $product,
            ['price' => $product->get_variation_price('max', false)]
        );

        // zakres 124 zł – 199 zł albo pojedyncza kwota
        return ($min !== $max)
            ? wc_format_price_range(wc_price($min), wc_price($max))
            : wc_price($min);
    }

    // 4) DOMYŚLNIE: cokolwiek innego (grupa, external…) – zostaw jak było
    return $price_html;
}
