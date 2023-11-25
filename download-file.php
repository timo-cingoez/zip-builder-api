<?php
$filePath = str_replace('/', DIRECTORY_SEPARATOR, $_GET['filepath']);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: *");
header("Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With");
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($filePath).'"');

readfile($filePath);