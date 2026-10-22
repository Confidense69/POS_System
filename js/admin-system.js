document.addEventListener("DOMContentLoaded", () => {
    loadAccounts();
    loadInventory();
    loadSuppliers();

    // Form Submissions
    document.getElementById("addProductForm")?.addEventListener("submit", handleAddProduct);
    document.getElementById("addSupplierForm")?.addEventListener("submit", handleAddSupplier);
    document.getElementById("editSupplierForm")?.addEventListener("submit", handleEditSupplier);
    document.getElementById("addAccountForm")?.addEventListener("submit", handleAddAccount);
    document.getElementById("posCheckoutBtn")?.addEventListener("click", handlePOSCheckout);
});

let posCart = [];
let inventoryCache = [];
let suppliersCache = [];

// Escape a value that came FROM THE DATABASE before it goes into innerHTML.
// Without this, a supplier named  <img src=x onerror=alert(1)>  is parsed as HTML, not text.
function esc(value) {
    if (value === null || value === undefined) return "";
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

// POST JSON and parse the reply SAFELY.
// php.ini has display_errors=On, so a single PHP warning can glue itself onto the front of
// the JSON body and make res.json() throw. Reading the raw text shows us what really came back.
async function postJSON(url, payload) {
    const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (err) {
        throw new Error("Server sent a non-JSON reply: " + text.slice(0, 200));
    }
}

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
                <td class="fw-bold">${esc(u.username)}</td>
                <td>${esc(u.email) || 'N/A'}</td>
                <td><span class="badge bg-secondary">${esc(u.role)}</span></td>
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
        inventoryCache = result.data;
        let lowStockCount = 0;
        tbody.innerHTML = result.data.map(p => {
            const isLow = p.stock_qty <= p.reorder_level;
            if (isLow) lowStockCount++;

            return `
                <tr>
                    <td><code>${esc(p.barcode)}</code></td>
                    <td class="fw-bold">${esc(p.name)}</td>
                    <td>${esc(p.supplier_name) || 'N/A'}</td>
                    <td>₱${parseFloat(p.price).toFixed(2)}</td>
                    <td>
                        <span class="badge ${isLow ? 'bg-warning text-dark' : 'bg-success'}">
                            ${p.stock_qty} units
                        </span>
                    </td>
                    <td>
                        <span class="badge ${p.is_active == 1 ? 'bg-success' : 'bg-danger'} me-1">${p.is_active == 1 ? 'Active' : 'Inactive'}</span>
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="openEditModal(${p.id})">Edit</button>
                        <button class="btn btn-sm btn-outline-danger me-1" onclick="toggleProduct(${p.id})">
                            ${p.is_active == 1 ? 'Deactivate' : 'Activate'}
                        </button>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="restockProduct(${p.id}, 10)">+10 Stock</button>
                        ${p.is_active == 1 ? `<button class="btn btn-sm btn-success" onclick="addCartItem(${p.id})">+ POS</button>` : ""}
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
    const val = id => document.getElementById(id).value;
    const num = v => (v === "" ? "" : Number(v));  // "" stays "" (contract: error), never Number("")=0

    const productId = val("mProductId");
    const url = productId
        ? "../api/pos_system_api.php?action=update_product"
        : "../api/pos_system_api.php?action=add_product";

    // update_product sends the full locked contract; add_product keeps its original keys
    const payload = productId
        ? {
            product_id: Number(productId),
            barcode: val("mSku"),
            name: val("mName"),
            category: val("mCategory"),
            price: num(val("mPrice")),
            stock_qty: num(val("mStock")),
            reorder_level: num(val("mReorder")),
            expiration_date: val("mExpiry"),
            supplier_id: val("mSupplier")
        }
        : {
            barcode: val("mSku"),
            name: val("mName"),
            category: val("mCategory"),
            price: num(val("mPrice")),
            stock_qty: num(val("mStock")),
            reorder_level: num(val("mReorder")),
            supplier_id: val("mSupplier")
        };

    await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });

    document.getElementById("mProductId").value = "";
    document.getElementById("mTitle").innerText = "Add Product";
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

async function toggleProduct(productId) {
    await fetch("../api/pos_system_api.php?action=toggle_product", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_id: productId })
    });
    loadInventory();
}

function openEditModal(productId) {
    const p = inventoryCache.find(item => item.id === productId);
    if (!p) return;

    document.getElementById("mProductId").value = p.id;
    document.getElementById("mTitle").innerText = "Edit Product";
    document.getElementById("mSku").value = p.barcode ?? "";
    document.getElementById("mName").value = p.name ?? "";
    document.getElementById("mSupplier").value = p.supplier_id ?? "";
    document.getElementById("mPrice").value = p.price ?? "";
    document.getElementById("mStock").value = p.stock_qty ?? "";
    document.getElementById("mCategory").value = p.category ?? "";
    document.getElementById("mReorder").value = p.reorder_level ?? "";
    document.getElementById("mExpiry").value = p.expiration_date ?? "";

    bootstrap.Modal.getOrCreateInstance(document.getElementById("addProductModal")).show();
}

function resetProductModal() {
    document.getElementById("mProductId").value = "";
    document.getElementById("mTitle").innerText = "Add Product";
    document.getElementById("addProductForm").reset();
}

// 3. Supplier Management
async function loadSuppliers() {
    const res = await fetch("../api/pos_system_api.php?action=get_suppliers");
    const result = await res.json();
    const tbody = document.getElementById("suppliersTableBody");
    const select = document.getElementById("mSupplier");
    if (!tbody) return;

    if (result.status === "success") {
        suppliersCache = result.data;
        tbody.innerHTML = result.data.map(s => `
            <tr>
                <td><code>SUP-${s.id}</code></td>
                <td class="fw-bold">${esc(s.name)}</td>
                <td>${esc(s.email) || 'N/A'}</td>
                <td>${esc(s.phone) || 'N/A'}</td>
                <td>${esc(s.address) || 'N/A'}</td>
                <td>
                    <span class="badge ${s.is_active == 1 ? 'bg-success' : 'bg-danger'}">${s.is_active == 1 ? 'Active' : 'Inactive'}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="openEditSupplierModal(${s.id})">Edit</button>
                    <button class="btn btn-sm btn-outline-danger me-1" onclick="toggleSupplier(${s.id})">
                        ${s.is_active == 1 ? 'Deactivate' : 'Activate'}
                    </button>
                </td>
            </tr>
        `).join("");

        if (select) {
            // Keep DEACTIVATED suppliers in the list (marked) on purpose: if they were filtered
            // out, editing a product that points at one would silently blank its supplier_id.
            select.innerHTML = `<option value="">Select Supplier</option>` +
                result.data.map(s => `<option value="${s.id}">${esc(s.name)}${s.is_active == 1 ? '' : ' (Inactive)'}</option>`).join("");
        }
    }
}

async function toggleSupplier(supplierId) {
    await fetch("../api/pos_system_api.php?action=toggle_supplier", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ supplier_id: supplierId })
    });
    loadSuppliers();
}

async function handleAddSupplier(e) {
    e.preventDefault();
    const val = id => document.getElementById(id).value.trim();

    const payload = {
        name: val("supName"),
        contact_person: val("supContact"),
        email: val("supEmail"),
        phone: val("supPhone"),
        address: val("supAddress")
    };

    await fetch("../api/pos_system_api.php?action=add_supplier", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    loadSuppliers();
}

// --- Edit Supplier (M4 #2 step 4) ---
// CONTRACT (locked with the backend): POST update_supplier with
// {supplier_id, name, contact_person, phone, email, address} -> {status:'success'|'error', message}
function openEditSupplierModal(supplierId) {
    const s = suppliersCache.find(item => item.id === supplierId);
    if (!s) return;

    document.getElementById("mSupplierId").value = s.id;
    document.getElementById("mSupplierTitle").innerText = "Edit Supplier";
    document.getElementById("mSupName").value = s.name ?? "";
    document.getElementById("mSupContact").value = s.contact_person ?? "";
    document.getElementById("mSupEmail").value = s.email ?? "";
    document.getElementById("mSupPhone").value = s.phone ?? "";
    document.getElementById("mSupAddress").value = s.address ?? "";

    bootstrap.Modal.getOrCreateInstance(document.getElementById("editSupplierModal")).show();
}

async function handleEditSupplier(e) {
    e.preventDefault();
    const val = id => document.getElementById(id).value.trim();

    const payload = {
        supplier_id: Number(val("mSupplierId")),
        name: val("mSupName"),
        contact_person: val("mSupContact"),
        phone: val("mSupPhone"),
        email: val("mSupEmail"),
        address: val("mSupAddress")
    };

    try {
        const result = await postJSON("../api/pos_system_api.php?action=update_supplier", payload);

        if (result.status === "success") {
            document.getElementById("mSupplierId").value = "";
            document.getElementById("mSupplierTitle").innerText = "Edit Supplier";
            bootstrap.Modal.getOrCreateInstance(document.getElementById("editSupplierModal")).hide();
            loadSuppliers();
        } else {
            alert("Error: " + result.message);
        }
    } catch (err) {
        alert(err.message);
    }
}

// 4. POS / Sales Management
function addCartItem(productId) {
    const product = inventoryCache.find(p => p.id === productId);
    if (!product) return;

    const existing = posCart.find(item => item.product_id === productId);
    if (existing) {
        existing.quantity += 1;
    } else {
        posCart.push({ product_id: productId, name: product.name, price: parseFloat(product.price), quantity: 1 });
    }
    renderPOSCart();
}

function renderPOSCart() {
    const tbody = document.getElementById("posCartBody");
    if (!tbody) return;

    if (posCart.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">Cart is empty</td></tr>`;
        document.getElementById("posSubtotal").innerText = "₱0.00";
        document.getElementById("posTax").innerText = "₱0.00";
        document.getElementById("posTotal").innerText = "₱0.00";
        return;
    }

    let subtotal = 0;
    tbody.innerHTML = posCart.map(item => {
        const lineTotal = item.price * item.quantity;
        subtotal += lineTotal;
        return `
            <tr>
                <td>${esc(item.name)}</td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-end">₱${item.price.toFixed(2)}</td>
                <td class="text-end fw-bold">₱${lineTotal.toFixed(2)}</td>
            </tr>
        `;
    }).join("");

    const tax = subtotal * 0.08;
    const total = subtotal + tax;

    document.getElementById("posSubtotal").innerText = `₱${subtotal.toFixed(2)}`;
    document.getElementById("posTax").innerText = `₱${tax.toFixed(2)}`;
    document.getElementById("posTotal").innerText = `₱${total.toFixed(2)}`;
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
