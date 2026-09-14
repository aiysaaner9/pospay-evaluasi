<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../config/koneksi.php";

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
  http_response_code(500);
  echo "Koneksi database tidak tersedia. Pastikan ../config/koneksi.php benar dan variabelnya \$koneksi.";
  exit;
}

/* =========================
   Helper: CSV-safe cell
   - Cegah baris pecah (hapus \r\n jadi spasi)
   - Cegah Excel formula injection (awal =,+,-,@)
========================= */
function csv_cell($v): string {
  if ($v === null) return '';
  $s = (string)$v;

  // rapikan newline biar ga pecah baris di Excel
  $s = str_replace(["\r\n", "\n", "\r"], ' ', $s);

  // trim ringan (opsional, biar bersih)
  $s = trim($s);

  // anti formula injection
  if ($s !== '' && preg_match('/^[=\+\-@]/', $s)) {
    $s = "'" . $s;
  }

  return $s;
}

// ambil filter dari halaman kelola_jawaban
$search = trim((string)($_GET['q'] ?? ''));

// where (ikut list SQL jawaban: id_jawaban, id_responden, tanggal)
$where  = "1=1";
$params = [];
$types  = "";

if ($search !== "") {
  $where .= " AND (
    CAST(j.id_jawaban AS CHAR) LIKE ? OR
    CAST(j.id_responden AS CHAR) LIKE ? OR
    j.tanggal LIKE ?
  )";
  $like = "%{$search}%";
  $params = [$like, $like, $like];
  $types  = "sss";
}

// kolom p1..p39
$pCols = [];
for ($i=1; $i<=39; $i++) $pCols[] = "p{$i}";

$sql = "SELECT
          j.id_jawaban, j.id_responden,
          " . implode(", ", array_map(fn($c)=>"j.$c", $pCols)) . ",
          j.tanggal
        FROM jawaban j
        WHERE $where
        ORDER BY j.id_jawaban DESC";

$st = $koneksi->prepare($sql);
if ($types !== "") $st->bind_param($types, ...$params);
$st->execute();
$res = $st->get_result();

// header download
$filename = "jawaban_kuesioner_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

// output stream
$out = fopen('php://output', 'w');

// UTF-8 BOM biar Excel tidak “acak” karakter
fwrite($out, "\xEF\xBB\xBF");

// header kolom (PERSIS struktur tabel jawaban)
$headers = ['id_jawaban','id_responden'];
for ($i=1; $i<=39; $i++) $headers[] = "p{$i}";
$headers[] = 'tanggal';

// delimiter ; biar Excel Indonesia aman
fputcsv($out, $headers, ';');

// data rows
while ($row = $res->fetch_assoc()) {
  $line = [];

  $line[] = csv_cell($row['id_jawaban'] ?? '');
  $line[] = csv_cell($row['id_responden'] ?? '');

  for ($i=1; $i<=39; $i++) {
    $key = "p{$i}";
    // kalau null/kosong -> ''
    $val = $row[$key] ?? '';

    // pastikan jawaban angka jadi stabil (string angka)
    // kalau val bukan angka, tetap simpan apa adanya
    if ($val !== '' && $val !== null && is_numeric($val)) {
      $val = (string)(int)$val;
    }

    $line[] = csv_cell($val);
  }

  // tanggal biarkan dari DB (persis tabel)
  $line[] = csv_cell($row['tanggal'] ?? '');

  fputcsv($out, $line, ';');
}

fclose($out);
$st->close();
exit;
