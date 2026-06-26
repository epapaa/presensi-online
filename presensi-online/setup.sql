-- ============================================================
--  PRESENSI ONLINE – Setup Database
--  Jalankan file ini di phpMyAdmin > SQL
-- ============================================================

-- 1. Tabel admin
CREATE TABLE IF NOT EXISTS `admin` (
  `id`       INT AUTO_INCREMENT PRIMARY KEY,
  `nama`     VARCHAR(100) NOT NULL,
  `username` VARCHAR(50)  NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel karyawan
CREATE TABLE IF NOT EXISTS `karyawan` (
  `id`       INT AUTO_INCREMENT PRIMARY KEY,
  `nama`     VARCHAR(100) NOT NULL,
  `username` VARCHAR(50)  NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `jabatan`  VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel presensi
CREATE TABLE IF NOT EXISTS `presensi` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `karyawan_id`  INT NOT NULL,
  `tanggal`      DATE NOT NULL,
  `jam_masuk`    TIME DEFAULT NULL,
  `jam_pulang`   TIME DEFAULT NULL,
  `predikat`     ENUM('hadir','telat','alpha','izin') DEFAULT 'alpha',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_presensi` (`karyawan_id`, `tanggal`),
  FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  SEED DATA – Akun Default
--  Password untuk semua akun: password123
-- ============================================================

-- Admin default
INSERT INTO `admin` (`nama`, `username`, `password`) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Karyawan contoh
INSERT INTO `karyawan` (`nama`, `username`, `password`, `jabatan`) VALUES
('Budi Santoso',   'budi',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Frontend Developer'),
('Dewi Rahayu',    'dewi',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Backend Developer'),
('Ahmad Fauzi',    'ahmad',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'UI/UX Designer'),
('Siti Nurhaliza', 'siti',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'QA Engineer');

-- ============================================================
--  CATATAN:
--  Hash di atas adalah bcrypt dari kata "password"
--  (bawaan Laravel, cocok untuk PHP password_verify)
--
--  Kalau mau ganti password, jalankan PHP ini di terminal:
--  php -r "echo password_hash('passwordbaru', PASSWORD_DEFAULT);"
-- ============================================================

-- ============================================================
--  UPDATE: Tambah kolom menit_telat (jalankan ini jika tabel sudah ada)
-- ============================================================
ALTER TABLE `presensi` ADD COLUMN IF NOT EXISTS `menit_telat` INT DEFAULT 0 AFTER `predikat`;
