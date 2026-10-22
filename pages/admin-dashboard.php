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
<!-- ==========================================================================
     NOTE: base markup is the groupmate's working admin dashboard.
     Your OWN @M3 markers and intended structure are preserved below:
       - session guard  -> done by the PHP block above
       - echo role      -> sidebar .role-tag
       - echo full_name -> navbar "Logged in as"
       - logout link    -> ../api/logout.php
       - extra sidebar links (Payroll / Reports / System Logs) kept as disabled TODOs
     The merge mapping lived in groupmate/README.md; that folder was removed in commit bab03f8
     (recover it from git history if ever needed).
     ========================================================================== -->
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
      <span class="role-tag align-self-start mb-3"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?><!-- @M3: echo role --></span>
      
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
        <!-- @M3/TODO: links from YOUR original shell -- not implemented yet -->
        <li class="nav-item"><a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true"><i class="bi bi-cash-coin"></i> Payroll <span class="badge bg-secondary ms-1">soon</span></a></li>
        <li class="nav-item"><a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true"><i class="bi bi-graph-up"></i> Reports <span class="badge bg-secondary ms-1">soon</span></a></li>
        <li class="nav-item"><a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true"><i class="bi bi-journal-text"></i> System Logs <span class="badge bg-secondary ms-1">soon</span></a></li>
      </ul>
      <!-- @M3: logout link -->
      <a href="../api/logout.php" class="btn btn-outline-light mt-auto btn-sm">Logout</a>
    </aside>

    <!-- Main -->
    <main class="main-content flex-grow-1">
      <nav class="navbar navbar-custom px-4 py-3">
        <h1 class="h5 mb-0 fw-bold">Enterprise Management Dashboard</h1>
        <span class="text-muted small">Logged in as <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?><!-- @M3: echo full_name --></strong></span>
      </nav>

      <div class="p-4 tab-content" id="dashboardTabsContent">

        <!-- OVERVIEW TAB -->
        <div class="tab-pane fade show active" id="sec-overview">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Low Stock Items</div><div class="h5 mb-0 text-warning" id="statLowStock">0</div></div></div>
            </div>
            <div class="col-md-4">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">Total Products</div><div class="h5 mb-0" id="statProducts">0</div></div></div>
            </div>
            <div class="col-md-4">
              <div class="card stat-card"><div class="card-body"><div class="text-muted small">System Status</div><div class="h5 mb-0 text-success">Connected</div></div></div>
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
                    <tr><th>SKU</th><th>Product Name</th><th>Supplier</th><th>Price</th><th>Stock</th><th>Action</th></tr>
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
          <div class="row g-3">
            <div class="col-lg-8">
              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">POS Register</div>
                <div class="card-body">
                  <table class="table table-hover mb-0">
                    <thead class="table-light">
                      <tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Line Total</th></tr>
                    </thead>
                    <tbody id="posCartBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Payment Summary</div>
                <div class="card-body">
                  <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span id="posSubtotal">&#8369;0.00</span></div>
                  <div class="d-flex justify-content-between mb-3"><span class="text-muted">Tax (8%)</span><span id="posTax">&#8369;0.00</span></div>
                  <hr>
                  <div class="d-flex justify-content-between mb-3"><span class="fw-bold">TOTAL</span><span class="fw-bold h5 text-primary mb-0" id="posTotal">&#8369;0.00</span></div>
                  <button class="btn btn-success w-100 btn-lg" id="posCheckoutBtn">Checkout &amp; Deduct Stock</button>
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

  <!-- Modal: Edit Supplier (M4 #2 step 4) -->
  <!-- maxlength mirrors the REAL column widths (SHOW COLUMNS suppliers):
       name 100, contact_person 100, email 100, phone 30, address 255.
       This stops the silent truncation before it reaches the API. -->
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



















  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/admin-system.js"></script>
</body>
</html>
