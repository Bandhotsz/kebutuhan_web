-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: undangan_digital
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `invitations`
--

DROP TABLE IF EXISTS `invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(50) NOT NULL,
  `template_id` varchar(50) NOT NULL,
  `groom_name` varchar(255) NOT NULL,
  `bride_name` varchar(255) NOT NULL,
  `groom_father` varchar(255) DEFAULT NULL,
  `groom_mother` varchar(255) DEFAULT NULL,
  `bride_father` varchar(255) DEFAULT NULL,
  `bride_mother` varchar(255) DEFAULT NULL,
  `wedding_date` date NOT NULL,
  `wedding_time` time NOT NULL,
  `ceremony_location` text NOT NULL,
  `reception_location` text DEFAULT NULL,
  `love_story` text DEFAULT NULL,
  `custom_message` text DEFAULT NULL,
  `background_music` varchar(255) DEFAULT 'default',
  `theme_color` varchar(50) DEFAULT '#d4a373',
  `slug` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `order_id` (`order_id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `invitations_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invitations_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invitations`
--

LOCK TABLES `invitations` WRITE;
/*!40000 ALTER TABLE `invitations` DISABLE KEYS */;
/*!40000 ALTER TABLE `invitations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `template_id` varchar(50) NOT NULL,
  `total` bigint(20) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(500) DEFAULT NULL,
  `payment_status` enum('Belum Bayar','Menunggu Verifikasi','Terverifikasi','Ditolak') DEFAULT 'Belum Bayar',
  `order_status` enum('Pending','Diproses','Aktif','Selesai','Dibatalkan') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rsvp`
--

DROP TABLE IF EXISTS `rsvp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rsvp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invitation_id` int(11) NOT NULL,
  `guest_name` varchar(255) NOT NULL,
  `attendance` enum('Hadir','Tidak Hadir','Ragu-ragu') NOT NULL,
  `guest_count` int(11) DEFAULT 1,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `invitation_id` (`invitation_id`),
  CONSTRAINT `rsvp_ibfk_1` FOREIGN KEY (`invitation_id`) REFERENCES `invitations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rsvp`
--

LOCK TABLES `rsvp` WRITE;
/*!40000 ALTER TABLE `rsvp` DISABLE KEYS */;
/*!40000 ALTER TABLE `rsvp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `templates` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` enum('Elegan','Tradisional','Modern') NOT NULL,
  `price` bigint(20) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `preview_color` varchar(100) DEFAULT '#d4a373',
  `gradient` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `templates`
--

LOCK TABLES `templates` WRITE;
/*!40000 ALTER TABLE `templates` DISABLE KEYS */;
INSERT INTO `templates` VALUES ('tmpl-001','Sakura Blossom','Elegan',150000,'Template undangan pernikahan elegan dengan nuansa bunga sakura. Desain minimalis dengan sentuhan mewah, cocok untuk pasangan modern yang menghargai kesederhanaan dan keindahan.','Animasi bunga sakura jatuh, Galeri foto, Hitung mundur, RSVP online, Peta lokasi, Musik latar','#f8b4c8','linear-gradient(135deg, #f8b4c8 0%, #ffffff 50%, #ffffff 100%)',1,'2026-06-24 06:42:11'),('tmpl-002','Batik Nusantara','Tradisional',125000,'Undangan pernikahan bernuansa batik khas Nusantara. Memadukan motif batik tradisional dengan desain digital modern, menghadirkan keanggunan budaya Indonesia.','Motif batik animasi, Galeri foto, Hitung mundur, RSVP online, Peta lokasi, Musik gamelan','#8B6914','linear-gradient(135deg, #8B6914 0%, #DAA520 50%, #F5DEB3 100%)',1,'2026-06-24 06:42:11'),('tmpl-003','Minimalist White','Modern',100000,'Template undangan modern minimalis dengan tema putih bersih. Desain clean dan profesional, fokus pada tipografi yang elegan dan tata letak yang rapi.','Desain minimalis, Galeri foto, Hitung mundur, RSVP online, Peta lokasi, Musik latar','#9CA3AF','linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%)',1,'2026-06-24 06:42:11'),('tmpl-004','Rose Gold Luxury','Elegan',200000,'Template premium dengan tema rose gold mewah. Efek kilau metalik dan ornamen floral memberi kesan glamor dan sophisticated untuk hari spesial Anda.','Efek rose gold shimmer, Galeri foto slideshow, Hitung mundur animasi, RSVP online, Peta interaktif, Pilihan musik','#d4a373','linear-gradient(135deg, #d4a373 0%, #e8c8a9 50%, #f5e6d3 100%)',1,'2026-06-24 06:42:11'),('tmpl-005','Wayang Kulit','Tradisional',175000,'Undangan digital dengan tema wayang kulit Jawa. Menampilkan seni wayang tradisional dengan sentuhan animasi digital yang memukau.','Animasi wayang, Motif Jawa, Galeri foto, Hitung mundur, RSVP online, Musik gamelan Jawa','#5D4037','linear-gradient(135deg, #5D4037 0%, #8D6E63 50%, #BCAAA4 100%)',1,'2026-06-24 06:42:11'),('tmpl-006','Galaxy Dreams','Modern',175000,'Template undangan futuristik dengan tema galaxy dan bintang-bintang. Desain bold dan unik untuk pasangan yang ingin tampil beda di hari bahagia mereka.','Animasi bintang, Efek parallax, Galeri foto, Hitung mundur, RSVP online, Musik ambient','#7C3AED','linear-gradient(135deg, #1e1b4b 0%, #7c3aed 50%, #c084fc 100%)',1,'2026-06-24 06:42:11');
/*!40000 ALTER TABLE `templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` enum('buyer','admin') DEFAULT 'buyer',
  `otp` varchar(10) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Administrator','admin@nikahkita.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',NULL,'admin',NULL,NULL,1,'2026-06-24 06:42:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-25 21:59:59
