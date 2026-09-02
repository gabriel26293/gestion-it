<?php
session_start();
require_once 'db.php';

// Establecer el encabezado para respuesta JSON
header('Content-Type: application/json');

// 1. Verificación de seguridad básica (Debe estar logueado)
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado: No autorizado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// 2. Procesamiento de la solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $accion = $_POST['accion'] ?? null;

    if (!$id || !$accion) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos para procesar la solicitud']);
        exit();
    }

    // Restricción de acciones administrativas/técnicas
    $acciones_staff = ['asignar', 'extender_tiempo', 'mantenimiento', 'resolver', 'editar_basico'];
    if (in_array($accion, $acciones_staff) && $rol !== 'administrador' && $rol !== 'tecnico') {
        echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para realizar esta acción']);
        exit();
    }

    $stmt = null;

    switch ($accion) {
        // ACCIÓN: EDITAR TIPO Y PRIORIDAD DESDE EL MODAL DE DETALLES
        case 'editar_ticket_base':
            $tipo = $_POST['tipo'] ?? '';
            $prioridad = $_POST['prioridad'] ?? '';

            if (empty($tipo) || empty($prioridad)) {
                echo json_encode(['status' => 'error', 'message' => 'El tipo de solicitud y la prioridad son obligatorios']);
                exit();
            }

            // Si es un usuario común, asegurarnos en la query que el ticket sea de su propiedad por seguridad
            if ($rol !== 'administrador' && $rol !== 'tecnico') {
                $stmt = $conexion->prepare("UPDATE tickets SET tipo = ?, prioridad = ? WHERE id = ? AND solicitante_id = ?");
                $stmt->bind_param("ssii", $tipo, $prioridad, $id, $usuario_id);
            } else {
                $stmt = $conexion->prepare("UPDATE tickets SET tipo = ?, prioridad = ? WHERE id = ?");
                $stmt->bind_param("ssi", $tipo, $prioridad, $id);
            }
            break;

        // ACCIÓN: ASIGNAR TÉCNICO
        case 'asignar':
            if (!isset($_POST['tecnico_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un técnico']);
                exit();
            }
            $tecnico_id = $_POST['tecnico_id'];
            
            /**
             * Al asignar:
             * 1. Cambiamos estado a 'En Proceso'.
             * 2. Limpiamos 'detalle_resolucion' por si venía de un modo Mantenimiento (para recuperar el título original).
             * 3. Establecemos 48hs de plazo.
             */
            $stmt = $conexion->prepare("UPDATE tickets SET tecnico_id = ?, estado = 'En Proceso', detalle_resolucion = NULL, fecha_limite = DATE_ADD(NOW(), INTERVAL 48 HOUR) WHERE id = ?");
            $stmt->bind_param("ii", $tecnico_id, $id);
            break;

        // ACCIÓN: EXTENDER TIEMPO
        case 'extender_tiempo':
            $horas = isset($_POST['horas']) ? (int)$_POST['horas'] : 0;
            if ($horas !== 24 && $horas !== 48 && $horas !== 72) {
                echo json_encode(['status' => 'error', 'message' => 'Intervalo de tiempo no válido']);
                exit();
            }
            // Extiende la fecha_limite actual.
            $stmt = $conexion->prepare("UPDATE tickets SET fecha_limite = DATE_ADD(fecha_limite, INTERVAL ? HOUR) WHERE id = ?");
            $stmt->bind_param("ii", $horas, $id);
            break;

        // ACCIÓN: PROGRAMAR MANTENIMIENTO
        case 'mantenimiento':
            $fecha = $_POST['fecha'] ?? '';
            $detalle = $_POST['detalle'] ?? ''; 
            
            if (empty($fecha) || empty($detalle)) {
                echo json_encode(['status' => 'error', 'message' => 'Fecha y descripción de mantenimiento son obligatorios']);
                exit();
            }

            /**
             * Al programar mantenimiento:
             * Usamos 'detalle_resolucion' para guardar el título que se verá en la card.
             * Usamos 'fecha_mantenimiento' para el cronómetro.
             */
            $stmt = $conexion->prepare("UPDATE tickets SET estado = 'Mantenimiento', fecha_mantenimiento = ?, detalle_resolucion = ? WHERE id = ?");
            $stmt->bind_param("ssi", $fecha, $detalle, $id);
            break;

        // ACCIÓN: RESOLVER TICKET
        case 'resolver':
            $estado = $_POST['estado'] ?? ''; 
            $detalle = $_POST['detalle'] ?? ''; 
            // Se guarda la resolución final y el estado cambia a Resuelto/No Resuelto.
            $stmt = $conexion->prepare("UPDATE tickets SET estado = ?, detalle_resolucion = ? WHERE id = ?");
            $stmt->bind_param("ssi", $estado, $detalle, $id);
            break;

        // ACCIÓN: EDITAR DATOS BÁSICOS
        case 'editar_basico':
            $asunto = $_POST['asunto'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            if (empty($asunto) || empty($descripcion)) {
                echo json_encode(['status' => 'error', 'message' => 'Título y descripción obligatorios']);
                exit();
            }
            $stmt = $conexion->prepare("UPDATE tickets SET asunto = ?, descripcion = ?, fecha_limite = IFNULL(fecha_limite, DATE_ADD(NOW(), INTERVAL 24 HOUR)) WHERE id = ?");
            $stmt->bind_param("ssi", $asunto, $descripcion, $id);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'La acción solicitada no es válida']);
            exit();
    }

    // 3. Ejecución y respuesta
    if ($stmt && $stmt->execute()) {
        // Validar si realmente se modificó una fila (por ejemplo, si el ID no correspondía al usuario)
        if ($stmt->affected_rows === 0 && $accion === 'editar_ticket_base') {
            echo json_encode(['status' => 'error', 'message' => 'No se realizaron cambios o no tiene permisos sobre este ticket']);
        } else {
            echo json_encode(['status' => 'success']);
        }
    } else {
        $errorMsg = $stmt ? $stmt->error : $conexion->error;
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $errorMsg]);
    }
    
    if ($stmt) $stmt->close();

} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>