<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tax_engine.php';
session_start();

// Reject requests without a logged-in session.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];

/** Send a JSON error response and stop. */
function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

/** VAT rates the admin is allowed to choose from (percent). */
const ALLOWED_VAT_RATES = [12, 16, 20];

/** Read the VAT rate from settings, as a decimal (12 -> 0.12). */
function current_vat_rate(PDO $pdo): float
{
    try {
        $value = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vat_rate'")->fetchColumn();
    } catch (Throwable $e) {
        return DEFAULT_VAT_RATE;
    }

    $percent = is_numeric($value) ? (float)$value : 0.0;
    if (!in_array((int)$percent, ALLOWED_VAT_RATES, true)) {
        return DEFAULT_VAT_RATE;
    }

    return $percent / 100;
}

try{
    switch ($action) {

    // ----------------------------------------------------
    // 0. STORE SETTINGS
    // ----------------------------------------------------
    case 'get_settings':
        echo json_encode([
            'status' => 'success',
            'data'   => [
                'vat_rate'          => (int)round(current_vat_rate($pdo) * 100),
                'allowed_vat_rates' => ALLOWED_VAT_RATES,
            ],
        ]);
        break;

    case 'update_settings':
        // Store-wide config is an admin-only action.
        if (($_SESSION['role'] ?? '') !== 'admin') {
            json_error('Only an admin can change store settings', 403);
        }

        $vatRate = filter_var($data['vat_rate'] ?? null, FILTER_VALIDATE_INT);
        if ($vatRate === false || !in_array($vatRate, ALLOWED_VAT_RATES, true)) {
            json_error('VAT rate must be one of 12, 16 or 20');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES ('vat_rate', :value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute([':value' => number_format($vatRate, 2, '.', '')]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'VAT rate updated',
            'data'    => ['vat_rate' => $vatRate],
        ]);
        break;

    // ----------------------------------------------------
    // 1. ACCOUNT MANAGEMENT
    // ----------------------------------------------------
    case 'get_users':
        $stmt = $pdo->query("SELECT id, username, full_name, role, email, is_active FROM users ORDER BY id DESC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'add_user':
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, email) VALUES (:username, :hash, :full_name, :role, :email)");
        $stmt->execute([
            ':username'  => $data['username'],
            ':hash'      => password_hash($data['password'] ?? 'password123', PASSWORD_DEFAULT),
            ':full_name' => $data['full_name'],
            ':role'      => $data['role'],
            ':email'     => !empty($data['email']) ? $data['email'] : null
        ]);
        echo json_encode(['status' => 'success', 'message' => 'User created successfully']);
        break;

    case 'toggle_user':
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = :id");
        $stmt->execute([':id' => $data['user_id']]);
        echo json_encode(['status' => 'success', 'message' => 'User Status toggled']);
        break;

    // ----------------------------------------------------
    // 2. INVENTORY MANAGEMENT
    // ----------------------------------------------------
    case 'get_products':
        $stmt = $pdo->query("
            SELECT p.*, s.name AS supplier_name 
            FROM products p 
            LEFT JOIN suppliers s ON p.supplier_id = s.id 
            ORDER BY p.id DESC
        ");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'get_inventory_alerts':
        $lowStock = $pdo->query("
            SELECT id, name, stock_qty, reorder_level
            FROM products
            WHERE is_active = 1 AND stock_qty <= reorder_level
            ORDER BY stock_qty ASC, name ASC
        ")->fetchAll();

        $expiring = $pdo->query("
            SELECT id, name, expiration_date,
                   DATEDIFF(expiration_date, CURDATE()) AS days_left
            FROM products
            WHERE is_active = 1
              AND expiration_date IS NOT NULL
              AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY expiration_date ASC
        ")->fetchAll();

        echo json_encode([
            'status' => 'success',
            'data' => ['low_stock' => $lowStock, 'expiring' => $expiring]
        ]);
        break;

    case 'search_products':
        // Barcode-or-name lookup for the cashier register.
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            echo json_encode(['status' => 'success', 'data' => []]);
            break;
        }

        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("
            SELECT id, barcode, name, price, stock_qty, is_active
            FROM products
            WHERE is_active = 1
              AND (barcode = ? OR barcode LIKE ? OR name LIKE ?)
            ORDER BY (barcode = ?) DESC, name ASC
            LIMIT 10
        ");
        $stmt->execute([$q, $like, $like, $q]);

        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'add_product':
        if (empty($data['barcode']) || empty($data['name']) || !isset($data['price']) || ($data["price"] === '')) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: barcode, name, price']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO products (barcode, name, category, price, stock_qty, reorder_level, supplier_id, expiration_date) VALUES (:barcode, :name, :category, :price, :stock, :reorder, :supplier, :expiration_date)");
        $stmt->execute([
            ':barcode'  => $data['barcode'],
            ':name'     => $data['name'],
            ':category' => (empty($data['category']) ? null : $data['category']),
            ':price'    => $data['price'],
            ':stock'    => (isset($data['stock_qty']) ? $data['stock_qty'] : 0),
            ':reorder'  => (isset($data['reorder_level']) ? $data['reorder_level'] : 5),
            ':supplier' => (empty($data['supplier_id']) ? null : $data['supplier_id']),
            ':expiration_date' => (empty($data['expiration_date']) ? null : $data['expiration_date'])
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Product saved successfully']);
        break;

    case 'restock_product':
        $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :id");
        $stmt->execute([':qty' => $data['quantity'], ':id' => $data['product_id']]);

        $logStmt = $pdo->prepare("INSERT INTO restock_log (product_id, quantity, note) VALUES (:id, :qty, :note)");
        $logStmt->execute([':id' => $data['product_id'], ':qty' => $data['quantity'], ':note' => 'Restocked via admin UI']);
        
        echo json_encode(['status' => 'success', 'message' => 'Restocked successfully']);
        break;

    case 'toggle_product':
        if (empty($data['product_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing product_id']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = :id");
        $stmt->execute([':id' => $data['product_id']]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            break;
        }
        echo json_encode(['status' => 'success', 'message' => 'Product Status toggled']);
        break;

    case 'update_product':
        if(empty($data['product_id']) || empty($data['barcode']) || empty($data['name']) || !isset($data['price']) || ($data["price"] === '')) {
            echo json_encode(['status' => 'error' , 'message' => 'Missing required fields: barcode, name, price, product_id']);
            break;
        }

        $checkStml = $pdo->prepare("SELECT id FROM products WHERE id = :id");
        $checkStml->execute([':id' => $data['product_id']]);
        if (!$checkStml->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE products SET barcode = :barcode, name = :name, category = :category, price = :price, stock_qty = :stock_qty, reorder_level = :reorder_level, supplier_id = :supplier_id, expiration_date = :expiration_date WHERE id = :id");
        $stmt->execute([
            ':id' => $data['product_id'],
            ':barcode' => $data['barcode'],
            ':name' => $data['name'],
            ':category' => (empty($data['category']) ? null : $data['category']),
            ':price' => $data['price'],
            ':stock_qty' => (isset($data['stock_qty']) ? $data['stock_qty'] : 0),
            ':reorder_level' => (isset($data['reorder_level']) ? $data['reorder_level'] : 5),
            ':supplier_id' => (empty($data['supplier_id']) ? null : $data['supplier_id']),
            ':expiration_date' => (empty($data['expiration_date']) ? null : $data['expiration_date'])
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        break;

    // ----------------------------------------------------
    // 3. SUPPLIER MANAGEMENT
    // ----------------------------------------------------
    case 'get_suppliers':
        $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'add_supplier':
        if (empty($data['name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required field: name']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (:name, :contact, :phone, :email, :address)");
        $stmt->execute([
            ':name'    => $data['name'],
            ':contact' => !empty($data['contact_person']) ? $data['contact_person'] : null,
            ':phone'   => !empty($data['phone']) ? $data['phone'] : null,
            ':email'   => !empty($data['email']) ? $data['email'] : null,
            ':address' => !empty($data['address']) ? $data['address'] : null
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Supplier added']);
        break;

    case 'toggle_supplier':
        if (empty($data['supplier_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing supplier_id']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE suppliers SET is_active = NOT is_active WHERE id = :id");
        $stmt->execute([':id' => $data['supplier_id']]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Supplier not found']);
            break;
        }
        echo json_encode(['status' => 'success', 'message' => 'Supplier Status toggled']);
        break;

    case 'update_supplier':
        if (empty($data['supplier_id']) || empty($data['name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields: supplier_id, name']);
            break;
        }
        $checkStml = $pdo->prepare("SELECT id FROM suppliers WHERE id = :id");
        $checkStml->execute([':id' => $data['supplier_id']]);
        if (!$checkStml->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Supplier not found']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE suppliers SET name = :name, contact_person = :contact, phone = :phone, email = :email, address = :address WHERE id = :id");
        $stmt->execute([
            ':id'      => $data['supplier_id'],
            ':name'    => $data['name'],
            ':contact' => !empty($data['contact_person']) ? $data['contact_person'] : null,
            ':phone'   => !empty($data['phone']) ? $data['phone'] : null,
            ':email'   => !empty($data['email']) ? $data['email'] : null,
            ':address' => !empty($data['address']) ? $data['address'] : null
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Supplier updated']);
        break;
    // ----------------------------------------------------
    // 4. TRANSACTION / SALES / POS
    // ----------------------------------------------------
    case 'checkout':
        $cart = $data['cart'] ?? [];
        $cashierId = $_SESSION['user_id'] ?? null;

        if (!is_array($cart) || count($cart) === 0) {
            json_error('Cart is empty');
        }

        // Tax rate comes from settings; prices are re-read below.
        $taxRate       = current_vat_rate($pdo);
        $customerType  = strtolower(trim((string)($data['customer_type'] ?? 'regular')));
        $idNumber      = trim((string)($data['id_number'] ?? ''));
        $paymentMethod = strtolower(trim((string)($data['payment_method'] ?? 'cash')));

        if (!in_array($customerType, ['regular', 'pwd', 'senior'], true)) {
            $customerType = 'regular';
        }
        if (!in_array($paymentMethod, ['cash', 'gcash', 'maya', 'maribank'], true)) {
            $paymentMethod = 'cash';
        }
        if ($idNumber === '') {
            $idNumber = null;
        }

        // Validate + normalize the cart, merging duplicate product lines.
        $requested = [];
        foreach ($cart as $index => $item) {
            if (!is_array($item) || !array_key_exists('product_id', $item) || !array_key_exists('quantity', $item)) {
                json_error('Invalid cart item at position ' . ($index + 1) . '.');
            }

            $productId = filter_var($item['product_id'], FILTER_VALIDATE_INT);
            $quantity  = filter_var($item['quantity'], FILTER_VALIDATE_INT);

            if ($productId === false || $productId < 1) {
                json_error('Invalid product in cart at position ' . ($index + 1) . '.');
            }
            if ($quantity === false || $quantity < 1) {
                json_error('Quantity must be a whole number of at least 1 (position ' . ($index + 1) . ').');
            }

            $requested[$productId] = ($requested[$productId] ?? 0) + $quantity;
        }

        // Load prices, stock and status from the database.
        $ids          = array_keys($requested);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $productStmt  = $pdo->prepare("SELECT id, name, price, stock_qty, is_active FROM products WHERE id IN ($placeholders)");
        $productStmt->execute($ids);

        $products = [];
        foreach ($productStmt->fetchAll() as $row) {
            $products[(int)$row['id']] = $row;
        }

        $lineItems = [];
        foreach ($requested as $productId => $quantity) {
            if (!isset($products[$productId])) {
                json_error('Product #' . $productId . ' no longer exists.');
            }

            $product = $products[$productId];

            if ((int)$product['is_active'] !== 1) {
                json_error('"' . $product['name'] . '" is no longer available.');
            }
            if ((int)$product['stock_qty'] < $quantity) {
                json_error('Not enough stock for "' . $product['name'] . '" (available: ' . (int)$product['stock_qty'] . ').');
            }

            $lineItems[] = [
                'product_id' => $productId,
                'name'       => $product['name'],
                'price'      => (float)$product['price'],
                'quantity'   => $quantity,
            ];
        }

        try {
            $totals = calculate_tax_and_totals($lineItems, $taxRate, $customerType, $idNumber);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage());
        }

        $pdo->beginTransaction();
        try {
            // Save the transaction with its VAT breakdown.
            $txnStmt = $pdo->prepare("INSERT INTO transactions (cashier_id, customer_type, discount_id, tax_rate, subtotal, vatable_sales, vat_exempt_sales, discount_amt, tax_amt, total, payment_method) VALUES (:cashier, :customer_type, :discount_id, :tax_rate, :subtotal, :vatable, :vat_exempt, :discount, :tax, :total, :payment_method)");
            $txnStmt->execute([
                ':cashier'        => $cashierId,
                ':customer_type'  => $totals['customer_type'],
                ':discount_id'    => $totals['discount_id'],
                ':tax_rate'       => $totals['tax_rate_applied'],
                ':subtotal'       => $totals['gross_subtotal'],
                ':vatable'        => $totals['vatable_sales'],
                ':vat_exempt'     => $totals['vat_exempt_sales'],
                ':discount'       => $totals['discount_amount'],
                ':tax'            => $totals['vat_amount'],
                ':total'          => $totals['net_total'],
                ':payment_method' => $paymentMethod
            ]);
            $txnId = $pdo->lastInsertId();

            // Save items and deduct stock.
            $itemStmt  = $pdo->prepare("INSERT INTO transaction_items (transaction_id, product_id, quantity, unit_price, line_total) VALUES (:tx_id, :prod_id, :qty, :price, :line_total)");
            $stockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND is_active = 1 AND stock_qty >= ?");

            foreach ($lineItems as $item) {
                $itemStmt->execute([
                    ':tx_id'      => $txnId,
                    ':prod_id'    => $item['product_id'],
                    ':qty'        => $item['quantity'],
                    ':price'      => $item['price'],
                    ':line_total' => round($item['price'] * $item['quantity'], 2)
                ]);

                $stockStmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                if ($stockStmt->rowCount() === 0) {
                    // Another sale changed the stock between validation and now.
                    throw new RuntimeException('Stock for "' . $item['name'] . '" changed during checkout. Please try again.');
                }
            }

            $pdo->commit();

            // Use the stored timestamp for the receipt.
            $createdStmt = $pdo->prepare("SELECT created_at FROM transactions WHERE id = :id");
            $createdStmt->execute([':id' => $txnId]);
            $createdAt = $createdStmt->fetchColumn();

            // Fields used by the receipt.
            echo json_encode([
                'status'         => 'success',
                'message'        => 'Checkout complete!',
                'transaction_id' => (int)$txnId,
                'created_at'     => $createdAt,
                'cashier'        => $_SESSION['full_name'] ?? 'Cashier',
                'payment_method' => $paymentMethod,
                'customer_type'  => $totals['customer_type'],
                'discount_id'    => $totals['discount_id'],
                'items'          => array_map(function (array $item): array {
                    return [
                        'name'       => $item['name'],
                        'quantity'   => $item['quantity'],
                        'unit_price' => $item['price'],
                        'line_total' => round($item['price'] * $item['quantity'], 2),
                    ];
                }, $lineItems),
                'summary'        => $totals,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
        break;

    case 'get_transactions':
        // Sales history. Cashiers are always limited to their own sales.
        $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $role  = $_SESSION['role'] ?? 'cashier';
        $scope = (string)($_GET['scope'] ?? 'all');
        $today = (string)($_GET['today'] ?? '') === '1';

        $where  = [];
        $params = [];

        if ($role === 'cashier' || $scope === 'mine') {
            $where[]            = 't.cashier_id = :cashier_id';
            $params[':cashier_id'] = $_SESSION['user_id'];
        }
        if ($today) {
            $where[] = 'DATE(t.created_at) = CURDATE()';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT t.id, t.created_at, t.cashier_id, u.full_name AS cashier,
                   t.customer_type, t.discount_id, t.tax_rate, t.subtotal,
                   t.vatable_sales, t.vat_exempt_sales, t.discount_amt, t.tax_amt,
                   t.total, t.payment_method, t.is_voided,
                   v.full_name AS voided_by_name
            FROM transactions t
            LEFT JOIN users u ON t.cashier_id = u.id
            LEFT JOIN users v ON t.voided_by = v.id
            $whereSql
            ORDER BY t.id DESC
            LIMIT $limit
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Attach line items in a single query.
        if ($rows) {
            $ids          = array_column($rows, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $itemStmt     = $pdo->prepare("
                SELECT ti.transaction_id, ti.product_id, ti.quantity,
                       ti.unit_price, ti.line_total, COALESCE(p.name, 'Deleted product') AS name
                FROM transaction_items ti
                LEFT JOIN products p ON ti.product_id = p.id
                WHERE ti.transaction_id IN ($placeholders)
                ORDER BY ti.id
            ");
            $itemStmt->execute($ids);

            $itemsByTxn = [];
            foreach ($itemStmt->fetchAll() as $item) {
                $itemsByTxn[(int)$item['transaction_id']][] = $item;
            }

            foreach ($rows as &$row) {
                $row['items'] = $itemsByTxn[(int)$row['id']] ?? [];
            }
            unset($row);
        }

        echo json_encode(['status' => 'success', 'data' => $rows]);
        break;

    case 'cashier_stats':
        // Today's numbers for the logged-in cashier (voided sales excluded).
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(t.total), 0) AS todays_sales,
                   COUNT(*) AS transactions,
                   COALESCE((
                       SELECT SUM(ti.quantity)
                       FROM transaction_items ti
                       JOIN transactions t2 ON ti.transaction_id = t2.id
                       WHERE t2.cashier_id = :cashier_items
                         AND DATE(t2.created_at) = CURDATE()
                         AND t2.is_voided = 0
                   ), 0) AS items_scanned
            FROM transactions t
            WHERE t.cashier_id = :cashier_sales
              AND DATE(t.created_at) = CURDATE()
              AND t.is_voided = 0
        ");
        $stmt->execute([
            ':cashier_items' => $_SESSION['user_id'],
            ':cashier_sales' => $_SESSION['user_id'],
        ]);

        echo json_encode(['status' => 'success', 'data' => $stmt->fetch()]);
        break;

    case 'void_transaction':
        $transactionId = filter_var($data['transaction_id'] ?? null, FILTER_VALIDATE_INT);
        if ($transactionId === false || $transactionId < 1) {
            json_error('Missing transaction_id');
        }

        $stmt = $pdo->prepare("SELECT id, cashier_id, is_voided, DATE(created_at) AS sale_date FROM transactions WHERE id = :id");
        $stmt->execute([':id' => $transactionId]);
        $txn = $stmt->fetch();

        if (!$txn) {
            json_error('Transaction not found', 404);
        }
        if ((int)$txn['is_voided'] === 1) {
            json_error('Transaction is already voided');
        }

        // Admin/manager: any sale. Cashier: own sale from today.
        if (($_SESSION['role'] ?? 'cashier') === 'cashier') {
            if ((int)$txn['cashier_id'] !== (int)$_SESSION['user_id']) {
                json_error('You can only void your own sales', 403);
            }
            if ($txn['sale_date'] !== date('Y-m-d')) {
                json_error('Cashiers can only void sales from today. Ask a manager.', 403);
            }
        }

        $pdo->beginTransaction();
        try {
            // Guard against two voids at once.
            $voidStmt = $pdo->prepare("UPDATE transactions SET is_voided = 1, voided_by = :user WHERE id = :id AND is_voided = 0");
            $voidStmt->execute([':user' => $_SESSION['user_id'], ':id' => $transactionId]);
            if ($voidStmt->rowCount() === 0) {
                throw new RuntimeException('Transaction is already voided');
            }

            // Restore stock.
            $itemStmt = $pdo->prepare("SELECT product_id, quantity FROM transaction_items WHERE transaction_id = :id");
            $itemStmt->execute([':id' => $transactionId]);

            $restockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :id");
            foreach ($itemStmt->fetchAll() as $item) {
                if ($item['product_id'] !== null) {
                    $restockStmt->execute([
                        ':qty' => $item['quantity'],
                        ':id'  => $item['product_id'],
                    ]);
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Transaction #' . $transactionId . ' voided']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_error($e->getMessage());
        }
        break;

    case 'manager_stats':
        $stats = $pdo->query("SELECT 
        (SELECT COALESCE(SUM(total), 0) FROM transactions WHERE DATE(created_at) = CURDATE() AND is_voided = 0) AS todays_sales,
        (SELECT COUNT(*) FROM products WHERE stock_qty <= reorder_level AND is_active = 1) AS low_stock,
        (SELECT COUNT(*) FROM products) AS products,
        (SELECT COUNT(*) FROM users) AS employees
        ")->fetch();

        $recent = $pdo->query("SELECT 
        t.id, t.created_at, u.full_name AS cashier,
        t.payment_method, t.total
        FROM transactions t LEFT JOIN users u ON t.cashier_id = u.id 
        WHERE t.is_voided = 0
        ORDER BY t.created_at DESC LIMIT 10
        ")->fetchAll();

        $stats['recent_transactions'] = $recent;

        echo json_encode(['status' => 'success', 'data' => $stats]);
        
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
    }
}

catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}

?>
