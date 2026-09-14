<?php
include "config/koneksi.php";

$responden_id = $_POST['responden_id'];
foreach ($_POST['jawaban'] as $kode => $nilai) {
    mysqli_query($koneksi, "INSERT INTO jawaban VALUES (
        '', '$responden_id', '$kode', '$nilai'
    )");
}
header("Location: selesai.php");
