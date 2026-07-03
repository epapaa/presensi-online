<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: dashboard_admin.php"); exit; }

// Data karyawan
$stmt = $conn->prepare("SELECT * FROM karyawan WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$karyawan = $stmt->get_result()->fetch_assoc();
if (!$karyawan) { header("Location: dashboard_admin.php"); exit; }

// Filter bulan
$bulan = $_GET['bulan'] ?? date('Y-m');

// Rekap 30 hari
$stmt2 = $conn->prepare("SELECT * FROM presensi WHERE karyawan_id = ? AND DATE_FORMAT(tanggal,'%Y-%m') = ? ORDER BY tanggal DESC");
$stmt2->bind_param("is", $id, $bulan);
$stmt2->execute();
$rekap = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats bulan ini
$stmt3 = $conn->prepare("SELECT
    COUNT(*) as total,
    SUM(predikat='hadir') as hadir,
    SUM(predikat='telat') as telat,
    SUM(predikat='alpha') as alpha,
    SUM(menit_telat) as total_menit_telat
  FROM presensi WHERE karyawan_id = ? AND DATE_FORMAT(tanggal,'%Y-%m') = ?");
$stmt3->bind_param("is", $id, $bulan);
$stmt3->execute();
$stats = $stmt3->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rekap – <?= htmlspecialchars($karyawan['nama']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <span>Presensi</span>
    </div>
    <div class="sidebar-label">Menu Admin</div>
    <a href="dashboard_admin.php" class="nav-item active"><span class="icon"></span> Rekap Presensi</a>
    <a href="kelola_karyawan.php" class="nav-item"><span class="icon"></span> Kelola Karyawan</a>
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
    <div style="margin-bottom:20px">
      <a href="dashboard_admin.php" style="font-size:13px;color:var(--muted);text-decoration:none">Kembali ke Rekap</a>
    </div>

    <!-- PROFILE KARYAWAN -->
    <div class="card" style="padding:24px;margin-bottom:24px">
      <div style="display:flex;align-items:center;gap:16px">
        <div style="width:52px;height:52px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:700;flex-shrink:0">
          <?= strtoupper(substr($karyawan['nama'],0,1)) ?>
        </div>
        <div>
          <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($karyawan['nama']) ?></div>
          <div style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($karyawan['jabatan'] ?? 'Karyawan') ?> &nbsp;•&nbsp; @<?= htmlspecialchars($karyawan['username']) ?></div>
        </div>
        <form method="GET" style="margin-left:auto;display:flex;gap:10px;align-items:center">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="month" name="bulan" value="<?= $bulan ?>" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:14px;outline:none">
          <button type="submit" class="btn-primary" style="width:auto;padding:9px 18px;margin:0">Lihat</button>
        </form>
      </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid" style="margin-bottom:24px">
      <div class="stat-card">
        <div class="label">Total Hadir</div>
        <div class="value green"><?= $stats['hadir'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Total Telat</div>
        <div class="value yellow"><?= $stats['telat'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Alpha</div>
        <div class="value red"><?= $stats['alpha'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Total Menit Telat</div>
        <div class="value yellow"><?= $stats['total_menit_telat'] ?? 0 ?> mnt</div>
      </div>
    </div>

    <!-- TABEL DETAIL -->
    <div class="card">
      <div class="card-header">
        <h3>Detail Kehadiran – <?= date('F Y', strtotime($bulan.'-01')) ?></h3>
        <span style="font-size:13px;color:var(--muted)"><?= count($rekap) ?> hari</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Hari</th>
              <th>Jam Masuk</th>
              <th>Jam Pulang</th>
              <th>Telat</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rekap)): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px">Tidak ada data bulan ini</td></tr>
            <?php else: ?>
              <?php foreach ($rekap as $r): ?>
              <tr>
                <td><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                <td style="color:var(--muted);font-size:13px"><?= date('l', strtotime($r['tanggal'])) ?></td>
                <td><?= $r['jam_masuk'] ? substr($r['jam_masuk'],0,5) : '<span style="color:var(--muted)">–</span>' ?></td>
                <td><?= $r['jam_pulang'] ? substr($r['jam_pulang'],0,5) : '<span style="color:var(--muted)">–</span>' ?></td>
                <td>
                  <?php if ($r['menit_telat'] > 0): ?>
                    <span style="color:var(--yellow);font-weight:600"><?= $r['menit_telat'] ?> mnt</span>
                  <?php else: ?>
                    <span style="color:var(--muted)">–</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $p = $r['predikat'] ?? 'alpha'; ?>
                  <?php if ($p=='hadir'): ?><span class="badge hadir">Hadir</span>
                  <?php elseif ($p=='telat'): ?><span class="badge telat">Telat</span>
                  <?php elseif ($p=='alpha'): ?><span class="badge alpha">Alpha</span>
                  <?php else: ?><span class="badge izin">Izin</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
