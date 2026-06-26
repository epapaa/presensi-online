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

// ============================================
// Cek apakah sudah absen hari ini
// ============================================

$stmt = $conn->prepare("
SELECT id
FROM presensi
WHERE karyawan_id = ?
AND tanggal = ?
AND jam_masuk IS NOT NULL
");

$stmt->bind_param("is", $karyawan_id, $today);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    header("Location: dashboard_karyawan.php?msg=sudah_masuk");
    exit;
}

// ============================================
// Menentukan status presensi
// ============================================

// Ontime
if (
    $now_time >= JAM_MASUK_MULAI &&
    $now_time <= JAM_MASUK_ONTIME
) {

    $predikat = "hadir";
    $menit_telat = 0;
}

// Terlambat
elseif (
    $now_time > JAM_MASUK_ONTIME &&
    $now_time <= JAM_MASUK_TERLAMBAT
) {

    $predikat = "telat";

    $batas_ts = strtotime(date('Y-m-d') . " " . JAM_MASUK_ONTIME);

    $now_ts = strtotime(date('Y-m-d') . " " . $now_time);

    $menit_telat = floor(($now_ts - $batas_ts) / 60);
}

// Alpha (lebih dari jam 11.10)
else {

    header("Location: dashboard_karyawan.php?msg=alpha");
    exit;
}

// ============================================
// Simpan ke database
// ============================================

$stmt4 = $conn->prepare("
INSERT INTO presensi
(
    karyawan_id,
    tanggal,
    jam_masuk,
    predikat,
    menit_telat
)
VALUES
(
    ?,
    ?,
    ?,
    ?,
    ?
)
");

$stmt4->bind_param(
    "isssi",
    $karyawan_id,
    $today,
    $now_time,
    $predikat,
    $menit_telat
);

$stmt4->execute();

header("Location: dashboard_karyawan.php?msg=checkin_ok&predikat=$predikat&menit=$menit_telat");
exit;
?>
```
