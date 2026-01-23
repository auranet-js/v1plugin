<?php
/**
 * Ustawienia PDF koszyków - header, footer, numeracja
 * 
 * @package Victorini2025
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dodaj podmenu w menu Koszyki PDF
 */
add_action('admin_menu', 'auranet_cart_pdf_settings_menu');

function auranet_cart_pdf_settings_menu() {
    add_submenu_page(
        'edit.php?post_type=saved_cart',
        'Ustawienia PDF',
        'Ustawienia PDF',
        'manage_options',
        'cart-pdf-settings',
        'auranet_cart_pdf_settings_page'
    );
}

/**
 * Rejestracja ustawień
 */
add_action('admin_init', 'auranet_cart_pdf_register_settings');

function auranet_cart_pdf_register_settings() {
    // Sekcja: Numeracja
    add_settings_section(
        'auranet_cart_pdf_numbering',
        'Numeracja kalkulacji',
        '__return_false',
        'cart-pdf-settings'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_prefix', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'KOSZ',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_prefix',
        'Prefix numeru',
        'auranet_cart_pdf_prefix_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_numbering'
    );
    
    // Sekcja: Header
    add_settings_section(
        'auranet_cart_pdf_header_section',
        'Nagłówek PDF',
        function() {
            echo '<p>Treść wyświetlana na górze dokumentu PDF. Możesz użyć podstawowego HTML (tabele, pogrubienia).</p>';
        },
        'cart-pdf-settings'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_logo', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_logo',
        'Logo firmy (URL)',
        'auranet_cart_pdf_logo_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_header_section'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_header', array(
        'type'              => 'string',
        'sanitize_callback' => 'wp_kses_post',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_header',
        'Treść nagłówka',
        'auranet_cart_pdf_header_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_header_section'
    );
    
    // Sekcja: Footer
    add_settings_section(
        'auranet_cart_pdf_footer_section',
        'Stopka PDF',
        function() {
            echo '<p>Treść wyświetlana na dole dokumentu PDF (np. dane kontaktowe, regulamin).</p>';
        },
        'cart-pdf-settings'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_footer', array(
        'type'              => 'string',
        'sanitize_callback' => 'wp_kses_post',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_footer',
        'Treść stopki',
        'auranet_cart_pdf_footer_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_footer_section'
    );
    
    // Sekcja: Dane firmy
    add_settings_section(
        'auranet_cart_pdf_company_section',
        'Dane firmy',
        function() {
            echo '<p>Dane firmy wyświetlane w nagłówku PDF.</p>';
        },
        'cart-pdf-settings'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_company_name', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_company_name',
        'Nazwa firmy',
        'auranet_cart_pdf_company_name_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_company_section'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_company_address', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_company_address',
        'Adres',
        'auranet_cart_pdf_company_address_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_company_section'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_company_nip', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_company_nip',
        'NIP',
        'auranet_cart_pdf_company_nip_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_company_section'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_company_phone', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_company_phone',
        'Telefon',
        'auranet_cart_pdf_company_phone_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_company_section'
    );
    
    register_setting('auranet_cart_pdf_settings', 'auranet_cart_pdf_company_email', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_email',
        'default'           => '',
    ));
    
    add_settings_field(
        'auranet_cart_pdf_company_email',
        'Email',
        'auranet_cart_pdf_company_email_callback',
        'cart-pdf-settings',
        'auranet_cart_pdf_company_section'
    );
}

/**
 * Callbacki pól formularza
 */
function auranet_cart_pdf_prefix_callback() {
    $value = get_option('auranet_cart_pdf_prefix', 'KALK');
    echo '<input type="text" name="auranet_cart_pdf_prefix" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">Format: PREFIX-0000000000 (np. KALK-4324203495)</p>';
}

function auranet_cart_pdf_logo_callback() {
    $value = get_option('auranet_cart_pdf_logo', '');
    echo '<input type="text" name="auranet_cart_pdf_logo" value="' . esc_attr($value) . '" class="large-text" id="cart_pdf_logo_url">';
    echo '<button type="button" class="button" id="cart_pdf_logo_upload">Wybierz z biblioteki</button>';
    echo '<p class="description">URL do logo (zalecane: PNG lub JPG, max 300px szerokości)</p>';
    if ($value) {
        echo '<p><img src="' . esc_url($value) . '" style="max-width: 200px; margin-top: 10px;"></p>';
    }
}

function auranet_cart_pdf_header_callback() {
    $value = get_option('auranet_cart_pdf_header', '');
    wp_editor($value, 'auranet_cart_pdf_header', array(
        'textarea_name' => 'auranet_cart_pdf_header',
        'textarea_rows' => 6,
        'media_buttons' => false,
        'teeny'         => true,
        'quicktags'     => true,
    ));
    echo '<p class="description">Dodatkowa treść nagłówka (opcjonalnie). Dane firmy są generowane automatycznie z pól powyżej.</p>';
}

function auranet_cart_pdf_footer_callback() {
    $value = get_option('auranet_cart_pdf_footer', '');
    wp_editor($value, 'auranet_cart_pdf_footer', array(
        'textarea_name' => 'auranet_cart_pdf_footer',
        'textarea_rows' => 4,
        'media_buttons' => false,
        'teeny'         => true,
        'quicktags'     => true,
    ));
}

function auranet_cart_pdf_company_name_callback() {
    $value = get_option('auranet_cart_pdf_company_name', '');
    echo '<input type="text" name="auranet_cart_pdf_company_name" value="' . esc_attr($value) . '" class="large-text">';
}

function auranet_cart_pdf_company_address_callback() {
    $value = get_option('auranet_cart_pdf_company_address', '');
    echo '<textarea name="auranet_cart_pdf_company_address" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
}

function auranet_cart_pdf_company_nip_callback() {
    $value = get_option('auranet_cart_pdf_company_nip', '');
    echo '<input type="text" name="auranet_cart_pdf_company_nip" value="' . esc_attr($value) . '" class="regular-text">';
}

function auranet_cart_pdf_company_phone_callback() {
    $value = get_option('auranet_cart_pdf_company_phone', '');
    echo '<input type="text" name="auranet_cart_pdf_company_phone" value="' . esc_attr($value) . '" class="regular-text">';
}

function auranet_cart_pdf_company_email_callback() {
    $value = get_option('auranet_cart_pdf_company_email', '');
    echo '<input type="email" name="auranet_cart_pdf_company_email" value="' . esc_attr($value) . '" class="regular-text">';
}

/**
 * Strona ustawień
 */
function auranet_cart_pdf_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_GET['settings-updated'])) {
        add_settings_error('auranet_cart_pdf_messages', 'auranet_cart_pdf_message', 'Ustawienia zapisane.', 'updated');
    }
    
    settings_errors('auranet_cart_pdf_messages');
    ?>
    <div class="wrap">
        <h1>Ustawienia PDF kalkulacji</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('auranet_cart_pdf_settings');
            do_settings_sections('cart-pdf-settings');
            submit_button('Zapisz ustawienia');
            ?>
        </form>
        
        <hr>
        <h2>Podgląd</h2>
        <p>
            <a href="<?php echo admin_url('admin-post.php?action=preview_cart_pdf&nonce=' . wp_create_nonce('preview_cart_pdf')); ?>" class="button" target="_blank">
                Podgląd PDF (przykładowe dane)
            </a>
        </p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#cart_pdf_logo_upload').on('click', function(e) {
            e.preventDefault();
            
            var frame = wp.media({
                title: 'Wybierz logo',
                button: { text: 'Użyj jako logo' },
                multiple: false
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#cart_pdf_logo_url').val(attachment.url);
            });
            
            frame.open();
        });
    });
    </script>
    <?php
}

/**
 * Załaduj media uploader w admin
 */
add_action('admin_enqueue_scripts', 'auranet_cart_pdf_admin_scripts');

function auranet_cart_pdf_admin_scripts($hook) {
    if ($hook === 'saved_cart_page_cart-pdf-settings') {
        wp_enqueue_media();
    }
}

/**
 * Pomocnicze funkcje do pobierania ustawień
 */
function auranet_get_cart_pdf_settings() {
    return array(
        'prefix'          => get_option('auranet_cart_pdf_prefix', 'KOSZ'),
        'logo'            => get_option('auranet_cart_pdf_logo', ''),
        'header'          => get_option('auranet_cart_pdf_header', ''),
        'footer'          => get_option('auranet_cart_pdf_footer', ''),
        'company_name'    => get_option('auranet_cart_pdf_company_name', ''),
        'company_address' => get_option('auranet_cart_pdf_company_address', ''),
        'company_nip'     => get_option('auranet_cart_pdf_company_nip', ''),
        'company_phone'   => get_option('auranet_cart_pdf_company_phone', ''),
        'company_email'   => get_option('auranet_cart_pdf_company_email', ''),
    );
}
