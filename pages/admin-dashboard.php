<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit;
}

$allowed_roles = ['admin'];
$role = $_SESSION['role'] ?? '';

if (!in_array($role, $allowed_roles, true)){
  if (in_array($role, ['admin', 'manager', 'cashier'], true)){
    header('Location: ' . $role . '-dashboard.php');
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
  <title>Admin Dashboard | POS Accounting System</title>
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
      <span class="role-tag align-self-start mb-3"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?></span>
      
      <ul class="nav nav-pills flex-column gap-1" id="dashboardTabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="tab-overview" data-bs-toggle="pill" href="#sec-overview"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-accounts" data-bs-toggle="pill" href="#sec-accounts"><i class="bi bi-people"></i> Users &amp; Accounts</a>
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
          <a class="nav-link" id="tab-payroll" data-bs-toggle="pill" href="#sec-payroll"><i class="bi bi-cash-coin"></i> Payroll</a>
        </li>
        <!-- Reports are not implemented yet -->
        <li class="nav-item"><a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true"><i class="bi bi-graph-up"></i> Reports <span class="badge bg-secondary ms-1">soon</span></a></li>
        <li class="nav-item">
          <a class="nav-link" id="tab-logs" data-bs-toggle="pill" href="#sec-logs"><i class="bi bi-journal-text"></i> System Logs</a>
        </li>
      </ul>
      <a href="../api/logout.php" class="btn btn-outline-light mt-auto btn-sm">Logout</a>
    </aside>

    <!-- Main -->
    <main class="main-content flex-grow-1">
      <nav class="navbar navbar-custom px-4 py-3">
        <h1 class="h5 mb-0 fw-bold">Enterprise Management Dashboard</h1>
        <span class="text-muted small">Logged in as <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></strong></span>
      </nav>

      <div class="p-4 tab-content" id="dashboardTabsContent">

        <!-- OVERVIEW TAB -->
        <div class="tab-pane fade show active" id="sec-overview">
          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Low Stock Items</div><div class="h5 mb-0 text-warning" id="statLowStock">0</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Total Products</div><div class="h5 mb-0" id="statProducts">0</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">System Status</div><div class="h5 mb-0 text-success">Connected</div></div></div>
            </div>
            <div class="col-md-3">
              <div class="card stat-card">
                <div class="card-body">
                  <div class="text-muted small mb-1">VAT Rate</div>
                  <div class="d-flex gap-2">
                    <select id="vatRateSelect" class="form-select form-select-sm">
                      <option value="12">12%</option>
                      <option value="16">16%</option>
                      <option value="20">20%</option>
                    </select>
                    <button id="saveVatRateBtn" class="btn btn-primary btn-sm" type="button">Save</button>
                  </div>
                  <div class="small text-muted mt-1" id="vatRateStatus">Applied to every new sale.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-6">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                  <i class="bi bi-exclamation-triangle text-warning me-2"></i>Low Stock
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <thead class="table-light">
                        <tr><th>Product</th><th class="text-end">Stock</th><th class="text-end">Reorder At</th></tr>
                      </thead>
                      <tbody id="lowStockBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                  <i class="bi bi-calendar-x text-danger me-2"></i>Expiring Within 30 Days
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <thead class="table-light">
                        <tr><th>Product</th><th>Expiry</th><th class="text-end">Days Left</th></tr>
                      </thead>
                      <tbody id="expiringBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 1. ACCOUNTS TAB -->
        <div class="tab-pane fade" id="sec-accounts">
          <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 class="mb-0 fw-bold"><i class="bi bi-people text-primary me-2"></i>Account Management</h5>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">+ Add User</button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr>
                  </thead>
                  <tbody id="accountsTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- 2. INVENTORY TAB -->
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

        <!-- 3. SUPPLIERS TAB -->
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

        <!-- 4. POS / TRANSACTIONS TAB -->
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

        <!-- 5. PAYROLL TAB -->
        <div class="tab-pane fade" id="sec-payroll">
          <div class="row g-3">
            <div class="col-lg-7">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                  <h5 class="mb-0 fw-bold"><i class="bi bi-people text-primary me-2"></i>Employees</h5>
                  <button class="btn btn-primary btn-sm" type="button" onclick="resetEmployeeModal()" data-bs-toggle="modal" data-bs-target="#employeeModal">+ Add Employee</button>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                      <thead class="table-light">
                        <tr><th>Name</th><th>Role</th><th class="text-end">Hourly Rate</th><th class="text-end">Action</th></tr>
                      </thead>
                      <tbody id="employeesTableBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-5">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin text-success me-2"></i>Run Payroll</div>
                <div class="card-body">
                  <form id="payrollForm">
                    <div class="mb-2">
                      <label class="form-label">Employee</label>
                      <select id="payEmployee" class="form-select" required></select>
                    </div>
                    <div class="row g-2 mb-2">
                      <div class="col-6"><label class="form-label">Period Start</label><input type="date" id="payStart" class="form-control" required></div>
                      <div class="col-6"><label class="form-label">Period End</label><input type="date" id="payEnd" class="form-control" required></div>
                    </div>
                    <div class="row g-2 mb-2">
                      <div class="col-6"><label class="form-label">Hours Worked</label><input type="number" step="0.25" min="0" id="payHours" class="form-control" required></div>
                      <div class="col-6"><label class="form-label">Deductions (&#8369;)</label><input type="number" step="0.01" min="0" id="payDeductions" class="form-control" value="0"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-3">
                      <span>Gross: <strong id="payGross">&#8369;0.00</strong></span>
                      <span>Net: <strong id="payNet">&#8369;0.00</strong></span>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save Payroll</button>
                    <div class="small mt-2" id="payrollStatus"></div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text text-primary me-2"></i>Payroll History</h5>
              <button class="btn btn-outline-secondary btn-sm" id="refreshPayrollBtn" type="button">Refresh</button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr><th>Employee</th><th>Period</th><th class="text-end">Hours</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net Pay</th><th>Paid</th></tr>
                  </thead>
                  <tbody id="payrollTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- 6. SYSTEM LOGS TAB -->
        <div class="tab-pane fade" id="sec-logs">
          <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text text-primary me-2"></i>System Logs</h5>
              <button class="btn btn-outline-secondary btn-sm" id="refreshLogsBtn" type="button">Refresh</button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr>
                  </thead>
                  <tbody id="logsTableBody"></tbody>
                </table>
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

  <!-- Modal: Add User -->
  <div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="addAccountForm">
          <div class="modal-header"><h5 class="modal-title">Create User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-2"><label class="form-label">Username</label><input type="text" id="accUsername" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Full Name</label><input type="text" id="accFullName" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Email</label><input type="email" id="accEmail" class="form-control"></div>
            <div class="mb-2">
              <label class="form-label">Role</label>
              <select id="accRole" class="form-select">
                <option value="cashier">Cashier</option>
                <option value="manager">Manager</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <div class="modal-footer"><button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Create Account</button></div>
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

  <!-- Modal: Add / Edit Employee -->
  <div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="employeeForm">
          <div class="modal-header"><h5 class="modal-title" id="employeeTitle">Add Employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" id="empId" value="">
            <div class="row g-2 mb-2">
              <div class="col-6"><label class="form-label">First Name</label><input type="text" id="empFirst" class="form-control" required></div>
              <div class="col-6"><label class="form-label">Last Name</label><input type="text" id="empLast" class="form-control" required></div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-6"><label class="form-label">Role</label><input type="text" id="empRole" class="form-control" placeholder="e.g. Cashier"></div>
              <div class="col-6"><label class="form-label">Hourly Rate (&#8369;)</label><input type="number" step="0.01" min="0" id="empRate" class="form-control" required></div>
            </div>
          </div>
          <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Employee</button></div>
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
</body>
</html>
