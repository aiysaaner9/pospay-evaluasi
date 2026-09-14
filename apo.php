<?php
session_start();
include "config/koneksi.php";

if(!isset($_SESSION['responden'])){
  mysqli_query($koneksi,"INSERT INTO responden VALUES
  ('','$_POST[nama]','$_POST[umur]','$_POST[jk]','$_POST[pekerjaan]',NOW())");
  $_SESSION['responden']=mysqli_insert_id($koneksi);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>APO</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container">
<h2>Domain APO</h2>

<form action="proses_simpan.php" method="post">
<input type="hidden" name="next" value="dss.php">

<?php
$q=mysqli_query($koneksi,"SELECT * FROM pertanyaan WHERE domain='APO'");
while($p=mysqli_fetch_assoc($q)){
  echo "<p>$p[teks]</p>";
  echo "<select name='jawaban[$p[id]]'>
    <option value='0'>Sangat Tidak Setuju</option>
    <option value='1'>Tidak Setuju</option>
    <option value='2'>Tidak Tahu</option>
    <option value='3'>Kurang Setuju</option>
    <option value='4'>Setuju</option>
    <option value='5'>Sangat Setuju</option>
  </select>";
}
?>

<h3>APO</h3>
<?php
$apo = [
"APO01-P1","APO01-P2","APO01-P3",
"APO02-P4","APO02-P5","APO02-P6",
"APO03-P7","APO03-P8","APO03-P9",
"APO07-P10","APO07-P11","APO07-P12",
"APO12-P13","APO12-P14","APO12-P15"
];

foreach ($apo as $p) {
    echo "<label>$p</label><br>";
    for ($i=0;$i<=5;$i++) {
        echo "<input type='radio' name='jawaban[$p]' value='$i' required> $i ";
    }
    echo "<br><br>";
}
?>

<button type="submit">Lanjut DSS</button>
</form>
</div>

</body>
</html>
