<?php
session_start();
if (!$_SESSION['auth']) {
    header('Location: subir.php');
}

$fileName = $_SESSION['nombreFichero'];
$filePath = 'files/output.txt';
if(file_exists($filePath)){
    // Define headers
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=".$fileName);
    header("Content-Type: application/zip");
    header("Content-Transfer-Encoding: binary");
    
    // Read the file
    readfile($filePath);
    exit;
}
