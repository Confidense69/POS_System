document.addEventListener("DOMContentLoaded", () => {
    loadAccounts();
    loadInventory();
    loadSuppliers();

    // Form Submissions
    document.getElementById("addProductForm")?.addEventListener("submit", handleAddProduct);
    document.getElementById("addSupplierForm")?.addEventListener("submit", handleAddSupplier);
    document.getElementById("addAccountForm")?.addEventListener("submit", handleAddAccount);
    document.getElementById("posCheckoutBtn")?.addEventListener("click", handlePOSCheckout);
});

let posCart = [];

// 1. Account Management
async function loadAccounts() {
    const res = await fetch("../api/pos_system_api.php?action=get_users");
    const result = await res.json();
    const tbody = document.getElementById("accountsTableBody");
    if (!tbody) return;

    if (result.status === "success") {
        tbody.innerHTML = result.data.map(u => `
            <tr>
                <td><code>USR-${u.id}</code></td>
                <td class="fw-bold">${u.username}</td>
                <td>${u.email || 'N/A'}</td>
                <td><span class="badge bg-secondary">${u.role}</span></td>
                <td><span class="badge ${u.is_active == 1 ? 'bg-success' : 'bg-danger'}">${u.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" onclick="toggleUser(${u.id})">
                        ${u.is_active == 1 ? 'Deactivate' : 'Activate'}
                    </button>
                </td>
            </tr>
        `).join("");
    }
}

async function toggleUser(userId) {
    await fetch("../api/pos_system_api.php?action=toggle_user", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: userId })
    });
    loadAccounts();
}

async function handleAddAccount(e) {
    e.preventDefault();
    const payload = {
        username: document.getElementById("accUsername").value,
        full_name: document.getElementById("accFullName").value,
        email: document.getElementById("accEmail").value,
        role: document.getElementById("accRole").value
    };

    await fetch("../api/pos_system_api.php?action=add_user", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    loadAccounts();
}

// 2. Inventory Management
async function loadInventory() {
    const res = await fetch("../api/pos_system_api.php?action=get_products");
    const result = await res.json();
    const tbody = document.getElementById("inventoryTableBody");
    if (!tbody) return;

    if (result.status === "success") {
        let lowStockCount = 0;
        tbody.innerHTML = result.data.map(p => {
            const isLow = p.stock_qty <= p.reorder_level;
            if (isLow) lowStockCount++;

            return `
                <tr>
                    <td><code>${p.barcode}</code></td>
                    <td class="fw-bold">${p.name}</td>
                    <td>${p.supplier_name || 'N/A'}</td>
                    <td>$${parseFloat(p.price).toFixed(2)}</td>
                    <td>
                        <span class="badge ${isLow ? 'bg-warning text-dark' : 'bg-success'}">
                            ${p.stock_qty} units
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="restockProduct(${p.id}, 10)">+10 Stock</button>
                        <button class="btn btn-sm btn-success" onclick="addCartItem(${p.id}, '${p.name}', ${p.price})">+ POS</button>
                    </td>
                </tr>
            `;
        }).join("");

        document.getElementById("statLowStock").innerText = lowStockCount;
        document.getElementById("statProducts").innerText = result.data.length;
    }
}

async function handleAddProduct(e) {
    e.preventDefault();
    const payload = {
        barcode: document.getElementById("mSku").value,
        name: document.getElementById("mName").value,
        price: parseFloat(document.getElementById("mPrice").value),
        stock_qty: parseInt(document.getElementById("mStock").value),
        supplier_id: document.getElementById("mSupplier").value
    };

    await fetch("../api/pos_system_api.php?action=add_product", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    loadInventory();
}

async function restockProduct(productId, qty) {
    await fetch("../api/pos_system_api.php?action=restock_product", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_id: productId, quantity: qty })
    });
    loadInventory();
}

// 3. Supplier Management
async function loadSuppliers() {
    const res = await fetch("../api/pos_system_api.php?action=get_suppliers");
    const result = await res.json();
    const tbody = document.getElementById("suppliersTableBody");
    const select = document.getElementById("mSupplier");
    if (!tbody) return;

    if (result.status === "success") {
        tbody.innerHTML = result.data.map(s => `
            <tr>
                <td><code>SUP-${s.id}</code></td>
                <td class="fw-bold">${s.name}</td>
                <td>${s.email || 'N/A'}</td>
                <td>${s.phone || 'N/A'}</td>
                <td>${s.address || 'N/A'}</td>
            </tr>
        `).join("");

        if (select) {
            select.innerHTML = `<option value="">Select Supplier</option>` + 
                result.data.map(s => `<option value="${s.id}">${s.name}</option>`).join("");
        }
    }
}

async function handleAddSupplier(e) {
    e.preventDefault();
    const payload = {
        name: document.getElementById("supName").value,
        contact_person: document.getElementById("supContact").value,
        email: document.getElementById("supEmail").value,
        phone: document.getElementById("supPhone").value,
        address: document.getElementById("supAddress").value
    };

    await fetch("../api/pos_system_api.php?action=add_supplier", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    loadSuppliers();
}

// 4. POS / Sales Management
function addCartItem(productId, name, price) {
    const existing = posCart.find(item => item.product_id === productId);
    if (existing) {
        existing.quantity += 1;
    } else {
        posCart.push({ product_id: productId, name, price, quantity: 1 });
    }
    renderPOSCart();
}

function renderPOSCart() {
    const tbody = document.getElementById("posCartBody");
    if (!tbody) return;

    if (posCart.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">Cart is empty</td></tr>`;
        document.getElementById("posSubtotal").innerText = "$0.00";
        document.getElementById("posTax").innerText = "$0.00";
        document.getElementById("posTotal").innerText = "$0.00";
        return;
    }

    let subtotal = 0;
    tbody.innerHTML = posCart.map(item => {
        const lineTotal = item.price * item.quantity;
        subtotal += lineTotal;
        return `
            <tr>
                <td>${item.name}</td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-end">$${item.price.toFixed(2)}</td>
                <td class="text-end fw-bold">$${lineTotal.toFixed(2)}</td>
            </tr>
        `;
    }).join("");

    const tax = subtotal * 0.08;
    const total = subtotal + tax;

    document.getElementById("posSubtotal").innerText = `$${subtotal.toFixed(2)}`;
    document.getElementById("posTax").innerText = `$${tax.toFixed(2)}`;
    document.getElementById("posTotal").innerText = `$${total.toFixed(2)}`;
}

async function handlePOSCheckout() {
    if (posCart.length === 0) {
        alert("Please add items to cart before checkout!");
        return;
    }

    const res = await fetch("../api/pos_system_api.php?action=checkout", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ cart: posCart })
    });
    const result = await res.json();

    if (result.status === "success") {
        alert("Transaction completed successfully!");
        posCart = [];
        renderPOSCart();
        loadInventory();
    } else {
        alert("Error: " + result.message);
    }
}
