<?php
session_start();
require_once 'db.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$msg = "";
$error = "";

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

// Lógica para el Bot de Soporte
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_soporte_bot'])) {
    $mail_usuario = $_POST['email_soporte'];
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'tu_servidor_smtp'; 
        $mail->SMTPAuth = true;
        $mail->Username = 'tu_correo@dominio.com';
        $mail->Password = 'tu_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('sistema@it.com', 'IT System Support');
        $mail->addAddress('tu_correo_admin@dominio.com');
        $mail->isHTML(true);
        $mail->Subject = 'Solicitud de ayuda desde el Bot';
        $mail->Body    = "El usuario con correo <b>$mail_usuario</b> solicita asistencia técnica.";
        $mail->send();
        $msg = "Solicitud enviada al equipo técnico.";
    } catch (Exception $e) { $error = "No se pudo enviar el aviso: {$mail->ErrorInfo}"; }
}

// Lógica de creación de ticket
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_crear_ticket'])) {
    $asunto = $_POST['asunto'];
    $descripcion = $_POST['descripcion'];
    $prioridad = $_POST['prioridad'];
    $tipo = $_POST['tipo'];
    $origen = $_POST['origen'];
    $solicitante_id = $_SESSION['usuario_id'];
    
    // Variables para el archivo
    $contenido_archivo = null;
    $nombre_archivo = null;
    $tipo_archivo = null;

    // Verificar si se subió un archivo sin errores
    if (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] == 0) {
        $nombre_archivo = basename($_FILES['adjunto']['name']);
        $tipo_archivo = $_FILES['adjunto']['type'];
        $ruta_temporal = $_FILES['adjunto']['tmp_name'];
    }

    // Consulta SQL adaptada para BLOB y los metadatos del archivo
    $sql = "INSERT INTO tickets (asunto, descripcion, prioridad, tipo, origen, archivo_adjunto, archivo_nombre, archivo_tipo, solicitante_id, estado) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Abierto')";
    
    $stmt = $conexion->prepare($sql);

    // Nota: Para datos BLOB pasamos 'b' en el bind_param. 
    // Usamos NULL temporalmente en el bind para luego inyectar los datos binarios con send_long_data.
    $stmt->bind_param("sssssbssi", $asunto, $descripcion, $prioridad, $tipo, $origen, $contenido_archivo, $nombre_archivo, $tipo_archivo, $solicitante_id);

    // Si hay un archivo cargado, se envía en bloques de datos (evita desbordamiento de memoria)
    if ($nombre_archivo !== null) {
        // El parámetro BLOB es el sexto (índice 5 empezando desde 0)
        $stmt->send_long_data(5, file_get_contents($ruta_temporal));
    }

    if ($stmt->execute()) {
        $msg = "Ticket #".$conexion->insert_id." generado con éxito.";
    } else {
        $error = "Error al guardar en base de datos: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoAdmin | Crear Ticket</title>
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
            --text-gray: #94a3b8;
        }
        
        body { 
            background-color: var(--bg-dark); 
            font-family: 'Inter', sans-serif; 
            color: var(--text-main); 
            min-height: 100vh; 
            background-image: linear-gradient(rgba(56, 189, 248, 0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(56, 189, 248, 0.02) 1px, transparent 1px); 
            background-size: 50px 50px; 
        }

        .neo-navbar { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid var(--glass-border); padding: 12px 30px; }
        .nav-link-neo { color: white; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.3s; padding: 8px 15px; border-radius: 10px; font-size: 0.95rem; }
        .nav-link-neo:hover { background: var(--accent-soft); color: var(--accent); }

        .logout-btn {
            color: #f87171 !important;
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

        .form-container { max-width: 850px; margin: 40px auto; background: var(--card-dark); border: 1px solid var(--glass-border); border-radius: 25px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .form-control-neo { background: rgba(15, 23, 42, 0.8) !important; border: 1px solid var(--glass-border) !important; color: #ffffff !important; border-radius: 12px; padding: 12px; }
        .form-control-neo:focus { box-shadow: 0 0 0 2px var(--accent-soft); border-color: var(--accent) !important; }
        
        .termometro-baja { color: #10b981 !important; } 
        .termometro-media { color: #f59e0b !important; } 
        .termometro-alta { color: #ef4444 !important; } 

        .input-group-text-neo { background: rgba(15, 23, 42, 0.9); border: 1px solid var(--glass-border); border-radius: 12px 0 0 12px; transition: 0.3s; }
        .btn-submit { background: var(--accent); color: var(--bg-dark); font-weight: 800; border-radius: 12px; padding: 14px; border: none; text-transform: uppercase; transition: 0.3s; }
        .btn-submit:hover { background: #7dd3fc; transform: translateY(-2px); }
        .btn-cancel { background: transparent; color: #94a3b8; border: 1px solid #475569; border-radius: 12px; font-weight: 600; transition: 0.3s; }

        .support-bot-trigger { position: fixed; bottom: 30px; right: 30px; width: 65px; height: 65px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 1000; color: var(--bg-dark); font-size: 1.8rem; animation: radar-pulse 2s infinite; }
        @keyframes radar-pulse { 0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(56, 189, 248, 0); } 100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); } }

        #chatBot { position: fixed; bottom: 110px; right: 30px; width: 320px; background: white; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); display: none; z-index: 1001; overflow: hidden; color: #1e293b; border: 1px solid #ddd; }
        .bot-header { background: #1e293b; color: white; padding: 15px; font-weight: 800; display: flex; justify-content: space-between; align-items: center; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; }
    </style>
</head>
<body>

    <nav class="neo-navbar d-flex justify-content-between align-items-center sticky-top">
        <div class="d-flex align-items-center gap-3">
            <img src="img/logo_neoadmin.png" alt="Logo" style="width: 45px;">
            <span style="font-family: 'Orbitron'; font-size: 1.2rem; color: var(--accent);">NEO ADMIN</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="nav-link-neo"><i class="bi bi-house-door-fill"></i> Inicio</a>
            <a href="tickets_lista.php" class="nav-link-neo"><i class="bi bi-headset"></i> Mesa de Ayuda</a>
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

    <main class="container">
        <div class="form-container">
            <div class="text-center mb-4">
                <h2 style="font-family: 'Orbitron'; color: var(--accent);">NUEVO TICKET</h2>
                <p class="text-secondary small">Panel de apertura de incidencias y solicitudes técnicas.</p>
            </div>

            <?php if($msg): ?> <div class="alert alert-success bg-success bg-opacity-10 text-success border-0 rounded-4"><i class="bi bi-check-circle-fill me-2"></i><?php echo $msg; ?></div> <?php endif; ?>
            <?php if($error): ?> <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 rounded-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div> <?php endif; ?>

            <form action="tickets_crear.php" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">ASUNTO</label>
                            <input type="text" name="asunto" class="form-control form-control-neo" placeholder="¿Qué sucede?" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">DESCRIPCIÓN DETALLADA</label>
                            <textarea name="descripcion" class="form-control form-control-neo" rows="6" placeholder="Describe el error paso a paso..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">ADJUNTAR ARCHIVO (Imagen o PDF)</label>
                            <input type="file" name="adjunto" class="form-control form-control-neo" accept=".jpg,.png,.pdf">
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">TIPO DE SOLICITUD</label>
                            <select name="tipo" class="form-select form-control-neo" required>
                                <option value="Incidencia">Incidencia (Algo falló)</option>
                                <option value="Solicitud">Solicitud (Nuevo requerimiento)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">ORIGEN / DEPARTAMENTO</label>
                            <select name="origen" class="form-select form-control-neo" required>
                                <option value="Soporte">Soporte</option>
                                <option value="Administración">Administración</option>
                                <option value="Finanzas">Finanzas</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Operaciones">Operaciones</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">PRIORIDAD</label>
                            <div class="input-group">
                                <span class="input-group-text input-group-text-neo" id="termometro-icon">
                                    <i class="bi bi-thermometer-half termometro-media"></i>
                                </span>
                                <select name="prioridad" id="prioridadSelect" class="form-select form-control-neo" required onchange="actualizarColorPrioridad()">
                                    <option value="Baja">Baja</option>
                                    <option value="Media" selected>Media</option>
                                    <option value="Alta">Alta (Crítica)</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" name="btn_crear_ticket" class="btn-submit w-100">GENERAR</button>
                            <a href="tickets_lista.php" class="btn btn-cancel w-100 d-flex align-items-center justify-content-center">CANCELAR</a>
                        </div>
                    </div>
                </div>
            </form>
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
            <form action="tickets_crear.php" method="POST">
                <input type="email" name="email_soporte" class="form-control form-control-sm mb-3" placeholder="Correo corporativo" required style="background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1;">
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

        function actualizarColorPrioridad() {
            const select = document.getElementById('prioridadSelect');
            const icon = document.querySelector('#termometro-icon i');
            const valor = select.value;

            icon.classList.remove('termometro-baja', 'termometro-media', 'termometro-alta');

            if (valor === 'Baja') {
                icon.classList.add('termometro-baja');
            } else if (valor === 'Media') {
                icon.classList.add('termometro-media');
            } else if (valor === 'Alta') {
                icon.classList.add('termometro-alta');
            }
        }

        window.onload = actualizarColorPrioridad;
    </script>
</body>
</html>