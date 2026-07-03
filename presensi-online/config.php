<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "presensi_online";

$conn = new mysqli($host, $user, $pass, $db);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

session_start();

date_default_timezone_set('Asia/Jakarta');


define('JAM_MASUK_MULAI',      '08:30:00');
define('JAM_MASUK_ONTIME',     '09:10:59');
define('JAM_MASUK_TERLAMBAT',  '11:10:59');

define('JAM_PULANG_MULAI',     '16:30:00');
define('JAM_PULANG_SELESAI',   '17:00:00');
?>
