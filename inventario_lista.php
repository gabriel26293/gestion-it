<?php
session_start();
require_once 'db.php'; 

/**
 * CONTROL DE ACCESO
 * Verifica que el usuario esté logueado y tenga permisos suficientes.
 */
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

$rol = $_SESSION['rol'];
$mostrar_alerta_permiso = false;

if ($rol === 'operativo') {
    $mostrar_alerta_permiso = true;
} 
elseif ($rol !== 'administrador' && $rol !== 'tecnico') {
    header("Location: dashboard.php"); 
    exit();
}

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

// Consulta de Inventario con nombres de usuarios y categorías
$query = "SELECT i.*, c.nombre AS categoria_nombre, u.nombre_completo AS nombre_usuario
          FROM inventario i 
          INNER JOIN categorias c ON i.tipo_id = c.id 
          LEFT JOIN usuarios u ON i.usuario_asignado_id = u.id
          ORDER BY i.id DESC";

$resultado = $conexion->query($query);

// Consulta para categorías (Filtro y Modal)
$res_categorias = $conexion->query("SELECT * FROM categorias ORDER BY nombre ASC");
$res_cat_modal = $conexion->query("SELECT * FROM categorias ORDER BY nombre ASC");

// CORRECCIÓN CLAVE: Se consulta el campo 'area' en lugar de 'sector'
$res_usuarios_modal = $conexion->query("SELECT id, nombre_completo, area FROM usuarios ORDER BY nombre_completo ASC");
$usuarios_opciones = "";
while($u = $res_usuarios_modal->fetch_assoc()){
    $area_attr = htmlspecialchars($u['area'] ?? '', ENT_QUOTES);
    $usuarios_opciones .= "<option value='{$u['id']}' data-sector='{$area_attr}'>{$u['nombre_completo']}</option>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoAdmin | Inventario</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-dark: #1e293b;
            --accent: #38bdf8;
            --accent-soft: rgba(56, 189, 248, 0.1);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--bg-dark);
            font-family: 'Inter', sans-serif;
            color: white;
            min-height: 100vh;
            margin: 0;
            background-image: 
                linear-gradient(rgba(56, 189, 248, 0.02) 1px, transparent 1px), 
                linear-gradient(90deg, rgba(56, 189, 248, 0.02) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        .neo-navbar {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
            padding: 12px 30px;
        }

        .nav-link-neo {
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            padding: 8px 15px;
            border-radius: 10px;
            font-size: 0.95rem;
        }

        .nav-link-neo:hover { background: var(--accent-soft); color: var(--accent); }

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
            background-color: var(--card-dark);
        }

        .main-content { padding: 40px; animation: fadeInPage 0.6s ease-out; }

        @keyframes fadeInPage {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .search-container {
            background: var(--card-dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .form-control-neo {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--glass-border);
            color: white;
            border-radius: 10px;
        }

        .form-control-neo:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: var(--accent);
            color: white;
            box-shadow: none;
        }

        .asset-card {
            background: var(--card-dark);
            border: 1px solid var(--glass-border);
            border-radius: 22px;
            padding: 25px;
            height: 100%;
            transition: 0.4s;
            position: relative;
            overflow: hidden; 
        }

        .asset-card:hover { transform: translateY(-5px); border-color: var(--accent); }

        .id-badge {
            position: absolute;
            top: 12px;
            right: 15px;
            color: var(--accent);
            font-size: 0.65rem;
            font-weight: 700;
            font-family: 'Orbitron';
            opacity: 0.6;
            z-index: 5;
        }

        .status-pill {
            position: absolute;
            top: 40px;
            right: -2px;
            font-size: 0.6rem; 
            padding: 4px 12px;
            border-radius: 4px 0 0 4px; 
            font-weight: 800;
            text-transform: uppercase;
            box-shadow: -2px 2px 8px rgba(0,0,0,0.4);
            z-index: 10;
            letter-spacing: 0.5px;
        }

        .status-disponible { background: #10b981; color: #fff; border: 1px solid #059669; }
        .status-asignado { background: #3b82f6; color: #fff; border: 1px solid #2563eb; }
        .status-reparacion { background: #ef4444; color: #fff; border: 1px solid #dc2626; }

        .asset-icon-wrapper {
            width: 48px; height: 48px;
            background: var(--accent-soft);
            color: var(--accent);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .info-row { display: flex; align-items: center; gap: 10px; margin-top: 8px; font-size: 0.85rem; }
        .info-row i { color: var(--accent); width: 16px; }

        .modal-content { background: var(--card-dark); border: 1px solid var(--accent); color: white; border-radius: 20px; }
        .modal-header { border-bottom: 1px solid var(--glass-border); }
    </style>
</head>
<body>

    <nav class="neo-navbar d-flex justify-content-between align-items-center sticky-top">
        <div class="d-flex align-items-center gap-3">
            <img src="img/logo_neoadmin.png" alt="Logo" style="height: 40px;">
            <span style="font-family: 'Orbitron'; font-size: 1.2rem; color: var(--accent);">NEO ADMIN</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="nav-link-neo"><i class="bi bi-house-door-fill"></i> Inicio</a>
            <a href="tickets_lista.php" class="nav-link-neo"><i class="bi bi-headset"></i> Mesa de Ayuda</a>
            <div class="vr mx-2 opacity-25" style="height: 20px; align-self: center;"></div>
            
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

    <main class="main-content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h1 class="fw-bold mb-1" style="font-family: 'Orbitron';">INVENTARIO</h1>
        </div>

        <div class="search-container">
            <div class="mb-3">
                <button type="button" class="btn btn-info fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevo">
                    <i class="bi bi-plus-circle"></i> Nuevo Equipo
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-9">
                    <input type="text" id="searchInput" class="form-control form-control-neo" placeholder="Buscar por marca, modelo, sector...">
                </div>
                <div class="col-md-3">
                    <select id="categoryFilter" class="form-select form-control-neo">
                        <option value="all">Todas las categorías</option>
                        <?php while($cat_row = $res_categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat_row['nombre']; ?>"><?php echo $cat_row['nombre']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row g-4" id="inventoryGrid">
            <?php while($row = $resultado->fetch_assoc()): 
                $estado_actual = $row['estado'] ?: 'Disponible';
                $st_comp = mb_strtolower($estado_actual, 'UTF-8');
                
                $st_class = "status-disponible"; 
                if ($st_comp === 'asignado') {
                    $st_class = "status-asignado";
                } elseif (strpos($st_comp, 'repara') !== false) {
                    $st_class = "status-reparacion";
                }

                $search_data = strtolower($row['marca']." ".$row['modelo']." ".$row['codigo_patrimonial']." ".$row['sector']." ".$row['nombre_usuario']);
            ?>
            <div class="col-md-6 col-lg-4 asset-item" data-category="<?php echo $row['categoria_nombre']; ?>" data-search="<?php echo $search_data; ?>">
                <div class="asset-card">
                    <span class="status-pill <?php echo $st_class; ?>">
                        <?php echo htmlspecialchars($estado_actual); ?>
                    </span>
                    
                    <span class="id-badge">ID #<?php echo $row['id']; ?></span>

                    <div class="asset-icon-wrapper"><i class="bi bi-pc-display"></i></div>

                    <h5 class="fw-bold mb-1"><?php echo $row['marca']." ".$row['modelo']; ?></h5>
                    <p class="small text-secondary mb-3">Patrimonio: <span class="text-white"><?php echo $row['codigo_patrimonial']; ?></span></p>

                    <div class="info-row"><i class="bi bi-geo-alt"></i><span>Sector: <strong><?php echo $row['sector'] ?: 'No definido'; ?></strong></span></div>
                    <div class="info-row"><i class="bi bi-person-badge"></i><span>Asignado: <strong><?php echo $row['nombre_usuario'] ?: 'Sin asignar'; ?></strong></span></div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-white border-opacity-10">
                        <span class="badge bg-secondary opacity-50"><?php echo $row['categoria_nombre']; ?></span>
                        <button onclick='abrirModalEditar(<?php echo json_encode($row); ?>)' class="btn btn-sm btn-outline-info border-0">
                            <i class="bi bi-pencil-square fs-5"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </main>

    <!-- MODAL EDITAR -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" style="font-family: 'Orbitron';">EDITAR ASIGNACIÓN</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formUpdateInventario">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="small text-secondary mb-1">SECTOR</label>
                            <select name="sector" id="edit_sector" class="form-select form-control-neo select-sector">
                                <option value="">Sin definir / No aplica</option>
                                <option value="Administración">Administración</option>
                                <option value="Atención al Cliente">Atención al Cliente</option>
                                <option value="Contabilidad">Contabilidad</option>
                                <option value="Data Center">Data Center</option>
                                <option value="Dirección General">Dirección General</option>
                                <option value="Finanzas">Finanzas</option>
                                <option value="Gerencia">Gerencia</option>
                                <option value="Infraestructura">Infraestructura</option>
                                <option value="Legales">Legales</option>
                                <option value="Logística TI">Logística TI</option>
                                <option value="Mesa de Entradas">Mesa de Entradas</option>
                                <option value="Monitoreo de Red">Monitoreo de Red</option>
                                <option value="Operaciones">Operaciones</option>
                                <option value="Operaciones TI">Operaciones TI</option>
                                <option value="Recepción">Recepción</option>
                                <option value="Recursos Humanos">Recursos Humanos</option>
                                <option value="Seguridad Informática">Seguridad Informática</option>
                                <option value="Sistemas">Sistemas</option>
                                <option value="Soporte Técnico">Soporte Técnico</option>
                                <option value="Ventas">Ventas</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small text-secondary mb-1">USUARIO ASIGNADO</label>
                            <select name="usuario_id" id="edit_usuario" class="form-select form-control-neo select-usuario">
                                <option value="">Sin asignar</option>
                                <?php echo $usuarios_opciones; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small text-secondary mb-1">ESTADO</label>
                            <select name="estado" id="edit_estado" class="form-select form-control-neo">
                                <option value="Disponible">Disponible</option>
                                <option value="Asignado">Asignado</option>
                                <option value="Reparación">Reparación</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info w-100 fw-bold mt-2">GUARDAR CAMBIOS</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL NUEVO -->
    <div class="modal fade" id="modalNuevo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" style="font-family: 'Orbitron';">NUEVO EQUIPO</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNuevoEquipo">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">CATEGORÍA</label>
                                <select name="tipo_id" class="form-select form-control-neo" required>
                                    <option value="" disabled selected>Seleccione una categoría</option>
                                    <?php 
                                    $res_cat_modal->data_seek(0);
                                    while($cat = $res_cat_modal->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nombre']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">CÓDIGO PATRIMONIAL</label>
                                <input type="text" name="codigo_patrimonial" class="form-control form-control-neo" placeholder="Ej: NBK-2026-001" required>
                            </div>

                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">MARCA</label>
                                <input type="text" name="marca" class="form-control form-control-neo" placeholder="Ej: Dell, Lenovo..." required>
                            </div>

                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">MODELO</label>
                                <input type="text" name="modelo" class="form-control form-control-neo" placeholder="Ej: ThinkPad E14..." required>
                            </div>

                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">NÚMERO DE SERIE (OPCIONAL)</label>
                                <input type="text" name="serie" class="form-control form-control-neo" placeholder="Ej: BR549XF...">
                            </div>

                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">ESTADO INICIAL</label>
                                <select name="estado" class="form-select form-control-neo" required>
                                    <option value="Disponible" selected>Disponible</option>
                                    <option value="Asignado">Asignado</option>
                                    <option value="Reparación">Reparación</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">SECTOR DEL EQUIPO</label>
                                <select name="sector" id="nuevo_sector" class="form-select form-control-neo select-sector">
                                    <option value="" selected>Sin definir / No aplica</option>
                                    <option value="Administración">Administración</option>
                                    <option value="Atención al Cliente">Atención al Cliente</option>
                                    <option value="Contabilidad">Contabilidad</option>
                                    <option value="Data Center">Data Center</option>
                                    <option value="Dirección General">Dirección General</option>
                                    <option value="Finanzas">Finanzas</option>
                                    <option value="Gerencia">Gerencia</option>
                                    <option value="Infraestructura">Infraestructura</option>
                                    <option value="Legales">Legales</option>
                                    <option value="Logística TI">Logística TI</option>
                                    <option value="Mesa de Entradas">Mesa de Entradas</option>
                                    <option value="Monitoreo de Red">Monitoreo de Red</option>
                                    <option value="Operaciones">Operaciones</option>
                                    <option value="Operaciones TI">Operaciones TI</option>
                                    <option value="Recepción">Recepción</option>
                                    <option value="Recursos Humanos">Recursos Humanos</option>
                                    <option value="Seguridad Informática">Seguridad Informática</option>
                                    <option value="Sistemas">Sistemas</option>
                                    <option value="Soporte Técnico">Soporte Técnico</option>
                                    <option value="Ventas">Ventas</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="small text-secondary mb-1">USUARIO ASIGNADO</label>
                                <select name="usuario_id" id="nuevo_usuario" class="form-select form-control-neo select-usuario">
                                    <option value="" selected>Sin asignar inicialmente</option>
                                    <?php echo $usuarios_opciones; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-info w-100 fw-bold py-2" style="font-family: 'Orbitron'; letter-spacing: 1px;">
                                GUARDAR EQUIPO
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalEdit = new bootstrap.Modal(document.getElementById('modalEditar'));
        const modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));

        function abrirModalEditar(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_sector').value = data.sector || '';
            
            // Filtrar usuarios según el sector cargado en edición
            filtrarUsuariosPorSector('#modalEditar', data.sector || '', data.usuario_asignado_id || '');
            
            document.getElementById('edit_estado').value = data.estado || 'Disponible';
            modalEdit.show();
        }

        function filter() {
            const text = document.getElementById('searchInput').value.toLowerCase();
            const cat = document.getElementById('categoryFilter').value;
            document.querySelectorAll('.asset-item').forEach(item => {
                const match = item.dataset.search.includes(text) && (cat === 'all' || item.dataset.category === cat);
                item.style.display = match ? 'block' : 'none';
            });
        }
        document.getElementById('searchInput').addEventListener('input', filter);
        document.getElementById('categoryFilter').addEventListener('change', filter);

        // FUNCIÓN FILTRADORA: Muestra solo opciones de usuarios que pertenecen al área/sector seleccionado
        function filtrarUsuariosPorSector(containerSelector, sectorElegido, usuarioASeleccionar = null) {
            const container = document.querySelector(containerSelector);
            if (!container) return;

            const selectUsuario = container.querySelector('.select-usuario');
            const options = Array.from(selectUsuario.options);

            options.forEach(option => {
                const sectorUsuario = option.dataset.sector;
                // Opción vacía ("Sin asignar") siempre visible
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                } else if (!sectorElegido || sectorUsuario === sectorElegido) {
                    option.hidden = false;
                    option.disabled = false;
                } else {
                    option.hidden = true;
                    option.disabled = true;
                }
            });

            // Si se mandó preseleccionar un ID de usuario (caso editar)
            if (usuarioASeleccionar !== null) {
                selectUsuario.value = usuarioASeleccionar;
            } else {
                // Si la selección actual fue ocultada, reiniciar a la primera opción ("Sin asignar")
                const selectedOption = selectUsuario.options[selectUsuario.selectedIndex];
                if (selectedOption && selectedOption.disabled) {
                    selectUsuario.value = "";
                }
            }
        }

        // ASIGNACIÓN DE EVENTOS DE VINCULACIÓN
        function inicializarVinculacion(containerSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) return;

            const selectSector = container.querySelector('.select-sector');
            const selectUsuario = container.querySelector('.select-usuario');

            // 1. Al cambiar Sector -> Mostrar solo los usuarios del sector/área elegida
            selectSector.addEventListener('change', function () {
                filtrarUsuariosPorSector(containerSelector, this.value);
            });

            // 2. Al cambiar Usuario -> Autoseleccionar su sector/área
            selectUsuario.addEventListener('change', function () {
                const optionSelected = this.options[this.selectedIndex];
                const sectorDelUsuario = optionSelected.dataset.sector;

                if (sectorDelUsuario) {
                    selectSector.value = sectorDelUsuario;
                    filtrarUsuariosPorSector(containerSelector, sectorDelUsuario, this.value);
                }
            });
        }

        // Inicialización de la vinculación
        inicializarVinculacion('#modalEditar');
        inicializarVinculacion('#modalNuevo');

        // Resetear modal Nuevo cuando se abra
        document.getElementById('modalNuevo').addEventListener('show.bs.modal', function () {
            document.getElementById('formNuevoEquipo').reset();
            filtrarUsuariosPorSector('#modalNuevo', '');
        });

        // Envío Editar
        document.getElementById('formUpdateInventario').onsubmit = function(e) {
            e.preventDefault();
            fetch('inventario_update_proceso.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.text()).then(data => {
                if(data.trim() === "success") {
                    location.reload();
                } else {
                    Swal.fire('Error', data, 'error');
                }
            });
        };

        // Envío Nuevo
        document.getElementById('formNuevoEquipo').onsubmit = function(e) {
            e.preventDefault();
            fetch('inventario_insert_proceso.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.text()).then(data => {
                if(data.trim() === "success") {
                    location.reload();
                } else {
                    Swal.fire('Error', data, 'error');
                }
            });
        };
    </script>
</body>
</html>