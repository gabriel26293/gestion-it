<?php
session_start();
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    echo "No autorizado.";
    exit();
}

$idArticulo = (int)($_POST['id'] ?? 0);
$columnaVoto = ($_POST['valor'] ?? '') === 'si' ? 'util_si' : 'util_no';

if ($idArticulo <= 0) {
    echo "Artículo inválido.";
    exit();
}

$consulta = $conexion->prepare("UPDATE bc_articulos SET {$columnaVoto} = {$columnaVoto} + 1 WHERE id = ?");
$consulta->bind_param("i", $idArticulo);

if ($consulta->execute()) {
    echo "success";
} else {
    echo "Error al registrar el voto: " . $conexion->error;
}
