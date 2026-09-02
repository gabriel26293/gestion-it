<?php
session_start();
require_once 'db.php'; 

// 1. Verificación de seguridad
if (!isset($_SESSION['rol'])) {
    header("Location: index.php");
    exit();
}

$rol = $_SESSION['rol'];
$mostrar_alerta_permiso = false;

// 2. Control de acceso según tu base de datos
if ($rol === 'operativo') {
    $mostrar_alerta_permiso = true;
} 
elseif ($rol !== 'administrador' && $rol !== 'tecnico') {
    header("Location: dashboard.php"); 
    exit();
}

// 3. Consulta de datos
$query = "SELECT i.*, c.nombre AS categoria_nombre 
          FROM inventario i 
          INNER JOIN categorias c ON i.tipo_id = c.id 
          ORDER BY i.id DESC";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>NeoAdmin | Lista IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #0f172a; color: white; font-family: sans-serif; padding: 40px; }
        .card-custom { background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 15px; padding: 20px; }
        .accent { color: #38bdf8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between mb-4">
            <h1><span class="accent">INVENTARIO</span> CLON</h1>
            <a href="dashboard.php" class="btn btn-outline-light">Volver</a>
        </div>

        <?php if (!$mostrar_alerta_permiso): ?>
            <div class="row g-3">
                <?php while($row = $resultado->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="card-custom">
                        <h5 class="accent"><?php echo $row['marca']; ?></h5>
                        <p class="small mb-0"><?php echo $row['modelo']; ?></p>
                        <hr>
                        <span class="badge bg-info text-dark"><?php echo $row['estado']; ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($mostrar_alerta_permiso): ?>
        Swal.fire({
            title: 'ACCESO RESTRINGIDO',
            text: 'Tu rol operativo no permite ver esta lista.',
            icon: 'error',
            confirmButtonText: 'Regresar'
        }).then(() => { window.location.href = 'dashboard.php'; });
        <?php endif; ?>
    </script>
</body>
</html>