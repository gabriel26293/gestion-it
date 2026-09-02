<?php
session_start();
require_once 'db.php';

$usuario_id = $_SESSION['usuario_id']; // O la variable de sesión con la que identificas al usuario

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['foto']['tmp_name'];
    $fileName    = $_FILES['foto']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Extensiones permitidas
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($fileExtension, $allowedExtensions)) {
        // Renombrar archivo para evitar duplicados
        $newFileName = 'user_' . $usuario_id . '_' . time() . '.' . $fileExtension;
        $uploadFileDir = 'img/';
        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            // Actualizar la ruta en la base de datos
            $stmt = $conexion->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
            $stmt->bind_param("si", $newFileName, $usuario_id);
            $stmt->execute();

            $_SESSION['foto_perfil'] = $newFileName; // Actualizar variable de sesión si la usas
            header("Location: dashboard.php?msg=foto_actualizada");
            exit;
        }
    }
}

header("Location: dashboard.php?error=carga_fallida");
exit;