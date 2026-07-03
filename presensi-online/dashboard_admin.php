<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$today   = date('Y-m-d');
$bulan   = $_GET['bulan']    ?? date('Y-m');
$cari    = $_GET['cari']     ?? '';
$predikat = $_GET['predikat'] ?? '';
$view    = $_GET['view']     ?? 'rekap'; // rekap | karyawan

// Stats hari ini
$stmt = $conn->prepare("SELECT
    COUNT(*) AS total,
    SUM(predikat='hadir') AS hadir,
    SUM(predikat='telat') AS telat,
    SUM(jam_masuk IS NULL) AS alpha
  FROM presensi WHERE tanggal = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$today_stats = $stmt->get_result()->fetch_assoc();

$total_karyawan = $conn->query("SELECT COUNT(*) as n FROM karyawan")->fetch_assoc()['n'];

// Data rekap
$where  = ["DATE_FORMAT(p.tanggal,'%Y-%m') = ?"];
$params = [$bulan];
$types  = "s";
if ($cari) { $where[] = "k.nama LIKE ?"; $params[] = "%$cari%"; $types .= "s"; }
if ($predikat) { $where[] = "p.predikat = ?"; $params[] = $predikat; $types .= "s"; }

$sql = "SELECT p.*, k.nama, k.username, k.jabatan
  FROM presensi p JOIN karyawan k ON p.karyawan_id = k.id
  WHERE " . implode(' AND ', $where) . " ORDER BY p.tanggal DESC, k.nama ASC";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param($types, ...$params);
$stmt2->execute();
$rekap = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary per karyawan bulan ini
$stmt3 = $conn->prepare("SELECT k.id, k.nama, k.jabatan, k.username,
    SUM(p.predikat='hadir') as hadir,
    SUM(p.predikat='telat') as telat,
    SUM(p.predikat='alpha') as alpha,
    SUM(p.menit_telat) as total_menit,
    COUNT(p.id) as total
  FROM karyawan k
  LEFT JOIN presensi p ON k.id = p.karyawan_id AND DATE_FORMAT(p.tanggal,'%Y-%m') = ?
  GROUP BY k.id ORDER BY k.nama");
$stmt3->bind_param("s", $bulan);
$stmt3->execute();
$summary = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – Presensi Online</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <!-- <div class="logo-icon"></div> -->
      <span>Presensi</span>
    </div>
    <div class="sidebar-label">Menu Admin</div>
    <a href="dashboard_admin.php" class="nav-item active">Rekap Presensi</a>
    <a href="kelola_karyawan.php" class="nav-item">Kelola Karyawan</a>
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
      <h1>Rekap Presensi</h1>
      <p>Pantau kehadiran seluruh karyawan</p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card"><div class="label">Total Karyawan</div><div class="value blue"><?= (int)$total_karyawan ?></div></div>
      <div class="stat-card"><div class="label">Hadir Hari Ini</div><div class="value green"><?= $today_stats['hadir'] ?? 0 ?></div></div>
      <div class="stat-card"><div class="label">Telat Hari Ini</div><div class="value yellow"><?= $today_stats['telat'] ?? 0 ?></div></div>
      <div class="stat-card"><div class="label">Alpha Hari Ini</div><div class="value red"><?= $today_stats['alpha'] ?? 0 ?></div></div>
    </div>

    <!-- TAB SWITCH -->
    <div style="display:flex;gap:8px;margin-bottom:20px">
      <a href="?view=rekap&bulan=<?= $bulan ?>"
         style="padding:9px 18px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;border:1.5px solid var(--border);
         <?= $view=='rekap' ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : 'color:var(--muted);background:#fff' ?>">
          Log Absen
      </a>
      <a href="?view=karyawan&bulan=<?= $bulan ?>"
         style="padding:9px 18px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;border:1.5px solid var(--border);
         <?= $view=='karyawan' ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : 'color:var(--muted);background:#fff' ?>">
          Per Karyawan
      </a>
    </div>

    <?php if ($view == 'karyawan'): ?>
    <!-- VIEW PER KARYAWAN -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <form method="GET" style="display:flex;gap:10px;align-items:center">
        <input type="hidden" name="view" value="karyawan">
        <input type="month" name="bulan" value="<?= $bulan ?>" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:14px;outline:none">
        <button type="submit" class="btn-primary" style="width:auto;padding:9px 18px;margin:0">Lihat</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>Ringkasan Per Karyawan – <?= date('F Y', strtotime($bulan.'-01')) ?></h3>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Karyawan</th>
              <th>Hadir</th>
              <th>Telat</th>
              <th>Alpha</th>
              <th>Total Telat</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($summary as $s): ?>
            <tr>
              <td>
                <div style="font-weight:600"><?= htmlspecialchars($s['nama']) ?></div>
                <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($s['jabatan'] ?? '–') ?></div>
              </td>
              <td><span style="color:var(--green);font-weight:600"><?= $s['hadir'] ?? 0 ?></span></td>
              <td><span style="color:var(--yellow);font-weight:600"><?= $s['telat'] ?? 0 ?></span></td>
              <td><span style="color:var(--red);font-weight:600"><?= $s['alpha'] ?? 0 ?></span></td>
              <td>
                <?php if ($s['total_menit'] > 0): ?>
                  <span style="color:var(--yellow);font-weight:600"><?= $s['total_menit'] ?> mnt</span>
                <?php else: ?>
                  <span style="color:var(--muted)">–</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="rekap_karyawan.php?id=<?= $s['id'] ?>&bulan=<?= $bulan ?>"
                   style="padding:5px 14px;background:var(--blue-bg);color:var(--primary);border-radius:6px;font-size:13px;font-weight:600;text-decoration:none">
                  Lihat →
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php else: ?>
    <!-- VIEW LOG ABSEN -->
    <form method="GET" class="filter-bar">
      <input type="hidden" name="view" value="rekap">
      <input type="month" name="bulan" value="<?= htmlspecialchars($bulan) ?>">
      <input type="text" name="cari" placeholder="🔍 Cari nama..." value="<?= htmlspecialchars($cari) ?>" style="min-width:180px">
      <select name="predikat">
        <option value="">Semua Status</option>
        <option value="hadir"  <?= $predikat=='hadir'  ?'selected':'' ?>>Hadir</option>
        <option value="telat"  <?= $predikat=='telat'  ?'selected':'' ?>>Telat</option>
        <option value="alpha"  <?= $predikat=='alpha'  ?'selected':'' ?>>Alpha</option>
      </select>
      <button type="submit" class="btn-primary" style="width:auto;padding:9px 18px;margin:0">Filter</button>
      <a href="dashboard_admin.php" style="padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;color:var(--muted);text-decoration:none">Reset</a>
    </form>

    <div class="card">
      <div class="card-header">
        <h3>Log Kehadiran – <?= date('F Y', strtotime($bulan.'-01')) ?></h3>
        <span style="font-size:13px;color:var(--muted)"><?= count($rekap) ?> record</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Tanggal</th><th>Nama Karyawan</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Telat</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if (empty($rekap)): ?>
              <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">Tidak ada data</td></tr>
            <?php else: ?>
              <?php foreach ($rekap as $i => $r): ?>
              <tr>
                <td style="color:var(--muted)"><?= $i+1 ?></td>
                <td><?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?></td>
                <td>
                  <a href="rekap_karyawan.php?id=<?= $r['karyawan_id'] ?>&bulan=<?= $bulan ?>" style="font-weight:600;color:var(--primary);text-decoration:none"><?= htmlspecialchars($r['nama']) ?></a>
                  <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['jabatan'] ?? '') ?></div>
                </td>
                <td><?= $r['jam_masuk'] ? substr($r['jam_masuk'],0,5) : '<span style="color:var(--muted)">–</span>' ?></td>
                <td><?= $r['jam_pulang'] ? substr($r['jam_pulang'],0,5) : '<span style="color:var(--muted)">–</span>' ?></td>
                <td>
                  <?php if (($r['menit_telat'] ?? 0) > 0): ?>
                    <span style="color:var(--yellow);font-weight:600"><?= $r['menit_telat'] ?> mnt</span>
                  <?php else: ?><span style="color:var(--muted)">–</span><?php endif; ?>
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
    <?php endif; ?>

  </main>
</div>
</body>
</html>
