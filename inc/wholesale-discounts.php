<?php

/**
 * Plugin Name: Wholesale Discounts (Uproszczona + Modyfikacja ceny)
 * Description: Ustawianie rabatów hurtowych dla użytkowników z rolą 'wholesale_buyer'. Rabaty w kategoriach, indywidualne ceny i rabaty procentowe. Modyfikuje cenę w sklepie.
 * Version: 1.0
 * Author: Your Name
 * Text Domain: wholesale-discounts
 */

// Zabezpieczenie przed bezpośrednim dostępem.
if (! defined('ABSPATH')) {
    exit;
}
// Ładowanie jQuery UI Autocomplete na stronie rabatów hurtowych
add_action('admin_enqueue_scripts', 'wd_enqueue_admin_scripts');
function wd_enqueue_admin_scripts($hook)
{
    if ($hook === 'toplevel_page_wholesale-discounts') {
        wp_enqueue_script('jquery-ui-autocomplete');
        // Opcjonalnie:
        // wp_enqueue_style( 'jquery-ui-style', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css' );
    }
}

/**
 * 1. Dodanie roli 'wholesale_buyer' (Odbiorca Hurtowy).
 */
add_action('init', function () {
    add_role('wholesale_buyer', __('Odbiorca Hurtowy', 'wholesale-discounts'), array('read' => true));
});

/**
 * 2. Rejestracja strony w menu administracyjnym "Rabaty Hurt".
 */
add_action('admin_menu', 'wd_register_admin_menu');
function wd_register_admin_menu()
{
    add_menu_page(
        __('Rabaty Hurt', 'wholesale-discounts'),
        __('Rabaty Hurt', 'wholesale-discounts'),
        'manage_options',
        'wholesale-discounts',
        'wd_wholesale_discounts_main_page',
        'dashicons-admin-generic',
        56
    );
}

/**
 * Główna funkcja wyświetlająca stronę "Rabaty Hurt".
 * - Jeśli w URL jest ?user_id=..., pokazuje formularz edycji rabatów.
 * - W przeciwnym wypadku pokazuje listę użytkowników z rolą 'wholesale_buyer'.
 */
function wd_wholesale_discounts_main_page()
{
    if (isset($_GET['user_id'])) {
        wd_wholesale_discounts_edit_user_page(intval($_GET['user_id']));
    } else {
        wd_wholesale_discounts_users_page();
    }
}

/**
 * 3. Wyświetlanie listy użytkowników z rolą 'wholesale_buyer'.
 */
function wd_wholesale_discounts_users_page()
{
    $users = get_users(array('role' => 'wholesale_buyer'));
?>
    <div class="wrap">
        <h1><?php esc_html_e('Rabaty hurtowe – lista użytkowników', 'wholesale-discounts'); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'wholesale-discounts'); ?></th>
                    <th><?php esc_html_e('Nazwa użytkownika', 'wholesale-discounts'); ?></th>
                    <th><?php esc_html_e('Email', 'wholesale-discounts'); ?></th>
                    <th><?php esc_html_e('Akcja', 'wholesale-discounts'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (! empty($users)) : ?>
                    <?php foreach ($users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html($user->ID); ?></td>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wholesale-discounts&user_id=' . $user->ID)); ?>">
                                    <?php esc_html_e('Edytuj rabaty', 'wholesale-discounts'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('Brak użytkowników z rolą hurtową.', 'wholesale-discounts'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}

/**
 * 4. Formularz edycji rabatów dla konkretnego użytkownika.
 */
function wd_wholesale_discounts_edit_user_page($user_id)
{
    $user = get_userdata($user_id);
    if (! $user || ! in_array('wholesale_buyer', (array) $user->roles)) {
        echo '<div class="error"><p>' . esc_html__('Niepoprawny użytkownik.', 'wholesale-discounts') . '</p></div>';
        return;
    }

    // Obsługa zapisu formularza
    if (isset($_POST['wd_wholesale_nonce']) && wp_verify_nonce($_POST['wd_wholesale_nonce'], 'wd_save_wholesale_discounts')) {
        // Kategorie – rabaty w kategoriach
        $cat_discounts = isset($_POST['cat_discount']) ? array_map('floatval', $_POST['cat_discount']) : array();
        update_user_meta($user_id, 'wholesale_category_discounts', $cat_discounts);


        // Rabaty indywidualne – TYLKO rabat procentowy
        $individual = array();
        if (isset($_POST['individual']) && is_array($_POST['individual'])) {
            foreach ($_POST['individual'] as $item) {
                if (! empty($item['product_id']) && isset($item['custom_discount']) && $item['custom_discount'] !== '') {
                    $individual[] = array(
                        'product_id'      => intval($item['product_id']),
                        'custom_discount' => floatval($item['custom_discount'])
                    );
                }
            }
        }
        update_user_meta($user_id, 'wholesale_individual_pricing', $individual);


        echo '<div class="updated"><p>' . esc_html__('Ustawienia zapisane.', 'wholesale-discounts') . '</p></div>';
    }

    // Pobranie zapisanych ustawień
    $cat_discounts = get_user_meta($user_id, 'wholesale_category_discounts', true);
    if (! is_array($cat_discounts)) {
        $cat_discounts = array();
    }
    $individual = get_user_meta($user_id, 'wholesale_individual_pricing', true);
    if (! is_array($individual)) {
        $individual = array();
    }
?>
    <div class="wrap">
        <h1>
            <?php
            /* translators: %s: user display name */
            printf(esc_html__('Ustawienia rabatów dla użytkownika: %s', 'wholesale-discounts'), esc_html($user->display_name));
            ?>
        </h1>

        <form method="post">
            <?php wp_nonce_field('wd_save_wholesale_discounts', 'wd_wholesale_nonce'); ?>

            <!-- Rabaty w kategoriach -->
            <h2><?php esc_html_e('Rabaty w kategoriach', 'wholesale-discounts'); ?></h2>
            <p><?php esc_html_e('Poniżej wybierz rabat procentowy dla poszczególnych kategorii produktów.', 'wholesale-discounts'); ?></p>

            <div style="padding:10px; border:1px solid #ccc; background:#fff;">
                <?php
                $categories = get_terms(array(
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                ));
                if (! empty($categories) && ! is_wp_error($categories)) {
                    echo '<ul style="list-style: none; margin: 0; padding: 0;">';
                    foreach ($categories as $cat) {
                        $discount = isset($cat_discounts[$cat->term_id]) ? $cat_discounts[$cat->term_id] : 0;
                        echo '<li style="margin-bottom:5px;">';
                        echo sprintf(
                            '<label>%s - <input type="number" name="cat_discount[%d]" value="%s" step="0.1" min="0" max="100" style="width:60px;" /> %%</label>',
                            esc_html($cat->name),
                            intval($cat->term_id),
                            esc_attr($discount)
                        );
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    esc_html_e('Brak kategorii produktów.', 'wholesale-discounts');
                }
                ?>
            </div>

            <!-- Rabaty indywidualne -->
            <h2 style="margin-top:30px;"><?php esc_html_e('Rabaty indywidualne', 'wholesale-discounts'); ?></h2>
            <p><?php esc_html_e('Wybierz produkt z listy i przypisz mu rabat procentowy, który będzie miał wyższy priorytet od rabatu grupowego.', 'wholesale-discounts'); ?></p>
            <p>
                <button type="button" id="wd_add_individual" class="button"><?php esc_html_e('DODAJ', 'wholesale-discounts'); ?></button>
            </p>
            <table class="wp-list-table widefat fixed striped" id="wd_individual_table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID Produktu', 'wholesale-discounts'); ?></th>
                        <th><?php esc_html_e('Nazwa Produktu', 'wholesale-discounts'); ?></th>
                        <th><?php esc_html_e('Rabat (%)', 'wholesale-discounts'); ?></th>
                        <th><?php esc_html_e('Akcja', 'wholesale-discounts'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! empty($individual)) : ?>
                        <?php foreach ($individual as $index => $item) :
                            $product = wc_get_product($item['product_id']);
                            $prod_name = $product ? $product->get_name() : __('Produkt usunięty', 'wholesale-discounts');
                        ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="individual[<?php echo esc_attr($index); ?>][product_id]" value="<?php echo esc_attr($item['product_id']); ?>" />
                                    <span style="color:#666;"><?php echo intval($item['product_id']); ?></span>
                                </td>
                                <td>
                                    <input type="text" name="individual[<?php echo esc_attr($index); ?>][product_name]" class="wd_product_name" value="<?php echo esc_attr($prod_name); ?>" style="width:200px;" />
                                </td>
                                <td>
                                    <input type="number" step="any" name="individual[<?php echo esc_attr($index); ?>][custom_discount]" value="<?php echo esc_attr($item['custom_discount']); ?>" style="width:80px;" />
                                </td>
                                <td>
                                    <button type="button" class="wd_remove_individual button">
                                        <?php esc_html_e('Usuń', 'wholesale-discounts'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('Brak indywidualnych rabatów.', 'wholesale-discounts'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="margin-top:20px;">
                <input type="submit" value="<?php esc_attr_e('Zapisz zmiany', 'wholesale-discounts'); ?>" class="button-primary" />
                <a href="<?php echo esc_url(admin_url('admin.php?page=wholesale-discounts')); ?>" class="button-secondary">
                    <?php esc_html_e('Powrót do listy użytkowników', 'wholesale-discounts'); ?>
                </a>
            </p>
        </form>
    </div><!-- .wrap -->

    <script>
        jQuery(document).ready(function($) {
            var row_index = $('#wd_individual_table tbody tr').length;
            console.log('Initial row_index:', row_index); // Debug: wypisz początkowy index

            $('#wd_add_individual').on('click', function(e) {
                e.preventDefault();
                console.log('DODAJ button clicked'); // Debug: przycisk kliknięty

                var newRow = '<tr>' +
                    '<td><input type="hidden" name="individual[' + row_index + '][product_id]" class="wd_product_id" value="" />' +
                    '<span style="color:#666;" class="wd_product_id_text"></span></td>' +
                    '<td><input type="text" name="individual[' + row_index + '][product_name]" class="wd_product_name" value="" style="width:200px;" /></td>' +
                    '<td><input type="number" step="any" name="individual[' + row_index + '][custom_discount]" value="" style="width:80px;" /></td>' +
                    '<td><button type="button" class="wd_remove_individual button"><?php esc_html_e("Usuń", "wholesale-discounts"); ?></button></td>' +
                    '</tr>';
                console.log('New row HTML:', newRow); // Debug: wypisz HTML nowego wiersza

                $('#wd_individual_table tbody').append(newRow);
                console.log('Row appended at index:', row_index);
                row_index++;
                console.log('New row_index after increment:', row_index);
            });

            $(document).on('click', '.wd_remove_individual', function(e) {
                e.preventDefault();
                $(this).closest('tr').remove();
                console.log('Row removed');
            });

            var products = [];
            <?php
            $prods = wc_get_products(array('limit' => 50));
            foreach ($prods as $prod) {
                echo "products.push({ id: " . intval($prod->get_id()) . ", label: '" . esc_js($prod->get_name()) . "' });\n";
            }
            ?>
            $(document).on('focus', '.wd_product_name', function() {
                var input = $(this);
                input.autocomplete({
                    source: products,
                    minLength: 2,
                    select: function(event, ui) {
                        var row = input.closest('tr');
                        row.find('.wd_product_id').val(ui.item.id);
                        row.find('.wd_product_id_text').text(ui.item.id);
                        input.val(ui.item.label);
                        console.log('Selected product:', ui.item);
                        return false;
                    }
                });
            });
        });
    </script>
<?php
}


/**
 * 5. Modyfikacja ceny w sklepie – uwzględnienie rabatów dla 'wholesale_buyer'.
 */
add_filter('woocommerce_product_get_price', 'wd_apply_wholesale_price', 9999, 2);
add_filter('woocommerce_product_get_regular_price', 'wd_apply_wholesale_price', 9999, 2);
// Dla wariantów produktów:
add_filter('woocommerce_product_variation_get_price', 'wd_apply_wholesale_price', 9999, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'wd_apply_wholesale_price', 9999, 2);

function wd_apply_wholesale_price($price, $product)
{
    if (! is_user_logged_in()) {
        return $price;
    }
    $user = wp_get_current_user();
    if (! in_array('wholesale_buyer', (array) $user->roles)) {
        return $price;
    }

    // Upewnij się, że $price jest liczbą
    $price = floatval($price);

    if ($price == 0 && $product->is_type('variable')) {
        $price = floatval($product->get_variation_price('min'));
    }

    $product_id = $product->get_id();
    // Pobierz indywidualne ustawienia użytkownika:
    $individual = get_user_meta($user->ID, 'wholesale_individual_pricing', true);
    if (! is_array($individual)) {
        $individual = array();
    }

    // 1. Sprawdź, czy jest indywidualna cena / rabat
    foreach ($individual as $item) {
        if (isset($item['product_id']) && intval($item['product_id']) === $product_id) {
            // Używamy tylko rabatu procentowego
            if (! empty($item['custom_discount'])) {
                $disc = floatval($item['custom_discount']);
                return max(0, $price - ($price * ($disc / 100)));
            }
        }
    }




    // 2. Jeśli brak indywidualnych ustawień, sprawdź rabat w kategoriach
    $cat_discounts = get_user_meta($user->ID, 'wholesale_category_discounts', true);
    if (! is_array($cat_discounts)) {
        $cat_discounts = array();
    }

    $terms = get_the_terms($product_id, 'product_cat');
    $max_cat_discount = 0;
    if ($terms && ! is_wp_error($terms)) {
        foreach ($terms as $term) {
            $cat_id = $term->term_id;
            if (isset($cat_discounts[$cat_id])) {
                $val = floatval($cat_discounts[$cat_id]);
                if ($val > $max_cat_discount) {
                    $max_cat_discount = $val;
                }
            }
        }
    }
    if ($max_cat_discount > 0) {
        // Upewnij się, że rabat jest liczbą
        $max_cat_discount = floatval($max_cat_discount);
        $new_price = $price - ($price * ($max_cat_discount / 100));
        return max(0, $new_price);
    }

    // 3. Jeśli nie ma żadnych rabatów indywidualnych ani kategorii, zwracamy oryginalną cenę
    return $price;
}


//Wyświetlanie cen z i przed rabatem
add_filter('woocommerce_get_price_html', 'custom_display_regular_and_discounted_price', 10000, 2);
function custom_display_regular_and_discounted_price($price_html, $product)
{
    $is_linear = get_post_meta($product->get_id(), '_linear_meter_pricing', true) === 'yes';
    $is_area = get_post_meta($product->get_id(), '_is_calculated_by_area', true) === 'yes';

    $unit = '';
    if ($is_linear) {
        $unit = ' / mb';
    } elseif ($is_area) {
        $unit = ' / m²';
    }

    if ($is_linear) {
        $effective_width_m = (100 + 70) / 1000;
        $price_per_m2 = (float) $product->get_price();
        $price_per_linear_m = $price_per_m2 * $effective_width_m;

        $price_html = wc_price($price_per_linear_m) ;
    }

    if (is_user_logged_in() && in_array('wholesale_buyer', (array) wp_get_current_user()->roles)) {
        if ($product->is_type('variable')) {
            $retail_price = $product->get_variation_regular_price('min', true);
            $discounted_price = $product->get_variation_price('min', true);
        } else {
            $retail_price = $product->get_regular_price();
            $discounted_price = $product->get_price();
        }
        if (floatval($discounted_price) < floatval($retail_price)) {
            $regular_html = '<span class="regular-price" style="text-decoration: line-through; color: #999;">' . wc_price($retail_price) . $unit . '</span>';
            $discounted_html = '<span class="discounted-price" style="font-weight: bold;">' . wc_price($discounted_price) . $unit . ' </span>';
            $price_html = $discounted_html . '<br>' . $regular_html;
            return $price_html;
        } else {
            $price_html = '<span class="discounted-price" style="font-weight: bold;">' . wc_price($discounted_price) .' </span>';
        }
    }
    return $price_html . $unit;

    // Jeśli dotyczy użytkowników hurtowych
    // if (is_user_logged_in()) {
    //     $user = wp_get_current_user();
    //     if (in_array('wholesale_buyer', (array)$user->roles)) {
    //         // Pobierz surową (detaliczną) cenę – bez rabatu
    //         if ($product->is_type('variable')) {
    //             // Dla produktów zmiennych pobieramy najniższą cenę regularną ze wszystkich wariantów
    //             $retail_price = $product->get_variation_regular_price('min', true);
    //             // Dla rabatowanej ceny – jeśli stosujesz rabat, najlepiej też użyć najniższej ceny wariantu
    //             //$discounted_price = $product->get_variation_price('min', true);
    //             $discounted_price = $product->get_price();
    //         } else {
    //             $retail_price = $product->get_regular_price();
    //             $discounted_price = $product->get_price();
    //         }

    //         // Jeśli cena rabatowana jest niższa niż regularna, wyświetlamy obie.
    //         if (floatval($discounted_price) < floatval($retail_price)) {
    //             $regular_html = '<span class="regular-price" style="text-decoration: line-through; color: #999;">' . wc_price($retail_price) .$per_square. '</span>';
    //             $discounted_html = '<span class="discounted-price" style="font-weight: bold;">' . wc_price($discounted_price) . $per_square.' </span>';
    //             $price_html = $discounted_html . '<br>' . $regular_html;
    //         } else {
    //             $price_html = '<span class="discounted-price" style="font-weight: bold;">' . wc_price($discounted_price) . $per_square.' </span>';
    //         }
    //     }
    // }
    // return $price_html . $per_square;
}
