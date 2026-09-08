<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$rol = $_SESSION['rol'] ?? 'operativo';
$esPersonalTecnico = ($rol === 'administrador' || $rol === 'tecnico');

$idArticulo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$consultaArticulo = $conexion->prepare("SELECT a.*, c.nombre AS categoria_nombre, c.icono AS categoria_icono
                             FROM bc_articulos a
                             LEFT JOIN bc_categorias c ON a.categoria_id = c.id
                             WHERE a.id = ? LIMIT 1");
$consultaArticulo->bind_param("i", $idArticulo);
$consultaArticulo->execute();
$articulo = $consultaArticulo->get_result()->fetch_assoc();

if (!$articulo) {
    header("Location: bc_lista.php");
    exit();
}

// Un usuario operativo no puede ver artículos internos
if ($articulo['visibilidad'] === 'interno' && !$esPersonalTecnico) {
    header("Location: bc_lista.php");
    exit();
}

// Sumar una vista cada vez que se abre el artículo
$conexion->query("UPDATE bc_articulos SET vistas = vistas + 1 WHERE id = " . (int)$idArticulo);

// FOTO DE PERFIL
$usuarioActual = $_SESSION['usuario'] ?? '';
$consultaUsuario = $conexion->prepare("SELECT foto_perfil FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
$consultaUsuario->bind_param("ss", $usuarioActual, $usuarioActual);
$consultaUsuario->execute();
$datosUsuario = $consultaUsuario->get_result()->fetch_assoc();
$fotoGuardada = trim($datosUsuario['foto_perfil'] ?? '');
$avatarPorDefecto = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="%2394a3b8"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>';
$fotoPerfil = $avatarPorDefecto;
if (!empty($fotoGuardada)) {
    if (filter_var($fotoGuardada, FILTER_VALIDATE_URL)) {
        $fotoPerfil = $fotoGuardada;
    } elseif (file_exists('img/' . $fotoGuardada)) {
        $fotoPerfil = 'img/' . $fotoGuardada;
    } elseif (file_exists($fotoGuardada)) {
        $fotoPerfil = $fotoGuardada;
    }
}

$resultadoCategorias = $conexion->query("SELECT * FROM bc_categorias ORDER BY orden ASC");
$listaCategorias = [];
while ($categoria = $resultadoCategorias->fetch_assoc()) { $listaCategorias[] = $categoria; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeoAdmin | <?php echo htmlspecialchars($articulo['titulo']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --fondo-oscuro: #0f172a;
    --fondo-tarjeta: #1e293b;
    --color-acento: #38bdf8;
    --acento-suave: rgba(56, 189, 248, 0.1);
    --borde-vidrio: rgba(255, 255, 255, 0.08);
}
body {
    background-color: var(--fondo-oscuro);
    font-family: 'Inter', sans-serif;
    color: white;
    min-height: 100vh;
    margin: 0;
    background-image:
        linear-gradient(rgba(56, 189, 248, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(56, 189, 248, 0.02) 1px, transparent 1px);
    background-size: 50px 50px;
}
.barra-navegacion { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid var(--borde-vidrio); padding: 12px 30px; }
.enlace-navegacion { color: white; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.3s; padding: 8px 15px; border-radius: 10px; font-size: 0.95rem; }
.enlace-navegacion:hover { background: var(--acento-suave); color: var(--color-acento); }
.boton-salir { color: #f87171; border: 1px solid rgba(248, 113, 113, 0.2); }
.avatar-usuario { width: 38px; height: 38px; object-fit: cover; border-radius: 50%; border: 2px solid var(--color-acento); background-color: var(--fondo-tarjeta); }
.contenido-principal { padding: 40px; animation: aparecerPagina 0.6s ease-out; max-width: 900px; margin: 0 auto; }
@keyframes aparecerPagina { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.tarjeta-detalle { background: var(--fondo-tarjeta); border: 1px solid var(--borde-vidrio); border-radius: 25px; padding: 45px; }
.texto-articulo { white-space: pre-line; line-height: 1.7; color: #e2e8f0; font-size: 1.02rem; }
.boton-voto { border-radius: 14px; padding: 10px 20px; font-weight: 700; border: 1px solid #334155; background: transparent; color: white; transition: 0.3s; }
.boton-voto:hover { border-color: var(--color-acento); color: var(--color-acento); background: var(--acento-suave); }
</style>
</head>
<body>

<nav class="barra-navegacion d-flex justify-content-between align-items-center sticky-top">
    <div class="d-flex align-items-center gap-3">
        <img src="img/logo_neoadmin.png" alt="Logo" style="height: 40px;">
        <span style="font-family: 'Orbitron'; font-size: 1.2rem; color: var(--color-acento);">NEO ADMIN</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="dashboard.php" class="enlace-navegacion"><i class="bi bi-house-door-fill"></i> Inicio</a>
        <a href="tickets_lista.php" class="enlace-navegacion"><i class="bi bi-headset"></i> Mesa de Ayuda</a>
        <a href="bc_lista.php" class="enlace-navegacion" style="background: var(--acento-suave); color: var(--color-acento);"><i class="bi bi-journal-text"></i> Base de Conocimiento</a>
        <div class="vr mx-2 opacity-25" style="height: 20px; align-self: center;"></div>
        <div class="dropdown me-2">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="menuUsuarioCabecera" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Perfil" class="avatar-usuario me-2">
                <span class="fw-bold small d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg rounded-4" aria-labelledby="menuUsuarioCabecera">
                <li><a class="dropdown-item py-2" href="perfil.php"><i class="bi bi-person-fill me-2"></i> Mi Perfil</a></li>
                <li><hr class="dropdown-divider opacity-25"></li>
                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
            </ul>
        </div>
        <a href="logout.php" class="enlace-navegacion boton-salir d-none d-md-flex"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<main class="contenido-principal">
    <a href="bc_lista.php" class="enlace-navegacion mb-3 d-inline-flex" style="padding-left:0;"><i class="bi bi-arrow-left"></i> Volver a la Base de Conocimiento</a>

    <div class="tarjeta-detalle mt-3">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <span class="badge bg-secondary opacity-75"><i class="bi <?php echo htmlspecialchars($articulo['categoria_icono'] ?? 'bi-journal-text'); ?> me-1"></i> <?php echo htmlspecialchars($articulo['categoria_nombre'] ?? 'Sin categoría'); ?></span>
            <?php if ($articulo['visibilidad'] === 'interno'): ?>
            <span class="badge" style="background:#eab308;color:#0f172a;">Interno</span>
            <?php endif; ?>
        </div>
        <h1 class="fw-bold mb-4" style="font-family:'Orbitron'; font-size:1.8rem;"><?php echo htmlspecialchars($articulo['titulo']); ?></h1>

        <div class="texto-articulo"><?php echo htmlspecialchars($articulo['contenido']); ?></div>

        <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top border-white border-opacity-10 flex-wrap gap-3">
            <span class="small text-secondary"><i class="bi bi-eye"></i> <?php echo (int)$articulo['vistas']; ?> vistas</span>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-secondary me-2">¿Te sirvió este artículo?</span>
                <button class="boton-voto" onclick="votar('si')"><i class="bi bi-hand-thumbs-up"></i> Sí (<span id="contadorSi"><?php echo (int)$articulo['util_si']; ?></span>)</button>
                <button class="boton-voto" onclick="votar('no')"><i class="bi bi-hand-thumbs-down"></i> No (<span id="contadorNo"><?php echo (int)$articulo['util_no']; ?></span>)</button>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let yaVoto = false;
function votar(valor) {
    if (yaVoto) {
        Swal.fire('Gracias', 'Ya registramos tu voto en este artículo.', 'info');
        return;
    }
    const datosFormulario = new FormData();
    datosFormulario.append('id', <?php echo (int)$articulo['id']; ?>);
    datosFormulario.append('valor', valor);
    fetch('bc_voto_proceso.php', { method: 'POST', body: datosFormulario })
        .then(function (respuesta) { return respuesta.text(); })
        .then(function (datos) {
            if (datos.trim() === "success") {
                yaVoto = true;
                const contador = document.getElementById(valor === 'si' ? 'contadorSi' : 'contadorNo');
                contador.textContent = parseInt(contador.textContent) + 1;
            } else {
                Swal.fire('Error', datos, 'error');
            }
        });
}
</script>
</body>
</html>
