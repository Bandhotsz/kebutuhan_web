# 💍 NikahKita - Platform Undangan Pernikahan Digital

NikahKita adalah platform pembuatan undangan pernikahan digital interaktif modern berbasis web. Dilengkapi dengan beragam pilihan tema template elegan, sistem RSVP online, buku tamu, pemutar musik latar, integrasi pembayaran Midtrans, serta dashboard administrator yang lengkap.

---

## ✨ Fitur Utama

- 🎨 **Katalog Template Interaktif**: Beragam pilihan template responsif (*Sakura Blossom, Batik Nusantara, Minimalist White, Rose Gold Luxury, Wayang Kulit, Galaxy Dreams, 3D Motion Luxury*).
- 📱 **Desain Responsif & Modern**: Tampilan optimal di smartphone, tablet, maupun layar desktop.
- ✍️ **Kustomisasi Lengkap**: Form data mempelai, cerita cinta (*love story*), detail akad & resepsi, lokasi Google Maps, pemilihan tema warna, serta musik latar.
- 💌 **RSVP Online & Ucapan Doa**: Tamu undangan dapat mengonfirmasi kehadiran serta menuliskan pesan & doa secara realtime.
- 💳 **Sistem Pembayaran Fleksibel**:
  - Otomatis via **Midtrans Payment Gateway** (Snap API).
  - Transfer Manual dengan unggah bukti pembayaran.
- 🔐 **Autentikasi & Keamanan**:
  - Registrasi & Login pembeli dan admin.
  - Verifikasi OTP email via **PHPMailer**.
  - Proteksi CSRF & Sanitasi input query database (PDO prepared statements).
- 📊 **Dashboard Administrator**:
  - Pantau total pendapatan, status pesanan, dan verifikasi pembayaran.
  - Manajemen katalog template dan kelola link undangan aktif.

---

## 🛠️ Tech Stack

- **Frontend**: HTML5, CSS3 (Modern Glassmorphism & Animations), Vanilla JavaScript (ES6+)
- **Backend**: PHP 8.x (Native modular architecture di `api_core/`)
- **Database**: MySQL / MariaDB (PDO driver)
- **External Libraries**: PHPMailer, Midtrans Snap JS, Google Fonts, Font Awesome

---

## 🚀 Panduan Instalasi Lokal (XAMPP)

### 1. Clone Repository
Letakkan folder project ini di direktori `htdocs` XAMPP kamu:
```bash
cd C:/xampp/htdocs
git clone https://github.com/USERNAME_KAMU/NAMA_REPO.git "project web"
```

### 2. Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Sesuaikan pengaturan database dan kredensial di dalam file `.env`:
```env
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=undangan_digital

# Set 'true' untuk mode testing tanpa perlu SMTP asli / Midtrans asli
IS_DEV_MODE=true

# Konfigurasi SMTP (opsional jika IS_DEV_MODE=false)
SMTP_HOST=smtp.gmail.com
SMTP_USER=email_kamu@gmail.com
SMTP_PASS=app_password_kamu
SMTP_FROM=noreply@nikahkita.com

# Konfigurasi Midtrans
MIDTRANS_SERVER_KEY=SB-Mid-server-YOUR_SERVER_KEY
MIDTRANS_IS_PRODUCTION=false
```

### 3. Setup Database
Kamu bisa menggunakan salah satu dari dua cara berikut:

- **Cara A (Import File SQL)**:
  1. Buka `phpMyAdmin` (`http://localhost/phpmyadmin`).
  2. Buat database baru bernama `undangan_digital`.
  3. Import file `undangan_digital.sql`.

- **Cara B (Setup Otomatis via Browser)**:
  Akses URL setup database berikut di browser kamu:
  ```
  http://localhost/project web/api_core/db_setup.php
  ```

---

## 🔑 Kredensial Administrator Default

- **URL Admin**: `http://localhost/project web/admin.html`
- **Email**: `admin@nikahkita.com`
- **Password**: `password` *(atau `admin123` jika dibuat via db_setup.php)*

> ⚠️ **Catatan**: Segera ubah password default ini melalui menu pengaturan profil administrator setelah pertama kali login.

---

## 📁 Struktur Direktori

```
├── .env.example            # Template konfigurasi environment
├── .gitignore              # Proteksi file sensitif & junk files
├── index.html              # Halaman beranda / katalog utama
├── preview.html            # Halaman pratinjau & pembuatan undangan
├── admin.html              # Dashboard manajemen administrator
├── api.php                 # Routing API utama
├── api_core/               # Handler backend (Auth, Order, Template, DB)
├── css/                    # Stylesheet tema dan layout
├── js/                     # Skrip interaktif frontend & konfigurasi
├── phpmailer/              # Library pengiriman email OTP
├── uploads/                # Direktori penyimpanan bukti bayar & media
└── undangan_digital.sql    # Skema & seed database
```

---

## 📄 Lisensi
Project ini dibuat untuk keperluan portfolio dan pengembangan aplikasi undangan pernikahan digital. Silakan digunakan dan dimodifikasi sesuai kebutuhan.
