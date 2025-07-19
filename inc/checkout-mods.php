<?php


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* ============================================================================
   1. Modyfikacja pól w formularzu zamówienia (checkout)
============================================================================= */
add_filter( 'woocommerce_checkout_fields', 'custom_override_checkout_fields' );
function custom_override_checkout_fields( $fields ) {
    // Upewnij się, że pole firmy istnieje – modyfikacja etykiety lub dodanie, jeśli nie ma.
    if ( isset( $fields['billing']['billing_company'] ) ) {
        $fields['billing']['billing_company']['label']    = __( 'Nazwa firmy', 'custom-billing-fields' );
        $fields['billing']['billing_company']['required'] = false;
    } else {
        $fields['billing']['billing_company'] = array(
            'type'        => 'text',
            'label'       => __( 'Nazwa firmy', 'custom-billing-fields' ),
            'required'    => false,
            'class'       => array( 'form-row-wide' ),
            'clear'       => true,
        );
    }
    
    // Dodanie pola NIP
    $fields['billing']['billing_nip'] = array(
        'type'        => 'text',
        'label'       => __( 'NIP', 'custom-billing-fields' ),
        'placeholder' => _x( 'Podaj numer NIP', 'placeholder', 'custom-billing-fields' ),
        'required'    => false,
        'class'       => array( 'form-row-wide' ),
        'clear'       => true,
    );
    
    return $fields;
}

/* ============================================================================
   2. Zapis danych pól zamówienia w metadanych (order meta)
============================================================================= */
add_action( 'woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta' );
function custom_checkout_field_update_order_meta( $order_id ) {
    if ( ! empty( $_POST['billing_company'] ) ) {
        update_post_meta( $order_id, '_billing_company', sanitize_text_field( $_POST['billing_company'] ) );
    }
    if ( ! empty( $_POST['billing_nip'] ) ) {
        update_post_meta( $order_id, '_billing_nip', sanitize_text_field( $_POST['billing_nip'] ) );
    }
}

/* ============================================================================
   3. Wyświetlenie pola NIP w szczegółach zamówienia (WP Admin)
============================================================================= */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'custom_display_admin_order_meta', 10, 1 );
function custom_display_admin_order_meta( $order ) {
    $billing_nip = get_post_meta( $order->get_id(), '_billing_nip', true );
    if ( $billing_nip ) {
        echo '<p><strong>' . __( 'NIP', 'custom-billing-fields' ) . ':</strong> ' . esc_html( $billing_nip ) . '</p>';
    }
}

/* ============================================================================
   4. Modyfikacja pól adresu rozliczeniowego w "Moim koncie"
============================================================================= */
add_filter( 'woocommerce_billing_fields', 'custom_modify_billing_fields_for_account' );
function custom_modify_billing_fields_for_account( $fields ) {
    // Pole Nazwa firmy.
    if ( isset( $fields['billing_company'] ) ) {
        $fields['billing_company']['label']    = __( 'Nazwa firmy', 'custom-billing-fields' );
        $fields['billing_company']['required'] = false;
        $fields['billing_company']['priority'] = 25;
    } else {
        $fields['billing_company'] = array(
            'label'    => __( 'Nazwa firmy', 'custom-billing-fields' ),
            'type'     => 'text',
            'required' => false,
            'priority' => 25,
        );
    }
    
    // Pole NIP.
    $fields['billing_nip'] = array(
         'label'    => __( 'NIP', 'custom-billing-fields' ),
         'required' => false,
         'clear'    => true,
         'type'     => 'text',
         'priority' => 26,
    );
    
    return $fields;
}

/* ============================================================================
   5. Zapis danych przy edycji adresu w "Moim koncie"
============================================================================= */
add_action( 'woocommerce_customer_save_address', 'custom_save_billing_fields', 10, 2 );
function custom_save_billing_fields( $user_id, $load_address ) {
   if ( 'billing' === $load_address ) {
        if ( isset( $_POST['billing_company'] ) ) {
            update_user_meta( $user_id, 'billing_company', sanitize_text_field( $_POST['billing_company'] ) );
        }
        if ( isset( $_POST['billing_nip'] ) ) {
            update_user_meta( $user_id, 'billing_nip', sanitize_text_field( $_POST['billing_nip'] ) );
        }
   }
}

/* ============================================================================
   6. Dodanie pól do strony edycji profilu w panelu administracyjnym użytkownika
============================================================================= */
add_action( 'show_user_profile', 'custom_extra_user_profile_fields' );
add_action( 'edit_user_profile', 'custom_extra_user_profile_fields' );
function custom_extra_user_profile_fields( $user ) {
    ?>
    <h3><?php _e( 'Dane rozliczeniowe', 'custom-billing-fields' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="billing_company"><?php _e( 'Nazwa firmy', 'custom-billing-fields' ); ?></label></th>
            <td>
                <input type="text" name="billing_company" id="billing_company" value="<?php echo esc_attr( get_the_author_meta( 'billing_company', $user->ID ) ); ?>" class="regular-text" /><br />
                <span class="description"><?php _e( 'Wprowadź nazwę firmy.', 'custom-billing-fields' ); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="billing_nip"><?php _e( 'NIP', 'custom-billing-fields' ); ?></label></th>
            <td>
                <input type="text" name="billing_nip" id="billing_nip" value="<?php echo esc_attr( get_the_author_meta( 'billing_nip', $user->ID ) ); ?>" class="regular-text" /><br />
                <span class="description"><?php _e( 'Wprowadź numer NIP.', 'custom-billing-fields' ); ?></span>
            </td>
        </tr>
    </table>
    <?php
}

/* ============================================================================
   7. Zapis danych przy edycji profilu w panelu administracyjnym użytkownika
============================================================================= */
add_action( 'personal_options_update', 'custom_save_extra_user_profile_fields' );
add_action( 'edit_user_profile_update', 'custom_save_extra_user_profile_fields' );
function custom_save_extra_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    if ( isset( $_POST['billing_company'] ) ) {
        update_user_meta( $user_id, 'billing_company', sanitize_text_field( $_POST['billing_company'] ) );
    }
    if ( isset( $_POST['billing_nip'] ) ) {
        update_user_meta( $user_id, 'billing_nip', sanitize_text_field( $_POST['billing_nip'] ) );
    }
}
