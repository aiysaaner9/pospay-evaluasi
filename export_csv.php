<?php
session_start();
if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit;
}
?>

<?php
require_once __DIR__ . '/../config/koneksi.php';
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=data_pospay.csv");

$out = fopen("php://output", "w");
fputcsv($out, ["Responden","Kode","Nilai"]);

$q = mysqli_query($koneksi, "SELECT * FROM jawaban");
while ($d = mysqli_fetch_assoc($q)) {
    fputcsv($out, $d);
}
fclose($out);

while($d=mysqli_fetch_assoc($q)){
  fputcsv($out,$d);
}
fclose($out);
?>
