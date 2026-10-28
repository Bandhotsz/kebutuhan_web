<?php
// api_core/order_handler.php
if (!isset($pdo)) exit('Direct access not permitted.');

// ========================
//  ORDERS: CREATE
// ========================
if ($action === 'create_order') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "Harap login terlebih dahulu."]);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['template_id'], $data['payment_method'])) {
        echo json_encode(["success" => false, "message" => "Data pesanan tidak lengkap."]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT price FROM templates WHERE id = ?");
    $stmt->execute([$data['template_id']]);
    $template = $stmt->fetch();
    
    if (!$template) {
        echo json_encode(["success" => false, "message" => "Template tidak ditemukan."]);
        exit();
    }

    $order_id = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $price = $template['price'];
    $method = sanitizeInput($data['payment_method']);
    
    $stmt = $pdo->prepare("INSERT INTO orders (id, user_id, template_id, total, payment_method) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$order_id, $_SESSION['user_id'], $data['template_id'], $price, $method]);

    $res = ["success" => true, "message" => "Pesanan berhasil dibuat.", "order_id" => $order_id];

    // Midtrans Snap Token logic
    if (strpos($method, 'Midtrans') !== false) {
        $userStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        $params = [
            'transaction_details' => ['order_id' => $order_id, 'gross_amount' => (int)$price],
            'customer_details' => ['first_name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']]
        ];

        $serverKey = MIDTRANS_SERVER_KEY;
        $url = MIDTRANS_IS_PRODUCTION ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($serverKey . ':')
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        $snapData = json_decode($result, true);
        if (isset($snapData['token'])) {
            $res['snap_token'] = $snapData['token'];
        }
    }

    echo json_encode($res);
    exit();
}

// ========================
//  ORDERS: UPLOAD PAYMENT (Manual)
// ========================
if ($action === 'upload_payment') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "Harap login terlebih dahulu."]);
        exit();
    }
    if (!isset($_POST['order_id']) || !isset($_FILES['payment_proof'])) {
        echo json_encode(["success" => false, "message" => "Data tidak lengkap."]);
        exit();
    }

    $order_id = sanitizeInput($_POST['order_id']);
    
    $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order || ($order['user_id'] != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin')) {
        echo json_encode(["success" => false, "message" => "Pesanan tidak valid atau bukan milik Anda."]);
        exit();
    }

    $file = $_FILES['payment_proof'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowedTypes) || $file['size'] > 2 * 1024 * 1024) {
        echo json_encode(["success" => false, "message" => "File tidak valid. Harus gambar (JPG/PNG) & max 2MB."]);
        exit();
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $order_id . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $url = 'uploads/' . $filename;
        $pdo->prepare("UPDATE orders SET payment_proof = ?, payment_status = 'Menunggu Verifikasi' WHERE id = ?")
            ->execute([$url, $order_id]);
        echo json_encode(["success" => true, "message" => "Bukti pembayaran berhasil diunggah."]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal mengunggah file."]);
    }
    exit();
}

// ========================
//  ORDERS: GET ORDERS
// ========================
if ($action === 'get_orders') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "Akses ditolak"]);
        exit();
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    
    if ($_SESSION['user_role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT o.*, t.name as template_name, u.name as user_name, u.email as user_email 
                              FROM orders o 
                              JOIN templates t ON o.template_id = t.id 
                              JOIN users u ON o.user_id = u.id 
                              ORDER BY o.created_at DESC LIMIT ?");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        $stmt = $pdo->prepare("SELECT o.*, t.name as template_name,
                               (SELECT id FROM invitations WHERE order_id = o.id LIMIT 1) as inv_id,
                               (SELECT slug FROM invitations WHERE order_id = o.id LIMIT 1) as inv_slug
                              FROM orders o 
                              JOIN templates t ON o.template_id = t.id 
                              WHERE o.user_id = ? 
                              ORDER BY o.created_at DESC LIMIT ?");
        $stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    exit();
}

// ========================
//  ORDERS: UPDATE ORDER STATUS (ADMIN)
// ========================
if ($action === 'update_order_status') {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['order_id'])) {
        echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
        exit();
    }

    $updates = [];
    $params = [];
    if (isset($data['payment_status'])) {
        $updates[] = "payment_status = ?";
        $params[] = sanitizeInput($data['payment_status']);
        if ($data['payment_status'] === 'Terverifikasi') {
            $updates[] = "order_status = ?";
            $params[] = 'Aktif';
        }
    }
    if (isset($data['order_status'])) {
        $updates[] = "order_status = ?";
        $params[] = sanitizeInput($data['order_status']);
    }
    
    if (empty($updates)) {
        echo json_encode(["success" => false, "message" => "Tidak ada data yang diubah"]);
        exit();
    }

    $sql = "UPDATE orders SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $data['order_id'];
    
    $pdo->prepare($sql)->execute($params);
    echo json_encode(["success" => true, "message" => "Status pesanan diperbarui"]);
    exit();
}

// ========================
//  ORDERS: MIDTRANS NOTIFICATION WEBHOOK
// ========================
if ($action === 'midtrans_notification') {
    $json = file_get_contents('php://input');
    $notif = json_decode($json, true);
    
    if (!$notif || !isset($notif['order_id'], $notif['transaction_status'], $notif['signature_key'])) {
        http_response_code(400);
        exit('Invalid data');
    }

    $orderId = $notif['order_id'];
    $statusCode = $notif['status_code'];
    $grossAmount = $notif['gross_amount'];
    $serverKey = MIDTRANS_SERVER_KEY;
    $inputSignature = $notif['signature_key'];
    
    $signatureData = $orderId . $statusCode . $grossAmount . $serverKey;
    $calculatedSignature = hash('sha512', $signatureData);
    
    if ($calculatedSignature !== $inputSignature) {
        http_response_code(403);
        exit('Invalid signature');
    }

    $transactionStatus = $notif['transaction_status'];
    $paymentStatus = 'Belum Bayar';
    $orderStatus = 'Pending';

    if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
        $paymentStatus = 'Terverifikasi';
        $orderStatus = 'Aktif';
    } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
        $paymentStatus = 'Ditolak';
        $orderStatus = 'Dibatalkan';
    } else if ($transactionStatus == 'pending') {
        $paymentStatus = 'Menunggu Verifikasi';
    }

    $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, order_status = ? WHERE id = ?");
    $stmt->execute([$paymentStatus, $orderStatus, $orderId]);

    echo "OK";
    exit();
}

// ========================
//  ORDERS: CHECK PAYMENT STATUS
// ========================
if ($action === 'check_payment_status') {
    if (!isset($_SESSION['user_id'])) exit();
    $orderId = isset($_GET['order_id']) ? $_GET['order_id'] : '';
    
    $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $res = $stmt->fetch();
    
    if ($res) {
        echo json_encode(["success" => true, "payment_status" => $res['payment_status']]);
    } else {
        echo json_encode(["success" => false]);
    }
    exit();
}

// ========================
//  ADMIN: DASHBOARD STATS
// ========================
if ($action === 'admin_stats') {
    requireAdmin();
    
    $revenue = $pdo->query("SELECT SUM(total) FROM orders WHERE payment_status = 'Terverifikasi'")->fetchColumn() ?: 0;
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0;
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
    $pendingPayments = $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'Menunggu Verifikasi'")->fetchColumn() ?: 0;
    
    $catStats = $pdo->query("
        SELECT t.category, SUM(o.total) as revenue 
        FROM orders o 
        JOIN templates t ON o.template_id = t.id 
        WHERE o.payment_status = 'Terverifikasi' 
        GROUP BY t.category
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "stats" => [
            "total_revenue" => $revenue,
            "total_orders" => $totalOrders,
            "total_users" => $totalUsers,
            "pending_payments" => $pendingPayments,
            "category_stats" => $catStats
        ]
    ]);
    exit();
}
?>
