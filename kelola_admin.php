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

// CSRF
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

// ===== Ambil admin aktif dari session (bisa string / array) =====
$adminUser = '';
if (isset($_SESSION['admin'])) {
    if (is_array($_SESSION['admin'])) $adminUser = (string)($_SESSION['admin']['username'] ?? '');
    else $adminUser = (string)$_SESSION['admin'];
}

$currentAdmin = null;
$currentRole  = 'basic';

if ($adminUser !== '') {
    $st = $koneksi->prepare("SELECT id_admin, username, role FROM admin WHERE username=? LIMIT 1");
    $st->bind_param("s", $adminUser);
    $st->execute();
    $currentAdmin = $st->get_result()->fetch_assoc();
    $st->close();
    if ($currentAdmin) $currentRole = (string)$currentAdmin['role'];
}

// ===== Guard: basic tidak boleh akses kelola admin =====
if ($currentRole !== 'superadmin') {
    echo '<div class="alert alert-warning" style="border-radius:14px;">
            <div class="fw-semibold mb-1"><i class="bi bi-shield-lock me-1"></i>Akses dibatasi</div>
            Hanya <b>superadmin</b> yang dapat mengelola admin.
          </div>';
    return;
}

// ===== Mode =====
$action = $_GET['action'] ?? '';      // add | edit
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ===== Handle POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    $token      = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        flash('danger', 'Token tidak valid (CSRF).');
        redirect_to("dashboard.php?menu=kelola_admin");
    }

    // CREATE
    if ($postAction === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role     = (string)($_POST['role'] ?? 'basic');

        if ($username === '' || $password === '') {
            flash('danger', 'Username dan password wajib diisi.');
            redirect_to("dashboard.php?menu=kelola_admin&action=add");
        }
        if (!in_array($role, ['superadmin','basic'], true)) {
            flash('danger', 'Role tidak valid.');
            redirect_to("dashboard.php?menu=kelola_admin&action=add");
        }

        $st = $koneksi->prepare("SELECT COUNT(*) AS c FROM admin WHERE username=?");
        $st->bind_param("s", $username);
        $st->execute();
        $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        if ($c > 0) {
            flash('danger', 'Username sudah dipakai. Gunakan username lain.');
            redirect_to("dashboard.php?menu=kelola_admin&action=add");
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $koneksi->prepare("INSERT INTO admin (username, password_hash, role) VALUES (?, ?, ?)");
        $st->bind_param("sss", $username, $hash, $role);
        $st->execute();
        $st->close();

        flash('success', 'Admin berhasil ditambahkan.');
        redirect_to("dashboard.php?menu=kelola_admin");
    }

    // UPDATE
    if ($postAction === 'update') {
        $id       = (int)($_POST['id_admin'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $role     = (string)($_POST['role'] ?? 'basic');
        $newpass  = (string)($_POST['new_password'] ?? '');

        if ($id <= 0 || $username === '') {
            flash('danger', 'Data tidak valid.');
            redirect_to("dashboard.php?menu=kelola_admin");
        }
        if (!in_array($role, ['superadmin','basic'], true)) {
            flash('danger', 'Role tidak valid.');
            redirect_to("dashboard.php?menu=kelola_admin");
        }

        // cek username unik selain dirinya
        $st = $koneksi->prepare("SELECT COUNT(*) AS c FROM admin WHERE username=? AND id_admin<>?");
        $st->bind_param("si", $username, $id);
        $st->execute();
        $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        if ($c > 0) {
            flash('danger', 'Username sudah dipakai admin lain.');
            redirect_to("dashboard.php?menu=kelola_admin&action=edit&id=".$id);
        }

        if ($newpass !== '') {
            $hash = password_hash($newpass, PASSWORD_DEFAULT);
            $st = $koneksi->prepare("UPDATE admin SET username=?, role=?, password_hash=? WHERE id_admin=?");
            $st->bind_param("sssi", $username, $role, $hash, $id);
        } else {
            $st = $koneksi->prepare("UPDATE admin SET username=?, role=? WHERE id_admin=?");
            $st->bind_param("ssi", $username, $role, $id);
        }
        $st->execute();
        $st->close();

        // kalau edit diri sendiri dan username berubah → update session
        if ($currentAdmin && (int)$currentAdmin['id_admin'] === $id) {
            if (is_array($_SESSION['admin'])) $_SESSION['admin']['username'] = $username;
            else $_SESSION['admin'] = $username;
        }

        flash('success', 'Admin berhasil diperbarui.');
        redirect_to("dashboard.php?menu=kelola_admin");
    }

    // DELETE
    if ($postAction === 'delete') {
        $id = (int)($_POST['id_admin'] ?? 0);

        if ($id <= 0) {
            flash('danger', 'ID tidak valid.');
            redirect_to("dashboard.php?menu=kelola_admin");
        }

        // tidak boleh hapus diri sendiri
        if ($currentAdmin && (int)$currentAdmin['id_admin'] === $id) {
            flash('warning', 'Tidak bisa menghapus akun yang sedang login.');
            redirect_to("dashboard.php?menu=kelola_admin");
        }

        $st = $koneksi->prepare("DELETE FROM admin WHERE id_admin=?");
        $st->bind_param("i", $id);
        $st->execute();
        $st->close();

        flash('success', 'Admin berhasil dihapus.');

        $q = trim($_GET['q'] ?? '');
        $p = max(1, (int)($_GET['page'] ?? 1));
        $url = "dashboard.php?menu=kelola_admin";
        if ($q !== '') $url .= "&q=" . urlencode($q);
        if ($p > 1)   $url .= "&page=" . $p;
        redirect_to($url);
    }
}

// ===== EDIT DATA =====
$editData = null;
if ($action === 'edit' && $editId > 0) {
    $st = $koneksi->prepare("SELECT id_admin, username, role, created_at, updated_at FROM admin WHERE id_admin=? LIMIT 1");
    $st->bind_param("i", $editId);
    $st->execute();
    $editData = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$editData) {
        flash('warning', 'Data admin tidak ditemukan.');
        redirect_to("dashboard.php?menu=kelola_admin");
    }
}

// ===== LIST + SEARCH + PAGINATION =====
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = "1=1";
$like1 = null; $like2 = null;

if ($search !== '') {
    $where .= " AND (username LIKE ? OR role LIKE ?)";
    $like1 = "%{$search}%";
    $like2 = "%{$search}%";
}

// count
$sqlCount = "SELECT COUNT(*) AS total FROM admin WHERE $where";
$st = $koneksi->prepare($sqlCount);
if ($search !== '') $st->bind_param("ss", $like1, $like2);
$st->execute();
$total = (int)($st->get_result()->fetch_assoc()['total'] ?? 0);
$st->close();

$totalPages = max(1, (int)ceil($total / $limit));

// list
$sqlList = "SELECT id_admin, username, role, created_at, updated_at
            FROM admin
            WHERE $where
            ORDER BY id_admin DESC
            LIMIT ? OFFSET ?";

$st = $koneksi->prepare($sqlList);
if ($search !== '') $st->bind_param("ssii", $like1, $like2, $limit, $offset);
else $st->bind_param("ii", $limit, $offset);
$st->execute();
$list = $st->get_result();
$st->close();

// base url for pagination
$base = "dashboard.php?menu=kelola_admin";
if ($search !== '') $base .= "&q=" . urlencode($search);
?>

<style>
/* ===== Soft modern theme-safe UI (nyatu sama dashboard vars) ===== */
.a-wrap{ display:grid; gap:14px; }

/* header */
.a-head{
  display:flex; flex-wrap:wrap; gap:12px;
  align-items:flex-start; justify-content:space-between;
}
.a-sub{ color: var(--muted); font-size:12px; margin-top:3px; }

/* title icon */
.a-titleIcon{
  width:38px;height:38px;border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  border:1px solid var(--border);
  background: rgba(90,84,232,.10);
}
[data-theme="dark"] .a-titleIcon{
  background: rgba(160,145,255,.12);
}

/* search */
.a-search{ display:flex; gap:10px; align-items:center; width:min(720px,100%); }
.a-search .form-control{ border-radius:14px !important; }
.a-search .btn{ border-radius:14px !important; }

/* chips */
.a-chipline{ display:flex; flex-wrap:wrap; gap:8px; }
.a-chip{
  border:1px solid rgba(17,24,39,.10);
  background: rgba(255,255,255,.04);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  color: var(--muted);
}
[data-theme="dark"] .a-chip{
  border-color: rgba(255,255,255,.14);
  background: rgba(255,255,255,.04);
  color: rgba(255,255,255,.72);
}

/* card */
.a-card{
  border:1px solid var(--border);
  border-radius:18px;
  background: rgba(255,255,255,.02);
  box-shadow: var(--shadow);
  overflow:hidden;
}
[data-theme="dark"] .a-card{ background: rgba(255,255,255,.02); }

.a-card .hd{
  padding:14px;
  border-bottom:1px solid var(--border);
  display:flex; flex-wrap:wrap; gap:10px;
  align-items:center; justify-content:space-between;
  background: rgba(255,255,255,.02);
}

/* table */
.a-table-wrap{ overflow:auto; }
.a-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  min-width: 920px;
}
.a-table thead th{
  position: sticky; top: 0; z-index: 2;
  padding: 12px 12px;
  font-weight: 900;
  font-size: 13px;
  border-bottom: 1px solid rgba(17,24,39,.10);
  background: rgba(17,24,39,.04);
  color: rgba(17,24,39,.86);
  backdrop-filter: blur(8px);
}
.a-table tbody td{
  padding: 12px 12px;
  border-bottom: 1px solid rgba(17,24,39,.06);
  color: var(--text);
  vertical-align: middle;
}
[data-theme="dark"] .a-table thead th{
  background: rgba(255,255,255,.06);
  color: rgba(255,255,255,.92);
  border-bottom-color: rgba(255,255,255,.12);
}
[data-theme="dark"] .a-table tbody td{
  border-bottom-color: rgba(255,255,255,.08);
  color: rgba(255,255,255,.92);
}
.a-row{ transition: background-color .12s ease; }
.a-row:hover{ background: rgba(90,84,232,.06); }
[data-theme="dark"] .a-row:hover{ background: rgba(160,145,255,.10); }

.a-actions .btn{ border-radius:12px !important; }
.btn-soft-primary{
  border:1px solid rgba(90,84,232,.28) !important;
  background: rgba(90,84,232,.10) !important;
  color: var(--text) !important;
}
[data-theme="dark"] .btn-soft-primary{
  border-color: rgba(160,145,255,.32) !important;
  background: rgba(160,145,255,.12) !important;
  color: rgba(255,255,255,.92) !important;
}
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

/* form */
.a-form .form-control, .a-form .form-select{ border-radius:14px !important; }

/* mobile cards */
.a-mobile{ display:none; }
@media (max-width: 768px){
  .a-table-wrap{ display:none; }
  .a-mobile{ display:grid; gap:12px; padding:14px; }
  .a-mcard{
    border:1px solid var(--border);
    border-radius:18px;
    background: rgba(255,255,255,.02);
    padding:14px;
  }
  .a-mtop{ display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
  .a-username{ font-weight:900; }
  .a-meta{ color: var(--muted); font-size:12px; margin-top:6px; }
  [data-theme="dark"] .a-meta{ color: rgba(255,255,255,.72); }
  .a-mact{ display:flex; gap:10px; margin-top:12px; }
  .a-mact a, .a-mact button{ flex:1; border-radius:14px !important; }
}

/* pagination */
.a-pagi .page-link{
  border-radius:12px !important;
  border-color: var(--border) !important;
  color: var(--text) !important;
  background: rgba(255,255,255,.02) !important;
}
[data-theme="dark"] .a-pagi .page-link{
  background: rgba(255,255,255,.03) !important;
  color: rgba(255,255,255,.92) !important;
}
.a-pagi .page-item.active .page-link{
  background: rgba(90,84,232,.18) !important;
  border-color: rgba(90,84,232,.30) !important;
  font-weight: 900;
}
[data-theme="dark"] .a-pagi .page-item.active .page-link{
  background: rgba(160,145,255,.16) !important;
  border-color: rgba(160,145,255,.30) !important;
}
</style>

<div class="a-wrap">

  <?php if (!empty($_SESSION['toast'])): ?>
    <?php
      $t = $_SESSION['toast'];
      unset($_SESSION['toast']);
      $type = in_array($t['type'], ['success','danger','warning','info'], true) ? $t['type'] : 'info';
    ?>
    <div class="alert alert-<?= e($type); ?> d-flex align-items-center justify-content-between mb-0" style="border-radius:14px;">
      <div><i class="bi bi-info-circle me-1"></i> <?= e($t['message']); ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- HEADER + JUDUL -->
  <div class="a-head">
    <div>
      <div class="d-flex align-items-center gap-2">
        <div class="a-titleIcon">
          <i class="bi bi-shield-lock" style="font-size:18px;"></i>
        </div>
        <div>
          <div style="font-weight:900; font-size:18px; line-height:1.1;">Kelola Admin</div>
          <div class="a-sub">Hanya <b>superadmin</b> yang bisa mengatur admin.</div>
        </div>
      </div>

      <div class="small text-muted mt-2">
        Total: <span class="fw-semibold"><?= number_format($total); ?></span> admin
        <?php if ($search !== ''): ?>
          <span class="mx-1">•</span> Filter: <span class="fw-semibold"><?= e($search); ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex gap-2 align-items-center" style="max-width:760px; width:100%;">
      <form class="a-search flex-grow-1" method="get" action="dashboard.php">
        <input type="hidden" name="menu" value="kelola_admin">
        <input class="form-control" type="text" name="q"
               placeholder="Cari username / role..."
               value="<?= e($search); ?>">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>

        <?php if ($search !== ''): ?>
          <a class="btn btn-outline-secondary" style="border-radius:14px;"
             href="dashboard.php?menu=kelola_admin">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </form>

      <a class="btn btn-outline-primary" style="border-radius:14px; white-space:nowrap;"
         href="dashboard.php?menu=kelola_admin&action=add">
        <i class="bi bi-plus-circle"></i> Tambah
      </a>
    </div>
  </div>

  <div class="a-chipline">
    <div class="a-chip"><i class="bi bi-database me-1"></i> Total: <b><?= number_format($total); ?></b></div>
    <div class="a-chip"><i class="bi bi-list-check me-1"></i> Per halaman: <b><?= (int)$limit; ?></b></div>
    <div class="a-chip"><i class="bi bi-funnel me-1"></i> Filter: <b><?= $search!=="" ? e($search) : "Tidak ada"; ?></b></div>
  </div>

  <?php if ($action === 'add' || ($action === 'edit' && $editData)): ?>
    <div class="a-card a-form">
      <div class="hd">
        <div class="fw-semibold">
          <i class="bi <?= ($action==='add') ? 'bi-person-plus' : 'bi-pencil-square'; ?> me-1"></i>
          <?= ($action==='add') ? 'Tambah Admin' : 'Edit Admin'; ?>
        </div>
        <div class="small text-muted"><?= ($action==='edit') ? 'Password baru.' : 'Wajib isi password.'; ?></div>
      </div>

      <div style="padding:14px;">
        <form method="post" class="row g-3">
          <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
          <input type="hidden" name="action" value="<?= ($action==='add') ? 'create' : 'update'; ?>">
          <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id_admin" value="<?= (int)$editData['id_admin']; ?>">
          <?php endif; ?>

          <div class="col-md-4">
            <label class="form-label">Username</label>
            <input class="form-control" type="text" name="username"
                   value="<?= e($editData['username'] ?? ''); ?>" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Role</label>
            <?php $roleVal = $editData['role'] ?? 'basic'; ?>
            <select class="form-select" name="role" required>
              <option value="basic" <?= ($roleVal==='basic')?'selected':''; ?>>basic</option>
              <option value="superadmin" <?= ($roleVal==='superadmin')?'selected':''; ?>>superadmin</option>
            </select>
          </div>

          <div class="col-md-5">
            <label class="form-label"><?= ($action==='add') ? 'Password' : 'Password Baru'; ?></label>
            <input class="form-control" type="password"
                   name="<?= ($action==='add') ? 'password' : 'new_password'; ?>"
                   <?= ($action==='add') ? 'required' : ''; ?>
                   placeholder="<?= ($action==='add') ? 'Masukkan password' : 'Kosongkan jika tidak diubah'; ?>">
            <?php if ($action==='edit'): ?>
              <div class="small text-muted mt-1">Kalau diisi, password akan diganti.</div>
            <?php endif; ?>
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" style="border-radius:14px;" type="submit">
              <i class="bi bi-save"></i> Simpan
            </button>
            <a class="btn btn-outline-secondary" style="border-radius:14px;"
               href="dashboard.php?menu=kelola_admin">
              Batal
            </a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="a-card">
    <div class="hd">
      <div class="fw-semibold"><i class="bi bi-people me-1"></i> Daftar Admin</div>
      <div class="small text-muted">Klik edit untuk ubah data.</div>
    </div>

    <!-- DESKTOP TABLE -->
    <div class="a-table-wrap">
      <table class="a-table">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th>Username</th>
            <th style="width:160px;">Role</th>
            <th style="width:210px;">Created</th>
            <th style="width:210px;">Updated</th>
            <th style="width:240px; text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($list->num_rows === 0): ?>
            <tr>
              <td colspan="6" style="text-align:center; padding:26px; color:var(--muted);">Data admin kosong.</td>
            </tr>
          <?php else: ?>
            <?php while ($row = $list->fetch_assoc()): ?>
              <?php
                $isMe = ($currentAdmin && (int)$currentAdmin['id_admin'] === (int)$row['id_admin']);
                $isSuper = ((string)$row['role'] === 'superadmin');
              ?>
              <tr class="a-row">
                <td style="font-weight:900;"><?= (int)$row['id_admin']; ?></td>
                <td>
                  <div class="fw-semibold"><?= e($row['username']); ?></div>
                  <?php if ($isMe): ?>
                    <div class="small text-muted">Ini akun kamu (sedang login)</div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $isSuper ? 'text-bg-success' : 'text-bg-secondary'; ?>" style="border-radius:999px; font-weight:900;">
                    <?= e($row['role']); ?>
                  </span>
                </td>
                <td class="small text-muted"><?= e(fmt_date($row['created_at'] ?? '')); ?></td>
                <td class="small text-muted"><?= e(fmt_date($row['updated_at'] ?? '')); ?></td>
                <td class="text-end a-actions">
                  <a class="btn btn-sm btn-soft-primary"
                     href="dashboard.php?menu=kelola_admin&action=edit&id=<?= (int)$row['id_admin']; ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </a>

                  <form method="post"
                        action="dashboard.php?menu=kelola_admin<?= $search!=='' ? '&q='.urlencode($search) : ''; ?>&page=<?= (int)$page; ?>"
                        class="d-inline"
                        onsubmit="return confirm('Yakin hapus admin: <?= e($row['username']); ?> ?');">
                    <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_admin" value="<?= (int)$row['id_admin']; ?>">
                    <button class="btn btn-sm btn-soft-danger" type="submit" <?= $isMe ? 'disabled' : ''; ?>>
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
    <div class="a-mobile">
      <?php
      $stM = $koneksi->prepare($sqlList);
      if ($search !== '') $stM->bind_param("ssii", $like1, $like2, $limit, $offset);
      else $stM->bind_param("ii", $limit, $offset);
      $stM->execute();
      $listM = $stM->get_result();
      ?>

      <?php if ($listM->num_rows === 0): ?>
        <div style="padding:14px; color:var(--muted); text-align:center;">Data admin kosong.</div>
      <?php else: ?>
        <?php while ($row = $listM->fetch_assoc()): ?>
          <?php
            $isMe = ($currentAdmin && (int)$currentAdmin['id_admin'] === (int)$row['id_admin']);
            $isSuper = ((string)$row['role'] === 'superadmin');
          ?>
          <div class="a-mcard">
            <div class="a-mtop">
              <div>
                <div class="a-username"><?= e($row['username']); ?></div>
                <?php if ($isMe): ?><div class="a-meta">Ini akun kamu (sedang login)</div><?php endif; ?>
              </div>
              <span class="badge <?= $isSuper ? 'text-bg-success' : 'text-bg-secondary'; ?>" style="border-radius:999px; font-weight:900;">
                <?= e($row['role']); ?>
              </span>
            </div>

            <div class="a-meta">
              ID: #<?= (int)$row['id_admin']; ?><br>
              Created: <?= e(fmt_date($row['created_at'] ?? '')); ?><br>
              Updated: <?= e(fmt_date($row['updated_at'] ?? '')); ?>
            </div>

            <div class="a-mact">
              <a class="btn btn-soft-primary"
                 href="dashboard.php?menu=kelola_admin&action=edit&id=<?= (int)$row['id_admin']; ?>">
                <i class="bi bi-pencil"></i> Edit
              </a>

              <form method="post"
                    action="dashboard.php?menu=kelola_admin<?= $search!=='' ? '&q='.urlencode($search) : ''; ?>&page=<?= (int)$page; ?>"
                    onsubmit="return confirm('Yakin hapus admin: <?= e($row['username']); ?> ?');">
                <input type="hidden" name="csrf" value="<?= e($csrf); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_admin" value="<?= (int)$row['id_admin']; ?>">
                <button class="btn btn-soft-danger" type="submit" <?= $isMe ? 'disabled' : ''; ?>>
                  <i class="bi bi-trash"></i> Hapus
                </button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
      <?php $stM->close(); ?>
    </div>

  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="mt-2">
    <ul class="pagination a-pagi justify-content-end mb-0">
      <li class="page-item <?= ($page<=1)?'disabled':''; ?>">
        <a class="page-link" href="<?= e($base . "&page=" . max(1,$page-1)); ?>"><i class="bi bi-chevron-left"></i></a>
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
        <a class="page-link" href="<?= e($base . "&page=" . min($totalPages,$page+1)); ?>"><i class="bi bi-chevron-right"></i></a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>

</div>
