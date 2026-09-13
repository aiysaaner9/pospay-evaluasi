<?php session_start(); include "config/koneksi.php"; ?>
<!DOCTYPE html>
<html>
<head>
<title>MEA</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container">
<h2>Domain MEA</h2>

<form action="proses_simpan.php" method="post">
<input type="hidden" name="next" value="selesai.php">

<?php
$q=mysqli_query($koneksi,"SELECT * FROM pertanyaan WHERE domain='MEA'");
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

<h3>MEA</h3>
<?php
$mea = [
"MEA01-P31","MEA01-P32","MEA01-P33",
"MEA02-P34","MEA02-P35","MEA02-P36",
"MEA03-P37","MEA03-P38","MEA03-P39"
];

foreach ($mea as $p) {
    echo "<label>$p</label><br>";
    for ($i=0;$i<=5;$i++) {
        echo "<input type='radio' name='jawaban[$p]' value='$i' required> $i ";
    }
    echo "<br><br>";
}
?>

<button type="submit">Selesai</button>
</form>
</div>

</body>
</html>
