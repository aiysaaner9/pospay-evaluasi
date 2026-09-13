<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Guard admin
if (!isset($_SESSION['admin'])) {
  header("Location: login.php");
  exit;
}

// Ensure DB connection
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
  @require_once __DIR__ . "/../config/koneksi.php";
}
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
  echo '<div class="alert alert-danger">Koneksi database tidak tersedia. Pastikan <b>../config/koneksi.php</b> benar dan variabelnya <b>$koneksi</b>.</div>';
  return;
}

/* =========================
   Helpers
========================= */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function f2($n){ return number_format((float)$n, 2, ',', '.'); }

function capability_level(float $ica): array {
  if ($ica >= 4.51) return [5, "Dioptimalisasi"];
  if ($ica >= 3.51) return [4, "Dikelola"];
  if ($ica >= 2.51) return [3, "Ditetapkan"];
  if ($ica >= 1.51) return [2, "Dapat Diulang"];
  if ($ica >= 0.51) return [1, "Inisialisasi"];
  return [0, "Tidak Ada"];
}

function short_q($t, $max=110){
  $t = trim((string)$t);
  $t = preg_replace('/\s+/', ' ', $t);
  $t = preg_replace('/^\s*P\d+\s*[:\-]?\s*/i', '', $t);
  if (mb_strlen($t) > $max) $t = mb_substr($t, 0, $max-3) . '...';
  return $t;
}

function suggest_from_texts(array $texts): array {
  $all = mb_strtolower(implode(" ", $texts));
  $sugs = [];

  if (preg_match('/error|bug|gagal|crash|login|keluar|force|lemot|lambat|kendala|gangguan|stabil/', $all)) {
    $sugs[] = "Stabilkan aplikasi (kurangi error/lemot), terutama saat login & pemakaian.";
  }
  if (preg_match('/respon|tanggap|cepat|lama|menunggu|petugas|cs|layanan pelanggan|balasan|ditangani/', $all)) {
    $sugs[] = "Percepat respon layanan/CS dan buat status penanganan lebih jelas.";
  }
  if (preg_match('/tampilan|desain|menu|navigasi|mudah digunakan|user friendly|bingung|fitur|tata letak/', $all)) {
    $sugs[] = "Rapikan tampilan & alur supaya lebih mudah dipahami pengguna.";
  }
  if (preg_match('/informasi|jelas|panduan|alur|status|notifikasi|update/', $all)) {
    $sugs[] = "Perjelas info/panduan dan status proses agar pengguna tidak bingung.";
  }
  if (preg_match('/aman|keamanan|privasi|data|rahasia|akses/', $all)) {
    $sugs[] = "Perkuat keamanan & privasi data pengguna, serta atur aksesnya.";
  }
  if (!$sugs) $sugs[] = "Perbaiki SOP dan pengecekan rutin agar layanan konsisten dan stabil.";

  return array_values(array_unique($sugs));
}

function base64_logo_if_exists(string $path): string {
  if (!is_file($path)) return '';
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : (($ext === 'png') ? 'image/png' : 'image/*');
  $data = @file_get_contents($path);
  if ($data === false) return '';
  return 'data:'.$mime.';base64,'.base64_encode($data);
}

/* =========================
   GAP label (WEB) — singkat
========================= */
function gap_tag_short(int $gap): string {
  if ($gap <= 0) return "Sesuai";
  if ($gap === 1) return "Rapihin";
  if ($gap === 2) return "Perbaiki";
  if ($gap === 3) return "Prioritas";
  return "Kritis";
}

/* =========================
   Prioritas 0–5 (PDF saja)
========================= */
function priority_score_for_pdf(int $gap, float $avg): int {
  if ($gap <= 0) return 0;
  if ($gap === 1) return ($avg >= 3.50) ? 1 : 2;
  if ($gap === 2) return 3;
  if ($gap === 3) return 4;
  return 5; // gap >= 4
}
function priority_label_pdf(int $p): string {
  $map = [
    0 => "Tidak perlu (sudah sesuai target)",
    1 => "Sangat rendah (tinggal rapihin sedikit)",
    2 => "Rendah (kurang sedikit dari target)",
    3 => "Sedang (perlu program perbaikan)",
    4 => "Tinggi (butuh percepatan perbaikan)",
    5 => "Sangat tinggi (paling kritis/utama)",
  ];
  return $map[$p] ?? "Tidak diketahui";
}

/* =========================
   Penjelasan awam
========================= */
$cobitExplain = [
  'cobit' => "COBIT 5 adalah framework (kerangka) untuk menilai dan merapikan pengelolaan layanan/aplikasi. Dipakai agar evaluasi terstruktur dan hasilnya bisa jadi rencana perbaikan.",
  'apo'   => "APO fokus ke perencanaan & pengelolaan.",
  'dss'   => "DSS fokus ke operasional layanan & penanganan gangguan.",
  'mea'   => "MEA fokus ke pemantauan, evaluasi, dan perbaikan berkelanjutan.",
  'gap'   => "GAP adalah selisih Target dan Hasil (GAP = Target − Hasil). Makin besar GAP, makin jauh dari target.",
];

/* =========================
   Nama Proses (Indonesia)
========================= */
$cobitProcessLabel = [
  'APO01' => 'Mengelola Kerangka Manajemen TI',
  'APO02' => 'Mengelola Strategi',
  'APO03' => 'Mengelola Arsitektur Enterprise',
  'APO07' => 'Mengelola SDM',
  'APO12' => 'Mengelola Risiko',

  'DSS01' => 'Mengelola Operasional',
  'DSS02' => 'Mengelola Permintaan Layanan & Insiden',
  'DSS03' => 'Mengelola Masalah',
  'DSS05' => 'Mengelola Layanan Keamanan',
  'DSS06' => 'Mengelola Kontrol Proses Bisnis',

  'MEA01' => 'Memantau, Mengevaluasi, dan Menilai Kinerja',
  'MEA02' => 'Memantau Kontrol Internal',
  'MEA03' => 'Memantau Kepatuhan terhadap Kebutuhan Eksternal',
];
function process_label(string $code, array $map): string {
  $k = strtoupper(trim($code));
  return $map[$k] ?? 'Nama proses belum dimapping';
}

/* =========================
   Config Target
========================= */
$defaultTarget = 4;
$targetLevel = [
  // 'APO01' => 4,
];

/* =========================
   Respondent count
========================= */
$jumlahResponden = 0;
$res = $koneksi->query("SELECT COUNT(*) AS c FROM jawaban");
if ($res) $jumlahResponden = (int)($res->fetch_assoc()['c'] ?? 0);

if ($jumlahResponden === 0) {
  echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Belum ada data jawaban di tabel <b>jawaban</b>.</div>';
  return;
}

/* =========================
   Question mapping P1..P39 (AMAN)
========================= */
$qMap = [];
$resQ = $koneksi->query("
  SELECT p.id_pertanyaan, d.kode AS domain, p.sub_domain, p.pertanyaan_text
  FROM pertanyaan p
  JOIN domain d ON d.id_domain = p.id_domain
  ORDER BY p.id_pertanyaan ASC
");
if ($resQ) {
  while ($r = $resQ->fetch_assoc()) {
    $text = (string)($r['pertanyaan_text'] ?? '');
    $dom  = strtoupper((string)($r['domain'] ?? '-'));
    $sub  = strtolower((string)($r['sub_domain'] ?? 'unknown'));

    $num = null;
    if (preg_match('/^\s*P\s*([0-9]{1,3})\b/i', $text, $m)) {
      $num = (int)$m[1];
    }

    if ($num !== null && $num >= 1 && $num <= 39) {
      $qMap[$num] = [
        'domain' => $dom,
        'sub'    => $sub,
        'text'   => $text,
      ];
    }
  }
}
for ($i=1; $i<=39; $i++) {
  if (!isset($qMap[$i])) {
    $qMap[$i] = ['domain'=>'-', 'sub'=>'unknown', 'text'=>"P{$i}"];
  }
}

/* =========================
   ICA per question (AVG)
========================= */
$avgSelect = [];
for ($i=1; $i<=39; $i++) $avgSelect[] = "AVG(p{$i}) AS ica_p{$i}";
$sqlICA = "SELECT ".implode(", ", $avgSelect)." FROM jawaban";
$resICA = $koneksi->query($sqlICA);
$rowICA = $resICA ? $resICA->fetch_assoc() : [];

$ica = [];
for ($i=1; $i<=39; $i++) {
  $v = $rowICA["ica_p{$i}"] ?? null;
  $ica[$i] = ($v === null) ? 0.0 : (float)$v;
}

/* =========================
   Process scoring by sub_domain
========================= */
$process = [];
$subQuestions = [];

for ($i=1; $i<=39; $i++) {
  $meta = $qMap[$i];
  $sub  = strtoupper((string)$meta['sub']);
  $dom  = strtoupper((string)$meta['domain']);

  if (preg_match('/^([A-Z]{3})\s*0?([0-9]{1,2})$/', $sub, $mm)) {
    $sub = $mm[1] . str_pad($mm[2], 2, '0', STR_PAD_LEFT);
  }

  if (!isset($process[$sub])) {
    $process[$sub] = [
      'domain'=>$dom, 'sub'=>$sub,
      'sum'=>0.0, 'count'=>0,
      'avg'=>0.0, 'level'=>0, 'ket'=>'',
      'target'=>0, 'gap'=>0,
      'prio'=>0, 'prio_text'=>''
    ];
  }
  $process[$sub]['sum'] += $ica[$i];
  $process[$sub]['count'] += 1;

  if (!isset($subQuestions[$sub])) $subQuestions[$sub] = [];
  $subQuestions[$sub][] = (string)$meta['text'];
}

foreach ($process as $sub => $p) {
  $avg = $p['count'] ? ($p['sum']/$p['count']) : 0.0;
  [$lv, $ket] = capability_level($avg);

  $t = (int)($targetLevel[$sub] ?? $defaultTarget);
  $gap = $t - (int)$lv;
  $prio = priority_score_for_pdf((int)$gap, (float)$avg);

  $process[$sub]['avg']   = $avg;
  $process[$sub]['level'] = (int)$lv;
  $process[$sub]['ket']   = $ket;
  $process[$sub]['target']= $t;
  $process[$sub]['gap']   = (int)$gap;
  $process[$sub]['prio'] = (int)$prio;
  $process[$sub]['prio_text'] = priority_label_pdf((int)$prio);
}
uksort($process, fn($a,$b)=>strcmp((string)$a,(string)$b));

/* =========================
   Domain recap
========================= */
$domainRecap = [];
foreach ($process as $p) {
  $d = $p['domain'] ?: '-';
  if (!isset($domainRecap[$d])) $domainRecap[$d] = ['sum'=>0.0,'count'=>0,'avg'=>0.0,'level'=>0,'ket'=>''];
  $domainRecap[$d]['sum'] += (float)$p['avg'];
  $domainRecap[$d]['count'] += 1;
}
foreach ($domainRecap as $d => $v) {
  $avg = $v['count'] ? ($v['sum']/$v['count']) : 0.0;
  [$lv, $ket] = capability_level($avg);
  $domainRecap[$d]['avg'] = $avg;
  $domainRecap[$d]['level'] = (int)$lv;
  $domainRecap[$d]['ket'] = $ket;
}
ksort($domainRecap);

/* =========================
   Chart data
========================= */
$procLabels = array_map(fn($k)=>strtoupper((string)$k), array_keys($process));
$procScores = array_map(fn($p)=>round((float)$p['avg'], 2), array_values($process));
$radarLabels    = $procLabels;
$radarCurrent   = array_map(fn($p)=>(int)$p['level'], array_values($process));
$radarExpected  = array_map(fn($p)=>(int)$p['target'], array_values($process));

/* =========================
   Insights
========================= */
$allProcAvg = array_map(fn($p)=>(float)$p['avg'], array_values($process));
$avgAll = count($allProcAvg) ? array_sum($allProcAvg)/count($allProcAvg) : 0.0;
[$avgLevel, $avgKet] = capability_level($avgAll);

$gapArr = array_map(fn($p)=>(int)$p['gap'], array_values($process));
$gapAvg = count($gapArr) ? array_sum($gapArr)/count($gapArr) : 0.0;

$levelCounts = [];
foreach ($process as $p) { $levelCounts[(int)$p['level']] = ($levelCounts[(int)$p['level']] ?? 0) + 1; }
arsort($levelCounts);
$dominantLevel = (int)array_key_first($levelCounts);
$dominantCount = (int)($levelCounts[$dominantLevel] ?? 0);

/* =========================
   Need improve
========================= */
$needImprove = [];
foreach ($process as $sub => $p) {
  $gap = (int)$p['gap'];
  if ($gap > 0) {
    $texts = $subQuestions[$sub] ?? [];
    $samples = [];
    foreach ($texts as $t) {
      $samples[] = short_q($t, 115);
      if (count($samples) >= 2) break;
    }
    $needImprove[] = [
      'sub' => strtoupper((string)$sub),
      'dom' => strtoupper((string)$p['domain']),
      'gap' => (int)$gap,
      'avg' => (float)$p['avg'],
      'lv'  => (int)$p['level'],
      'tgt' => (int)$p['target'],
      'label' => process_label((string)$sub, $cobitProcessLabel),
      'samples' => $samples,
      'sugs' => suggest_from_texts($texts),
      'prio' => (int)$p['prio'],
      'prio_text' => (string)$p['prio_text'],
    ];
  }
}
usort($needImprove, function($a,$b){
  if ($b['prio'] !== $a['prio']) return $b['prio'] <=> $a['prio'];
  if ($b['gap'] !== $a['gap']) return $b['gap'] <=> $a['gap'];
  return $a['avg'] <=> $b['avg'];
});
$topNeed = array_slice($needImprove, 0, 4);

$webSugs = [];
foreach ($topNeed as $t) foreach (($t['sugs'] ?? []) as $s) $webSugs[] = $s;
$webSugs = array_values(array_unique($webSugs));
$webSugs = array_slice($webSugs, 0, 6);

$tanggalCetak = date('d F Y');

/* =========================
   EXPORT PDF via DOMPDF
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {

  error_reporting(E_ALL);
  ini_set('display_errors', '1');

  @ini_set('memory_limit', '512M');
  @set_time_limit(120);

  $autoload = realpath(__DIR__ . "/../vendor/autoload.php");
  if (!$autoload) $autoload = realpath(__DIR__ . "/../../vendor/autoload.php");
  if (!$autoload) {
    header("Content-Type: text/plain; charset=utf-8");
    echo "DOMPDF belum terdeteksi. Pastikan composer install dan file vendor/autoload.php ada.";
    exit;
  }
  require_once $autoload;

  $optionsClass = '\\Dompdf\\Options';
  $dompdfClass  = '\\Dompdf\\Dompdf';

  $logoData = '';
  $logoPath = realpath(__DIR__ . "/../assets/logo.png");
  if ($logoPath) {
    $tmp = base64_logo_if_exists($logoPath);
    if ($tmp) $logoData = $tmp;
  }

  $judulSkripsi = "EVALUASI KINERJA APLIKASI POSPAY PT. POS INDONESIA (PERSERO) MENGGUNAKAN FRAMEWORK COBIT 5";
  $subJudul     = "(STUDI KASUS: PT. POS INDONESIA (PERSERO) CABANG RENGAT)";

  // tabel rekomendasi
  $pdfRekom = [];
  foreach ($needImprove as $ni) {
    $pdfRekom[] = [
      'sub'  => $ni['sub'],
      'label'=> $ni['label'],
      'gap'  => (int)$ni['gap'],
      'prio' => (int)$ni['prio'],
      'aksi' => $ni['sugs'][0] ?? "Lakukan perbaikan bertahap sesuai temuan pengguna.",
    ];
  }

  // rekomendasi narasi (lebih rapi & panjang)
  $topForParagraph = array_slice($needImprove, 0, 4);
  $topRekomItems = [];
  if (!empty($topForParagraph)) {
    foreach ($topForParagraph as $t) {
      $aksi = $t['sugs'][0] ?? "Lakukan perbaikan bertahap sesuai temuan pengguna.";
      $topRekomItems[] = [
        'sub'   => strtoupper((string)$t['sub']),
        'label' => (string)$t['label'],
        'gap'   => (int)$t['gap'],
        'aksi'  => (string)$aksi,
      ];
    }

    $rekomIntro =
      "Rekomendasi perbaikan disusun berdasarkan proses-proses yang memiliki nilai GAP positif (target belum tercapai). "
      ."Semakin besar nilai GAP, semakin tinggi urgensi perbaikan. Prioritas perbaikan diarahkan pada proses yang berdampak langsung "
      ."terhadap kualitas layanan, pengalaman pengguna, serta pengendalian internal agar operasional aplikasi berjalan lebih stabil dan terukur.";

    $rekomPenjelasan =
      "Perbaikan dapat dilakukan secara bertahap melalui: (1) identifikasi akar masalah pada proses yang memiliki GAP, "
      ."(2) penyusunan rencana aksi dan penanggung jawab (PIC), (3) implementasi perbaikan sesuai timeline, "
      ."(4) monitoring dan evaluasi hasil perbaikan, serta (5) pembaruan SOP/kebijakan agar perbaikan konsisten. "
      ."Dengan pendekatan tersebut, perbaikan tidak berhenti pada tindakan teknis semata, tetapi juga memperkuat tata kelola dan kontrol operasional.";

    $rekomPenutup =
      "Implementasi rekomendasi berikut diharapkan mampu menurunkan nilai GAP, meningkatkan level kapabilitas proses, "
      ."serta memperkuat tata kelola layanan aplikasi POSPAY menuju target yang ditetapkan.";
  } else {
    $rekomIntro =
      "Berdasarkan hasil evaluasi, seluruh proses telah memenuhi target yang ditetapkan.";
    $rekomPenjelasan =
      "Walaupun demikian, disarankan untuk tetap melakukan monitoring berkala, pemeliharaan sistem, dan evaluasi rutin "
      ."agar kinerja tetap stabil serta dapat beradaptasi terhadap kebutuhan pengguna di masa mendatang.";
    $rekomPenutup =
      "Dengan pelaksanaan monitoring dan peningkatan berkelanjutan, kualitas layanan diharapkan tetap terjaga.";
  }

  $pdfCss = '
  @page { margin: 34px 34px; }
  body{ font-family:"Times New Roman", Times, serif; font-size:12pt; color:#111; line-height: 1.55; }
  .pdf-head-table{ width:100%; border-collapse:collapse; }
  .pdf-head-table td{ vertical-align:middle; }
  .pdf-logo{ width:72px; height:72px; border:1px solid #111; padding:6px; object-fit:contain; }
  .pdf-title{ text-align:center; }
  .pdf-title h1{ font-size:16pt; margin:0; font-weight:700; text-transform:uppercase; }
  .pdf-title h2{ font-size:12pt; margin:8px 0 0; font-weight:700; }
  .pdf-small{ font-size:10.8pt; color:#333; margin-top:6px; }
  .pdf-hr{ border-top:2px solid #111; margin:14px 0 18px; }
  .section{ margin-top: 6px; }
  .pagebreak{ page-break-before: always; }
  .pdf-section-title{ text-align:center; font-weight:700; margin: 0 0 14px; text-transform:uppercase; letter-spacing:.4px; }
  .pdf-p{ margin: 0 0 12px; text-align:justify; }
  .pdf-table{ width:100%; border-collapse:collapse; margin: 10px 0 18px; font-size:10.4pt; table-layout:fixed; }
  .pdf-table th, .pdf-table td{ border:1px solid #111; padding:7px; vertical-align:top; word-wrap:break-word; overflow-wrap:break-word; }
  .pdf-table th{ text-align:center; font-weight:700; background:#f2f2f2; }
  thead { display: table-header-group; }
  .pdf-right{ text-align:right; }
  .pdf-center{ text-align:center; }

  /* TTD model surat (tanpa nama & tanggal) */
  .ttd-wrap{ margin-top: 18px; }
  .ttd-table{ width:100%; border-collapse:collapse; }
  .ttd-col{ width:50%; text-align:center; vertical-align:top; }
  .ttd-space{ height:72px; }
  .ttd-line{ display:inline-block; width:260px; border-bottom:1px solid #111; height:0; margin-top:6px; }
  .ttd-name{ margin-top:6px; font-weight:700; }
  .ttd-role{ margin-top:4px; font-size:11pt; }
  ';

  ob_start();
  ?>
  <!doctype html>
  <html>
  <head><meta charset="utf-8"><style><?= $pdfCss ?></style></head>
  <body>

    <!-- HALAMAN 1 -->
    <div class="section">
      <table class="pdf-head-table">
        <tr>
          <td style="width:90px;">
            <?php if ($logoData): ?><img class="pdf-logo" src="<?= e($logoData) ?>" alt="Logo"><?php endif; ?>
          </td>
          <td class="pdf-title">
            <h1>LAPORAN HASIL EVALUASI COBIT 5 DAN REKOMENDASI PERBAIKAN</h1>
            <h2>Hasil, Rekomendasi, dan Kesimpulan</h2>
            <div class="pdf-small">
              <div style="margin-top:6px; font-weight:700;"><?= e($judulSkripsi); ?></div>
              <div style="margin-top:4px;"><?= e($subJudul); ?></div>
              <div style="margin-top:8px;">
                Responden: <b><?= number_format($jumlahResponden); ?></b> — Dicetak: <b><?= e($tanggalCetak); ?></b>
              </div>
            </div>
          </td>
          <td style="width:90px;"></td>
        </tr>
      </table>
      <div class="pdf-hr"></div>
    </div>

    <!-- PENDAHULUAN -->
    <div class="section">
      <div class="pdf-section-title">PENDAHULUAN</div>

      <p class="pdf-p">
      Laporan ini disusun sebagai bentuk evaluasi terhadap kinerja aplikasi POSPAY
      berdasarkan persepsi pengguna layanan. Evaluasi dilakukan untuk mengetahui sejauh
      mana aplikasi mampu memenuhi kebutuhan pengguna dari sisi stabilitas sistem,
      kemudahan penggunaan, kecepatan layanan, serta kejelasan informasi yang diberikan.
      Data penelitian diperoleh melalui penyebaran kuesioner kepada pengguna aplikasi,
      kemudian diolah secara kuantitatif untuk menghasilkan nilai rata-rata kapabilitas proses.
      </p>

      <p class="pdf-p">
      Penggunaan metode evaluasi berbasis framework COBIT 5 dipilih karena mampu
      memberikan struktur penilaian yang sistematis dan terukur. Framework ini membantu
      dalam mengidentifikasi area yang telah berjalan optimal sekaligus mendeteksi aspek yang
      masih memiliki kesenjangan terhadap target yang ditetapkan. Dengan demikian,
      hasil evaluasi tidak hanya menampilkan angka, tetapi juga menjadi dasar penyusunan
      strategi peningkatan kualitas layanan aplikasi secara berkelanjutan.
      </p>

      <p class="pdf-p">
      Melalui laporan ini diharapkan pihak pengelola aplikasi memperoleh gambaran
      menyeluruh mengenai kondisi aktual sistem, sehingga keputusan pengembangan dan
      perbaikan dapat dilakukan secara tepat sasaran, efisien, dan berorientasi pada kebutuhan
      pengguna.
      </p>
    </div>

    <!-- HALAMAN 2 -->
    <div class="section pagebreak">
      <div class="pdf-section-title">TABEL HASIL EVALUASI</div>
      <table class="pdf-table">
        <thead>
          <tr>
            <th style="width:8%;">No</th>
            <th style="width:12%;">Domain</th>
            <th style="width:13%;">Proses</th>
            <th style="width:35%;">Nama Proses</th>
            <th style="width:10%;">Skor</th>
            <th style="width:8%;">Lv</th>
            <th style="width:8%;">Tgt</th>
            <th style="width:8%;">GAP</th>
          </tr>
        </thead>
        <tbody>
          <?php $no=1; foreach ($process as $sub => $p): ?>
            <tr>
              <td class="pdf-center"><?= $no++; ?></td>
              <td class="pdf-center"><?= e($p['domain']); ?></td>
              <td class="pdf-center"><?= e(strtoupper($sub)); ?></td>
              <td><?= e(process_label((string)$sub, $cobitProcessLabel)); ?></td>
              <td class="pdf-right"><?= f2($p['avg']); ?></td>
              <td class="pdf-center"><?= (int)$p['level']; ?></td>
              <td class="pdf-center"><?= (int)$p['target']; ?></td>
              <td class="pdf-center"><?= (int)$p['gap']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- HALAMAN 3 -->
    <div class="section pagebreak">
      <div class="pdf-section-title">TABEL REKOMENDASI PERBAIKAN</div>
      <?php if (!empty($pdfRekom)): ?>
      <table class="pdf-table">
        <thead>
          <tr>
            <th style="width:8%;">No</th>
            <th style="width:14%;">Proses</th>
            <th style="width:38%;">Nama Proses</th>
            <th style="width:8%;">GAP</th>
            <th style="width:32%;">Saran Perbaikan</th>
          </tr>
        </thead>
        <tbody>
          <?php $no=1; foreach ($pdfRekom as $r): ?>
            <tr>
              <td class="pdf-center"><?= $no++; ?></td>
              <td class="pdf-center"><?= e($r['sub']); ?></td>
              <td><?= e($r['label']); ?></td>
              <td class="pdf-center"><?= (int)$r['gap']; ?></td>
              <td><?= e($r['aksi']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p class="pdf-p">Tidak ada rekomendasi perbaikan karena seluruh proses sudah memenuhi target.</p>
      <?php endif; ?>
    </div>

    <!-- HALAMAN 4 -->
    <div class="section pagebreak">
      <div class="pdf-section-title">REKOMENDASI PERBAIKAN</div>

      <p class="pdf-p">
      Berdasarkan hasil pengolahan data kuesioner dan analisis menggunakan framework
      COBIT 5, diperoleh rata-rata skor kapabilitas proses sebesar
      <b><?= f2($avgAll) ?></b> dengan level kapabilitas berada pada
      <b>Level <?= (int)$avgLevel ?></b> (<?= e($avgKet) ?>).
      Target yang digunakan dalam penelitian ini adalah <b>Level <?= (int)$defaultTarget ?></b>,
      sehingga rata-rata kesenjangan (GAP) yang diperoleh sebesar
      <b><?= f2($gapAvg) ?></b>. Nilai tersebut menunjukkan bahwa masih terdapat sejumlah proses yang
      berada di bawah target sehingga memerlukan tindakan perbaikan yang terencana.
      </p>

      <p class="pdf-p"><?= e($rekomIntro); ?></p>
      <p class="pdf-p"><?= e($rekomPenjelasan); ?></p>

      <?php if (!empty($topRekomItems)): ?>
        <ol style="margin: 0 0 12px 18px; padding: 0;">
          <?php foreach ($topRekomItems as $it): ?>
            <li style="margin: 0 0 10px 0; text-align:justify;">
              <b><?= e($it['sub']); ?></b> (<?= e($it['label']); ?>) — <b>GAP <?= (int)$it['gap']; ?></b><br>
              Rekomendasi: <?= e($it['aksi']); ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <p class="pdf-p"><?= e($rekomPenutup); ?></p>

      <!-- TTD rapi (tanpa nama & tanpa tanggal) -->
      <div class="ttd-wrap">
        <table class="ttd-table">
          <tr>
            <td class="ttd-col">
              <div>Executive Manager</div>
              <div class="ttd-space"></div>
              <div class="ttd-line"></div>
            </td>
            <td class="ttd-col">
              <div>Peneliti</div>
              <div class="ttd-space"></div>
              <div class="ttd-line"></div>
            </td>
          </tr>
        </table>
      </div>

    </div>

  </body>
  </html>
  <?php
  $html = ob_get_clean();

  if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    header("Content-Type: text/plain; charset=utf-8");
    echo "HTML LENGTH: " . strlen($html) . "\n\n";
    echo $html;
    exit;
  }

  if (trim($html) === '' || strlen($html) < 200) {
    header("Content-Type: text/plain; charset=utf-8");
    echo "HTML untuk PDF kosong/terlalu pendek. Panjang: ".strlen($html)."\n";
    exit;
  }

  $opt = new $optionsClass();
  $opt->set('isRemoteEnabled', false);
  $opt->set('isHtml5ParserEnabled', true);
  $opt->set('defaultFont', 'Times-Roman');

  $dompdf = new $dompdfClass($opt);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');

  $dompdf->render();

  $output = $dompdf->output();
  if (!$output || strlen($output) < 800) {
    header("Content-Type: text/plain; charset=utf-8");
    echo "PDF output terlalu kecil/blank. Output bytes: ".strlen($output)."\n";
    echo "Coba naikkan memory_limit & cek HTML debug.\n";
    exit;
  }

  header("Content-Type: application/pdf");
  header('Content-Disposition: attachment; filename="Laporan_COBIT5.pdf"');
  echo $output;
  exit;
}

?>
<!-- =========================
     UI (HTML + CSS + JS)
========================= -->
<style>
/* (CSS kamu: aku biarin persis kayak yang kamu kasih) */
:root{
  --text: rgba(17,24,39,.92);
  --muted: rgba(17,24,39,.62);
  --border: rgba(17,24,39,.10);
  --shadow: 0 12px 28px rgba(0,0,0,.12);
  --accent: rgba(90,84,232,.95);
  --accentSoft: rgba(90,84,232,.10);
  --ok: rgba(25,135,84,.85);
  --warn: rgba(255,193,7,.90);
  --bad: rgba(220,53,69,.86);
}
[data-theme="dark"], [data-bs-theme="dark"]{
  --text: rgba(255,255,255,.92);
  --muted: rgba(255,255,255,.70);
  --border: rgba(255,255,255,.14);
  --shadow: 0 14px 30px rgba(0,0,0,.28);
  --accent: rgba(160,145,255,.98);
  --accentSoft: rgba(160,145,255,.12);
  --ok: rgba(46, 204, 113, .82);
  --warn: rgba(255, 193, 7, .88);
  --bad: rgba(255, 99, 132, .86);
}
.report-wrap{ display:grid; gap:14px; }
.krow{ display:grid; grid-template-columns:1fr; gap:14px; }
@media (min-width: 992px){ .krow{ grid-template-columns: 1.05fr .95fr; } }
.insights{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; }
@media (max-width: 992px){ .insights{ grid-template-columns: 1fr; } }
.card-soft{
  border:1px solid var(--border);
  border-radius:18px;
  background: rgba(255,255,255,.02);
  box-shadow: var(--shadow);
  overflow:hidden;
}
.card-soft .head{
  padding:14px;
  border-bottom:1px solid var(--border);
  display:flex; flex-wrap:wrap; gap:10px;
  align-items:center; justify-content:space-between;
  background: rgba(255,255,255,.02);
}
.card-soft .body{ padding:14px; color: var(--text); }
.insight-card{
  border:1px solid var(--border);
  border-radius:18px;
  padding:14px;
  background: rgba(255,255,255,.02);
  box-shadow: var(--shadow);
}
.ins-title{ font-size: 12px; color: var(--muted); }
.ins-value{ font-size: 22px; font-weight: 900; line-height: 1.1; color: var(--text); }
.ins-sub{ font-size: 12px; color: var(--muted); margin-top: 4px; }
.chart-box{
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: rgba(255,255,255,.02);
}
.chips{ display:flex; flex-wrap:wrap; gap:8px; }
.chip{
  border:1px solid rgba(17,24,39,.10);
  background: rgba(255,255,255,.04);
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  color: var(--muted);
}
[data-theme="dark"] .chip,[data-bs-theme="dark"] .chip{
  border-color: rgba(255,255,255,.14);
  background: rgba(255,255,255,.04);
  color: rgba(255,255,255,.72);
}
.pill-sub{
  display:inline-flex; padding:6px 12px; border-radius:999px;
  font-weight:900; font-size:12px;
  border:1px solid var(--border);
  background: rgba(255,255,255,.04);
  color: var(--text);
}
.pill-mini{
  display:inline-flex; padding:6px 12px; border-radius:999px;
  font-weight:900; font-size:12px;
  border:1px solid var(--border);
  background: rgba(255,255,255,.04);
  color: var(--text);
}
.exportbar{
  display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:flex-end;
  padding: 10px 12px;
  border:1px solid var(--border);
  border-radius:18px;
  background: rgba(255,255,255,.02);
  box-shadow: var(--shadow);
}
.exporthint{ margin-right:auto; font-size:12px; color:var(--muted); display:flex; align-items:center; gap:8px; }
.exportbtn{
  border:1px solid rgba(90,84,232,.28);
  background: rgba(90,84,232,.10);
  color: var(--text);
  border-radius: 14px;
  padding: 9px 12px;
  font-weight: 900;
  font-size: 12px;
  display:inline-flex; align-items:center; gap:8px;
  transition: transform .08s ease, background .15s ease, border-color .15s ease;
  user-select:none; text-decoration:none; cursor:pointer;
}
.exportbtn:hover{
  transform: translateY(-1px);
  background: rgba(90,84,232,.14);
  border-color: rgba(90,84,232,.34);
}
[data-theme="dark"] .exportbtn,[data-bs-theme="dark"] .exportbtn{
  border-color: rgba(160,145,255,.28);
  background: rgba(160,145,255,.10);
}
.table-wrap{
  border:1px solid var(--border);
  border-radius:18px;
  overflow:hidden;
  background: rgba(255,255,255,.02);
}
.table-snap{
  width:100%;
  border-collapse: separate;
  border-spacing:0;
  color: var(--text);
  margin:0;
  min-width: 980px;
}
.table-snap thead th{
  position: sticky; top: 0; z-index: 2;
  padding: 12px 12px;
  font-weight: 900;
  font-size: 13px;
  border-bottom: 1px solid rgba(17,24,39,.10);
  background: rgba(17,24,39,.04);
  color: rgba(17,24,39,.86);
  backdrop-filter: blur(8px);
  white-space: nowrap;
}
.table-snap tbody td{
  padding: 12px 12px;
  border-bottom: 1px solid rgba(17,24,39,.06);
  color: var(--text);
  vertical-align: middle;
  background: transparent;
}
[data-theme="dark"] .table-snap thead th,
[data-bs-theme="dark"] .table-snap thead th{
  background: rgba(255,255,255,.06);
  color: rgba(255,255,255,.92);
  border-bottom-color: rgba(255,255,255,.12);
}
[data-theme="dark"] .table-snap tbody td,
[data-bs-theme="dark"] .table-snap tbody td{
  border-bottom-color: rgba(255,255,255,.08);
}
.table-snap tbody tr{ transition: background-color .12s ease; }
.table-snap tbody tr:hover td{ background: rgba(90,84,232,.06); }
[data-theme="dark"] .table-snap tbody tr:hover td,
[data-bs-theme="dark"] .table-snap tbody tr:hover td{ background: rgba(160,145,255,.10); }
.gapcell{ display:flex; flex-direction:column; gap:8px; min-width: 160px; }
.gapnum{ font-weight: 900; line-height: 1; }
.gapbar{
  height: 10px;
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid var(--border);
  background: rgba(255,255,255,.04);
}
.gapfill{ height:100%; width:0%; border-radius:999px; }
.stat-ok{ color: rgba(25,135,84,.95); font-weight: 900; }
.stat-bad{ color: rgba(220,53,69,.95); font-weight: 900; }
.stat-mid{ color: rgba(255,193,7,.95); font-weight: 900; }
[data-theme="dark"] .stat-ok,[data-bs-theme="dark"] .stat-ok{ color: rgba(170, 255, 210, .92); }
[data-theme="dark"] .stat-bad,[data-bs-theme="dark"] .stat-bad{ color: rgba(255, 180, 190, .92); }
[data-theme="dark"] .stat-mid,[data-bs-theme="dark"] .stat-mid{ color: rgba(255, 235, 160, .92); }
</style>

<div class="report-wrap">

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <div>
      <div class="fw-bold fs-5">Laporan Evaluasi COBIT 5</div>
      <div class="small" style="color:var(--muted)">
        Responden mengisi: <span class="fw-semibold"><?= number_format($jumlahResponden); ?></span>
      </div>
    </div>
    <div class="chips">
      <div class="chip"><i class="bi bi-bullseye me-1"></i> Target default: <b><?= (int)$defaultTarget; ?></b></div>
      <div class="chip"><i class="bi bi-calculator me-1"></i> GAP = Target - Hasil</div>
    </div>
  </div>

  <div class="exportbar">
    <div class="exporthint">
      <span class="pill-sub"><i class="bi bi-download me-1"></i> Export</span>
    </div>
    <button type="button" class="exportbtn" id="__btnPdf"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
    <button type="button" class="exportbtn" id="__btnXls"><i class="bi bi-file-earmark-excel"></i> Excel</button>
  </div>

  <div class="card-soft">
    <div class="head">
      <div class="fw-semibold"><i class="bi bi-chat-left-text me-1"></i> Kesimpulan Hasil Evaluasi dan Rekomendasi Perbaikan</div>
    </div>
    <div class="body">

      <div class="p-3 mb-3" style="border:1px solid var(--border); border-radius:16px; background:rgba(255,255,255,.02);">
        <div class="fw-semibold mb-1">Apa itu COBIT 5 & domain yang dipakai?</div>
        <ul class="mb-0" style="color:var(--muted)">
          <li><b>COBIT 5</b>: <?= e($cobitExplain['cobit']); ?></li>
          <li><b>APO</b>: <?= e($cobitExplain['apo']); ?></li>
          <li><b>DSS</b>: <?= e($cobitExplain['dss']); ?></li>
          <li><b>MEA</b>: <?= e($cobitExplain['mea']); ?></li>
          <li><b>GAP</b>: <?= e($cobitExplain['gap']); ?></li>
        </ul>
      </div>

      <div class="p-3 mb-3" style="border:1px solid var(--border); border-radius:16px; background:rgba(255,255,255,.02);">
        <div class="fw-semibold mb-1">Gambaran umum:</div>
        <div class="small" style="color:var(--muted)">
          Rata-rata nilai keseluruhan <b style="color:var(--text)"><?= f2($avgAll); ?></b>
          (Level <b style="color:var(--text)"><?= (int)$avgLevel; ?></b> / <?= e($avgKet); ?>).
          Target <b style="color:var(--text)">Level <?= (int)$defaultTarget; ?></b>.
        </div>
      </div>

      <?php if (!empty($topNeed)): ?>
        <div class="fw-semibold mb-2">Yang paling perlu diperbaiki:</div>
        <div class="row g-2 mb-2">
          <?php foreach ($topNeed as $t): ?>
            <?php $g = (int)$t['gap']; $tag = gap_tag_short($g); ?>
            <div class="col-12 col-lg-6">
              <div class="p-3" style="border:1px solid var(--border); border-radius:16px; background:rgba(255,255,255,.02);">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">
                      <span class="badge text-bg-primary" style="border-radius:999px; font-weight:900;"><?= e($t['dom']); ?></span>
                      <span class="badge text-bg-secondary ms-1" style="border-radius:999px; font-weight:900;"><?= e(strtolower($t['sub'])); ?></span>
                    </div>
                    <div class="small" style="color:var(--muted)"><?= e($t['label']); ?></div>
                    <div class="small" style="color:var(--muted)">
                      Skor: <b style="color:var(--text)"><?= f2($t['avg']); ?></b> • Target: <b style="color:var(--text)">Lv <?= (int)$t['tgt']; ?></b> • GAP: <b style="color:var(--text)"><?= $g; ?></b> (<?= e($tag); ?>)
                    </div>
                  </div>
                  <span class="pill-sub">GAP <?= $g; ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="fw-semibold mt-2 mb-2">Rekomendasi Perbaikan:</div>
        <ol class="mb-0">
          <?php foreach ($webSugs as $s): ?>
            <li><?= e($s); ?></li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <div class="alert alert-success mb-0" style="border-radius:14px;">
          Kabar baik: mayoritas proses sudah memenuhi target.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="insights">
    <div class="insight-card">
      <div class="ins-title"><i class="bi bi-speedometer2 me-1"></i> Rata-rata Skor Proses</div>
      <div class="ins-value"><?= f2($avgAll); ?></div>
      <div class="ins-sub">Level: <b><?= (int)$avgLevel; ?></b> (<?= e($avgKet); ?>)</div>
    </div>
    <div class="insight-card">
      <div class="ins-title"><i class="bi bi-layers me-1"></i> Level Dominan</div>
      <div class="ins-value"><?= (int)$dominantLevel; ?></div>
      <div class="ins-sub"><?= $dominantCount; ?> proses berada di level ini</div>
    </div>
    <div class="insight-card">
      <div class="ins-title"><i class="bi bi-bullseye me-1"></i> Rata-rata GAP</div>
      <div class="ins-value"><?= f2($gapAvg); ?></div>
      <div class="ins-sub">Semakin kecil GAP, semakin mendekati target</div>
    </div>
  </div>

  <div class="krow">
    <div class="card-soft">
      <div class="head">
        <div class="fw-semibold"><i class="bi bi-bar-chart me-1"></i> Skor Proses</div>
        <div class="small" style="color:var(--muted)">Grafik batang skor proses (0–5)</div>
      </div>
      <div class="body">
        <div class="chart-box">
          <canvas id="chartBarProcess" height="240"></canvas>
        </div>
      </div>
    </div>

    <div class="card-soft">
      <div class="head">
        <div class="fw-semibold"><i class="bi bi-radar me-1"></i> Analisis GAP</div>
        <div class="small" style="color:var(--muted)">Radar Expected vs Current</div>
      </div>
      <div class="body">
        <div class="chart-box">
          <canvas id="chartRadarGap" height="240"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="card-soft">
    <div class="head">
      <div class="fw-semibold"><i class="bi bi-clipboard-data me-1"></i> Tabel Proses</div>
      <div class="small" style="color:var(--muted)">Domain • Proses • Skor • Level • Target • Gap</div>
    </div>
    <div class="body">
      <div class="table-wrap">
        <div class="table-responsive">
          <table class="table-snap" id="__tableProses">
            <thead>
              <tr>
                <th style="width:120px;">Domain</th>
                <th style="width:140px;">Proses</th>
                <th style="width:140px;">Skor Proses</th>
                <th style="width:130px;">Level Hasil</th>
                <th style="width:120px;">Target</th>
                <th style="width:200px;">Gap</th>
                <th style="width:170px;">Keterangan</th>
                <th style="width:150px;">Hasil</th>
                <th style="width:120px;" class="text-end">Jml Pertanyaan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($process as $sub => $p): ?>
                <?php
                  $gap = (int)$p['gap'];
                  $gapCap = max(0, min(4, $gap));
                  $w = (int)round(($gapCap/4)*100);
                  $fill = ($gap <= 0) ? 'var(--ok)' : (($gap === 1) ? 'var(--warn)' : 'var(--bad)');
                  if ($gap <= 0) { $status = "Target tercapai"; $statusClass = "stat-ok"; }
                  elseif ($gap === 1) { $status = "Sedikit di bawah target"; $statusClass = "stat-mid"; }
                  else { $status = "Belum tercapai"; $statusClass = "stat-bad"; }
                  $procCode = strtolower((string)$sub);
                ?>
                <tr>
                  <td><span class="badge text-bg-primary" style="border-radius:999px; font-weight:900;"><?= e(strtoupper((string)$p['domain'])); ?></span></td>
                  <td><span class="badge text-bg-secondary" style="border-radius:999px; font-weight:900;"><?= e($procCode); ?></span></td>
                  <td class="fw-semibold"><?= f2($p['avg']); ?></td>
                  <td><span class="pill-mini">Lv <?= (int)$p['level']; ?></span></td>
                  <td><span class="pill-mini">Lv <?= (int)$p['target']; ?></span></td>
                  <td>
                    <div class="gapcell">
                      <div class="gapnum"><?= (int)$gap; ?></div>
                      <div class="gapbar"><div class="gapfill" style="width: <?= $w; ?>%; background: <?= e($fill); ?>;"></div></div>
                    </div>
                  </td>
                  <td class="<?= e($statusClass); ?>"><?= e($status); ?></td>
                  <td class="fw-semibold"><?= e($p['ket']); ?></td>
                  <td class="text-end fw-semibold"><?= (int)$p['count']; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="small mt-2" style="color:var(--muted)">
        Cara baca: <b>Gap</b> = Target − Level Hasil. Jika <b>0</b berarti target tercapai. Jika <b>1+</b berarti perlu perbaikan.
      </div>
    </div>
  </div>

  <div class="card-soft">
    <div class="head">
      <div class="fw-semibold"><i class="bi bi-diagram-3 me-1"></i> Rekap Domain</div>
      <div class="small" style="color:var(--muted)">Rata-rata skor per domain</div>
    </div>
    <div class="body">
      <div class="table-wrap">
        <div class="table-responsive">
          <table class="table-snap" id="__tableDomain">
            <thead>
              <tr>
                <th style="width:160px;">Domain</th>
                <th style="width:160px;">Skor</th>
                <th style="width:140px;">Level</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($domainRecap as $dom => $v): ?>
                <tr>
                  <td><span class="badge text-bg-primary" style="border-radius:999px; font-weight:900;"><?= e($dom); ?></span></td>
                  <td class="fw-semibold"><?= f2($v['avg']); ?></td>
                  <td><span class="pill-mini">Lv <?= (int)$v['level']; ?></span></td>
                  <td class="fw-semibold"><?= e($v['ket']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
if (typeof Chart === "undefined") {
  var s = document.createElement("script");
  s.src = "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
  s.onload = setupCharts;
  document.head.appendChild(s);
} else setupCharts();

let __barChart = null;
let __radarChart = null;

function setupCharts(){
  drawCharts();
  const obs = new MutationObserver(() => drawCharts(true));
  obs.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme", "data-bs-theme"] });
}
function destroyCharts(){
  if (__barChart) { __barChart.destroy(); __barChart = null; }
  if (__radarChart) { __radarChart.destroy(); __radarChart = null; }
}
function drawCharts(force=false){
  const procLabels = <?= json_encode($procLabels); ?>;
  const procScores = <?= json_encode($procScores); ?>;
  const radarLabels  = <?= json_encode($radarLabels); ?>;
  const radarExpected  = <?= json_encode($radarExpected); ?>;
  const radarCurrent = <?= json_encode($radarCurrent); ?>;

  const root = document.documentElement;
  const isDark = (root.getAttribute("data-theme") === "dark") || (root.getAttribute("data-bs-theme") === "dark");

  const gridColor   = isDark ? "rgba(255,255,255,0.14)" : "rgba(17,24,39,0.12)";
  const tickColor   = isDark ? "rgba(255,255,255,0.80)" : "rgba(17,24,39,0.72)";
  const legendColor = isDark ? "rgba(255,255,255,0.88)" : "rgba(17,24,39,0.78)";

  if (force) destroyCharts();

  const barEl = document.getElementById("chartBarProcess");
  if (barEl && !__barChart) {
    __barChart = new Chart(barEl, {
      type: "bar",
      data: {
        labels: procLabels,
        datasets: [{
          label: "Skor Proses",
          data: procScores,
          backgroundColor: isDark ? "rgba(160,145,255,0.26)" : "rgba(90,84,232,0.20)",
          borderColor: isDark ? "rgba(160,145,255,0.85)" : "rgba(90,84,232,0.72)",
          borderWidth: 1.2,
          borderRadius: 10,
          barPercentage: 0.72,
          categoryPercentage: 0.78
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, suggestedMax: 5, ticks: { stepSize: 1, color: tickColor }, grid: { color: gridColor } },
          x: { ticks: { maxRotation: 0, autoSkip: true, color: tickColor }, grid: { display: false } }
        }
      }
    });
    barEl.parentElement.style.height = "260px";
  }

  const radarEl = document.getElementById("chartRadarGap");
  if (radarEl && !__radarChart) {
    __radarChart = new Chart(radarEl, {
      type: "radar",
      data: {
        labels: radarLabels,
        datasets: [
          {
            label: "Expected",
            data: radarExpected,
            borderColor: isDark ? "rgba(88,176,255,0.85)" : "rgba(13,110,253,0.72)",
            backgroundColor: isDark ? "rgba(88,176,255,0.12)" : "rgba(13,110,253,0.10)",
            borderWidth: 2,
            pointRadius: 2
          },
          {
            label: "Current",
            data: radarCurrent,
            borderColor: isDark ? "rgba(160,145,255,0.88)" : "rgba(90,84,232,0.72)",
            backgroundColor: isDark ? "rgba(160,145,255,0.12)" : "rgba(90,84,232,0.08)",
            borderWidth: 2,
            pointRadius: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: "bottom", labels: { color: legendColor } } },
        scales: {
          r: {
            min: 0, max: 5,
            ticks: { stepSize: 1, color: tickColor, backdropColor: "transparent" },
            grid: { color: gridColor },
            angleLines: { color: gridColor },
            pointLabels: { color: tickColor }
          }
        }
      }
    });
    radarEl.parentElement.style.height = "260px";
  }
}

/* =========================
   EXPORT Excel (.xls)
========================= */
function __downloadBlob(filename, mime, content){
  const blob = new Blob([content], {type:mime});
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(()=>URL.revokeObjectURL(url), 1000);
}
function __exportExcelXls(){
  const t1 = document.getElementById("__tableProses");
  const t2 = document.getElementById("__tableDomain");
  if (!t1 || !t2) return;

  const html =
`<!doctype html>
<html><head><meta charset="utf-8">
<style>
  body{font-family:Arial,Helvetica,sans-serif;font-size:12px}
  h2{font-size:14px;margin:8px 0}
  table{border-collapse:collapse;width:100%}
  td,th{border:1px solid #111;padding:6px;vertical-align:top}
  th{background:#f2f2f2}
</style>
</head><body>
<h2>Tabel Proses</h2>
${t1.outerHTML}
<br>
<h2>Rekap Domain</h2>
${t2.outerHTML}
</body></html>`;

  __downloadBlob("Laporan_COBIT5.xls", "application/vnd.ms-excel;charset=utf-8", html);
}

/* Bind */
document.getElementById("__btnPdf")?.addEventListener("click", ()=>{
  window.location.href = "laporan.php?export=pdf";
});
document.getElementById("__btnXls")?.addEventListener("click", __exportExcelXls);
</script>
