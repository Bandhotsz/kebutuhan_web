<?php
// api_core/template_handler.php
if (!isset($pdo)) exit('Direct access not permitted.');

// ========================
//  TEMPLATES: GET ALL
// ========================
if ($action === 'get_templates') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $stmt = $pdo->prepare("SELECT * FROM templates ORDER BY created_at DESC LIMIT ?");
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit();
}

// ========================
//  ADMIN: SAVE TEMPLATE
// ========================
if ($action === 'save_template') {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'], $data['name'], $data['category'], $data['price'])) {
        echo json_encode(["success" => false, "message" => "Data template tidak lengkap"]);
        exit();
    }

    $id = sanitizeInput($data['id']);
    
    // Validate if it exists
    $stmt = $pdo->prepare("SELECT id FROM templates WHERE id = ?");
    $stmt->execute([$id]);
    $exists = $stmt->fetch();

    $name = sanitizeInput($data['name']);
    $category = sanitizeInput($data['category']);
    $price = (int)$data['price'];
    $desc = sanitizeInput($data['description'] ?? '');
    $features = sanitizeInput($data['features'] ?? '');
    $color = sanitizeInput($data['preview_color'] ?? '#d4a373');
    $gradient = sanitizeInput($data['gradient'] ?? '');

    if ($exists) {
        $pdo->prepare("UPDATE templates SET name=?, category=?, price=?, description=?, features=?, preview_color=?, gradient=? WHERE id=?")
            ->execute([$name, $category, $price, $desc, $features, $color, $gradient, $id]);
        echo json_encode(["success" => true, "message" => "Template diperbarui"]);
    } else {
        $pdo->prepare("INSERT INTO templates (id, name, category, price, description, features, preview_color, gradient) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$id, $name, $category, $price, $desc, $features, $color, $gradient]);
        echo json_encode(["success" => true, "message" => "Template ditambahkan"]);
    }
    exit();
}

// ========================
//  ADMIN: DELETE TEMPLATE
// ========================
if ($action === 'delete_template') {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "ID diperlukan"]);
        exit();
    }
    $pdo->prepare("DELETE FROM templates WHERE id = ?")->execute([$data['id']]);
    echo json_encode(["success" => true, "message" => "Template dihapus"]);
    exit();
}

// ========================
//  INVITATIONS: SAVE/UPDATE
// ========================
if ($action === 'save_invitation') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "Harap login terlebih dahulu"]);
        exit();
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['order_id'], $data['groom_name'], $data['bride_name'])) {
        echo json_encode(["success" => false, "message" => "Data undangan tidak lengkap"]);
        exit();
    }

    $orderId = sanitizeInput($data['order_id']);
    
    // Verify Order Ownership
    $stmt = $pdo->prepare("SELECT user_id, template_id, order_status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order || $order['user_id'] != $_SESSION['user_id']) {
        echo json_encode(["success" => false, "message" => "Pesanan tidak valid"]);
        exit();
    }
    if ($order['order_status'] !== 'Aktif') {
        echo json_encode(["success" => false, "message" => "Pesanan belum aktif. Tunggu verifikasi pembayaran."]);
        exit();
    }

    $slug = sanitizeInput($data['slug'] ?? '');
    if (empty($slug)) {
        $slug = strtolower(str_replace(' ', '-', $data['groom_name'] . '-' . $data['bride_name'])) . '-' . rand(100,999);
    }
    $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $slug);

    // Check slug uniqueness
    $stmt = $pdo->prepare("SELECT id FROM invitations WHERE slug = ? AND order_id != ?");
    $stmt->execute([$slug, $orderId]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Link/Slug sudah dipakai, gunakan yang lain."]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM invitations WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $existing = $stmt->fetch();

    $params = [
        sanitizeInput($data['groom_name']), sanitizeInput($data['bride_name']),
        sanitizeInput($data['groom_father'] ?? ''), sanitizeInput($data['groom_mother'] ?? ''),
        sanitizeInput($data['bride_father'] ?? ''), sanitizeInput($data['bride_mother'] ?? ''),
        sanitizeInput($data['wedding_date']), sanitizeInput($data['wedding_time']),
        sanitizeInput($data['ceremony_location']), sanitizeInput($data['reception_location'] ?? ''),
        sanitizeInput($data['love_story'] ?? ''), sanitizeInput($data['custom_message'] ?? ''),
        sanitizeInput($data['background_music'] ?? 'default'), sanitizeInput($data['theme_color'] ?? '#d4a373'),
        $slug
    ];

    if ($existing) {
        $sql = "UPDATE invitations SET 
            groom_name=?, bride_name=?, groom_father=?, groom_mother=?, bride_father=?, bride_mother=?,
            wedding_date=?, wedding_time=?, ceremony_location=?, reception_location=?, love_story=?, 
            custom_message=?, background_music=?, theme_color=?, slug=? WHERE order_id=?";
        $params[] = $orderId;
        $pdo->prepare($sql)->execute($params);
    } else {
        $sql = "INSERT INTO invitations (
            order_id, template_id, groom_name, bride_name, groom_father, groom_mother, bride_father, bride_mother,
            wedding_date, wedding_time, ceremony_location, reception_location, love_story, custom_message,
            background_music, theme_color, slug
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        array_unshift($params, $orderId, $order['template_id']);
        $pdo->prepare($sql)->execute($params);
    }

    echo json_encode(["success" => true, "message" => "Undangan berhasil disimpan!", "slug" => $slug]);
    exit();
}

// ========================
//  INVITATIONS: GET MY INVITATIONS (FORM DATA)
// ========================
if ($action === 'get_my_invitation') {
    if (!isset($_SESSION['user_id'])) exit();
    $orderId = isset($_GET['order_id']) ? $_GET['order_id'] : '';
    
    $stmt = $pdo->prepare("SELECT i.* FROM invitations i JOIN orders o ON i.order_id = o.id WHERE i.order_id = ? AND o.user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invitation) {
        echo json_encode(["success" => true, "invitation" => $invitation]);
    } else {
        echo json_encode(["success" => false]);
    }
    exit();
}

// ========================
//  PUBLIC: GET INVITATION (PREVIEW/VISITOR)
// ========================
if ($action === 'get_invitation') {
    $slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';
    
    $stmt = $pdo->prepare("SELECT i.*, t.gradient FROM invitations i JOIN templates t ON i.template_id = t.id WHERE i.slug = ? AND i.is_active = 1");
    $stmt->execute([$slug]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invitation) {
        echo json_encode(["success" => true, "invitation" => $invitation]);
    } else {
        echo json_encode(["success" => false, "message" => "Undangan tidak ditemukan."]);
    }
    exit();
}

// ========================
//  PUBLIC: SUBMIT RSVP
// ========================
if ($action === 'submit_rsvp') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['invitation_id'], $data['guest_name'], $data['attendance'])) {
        echo json_encode(["success" => false, "message" => "Data RSVP tidak lengkap"]);
        exit();
    }
    
    $stmt = $pdo->prepare("INSERT INTO rsvp (invitation_id, guest_name, attendance, guest_count, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['invitation_id'],
        sanitizeInput($data['guest_name']),
        sanitizeInput($data['attendance']),
        (int)($data['guest_count'] ?? 1),
        sanitizeInput($data['message'] ?? '')
    ]);
    
    echo json_encode(["success" => true, "message" => "RSVP berhasil dikirim!"]);
    exit();
}

// ========================
//  PUBLIC: GET RSVP LIST
// ========================
if ($action === 'get_rsvp') {
    $invId = isset($_GET['invitation_id']) ? (int)$_GET['invitation_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $stmt = $pdo->prepare("SELECT guest_name, attendance, message, created_at FROM rsvp WHERE invitation_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindParam(1, $invId, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit();
}
?>
