<?php
// kuesioner.php (FINAL 1 FILE - 2 STEP + FONT POPPINS)
// - Step 1 simpan identitas ke SESSION
// - Step 2 isi pertanyaan P1-P39 lalu submit ke proses_kuesioner.php
// - Font: Poppins, sans-serif

if (session_status() === PHP_SESSION_NONE) session_start();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean($s){ return trim((string)$s); }

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step !== 1 && $step !== 2) $step = 1;

$errors = [];

// ====== HANDLE SUBMIT STEP 1 (IDENTITAS) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_identitas') {
    $nama      = clean($_POST['nama'] ?? '');
    $jk        = clean($_POST['jenis_kelamin'] ?? '');
    $jp        = clean($_POST['jenis_pengguna'] ?? '');
    $pekerjaan = clean($_POST['pekerjaan'] ?? '');
    $lamaPakai = clean($_POST['lamaPakai'] ?? '');
    $tanggal   = clean($_POST['tanggal'] ?? date('Y-m-d'));

    if ($nama==='') $errors[] = "Nama wajib diisi.";
    if ($jk==='') $errors[] = "Jenis kelamin wajib dipilih.";
    if ($jp==='') $errors[] = "Jenis pengguna wajib dipilih.";
    if ($lamaPakai==='') $errors[] = "Lama menggunakan POSPAY wajib dipilih.";

    $_SESSION['identitas'] = [
        'nama' => $nama,
        'jenis_kelamin' => $jk,
        'jenis_pengguna' => $jp,
        'pekerjaan' => $pekerjaan,
        'lamaPakai' => $lamaPakai,
        'tanggal' => $tanggal ?: date('Y-m-d'),
    ];

    if (!$errors) {
        header("Location: kuesioner.php?step=2");
        exit;
    }
    $step = 1;
}

// ====== GUARD STEP 2 (harus ada identitas dulu) ======
if ($step === 2 && !isset($_SESSION['identitas'])) {
    header("Location: kuesioner.php?step=1");
    exit;
}

$old = $_SESSION['identitas'] ?? [];

function radio_scale($name){
    $html = '<div class="radio-group">';
    for($i=0;$i<=5;$i++){
        $id  = $name.'_'.$i;
        $req = ($i===0) ? ' required' : ''; // cukup satu required per group
        $html .= '<label for="'.e($id).'">';
        $html .= '<input id="'.e($id).'" type="radio" name="'.e($name).'" value="'.$i.'"'.$req.'>';
        $html .= '<span>'.$i.'</span></label>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kuesioner POSPAY</title>

<!-- POPPINS -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: #f5f7fb;
        margin: 0;
        padding: 16px;
    }

    .container { max-width: 800px; margin: auto; }

    .card {
        background: #ffffff;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.05);
    }

    h2 { text-align: center; color: #333; margin: 0; }

    h3 { margin-bottom: 12px; color: #444; margin-top: 0; }

    p {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
        margin-top: 0;
    }

    .btn {
        display: inline-block;
        padding: 12px 18px;
        background: #4f46e5;
        color: #fff;
        text-decoration: none;
        border-radius: 10px;
        font-size: 14px;
        margin-top: 10px;
        font-weight: 600;
    }

    .btn-center { text-align: center; }

    .scale-list li { font-size: 14px; margin-bottom: 6px; color: #555; }

    .form-group { margin-bottom: 16px; }

    label {
        font-size: 14px;
        display: block;
        margin-bottom: 6px;
        margin-top: 12px;
        color: #333;
        font-weight: 500;
    }

    input, select {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #ddd;
        font-size: 14px;
        box-sizing: border-box;
        background: #fff;
        font-family: 'Poppins', sans-serif;
    }

    .question { margin-bottom: 16px; }

    .radio-group {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        gap: 6px;
    }

    .radio-group label {
        flex: 1;
        text-align: center;
        background: #f0f2f7;
        padding: 8px 0;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        font-weight: 600;
        user-select: none;
    }

    .radio-group input { display: none; }

    .radio-group input:checked + span {
        background: #4f46e5;
        color: #fff;
        padding: 8px;
        border-radius: 10px;
        display: block;
    }

    .btn-primary {
        display: inline-block;
        width: 100%;
        padding: 14px 0;
        font-size: 16px;
        font-weight: 700;
        text-align: center;
        color: #fff;
        background: linear-gradient(90deg, #4f46e5, #6366f1);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        text-decoration: none;
        font-family: 'Poppins', sans-serif;
    }

    .btn-primary:hover {
        background: linear-gradient(90deg, #6366f1, #4f46e5);
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        transform: translateY(-2px);
    }

    .btn-secondary{
        display:inline-block;
        width:100%;
        padding:12px 0;
        font-size:14px;
        font-weight:700;
        text-align:center;
        background:#e5e7eb;
        color:#111827;
        border:none;
        border-radius:12px;
        cursor:pointer;
        margin-bottom:10px;
        font-family: 'Poppins', sans-serif;
    }

    .alert{
        background:#fee2e2;
        color:#7f1d1d;
        border:1px solid #fecaca;
        padding:12px 14px;
        border-radius:12px;
        margin-bottom:14px;
        font-size:14px;
        font-weight: 500;
    }

    .stepbar{
        display:flex;
        gap:8px;
        margin-top:10px;
        justify-content:center;
        flex-wrap: wrap;
    }
    .pill{
        padding:6px 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:700;
        background:#e5e7eb;
        color:#374151;
    }
    .pill.active{
        background:#4f46e5;
        color:#fff;
    }
</style>
</head>

<body>
<div class="container">

<!-- JUDUL -->
<div class="card">
    <h2>Kuesioner Evaluasi Aplikasi POSPAY</h2>
    <div class="stepbar">
        <span class="pill <?= $step===1?'active':''; ?>">1. Identitas</span>
        <span class="pill <?= $step===2?'active':''; ?>">2. Pertanyaan</span>
    </div>
</div>

<?php if ($errors): ?>
<div class="card">
    <div class="alert">
        <b>Mohon periksa:</b>
        <ul style="margin:8px 0 0 18px;">
            <?php foreach($errors as $er): ?>
                <li><?= e($er) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if ($step === 1): ?>

    <!-- INFO & PANDUAN -->
    <div class="card">
        <h3>📌 Informasi & Panduan</h3>
        <p>
            Silahkan melihat informasi dan panduan sebelum mengisi kuesioner
            agar dapat memberikan jawaban yang sesuai dengan pengalaman Anda.
        </p>
        <div class="btn-center">
            <a href="bantuan.php" class="btn">Lihat Panduan Pengisian</a>
        </div>
    </div>

    <!-- IDENTITAS RESPONDEN (STEP 1) -->
    <div class="card">
        <h3>👤 Identitas Responden</h3>

        <form action="kuesioner.php?step=1" method="POST">
            <input type="hidden" name="action" value="save_identitas">

            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="nama" required value="<?= e($old['nama'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin" required>
                    <option value="">Pilih</option>
                    <option value="Laki-laki" <?= (($old['jenis_kelamin'] ?? '')==='Laki-laki')?'selected':''; ?>>Laki-laki</option>
                    <option value="Perempuan" <?= (($old['jenis_kelamin'] ?? '')==='Perempuan')?'selected':''; ?>>Perempuan</option>
                </select>
            </div>

            <div class="form-group">
                <label>Jenis Pengguna</label>
                <select name="jenis_pengguna" required>
                    <option value="">Pilih</option>
                    <option value="Karyawan PT. Pos Indonesia (Persero)" <?= (($old['jenis_pengguna'] ?? '')==='Karyawan PT. Pos Indonesia (Persero)')?'selected':''; ?>>Karyawan PT. Pos Indonesia (Persero)</option>
                    <option value="Pengguna Umum" <?= (($old['jenis_pengguna'] ?? '')==='Pengguna Umum')?'selected':''; ?>>Pengguna Umum</option>
                </select>
            </div>

            <div class="form-group">
                <label>Pekerjaan</label>
                <input type="text" name="pekerjaan" value="<?= e($old['pekerjaan'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Lama Menggunakan POSPAY</label>
                <select name="lamaPakai" required>
                    <option value="">Pilih</option>
                    <option value="<6 bulan" <?= (($old['lamaPakai'] ?? '')==='<6 bulan')?'selected':''; ?>>&lt;6 bulan</option>
                    <option value="6 bulan - 12 bulan" <?= (($old['lamaPakai'] ?? '')==='6 bulan - 12 bulan')?'selected':''; ?>>6 bulan - 12 bulan</option>
                    <option value="1 tahun - 2 tahun" <?= (($old['lamaPakai'] ?? '')==='1 tahun - 2 tahun')?'selected':''; ?>>1 tahun - 2 tahun</option>
                    <option value="2 tahun - 3 tahun" <?= (($old['lamaPakai'] ?? '')==='2 tahun - 3 tahun')?'selected':''; ?>>2 tahun - 3 tahun</option>
                    <option value=">3 tahun" <?= (($old['lamaPakai'] ?? '')==='>3 tahun')?'selected':''; ?>>&gt;3 tahun</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tanggal</label>
                <input type="date" name="tanggal" value="<?= e($old['tanggal'] ?? date('Y-m-d')) ?>">
            </div>

            <button type="submit" class="btn-primary">Lanjut ke Pertanyaan</button>
        </form>
    </div>

<?php else: ?>

    <!-- SKALA PENILAIAN (STEP 2) -->
    <div class="card">
        <h3>📊 Skala Penilaian</h3>
        <p>Semakin tinggi nilai yang dipilih, semakin jarang kendala yang dirasakan.</p>
        <ul class="scale-list">
            <li><b>0</b> – Sangat Tidak Setuju (STS)</li>
            <li><b>1</b> – Tidak Setuju (TS)</li>
            <li><b>2</b> – Kurang Setuju (KS)</li>
            <li><b>3</b> – Netral (N)</li>
            <li><b>4</b> – Setuju (S)</li>
            <li><b>5</b> – Sangat Setuju (SS)</li>
        </ul>
    </div>

    <!-- PERTANYAAN (STEP 2) -->
    <form action="proses_kuesioner.php" method="POST">
        <div class="card">
            <h3>📝 Pertanyaan Kuesioner</h3>

            <div class="question"><p><b>P1.</b> Aplikasi POSPAY mudah digunakan tanpa sering mengalami error saat login.</p><?= radio_scale('p1'); ?></div>
            <div class="question"><p><b>P2.</b> Jarang mengalami kendala sistem yang menghambat aktivitas transaksi di POSPAY.</p><?= radio_scale('p2'); ?></div>
            <div class="question"><p><b>P3.</b> Secara umum, pengelolaan sistem aplikasi POSPAY sudah berjalan dengan baik.</p><?= radio_scale('p3'); ?></div>
            <div class="question"><p><b>P4.</b> Fitur yang tersedia di aplikasi POSPAY sesuai dengan kebutuhan saya sebagai pengguna.</p><?= radio_scale('p4'); ?></div>
            <div class="question"><p><b>P5.</b> Pembaruan (update) aplikasi POSPAY meningkatkan kualitas layanan, bukan menambah masalah.</p><?= radio_scale('p5'); ?></div>
            <div class="question"><p><b>P6.</b> POSPAY memiliki arah pengembangan aplikasi yang jelas dan bermanfaat bagi pengguna.</p><?= radio_scale('p6'); ?></div>
            <div class="question"><p><b>P7.</b> Aplikasi POSPAY dapat digunakan dengan baik menggunakan berbagai jenis jaringan internet (WiFi maupun data seluler).</p><?= radio_scale('p7'); ?></div>
            <div class="question"><p><b>P8.</b> Saya jarang mengalami kendala koneksi jaringan saat menggunakan POSPAY.</p><?= radio_scale('p8'); ?></div>
            <div class="question"><p><b>P9.</b> Infrastruktur sistem POSPAY mendukung penggunaan aplikasi secara stabil.</p><?= radio_scale('p9'); ?></div>
            <div class="question"><p><b>P10.</b> Petugas layanan pelanggan (Customer Service) POSPAY mudah dihubungi saat terjadi masalah.</p><?= radio_scale('p10'); ?></div>
            <div class="question"><p><b>P11.</b> Customer Service POSPAY memberikan solusi yang jelas dan membantu.</p><?= radio_scale('p11'); ?></div>
            <div class="question"><p><b>P12.</b> Penanganan keluhan pengguna oleh Customer Service POSPAY dilakukan secara profesional.</p><?= radio_scale('p12'); ?></div>
            <div class="question"><p><b>P13.</b> Saya merasa aman melakukan transaksi keuangan menggunakan aplikasi POSPAY.</p><?= radio_scale('p13'); ?></div>
            <div class="question"><p><b>P14.</b> Risiko kehilangan saldo atau keterlambatan dana jarang terjadi di POSPAY.</p><?= radio_scale('p14'); ?></div>
            <div class="question"><p><b>P15.</b> POSPAY mampu menangani risiko transaksi dengan baik saat terjadi masalah.</p><?= radio_scale('p15'); ?></div>
            <div class="question"><p><b>P16.</b> Riwayat transaksi pada aplikasi POSPAY ditampilkan dengan lengkap dan jelas.</p><?= radio_scale('p16'); ?></div>
            <div class="question"><p><b>P17.</b> Dapat dengan mudah memeriksa status transaksi yang telah dilakukan.</p><?= radio_scale('p17'); ?></div>
            <div class="question"><p><b>P18.</b> Operasional aplikasi POSPAY berjalan dengan lancar tanpa gangguan berarti.</p><?= radio_scale('p18'); ?></div>
            <div class="question"><p><b>P19.</b> Laporan keluhan yang saya sampaikan ke POSPAY mendapatkan respons.</p><?= radio_scale('p19'); ?></div>
            <div class="question"><p><b>P20.</b> Permasalahan yang saya laporkan ditindaklanjuti dalam waktu yang wajar.</p><?= radio_scale('p20'); ?></div>
            <div class="question"><p><b>P21.</b> Proses pengaduan masalah di aplikasi POSPAY mudah dilakukan.</p><?= radio_scale('p21'); ?></div>
            <div class="question"><p><b>P22.</b> Masalah yang sering muncul di aplikasi POSPAY jarang terulang kembali.</p><?= radio_scale('p22'); ?></div>
            <div class="question"><p><b>P23.</b> Error seperti gagal inisialisasi atau gagal login jarang saya alami.</p><?= radio_scale('p23'); ?></div>
            <div class="question"><p><b>P24.</b> POSPAY mampu menyelesaikan masalah teknis secara permanen.</p><?= radio_scale('p24'); ?></div>
            <div class="question"><p><b>P25.</b> Sistem keamanan login POSPAY tidak menyulitkan pengguna.</p><?= radio_scale('p25'); ?></div>
            <div class="question"><p><b>P26.</b> Saya merasa sistem login POSPAY cukup aman tanpa menghambat akses.</p><?= radio_scale('p26'); ?></div>
            <div class="question"><p><b>P27.</b> Fitur verifikasi akun POSPAY membantu menjaga keamanan data pengguna.</p><?= radio_scale('p27'); ?></div>
            <div class="question"><p><b>P28.</b> Proses transaksi seperti transfer bank atau pembayaran berjalan sesuai informasi yang ditampilkan.</p><?= radio_scale('p28'); ?></div>
            <div class="question"><p><b>P29.</b> Jika transaksi dinyatakan berhasil, dana benar-benar masuk ke tujuan.</p><?= radio_scale('p29'); ?></div>
            <div class="question"><p><b>P30.</b> Kesalahan transaksi di POSPAY jarang terjadi.</p><?= radio_scale('p30'); ?></div>
            <div class="question"><p><b>P31.</b> Aplikasi POSPAY memberikan notifikasi atas transaksi yang saya lakukan.</p><?= radio_scale('p31'); ?></div>
            <div class="question"><p><b>P32.</b> Informasi status transaksi di POSPAY mudah dipantau.</p><?= radio_scale('p32'); ?></div>
            <div class="question"><p><b>P33.</b> Kinerja aplikasi POSPAY dapat dipantau dengan jelas oleh pengguna.</p><?= radio_scale('p33'); ?></div>
            <div class="question"><p><b>P34.</b> Sistem POSPAY mampu mengontrol transaksi agar tidak terjadi kesalahan.</p><?= radio_scale('p34'); ?></div>
            <div class="question"><p><b>P35.</b> Permasalahan dana yang belum masuk jarangARANG terjadi di POSPAY.</p><?= radio_scale('p35'); ?></div>
            <div class="question"><p><b>P36.</b> POSPAY memiliki mekanisme kontrol yang baik terhadap transaksi pengguna.</p><?= radio_scale('p36'); ?></div>
            <div class="question"><p><b>P37.</b> Proses layanan seperti BSU di POSPAY berjalan sesuai prosedur yang ditetapkan.</p><?= radio_scale('p37'); ?></div>
            <div class="question"><p><b>P38.</b> Informasi terkait BSU di aplikasi POSPAY mudah diakses.</p><?= radio_scale('p38'); ?></div>
            <div class="question"><p><b>P39.</b> Fitur BSU di POSPAY dapat digunakan tanpa kendala teknis.</p><?= radio_scale('p39'); ?></div>

            <button type="button" class="btn-secondary" onclick="window.location.href='kuesioner.php?step=1'">
                Kembali ke Identitas
            </button>

            <button type="submit" class="btn-primary">Kirim Jawaban</button>
        </div>
    </form>

<?php endif; ?>

</div>
</body>
</html>
