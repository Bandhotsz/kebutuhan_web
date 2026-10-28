<?php
// api_core/db_setup.php

if (!isset($pdo)) {
    exit('Direct access not permitted.');
}

// --- TABLE: users ---
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    role ENUM('buyer','admin') DEFAULT 'buyer',
    otp VARCHAR(10) DEFAULT NULL,
    otp_expiry DATETIME DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- TABLE: templates ---
$pdo->exec("CREATE TABLE IF NOT EXISTS templates (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category ENUM('Elegan','Tradisional','Modern') NOT NULL,
    price BIGINT NOT NULL,
    description TEXT,
    features TEXT,
    preview_color VARCHAR(100) DEFAULT '#d4a373',
    gradient VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// --- TABLE: orders ---
$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL,
    template_id VARCHAR(50) NOT NULL,
    total BIGINT NOT NULL,
    payment_method VARCHAR(100),
    payment_proof VARCHAR(500) DEFAULT NULL,
    payment_status ENUM('Belum Bayar','Menunggu Verifikasi','Terverifikasi','Ditolak') DEFAULT 'Belum Bayar',
    order_status ENUM('Pending','Diproses','Aktif','Selesai','Dibatalkan') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
)");

// --- TABLE: invitations ---
$pdo->exec("CREATE TABLE IF NOT EXISTS invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    template_id VARCHAR(50) NOT NULL,
    groom_name VARCHAR(255) NOT NULL,
    bride_name VARCHAR(255) NOT NULL,
    groom_father VARCHAR(255),
    groom_mother VARCHAR(255),
    bride_father VARCHAR(255),
    bride_mother VARCHAR(255),
    wedding_date DATE NOT NULL,
    wedding_time TIME NOT NULL,
    ceremony_location TEXT NOT NULL,
    reception_location TEXT,
    love_story TEXT,
    custom_message TEXT,
    background_music VARCHAR(255) DEFAULT 'default',
    theme_color VARCHAR(50) DEFAULT '#d4a373',
    slug VARCHAR(100) UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
)");

// --- TABLE: rsvp ---
$pdo->exec("CREATE TABLE IF NOT EXISTS rsvp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invitation_id INT NOT NULL,
    guest_name VARCHAR(255) NOT NULL,
    attendance ENUM('Hadir','Tidak Hadir','Ragu-ragu') NOT NULL,
    guest_count INT DEFAULT 1,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
)");

// --- Seed admin user if not exists ---
$adminCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if ($adminCheck == 0) {
    $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@nikahkita.com';
    $rawAdminPass = $_ENV['ADMIN_PASSWORD'] ?? 'admin123';
    $adminPass = password_hash($rawAdminPass, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (name, email, password, role, is_verified) VALUES (?, ?, ?, 'admin', 1)")
        ->execute(['Administrator', $adminEmail, $adminPass]);
}

// --- Seed Template 007 if not exists ---
$checkTmpl7 = $pdo->query("SELECT COUNT(*) FROM templates WHERE id = 'tmpl-007'")->fetchColumn();
if ($checkTmpl7 == 0) {
    $pdo->prepare("INSERT INTO templates (id, name, category, price, description, features, preview_color, gradient) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            "tmpl-007",
            "3D Motion Luxury (Miftah & Agus)",
            "Elegan",
            250000,
            "Template undangan pernikahan digital premium dengan efek 3D Motion dan nuansa Dark Luxury Gold, terinspirasi dari gaya eksklusif Miftah & Agus. Menampilkan animasi elegan, kutipan ayat suci, hitung mundur presisi, galeri foto, serta fitur simpan ke kalender.",
            "Animasi 3D Motion, Dark Gold Theme, Hitung Mundur Animasi, Galeri Foto Slideshow, RSVP Online, Peta Interaktif, Musik Latar Eksklusif, Kutipan Ayat Suci, Simpan Kalender",
            "#d4af37",
            "linear-gradient(135deg, #181412 0%, #2a201c 50%, #4a382e 100%)"
        ]);
}

// --- Seed Demo Order and Invitation if not exists ---
$checkDemoInv = $pdo->query("SELECT COUNT(*) FROM invitations WHERE slug = 'miftah-agus'")->fetchColumn();
if ($checkDemoInv == 0) {
    $adminUserId = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
    if (!$adminUserId) $adminUserId = 1;

    $checkOrder = $pdo->query("SELECT COUNT(*) FROM orders WHERE id = 'ORD-DEMO-MIFTAH'")->fetchColumn();
    if ($checkOrder == 0) {
        $pdo->prepare("INSERT INTO orders (id, user_id, template_id, total, payment_method, payment_status, order_status) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute(['ORD-DEMO-MIFTAH', $adminUserId, 'tmpl-007', 250000, 'Transfer Bank', 'Terverifikasi', 'Aktif']);
    }

    $pdo->prepare("INSERT INTO invitations (order_id, template_id, groom_name, bride_name, groom_father, groom_mother, bride_father, bride_mother, wedding_date, wedding_time, ceremony_location, reception_location, love_story, custom_message, background_music, theme_color, slug, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")
        ->execute([
            'ORD-DEMO-MIFTAH',
            'tmpl-007',
            'Agus Suratno, S.T.',
            'Miftahul Hasanah, S.Pd',
            'Sumo Prayitno',
            'Tarti',
            'M. Kholidin',
            'Murliyah',
            '2026-06-07',
            '08:00:00',
            'Masjid Al-Ikhlas, Jl. Raya Utama No. 123, Sleman, Yogyakarta',
            'Gedung Serbaguna Mulia, Jl. Kaliurang KM 10, Yogyakarta',
            'Dan insyaAllah, pada 7 Juni 2026 kami memutuskan untuk menyempurnakan separuh agama, menuju pernikahan yang diberkahi. Bukan lagi tentang aku dan kamu, tapi tentang “kita” dalam ikatan suci yang diridhai Allah.',
            'Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir untuk memberikan doa restu pada acara pernikahan kami.',
            'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
            '#d4af37',
            'miftah-agus'
        ]);
}
?>
