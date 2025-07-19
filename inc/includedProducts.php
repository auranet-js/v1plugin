<?php

add_action('woocommerce_before_add_to_cart_button', 'display_acf_related_products', 20);


add_action('woocommerce_before_add_to_cart_button', 'display_acf_related_products', 20);
function display_acf_related_products() {
    global $product;
    $acf_zakonczenia_field_name = 'zakonczenia';
    $acf_laczniki_field_name = 'laczniki';
    
    $zakonczenia_products = get_field($acf_zakonczenia_field_name);
    $laczniki_products = get_field($acf_laczniki_field_name);
    ?>
    <style>
        .acf-related-products-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .acf-related-product {
            display: flex;
            flex-direction: column;
            width: 100px;
            cursor: pointer;
        }
        .acf-related-product img {
            max-width: 100px;
            max-height: 100px;
            border: 2px solid transparent;
            transition: border-color 0.3s;
        }
        .acf-related-product.selected img {
           
            border-color: #007bff;
        }
        .acf-related-product-placeholder {
            width: 100px;
            height: 100px;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
        }
        .acf-related-product h3 {
            margin-top: 5px;
            font-size: 1em;
            line-height: 1.2;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
    </style>
    
    <input type="hidden" name="selected_zakonczenie_id" id="selected_zakonczenie_id" value="">
    <input type="hidden" name="selected_lacznik_id" id="selected_lacznik_id" value="">
    
    <?php
    if ($zakonczenia_products): ?>
        <section class="custom-related products zakonczenia acf-related">
            <p><strong><?php echo esc_html__('Zakończenia', 'woocommerce'); ?></strong></p>
            <div class="acf-related-products-grid">
                <?php foreach ($zakonczenia_products as $zakonczenie_product):
                    $product_obj = wc_get_product($zakonczenie_product->ID);
                    if ($product_obj):
                        $product_image_url = wp_get_attachment_url($product_obj->get_image_id());
                        $product_title = wp_trim_words($product_obj->get_name(), 5, '...');
                        $product_price = wc_price($product_obj->get_price());
                        $product_title_with_price = $product_title . ' ' . $product_price;
                        ?>
                        <div class="acf-related-product zakonczenie-item" data-zakonczenia-price="<?= $product_obj->get_price()?>" data-product-id="<?php echo esc_attr($zakonczenie_product->ID); ?>">
                            <?php if ($product_image_url): ?>
                                <img src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($product_obj->get_name()); ?>" />
                            <?php else: ?>
                                <div class="acf-related-product-placeholder"></div>
                            <?php endif; ?>
                            <h3><?php echo $product_title_with_price ?></h3>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <?php
    if ($laczniki_products): ?>
        <section class="custom-related products laczniki acf-related">
            <p><strong><?php echo esc_html__('Łączniki', 'woocommerce'); ?></strong></p>
            <div class="acf-related-products-grid">
                <?php foreach ($laczniki_products as $lacznik_product):
                    $product_obj = wc_get_product($lacznik_product->ID);
                    if ($product_obj):
                        $product_image_url = wp_get_attachment_url($product_obj->get_image_id());
                        $product_title = wp_trim_words($product_obj->get_name(), 5, '...');
                        $product_price = wc_price($product_obj->get_price());
                        $product_title_with_price = $product_title . ' ' . $product_price;
                        ?>
                        <div class="acf-related-product lacznik-item" data-lacznik-price="<?= $product_obj->get_price()?>" data-product-id="<?php echo esc_attr($lacznik_product->ID); ?>">
                            <?php if ($product_image_url): ?>
                                <img src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($product_obj->get_name()); ?>" />
                            <?php else: ?>
                                <div class="acf-related-product-placeholder"></div>
                            <?php endif; ?>
                            <h3><?php echo $product_title_with_price ?></h3>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.zakonczenie-item').click(function() {
            $('.zakonczenie-item').removeClass('selected');
            $(this).addClass('selected');
            window.zakonczenie_price = $(this).data('zakonczenia-price');
            $('#selected_zakonczenie_id').val($(this).data('product-id'));

            calculateFinalPrice();
        });
        
        $('.lacznik-item').click(function() {
            $('.lacznik-item').removeClass('selected');
            $(this).addClass('selected');
            window.lacznik_price = $(this).data('lacznik-price');
            $('#selected_lacznik_id').val($(this).data('product-id'));

            calculateFinalPrice();

        });
    });
    </script>
    <?php
}
add_action('woocommerce_add_to_cart', 'add_selected_accessories_to_cart', 10, 6);
function add_selected_accessories_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    static $is_running = false;
    
    if ($is_running) {
        return;
    }
    
    $is_running = true;
    
    if (isset($_POST['selected_zakonczenie_id']) && !empty($_POST['selected_zakonczenie_id'])) {
        $zakonczenie_id = absint($_POST['selected_zakonczenie_id']);
        WC()->cart->add_to_cart($zakonczenie_id, $quantity);
    }
    
    if (isset($_POST['selected_lacznik_id']) && !empty($_POST['selected_lacznik_id'])) {
        $lacznik_id = absint($_POST['selected_lacznik_id']);
        WC()->cart->add_to_cart($lacznik_id, $quantity);
    }
    
    $is_running = false;
}