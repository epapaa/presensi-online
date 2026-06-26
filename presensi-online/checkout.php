```php
<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'karyawan') {
    header("Location: login.php");
    exit;
}

$karyawan_id = $_SESSION['user_id'];
$today       = date('Y-m-d');
$now_time    = date('H:i:s');

// ========================================
// Harus sudah check in
// ========================================

$stmt = $conn->prepare("
SELECT id, jam_pulang
FROM presensi
WHERE karyawan_id = ?
AND tanggal = ?
AND jam_masuk IS NOT NULL
");

$stmt->bind_param("is", $karyawan_id, $today);
$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    header("Location: dashboard_karyawan.php?msg=belum_masuk");
    exit;
}

// ========================================
// Sudah checkout?
// ========================================

if ($row['jam_pulang']) {
    header("Location: dashboard_karyawan.php?msg=sudah_pulang");
    exit;
}

// ========================================
// Validasi Jam Checkout
// ========================================

// Belum boleh checkout
if ($now_time < JAM_PULANG_MULAI) {

    header("Location: dashboard_karyawan.php?msg=belum_waktunya");
    exit;
}

// 16.30 - 17.00
elseif (
    $now_time >= JAM_PULANG_MULAI &&
    $now_time <= JAM_PULANG_SELESAI
) {

    // Checkout normal
}

// Setelah jam 17.00
else {

    // Tetap diperbolehkan checkout
}

// ========================================
// Simpan Jam Pulang
// ========================================

$stmt2 = $conn->prepare("
UPDATE presensi
SET jam_pulang = ?
WHERE id = ?
");

$stmt2->bind_param("si", $now_time, $row['id']);
$stmt2->execute();

header("Location: dashboard_karyawan.php?msg=checkout_ok");
exit;

?>
```
