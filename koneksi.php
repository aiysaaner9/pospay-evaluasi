<?php

$DB_HOST = "localhost";
$DB_USER = "anerstco_aiysa";
$DB_PASS = "V2gk5CE1wsbbc)&U";
$DB_NAME = "anerstco_db_pospay_evaluasi";

$koneksi = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($koneksi->connect_errno) {
    die(
        "<div style='padding:12px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:8px;'>
            <b>Gagal konek database</b><br>
            Error: " . htmlspecialchars($koneksi->connect_error) . "
        </div>"
    );
}

$koneksi->set_charset("utf8mb4");
