const input = document.getElementById('barcodeInput');
const btn = document.getElementById('addItemBtn');
const tbody = document.getElementById('salesTableBody');
const checkoutBtn = document.getElementById('checkoutBtn');
const clearCartBtn = document.getElementById('clearCartBtn');
const customerType = document.getElementById('customerType');
const idNumber = document.getElementById('idNumber');
const paymentMethod = document.getElementById('paymentMethod');
const cashRow = document.getElementById('cashRow');
const cashTendered = document.getElementById('cashTendered');
const changeValue = document.getElementById('changeValue');
const searchResultsEl = document.getElementById('searchResults');

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

const statSales = document.getElementById('statSales');
const statItems = document.getElementById('statItems');
const statTransactions = document.getElementById('statTransactions');

const historyBody = document.getElementById('historyTableBody');
const receiptBody = document.getElementById('receiptBody');

const API = '../api/pos_system_api.php';

// Mirrors api/tax_engine.php. vatRate is replaced by the admin setting on load.
const DISCOUNT_RATE = 0.20;
let vatRate = 0.12;

const cart = [];
let selectedIndex = -1;
let checkoutInProgress = false;

let searchResults = [];
let highlightedResult = -1;
let searchTimer = null;

let historyCache = [];

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

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d)
        ? String(value)
        : d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function postJSON(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (err) {
        throw new Error('Server sent a non-JSON reply: ' + text.slice(0, 200));
    }
}

function vatPercent() {
    return Math.round(vatRate * 100);
}

function isDiscountedSale() {
    const type = (customerType.value || 'regular').toLowerCase();
    return type === 'senior' || type === 'pwd';
}

/**
 * Mirror of api/tax_engine.php. Product prices are VAT-exclusive, so VAT is
 * added on top of the subtotal. Senior/PWD sales are VAT-exempt and get 20%
 * off the net subtotal instead.
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

    const vat = round2(gross * vatRate);
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

function updateChange() {
    const totals = computeTotals();
    const isCash = paymentMethod.value === 'cash';

    cashRow.style.display = isCash ? 'block' : 'none';

    if (!isCash) {
        changeValue.textContent = currency(0);
        changeValue.className = '';
        return;
    }

    const tendered = Number(cashTendered.value);
    if (!cashTendered.value || isNaN(tendered)) {
        changeValue.textContent = currency(0);
        changeValue.className = '';
        return;
    }

    const change = round2(tendered - totals.total);
    if (change < 0) {
        changeValue.textContent = 'Short ' + currency(Math.abs(change));
        changeValue.className = 'text-danger';
    } else {
        changeValue.textContent = currency(change);
        changeValue.className = 'text-success';
    }
}

function updateSummary() {
    const totals = computeTotals();
    const taxLabelText = isDiscountedSale()
        ? 'VAT (exempt)'
        : 'VAT (' + vatPercent() + '%)';

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

    updateChange();
}

function renderReceiptPreview() {
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
        renderReceiptPreview();
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
    renderReceiptPreview();
}

function addProductToCart(product) {
    const productId = Number(product.id);
    const stockQty = Number(product.stock_qty);
    const existing = cart.find(function (item) {
        return item.product_id === productId;
    });

    if (existing) {
        if (existing.quantity >= stockQty) {
            alert(`Only ${stockQty} in stock for "${product.name}".`);
            return false;
        }
        existing.quantity += 1;
        selectedIndex = cart.indexOf(existing);
    } else {
        if (stockQty < 1) {
            alert(`"${product.name}" is out of stock.`);
            return false;
        }
        cart.push({
            product_id: productId,
            barcode: product.barcode,
            name: product.name,
            price: Number(product.price),
            stock_qty: stockQty,
            quantity: 1
        });
        selectedIndex = cart.length - 1;
    }

    renderCart();
    return true;
}

function hideSearchResults() {
    searchResults = [];
    highlightedResult = -1;
    searchResultsEl.classList.add('d-none');
    searchResultsEl.innerHTML = '';
}

function renderSearchResults() {
    if (searchResults.length === 0) {
        hideSearchResults();
        return;
    }

    searchResultsEl.innerHTML = searchResults.map(function (product, index) {
        const active = index === highlightedResult ? ' active' : '';
        const stock = Number(product.stock_qty);
        return `
            <button type="button" class="list-group-item list-group-item-action${active}" data-result-index="${index}">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">${escapeHtml(product.name)}</span>
                    <span>${currency(product.price)}</span>
                </div>
                <small class="${stock > 0 ? 'text-muted' : 'text-danger'}">
                    ${escapeHtml(product.barcode)} · ${stock > 0 ? stock + ' in stock' : 'out of stock'}
                </small>
            </button>
        `;
    }).join('');

    searchResultsEl.classList.remove('d-none');
}

async function fetchSearch(query) {
    const res = await fetch(API + '?action=search_products&q=' + encodeURIComponent(query));
    const result = await res.json();
    return result.status === 'success' && Array.isArray(result.data) ? result.data : [];
}

async function searchByName() {
    const query = input.value.trim();
    if (!query) {
        hideSearchResults();
        return;
    }

    try {
        searchResults = await fetchSearch(query);
        highlightedResult = searchResults.length > 0 ? 0 : -1;
        renderSearchResults();
    } catch (error) {
        console.error(error);
    }
}

async function addByBarCode() {
    const query = input.value.trim();
    if (!query) return;

    try {
        const results = await fetchSearch(query);

        if (results.length === 0) {
            alert('No product matched "' + query + '".');
            hideSearchResults();
            input.value = '';
            return;
        }

        const exact = results.find(function (product) {
            return product.barcode === query;
        });

        let pick = null;
        if (highlightedResult >= 0 && results[highlightedResult]) {
            pick = results[highlightedResult];
        } else if (exact) {
            pick = exact;
        } else if (results.length === 1) {
            pick = results[0];
        }

        if (!pick) {
            // Several name matches: show the list and let the cashier choose.
            searchResults = results;
            highlightedResult = 0;
            renderSearchResults();
            return;
        }

        if (addProductToCart(pick)) {
            hideSearchResults();
            input.value = '';
        }
        input.focus();
    } catch (error) {
        console.error(error);
        alert('Unable to search products.');
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
    if (paymentMethod.value === 'cash') {
        const totals = computeTotals();
        const tendered = Number(cashTendered.value);
        if (cashTendered.value && !isNaN(tendered) && tendered < totals.total) {
            alert('Cash tendered is less than the total.');
            cashTendered.focus();
            return false;
        }
    }
    return true;
}

function showReceipt(data) {
    const summary = data.summary;
    const items = data.items || [];
    const isVoid = data.is_voided === true;
    const isExempt = data.customer_type === 'senior'
        || data.customer_type === 'pwd'
        || Number(summary.vat_exempt_sales) > 0;
    const receiptVatPercent = Math.round(Number(summary.tax_rate_applied) * 100);
    const paymentLabels = {
        cash: 'Cash',
        gcash: 'GCash',
        maya: 'Maya',
        maribank: 'MariBank'
    };
    const customerLabels = {
        regular: 'Regular',
        senior: 'Senior Citizen',
        pwd: 'PWD'
    };

    receiptBody.innerHTML = `
        <div class="receipt-paper">
            <div class="text-center mb-2">
                <div class="fw-bold">POS ACCOUNTING SYSTEM</div>
                <div class="small text-muted">Soft copy receipt</div>
                ${isVoid ? '<div class="badge bg-danger mt-2">VOIDED</div>' : ''}
            </div>
            <hr class="my-2">
            <div class="small">
                <div class="d-flex justify-content-between"><span>Txn</span><span>#${escapeHtml(data.transaction_id)}</span></div>
                <div class="d-flex justify-content-between"><span>Date</span><span>${escapeHtml(formatDate(data.created_at))}</span></div>
                <div class="d-flex justify-content-between"><span>Cashier</span><span>${escapeHtml(data.cashier || '—')}</span></div>
                <div class="d-flex justify-content-between"><span>Payment</span><span>${escapeHtml(paymentLabels[data.payment_method] || data.payment_method)}</span></div>
                <div class="d-flex justify-content-between"><span>Customer</span><span>${escapeHtml(customerLabels[data.customer_type] || data.customer_type || 'Regular')}</span></div>
                ${data.discount_id ? `<div class="d-flex justify-content-between"><span>ID No.</span><span>${escapeHtml(data.discount_id)}</span></div>` : ''}
            </div>
            <hr class="my-2">
            <div class="small">
                ${items.map(function (item) {
                    return `<div class="d-flex justify-content-between gap-2">
                        <span>${escapeHtml(item.name)} x${item.quantity}</span>
                        <span>${currency(item.line_total)}</span>
                    </div>`;
                }).join('')}
            </div>
            <hr class="my-2">
            <div class="small">
                <div class="d-flex justify-content-between"><span>Subtotal</span><span>${currency(summary.gross_subtotal)}</span></div>
                <div class="d-flex justify-content-between"><span>Discount</span><span>-${currency(summary.discount_amount)}</span></div>
                <div class="d-flex justify-content-between">
                    <span>VAT ${isExempt ? '(exempt)' : '(' + receiptVatPercent + '%)'}</span>
                    <span>${currency(summary.vat_amount)}</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold"><span>Total</span><span>${currency(summary.net_total)}</span></div>
                ${data.tendered !== undefined && data.tendered !== null ? `<div class="d-flex justify-content-between"><span>Tendered</span><span>${currency(data.tendered)}</span></div>` : ''}
                ${data.change !== undefined && data.change !== null ? `<div class="d-flex justify-content-between"><span>Change</span><span>${currency(data.change)}</span></div>` : ''}
            </div>
            <div class="text-center small text-muted mt-3">Thank you for your purchase!</div>
        </div>
    `;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('receiptModal')).show();
}

async function checkout() {
    if (checkoutInProgress || !validateCheckout()) {
        return;
    }

    const totals = computeTotals();
    const tendered = paymentMethod.value === 'cash' ? Number(cashTendered.value) : null;
    const change = tendered !== null && !isNaN(tendered) ? round2(tendered - totals.total) : null;

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
        const result = await postJSON(API + '?action=checkout', payload);

        if (result.status !== 'success') {
            throw new Error(result.message || 'Checkout failed.');
        }

        showReceipt({
            ...result,
            tendered: tendered,
            change: change
        });

        cart.length = 0;
        selectedIndex = -1;
        idNumber.value = '';
        cashTendered.value = '';
        renderCart();
        input.value = '';
        loadCashierStats();
        loadHistory();
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

async function loadSettings() {
    try {
        const res = await fetch(API + '?action=get_settings');
        const result = await res.json();
        if (result.status === 'success' && result.data && Number(result.data.vat_rate) > 0) {
            vatRate = Number(result.data.vat_rate) / 100;
        }
    } catch (error) {
        console.error('Could not load VAT setting, using 12%.', error);
    }
    updateSummary();
}

async function loadCashierStats() {
    try {
        const res = await fetch(API + '?action=cashier_stats');
        const result = await res.json();
        if (result.status !== 'success' || !result.data) return;

        if (statSales) statSales.textContent = currency(result.data.todays_sales);
        if (statItems) statItems.textContent = Number(result.data.items_scanned || 0);
        if (statTransactions) statTransactions.textContent = Number(result.data.transactions || 0);
    } catch (error) {
        console.error('Could not load today\'s totals.', error);
    }
}

function receiptFromTransaction(txn) {
    return {
        transaction_id: txn.id,
        created_at: txn.created_at,
        cashier: txn.cashier,
        payment_method: txn.payment_method,
        customer_type: txn.customer_type,
        discount_id: txn.discount_id,
        is_voided: Number(txn.is_voided) === 1,
        items: (txn.items || []).map(function (item) {
            return {
                name: item.name,
                quantity: Number(item.quantity),
                unit_price: Number(item.unit_price),
                line_total: Number(item.line_total)
            };
        }),
        summary: {
            gross_subtotal: Number(txn.subtotal),
            vatable_sales: Number(txn.vatable_sales),
            vat_exempt_sales: Number(txn.vat_exempt_sales),
            tax_rate_applied: Number(txn.tax_rate),
            vat_amount: Number(txn.tax_amt),
            discount_amount: Number(txn.discount_amt),
            net_total: Number(txn.total)
        }
    };
}

async function loadHistory() {
    if (!historyBody) return;

    try {
        const res = await fetch(API + '?action=get_transactions&scope=mine&limit=50');
        const result = await res.json();

        if (result.status !== 'success' || !Array.isArray(result.data)) {
            historyBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Could not load transactions.</td></tr>';
            return;
        }

        historyCache = result.data;

        if (historyCache.length === 0) {
            historyBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No transactions yet.</td></tr>';
            return;
        }

        historyBody.innerHTML = historyCache.map(function (txn) {
            const voided = Number(txn.is_voided) === 1;
            return `
                <tr class="${voided ? 'table-danger' : ''}">
                    <td><code>#${txn.id}</code></td>
                    <td>${escapeHtml(formatDate(txn.created_at))}</td>
                    <td>${escapeHtml(txn.customer_type)}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(txn.payment_method)}</span></td>
                    <td class="text-end fw-bold">${currency(txn.total)}</td>
                    <td>${voided
                        ? '<span class="badge bg-danger">Voided</span>'
                        : '<span class="badge bg-success">Completed</span>'}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="viewTransaction(${txn.id})">View</button>
                        ${voided ? '' : `<button class="btn btn-sm btn-outline-danger" onclick="voidTransaction(${txn.id})">Void</button>`}
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error(error);
        historyBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Could not load transactions.</td></tr>';
    }
}

function viewTransaction(transactionId) {
    const txn = historyCache.find(function (row) {
        return Number(row.id) === Number(transactionId);
    });
    if (!txn) return;
    showReceipt(receiptFromTransaction(txn));
}

async function voidTransaction(transactionId) {
    if (!confirm('Void transaction #' + transactionId + '? Stock will be returned to inventory.')) {
        return;
    }

    try {
        const result = await postJSON(API + '?action=void_transaction', {
            transaction_id: transactionId
        });

        if (result.status !== 'success') {
            alert(result.message || 'Could not void this transaction.');
            return;
        }

        await loadHistory();
        loadCashierStats();
    } catch (error) {
        alert(error.message || 'Could not void this transaction.');
    }
}

btn.addEventListener('click', addByBarCode);
input.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(searchByName, 200);
});
input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addByBarCode();
        return;
    }
    if (searchResultsEl.classList.contains('d-none')) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightedResult = Math.min(highlightedResult + 1, searchResults.length - 1);
        renderSearchResults();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightedResult = Math.max(highlightedResult - 1, 0);
        renderSearchResults();
    } else if (e.key === 'Escape') {
        hideSearchResults();
    }
});
input.addEventListener('blur', function () {
    // Small delay so a click on a result still registers.
    setTimeout(hideSearchResults, 150);
});
searchResultsEl.addEventListener('mousedown', function (event) {
    const item = event.target.closest('[data-result-index]');
    if (!item) return;
    event.preventDefault();
    const product = searchResults[Number(item.dataset.resultIndex)];
    if (product && addProductToCart(product)) {
        hideSearchResults();
        input.value = '';
        input.focus();
    }
});

customerType.addEventListener('change', function () {
    updateCustomerTypeUi();
    updateSummary();
});
paymentMethod.addEventListener('change', function () {
    updateQrDisplay();
    updateChange();
});
cashTendered.addEventListener('input', updateChange);
checkoutBtn.addEventListener('click', checkout);
clearCartBtn.addEventListener('click', clearCart);
document.getElementById('printReceiptBtn')?.addEventListener('click', function () {
    window.print();
});
document.getElementById('refreshHistoryBtn')?.addEventListener('click', loadHistory);
document.getElementById('historyModal')?.addEventListener('shown.bs.modal', loadHistory);

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
            updateChange();
        }
    }
});

renderCart();
updateCustomerTypeUi();
updateQrDisplay();
loadSettings();
loadCashierStats();
loadHistory();
