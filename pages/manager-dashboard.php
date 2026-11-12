<?php
// Require a logged-in manager.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}

$allowed_roles = ['manager', 'admin'];
$role = $_SESSION['role'];
if (!in_array($role, $allowed_roles, true)){
  if (in_array($role, ['manager', 'cashier', 'admin'])){
    header('Location: '. $role .'-dashboard.php');
  }else{
    header('Location: ../index.html');
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Dashboard | POS Accounting System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
</head>
<body class="dashboard-body">

  <div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar d-flex flex-column p-3">
      <div class="brand mb-4">
        <div class="brand-badge-sm">POS</div>
        <span>Accounting Sys</span>
      </div>
      <span class="role-tag align-self-start mb-3"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Manager'); ?></span>

      <ul class="nav nav-pills flex-column gap-1" id="dashboardTabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="tab-overview" data-bs-toggle="pill" href="#sec-overview"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-inventory" data-bs-toggle="pill" href="#sec-inventory"><i class="bi bi-box-seam"></i> Products / Inventory</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-suppliers" data-bs-toggle="pill" href="#sec-suppliers"><i class="bi bi-truck"></i> Suppliers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-pos" data-bs-toggle="pill" href="#sec-pos"><i class="bi bi-receipt"></i> POS &amp; Transactions</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-reports" data-bs-toggle="pill" href="#sec-reports"><i class="bi bi-graph-up"></i> Reports</a>
        </li>
      </ul>

      <div class="sidebar-label mt-3 mb-1">Other views</div>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link" href="cashier-dashboard.php"><i class="bi bi-cart3"></i> Cashier / POS</a></li>
      </ul>

      <a href="../api/logout.php" class="btn btn-outline-light mt-auto btn-sm">Logout</a>
    </aside>

    <!-- Main -->
    <main class="main-content flex-grow-1">
      <nav class="navbar navbar-custom px-4 py-3">
        <h1 class="h5 mb-0 fw-bold">Manager Dashboard</h1>
        <span class="text-muted small">Logged in as <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Manager'); ?></strong></span>
      </nav>

      <div class="p-4 tab-content" id="dashboardTabsContent">

        <!-- OVERVIEW TAB -->
        <div class="tab-pane fade show active" id="sec-overview">
          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="card stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="icon bg-success-subtle"><i class="bi bi-cash-coin"></i></div>
                  <div><div class="text-muted small">Today's Sales</div><div class="h5 mb-0" id="statSales">&#8369;0.00</div></div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="icon bg-warning-subtle"><i class="bi bi-exclamation-triangle"></i></div>
                  <div><div class="text-muted small">Low Stock</div><div class="h5 mb-0" id="statLowStock">0</div></div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="icon bg-info-subtle"><i class="bi bi-box-seam"></i></div>
                  <div><div class="text-muted small">Products</div><div class="h5 mb-0" id="statProducts">0</div></div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card">
                <div class="card-body d-flex align-items-center gap-3">
                  <div class="icon bg-secondary-subtle"><i class="bi bi-person-badge"></i></div>
                  <div><div class="text-muted small">Employees</div><div class="h5 mb-0" id="statEmployees">0</div></div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-7">
              <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Recent Transactions</div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>#</th>
                          <th>Date</th>
                          <th>Cashier</th>
                          <th>Method</th>
                          <th class="text-end">Total</th>
                        </tr>
                      </thead>
                      <tbody id="recentTransactionsBody">
                        <tr><td colspan="5" class="text-center text-muted py-4">No transactions recorded yet.</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-5">
              <div class="card h-100">
                <div class="card-header bg-white fw-semibold">
                  <i class="bi bi-exclamation-triangle text-warning me-2"></i>Inventory Alerts
                </div>
                <div class="card-body">
                  <div class="small text-muted mb-1">Low stock</div>
                  <ul class="list-group list-group-flush mb-3" id="managerLowStock"></ul>
                  <div class="small text-muted mb-1">Expiring within 30 days</div>
                  <ul class="list-group list-group-flush" id="managerExpiring"></ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- INVENTORY TAB -->
        <div class="tab-pane fade" id="sec-inventory">
          <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Inventory Management</h5>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal" onclick="resetProductModal()">+ Add Product</button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr><th>SKU</th><th>Product Name</th><th>Supplier</th><th>Price</th><th>Stock</th><th>Expiry</th><th>Action</th></tr>
                  </thead>
                  <tbody id="inventoryTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- SUPPLIERS TAB -->
        <div class="tab-pane fade" id="sec-suppliers">
          <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 class="mb-0 fw-bold"><i class="bi bi-truck text-primary me-2"></i>Supplier Management</h5>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">+ Add Supplier</button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr><th>ID</th><th>Company</th><th>Email</th><th>Phone</th><th>Address</th><th>Status</th><th>Action</th></tr>
                  </thead>
                  <tbody id="suppliersTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- POS / TRANSACTIONS TAB -->
        <div class="tab-pane fade" id="sec-pos">
          <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 class="mb-0 fw-bold"><i class="bi bi-receipt text-primary me-2"></i>Sales &amp; Transactions</h5>
              <button class="btn btn-outline-secondary btn-sm" id="refreshTransactionsBtn" type="button">Refresh</button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Txn</th>
                      <th>Date</th>
                      <th>Cashier</th>
                      <th>Customer</th>
                      <th>Payment</th>
                      <th class="text-end">Total</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="transactionsTableBody">
                    <tr><td colspan="8" class="text-center text-muted py-4">No transactions yet.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- REPORTS TAB -->
        <div class="tab-pane fade" id="sec-reports">
          <div class="card shadow-sm mb-3">
            <div class="card-body">
              <form id="reportFilterForm" class="row g-2 align-items-end">
                <div class="col-auto">
                  <label class="form-label mb-1">From</label>
                  <input type="date" id="reportFrom" class="form-control form-control-sm" required>
                </div>
                <div class="col-auto">
                  <label class="form-label mb-1">To</label>
                  <input type="date" id="reportTo" class="form-control form-control-sm" required>
                </div>
                <div class="col-auto">
                  <button class="btn btn-primary btn-sm" type="submit">Apply</button>
                </div>
              </form>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Revenue</div><div class="h5 mb-0 text-success" id="repRevenue">&#8369;0.00</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Discounts</div><div class="h5 mb-0 text-warning" id="repDiscounts">&#8369;0.00</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Voided Losses</div><div class="h5 mb-0 text-danger" id="repLosses">&#8369;0.00</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Salaries</div><div class="h5 mb-0" id="repSalaries">&#8369;0.00</div></div></div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-7">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Sales by Day</div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                      <thead class="table-light">
                        <tr><th>Date</th><th class="text-end">Transactions</th><th class="text-end">Revenue</th></tr>
                      </thead>
                      <tbody id="reportDailyBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-5">
              <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Income Statement</div>
                <div class="card-body">
                  <div class="d-flex justify-content-between mb-2"><span>Revenue</span><strong id="isRevenue">&#8369;0.00</strong></div>
                  <div class="d-flex justify-content-between mb-2"><span>Salaries</span><strong id="isExpenses">&#8369;0.00</strong></div>
                  <hr>
                  <div class="d-flex justify-content-between"><span class="fw-bold">Net Income</span><strong id="isNet">&#8369;0.00</strong></div>
                </div>
              </div>

              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Sales by Customer Type</div>
                <div class="card-body p-0">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr><th>Type</th><th class="text-end">Sales</th><th class="text-end">Discounts</th></tr>
                    </thead>
                    <tbody id="reportCustomerBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- Modal: Add Product -->
  <div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="addProductForm">
          <div class="modal-header"><h5 class="modal-title" id="mTitle">Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" id="mProductId" value="">
            <div class="mb-2"><label class="form-label">SKU / Barcode</label><input type="text" id="mSku" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Name</label><input type="text" id="mName" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Supplier</label><select id="mSupplier" class="form-select"></select></div>
            <div class="row g-2 mb-2">
              <div class="col-6"><label class="form-label">Price (&#8369;)</label><input type="number" step="0.01" id="mPrice" class="form-control" required></div>
              <div class="col-6"><label class="form-label">Stock Qty</label><input type="number" id="mStock" class="form-control" required></div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6"><label class="form-label">Category</label><input type="text" id="mCategory" class="form-control" placeholder="e.g. Grocery"></div>
              <div class="col-6"><label class="form-label">Reorder Level</label><input type="number" id="mReorder" class="form-control" required></div>
            </div>
            <div class="mb-2"><label class="form-label">Expiration Date</label><input type="date" id="mExpiry" class="form-control"></div>
          </div>
          <div class="modal-footer"><button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Save Item</button></div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Add Supplier -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="addSupplierForm">
          <div class="modal-header"><h5 class="modal-title">Register Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-2"><label class="form-label">Company Name</label><input type="text" id="supName" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Contact Person</label><input type="text" id="supContact" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Email</label><input type="email" id="supEmail" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Phone</label><input type="text" id="supPhone" class="form-control"></div>
            <div class="mb-2"><label class="form-label">Address</label><input type="text" id="supAddress" class="form-control"></div>
          </div>
          <div class="modal-footer"><button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Save Supplier</button></div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Edit Supplier -->
  <!-- maxlength values match the suppliers table columns -->
  <div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="editSupplierForm">
          <div class="modal-header"><h5 class="modal-title" id="mSupplierTitle">Edit Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" id="mSupplierId" value="">
            <div class="mb-2"><label class="form-label">Company Name</label><input type="text" id="mSupName" class="form-control" required maxlength="100"></div>
            <div class="mb-2"><label class="form-label">Contact Person</label><input type="text" id="mSupContact" class="form-control" maxlength="100"></div>
            <div class="mb-2"><label class="form-label">Email</label><input type="email" id="mSupEmail" class="form-control" maxlength="100"></div>
            <div class="mb-2"><label class="form-label">Phone</label><input type="text" id="mSupPhone" class="form-control" maxlength="30"></div>
            <div class="mb-2"><label class="form-label">Address</label><input type="text" id="mSupAddress" class="form-control" maxlength="255"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveSupplierBtn">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: receipt (sales history) -->
  <div class="modal fade" id="adminReceiptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Soft Copy Receipt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="adminReceiptBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="adminPrintReceiptBtn">Print</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/admin-system.js"></script>
  <script src="../js/manager-dashboard.js"></script>
</body>
</html>
