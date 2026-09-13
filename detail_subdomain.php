<?php
session_start();
if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit;
}
?>
<?php
require_once __DIR__ . '/../config/koneksi.php';
$domain=$_GET['domain'];
?>

<h2>Detail Domain <?= $domain ?></h2>

<table border="1">
<tr>
<th>Subdomain</th>
<th>Rata-rata</th>
</tr>

<?php
$q=mysqli_query($koneksi,"
SELECT subdomain, AVG(nilai) rata
FROM jawaban j
JOIN pertanyaan p ON j.pertanyaan_id=p.id
WHERE domain='$domain'
GROUP BY subdomain");

while($d=mysqli_fetch_assoc($q)){
echo "<tr>
<td>$d[subdomain]</td>
<td>".number_format($d['rata'],2)."</td>
</tr>";
}
?>
</table>

<a href="index.php">⬅ Kembali</a>
