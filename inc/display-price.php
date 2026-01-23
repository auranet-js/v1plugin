<?php
/**
 * Victorini — display-price.php
 *
 * Co robi:
 * - Wystawia i ZAPISUJE do wp_postmeta:
 *     • price_ctx       = MIN REGULARNA cena brutto (bez promo)
 *     • sale_price_ctx  = MIN EFEKTYWNA cena brutto (z promo; gdy brak promo → regularna)
 * - Dla produktów w kategoriach: parapety-zewnetrzne/(stalowe|aluminiowe) — obie ceny mnoży × 0.17
 * - Aktualizuje meta AUTOMATYCZNIE:
 *     • przy zapisie produktu/wariantu (panel / API / programowo)
 *     • przy imporcie CSV (wbudowany importer WooCommerce)
 * - „Leniwe” wyliczanie przy odczycie:
 *     • jeśli CTX poprosi o meta, a go nie ma → licz i zapisz na żądanie (write-through)
 *
 * Użycie w CTX Feed (darmowa wersja):
 *   - Mapuj:
 *       g:price      → Custom Field: price_ctx
 *       g:sale_price → Custom Field: sale_price_ctx
 */

defined('ABSPATH') || exit;

/** Mnożnik dla parapetów stalowych/aluminiowych */
if (!defined('VICTORINI_CTX_FACTOR')) {
    define('VICTORINI_CTX_FACTOR', 0.17);
}

/** Jeśli WooCommerce nie jest aktywne — nic nie rób (bez fatal error) */
if (!class_exists('WooCommerce')) {
    return;
}

/* =========================================================
 * HELPERY — produkt bazowy, rozpoznanie „metalowych parapetów”
 * ======================================================= */

if (!function_exists('victorini_feed_is_product_like')) {
    function victorini_feed_is_product_like($post_id): bool {
        $t = get_post_type($post_id);
        return $t === 'product' || $t === 'product_variation';
    }
}

if (!function_exists('victorini_feed_base_product')) {
    function victorini_feed_base_product(WC_Product $product): WC_Product {
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $parent    = $parent_id ? wc_get_product($parent_id) : null;
            if ($parent instanceof WC_Product) return $parent;
        }
        return $product;
    }
}

/**
 * True, jeśli produkt należy do drzewa: parapety-zewnetrzne / (stalowe|aluminiowe)
 * Odporne na warianty slugów (np. „…-stalowe-2”).
 */
if (!function_exists('victorini_feed_is_parapet_metal')) {
    function victorini_feed_is_parapet_metal(WC_Product $product): bool {
        static $cache = [];
        $base = victorini_feed_base_product($product);
        $pid  = $base->get_id();
        if (isset($cache[$pid])) return $cache[$pid];

        $terms = get_the_terms($pid, 'product_cat');
        if (empty($terms) || is_wp_error($terms)) return $cache[$pid] = false;

        $has_parent = false; // „parapety-zewnetrzne”
        $has_child  = false; // „stalowe” lub „aluminiowe”

        foreach ($terms as $t) {
            $s  = sanitize_title($t->slug);
            $n  = sanitize_title($t->name);
            if (strpos($s, 'parapety-zewnetrzne') !== false || strpos($n, 'parapety-zewnetrzne') !== false) {
                $has_parent = true;
            }
            foreach (['stalowe', 'aluminiowe'] as $child) {
                $re = '/(^|-)'.preg_quote($child,'/').'($|-)/';
                if (preg_match($re, $s) || preg_match($re, $n)) $has_child = true;
            }

            // przodkowie
            $ancestors = get_ancestors($t->term_id, 'product_cat');
            foreach ($ancestors as $anc_id) {
                $anc = get_term($anc_id, 'product_cat');
                if (!$anc || is_wp_error($anc)) continue;
                $as = sanitize_title($anc->slug);
                $an = sanitize_title($anc->name);
                if (strpos($as, 'parapety-zewnetrzne') !== false || strpos($an, 'parapety-zewnetrzne') !== false) {
                    $has_parent = true;
                }
                foreach (['stalowe', 'aluminiowe'] as $child) {
                    $re = '/(^|-)'.preg_quote($child,'/').'($|-)/';
                    if (preg_match($re, $as) || preg_match($re, $an)) $has_child = true;
                }
            }
        }

        return $cache[$pid] = (bool) ($has_parent && $has_child);
    }
}

/**
 * Mnożnik powierzchni dla produktów z włączoną „Obróbką blachy”.
 * Oblicza domyślną powierzchnię na podstawie: min_length × suma domyślnych wymiarów.
 * Zwraca 1.0, jeżeli produkt nie ma flagi lub brak kompletnych danych.
 */
if (!function_exists('victorini_obrobka_area_multiplier')) {
    function victorini_obrobka_area_multiplier($product_or_id): float {
        $pid = is_numeric($product_or_id) ? (int) $product_or_id : 0;
        if ($product_or_id instanceof WC_Product) {
            $pid = $product_or_id->get_id();
        }
        if ($pid <= 0) return 1.0;

        $enabled = get_post_meta($pid, '_obrobka_blachy_enabled', true) === 'yes';
        if (!$enabled) return 1.0;

        $min_length_mm = (float) (get_post_meta($pid, '_min_length', true) ?: 0);
        $dims          = get_post_meta($pid, '_wymiary_obrobki', true);
        $sum_width_mm  = 0.0;
        if (is_array($dims)) {
            foreach ($dims as $row) {
                $sum_width_mm += isset($row['domyslna']) ? (float) $row['domyslna'] : 0.0;
            }
        }

        if ($min_length_mm > 0 && $sum_width_mm > 0) {
            return ($min_length_mm / 1000.0) * ($sum_width_mm / 1000.0); // m^2
        }
        return 1.0;
    }
}

/* =========================================================
 * LICZENIE CEN — min efektywna i min regularna (obie BRUTTO)
 * ======================================================= */

if (!function_exists('victorini_min_price_incl_tax')) {
    /** MIN EFEKTYWNA brutto (sale jeśli jest, inaczej regularna) */
    function victorini_min_price_incl_tax(WC_Product $product): float {
        if ($product->is_type('variation')) {
            $raw = $product->get_price(); // uwzględnia sale
            if ($raw === '' || $raw === null) return 0.0;
            return (float) wc_get_price_including_tax($product, ['price' => $raw]);
        }

        if ($product->is_type('variable')) {
            $min = $product->get_variation_price('min', true); // true => brutto
            if ($min !== '' && $min !== null) return (float) $min;
            // awaryjny fallback
            $best = null;
            foreach ($product->get_children() as $vid) {
                $v = wc_get_product($vid); if (!$v) continue;
                $raw = $v->get_price(); if ($raw === '' || $raw === null) continue;
                $incl = wc_get_price_including_tax($v, ['price' => $raw]);
                if ($best === null || $incl < $best) $best = $incl;
            }
            return (float) ($best ?? 0.0);
        }

        // simple
        $raw = $product->get_price();
        if ($raw === '' || $raw === null) return 0.0;
        return (float) wc_get_price_including_tax($product, ['price' => $raw]);
    }
}

if (!function_exists('victorini_min_regular_price_incl_tax')) {
    /** MIN REGULARNA brutto (bez promo) */
    function victorini_min_regular_price_incl_tax(WC_Product $product): float {
        if ($product->is_type('variation')) {
            $raw = $product->get_regular_price();
            if ($raw === '' || $raw === null) return 0.0;
            return (float) wc_get_price_including_tax($product, ['price' => $raw]);
        }

        if ($product->is_type('variable')) {
            $min = $product->get_variation_regular_price('min', true); // true => brutto
            if ($min !== '' && $min !== null) return (float) $min;
            // awaryjny fallback
            $best = null;
            foreach ($product->get_children() as $vid) {
                $v = wc_get_product($vid); if (!$v) continue;
                $raw = $v->get_regular_price(); if ($raw === '' || $raw === null) continue;
                $incl = wc_get_price_including_tax($v, ['price' => $raw]);
                if ($best === null || $incl < $best) $best = $incl;
            }
            return (float) ($best ?? 0.0);
        }

        // simple
        $raw = $product->get_regular_price();
        if ($raw === '' || $raw === null) return 0.0;
        return (float) wc_get_price_including_tax($product, ['price' => $raw]);
    }
}

/* =========================================================
 * ZAPIS META (price_ctx / sale_price_ctx) + „leniwa” aktualizacja
 * ======================================================= */

if (!function_exists('victorini_ctx_update_one')) {
    function victorini_ctx_update_one(WC_Product $product): void {
        $base = victorini_feed_base_product($product);
        $pid  = (int) $base->get_id();
        if ($pid <= 0) return;

        $reg = victorini_min_regular_price_incl_tax($base);
        $eff = victorini_min_price_incl_tax($base);
        if ($eff <= 0 && $reg > 0) $eff = $reg;

        // Obróbka blachy: przemnóż przez domyślną powierzchnię (m^2), jeśli włączona
        $area_mult = victorini_obrobka_area_multiplier($base);
        if ($area_mult > 0 && $area_mult !== 1.0) {
            $reg *= $area_mult;
            $eff *= $area_mult;
        }

        if (victorini_feed_is_parapet_metal($base)) {
            $reg *= VICTORINI_CTX_FACTOR;
            $eff *= VICTORINI_CTX_FACTOR;
        }

        $dec      = wc_get_price_decimals();
        $currency = get_woocommerce_currency();

        $is_obrobka = (get_post_meta($pid, '_obrobka_blachy_enabled', true) === 'yes');

        if ($is_obrobka) {
            // Dla obróbek: price_ctx/sale_price_ctx zapisujemy jako liczby (bez waluty)
            $price_num = wc_format_decimal($reg, $dec);
            $sale_num  = wc_format_decimal($eff, $dec);

            update_post_meta($pid, 'price_ctx', $price_num);
            update_post_meta($pid, 'sale_price_ctx', $sale_num);
            // Aliasowe klucze (niektóre profile CTX mogą używać wersji z myślnikiem)
            update_post_meta($pid, 'price-ctx', $price_num);
            update_post_meta($pid, 'sale-price-ctx', $sale_num);

            // Alias z walutą (czytelny podgląd lub alternatywne mapowanie)
            $price_s = $price_num . ' ' . $currency;
            $sale_s  = $sale_num . ' ' . $currency;
            update_post_meta($pid, 'victorini_price_ctx', $price_s);
            update_post_meta($pid, 'victorini_sale_price_ctx', $sale_s);

            // Propagacja na warianty (CTX często czyta meta na wariancie)
            if ($base->is_type('variable')) {
                foreach ($base->get_children() as $vid) {
                    update_post_meta($vid, 'price_ctx', $price_num);
                    update_post_meta($vid, 'sale_price_ctx', $sale_num);
                    update_post_meta($vid, 'price-ctx', $price_num);
                    update_post_meta($vid, 'sale-price-ctx', $sale_num);
                    update_post_meta($vid, 'victorini_price_ctx', $price_s);
                    update_post_meta($vid, 'victorini_sale_price_ctx', $sale_s);
                }
            }
        } else {
            // Dla pozostałych: zachowujemy dotychczasowy format "kwota waluta"
            $price_s = wc_format_decimal($reg, $dec) . ' ' . $currency;
            $sale_s  = wc_format_decimal($eff, $dec) . ' ' . $currency;

            update_post_meta($pid, 'price_ctx', $price_s);
            update_post_meta($pid, 'sale_price_ctx', $sale_s);
            // Aliasowe klucze (z myślnikiem)
            update_post_meta($pid, 'price-ctx', $price_s);
            update_post_meta($pid, 'sale-price-ctx', $sale_s);
            update_post_meta($pid, 'victorini_price_ctx', $price_s);
            update_post_meta($pid, 'victorini_sale_price_ctx', $sale_s);
        }
    }
}

if (!function_exists('victorini_ctx_update_parent_if_variation')) {
    function victorini_ctx_update_parent_if_variation(WC_Product $product): void {
        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                victorini_ctx_update_one($parent);
            }
        }
    }
}

/**
 * AUTO-UPDATE przy zapisie produktu/wariantu
 */
add_action('woocommerce_after_product_object_save', function($product){
    if ($product instanceof WC_Product) {
        victorini_ctx_update_one($product);
        victorini_ctx_update_parent_if_variation($product);
    }
}, 20);

/**
 * AUTO-UPDATE przy imporcie CSV (wbudowany importer WooCommerce)
 */
add_action('woocommerce_product_import_inserted_product_object', function($product){
    if ($product instanceof WC_Product) {
        victorini_ctx_update_one($product);
        victorini_ctx_update_parent_if_variation($product);
    }
}, 10);

/* =========================================================
 * WIRTUALNE META (na wypadek, gdy CTX czyta przez get_post_meta)
 *  - jeśli pole nie istnieje → policz teraz i zapisz (write-through)
 * ======================================================= */

if (!function_exists('victorini_feed_get_meta_keys_map')) {
    function victorini_feed_get_meta_keys_map(): array {
        $price_ctx_keys = ['price-ctx','price_ctx','victorini_price_ctx','_victorini_price_ctx'];
        $sale_ctx_keys  = ['sale-price-ctx','sale_price_ctx','victorini_sale_price_ctx','_victorini_sale_price_ctx'];
        return [$price_ctx_keys, $sale_ctx_keys];
    }
}

add_filter('get_post_metadata', function ($value, $object_id, $meta_key, $single) {
    if (!victorini_feed_is_product_like($object_id)) return $value;

    list($price_ctx_keys, $sale_ctx_keys) = victorini_feed_get_meta_keys_map();

    // price_ctx
    if (in_array($meta_key, $price_ctx_keys, true)) {
        $raw = get_metadata('post', $object_id, 'price_ctx', true);
        $num = null;
        if (is_numeric($raw)) {
            $num = (float) $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $num = (float) str_replace(',', '.', preg_replace('/[^0-9\.,-]/', '', $raw));
        }
        // Zawsze przeliczamy, aby zapewnić spójność (dynamiczne ceny/obr. blachy)

        $p = wc_get_product($object_id);
        if ($p instanceof WC_Product) {
            $read_id = $object_id;
            if ($p->is_type('variation')) {
                $parent_id = $p->get_parent_id();
                if ($parent_id) $read_id = $parent_id;
            }
            victorini_ctx_update_one($p);
            $fresh = get_metadata('post', $read_id, 'price_ctx', true) ?: '';
            return $single ? $fresh : [$fresh];
        }
        return $value;
    }

    // sale_price_ctx
    if (in_array($meta_key, $sale_ctx_keys, true)) {
        $raw = get_metadata('post', $object_id, 'sale_price_ctx', true);
        $num = null;
        if (is_numeric($raw)) {
            $num = (float) $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $num = (float) str_replace(',', '.', preg_replace('/[^0-9\.,-]/', '', $raw));
        }
        // Zawsze przeliczamy, aby zapewnić spójność (dynamiczne ceny/obr. blachy)

        $p = wc_get_product($object_id);
        if ($p instanceof WC_Product) {
            $read_id = $object_id;
            if ($p->is_type('variation')) {
                $parent_id = $p->get_parent_id();
                if ($parent_id) $read_id = $parent_id;
            }
            victorini_ctx_update_one($p);
            $fresh = get_metadata('post', $read_id, 'sale_price_ctx', true) ?: '';
            return $single ? $fresh : [$fresh];
        }
        return $value;
    }

    return $value;
}, 10, 4);


/* =========================================================
 * UI (ADMIN): pokaż price_ctx / sale_price_ctx jako read-only
 *  - Simple/Variable: pole pod ceną regularną/promocyjną
 *  - Variations: podgląd wartości z rodzica (read-only)
 * ======================================================= */
if (is_admin()) {

    // Simple & Variable (na karcie "Ogólne" → Pricing)
    add_action('woocommerce_product_options_pricing', function () {
        global $post;
        if (!$post) return;

        $price_ctx = get_post_meta($post->ID, 'price_ctx', true);
        $sale_ctx  = get_post_meta($post->ID, 'sale_price_ctx', true);

        echo '<div class="options_group">';
        echo '  <p class="form-field">';
        echo '    <label for="victorini_price_ctx_ro">price_ctx (auto)</label>';
        echo '    <input id="victorini_price_ctx_ro" type="text" class="short" value="' . esc_attr($price_ctx) . '" readonly />';
        echo '  </p>';
        echo '  <p class="form-field">';
        echo '    <label for="victorini_sale_price_ctx_ro">sale_price_ctx (auto)</label>';
        echo '    <input id="victorini_sale_price_ctx_ro" type="text" class="short" value="' . esc_attr($sale_ctx) . '" readonly />';
        echo '  </p>';
        echo '  <p class="form-field"><em>Automatycznie liczone: min REG brutto i min EFEKTYWNA brutto; dla parapetów stal/aluminium × ' . esc_html(VICTORINI_CTX_FACTOR) . '.</em></p>';
        echo '</div>';
    });

    // Variations: pokaż wartości z rodzica (to te, które CTX czyta)
    add_action('woocommerce_variation_options_pricing', function($loop, $variation_data, $variation) {
        $parent_id  = (int) $variation->post_parent;
        $price_ctx  = $parent_id ? get_post_meta($parent_id, 'price_ctx', true) : '';
        $sale_ctx   = $parent_id ? get_post_meta($parent_id, 'sale_price_ctx', true) : '';
        echo '<div class="form-row form-row-full">';
        echo '  <label style="display:inline-block;min-width:160px;">price_ctx (parent, auto)</label>';
        echo '  <input type="text" class="short" value="' . esc_attr($price_ctx) . '" readonly />';
        echo '  &nbsp; ';
        echo '  <label style="display:inline-block;min-width:180px;">sale_price_ctx (parent, auto)</label>';
        echo '  <input type="text" class="short" value="' . esc_attr($sale_ctx) . '" readonly />';
        echo '</div>';
    }, 10, 3);
}
