<?php
session_start();
if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit;
}
?>

<?php require_once __DIR__ . '/../config/koneksi.php'; ?>
<h2>Rekap Nilai Domain</h2>
<td>
<a href="detail_subdomain.php?domain=<?= $d['domain'] ?>">Detail</a>
</td>

<?php
$q=mysqli_query($koneksi,"
SELECT domain, AVG(nilai) rata
FROM jawaban j
JOIN pertanyaan p ON j.pertanyaan_id=p.id
GROUP BY domain");
while($d=mysqli_fetch_assoc($q)){
echo "<tr><td>$d[domain]</td><td>".number_format($d['rata'],2)."</td></tr>";
}
?>
</table>

<a href="grafik.php">Grafik</a> |
<a href="export_csv.php">Export CSV</a>
