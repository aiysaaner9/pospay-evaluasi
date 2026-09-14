<?php
// ===== Debug anti blank (kalau sudah stabil, boleh hapus 4 baris ini) =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/koneksi.php';
try {
  if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    throw new Exception("Koneksi database tidak tersedia. Pastikan ../config/koneksi.php benar dan variabelnya \$koneksi.");
  }

  /* =========================
     Helpers
  ========================= */
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

  function redirect_to($url){
    if (!headers_sent()) { header("Location: $url"); exit; }
    echo "<script>window.location.href=".json_encode($url).";</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url=".htmlspecialchars($url, ENT_QUOTES, 'UTF-8')."'></noscript>";
    exit;
  }

  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  $csrf = $_SESSION['csrf_token'];

  function flash($type, $msg){
    $_SESSION['toast'] = ['type'=>$type, 'message'=>$msg];
  }

  function fmt_date($s){
    $s = (string)$s;
    if ($s === '') return '-';
    $t = strtotime($s);
    return $t ? date('d M Y, H:i', $t) : e($s);
  }

  /* =========================
     ROLE
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

  $isSuper = ($currentRole === 'superadmin');

  if ($adminUser === '') {
    flash('danger', 'Silakan login terlebih dahulu.');
    redirect_to("login.php");
  }
  if (!$isSuper) {
    flash('danger', 'Akses ditolak. Halaman Kelola Pertanyaan hanya untuk superadmin.');
    redirect_to("dashboard.php");
  }

  /* =========================
     DB schema check (MariaDB-safe)
  ========================= */
  function has_table(mysqli $db, $table){
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?";
    $st = $db->prepare($sql);
    $st->bind_param("s", $table);
    $st->execute();
    $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    return $c > 0;
  }

  function has_column(mysqli $db, $table, $col){
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $st = $db->prepare($sql);
    $st->bind_param("ss", $table, $col);
    $st->execute();
    $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    return $c > 0;
  }

  $dbErr = [];
  if (!has_table($koneksi,'domain')) $dbErr[] = "Tabel <b>domain</b> belum ada.";
  if (!has_table($koneksi,'pertanyaan')) $dbErr[] = "Tabel <b>pertanyaan</b> belum ada.";

  if (!$dbErr) {
    foreach (['id_domain','kode','nama'] as $c) {
      if (!has_column($koneksi,'domain',$c)) $dbErr[] = "Kolom <b>domain.$c</b> belum ada.";
    }
    foreach (['id_pertanyaan','id_domain','sub_domain','pertanyaan_text'] as $c) {
      if (!has_column($koneksi,'pertanyaan',$c)) $dbErr[] = "Kolom <b>pertanyaan.$c</b> belum ada.";
    }
  }

  if ($dbErr) {
    echo '<div class="alert alert-danger" style="border-radius:14px;">';
    echo '<b>Struktur database belum sesuai.</b>';
    echo '<ul style="margin:8px 0 0 18px;">';
    foreach ($dbErr as $m) echo '<li>'.$m.'</li>';
    echo '</ul>';
    echo '</div>';
    return;
  }

  /* =========================
     COBIT subdomain map
  ========================= */
  $subMap = [
    'EDM' => ['edm01','edm02','edm03','edm04','edm05'],
    'APO' => ['apo01','apo02','apo03','apo04','apo05','apo06','apo07','apo08','apo09','apo10','apo11','apo12','apo13'],
    'BAI' => ['bai01','bai02','bai03','bai04','bai05','bai06','bai07','bai08','bai09','bai10'],
    'DSS' => ['dss01','dss02','dss03','dss04','dss05','dss06'],
    'MEA' => ['mea01','mea02','mea03'],
  ];

  /* =========================
     Load domains
  ========================= */
  function load_domains(mysqli $db){
    $rows = [];
    $byId = [];
    $st = $db->prepare("SELECT id_domain, kode, nama, created_at, updated_at FROM `domain` ORDER BY id_domain ASC");
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
      $id = (int)$r['id_domain'];
      $kode = strtoupper(trim((string)$r['kode']));
      $nama = trim((string)$r['nama']);
      if ($id>0 && $kode!=='') {
        $rows[] = [
          'id_domain'=>$id,'kode'=>$kode,'nama'=>$nama,
          'created_at'=>($r['created_at'] ?? ''),'updated_at'=>($r['updated_at'] ?? '')
        ];
        $byId[$id] = ['kode'=>$kode,'nama'=>$nama];
      }
    }
    $st->close();
    return [$rows, $byId];
  }

  [$domainRows, $domainById] = load_domains($koneksi);

  /* =========================
     MODE
  ========================= */
  $tab       = $_GET['tab'] ?? 'pertanyaan'; // pertanyaan | domain
  $action    = $_GET['action'] ?? '';        // add | edit | domain_edit
  $editId    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $editDomId = isset($_GET['id_domain']) ? (int)$_GET['id_domain'] : 0;

  /* =========================
     Handle POST
  ========================= */
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $token      = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
      flash('danger','Token CSRF tidak valid.');
      redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=".$tab);
    }

    // ===== DOMAIN CRUD =====
    if ($postAction === 'domain_create') {
      $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
      $nama = trim((string)($_POST['nama'] ?? ''));

      if ($kode==='' || $nama==='') {
        flash('danger','Kode & Nama domain wajib diisi.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }
      if (!preg_match('/^[A-Z0-9_]{2,10}$/', $kode)) {
        flash('danger','Kode domain harus 2–10 karakter (A-Z, 0-9, underscore).');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }

      $st = $koneksi->prepare("SELECT id_domain FROM `domain` WHERE kode=? LIMIT 1");
      $st->bind_param("s",$kode);
      $st->execute();
      $ex = $st->get_result()->fetch_assoc();
      $st->close();
      if ($ex) {
        flash('danger','Kode domain sudah ada.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }

      $st = $koneksi->prepare("INSERT INTO `domain` (kode, nama) VALUES (?,?)");
      $st->bind_param("ss",$kode,$nama);
      $st->execute();
      $st->close();

      flash('success','Domain berhasil ditambahkan.');
      redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
    }

    if ($postAction === 'domain_update') {
      $id  = (int)($_POST['id_domain'] ?? 0);
      $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
      $nama = trim((string)($_POST['nama'] ?? ''));

      if ($id<=0 || $kode==='' || $nama==='') {
        flash('danger','Data domain tidak valid.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }
      if (!preg_match('/^[A-Z0-9_]{2,10}$/', $kode)) {
        flash('danger','Kode domain harus 2–10 karakter (A-Z, 0-9, underscore).');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }

      $st = $koneksi->prepare("SELECT id_domain FROM `domain` WHERE kode=? AND id_domain<>? LIMIT 1");
      $st->bind_param("si",$kode,$id);
      $st->execute();
      $ex = $st->get_result()->fetch_assoc();
      $st->close();
      if ($ex) {
        flash('danger','Kode domain sudah dipakai domain lain.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }

      $st = $koneksi->prepare("UPDATE `domain` SET kode=?, nama=? WHERE id_domain=?");
      $st->bind_param("ssi",$kode,$nama,$id);
      $st->execute();
      $st->close();

      flash('success','Domain berhasil diperbarui.');
      redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
    }

    if ($postAction === 'domain_delete') {
      $id = (int)($_POST['id_domain'] ?? 0);
      if ($id<=0) {
        flash('danger','ID domain tidak valid.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }

      $st = $koneksi->prepare("SELECT COUNT(*) c FROM pertanyaan WHERE id_domain=?");
      $st->bind_param("i",$id);
      $st->execute();
      $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
      $st->close();

      if ($c>0) {
        flash('danger',"Domain tidak bisa dihapus karena masih dipakai $c pertanyaan.");
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
      }

      $st = $koneksi->prepare("DELETE FROM `domain` WHERE id_domain=?");
      $st->bind_param("i",$id);
      $st->execute();
      $st->close();

      flash('success','Domain berhasil dihapus.');
      redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
    }

    // reload domain map after domain CRUD
    if (in_array($postAction, ['domain_create','domain_update','domain_delete'], true)) {
      [$domainRows, $domainById] = load_domains($koneksi);
    }

    // ===== PERTANYAAN CRUD =====
    if ($postAction === 'create' || $postAction === 'update') {
      $id_domain = (int)($_POST['id_domain'] ?? 0);
      $sub       = strtolower(trim((string)($_POST['sub_domain'] ?? '')));
      $text      = trim((string)($_POST['pertanyaan_text'] ?? ''));

      $kode = $domainById[$id_domain]['kode'] ?? '';
      if ($id_domain<=0 || $kode==='' || $text==='') {
        flash('danger','Domain dan Pertanyaan wajib diisi.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan");
      }

      if (isset($subMap[$kode])) {
        if ($sub==='' || !in_array($sub, $subMap[$kode], true)) {
          flash('danger','Sub Domain tidak valid untuk domain terpilih.');
          redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan");
        }
      } else {
        if ($sub==='') $sub = 'custom';
      }

      if ($postAction === 'create') {
        $st = $koneksi->prepare("INSERT INTO pertanyaan (id_domain, sub_domain, pertanyaan_text) VALUES (?,?,?)");
        $st->bind_param("iss",$id_domain,$sub,$text);
        $st->execute();
        $st->close();
        flash('success','Pertanyaan berhasil ditambahkan.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan");
      } else {
        $id = (int)($_POST['id_pertanyaan'] ?? 0);
        if ($id<=0) {
          flash('danger','ID pertanyaan tidak valid.');
          redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan");
        }
        $st = $koneksi->prepare("UPDATE pertanyaan SET id_domain=?, sub_domain=?, pertanyaan_text=? WHERE id_pertanyaan=?");
        $st->bind_param("issi",$id_domain,$sub,$text,$id);
        $st->execute();
        $st->close();
        flash('success','Pertanyaan berhasil diperbarui.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan");
      }
    }

    if ($postAction === 'delete') {
      $id = (int)($_POST['id_pertanyaan'] ?? 0);
      if ($id <= 0) {
        flash('danger','ID tidak valid.');
        redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan");
      }

      $st = $koneksi->prepare("DELETE FROM pertanyaan WHERE id_pertanyaan=?");
      $st->bind_param("i",$id);
      $st->execute();
      $st->close();

      flash('success','Pertanyaan berhasil dihapus.');

      // balik ke halaman dan search yang sama
      $q = trim((string)($_GET['q'] ?? ''));
      $p = max(1, (int)($_GET['page'] ?? 1));
      $url = "dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan";
      if ($q !== '') $url .= "&q=".urlencode($q);
      if ($p > 1) $url .= "&page=".$p;
      redirect_to($url);
    }
  }

  /* =========================
     EDIT FETCH
  ========================= */
  $editData = null;
  if ($tab==='pertanyaan' && $action==='edit' && $editId>0) {
    $st = $koneksi->prepare("SELECT * FROM pertanyaan WHERE id_pertanyaan=? LIMIT 1");
    $st->bind_param("i",$editId);
    $st->execute();
    $editData = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$editData) {
      flash('warning','Data pertanyaan tidak ditemukan.');
      redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan");
    }
  }

  $editDomain = null;
  if ($tab==='domain' && $action==='domain_edit' && $editDomId>0) {
    $st = $koneksi->prepare("SELECT * FROM `domain` WHERE id_domain=? LIMIT 1");
    $st->bind_param("i",$editDomId);
    $st->execute();
    $editDomain = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$editDomain) {
      flash('warning','Data domain tidak ditemukan.');
      redirect_to("dashboard.php?menu=kelola_pertanyaan&tab=domain");
    }
  }

  /* =========================
     LIST + SEARCH + PAGINATION (PERTANYAAN)
  ========================= */
  $search = trim((string)($_GET['q'] ?? ''));
  $page   = max(1, (int)($_GET['page'] ?? 1));
  $limit  = 10;
  $offset = ($page - 1) * $limit;

  $where = "1=1";
  $like  = null;

  if ($search !== '') {
    $where .= " AND (p.pertanyaan_text LIKE ? OR d.kode LIKE ? OR p.sub_domain LIKE ?)";
    $like = "%{$search}%";
  }

  $sqlCount = "SELECT COUNT(*) AS total
               FROM pertanyaan p
               JOIN `domain` d ON d.id_domain=p.id_domain
               WHERE $where";
  $st = $koneksi->prepare($sqlCount);
  if ($search !== '') $st->bind_param("sss", $like, $like, $like);
  $st->execute();
  $total = (int)($st->get_result()->fetch_assoc()['total'] ?? 0);
  $st->close();

  $totalPages = max(1, (int)ceil($total / $limit));

  $sqlList = "SELECT p.id_pertanyaan, d.kode AS domain, p.sub_domain, p.pertanyaan_text,
                     p.created_at, p.updated_at, p.id_domain
              FROM pertanyaan p
              JOIN `domain` d ON d.id_domain=p.id_domain
              WHERE $where
              ORDER BY p.id_pertanyaan ASC
              LIMIT ? OFFSET ?";

  $st = $koneksi->prepare($sqlList);
  if ($search !== '') {
    $st->bind_param("sssii", $like, $like, $like, $limit, $offset);
  } else {
    $st->bind_param("ii", $limit, $offset);
  }
  $st->execute();
  $list = $st->get_result();
  $st->close();

  // untuk mobile list (biar gak reuse result pointer)
  $stM = $koneksi->prepare($sqlList);
  if ($search !== '') {
    $stM->bind_param("sssii", $like, $like, $like, $limit, $offset);
  } else {
    $stM->bind_param("ii", $limit, $offset);
  }
  $stM->execute();
  $listM = $stM->get_result();

  $formDomainId = (int)($editData['id_domain'] ?? ($domainRows[0]['id_domain'] ?? 0));
  $formKode = $domainById[$formDomainId]['kode'] ?? ($domainRows[0]['kode'] ?? 'APO');
  $formSub  = strtolower((string)($editData['sub_domain'] ?? (($subMap[$formKode][0] ?? 'custom'))));
  $formText = (string)($editData['pertanyaan_text'] ?? '');

  ?>
  <style>
  /* CSS rapi (gaya kamu) */
  .q-wrap{ display:grid; gap:14px; }
  .q-head{ display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start; justify-content:space-between; }
  .q-title{ margin:0; font-weight:900; letter-spacing:.2px; }
  .q-sub{ color: var(--muted); font-size:12px; margin-top:3px; }
  .q-search{ display:flex; gap:10px; align-items:center; width: min(720px, 100%); }
  .q-search .form-control{ border-radius:14px !important; }
  .q-search .btn{ border-radius:14px !important; }
  .q-card{ border:1px solid var(--border); border-radius:18px; background: rgba(255,255,255,.02); box-shadow: var(--shadow); overflow:hidden; }
  .q-card .hd{ padding:14px; border-bottom:1px solid var(--border); display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; background: rgba(255,255,255,.02); }
  .q-chipline{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
  .q-chip{ border: 1px solid rgba(17,24,39,.10); background: rgba(255,255,255,.04); border-radius: 999px; padding: 6px 10px; font-size: 12px; color: var(--muted); }
  [data-theme="dark"] .q-chip{ border-color: rgba(255,255,255,.14); background: rgba(255,255,255,.04); color: rgba(255,255,255,.72); }
  .q-table-wrap{ overflow:auto; }
  .q-table{ width:100%; border-collapse:separate; border-spacing:0; min-width: 980px; }
  .q-table thead th{ position: sticky; top: 0; z-index: 2; padding: 12px 12px; font-weight: 900; font-size: 13px; border-bottom: 1px solid rgba(17,24,39,.10); background: rgba(17,24,39,.04); color: rgba(17,24,39,.86); backdrop-filter: blur(8px); }
  .q-table tbody td{ padding: 12px 12px; border-bottom: 1px solid rgba(17,24,39,.06); color: var(--text); vertical-align: middle; }
  [data-theme="dark"] .q-table thead th{ background: rgba(255,255,255,.06); color: rgba(255,255,255,.92); border-bottom-color: rgba(255,255,255,.12); }
  [data-theme="dark"] .q-table tbody td{ border-bottom-color: rgba(255,255,255,.08); color: rgba(255,255,255,.92); }
  .q-row{ transition: background-color .12s ease; }
  .q-row:hover{ background: rgba(90,84,232,.06); }
  [data-theme="dark"] .q-row:hover{ background: rgba(160,145,255,.10); }
  .q-actions .btn{ border-radius: 12px !important; }
  .btn-soft-primary{ border:1px solid rgba(90,84,232,.28) !important; background: rgba(90,84,232,.10) !important; color: var(--text) !important; }
  .btn-soft-danger{ border: 1px solid rgba(220,53,69,.28) !important; background: rgba(220,53,69,.10) !important; color: var(--text) !important; }
  .q-form .form-control, .q-form .form-select{ border-radius: 14px !important; }
  .q-form textarea{ min-height: 130px; }
  .q-tabs a{ border-radius:14px !important; }
  .q-mobile{ display:none; }

  @media (max-width: 768px){
    .q-table-wrap{ display:none; }
    .q-mobile{ display:grid; gap:12px; padding: 14px; }
    .q-mcard{ border:1px solid var(--border); border-radius:18px; background: rgba(255,255,255,.02); padding: 14px; }
    .q-mtop{ display:flex; gap:10px; align-items:flex-start; justify-content:space-between; }
    .q-mid{ margin-top:10px; color: var(--text); line-height: 1.35; }
    .q-badges{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .q-mact{ display:flex; gap:10px; margin-top: 12px; }
    .q-mact a, .q-mact button{ flex:1; border-radius:14px !important; }
  }
  .q-pagi .page-link{ border-radius: 12px !important; border-color: var(--border) !important; color: var(--text) !important; background: rgba(255,255,255,.02) !important; }
  .q-pagi .page-item.active .page-link{ background: rgba(90,84,232,.18) !important; border-color: rgba(90,84,232,.30) !important; font-weight: 900; }
  </style>

  <div class="q-wrap">

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

    <div class="q-head">
      <div>
        <h5 class="q-title">Kelola Pertanyaan</h5>
        <div class="q-sub">Tab <b>Kelola Domain</b> untuk tambah/edit/hapus domain. Tab <b>Pertanyaan</b> untuk CRUD pertanyaan.</div>
      </div>
      <div class="d-flex gap-2 q-tabs">
        <a class="btn <?= ($tab==='pertanyaan')?'btn-primary':'btn-outline-primary'; ?>"
           href="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan" style="border-radius:14px;">
          <i class="bi bi-patch-question"></i> Pertanyaan
        </a>
        <a class="btn <?= ($tab==='domain')?'btn-primary':'btn-outline-primary'; ?>"
           href="dashboard.php?menu=kelola_pertanyaan&tab=domain" style="border-radius:14px;">
          <i class="bi bi-diagram-3"></i> Kelola Domain
        </a>
      </div>
    </div>

    <?php if ($tab === 'domain'): ?>
      <!-- ====================== TAB DOMAIN ====================== -->
      <div class="q-card q-form">
        <div class="hd">
          <div class="fw-semibold">
            <i class="bi bi-diagram-3 me-1"></i>
            <?= ($action==='domain_edit' && $editDomain) ? 'Edit Domain' : 'Tambah Domain'; ?>
          </div>
        </div>
        <div style="padding:14px;">
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
            <?php if ($action==='domain_edit' && $editDomain): ?>
              <input type="hidden" name="action" value="domain_update">
              <input type="hidden" name="id_domain" value="<?= (int)$editDomain['id_domain']; ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="domain_create">
            <?php endif; ?>

            <div class="col-md-3">
              <label class="form-label">Kode</label>
              <input class="form-control" name="kode" required placeholder="APO"
                     value="<?= e($editDomain['kode'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Nama Domain</label>
              <input class="form-control" name="nama" required placeholder="Align, Plan and Organize"
                     value="<?= e($editDomain['nama'] ?? ''); ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
              <button class="btn btn-primary" style="border-radius:14px;" type="submit">
                <i class="bi bi-save"></i> Simpan
              </button>
              <?php if ($action==='domain_edit'): ?>
                <a class="btn btn-outline-secondary" style="border-radius:14px;"
                   href="dashboard.php?menu=kelola_pertanyaan&tab=domain">Batal</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <div class="q-card">
        <div class="hd">
          <div class="fw-semibold"><i class="bi bi-list-ul me-1"></i> Daftar Domain</div>
          <div class="q-chipline">
            <span class="q-chip">Total: <b><?= number_format(count($domainRows)); ?></b></span>
          </div>
        </div>

        <div class="q-table-wrap">
          <table class="q-table" style="min-width:860px;">
            <thead>
              <tr>
                <th style="width:90px;">ID</th>
                <th style="width:140px;">Kode</th>
                <th>Nama</th>
                <th style="width:230px; text-align:right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$domainRows): ?>
                <tr><td colspan="4" style="text-align:center; padding:26px; color:var(--muted);">Belum ada domain.</td></tr>
              <?php else: ?>
                <?php foreach ($domainRows as $d): ?>
                  <tr class="q-row">
                    <td style="font-weight:900;"><?= (int)$d['id_domain']; ?></td>
                    <td><span class="badge text-bg-primary" style="border-radius:999px; font-weight:900;"><?= e($d['kode']); ?></span></td>
                    <td><?= e($d['nama']); ?></td>
                    <td class="text-end q-actions">
                      <a class="btn btn-sm btn-soft-primary"
                         href="dashboard.php?menu=kelola_pertanyaan&tab=domain&action=domain_edit&id_domain=<?= (int)$d['id_domain']; ?>">
                        <i class="bi bi-pencil"></i> Edit
                      </a>
                      <form method="post" class="d-inline"
                            action="dashboard.php?menu=kelola_pertanyaan&tab=domain"
                            onsubmit="return confirm('Yakin hapus domain ini? (Tidak bisa jika masih dipakai pertanyaan)');">
                        <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
                        <input type="hidden" name="action" value="domain_delete">
                        <input type="hidden" name="id_domain" value="<?= (int)$d['id_domain']; ?>">
                        <button class="btn btn-sm btn-soft-danger" type="submit">
                          <i class="bi bi-trash"></i> Hapus
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <!-- ====================== TAB PERTANYAAN ====================== -->

      <div class="q-head" style="margin-top:-6px;">
        <div></div>
        <div class="d-flex gap-2 align-items-center" style="max-width:760px; width:100%;">
          <form class="q-search flex-grow-1" method="get" action="dashboard.php">
            <input type="hidden" name="menu" value="kelola_pertanyaan">
            <input type="hidden" name="tab" value="pertanyaan">
            <input class="form-control" type="text" name="q" placeholder="Cari domain / sub / pertanyaan..." value="<?= e($search); ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            <?php if ($search !== ''): ?>
              <a class="btn btn-outline-secondary" style="border-radius:14px;"
                 href="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan">
                <i class="bi bi-x-lg"></i>
              </a>
            <?php endif; ?>
          </form>

          <a class="btn btn-outline-primary" style="border-radius:14px; white-space:nowrap;"
             href="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan&action=add">
            <i class="bi bi-plus-circle"></i> Tambah
          </a>
        </div>
      </div>

      <?php if ($action === 'add' || ($action === 'edit' && $editData)): ?>
        <div class="q-card q-form">
          <div class="hd">
            <div class="fw-semibold">
              <i class="bi <?= ($action==='add') ? 'bi-plus-circle' : 'bi-pencil-square'; ?> me-1"></i>
              <?= ($action==='add') ? 'Tambah Pertanyaan' : 'Edit Pertanyaan'; ?>
            </div>
          </div>
          <div style="padding:14px;">
            <form method="post" class="row g-3" id="qForm">
              <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
              <input type="hidden" name="action" value="<?= ($action==='add') ? 'create' : 'update'; ?>">
              <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id_pertanyaan" value="<?= (int)$editData['id_pertanyaan']; ?>">
              <?php endif; ?>

              <div class="col-md-3">
                <label class="form-label">Domain</label>
                <select class="form-select" name="id_domain" id="domainSelect" required>
                  <?php foreach ($domainRows as $d): ?>
                    <option value="<?= (int)$d['id_domain']; ?>" data-kode="<?= e($d['kode']); ?>"
                      <?= ($formDomainId === (int)$d['id_domain']) ? 'selected' : ''; ?>>
                      <?= e($d['kode']); ?> - <?= e($d['nama']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-3">
                <label class="form-label">Sub Domain</label>
                <select class="form-select" name="sub_domain" id="subSelect" required></select>
                <div class="small text-muted mt-1">Jika domain custom, sub otomatis jadi <b>custom</b>.</div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Pertanyaan</label>
                <textarea class="form-control" name="pertanyaan_text" required placeholder="Contoh: P40 - ..."><?= e($formText); ?></textarea>
              </div>

              <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" style="border-radius:14px;" type="submit">
                  <i class="bi bi-save"></i> Simpan
                </button>
                <a class="btn btn-outline-secondary" style="border-radius:14px;"
                   href="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan">
                  Batal
                </a>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <div class="q-card">
        <div class="hd">
          <div class="fw-semibold"><i class="bi bi-patch-question me-1"></i> Daftar Pertanyaan</div>
          <div class="q-chipline">
            <span class="q-chip">Total: <b><?= number_format($total); ?></b></span>
            <span class="q-chip">Halaman: <b><?= (int)$page; ?></b>/<?= (int)$totalPages; ?></span>
          </div>
        </div>

        <div class="q-table-wrap">
          <table class="q-table">
            <thead>
              <tr>
                <th style="width:70px;">ID</th>
                <th style="width:90px;">Domain</th>
                <th style="width:120px;">Sub</th>
                <th>Pertanyaan</th>
                <th style="width:190px;">Created</th>
                <th style="width:190px;">Updated</th>
                <th style="width:230px; text-align:right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($list->num_rows === 0): ?>
                <tr>
                  <td colspan="7" style="text-align:center; padding:26px; color:var(--muted);">
                    <?= ($search !== '') ? 'Data tidak ditemukan.' : 'Belum ada pertanyaan.'; ?>
                  </td>
                </tr>
              <?php else: ?>
                <?php while ($row = $list->fetch_assoc()): ?>
                  <tr class="q-row">
                    <td style="font-weight:900;"><?= (int)$row['id_pertanyaan']; ?></td>
                    <td><span class="badge text-bg-primary" style="border-radius:999px; font-weight:900;"><?= e($row['domain']); ?></span></td>
                    <td><span class="badge text-bg-secondary" style="border-radius:999px; font-weight:900;"><?= e($row['sub_domain']); ?></span></td>
                    <td><?= e($row['pertanyaan_text']); ?></td>
                    <td class="small text-muted"><?= e(fmt_date($row['created_at'] ?? '')); ?></td>
                    <td class="small text-muted"><?= e(fmt_date($row['updated_at'] ?? '')); ?></td>
                    <td class="text-end q-actions">
                      <a class="btn btn-sm btn-soft-primary"
                         href="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan&action=edit&id=<?= (int)$row['id_pertanyaan']; ?>">
                        <i class="bi bi-pencil"></i> Edit
                      </a>

                      <form method="post"
                            action="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan<?= $search!=='' ? '&q='.urlencode($search) : ''; ?>&page=<?= (int)$page; ?>"
                            class="d-inline"
                            onsubmit="return confirm('Yakin hapus pertanyaan ini?');">
                        <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_pertanyaan" value="<?= (int)$row['id_pertanyaan']; ?>">
                        <button class="btn btn-sm btn-soft-danger" type="submit">
                          <i class="bi bi-trash"></i> Hapus
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- MOBILE CARDS -->
        <div class="q-mobile">
          <?php if ($listM->num_rows === 0): ?>
            <div style="padding:14px; color:var(--muted); text-align:center;">
              <?= ($search !== '') ? 'Data tidak ditemukan.' : 'Belum ada pertanyaan.'; ?>
            </div>
          <?php else: ?>
            <?php while ($row = $listM->fetch_assoc()): ?>
              <div class="q-mcard">
                <div class="q-mtop">
                  <div style="font-weight:900;">#<?= (int)$row['id_pertanyaan']; ?></div>
                  <div class="q-badges">
                    <span class="badge text-bg-primary" style="border-radius:999px; font-weight:900;"><?= e($row['domain']); ?></span>
                    <span class="badge text-bg-secondary" style="border-radius:999px; font-weight:900;"><?= e($row['sub_domain']); ?></span>
                  </div>
                </div>

                <div class="q-mid"><?= e($row['pertanyaan_text']); ?></div>

                <div class="small text-muted mt-2">
                  Created: <?= e(fmt_date($row['created_at'] ?? '')); ?><br>
                  Updated: <?= e(fmt_date($row['updated_at'] ?? '')); ?>
                </div>

                <div class="q-mact">
                  <a class="btn btn-soft-primary"
                     href="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan&action=edit&id=<?= (int)$row['id_pertanyaan']; ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </a>

                  <form method="post"
                        action="dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan<?= $search!=='' ? '&q='.urlencode($search) : ''; ?>&page=<?= (int)$page; ?>"
                        onsubmit="return confirm('Yakin hapus pertanyaan ini?');">
                    <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_pertanyaan" value="<?= (int)$row['id_pertanyaan']; ?>">
                    <button class="btn btn-soft-danger" type="submit">
                      <i class="bi bi-trash"></i> Hapus
                    </button>
                  </form>
                </div>
              </div>
            <?php endwhile; ?>
          <?php endif; ?>
        </div>

      </div>

      <?php
        $stM->close();
      ?>

      <!-- PAGINATION -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-2">
          <ul class="pagination q-pagi justify-content-end mb-0">
            <?php
              $base = "dashboard.php?menu=kelola_pertanyaan&tab=pertanyaan";
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

    <?php endif; ?>

  </div>

  <script>
  (function(){
    const subMap = <?= json_encode($subMap); ?>;
    const domainEl = document.getElementById("domainSelect");
    const subEl    = document.getElementById("subSelect");
    if (!domainEl || !subEl) return;

    const selectedSub = <?= json_encode((string)$formSub); ?>;

    function getKode(){
      const opt = domainEl.options[domainEl.selectedIndex];
      return opt ? (opt.getAttribute("data-kode") || "") : "";
    }

    function fillSub(){
      const kode = getKode();
      const arr = subMap[kode] || [];
      subEl.innerHTML = "";

      if (arr.length === 0) {
        const opt = document.createElement("option");
        opt.value = "custom";
        opt.textContent = "custom";
        subEl.appendChild(opt);
        subEl.value = "custom";
        return;
      }

      arr.forEach(v=>{
        const opt = document.createElement("option");
        opt.value = v;
        opt.textContent = v;
        subEl.appendChild(opt);
      });

      if (arr.includes(selectedSub)) subEl.value = selectedSub;
      else subEl.value = arr[0];
    }

    fillSub();
    domainEl.addEventListener("change", fillSub);
  })();
  </script>

  <?php

} catch (Throwable $ex) {
  echo '<div class="alert alert-danger" style="border-radius:14px;">';
  echo '<b>Terjadi error:</b><br>';
  echo '<pre style="white-space:pre-wrap;margin-top:8px;">'.htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8').'</pre>';
  echo '</div>';
}
