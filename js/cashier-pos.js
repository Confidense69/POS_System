const input = document.getElementById('barcodeInput');
const btn = document.getElementById('addItemBtn');
const tbody = document.getElementById('salesTableBody');
const checkoutBtn = document.getElementById('checkoutBtn');
const customerType = document.getElementById('customerType');
const idNumber = document.getElementById('idNumber');
const paymentMethod = document.getElementById('paymentMethod');

const subtotalValue = document.getElementById('subtotalValue');
const discountValue = document.getElementById('discountValue');
const taxValue = document.getElementById('taxValue');
const totalValue = document.getElementById('totalValue');

const cart = [];

function currency(value) {
    return Number(value || 0).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP'
    });
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
}

function renderCart() {
    tbody.innerHTML = '';

    if (cart.length === 0) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="4" class="text-center text-muted py-4">No items in this sale.</td></tr>';
        updateSummary();
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

btn.addEventListener('click', addByBarCode);
input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') addByBarCode();
});

customerType.addEventListener('change', updateSummary);
idNumber.addEventListener('input', updateSummary);
paymentMethod.addEventListener('change', () => {});

checkoutBtn.addEventListener('click', checkout);

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

renderCart();
   