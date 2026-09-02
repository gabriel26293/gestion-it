<?php
session_start();
require_once 'db.php'; // Conexión a la base de datos

// Importar clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$error = "";
$success = "";
$mostrar_modal_registro = false; // Bandera de control para levantar el formulario de registro

// Banderas para persistencia de modales de administración si hay errores/éxitos específicos
$mostrar_modal_baja = false;
$mostrar_modal_editar = false;
$usuario_a_editar = null;

// Bandera para forzar cambio de clave vencida vía Modal
$mostrar_modal_cambio_clave = false;

/**
 * Función auxiliar para configurar y enviar correos
 */
function enviarCorreo($destinatario, $asunto, $cuerpo) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'testadministrador@gmail.com'; 
        $mail->Password   = 'qybz utdg jdor lacj'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('testadministrador@gmail.com', 'NEO ADMIN SYSTEM');
        $mail->addAddress($destinatario); 

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// 1. LÓGICA DE LOGIN NORMAL CON VERIFICACIÓN POR USUARIO O EMAIL Y CADUCIDAD DE CONTRASEÑA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_login'])) {
    $user = trim($_POST['usuario']);
    $pass = $_POST['password'];

    // Buscar si ingresó el nombre de usuario o el correo electrónico
    $stmt = $conexion->prepare("SELECT id, usuario, email, password, rol, foto_perfil, ultima_modificacion_pass FROM usuarios WHERE usuario = ? OR email = ?");
    $stmt->bind_param("ss", $user, $user);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($row = $resultado->fetch_assoc()) {
        // Verificar usando password_verify o texto plano (fallback)
        if (password_verify($pass, $row['password']) || $pass == $row['password']) {
            
            // --- CÁLCULO DE CADUCIDAD DE CONTRASEÑA ---
            $fecha_pass_str = $row['ultima_modificacion_pass'] ?? null;
            $dias_restantes = 30;

            if (!empty($fecha_pass_str)) {
                $fecha_pass = new DateTime($fecha_pass_str);
                $fecha_actual = new DateTime();
                $dias_transcurridos = (int)$fecha_actual->diff($fecha_pass)->days;
                $dias_restantes = max(0, 30 - $dias_transcurridos);
            } else {
                $dias_restantes = 0;
            }

            if ($dias_restantes <= 0) {
                // Caso 1: Clave vencida -> Activar modal de cambio obligatorio sin redirigir
                $_SESSION['id_caducado'] = $row['id'];
                $_SESSION['usuario_caducado'] = $row['usuario'];
                $_SESSION['rol_caducado'] = $row['rol'];
                $_SESSION['foto_perfil_caducado'] = !empty($row['foto_perfil']) ? $row['foto_perfil'] : 'default.png';
                
                $mostrar_modal_cambio_clave = true;
            } else {
                // Iniciar sesión normal
                $_SESSION['usuario_id'] = $row['id'];
                $_SESSION['usuario']    = $row['usuario'];
                $_SESSION['rol']        = $row['rol'];
                $_SESSION['foto_perfil'] = !empty($row['foto_perfil']) ? $row['foto_perfil'] : 'default.png';
                $_SESSION['forzar_cambio_clave'] = false;
                
                // Caso 2: 7 días o menos -> Marcar para mostrar advertencia en el dashboard
                if ($dias_restantes <= 7) {
                    $_SESSION['mostrar_alerta_clave'] = true;
                    $_SESSION['dias_restantes_clave'] = $dias_restantes;
                }
                
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error = "Acceso denegado: Credenciales inválidas.";
        }
    } else {
        $error = "Usuario o correo no registrado en la red.";
    }
}

// 1.1 LÓGICA DE PROCESAMIENTO DE CAMBIO OBLIGATORIO DE CLAVE CADUCADA DESDE MODAL
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_cambiar_clave_expirada'])) {
    $pass1 = $_POST['nueva_password_expirada'];
    $pass2 = $_POST['confirmar_password_expirada'];
    $user_id_exp = $_SESSION['id_caducado'] ?? null;

    if (!$user_id_exp) {
        $error = "Sesión expirada. Intente iniciar sesión nuevamente.";
    } elseif (empty($pass1) || empty($pass2)) {
        $error = "Por favor complete ambos campos de contraseña.";
        $mostrar_modal_cambio_clave = true;
    } elseif ($pass1 !== $pass2) {
        $error = "Las contraseñas no coinciden. Intente nuevamente.";
        $mostrar_modal_cambio_clave = true;
    } else {
        $pass_hash = password_hash($pass1, PASSWORD_BCRYPT);
        $fecha_actual = date('Y-m-d H:i:s');

        $stmt_upd_pass = $conexion->prepare("UPDATE usuarios SET password = ?, ultima_modificacion_pass = ? WHERE id = ?");
        $stmt_upd_pass->bind_param("ssi", $pass_hash, $fecha_actual, $user_id_exp);

        if ($stmt_upd_pass->execute()) {
            // Promover las variables temporales a la sesión global e ingresar al sistema
            $_SESSION['usuario_id']  = $_SESSION['id_caducado'];
            $_SESSION['usuario']     = $_SESSION['usuario_caducado'];
            $_SESSION['rol']         = $_SESSION['rol_caducado'];
            $_SESSION['foto_perfil'] = $_SESSION['foto_perfil_caducado'];

            // Limpiar temporales
            unset($_SESSION['id_caducado'], $_SESSION['usuario_caducado'], $_SESSION['rol_caducado'], $_SESSION['foto_perfil_caducado']);

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error al actualizar la contraseña en la base de datos.";
            $mostrar_modal_cambio_clave = true;
        }
    }
}

// 2. PASO 1: VERIFICACIÓN DE CREDENCIALES DE ADMINISTRADOR (Filtro de Seguridad)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_verificar_admin'])) {
    $admin_user = trim($_POST['admin_usuario']);
    $admin_pass = $_POST['admin_password'];

    $stmt = $conexion->prepare("SELECT password, rol FROM usuarios WHERE usuario = ? OR email = ?");
    $stmt->bind_param("ss", $admin_user, $admin_user);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($row = $resultado->fetch_assoc()) {
        if (password_verify($admin_pass, $row['password']) || $admin_pass == $row['password']) {
            $rol_limpio = strtolower(trim($row['rol']));
            if ($rol_limpio == 'admin' || $rol_limpio == 'administrador') {
                $mostrar_modal_registro = true; 
            } else {
                $error = "Acceso denegado: Su cuenta no posee privilegios de Administrador.";
            }
        } else {
            $error = "Autenticación de Administrador fallida: Contraseña incorrecta.";
        }
    } else {
        $error = "El usuario administrador ingresado no existe.";
    }
}

// 3. PASO 2: PROCESAR EL REGISTRO REAL DEL NUEVO USUARIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_registrar_usuario'])) {
    $nombre_completo = trim($_POST['nombre_completo']);
    $nuevo_user      = trim($_POST['nuevo_usuario']);
    $email           = trim($_POST['email']);
    $dni             = trim($_POST['dni']);
    $rol             = $_POST['rol'];
    $area            = trim($_POST['area']);
    $pass1           = $_POST['nueva_password'];

    // Procesamiento de foto de perfil
    $nombre_foto = 'default.png';
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['foto_perfil']['tmp_name'];
        $fileName      = $_FILES['foto_perfil']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $nombre_foto = 'user_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadFileDir = 'img/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            move_uploaded_file($fileTmpPath, $uploadFileDir . $nombre_foto);
        }
    }

    // Verificar duplicados (Usuario, Email o DNI)
    $stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ? OR email = ? OR dni = ?");
    $stmt_check->bind_param("sss", $nuevo_user, $email, $dni);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        $error = "Error de protocolo: El identificador, correo o DNI ya se encuentra asignado a otra cuenta.";
        $mostrar_modal_registro = true;
    } else {
        $pass_hash = password_hash($pass1, PASSWORD_BCRYPT);
        $fecha_actual = date('Y-m-d H:i:s');

        $stmt_ins = $conexion->prepare("INSERT INTO usuarios (nombre_completo, usuario, email, dni, password, rol, area, foto_perfil, ultima_modificacion_pass) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("sssssssss", $nombre_completo, $nuevo_user, $email, $dni, $pass_hash, $rol, $area, $nombre_foto, $fecha_actual);

        if ($stmt_ins->execute()) {
            $success = "Ficha de usuario e identidad sincronizadas con éxito en el sistema.";
        } else {
            $error = "Error de sistema: No se pudo escribir en el registro de credenciales.";
            $mostrar_modal_registro = true;
        }
    }
}

// LÓGICA DE BAJA DE USUARIO (PROCESAMIENTO)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_baja_usuario'])) {
    $id_baja = $_POST['id_baja'];
    
    $stmt_del = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt_del->bind_param("i", $id_baja);
    
    if ($stmt_del->execute()) {
        $success = "El usuario ha sido dado de baja del sistema con éxito.";
    } else {
        $error = "Error de sistema: No se pudo eliminar el registro de la red.";
        $mostrar_modal_baja = true;
    }
}

// LÓGICA DE BÚSQUEDA PARA EDITAR (PROCESAMIENTO TRADICIONAL Y DESDE AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_buscar_editar'])) {
    $busqueda = trim($_POST['busqueda_editar']);
    
    $stmt_edit_search = $conexion->prepare("SELECT * FROM usuarios WHERE id = ? OR usuario = ? OR dni = ? OR email = ?");
    $stmt_edit_search->bind_param("ssss", $busqueda, $busqueda, $busqueda, $busqueda);
    $stmt_edit_search->execute();
    $res_edit = $stmt_edit_search->get_result();
    
    if ($row_edit = $res_edit->fetch_assoc()) {
        $usuario_a_editar = $row_edit;
        $mostrar_modal_editar = true;
    } else {
        $error = "No se encontró ningún usuario con los criterios especificados para su edición.";
        $mostrar_modal_registro = true; // Volver al panel anterior
    }
}

// LÓGICA DE ACTUALIZACIÓN / GUARDAR EDICIÓN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_actualizar_usuario'])) {
    $id_edit         = $_POST['id_editar'];
    $nombre_completo = trim($_POST['nombre_completo']);
    $nuevo_user      = trim($_POST['nuevo_usuario']);
    $email           = trim($_POST['email']);
    $dni             = trim($_POST['dni']);
    $rol             = $_POST['rol'];
    $area            = trim($_POST['area']);
    $pass1           = $_POST['nueva_password'];

    // Verificar duplicados excluyendo el usuario actual
    $stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE (usuario = ? OR email = ? OR dni = ?) AND id != ?");
    $stmt_check->bind_param("sssi", $nuevo_user, $email, $dni, $id_edit);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        $error = "Error de conflicto: Los nuevos datos ingresados pertenecen a otra entidad de la red.";
    } else {
        // Verificar si se subió una nueva foto en la edición
        $nueva_foto = null;
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath   = $_FILES['foto_perfil']['tmp_name'];
            $fileName      = $_FILES['foto_perfil']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $nueva_foto = 'user_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $uploadFileDir = 'img/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                move_uploaded_file($fileTmpPath, $uploadFileDir . $nueva_foto);
            }
        }

        $fecha_actual = date('Y-m-d H:i:s');

        // Construcción de consulta dinámica según si cambia password o foto
        if (!empty($pass1) && $nueva_foto !== null) {
            $pass_hash = password_hash($pass1, PASSWORD_BCRYPT);
            $stmt_upd = $conexion->prepare("UPDATE usuarios SET nombre_completo=?, usuario=?, email=?, dni=?, password=?, rol=?, area=?, foto_perfil=?, ultima_modificacion_pass=? WHERE id=?");
            $stmt_upd->bind_param("sssssssssi", $nombre_completo, $nuevo_user, $email, $dni, $pass_hash, $rol, $area, $nueva_foto, $fecha_actual, $id_edit);
        } else if (!empty($pass1)) {
            $pass_hash = password_hash($pass1, PASSWORD_BCRYPT);
            $stmt_upd = $conexion->prepare("UPDATE usuarios SET nombre_completo=?, usuario=?, email=?, dni=?, password=?, rol=?, area=?, ultima_modificacion_pass=? WHERE id=?");
            $stmt_upd->bind_param("ssssssssi", $nombre_completo, $nuevo_user, $email, $dni, $pass_hash, $rol, $area, $fecha_actual, $id_edit);
        } else if ($nueva_foto !== null) {
            $stmt_upd = $conexion->prepare("UPDATE usuarios SET nombre_completo=?, usuario=?, email=?, dni=?, rol=?, area=?, foto_perfil=? WHERE id=?");
            $stmt_upd->bind_param("sssssssi", $nombre_completo, $nuevo_user, $email, $dni, $rol, $area, $nueva_foto, $id_edit);
        } else {
            $stmt_upd = $conexion->prepare("UPDATE usuarios SET nombre_completo=?, usuario=?, email=?, dni=?, rol=?, area=? WHERE id=?");
            $stmt_upd->bind_param("ssssssi", $nombre_completo, $nuevo_user, $email, $dni, $rol, $area, $id_edit);
        }

        if ($stmt_upd->execute()) {
            if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] == $id_edit && $nueva_foto !== null) {
                $_SESSION['foto_perfil'] = $nueva_foto;
            }
            $success = "La ficha de identidad y privilegios del usuario han sido actualizados con éxito.";
        } else {
            $error = "Error de sistema: Fallo al reescribir la matriz de datos.";
        }
    }
}

// 4. LÓGICA DE RECUPERACIÓN (URL DINÁMICA SEGÚN ENTORNO / DOMINIO)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_recuperar'])) {
    $email_rec = trim($_POST['email_recuperacion']);
    
    // Construcción dinámica del enlace de restablecimiento
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $directorio_actual = dirname($_SERVER['SCRIPT_NAME']);
    $directorio_limpio = ($directorio_actual == '/' || $directorio_actual == '\\') ? '' : $directorio_actual;
    $url_restablecer = $protocolo . "://" . $_SERVER['HTTP_HOST'] . $directorio_limpio . "/restablecer.php?user=" . urlencode($email_rec);

    $asunto = "🔐 Recuperación de Acceso - NEO ADMIN";
    $cuerpo = "<div style='font-family: sans-serif; padding: 30px; background: #020617; color: white; border-radius: 15px; border: 1px solid #38bdf8;'>
                <h2 style='color: #38bdf8; text-align: center;'>NEO ADMIN SYSTEM</h2>
                <p>Se ha solicitado un enlace de recuperación para esta cuenta.</p>
                <p style='text-align: center; margin-top: 20px;'>
                    <a href='$url_restablecer' style='display: inline-block; padding: 12px 25px; background: #38bdf8; color: #020617; text-decoration: none; border-radius: 5px; font-weight: bold;'>RESTABLECER CLAVE</a>
                </p>
                <p style='margin-top: 20px; font-size: 12px; color: #94a3b8;'>Si el botón no funciona, copia y pega la siguiente URL en tu navegador:<br><a href='$url_restablecer' style='color: #38bdf8;'>$url_restablecer</a></p>
               </div>";

    if (enviarCorreo($email_rec, $asunto, $cuerpo)) {
        $success = "Protocolo de recuperación enviado con éxito. Revisa tu correo.";
    } else {
        $error = "Error de protocolo: Fallo en la conexión con el servidor de seguridad.";
    }
}

// Mini endpoint para la búsqueda interactiva asíncrona de usuarios a dar de baja
if (isset($_GET['action']) && $_GET['action'] == 'buscar_nodo_baja' && isset($_GET['term'])) {
    ob_clean();
    header('Content-Type: application/json');
    $term_limpio = trim($_GET['term']);
    $term_like = "%" . $term_limpio . "%";
    
    $stmt_ajax = $conexion->prepare("SELECT id, nombre_completo, usuario, dni, rol, area FROM usuarios WHERE id = ? OR nombre_completo LIKE ? OR usuario LIKE ? OR dni LIKE ? OR rol LIKE ? OR area LIKE ? LIMIT 5");
    $stmt_ajax->bind_param("ssssss", $term_limpio, $term_like, $term_like, $term_like, $term_like, $term_like);
    $stmt_ajax->execute();
    $res_ajax = $stmt_ajax->get_result();
    $usuarios_encontrados = [];
    while ($r = $res_ajax->fetch_assoc()) {
        $usuarios_encontrados[] = $r;
    }
    echo json_encode($usuarios_encontrados);
    exit();
}

// Mini endpoint para la búsqueda interactiva asíncrona de usuarios a editar/modificar
if (isset($_GET['action']) && $_GET['action'] == 'buscar_nodo_editar' && isset($_GET['term'])) {
    ob_clean();
    header('Content-Type: application/json');
    $term_limpio = trim($_GET['term']);
    $term_like = "%" . $term_limpio . "%";
    
    $stmt_ajax = $conexion->prepare("SELECT id, nombre_completo, usuario, dni, rol, area FROM usuarios WHERE id = ? OR nombre_completo LIKE ? OR usuario LIKE ? OR dni LIKE ? OR rol LIKE ? OR area LIKE ? LIMIT 5");
    $stmt_ajax->bind_param("ssssss", $term_limpio, $term_like, $term_like, $term_like, $term_like, $term_like);
    $stmt_ajax->execute();
    $res_ajax = $stmt_ajax->get_result();
    $usuarios_encontrados = [];
    while ($r = $res_ajax->fetch_assoc()) {
        $usuarios_encontrados[] = $r;
    }
    echo json_encode($usuarios_encontrados);
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEO ADMIN | Secure Access</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Plus+Jakarta+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --neon-blue: #38bdf8;
            --deep-space: #020617;
            --glass: rgba(15, 23, 42, 0.85);
            --success-glow: rgba(16, 185, 129, 0.2);
            --error-glow: rgba(239, 68, 68, 0.2);
            --neon-red: #f43f5e;
            --neon-yellow: #eab308;
        }

        body {
            background-image: url('img/neoadmin.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: white;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at center, rgba(2, 6, 23, 0.4) 0%, rgba(2, 6, 23, 0.9) 100%);
            z-index: 0;
        }

        body::after {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(56, 189, 248, 0.05) 1px, transparent 1px), 
                              linear-gradient(90deg, rgba(56, 189, 248, 0.05) 1px, transparent 1px);
            background-size: 45px 45px;
            z-index: 1;
            pointer-events: none;
        }

        .login-card {
            position: relative;
            z-index: 10;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 28px;
            padding: 40px 50px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-text {
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 5px;
            color: var(--neon-blue);
            text-shadow: 0 0 15px rgba(56, 189, 248, 0.6);
        }

        .form-control, .form-select {
            background: rgba(2, 6, 23, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            border-radius: 14px;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(2, 6, 23, 0.95);
            border-color: var(--neon-blue);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.2);
            color: white;
        }

        .form-select option {
            background: #0f172a;
            color: white;
        }

        .btn-neon {
            background: var(--neon-blue);
            color: var(--deep-space);
            font-weight: 800;
            border-radius: 14px;
            padding: 15px;
            width: 100%;
            border: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: 0.4s;
            box-shadow: 0 5px 20px rgba(56, 189, 248, 0.4);
        }

        .btn-neon:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(56, 189, 248, 0.6);
            background: #7dd3fc;
        }

        .btn-outline-neon {
            background: transparent;
            color: var(--neon-blue);
            font-weight: 700;
            border-radius: 14px;
            padding: 12px;
            width: 100%;
            border: 1px solid rgba(56, 189, 248, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .btn-outline-neon:hover {
            background: rgba(56, 189, 248, 0.1);
            border-color: var(--neon-blue);
            color: white;
        }

        .status-alert {
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }

        .alert-success { 
            background: var(--success-glow); 
            color: #34d399; 
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.1);
        }

        .alert-danger { 
            background: var(--error-glow); 
            color: #f87171; 
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.1);
        }

        .support-bot-trigger {
            position: fixed;
            bottom: 30px; right: 30px; width: 65px; height: 65px;
            background: linear-gradient(135deg, var(--neon-blue), #0284c7);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 10px 30px rgba(56, 189, 248, 0.4);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 1000;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .support-bot-trigger i { color: var(--deep-space); font-size: 30px; }

        #chatBot {
            position: fixed; bottom: 110px; right: 30px; width: 320px;
            background: #ffffff; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            display: none; z-index: 1001; overflow: hidden;
        }

        .bot-header { 
            background: var(--neon-blue); padding: 15px 20px; color: var(--deep-space); font-weight: 800;
            display: flex; justify-content: space-between;
        }
        
        .search-results-list {
            max-height: 180px;
            overflow-y: auto;
            background: rgba(2, 6, 23, 0.95);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 12px;
            padding: 0;
            list-style: none;
        }
        .search-results-list li {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: background 0.2s;
            font-size: 0.9rem;
        }
        .search-results-list li:hover {
            background: rgba(56, 189, 248, 0.15);
            color: var(--neon-blue);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-5">
            <h1 class="logo-text h2 mb-1">NEO ADMIN</h1>
            <p class="text-white-50 small text-uppercase fw-light" style="letter-spacing: 2px;">Protocolo de Autenticación</p>
        </div>

        <?php if($error && !$mostrar_modal_cambio_clave): ?>
            <div class="status-alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="status-alert alert-success">
                <i class="bi bi-shield-check fs-5"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="mb-4">
                <label class="form-label small text-white-50 fw-bold ms-1">USUARIO O EMAIL</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-person-badge"></i></span>
                    <input type="text" name="usuario" class="form-control" placeholder="Usuario o Correo" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label small text-white-50 fw-bold ms-1">CONTRASEÑA</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-shield-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            
            <button type="submit" name="btn_login" class="btn-neon mt-2 mb-3">ACCEDER AL SISTEMA</button>
            
            <button type="button" class="btn-outline-neon mb-4" data-bs-toggle="modal" data-bs-target="#modalFiltroAdmin">
                <i class="bi bi-person-plus-fill me-2"></i>Crear nuevo usuario
            </button>
            
            <div class="text-center">
                <a href="#" class="text-decoration-none small text-white-50" style="transition:0.3s" onmouseover="this.style.color='#38bdf8'" onmouseout="this.style.color='rgba(255,255,255,0.5)'" data-bs-toggle="modal" data-bs-target="#modalRecuperar">¿Olvidaste tu contraseña?</a>
            </div>
        </form>
    </div>

    <!-- MODAL DE CAMBIO OBLIGATORIO DE CLAVE CADUCADA -->
    <div class="modal fade" id="modalClaveExpirada" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #0f172a; border: 1px solid #f43f5e; border-radius: 25px; box-shadow: 0 0 40px rgba(244, 63, 94, 0.4);">
                <div class="modal-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-exclamation text-danger" style="font-size: 3.5rem;"></i>
                        <h4 class="text-white fw-bold mt-2">CLAVE CADUCADA</h4>
                        <p class="text-white-50 small">Su contraseña ha expirado (30 días de vigencia). Debe actualizarla obligatoriamente para poder ingresar al sistema.</p>
                    </div>

                    <?php if($error && $mostrar_modal_cambio_clave): ?>
                        <div class="status-alert alert-danger mb-4">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="index.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-white-50 fw-bold ms-1">NUEVA CONTRASEÑA</label>
                            <input type="password" name="nueva_password_expirada" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-white-50 fw-bold ms-1">CONFIRMAR NUEVA CONTRASEÑA</label>
                            <input type="password" name="confirmar_password_expirada" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button type="submit" name="btn_cambiar_clave_expirada" class="btn btn-danger w-100 py-3 fw-bold" style="border-radius: 14px; text-transform:uppercase; letter-spacing:1px;">Actualizar e Ingresar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFiltroAdmin" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #0f172a; border: 1px solid #f43f5e; border-radius: 25px; box-shadow: 0 0 40px rgba(244, 63, 94, 0.3);">
                <div class="modal-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill text-danger" style="font-size: 3.5rem;"></i>
                        <h4 class="text-white fw-bold mt-2">CONTROL DE ACCESO</h4>
                        <p class="text-white-50 small">Esta acción requiere elevación de privilegios. Autentique credenciales de Administrador.</p>
                    </div>
                    
                    <form action="index.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-white-50 fw-bold ms-1">USUARIO ADMINISTRADOR</label>
                            <input type="text" name="admin_usuario" class="form-control text-center" placeholder="ID de Administrador" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-white-50 fw-bold ms-1">CONTRASEÑA DE CONFIRMACIÓN</label>
                            <input type="password" name="admin_password" class="form-control text-center" placeholder="••••••••" required>
                        </div>
                        <button type="submit" name="btn_verificar_admin" class="btn btn-danger w-100 py-3 fw-bold" style="border-radius: 14px; text-transform:uppercase; letter-spacing:1px;">Validar Privilegios</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRegistro" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: #0f172a; border: 1px solid var(--neon-blue); border-radius: 25px; box-shadow: 0 0 40px rgba(56, 189, 248, 0.4);">
                <div class="modal-body p-5">
                    
                    <div class="d-flex justify-content-end gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-warning fw-bold px-3" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#modalBuscarEditar" data-bs-dismiss="modal">
                            <i class="bi bi-pencil-square me-1"></i> Modificar Usuario
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#modalBajaUsuario" data-bs-dismiss="modal">
                            <i class="bi bi-person-x-fill me-1"></i> Dar de Baja
                        </button>
                    </div>

                    <div class="text-center mb-4">
                        <i class="bi bi-person-gear text-info" style="font-size: 3.5rem;"></i>
                        <h4 class="text-white fw-bold mt-2">ALTA DE NUEVO USUARIO</h4>
                        <p class="text-white-50 small">Complete los campos de identidad solicitados para el registro en la infraestructura.</p>
                    </div>
                    
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">NOMBRE Y APELLIDO completo</label>
                                <input type="text" name="nombre_completo" class="form-control" placeholder="Ej: Alan Rodrigo Abalos" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">USUARIO (ID de Acceso)</label>
                                <input type="text" name="nuevo_usuario" class="form-control" placeholder="Ej: alan_sys" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">CORREO ELECTRÓNICO</label>
                                <input type="email" name="email" class="form-control" placeholder="correo@dominio.com" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">DOCUMENTO (DNI)</label>
                                <input type="text" name="dni" class="form-control" placeholder="Ej: 35123456" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">ÁREA / DEPARTAMENTO</label>
                                <input type="text" name="area" class="form-control" placeholder="Ej: Soporte TI / Infraestructura" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">ROL DEL SISTEMA</label>
                                <select name="rol" class="form-select" required>
                                    <option value="usuario">Usuario Estándar</option>
                                    <option value="admin">Administrador Global</option>
                                    <option value="tecnico">Técnico TI</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">CONTRASEÑA ASIGNADA</label>
                                <input type="password" name="nueva_password" class="form-control" placeholder="••••••••" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">FOTO DE PERFIL (OPCIONAL)</label>
                                <input type="file" name="foto_perfil" class="form-control" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3 mt-2">
                            <button type="button" class="btn btn-secondary w-50 py-3 fw-bold" data-bs-dismiss="modal" style="border-radius: 14px;">Cancelar</button>
                            <button type="submit" name="btn_registrar_usuario" class="btn btn-info w-50 py-3 fw-bold" style="border-radius: 14px; background: var(--neon-blue); color: var(--deep-space); border: none;">Guardar en Red</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalBajaUsuario" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #0f172a; border: 1px solid var(--neon-red); border-radius: 25px; box-shadow: 0 0 40px rgba(244, 63, 94, 0.4);">
                <div class="modal-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-dash-fill text-danger" style="font-size: 3.5rem;"></i>
                        <h4 class="text-white fw-bold mt-2">BAJA DE USUARIO</h4>
                        <p class="text-white-50 small">Busque la identidad del nodo que desea desvincular del sistema global.</p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-white-50 fw-bold">BUSCADOR DE ENTIDADES</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-search"></i></span>
                            <input type="text" id="inputBuscarBaja" class="form-control text-center" placeholder="Ingrese ID, nombre, DNI, rol o área...">
                        </div>
                        <ul id="listaResultadosBaja" class="search-results-list mt-2 d-none"></ul>
                    </div>

                    <form action="index.php" method="POST" id="formConfirmarBaja" class="d-none text-center">
                        <input type="hidden" name="id_baja" id="idBajaTarget">
                        <div class="alert alert-dark border-danger bg-opacity-25 text-white p-3 mb-4" style="border-radius:14px;">
                            <p class="small text-white-50 mb-1">Confirmar baja crítica para la entidad:</p>
                            <h5 id="textTargetBaja" class="text-danger fw-bold mb-0"></h5>
                        </div>
                        <div class="d-flex gap-3">
                            <button type="button" class="btn btn-secondary w-50 py-3 fw-bold" style="border-radius: 14px;" data-bs-toggle="modal" data-bs-target="#modalRegistro" data-bs-dismiss="modal">Volver</button>
                            <button type="submit" name="btn_baja_usuario" class="btn btn-danger w-50 py-3 fw-bold" style="border-radius: 14px;">Confirmar Baja</button>
                        </div>
                    </form>
                    
                    <div id="btnVolverBajaDefault" class="text-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#modalRegistro" data-bs-dismiss="modal">Volver al Panel de Alta</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalBuscarEditar" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #0f172a; border: 1px solid var(--neon-yellow); border-radius: 25px; box-shadow: 0 0 40px rgba(234, 179, 8, 0.4);">
                <div class="modal-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-search-heart text-warning" style="font-size: 3.5rem;"></i>
                        <h4 class="text-white fw-bold mt-2">BUSCADOR PARA MODIFICACIÓN</h4>
                        <p class="text-white-50 small">Localice el registro ingresando el ID de Acceso, Correo Electrónico o número de DNI exacto.</p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-white-50 fw-bold">CRITERIO DE BÚSQUEDA</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-0 text-white-50"><i class="bi bi-search"></i></span>
                            <input type="text" id="inputBuscarEditar" class="form-control text-center" placeholder="Ingrese ID, nombre, DNI, rol o área...">
                        </div>
                        <ul id="listaResultadosEditar" class="search-results-list mt-2 d-none"></ul>
                    </div>

                    <form action="index.php" method="POST" id="formConfirmarEditar" class="d-none">
                        <input type="hidden" name="busqueda_editar" id="idEditarTarget">
                        <input type="hidden" name="btn_buscar_editar" value="1">
                    </form>

                    <div class="text-center">
                        <button type="button" class="btn btn-secondary w-100 py-3 fw-bold" style="border-radius: 14px;" data-bs-toggle="modal" data-bs-target="#modalRegistro" data-bs-dismiss="modal">Volver</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($mostrar_modal_editar && $usuario_a_editar): ?>
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: #0f172a; border: 1px solid var(--neon-yellow); border-radius: 25px; box-shadow: 0 0 40px rgba(234, 179, 8, 0.4);">
                <div class="modal-body p-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-pencil-square text-warning" style="font-size: 3.5rem;"></i>
                        <h4 class="text-white fw-bold mt-2">EDITAR MATRIZ DE IDENTIDAD</h4>
                        <p class="text-white-50 small">Modifique los campos correspondientes. Deje la contraseña en blanco si prefiere conservar la actual.</p>
                    </div>
                    
                    <form action="index.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_editar" value="<?php echo $usuario_a_editar['id']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">NOMBRE Y APELLIDO completo</label>
                                <input type="text" name="nombre_completo" class="form-control" value="<?php echo htmlspecialchars($usuario_a_editar['nombre_completo']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">USUARIO (ID de Acceso)</label>
                                <input type="text" name="nuevo_usuario" class="form-control" value="<?php echo htmlspecialchars($usuario_a_editar['usuario']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">CORREO ELECTRÓNICO</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario_a_editar['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-white-50 fw-bold">DOCUMENTO (DNI)</label>
                                <input type="text" name="dni" class="form-control" value="<?php echo htmlspecialchars($usuario_a_editar['dni']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">ÁREA / DEPARTAMENTO</label>
                                <input type="text" name="area" class="form-control" value="<?php echo htmlspecialchars($usuario_a_editar['area']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">ROL DEL SISTEMA</label>
                                <select name="rol" class="form-select" required>
                                    <option value="usuario" <?php echo ($usuario_a_editar['rol'] == 'usuario') ? 'selected' : ''; ?>>Usuario Estándar</option>
                                    <option value="admin" <?php echo ($usuario_a_editar['rol'] == 'admin') ? 'selected' : ''; ?>>Administrador Global</option>
                                    <option value="tecnico" <?php echo ($usuario_a_editar['rol'] == 'tecnico') ? 'selected' : ''; ?>>Técnico TI</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">ASIGNAR NUEVA CONTRASEÑA (OPCIONAL)</label>
                                <input type="password" name="nueva_password" class="form-control" placeholder="Dejar en blanco para no modificar">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small text-white-50 fw-bold">CAMBIAR FOTO DE PERFIL (OPCIONAL)</label>
                                <input type="file" name="foto_perfil" class="form-control" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3 mt-2">
                            <button type="button" class="btn btn-secondary w-50 py-3 fw-bold" data-bs-dismiss="modal" style="border-radius: 14px;">Cancelar</button>
                            <button type="submit" name="btn_actualizar_usuario" class="btn btn-warning w-50 py-3 fw-bold text-dark" style="border-radius: 14px;">Actualizar Registro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="modalRecuperar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #0f172a; border: 1px solid var(--neon-blue); border-radius: 25px; box-shadow: 0 0 40px rgba(56, 189, 248, 0.4);">
                <div class="modal-body p-5 text-center">
                    <div class="mb-4">
                        <i class="bi bi-envelope-at text-info" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="text-white fw-bold mb-3">RECUPERAR IDENTIDAD</h4>
                    <p class="text-white-50 small mb-4">Se enviará un paquete de datos con el enlace de recuperación a su casilla corporativa.</p>
                    
                    <form action="index.php" method="POST">
                        <input type="email" name="email_recuperacion" class="form-control mb-4 text-center py-3" placeholder="email@dominio.com" required style="border-radius: 15px;">
                        <button type="submit" name="btn_recuperar" class="btn btn-info w-100 py-3 fw-bold shadow-sm" style="border-radius: 15px;">INICIAR TRANSFERENCIA</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="support-bot-trigger" onclick="toggleBot()">
        <i class="bi bi-headset"></i>
    </div>

    <div id="chatBot">
        <div class="bot-header">
            <span>TERMINAL DE AYUDA</span>
            <i class="bi bi-x-lg" style="cursor:pointer" onclick="toggleBot()"></i>
        </div>
        <div class="p-4" style="color: #475569;">
            <p class="small mb-4">¿En qué podemos ayudarte? Notifique al administrador de guardia de inmediato.</p>
            <form action="index.php" method="POST">
                <input type="email" name="email_soporte" class="form-control form-control-sm mb-3" placeholder="Tu correo para contacto" required style="background: #f1f5f9; color: black; border: 1px solid #cbd5e1;">
                <button type="submit" name="btn_soporte_bot" class="btn btn-dark w-100 fw-bold">ENVIAR REPORTE</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleBot() {
            const bot = document.getElementById('chatBot');
            if(bot.style.display === 'block') {
                bot.style.display = 'none';
            } else {
                bot.style.display = 'block';
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            <?php if ($mostrar_modal_cambio_clave): ?>
                var modalClave = new bootstrap.Modal(document.getElementById('modalClaveExpirada'));
                modalClave.show();
            <?php endif; ?>

            <?php if ($mostrar_modal_registro): ?>
                var modalRegistro = new bootstrap.Modal(document.getElementById('modalRegistro'));
                modalRegistro.show();
            <?php endif; ?>

            <?php if ($mostrar_modal_editar && $usuario_a_editar): ?>
                var modalEditar = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
                modalEditar.show();
            <?php endif; ?>

            <?php if ($mostrar_modal_baja): ?>
                var modalBaja = new bootstrap.Modal(document.getElementById('modalBajaUsuario'));
                modalBaja.show();
            <?php endif; ?>

            const inputBuscarBaja = document.getElementById('inputBuscarBaja');
            const listaResultadosBaja = document.getElementById('listaResultadosBaja');
            const formConfirmarBaja = document.getElementById('formConfirmarBaja');
            const idBajaTarget = document.getElementById('idBajaTarget');
            const textTargetBaja = document.getElementById('textTargetBaja');
            const btnVolverBajaDefault = document.getElementById('btnVolverBajaDefault');

            if (inputBuscarBaja) {
                inputBuscarBaja.addEventListener('input', function() {
                    let valor = this.value.trim();
                    if (valor.length < 1) {
                        listaResultadosBaja.classList.add('d-none');
                        formConfirmarBaja.classList.add('d-none');
                        btnVolverBajaDefault.classList.remove('d-none');
                        return;
                    }

                    fetch(`index.php?action=buscar_nodo_baja&term=${encodeURIComponent(valor)}`)
                        .then(response => response.json())
                        .then(data => {
                            listaResultadosBaja.innerHTML = '';
                            if (data.length > 0) {
                                listaResultadosBaja.classList.remove('d-none');
                                data.forEach(u => {
                                    let li = document.createElement('li');
                                    li.innerHTML = `<i class="bi bi-person-fill text-danger me-2"></i>ID: <strong>${u.id}</strong> | <strong>${u.nombre_completo}</strong> (${u.usuario})<br><small class="text-white-50 ms-4">DNI: ${u.dni} | Rol: ${u.rol} | Área: ${u.area}</small>`;
                                    li.addEventListener('click', function() {
                                        idBajaTarget.value = u.id;
                                        textTargetBaja.innerText = `${u.nombre_completo} [${u.usuario}]`;
                                        
                                        formConfirmarBaja.classList.remove('d-none');
                                        listaResultadosBaja.classList.add('d-none');
                                        btnVolverBajaDefault.classList.add('d-none');
                                    });
                                    listaResultadosBaja.appendChild(li);
                                });
                            } else {
                                listaResultadosBaja.classList.remove('d-none');
                                listaResultadosBaja.innerHTML = '<li class="text-muted text-center py-2 small">Ninguna coincidencia en el servidor.</li>';
                            }
                        });
                });
            }

            const inputBuscarEditar = document.getElementById('inputBuscarEditar');
            const listaResultadosEditar = document.getElementById('listaResultadosEditar');
            const formConfirmarEditar = document.getElementById('formConfirmarEditar');
            const idEditarTarget = document.getElementById('idEditarTarget');

            if (inputBuscarEditar) {
                inputBuscarEditar.addEventListener('input', function() {
                    let valor = this.value.trim();
                    if (valor.length < 1) {
                        listaResultadosEditar.classList.add('d-none');
                        return;
                    }

                    fetch(`index.php?action=buscar_nodo_editar&term=${encodeURIComponent(valor)}`)
                        .then(response => response.json())
                        .then(data => {
                            listaResultadosEditar.innerHTML = '';
                            if (data.length > 0) {
                                listaResultadosEditar.classList.remove('d-none');
                                data.forEach(u => {
                                    let li = document.createElement('li');
                                    li.innerHTML = `<i class="bi bi-pencil-fill text-warning me-2"></i>ID: <strong>${u.id}</strong> | <strong>${u.nombre_completo}</strong> (${u.usuario})<br><small class="text-white-50 ms-4">DNI: ${u.dni} | Rol: ${u.rol} | Área: ${u.area}</small>`;
                                    li.addEventListener('click', function() {
                                        idEditarTarget.value = u.id;
                                        listaResultadosEditar.classList.add('d-none');
                                        formConfirmarEditar.submit();
                                    });
                                    listaResultadosEditar.appendChild(li);
                                });
                            } else {
                                listaResultadosEditar.classList.remove('d-none');
                                listaResultadosEditar.innerHTML = '<li class="text-muted text-center py-2 small">Ninguna coincidencia en el servidor.</li>';
                            }
                        });
                });
            }
        });
    </script>
</body>
</html>