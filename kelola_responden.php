<?php

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
  echo '<div class="alert alert-danger">Koneksi database tidak tersedia. Pastikan ../config/koneksi.php benar.</div>';
  return;
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// redirect aman walau headers sudah terkirim
function redirect_to($url){
  if (!headers_sent()) { header("Location: $url"); exit; }
  echo "<script>window.location.href=".json_encode($url).";</script>";
  echo "<noscript><meta http-equiv='refresh' content='0;url=".htmlspecialchars($url, ENT_QUOTES, 'UTF-8')."'></noscript>";
  exit;
}

function flash($type, $msg){
  $_SESSION['toast'] = ['type'=>$type, 'message'=>$msg];
}

// ===== CSRF =====
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

// Tanggal TANPA jam
function fmt_date_only($s){
  $s = (string)$s;
  if ($s === '') return '-';
  $t = strtotime($s);
  return $t ? date('d/m/Y', $t) : e($s);
}

/* =========================
   ROLE GUARD (BASIC DITOLAK TOTAL)
========================= */
$adminUser = '';
if (isset($_SESSION['admin'])) {
  $adminUser = is_array($_SESSION['admin'])
    ? (string)($_SESSION['admin']['username'] ?? '')
    : (string)$_SESSION['admin'];
}

$currentRole = 'basic';
if ($adminUser !== '') {
  $stR = $koneksi->prepare("SELECT role FROM admin WHERE username=? LIMIT 1");
  $stR->bind_param("s", $adminUser);
  $stR->execute();
  $rowR = $stR->get_result()->fetch_assoc();
  $stR->close();
  if ($rowR && isset($rowR['role'])) $currentRole = (string)$rowR['role'];
}

// Kalau belum login admin sama sekali -> tendang ke login (opsional, biar aman)
if ($adminUser === '') {
  flash('danger', 'Silakan login terlebih dahulu.');
  redirect_to("login.php");
}

// INI INTINYA: BASIC NGGAK BOLEH LIAT MENU INI
if ($currentRole === 'basic') {
  flash('danger', 'Akses ditolak. Role basic tidak memiliki izin membuka Kelola Responden.');
  redirect_to("dashboard.php"); // kalau mau spesifik: dashboard.php?menu=home
}

$canDelete = ($currentRole === 'superadmin');

/* =========================
   EXPORT CSV RAPI (1 FILE)
   trigger: dashboard.php?menu=kelola_responden&export=csv[&q=...]
   delimiter ";" + BOM UTF-8 + tanggal dd/mm/YYYY
   Urutan: ASC
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

  $search = trim((string)($_GET['q'] ?? ''));
  $where = "1=1";
  $like  = null;

  if ($search !== '') {
    $where .= " AND (
      nama LIKE ? OR
      jenis_kelamin LIKE ? OR
      jenis_pengguna LIKE ? OR
      pekerjaan LIKE ? OR
      lama_menggunakan LIKE ? OR
      DATE_FORMAT(tanggal, '%Y-%m-%d') LIKE ?
    )";
    $like = "%{$search}%";
  }

  $sqlCSV = "SELECT id_responden, nama, jenis_kelamin, jenis_pengguna, tanggal, pekerjaan, lama_menggunakan
             FROM responden
             WHERE $where
             ORDER BY id_responden ASC";

  $stCSV = $koneksi->prepare($sqlCSV);
  if ($search !== '') {
    $stCSV->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
  }
  $stCSV->execute();
  $rsCSV = $stCSV->get_result();

  $fname = "data_responden_" . date('Ymd_His') . ".csv";

  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Pragma: no-cache');
  header('Expires: 0');

  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF"); // BOM biar Excel kebaca UTF-8
  $delim = ';';

  fputs($out, "DATA RESPONDEN{$delim}{$delim}{$delim}{$delim}{$delim}{$delim}\r\n");
  fputs($out, "Filter{$delim}" . ($search !== '' ? str_replace(["\r","\n"], ' ', $search) : '-') . "{$delim}{$delim}{$delim}{$delim}{$delim}\r\n");
  fputs($out, "\r\n");

  $headers = ['Nomor','Nama','Jenis Kelamin','Jenis Pengguna','Tanggal','Pekerjaan','Lama Menggunakan'];
  fputcsv($out, $headers, $delim);

  $no = 0;
  while ($r = $rsCSV->fetch_assoc()) {
    $no++;

    $tglRaw = (string)($r['tanggal'] ?? '');
    $tgl = '-';
    if ($tglRaw !== '') {
      $ts = strtotime($tglRaw);
      $tgl = $ts ? date('d/m/Y', $ts) : $tglRaw;
    }

    $nama = str_replace(["\r","\n"], ' ', (string)($r['nama'] ?? ''));
    $jk   = str_replace(["\r","\n"], ' ', (string)($r['jenis_kelamin'] ?? ''));
    $jp   = str_replace(["\r","\n"], ' ', (string)($r['jenis_pengguna'] ?? ''));
    $pkj  = str_replace(["\r","\n"], ' ', (string)($r['pekerjaan'] ?? ''));
    $lm   = str_replace(["\r","\n"], ' ', (string)($r['lama_menggunakan'] ?? ''));

    fputcsv($out, [(string)$no, $nama, $jk, $jp, $tgl, $pkj, $lm], $delim);
  }

  fclose($out);
  $stCSV->close();
  exit;
}

/* =========================
   HANDLE POST (DELETE ONLY) - superadmin saja
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token  = $_POST['csrf'] ?? '';

  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    flash('danger', 'Token tidak valid (CSRF).');
    $q = trim((string)($_GET['q'] ?? ''));
    $p = max(1, (int)($_GET['page'] ?? 1));
    $url = "dashboard.php?menu=kelola_responden";
    if ($q !== '') $url .= "&q=" . urlencode($q);
    if ($p > 1)   $url .= "&page=" . $p;
    redirect_to($url);
  }

  if ($action === 'delete') {
    if (!$canDelete) {
      flash('danger', 'Akses ditolak. Hapus hanya untuk superadmin.');
      redirect_to("dashboard.php?menu=kelola_responden");
    }

    $id = (int)($_POST['id_responden'] ?? 0);

    if ($id <= 0) {
      flash('danger', 'ID responden tidak valid.');
    } else {
      $koneksi->begin_transaction();
      try {
        $st1 = $koneksi->prepare("DELETE FROM jawaban WHERE id_responden=?");
        $st1->bind_param("i", $id);
        if (!$st1->execute()) throw new Exception("Gagal hapus jawaban: " . ($st1->error ?: $koneksi->error));
        $deletedJawaban = $st1->affected_rows;
        $st1->close();

        $st2 = $koneksi->prepare("DELETE FROM responden WHERE id_responden=? LIMIT 1");
        $st2->bind_param("i", $id);
        if (!$st2->execute()) throw new Exception("Gagal hapus responden: " . ($st2->error ?: $koneksi->error));
        $deletedResponden = $st2->affected_rows;
        $st2->close();

        $koneksi->commit();

        if ($deletedResponden > 0) {
          flash('success', "Responden dihapus. Jawaban terhapus: {$deletedJawaban} baris.");
        } else {
          flash('warning', "Responden tidak ditemukan. Jawaban terhapus: {$deletedJawaban} baris.");
        }
      } catch (Throwable $e) {
        $koneksi->rollback();
        flash('danger', "Gagal menghapus (rollback). Detail: " . $e->getMessage());
      }
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $p = max(1, (int)($_GET['page'] ?? 1));
    $url = "dashboard.php?menu=kelola_responden";
    if ($q !== '') $url .= "&q=" . urlencode($q);
    if ($p > 1)   $url .= "&page=" . $p;
    redirect_to($url);
  }
}

/* =========================
   LIST + SEARCH + PAGINATION
   Urutan: ASC
========================= */
$search = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = "1=1";
$like  = null;

if ($search !== '') {
  $where .= " AND (
    nama LIKE ? OR
    jenis_kelamin LIKE ? OR
    jenis_pengguna LIKE ? OR
    pekerjaan LIKE ? OR
    lama_menggunakan LIKE ? OR
    DATE_FORMAT(tanggal, '%Y-%m-%d') LIKE ?
  )";
  $like = "%{$search}%";
}

$sqlCount = "SELECT COUNT(*) AS total FROM responden WHERE $where";
$st = $koneksi->prepare($sqlCount);
if ($search !== '') $st->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
$st->execute();
$total = (int)($st->get_result()->fetch_assoc()['total'] ?? 0);
$st->close();

$totalPages = max(1, (int)ceil($total / $limit));

$sqlList = "SELECT id_responden, nama, jenis_kelamin, jenis_pengguna, tanggal, pekerjaan, lama_menggunakan
            FROM responden
            WHERE $where
            ORDER BY id_responden ASC
            LIMIT ? OFFSET ?";

$st = $koneksi->prepare($sqlList);
if ($search !== '') {
  $st->bind_param("ssssssii", $like, $like, $like, $like, $like, $like, $limit, $offset);
} else {
  $st->bind_param("ii", $limit, $offset);
}
$st->execute();
$list = $st->get_result();
$st->close();

$csvUrl = "dashboard.php?menu=kelola_responden&export=csv" . ($search !== '' ? "&q=" . urlencode($search) : "");
$noStart = $offset + 1;
?>

<style>
.rs-wrap{ display:grid; gap:14px; }
.rs-head{ display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start; justify-content:space-between; }
.rs-title{ margin:0; font-weight:900; letter-spacing:.2px; }
.rs-sub{ color: var(--muted); font-size: 12px; margin-top: 3px; }
.rs-search{ display:flex; gap:10px; align-items:center; width: min(820px, 100%); }
.rs-search .form-control{ border-radius: 14px !important; }
.rs-search .btn{ border-radius: 14px !important; }

.rs-card{ border: 1px solid var(--border); border-radius: 18px; background: rgba(255,255,255,.02); box-shadow: var(--shadow); overflow: hidden; }
[data-theme="dark"] .rs-card{ background: rgba(255,255,255,.02); }

.rs-card .hd{ padding: 14px 14px; border-bottom: 1px solid var(--border); display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; background: rgba(255,255,255,.02); }

.rs-table-wrap{ overflow:auto; }
.rs-table{ width:100%; border-collapse: separate; border-spacing: 0; min-width: 980px; }
.rs-table thead th{
  position: sticky; top: 0; z-index: 2;
  padding: 12px 12px;
  font-weight: 900;
  font-size: 13px;
  border-bottom: 1px solid rgba(17,24,39,.10);
  background: rgba(17,24,39,.04);
  color: rgba(17,24,39,.86);
  backdrop-filter: blur(8px);
}
.rs-table tbody td{
  padding: 12px 12px;
  border-bottom: 1px solid rgba(17,24,39,.06);
  color: var(--text);
  vertical-align: middle;
}
[data-theme="dark"] .rs-table thead th{
  background: rgba(255,255,255,.06);
  color: rgba(255,255,255,.92);
  border-bottom-color: rgba(255,255,255,.12);
}
[data-theme="dark"] .rs-table tbody td{
  border-bottom-color: rgba(255,255,255,.08);
  color: rgba(255,255,255,.92);
}
.rs-row{ transition: background-color .12s ease; }
.rs-row:hover{ background: rgba(90,84,232,.06); }
[data-theme="dark"] .rs-row:hover{ background: rgba(160,145,255,.10); }

.rs-person{ display:flex; align-items:center; gap:12px; min-width: 240px; }
.rs-avatar{
  width:38px; height:38px; border-radius: 14px;
  display:flex; align-items:center; justify-content:center;
  font-weight: 900;
  border: 1px solid rgba(17,24,39,.10);
  background: rgba(90,84,232,.10);
  color: rgba(90,84,232,.95);
  flex: 0 0 auto;
}
[data-theme="dark"] .rs-avatar{
  border-color: rgba(255,255,255,.14);
  background: rgba(160,145,255,.12);
  color: rgba(255,255,255,.92);
}
.rs-name{ font-weight: 900; line-height: 1.1; }
.rs-meta{ font-size: 12px; color: var(--muted); margin-top: 3px; }
[data-theme="dark"] .rs-meta{ color: rgba(255,255,255,.72); }

.rs-actions .btn{ border-radius: 12px !important; }
.btn-soft-danger{
  border: 1px solid rgba(220,53,69,.28) !important;
  background: rgba(220,53,69,.10) !important;
  color: var(--text) !important;
}
[data-theme="dark"] .btn-soft-danger{
  border-color: rgba(220,53,69,.32) !important;
  background: rgba(220,53,69,.12) !important;
  color: rgba(255,255,255,.92) !important;
}

.rs-pagi .page-link{
  border-radius: 12px !important;
  border-color: var(--border) !important;
  color: var(--text) !important;
  background: rgba(255,255,255,.02) !important;
}
[data-theme="dark"] .rs-pagi .page-link{
  background: rgba(255,255,255,.03) !important;
  color: rgba(255,255,255,.92) !important;
}
.rs-pagi .page-item.active .page-link{
  background: rgba(90,84,232,.18) !important;
  border-color: rgba(90,84,232,.30) !important;
  font-weight: 900;
}
[data-theme="dark"] .rs-pagi .page-item.active .page-link{
  background: rgba(160,145,255,.16) !important;
  border-color: rgba(160,145,255,.30) !important;
}

.rs-mobile{ display:none; }
@media (max-width: 768px){
  .rs-table-wrap{ display:none; }
  .rs-mobile{ display:grid; gap:12px; padding: 14px; }
  .rs-mcard{ border: 1px solid var(--border); border-radius: 18px; background: rgba(255,255,255,.02); padding: 14px; }
  .rs-mtop{ display:flex; gap:12px; align-items:center; }
  .rs-mgrid{ display:grid; gap:10px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 12px; }
  .rs-mbox{ border: 1px solid rgba(17,24,39,.10); background: rgba(255,255,255,.04); border-radius: 16px; padding: 10px 12px; }
  [data-theme="dark"] .rs-mbox{ border-color: rgba(255,255,255,.14); background: rgba(255,255,255,.03); }
  .rs-mbox .k{ font-size: 12px; color: var(--muted); }
  [data-theme="dark"] .rs-mbox .k{ color: rgba(255,255,255,.72); }
  .rs-mbox .v{ font-weight: 900; margin-top: 2px; }
  .rs-mact{ display:flex; gap:10px; margin-top: 12px; }
  .rs-mact button{ width:100%; border-radius: 14px !important; }
}
</style>

<div class="rs-wrap">

  <?php if (!empty($_SESSION['toast'])): ?>
    <?php
      $t = $_SESSION['toast'];
      unset($_SESSION['toast']);
      $type = in_array(($t['type'] ?? ''), ['success','danger','warning','info'], true) ? $t['type'] : 'info';
      $msg  = (string)($t['message'] ?? '');
    ?>
    <div class="alert alert-<?= e($type); ?> d-flex align-items-center justify-content-between mb-0" style="border-radius:14px;">
      <div><i class="bi bi-info-circle me-1"></i> <?= e($msg); ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="rs-head">
    <div>
      <h5 class="rs-title">Kelola Responden</h5>
      <div class="rs-sub">
        Total: <b><?= number_format($total); ?></b> responden
        <span class="badge bg-secondary ms-2" style="border-radius:999px; font-weight:900;">
          Role: <?= e($currentRole); ?>
        </span>
      </div>
    </div>

    <div class="d-flex gap-2 align-items-center" style="max-width:920px; width:100%;">
      <form class="rs-search flex-grow-1" method="get" action="dashboard.php">
        <input type="hidden" name="menu" value="kelola_responden">

        <input class="form-control" type="text" name="q"
               placeholder="Cari nama / kelamin / pengguna / pekerjaan / tanggal..."
               value="<?= e($search); ?>">

        <button class="btn btn-primary" type="submit" title="Cari"><i class="bi bi-search"></i></button>

        <?php if ($search !== ''): ?>
          <a class="btn btn-outline-secondary" style="border-radius:14px; white-space:nowrap;" href="dashboard.php?menu=kelola_responden" title="Reset">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>

        <a class="btn btn-success" style="border-radius:14px; white-space:nowrap;"
           href="<?= e($csvUrl); ?>" title="Export CSV">
          <i class="bi bi-filetype-csv"></i> Export CSV
        </a>
      </form>
    </div>
  </div>

  <?php if ($canDelete): ?>
    <div class="alert alert-warning py-2 mb-0" style="border-radius:14px;">
      <i class="bi bi-exclamation-triangle me-1"></i>
      <b>Superadmin</b> dapat menghapus responden. Hapus akan menghapus jawaban responden juga.
    </div>
  <?php else: ?>
    <div class="alert alert-info py-2 mb-0" style="border-radius:14px;">
      <i class="bi bi-eye me-1"></i>
      Aksi hapus dinonaktifkan untuk role ini. (Hanya <b>superadmin</b> yang bisa hapus.)
    </div>
  <?php endif; ?>

  <div class="rs-card">
    <div class="hd">
      <div class="fw-semibold"><i class="bi bi-people me-1"></i> Daftar Responden</div>
      <div class="small text-muted"><?= $canDelete ? 'Aksi: Hapus tersedia untuk superadmin.' : 'Aksi: Hanya lihat.'; ?></div>
    </div>

    <!-- DESKTOP TABLE -->
    <div class="rs-table-wrap">
      <table class="rs-table">
        <thead>
          <tr>
            <th style="width:90px;">Nomor</th>
            <th style="min-width:260px;">Nama</th>
            <th style="width:150px;">Jenis Kelamin</th>
            <th style="min-width:180px;">Jenis Pengguna</th>
            <th style="width:160px;">Tanggal</th>
            <th style="min-width:200px;">Pekerjaan</th>
            <th style="min-width:180px;">Lama Menggunakan</th>
            <th style="width:160px; text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($list->num_rows === 0): ?>
          <tr>
            <td colspan="8" style="text-align:center; padding:26px; color:var(--muted);">
              <?= ($search !== '') ? 'Data tidak ditemukan untuk kata kunci itu.' : 'Belum ada data responden.'; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php $no = $noStart; ?>
          <?php while ($row = $list->fetch_assoc()): ?>
            <?php
              $nama = (string)($row['nama'] ?? '');
              $initial = $nama !== '' ? mb_strtoupper(mb_substr($nama, 0, 1)) : 'R';
              $jk = (string)($row['jenis_kelamin'] ?? '');
              $badge = ($jk === 'Laki-Laki') ? 'text-bg-primary'
                      : (($jk === 'Perempuan') ? 'text-bg-danger' : 'text-bg-secondary');
            ?>
            <tr class="rs-row">
              <td style="font-weight:900;"><?= (int)$no; ?></td>

              <td>
                <div class="rs-person">
                  <div class="rs-avatar"><?= e($initial); ?></div>
                  <div>
                    <div class="rs-name"><?= e($nama); ?></div>
                    <div class="rs-meta"><?= e($row['jenis_pengguna'] ?? '-'); ?> • <?= e($row['lama_menggunakan'] ?? '-'); ?></div>
                  </div>
                </div>
              </td>

              <td><span class="badge <?= $badge; ?>" style="border-radius:999px; font-weight:900;"><?= e($jk); ?></span></td>
              <td><?= e($row['jenis_pengguna']); ?></td>
              <td class="rs-meta"><?= e(fmt_date_only($row['tanggal'] ?? '')); ?></td>
              <td><?= e($row['pekerjaan']); ?></td>
              <td><?= e($row['lama_menggunakan']); ?></td>

              <td class="text-end rs-actions">
                <?php if ($canDelete): ?>
                  <form method="post"
                        action="dashboard.php?menu=kelola_responden<?= $search!=='' ? '&q='.urlencode($search) : ''; ?>&page=<?= (int)$page; ?>"
                        class="d-inline"
                        onsubmit="return confirm('Yakin hapus responden nomor <?= (int)$no; ?>?\\nIni akan menghapus jawaban responden juga.');">
                    <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_responden" value="<?= (int)$row['id_responden']; ?>">
                    <button class="btn btn-sm btn-soft-danger" type="submit">
                      <i class="bi bi-trash"></i> Hapus
                    </button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">Tidak ada aksi</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php $no++; ?>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="rs-mobile">
      <?php
      $stM = $koneksi->prepare($sqlList);
      if ($search !== '') {
        $stM->bind_param("ssssssii", $like, $like, $like, $like, $like, $like, $limit, $offset);
      } else {
        $stM->bind_param("ii", $limit, $offset);
      }
      $stM->execute();
      $listM = $stM->get_result();
      $noM = $noStart;
      ?>

      <?php if ($listM->num_rows === 0): ?>
        <div style="padding:14px; color:var(--muted); text-align:center;">
          <?= ($search !== '') ? 'Data tidak ditemukan.' : 'Belum ada data responden.'; ?>
        </div>
      <?php else: ?>
        <?php while ($row = $listM->fetch_assoc()): ?>
          <?php
            $nama = (string)($row['nama'] ?? '');
            $initial = $nama !== '' ? mb_strtoupper(mb_substr($nama, 0, 1)) : 'R';
            $jk = (string)($row['jenis_kelamin'] ?? '');
            $badge = ($jk === 'Laki-Laki') ? 'text-bg-primary'
                    : (($jk === 'Perempuan') ? 'text-bg-danger' : 'text-bg-secondary');
          ?>
          <div class="rs-mcard">
            <div class="rs-mtop">
              <div class="rs-avatar"><?= e($initial); ?></div>
              <div style="min-width:0;">
                <div class="rs-name"><?= e($nama); ?></div>
                <div class="rs-meta">Nomor: <?= (int)$noM; ?> • <?= e(fmt_date_only($row['tanggal'] ?? '')); ?></div>
              </div>
            </div>

            <div class="rs-mgrid">
              <div class="rs-mbox"><div class="k">Jenis Kelamin</div><div class="v"><span class="badge <?= $badge; ?>" style="border-radius:999px; font-weight:900;"><?= e($jk); ?></span></div></div>
              <div class="rs-mbox"><div class="k">Jenis Pengguna</div><div class="v"><?= e($row['jenis_pengguna']); ?></div></div>
              <div class="rs-mbox"><div class="k">Pekerjaan</div><div class="v"><?= e($row['pekerjaan']); ?></div></div>
              <div class="rs-mbox"><div class="k">Lama Menggunakan</div><div class="v"><?= e($row['lama_menggunakan']); ?></div></div>
            </div>

            <div class="rs-mact">
              <?php if ($canDelete): ?>
                <form method="post"
                      action="dashboard.php?menu=kelola_responden<?= $search!=='' ? '&q='.urlencode($search) : ''; ?>&page=<?= (int)$page; ?>"
                      onsubmit="return confirm('Yakin hapus responden nomor <?= (int)$noM; ?>?\\nIni akan menghapus jawaban responden juga.');">
                  <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id_responden" value="<?= (int)$row['id_responden']; ?>">
                  <button class="btn btn-soft-danger" type="submit">
                    <i class="bi bi-trash"></i> Hapus
                  </button>
                </form>
              <?php else: ?>
                <button class="btn btn-outline-secondary" type="button" disabled style="border-radius:14px;">
                  <i class="bi bi-lock"></i> Tidak ada aksi
                </button>
              <?php endif; ?>
            </div>
          </div>
          <?php $noM++; ?>
        <?php endwhile; ?>
      <?php endif; ?>
      <?php $stM->close(); ?>
    </div>

  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="mt-2">
    <ul class="pagination rs-pagi justify-content-end mb-0">
      <?php
        $base = "dashboard.php?menu=kelola_responden";
        if ($search !== '') $base .= "&q=" . urlencode($search);
        $prev = max(1, $page - 1);
        $next = min($totalPages, $page + 1);
      ?>
      <li class="page-item <?= ($page<=1)?'disabled':''; ?>">
        <a class="page-link" href="<?= e($base . "&page=" . $prev); ?>"><i class="bi bi-chevron-left"></i></a>
      </li>

      <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        for ($p=$start; $p<=$end; $p++):
      ?>
        <li class="page-item <?= ($p===$page)?'active':''; ?>">
          <a class="page-link" href="<?= e($base . "&page=" . $p); ?>"><?= (int)$p; ?></a>
        </li>
      <?php endfor; ?>

      <li class="page-item <?= ($page>=$totalPages)?'disabled':''; ?>">
        <a class="page-link" href="<?= e($base . "&page=" . $next); ?>"><i class="bi bi-chevron-right"></i></a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>

</div>
