// Manager dashboard — live stats (M4 #1)
//
// Backend contract (agreed 10/19):
//   GET ../api/pos_system_api.php?action=manager_stats
//   -> { "status": "success", "data": {
//          "todays_sales": "123.45", "low_stock": 1, "products": 4, "employees": 3,
//          "recent_transactions": [
//            { "id": 1, "created_at": "2026-10-19 15:18:21",
//              "cashier": "Plaba", "payment_method": "cash", "total": "123.45" }
//          ] } }

document.addEventListener("DOMContentLoaded", () => {
  loadManagerStats();
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

    // Money: SUM() is NULL when nothing sold today, and PDO often hands a
    // decimal back as a string — `|| 0` + Number() normalise both cases.
    setText("statSales", "₱" + Number(d.todays_sales || 0).toFixed(2));

    // Counts: plain integers.
    setText("statLowStock", Number(d.low_stock || 0));
    setText("statProducts", Number(d.products || 0));
    setText("statEmployees", Number(d.employees || 0));

    renderRecentTransactions(d.recent_transactions);
  } catch (err) {
    console.error("Failed to load manager stats:", err);
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

// "2026-10-19 15:18:21" -> "Oct 19, 03:18 PM"
// (the space is swapped for "T" so every browser parses it as local time)
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

// Stop user-supplied text (e.g. a full name) being read as HTML.
function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, c => (
    { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]
  ));
}
