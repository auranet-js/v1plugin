(function ($) {

    /*  Metody wyłączane przy koszyku > 1000 zł  */
    const blockedWhenHighTotal = ['flat_rate:1', 'flat_rate:5'];

    /**
     * Dodaje czerwoną notatkę, jeżeli jeszcze jej nie ma.
     */
    function addNote($li, html) {
        if (!$li.find('.blocked-note').length) {
            $('<span/>', {
                class: 'blocked-note',
                css: { color: 'red', marginLeft: '4px' },
                html: html
            }).appendTo($li);
        }
    }

    /**
     * Wspólna funkcja blokująca radio + li
     */
    function disableRate($radio) {
        const $li = $radio.closest('li');
        $radio.prop({ disabled: true, checked: false });
        $li.css('opacity', 0.5);
        return $li;
    }

    function hideRate($radio) {
        const $li = $radio.closest('li');
        $radio.prop({ disabled: true, checked: false });
        $li.css('display', 'none');
        return $li;
    }

    /**
     * Główna logika – wywoływana na start i po odświeżeniu checkoutu.
     */
    function disableBlockedRates() {

        $('input[name^="shipping_method"]').each(function () {

            const $radio = $(this);
            const rateId = $radio.val();

            /* --- A. Blokada za długi produkt (> 2500 mm) --- */
            if (victoriniBlock.hasRestrictCat && rateId === victoriniBlock.blockedId) {
                const $li = disableRate($radio);
                addNote($li, '&nbsp;(koszt dostawy wyrobów z kamienia ustalamy indywidualnie)');
            } else if ((victoriniBlock.hasOversize && rateId === victoriniBlock.blockedId)) {
                const $li = disableRate($radio);
                addNote($li, '&nbsp;( w przypadku długości powyżej 2500 mm koszt dostawy ustalamy indywidualnie)');
            }



            /* --- B. Blokada przy koszyku > 1000 zł --- */
            if (victoriniBlock.cartValue > 1000 && blockedWhenHighTotal.includes(rateId)) {
                const $li = hideRate($radio);
            }
        });
    }

    /*  Pierwsze uruchomienie + każde „updated_checkout” z WooCommerce  */
    $(document).ready(disableBlockedRates);
    $('body').on('updated_checkout updated_cart_totals', disableBlockedRates);

})(jQuery);

(function ($) {

    const ajaxURL = '/?wc-ajax=victorini_get_cart_items'; // ← właściwy adres

    function logCartItems() {
        $.getJSON(ajaxURL)
            .done(items => {
                console.table(items);
            })
            .fail((_, status, err) =>
                console.warn('Nie pobrano koszyka:', status, err));
    }

    $(document).ready(logCartItems);                                  // po załadowaniu
    $('body').on('updated_cart_totals updated_checkout', logCartItems); // po przeliczeniu

})(jQuery);