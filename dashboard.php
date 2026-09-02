<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario'])) { 
    header("Location: index.php"); 
    exit(); 
}

// Redirección forzada si la clave ya expiró (30 días cumplidos)
if (isset($_SESSION['forzar_cambio_clave']) && $_SESSION['forzar_cambio_clave'] === true) {
    header("Location: perfil.php?expirado=1");
    exit();
}

$nombre = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'];
$usuario = $_SESSION['usuario'];

// Obtener la foto actualizada directamente de la Base de Datos
$stmt = $conexion->prepare("SELECT foto_perfil FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $usuario, $usuario);
$stmt->execute();
$res = $stmt->get_result();
$user_db = $res->fetch_assoc();

$foto_db = trim($user_db['foto_perfil'] ?? '');

// SVG inline genérico de respaldo
$avatar_default = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="%2394a3b8"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>';

$foto_perfil = $avatar_default;

// Verificación de la ruta considerando que las imágenes están en img/
if (!empty($foto_db)) {
    if (filter_var($foto_db, FILTER_VALIDATE_URL)) {
        $foto_perfil = $foto_db;
    } elseif (file_exists('img/' . $foto_db)) {
        $foto_perfil = 'img/' . $foto_db;
    } elseif (file_exists($foto_db)) {
        $foto_perfil = $foto_db;
    }
}

// Variables de control de alertas preventivas de contraseña
$mostrar_alerta_clave = $_SESSION['mostrar_alerta_clave'] ?? false;
$dias_restantes_clave = $_SESSION['dias_restantes_clave'] ?? 30;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoAdmin | Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-dark: #1e293b;
            --accent: #38bdf8;
            --accent-soft: rgba(56, 189, 248, 0.1);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        body {
            background-color: var(--bg-dark);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
            background-image: 
                linear-gradient(rgba(56, 189, 248, 0.02) 1px, transparent 1px), 
                linear-gradient(90deg, rgba(56, 189, 248, 0.02) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        /* Navbar Superior */
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

        .nav-link-neo:hover {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .logout-btn {
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.2);
        }

        .logo-img {
            width: 45px;
            height: auto;
            object-fit: contain;
            display: block;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--accent);
            background-color: var(--card-dark);
        }

        /* Contenido Principal */
        .main-content {
            padding: 40px;
            animation: fadeInPage 0.6s ease-out;
        }

        @keyframes fadeInPage {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Banner Hero */
        .hero-banner { 
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid var(--glass-border);
            border-radius: 30px; 
            padding: 60px; 
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            margin-bottom: 40px;
        }

        .support-character {
            position: absolute;
            right: 40px;
            bottom: -10px;
            width: 280px;
            height: 280px;
            background: url('https://cdn-icons-png.flaticon.com/512/4333/4333609.png'); 
            background-size: contain;
            background-repeat: no-repeat;
            filter: drop-shadow(0 0 15px var(--accent));
            animation: float 5s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(2deg); }
        }

        /* Tarjetas Estilo Cyber */
        .card-cyber {
            background: var(--card-dark);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 35px;
            transition: all 0.4s ease;
            height: 100%;
        }

        .card-cyber:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .icon-box {
            width: 65px; height: 65px;
            background: var(--accent-soft);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 25px;
            color: var(--accent);
            font-size: 1.8rem;
            border: 1px solid rgba(56, 189, 248, 0.2);
        }

        .btn-cyber {
            border-radius: 14px; padding: 12px;
            font-weight: 700; text-transform: uppercase;
            letter-spacing: 1px; font-size: 0.8rem;
            transition: 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-accent { background: var(--accent); color: var(--bg-dark); border: none; }
        .btn-accent:hover { background: #7dd3fc; transform: scale(1.02); }

        .btn-outline-custom { background: transparent; border: 1px solid #334155; color: var(--text-main); }
        .btn-outline-custom:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }

        /* BOT DE SOPORTE */
        .support-bot-trigger {
            position: fixed; bottom: 30px; right: 30px;
            width: 70px; height: 70px;
            background: #38bdf8;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 4px solid rgba(15, 23, 42, 0.1);
            animation: pulse-shadow 2s infinite;
        }

        @keyframes pulse-shadow {
            0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.6); }
            70% { box-shadow: 0 0 0 15px rgba(56, 189, 248, 0); }
            100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
        }

        .support-bot-trigger:hover {
            transform: scale(1.2);
            box-shadow: 0 0 30px rgba(56, 189, 248, 0.8);
        }

        .support-bot-trigger i {
            color: #0f172a;
            font-size: 2rem;
        }

        #chatBot {
            position: fixed; bottom: 110px; right: 30px;
            width: 320px; background: white; border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5); display: none;
            z-index: 1001; overflow: hidden; color: #1e293b;
        }

        .bot-header { 
            background: var(--accent); 
            padding: 15px; 
            color: var(--bg-dark); 
            font-weight: 800; 
            display: flex; 
            justify-content: space-between; 
        }

        @media (max-width: 992px) {
            .support-character { display: none; }
        }
    </style>
</head>
<body>

    <nav class="neo-navbar d-flex justify-content-between align-items-center sticky-top">
        <div class="d-flex align-items-center gap-3">
            <img src="img/logo_neoadmin.png" alt="Logo" class="logo-img">
            <span style="font-family: 'Orbitron'; font-size: 1.2rem; letter-spacing: 1px; color: var(--accent);">NEO ADMIN</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="nav-link-neo" style="background: var(--accent-soft); color: var(--accent);">
                <i class="bi bi-house-door-fill"></i> Inicio
            </a>
            <a href="tickets_lista.php" class="nav-link-neo">
                <i class="bi bi-headset"></i> Mesa de Ayuda
            </a>
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

    <!-- TOAST CONTRASEÑA PRÓXIMA A EXPIRAR (7 días o menos) -->
    <?php if ($mostrar_alerta_clave): ?>
    <div class="toast-container position-fixed bottom-0 start-0 p-4" style="z-index: 1100;">
        <div id="toastClaveExpiracion" class="toast align-items-center text-white bg-warning border-0 show shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border-radius: 18px; background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%) !important;">
            <div class="d-flex p-2">
                <div class="toast-body d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill fs-2 text-dark"></i>
                    <div>
                        <strong class="d-block text-dark fw-bold" style="font-size: 0.95rem;">¡Advertencia de Seguridad!</strong>
                        <span class="text-dark small">Tu contraseña expirará en <strong><?php echo $dias_restantes_clave; ?> día(s)</strong>. Te recomendamos actualizarla hoy.</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="px-3 pb-3 pt-0 text-end">
                <a href="perfil.php" class="btn btn-sm btn-dark fw-bold px-3" style="border-radius: 10px;">Cambiar Ahora</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <main class="main-content container">
        
        <!-- BANNER DE ADVERTENCIA PREVENTIVA EN EL PANEL -->
        <?php if ($mostrar_alerta_clave): ?>
        <div class="alert alert-warning border-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-between p-3 mb-4 rounded-4 shadow-sm" style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3);">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-shield-exclamation fs-3"></i>
                <div>
                    <h6 class="fw-bold mb-0">Atención: Expiración de Clave Programada</h6>
                    <small class="opacity-75">Tu clave de acceso vencerá en <?php echo $dias_restantes_clave; ?> día(s) según la directiva de seguridad de 30 días.</small>
                </div>
            </div>
            <a href="perfil.php" class="btn btn-warning btn-sm text-dark fw-bold px-3 py-2 ms-3" style="border-radius: 10px; whitespace: nowrap;">
                Actualizar Clave
            </a>
        </div>
        <?php endif; ?>

        <div class="hero-banner">
            <div class="hero-text">
                <div class="status-badge" style="background: var(--accent-soft); color: var(--accent); padding: 8px 16px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; margin-bottom: 20px; display: inline-flex; align-items: center;">
                    <i class="bi bi-shield-check me-2"></i> Sistema Protegido
                </div>
                <h1 style="font-family: 'Orbitron'; font-weight: 700; font-size: 2.5rem; margin-bottom: 15px;">Bienvenido, <?php echo htmlspecialchars($nombre); ?></h1>
                <p class="opacity-50 mt-3" style="max-width: 450px;">
                    Consola central activa. Gestione el inventario de hardware y supervise los tickets de soporte desde un solo lugar.
                </p>
            </div>
            <div class="support-character"></div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card-cyber">
                    <div class="icon-box"><i class="bi bi-pc-display-horizontal"></i></div>
                    <h3>INVENTARIO</h3>
                    <p class="opacity-50 mb-4">Control total de activos: servidores, laptops y periféricos asignados a la red.</p>
                    <a href="inventario_lista.php" class="btn-cyber btn-outline-custom">Ver Activos IT</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-cyber">
                    <div class="icon-box"><i class="bi bi-ticket-perforated-fill"></i></div>
                    <h3>NUEVO TICKET</h3>
                    <p class="opacity-50 mb-4">Inicie un reporte de incidencia técnica. El sistema priorizará la resolución automáticamente.</p>
                    <a href="tickets_crear.php" class="btn-cyber btn-accent">Abrir Ticket</a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-cyber">
                    <div class="icon-box" style="background: rgba(148, 163, 184, 0.05); color: var(--text-muted); border: 1px solid rgba(255,255,255,0.05);">
                        <i class="bi bi-activity"></i>
                    </div>
                    <h3>SOLICITUDES</h3>
                    <p class="opacity-50 mb-4">Siga el progreso de sus requerimientos y revise el feedback del equipo técnico.</p>
                    <a href="tickets_lista.php" class="btn-cyber btn-outline-custom">Historial</a>
                </div>
            </div>
        </div>
    </main>

    <div class="support-bot-trigger" onclick="toggleBot()">
        <i class="bi bi-headset"></i>
    </div>

    <div id="chatBot">
        <div class="bot-header">
            <span>TERMINAL DE AYUDA</span>
            <i class="bi bi-x-lg" style="cursor:pointer" onclick="toggleBot()"></i>
        </div>
        <div class="p-4">
            <p class="small mb-4">¿En que podemos ayudarte? Notifique al administrador de guardia.</p>
            <form action="index.php" method="POST">
                <input type="email" name="email_soporte" class="form-control form-control-sm mb-3" placeholder="Correo corporativo" required>
                <button type="submit" name="btn_soporte_bot" class="btn btn-dark w-100 fw-bold">ENVIAR REPORTE</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleBot() {
            const bot = document.getElementById('chatBot');
            bot.style.display = (bot.style.display === 'block') ? 'none' : 'block';
        }

        document.addEventListener("DOMContentLoaded", function() {
            var toastEl = document.getElementById('toastClaveExpiracion');
            if (toastEl) {
                var toast = new bootstrap.Toast(toastEl, { delay: 10000 });
                toast.show();
            }
        });
    </script>
</body>
</html>