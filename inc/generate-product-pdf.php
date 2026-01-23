<?php

add_action('woocommerce_after_add_to_cart_form', 'dodaj_przycisk_pdf_na_stronie_produktu', 50);
function dodaj_przycisk_pdf_na_stronie_produktu()
{
    global $product;

    if (!is_user_logged_in()) {
        return;
    }

    $product_id = $product->get_id();

    $image_url = wp_get_attachment_url($product->get_image_id());
    $image_base64 = '';
    if ($image_url) {
        $image_data = file_get_contents($image_url);
        $image_base64 = 'data:image/' . pathinfo($image_url, PATHINFO_EXTENSION) . ';base64,' . base64_encode($image_data);
    }

    // Dane z "Wysyłka"
    $shipping_length = $product->get_length() ?: '';
    $shipping_width = $product->get_width() ?: '';

    $product_data = array(
        'product_id' => $product_id,
        'name' => $product->get_name(),
        'price' => $product->get_price(),
        'image_base64' => $image_base64,
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('generate_product_pdf'),
        'shipping_length' => $shipping_length,
        'shipping_width' => $shipping_width
    );

    echo '<div class="product-pdf-button" style="margin-top: 20px;">';

    // Dodaj hidden input product_id jeśli brakuje
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            if (!document.querySelector("input[name=\'product_id\']")) {
                var hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = "product_id";
                hiddenInput.value = "' . esc_js($product_id) . '";
                var form = document.querySelector("form.cart");
                if (form) form.appendChild(hiddenInput);
            }
        });
    </script>';

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

                    var customLength = $('#custom_length').length ? $('#custom_length').val() : productData.shipping_length;
                    var customWidth = $('#custom_width').length ? $('#custom_width').val() : productData.shipping_width;
                    var kapinosy = $('#pa_kapinosy').val();
                    var narozniki = $('#pa_narozniki').val();
                    var variationId = $('input.variation_id').val() || '';
                    var productId = $('input[name="product_id"]').val() || productData.product_id;
                    var finalPrice = $('#final_price').text();
                    var quantity = $('input[name="quantity"]').val();

                    var messagesContainer = $('.pdf-messages');
                    if (!messagesContainer.length) {
                        button.after('<div class="pdf-messages" style="margin-top: 10px; color: red;"></div>');
                        messagesContainer = $('.pdf-messages');
                    }
                    messagesContainer.empty();

                    var errors = [];

                    if ($('#custom_length').length && $('#custom_length').is(':visible') && (!customLength || customLength.trim() === '')) {
                        errors.push('Długość jest wymagana');
                    }

                    if ($('#custom_width').length && $('#custom_width').is(':visible') && (!customWidth || customWidth.trim() === '')) {
                        errors.push('Szerokość jest wymagana');
                    }

                    if ($('#pa_kapinosy').is(':visible') && (!kapinosy || kapinosy.trim() === '')) {
                        errors.push('Kapinosy są wymagane');
                    }
                    
                    if ($('#pa_narozniki').is(':visible') && (!narozniki || narozniki.trim() === '')) {
                        errors.push('Narożniki są wymagane');
                    }

                    if ($('input.variation_id').length && variationId.trim() === '') {
                        errors.push('Wariant produktu jest wymagany');
                    }

                    kapinosy = kapinosy && kapinosy.trim() !== '' ? kapinosy : 'Nie dotyczy';
                    narozniki = narozniki && narozniki.trim() !== '' ? narozniki : 'Nie dotyczy';

                    if (errors.length > 0) {
                        messagesContainer.html('<strong>Proszę uzupełnić następujące pola:</strong><ul>' +
                            errors.map(error => '<li>' + error + '</li>').join('') + '</ul>');
                        return false;
                    }

                    var productImage = '';
                    var fileInput = $('#custom_file')[0];
                    if (fileInput && fileInput.files && fileInput.files[0]) {
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

    $base_price = get_base_price($product);
    $length = floatval($products_data['custom_length']);
    $width = floatval($products_data['custom_width']);
    $quantity = intval($products_data['quantity']);

    if ($length > 0 && $width > 0) {
        // Zakładamy, że rozmiary są w mm -> przelicz na m²
        $area_m2 = ($length / 1000) * ($width / 1000);
        $total = round($area_m2 * $base_price * $quantity, 2);
    } else {
        $total = get_base_price($product) * $quantity;
    }

    $html .= '</div>';

    $html .= '<div class="product-details">';
    $html .= '<table>';
    $html .= '<tr><th>Cena</th><td>' . get_base_price($product) . ' / m²</td></tr>';
    $html .= '<tr><th>Koszt</th><td>' . $total . ' PLN</td></tr>';
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
