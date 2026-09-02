<?php
session_start();
require_once 'db.php';

// Redirigir si no hay sesión iniciada
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// --- CAPTURA DE FILTROS DESDE EL FORMULARIO ---
$filtro_estado = $_GET['estado'] ?? '';
$filtro_depto = $_GET['departamento'] ?? ''; // Recibe el parámetro del selector de áreas
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_tecnico = $_GET['tecnico_id'] ?? '';
$buscar = $_GET['buscar'] ?? '';
$formato = $_GET['formato'] ?? 'excel';

// --- CONSULTA BASE CON FILTROS DINÁMICOS (SINCRO CON TICKETS_LISTA.PHP) ---
if ($rol == 'administrador' || $rol == 'tecnico') {
    $sql = "SELECT t.id, t.asunto, t.descripcion, t.prioridad, t.estado, t.tipo, t.fecha_creacion, t.fecha_limite,
                  u_sol.nombre_completo AS solicitante_nombre, u_sol.area AS solicitante_depto, 
                  u_tec.nombre_completo AS tecnico_nombre, t.tecnico_id
           FROM tickets t
           JOIN usuarios u_sol ON t.solicitante_id = u_sol.id
           LEFT JOIN usuarios u_tec ON t.tecnico_id = u_tec.id 
           WHERE 1=1";
} else {
    $sql = "SELECT t.id, t.asunto, t.descripcion, t.prioridad, t.estado, t.tipo, t.fecha_creacion, t.fecha_limite,
                  u_sol.nombre_completo AS solicitante_nombre, u_sol.area AS solicitante_depto,
                  'N/A' as tecnico_nombre, t.tecnico_id
           FROM tickets t
           JOIN usuarios u_sol ON t.solicitante_id = u_sol.id
           WHERE t.solicitante_id = $usuario_id";
}

// --- APLICACIÓN DE CONDICIONES DINÁMICAS Y SEGURAS ---
if (!empty($filtro_estado)) {
    $sql .= " AND t.estado = '" . $conexion->real_escape_string($filtro_estado) . "'";
}
if (!empty($filtro_depto)) {
    $sql .= " AND u_sol.area = '" . $conexion->real_escape_string($filtro_depto) . "'";
}
if (!empty($filtro_tipo)) {
    $sql .= " AND t.tipo = '" . $conexion->real_escape_string($filtro_tipo) . "'";
}
if (!empty($filtro_prioridad)) {
    $sql .= " AND t.prioridad = '" . $conexion->real_escape_string($filtro_prioridad) . "'";
}
if (!empty($filtro_tecnico)) {
    $sql .= " AND t.tecnico_id = '" . $conexion->real_escape_string($filtro_tecnico) . "'";
}
if (!empty($buscar)) {
    $b = $conexion->real_escape_string($buscar);
    $sql .= " AND (t.id LIKE '%$b%' OR t.asunto LIKE '%$b%' OR t.descripcion LIKE '%$b%' OR u_sol.nombre_completo LIKE '%$b%')";
}

$sql .= " ORDER BY t.fecha_creacion DESC";
$res = $conexion->query($sql);

// Nombre único del archivo de descarga
$filename = "reporte_tickets_" . (!empty($filtro_estado) ? strtolower($filtro_estado) : "todos") . "_" . date('Ymd_His');

// ==========================================
// MODO EXCEL
// ==========================================
if ($formato === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename.xls");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    
    // Cambiar salida a UTF-8 BOM para compatibilidad total de tildes/caracteres en Excel
    echo "\xEF\xBB\xBF"; 
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #161c2d; color: #ffffff; font-weight: bold;">
                <th>ID</th>
                <th>Asunto</th>
                <th>Descripción</th>
                <th>Tipo</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Solicitante</th>
                <th>Área / Depto</th>
                <th>Técnico Asignado</th>
                <th>Fecha Creación</th>
                <th>Fecha Límite</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['asunto']); ?></td>
                <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                <td><?php echo htmlspecialchars($row['tipo']); ?></td>
                <td><?php echo htmlspecialchars($row['prioridad']); ?></td>
                <td><?php echo htmlspecialchars($row['estado']); ?></td>
                <td><?php echo htmlspecialchars($row['solicitante_nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['solicitante_depto'] ?? 'General'); ?></td>
                <td><?php echo htmlspecialchars($row['tecnico_nombre'] ?? 'Pendiente'); ?></td>
                <td><?php echo $row['fecha_creacion']; ?></td>
                <td><?php echo $row['fecha_limite'] ?? 'N/A'; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php
    exit();
}

// ==========================================
// MODO PDF (Layout limpio para impresión / guardado)
// ==========================================
if ($formato === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte de Tickets</title>
        <style>
            body { font-family: 'Helvetica', Arial, sans-serif; color: #333; margin: 20px; font-size: 11px; }
            .header { margin-bottom: 20px; border-bottom: 2px solid #38bdf8; padding-bottom: 10px; }
            .header h1 { margin: 0; font-size: 18px; color: #111827; }
            .header p { margin: 4px 0 0 0; color: #6b7280; font-size: 11px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #e5e7eb; padding: 7px 5px; text-align: left; }
            th { background-color: #f3f4f6; color: #1f2937; font-weight: bold; }
            tr:nth-child(even) { background-color: #f9fafb; }
            .badge { padding: 2px 5px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
            .badge-Alta { background-color: #fee2e2; color: #ef4444; }
            .badge-Media { background-color: #fef3c7; color: #d97706; }
            .badge-Baja { background-color: #d1fae5; color: #10b981; }
            .badge-Mantenimiento { background-color: #ede9fe; color: #6d28d9; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>NEOADMIN - REPORTE DE TICKETS</h1>
            <p>
                Filtros aplicados » 
                Estado: <strong><?php echo !empty($filtro_estado) ? strtoupper($filtro_estado) : 'TODOS'; ?></strong> | 
                Área: <strong><?php echo !empty($filtro_depto) ? strtoupper($filtro_depto) : 'TODAS'; ?></strong> | 
                Generado el: <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 22%;">Asunto</th>
                    <th style="width: 10%;">Tipo</th>
                    <th style="width: 10%;">Prioridad</th>
                    <th style="width: 11%;">Estado</th>
                    <th style="width: 14%;">Solicitante</th>
                    <th style="width: 14%;">Área</th>
                    <th style="width: 14%;">Asignado</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $res->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['asunto']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['tipo']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo ($row['estado'] === 'Mantenimiento') ? 'Mantenimiento' : $row['prioridad']; ?>">
                            <?php echo ($row['estado'] === 'Mantenimiento') ? 'Mante.' : $row['prioridad']; ?>
                        </span>
                    </td>
                    <td><strong><?php echo htmlspecialchars($row['estado']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['solicitante_nombre']); ?></td>
                    <td><?php echo htmlspecialchars($row['solicitante_depto'] ?? 'General'); ?></td>
                    <td><?php echo htmlspecialchars($row['tecnico_nombre'] ?? 'Pendiente'); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <script>
            // Disparador automático de guardado / impresión en PDF nativo al cargar la ventana
            window.addEventListener('DOMContentLoaded', () => {
                window.print();
                setTimeout(() => { window.history.back(); }, 1000);
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}
?>