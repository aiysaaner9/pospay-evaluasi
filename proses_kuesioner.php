<?php

if (session_status() === PHP_SESSION_NONE) session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* =========================
   Guard: harus ada identitas
========================= */
if (!isset($_SESSION['identitas'])) {
    header("Location: kuesioner.php?step=1");
    exit;
}

/* =========================
   Koneksi DB
========================= */
$DB_HOST = "localhost";
$DB_USER = "anerstco_aiysa";
$DB_PASS = "V2gk5CE1wsbbc)&U";
$DB_NAME = "anerstco_db_pospay_evaluasi";

$koneksi = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$koneksi->set_charset("utf8mb4");

/* =========================
   Ambil identitas dari session
========================= */
$identitas = $_SESSION['identitas'];

$nama             = trim((string)($identitas['nama'] ?? ''));
$jenis_kelamin    = trim((string)($identitas['jenis_kelamin'] ?? ''));
$jenis_pengguna   = trim((string)($identitas['jenis_pengguna'] ?? ''));
$pekerjaan        = trim((string)($identitas['pekerjaan'] ?? ''));
$lama_menggunakan = trim((string)($identitas['lamaPakai'] ?? ''));
$tanggal          = trim((string)($identitas['tanggal'] ?? date('Y-m-d')));

/* =========================
   Validasi minimal identitas
========================= */
if ($nama === '' || $jenis_kelamin === '' || $jenis_pengguna === '' || $lama_menggunakan === '') {
    header("Location: kuesioner.php?step=1");
    exit;
}

/* =========================
   Ambil jawaban p1..p39
========================= */
$jawaban = [];
for ($i = 1; $i <= 39; $i++) {
    $key = 'p' . $i;
    if (!isset($_POST[$key])) {
        header("Location: kuesioner.php?step=2");
        exit;
    }
    $v = (int)$_POST[$key];
    if ($v < 0 || $v > 5) $v = 0;
    $jawaban[$i] = $v;
}

/* =========================
   Simpan ke DB (Transaksi)
========================= */
$koneksi->begin_transaction();

try {
    // 1) Insert responden
    $stmt = $koneksi->prepare(
        "INSERT INTO responden (nama, jenis_kelamin, jenis_pengguna, pekerjaan, lama_menggunakan, tanggal)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ssssss", $nama, $jenis_kelamin, $jenis_pengguna, $pekerjaan, $lama_menggunakan, $tanggal);
    $stmt->execute();

    $id_responden = $koneksi->insert_id;
    $stmt->close();

    // 2) Insert jawaban
    $sql = "INSERT INTO jawaban (
        id_responden, p1, p2, p3, p4, p5, p6, p7, p8, p9, p10,
        p11, p12, p13, p14, p15, p16, p17, p18, p19, p20,
        p21, p22, p23, p24, p25, p26, p27, p28, p29, p30,
        p31, p32, p33, p34, p35, p36, p37, p38, p39, tanggal
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
    )";

    $stmt2 = $koneksi->prepare($sql);

    // 40 integer: 1 id_responden + 39 jawaban
    $types = str_repeat("i", 40);

    $stmt2->bind_param(
        $types,
        $id_responden,
        $jawaban[1], $jawaban[2], $jawaban[3], $jawaban[4], $jawaban[5], $jawaban[6], $jawaban[7], $jawaban[8], $jawaban[9], $jawaban[10],
        $jawaban[11], $jawaban[12], $jawaban[13], $jawaban[14], $jawaban[15], $jawaban[16], $jawaban[17], $jawaban[18], $jawaban[19], $jawaban[20],
        $jawaban[21], $jawaban[22], $jawaban[23], $jawaban[24], $jawaban[25], $jawaban[26], $jawaban[27], $jawaban[28], $jawaban[29], $jawaban[30],
        $jawaban[31], $jawaban[32], $jawaban[33], $jawaban[34], $jawaban[35], $jawaban[36], $jawaban[37], $jawaban[38], $jawaban[39]
    );
    $stmt2->execute();
    $stmt2->close();

    // commit transaksi
    $koneksi->commit();

    // bersihkan session identitas (biar refresh nggak dobel insert)
    unset($_SESSION['identitas']);

    // redirect sukses
    header("Location: terima_kasih.php");
    exit;

} catch (Throwable $e) {
    $koneksi->rollback();
    // kalau mau versi rapi, bisa redirect ke halaman error.
    echo "Gagal simpan: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} finally {
    $koneksi->close();
}
