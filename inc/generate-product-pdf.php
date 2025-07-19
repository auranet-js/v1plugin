<?php


// add_filter('woocommerce_admin_order_actions', 'dodaj_przycisk_generowania_pdf', 10, 2);
// function dodaj_przycisk_generowania_pdf($actions, $order) {
//     $actions['generate_pdf'] = array(
//         'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=generate_order_pdf&order_id=' . $order->get_id()), 'generate_order_pdf'),
//         'name'   => __('Generuj PDF produktów', 'twoj-plugin'),
//         'action' => 'view generate-pdf'
//     );
//     return $actions;
// }

// add_action('admin_head', 'dodaj_styl_przycisku_pdf');
// function dodaj_styl_przycisku_pdf() {
//     echo '<style>
//         .generate-pdf::after {
//             font-family: WooCommerce !important;
//             content: "\e02e" !important;
//         }
//     </style>';
// }


// add_action('wp_ajax_generate_order_pdf', 'generuj_pdf_produktow');
// function generuj_pdf_produktow() {
//     if (!current_user_can('manage_woocommerce')) {
//         wp_die(__('Nie masz uprawnień do wykonania tej akcji', 'twoj-plugin'));
//     }

//     check_admin_referer('generate_order_pdf');

//     $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

//     if (!$order_id) {
//         wp_die(__('Nieprawidłowe ID zamówienia', 'twoj-plugin'));
//     }

//     $order = wc_get_order($order_id);

//     if (!$order) {
//         wp_die(__('Zamówienie nie istnieje', 'twoj-plugin'));
//     }

//     $products_data = przygotuj_dane_produktow($order);
//     generuj_pdf_z_produktow($products_data, $order_id);

//     wp_die();
// }

// function przygotuj_dane_produktow($order) {
//     $items = $order->get_items();
//     $_order = $order;
//     $products_data = array();

//     foreach ($items as $item) {
//         $product = $item->get_product();
//         $product_id = $product ? $product->get_id() : 0;

//         // Get variation attributes if it's a variable product
//         $variation_data = '';
//         if ($product && $product->is_type('variation')) {
//             $attributes = $product->get_variation_attributes();
//             if (!empty($attributes)) {
//                 $variation_data = array();
//                 foreach ($attributes as $attribute_name => $attribute_value) {
//                     $taxonomy = str_replace('attribute_', '', $attribute_name);
//                     $term_name = $attribute_value;

//                     if (taxonomy_exists($taxonomy)) {
//                         $term = get_term_by('slug', $attribute_value, $taxonomy);
//                         if ($term && !is_wp_error($term)) {
//                             $term_name = $term->name;
//                         }
//                     }

//                     $variation_data[] = wc_attribute_label($taxonomy) . ': ' . $term_name;
//                 }
//                 $variation_data = implode(', ', $variation_data);
//             }
//         }

//         $product_image_url = '';
//         if ($product) {
//             if ($product->get_image_id()) {
//                 $product_image_url = wp_get_attachment_url($product->get_image_id());
//             }
//         }

//         $custom_file_path = $item->get_meta('custom_file_path');
//         $custom_file_url = '';

//         if (!empty($custom_file_path)) {
//             if (filter_var($custom_file_path, FILTER_VALIDATE_URL)) {
//                 $custom_file_url = $custom_file_path;
//             } else {
//                 $upload_dir = wp_upload_dir();
//                 if (file_exists($upload_dir['basedir'] . '/' . $custom_file_path)) {
//                     $custom_file_url = $upload_dir['baseurl'] . '/' . $custom_file_path;
//                 } elseif (file_exists($custom_file_path)) {
//                     $custom_file_url = site_url(str_replace(ABSPATH, '', $custom_file_path));
//                 }
//             }
//         }


//         $item_data = $item->get_data();
//         $custom_length = $item->get_meta('custom_length');
//         $custom_width = $item->get_meta('custom_width');

//         $products_data[] = array(
//             'name' => $item->get_name(),
//             'quantity' => $item->get_quantity(),
//             'price' => wc_price($item->get_total()),
//             'sku' => $product ? $product->get_sku() : '',
//             'variation_data' => $variation_data,
//             'custom_file_url' => $custom_file_url,
//             'product_image_url' => $product_image_url,
//             'custom_length' => $custom_length ? $custom_length : 'N/A',
//             'custom_width' => $custom_width ? $custom_width : 'N/A'
//         );
//     }

//     return $products_data;
// }

// function generuj_pdf_z_produktow($products_data, $order_id) {
//     require_once plugin_dir_path(__DIR__) . 'vendor/autoload.php';

//     $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
//     $html .= '<style>
//         body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
//         h1 { color: #333; text-align: center; margin-bottom: 30px; }
//         h2 { color: #333; margin-top: 40px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
//         .product-container { margin-bottom: 40px; page-break-inside: avoid; }
//         .product-header { overflow: hidden; margin-bottom: 20px; } 
//         .product-image { width: 48%; float: left; padding-right: 2%; }
//         .product-image img { max-width: 100%; height: auto; margin-bottom: 10px; }
//         .product-details { width: 48%; float: left; padding-left: 2%; } 
//         .clearfix::after { content: ""; clear: both; display: table; }
//         table { width: 100%; border-collapse: collapse; font-size: 12px; }
//         table, th, td { border: 1px solid #ddd; }
//         th, td { padding: 8px; text-align: left; }
//         th { background-color: #f2f2f2; width: 40%; }
//         .custom-file { margin-top: 10px; clear: both; }
//         .custom-file-label { font-weight: bold; margin-bottom: 15px; }
//     </style>';
//     $html .= '</head><body>';

//     foreach ($products_data as $product) {
//         $html .= '<div class="product-container">';

//         $html .= '<h2>' . $product['name'] . '</h2>';

//         $html .= '<div class="product-header clearfix">';

//         $html .= '<div class="product-image">';
//         if (!empty($product['product_image_url'])) {
//             $html .= '<img src="' . $product['product_image_url'] . '" />';
//         } else {
//             $html .= '<p>Brak zdjęcia produktu</p>';
//         }
//         $html .= '</div>';

//         $html .= '<div class="product-details">';
//         $html .= '<table>';
//         $html .= '<tr><th>SKU</th><td>' . ($product['sku'] ? $product['sku'] : 'N/A') . '</td></tr>';
//         if (!empty($product['variation_data'])) {
//             $html .= '<tr><th>Wariant</th><td>' . $product['variation_data'] . '</td></tr>';
//         }
//         $html .= '<tr><th>Ilość</th><td>' . $product['quantity'] . '</td></tr>';
//         $html .= '<tr><th>Cena</th><td>' . $product['price'] . '</td></tr>';
//         $html .= '<tr><th>Długość</th><td>' . $product['custom_length'] . ' mm</td></tr>';
//         $html .= '<tr><th>Szerokość</th><td>' . $product['custom_width'] . ' mm</td></tr>';
//         $html .= '</table>';
//         $html .= '</div>'; 

//         $html .= '</div>'; 

//         if (!empty($product['custom_file_url'])) {
//             $html .= '<div class="custom-file">';
//             $html .= '<div class="custom-file-label">Plik niestandardowy:</div>';
//             $html .= '<img  style="max-width: 100%; width: auto; height: auto; max-height: 250mm; margin: 0 auto; display: block;" src="' . $product['custom_file_url'] . '" />';
//             $html .= '</div>';
//         }

//         $html .= '</div>';
//     }

//     $html .= '</body></html>';

//     $options = new \Dompdf\Options();
//     $options->set('defaultFont', 'DejaVu Sans');
//     $options->setIsRemoteEnabled(true); 

//     $dompdf = new \Dompdf\Dompdf($options);
//     $dompdf->loadHtml($html);
//     $dompdf->setPaper('A4', 'portrait');
//     $dompdf->render();

//     $filename = 'produkty-zamowienie-' . $order_id . '.pdf';

//     $dompdf->stream($filename, array('Attachment' => true));
//     exit;
// }


// add_action('woocommerce_admin_order_data_after_order_details', 'dodaj_przycisk_pdf_w_szczegolach_zamowienia');
// function dodaj_przycisk_pdf_w_szczegolach_zamowienia($order) {
//     $url = wp_nonce_url(admin_url('admin-ajax.php?action=generate_order_pdf&order_id=' . $order->get_id()), 'generate_order_pdf');
//     echo '<p class="form-field form-field-wide">';
//     echo '<a href="' . esc_url($url) . '" class="button button-primary" target="_blank">' . __('Generuj PDF produktów', 'twoj-plugin') . '</a>';
//     echo '</p>';
// }
add_action('woocommerce_after_add_to_cart_form', 'dodaj_przycisk_pdf_na_stronie_produktu', 50);
function dodaj_przycisk_pdf_na_stronie_produktu()
{
    global $product;

    if (!is_user_logged_in()) {
        return;
    }

    $image_url = wp_get_attachment_url($product->get_image_id());
    $image_base64 = '';
    if ($image_url) {
        $image_data = file_get_contents($image_url);
        $image_base64 = 'data:image/' . pathinfo($image_url, PATHINFO_EXTENSION) . ';base64,' . base64_encode($image_data);
    }

    $product_data = array(
        'product_id' => $product->get_id(),
        'name' => $product->get_name(),
        'price' => $product->get_price(),
        'image_base64' => $image_base64,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('generate_product_pdf')
    );

    echo '<div class="product-pdf-button" style="margin-top: 20px;">';
    echo '<button id="generate-product-pdf" class="button" data-product=\'' . json_encode($product_data) . '\'>';
    echo __('Generuj PDF produktu', 'twoj-plugin');
    echo '</button>';
    echo '<div class="pdf-messages" style="margin-top: 10px; color: red;"></div>';
    echo '</div>';

    add_action('wp_footer', function () {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#generate-product-pdf').on('click', function() {
                    var button = $(this);
                    var productData = button.data('product');

                    var customLength = $('#custom_length').val();
                    var customWidth = $('#custom_width').val();
                    var kapinosy = $('#pa_kapinosy').val();
                    var narozniki = $('#pa_narozniki').val();
                    var variationId = $('input.variation_id').val();
                    var finalPrice = $('#final_price').text();
                    var productId = $('input[name="product_id"]').val();
                    var quantity = $('input[name="quantity"]').val();


                    var messagesContainer = $('.pdf-messages');
                    if (!messagesContainer.length) {
                        button.after('<div class="pdf-messages" style="margin-top: 10px; color: red;"></div>');
                        messagesContainer = $('.pdf-messages');
                    }
                    messagesContainer.empty();

                    var errors = [];

                    if (!customLength || customLength.trim() === '') {
                        errors.push('Długość jest wymagana');
                    }

                    if (!customWidth || customWidth.trim() === '') {
                        errors.push('Szerokość jest wymagana');
                    }

                    if (!kapinosy || kapinosy.trim() === '') {
                        errors.push('Kapinosy są wymagane');
                    }

                    if (!narozniki || narozniki.trim() === '') {
                        errors.push('Narożniki są wymagane');
                    }

                    if (!variationId || variationId.trim() === '') {
                        errors.push('Wariant produktu jest wymagany');
                    }

                    if (errors.length > 0) {
                        messagesContainer.html('<strong>Proszę uzupełnić następujące pola:</strong><ul>' +
                            errors.map(error => '<li>' + error + '</li>').join('') + '</ul>');
                        return false;
                    }

                    var productImage = '';
                    var fileInput = $('#custom_file')[0];
                    if (fileInput.files && fileInput.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            productImage = e.target.result;
                            sendAjaxRequest();
                        };
                        reader.readAsDataURL(fileInput.files[0]);
                    } else {
                        sendAjaxRequest();
                    }

                    function sendAjaxRequest() {
                        button.prop('disabled', true).text('Generowanie...');

                        productData.custom_length = customLength;
                        productData.custom_width = customWidth;
                        productData.kapinosy = kapinosy;
                        productData.narozniki = narozniki;
                        productData.variation_id = variationId;
                        productData.product_id = productId;
                        productData.image_base64 = productImage;
                        productData.final_price = finalPrice;
                        productData.quantity = quantity;


                        $.ajax({
                            url: productData.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'generate_product_pdf',
                                product: productData,
                                _wpnonce: productData.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    window.open(response.data.pdf_url, '_blank');
                                } else {
                                    alert('Wystąpił błąd: ' + response.data.message);
                                }
                                button.prop('disabled', false).text('Generuj PDF produktu');
                            },
                            error: function() {
                                alert('Wystąpił błąd podczas generowania PDF');
                                button.prop('disabled', false).text('Generuj PDF produktu');
                            }
                        });
                    }
                });
            });
        </script>
<?php
    });
}

add_action('wp_ajax_generate_product_pdf', 'generuj_pdf_produktu_ajax');
function generuj_pdf_produktu_ajax()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Musisz być zalogowany, aby wygenerować PDF', 'twoj-plugin')));
    }

    check_ajax_referer('generate_product_pdf', '_wpnonce');

    if (!isset($_POST['product'])) {
        wp_send_json_error(array('message' => __('Brak danych produktu', 'twoj-plugin')));
    }

    $product_data = $_POST['product'];

    $pdf_data = array(
        'name' => $product_data['name'],
        'productId' => $product_data['product_id'],
        'price' => $product_data['final_price'],
        'product_image_base64' => $product_data['image_base64'],
        'custom_length' =>  $product_data["custom_length"],
        'custom_width' => $product_data["custom_width"],
        'kapinosy' => $product_data["kapinosy"],
        'narozniki' => $product_data["narozniki"],
        'quantity' => $product_data["quantity"],
    );

    $pdf_url = generuj_pdf_z_produktow($pdf_data, $product_data['product_id'], true);

    if ($pdf_url) {
        wp_send_json_success(array('pdf_url' => $pdf_url));
    } else {
        wp_send_json_error(array('message' => __('Błąd podczas generowania PDF', 'twoj-plugin')));
    }
}

function generuj_pdf_z_produktow($products_data, $order_id, $return_url = false)
{
    require_once plugin_dir_path(__DIR__) . 'vendor/autoload.php';
    $product = wc_get_product($products_data["productId"]);
    $image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');

    $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
    $html .= '<style>
        body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
        h1 { color: #333; text-align: center; margin-bottom: 30px; }
        h2 { color: #333; margin-top: 40px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .product-container { margin-bottom: 40px; page-break-inside: avoid; }
        .product-header { overflow: hidden; margin-bottom: 20px; } 
        .product-image { width: 48%; float: left; padding-right: 2%; }
        .product-image img { max-width: 100%; height: auto; margin-bottom: 10px; }
        .product-details { width: 48%; float: left; padding-left: 2%; } 
        .clearfix::after { content: ""; clear: both; display: table; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; width: 40%; }
    </style>';
    $html .= '</head><body>';

    $html .= '<div class="product-container">';

    $html .= '<h2>' . $products_data['name'] . '</h2>';

    $html .= '<div class="product-header clearfix">';


    $html .= '<div class="product-image">';
    if (!empty($image_url)) {
        $html .= '<img src="' . $image_url . '" />';
    } else {
        $html .= '<p>Brak zdjęcia produktu</p>';
    }
    $html .= '</div>';

    $html .= '<div class="product-details">';
    $html .= '<table>';
    $html .= '<tr><th>Cena</th><td>' . get_base_price($product) . ' / m²</td></tr>';
    $html .= '<tr><th>Koszt</th><td>' . $products_data['price'] . '</td></tr>';
    $html .= '<tr><th>Ilość</th><td>' . $products_data['quantity'] . '</td></tr>';
    $html .= '<tr><th>Długość</th><td>' . $products_data['custom_length'] . ' mm</td></tr>';
    $html .= '<tr><th>Szerokość</th><td>' . $products_data['custom_width'] . ' mm</td></tr>';
    $html .= '<tr><th>Kapinosy</th><td>' . $products_data['kapinosy'] . '</td></tr>';
    $html .= '<tr><th>Narożniki</th><td>' . $products_data['narozniki'] . '</td></tr>';
    $html .= '</table>';
    $html .= '</div>';

    $html .= '</div>';
    if (!empty($products_data['product_image_base64'])) {
        $html .= '<div class="custom-file">';
        $html .= '<div class="custom-file-label">Załącznik klienta:</div>';
        $html .= '<img src="' . $products_data['product_image_base64'] . '" style="max-width: 100%; height: auto;" />';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</body></html>';

    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->setIsRemoteEnabled(true);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'produkt-' . $order_id . '-' . time() . '.pdf';

    if ($return_url) {
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['path'] . '/' . $filename;
        file_put_contents($pdf_path, $dompdf->output());
        return $upload_dir['url'] . '/' . $filename;
    } else {
        $dompdf->stream($filename, array('Attachment' => true));
        exit;
    }
}
