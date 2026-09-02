<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'administrador' && $_SESSION['rol'] !== 'tecnico')) {
    exit("Error: No autorizado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_id = intval($_POST['tipo_id']);
    $marca = mysqli_real_escape_string($conexion, trim($_POST['marca']));
    $modelo = mysqli_real_escape_string($conexion, trim($_POST['modelo']));
    $codigo = mysqli_real_escape_string($conexion, trim($_POST['codigo_patrimonial']));
    $estado = mysqli_real_escape_string($conexion, trim($_POST['estado'] ?? 'Disponible'));
    $sector = mysqli_real_escape_string($conexion, trim($_POST['sector'] ?? ''));

    $usuario_id = !empty($_POST['usuario_id']) ? intval($_POST['usuario_id']) : null;
    $usuario_val = is_null($usuario_id) ? "NULL" : $usuario_id;

    // 1. Validar si ya existe el código patrimonial
    $check = $conexion->query("SELECT id FROM inventario WHERE codigo_patrimonial = '$codigo'");
    
    if ($check->num_rows > 0) {
        echo "Error: El código patrimonial ya está registrado.";
        exit();
    }

    // 2. Insertar con sector y usuario asignado
    $sql = "INSERT INTO inventario (tipo_id, marca, modelo, codigo_patrimonial, estado, sector, usuario_asignado_id) 
            VALUES ($tipo_id, '$marca', '$modelo', '$codigo', '$estado', '$sector', $usuario_val)";

    if ($conexion->query($sql)) {
        echo "success";
    } else {
        echo "Error al guardar: " . $conexion->error;
    }
}
?>