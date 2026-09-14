<?php
require_once __DIR__ . '/../config/koneksi.php';
$q = mysqli_query($koneksi,"
SELECT kode_pertanyaan, AVG(nilai) rata
FROM jawaban GROUP BY kode_pertanyaan
");
while($d=mysqli_fetch_assoc($q)){
    echo $d['kode_pertanyaan']." : ".$d['rata']."<br>";
}

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<h2>Grafik Rata-rata Domain COBIT 5</h2>
<canvas id="chart"></canvas>

<script>
new Chart(document.getElementById('chart'),{
  type:'bar',
  data:{
    labels:<?= json_encode(array_keys($data)) ?>,
    datasets:[{
      label:'Nilai Rata-rata',
      data:<?= json_encode(array_values($data)) ?>,
      backgroundColor:['#c62828','#ef6c00','#2e7d32']
    }]
  },
  options:{
    scales:{ y:{ beginAtZero:true, max:6 } }
  }
});
</script>
