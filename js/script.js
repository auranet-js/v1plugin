const PD = window.productData || {};
const IS_FALLBACK = !!PD.fallback;
let linearInputsTouched = false;
let linearModeInitialized = false;
let productDataIsReady = typeof window.productData !== 'undefined' && window.productData !== null;
const productDataReadyQueue = [];

function productDataReady() {
    if (productDataIsReady) {
        return true;
    }
    if (typeof window.productData !== 'undefined' && window.productData !== null) {
        productDataIsReady = true;
        return true;
    }
    return false;
}

function isLinearProduct() {
    return productDataReady() && !!window.productData.isLinear;
}

function ensureLinearInitialState() {
    if (!linearModeInitialized && isLinearProduct()) {
        window.areaPrice = undefined;
        linearModeInitialized = true;
    }
}

function markLinearInputsTouched() {
    if (isLinearProduct()) {
        linearInputsTouched = true;
    }
}

function applyLinearDefaultsIfNeeded() {
    if (!productDataReady() || !isLinearProduct()) {
        return;
    }

    const widthField = document.getElementById('custom_width');
    const lengthField = document.getElementById('custom_length');

    if (widthField && widthField.value.trim() === '') {
        const defaultWidth = Number(window.productData.minWidth) > 0 ? Number(window.productData.minWidth) : 100;
        widthField.value = defaultWidth;
        const widthInside = document.getElementById('custom_width_inside');
        if (widthInside && widthInside.value.trim() === '') {
            widthInside.value = defaultWidth;
        }
    }

    if (lengthField && lengthField.value.trim() === '') {
        const defaultLength = 1000;
        lengthField.value = defaultLength;
        const lengthInside = document.getElementById('custom_length_inside');
        if (lengthInside && lengthInside.value.trim() === '') {
            lengthInside.value = defaultLength;
        }
    }
}

document.addEventListener('victoriniProductDataReady', function(event) {
    if (!productDataIsReady && event && event.detail && !window.productData) {
        window.productData = event.detail;
    }
    productDataIsReady = typeof window.productData !== 'undefined' && window.productData !== null;
    ensureLinearInitialState();
    applyLinearDefaultsIfNeeded();
    while (productDataReadyQueue.length) {
        const cb = productDataReadyQueue.shift();
        try {
            cb();
        } catch (err) {
            console.error(err);
        }
    }
});

function onProductDataReady(callback) {
    if (typeof callback !== 'function') {
        return;
    }
    if (productDataReady()) {
        callback();
    } else {
        productDataReadyQueue.push(callback);
    }
}

function calculateFinalPrice() {
    ensureLinearInitialState();
    if (isLinearProduct() && (!linearInputsTouched || typeof window.areaPrice === 'undefined')) {
        return;
    }

    if (typeof window.updateCutoutsPrice === 'function') {
        window.updateCutoutsPrice({silent:true}); // nie twórz pętli – poniżej obsłużone
    }


    if (IS_FALLBACK) {
        const base = Number(PD.unitPrice) || 0;   // <-- masz to z PHP
        let qty = document.querySelector('input[name="quantity"]').value;

        let finalPrice =
            (base * qty) +
            (window.lacznik_price     ?? 0) +
            (window.zakonczenie_price ?? 0) +
            (window.cutouts_price     ?? 0);

        const el = document.getElementById('final-price');
        if (el) el.innerHTML = finalPrice.toFixed(2) + ' zł';
        window.areaPrice = 0;
        return;
    }


     let finalPrice =
        (window.lacznik_price      ?? 0) +
        (window.zakonczenie_price  ?? 0) +
        (window.areaPrice          ?? 0) +
        (window.cutouts_price      ?? 0);

    finalPrice = finalPrice * (window.quantity ?? 1);

    const el = document.getElementById("final-price");
    if (el) {
        el.innerHTML = finalPrice.toFixed(2) + " zł";
    }
}


document.addEventListener('DOMContentLoaded', function () {
    const lengthField = document.getElementById('custom_length');
    const widthField = document.getElementById('custom_width');
    const priceDisplay = document.getElementById('calculated_price');
    const lengthErrorDisplay = document.getElementById('length_error');
    const widthErrorDisplay = document.getElementById('width_error');
    const deliveryDisplay = document.getElementById('delivery_info')

    function calculatePrice() {
        if (!lengthField || !widthField) {
            return;
        }
        if (!productDataReady()) {
            return;
        }
        ensureLinearInitialState();
        if (isLinearProduct() && !linearInputsTouched) {
            return;
        }

        let lengthStr = lengthField.value.trim();
        let widthStr = widthField.value.trim();

        if (lengthStr === '' || widthStr === '') {
            priceDisplay.innerHTML = '<span style=\"color:red;\">uzupełnij wymiary</span>';
            lengthErrorDisplay.innerText = '';
            widthErrorDisplay.innerText = '';
            return;
        }

        let length = parseInt(lengthStr);
        let width = parseInt(widthStr);
        let lengthError = '';
        let widthError = '';

        if (length > 2500 && deliveryDisplay !== null) {
            deliveryDisplay.innerHTML = '<b>Dla produktu o długości powyżej 2500mm dostawa wyceniana jest indywidualnie</b>';
        }
        else {
            deliveryDisplay.innerHTML = '';
        }



        if (isNaN(length)) {
            lengthError = 'niepoprawna wartość';
        } else {
            if (length < productData.minLength) {
                lengthError = 'Minimalna długość to ' + productData.minLength + ' mm.';
            } else if (length > productData.maxLength) {
                lengthError = 'Maksymalna długość to ' + productData.maxLength + ' mm.';
            }
        }

        if (isNaN(width)) {
            widthError = 'niepoprawna wartość';
        } else {
            if (width < productData.minWidth) {
                widthError = 'Minimalna szerokość to ' + productData.minWidth + ' mm.';
            } else if (width > productData.maxWidth) {
                widthError = 'Maksymalna szerokość to ' + productData.maxWidth + ' mm.';
            }
        }

        lengthErrorDisplay.innerText = lengthError;
        widthErrorDisplay.innerText = widthError;

        if (lengthError !== '' || widthError !== '') {
            priceDisplay.innerHTML = '<span style=\"color:red; font-weight:bold;\">Popraw wymiary</span>';
            return;
        }
        if (productData.isLinear) {
            width += 70;
        }

        if (isNaN(productData.pricePerM2)) {
            priceDisplay.innerHTML = '<span style=\"color:red; font-weight:bold;\">Popraw wymiary</span>';
            return;
        }


        let area = (length / 1000) * (width / 1000);
        let basePrice = area * productData.pricePerM2;
        let price = area * productData.pricePerM2;
        if (productData.isPcv) {
            productData.pricePerM2 = parseFloat(window.wcVariationDimensions[productData.variation]);
            basePrice = (length / 1000) * productData.pricePerM2;
            price = (length / 1000) * productData.pricePerM2;
            if (isNaN(price)) {
                const keys = Object.keys(window.wcVariationDimensions);
                const lastKey = keys[keys.length - 1];
                priceDisplay.innerHTML = '<span style=\"color:red; font-weight:bold;\">Maksymalna szerokość to ' + lastKey + ' mm.</span>';
                return;
            }

            if (width in window.wcVariationDimensions) {
                document.getElementById("cutting_required").value = "0";
                document.getElementById("price_info").innerHTML = '';
            } else {
                document.getElementById("cutting_required").value = "1";
                price += parseFloat(window.pcvCuttingCost ?? 0);
                document.getElementById("price_info").innerHTML = '<br><span style="color:red; font-weight:bold;">Koszt docięcia: ' +
                    (window.pcvCuttingCost ?? 0).toFixed(2).replace('.', ',') + ' zł</span>';
            }

        }

        const kapinosy = document.getElementById("pa_kapinosy");
        if (kapinosy && (kapinosy.value == "tak" || kapinosy.value == "kapinosy-tak")) {
            price += productData.kapinosyPrice * length / 1000;
        }

        let qty = document.querySelector('input[name="quantity"]').value;
        let total = price * qty;

        const minPrice = document.getElementById("minimalPrice");
        if (total < productData.minPrice) {
            if (minPrice) {
                minPrice.style.display = 'block';
                document.querySelectorAll(".single_add_to_cart_button").forEach(element => {
                    //element.classList.add("disabled");
                });
            }
        } else {
            if (minPrice) {
                minPrice.style.display = 'none';
                document.querySelectorAll(".single_add_to_cart_button").forEach(element => {
                    ///element.classList.remove("disabled");
                });
            }
        }

        window.areaPrice = price;
        let formattedPrice = price.toFixed(2).replace('.', ',') + ' PLN';
        let formattedBasePrice = basePrice.toFixed(2).replace('.', ',') + ' PLN';
        let formattedArea = area.toFixed(2).replace('.', ',') + ' m²';
        let formattedUnitPrice = (productData.pricePerM2).toFixed(2).replace('.', ',') + ' zł/m²';
        if (productData.isPcv) {
            //formattedUnitPrice = formattedUnitPrice + " x 1,23 (vat)"
            formattedUnitPrice = (productData.pricePerM2).toFixed(2).replace('.', ',') + ' zł/mb' + "";
        }

        calculateFinalPrice();
        if (productData.isPcv) {
            priceDisplay.innerHTML = '<span id=\"final_price\" class=\"woocommerce-Price-amount amount\" style=\"font-size:16px; font-weight:bold; color:black;\">' + formattedBasePrice + '</span> ' +
                length + ' mm x ' + width + ' mm ' +
                '(<span style=\"font-size:14px; font-weight:normal; color:black;\">' + (length / 1000) + ' x ' + formattedUnitPrice + '</span>)';
        } else if (!productData.isLinear) {
            priceDisplay.innerHTML = '<span id=\"final_price\" class=\"woocommerce-Price-amount amount\" style=\"font-size:16px; font-weight:bold; color:black;\">' + formattedPrice + '</span> ' +
                length + ' mm x ' + width + ' mm ' +
                '(<span style=\"font-size:14px; font-weight:normal; color:black;\">' + formattedArea + ' x ' + formattedUnitPrice + '</span>)';
        } else {
            priceDisplay.innerHTML = "";
        }
    }

    window.calculatePrice = calculatePrice;


    if (lengthField) {
        lengthField.addEventListener('input', function () {
            markLinearInputsTouched();
            calculatePrice();
        });
    }
    if (widthField) {
        widthField.addEventListener('input', function () {
            markLinearInputsTouched();
            calculatePrice();
        });
    }
    const kapinosy = document.getElementById("pa_kapinosy");
    if (kapinosy)
        kapinosy.addEventListener('change', calculatePrice);
    const narozniki = document.getElementById("pa_narozniki");
    if (narozniki)
        narozniki.addEventListener('change', calculatePrice);
    const handleProductDataReady = () => {
        ensureLinearInitialState();
        applyLinearDefaultsIfNeeded();
        if (!isLinearProduct()) {
            calculatePrice();
        }
    };

    onProductDataReady(handleProductDataReady);
    const quantityInput = document.querySelector('input[name="quantity"]');
    if(quantityInput) {
        quantityInput.addEventListener('change', function () {
            if (isLinearProduct() && !linearInputsTouched) {
                return;
            }
            calculatePrice();
        });
    }
});


const quantityInputGlobal = document.querySelector('input[name="quantity"]');
if (quantityInputGlobal) {
    quantityInputGlobal.addEventListener('change', function () {
        window.quantity = this.value;
        calculateFinalPrice();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const customWidthInput = document.getElementById('custom_width');
    const customLengthInput = document.getElementById('custom_length');
    const customWidthInsideInput = document.getElementById('custom_width_inside');
    const customLengthInsideInput = document.getElementById('custom_length_inside');
    if (customWidthInput && customWidthInsideInput) {
        customWidthInput.addEventListener('change', function () {
            markLinearInputsTouched();
            customWidthInsideInput.value = customWidthInput.value;
        });
    }
    if (customLengthInput && customLengthInsideInput) {
        customLengthInput.addEventListener('change', function () {
            markLinearInputsTouched();
            customLengthInsideInput.value = customLengthInput.value;
        });
    }
});


document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll(".calculatePrice").forEach(element => {
        element.addEventListener("input", obrobkaBlachyCalculatePrice);
    })

    const thicknessSelect = document.getElementById('pa_grubosc');
    if (thicknessSelect) {

        const singleVariation = document.getElementsByClassName("single_variation")[0];
        if (singleVariation) {
            singleVariation.style.display = "none";
        }

        thicknessSelect.addEventListener('change', () => {
            if (!productDataReady()) {
                return;
            }
            const slug = thicknessSelect.value;
            if (productData.variantPrices?.[slug]) {
                productData.pricePerM2 = productData.variantPrices[slug];
            }

            if (document.getElementById('custom_length_obrobka')) {
                obrobkaBlachyCalculatePrice();
            } else {
                calculatePrice();
            }
        });
    }
    onProductDataReady(() => {
        obrobkaBlachyCalculatePrice();
        if (!isLinearProduct() && typeof window.calculatePrice === 'function') {
            window.calculatePrice();
        }
    });
});


function obrobkaBlachyCalculatePrice() {
    if (!productDataReady()) {
        return;
    }
    if (isLinearProduct()) {
        return;
    }

    const dimensionsAreValid = validateDimensions();
    if (!dimensionsAreValid) {
        return;
    }

    if (productData.variantPrices && Object.keys(productData.variantPrices).length) {
        const select = document.getElementById('pa_grubosc');
        if (select) {
            const variantValue = select.value;
            if (productData.variantPrices[variantValue]) {
                productData.pricePerM2 = productData.variantPrices[variantValue];
            }
        }
    }

    const unitPrice = productData.pricePerM2;


    const lengthInput = document.getElementById('custom_length_obrobka') ?? document.getElementById('custom_length');
    const length = lengthInput ? parseFloat(lengthInput.value) || 0 : 0;


    let width = 0;
    const dimensions = ['a', 'b', 'c', 'd', 'e', 'f', 'g'];

    dimensions.forEach(dim => {
        const input = document.getElementById(`custom_${dim}`);
        if (input && !isNaN(parseFloat(input.value))) {
            width += parseFloat(input.value);
        }
    });

    if (width == 0) {
        width = parseFloat(document.getElementById("custom_width").value) || 0;
    }

    const totalPrice = unitPrice * length / 1000 * width / 1000;
    window.areaPrice = totalPrice;

    calculateFinalPrice();

    const priceElement = document.getElementById('calculated_price');
    if (priceElement) {
        priceElement.textContent = `Cena: ${totalPrice.toFixed(2)} zł`;
    }

    document.getElementById("final-price").innerHTML = totalPrice.toFixed(2) + " zł";
}


function validateDimensions() {
    const errorElement = document.getElementById('wymiary_error');
    if (errorElement) {
        errorElement.textContent = '';
    }

    let allDimensionsValid = true;
    const inputsToValidate = document.querySelectorAll('.calculatePrice');

    for (const input of inputsToValidate) {
        const value = parseFloat(input.value);
        const min = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : Number.MIN_SAFE_INTEGER;
        const max = input.hasAttribute('max') ? parseFloat(input.getAttribute('max')) : Number.MAX_SAFE_INTEGER;

        if (isNaN(value) || value < min || value > max) {
            allDimensionsValid = false;
            const label = document.querySelector(`label[for="${input.id}"]`);
            const labelText = label ? label.textContent.replace(':', '').trim() : input.id;

            const errorMessage = `${labelText} jest poza zakresem (min: ${min}, max: ${max}).`;

            if (errorElement) {
                errorElement.textContent = errorMessage;
            }
            break;
        }
    }

    return allDimensionsValid;
}

// --- DODATKOWA OBSŁUGA USŁUG PERSONALIZACJI (otworów) ---

window.cutouts_price = 0;


function syncCutoutsHidden() {
    const keys = [];
    document.querySelectorAll('#cutouts_container select[name^="custom_cutouts"]').forEach(sel => {
        const v = (sel.value || '').trim();
        if (v) keys.push(v);
    });
    const hid = document.getElementById('countertop_cutouts_hidden');
    if (hid) {
        hid.value = keys.join(',');
    }
}


/**
 * Zwraca sumę cen zaznaczonych usług (jedna linia = jedna usługa).
 * Pobiera wartość z data-price na wybranej opcji.
 */
function getSelectedCutoutsTotal() {
    let total = 0;
    document.querySelectorAll('#cutouts_container select[name="custom_cutouts[]"]').forEach(sel => {
        const opt = sel.options[sel.selectedIndex];
        if (!opt) return;
        const p = parseFloat(opt.dataset.price);
        if (!isNaN(p)) {
            total += p;
        }
    });
    return total;
}

/**
 * Aktualizuje globalną zmienną i – o ile nie poproszono o silent –
 * pokazuje wartość w małej adnotacji pod kalkulatorem (opcjonalnie).
 */
window.updateCutoutsPrice = function(opts = {}) {
    window.cutouts_price = getSelectedCutoutsTotal();

    syncCutoutsHidden();

    if (!opts.silent) {
        // Pokazujemy krótką informację (opcjonalnie)
        let infoEl = document.getElementById('cutouts_total_info');
        if (!infoEl) {
            infoEl = document.createElement('div');
            infoEl.id = 'cutouts_total_info';
            infoEl.style.marginTop = '6px';
            infoEl.style.fontSize = '14px';
            infoEl.style.color = '#333';
            const ref = document.getElementById('calculated_price');
            if (ref && ref.parentNode) {
                ref.parentNode.appendChild(infoEl);
            }
        }
        infoEl.textContent = 'Dodatkowe usługi: ' + window.cutouts_price.toFixed(2).replace('.', ',') + ' zł';
    }
};

// Delegowane nasłuchiwanie na zmiany w selectach z usługami
document.addEventListener('change', function(e) {
    if (e.target.matches('#cutouts_container select[name="custom_cutouts[]"]')) {
        window.updateCutoutsPrice();
        calculateFinalPrice();
    }
});

// Po kliknięciu "Usuń" – przelicz ponownie po usunięciu elementu
document.addEventListener('click', function(e) {
    const btn = e.target.closest('#cutouts_container .remove-cutout');
    if (!btn) return;
    e.preventDefault();
    const row = btn.closest('.cutout-row');
    if (row) row.remove();
    window.updateCutoutsPrice();
    calculateFinalPrice();
});

// Po kliknięciu "Dodaj kolejną usługę" – gdy nowy wiersz się pojawi
document.addEventListener('click', function(e) {
    if (e.target.matches('#add_cutout_row')) {
        // nowy row powstanie chwilę później
        setTimeout(() => {
            window.updateCutoutsPrice();
            calculateFinalPrice();
        }, 0);
    }
});

// Początkowe wyliczenie po załadowaniu strony
document.addEventListener('DOMContentLoaded', function() {
    window.updateCutoutsPrice({silent:true});
    document.querySelectorAll('.calculatePrice').forEach(el =>
        el.addEventListener('input', () => {
        obrobkaBlachyCalculatePrice();
        if (!isLinearProduct() || linearInputsTouched) {
            calculateFinalPrice();
        }
        })
    );

     const qty = document.querySelector('input[name="quantity"]');
        if (qty) qty.addEventListener('change', () => calculateFinalPrice());

    // 4) zmiana wariacji
    const thicknessSelect = document.getElementById('pa_grubosc');
    if (thicknessSelect) {
        thicknessSelect.addEventListener('change', () => {
        const slug = thicknessSelect.value;
        if (productData.variantPrices?.[slug]) {
            productData.pricePerM2 = productData.variantPrices[slug];
            const display = document.getElementById('gross_price_display');
            display.innerHTML =productData.pricePerM2.toFixed(2).replace('.', ',') + ' zł';
        }
        obrobkaBlachyCalculatePrice();
        });
    }

    onProductDataReady(() => {
        obrobkaBlachyCalculatePrice();
        if (!isLinearProduct() || linearInputsTouched) {
            calculateFinalPrice();
        }
    });

});
