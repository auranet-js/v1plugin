<?php
/**
 * Batch: Ustaw price_ctx i sale_price_ctx
 * - Dla wszystkich produktów:
 *     • price_ctx       = aktualna (efektywna) cena brutto (sale→regular)
 *     • sale_price_ctx  = regularna cena brutto (bez promo)
 * - WYJĄTEK (tylko tu mnożymy ×0.17): jeśli produkt jest w kategoriach:
 *      parapety-zewnetrzne/stalowe
 *      parapety-zewnetrzne/aluminiowe
 *   i ma _linear_meter_pricing = yes → obie wartości ×= 0.17
 * - Bez formularzy — Narzędzia → „Ustaw meta dla eksportu”.
 */
if (!defined('ABSPATH')) exit;

// ===== CONFIG =====
const APPLY_TO_VARIATIONS = true; // true → licz per-wariant (tylko jeśli rodzic w docelowych kategoriach)
const BATCH_PAGE_SIZE     = 200;
const DRY_RUN             = false; // true → log bez zapisu

const TARGET_CAT_PATHS = [
    'parapety-zewnetrzne/stalowe',
    'parapety-zewnetrzne/aluminiowe',
];

// ===== MENU =====
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Ustaw price-ctx dla eksportu',
        'Ustaw price-ctx dla eksportu',
        'manage_woocommerce',
        'export-hardcoded-batch',
        'ehb_render_price_ctx_page'
    );
});

function ehb_render_price_ctx_page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Brak uprawnień.');
    echo '<div class="wrap"><h1>Batch: Ustaw price_ctx i sale_price_ctx</h1>';
    $result = ehb_run_price_ctx_batch();
    echo '<h2>Wynik</h2>';
    echo '<pre style="max-height:60vh;overflow:auto;background:#111;color:#eee;padding:12px;">' . esc_html($result) . '</pre>';
    echo '<p>Zrobione.</p></div>';
}

// ===== CORE =====
function ehb_run_price_ctx_batch(): string {
    if (!class_exists('WC_Product')) return "WooCommerce nie jest aktywny.\n";

    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');

    $page=1; $processed=0; $updated=0; $log=[];
    do {
        $ids = get_posts([
            'post_type'      => 'product',
            'fields'         => 'ids',
            'posts_per_page' => BATCH_PAGE_SIZE,
            'paged'          => $page,
            'post_status'    => ['publish','draft','pending','private'],
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);
        if (empty($ids)) break;

        foreach ($ids as $pid) {
            $processed++;
            $product = wc_get_product($pid);
            if (!$product) continue;

            // bazowo — ceny brutto
            $base_eff_parent = ehb_effective_price_gross_parent($product); // sale→regular
            $base_reg_parent = ehb_regular_price_gross_parent($product);   // regular only

            // czy rodzic w docelowych kategoriach?
            $is_target_cat = ehb_product_in_target_cats($pid);

            // jeśli w docelowych i ma _linear_meter_pricing=yes → ×0.17
            $factor_parent = ($is_target_cat && ehb_linear_meter_is_yes($pid)) ? 0.17 : 1.0;

            // wartości końcowe
            $val_price_ctx_parent      = round($base_eff_parent * $factor_parent, 2);
            $val_sale_price_ctx_parent = round($base_reg_parent * $factor_parent, 2);

            // Obróbka blachy: przemnóż przez domyślną powierzchnię (m^2)
            $area_mult_parent = ehb_obrobka_area_multiplier($pid);
            if ($area_mult_parent > 0 && $area_mult_parent !== 1.0) {
                $val_price_ctx_parent      = round($val_price_ctx_parent * $area_mult_parent, 2);
                $val_sale_price_ctx_parent = round($val_sale_price_ctx_parent * $area_mult_parent, 2);
            }
            // Dodatkowo: dla produktów z obróbką blachy aktualizuj meta wariantów niezależnie od kategorii
            if (get_post_meta($pid, '_obrobka_blachy_enabled', true) === 'yes' && APPLY_TO_VARIATIONS && $product->is_type('variable')) {
                $dec      = wc_get_price_decimals();
                $currency = get_woocommerce_currency();
                $area_mult_ob = ehb_obrobka_area_multiplier($pid); if ($area_mult_ob <= 0) $area_mult_ob = 1.0;
                foreach ($product->get_children() as $vid) {
                    $v = wc_get_product($vid); if (!$v) continue;
                    $base_eff_v = ehb_effective_price_gross_variant($v);
                    $base_reg_v = ehb_regular_price_gross_variant($v);
                    $factor_v   = ehb_linear_meter_is_yes($vid) ? 0.17 : 1.0;
                    $p_raw = round($base_reg_v  * $factor_v * $area_mult_ob, 2);
                    $s_raw = round($base_eff_v * $factor_v * $area_mult_ob, 2);
                    if (!DRY_RUN) {
                        // Zapis liczb (bez waluty) – CTX wymaga liczbowych pól dla obróbek
                        update_post_meta($vid, 'price_ctx', $p_raw);
                        update_post_meta($vid, 'sale_price_ctx', $s_raw);
                        // Aliasowe klucze z myślnikiem (często używane w mapowaniu CTX)
                        update_post_meta($vid, 'price-ctx', $p_raw);
                        update_post_meta($vid, 'sale-price-ctx', $s_raw);
                    }
                    $log[] = "  - #{$vid} [VAR-OBROBKA] {$v->get_name()} => price_ctx={$p_raw}, sale_price_ctx={$s_raw} (area={$area_mult_ob})";
                }
            }

            if (!DRY_RUN) {
                update_post_meta($pid, 'price_ctx', $val_price_ctx_parent);
                update_post_meta($pid, 'sale_price_ctx', $val_sale_price_ctx_parent);
                // Aliasowe klucze z myślnikiem
                update_post_meta($pid, 'price-ctx', $val_price_ctx_parent);
                update_post_meta($pid, 'sale-price-ctx', $val_sale_price_ctx_parent);
            }
            $log[] = "#{$pid} [PARENT] {$product->get_name()} → price_ctx={$val_price_ctx_parent}, sale_price_ctx={$val_sale_price_ctx_parent} (eff={$base_eff_parent}, reg={$base_reg_parent}, factor={$factor_parent}, target_cat=" . ($is_target_cat?'Y':'N') . ")";
            $updated++;

            // warianty — tylko jeśli rodzic w docelowych kategoriach i opcja włączona
            if ($is_target_cat && APPLY_TO_VARIATIONS && $product->is_type('variable')) {
                foreach ($product->get_children() as $vid) {
                    $v = wc_get_product($vid);
                    if (!$v) continue;

                    $base_eff_v = ehb_effective_price_gross_variant($v);
                    $base_reg_v = ehb_regular_price_gross_variant($v);

                    $factor_v = ehb_linear_meter_is_yes($vid) ? 0.17 : 1.0;

                    $val_price_ctx_v      = round($base_eff_v * $factor_v, 2);
                    $val_sale_price_ctx_v = round($base_reg_v * $factor_v, 2);

                    // Obróbka blachy (wariant): powierzchnia z konfiguratora (meta na rodzicu)
                    $area_mult_v = ehb_obrobka_area_multiplier($pid);
                    if ($area_mult_v > 0 && $area_mult_v !== 1.0) {
                        $val_price_ctx_v      = round($val_price_ctx_v * $area_mult_v, 2);
                        $val_sale_price_ctx_v = round($val_sale_price_ctx_v * $area_mult_v, 2);
                    }

                    if (!DRY_RUN) {
                        update_post_meta($vid, 'price_ctx', $val_price_ctx_v);
                        update_post_meta($vid, 'sale_price_ctx', $val_sale_price_ctx_v);
                    }
                    $log[] = "  - #{$vid} [VAR] {$v->get_name()} → price_ctx={$val_price_ctx_v}, sale_price_ctx={$val_sale_price_ctx_v} (eff={$base_eff_v}, reg={$base_reg_v}, factor={$factor_v})";
                }
            }
        }
        $page++;
    } while (count($ids) === BATCH_PAGE_SIZE);

    if (!DRY_RUN && function_exists('wc_update_product_lookup_tables')) {
        wc_update_product_lookup_tables();
    }

    $summary = "Przetworzono: {$processed}\nZaktualizowano: {$updated}\nDRY_RUN: " . (DRY_RUN ? 'TAK' : 'NIE') . "\n";
    return $summary . "\n--- LOG ---\n" . implode("\n", $log) . "\n";
}

// ===== HELPERS =====
function ehb_product_in_target_cats(int $product_id): bool {
    $paths = ehb_get_product_category_paths_and_slugs($product_id);
    foreach (TARGET_CAT_PATHS as $needle) {
        if (in_array($needle, $paths, true)) return true;
    }
    return false;
}

/** true, jeśli meta _linear_meter_pricing to yes/1/true/tak/on (case-insensitive) */
function ehb_linear_meter_is_yes(int $post_id): bool {
    $raw = (string) get_post_meta($post_id, '_linear_meter_pricing', true);
    $v = strtolower(trim($raw));
    return in_array($v, ['yes','1','true','tak','on'], true);
}

/** Lista pojedynczych slugów i ścieżek slugów rodzic/dziecko (np. parapety-zewnetrzne/stalowe). */
function ehb_get_product_category_paths_and_slugs(int $product_id): array {
    $terms = get_the_terms($product_id, 'product_cat');
    if (empty($terms) || is_wp_error($terms)) return [];

    $by_id = [];
    foreach ($terms as $t) $by_id[$t->term_id] = $t;

    $all = [];
    foreach ($terms as $t) {
        $all[] = $t->slug;
        $path = [$t->slug];
        $parent = $t->parent;
        while ($parent && isset($by_id[$parent])) {
            array_unshift($path, $by_id[$parent]->slug);
            $parent = $by_id[$parent]->parent;
        }
        if (count($path) > 1) $all[] = implode('/', $path);
    }
    return array_values(array_unique($all));
}

/** Aktualna (efektywna) cena brutto rodzica: variable → min z wariantów (sale→regular); simple → sale→regular. */
function ehb_effective_price_gross_parent(WC_Product $product): float {
    if ($product->is_type('variable')) {
        $min = INF;
        foreach ($product->get_children() as $vid) {
            $v = wc_get_product($vid);
            if (!$v) continue;
            $p = ehb_effective_price_gross_variant($v);
            if ($p > 0 && $p < $min) $min = $p;
        }
        return is_finite($min) ? (float)$min : 0.0;
    }
    $base = $product->get_sale_price() !== '' ? (float)$product->get_sale_price() : (float)$product->get_regular_price();
    return (float) wc_get_price_including_tax($product, ['price' => $base]);
}

/** Regularna cena brutto rodzica (bez promo): variable → min z wariantów regularnych; simple → regular. */
function ehb_regular_price_gross_parent(WC_Product $product): float {
    if ($product->is_type('variable')) {
        $min = INF;
        foreach ($product->get_children() as $vid) {
            $v = wc_get_product($vid);
            if (!$v) continue;
            $p = ehb_regular_price_gross_variant($v);
            if ($p > 0 && $p < $min) $min = $p;
        }
        return is_finite($min) ? (float)$min : 0.0;
    }
    $reg = (float) $product->get_regular_price();
    return (float) wc_get_price_including_tax($product, ['price' => $reg]);
}

/** Aktualna (efektywna) cena brutto wariantu: sale→regular. */
function ehb_effective_price_gross_variant(WC_Product $v): float {
    $base = $v->get_sale_price() !== '' ? (float)$v->get_sale_price() : (float)$v->get_regular_price();
    return (float) wc_get_price_including_tax($v, ['price' => $base]);
}

/** Regularna cena brutto wariantu (bez promo). */
function ehb_regular_price_gross_variant(WC_Product $v): float {
    $reg = (float) $v->get_regular_price();
    return (float) wc_get_price_including_tax($v, ['price' => $reg]);
}

/**
 * Mnożnik powierzchni dla produktów z włączoną „Obróbką blachy”.
 * area = (min_length_mm/1000) * (sum(default_dimensions_mm)/1000)
 */
function ehb_obrobka_area_multiplier(int $post_id): float {
    $enabled = get_post_meta($post_id, '_obrobka_blachy_enabled', true) === 'yes';
    if (!$enabled) return 1.0;

    $min_length_mm = (float) (get_post_meta($post_id, '_min_length', true) ?: 0);
    $dims = get_post_meta($post_id, '_wymiary_obrobki', true);

    $sum_width_mm = 0.0;
    if (is_array($dims)) {
        foreach ($dims as $row) {
            $sum_width_mm += isset($row['domyslna']) ? (float) $row['domyslna'] : 0.0;
        }
    }

    if ($min_length_mm > 0 && $sum_width_mm > 0) {
        return ($min_length_mm/1000.0) * ($sum_width_mm/1000.0);
    }
    return 1.0;
}
