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
    echo "No tenés permisos para crear artículos.";
    exit();
}

$titulo = trim($_POST['titulo'] ?? '');
$contenido = trim($_POST['contenido'] ?? '');
$categoriaId = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
$visibilidad = ($_POST['visibilidad'] ?? 'publico') === 'interno' ? 'interno' : 'publico';

if ($titulo === '' || $contenido === '') {
    echo "El título y el contenido son obligatorios.";
    exit();
}

// Identificar autor
$usuarioActual = $_SESSION['usuario'] ?? '';
$consultaUsuario = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
$consultaUsuario->bind_param("ss", $usuarioActual, $usuarioActual);
$consultaUsuario->execute();
$filaUsuario = $consultaUsuario->get_result()->fetch_assoc();
$autorId = $filaUsuario['id'] ?? null;

$consulta = $conexion->prepare("INSERT INTO bc_articulos (categoria_id, titulo, contenido, visibilidad, autor_id) VALUES (?, ?, ?, ?, ?)");
$consulta->bind_param("isssi", $categoriaId, $titulo, $contenido, $visibilidad, $autorId);

if ($consulta->execute()) {
    echo "success";
} else {
    echo "Error al guardar el artículo: " . $conexion->error;
}
