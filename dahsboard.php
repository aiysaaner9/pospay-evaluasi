<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
}
?>
<h2>Dashboard Admin POSPAY</h2>
<a href="grafik.php">Grafik</a> |
<a href="export_csv.php">Export CSV</a> |
<a href="logout.php">Logout</a>
