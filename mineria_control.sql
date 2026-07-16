-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-07-2026 a las 05:43:59
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `mineria_control`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas`
--

DROP TABLE IF EXISTS `alertas`;
CREATE TABLE `alertas` (
  `id` int(11) NOT NULL,
  `nivel` enum('baja','media','alta','critica') DEFAULT 'baja',
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `viaje_id` int(11) DEFAULT NULL,
  `estado` enum('activa','resuelta','ignorada') DEFAULT 'activa',
  `fecha_alerta` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `categoria` varchar(50) DEFAULT 'General',
  `usuario_creo` int(11) DEFAULT NULL,
  `usuario_resolvio` int(11) DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `notificacion_enviada` tinyint(4) DEFAULT 0,
  `prioridad` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alertas`
--

INSERT INTO `alertas` (`id`, `nivel`, `titulo`, `descripcion`, `viaje_id`, `estado`, `fecha_alerta`, `created_at`, `updated_at`, `categoria`, `usuario_creo`, `usuario_resolvio`, `fecha_resolucion`, `notificacion_enviada`, `prioridad`) VALUES
(1, 'media', 'retraso', 'falta', 3, 'resuelta', '2026-07-16 01:16:39', '2026-07-16 01:16:39', '2026-07-16 01:16:44', 'General', NULL, NULL, NULL, 0, 1),
(2, 'critica', '⏰ Retraso en viaje VIA-0003', 'El viaje VIA-0003 lleva 984 horas sin actualización. Estado actual: en_progreso.', 4, 'resuelta', '2026-07-16 01:25:18', '2026-07-16 01:25:18', '2026-07-16 01:25:40', 'Retraso Viaje', NULL, 2, '2026-07-15 20:25:40', 0, 5),
(3, 'critica', '⏰ Retraso en viaje VIA-0004', 'El viaje VIA-0004 lleva 984 horas sin actualización. Estado actual: pendiente.', 5, 'resuelta', '2026-07-16 01:25:18', '2026-07-16 01:25:18', '2026-07-16 01:25:38', 'Retraso Viaje', NULL, 2, '2026-07-15 20:25:38', 0, 5),
(4, 'critica', '⏰ Retraso en viaje VIA-0006', 'El viaje VIA-0006 lleva 984 horas sin actualización. Estado actual: pendiente.', 6, 'resuelta', '2026-07-16 01:25:18', '2026-07-16 01:25:18', '2026-07-16 01:25:36', 'Retraso Viaje', NULL, 2, '2026-07-15 20:25:36', 0, 5),
(5, 'baja', '???? Conductor inactivo: Juan Pérez', 'El conductor Juan Pérez no tiene viajes registrados en los últimos 45 días.', NULL, 'ignorada', '2026-07-16 01:25:18', '2026-07-16 01:25:18', '2026-07-16 01:25:32', 'Conductor Inactivo', NULL, NULL, NULL, 0, 1),
(6, 'critica', '⚠️ PRUEBA: Alerta crítica de prueba', 'Esta es una alerta de prueba para verificar las notificaciones push.', NULL, 'activa', '2026-07-16 01:29:54', '2026-07-16 01:29:54', '2026-07-16 01:29:54', 'Seguridad', NULL, NULL, NULL, 0, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones`
--

DROP TABLE IF EXISTS `asignaciones`;
CREATE TABLE `asignaciones` (
  `id` int(11) NOT NULL,
  `vehiculo_id` int(11) DEFAULT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `fecha_asignacion` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('activa','completada','cancelada') DEFAULT 'activa',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `motivo` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `notificacion_enviada` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `asignaciones`
--

INSERT INTO `asignaciones` (`id`, `vehiculo_id`, `conductor_id`, `fecha_asignacion`, `fecha_fin`, `estado`, `created_at`, `updated_at`, `motivo`, `observaciones`, `notificacion_enviada`) VALUES
(1, 1, 1, '2026-07-08', '2026-07-15', 'cancelada', '2026-07-09 02:09:24', '2026-07-16 00:10:24', 'Asignación para ruta minera Cerro Verde', NULL, 0),
(2, 2, 2, '2026-07-08', '2026-07-15', 'cancelada', '2026-07-09 02:09:24', '2026-07-16 00:10:27', 'Transporte de material a planta', NULL, 0),
(3, 3, 3, '2026-06-28', '2026-07-05', 'completada', '2026-07-09 02:09:24', '2026-07-09 02:09:24', 'Mantenimiento preventivo', NULL, 0),
(4, 3, 2, '2026-07-16', '2026-07-23', 'activa', '2026-07-16 00:10:59', '2026-07-16 00:10:59', 'ruta de minerales', '', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(100) DEFAULT NULL,
  `accion` varchar(50) DEFAULT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `datos_anteriores` text DEFAULT NULL,
  `datos_nuevos` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notas` text DEFAULT NULL,
  `revisado` tinyint(4) DEFAULT 0,
  `revisado_por` int(11) DEFAULT NULL,
  `fecha_revision` datetime DEFAULT NULL,
  `editado_por` int(11) DEFAULT NULL,
  `fecha_edicion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `usuario_id`, `usuario_nombre`, `accion`, `tabla_afectada`, `registro_id`, `datos_anteriores`, `datos_nuevos`, `ip_address`, `created_at`, `notas`, `revisado`, `revisado_por`, `fecha_revision`, `editado_por`, `fecha_edicion`) VALUES
(1, 2, 'Administrador', 'CIERRE_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-05-31 17:23:52', NULL, 0, NULL, NULL, NULL, NULL),
(2, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-05-31 17:24:01', NULL, 0, NULL, NULL, NULL, NULL),
(3, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-06-04 02:39:18', NULL, 0, NULL, NULL, NULL, NULL),
(4, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-06-05 00:28:44', NULL, 0, NULL, NULL, NULL, NULL),
(5, 2, 'Administrador', 'CREAR', 'viajes', 6, NULL, NULL, '::1', '2026-06-05 00:35:44', NULL, 0, NULL, NULL, NULL, NULL),
(6, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-09 01:47:55', NULL, 0, NULL, NULL, NULL, NULL),
(7, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-15 23:58:25', NULL, 0, NULL, NULL, NULL, NULL),
(8, 2, 'Administrador', 'CAMBIAR_ESTADO', 'asignaciones', 1, NULL, NULL, '::1', '2026-07-16 00:10:24', NULL, 0, NULL, NULL, NULL, NULL),
(9, 2, 'Administrador', 'CAMBIAR_ESTADO', 'asignaciones', 2, NULL, NULL, '::1', '2026-07-16 00:10:27', NULL, 0, NULL, NULL, NULL, NULL),
(10, 2, 'Administrador', 'CREAR', 'asignaciones', 0, NULL, NULL, '::1', '2026-07-16 00:10:59', NULL, 0, NULL, NULL, NULL, NULL),
(11, 2, 'Administrador', 'CREAR', 'alertas', 1, NULL, NULL, '::1', '2026-07-16 01:16:39', NULL, 0, NULL, NULL, NULL, NULL),
(12, 2, 'Administrador', 'CAMBIAR_ESTADO', 'alertas', 1, NULL, NULL, '::1', '2026-07-16 01:16:44', NULL, 0, NULL, NULL, NULL, NULL),
(13, 2, 'Administrador', 'ALERTAS_AUTOMATICAS', 'alertas', 0, NULL, '{\"generadas\":4}', '::1', '2026-07-16 01:25:18', NULL, 1, 2, '2026-07-15 21:21:15', NULL, NULL),
(14, 2, 'Administrador', 'CAMBIAR_ESTADO', 'alertas', 5, NULL, NULL, '::1', '2026-07-16 01:25:32', NULL, 0, NULL, NULL, NULL, NULL),
(15, 2, 'Administrador', 'CAMBIAR_ESTADO', 'alertas', 4, NULL, NULL, '::1', '2026-07-16 01:25:36', NULL, 0, NULL, NULL, NULL, NULL),
(16, 2, 'Administrador', 'CAMBIAR_ESTADO', 'alertas', 3, NULL, NULL, '::1', '2026-07-16 01:25:38', NULL, 0, NULL, NULL, NULL, NULL),
(17, 2, 'Administrador', 'CAMBIAR_ESTADO', 'alertas', 2, NULL, NULL, '::1', '2026-07-16 01:25:40', NULL, 0, NULL, NULL, NULL, NULL),
(24, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, NULL, '127.0.0.1', '2026-07-16 01:49:32', NULL, 0, NULL, NULL, NULL, NULL),
(25, 2, 'Administrador', 'CREAR', 'clientes', 1, NULL, NULL, '127.0.0.1', '2026-07-16 01:49:32', NULL, 0, NULL, NULL, NULL, NULL),
(26, 2, 'Administrador', 'ACTUALIZAR', 'conductores', 2, NULL, NULL, '127.0.0.1', '2026-07-16 01:49:32', NULL, 0, NULL, NULL, NULL, NULL),
(27, 2, 'Administrador', 'ELIMINAR', 'viajes', 5, NULL, NULL, '127.0.0.1', '2026-07-16 01:49:32', NULL, 0, NULL, NULL, NULL, NULL),
(28, 2, 'Administrador', 'CAMBIAR_ESTADO', 'asignaciones', 3, NULL, NULL, '127.0.0.1', '2026-07-16 01:49:32', NULL, 0, NULL, NULL, NULL, NULL),
(29, 2, 'Administrador', 'CIERRE_SESION', 'usuarios', 2, NULL, NULL, '127.0.0.1', '2026-07-16 01:49:32', NULL, 0, NULL, NULL, NULL, NULL),
(30, 2, 'Administrador', 'CREAR', 'usuarios', 6, NULL, NULL, '::1', '2026-07-16 02:31:34', NULL, 0, NULL, NULL, NULL, NULL),
(31, 2, 'Administrador', 'CIERRE_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 02:31:41', NULL, 0, NULL, NULL, NULL, NULL),
(32, 6, 'HEIDY PAOLA', 'INICIO_SESION', 'usuarios', 6, NULL, '{\"username\":\"heidy\"}', '::1', '2026-07-16 02:31:58', NULL, 0, NULL, NULL, NULL, NULL),
(33, 6, 'HEIDY PAOLA', 'CIERRE_SESION', 'usuarios', 6, NULL, '{\"username\":\"heidy\"}', '::1', '2026-07-16 02:38:04', NULL, 0, NULL, NULL, NULL, NULL),
(34, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 02:38:09', NULL, 0, NULL, NULL, NULL, NULL),
(35, 2, 'Administrador', 'CIERRE_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 02:40:04', NULL, 0, NULL, NULL, NULL, NULL),
(36, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 02:40:07', NULL, 0, NULL, NULL, NULL, NULL),
(37, 2, 'Administrador', 'CREAR', 'usuarios', 7, NULL, NULL, '::1', '2026-07-16 02:40:56', NULL, 0, NULL, NULL, NULL, NULL),
(38, 2, 'Administrador', 'CIERRE_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 02:41:00', NULL, 0, NULL, NULL, NULL, NULL),
(39, 7, 'heddy soyan', 'INICIO_SESION', 'usuarios', 7, NULL, '{\"username\":\"poma\"}', '::1', '2026-07-16 02:41:10', NULL, 0, NULL, NULL, NULL, NULL),
(40, 7, 'heddy soyan', 'CIERRE_SESION', 'usuarios', 7, NULL, '{\"username\":\"poma\"}', '::1', '2026-07-16 02:41:46', NULL, 0, NULL, NULL, NULL, NULL),
(41, 6, 'HEIDY PAOLA', 'INICIO_SESION', 'usuarios', 6, NULL, '{\"username\":\"heidy\"}', '::1', '2026-07-16 02:41:56', NULL, 0, NULL, NULL, NULL, NULL),
(42, 6, 'HEIDY PAOLA', 'CIERRE_SESION', 'usuarios', 6, NULL, '{\"username\":\"heidy\"}', '::1', '2026-07-16 02:44:02', NULL, 0, NULL, NULL, NULL, NULL),
(43, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 02:44:05', NULL, 0, NULL, NULL, NULL, NULL),
(44, 2, 'Administrador', 'CREAR', 'usuarios', 8, NULL, NULL, '::1', '2026-07-16 03:03:26', NULL, 0, NULL, NULL, NULL, NULL),
(45, 2, 'Administrador', 'CAMBIAR_PASSWORD', 'usuarios', 8, NULL, '{\"usuario\":\"fernando\",\"cambiado_por\":\"Administrador\"}', '::1', '2026-07-16 03:06:12', NULL, 0, NULL, NULL, NULL, NULL),
(46, 2, 'Administrador', 'CIERRE_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 03:06:18', NULL, 0, NULL, NULL, NULL, NULL),
(47, 2, 'Administrador', 'INICIO_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 03:06:37', NULL, 0, NULL, NULL, NULL, NULL),
(48, 2, 'Administrador', 'CIERRE_SESION', 'usuarios', 2, NULL, '{\"username\":\"admin\"}', '::1', '2026-07-16 03:06:44', NULL, 0, NULL, NULL, NULL, NULL),
(49, 8, 'fernando', 'INICIO_SESION', 'usuarios', 8, NULL, '{\"username\":\"veli\"}', '::1', '2026-07-16 03:06:53', NULL, 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ruc` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `ruc`, `telefono`, `email`, `direccion`, `estado`, `created_at`, `updated_at`) VALUES
(1, 'Minera XYZ', '20123456789', '987654321', 'contacto@mineraxyz.com', 'Av. Principal 123', 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31'),
(2, 'Constructora ABC', '20987654321', '987654322', 'info@constructoraabc.com', 'Calle Los Andes 456', 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conductores`
--

DROP TABLE IF EXISTS `conductores`;
CREATE TABLE `conductores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `licencia` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `estado` enum('disponible','ocupado','vacaciones','inactivo') DEFAULT 'disponible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `foto` varchar(255) DEFAULT NULL,
  `calificacion` decimal(2,1) DEFAULT 0.0,
  `puntuacion` int(11) DEFAULT 0,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_vencimiento_licencia` date DEFAULT NULL,
  `numero_emergencia` varchar(20) DEFAULT NULL,
  `experiencia_anos` int(11) DEFAULT 0,
  `documentos` text DEFAULT NULL,
  `ultimo_servicio` date DEFAULT NULL,
  `total_viajes` int(11) DEFAULT 0,
  `total_toneladas` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conductores`
--

INSERT INTO `conductores` (`id`, `nombre`, `licencia`, `telefono`, `email`, `direccion`, `estado`, `created_at`, `updated_at`, `foto`, `calificacion`, `puntuacion`, `fecha_nacimiento`, `fecha_vencimiento_licencia`, `numero_emergencia`, `experiencia_anos`, `documentos`, `ultimo_servicio`, `total_viajes`, `total_toneladas`) VALUES
(1, 'Juan Pérez', 'L123456', '987654323', 'juan.perez@email.com', NULL, 'disponible', '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, 0.0, 0, NULL, NULL, NULL, 0, NULL, NULL, 0, 0.00),
(2, 'Carlos López', 'L654321', '987654324', 'carlos.lopez@email.com', NULL, 'ocupado', '2026-05-31 16:49:31', '2026-07-16 00:10:59', NULL, 0.0, 0, NULL, NULL, NULL, 0, NULL, NULL, 0, 0.00),
(3, 'Miguel Ángel', 'L789012', '987654325', 'miguel.angel@email.com', NULL, 'ocupado', '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, 0.0, 0, NULL, NULL, NULL, 0, NULL, NULL, 0, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

DROP TABLE IF EXISTS `materiales`;
CREATE TABLE `materiales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `unidad_medida` varchar(20) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `codigo` varchar(50) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT 'Mineral',
  `stock_actual` decimal(12,2) DEFAULT 0.00,
  `stock_minimo` decimal(12,2) DEFAULT 0.00,
  `ubicacion` varchar(100) DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `ultima_compra` date DEFAULT NULL,
  `ultimo_precio_compra` decimal(10,2) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `codigo_barras` varchar(100) DEFAULT NULL,
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id`, `nombre`, `unidad_medida`, `precio_unitario`, `estado`, `created_at`, `updated_at`, `codigo`, `categoria`, `stock_actual`, `stock_minimo`, `ubicacion`, `proveedor`, `ultima_compra`, `ultimo_precio_compra`, `imagen`, `codigo_barras`, `notas`) VALUES
(1, 'Cobre', 'Tonelada', 8500.00, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, 'Mineral', 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Hierro', 'Tonelada', 1200.00, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, 'Mineral', 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Oro', 'Kilogramo', 45000.00, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, 'Mineral', 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Plata', 'Kilogramo', 6200.00, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, 'Mineral', 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Zinc', 'Tonelada', 2800.00, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, 'Mineral', 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Cobre', 'Tonelada', 8500.00, 1, '2026-06-04 03:51:28', '2026-06-04 03:51:28', 'MAT-001', 'Mineral', 500.00, 100.00, NULL, 'Southern Peru', NULL, NULL, NULL, NULL, NULL),
(7, 'Hierro', 'Tonelada', 1200.00, 1, '2026-06-04 03:51:28', '2026-06-04 03:51:28', 'MAT-002', 'Mineral', 1200.00, 200.00, NULL, 'Shougang', NULL, NULL, NULL, NULL, NULL),
(8, 'Oro', 'Kilogramo', 45000.00, 1, '2026-06-04 03:51:28', '2026-06-04 03:51:28', 'MAT-003', 'Mineral', 50.00, 10.00, NULL, 'Barrick', NULL, NULL, NULL, NULL, NULL),
(9, 'Plata', 'Kilogramo', 6200.00, 1, '2026-06-04 03:51:28', '2026-06-04 03:51:28', 'MAT-004', 'Mineral', 200.00, 30.00, NULL, 'Buenaventura', NULL, NULL, NULL, NULL, NULL),
(10, 'Zinc', 'Tonelada', 2800.00, 1, '2026-06-04 03:51:28', '2026-06-04 03:51:28', 'MAT-005', 'Concentrado', 800.00, 150.00, NULL, 'Nexa', NULL, NULL, NULL, NULL, NULL),
(11, 'Caliza', 'Tonelada', 150.00, 1, '2026-06-04 03:51:28', '2026-06-04 03:51:28', 'MAT-006', 'Insumo', 5000.00, 500.00, NULL, 'Cementos Lima', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

DROP TABLE IF EXISTS `rutas`;
CREATE TABLE `rutas` (
  `id` int(11) NOT NULL,
  `origen` varchar(100) NOT NULL,
  `destino` varchar(100) NOT NULL,
  `distancia` decimal(10,2) DEFAULT NULL,
  `tiempo_estimado` int(11) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tipo` varchar(50) DEFAULT 'Terrestre',
  `dificultad` enum('baja','media','alta','extrema') DEFAULT 'media',
  `peligrosidad` enum('baja','media','alta') DEFAULT 'media',
  `restriccion_peso` decimal(10,2) DEFAULT NULL,
  `restriccion_altura` decimal(5,2) DEFAULT NULL,
  `puntos_intermedios` text DEFAULT NULL,
  `coordenadas_origen` varchar(100) DEFAULT NULL,
  `coordenadas_destino` varchar(100) DEFAULT NULL,
  `condiciones` text DEFAULT NULL,
  `ultimo_mantenimiento` date DEFAULT NULL,
  `total_viajes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rutas`
--

INSERT INTO `rutas` (`id`, `origen`, `destino`, `distancia`, `tiempo_estimado`, `estado`, `created_at`, `updated_at`, `tipo`, `dificultad`, `peligrosidad`, `restriccion_peso`, `restriccion_altura`, `puntos_intermedios`, `coordenadas_origen`, `coordenadas_destino`, `condiciones`, `ultimo_mantenimiento`, `total_viajes`) VALUES
(1, 'Mina Central', 'Puerto', 250.00, 360, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', 'Terrestre', 'media', 'media', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(2, 'Mina Norte', 'Planta', 120.00, 180, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', 'Terrestre', 'media', 'media', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(3, 'Planta', 'Puerto', 150.00, 240, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', 'Terrestre', 'media', 'media', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(4, 'Mina Sur', 'Planta', 90.00, 150, 1, '2026-05-31 16:49:31', '2026-05-31 16:49:31', 'Terrestre', 'media', 'media', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(5, 'Mina Cerro Verde', 'Puerto Matarani', 250.50, 360, 1, '2026-06-05 00:41:49', '2026-06-05 00:41:49', 'Minería', 'media', 'media', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(6, 'Mina Toquepala', 'Planta Ilo', 180.00, 270, 1, '2026-06-05 00:41:49', '2026-06-05 00:41:49', 'Minería', 'alta', 'alta', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(7, 'Planta Ilo', 'Puerto Matarani', 120.00, 180, 1, '2026-06-05 00:41:49', '2026-06-05 00:41:49', 'Terrestre', 'baja', 'baja', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(8, 'Mina Antamina', 'Planta Huaraz', 95.50, 150, 1, '2026-06-05 00:41:49', '2026-06-05 00:41:49', 'Minería', 'extrema', 'alta', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','supervisor','operador','cliente') DEFAULT 'operador',
  `estado` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL,
  `ultimo_ip` varchar(45) DEFAULT NULL,
  `intentos_fallidos` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `nombre`, `email`, `password`, `rol`, `estado`, `created_at`, `updated_at`, `ultimo_acceso`, `ultimo_ip`, `intentos_fallidos`) VALUES
(2, 'admin', 'Administrador', 'admin@hymineria.com', '$2y$10$tCDLkr0GeRcGMfIWJejv5elT5bncyQHlMjj2bFIBKf5WQadwgNlDy', 'admin', 1, '2026-05-31 17:13:49', '2026-07-16 03:06:37', '2026-07-15 22:06:37', '::1', 0),
(3, 'supervisor', 'Juan Supervisor', 'supervisor@hymineria.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor', 1, '2026-07-16 02:30:15', '2026-07-16 02:30:15', NULL, NULL, 0),
(4, 'operador', 'Carlos Operador', 'operador@hymineria.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operador', 1, '2026-07-16 02:30:15', '2026-07-16 02:30:15', NULL, NULL, 0),
(5, 'cliente', 'Maria Cliente', 'cliente@hymineria.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente', 1, '2026-07-16 02:30:15', '2026-07-16 02:30:15', NULL, NULL, 0),
(6, 'heidy', 'HEIDY PAOLA', 'hnavarroc@undac.edu.pe', '$2y$10$rjH2xw09ADueENN1o9QmP.QjaoS28MXUXqwxiOrDEBqJCPgVuV84S', 'cliente', 1, '2026-07-16 02:31:34', '2026-07-16 02:41:56', '2026-07-15 21:41:56', '::1', 0),
(7, 'poma', 'heddy soyan', 'heidy@undac.edu.pe', '$2y$10$2BeKWhO5.Fj2zhypUbyJBOgxXGn9Gj1TvOs80N0GUvcam7nK18ZHa', 'supervisor', 1, '2026-07-16 02:40:56', '2026-07-16 02:41:10', '2026-07-15 21:41:10', '::1', 0),
(8, 'veli', 'fernando', 'fer@undac.edu.pe', '$2y$10$75n6PE/NJ8yhuFO6udym9.mehkA26EDJBMg.9oJsuC.bEcJQ3C79.', 'operador', 1, '2026-07-16 03:03:26', '2026-07-16 03:06:53', '2026-07-15 22:06:53', '::1', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

DROP TABLE IF EXISTS `vehiculos`;
CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
  `placa` varchar(10) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `año` int(11) DEFAULT NULL,
  `capacidad` decimal(10,2) DEFAULT NULL,
  `estado` enum('activo','mantenimiento','inactivo') DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `numero_motor` varchar(50) DEFAULT NULL,
  `numero_chasis` varchar(50) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `soat_vencimiento` date DEFAULT NULL,
  `revision_tecnica` date DEFAULT NULL,
  `seguro_vencimiento` date DEFAULT NULL,
  `ultimo_mantenimiento` date DEFAULT NULL,
  `proximo_mantenimiento` date DEFAULT NULL,
  `kilometraje` int(11) DEFAULT 0,
  `fotos` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`id`, `placa`, `marca`, `modelo`, `año`, `capacidad`, `estado`, `created_at`, `updated_at`, `numero_motor`, `numero_chasis`, `color`, `soat_vencimiento`, `revision_tecnica`, `seguro_vencimiento`, `ultimo_mantenimiento`, `proximo_mantenimiento`, `kilometraje`, `fotos`, `observaciones`) VALUES
(1, 'ABC-123', 'Volvo', 'FH16', 2022, 40.00, 'activo', '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL),
(2, 'DEF-456', 'Scania', 'R500', 2021, 35.50, 'activo', '2026-05-31 16:49:31', '2026-05-31 16:49:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL),
(3, 'GHI-789', 'Mercedes', 'Actros', 2023, 42.00, 'activo', '2026-05-31 16:49:31', '2026-06-04 03:41:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `viajes`
--

DROP TABLE IF EXISTS `viajes`;
CREATE TABLE `viajes` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `vehiculo_id` int(11) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `ruta_id` int(11) DEFAULT NULL,
  `peso` decimal(10,2) DEFAULT NULL,
  `estado` enum('pendiente','en_progreso','completado','cancelado') DEFAULT 'pendiente',
  `fecha_viaje` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `costo_total` decimal(12,2) DEFAULT 0.00,
  `ingreso_total` decimal(12,2) DEFAULT 0.00,
  `ganancia` decimal(12,2) DEFAULT 0.00,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `tiempo_real` int(11) DEFAULT NULL,
  `kilometros_recorridos` int(11) DEFAULT 0,
  `combustible_usado` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `comprobante` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `viajes`
--

INSERT INTO `viajes` (`id`, `codigo`, `cliente_id`, `conductor_id`, `vehiculo_id`, `material_id`, `ruta_id`, `peso`, `estado`, `fecha_viaje`, `created_at`, `updated_at`, `costo_total`, `ingreso_total`, `ganancia`, `fecha_inicio`, `fecha_fin`, `tiempo_real`, `kilometros_recorridos`, `combustible_usado`, `observaciones`, `comprobante`) VALUES
(1, 'VIA-001', 1, 1, 1, 1, 1, 35.50, 'completado', '2026-05-31', '2026-05-31 16:49:31', '2026-05-31 16:49:31', 0.00, 0.00, 0.00, NULL, NULL, NULL, 0, 0.00, NULL, NULL),
(2, 'VIA-0001', 1, 1, 1, 1, 1, 35.50, 'completado', '2024-06-01', '2026-06-05 00:33:52', '2026-06-05 00:33:52', 0.00, 301750.00, 0.00, NULL, NULL, NULL, 0, 0.00, NULL, NULL),
(3, 'VIA-0002', 2, 2, 2, 2, 2, 28.00, 'completado', '2024-06-02', '2026-06-05 00:33:52', '2026-06-05 00:33:52', 0.00, 33600.00, 0.00, NULL, NULL, NULL, 0, 0.00, NULL, NULL),
(4, 'VIA-0003', 1, 1, 1, 1, 1, 40.00, 'en_progreso', '2024-06-03', '2026-06-05 00:33:52', '2026-06-05 00:33:52', 0.00, 340000.00, 0.00, NULL, NULL, NULL, 0, 0.00, NULL, NULL),
(5, 'VIA-0004', 2, 3, 3, 3, 3, 0.50, 'pendiente', '2024-06-04', '2026-06-05 00:33:52', '2026-06-05 00:33:52', 0.00, 22500.00, 0.00, NULL, NULL, NULL, 0, 0.00, NULL, NULL),
(6, 'VIA-0006', 2, 2, 2, 11, 4, 0.06, 'pendiente', '2026-06-06', '2026-06-05 00:35:44', '2026-06-05 00:35:44', 0.00, 9.00, 0.00, NULL, NULL, NULL, 0, 0.00, '', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `viaje_id` (`viaje_id`);

--
-- Indices de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `conductor_id` (`conductor_id`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ruc` (`ruc`);

--
-- Indices de la tabla `conductores`
--
ALTER TABLE `conductores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `licencia` (`licencia`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `placa` (`placa`);

--
-- Indices de la tabla `viajes`
--
ALTER TABLE `viajes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `conductor_id` (`conductor_id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `ruta_id` (`ruta_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas`
--
ALTER TABLE `alertas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `conductores`
--
ALTER TABLE `conductores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `viajes`
--
ALTER TABLE `viajes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`);

--
-- Filtros para la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD CONSTRAINT `asignaciones_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`),
  ADD CONSTRAINT `asignaciones_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`);

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `viajes`
--
ALTER TABLE `viajes`
  ADD CONSTRAINT `viajes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `viajes_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`),
  ADD CONSTRAINT `viajes_ibfk_3` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`),
  ADD CONSTRAINT `viajes_ibfk_4` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`),
  ADD CONSTRAINT `viajes_ibfk_5` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
