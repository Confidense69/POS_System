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
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, email) VALUES (:username, :hash, :full_name, :role, :email)");
        $stmt->execute([
            ':username'  => $data['username'],
            ':hash'      => password_hash($data['password'] ?? 'password123', PASSWORD_DEFAULT),
            ':full_name' => $data['full_name'],
            ':role'      => $data['role'],
            ':email'     => $data['email'] ?? null
        ]);
        echo json_encode(['status' => 'success', 'message' => 'User created successfully']);
        break;

    case 'toggle_user':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = :id");
        $stmt->execute([':id' => $data['user_id']]);
        echo json_encode(['status' => 'success', 'message' => 'Status toggled']);
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
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO products (barcode, name, category, price, stock_qty, reorder_level, supplier_id) VALUES (:barcode, :name, :category, :price, :stock, :reorder, :supplier)");
        $stmt->execute([
            ':barcode'  => $data['barcode'],
            ':name'     => $data['name'],
            ':category' => $data['category'] ?? 'General',
            ':price'    => $data['price'],
            ':stock'    => $data['stock_qty'],
            ':reorder'  => $data['reorder_level'] ?? 5,
            ':supplier' => $data['supplier_id'] ?: null
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Product saved successfully']);
        break;

    case 'restock_product':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :id");
        $stmt->execute([':qty' => $data['quantity'], ':id' => $data['product_id']]);
        
        $logStmt = $pdo->prepare("INSERT INTO restock_log (product_id, quantity, note) VALUES (:id, :qty, :note)");
        $logStmt->execute([':id' => $data['product_id'], ':qty' => $data['quantity'], ':note' => 'Restocked via admin UI']);
        
        echo json_encode(['status' => 'success', 'message' => 'Restocked successfully']);
        break;

    // ----------------------------------------------------
    // 3. SUPPLIER MANAGEMENT
    // ----------------------------------------------------
    case 'get_suppliers':
        $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'add_supplier':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (:name, :contact, :phone, :email, :address)");
        $stmt->execute([
            ':name'    => $data['name'],
            ':contact' => $data['contact_person'] ?? null,
            ':phone'   => $data['phone'] ?? null,
            ':email'   => $data['email'] ?? null,
            ':address' => $data['address'] ?? null
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Supplier added']);
        break;

    // ----------------------------------------------------
    // 4. TRANSACTION / SALES / POS
    // ----------------------------------------------------
    case 'checkout':
        $data = json_decode(file_get_contents('php://input'), true);
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

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>
