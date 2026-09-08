<?php
session_start();
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(403);
    echo "No autorizado.";
    exit();
}

$rol = $_SESSION['rol'] ?? 'operativo';
if ($rol !== 'administrador' && $rol !== 'tecnico') {
    http_response_code(403);
    echo "No tenés permisos para editar artículos.";
    exit();
}

$idArticulo = (int)($_POST['id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$contenido = trim($_POST['contenido'] ?? '');
$categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
$visibilidad = ($_POST['visibilidad'] ?? 'publico') === 'interno' ? 'interno' : 'publico';

if ($idArticulo <= 0 || $titulo === '' || $contenido === '') {
    echo "Faltan datos obligatorios.";
    exit();
}

$consulta = $conexion->prepare("UPDATE bc_articulos SET categoria_id = ?, titulo = ?, contenido = ?, visibilidad = ? WHERE id = ?");
$consulta->bind_param("isssi", $categoriaId, $titulo, $contenido, $visibilidad, $idArticulo);

if ($consulta->execute()) {
    echo "success";
} else {
    echo "Error al actualizar el artículo: " . $conexion->error;
}
