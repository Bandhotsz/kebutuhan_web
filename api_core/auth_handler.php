<?php
// api_core/auth_handler.php
if (!isset($pdo)) exit('Direct access not permitted.');

// ========================
//  AUTH: REGISTER
// ========================
if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['name'], $data['email'], $data['password'])) {
        echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Email sudah terdaftar"]);
        exit();
    }

    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, otp, otp_expiry) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        sanitizeInput($data['name']),
        $data['email'],
        $hashedPassword,
        isset($data['phone']) ? sanitizeInput($data['phone']) : null,
        $otp,
        $otpExpiry
    ]);

    $subject = "Kode OTP Registrasi - NikahYuk";
    $message = "Kode OTP Anda: $otp\nBerlaku selama " . OTP_EXPIRY_MINUTES . " menit.";
    $emailSent = sendEmailOTP($data['email'], $subject, $message);
    if (!$emailSent) {
        if (IS_DEV_MODE) {
            echo json_encode(["success" => true, "message" => "[DEV MODE] Gagal kirim email, gunakan OTP berikut:", "otp_preview" => $otp]);
        } else {
            echo json_encode(["success" => false, "message" => "Gagal mengirim email OTP. Pastikan email Anda aktif."]);
        }
        exit();
    }

    $res = ["success" => true, "message" => "Registrasi berhasil! Kode OTP telah dikirim ke email Anda."];
    if (IS_DEV_MODE) $res["otp_preview"] = $otp;
    echo json_encode($res);
    exit();
}

// ========================
//  AUTH: VERIFY OTP
// ========================
if ($action === 'verify_otp') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email'], $data['otp'])) {
        echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, otp, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "Email tidak ditemukan atau sudah terverifikasi"]);
        exit();
    }

    if ($user['otp'] !== $data['otp']) {
        echo json_encode(["success" => false, "message" => "Kode OTP salah"]);
        exit();
    }

    if (strtotime($user['otp_expiry']) < time()) {
        echo json_encode(["success" => false, "message" => "Kode OTP sudah kadaluarsa"]);
        exit();
    }

    $pdo->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expiry = NULL WHERE id = ?")->execute([$user['id']]);

    echo json_encode(["success" => true, "message" => "Email berhasil diverifikasi! Silakan login."]);
    exit();
}

// ========================
//  AUTH: RESEND OTP
// ========================
if ($action === 'resend_otp') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email'])) {
        echo json_encode(["success" => false, "message" => "Email diperlukan"]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 0");
    $stmt->execute([$data['email']]);
    if (!$stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Email tidak ditemukan"]);
        exit();
    }

    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $pdo->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE email = ?")->execute([$otp, $otpExpiry, $data['email']]);

    $subject = "Kode OTP Baru - NikahYuk";
    $message = "Kode OTP baru Anda: $otp\nBerlaku selama " . OTP_EXPIRY_MINUTES . " menit.";
    $emailSent = sendEmailOTP($data['email'], $subject, $message);
    
    if (!$emailSent) {
        if (IS_DEV_MODE) {
            echo json_encode(["success" => true, "message" => "[DEV MODE] Gagal kirim email, gunakan OTP berikut:", "otp_preview" => $otp]);
        } else {
            echo json_encode(["success" => false, "message" => "Gagal mengirim email OTP baru. Coba beberapa saat lagi."]);
        }
        exit();
    }

    $res = ["success" => true, "message" => "OTP baru telah dikirim ke email Anda."];
    if (IS_DEV_MODE) $res["otp_preview"] = $otp;
    echo json_encode($res);
    exit();
}

// ========================
//  AUTH: FORGOT PASSWORD
// ========================
if ($action === 'forgot_password') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email'])) {
        echo json_encode(["success" => false, "message" => "Email diperlukan"]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 1");
    $stmt->execute([$data['email']]);
    if (!$stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Email tidak terdaftar atau belum diverifikasi"]);
        exit();
    }

    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $pdo->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE email = ?")->execute([$otp, $otpExpiry, $data['email']]);

    $subject = "Kode OTP Reset Password - NikahYuk";
    $message = "Kode OTP Anda untuk reset password: $otp\nBerlaku selama " . OTP_EXPIRY_MINUTES . " menit.";
    $emailSent = sendEmailOTP($data['email'], $subject, $message);
    
    if (!$emailSent) {
        if (IS_DEV_MODE) {
            echo json_encode(["success" => true, "message" => "[DEV MODE] Gagal kirim email, gunakan OTP berikut:", "otp_preview" => $otp]);
        } else {
            echo json_encode(["success" => false, "message" => "Gagal mengirim email OTP reset password. Coba beberapa saat lagi."]);
        }
        exit();
    }

    $res = ["success" => true, "message" => "Kode OTP reset password telah dikirim ke email Anda."];
    if (IS_DEV_MODE) $res["otp_preview"] = $otp;
    echo json_encode($res);
    exit();
}

// ========================
//  AUTH: RESET PASSWORD
// ========================
if ($action === 'reset_password') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email'], $data['otp'], $data['new_password'])) {
        echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, otp, otp_expiry FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['otp'] !== $data['otp']) {
        echo json_encode(["success" => false, "message" => "Kode OTP salah atau email tidak valid"]);
        exit();
    }

    if (strtotime($user['otp_expiry']) < time()) {
        echo json_encode(["success" => false, "message" => "Kode OTP sudah kadaluarsa"]);
        exit();
    }

    $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ?, otp = NULL, otp_expiry = NULL WHERE id = ?")->execute([$hashedPassword, $user['id']]);

    echo json_encode(["success" => true, "message" => "Password berhasil diubah! Silakan login."]);
    exit();
}

// ========================
//  AUTH: LOGIN
// ========================
if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email'], $data['password'])) {
        echo json_encode(["success" => false, "message" => "Email dan password diperlukan"]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data['password'], $user['password'])) {
        echo json_encode(["success" => false, "message" => "Email atau password salah"]);
        exit();
    }

    if (!$user['is_verified']) {
        echo json_encode(["success" => false, "message" => "Akun belum diverifikasi. Silakan cek email untuk kode OTP.", "need_verify" => true]);
        exit();
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];

    echo json_encode([
        "success" => true,
        "message" => "Login berhasil!",
        "csrf_token" => generateCsrfToken(),
        "user" => ["id" => $user['id'], "name" => $user['name'], "email" => $user['email'], "phone" => $user['phone'], "role" => $user['role']]
    ]);
    exit();
}

// ========================
//  AUTH: GOOGLE LOGIN
// ========================
if ($action === 'google_login') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['credential'])) {
        echo json_encode(["success" => false, "message" => "Credential tidak ditemukan"]);
        exit();
    }

    $id_token = $data['credential'];
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
    $response = @file_get_contents($verify_url, false, $ctx);
    $payload = $response ? json_decode($response, true) : null;
    
    if (!$payload || isset($payload['error'])) {
        echo json_encode(["success" => false, "message" => "Verifikasi token Google gagal"]);
        exit();
    }
    
    $email = $payload['email'];
    $name = sanitizeInput($payload['name']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            
            echo json_encode([
                "success" => true,
                "message" => "Login berhasil!",
                "csrf_token" => generateCsrfToken(),
                "user" => ["id" => $user['id'], "name" => $user['name'], "email" => $user['email'], "phone" => $user['phone'], "role" => $user['role']]
            ]);
        } else {
            $random_pass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, 'buyer', 1)");
            $stmt->execute([$name, $email, $random_pass]);
            
            $new_user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['user_role'] = 'buyer';
            
            echo json_encode([
                "success" => true,
                "message" => "Registrasi dengan Google berhasil!",
                "csrf_token" => generateCsrfToken(),
                "user" => ["id" => $new_user_id, "name" => $name, "email" => $email, "phone" => null, "role" => 'buyer']
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
    exit();
}

// ========================
//  AUTH: LOGOUT
// ========================
if ($action === 'logout') {
    session_destroy();
    echo json_encode(["success" => true, "message" => "Berhasil logout"]);
    exit();
}

// ========================
//  AUTH: CHECK SESSION
// ========================
if ($action === 'check_session') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            echo json_encode(["success" => true, "csrf_token" => generateCsrfToken(), "user" => $user]);
            exit();
        }
    }
    echo json_encode(["success" => false]);
    exit();
}
?>
