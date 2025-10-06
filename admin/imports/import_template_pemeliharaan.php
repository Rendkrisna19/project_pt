<?php
// admin/imports/import_template_pemeliharaan.php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
  header("Location: ../auth/login.php"); exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pemeliharaan_template.csv');

$fh = fopen('php://output', 'w');
$header = ['tanggal','bulan','tahun','jenis','tenaga','unit','kebun','rencana','realisasi','status'];
fputcsv($fh, $header);

// Contoh baris
$rows = [
  ['2025-01-10','Januari','2025','Piringan','Harian Lepas','Afdeling I','Kebun Sei Rokan','10','7','Berjalan'],
  ['2025-01-11','Januari','2025','Semprot','Kontrak','Afdeling II','Kebun Sei Rokan','8','8','Selesai'],
];
foreach ($rows as $r) fputcsv($fh, $r);
fclose($fh);
exit;
