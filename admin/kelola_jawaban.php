<?php
// admin/kelola_jawaban.php (FINAL: list + detail + export CSV download only; TANPA EDIT; TANPA PREVIEW EXPORT)
// UPDATE SESUAI REQUEST TERAKHIR:
// - Aksi EDIT dihapus total (UI + handler update + view edit)
// - Preview Export CSV dihapus
// - Export tinggal tombol Download CSV (masih ikut filter q)
// - List tetap ORDER BY id_jawaban ASC
// - Kolom LIST: No, id_jawaban, nama, P1-P39, tanggal (tanpa aksi hapus)

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
  echo '<div class="alert alert-danger">Koneksi database tidak tersedia. Pastikan ../config/koneksi.php benar dan variabelnya $koneksi.</div>';
  return;
}

/* =========================
   Helpers
========================= */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fmt_date($s){
  $s = (string)$s;
  if ($s === '') return '-';
  $t = strtotime($s);
  if (!$t) return e($s);
  return date('d M Y, H:i', $t); // kalau mau tanpa jam: date('d M Y', $t)
}

function avg_score_from_row($row){
  $sum = 0; $cnt = 0;
  for ($i=1; $i<=39; $i++){
    $v = $row["p{$i}"] ?? null;
    if ($v !== null && $v !== '') { $sum += (int)$v; $cnt++; }
  }
  return $cnt ? round($sum/$cnt, 2) : 0;
}

function status_badge($avg){
  if ($avg >= 3.26) return ['Baik','success'];
  if ($avg >= 2.51) return ['Cukup','warning'];
  return ['Perlu Perbaikan','danger'];
}

// (opsional) mapping domain/subdomain untuk tabel detail
function meta_domain_sub($i){
  $i = (int)$i;
  if ($i >= 1  && $i <= 9)  return ['APO', 'APO01'];
  if ($i >= 10 && $i <= 12) return ['APO', 'APO07'];
  if ($i >= 13 && $i <= 15) return ['APO', 'APO12'];
  if ($i >= 16 && $i <= 18) return ['APO', 'APO13'];
  if ($i >= 19 && $i <= 24) return ['APO', 'APO14'];
  if ($i >= 25 && $i <= 27) return ['APO', 'APO15'];
  if ($i >= 28 && $i <= 36) return ['APO', 'APO16'];
  if ($i >= 37 && $i <= 39) return ['APO', 'APO17'];
  return ['APO','APO01'];
}

/* =========================
   Pertanyaan P1..P39 (DITANAM)
========================= */
$Q_TEXT = [
  1  => "Aplikasi POSPAY mudah digunakan tanpa sering mengalami error saat login.",
  2  => "Jarang mengalami kendala sistem yang menghambat aktivitas transaksi di POSPAY.",
  3  => "Secara umum, pengelolaan sistem aplikasi POSPAY sudah berjalan dengan baik.",
  4  => "Fitur yang tersedia di aplikasi POSPAY sesuai dengan kebutuhan saya sebagai pengguna.",
  5  => "Pembaruan (update) aplikasi POSPAY meningkatkan kualitas layanan, bukan menambah masalah.",
  6  => "POSPAY memiliki arah pengembangan aplikasi yang jelas dan bermanfaat bagi pengguna.",
  7  => "Aplikasi POSPAY dapat digunakan dengan baik menggunakan berbagai jenis jaringan internet (WiFi maupun data seluler).",
  8  => "Saya jarang mengalami kendala koneksi jaringan saat menggunakan POSPAY.",
  9  => "Infrastruktur sistem POSPAY mendukung penggunaan aplikasi secara stabil.",
  10 => "Petugas layanan pelanggan (Customer Service) POSPAY mudah dihubungi saat terjadi masalah.",
  11 => "Customer Service POSPAY memberikan solusi yang jelas dan membantu.",
  12 => "Penanganan keluhan pengguna oleh Customer Service POSPAY dilakukan secara profesional.",
  13 => "Saya merasa aman melakukan transaksi keuangan menggunakan aplikasi POSPAY.",
  14 => "Risiko kehilangan saldo atau keterlambatan dana jarang terjadi di POSPAY.",
  15 => "POSPAY mampu menangani risiko transaksi dengan baik saat terjadi masalah.",
  16 => "Riwayat transaksi pada aplikasi POSPAY ditampilkan dengan lengkap dan jelas.",
  17 => "Dapat dengan mudah memeriksa status transaksi yang telah dilakukan.",
  18 => "Operasional aplikasi POSPAY berjalan dengan lancar tanpa gangguan berarti.",
  19 => "Laporan keluhan yang saya sampaikan ke POSPAY mendapatkan respons.",
  20 => "Permasalahan yang saya laporkan ditindaklanjuti dalam waktu yang wajar.",
  21 => "Proses pengaduan masalah di aplikasi POSPAY mudah dilakukan.",
  22 => "Masalah yang sering muncul di aplikasi POSPAY jarang terulang kembali.",
  23 => "Error seperti gagal inisialisasi atau gagal login jarang saya alami.",
  24 => "POSPAY mampu menyelesaikan masalah teknis secara permanen.",
  25 => "Sistem keamanan login POSPAY tidak menyulitkan pengguna.",
  26 => "Saya merasa sistem login POSPAY cukup aman tanpa menghambat akses.",
  27 => "Fitur verifikasi akun POSPAY membantu menjaga keamanan data pengguna.",
  28 => "Proses transaksi seperti transfer bank atau pembayaran berjalan sesuai informasi yang ditampilkan.",
  29 => "Jika transaksi dinyatakan berhasil, dana benar-benar masuk ke tujuan.",
  30 => "Kesalahan transaksi di POSPAY jarang terjadi.",
  31 => "Aplikasi POSPAY memberikan notifikasi atas transaksi yang saya lakukan.",
  32 => "Informasi status transaksi di POSPAY mudah dipantau.",
  33 => "Kinerja aplikasi POSPAY dapat dipantau dengan jelas oleh pengguna.",
  34 => "Sistem POSPAY mampu mengontrol transaksi agar tidak terjadi kesalahan.",
  35 => "Permasalahan dana yang belum masuk jarang terjadi di POSPAY.",
  36 => "POSPAY memiliki mekanisme kontrol yang baik terhadap transaksi pengguna.",
  37 => "Proses layanan seperti BSU di POSPAY berjalan sesuai prosedur yang ditetapkan.",
  38 => "Informasi terkait BSU di aplikasi POSPAY mudah diakses.",
  39 => "Fitur BSU di POSPAY dapat digunakan tanpa kendala teknis.",
];

/* =========================
   Mode: detail
========================= */
$detail_id = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;

/* =========================
   Columns p1..p39
========================= */
$pCols = [];
for ($i=1; $i<=39; $i++) $pCols[] = "p{$i}";
?>

<style>
.jw-wrap{ display:grid; gap:14px; }
.jw-head{ display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start; justify-content:space-between; }
.jw-title{ margin:0; font-weight:900; letter-spacing:.2px; }
.jw-sub{ color: var(--muted); font-size: 12px; margin-top: 3px; }

.jw-card{ border: 1px solid var(--border); border-radius: 18px; background: rgba(255,255,255,.02); box-shadow: var(--shadow); overflow:hidden; }
.jw-card .hd{ padding: 14px 14px; border-bottom: 1px solid var(--border); display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; background: rgba(255,255,255,.02); }

/* toolbar */
.jw-actionsbar{ display:grid; grid-template-columns: max-content minmax(320px, 520px); gap:10px; align-items:center; justify-content:end; }
.jw-search{ display:flex; gap:10px; align-items:center; width:100%; }
.jw-search .form-control{ border-radius: 14px !important; }
.jw-search .btn{ border-radius: 14px !important; }
@media (max-width: 768px){ .jw-actionsbar{ grid-template-columns: 1fr; justify-content:stretch; } }
@media (max-width: 520px){ .jw-btn-text{ display:none; } }

.jw-chips{ display:flex; flex-wrap:wrap; gap:8px; }
.jw-chip{ border: 1px solid rgba(17,24,39,.10); background: rgba(255,255,255,.04); border-radius: 999px; padding: 6px 10px; font-size: 12px; color: var(--muted); }

.jw-table-wrap{ overflow:auto; }
.jw-table{ width:100%; border-collapse: separate; border-spacing: 0; min-width: 3600px; }
.jw-table thead th{ position: sticky; top: 0; z-index: 5; background: rgba(17,24,39,.04); border-bottom: 1px solid rgba(17,24,39,.10); padding: 12px 12px; font-weight: 900; font-size: 12px; backdrop-filter: blur(8px); white-space: nowrap; }
.jw-table tbody td{ padding: 10px 12px; border-bottom: 1px solid rgba(17,24,39,.06); vertical-align: middle; color: var(--text); white-space: nowrap; }
.jw-row{ transition: background-color .12s ease; }
.jw-row:hover{ background: rgba(90,84,232,.06); }

.jw-actions{ display:flex; justify-content:flex-end; gap:8px; align-items:center; }
.jw-actions .btn{ border-radius: 12px !important; }
.btn-soft-primary{ border: 1px solid rgba(90,84,232,.28) !important; background: rgba(90,84,232,.10) !important; color: var(--text) !important; }

/* Pagination */
.jw-pagi .page-link{ border-radius: 12px !important; border-color: var(--border) !important; color: var(--text) !important; background: rgba(255,255,255,.02) !important; }
.jw-pagi .page-item.active .page-link{ background: rgba(90,84,232,.18) !important; border-color: rgba(90,84,232,.30) !important; font-weight: 900; }

/* Sticky left cols (3 kolom: No, id_jawaban, nama) */
.sticky-col-1{ position: sticky; left: 0; z-index: 8; background: rgba(255,255,255,.96); backdrop-filter: blur(6px); box-shadow: 6px 0 12px rgba(0,0,0,.06); }
.sticky-col-2{ position: sticky; left: 70px; z-index: 8; background: rgba(255,255,255,.96); backdrop-filter: blur(6px); box-shadow: 6px 0 12px rgba(0,0,0,.06); }
.sticky-col-3{ position: sticky; left: 210px; z-index: 8; background: rgba(255,255,255,.96); backdrop-filter: blur(6px); box-shadow: 6px 0 12px rgba(0,0,0,.06); }
.jw-table thead th.sticky-col-1,
.jw-table thead th.sticky-col-2,
.jw-table thead th.sticky-col-3{ z-index: 10; background: rgba(17,24,39,.08); }

/* Dark */
[data-theme="dark"] .jw-table thead th{ background: rgba(255,255,255,.06); border-bottom-color: rgba(255,255,255,.12); color: rgba(255,255,255,.92); }
[data-theme="dark"] .jw-table tbody td{ border-bottom-color: rgba(255,255,255,.08); color: rgba(255,255,255,.92); }
[data-theme="dark"] .sticky-col-1,
[data-theme="dark"] .sticky-col-2,
[data-theme="dark"] .sticky-col-3{ background: rgba(17,24,39,.92); box-shadow: 6px 0 12px rgba(0,0,0,.35); }
[data-theme="dark"] .jw-table thead th.sticky-col-1,
[data-theme="dark"] .jw-table thead th.sticky-col-2,
[data-theme="dark"] .jw-table thead th.sticky-col-3{ background: rgba(255,255,255,.10); }

/* Detail table */
.jw-qwrap{ overflow:auto; }
.jw-qtable{ width:100%; border-collapse:separate; border-spacing:0; min-width: 980px; }
.jw-qtable thead th{ position: sticky; top:0; z-index:2; padding:12px; font-weight:900; font-size:12px; background: rgba(17,24,39,.04); border-bottom:1px solid rgba(17,24,39,.10); white-space: nowrap; backdrop-filter: blur(8px); }
.jw-qtable tbody td{ padding:12px; border-bottom:1px solid rgba(17,24,39,.06); vertical-align: top; }
[data-theme="dark"] .jw-qtable thead th{ background: rgba(255,255,255,.06); border-bottom-color: rgba(255,255,255,.12); color: rgba(255,255,255,.92); }
[data-theme="dark"] .jw-qtable tbody td{ border-bottom-color: rgba(255,255,255,.08); color: rgba(255,255,255,.92); }
.jw-qbadge{ display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:26px; border-radius:999px; font-weight:900; border:1px solid rgba(90,84,232,.25); background: rgba(90,84,232,.12); }
[data-theme="dark"] .jw-qbadge{ border-color: rgba(160,145,255,.28); background: rgba(160,145,255,.14); }

/* MOBILE */
.jw-mobile{ display:none; }
@media (max-width: 768px){
  .jw-table-wrap{ display:none; }
  .jw-mobile{ display:grid; gap:12px; padding: 14px; }
  .jw-mcard{ border: 1px solid var(--border); border-radius: 18px; background: rgba(255,255,255,.02); padding: 14px; }
  .jw-mrow{ display:flex; justify-content:space-between; gap:10px; }
  .jw-mk{ color: var(--muted); font-size:12px; }
  .jw-mv{ font-weight:900; }
  .jw-mact{ display:flex; gap:10px; margin-top: 12px; }
  .jw-mact a{ flex:1; border-radius: 14px !important; }
}
</style>

<?php
/* ==========================================================
   DETAIL VIEW
========================================================== */
if ($detail_id > 0) {
  $sql = "SELECT j.*, r.nama, r.jenis_kelamin, r.jenis_pengguna, r.pekerjaan, r.lama_menggunakan
          FROM jawaban j
          JOIN responden r ON r.id_responden = j.id_responden
          WHERE j.id_jawaban = ?
          LIMIT 1";
  $st = $koneksi->prepare($sql);
  $st->bind_param("i", $detail_id);
  $st->execute();
  $jawab = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$jawab) {
    echo '<div class="alert alert-warning" style="border-radius:14px;">Data jawaban tidak ditemukan.</div>';
    echo '<a class="btn btn-outline-primary" style="border-radius:14px;" href="dashboard.php?menu=jawaban"><i class="bi bi-arrow-left"></i> Kembali</a>';
    return;
  }

  $avg = avg_score_from_row($jawab);
  [$stText,$stType] = status_badge($avg);
  ?>
  <div class="jw-wrap">
    <div class="jw-head">
      <div>
        <h5 class="jw-title">Detail Jawaban</h5>
        <div class="jw-sub">
          ID: <b><?= e($jawab['id_jawaban']); ?></b> • <?= e(fmt_date($jawab['tanggal'] ?? '')); ?>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" style="border-radius:14px;" href="dashboard.php?menu=jawaban">
          <i class="bi bi-arrow-left"></i> Kembali
        </a>
      </div>
    </div>

    <div class="jw-card">
      <div class="hd"><div class="fw-semibold">Responden</div></div>
      <div style="padding:14px;">
        <div style="font-weight:900; font-size:16px;"><?= e($jawab['nama'] ?? '-'); ?></div>
        <div style="color:var(--muted); font-size:12px;">
          <?= e($jawab['jenis_kelamin'] ?? '-'); ?> • <?= e($jawab['jenis_pengguna'] ?? '-'); ?> • <?= e($jawab['pekerjaan'] ?? '-'); ?> • <?= e($jawab['lama_menggunakan'] ?? '-'); ?>
        </div>
        <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
          <span class="badge bg-<?= e($stType); ?>" style="border-radius:999px; font-weight:900;"><?= e($stText); ?></span>
          <span class="badge bg-secondary" style="border-radius:999px; font-weight:900;">Rata-rata: <?= e($avg); ?></span>
        </div>
      </div>
    </div>

    <div class="jw-card">
      <div class="hd">
        <div class="fw-semibold">Rincian Per Pertanyaan (Domain • Sub Domain • Pertanyaan • Nilai)</div>
      </div>
      <div class="jw-qwrap">
        <table class="jw-qtable">
          <thead>
            <tr>
              <th style="width:90px;">Kode</th>
              <th style="width:90px;">Domain</th>
              <th style="width:120px;">Sub Domain</th>
              <th>Pertanyaan</th>
              <th style="width:90px; text-align:center;">Nilai</th>
            </tr>
          </thead>
          <tbody>
            <?php for ($i=1; $i<=39; $i++):
              [$dom, $sub] = meta_domain_sub($i);
              $qtext = $Q_TEXT[$i] ?? '-';
              $nilaiRaw = $jawab["p{$i}"] ?? null;
              $nilai = ($nilaiRaw === null || $nilaiRaw === '') ? '-' : (int)$nilaiRaw;
            ?>
              <tr>
                <td style="font-weight:900;">P<?= $i; ?></td>
                <td><?= e($dom); ?></td>
                <td><?= e($sub); ?></td>
                <td style="white-space: normal;"><?= e($qtext); ?></td>
                <td style="text-align:center;"><span class="jw-qbadge"><?= e($nilai); ?></span></td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php
  return;
}

/* ==========================================================
   LIST VIEW
========================================================== */
$search = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where = "1=1";
$params = [];
$types  = "";

if ($search !== "") {
  $where .= " AND (
      CAST(j.id_jawaban AS CHAR) LIKE ? OR
      CAST(j.id_responden AS CHAR) LIKE ? OR
      j.tanggal LIKE ? OR
      r.nama LIKE ?
  )";
  $like = "%{$search}%";
  $params = [$like,$like,$like,$like];
  $types  = "ssss";
}

// count
$sqlCount = "SELECT COUNT(*) AS total
             FROM jawaban j
             JOIN responden r ON r.id_responden = j.id_responden
             WHERE $where";
$stC = $koneksi->prepare($sqlCount);
if ($types !== "") $stC->bind_param($types, ...$params);
$stC->execute();
$total = (int)($stC->get_result()->fetch_assoc()['total'] ?? 0);
$stC->close();

$totalPages = max(1, (int)ceil($total / $limit));

// list
$sqlList = "SELECT
              j.id_jawaban, j.id_responden, j.tanggal,
              r.nama,
              " . implode(", ", array_map(fn($c)=>"j.$c", $pCols)) . "
            FROM jawaban j
            JOIN responden r ON r.id_responden = j.id_responden
            WHERE $where
            ORDER BY j.id_jawaban ASC
            LIMIT ? OFFSET ?";

$st = $koneksi->prepare($sqlList);

if ($types !== "") {
  $types2 = $types . "ii";
  $bind = array_merge($params, [$limit, $offset]);
  $st->bind_param($types2, ...$bind);
} else {
  $st->bind_param("ii", $limit, $offset);
}

$st->execute();
$list = $st->get_result();

$base = "dashboard.php?menu=jawaban";
if ($search !== "") $base .= "&q=" . urlencode($search);

$csvUrl = "export_jawaban_csv.php" . ($search !== '' ? '?q='.urlencode($search) : '');
?>

<div class="jw-wrap">

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

  <div class="jw-head">
    <div>
      <h5 class="jw-title">Jawaban Kuesioner</h5>
      <div class="jw-sub">
        Setiap pertanyaan berdasarkan skala likert <b>0 - 5.</b>
      </div>
    </div>

    <div class="jw-actionsbar">
      <a class="btn btn-success" style="border-radius:14px;" href="<?= e($csvUrl); ?>">
        <i class="bi bi-download"></i>
        <span class="jw-btn-text"> Download CSV</span>
      </a>

      <form class="jw-search" method="get" action="dashboard.php">
        <input type="hidden" name="menu" value="jawaban">
        <input type="text" name="q" class="form-control" placeholder="Cari id_jawaban / id_responden / nama / tanggal..."
               value="<?= e($search); ?>">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-search"></i>
        </button>
        <?php if ($search !== ""): ?>
          <a class="btn btn-outline-secondary" style="border-radius:14px;" href="dashboard.php?menu=jawaban">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="jw-chips">
    <div class="jw-chip"><i class="bi bi-database me-1"></i> Total data: <b><?= number_format($total); ?></b></div>
    <div class="jw-chip"><i class="bi bi-list-check me-1"></i> Per halaman: <b><?= (int)$limit; ?></b></div>
    <div class="jw-chip"><i class="bi bi-funnel me-1"></i> Filter: <b><?= $search!=="" ? e($search) : "Tidak ada"; ?></b></div>
  </div>

  <div class="jw-card">
    <div class="hd">
      <div class="fw-semibold"><i class="bi bi-table me-1"></i> Daftar Jawaban</div>
    </div>

    <div class="jw-table-wrap">
      <table class="jw-table">
        <thead>
          <tr>
            <th class="sticky-col-1" style="width:70px; text-align:center;">No</th>
            <th class="sticky-col-2" style="width:140px;">id_jawaban</th>
            <th class="sticky-col-3" style="width:260px;">nama</th>
            <?php for ($i=1; $i<=39; $i++): ?>
              <th style="width:60px; text-align:center;">P<?= $i; ?></th>
            <?php endfor; ?>
            <th style="width:180px;">tanggal</th>
            <th style="width:160px; text-align:right;">aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($list->num_rows === 0): ?>
          <tr>
            <td colspan="<?= 3 + 39 + 2; ?>" style="text-align:center; padding:26px; color:var(--muted);">
              Belum ada data jawaban.
            </td>
          </tr>
        <?php else: ?>
          <?php $no = $offset + 1; ?>
          <?php while ($row = $list->fetch_assoc()): ?>
            <tr class="jw-row">
              <td class="sticky-col-1" style="font-weight:900; text-align:center;"><?= (int)$no++; ?></td>
              <td class="sticky-col-2" style="font-weight:900;"><?= e($row['id_jawaban']); ?></td>
              <td class="sticky-col-3" style="font-weight:900;"><?= e($row['nama'] ?? '-'); ?></td>

              <?php for ($i=1; $i<=39; $i++):
                $v = $row["p{$i}"] ?? '';
                $vv = ($v === '' || $v === null) ? '-' : (int)$v;
              ?>
                <td style="text-align:center; font-weight:700;"><?= e($vv); ?></td>
              <?php endfor; ?>

              <td><?= e(fmt_date($row['tanggal'] ?? '')); ?></td>

              <td style="text-align:right;">
                <div class="jw-actions" style="justify-content:flex-end;">
                  <a class="btn btn-sm btn-soft-primary"
                     href="dashboard.php?menu=jawaban&detail=<?= (int)$row['id_jawaban']; ?>">
                    <i class="bi bi-eye"></i> Detail
                  </a>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="jw-mobile">
      <?php
      $stM = $koneksi->prepare($sqlList);
      if ($types !== "") {
        $types2 = $types . "ii";
        $bindM = array_merge($params, [$limit, $offset]);
        $stM->bind_param($types2, ...$bindM);
      } else {
        $stM->bind_param("ii", $limit, $offset);
      }
      $stM->execute();
      $listM = $stM->get_result();
      ?>

      <?php if ($listM->num_rows === 0): ?>
        <div style="padding:14px; color:var(--muted); text-align:center;">Belum ada data jawaban.</div>
      <?php else: ?>
        <?php $noM = $offset + 1; ?>
        <?php while ($row = $listM->fetch_assoc()): ?>
          <div class="jw-mcard">
            <div class="jw-mrow"><div class="jw-mk">No</div><div class="jw-mv"><?= (int)$noM++; ?></div></div>
            <div class="jw-mrow"><div class="jw-mk">id_jawaban</div><div class="jw-mv"><?= e($row['id_jawaban']); ?></div></div>
            <div class="jw-mrow"><div class="jw-mk">nama</div><div class="jw-mv"><?= e($row['nama'] ?? '-'); ?></div></div>
            <div class="jw-mrow"><div class="jw-mk">tanggal</div><div class="jw-mv"><?= e(fmt_date($row['tanggal'] ?? '')); ?></div></div>

            <div class="jw-mrow" style="margin-top:8px;">
              <div class="jw-mk">P1..P39</div>
              <div class="jw-mv" style="font-size:12px; font-weight:800;">
                <?php
                $parts = [];
                for ($i=1; $i<=39; $i++){
                  $v = $row["p{$i}"] ?? '';
                  $vv = ($v === '' || $v === null) ? '-' : (int)$v;
                  $parts[] = "P{$i}:{$vv}";
                }
                echo e(implode(' • ', $parts));
                ?>
              </div>
            </div>

            <div class="jw-mact">
              <a class="btn btn-soft-primary" href="dashboard.php?menu=jawaban&detail=<?= (int)$row['id_jawaban']; ?>">
                <i class="bi bi-eye"></i> Detail
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
      <?php $stM->close(); ?>
    </div>

  </div>

  <!-- PAGINATION -->
  <nav class="mt-2">
    <ul class="pagination jw-pagi justify-content-end mb-0">
      <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?= e($base . "&page=" . max(1, $page - 1)); ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
      </li>

      <?php
      $start = max(1, $page - 2);
      $end   = min($totalPages, $page + 2);

      if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="'.e($base.'&page=1').'">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }

      for ($p=$start; $p<=$end; $p++):
      ?>
        <li class="page-item <?= ($p === $page) ? 'active' : ''; ?>">
          <a class="page-link" href="<?= e($base . "&page=" . $p); ?>"><?= (int)$p; ?></a>
        </li>
      <?php endfor; ?>

      <?php
      if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        echo '<li class="page-item"><a class="page-link" href="'.e($base.'&page='.$totalPages).'">'.(int)$totalPages.'</a></li>';
      }
      ?>

      <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?= e($base . "&page=" . min($totalPages, $page + 1)); ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
      </li>
    </ul>
  </nav>

</div>

<?php $st->close(); ?>
