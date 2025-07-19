<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Plugin Name: Victorini2025 by Auranet
 * Description: Rozszerzenie WooCommerce – dodaje do produktów możliwość uploadu pliku szkicu oraz pola tekstowego "Uwagi". Dla zalogowanych użytkowników, a w panelu admina strona "Szkice klientów" umożliwiająca przegląd i zbiorowe usuwanie przesłanych szkiców.
 * Version: 1.1
 * Author: Auranet
 * Text Domain: victorini2025-by-auranet
 */

/* ===============================================
   FRONTEND – DODAWANIE POLA UPLOADU I UWAG DO PRODUKTU
=============================================== */
// Wyświetlenie pola uploadu i pola tekstowego "Uwagi" na stronie produktu (tylko dla zalogowanych)
function victorini_display_sketch_and_note_fields() {
    // Tylko dla zalogowanych użytkowników na stronie produktu
    if ( is_user_logged_in() && is_product() ) {
        ?>
        <!-- Prosty kontener (bez stylowania) -->
        <div class="victorini-custom-fields">
            <p>
                <label for="customer_sketch">
                    <?php printf(esc_html__('Dodaj szkic (jpg, png, pdf, gif, bmp, max %dMB):', 'victorini2025-by-auranet'), intval(get_option('victorini_upload_limit', 10))); ?>
                </label><br>
                <input 
                    type="file" 
                    name="customer_sketch" 
                    id="customer_sketch" 
                    accept=".jpg,.jpeg,.png,.pdf,.gif,.bmp"
                >
            </p>
            <p id="customer_sketch_preview"></p>
            <p>
                <label for="customer_note">
                    <?php esc_html_e('Uwagi:', 'victorini2025-by-auranet'); ?>
                </label><br>
                <textarea 
                    name="customer_note" 
                    id="customer_note" 
                    rows="3"
                ></textarea>
            </p>
        </div>

        <!-- Skrypt podglądu pliku -->
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const fileInput = document.getElementById('customer_sketch');
            if (fileInput) {
                fileInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    const previewContainer = document.getElementById('customer_sketch_preview');
                    previewContainer.innerHTML = ''; // Czyścimy poprzedni podgląd
                    if (file) {
                        // Jeśli plik jest obrazem, generujemy podgląd
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.style.maxWidth = '200px';
                                img.style.maxHeight = '200px';
                                previewContainer.appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        } else {
                            // Dla innych formatów wyświetlamy tylko nazwę pliku
                            const info = document.createElement('p');
                            info.textContent = 
                                "<?php esc_html_e('Wybrano plik:', 'victorini2025-by-auranet'); ?> " + file.name;
                            previewContainer.appendChild(info);
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
}

// Używamy priorytetu 15 (lub innego >10), aby pojawiło się PO wariantach, ale PRZED przyciskiem
add_action( 'woocommerce_before_add_to_cart_button', 'victorini_display_sketch_and_note_fields', 15 );





// Przechwycenie danych z pól przy dodawaniu do koszyka
function victorini_capture_sketch_and_note( $cart_item_data, $product_id ) {
    // Obsługa uploadu pliku (jeśli użytkownik coś wybrał)
    if ( isset($_FILES['customer_sketch']) && !empty($_FILES['customer_sketch']['name']) ) {
        $file = $_FILES['customer_sketch'];
        // Walidacja rozmiaru
        $max_mb = intval(get_option('victorini_upload_limit', 10));
        if ( $file['size'] > $max_mb * 1024 * 1024 ) {
            wc_add_notice( sprintf( __( 'Plik jest za duży. Maksymalny rozmiar to %dMB.', 'victorini2025-by-auranet' ), $max_mb ), 'error' );
            return $cart_item_data;
        }
        // Dozwolone typy MIME
        $allowed_types = array( 'image/jpeg', 'image/png', 'application/pdf', 'image/gif', 'image/bmp' );
        $file_type = wp_check_filetype( $file['name'] );
        if ( ! in_array( $file_type['type'], $allowed_types ) ) {
            wc_add_notice( __( 'Niedozwolony typ pliku.', 'victorini2025-by-auranet' ), 'error' );
            return $cart_item_data;
        }
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $upload_overrides = array( 'test_form' => false );
        $uploaded_file = wp_handle_upload( $file, $upload_overrides );
        if ( isset( $uploaded_file['url'] ) ) {
            $cart_item_data['customer_sketch'] = $uploaded_file['url'];
        }
    }
    // Przechwycenie pola tekstowego "Uwagi"
    if ( isset( $_POST['customer_note'] ) && ! empty( $_POST['customer_note'] ) ) {
        $cart_item_data['customer_note'] = sanitize_text_field( $_POST['customer_note'] );
    }
    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'victorini_capture_sketch_and_note', 10, 2 );

// Przekazanie danych z koszyka do zamówienia (dla każdej pozycji)
function victorini_add_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['customer_sketch'] ) ) {
        $item->update_meta_data( 'customer_sketch', $values['customer_sketch'] );
    }
    if ( isset( $values['customer_note'] ) ) {
        $item->update_meta_data( 'customer_note', $values['customer_note'] );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'victorini_add_order_item_meta', 10, 4 );

/* ===============================================
   PANEL ADMINISTRACYJNY – STRONA "SZKICE KLIENTÓW"
=============================================== */
function victorini_register_sketch_admin_page() {
    add_menu_page(
        'Szkice klientów',
        'Szkice klientów',
        'manage_options',
        'customer-sketches',
        'victorini_render_sketch_admin_page',
        'dashicons-format-image',
        56
    );
}
add_action( 'admin_menu', 'victorini_register_sketch_admin_page' );

function victorini_render_sketch_admin_page() {
    // Pobranie wszystkich zamówień
    $orders = wc_get_orders( array(
        'limit' => -1,
    ) );
    
    // Filtrujemy zamówienia posiadające przynajmniej jedną linię z 'customer_sketch'
    $orders_with_sketch = array();
    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( 'customer_sketch' ) ) {
                $orders_with_sketch[ $order->get_id() ] = $order;
                break;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Szkice klientów', 'victorini2025-by-auranet' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'delete_customer_sketches', 'customer_sketch_nonce' ); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="cb-select-all"></th>
                        <th><?php esc_html_e( 'ID Zamówienia', 'victorini2025-by-auranet' ); ?></th>
                        <th><?php esc_html_e( 'Szkic', 'victorini2025-by-auranet' ); ?></th>
                        <th><?php esc_html_e( 'Uwagi', 'victorini2025-by-auranet' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $orders_with_sketch ) ) : ?>
                        <?php foreach ( $orders_with_sketch as $order ) : 
                            $order_id = $order->get_id();
                            // Wyszukujemy szkice z każdej pozycji
                            $sketches = array();
                            $notes    = array();
                            foreach ( $order->get_items() as $item ) {
                                $sketch = $item->get_meta( 'customer_sketch' );
                                $note   = $item->get_meta( 'customer_note' );
                                if ( $sketch ) {
                                    $sketches[] = $sketch;
                                }
                                if ( $note ) {
                                    $notes[] = $note;
                                }
                            }
                            ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="order_ids[]" value="<?php echo esc_attr( $order_id ); ?>">
                                </th>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>">
                                        <?php echo esc_html( $order_id ); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    // Wyświetlamy linki do szkiców – oddzielone przecinkiem
                                    echo implode( ', ', array_map( function( $url ) {
                                        return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'Zobacz szkic', 'victorini2025-by-auranet' ) . '</a>';
                                    }, $sketches ) );
                                    ?>
                                </td>
                                <td>
                                    <?php echo implode( ', ', $notes ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'Brak szkiców do wyświetlenia.', 'victorini2025-by-auranet' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <br>
            <input type="submit" name="delete_sketches" class="button button-primary" value="<?php esc_attr_e( 'Usuń zaznaczone szkice', 'victorini2025-by-auranet' ); ?>">
        </form>
    </div>
    <script>
    // Skrypt do zaznaczania/odznaczania wszystkich checkboxów
    document.getElementById('cb-select-all').addEventListener('change', function(){
        var checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });
    </script>
    <?php
    // Obsługa usunięcia – po zatwierdzeniu formularza
    if ( isset( $_POST['delete_sketches'] ) && check_admin_referer( 'delete_customer_sketches', 'customer_sketch_nonce' ) ) {
        if ( ! empty( $_POST['order_ids'] ) ) {
            foreach ( $_POST['order_ids'] as $order_id ) {
                $order = wc_get_order( $order_id );
                foreach ( $order->get_items() as $item ) {
                    $sketch = $item->get_meta( 'customer_sketch' );
                    if ( $sketch ) {
                        // Usunięcie pliku z serwera – przekształcamy URL na ścieżkę lokalną
                        $upload_dir = wp_upload_dir();
                        $file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $sketch );
                        if ( file_exists( $file_path ) ) {
                            unlink( $file_path );
                        }
                        // Usuwamy meta – aby nie wyświetlać już szkicu
                        $item->delete_meta_data( 'customer_sketch' );
                    }
                }
                $order->save();
            }
            echo '<div class="updated"><p>' . esc_html__( 'Zaznaczone szkice zostały usunięte.', 'victorini2025-by-auranet' ) . '</p></div>';
        }
    }
}
