-- ============================================================
-- Base de Conocimientos (bc_) para NEO ADMIN
-- Ejecutar este script en phpMyAdmin, dentro de la base `gestion_it`
-- ============================================================

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `bc_categorias`
-- --------------------------------------------------------

CREATE TABLE `bc_categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `icono` varchar(50) NOT NULL DEFAULT 'bi-journal-text',
  `orden` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bc_categorias` (`id`, `nombre`, `icono`, `orden`) VALUES
(1, 'Ingreso y Cuentas', 'bi-shield-lock', 1),
(2, 'Mesa de Ayuda (Tickets)', 'bi-ticket-perforated', 2),
(3, 'Inventario', 'bi-pc-display', 3),
(4, 'Perfil y Seguridad', 'bi-person-gear', 4),
(5, 'Errores Frecuentes', 'bi-exclamation-triangle', 5);

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `bc_articulos`
-- --------------------------------------------------------

CREATE TABLE `bc_articulos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `contenido` text NOT NULL,
  `visibilidad` enum('publico','interno') NOT NULL DEFAULT 'publico',
  `autor_id` int(11) DEFAULT NULL,
  `vistas` int(11) NOT NULL DEFAULT 0,
  `util_si` int(11) NOT NULL DEFAULT 0,
  `util_no` int(11) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Carga inicial de artículos (contenido base tomado del manual de usuario)
INSERT INTO `bc_articulos` (`id`, `categoria_id`, `titulo`, `contenido`, `visibilidad`, `autor_id`, `vistas`) VALUES
(1, 1, 'Cómo ingresar al sistema', 'Ingresá a la dirección web de NEO ADMIN. Vas a ver la pantalla de "Protocolo de Autenticación".\n\n1. Completá el campo USUARIO O EMAIL con tu nombre de usuario o correo registrado.\n2. Completá el campo CONTRASEÑA con tu clave personal.\n3. Presioná el botón ACCEDER AL SISTEMA.\n\nSi todavía no tenés una cuenta, presioná CREAR NUEVO USUARIO.\n\nNota: la contraseña tiene una vigencia de 30 días. Si expiró, el sistema te va a pedir que la actualices antes de poder ingresar.', 'publico', 1, 0),
(2, 1, 'No puedo iniciar sesión, ¿qué hago?', 'Verificá primero que el USUARIO y la CONTRASEÑA sean exactamente los que te asignaron (revisá mayúsculas y espacios).\n\nSi olvidaste tu contraseña, desde la pantalla de ingreso hacé clic en "¿Olvidaste tu contraseña?" y seguí los pasos del artículo "Recuperar contraseña".\n\nSi el problema persiste después de restablecerla, contactá a soporte técnico.', 'publico', 1, 0),
(3, 1, 'Recuperar contraseña olvidada', 'Desde la pantalla de ingreso, hacé clic en "¿Olvidaste tu contraseña?".\n\n1. Ingresá tu correo electrónico registrado.\n2. Presioná INICIAR TRANSFERENCIA.\n3. El sistema te va a enviar un enlace de recuperación a tu casilla de correo.\n4. Revisá tu correo y seguí el enlace para definir una nueva contraseña.', 'publico', 1, 0),
(4, 2, 'Cómo crear un nuevo ticket', 'Ingresá a "Nuevo Ticket" desde el panel principal o desde "+ NUEVO TICKET" en la Mesa de Ayuda.\n\nCompletá: Asunto, Descripción detallada, Tipo de solicitud, Origen/departamento y Prioridad. Podés adjuntar un archivo (imagen o PDF) de forma opcional.\n\nCuanto más detalle incluyas (y una captura de pantalla si aplica), más rápido va a poder resolverlo el equipo técnico.', 'publico', 1, 0),
(5, 2, 'Cómo hacer seguimiento de mis tickets', 'Desde el panel principal, presioná HISTORIAL en la tarjeta SOLICITUDES para ver el estado de todos los tickets que generaste (Nuevo, En curso, Resuelto o Cerrado).\n\nTambién podés escribirle al administrador de guardia desde el botón de auriculares (Terminal de Ayuda), abajo a la derecha.', 'publico', 1, 0),
(6, 3, 'Cómo consultar el inventario de equipos', 'El módulo de Inventario está disponible para administradores y técnicos. Muestra cada equipo con su categoría, código patrimonial, sector y usuario asignado.\n\nPodés buscar por marca, modelo o sector, y filtrar por categoría desde la parte superior del listado.', 'interno', 1, 0),
(7, 4, 'Actualizar mi contraseña desde el perfil', 'Ingresá a "Mi Perfil" desde el menú de usuario. En la sección ACTUALIZAR CONTRASEÑA completá los campos Nueva Contraseña y Confirmar Contraseña, y presioná Guardar Cambios.\n\nRecordá que la clave debe renovarse cada 30 días por política de seguridad.', 'publico', 1, 0),
(8, 5, 'El archivo adjunto no carga en mi ticket', 'Revisá que el archivo sea una imagen o un PDF, y que no supere el tamaño máximo permitido por el servidor. Si el problema continúa, probá con un archivo más liviano o contactá a soporte.', 'publico', 1, 0);

-- --------------------------------------------------------
-- Índices y llaves foráneas
-- --------------------------------------------------------

ALTER TABLE `bc_categorias`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bc_articulos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `autor_id` (`autor_id`),
  ADD FULLTEXT KEY `busqueda_texto` (`titulo`,`contenido`);

ALTER TABLE `bc_categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `bc_articulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `bc_articulos`
  ADD CONSTRAINT `bc_articulos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `bc_categorias` (`id`),
  ADD CONSTRAINT `bc_articulos_ibfk_2` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`);
