<?php
error_reporting(E_ERROR | E_PARSE);
if (empty($_POST['data'])) {
	die('');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . ($_GET['filename'] ?: 'csv-' . date('-Y-m-d') . '.csv') . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($_POST['data']));
print($_POST['data']);
