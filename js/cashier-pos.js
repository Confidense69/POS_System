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
const taxValue = document.getElementById('taxValue');
const totalValue = document.getElementById('totalValue');
const receiptItems = document.getElementById('receiptItems');
const receiptSubtotal = document.getElementById('receiptSubtotal');
const receiptTax = document.getElementById('receiptTax');
const receiptTotal = document.getElementById('receiptTotal');
const qrImage = document.getElementById('qrImage');
const qrDisplayWrap = document.getElementById('qrDisplayWrap');

const cart = [];
const qrImages = {
    gcash: '../assets/qrs/gcash.jpg',
    maya: '../assets/qrs/maya.jpg',
    maribank: '../assets/qrs/maribank.jpg'
};

function currency(value) {
    return Number(value || 0).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP'
    });
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

function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (Number(item.price) * Number(item.quantity)), 0);
    const type = (customerType.value || 'regular').toLowerCase();
    const isDiscounted = type === 'senior' || type === 'pwd';

    let discount = 0;
    let tax = 0;
    let total = subtotal;

    if (isDiscounted) {
        const vatExemptSales = subtotal / 1.12;
        discount = vatExemptSales * 0.20;
        total = vatExemptSales - discount;
        tax = 0;
    } else {
        tax = subtotal * 0.12;
        total = subtotal + tax;
    }

    subtotalValue.textContent = currency(subtotal);
    discountValue.textContent = currency(discount);
    taxValue.textContent = currency(tax);
    totalValue.textContent = currency(total);

    receiptSubtotal.textContent = currency(subtotal);
    receiptTax.textContent = currency(tax);
    receiptTotal.textContent = currency(total);
    updateQrDisplay();
}

function renderReceipt() {
    if (cart.length === 0) {
        receiptItems.innerHTML = '<div class="text-muted">No items yet</div>';
        return;
    }

    const itemsHtml = cart.map(item => `
        <div class="d-flex justify-content-between gap-2">
            <span>${item.name} x${item.quantity}</span>
            <span>${currency(item.price * item.quantity)}</span>
        </div>
    `).join('');

    receiptItems.innerHTML = itemsHtml;
}

function renderCart() {
    clearCartBtn.disabled = cart.length === 0;
    tbody.innerHTML = '';

    if (cart.length === 0) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="4" class="text-center text-muted py-4">No items in this sale.</td></tr>';
        updateSummary();
        renderReceipt();
        return;
    }

    cart.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="fw-semibold">${item.name}</div>
                <small class="text-muted">${item.barcode}</small>
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
        const existing = cart.find(item => item.product_id === product.id);

        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                product_id: product.id,
                barcode: product.barcode,
                name: product.name,
                price: Number(product.price),
                quantity: 1
            });
        }

        renderCart();
        input.value = '';
    } catch (error) {
        console.error(error);
        alert('Unable to fetch product.');
    }
}

async function checkout() {
    if (cart.length === 0) {
        alert('Cart is empty.');
        return;
    }

    const payload = {
        cart: cart.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            price: item.price
        })),
        tax_rate: 0.12,
        customer_type: customerType.value,
        id_number: idNumber.value.trim(),
        payment_method: paymentMethod.value
    };

    try {
        const res = await fetch('../api/pos_system_api.php?action=checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await res.json();

        if (!res.ok || result.status !== 'success') {
            throw new Error(result.message || 'Checkout failed.');
        }

        alert('Checkout successful! Transaction ID: ' + result.transaction_id);
        cart.length = 0;
        renderCart();
        input.value = '';
    } catch (error) {
        console.error(error);
        alert(error.message || 'Checkout failed.');
    }
}

function clearCart() {
    cart.length = 0;
    renderCart();
    input.value = '';
    input.focus();
}

btn.addEventListener('click', addByBarCode);
input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addByBarCode();
    }
});

customerType.addEventListener('change', updateSummary);
idNumber.addEventListener('input', updateSummary);
paymentMethod.addEventListener('change', updateQrDisplay);
checkoutBtn.addEventListener('click', checkout);
clearCartBtn.addEventListener('click', clearCart);

tbody.addEventListener('click', function (event) {
    const btnTarget = event.target.closest('button[data-action]');
    if (!btnTarget) return;

    const index = Number(btnTarget.dataset.index);
    const action = btnTarget.dataset.action;

    if (action === 'increase') {
        cart[index].quantity += 1;
    } else if (action === 'decrease') {
        cart[index].quantity -= 1;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
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
        if (cart.length > 0) {
            cart.pop();
            renderCart();
        }
    } else if (event.key === 'F4') {
        event.preventDefault();
        checkout();
    } else if (event.key === 'F5') {
        event.preventDefault();
        clearCart();
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
updateQrDisplay();
   