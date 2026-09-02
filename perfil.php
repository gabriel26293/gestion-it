<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario'])) { 
    header("Location: index.php"); 
    exit(); 
}

$usuario_sesion = $_SESSION['usuario'];
$mensaje = '';
$tipo_alerta = '';

// Notificación de clave expirada si viene redirigido
if (isset($_GET['expirado']) && $_GET['expirado'] == '1') {
    $mensaje = "Su contraseña ha caducado. Por motivos de seguridad, debe definir una nueva para continuar.";
    $tipo_alerta = "warning";
}

// 1. Cargar datos actualizados del usuario
$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $usuario_sesion, $usuario_sesion);
$stmt->execute();
$res = $stmt->get_result();
$user_data = $res->fetch_assoc();

if (!$user_data) {
    die("Usuario no encontrado.");
}

$es_admin = (isset($user_data['rol']) && strtolower($user_data['rol']) === 'admin') || (isset($_SESSION['rol']) && strtolower($_SESSION['rol']) === 'admin');

// 2. Procesar el formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_actualizar'])) {
    
    // Cargar valores actuales o modificados según el rol
    if ($es_admin) {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = trim($_POST['rol'] ?? 'usuario');
    } else {
        $nombre_completo = $user_data['nombre_completo'];
        $email = $user_data['email'];
        $rol = $user_data['rol'] ?? 'usuario';
    }

    $nueva_clave = $_POST['password'] ?? '';
    $confirmar_clave = $_POST['confirm_password'] ?? '';
    $nombre_foto = $user_data['foto_perfil'] ?? '';

    // Validar coincidencia de contraseña si ingresó una nueva
    if (!empty($nueva_clave) || !empty($confirmar_clave)) {
        if ($nueva_clave !== $confirmar_clave) {
            $mensaje = "Las contraseñas no coinciden. Por favor, verifica e intenta nuevamente.";
            $tipo_alerta = "danger";
        }
    }

    // Manejo de la subida de foto de perfil a la carpeta img/
    if (empty($mensaje) && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($ext, $extensiones_permitidas)) {
            if (!file_exists('img')) {
                mkdir('img', 0777, true);
            }
            $nuevo_nombre_foto = 'user_' . time() . '_' . uniqid() . '.' . $ext;
            $destino = 'img/' . $nuevo_nombre_foto;

            if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $destino)) {
                $nombre_foto = $nuevo_nombre_foto;
            }
        } else {
            $mensaje = "Formato de imagen no permitido (solo JPG, PNG, WEBP, GIF).";
            $tipo_alerta = "danger";
        }
    }

    if (empty($mensaje)) {
        // Actualizar contraseña si se ingresó una nueva
        if (!empty($nueva_clave)) {
            $clave_hash = password_hash($nueva_clave, PASSWORD_BCRYPT);
            $fecha_actual = date('Y-m-d H:i:s');

            $sql = "UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, foto_perfil = ?, password = ?, ultima_modificacion_pass = ? WHERE id = ?";
            $stmt_up = $conexion->prepare($sql);
            $stmt_up->bind_param("ssssssi", $nombre_completo, $email, $rol, $nombre_foto, $clave_hash, $fecha_actual, $user_data['id']);
        } else {
            $sql = "UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, foto_perfil = ? WHERE id = ?";
            $stmt_up = $conexion->prepare($sql);
            $stmt_up->bind_param("ssssi", $nombre_completo, $email, $rol, $nombre_foto, $user_data['id']);
        }

        if ($stmt_up->execute()) {
            $_SESSION['nombre_completo'] = $nombre_completo;
            
            // Si cambió la clave, limpiar flags de caducidad en sesión
            if (!empty($nueva_clave)) {
                $_SESSION['forzar_cambio_clave'] = false;
                $_SESSION['mostrar_alerta_clave'] = false;
            }

            $mensaje = "Perfil actualizado con éxito.";
            $tipo_alerta = "success";

            // Recargar datos actualizados
            $stmt->execute();
            $user_data = $stmt->get_result()->fetch_assoc();
        } else {
            $mensaje = "Error al actualizar los datos en la base de datos.";
            $tipo_alerta = "danger";
        }
    }
}

// 3. Procesar foto de perfil para visualización
$foto_db = trim($user_data['foto_perfil'] ?? '');
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

// 4. Calcular días restantes para la caducidad de clave (30 días)
$dias_restantes = 30;
$clave_caducada = false;

if (!empty($user_data['ultima_modificacion_pass'])) {
    $fecha_pass = new DateTime($user_data['ultima_modificacion_pass']);
    $fecha_actual = new DateTime();
    $diferencia = $fecha_actual->diff($fecha_pass);
    $dias_transcurridos = $diferencia->days;
    $dias_restantes = max(0, 30 - $dias_transcurridos);
    
    if ($dias_transcurridos >= 30) {
        $clave_caducada = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoAdmin | Mi Perfil</title>
    
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

        .logo-img { width: 45px; height: auto; display: block; }

        .user-avatar {
            width: 38px; height: 38px;
            object-fit: cover; border-radius: 50%;
            border: 2px solid var(--accent); background-color: var(--card-dark);
        }

        .profile-avatar-lg {
            width: 120px; height: 120px;
            object-fit: cover; border-radius: 50%;
            border: 3px solid var(--accent);
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.3);
        }

        .card-cyber {
            background: var(--card-dark);
            border: 1px solid var(--glass-border);
            border-radius: 25px; padding: 35px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .form-control, .form-select {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            border-radius: 12px; padding: 12px 15px;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(15, 23, 42, 0.8);
            border-color: var(--accent);
            color: var(--text-main);
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.2);
        }

        .form-control:disabled, .form-select:disabled {
            background-color: rgba(15, 23, 42, 0.3);
            color: var(--text-muted);
            border-color: rgba(255, 255, 255, 0.03);
        }

        /* Estilos Cyberpunk para el módulo de contraseñas */
        .cyber-input-box {
            background: rgba(2, 6, 23, 0.8);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 12px;
            padding: 4px 12px;
            transition: all 0.3s ease;
        }

        .cyber-input-box:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 12px rgba(56, 189, 248, 0.3);
        }

        .cyber-input-box .input-group-text {
            background: transparent;
            border: none;
            color: var(--accent);
        }

        .cyber-input-box .form-control {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .cyber-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
            display: block;
        }

        .btn-cyber-accent {
            background: var(--accent); color: var(--bg-dark);
            font-weight: 700; border-radius: 12px;
            padding: 12px 25px; border: none; transition: 0.3s;
        }

        .btn-cyber-accent:hover {
            background: #7dd3fc; transform: translateY(-2px);
        }

        .pass-badge {
            padding: 10px 18px; border-radius: 12px;
            font-size: 0.85rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
        }

        .pass-ok { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .pass-warn { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
</head>
<body>

    <nav class="neo-navbar d-flex justify-content-between align-items-center sticky-top">
        <div class="d-flex align-items-center gap-3">
            <img src="img/logo_neoadmin.png" alt="Logo" class="logo-img">
            <span style="font-family: 'Orbitron'; font-size: 1.2rem; letter-spacing: 1px; color: var(--accent);">NEO ADMIN</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="nav-link-neo">
                <i class="bi bi-house-door-fill"></i> Inicio
            </a>
            <a href="tickets_lista.php" class="nav-link-neo">
                <i class="bi bi-headset"></i> Mesa de Ayuda
            </a>
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
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show rounded-4 mb-4" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card-cyber">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-25">
                        <h3 class="m-0" style="font-family: 'Orbitron'; font-size: 1.5rem;">PERFIL DE USUARIO</h3>
                        <span class="badge bg-outline-primary border border-info text-info px-3 py-2 rounded-3">
                            <i class="bi bi-shield-lock me-1"></i> <?php echo strtoupper(htmlspecialchars($user_data['rol'] ?? 'USUARIO')); ?>
                        </span>
                    </div>

                    <!-- Estado de expiración de la clave -->
                    <div class="mb-4">
                        <?php if ($clave_caducada): ?>
                            <div class="pass-badge pass-warn w-100">
                                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                                <span>Su contraseña ha caducado (superó los 30 días). Actualícela para mantener su cuenta segura.</span>
                            </div>
                        <?php else: ?>
                            <div class="pass-badge pass-ok w-100">
                                <i class="bi bi-check-circle-fill fs-5"></i>
                                <span>Su contraseña es válida. Le quedan <strong><?php echo $dias_restantes; ?> días</strong> antes de la próxima renovación mensual.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form action="perfil.php" method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Avatar" class="profile-avatar-lg mb-3">
                            <div class="mx-auto" style="max-width: 350px;">
                                <label for="foto_perfil" class="form-label small text-muted">Cambiar Foto de Perfil</label>
                                <input type="file" class="form-control form-control-sm" id="foto_perfil" name="foto_perfil" accept="image/*">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">USUARIO</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['usuario']); ?>" disabled>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">NOMBRE COMPLETO</label>
                                <input type="text" name="nombre_completo" class="form-control" value="<?php echo htmlspecialchars($user_data['nombre_completo']); ?>" <?php echo $es_admin ? '' : 'disabled'; ?>>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">CORREO ELECTRÓNICO</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" <?php echo $es_admin ? '' : 'disabled'; ?>>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">ROL DE SISTEMA</label>
                                <?php if ($es_admin): ?>
                                    <select name="rol" class="form-select">
                                        <option value="usuario" <?php echo ($user_data['rol'] === 'usuario') ? 'selected' : ''; ?>>Usuario</option>
                                        <option value="admin" <?php echo ($user_data['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                    </select>
                                <?php else: ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['rol'] ?? 'usuario'); ?>" disabled>
                                <?php endif; ?>
                            </div>

                            <!-- Campos de Contraseña con Estilo Neon e Iconos -->
                            <div class="col-12 mt-4">
                                <div class="p-3 border rounded-4" style="border-color: var(--glass-border) !important; background: rgba(0,0,0,0.25);">
                                    <span class="text-info small fw-bold d-block mb-3"><i class="bi bi-shield-lock me-1"></i> ACTUALIZAR CONTRASEÑA</span>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="cyber-label">NUEVA CONTRASEÑA</label>
                                            <div class="input-group cyber-input-box">
                                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                                <input type="password" id="pass1" name="password" class="form-control text-white" placeholder="••••••••" autocomplete="new-password">
                                                <button class="btn btn-link text-white-50 p-0 me-2" type="button" onclick="toggleVisibility('pass1', 'eye1')">
                                                    <i class="bi bi-eye-slash-fill" id="eye1"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="cyber-label">CONFIRMAR CONTRASEÑA</label>
                                            <div class="input-group cyber-input-box">
                                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                                <input type="password" id="pass2" name="confirm_password" class="form-control text-white" placeholder="••••••••" autocomplete="new-password">
                                                <button class="btn btn-link text-white-50 p-0 me-2" type="button" onclick="toggleVisibility('pass2', 'eye2')">
                                                    <i class="bi bi-eye-slash-fill" id="eye2"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <small class="text-muted mt-2 d-block">Dejar en blanco para mantener la contraseña actual. La contraseña se debe renovar cada 30 días.</small>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-4 pt-3 border-top border-secondary border-opacity-25">
                            <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 px-4">Cancelar</a>
                            <button type="submit" name="btn_actualizar" class="btn-cyber-accent">
                                <i class="bi bi-floppy me-2"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("bi-eye-slash-fill");
                icon.classList.add("bi-eye-fill");
            } else {
                input.type = "password";
                icon.classList.remove("bi-eye-fill");
                icon.classList.add("bi-eye-slash-fill");
            }
        }
    </script>
</body>
</html>