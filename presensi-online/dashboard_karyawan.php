<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'karyawan') {
    header("Location: login.php");
    exit;
}

$karyawan_id = $_SESSION['user_id'];
$today       = date('Y-m-d');
$now_time    = date('H:i:s');

// Data karyawan lengkap (termasuk jabatan)
$stmt0 = $conn->prepare("SELECT * FROM karyawan WHERE id = ?");
$stmt0->bind_param("i", $karyawan_id);
$stmt0->execute();
$karyawan_data = $stmt0->get_result()->fetch_assoc();

// Data presensi hari ini
$stmt = $conn->prepare("SELECT * FROM presensi WHERE karyawan_id = ? AND tanggal = ?");
$stmt->bind_param("is", $karyawan_id, $today);
$stmt->execute();
$today_row = $stmt->get_result()->fetch_assoc();

// Riwayat 30 hari terakhir
$stmt2 = $conn->prepare("SELECT * FROM presensi WHERE karyawan_id = ? ORDER BY tanggal DESC LIMIT 30");
$stmt2->bind_param("i", $karyawan_id);
$stmt2->execute();
$history = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats bulan ini
$bulan_ini = date('Y-m');
$stmt3 = $conn->prepare("SELECT
    COUNT(*) AS total,
    SUM(predikat='hadir') AS hadir,
    SUM(predikat='telat') AS telat
  FROM presensi WHERE karyawan_id = ? AND DATE_FORMAT(tanggal,'%Y-%m') = ?");
$stmt3->bind_param("is", $karyawan_id, $bulan_ini);
$stmt3->execute();
$stats = $stmt3->get_result()->fetch_assoc();

// Logika window absen
$bisa_masuk = (
    $now_time >= JAM_MASUK_MULAI &&
    $now_time <= JAM_MASUK_TERLAMBAT
);

$is_ontime = (
    $now_time <= JAM_MASUK_ONTIME
);
$bisa_pulang = (
    $now_time >= JAM_PULANG_MULAI
);

// Notifikasi
$msg       = $_GET['msg']      ?? '';
$predikat  = $_GET['predikat'] ?? '';
$menit     = (int)($_GET['menit'] ?? 0);

$nama_depan = explode(' ', $_SESSION['nama'])[0];
$jabatan    = $karyawan_data['jabatan'] ?? 'Karyawan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard – <?= htmlspecialchars($_SESSION['nama']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ===== NOTIF ABSEN SUKSES ===== */
    .notif-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.45);
      z-index: 999;
      align-items: center;
      justify-content: center;
    }
    .notif-overlay.show { display: flex; }

    .notif-card {
      background: #fff;
      border-radius: 20px;
      padding: 40px 36px;
      max-width: 420px;
      width: 90%;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,.2);
      animation: popIn .3s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes popIn { from{transform:scale(.85);opacity:0} to{transform:scale(1);opacity:1} }

    .notif-icon {
      font-size: 56px;
      margin-bottom: 12px;
      line-height: 1;
    }
    .notif-card h2 {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .notif-card .notif-nama {
      font-size: 16px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 2px;
    }
    .notif-card .notif-jabatan {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 20px;
    }
    .notif-status-box {
      border-radius: 12px;
      padding: 16px 20px;
      margin-bottom: 20px;
    }
    .notif-status-box.sukses { background: var(--green-bg); border: 2px solid #86EFAC; }
    .notif-status-box.gagal  { background: var(--red-bg);   border: 2px solid #FCA5A5; }
    .notif-status-box .label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing:.05em; margin-bottom: 4px; }
    .notif-status-box.sukses .label { color: var(--green); }
    .notif-status-box.gagal  .label { color: var(--red); }
    .notif-status-box .nilai { font-size: 22px; font-weight: 700; }
    .notif-status-box.sukses .nilai { color: var(--green); }
    .notif-status-box.gagal  .nilai { color: var(--red); }
    .notif-status-box .sub   { font-size: 13px; color: var(--muted); margin-top: 2px; }

    .notif-time { font-size: 13px; color: var(--muted); margin-bottom: 20px; }

    .btn-notif-close {
      padding: 11px 32px;
      border: none;
      border-radius: 9px;
      font-size: 15px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: all .15s;
    }
    .btn-notif-close.sukses { background: var(--green); color: #fff; }
    .btn-notif-close.sukses:hover { background: #15803D; }
    .btn-notif-close.gagal  { background: var(--red); color: #fff; }
    .btn-notif-close.gagal:hover  { background: #B91C1C; }
    .logout-modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.logout-modal.show{
    display:flex;
}

.logout-box{
    background:#fff;
    width:400px;
    max-width:90%;
    border-radius:18px;
    padding:30px;
    text-align:center;
    box-shadow:0 20px 60px rgba(0,0,0,.2);
    animation:popIn .25s ease;
}

.logout-icon{
    font-size:60px;
    margin-bottom:10px;
}

.logout-box h2{
    margin-bottom:8px;
}

.logout-box p{
    color:#666;
    margin-bottom:25px;
}

.logout-action{
    display:flex;
    gap:12px;
}

.logout-action button,
.logout-action a{
    flex:1;
    border:none;
    padding:12px;
    border-radius:10px;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    text-align:center;
}

.btn-batal{
    background:#E5E7EB;
    color:#333;
}

.btn-keluar{
    background:#DC2626;
    color:#fff;
}

.btn-keluar:hover{
    background:#B91C1C;
}
  </style>
</head>
<body>

<?php if ($msg == 'checkin_ok'): ?>
<!-- NOTIF ABSEN MASUK -->
<div class="notif-overlay show" id="notifOverlay">
  <div class="notif-card">
    <div class="notif-icon"><?= $predikat == 'hadir' ? '✅' : '⚠️' ?></div>
    <h2>Absen Masuk Berhasil</h2>
    <div class="notif-nama"><?= htmlspecialchars($_SESSION['nama']) ?></div>
    <div class="notif-jabatan"><?= htmlspecialchars($jabatan) ?></div>

    <div class="notif-status-box <?= $predikat == 'hadir' ? 'sukses' : 'gagal' ?>">
      <div class="label">Status Kehadiran</div>
      <?php if ($predikat == 'hadir'): ?>
        <div class="nilai">Tepat Waktu</div>
        <div class="sub">Kamu hadir tepat waktu sebelum jam 09.10</div>
      <?php else: ?>
        <div class="nilai">Terlambat <?= $menit ?> menit</div>
        <div class="sub">Absen diterima sebagai hadir (telat)</div>
      <?php endif; ?>
    </div>

    <div class="notif-time"> <?= date('l, d F Y') ?> &nbsp;•&nbsp; <?= date('H:i') ?></div>
    <button class="btn-notif-close <?= $predikat == 'hadir' ? 'sukses' : 'gagal' ?>" onclick="tutupNotif()">
      <?= $predikat == 'hadir' ? 'Lanjutkan →' : 'Oke, Mengerti' ?>
    </button>
  </div>
</div>
<?php endif; ?>

<?php if ($msg == 'checkout_ok'): ?>
<!-- NOTIF ABSEN PULANG -->
<div class="notif-overlay show" id="notifOverlay">
  <div class="notif-card">
    <div class="notif-icon">🏠</div>
    <h2>Absen Pulang Berhasil</h2>
    <div class="notif-nama"><?= htmlspecialchars($_SESSION['nama']) ?></div>
    <div class="notif-jabatan"><?= htmlspecialchars($jabatan) ?></div>

    <div class="notif-status-box sukses">
      <div class="label">Status Kepulangan</div>
      <div class="nilai">Berhasil Dicatat</div>
      <div class="sub">Selamat beristirahat, sampai besok! 👋</div>
    </div>

    <div class="notif-time">📅 <?= date('l, d F Y') ?> &nbsp;•&nbsp; ⏰ <?= date('H:i') ?></div>
    <button class="btn-notif-close sukses" onclick="tutupNotif()">Oke, Sampai Besok!</button>
  </div>
</div>
<?php endif; ?>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <!-- <div class="logo-icon">📋</div> -->
      <span>Presensi</span>
    </div>
    <div class="sidebar-label">Menu</div>
    <a href="dashboard_karyawan.php" class="nav-item active">
      <!-- <span class="icon">🏠</span> Dashboard -->
    </a>
    <div class="sidebar-footer">
      <div class="user-badge">
        <div class="avatar"><?= strtoupper(substr($_SESSION['nama'],0,1)) ?></div>
        <div class="info">
          <div class="name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
          <div class="role"><?= htmlspecialchars($jabatan) ?></div>
        </div>
      </div>
      <a href="#" class="btn-logout" onclick="openLogoutModal(); return false;">
    ↩ Keluar
</a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="page-header">
      <h1>Halo, <?= htmlspecialchars($nama_depan) ?> </h1>
      <p><?= date('l, d F Y') ?></p>
    </div>

    <?php if ($msg == 'sudah_masuk'): ?>
      <div class="alert" style="background:var(--blue-bg);border:1px solid #BFDBFE;color:var(--primary);margin-bottom:20px">ℹ️ Kamu sudah absen masuk hari ini.</div>
    <?php elseif ($msg == 'sudah_pulang'): ?>
      <div class="alert" style="background:var(--blue-bg);border:1px solid #BFDBFE;color:var(--primary);margin-bottom:20px">ℹ️ Kamu sudah absen pulang hari ini.</div>
    <?php elseif ($msg == 'belum_masuk'): ?>
      <div class="alert alert-danger" style="margin-bottom:20px">Lakukan absen masuk terlebih dahulu.</div>
    <?php elseif ($msg == 'alpha'): ?>
      <div class="alert alert-danger" style="margin-bottom:20px">Waktu absen masuk telah berakhir. Status hari ini <b>ALPHA</b>.</div>
    <?php elseif ($msg == 'belum_waktunya'): ?>
      <div class="alert" style="background:#FFF7ED;border:1px solid #FCD34D;color:#B45309;margin-bottom:20px">⏰ Belum waktunya melakukan absen.</div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="label">Hadir Bulan Ini</div>
        <div class="value green"><?= $stats['hadir'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Telat Bulan Ini</div>
        <div class="value yellow"><?= $stats['telat'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Total Absen</div>
        <div class="value blue"><?= $stats['total'] ?? 0 ?></div>
      </div>
    </div>

    <!-- ABSEN CARDS -->
    <div class="absen-grid">
      <!-- ABSEN MASUK -->
      <div class="absen-card">
        <div class="card-head">
          <h3>Absen Masuk</h3>
          <div class="time-window"> 08:30 – 11:10</div>
        </div>
        <div class="clock-display">
          <div class="time" id="jam">--:--:--</div>
          <div class="date"><?= date('d F Y') ?></div>
        </div>
        <?php if ($today_row && $today_row['jam_masuk']): ?>
          <div class="absen-done-info">
            <div>Absen masuk tercatat</div>
            <div class="time-done"><?= substr($today_row['jam_masuk'], 0, 5) ?></div>
            <div class="predikat">
              <?php if ($today_row['predikat'] == 'hadir'): ?>
                <span class="badge hadir"> Hadir Tepat Waktu</span>
              <?php else: ?>
                <span class="badge telat"> Telat <?= $today_row['menit_telat'] ?? 0 ?> menit</span>
              <?php endif; ?>
            </div>
          </div>
        <?php elseif ($bisa_masuk): ?>
          
          <div style="text-align:center">
            <span class="status-badge <?= $is_ontime ? 'open' : 'late' ?>">
              <span class="dot"></span> <?= $is_ontime ? 'Ontime' : 'Terlambat' ?>
            </span>
          </div>
          <form method="POST" action="checkin.php">
            <button type="submit" class="btn-absen masuk">Absen Masuk Sekarang</button>
          </form>
        <?php else: ?>
          <div style="text-align:center">
            <span class="status-badge closed"><span class="dot"></span> Belum Waktunya</span>
          </div>
          <button class="btn-absen masuk" disabled>Absen Masuk (Mulai 08:30)</button>
        <?php endif; ?>
      </div>

      <!-- ABSEN PULANG -->
      <div class="absen-card">
        <div class="card-head">
          <h3>Absen Pulang</h3>
          <div class="time-window"> 16:30 – 17:00</div>
        </div>
        <div class="clock-display">
          <div class="time" id="jam2">--:--:--</div>
          <div class="date"><?= date('d F Y') ?></div>
        </div>
        <?php if ($today_row && $today_row['jam_pulang']): ?>
          <div class="absen-done-info">
            <div>Absen pulang tercatat</div>
            <div class="time-done"><?= substr($today_row['jam_pulang'], 0, 5) ?></div>
          </div>
        <?php elseif (!$today_row || !$today_row['jam_masuk']): ?>
          <div style="text-align:center">
            <span class="status-badge closed"><span class="dot"></span> Belum Absen Masuk</span>
          </div>
          <button class="btn-absen pulang" disabled>Absen Pulang (Masuk dulu)</button>
        <?php elseif ($bisa_pulang): ?>
          <div style="text-align:center">
            <span class="status-badge open"><span class="dot"></span> Siap Pulang</span>
          </div>
          <form method="POST" action="checkout.php">
            <button type="submit" class="btn-absen pulang">Absen Pulang Sekarang</button>
          </form>
        <?php else: ?>
          <div style="text-align:center">
            <span class="status-badge closed">
              <span class="dot"></span>
              <?= ($now_time < JAM_PULANG_MULAI)
    ? 'Belum Waktunya'
    : 'Checkout Tersedia' ?>
            </span>
          </div>
          <button class="btn-absen pulang" disabled>Absen Pulang (Mulai 16:30)</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIWAYAT -->
    <div class="card">
      <div class="card-header">
        <h3>Riwayat Kehadiran</h3>
        <span style="font-size:13px;color:var(--muted)">30 hari terakhir</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Jam Masuk</th>
              <th>Jam Pulang</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($history)): ?>
              <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:32px">Belum ada data kehadiran</td></tr>
            <?php else: ?>
              <?php foreach ($history as $h): ?>
              <tr>
                <td><?= date('d M Y', strtotime($h['tanggal'])) ?></td>
                <td><?= $h['jam_masuk'] ? substr($h['jam_masuk'],0,5) : '<span style="color:var(--muted)">–</span>' ?></td>
                <td><?= $h['jam_pulang'] ? substr($h['jam_pulang'],0,5) : '<span style="color:var(--muted)">–</span>' ?></td>
                <td>
                  <?php $p = $h['predikat'] ?? 'alpha'; ?>
                  <?php if ($p == 'hadir'): ?>
                    <span class="badge hadir"> Hadir</span>
                  <?php elseif ($p == 'telat'): ?>
                    <span class="badge telat"> Telat <?= $h['menit_telat'] ?? 0 ?> mnt</span>
                  <?php elseif ($p == 'alpha'): ?>
                    <span class="badge alpha"> Alpha</span>
                  <?php else: ?>
                    <span class="badge izin"> Izin</span>
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


<!-- MODAL LOGOUT -->
<div id="logoutModal" class="logout-modal">
  <div class="logout-box">
    <!-- <div class="logout-icon">⚠️</div> -->
    <h2>Konfirmasi Logout</h2>
    <p>Apakah Anda yakin ingin keluar dari sistem presensi?</p>
    <div class="logout-action">
      <button class="btn-batal" onclick="closeLogoutModal()">Batal</button>
      <a href="logout.php" class="btn-keluar">Ya, Keluar</a>
    </div>
  </div>
</div>

<script>
function updateClock() {
  const now = new Date();
  const t = now.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
  document.getElementById('jam').textContent  = t;
  document.getElementById('jam2').textContent = t;
}
updateClock();
setInterval(updateClock, 1000);

function tutupNotif() {
  document.getElementById('notifOverlay').classList.remove('show');
  // Bersihkan URL dari parameter notif
  history.replaceState({}, '', 'dashboard_karyawan.php');
}


function openLogoutModal(){
  document.getElementById('logoutModal').classList.add('show');
}

function closeLogoutModal(){
  document.getElementById('logoutModal').classList.remove('show');
}

window.onclick = function(e){
  const modal=document.getElementById('logoutModal');
  if(e.target===modal){
    closeLogoutModal();
  }
}
</script>
</body>
</html>
