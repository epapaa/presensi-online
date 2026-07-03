<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $redirect = ($_SESSION['role'] == 'admin') ? 'dashboard_admin.php' : 'dashboard_karyawan.php';
    header("Location: $redirect");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        // Cek di tabel admin
        $stmt = $conn->prepare("SELECT id, nama, username, password FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['username']  = $row['username'];
                $_SESSION['nama']      = $row['nama'];
                $_SESSION['role']      = 'admin';
                header("Location: dashboard_admin.php");
                exit;
            }
        }

        // Cek di tabel karyawan
        $stmt2 = $conn->prepare("SELECT id, nama, username, password FROM karyawan WHERE username = ?");
        $stmt2->bind_param("s", $username);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        if ($row2 = $res2->fetch_assoc()) {
            if (password_verify($password, $row2['password'])) {
                $_SESSION['user_id']   = $row2['id'];
                $_SESSION['username']  = $row2['username'];
                $_SESSION['nama']      = $row2['nama'];
                $_SESSION['role']      = 'karyawan';
                header("Location: dashboard_karyawan.php");
                exit;
            }
        }

        $error = "Username atau password salah.";
    } else {
        $error = "Isi semua kolom terlebih dahulu.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – Presensi Online</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <!-- <div class="logo-icon">📋</div> -->
      <span>Presensi Online</span>
    </div>

    <h2>Selamat Datang</h2>
    <p class="sub">Masuk untuk mencatat kehadiran Anda</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="Masukkan username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-primary">Masuk →</button>
    </form>
  </div>
</div>
</body>
</html>
