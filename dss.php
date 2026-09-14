<?php session_start(); include "config/koneksi.php"; ?>
<!DOCTYPE html>
<html>
<head>
<title>DSS</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container">
<h2>Domain DSS</h2>

<form action="proses_simpan.php" method="post">
<input type="hidden" name="next" value="mea.php">

<?php
$q=mysqli_query($koneksi,"SELECT * FROM pertanyaan WHERE domain='DSS'");
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

<h3>DSS</h3>
<?php
$dss = [
"DSS01-P16","DSS01-P17","DSS01-P18",
"DSS02-P19","DSS02-P20","DSS02-P21",
"DSS03-P22","DSS03-P23","DSS03-P24",
"DSS05-P25","DSS05-P26","DSS05-P27",
"DSS06-P28","DSS06-P29","DSS06-P30"
];

foreach ($dss as $p) {
    echo "<label>$p</label><br>";
    for ($i=0;$i<=5;$i++) {
        echo "<input type='radio' name='jawaban[$p]' value='$i' required> $i ";
    }
    echo "<br><br>";
}
?>

<button type="submit">Lanjut MEA</button>
</form>
</div>

</body>
</html>
