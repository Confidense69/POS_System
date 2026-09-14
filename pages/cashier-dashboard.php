<!-- @M3 PLACE SESSION GUARD HERE: the php block must be the FIRST thing in this file,
     before <!DOCTYPE html>. Guard: session_start(); if not logged in, header() redirect to ../index.html + exit; -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cashier Dashboard | POS Accounting System</title>
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
        <span>Accounting System</span>
      </div>
      <span class="role-tag align-self-start mb-3">Cashier<!-- @M3: echo $_SESSION['role'] here --></span>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-cart3"></i> POS / New Sale</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-clock-history"></i> My Transactions</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-arrow-counterclockwise"></i> Void / Refund</a></li>
      </ul>
      <a href="../index.html" class="btn btn-outline-light mt-auto btn-sm">Logout<!-- @M3: point this at api/logout.php --></a>
    </aside>

    <!-- Main -->
    <main class="main-content">
      <nav class="navbar navbar-custom px-4 py-3">
        <h1 class="h5 mb-0 fw-bold">Cashier Dashboard</h1>
        <span class="text-muted small">Logged in as <strong>Cashier<!-- @M3: echo $_SESSION['full_name'] here --></strong></span>
      </nav>

      <div class="p-4">
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-success-subtle"><i class="bi bi-cash-coin"></i></div>
                <div><div class="text-muted small">Today's Sales</div><div class="h5 mb-0">P0.00</div></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-info-subtle"><i class="bi bi-upc-scan"></i></div>
                <div><div class="text-muted small">Items Scanned</div><div class="h5 mb-0">0</div></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-secondary-subtle"><i class="bi bi-receipt"></i></div>
                <div><div class="text-muted small">Transactions</div><div class="h5 mb-0">0</div></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Point of Sale -->
        <div class="row g-3">
          <div class="col-lg-8">
            <div class="card">
              <div class="card-header bg-white fw-semibold">New Sale</div>
              <div class="card-body">
                <div class="input-group mb-3">
                  <input type="text" class="form-control" placeholder="Type or scan barcode here..." id="barcodeInput"> 
                  <button class="btn btn-primary" id="addItemBtn">Add Item</button>
                </div>
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Item</th>
                      <th class="text-center">Qty</th>
                      <th class="text-end">Price</th>
                      <th class="text-end">Line Total</th>
                    </tr>
                  </thead>
                  <tbody id="salesTableBody">
                    <tr id="emptyRow"><td colspan="4" class="text-center text-muted py-4">No items in this sale.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card">
              <div class="card-header bg-white fw-semibold">Payment Summary</div>
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Subtotal</span><span>P0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Discount</span><span>P0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <span class="text-muted">Tax</span><span>P0.00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                  <span class="fw-bold">TOTAL</span><span class="fw-bold">P0.00</span>
                </div>
                <button class="btn btn-success w-100" disabled>Charge / Checkout</button>
                <div class="d-flex gap-2 justify-content-center mt-3">
                  <span class="badge text-bg-light border">F2 Search</span>
                  <span class="badge text-bg-light border">F3 Void</span>
                  <span class="badge text-bg-light border">F4 Pay</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

<script src="../js/cashier-pos.js"></script>
</body>
</html>