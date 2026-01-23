<?php
/**
 * Custom Post Type - Zapisane koszyki
 * 
 * @package Victorini2025
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rejestracja CPT saved_cart
 */
add_action('init', 'auranet_register_saved_cart_cpt');

function auranet_register_saved_cart_cpt() {
    $labels = array(
        'name'               => 'Kalkulacje',
        'singular_name'      => 'Kalkulacja',
        'menu_name'          => 'Kalkulacje PDF',
        'add_new'            => 'Dodaj nową',
        'add_new_item'       => 'Dodaj nową kalkulację',
        'edit_item'          => 'Edytuj kalkulację',
        'new_item'           => 'Nowa kalkulacja',
        'view_item'          => 'Zobacz kalkulację',
        'search_items'       => 'Szukaj kalkulacji',
        'not_found'          => 'Nie znaleziono kalkulacji',
        'not_found_in_trash' => 'Brak kalkulacji w koszu',
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 56,
        'menu_icon'           => 'dashicons-pdf',
        'supports'            => array('title'),
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
    );

    register_post_type('saved_cart', $args);
}

/**
 * Metaboxy dla saved_cart
 */
add_action('add_meta_boxes', 'auranet_saved_cart_metaboxes');

function auranet_saved_cart_metaboxes() {
    add_meta_box(
        'saved_cart_details',
        'Szczegóły koszyka',
        'auranet_saved_cart_details_callback',
        'saved_cart',
        'normal',
        'high'
    );
    
    add_meta_box(
        'saved_cart_customer',
        'Dane klienta',
        'auranet_saved_cart_customer_callback',
        'saved_cart',
        'side',
        'high'
    );
    
    add_meta_box(
        'saved_cart_actions',
        'Akcje',
        'auranet_saved_cart_actions_callback',
        'saved_cart',
        'side',
        'default'
    );
}

/**
 * Metabox - szczegóły koszyka (produkty) - EDYTOWALNY
 */
function auranet_saved_cart_details_callback($post) {
    $cart_data = get_post_meta($post->ID, '_cart_data', true);
    $cart_number = get_post_meta($post->ID, '_cart_number', true);
    $cart_total = get_post_meta($post->ID, '_cart_total', true);
    $cart_subtotal = get_post_meta($post->ID, '_cart_subtotal', true);
    $edited_by = get_post_meta($post->ID, '_edited_by', true);
    $edited_at = get_post_meta($post->ID, '_edited_at', true);
    
    wp_nonce_field('save_cart_items', 'cart_items_nonce');
    
    echo '<p><strong>Numer kalkulacji:</strong> ' . esc_html($cart_number) . '</p>';
    
    if (!empty($cart_data)) {
        $items = json_decode($cart_data, true);
        
        if (!empty($items)) {
            echo '<table class="widefat striped" style="margin-top: 15px;" id="cart-items-table">';
            echo '<thead><tr>';
            echo '<th>Zdjęcie</th>';
            echo '<th>Produkt</th>';
            echo '<th>SKU</th>';
            echo '<th>Wymiary</th>';
            echo '<th style="width: 100px;">Cena (zł)</th>';
            echo '<th style="width: 80px;">Ilość</th>';
            echo '<th style="width: 120px;">Razem</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($items as $index => $item) {
                $line_total = (float)$item['price'] * (int)$item['quantity'];
                
                echo '<tr class="cart-item-row">';
                echo '<td>';
                if (!empty($item['image_url'])) {
                    echo '<img src="' . esc_url($item['image_url']) . '" style="max-width: 50px; height: auto;">';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '<td>' . esc_html($item['name']) . '<input type="hidden" name="cart_items[' . $index . '][name]" value="' . esc_attr($item['name']) . '"></td>';
                echo '<td>' . esc_html($item['sku']) . '<input type="hidden" name="cart_items[' . $index . '][sku]" value="' . esc_attr($item['sku']) . '"></td>';
                echo '<td>' . (!empty($item['dimensions']) ? esc_html($item['dimensions']) : '-') . '<input type="hidden" name="cart_items[' . $index . '][dimensions]" value="' . esc_attr($item['dimensions'] ?? '') . '"></td>';
                echo '<td><input type="number" step="0.01" min="0" name="cart_items[' . $index . '][price]" value="' . esc_attr($item['price']) . '" class="cart-item-price" style="width: 90px;"></td>';
                echo '<td><input type="number" step="1" min="1" name="cart_items[' . $index . '][quantity]" value="' . esc_attr($item['quantity']) . '" class="cart-item-quantity" style="width: 60px;"></td>';
                echo '<td class="cart-item-line-total">' . number_format($line_total, 2, ',', ' ') . ' zł</td>';
                
                // Ukryte pola
                echo '<input type="hidden" name="cart_items[' . $index . '][product_id]" value="' . esc_attr($item['product_id'] ?? '') . '">';
                echo '<input type="hidden" name="cart_items[' . $index . '][image_url]" value="' . esc_attr($item['image_url'] ?? '') . '">';
                echo '<input type="hidden" name="cart_items[' . $index . '][custom_file]" value="' . esc_attr($item['custom_file'] ?? '') . '">';
                
                echo '</tr>';
                
                if (!empty($item['custom_file'])) {
                    echo '<tr><td colspan="7"><small>Załącznik: <a href="' . esc_url($item['custom_file']) . '" target="_blank">Zobacz plik</a></small></td></tr>';
                }
            }
            
            echo '</tbody>';
            echo '<tfoot>';
            $cart_tax = get_post_meta($post->ID, '_cart_tax', true);
            echo '<tr style="background: #333; font-weight: bold;">';
            echo '<td colspan="6" style="text-align: right; padding: 10px; color: #fff;">RAZEM BRUTTO:</td>';
            echo '<td style="padding: 10px; color: #fff;" id="cart-total-display">' . number_format((float)$cart_total, 2, ',', ' ') . ' zł</td>';
            echo '</tr>';
            echo '<tr style="background: #f9f9f9;">';
            echo '<td colspan="6" style="text-align: right; padding: 10px;">w tym VAT (23%):</td>';
            echo '<td style="padding: 10px;" id="cart-tax-display">' . number_format((float)$cart_tax, 2, ',', ' ') . ' zł</td>';
            echo '</tr>';
            echo '</tfoot>';
            echo '</table>';
            
            // Info o edycji
            if (!empty($edited_by)) {
                $editor = get_user_by('ID', $edited_by);
                $editor_name = $editor ? $editor->display_name : 'Nieznany użytkownik';
                echo '<p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">';
                echo '<strong>Kalkulacja edytowana przez:</strong> ' . esc_html($editor_name);
                if ($edited_at) {
                    echo ' <em>(' . esc_html($edited_at) . ')</em>';
                }
                echo '</p>';
            }
            
            echo '<p style="margin-top: 15px;"><em>Zmień cenę lub ilość i kliknij "Aktualizuj" aby zapisać zmiany.</em></p>';
        }
    } else {
        echo '<p>Brak danych koszyka.</p>';
    }
    
    // JavaScript do przeliczania sum
    ?>
    <script>
    jQuery(document).ready(function($) {
        function recalculateTotals() {
            var totalBrutto = 0;
            $('.cart-item-row').each(function() {
                var price = parseFloat($(this).find('.cart-item-price').val()) || 0;
                var qty = parseInt($(this).find('.cart-item-quantity').val()) || 0;
                var lineTotal = price * qty;
                totalBrutto += lineTotal;
                $(this).find('.cart-item-line-total').text(lineTotal.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' zł');
            });
            
            // VAT wstecz: brutto * 23/123
            var tax = totalBrutto * 23 / 123;
            
            $('#cart-tax-display').text(tax.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' zł');
            $('#cart-total-display').text(totalBrutto.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' zł');
        }
        
        $('.cart-item-price, .cart-item-quantity').on('change keyup', function() {
            recalculateTotals();
        });
    });
    </script>
    <?php
}

/**
 * Metabox - dane klienta
 */
function auranet_saved_cart_customer_callback($post) {
    $customer_name = get_post_meta($post->ID, '_customer_name', true);
    $customer_email = get_post_meta($post->ID, '_customer_email', true);
    $customer_phone = get_post_meta($post->ID, '_customer_phone', true);
    $customer_company = get_post_meta($post->ID, '_customer_company', true);
    $user_id = get_post_meta($post->ID, '_user_id', true);
    
    echo '<p><strong>Imię i nazwisko:</strong><br>' . esc_html($customer_name ?: '-') . '</p>';
    echo '<p><strong>Email:</strong><br>' . esc_html($customer_email ?: '-') . '</p>';
    echo '<p><strong>Telefon:</strong><br>' . esc_html($customer_phone ?: '-') . '</p>';
    echo '<p><strong>Firma:</strong><br>' . esc_html($customer_company ?: '-') . '</p>';
    
    if ($user_id) {
        $user = get_user_by('ID', $user_id);
        if ($user) {
            echo '<p><strong>Konto:</strong><br><a href="' . admin_url('user-edit.php?user_id=' . $user_id) . '">' . esc_html($user->display_name) . '</a></p>';
        }
    } else {
        echo '<p><strong>Konto:</strong><br>Gość</p>';
    }
}

/**
 * Metabox - akcje (pobierz PDF, wyślij email)
 */
function auranet_saved_cart_actions_callback($post) {
    $cart_number = get_post_meta($post->ID, '_cart_number', true);
    $customer_email = get_post_meta($post->ID, '_customer_email', true);
    $nonce = wp_create_nonce('cart_pdf_action_' . $post->ID);
    
    echo '<p>';
    echo '<a href="' . admin_url('admin-post.php?action=download_saved_cart_pdf&cart_id=' . $post->ID . '&nonce=' . $nonce) . '" class="button button-primary" style="width: 100%; text-align: center; margin-bottom: 10px;">Pobierz PDF</a>';
    echo '</p>';
    
    echo '<hr style="margin: 15px 0;">';
    echo '<p><strong>Wyślij PDF emailem:</strong></p>';
    
    echo '<p>';
    echo '<input type="email" id="cart_recipient_email" value="' . esc_attr($customer_email) . '" style="width: 100%; margin-bottom: 10px;" placeholder="Adres email">';
    echo '</p>';
    
    echo '<p>';
    echo '<a href="#" id="send_cart_email_btn" data-base-url="' . admin_url('admin-post.php?action=send_cart_email&cart_id=' . $post->ID . '&nonce=' . $nonce) . '" class="button" style="width: 100%; text-align: center;">Wyślij email</a>';
    echo '</p>';
    
    echo '<script>
    document.getElementById("send_cart_email_btn").addEventListener("click", function(e) {
        e.preventDefault();
        var email = document.getElementById("cart_recipient_email").value;
        if (!email || !email.includes("@")) {
            alert("Wprowadź poprawny adres email");
            return;
        }
        var url = this.getAttribute("data-base-url") + "&email=" + encodeURIComponent(email);
        window.location.href = url;
    });
    </script>';
}

/**
 * Generowanie unikalnego numeru kalkulacji (losowy)
 * Format: KALK-0000000000 (10 cyfr)
 */
function auranet_generate_cart_number() {
    $prefix = get_option('auranet_cart_pdf_prefix', 'KALK');
    
    $random_numbers = '';
    for ($i = 0; $i < 10; $i++) {
        $random_numbers .= random_int(0, 9);
    }
    
    $cart_number = $prefix . '-' . $random_numbers;
    
    // Sprawdź czy numer już istnieje
    $existing = get_posts(array(
        'post_type'  => 'saved_cart',
        'meta_key'   => '_cart_number',
        'meta_value' => $cart_number,
        'posts_per_page' => 1,
    ));
    
    // Jeśli istnieje, wygeneruj ponownie
    if (!empty($existing)) {
        return auranet_generate_cart_number();
    }
    
    return $cart_number;
}

/**
 * Zapis koszyka do CPT
 */
function auranet_save_cart_to_cpt($cart, $customer_data = array()) {
    $cart_number = auranet_generate_cart_number();
    
    
    $items = array();
    $subtotal = 0;
    $tax_total = 0;
    
    foreach ($cart->get_cart() as $cart_item) {
    $product = $cart_item['data'];
    
    $custom_length = isset($cart_item['custom_length']) ? $cart_item['custom_length'] : '';
    $custom_width = isset($cart_item['custom_width']) ? $cart_item['custom_width'] : '';
    $custom_length_obrobka = isset($cart_item['custom_length_obrobka']) ? $cart_item['custom_length_obrobka'] : '';
    $custom_wymiar = isset($cart_item['custom_wymiar']) ? $cart_item['custom_wymiar'] : array();
    
    $dimensions = '';
    
    // Wymiary obróbki blachy
    if (!empty($custom_length_obrobka) || !empty($custom_wymiar)) {
        $dims = array();
        if ($custom_length_obrobka) {
            $dims[] = 'Długość: ' . $custom_length_obrobka . 'mm';
        }
        if (is_array($custom_wymiar)) {
            foreach ($custom_wymiar as $name => $value) {
                $dims[] = strtoupper($name) . ': ' . $value . 'mm';
            }
        }
        $dimensions = implode(', ', $dims);
    }
    // Standardowe wymiary (parapety)
    elseif (!empty($custom_length) && !empty($custom_width)) {
        $dimensions = $custom_length . ' x ' . $custom_width . ' mm';
    }
    
    // Razem BRUTTO
    $line_total_incl_tax = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
    $price_per_unit = round($line_total_incl_tax / $cart_item['quantity'], 2);
    
    $subtotal += $cart_item['line_subtotal'];
    $tax_total += $cart_item['line_subtotal_tax'];
    
    $items[] = array(
        'product_id'  => $cart_item['product_id'],
        'name'        => $product->get_name(),
        'sku'         => $product->get_sku(),
        'price'       => $price_per_unit,
        'quantity'    => $cart_item['quantity'],
        'line_total'  => $line_total_incl_tax,
        'dimensions'  => $dimensions,
        'custom_file' => isset($cart_item['custom_file']) ? $cart_item['custom_file'] : '',
        'image_url'   => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
    );
}
    
    $post_id = wp_insert_post(array(
        'post_type'   => 'saved_cart',
        'post_title'  => $cart_number,
        'post_status' => 'publish',
    ));
    
    if (is_wp_error($post_id)) {
        return false;
    }
    
    update_post_meta($post_id, '_cart_number', $cart_number);
    update_post_meta($post_id, '_cart_data', json_encode($items, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, '_cart_subtotal', $subtotal + $tax_total); // suma brutto (jako "netto" w kontekście edycji)
    update_post_meta($post_id, '_cart_tax', $tax_total);
    update_post_meta($post_id, '_cart_total', $subtotal + $tax_total); // razem brutto
    
    $user_id = get_current_user_id();
    update_post_meta($post_id, '_user_id', $user_id);
    
    if ($user_id) {
        $user = get_user_by('ID', $user_id);
        $customer_name = trim(get_user_meta($user_id, 'billing_first_name', true) . ' ' . get_user_meta($user_id, 'billing_last_name', true));
        $customer_email = $user->user_email;
        $customer_phone = get_user_meta($user_id, 'billing_phone', true);
        $customer_company = get_user_meta($user_id, 'billing_company', true);
    } else {
        $customer_name = isset($customer_data['name']) ? $customer_data['name'] : '';
        $customer_email = isset($customer_data['email']) ? $customer_data['email'] : '';
        $customer_phone = isset($customer_data['phone']) ? $customer_data['phone'] : '';
        $customer_company = isset($customer_data['company']) ? $customer_data['company'] : '';
    }
    
    update_post_meta($post_id, '_customer_name', $customer_name);
    update_post_meta($post_id, '_customer_email', $customer_email);
    update_post_meta($post_id, '_customer_phone', $customer_phone);
    update_post_meta($post_id, '_customer_company', $customer_company);
    
    return array(
        'post_id'     => $post_id,
        'cart_number' => $cart_number,
    );
}

/**
 * Kolumny w liście koszyków
 */
add_filter('manage_saved_cart_posts_columns', 'auranet_saved_cart_columns');

function auranet_saved_cart_columns($columns) {
    return array(
        'cb'       => $columns['cb'],
        'title'    => 'Numer kalkulacji',
        'customer' => 'Klient',
        'total'    => 'Wartość',
        'date'     => 'Data',
    );
}

add_action('manage_saved_cart_posts_custom_column', 'auranet_saved_cart_column_content', 10, 2);

function auranet_saved_cart_column_content($column, $post_id) {
    switch ($column) {
        case 'customer':
            $name = get_post_meta($post_id, '_customer_name', true);
            $email = get_post_meta($post_id, '_customer_email', true);
            echo esc_html($name ?: 'Gość');
            if ($email) {
                echo '<br><small>' . esc_html($email) . '</small>';
            }
            break;
            
        case 'total':
            $total = get_post_meta($post_id, '_cart_total', true);
            echo number_format((float)$total, 2, ',', ' ') . ' zł';
            break;
    }
}

/**
 * Zapis edytowanych produktów w koszyku
 */
add_action('save_post_saved_cart', 'auranet_save_cart_items', 10, 3);

function auranet_save_cart_items($post_id, $post, $update) {
    if (!$update) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!isset($_POST['cart_items_nonce']) || !wp_verify_nonce($_POST['cart_items_nonce'], 'save_cart_items')) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (!isset($_POST['cart_items']) || !is_array($_POST['cart_items'])) {
        return;
    }
    
    $old_cart_data = get_post_meta($post_id, '_cart_data', true);
    $old_items = json_decode($old_cart_data, true) ?: array();
    
    $items = array();
    $total = 0;
    $has_changes = false;
    
    foreach ($_POST['cart_items'] as $index => $item) {
        $price = floatval($item['price']);
        $quantity = intval($item['quantity']);
        $line_total = $price * $quantity;
        $total += $line_total;
        
        // Sprawdź czy są zmiany
        if (isset($old_items[$index])) {
            if ((float)$old_items[$index]['price'] != $price || (int)$old_items[$index]['quantity'] != $quantity) {
                $has_changes = true;
            }
        }
        
        $items[] = array(
            'product_id'  => sanitize_text_field($item['product_id'] ?? ''),
            'name'        => sanitize_text_field($item['name']),
            'sku'         => sanitize_text_field($item['sku']),
            'price'       => $price,
            'quantity'    => $quantity,
            'line_total'  => $line_total,
            'dimensions'  => sanitize_text_field($item['dimensions'] ?? ''),
            'custom_file' => esc_url_raw($item['custom_file'] ?? ''),
            'image_url'   => esc_url_raw($item['image_url'] ?? ''),
        );
    }
    
    update_post_meta($post_id, '_cart_data', json_encode($items, JSON_UNESCAPED_UNICODE));
    
    // Ceny są BRUTTO - oblicz VAT wstecz (23/123)
    $total_brutto = $total;
    $tax = round($total_brutto * 23 / 123, 2);
    
    update_post_meta($post_id, '_cart_subtotal', $total_brutto);
    update_post_meta($post_id, '_cart_tax', $tax);
    update_post_meta($post_id, '_cart_total', $total_brutto);
    
    // Zapisz info o edycji tylko jeśli były zmiany
    if ($has_changes) {
        update_post_meta($post_id, '_edited_by', get_current_user_id());
        update_post_meta($post_id, '_edited_at', current_time('Y-m-d H:i:s'));
    }
}

/**
 * Sortowalne kolumny
 */
add_filter('manage_edit-saved_cart_sortable_columns', 'auranet_saved_cart_sortable_columns');

function auranet_saved_cart_sortable_columns($columns) {
    $columns['total'] = 'total';
    $columns['date'] = 'date';
    return $columns;
}

/**
 * Ukryj przycisk "Dodaj nowy" - koszyki tylko z frontu
 */
add_action('admin_head', 'auranet_hide_add_new_cart');

function auranet_hide_add_new_cart() {
    global $pagenow, $typenow;
    
    if ($typenow === 'saved_cart') {
        echo '<style>
            .post-type-saved_cart .page-title-action { display: none !important; }
            .post-type-saved_cart #minor-publishing { display: none !important; }
        </style>';
    }
}

/**
 * Usuń "Dodaj nowy" z menu
 */
add_action('admin_menu', 'auranet_remove_add_new_cart_menu', 999);

function auranet_remove_add_new_cart_menu() {
    remove_submenu_page('edit.php?post_type=saved_cart', 'post-new.php?post_type=saved_cart');
}

/**
 * Zablokuj bezpośredni dostęp do dodawania nowego koszyka
 */
add_action('admin_init', 'auranet_block_add_new_cart');

function auranet_block_add_new_cart() {
    global $pagenow, $typenow;
    
    if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'saved_cart') {
        wp_redirect(admin_url('edit.php?post_type=saved_cart'));
        exit;
    }
}