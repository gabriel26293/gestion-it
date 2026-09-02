<?php
session_start();
require_once 'db.php'; // Conexión a la base de datos[cite: 9]

// 1. Importar clases de PHPMailer[cite: 9]
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// Función para enviar correo mediante PHPMailer[cite: 9]
function enviarCorreoRestablecimiento($destinatario, $token, $url_restablecer) {
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor SMTP (Gmail)[cite: 9]
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'testadministrador@gmail.com'; 
        $mail->Password   = 'qybz utdg jdor lacj'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Emisor y Receptor[cite: 9]
        $mail->setFrom('testadministrador@gmail.com', 'NEO ADMIN SYSTEM');
        $mail->addAddress($destinatario); 

        // Contenido del correo en HTML[cite: 9]
        $mail->isHTML(true);
        $mail->Subject = "Código y Enlace para Restablecer Contraseña - NEO ADMIN";
        
        $mail->Body = "
        <div style='background-color: #020617; color: #ffffff; padding: 30px; font-family: Arial, sans-serif; border-radius: 10px;'>
            <h2 style='color: #38bdf8; text-align: center;'>NEO ADMIN SYSTEM</h2>
            <p>Hola,</p>
            <p>Hemos recibido una solicitud para restablecer tu contraseña.</p>
            <div style='background-color: rgba(56, 189, 248, 0.1); border: 1px solid #38bdf8; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #38bdf8; margin: 20px 0; border-radius: 8px;'>
                $token
            </div>
            <p>Tu código de verificación es de 6 dígitos. Este código y el enlace expiran en <strong>15 minutos</strong>.</p>
            <p style='text-align: center; margin-top: 25px;'>
                <a href='$url_restablecer' style='background-color: #38bdf8; color: #020617; padding: 12px 25px; text-decoration: none; font-weight: bold; border-radius: 5px; display: inline-block;'>RESTABLECER CONTRASEÑA</a>
            </p>
            <p style='margin-top: 20px; font-size: 12px; color: #94a3b8;'>Si el botón no funciona, copia y pega la siguiente URL en tu navegador:<br><a href='$url_restablecer' style='color: #38bdf8;'>$url_restablecer</a></p>
        </div>";

        // Texto alternativo plano[cite: 9]
        $mail->AltBody = "Tu código de confirmación es: $token\nEnlace para restablecer: $url_restablecer\n\nEste código expira en 15 minutos.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}

$mensaje = "";
$error = "";

// 2. Validar el parámetro inicial de la URL[cite: 9]
if (isset($_GET['user'])) {
    $usuario_target = trim($_GET['user']);
} else {
    header("Location: index.php");
    exit();
}

// 3. Si se accede por GET (primera vez desde el enlace), se genera y envía el Token[cite: 9]
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Buscar el email y el ID del usuario en la base de datos
    $stmt_email = $conexion->prepare("SELECT id, email FROM usuarios WHERE email = ? OR usuario = ?");
    $stmt_email->bind_param("ss", $usuario_target, $usuario_target);
    $stmt_email->execute();
    $res_email = $stmt_email->get_result();

    if ($row = $res_email->fetch_assoc()) {
        $user_id = $row['id'];
        $email_destino = $row['email'];
        
        // Generar token numérico de 6 dígitos y tiempo de expiración (15 minutos)[cite: 9]
        $token = sprintf("%06d", mt_rand(0, 999999));
        $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Guardar token y expiración usando el ID directo[cite: 9]
        $stmt_token = $conexion->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expira = ? WHERE id = ?");
        $stmt_token->bind_param("ssi", $token, $expiracion, $user_id);
        $stmt_token->execute();

        // Construir la URL dinámica actual para el enlace de restablecimiento[cite: 9]
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $url_restablecer = $protocolo . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?user=" . urlencode($usuario_target);

        // Enviar correo electrónico mediante PHPMailer[cite: 9]
        if (!enviarCorreoRestablecimiento($email_destino, $token, $url_restablecer)) {
            $error = "No se pudo enviar el correo de verificación. Por favor intente más tarde.";
        }
    } else {
        $error = "No se encontró ningún usuario vinculado con las credenciales proporcionadas.";
    }
}

// 4. Procesar el formulario cuando el usuario envía el token y la nueva contraseña[cite: 9]
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token_ingresado = trim($_POST['token']);
    $nueva_pass = $_POST['nueva_password'];
    $confirmar_pass = $_POST['confirmar_password'];

    if (empty($token_ingresado) || empty($nueva_pass) || empty($confirmar_pass)) {
        $error = "Por favor complete todos los campos.";
    } elseif ($nueva_pass !== $confirmar_pass) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $fecha_actual = date('Y-m-d H:i:s');

        // Validar el token y su vigencia obteniendo el ID del usuario
        $stmt_val = $conexion->prepare("SELECT id FROM usuarios WHERE (email = ? OR usuario = ?) AND reset_token = ? AND reset_token_expira >= ?");
        $stmt_val->bind_param("ssss", $usuario_target, $usuario_target, $token_ingresado, $fecha_actual);
        $stmt_val->execute();
        $res_val = $stmt_val->get_result();

        if ($res_val->num_rows > 0) {
            $row_user = $res_val->fetch_assoc();
            $user_id = $row_user['id'];

            // Generar Hash BCRYPT seguro
            $pass_encriptada = password_hash($nueva_pass, PASSWORD_BCRYPT);
            
            // Actualizar la contraseña apuntando al ID exacto en la BD
            $stmt_upd = $conexion->prepare("UPDATE usuarios SET password = ?, ultima_modificacion_pass = ?, reset_token = NULL, reset_token_expira = NULL WHERE id = ?");
            $stmt_upd->bind_param("ssi", $pass_encriptada, $fecha_actual, $user_id);

            if ($stmt_upd->execute()) {
                $mensaje = "Contraseña actualizada con éxito. Ya puede iniciar sesión.";
            } else {
                $error = "Error de sistema al actualizar la contraseña.";
            }
        } else {
            $error = "El código de confirmación es incorrecto o ha expirado.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEO ADMIN | Reset Protocol</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Plus+Jakarta+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --neon-blue: #38bdf8;
            --neon-pink: #f472b6;
            --deep-space: #020617;
            --glass: rgba(15, 23, 42, 0.7);
            --success-glow: rgba(16, 185, 129, 0.3);
            --error-glow: rgba(239, 68, 68, 0.3);
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

        .card-reset {
            position: relative;
            z-index: 10;
            background: var(--glass);
            backdrop-filter: blur(15px);
            border: 2px solid rgba(56, 189, 248, 0.3);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 0 50px rgba(56, 189, 248, 0.3);
            animation: fadeIn 0.8s ease;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .card-reset:hover {
            border-color: rgba(56, 189, 248, 0.8);
            box-shadow: 0 0 70px rgba(56, 189, 248, 0.5);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cyber-title {
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 3px;
            color: var(--neon-blue);
            text-shadow: 0 0 10px rgba(56, 189, 248, 0.7);
            text-transform: uppercase;
        }

        .user-tag {
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 8px;
            padding: 5px 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--neon-blue);
            font-size: 0.85rem;
            margin-bottom: 25px;
        }

        .form-label-cyber {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-left: 5px;
        }

        .form-control-cyber {
            background: rgba(2, 6, 23, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control-cyber:focus {
            background: rgba(2, 6, 23, 1);
            border-color: var(--neon-blue);
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.3);
            color: white;
        }

        .input-group-cyber .input-group-text {
            background: transparent;
            border: none;
            color: #64748b;
            padding-right: 0;
        }

        .input-group-cyber:focus-within .input-group-text {
            color: var(--neon-blue);
        }

        .btn-cyber-primary {
            background: linear-gradient(90deg, #0284c7, var(--neon-blue));
            color: var(--deep-space);
            font-weight: 800;
            border-radius: 10px;
            padding: 14px;
            width: 100%;
            border: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(2, 132, 199, 0.4);
        }

        .btn-cyber-primary:hover {
            box-shadow: 0 5px 25px rgba(56, 189, 248, 0.6);
            transform: translateY(-2px);
            background: linear-gradient(90deg, #38bdf8, #7dd3fc);
        }

        .status-alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
        }

        .alert-cyber-success { 
            background: var(--success-glow); 
            color: #34d399; 
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }

        .alert-cyber-danger { 
            background: var(--error-glow); 
            color: #f87171; 
            border-color: rgba(239, 68, 68, 0.4);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body>

<div class="card-reset text-center">
    <div class="mb-4">
        <i class="bi bi-shield-lock text-white-50" style="font-size: 2.5rem; filter: drop-shadow(0 0 8px rgba(255,255,255,0.3));"></i>
    </div>
    
    <h3 class="cyber-title mb-1">RESETEAR PROTOCOLO</h3>
    
    <div class="user-tag">
        <i class="bi bi-person-bounding-box"></i>
        <span><?php echo htmlspecialchars($usuario_target); ?></span>
    </div>

    <?php if ($mensaje): ?>
        <div class="status-alert alert-cyber-success">
            <i class="bi bi-shield-check fs-5"></i> <span><?php echo $mensaje; ?></span>
        </div>
        <a href="index.php" class="btn-cyber-primary" style="display: block; text-decoration: none;">IR AL LOGIN</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="status-alert alert-cyber-danger">
                <i class="bi bi-exclamation-octagon fs-5"></i> <span><?php echo $error; ?></span>
            </div>
        <?php else: ?>
            <div class="status-alert alert-cyber-success" style="background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border-color: rgba(56, 189, 248, 0.3);">
                <i class="bi bi-envelope-check fs-5"></i> <span>Se ha enviado un código de seguridad a su correo.</span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4 text-start">
                <label class="form-label-cyber">CÓDIGO DE CONFIRMACIÓN</label>
                <div class="input-group input-group-cyber form-control-cyber">
                    <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                    <input type="text" name="token" class="form-control border-0 bg-transparent text-white p-0 ps-3" required placeholder="123456" maxlength="6" autocomplete="off">
                </div>
            </div>
            <div class="mb-4 text-start">
                <label class="form-label-cyber">NUEVA CONTRASEÑA</label>
                <div class="input-group input-group-cyber form-control-cyber">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                    <input type="password" name="nueva_password" class="form-control border-0 bg-transparent text-white p-0 ps-3" required placeholder="••••••••">
                </div>
            </div>
            <div class="mb-5 text-start">
                <label class="form-label-cyber">CONFIRMAR CONTRASEÑA</label>
                <div class="input-group input-group-cyber form-control-cyber">
                    <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                    <input type="password" name="confirmar_password" class="form-control border-0 bg-transparent text-white p-0 ps-3" required placeholder="••••••••">
                </div>
            </div>
            <button type="submit" class="btn-cyber-primary">GUARDAR CAMBIOS</button>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>