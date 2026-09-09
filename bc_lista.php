<?php
session_start();
require_once 'db.php';

/**
 * CONTROL DE ACCESO
 */
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$rol = $_SESSION['rol'] ?? 'operativo';
$esPersonalTecnico = ($rol === 'administrador' || $rol === 'tecnico');

// FOTO DE PERFIL
$usuarioActual = $_SESSION['usuario'] ?? '';
$consultaUsuario = $conexion->prepare("SELECT foto_perfil FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
$consultaUsuario->bind_param("ss", $usuarioActual, $usuarioActual);
$consultaUsuario->execute();
$resultadoUsuario = $consultaUsuario->get_result();
$datosUsuario = $resultadoUsuario->fetch_assoc();
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

// ARTÍCULOS: los usuarios operativos no ven contenido 'interno'
if ($esPersonalTecnico) {
    $consultaArticulos = "SELECT a.*, c.nombre AS categoria_nombre, c.icono AS categoria_icono
              FROM bc_articulos a
              LEFT JOIN bc_categorias c ON a.categoria_id = c.id
              ORDER BY a.creado_en DESC";
} else {
    $consultaArticulos = "SELECT a.*, c.nombre AS categoria_nombre, c.icono AS categoria_icono
              FROM bc_articulos a
              LEFT JOIN bc_categorias c ON a.categoria_id = c.id
              WHERE a.visibilidad = 'publico'
              ORDER BY a.creado_en DESC";
}
$resultadoArticulos = $conexion->query($consultaArticulos);

$resultadoCategorias = $conexion->query("SELECT * FROM bc_categorias ORDER BY orden ASC");
$listaCategorias = [];
while ($filaCategoria = $resultadoCategorias->fetch_assoc()) {
    $listaCategorias[] = $filaCategoria;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeoAdmin | Base de Conocimiento</title>
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
.contenido-principal { padding: 40px; animation: aparecerPagina 0.6s ease-out; }
@keyframes aparecerPagina { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.contenedor-busqueda { background: var(--fondo-tarjeta); border: 1px solid var(--borde-vidrio); border-radius: 15px; padding: 20px; margin-bottom: 30px; }
.campo-formulario { background: rgba(15, 23, 42, 0.5); border: 1px solid var(--borde-vidrio); color: white; border-radius: 10px; }
.campo-formulario:focus { background: rgba(15, 23, 42, 0.8); border-color: var(--color-acento); color: white; box-shadow: none; }
.tarjeta-articulo { background: var(--fondo-tarjeta); border: 1px solid var(--borde-vidrio); border-radius: 22px; padding: 25px; height: 100%; transition: 0.4s; position: relative; overflow: hidden; text-decoration: none; color: white; display: block; }
.tarjeta-articulo:hover { transform: translateY(-5px); border-color: var(--color-acento); color: white; }
.contenedor-icono { width: 48px; height: 48px; background: var(--acento-suave); color: var(--color-acento); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 15px; }
.etiqueta-interno { position: absolute; top: 20px; right: -2px; font-size: 0.6rem; padding: 4px 12px; border-radius: 4px 0 0 4px; font-weight: 800; text-transform: uppercase; background: #eab308; color: #0f172a; letter-spacing: 0.5px; }
.modal-content { background: var(--fondo-tarjeta); border: 1px solid var(--color-acento); color: white; border-radius: 20px; }
.modal-header { border-bottom: 1px solid var(--borde-vidrio); }
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

<main class="contenido-principal container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h1 class="fw-bold mb-1" style="font-family: 'Orbitron';">BASE DE CONOCIMIENTO</h1>
        <?php if ($esPersonalTecnico): ?>
        <button type="button" class="btn btn-info fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevoArticulo">
            <i class="bi bi-plus-circle"></i> Nuevo Artículo
        </button>
        <?php endif; ?>
    </div>

    <div class="contenedor-busqueda">
        <div class="row g-3">
            <div class="col-md-9">
                <input type="text" id="entradaBusqueda" class="form-control campo-formulario" placeholder="Buscar por título o contenido...">
            </div>
            <div class="col-md-3">
                <select id="filtroCategoria" class="form-select campo-formulario">
                    <option value="todas">Todas las categorías</option>
                    <?php foreach ($listaCategorias as $categoria): ?>
                    <option value="<?php echo htmlspecialchars($categoria['nombre']); ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="row g-4" id="grillaArticulos">
        <?php while ($articulo = $resultadoArticulos->fetch_assoc()):
            $textoBusqueda = strtolower($articulo['titulo'] . ' ' . strip_tags($articulo['contenido']));
        ?>
        <div class="col-md-6 col-lg-4 elemento-articulo"
             data-categoria="<?php echo htmlspecialchars($articulo['categoria_nombre'] ?? ''); ?>"
             data-busqueda="<?php echo htmlspecialchars($textoBusqueda); ?>">
            <a href="bc_articulo.php?id=<?php echo $articulo['id']; ?>" class="tarjeta-articulo">
                <?php if ($articulo['visibilidad'] === 'interno'): ?>
                <span class="etiqueta-interno">Interno</span>
                <?php endif; ?>
                <div class="contenedor-icono"><i class="bi <?php echo htmlspecialchars($articulo['categoria_icono'] ?? 'bi-journal-text'); ?>"></i></div>
                <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($articulo['titulo']); ?></h5>
                <p class="small text-secondary mb-3"><?php echo htmlspecialchars(mb_substr(strip_tags($articulo['contenido']), 0, 90)); ?>...</p>
                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-white border-opacity-10">
                    <span class="badge bg-secondary opacity-50"><?php echo htmlspecialchars($articulo['categoria_nombre'] ?? 'Sin categoría'); ?></span>
                    <div class="d-flex align-items-center gap-3">
                        <span class="small text-secondary"><i class="bi bi-eye"></i> <?php echo (int)$articulo['vistas']; ?></span>
                        <?php if ($esPersonalTecnico):
                            $datosArticuloJson = htmlspecialchars(json_encode([
                                'id' => $articulo['id'],
                                'titulo' => $articulo['titulo'],
                                'contenido' => $articulo['contenido'],
                                'categoria_id' => $articulo['categoria_id'],
                                'visibilidad' => $articulo['visibilidad'],
                            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                        <button type="button" class="btn btn-sm btn-outline-info border-0 p-0 boton-editar-articulo" data-articulo="<?php echo $datosArticuloJson; ?>">
                            <i class="bi bi-pencil-square fs-6"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endwhile; ?>
    </div>
</main>

<?php if ($esPersonalTecnico): ?>
<!-- MODAL NUEVO ARTÍCULO -->
<div class="modal fade" id="modalNuevoArticulo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" style="font-family: 'Orbitron';">NUEVO ARTÍCULO</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formularioNuevoArticulo">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="small text-secondary mb-1">TÍTULO</label>
                            <input type="text" name="titulo" class="form-control campo-formulario" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-secondary mb-1">CATEGORÍA</label>
                            <select name="categoria_id" class="form-select campo-formulario" required>
                                <option value="" disabled selected>Seleccione</option>
                                <?php foreach ($listaCategorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="small text-secondary mb-1">CONTENIDO</label>
                            <textarea name="contenido" class="form-control campo-formulario" rows="8" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-secondary mb-1">VISIBILIDAD</label>
                            <select name="visibilidad" class="form-select campo-formulario">
                                <option value="publico">Público (todos los usuarios)</option>
                                <option value="interno">Interno (solo admin/técnico)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-info w-100 fw-bold py-2" style="font-family: 'Orbitron'; letter-spacing: 1px;">GUARDAR ARTÍCULO</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR ARTÍCULO -->
<div class="modal fade" id="modalEditarArticulo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" style="font-family: 'Orbitron';">EDITAR ARTÍCULO</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formularioEditarArticulo">
                    <input type="hidden" name="id" id="editar_id">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="small text-secondary mb-1">TÍTULO</label>
                            <input type="text" name="titulo" id="editar_titulo" class="form-control campo-formulario" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-secondary mb-1">CATEGORÍA</label>
                            <select name="categoria_id" id="editar_categoria" class="form-select campo-formulario" required>
                                <?php foreach ($listaCategorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="small text-secondary mb-1">CONTENIDO</label>
                            <textarea name="contenido" id="editar_contenido" class="form-control campo-formulario" rows="8" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-secondary mb-1">VISIBILIDAD</label>
                            <select name="visibilidad" id="editar_visibilidad" class="form-select campo-formulario">
                                <option value="publico">Público (todos los usuarios)</option>
                                <option value="interno">Interno (solo admin/técnico)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-info w-100 fw-bold py-2" style="font-family: 'Orbitron'; letter-spacing: 1px;">GUARDAR CAMBIOS</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filtrar() {
    const texto = document.getElementById('entradaBusqueda').value.toLowerCase();
    const categoria = document.getElementById('filtroCategoria').value;
    document.querySelectorAll('.elemento-articulo').forEach(function (elemento) {
        const coincide = elemento.dataset.busqueda.includes(texto) && (categoria === 'todas' || elemento.dataset.categoria === categoria);
        elemento.style.display = coincide ? 'block' : 'none';
    });
}
document.getElementById('entradaBusqueda').addEventListener('input', filtrar);
document.getElementById('filtroCategoria').addEventListener('change', filtrar);

<?php if ($esPersonalTecnico): ?>
document.getElementById('formularioNuevoArticulo').onsubmit = function (evento) {
    evento.preventDefault();
    fetch('bc_insertar_proceso.php', { method: 'POST', body: new FormData(this) })
        .then(function (respuesta) { return respuesta.text(); })
        .then(function (datos) {
            if (datos.trim() === "success") {
                location.reload();
            } else {
                Swal.fire('Error', datos, 'error');
            }
        });
};

const modalEditarArticulo = new bootstrap.Modal(document.getElementById('modalEditarArticulo'));

function abrirModalEditarArticulo(datosArticulo) {
    document.getElementById('editar_id').value = datosArticulo.id;
    document.getElementById('editar_titulo').value = datosArticulo.titulo;
    document.getElementById('editar_contenido').value = datosArticulo.contenido;
    document.getElementById('editar_categoria').value = datosArticulo.categoria_id;
    document.getElementById('editar_visibilidad').value = datosArticulo.visibilidad;
    modalEditarArticulo.show();
}

document.querySelectorAll('.boton-editar-articulo').forEach(function (boton) {
    boton.addEventListener('click', function (evento) {
        evento.preventDefault();
        evento.stopPropagation();
        const datosArticulo = JSON.parse(this.dataset.articulo);
        abrirModalEditarArticulo(datosArticulo);
    });
});

document.getElementById('formularioEditarArticulo').onsubmit = function (evento) {
    evento.preventDefault();
    fetch('bc_actualizar_proceso.php', { method: 'POST', body: new FormData(this) })
        .then(function (respuesta) { return respuesta.text(); })
        .then(function (datos) {
            if (datos.trim() === "success") {
                location.reload();
            } else {
                Swal.fire('Error', datos, 'error');
            }
        });
};
<?php endif; ?>
</script>
</body>
</html>
