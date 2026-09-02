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

// OBTENER FOTO DE PERFIL DEL USUARIO
$usuario_actual = $_SESSION['usuario'] ?? '';
$stmt_user = $conexion->prepare("SELECT foto_perfil FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
$stmt_user->bind_param("ss", $usuario_actual, $usuario_actual);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user_db = $res_user->fetch_assoc();

$foto_db = trim($user_db['foto_perfil'] ?? '');
$avatar_default = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="%2394a3b8"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>';

$foto_perfil = $avatar_default;
if (!empty($foto_db)) {
    if (filter_var($foto_db, FILTER_VALIDATE_URL)) {
        $foto_perfil = $foto_db;
    } elseif (file_exists('img/' . $foto_db)) {
        $foto_perfil = 'img/' . $foto_db;
    } elseif (file_exists($foto_db)) {
        $foto_perfil = $foto_db;
    }
}

// --- CAPTURA DE FILTROS Y BÚSQUEDA ---
$filtro_estado = $_GET['estado'] ?? '';
$filtro_depto = $_GET['departamento'] ?? ''; 
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_tecnico = $_GET['tecnico_id'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';
$buscar = $_GET['buscar'] ?? '';

// --- 1. LÓGICA DE CONTADORES CORREGIDA PARA NUEVOS ESTADOS ---
$query_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Nuevo' THEN 1 ELSE 0 END) as nuevos,
    SUM(CASE WHEN estado = 'En curso' THEN 1 ELSE 0 END) as en_curso,
    SUM(CASE WHEN estado = 'Resuelto' THEN 1 ELSE 0 END) as resueltos,
    SUM(CASE WHEN estado = 'Cerrado' THEN 1 ELSE 0 END) as cerrados
    FROM tickets WHERE 1=1";

if ($rol != 'administrador' && $rol != 'tecnico') {
    $query_stats .= " AND solicitante_id = $usuario_id";
}
if (!empty($filtro_fecha_desde)) {
    $query_stats .= " AND DATE(fecha_creacion) >= '" . $conexion->real_escape_string($filtro_fecha_desde) . "'";
}
if (!empty($filtro_fecha_hasta)) {
    $query_stats .= " AND DATE(fecha_creacion) <= '" . $conexion->real_escape_string($filtro_fecha_hasta) . "'";
}

$stats_res = $conexion->query($query_stats);
$stats = $stats_res->fetch_assoc();

// --- 2. CONSULTA DE TICKETS CON FILTROS DINÁMICOS ---
if ($rol == 'administrador' || $rol == 'tecnico') {
    $sql = "SELECT t.id, t.asunto, t.descripcion, t.prioridad, t.estado, t.tipo, t.fecha_creacion, t.fecha_limite,
                  t.fecha_mantenimiento, t.detalle_resolucion, t.archivo_adjunto, t.archivo_nombre, t.archivo_tipo,
                  u_sol.nombre_completo AS solicitante_nombre, u_sol.area AS solicitante_depto, 
                  u_tec.nombre_completo AS tecnico_nombre, t.tecnico_id
           FROM tickets t
           JOIN usuarios u_sol ON t.solicitante_id = u_sol.id
           LEFT JOIN usuarios u_tec ON t.tecnico_id = u_tec.id 
           WHERE 1=1";
} else {
    $sql = "SELECT t.id, t.asunto, t.descripcion, t.prioridad, t.estado, t.tipo, t.fecha_creacion, t.fecha_limite,
                  t.fecha_mantenimiento, t.detalle_resolucion, t.archivo_adjunto, t.archivo_nombre, t.archivo_tipo,
                  u_sol.nombre_completo AS solicitante_nombre, u_sol.area AS solicitante_depto,
                  'N/A' as tecnico_nombre, t.tecnico_id
           FROM tickets t
           JOIN usuarios u_sol ON t.solicitante_id = u_sol.id
           WHERE t.solicitante_id = $usuario_id";
}

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
if (!empty($filtro_fecha_desde)) {
    $sql .= " AND DATE(t.fecha_creacion) >= '" . $conexion->real_escape_string($filtro_fecha_desde) . "'";
}
if (!empty($filtro_fecha_hasta)) {
    $sql .= " AND DATE(t.fecha_creacion) <= '" . $conexion->real_escape_string($filtro_fecha_hasta) . "'";
}
if (!empty($buscar)) {
    $b = $conexion->real_escape_string($buscar);
    $sql .= " AND (t.id LIKE '%$b%' OR t.asunto LIKE '%$b%' OR t.descripcion LIKE '%$b%' OR u_sol.nombre_completo LIKE '%$b%')";
}

$sql .= " ORDER BY t.fecha_creacion DESC";
$res = $conexion->query($sql);

$tecnicos_res = $conexion->query("SELECT id, nombre_completo FROM usuarios WHERE rol = 'tecnico'");
$tecnicos = [];
while ($t = $tecnicos_res->fetch_assoc()) { $tecnicos[] = $t; }

$deptos_res = $conexion->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL AND area != ''");
$departamentos = [];
if($deptos_res) {
    while ($d = $deptos_res->fetch_assoc()) { $departamentos[] = $d['area']; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - NeoAdmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Inter:wght@300;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0b0f1a;
            --card-bg: #161c2d;
            --accent: #38bdf8;
            --accent-soft: rgba(56, 189, 248, 0.15);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-gray: #94a3b8;
            --warning-alert: #fbbf24;
            --danger-alert: #ef4444;
            --success-alert: #10b981;
        }
        body { background-color: var(--bg-dark); color: #f8fafc; font-family: 'Inter', sans-serif; }
        .neo-navbar { background: rgba(22, 28, 45, 0.8); backdrop-filter: blur(10px); padding: 0.75rem 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); margin-bottom: 2rem; }
        .logo-img { height: 35px; }
        .nav-link-neo { text-decoration: none; padding: 8px 16px; border-radius: 10px; font-size: 0.9rem; color: var(--text-gray); display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .nav-link-neo:hover { color: #fff; background: rgba(255, 255, 255, 0.05); }

        .logout-btn {
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.2);
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--accent);
            background-color: var(--card-bg);
        }
        
        .card-stat { background: var(--card-bg); border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); padding: 15px; text-align: center; text-decoration: none; display: block; transition: 0.2s; }
        .card-stat:hover { border-color: var(--accent); transform: translateY(-2px); }
        .card-stat h6 { font-family: 'Orbitron'; font-weight: bold; font-size: 1.2rem; margin: 0; }
        .card-stat small { color: #64748b; font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; }
        
        .card-ticket { background: var(--card-bg); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); padding: 1.5rem; height: 100%; transition: all 0.3s ease; position: relative; border-left: 5px solid transparent; }
        .card-normal { border-left-color: var(--accent); }
        .card-nuevo { border-left-color: #38bdf8; background: rgba(56, 189, 248, 0.02); }
        .card-en-curso { border-left-color: #fbbf24; background: rgba(251, 191, 36, 0.02); }
        .card-expired { border-left-color: var(--danger-alert); background: rgba(239, 68, 68, 0.05); animation: pulse-red 2s infinite; }
        
        /* Badges de prioridad estilizados por color */
        .badge-prioridad-baja { background-color: rgba(16, 185, 129, 0.15) !important; color: #10b981 !important; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-prioridad-media { background-color: rgba(245, 158, 11, 0.15) !important; color: #f59e0b !important; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-prioridad-alta { background-color: rgba(239, 68, 68, 0.15) !important; color: #ef4444 !important; border: 1px solid rgba(239, 68, 68, 0.4); box-shadow: 0 0 8px rgba(239, 68, 68, 0.2); }

        .form-control-neo { background: rgba(15, 23, 42, 0.8) !important; border: 1px solid var(--glass-border) !important; color: #ffffff !important; border-radius: 12px; padding: 12px; }
        .form-control-neo:focus { box-shadow: 0 0 0 2px var(--accent-soft); border-color: var(--accent) !important; }
        
        .form-select-neo { background: rgba(15, 23, 42, 0.8) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right 0.75rem center/16px 12px !important; border: 1px solid var(--glass-border) !important; color: #ffffff !important; border-radius: 12px; padding: 10px; font-size: 0.85rem; }
        .form-select-neo:focus { box-shadow: 0 0 0 2px var(--accent-soft); border-color: var(--accent) !important; }
        .form-select-neo option { background-color: #0f172a !important; color: white !important; }

        .search-wrapper-neo { position: relative; }
        .search-wrapper-neo i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-gray); }
        .search-wrapper-neo input { padding-left: 38px !important; }
        
        /* Modernización Custom SweetAlert2 Dark Futuristic */
        .neo-swal-popup { background: rgba(22, 28, 45, 0.95) !important; backdrop-filter: blur(15px); border: 1px solid rgba(56, 189, 248, 0.2) !important; border-radius: 20px !important; box-shadow: 0 20px 50px rgba(0,0,0,0.5) !important; color: #f8fafc !important; }
        .neo-swal-title { font-family: 'Orbitron', sans-serif !important; font-weight: bold !important; letter-spacing: 1px; color: #fff !important; font-size: 1.3rem !important; }
        .neo-swal-input, .neo-swal-textarea, .neo-swal-select { background-color: #0b0f1a !important; color: white !important; border: 1px solid rgba(255,255,255,0.1) !important; border-radius: 12px !important; padding: 10px !important; font-family: 'Inter', sans-serif; }
        .neo-swal-input:focus, .neo-swal-textarea:focus, .neo-swal-select:focus { border-color: var(--accent) !important; box-shadow: 0 0 8px var(--accent-soft) !important; }
        
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .timer-display { font-family: 'Orbitron'; font-size: 0.95rem; font-weight: bold; }
        .btn-action-group { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 15px; }
        .btn-action-card { background: #0f172a; border: 1px solid rgba(255,255,255,0.05); color: white; padding: 8px 2px; border-radius: 10px; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: 0.2s; }
        .btn-action-card:hover { background: var(--accent-soft); border-color: var(--accent); }
        .btn-action-card i { font-size: 1.1rem; margin-bottom: 3px; }
        .btn-action-card span { font-size: 8px; text-transform: uppercase; color: var(--text-gray); font-weight: 700; }
        
        .label-date-neo { font-size: 0.75rem; color: var(--text-gray); font-weight: 600; margin-bottom: 4px; display: block; padding-left: 4px; }
    </style>
</head>
<body>

    <nav class="neo-navbar d-flex justify-content-between align-items-center sticky-top">
        <div class="d-flex align-items-center gap-3">
            <img src="img/logo_neoadmin.png" alt="Logo" class="logo-img">
            <span style="font-family: 'Orbitron'; font-size: 1.1rem; color: var(--accent); font-weight: bold;">NEO ADMIN</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="nav-link-neo"><i class="bi bi-house-door"></i> Inicio</a>
            <a href="tickets_lista.php" class="nav-link-neo" style="background: var(--accent-soft); color: var(--accent);"><i class="bi bi-headset"></i> Mesa de Ayuda</a>
            <div class="vr mx-2 opacity-25" style="height: 20px; align-self: center;"></div>

            <!-- Foto de perfil y menú desplegable -->
            <div class="dropdown me-2">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userMenuHeader" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Perfil" class="user-avatar me-2">
                    <span class="fw-bold small d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg rounded-4" aria-labelledby="userMenuHeader">
                    <li><a class="dropdown-item py-2" href="perfil.php"><i class="bi bi-person-fill me-2"></i> Mi Perfil</a></li>
                    <li><hr class="dropdown-divider opacity-25"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
                </ul>
            </div>

            <a href="logout.php" class="nav-link-neo logout-btn d-none d-md-flex">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="font-family: 'Orbitron'; font-weight: bold;">TICKETS <?php echo !empty($filtro_estado) ? ' - '.strtoupper($filtro_estado) : ''; ?></h2>
            <div class="d-flex gap-2">
                <button onclick="solicitarReporte()" class="btn btn-outline-light fw-bold" style="border-radius: 12px; border: 1px solid var(--glass-border);">
                    <i class="bi bi-download me-1"></i> REPORTES
                </button>
                <a href="tickets_crear.php" class="btn btn-info fw-bold" style="border-radius: 12px; background: var(--accent); border:none;">+ NUEVO TICKET</a>
            </div>
        </div>

        <div class="p-4 mb-4 rounded-4" style="background: var(--card-bg); border: 1px solid rgba(255,255,255,0.05);">
            <form method="GET" action="tickets_lista.php" id="formFiltros" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <span class="label-date-neo">Término de búsqueda</span>
                    <div class="search-wrapper-neo">
                        <i class="bi bi-search"></i>
                        <input type="text" name="buscar" class="form-control form-control-neo" placeholder="Buscar ID, asunto..." value="<?php echo htmlspecialchars($buscar); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <span class="label-date-neo">Filtrar Estado</span>
                    <select name="estado" class="form-select form-select-neo">
                        <option value="">[Todos los Estados]</option>
                        <option value="Nuevo" <?php echo $filtro_estado === 'Nuevo' ? 'selected' : ''; ?>>Nuevo</option>
                        <option value="En curso" <?php echo $filtro_estado === 'En curso' ? 'selected' : ''; ?>>En curso</option>
                        <option value="Resuelto" <?php echo $filtro_estado === 'Resuelto' ? 'selected' : ''; ?>>Resuelto</option>
                        <option value="Cerrado" <?php echo $filtro_estado === 'Cerrado' ? 'selected' : ''; ?>>Cerrado</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <span class="label-date-neo">Área / Departamento</span>
                    <select name="departamento" class="form-select form-select-neo">
                        <option value="">[Área / Depto]</option>
                        <?php foreach($departamentos as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $filtro_depto === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <span class="label-date-neo">Tipo Incidencia</span>
                    <select name="tipo" class="form-select form-select-neo">
                        <option value="">[Incidencia / Tipo]</option>
                        <option value="Incidencia" <?php echo $filtro_tipo === 'Incidencia' ? 'selected' : ''; ?>>Incidencia</option>
                        <option value="Solicitud" <?php echo $filtro_tipo === 'Solicitud' ? 'selected' : ''; ?>>Solicitud</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <span class="label-date-neo">Prioridad</span>
                    <select name="prioridad" class="form-select form-select-neo">
                        <option value="">[Prioridad]</option>
                        <option value="Baja" <?php echo $filtro_prioridad === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                        <option value="Media" <?php echo $filtro_prioridad === 'Media' ? 'selected' : ''; ?>>Media</option>
                        <option value="Alta" <?php echo $filtro_prioridad === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <span class="label-date-neo">Fecha Desde</span>
                    <input type="date" name="fecha_desde" class="form-control form-control-neo py-2" value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
                </div>

                <div class="col-md-3">
                    <span class="label-date-neo">Fecha Hasta</span>
                    <input type="date" name="fecha_hasta" class="form-control form-control-neo py-2" value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
                </div>

                <?php if ($rol == 'administrador' || $rol == 'tecnico'): ?>
                <div class="col-md-3">
                    <span class="label-date-neo">Técnico Asignado</span>
                    <select name="tecnico_id" class="form-select form-select-neo">
                        <option value="">[Técnico Asignado]</option>
                        <?php foreach($tecnicos as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $filtro_tecnico == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['nombre_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md d-flex gap-2">
                    <button type="submit" class="btn btn-info w-100 fw-bold" style="border-radius:12px; background: var(--accent); border:none; height:45px;">Filtrar</button>
                    <a href="tickets_lista.php" class="btn btn-secondary fw-bold d-flex align-items-center justify-content-center" style="border-radius:12px; background:rgba(255,255,255,0.05); border:1px solid var(--glass-border); width:50px; height:45px;" title="Limpiar Filtros">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-5">
            <div class="col-md"><a href="tickets_lista.php" class="card-stat"><h6><?php echo $stats['total']; ?></h6><small>TOTAL</small></a></div>
            <div class="col-md"><a href="tickets_lista.php?estado=Nuevo" class="card-stat"><h6 class="text-info"><?php echo $stats['nuevos'] ?? 0; ?></h6><small>NUEVOS</small></a></div>
            <div class="col-md"><a href="tickets_lista.php?estado=En curso" class="card-stat"><h6 class="text-warning"><?php echo $stats['en_curso'] ?? 0; ?></h6><small>EN CURSO</small></a></div>
            <div class="col-md"><a href="tickets_lista.php?estado=Resuelto" class="card-stat"><h6 class="text-success"><?php echo $stats['resueltos'] ?? 0; ?></h6><small>RESUELTOS</small></a></div>
            <div class="col-md"><a href="tickets_lista.php?estado=Cerrado" class="card-stat"><h6 class="text-muted"><?php echo $stats['cerrados'] ?? 0; ?></h6><small>CERRADOS</small></a></div>
        </div>

        <div class="row g-4">
            <?php if ($res->num_rows === 0): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-folder-x text-secondary" style="font-size: 3rem;"></i>
                    <p class="text-secondary mt-2">No se encontraron registros de tickets para este criterio.</p>
                </div>
            <?php endif; ?>

            <?php while($row = $res->fetch_assoc()): 
                $displayTitle = $row['asunto'];
                $displayDeadline = $row['fecha_limite'];

                if(empty($displayDeadline)){
                    $displayDeadline = date('Y-m-d H:i:s', strtotime($row['fecha_creacion'] . ' + 48 hours'));
                }

                $ticketArray = [
                    'id' => $row['id'],
                    'asunto' => $row['asunto'],
                    'descripcion' => $row['descripcion'],
                    'prioridad' => $row['prioridad'],
                    'estado' => $row['estado'],
                    'tipo' => $row['tipo'],
                    'detalle_resolucion' => $row['detalle_resolucion'] ?? '',
                    'adjunto' => !empty($row['archivo_adjunto']) ? true : false,
                    'archivo_nombre' => $row['archivo_nombre'] ?? '',
                    'archivo_tipo' => $row['archivo_tipo'] ?? ''
                ];
                $ticketJsonSeguro = htmlspecialchars(json_encode($ticketArray, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                
                // Mapeo dinámico de estilos de tarjeta segun el estado
                $cardEstadoClass = 'card-normal';
                if($row['estado'] === 'Nuevo') $cardEstadoClass = 'card-nuevo';
                if($row['estado'] === 'En curso') $cardEstadoClass = 'card-en-curso';

                // Mapeo dinámico de estilos de badge por prioridad
                $badgePrioridadClass = 'badge-prioridad-baja';
                if(strtolower($row['prioridad']) === 'media') $badgePrioridadClass = 'badge-prioridad-media';
                if(strtolower($row['prioridad']) === 'alta') $badgePrioridadClass = 'badge-prioridad-alta';
            ?>
            <div class="col-md-4">
                <div class="card-ticket <?php echo $cardEstadoClass; ?>" 
                     id="card-<?php echo $row['id']; ?>" 
                     data-deadline="<?php echo $displayDeadline; ?>" 
                     data-estado="<?php echo $row['estado']; ?>">
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span class="badge <?php echo $badgePrioridadClass; ?> text-uppercase px-2 py-1">
                            <?php echo $row['prioridad']; ?>
                        </span>
                        <div id="timer-<?php echo $row['id']; ?>" class="timer-display">Cargando...</div>
                    </div>
                    
                    <h5 class="fw-bold text-white mb-2 text-truncate"><?php echo htmlspecialchars($displayTitle); ?></h5>
                    <p class="text-secondary small mb-1">Tipo: <span class="text-white fw-semibold"><?php echo htmlspecialchars($row['tipo']); ?></span></p>
                    <p class="text-secondary small mb-1">Estado: <span class="text-info fw-bold"><?php echo htmlspecialchars($row['estado']); ?></span></p>
                    <p class="text-secondary small mb-1">Técnico: <span class="text-white"><?php echo htmlspecialchars($row['tecnico_nombre'] ?? 'Pendiente'); ?></span></p>
                    <p class="text-secondary small mb-3" style="font-size:0.75rem;">Área: <span class="text-info"><?php echo htmlspecialchars($row['solicitante_depto'] ?? 'General'); ?></span></p>
                    
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-secondary text-truncate" style="max-width: 70%;"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($row['solicitante_nombre']); ?></span>
                        <a href="javascript:void(0)" onclick="verDetalle(this)" data-ticket="<?php echo $ticketJsonSeguro; ?>" class="text-info small text-decoration-none fw-bold">Detalles ></a>
                    </div>

                    <?php if ($rol == 'administrador' || $rol == 'tecnico'): ?>
                    <div class="btn-action-group">
                        <div onclick="asignarTicket(<?php echo $row['id']; ?>)" class="btn-action-card"><i class="bi bi-person-plus text-info"></i><span>Asignar</span></div>
                        <div onclick="extenderTiempo(<?php echo $row['id']; ?>)" class="btn-action-card"><i class="bi bi-clock-history text-primary"></i><span>+Tiempo</span></div>
                        <div onclick="resolverTicket(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['estado'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($row['detalle_resolucion'] ?? ''), ENT_QUOTES); ?>')" class="btn-action-card"><i class="bi bi-check2-circle text-success"></i><span>Estado</span></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: rgba(22, 28, 45, 0.95); backdrop-filter: blur(15px); border-radius: 20px; border: 1px solid rgba(56, 189, 248, 0.25); box-shadow: 0 0 25px rgba(56, 189, 248, 0.15);">
                <div id="modalContent"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function updateTimers() {
            const now = new Date().getTime();
            document.querySelectorAll('.card-ticket').forEach(card => {
                const deadlineStr = card.getAttribute('data-deadline');
                const estado = card.getAttribute('data-estado');
                const display = card.querySelector('.timer-display');

                if (estado === 'Resuelto' || estado === 'Cerrado') {
                    display.innerHTML = "FINALIZADO";
                    display.style.color = "#10b981";
                    card.classList.remove('card-expired', 'card-warning', 'card-nuevo', 'card-en-curso');
                    card.classList.add('card-normal');
                    return;
                }

                if (!deadlineStr) {
                    display.innerHTML = "--:--:--";
                    return;
                }

                const countDate = new Date(deadlineStr).getTime();
                const diff = countDate - now;

                if (diff <= 0) {
                    display.innerHTML = "EXPIRADO";
                    display.style.color = '#ef4444';
                    card.className = 'card-ticket card-expired';
                } else {
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    display.innerHTML = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

                    if (estado === 'Nuevo') {
                        card.className = 'card-ticket card-nuevo';
                        display.style.color = '#38bdf8';
                    } else if (estado === 'En curso') {
                        card.className = 'card-ticket card-en-curso';
                        display.style.color = '#fbbf24';
                    } else {
                        card.className = 'card-ticket card-normal';
                        display.style.color = '#38bdf8';
                    }
                }
            });
        }
        setInterval(updateTimers, 1000);
        updateTimers();

        function verDetalle(elemento) {
            let ticket = {};
            try {
                ticket = JSON.parse(elemento.getAttribute('data-ticket'));
            } catch (e) {
                console.error("Error al procesar los datos del ticket", e);
                return;
            }

            let adjuntoHtml = `<div class="py-2 text-muted small"><i class="bi bi-paperclip me-1" style="font-size: 1.2rem;"></i> Sin archivos adjuntos.</div>`;

            if (ticket.adjunto) {
                const rutaControlador = `ver_adjunto.php?id=${ticket.id}`;
                const tipoMime = ticket.archivo_tipo.toLowerCase();
                const nombreArchivo = ticket.archivo_nombre;

                if (tipoMime.includes('image/png') || tipoMime.includes('image/jpeg') || tipoMime.includes('image/jpg')) {
                    adjuntoHtml = `
                        <img src="${rutaControlador}" alt="Adjunto" class="img-fluid rounded-2 mb-2" style="max-height: 180px; object-fit: contain; border: 1px solid rgba(255,255,255,0.1);"><br>
                        <p class="small text-muted mb-1 text-truncate px-2">${nombreArchivo}</p>
                        <a href="${rutaControlador}" target="_blank" class="btn btn-sm btn-outline-info fw-bold mt-1" style="font-size: 0.75rem; border-radius: 8px;">
                            <i class="bi bi-eye me-1"></i> Ver Imagen Completa
                        </a>`;
                } else if (tipoMime.includes('pdf')) {
                    adjuntoHtml = `
                        <div class="py-2">
                            <i class="bi bi-file-earmark-pdf text-danger mb-2" style="font-size: 2.5rem;"></i>
                            <p class="small text-white mb-2 text-truncate px-3">${nombreArchivo}</p>
                            <a href="${rutaControlador}" target="_blank" class="btn btn-sm btn-outline-danger fw-bold" style="font-size: 0.75rem; border-radius: 8px;">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> Abrir / Ver PDF
                            </a>
                        </div>`;
                } else {
                    adjuntoHtml = `
                        <div class="py-2">
                            <i class="bi bi-file-earmark-text text-info mb-2" style="font-size: 2.5rem;"></i>
                            <p class="small text-white mb-2 text-truncate px-3">${nombreArchivo}</p>
                            <a href="${rutaControlador}" download="${nombreArchivo}" class="btn btn-sm btn-outline-info fw-bold" style="font-size: 0.75rem; border-radius: 8px;">
                                <i class="bi bi-download me-1"></i> Descargar Archivo Real
                            </a>
                        </div>`;
                }
            }

            // Visualización del Comentario/Detalle de Resolución Registrado
            let detalleResolucionHtml = '';
            if (ticket.detalle_resolucion && ticket.detalle_resolucion.trim() !== '') {
                detalleResolucionHtml = `
                    <div class="mb-3 p-3 rounded-3" style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.25);">
                        <label class="form-label small fw-bold text-info text-uppercase mb-1">
                            <i class="bi bi-chat-left-text me-1"></i> Comentario / Detalles de Resolución
                        </label>
                        <p class="mb-0 text-white" style="font-size: 0.9rem; white-space: pre-wrap;">${ticket.detalle_resolucion}</p>
                    </div>`;
            } else {
                detalleResolucionHtml = `
                    <div class="mb-3 p-3 rounded-3" style="background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1);">
                        <span class="small text-muted"><i class="bi bi-info-circle me-1"></i> No se han registrado comentarios o observaciones sobre el estado.</span>
                    </div>`;
            }

            const htmlModal = `
                <div class="modal-header border-bottom-0 pb-0" style="padding: 25px 25px 0 25px;">
                    <h5 class="modal-title fw-bold text-white" style="font-family: 'Orbitron'; font-size: 1.1rem; letter-spacing:1px;">GESTIONAR TICKET #${ticket.id}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <form id="formEditarTicketBase" onsubmit="event.preventDefault(); guardarCambiosTicketBase();">
                        <input type="hidden" name="id" value="${ticket.id}">
                        <input type="hidden" name="accion" value="editar_basico">

                        ${detalleResolucionHtml}
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Archivo Adjunto Cargado</label>
                            <div class="p-3 text-center rounded-3" style="background: #0b0f1a; border: 1px solid rgba(255,255,255,0.05);">
                                ${adjuntoHtml}
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">TÍTULO / ASUNTO <span class="text-danger">*</span></label>
                            <input type="text" name="asunto" class="form-control form-control-neo" value="${ticket.asunto}" required placeholder="Escriba el asunto...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">DESCRIPCIÓN <span class="text-danger">*</span></label>
                            <textarea name="descripcion" class="form-control form-control-neo" rows="3" required placeholder="Escriba el detalle de la incidencia..." style="font-size:0.9rem;">${ticket.descripcion}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">TIPO DE SOLICITUD / INCIDENCIA</label>
                            <select name="tipo" class="form-select form-control-neo form-select-neo" required>
                                <option value="Incidencia" ${ticket.tipo === 'Incidencia' ? 'selected' : ''}>Incidencia (Algo falló)</option>
                                <option value="Solicitud" ${ticket.tipo === 'Solicitud' ? 'selected' : ''}>Solicitud (Nuevo requerimiento)</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">PRIORIDAD</label>
                            <div class="input-group">
                                <span class="input-group-text input-group-text-neo" id="modal-termometro-icon" style="background: rgba(15, 23, 42, 0.9); border: 1px solid rgba(255,255,255,0.08);">
                                    <i class="bi bi-thermometer-half"></i>
                                </span>
                                <select name="prioridad" id="modalPrioridadSelect" class="form-select form-control-neo form-select-neo w-75" required onchange="actualizarColorPrioridadModal()">
                                    <option value="Baja" ${ticket.prioridad === 'Baja' ? 'selected' : ''}>Baja</option>
                                    <option value="Media" ${ticket.prioridad === 'Media' ? 'selected' : ''}>Media</option>
                                    <option value="Alta" ${ticket.prioridad === 'Alta' ? 'selected' : ''}>Alta</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2 pt-2">
                            <button type="submit" class="btn btn-info fw-bold w-100" style="border-radius: 12px; background: var(--accent); border: none; color: #0b0f1a; padding: 12px;"> GUARDAR CAMBIOS </button>
                            <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal" style="border-radius: 12px; background: transparent; border: 1px solid #475569; color: #94a3b8; padding: 12px;"> CANCELAR </button>
                        </div>
                    </form>
                </div>`;
            
            $('#modalContent').html(htmlModal);
            (new bootstrap.Modal(document.getElementById('modalDetalle'))).show();
            actualizarColorPrioridadModal();
        }

        function actualizarColorPrioridadModal() {
            const select = document.getElementById('modalPrioridadSelect');
            const icon = document.querySelector('#modal-termometro-icon i');
            if(!select || !icon) return;
            const valor = select.value;
            icon.style.color = (valor === 'Baja') ? '#10b981' : (valor === 'Media') ? '#f59e0b' : '#ef4444';
        }

        function guardarCambiosTicketBase() {
            const formulario = document.getElementById('formEditarTicketBase');
            if (!formulario.reportValidity()) return;

            const fd = new FormData(formulario);

            fetch('tickets_procesar.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { 
                if(d.status === 'success') {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'TICKET ACTUALIZADO', 
                        text: 'Los cambios de prioridad, incidencia y texto fueron guardados.',
                        customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title' },
                        showConfirmButton: false, 
                        timer: 1500 
                    }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: d.message, customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title' } });
                }
            }).catch(e => {
                console.error("Error en la solicitud:", e);
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo procesar en el servidor.', customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title' } });
            });
        }

        async function extenderTiempo(id) {
            const { value: horas } = await Swal.fire({
                title: 'EXTENDER PLAZO',
                input: 'select',
                inputOptions: { '24': '+24 Horas', '48': '+48 Horas', '72': '+72 Horas' },
                customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title', input: 'neo-swal-select' },
                confirmButtonColor: '#38bdf8', showCancelButton: true, cancelButtonColor: '#475569'
            });
            if (horas) enviarAccion({ id, horas, accion: 'extender_tiempo' });
        }

        async function asignarTicket(id) {
            const { value: tId } = await Swal.fire({
                title: 'ASIGNAR TÉCNICO',
                input: 'select',
                inputOptions: { <?php foreach($tecnicos as $t) echo "'{$t['id']}': '".addslashes($t['nombre_completo'])."',"; ?> },
                customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title', input: 'neo-swal-select' },
                confirmButtonColor: '#38bdf8', showCancelButton: true, cancelButtonColor: '#475569'
            });
            if (tId) enviarAccion({ id, tecnico_id: tId, accion: 'asignar' });
        }

        async function resolverTicket(id, estadoActual = 'En curso', detallePrevio = '') {
            const { value: f } = await Swal.fire({
                title: 'CAMBIAR ESTADO DEL TICKET',
                customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title' },
                html: `
                    <label class="small text-secondary d-block text-start mb-1 fw-bold">Seleccionar Estado</label>
                    <select id="s-est" class="neo-swal-select w-100 mb-3">
                        <option value="En curso" ${estadoActual === 'En curso' ? 'selected' : ''}>En curso</option>
                        <option value="Resuelto" ${estadoActual === 'Resuelto' ? 'selected' : ''}>Resuelto</option>
                        <option value="Cerrado" ${estadoActual === 'Cerrado' ? 'selected' : ''}>Cerrado</option>
                    </select>
                    <label class="small text-secondary d-block text-start mb-1 fw-bold">Comentarios / Detalles de la resolución</label>
                    <textarea id="s-det" class="neo-swal-textarea w-100" style="height:100px;" placeholder="Escriba los detalles aquí...">${detallePrevio}</textarea>
                `,
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#475569',
                preConfirm: () => ({ estado: document.getElementById('s-est').value, detalle: document.getElementById('s-det').value })
            });
            if (f) enviarAccion({ ...f, id, accion: 'resolver' });
        }

        function enviarAccion(datos) {
            const fd = new FormData();
            for (let k in datos) fd.append(k, datos[k]);
            fetch('tickets_procesar.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { 
                if(d.status === 'success') {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'ESTADO ACTUALIZADO', 
                        customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title' },
                        showConfirmButton: false, 
                        timer: 1200 
                    }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: d.message, customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title' } });
                }
            });
        }

        function solicitarReporte() {
            Swal.fire({
                title: 'EXPORTAR REPORTE',
                text: '¿En qué formato deseas descargar el listado con tus filtros actuales?',
                icon: 'question',
                customClass: { popup: 'neo-swal-popup', title: 'neo-swal-title' },
                showCancelButton: true, showDenyButton: true,
                confirmButtonColor: '#10b981', denyButtonColor: '#38bdf8', cancelButtonColor: '#475569',
                confirmButtonText: '<i class="bi bi-file-earmark-excel"></i> Excel',
                denyButtonText: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                let formato = '';
                if (result.isConfirmed) formato = 'excel';
                else if (result.isDenied) formato = 'pdf';
                else return;

                const formElement = document.getElementById('formFiltros');
                const formData = new FormData(formElement);
                const params = new URLSearchParams(formData);
                params.append('formato', formato);

                window.location.href = `tickets_reporte.php?${params.toString()}`;
            });
        }
    </script>
</body>
</html>