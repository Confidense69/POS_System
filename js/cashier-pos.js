const input = document.getElementById('barcodeInput');
const btn = document.getElementById('addItemBtn');
const tbody = document.getElementById('salesTableBody');
const checkoutBtn = document.getElementById('checkoutBtn');
const clearCartBtn = document.getElementById('clearCartBtn');
const customerType = document.getElementById('customerType');
const idNumber = document.getElementById('idNumber');
const paymentMethod = document.getElementById('paymentMethod');

const subtotalValue = document.getElementById('subtotalValue');
const discountValue = document.getElementById('discountValue');
const taxLabel = document.getElementById('taxLabel');
const taxValue = document.getElementById('taxValue');
const totalValue = document.getElementById('totalValue');

const receiptItems = document.getElementById('receiptItems');
const receiptSubtotal = document.getElementById('receiptSubtotal');
const receiptDiscount = document.getElementById('receiptDiscount');
const receiptTaxLabel = document.getElementById('receiptTaxLabel');
const receiptTax = document.getElementById('receiptTax');
const receiptTotal = document.getElementById('receiptTotal');

const qrImage = document.getElementById('qrImage');
const qrDisplayWrap = document.getElementById('qrDisplayWrap');

// Must match api/tax_engine.php.
const VAT_RATE = 0.12;
const DISCOUNT_RATE = 0.20;

const cart = [];
let selectedIndex = -1;
let checkoutInProgress = false;

const qrImages = {
    gcash: '../assets/qrs/gcash.jpg',
    maya: '../assets/qrs/maya.jpg',
    maribank: '../assets/qrs/maribank.jpg'
};

function round2(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
}

function currency(value) {
    return Number(value || 0).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP'
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char];
    });
}

function isDiscountedSale() {
    const type = (customerType.value || 'regular').toLowerCase();
    return type === 'senior' || type === 'pwd';
}

/**
 * Mirror of api/tax_engine.php. Product prices are VAT-exclusive, so 12% VAT
 * is added on top of the subtotal. Senior/PWD sales are VAT-exempt and get
 * 20% off the net subtotal instead.
 */
function computeTotals() {
    const gross = round2(cart.reduce(function (sum, item) {
        return sum + Number(item.price) * Number(item.quantity);
    }, 0));

    if (isDiscountedSale()) {
        const discount = round2(gross * DISCOUNT_RATE);
        return {
            gross: gross,
            vat: 0,
            discount: discount,
            total: round2(gross - discount)
        };
    }

    const vat = round2(gross * VAT_RATE);
    return {
        gross: gross,
        vat: vat,
        discount: 0,
        total: round2(gross + vat)
    };
}

function updateQrDisplay() {
    const method = paymentMethod.value;
    const imagePath = qrImages[method];
    if (!imagePath) {
        qrDisplayWrap.style.display = 'none';
        qrImage.removeAttribute('src');
        return;
    }

    qrDisplayWrap.style.display = 'block';
    qrImage.src = imagePath;
    qrImage.alt = `${paymentMethod.options[paymentMethod.selectedIndex].text} payment QR code`;
}

function updateCustomerTypeUi() {
    const discounted = isDiscountedSale();
    idNumber.required = discounted;
    idNumber.placeholder = discounted ? 'Required for Senior / PWD' : 'Only needed for Senior / PWD';
}

function updateSummary() {
    const totals = computeTotals();
    const taxLabelText = isDiscountedSale() ? 'VAT (exempt)' : 'VAT (12%)';

    taxLabel.textContent = taxLabelText;
    receiptTaxLabel.textContent = taxLabelText;

    subtotalValue.textContent = currency(totals.gross);
    discountValue.textContent = currency(totals.discount);
    taxValue.textContent = currency(totals.vat);
    totalValue.textContent = currency(totals.total);

    receiptSubtotal.textContent = currency(totals.gross);
    receiptDiscount.textContent = currency(totals.discount);
    receiptTax.textContent = currency(totals.vat);
    receiptTotal.textContent = currency(totals.total);
}

function renderReceipt() {
    if (cart.length === 0) {
        receiptItems.innerHTML = '<div class="text-muted">No items yet</div>';
        return;
    }

    receiptItems.innerHTML = cart.map(function (item) {
        return `
            <div class="d-flex justify-content-between gap-2">
                <span>${escapeHtml(item.name)} x${item.quantity}</span>
                <span>${currency(item.price * item.quantity)}</span>
            </div>
        `;
    }).join('');
}

function renderCart() {
    clearCartBtn.disabled = cart.length === 0;
    checkoutBtn.disabled = checkoutInProgress || cart.length === 0;
    tbody.innerHTML = '';

    if (cart.length === 0) {
        selectedIndex = -1;
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="4" class="text-center text-muted py-4">No items in this sale.</td></tr>';
        updateSummary();
        renderReceipt();
        return;
    }

    if (selectedIndex >= cart.length) {
        selectedIndex = cart.length - 1;
    }

    cart.forEach(function (item, index) {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        if (index === selectedIndex) {
            tr.classList.add('table-active');
        }
        tr.dataset.index = index;
        tr.innerHTML = `
            <td>
                <div class="fw-semibold">${escapeHtml(item.name)}</div>
                <small class="text-muted">${escapeHtml(item.barcode)}</small>
            </td>
            <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" data-action="decrease" data-index="${index}">-</button>
                    <span class="px-2 align-self-center">${item.quantity}</span>
                    <button type="button" class="btn btn-outline-secondary" data-action="increase" data-index="${index}">+</button>
                </div>
            </td>
            <td class="text-end">${currency(item.price)}</td>
            <td class="text-end">${currency(item.price * item.quantity)}</td>
        `;
        tbody.appendChild(tr);
    });

    updateSummary();
    renderReceipt();
}

async function addByBarCode() {
    const barcode = input.value.trim();
    if (!barcode) return;

    try {
        const res = await fetch('../api/products.php?barcode=' + encodeURIComponent(barcode));
        const data = await res.json();

        if (!Array.isArray(data) || data.length === 0) {
            alert('No product with that barcode');
            input.value = '';
            return;
        }

        const product = data[0];

        if (Number(product.is_active) !== 1) {
            alert(`"${product.name}" is no longer available.`);
            input.value = '';
            return;
        }

        const stockQty = Number(product.stock_qty);
        const existing = cart.find(function (item) {
            return item.product_id === Number(product.id);
        });

        if (existing) {
            if (existing.quantity >= stockQty) {
                alert(`Only ${stockQty} in stock for "${product.name}".`);
                input.value = '';
                return;
            }
            existing.quantity += 1;
            selectedIndex = cart.indexOf(existing);
        } else {
            if (stockQty < 1) {
                alert(`"${product.name}" is out of stock.`);
                input.value = '';
                return;
            }
            cart.push({
                product_id: Number(product.id),
                barcode: product.barcode,
                name: product.name,
                price: Number(product.price),
                stock_qty: stockQty,
                quantity: 1
            });
            selectedIndex = cart.length - 1;
        }

        renderCart();
        input.value = '';
        input.focus();
    } catch (error) {
        console.error(error);
        alert('Unable to fetch product.');
    }
}

function validateCheckout() {
    if (cart.length === 0) {
        alert('Cart is empty.');
        return false;
    }
    if (isDiscountedSale() && !idNumber.value.trim()) {
        alert('ID Number is required for Senior Citizen / PWD discounts.');
        idNumber.focus();
        return false;
    }
    return true;
}

async function checkout() {
    if (checkoutInProgress || !validateCheckout()) {
        return;
    }

    const payload = {
        cart: cart.map(function (item) {
            return {
                product_id: item.product_id,
                quantity: item.quantity
            };
        }),
        customer_type: customerType.value,
        id_number: idNumber.value.trim(),
        payment_method: paymentMethod.value
    };

    const originalLabel = checkoutBtn.textContent;
    checkoutInProgress = true;
    checkoutBtn.disabled = true;
    checkoutBtn.textContent = 'Processing...';

    try {
        const res = await fetch('../api/pos_system_api.php?action=checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await res.json().catch(function () {
            return { status: 'error', message: 'Server returned an invalid response.' };
        });

        if (!res.ok || result.status !== 'success') {
            throw new Error(result.message || 'Checkout failed.');
        }

        const paid = result.summary ? currency(result.summary.net_total) : '';
        alert('Checkout successful! Transaction ID: ' + result.transaction_id + (paid ? '\nTotal: ' + paid : ''));
        cart.length = 0;
        selectedIndex = -1;
        idNumber.value = '';
        renderCart();
        input.value = '';
    } catch (error) {
        console.error(error);
        alert(error.message || 'Checkout failed.');
    } finally {
        checkoutInProgress = false;
        checkoutBtn.textContent = originalLabel;
        checkoutBtn.disabled = cart.length === 0;
        input.focus();
    }
}

function clearCart() {
    if (cart.length === 0) return;
    if (!confirm('Clear all items from this sale?')) return;
    cart.length = 0;
    selectedIndex = -1;
    renderCart();
    input.value = '';
    input.focus();
}

function voidSelectedRow() {
    if (cart.length === 0) return;
    const index = selectedIndex >= 0 ? selectedIndex : cart.length - 1;
    cart.splice(index, 1);
    selectedIndex = Math.min(index, cart.length - 1);
    renderCart();
    input.focus();
}

btn.addEventListener('click', addByBarCode);
input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addByBarCode();
    }
});

customerType.addEventListener('change', function () {
    updateCustomerTypeUi();
    updateSummary();
});
paymentMethod.addEventListener('change', updateQrDisplay);
checkoutBtn.addEventListener('click', checkout);
clearCartBtn.addEventListener('click', clearCart);

tbody.addEventListener('click', function (event) {
    const row = event.target.closest('tr[data-index]');
    if (row) {
        selectedIndex = Number(row.dataset.index);
    }

    const btnTarget = event.target.closest('button[data-action]');
    if (!btnTarget) {
        renderCart();
        return;
    }

    const index = Number(btnTarget.dataset.index);
    const action = btnTarget.dataset.action;

    if (action === 'increase') {
        if (cart[index].quantity >= cart[index].stock_qty) {
            alert(`Only ${cart[index].stock_qty} in stock for "${cart[index].name}".`);
        } else {
            cart[index].quantity += 1;
        }
    } else if (action === 'decrease') {
        cart[index].quantity -= 1;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
            selectedIndex = Math.min(index, cart.length - 1);
        }
    }

    renderCart();
});

document.addEventListener('keydown', function (event) {
    const tagName = document.activeElement && document.activeElement.tagName;
    const isTyping = tagName === 'INPUT' || tagName === 'SELECT' || tagName === 'TEXTAREA';

    if (event.key === 'F2') {
        event.preventDefault();
        input.focus();
    } else if (event.key === 'F3') {
        event.preventDefault();
        voidSelectedRow();
    } else if (event.key === 'F4') {
        event.preventDefault();
        checkout();
    } else if (event.key === 'Escape' && !isTyping) {
        input.value = '';
        input.focus();
    } else if (!isTyping) {
        const paymentMethods = {
            '1': 'cash',
            '2': 'gcash',
            '3': 'maya',
            '4': 'maribank'
        };
        if (paymentMethods[event.key]) {
            paymentMethod.value = paymentMethods[event.key];
            updateQrDisplay();
        }
    }
});

renderCart();
updateCustomerTypeUi();
updateQrDisplay();
