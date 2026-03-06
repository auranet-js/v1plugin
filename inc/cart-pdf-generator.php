<?php
/**
 * Generator PDF z koszyka WooCommerce
 * 
 * @package Victorini2025
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test generowania PDF z koszyka - wywołanie przez URL
 * URL: https://victorini.pl/?test_cart_pdf_v2=1
 */
add_action('template_redirect', 'auranet_test_cart_pdf_v2');

function auranet_test_cart_pdf_v2() {
    if (!isset($_GET['test_cart_pdf_v2']) || $_GET['test_cart_pdf_v2'] != '1') {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Brak uprawnień');
    }
    
    if (!class_exists('WooCommerce')) {
        wp_die('WooCommerce nie jest aktywne');
    }
    
    $cart = WC()->cart;
    
    if ($cart->is_empty()) {
        wp_die('Koszyk jest pusty! Dodaj produkty do koszyka przed testem.');
    }
    
    $autoload_path = dirname(plugin_dir_path(__FILE__)) . '/vendor/autoload.php';
    
    if (!file_exists($autoload_path)) {
        wp_die('Brak pliku vendor/autoload.php. Ścieżka: ' . $autoload_path);
    }
    
    require_once $autoload_path;
    
    if (!class_exists('\Dompdf\Dompdf')) {
        wp_die('Dompdf nie jest zainstalowany.');
    }
    
    // Jeśli jest parametr save_cart - zapisz do CPT i generuj PDF
    if (isset($_GET['save_cart']) && $_GET['save_cart'] == '1') {
        $result = auranet_save_cart_to_cpt($cart);
        if ($result) {
            auranet_generate_saved_cart_pdf($result['post_id'], true);
        } else {
            wp_die('Błąd zapisu koszyka');
        }
        exit;
    }
    
    // Jeśli jest parametr generate_pdf - tylko generuj PDF (bez zapisu)
    if (isset($_GET['generate_pdf']) && $_GET['generate_pdf'] == '1') {
        auranet_generate_cart_pdf_from_wc_cart($cart);
        exit;
    }
    
    // Podgląd danych
    $settings = auranet_get_cart_pdf_settings();
    
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; margin-bottom: 10px; }
        .date { text-align: left; font-size: 14px; color: #666; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 12px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .product-image { max-width: 80px; height: auto; }
        .product-name { font-weight: bold; margin-bottom: 5px; }
        .product-sku { font-size: 13px; color: #666; margin-bottom: 5px; }
        .product-dims { font-size: 13px; color: #666; margin-bottom: 5px; }
        .file-link { font-size: 12px; margin-top: 5px; }
        .file-link a { color: #0066cc; text-decoration: none; }
        .total-row { background-color: #f5f5f5; font-weight: bold; }
        .final-row { background-color: #333; color: white; font-weight: bold; }
        .subtotal-label { text-align: right; padding-right: 15px; }
        .btn-generate { display: inline-block; background: #0073aa; color: white; padding: 15px 30px; 
                        text-decoration: none; border-radius: 3px; margin-top: 30px; font-size: 16px; margin-right: 10px; }
        .btn-generate:hover { background: #005177; }
        .btn-save { background: #46b450; }
        .btn-save:hover { background: #3a9a42; }
        .info { background: #e7f7e7; padding: 15px; border-left: 4px solid #4caf50; margin-bottom: 20px; }
        .header-preview { background: #f9f9f9; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
    </style>';
    echo '</head><body>';
    
    echo '<div class="container">';
    echo '<div class="info">✓ Dompdf dostępny - biblioteka załadowana poprawnie</div>';
    
    // Podgląd headera
    if (!empty($settings['logo']) || !empty($settings['company_name'])) {
        echo '<div class="header-preview">';
        echo '<strong>Podgląd nagłówka PDF:</strong><br><br>';
        if (!empty($settings['logo'])) {
            echo '<img src="' . esc_url($settings['logo']) . '" style="max-height: 60px;"><br><br>';
        }
        if (!empty($settings['company_name'])) {
            echo '<strong>' . esc_html($settings['company_name']) . '</strong><br>';
        }
        if (!empty($settings['company_address'])) {
            echo nl2br(esc_html($settings['company_address'])) . '<br>';
        }
        if (!empty($settings['company_nip'])) {
            echo 'NIP: ' . esc_html($settings['company_nip']) . '<br>';
        }
        if (!empty($settings['company_phone'])) {
            echo 'Tel: ' . esc_html($settings['company_phone']) . '<br>';
        }
        if (!empty($settings['company_email'])) {
            echo 'Email: ' . esc_html($settings['company_email']);
        }
        echo '</div>';
    }
    
    echo '<h1>Koszyk - Victorini.pl</h1>';
    echo '<p class="date">Data: ' . date('Y-m-d H:i:s') . '</p>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th width="10%">Zdjęcie</th>';
    echo '<th width="40%">Produkt</th>';
    echo '<th width="15%">Cena</th>';
    echo '<th width="10%">Ilość</th>';
    echo '<th width="15%">Razem</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        
        echo '<tr>';
        
        $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
        echo '<td>';
        if ($image_url) {
            echo '<img src="' . $image_url . '" class="product-image">';
        } else {
            echo '-';
        }
        echo '</td>';
        
        echo '<td>';
        echo '<div class="product-name">' . $product->get_name() . '</div>';
        
        if ($product->get_sku()) {
            echo '<div class="product-sku">SKU: ' . $product->get_sku() . '</div>';
        }
        
        $custom_length = isset($cart_item['custom_length']) ? $cart_item['custom_length'] : '';
        $custom_width = isset($cart_item['custom_width']) ? $cart_item['custom_width'] : '';
        
        if (!empty($custom_length) && !empty($custom_width)) {
            echo '<div class="product-dims">Wymiary: ' . $custom_length . ' x ' . $custom_width . ' mm</div>';
        }
        
        if (!empty($cart_item['custom_file'])) {
            echo '<div class="file-link">Załączony plik: <a href="' . $cart_item['custom_file'] . '" target="_blank">Zobacz plik</a></div>';
        }
        
        echo '</td>';
        echo '<td>' . wc_price($product->get_price()) . '</td>';
        echo '<td>' . $cart_item['quantity'] . '</td>';
        echo '<td>' . wc_price($cart_item['line_subtotal']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '<tfoot>';
    
    echo '<tr class="total-row">';
    echo '<td colspan="4" class="subtotal-label">Subtotal:</td>';
    echo '<td>' . wc_price($cart->get_subtotal()) . '</td>';
    echo '</tr>';
    
    if ($cart->get_total_tax() > 0) {
        echo '<tr class="total-row">';
        echo '<td colspan="4" class="subtotal-label">VAT:</td>';
        echo '<td>' . wc_price($cart->get_total_tax()) . '</td>';
        echo '</tr>';
    }
    
    echo '<tr class="final-row">';
    echo '<td colspan="4" class="subtotal-label">RAZEM:</td>';
    echo '<td>' . wc_price($cart->get_total('edit')) . '</td>';
    echo '</tr>';
    
    echo '</tfoot>';
    echo '</table>';
    
    echo '<center>';
    echo '<a href="?test_cart_pdf_v2=1&generate_pdf=1" class="btn-generate">Generuj PDF (bez zapisu)</a>';
    echo '<a href="?test_cart_pdf_v2=1&save_cart=1" class="btn-generate btn-save">Zapisz i generuj PDF</a>';
    echo '</center>';
    
    echo '</div>';
    echo '</body></html>';
    
    exit;
}

/**
 * Publiczny endpoint do zapisu kalkulacji PDF
 * URL: https://victorini.pl/?save_cart_pdf=1
 */
add_action('template_redirect', 'auranet_public_save_cart_pdf');

function auranet_public_save_cart_pdf() {
    if (!isset($_GET['save_cart_pdf']) || $_GET['save_cart_pdf'] != '1') {
        return;
    }
    
    if (!class_exists('WooCommerce')) {
        wp_die('WooCommerce nie jest aktywne');
    }
    
    $cart = WC()->cart;
    
    if ($cart->is_empty()) {
        wc_add_notice('Koszyk jest pusty. Dodaj produkty przed zapisaniem kalkulacji.', 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }
    
    $autoload_path = dirname(plugin_dir_path(__FILE__)) . '/vendor/autoload.php';
    
    if (!file_exists($autoload_path)) {
        wp_die('Błąd konfiguracji - brak biblioteki PDF');
    }
    
    require_once $autoload_path;
    
    if (!class_exists('\Dompdf\Dompdf')) {
        wp_die('Błąd konfiguracji - biblioteka PDF niedostępna');
    }
    
    // Zapisz koszyk do CPT
    $result = auranet_save_cart_to_cpt($cart);
    
    if (!$result) {
        wc_add_notice('Błąd zapisu kalkulacji. Spróbuj ponownie.', 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }
    
    // Generuj i pobierz PDF
    auranet_generate_saved_cart_pdf($result['post_id'], true);
    exit;
}

/**
 * Generowanie PDF bezpośrednio z koszyka WC (bez zapisu do CPT)
 */
function auranet_generate_cart_pdf_from_wc_cart($cart, $download = true) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once dirname(plugin_dir_path(__FILE__)) . '/vendor/autoload.php';
    
    $settings = auranet_get_cart_pdf_settings();
    
    // Przygotuj dane produktów
    $items = array();
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        
        $custom_length = isset($cart_item['custom_length']) ? $cart_item['custom_length'] : '';
        $custom_width = isset($cart_item['custom_width']) ? $cart_item['custom_width'] : '';
        $dimensions = '';
        if (!empty($custom_length) && !empty($custom_width)) {
            $dimensions = $custom_length . ' x ' . $custom_width . ' mm';
        }
        
        $items[] = array(
            'name'        => $product->get_name(),
            'sku'         => $product->get_sku(),
            'price'       => $product->get_price(),
            'quantity'    => $cart_item['quantity'],
            'line_total'  => $cart_item['line_subtotal'],
            'dimensions'  => $dimensions,
            'custom_file' => isset($cart_item['custom_file']) ? $cart_item['custom_file'] : '',
            'image_url'   => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
        );
    }
    
    $cart_data = array(
        'number'         => 'PODGLĄD-' . date('His'),
        'date'           => date('Y-m-d H:i:s'),
        'items'          => $items,
        'subtotal'       => $cart->get_subtotal(),
        'tax'            => $cart->get_total_tax(),
        'total'          => $cart->get_total('edit'),
        'transport_cost' => null,
        'notes'          => '',
        'customer'       => array(
            'name'    => '',
            'email'   => '',
            'phone'   => '',
            'company' => '',
        ),
    );
    
    $html = auranet_build_cart_pdf_html($cart_data, $settings);
    
    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->setIsRemoteEnabled(true);
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = 'koszyk-' . time() . '.pdf';
    
    if ($download) {
        $dompdf->stream($filename, array('Attachment' => true));
        exit;
    }
    
    return $dompdf->output();
}

/**
 * Generowanie PDF z zapisanego koszyka (CPT)
 */
function auranet_generate_saved_cart_pdf($post_id, $download = true) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    require_once dirname(plugin_dir_path(__FILE__)) . '/vendor/autoload.php';
    
    $settings = auranet_get_cart_pdf_settings();
    
    $cart_number = get_post_meta($post_id, '_cart_number', true);
    $cart_data_json = get_post_meta($post_id, '_cart_data', true);
    $items = json_decode($cart_data_json, true) ?: array();
    
    $post = get_post($post_id);
    
    // Dane o edycji
    $edited_by = get_post_meta($post_id, '_edited_by', true);
    $edited_by_name = '';
    if ($edited_by) {
        $editor = get_user_by('ID', $edited_by);
        $edited_by_name = $editor ? $editor->display_name : 'Nieznany użytkownik';
    }
    
    // Koszt transportu - pobierz surową wartość
    $transport_cost_raw = get_post_meta($post_id, '_transport_cost', true);
    // null gdy meta nie istnieje (puste pole = kreska), '0' lub wartość liczbowa
    $transport_cost = ($transport_cost_raw === '' || $transport_cost_raw === false) ? null : (float)$transport_cost_raw;
    
    $cart_data = array(
        'number'         => $cart_number,
        'date'           => $post->post_date,
        'items'          => $items,
        'subtotal'       => get_post_meta($post_id, '_cart_subtotal', true),
        'tax'            => get_post_meta($post_id, '_cart_tax', true),
        'total'          => get_post_meta($post_id, '_cart_total', true),
        'transport_cost' => $transport_cost,
        'notes'          => get_post_meta($post_id, '_cart_notes', true),
        'customer'       => array(
            'name'    => get_post_meta($post_id, '_customer_name', true),
            'email'   => get_post_meta($post_id, '_customer_email', true),
            'phone'   => get_post_meta($post_id, '_customer_phone', true),
            'company' => get_post_meta($post_id, '_customer_company', true),
        ),
        'edited_by'      => $edited_by,
        'edited_by_name' => $edited_by_name,
        'edited_at'      => get_post_meta($post_id, '_edited_at', true),
    );
    
    $html = auranet_build_cart_pdf_html($cart_data, $settings);
    
    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->setIsRemoteEnabled(true);
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = sanitize_file_name($cart_number) . '.pdf';
    
    if ($download) {
        $dompdf->stream($filename, array('Attachment' => true));
        exit;
    }
    
    return $dompdf->output();
}

/**
 * Budowanie HTML dla PDF
 */
function auranet_build_cart_pdf_html($cart_data, $settings) {
    $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
    $html .= '<style>
        body { font-family: DejaVu Sans, sans-serif; margin: 20px; font-size: 10px; }
        .header { margin-bottom: 20px; }
        .header-table { width: 100%; border: none; margin-bottom: 20px; }
        .header-table td { border: none; vertical-align: top; padding: 0; }
        .logo { max-height: 60px; }
        .company-info { font-size: 9px; line-height: 1.4; }
        .company-name { font-size: 12px; font-weight: bold; margin-bottom: 5px; }
        .cart-title { font-size: 16px; font-weight: bold; text-align: center; margin: 20px 0 10px 0; }
        .cart-info { margin-bottom: 15px; }
        .cart-number { font-size: 11px; font-weight: bold; }
        .cart-date { font-size: 9px; color: #666; }
        .customer-info { background: #f9f9f9; padding: 10px; margin-bottom: 15px; font-size: 9px; }
        .customer-info strong { display: block; margin-bottom: 5px; }
        table.products { width: 100%; border-collapse: collapse; font-size: 9px; }
        table.products th, table.products td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        table.products th { background-color: #f2f2f2; font-weight: bold; }
        .product-image { max-width: 50px; height: auto; }
        .product-name { font-weight: bold; }
        .product-sku { font-size: 8px; color: #666; }
        .product-dims { font-size: 8px; color: #666; }
        .product-file { font-size: 7px; color: #0066cc; }
        .total-row { background-color: #f5f5f5; font-weight: bold; }
        .transport-row { background-color: #e8f4fd; }
        .final-row { background-color: #333; color: white; font-weight: bold; }
        .text-right { text-align: right; }
        .notes-section { margin-top: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; font-size: 9px; }
        .notes-section strong { display: block; margin-bottom: 5px; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 8px; color: #666; }
        .custom-header { margin-bottom: 15px; font-size: 9px; }
        .custom-footer { margin-top: 15px; font-size: 8px; }
    </style>';
    $html .= '</head><body>';
    
    // HEADER
    $html .= '<div class="header">';
    $html .= '<table class="header-table"><tr>';
    
    // Logo
    $html .= '<td width="30%">';
    if (!empty($settings['logo'])) {
        $html .= '<img src="' . esc_url($settings['logo']) . '" class="logo">';
    }
    $html .= '</td>';
    
    // Dane firmy
    $html .= '<td width="70%" style="text-align: right;">';
    $html .= '<div class="company-info">';
    if (!empty($settings['company_name'])) {
        $html .= '<div class="company-name">' . esc_html($settings['company_name']) . '</div>';
    }
    if (!empty($settings['company_address'])) {
        $html .= nl2br(esc_html($settings['company_address'])) . '<br>';
    }
    if (!empty($settings['company_nip'])) {
        $html .= 'NIP: ' . esc_html($settings['company_nip']) . '<br>';
    }
    if (!empty($settings['company_phone'])) {
        $html .= 'Tel: ' . esc_html($settings['company_phone']) . '<br>';
    }
    if (!empty($settings['company_email'])) {
        $html .= 'Email: ' . esc_html($settings['company_email']);
    }
    $html .= '</div>';
    $html .= '</td>';
    
    $html .= '</tr></table>';
    
    // Dodatkowa treść nagłówka
    if (!empty($settings['header'])) {
        $html .= '<div class="custom-header">' . wp_kses_post($settings['header']) . '</div>';
    }
    
    $html .= '</div>';
    
    // TYTUŁ I INFO
    $html .= '<div class="cart-title">Kalkulacja</div>';
    $html .= '<div class="cart-info">';
    $html .= '<span class="cart-number">Numer: ' . esc_html($cart_data['number']) . '</span><br>';
    $html .= '<span class="cart-date">Data: ' . esc_html($cart_data['date']) . '</span>';
    $html .= '</div>';
    
    // DANE KLIENTA
    $customer = $cart_data['customer'];
    if (!empty($customer['name']) || !empty($customer['email']) || !empty($customer['company'])) {
        $html .= '<div class="customer-info">';
        $html .= '<strong>Dane klienta:</strong>';
        if (!empty($customer['company'])) {
            $html .= esc_html($customer['company']) . '<br>';
        }
        if (!empty($customer['name'])) {
            $html .= esc_html($customer['name']) . '<br>';
        }
        if (!empty($customer['email'])) {
            $html .= esc_html($customer['email']) . '<br>';
        }
        if (!empty($customer['phone'])) {
            $html .= 'Tel: ' . esc_html($customer['phone']);
        }
        $html .= '</div>';
    }
    
    // TABELA PRODUKTÓW
    $html .= '<table class="products">';
    $html .= '<thead><tr>';
    $html .= '<th width="10%">Zdjęcie</th>';
    $html .= '<th width="40%">Produkt</th>';
    $html .= '<th width="15%">Cena</th>';
    $html .= '<th width="10%">Ilość</th>';
    $html .= '<th width="15%">Razem</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach ($cart_data['items'] as $item) {
        $html .= '<tr>';
        
        // Zdjęcie
        $html .= '<td>';
        if (!empty($item['image_url'])) {
            $html .= '<img src="' . esc_url($item['image_url']) . '" class="product-image">';
        } else {
            $html .= '-';
        }
        $html .= '</td>';
        
        // Produkt
        $html .= '<td>';
        $html .= '<div class="product-name">' . esc_html($item['name']) . '</div>';
        if (!empty($item['sku'])) {
            $html .= '<div class="product-sku">SKU: ' . esc_html($item['sku']) . '</div>';
        }
        
        if (!empty($item['attributes'])) {
           $html .= '<div class="product-sku">' . esc_html($item['attributes']) . '</div>';
        }
        if (!empty($item['dimensions'])) {
            $html .= '<div class="product-dims">Wymiary: ' . esc_html($item['dimensions']) . '</div>';
        }
        if (!empty($item['custom_file'])) {
            $html .= '<div class="product-file">Załącznik: ' . esc_html($item['custom_file']) . '</div>';
        }
        $html .= '</td>';
        
        // Cena
        $html .= '<td>' . number_format((float)$item['price'], 2, ',', ' ') . ' zł</td>';
        
        // Ilość
        $html .= '<td>' . intval($item['quantity']) . '</td>';
        
        // Razem
        $html .= '<td>' . number_format((float)$item['line_total'], 2, ',', ' ') . ' zł</td>';
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '<tfoot>';
    
    // VAT (w tym)
    if ((float)$cart_data['tax'] > 0) {
        $html .= '<tr class="total-row">';
        $html .= '<td colspan="4" class="text-right">w tym VAT (23%):</td>';
        $html .= '<td>' . number_format((float)$cart_data['tax'], 2, ',', ' ') . ' zł</td>';
        $html .= '</tr>';
    }
    
    // Suma produktów
    $products_total = (float)$cart_data['total'];
    $html .= '<tr class="total-row">';
    $html .= '<td colspan="4" class="text-right">Suma produktów:</td>';
    $html .= '<td>' . number_format($products_total, 2, ',', ' ') . ' zł</td>';
    $html .= '</tr>';
    
    // KOSZT TRANSPORTU
    $transport_cost = isset($cart_data['transport_cost']) ? $cart_data['transport_cost'] : null;
    $html .= '<tr class="transport-row">';
    $html .= '<td colspan="4" class="text-right">Koszt transportu:</td>';
    $html .= '<td>';
    if ($transport_cost === null) {
        $html .= '–';
    } elseif ((float)$transport_cost == 0) {
        $html .= 'Gratis';
    } else {
        $html .= number_format((float)$transport_cost, 2, ',', ' ') . ' zł';
    }
    $html .= '</td>';
    $html .= '</tr>';
    
    // RAZEM BRUTTO (produkty + transport)
    $final_total = $products_total;
    if ($transport_cost !== null) {
        $final_total += (float)$transport_cost;
    }
    $html .= '<tr class="final-row">';
    $html .= '<td colspan="4" class="text-right">RAZEM BRUTTO:</td>';
    $html .= '<td>' . number_format($final_total, 2, ',', ' ') . ' zł</td>';
    $html .= '</tr>';
    
    $html .= '</tfoot>';
    $html .= '</table>';
    
    // UWAGI
    $notes = isset($cart_data['notes']) ? trim($cart_data['notes']) : '';
    if (!empty($notes)) {
        $html .= '<div class="notes-section">';
        $html .= '<strong>Uwagi:</strong>';
        $html .= nl2br(esc_html($notes));
        $html .= '</div>';
    }
    
    // FOOTER
    $html .= '<div class="footer">';
    
    // Info o edycji kalkulacji
    if (!empty($cart_data['edited_by'])) {
        $html .= '<div style="margin-bottom: 15px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 9px;">';
        $html .= '<strong>Kalkulacja edytowana przez:</strong> ' . esc_html($cart_data['edited_by_name']);
        if (!empty($cart_data['edited_at'])) {
            $html .= ' (' . esc_html($cart_data['edited_at']) . ')';
        }
        $html .= '</div>';
    }
    
    if (!empty($settings['footer'])) {
        $html .= '<div class="custom-footer">' . wp_kses_post($settings['footer']) . '</div>';
    }
    $html .= '<br>Dokument wygenerowany: ' . date('Y-m-d H:i:s');
    $html .= '</div>';
    
    $html .= '</body></html>';
    
    return $html;
}

/**
 * Handler: Pobierz PDF z admina
 */
add_action('admin_post_download_saved_cart_pdf', 'auranet_handle_download_saved_cart_pdf');

function auranet_handle_download_saved_cart_pdf() {
    if (!current_user_can('manage_options')) {
        wp_die('Brak uprawnień');
    }
    
    $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
    $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
    
    if (!wp_verify_nonce($nonce, 'cart_pdf_action_' . $cart_id)) {
        wp_die('Nieprawidłowy token bezpieczeństwa');
    }
    
    if (!$cart_id || get_post_type($cart_id) !== 'saved_cart') {
        wp_die('Nieprawidłowy koszyk');
    }
    
    auranet_generate_saved_cart_pdf($cart_id, true);
}

/**
 * Handler: Wyślij PDF emailem (przez GET z linku)
 */
add_action('admin_post_send_cart_email', 'auranet_handle_send_cart_email_get');

function auranet_handle_send_cart_email_get() {
    if (!current_user_can('manage_options')) {
        wp_die('Brak uprawnień');
    }
    
    $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
    $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
    
    if (!wp_verify_nonce($nonce, 'cart_pdf_action_' . $cart_id)) {
        wp_die('Nieprawidłowy token bezpieczeństwa');
    }
    
    if (!$cart_id || get_post_type($cart_id) !== 'saved_cart') {
        wp_die('Nieprawidłowa kalkulacja');
    }
    
    // Pobierz email z parametru GET
    $recipient_email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    
    if (empty($recipient_email) || !is_email($recipient_email)) {
        wp_redirect(admin_url('post.php?post=' . $cart_id . '&action=edit&message=invalid_email'));
        exit;
    }
    
    // Generuj PDF do pliku tymczasowego
    $pdf_content = auranet_generate_saved_cart_pdf($cart_id, false);
    $cart_number = get_post_meta($cart_id, '_cart_number', true);
    
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['basedir'] . '/cart-pdfs/';
    
    if (!file_exists($pdf_path)) {
        wp_mkdir_p($pdf_path);
    }
    
    $pdf_file = $pdf_path . sanitize_file_name($cart_number) . '.pdf';
    file_put_contents($pdf_file, $pdf_content);
    
    // Wyślij email - użyj danych z WooCommerce
    $from_name = get_option('woocommerce_email_from_name', get_bloginfo('name'));
    $from_email = get_option('woocommerce_email_from_address', get_option('admin_email'));
    
    $subject = 'Twoja kalkulacja - ' . $cart_number;
    $message = "Dzień dobry,\n\n";
    $message .= "W załączniku przesyłamy PDF z kalkulacją.\n";
    $message .= "Numer kalkulacji: " . $cart_number . "\n\n";
    $message .= "Pozdrawiamy,\n";
    $message .= $from_name;
    
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
    );
    $attachments = array($pdf_file);
    
    $sent = wp_mail($recipient_email, $subject, $message, $headers, $attachments);
    
    // Usuń plik tymczasowy
    @unlink($pdf_file);
    
    if ($sent) {
        wp_redirect(admin_url('post.php?post=' . $cart_id . '&action=edit&message=email_sent&sent_to=' . urlencode($recipient_email)));
    } else {
        wp_redirect(admin_url('post.php?post=' . $cart_id . '&action=edit&message=email_failed'));
    }
    exit;
}

/**
 * Handler: Wyślij PDF emailem (przez admin_init bo formularz jest w metaboxie)
 */
add_action('admin_init', 'auranet_handle_send_cart_email_form');

function auranet_handle_send_cart_email_form() {
    if (!isset($_POST['action']) || $_POST['action'] !== 'send_cart_email_form') {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Brak uprawnień');
    }
    
    $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
    
    if (!wp_verify_nonce($_POST['cart_email_nonce'], 'send_cart_email_' . $cart_id)) {
        wp_die('Nieprawidłowy token bezpieczeństwa');
    }
    
    if (!$cart_id || get_post_type($cart_id) !== 'saved_cart') {
        wp_die('Nieprawidłowy koszyk');
    }
    
    $recipient_email = isset($_POST['recipient_email']) ? sanitize_email($_POST['recipient_email']) : '';
    
    if (empty($recipient_email) || !is_email($recipient_email)) {
        wp_redirect(admin_url('post.php?post=' . $cart_id . '&action=edit&message=invalid_email'));
        exit;
    }
    
    // Generuj PDF do pliku tymczasowego
    $pdf_content = auranet_generate_saved_cart_pdf($cart_id, false);
    $cart_number = get_post_meta($cart_id, '_cart_number', true);
    
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['basedir'] . '/cart-pdfs/';
    
    if (!file_exists($pdf_path)) {
        wp_mkdir_p($pdf_path);
    }
    
    $pdf_file = $pdf_path . sanitize_file_name($cart_number) . '.pdf';
    file_put_contents($pdf_file, $pdf_content);
    
    // Wyślij email - użyj danych z WooCommerce
    $from_name = get_option('woocommerce_email_from_name', get_bloginfo('name'));
    $from_email = get_option('woocommerce_email_from_address', get_option('admin_email'));
    
    $subject = 'Twoja kalkulacja - ' . $cart_number;
    $message = "Dzień dobry,\n\n";
    $message .= "W załączniku przesyłamy PDF z kalkulacją.\n";
    $message .= "Numer kalkulacji: " . $cart_number . "\n\n";
    $message .= "Pozdrawiamy,\n";
    $message .= $from_name;
    
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
    );
    $attachments = array($pdf_file);
    
    $sent = wp_mail($recipient_email, $subject, $message, $headers, $attachments);
    
    // Usuń plik tymczasowy
    @unlink($pdf_file);
    
    if ($sent) {
        wp_redirect(admin_url('post.php?post=' . $cart_id . '&action=edit&message=email_sent&sent_to=' . urlencode($recipient_email)));
    } else {
        wp_redirect(admin_url('post.php?post=' . $cart_id . '&action=edit&message=email_failed'));
    }
    exit;
}

/**
 * Handler: Podgląd PDF z ustawień
 */
add_action('admin_post_preview_cart_pdf', 'auranet_handle_preview_cart_pdf');

function auranet_handle_preview_cart_pdf() {
    if (!current_user_can('manage_options')) {
        wp_die('Brak uprawnień');
    }
    
    $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
    
    if (!wp_verify_nonce($nonce, 'preview_cart_pdf')) {
        wp_die('Nieprawidłowy token bezpieczeństwa');
    }
    
    require_once dirname(plugin_dir_path(__FILE__)) . '/vendor/autoload.php';
    
    $settings = auranet_get_cart_pdf_settings();
    
    // Przykładowe dane
    $cart_data = array(
        'number'   => $settings['prefix'] . '-2025-0001',
        'date'     => date('Y-m-d H:i:s'),
        'items'    => array(
            array(
                'name'        => 'Przykładowy produkt 1',
                'sku'         => 'SKU-001',
                'price'       => 199.99,
                'quantity'    => 2,
                'line_total'  => 399.98,
                'dimensions'  => '1000 x 500 mm',
                'custom_file' => '',
                'image_url'   => '',
            ),
            array(
                'name'        => 'Przykładowy produkt 2',
                'sku'         => 'SKU-002',
                'price'       => 49.99,
                'quantity'    => 1,
                'line_total'  => 49.99,
                'dimensions'  => '',
                'custom_file' => 'https://example.com/plik.pdf',
                'image_url'   => '',
            ),
        ),
        'subtotal'       => 449.97,
        'tax'            => 103.49,
        'total'          => 553.46,
        'transport_cost' => 150.00,
        'notes'          => 'Przykładowe uwagi do kalkulacji. Dostawa w ciągu 5 dni roboczych.',
        'customer'       => array(
            'name'    => 'Jan Kowalski',
            'email'   => 'jan@example.com',
            'phone'   => '+48 123 456 789',
            'company' => 'Firma Przykładowa Sp. z o.o.',
        ),
    );
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $html = auranet_build_cart_pdf_html($cart_data, $settings);
    
    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->setIsRemoteEnabled(true);
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $dompdf->stream('podglad-pdf.pdf', array('Attachment' => false));
    exit;
}

/**
 * Komunikaty w adminie po wysłaniu emaila
 */
add_action('admin_notices', 'auranet_cart_pdf_admin_notices');

function auranet_cart_pdf_admin_notices() {
    if (!isset($_GET['message'])) {
        return;
    }
    
    $screen = get_current_screen();
    if ($screen->post_type !== 'saved_cart') {
        return;
    }
    
    switch ($_GET['message']) {
        case 'email_sent':
            $sent_to = isset($_GET['sent_to']) ? sanitize_email($_GET['sent_to']) : '';
            $msg = 'Email z PDF został wysłany';
            if ($sent_to) {
                $msg .= ' na adres: ' . esc_html($sent_to);
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
            break;
        case 'email_failed':
            echo '<div class="notice notice-error is-dismissible"><p>Błąd wysyłania emaila.</p></div>';
            break;
        case 'invalid_email':
            echo '<div class="notice notice-warning is-dismissible"><p>Nieprawidłowy adres email.</p></div>';
            break;
    }
}
