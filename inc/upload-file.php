<?php
if (!defined('ABSPATH')) {
    exit;
}

function getUploadedFileUrl(){
    $current_file_url = '';
    if (isset($_GET['victorini_action']) && $_GET['victorini_action'] === 'edit_cart_item' && isset($_GET['cart_item_key'])) {
        $cart_item_key = sanitize_text_field($_GET['cart_item_key']);
        $cart = WC()->cart->get_cart();
        
        if (isset($cart[$cart_item_key]) && isset($cart[$cart_item_key]['custom_file'])) {
            $current_file_url = $cart[$cart_item_key]['custom_file'];
        }
    }
    return $current_file_url;
}



function setDataFromCart(){
    $cart_item_data = getCartItemData();
    $attributes = $cart_item_data['attributes'];


    $kapinosy = isset($attributes['attribute_pa_kapinosy']) ? $attributes['attribute_pa_kapinosy'] : '';
    $narozniki = isset($attributes['attribute_pa_narozniki']) ? $attributes['attribute_pa_narozniki'] : '';
   


    if(!empty($kapinosy) && !empty($narozniki)) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                setTimeout(function() {
                    $('#pa_kapinosy').val("<?php echo esc_js($kapinosy); ?>").trigger('change');
                    $('#pa_narozniki').val("<?php echo esc_js($narozniki); ?>").trigger('change');
                }, 500); 
            });
        </script>
        <?php
    }
}

/**
 * Dodanie pola uploadu pliku przed dodaniem do koszyka
 */
function custom_wc_display_file_upload() {

   
   
    $current_file_url = getUploadedFileUrl();
    setDataFromCart();


    ?>
    <div class="custom-file-upload" style="margin: 12px 0; display: block;">
        <label for="custom_file">Dodaj załącznik (JPG, PNG, BMP, JPEG, max 10MB):</label>
        <input type="file" id="custom_file"  name="custom_file" accept=".jpg,.jpeg,.png,.bmp">
        <p id="file_upload_error" style="color:red;"></p>
        <div id="file_preview" style="margin-top:10px;"></div>
    </div>
    <?php
    
    echo '<div id="file_preview" style="margin-top:10px; margin-bottom:10px;"></div>';
    if ($current_file_url) {
        echo '<input type="hidden" name="existing_custom_file" value="' . esc_attr($current_file_url) . '" />';
}
 ?>
<script>
  var isUserLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
document.addEventListener("DOMContentLoaded", function(){
    var currentFileUrl = "<?= $current_file_url ?>";
    var fileInput = document.getElementById("custom_file");
    var previewElement = document.getElementById("file_preview");
    
    if (currentFileUrl) {
        previewElement.innerHTML = "<img src=\"" + currentFileUrl + "\" style=\"max-width:100px; max-height:100px; display:block; margin-bottom:5px;\" />" +
                                   "<a href=\"#\" id=\"remove_file\" style=\"text-decoration:none; color:#0073aa;\">usuń</a>";
        
        document.getElementById("remove_file").addEventListener("click", function(ev) {
            var existingFileInput = document.querySelector('input[name="existing_custom_file"]');
            if(existingFileInput) existingFileInput.remove();
            ev.preventDefault();
            fileInput.value = "";
            previewElement.innerHTML = "";
        });
    }


    fileInput.addEventListener("change", function(event) {
        var file = event.target.files[0];
        var errorElement = document.getElementById("file_upload_error");
        var previewElement = document.getElementById("file_preview");
        errorElement.textContent = "";
        previewElement.innerHTML = "";

        if (!isUserLoggedIn) {
            errorElement.textContent = "Musisz być zalogowany, aby przesłać plik!";
            event.target.value = ""; 
            return;
        }
        
        if (file) {
            var allowedTypes = ["image/jpeg", "image/png", "image/bmp"];
            if (!allowedTypes.includes(file.type)) {
                errorElement.textContent = "Nieprawidłowy format pliku!";
                event.target.value = "";
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                errorElement.textContent = "Plik jest za duży (max 10MB)!";
                event.target.value = "";
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                previewElement.innerHTML = "<img src=\"" + e.target.result + "\" style=\"max-width:100px; max-height:100px; display:block; margin-bottom:5px;\" />" +
                                           "<a href=\"#\" id=\"remove_file\" style=\"text-decoration:none; color:#0073aa;\">usuń</a>";
                
                document.getElementById("remove_file").addEventListener("click", function(ev) {
                    ev.preventDefault();
                    fileInput.value = "";
                    previewElement.innerHTML = "";
                });
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
<?php


}
add_action('woocommerce_after_add_to_cart_button', 'custom_wc_display_file_upload', 100);


/**
 * Wyświetlanie miniatury pliku w koszyku
 */
add_filter('woocommerce_get_item_data', 'custom_wc_display_cart_item_data', 10, 2);
function custom_wc_display_cart_item_data($item_data, $cart_item) {
    if (isset($cart_item['custom_file']) && !empty($cart_item['custom_file'])) {
        $file_url = esc_url($cart_item['custom_file']);
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'bmp');
        $file_extension = strtolower(pathinfo($file_url, PATHINFO_EXTENSION));
        if (in_array($file_extension, $allowed_extensions)) {
            $thumbnail_html = '<img src="' . $file_url . '" style="max-width: 50px; max-height: 50px; display: block; margin-top: 5px;">';
        } else {
            $thumbnail_html = '<a href="' . $file_url . '" target="_blank">Pobierz plik</a>';
        }
        $item_data[] = array(
            'name'  => __('Załączony plik', 'woocommerce'),
            'value' => $thumbnail_html
        );
        error_log('Wyświetlanie pliku w koszyku: ' . $file_url);
    }
    return $item_data;
}

/**
 * Przekazanie pliku do zamówienia
 */
if (!function_exists('custom_wc_add_order_item_meta')) {
    function custom_wc_add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['custom_file'])) {
            $item->update_meta_data('custom_file_path', $values['custom_file']);
        }
    }
    add_action('woocommerce_checkout_create_order_line_item', 'custom_wc_add_order_item_meta', 10, 4);
}


function add_custome_file_cart_item_data($cart_item_data, $product_id) {
    if (!is_user_logged_in() && !empty($_FILES['custom_file']['name'])) {
        wc_add_notice(__('Musisz być zalogowany, aby przesłać plik.', 'woocommerce'), 'error');
        return $cart_item_data;
    }

    if (!empty($_FILES['custom_file']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload_overrides = array('test_form' => false);
        $uploaded_file = wp_handle_upload($_FILES['custom_file'], $upload_overrides);
        
        if ($uploaded_file && !isset($uploaded_file['error'])) {
            $cart_item_data['custom_file'] = $uploaded_file['url'];
            $cart_item_data['custom_file_path'] = $uploaded_file['file'];
        }
    } 
    elseif (isset($_POST['existing_custom_file']) && !empty($_POST['existing_custom_file'])) {
        $cart_item_data['custom_file'] = esc_url($_POST['existing_custom_file']);
    }
    
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'add_custome_file_cart_item_data', 10, 2);