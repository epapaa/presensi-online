<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$msg = '';

// Tambah karyawan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'tambah') {
        $nama     = trim(htmlspecialchars($_POST['nama'] ?? ''));
        $username = trim(htmlspecialchars($_POST['username'] ?? ''));
        $jabatan  = trim(htmlspecialchars($_POST['jabatan'] ?? '');
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

        if (empty($nama) || empty($username) || empty($jabatan)) {
            $msg = 'error';
        } else {
        
        $stmt = $conn->prepare("INSERT INTO karyawan (nama, username, password, jabatan) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $nama, $username, $password, $jabatan);
        if ($stmt->execute()) {
            $msg = 'sukses';
        } else {
            $msg = 'error';
        }
    } 
    }
    if ($_POST['action'] == 'hapus') {
        $id = (int)$_POST['id'];
        $conn->prepare("DELETE FROM karyawan WHERE id = ?")->execute() || true;
        $stmt = $conn->prepare("DELETE FROM karyawan WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = 'hapus';
    }
}

$karyawan_list = $conn->query("SELECT * FROM karyawan ORDER BY nama")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Karyawan – Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon"></div>
      <span>Presensi</span>
    </div>
    <div class="sidebar-label">Menu Admin</div>
    <a href="dashboard_admin.php" class="nav-item">
      Rekap Presensi
    </a>
    <a href="kelola_karyawan.php" class="nav-item active">
      Kelola Karyawan
    </a>
    <div class="sidebar-footer">
      <div class="user-badge">
        <div class="avatar"><?= strtoupper(substr($_SESSION['nama'],0,1)) ?></div>
        <div class="info">
          <div class="name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
          <div class="role">Administrator</div>
        </div>
      </div>
      <a href="logout.php" class="btn-logout">↩ Keluar</a>
    </div>
  </aside>

  <main class="main">
    <div class="page-header">
      <h1>Kelola Karyawan</h1>
      <p>Tambah atau hapus akun karyawan</p>
    </div>

    <?php if ($msg == 'sukses'): ?>
      <div class="alert alert-success" style="margin-bottom:20px">Karyawan berhasil ditambahkan.</div>
    <?php elseif ($msg == 'hapus'): ?>
      <div class="alert alert-success" style="margin-bottom:20px">Karyawan berhasil dihapus.</div>
    <?php elseif ($msg == 'error'): ?>
      <div class="alert alert-danger" style="margin-bottom:20px">Gagal. Username mungkin sudah dipakai.</div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

      <!-- FORM TAMBAH -->
      <div class="card" style="padding:24px">
        <h3 style="margin-bottom:20px;font-size:15px">Tambah Karyawan Baru</h3>
        <form method="POST">
          <input type="hidden" name="action" value="tambah">
          <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="nama" placeholder="Contoh: Budi Santoso" required>
          </div>
          <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Contoh: budi" required>
          </div>
          <div class="form-group">
            <label>Jabatan</label>
            <input type="text" name="jabatan" placeholder="Contoh: Frontend Developer">
          </div>
          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Min. 6 karakter" required>
          </div>
          <button type="submit" class="btn-primary">+ Tambah Karyawan</button>
        </form>
      </div>

      <!-- DAFTAR KARYAWAN -->
      <div class="card">
        <div class="card-header">
          <h3>Daftar Karyawan</h3>
          <span style="font-size:13px;color:var(--muted)"><?= count($karyawan_list) ?> orang</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Nama</th><th>Jabatan</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($karyawan_list as $k): ?>
              <tr>
                <td>
                  <div style="font-weight:600"><?= htmlspecialchars($k['nama']) ?></div>
                  <div style="font-size:12px;color:var(--muted)">@<?= htmlspecialchars($k['username']) ?></div>
                </td>
                <td style="color:var(--muted);font-size:13px"><?= htmlspecialchars($k['jabatan'] ?? '–') ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Hapus karyawan ini?')">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                    <button type="submit" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:13px;font-weight:600">Hapus</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
</body>
</html>
