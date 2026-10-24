<?php
// @M3 session guard (implemented 10/16): must be the FIRST thing in the file, before <!DOCTYPE html>.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}

$allowed_roles = ['cashier'];
$role = $_SESSION['role'];

if (!in_array($role, $allowed_roles, true)){
  if (in_array($role, ['cashier', 'admin', 'manager'])){
    header('Location: '. $role . '-dashboard.php');
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
      <span class="role-tag align-self-start mb-3"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Cashier'); ?></span>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-cart3"></i> POS / New Sale</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-clock-history"></i> My Transactions</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-arrow-counterclockwise"></i> Void / Refund</a></li>
      </ul>
      <a href="../api/logout.php" class="btn btn-outline-light mt-auto btn-sm">Logout</a>
    </aside>

    <!-- Main -->
    <main class="main-content">
      <nav class="navbar navbar-custom px-4 py-3">
        <h1 class="h5 mb-0 fw-bold">Cashier Dashboard</h1>
        <span class="text-muted small">Logged in as <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Cashier'); ?></strong></span>
      </nav>

      <div class="p-4">
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="card stat-card">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-success-subtle"><i class="bi bi-cash-coin"></i></div>
                <div><div class="text-muted small">Today's Sales</div><div class="h5 mb-0">&#8369;0.00</div></div>
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
                <div class="mb-3">
                  <label class="form-label small text-muted">Customer Type</label>
                  <select id="customerType" class="form-select form-select-sm">
                    <option value="regular">Regular</option>
                    <option value="senior">Senior Citizen</option>
                    <option value="pwd">PWD</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label small text-muted">ID Number (for Senior/PWD)</label>
                  <input id="idNumber" type="text" class="form-control form-control-sm" placeholder="Optional">
                </div>
                <div class="mb-3">
                  <label class="form-label small text-muted">Payment Method</label>
                  <select id="paymentMethod" class="form-select form-select-sm">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="maribank">MariBank</option>
                  </select>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Subtotal</span><span id="subtotalValue">&#8369;0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Discount</span><span id="discountValue">&#8369;0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <span class="text-muted">Tax</span><span id="taxValue">&#8369;0.00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                  <span class="fw-bold">TOTAL</span><span id="totalValue" class="fw-bold">&#8369;0.00</span>
                </div>
                <div class="d-flex gap-2">
                  <button id="checkoutBtn" class="btn btn-success flex-grow-1">Charge / Checkout</button>
                  <button id="clearCartBtn" class="btn btn-outline-danger" type="button">Clear Cart</button>
                </div>

                <div class="card border-0 bg-light mt-3">
                  <div class="card-body p-3">
                    <div class="small text-muted fw-semibold mb-2">Soft Copy Receipt</div>
                    <div id="receiptItems" class="small text-muted"></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between small"><span>Subtotal</span><span id="receiptSubtotal">₱0.00</span></div>
                    <div class="d-flex justify-content-between small"><span>Tax</span><span id="receiptTax">₱0.00</span></div>
                    <div class="d-flex justify-content-between small"><span>Total</span><span id="receiptTotal" class="fw-semibold">₱0.00</span></div>
                  </div>
                </div>

                <div id="qrDisplayWrap" class="mt-3" style="display:none;">
                  <div class="small text-muted fw-semibold mb-2">QR Payment Display</div>
                  <img id="qrImage" class="img-fluid mx-auto d-block border rounded" src="" alt="Selected payment QR code" style="max-width: 220px;">
                </div>

                <div class="d-flex gap-2 justify-content-center mt-3">
                  <span class="badge text-bg-light border">F2 Search</span>
                  <span class="badge text-bg-light border">F3 Void</span>
                  <span class="badge text-bg-light border">F4 Pay</span>
                  <span class="badge text-bg-light border">F5 Clear</span>
                </div>
                <div class="small text-muted text-center mt-2">Payment: 1 Cash · 2 GCash · 3 Maya · 4 MariBank</div>
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