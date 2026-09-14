<!DOCTYPE html>
<html>
<head>
    <title>Data Responden</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
<form action="kuesioner.php" method="post">
    <label>Nama</label><br>
    <input type="text" name="nama" required><br><br>

    <label>Usia</label><br>
    <input type="text" name="usia"><br><br>

    <br>Jenis Kelamin <br>
<select name="jk">
  <option>Laki-laki</option>
  <option>Perempuan</option>
</select><br><br>
    <label>Pekerjaan</label><br>
    <input type="text" name="pekerjaan"><br><br>

    <button type="submit">Lanjut</button>
</form>
</div>
</body>
</html>
