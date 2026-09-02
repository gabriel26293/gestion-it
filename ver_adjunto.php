<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario']) || empty($_GET['id'])) {
    die("Acceso denegado.");
}

$id = intval($_GET['id']);
// Buscamos el BLOB y sus metadatos correspondientes
$stmt = $conexion->prepare("SELECT archivo_adjunto, archivo_nombre, archivo_tipo FROM tickets WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($blob, $nombre, $tipo);
    $stmt->fetch();
    
    // Forzamos al navegador a interpretar los bytes con su tipo real (image/png, application/pdf, etc.)
    header("Content-Type: " . $tipo);
    header("Content-Disposition: inline; filename=\"" . $nombre . "\"");
    echo $blob;
} else {
    echo "Archivo no encontrado.";
}
$stmt->close();
?>