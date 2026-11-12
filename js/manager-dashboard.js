// Manager dashboard: today's sales, stock counts and recent transactions.

document.addEventListener("DOMContentLoaded", () => {
  loadManagerStats();
  loadManagerAlerts();
});

async function loadManagerStats() {
  try {
    const res = await fetch("../api/pos_system_api.php?action=manager_stats");
    const result = await res.json();

    if (result.status !== "success") {
      console.error("manager_stats failed:", result);
      return;
    }

    const d = result.data;

    // SUM() is NULL with no sales; PDO returns decimals as strings.
    setText("statSales", "₱" + Number(d.todays_sales || 0).toFixed(2));

    setText("statLowStock", Number(d.low_stock || 0));
    setText("statProducts", Number(d.products || 0));
    setText("statEmployees", Number(d.employees || 0));

    renderRecentTransactions(d.recent_transactions);
  } catch (err) {
    console.error("Failed to load manager stats:", err);
  }
}

async function loadManagerAlerts() {
  const lowList = document.getElementById("managerLowStock");
  const expList = document.getElementById("managerExpiring");
  if (!lowList || !expList) return;

  try {
    const res = await fetch("../api/pos_system_api.php?action=get_inventory_alerts");
    const result = await res.json();
    if (result.status !== "success") return;

    const lowStock = result.data.low_stock || [];
    const expiring = result.data.expiring || [];

    lowList.innerHTML = lowStock.length
      ? lowStock.map(p => `
          <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span>${esc(p.name)}</span>
            <span class="badge bg-warning text-dark">${p.stock_qty} left</span>
          </li>`).join("")
      : '<li class="list-group-item px-0 text-center text-muted small py-3"><i class="bi bi-check2-circle d-block fs-5 mb-1"></i>Nothing here yet</li>';

    expList.innerHTML = expiring.length
      ? expiring.map(p => {
          const days = Number(p.days_left);
          const badge = days < 0 ? "bg-danger" : "bg-warning text-dark";
          const label = days < 0 ? "Expired" : days + "d left";
          return `
          <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span>${esc(p.name)}</span>
            <span class="badge ${badge}">${label}</span>
          </li>`;
        }).join("")
      : '<li class="list-group-item px-0 text-center text-muted small py-3"><i class="bi bi-check2-circle d-block fs-5 mb-1"></i>Nothing here yet</li>';
  } catch (err) {
    console.error("Failed to load inventory alerts:", err);
  }
}

function renderRecentTransactions(rows) {
  const tbody = document.getElementById("recentTransactionsBody");
  if (!tbody) return;

  if (!rows || rows.length === 0) {
    tbody.innerHTML =
      `<tr><td colspan="5" class="text-center text-muted py-4">No transactions recorded yet.</td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(t => `
    <tr>
      <td><code>#${t.id}</code></td>
      <td>${esc(formatDate(t.created_at))}</td>
      <td>${esc(t.cashier || "—")}</td>
      <td><span class="badge bg-secondary">${esc(t.payment_method)}</span></td>
      <td class="text-end fw-bold">₱${Number(t.total || 0).toFixed(2)}</td>
    </tr>
  `).join("");
}

// Format a MySQL timestamp for display.
function formatDate(value) {
  if (!value) return "—";
  const d = new Date(String(value).replace(" ", "T"));
  return isNaN(d)
    ? String(value)
    : d.toLocaleString(undefined, { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

// Escape text before inserting it into HTML.
function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, c => (
    { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]
  ));
}
