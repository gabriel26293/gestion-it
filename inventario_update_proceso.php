<?php
session_start();
require_once 'db.php';

/**
 * Validar permisos de acceso
 */
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'administrador' && $_SESSION['rol'] !== 'tecnico')) {
    echo "Error: Sesión no autorizada";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitización y limpieza de datos
    $id = intval($_POST['id']);
    $sector = mysqli_real_escape_string($conexion, trim($_POST['sector']));
    $estado = mysqli_real_escape_string($conexion, trim($_POST['estado']));
    
    // Manejo del usuario asignado para evitar errores de clave foránea
    $usuario_id = !empty($_POST['usuario_id']) ? intval($_POST['usuario_id']) : null;
    $usuario_val = is_null($usuario_id) ? "NULL" : $usuario_id;

    // Consulta SQL para actualizar el registro
    $sql = "UPDATE inventario SET 
            sector = '$sector', 
            usuario_asignado_id = $usuario_val, 
            estado = '$estado' 
            WHERE id = $id";

    if ($conexion->query($sql)) {
        echo "success"; // Mensaje clave para la respuesta de JavaScript
    } else {
        // En caso de error, muestra detalles para depuración
        echo "Error SQL: " . $conexion->error . " | Query: " . $sql;
    }
}
?>