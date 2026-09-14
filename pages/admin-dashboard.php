<!-- @M3 PLACE SESSION GUARD HERE: the php block must be the FIRST thing in this file,
     before <!DOCTYPE html>. Guard: session_start(); if not logged in, header() redirect to ../index.html + exit; -->
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
      <span class="role-tag align-self-start mb-3">Admin<!-- @M3: echo $_SESSION['role'] here --></span>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-people"></i> Users &amp; Accounts</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-box-seam"></i> Products / Inventory</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-truck"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-receipt"></i> Transactions</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cash-coin"></i> Payroll</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-graph-up"></i> Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-journal-text"></i> System Logs</a></li>
      </ul>
      <a href="../index.html" class="btn btn-outline-light mt-auto btn-sm">Logout<!-- @M3: point this at api/logout.php --></a>
    </aside>

    <!-- Main -->
    <main class="main-content">
      <nav class="navbar navbar-custom px-4 py-3">
        <h1 class="h5 mb-0 fw-bold">Admin Dashboard</h1>
        <span class="text-muted small">Logged in as <strong>Admin<!-- @M3: echo $_SESSION['full_name'] here --></strong></span>
      </nav>

      <div class="p-4">
        <!-- Stat cards (static placeholders) -->
        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-success-subtle"><i class="bi bi-cash-coin"></i></div>
                <div><div class="text-muted small">Today's Sales</div><div class="h5 mb-0">P0.00</div></div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-warning-subtle"><i class="bi bi-exclamation-triangle"></i></div>
                <div><div class="text-muted small">Low Stock</div><div class="h5 mb-0">0</div></div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-info-subtle"><i class="bi bi-box-seam"></i></div>
                <div><div class="text-muted small">Products</div><div class="h5 mb-0">0</div></div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-danger-subtle"><i class="bi bi-people"></i></div>
                <div><div class="text-muted small">Users</div><div class="h5 mb-0">0</div></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-3">
          <div class="col-lg-8">
            <div class="card">
              <div class="card-header bg-white fw-semibold">Recent Transactions</div>
              <div class="card-body p-0">
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
                  <tbody>
                    <tr><td colspan="5" class="text-center text-muted py-4">No transactions recorded yet.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card">
              <div class="card-header bg-white fw-semibold">Quick Actions</div>
              <div class="card-body d-grid gap-2">
                <button class="btn btn-outline-primary btn-sm text-start" disabled>Add New User</button>
                <button class="btn btn-outline-primary btn-sm text-start" disabled>Add Product</button>
                <button class="btn btn-outline-primary btn-sm text-start" disabled>Restock Item</button>
                <button class="btn btn-outline-primary btn-sm text-start" disabled>Generate Report</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

</body>
</html>