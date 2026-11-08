document.addEventListener("DOMContentLoaded", () => {
    loadAccounts();
    loadInventory();
    loadSuppliers();
    loadSettings();
    loadTransactions();

    // Form Submissions
    document.getElementById("addProductForm")?.addEventListener("submit", handleAddProduct);
    document.getElementById("addSupplierForm")?.addEventListener("submit", handleAddSupplier);
    document.getElementById("editSupplierForm")?.addEventListener("submit", handleEditSupplier);
    document.getElementById("addAccountForm")?.addEventListener("submit", handleAddAccount);
    document.getElementById("saveVatRateBtn")?.addEventListener("click", saveVatRate);
    document.getElementById("refreshTransactionsBtn")?.addEventListener("click", loadTransactions);
    document.getElementById("adminPrintReceiptBtn")?.addEventListener("click", () => window.print());
});

let inventoryCache = [];
let suppliersCache = [];
let transactionsCache = [];

// Escape text before inserting it into HTML.
function esc(value) {
    if (value === null || value === undefined) return "";
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

// Parse the response as JSON.
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

// 0. Store settings
async function loadSettings() {
    const select = document.getElementById("vatRateSelect");
    if (!select) return;

    try {
        const res = await fetch("../api/pos_system_api.php?action=get_settings");
        const result = await res.json();
        if (result.status === "success" && result.data) {
            select.value = String(result.data.vat_rate);
        }
    } catch (err) {
        console.error("Could not load settings:", err);
    }
}

async function saveVatRate() {
    const select = document.getElementById("vatRateSelect");
    const status = document.getElementById("vatRateStatus");
    const btn = document.getElementById("saveVatRateBtn");
    if (!select || !status || !btn) return;

    btn.disabled = true;
    try {
        const result = await postJSON("../api/pos_system_api.php?action=update_settings", {
            vat_rate: Number(select.value)
        });

        if (result.status === "success") {
            status.textContent = "Saved. New sales use " + result.data.vat_rate + "% VAT.";
            status.className = "small text-success mt-1";
        } else {
            status.textContent = result.message || "Could not save.";
            status.className = "small text-danger mt-1";
        }
    } catch (err) {
        status.textContent = err.message;
        status.className = "small text-danger mt-1";
    } finally {
        btn.disabled = false;
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
    const num = v => (v === "" ? "" : Number(v));  // keep "" as "" so the API rejects it

    const productId = val("mProductId");
    const url = productId
        ? "../api/pos_system_api.php?action=update_product"
        : "../api/pos_system_api.php?action=add_product";

    // update_product needs product_id; add_product does not.
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
            // Keep deactivated suppliers so editing a product does not clear its supplier.
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

// --- Edit Supplier ---
// POST update_supplier with the supplier fields.
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

// 4. Sales / Transactions history
function money(value) {
    return Number(value || 0).toLocaleString("en-PH", {
        style: "currency",
        currency: "PHP"
    });
}

function formatDate(value) {
    if (!value) return "—";
    const d = new Date(String(value).replace(" ", "T"));
    return isNaN(d)
        ? String(value)
        : d.toLocaleString(undefined, { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
}

async function loadTransactions() {
    const tbody = document.getElementById("transactionsTableBody");
    if (!tbody) return;

    try {
        const res = await fetch("../api/pos_system_api.php?action=get_transactions&limit=100");
        const result = await res.json();

        if (result.status !== "success" || !Array.isArray(result.data)) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Could not load transactions.</td></tr>`;
            return;
        }

        transactionsCache = result.data;

        if (transactionsCache.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No transactions yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = transactionsCache.map(t => {
            const voided = Number(t.is_voided) === 1;
            return `
                <tr class="${voided ? "table-danger" : ""}">
                    <td><code>#${t.id}</code></td>
                    <td>${esc(formatDate(t.created_at))}</td>
                    <td>${esc(t.cashier) || "—"}</td>
                    <td>${esc(t.customer_type)}</td>
                    <td><span class="badge bg-secondary">${esc(t.payment_method)}</span></td>
                    <td class="text-end fw-bold">${money(t.total)}</td>
                    <td>${voided
                        ? `<span class="badge bg-danger">Voided</span>`
                        : `<span class="badge bg-success">Completed</span>`}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="viewTransaction(${t.id})">View</button>
                        ${voided ? "" : `<button class="btn btn-sm btn-outline-danger" onclick="voidTransaction(${t.id})">Void</button>`}
                    </td>
                </tr>
            `;
        }).join("");
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Could not load transactions.</td></tr>`;
        console.error(err);
    }
}

function viewTransaction(transactionId) {
    const txn = transactionsCache.find(t => Number(t.id) === Number(transactionId));
    if (!txn) return;

    const body = document.getElementById("adminReceiptBody");
    if (!body) return;

    const paymentLabels = { cash: "Cash", gcash: "GCash", maya: "Maya", maribank: "MariBank" };
    const customerLabels = { regular: "Regular", senior: "Senior Citizen", pwd: "PWD" };
    const voided = Number(txn.is_voided) === 1;
    const items = txn.items || [];

    body.innerHTML = `
        <div class="receipt-paper">
            <div class="text-center mb-2">
                <div class="fw-bold">POS ACCOUNTING SYSTEM</div>
                <div class="small text-muted">Soft copy receipt</div>
                ${voided ? `<div class="badge bg-danger mt-2">VOIDED</div>` : ""}
            </div>
            <hr class="my-2">
            <div class="small">
                <div class="d-flex justify-content-between"><span>Txn</span><span>#${esc(txn.id)}</span></div>
                <div class="d-flex justify-content-between"><span>Date</span><span>${esc(formatDate(txn.created_at))}</span></div>
                <div class="d-flex justify-content-between"><span>Cashier</span><span>${esc(txn.cashier) || "—"}</span></div>
                <div class="d-flex justify-content-between"><span>Payment</span><span>${esc(paymentLabels[txn.payment_method] || txn.payment_method)}</span></div>
                <div class="d-flex justify-content-between"><span>Customer</span><span>${esc(customerLabels[txn.customer_type] || txn.customer_type)}</span></div>
                ${txn.discount_id ? `<div class="d-flex justify-content-between"><span>ID No.</span><span>${esc(txn.discount_id)}</span></div>` : ""}
                ${voided && txn.voided_by_name ? `<div class="d-flex justify-content-between"><span>Voided by</span><span>${esc(txn.voided_by_name)}</span></div>` : ""}
            </div>
            <hr class="my-2">
            <div class="small">
                ${items.map(item => `
                    <div class="d-flex justify-content-between gap-2">
                        <span>${esc(item.name)} x${item.quantity}</span>
                        <span>${money(item.line_total)}</span>
                    </div>
                `).join("")}
            </div>
            <hr class="my-2">
            <div class="small">
                <div class="d-flex justify-content-between"><span>Subtotal</span><span>${money(txn.subtotal)}</span></div>
                <div class="d-flex justify-content-between"><span>Discount</span><span>-${money(txn.discount_amt)}</span></div>
                <div class="d-flex justify-content-between">
                    <span>VAT ${Number(txn.vat_exempt_sales) > 0 ? "(exempt)" : "(" + Math.round(Number(txn.tax_rate) * 100) + "%)"}</span>
                    <span>${money(txn.tax_amt)}</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold"><span>Total</span><span>${money(txn.total)}</span></div>
            </div>
            <div class="text-center small text-muted mt-3">Thank you for your purchase!</div>
        </div>
    `;

    bootstrap.Modal.getOrCreateInstance(document.getElementById("adminReceiptModal")).show();
}

async function voidTransaction(transactionId) {
    if (!confirm("Void transaction #" + transactionId + "? Stock will be returned to inventory.")) {
        return;
    }

    try {
        const result = await postJSON("../api/pos_system_api.php?action=void_transaction", {
            transaction_id: transactionId
        });

        if (result.status !== "success") {
            alert(result.message || "Could not void this transaction.");
            return;
        }

        loadTransactions();
        loadInventory();
    } catch (err) {
        alert(err.message || "Could not void this transaction.");
    }
}
