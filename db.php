<?php
// Configuración de la base de datos
$host     = 'localhost';
$db_name  = 'gestion_it';
$username = 'root'; // Usuario por defecto en XAMPP/WAMP
$password = '';     // Contraseña por defecto en XAMPP es vacía

// Intentar establecer la conexión
try {
    $conexion = new mysqli($host, $username, $password, $db_name);

    // Ajustar el conjunto de caracteres a UTF-8 para evitar problemas con acentos
    $conexion->set_charset("utf8mb4");

    // Verificar si hay errores
    if ($conexion->connect_error) {
        throw new Exception("Error de conexión: " . $conexion->connect_error);
    }

} catch (Exception $e) {
    // En producción, es mejor guardar esto en un log y mostrar un mensaje genérico
    die("Lo sentimos, ocurrió un error en el servidor. Intente más tarde.");
}

// La variable $conexion estará disponible en los archivos que incluyan a db.php
?>