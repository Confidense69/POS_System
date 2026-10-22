<?php
require_once 'config.php';
session_start();

// @M3-style guard: reject unauthenticated API calls (login.php must set $_SESSION['user_id'])
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {

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
            ':email'     => $data['email'] ? $data['email'] : null
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

        if (empty($cart)) {
            echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            $subtotal = 0;
            foreach ($cart as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            $taxAmt = $subtotal * 0.08;
            $total  = $subtotal + $taxAmt;

            // 1. Save Transaction
            $txnStmt = $pdo->prepare("INSERT INTO transactions (cashier_id, subtotal, tax_amt, total, payment_method) VALUES (:cashier, :subtotal, :tax, :total, 'cash')");
            $txnStmt->execute([
                ':cashier'  => $cashierId,
                ':subtotal' => $subtotal,
                ':tax'      => $taxAmt,
                ':total'    => $total
            ]);
            $txnId = $pdo->lastInsertId();

            // 2. Save Items & Deduct Stock
            $itemStmt  = $pdo->prepare("INSERT INTO transaction_items (transaction_id, product_id, quantity, unit_price, line_total) VALUES (:tx_id, :prod_id, :qty, :price, :line_total)");
            $stockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :prod_id");

            foreach ($cart as $item) {
                $lineTotal = $item['price'] * $item['quantity'];
                $itemStmt->execute([
                    ':tx_id'      => $txnId,
                    ':prod_id'    => $item['product_id'],
                    ':qty'        => $item['quantity'],
                    ':price'      => $item['price'],
                    ':line_total' => $lineTotal
                ]);
                $stockStmt->execute([
                    ':qty'     => $item['quantity'],
                    ':prod_id' => $item['product_id']
                ]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Checkout complete!', 'transaction_id' => $txnId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
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
        ORDER BY t.created_at DESC LIMIT 10
        ")->fetchAll();

        $stats['recent_transactions'] = $recent;

        echo json_encode(['status' => 'success', 'data' => $stats]);
        
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>
