<?php add_action('admin_menu', 'countertop_customization_menu');
function countertop_customization_menu() {
    add_menu_page(
        'Personalizacja blatów',
        'Personalizacja blatów',
        'manage_options',
        'countertop_customization',
        'render_countertop_customization_page',
        'dashicons-hammer',
        56
    );
}

function render_countertop_customization_page() {
    $kitchen_services = get_option('countertop_kitchen_services', []);
    $bathroom_services = get_option('countertop_bathroom_services', []);
    ?>
    <div class="wrap">
        <h1>Personalizacja blatów</h1>
        <form method="post">
            <?php wp_nonce_field('save_countertop_customization'); ?>
            <h2>Blaty kuchenne</h2>
            <table id="kitchen-services-table" class="widefat">
                <thead>
                    <tr><th>Nazwa usługi</th><th>Cena (zł)</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($kitchen_services as $index => $service): ?>
                        <tr>
                            <td><input name="kitchen_services[<?php echo $index; ?>][label]" value="<?php echo esc_attr($service['label']); ?>" /></td>
                            <td><input name="kitchen_services[<?php echo $index; ?>][price]" value="<?php echo esc_attr($service['price']); ?>" type="number" step="0.01" /></td>
                            <td><button type="button" class="button remove-row">Usuń</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="add-kitchen-row">Dodaj usługę</button></p>

            <h2>Blaty łazienkowe</h2>
            <table id="bathroom-services-table" class="widefat">
                <thead>
                    <tr><th>Nazwa usługi</th><th>Cena (zł)</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($bathroom_services as $index => $service): ?>
                        <tr>
                            <td><input name="bathroom_services[<?php echo $index; ?>][label]" value="<?php echo esc_attr($service['label']); ?>" /></td>
                            <td><input name="bathroom_services[<?php echo $index; ?>][price]" value="<?php echo esc_attr($service['price']); ?>" type="number" step="0.01" /></td>
                            <td><button type="button" class="button remove-row">Usuń</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="add-bathroom-row">Dodaj usługę</button></p>

            <p><input type="submit" name="submit_customization" class="button button-primary" value="Zapisz zmiany"></p>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function addRow(tableId, prefix) {
            const table = document.querySelector(`#${tableId} tbody`);
            const index = table.rows.length;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input name="${prefix}[${index}][label]" /></td>
                <td><input name="${prefix}[${index}][price]" type="number" step="0.01" /></td>
                <td><button type="button" class="button remove-row">Usuń</button></td>
            `;
            table.appendChild(row);
        }

        document.getElementById('add-kitchen-row').addEventListener('click', function () {
            addRow('kitchen-services-table', 'kitchen_services');
        });

        document.getElementById('add-bathroom-row').addEventListener('click', function () {
            addRow('bathroom-services-table', 'bathroom_services');
        });

        document.querySelectorAll('.remove-row').forEach(btn => {
            btn.addEventListener('click', function () {
                this.closest('tr').remove();
            });
        });
    });
    </script>
    <?php
}

// Zapis z generowaniem kluczy
add_action('admin_init', 'save_countertop_customization_data');
function save_countertop_customization_data() {
    if (
        isset($_POST['submit_customization']) &&
        check_admin_referer('save_countertop_customization')
    ) {
        $kitchen_raw = $_POST['kitchen_services'] ?? [];
        $bathroom_raw = $_POST['bathroom_services'] ?? [];

        $kitchen = generate_service_list_with_keys($kitchen_raw);
        $bathroom = generate_service_list_with_keys($bathroom_raw);

        update_option('countertop_kitchen_services', $kitchen);
        update_option('countertop_bathroom_services', $bathroom);

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>Dane zostały zapisane.</p></div>';
        });
    }
}

// Generator kluczy z unikalnością
function generate_service_list_with_keys(array $raw) {
    $result = [];
    $used_keys = [];

    foreach ($raw as $entry) {
        $label = trim($entry['label'] ?? '');
        $price = floatval($entry['price'] ?? 0);

        if ($label === '') continue;

        $base_key = sanitize_title($label); // np. "Wycięcie pod zlew" → "wyciecie-pod-zlew"
        $key = $base_key;
        $suffix = 2;

        while (in_array($key, $used_keys)) {
            $key = $base_key . '_' . $suffix;
            $suffix++;
        }

        $used_keys[] = $key;

        $result[] = [
            'key' => $key,
            'label' => $label,
            'price' => $price,
        ];
    }

    return $result;
}

add_action('wp_footer', function () {
    if (!is_product()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('cutouts_container');
        const addBtn = document.getElementById('add_cutout_row');
        const template = document.getElementById('cutout_template');

        if (!addBtn || !container || !template) return;

        // function bindRemoveButtons() {
        //     container.querySelectorAll('.remove-cutout').forEach(btn => {
        //         btn.onclick = function () {
        //             const row = btn.closest('.cutout-row');
        //             if (row) row.remove();
        //         };
        //     });
        // }

        // bindRemoveButtons();

        addBtn.addEventListener('click', function () {
            const clone = template.content.cloneNode(true);
            container.insertBefore(clone, addBtn);
            bindRemoveButtons();
        });
    });
    </script>
    <?php
});

add_filter( 'woocommerce_get_item_data', 'victorini2025_show_cutouts_in_cart', 10, 2 );
function victorini2025_show_cutouts_in_cart( $item_data, $cart_item ) {
    if ( ! empty( $cart_item['countertop_cutouts'] ) && is_array( $cart_item['countertop_cutouts'] ) ) {
        $lines = array();
        foreach ( $cart_item['countertop_cutouts'] as $srv ) {
            $lines[] = esc_html( $srv['label'] ) . ' (+ ' . wc_price( $srv['price'] ) . ')';
        }
        $item_data[] = array(
            'name'  => __( 'Usługi dodatkowe', 'victorini2025-by-auranet' ),
            'value' => implode( ', ', $lines ),  // plain text
            'display' => implode( '<br>', $lines ), // w koszyku ładnie pod sobą
        );
    }
    return $item_data;
}
