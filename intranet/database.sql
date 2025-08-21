-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.32-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para fedexvb_intranet
CREATE DATABASE IF NOT EXISTS `fedexvb_intranet` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `fedexvb_intranet`;

-- Volcando estructura para tabla fedexvb_intranet.administradores
CREATE TABLE IF NOT EXISTS `administradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(200) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `administradores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.administradores: ~2 rows (aproximadamente)
INSERT INTO `administradores` (`id`, `usuario_id`, `nombre`, `apellidos`, `telefono`) VALUES
	(1, 16, 'Antonio', 'Gat', '653266205'),
	(14, 42, '', '', NULL);

-- Volcando estructura para tabla fedexvb_intranet.arbitros
CREATE TABLE IF NOT EXISTS `arbitros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(200) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ciudad` varchar(100) NOT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `licencia` enum('anotador','n1','n2','n3') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `arbitros_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.arbitros: ~1 rows (aproximadamente)
INSERT INTO `arbitros` (`id`, `usuario_id`, `nombre`, `apellidos`, `telefono`, `ciudad`, `iban`, `licencia`) VALUES
	(12, 26, 'Antonio', 'Gat Fernández', '653266205', 'Ribera del Fresno / Badajoz', 'ES1231231313131312321312313133', 'n1');

-- Volcando estructura para tabla fedexvb_intranet.categorias
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.categorias: ~10 rows (aproximadamente)
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`) VALUES
	(1, 'Alevín Masculino', 'Categoría Alevín Masculino'),
	(2, 'Alevín Femenino', 'Categoría Alevín Femenino'),
	(3, 'Infantil Masculino', 'Categoría Infantil Masculino'),
	(4, 'Infantil Femenino', 'Categoría Infantil Femenino'),
	(5, 'Cadete Masculino', 'Categoría Cadete Masculino'),
	(6, 'Cadete Femenino', 'Categoría Cadete Femenino'),
	(7, 'Juvenil Masculino', 'Categoría Juvenil Masculino'),
	(8, 'Juvenil Femenino', 'Categoría Juvenil Femenino'),
	(9, 'Senior Masculino', 'Categoría Senior Masculino'),
	(10, 'Senior Femenino', 'Categoría Senior Femenino');

-- Volcando estructura para tabla fedexvb_intranet.clubes
CREATE TABLE IF NOT EXISTS `clubes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nombre_club` varchar(200) NOT NULL,
  `razon_social` varchar(300) DEFAULT NULL,
  `nombre_responsable` varchar(200) NOT NULL,
  `iban` varchar(34) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `clubes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.clubes: ~1 rows (aproximadamente)
INSERT INTO `clubes` (`id`, `usuario_id`, `nombre_club`, `razon_social`, `nombre_responsable`, `iban`) VALUES
	(4, 19, 'CLUB Prueba', 'Pruebas SC', 'Antonio Gat Fernandez', 'ES13131313131313131313');

-- Volcando estructura para tabla fedexvb_intranet.clubes_backup
CREATE TABLE IF NOT EXISTS `clubes_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `usuario_id` int(11) NOT NULL,
  `nombre_club` varchar(200) NOT NULL,
  `razon_social` varchar(300) DEFAULT NULL,
  `nombre_responsable` varchar(200) NOT NULL,
  `iban` varchar(34) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.clubes_backup: ~1 rows (aproximadamente)
INSERT INTO `clubes_backup` (`id`, `usuario_id`, `nombre_club`, `razon_social`, `nombre_responsable`, `iban`) VALUES
	(4, 19, 'CLUB Prueba', 'Pruebas SC', 'Antonio Gat Fernandez', 'ES13131313131313131313');

-- Volcando estructura para tabla fedexvb_intranet.disponibilidad_arbitros
CREATE TABLE IF NOT EXISTS `disponibilidad_arbitros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arbitro_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `disponible` tinyint(1) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_disponibilidad` (`arbitro_id`,`fecha`),
  CONSTRAINT `disponibilidad_arbitros_ibfk_1` FOREIGN KEY (`arbitro_id`) REFERENCES `arbitros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=437 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.disponibilidad_arbitros: ~31 rows (aproximadamente)
INSERT INTO `disponibilidad_arbitros` (`id`, `arbitro_id`, `fecha`, `disponible`, `observaciones`) VALUES
	(364, 12, '2025-08-19', 1, ''),
	(365, 12, '2025-08-01', 1, ''),
	(366, 12, '2025-08-02', 0, ''),
	(367, 12, '2025-08-03', 0, ''),
	(368, 12, '2025-08-04', 1, ''),
	(369, 12, '2025-08-05', 1, ''),
	(370, 12, '2025-08-06', 1, ''),
	(371, 12, '2025-08-07', 1, ''),
	(372, 12, '2025-08-08', 1, ''),
	(373, 12, '2025-08-09', 0, ''),
	(374, 12, '2025-08-10', 0, ''),
	(375, 12, '2025-08-15', 1, ''),
	(376, 12, '2025-08-11', 1, ''),
	(377, 12, '2025-08-14', 1, ''),
	(378, 12, '2025-08-12', 1, ''),
	(379, 12, '2025-08-17', 0, ''),
	(380, 12, '2025-08-13', 1, ''),
	(382, 12, '2025-08-16', 0, ''),
	(383, 12, '2025-08-23', 0, ''),
	(384, 12, '2025-08-18', 1, ''),
	(385, 12, '2025-08-26', 1, ''),
	(386, 12, '2025-08-20', 0, ''),
	(387, 12, '2025-08-28', 1, ''),
	(388, 12, '2025-08-21', 1, ''),
	(389, 12, '2025-08-31', 0, ''),
	(390, 12, '2025-08-22', 1, ''),
	(391, 12, '2025-08-30', 0, ''),
	(392, 12, '2025-08-24', 0, ''),
	(393, 12, '2025-08-29', 1, ''),
	(394, 12, '2025-08-25', 1, ''),
	(395, 12, '2025-08-27', 1, 'holaaa'),
	(437, 12, '2025-09-01', 1, ''),
	(438, 12, '2025-09-16', 1, ''),
	(439, 12, '2025-09-25', 0, ''),
	(443, 12, '2025-09-18', 1, 'hola');

-- Volcando estructura para tabla fedexvb_intranet.documentos_clubes
CREATE TABLE IF NOT EXISTS `documentos_clubes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `club_id` int(11) NOT NULL,
  `nombre_documento` varchar(255) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tipo_archivo` varchar(50) NOT NULL,
  `tamaño_archivo` bigint(20) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_subida` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_club_id` (`club_id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_fecha_subida` (`fecha_subida`),
  KEY `fk_documentos_clubes_usuario` (`usuario_subida`),
  CONSTRAINT `fk_documentos_clubes_club` FOREIGN KEY (`club_id`) REFERENCES `clubes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_documentos_clubes_usuario` FOREIGN KEY (`usuario_subida`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.documentos_clubes: ~4 rows (aproximadamente)
INSERT INTO `documentos_clubes` (`id`, `club_id`, `nombre_documento`, `nombre_archivo`, `ruta_archivo`, `tipo_archivo`, `tamaño_archivo`, `fecha_subida`, `usuario_subida`, `activo`) VALUES
	(7, 4, 'Antonioo PRUEBA txt', 'Nuevo Documento de texto.txt', 'assets/uploads/documentos_clubes/68a4e9ae9ae2a_1755638190.txt', 'txt', 0, '2025-08-19 21:16:30', 16, 1),
	(8, 4, 'Antonioo PRUEBA jpg', 'Imagen de WhatsApp 2025-01-08 a las 23.06.46_ae422387.jpg', 'assets/uploads/documentos_clubes/68a4e9bf011d5_1755638207.jpg', 'jpg', 143477, '2025-08-19 21:16:47', 16, 1),
	(9, 4, 'Antonioo PRUEBA', 'Titulo ESO.pdf', 'assets/uploads/documentos_clubes/68a4e9cf3eef2_1755638223.pdf', 'pdf', 185335, '2025-08-19 21:17:03', 16, 1),
	(10, 4, 'asdasdasd', '25BT00380389_20250116-094718.pdf', 'assets/uploads/documentos_clubes/68a4f181b40d5_1755640193.pdf', 'pdf', 97561, '2025-08-19 21:49:53', 16, 0);

-- Volcando estructura para tabla fedexvb_intranet.equipos
CREATE TABLE IF NOT EXISTS `equipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `club_id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `club_id` (`club_id`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `equipos_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equipos_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.equipos: ~2 rows (aproximadamente)
INSERT INTO `equipos` (`id`, `club_id`, `nombre`, `categoria_id`) VALUES
	(18, 4, 'Antonio PRUEBA', 1),
	(19, 4, 'Antonioo PRUEBA', 2);

-- Volcando estructura para tabla fedexvb_intranet.jugadores
CREATE TABLE IF NOT EXISTS `jugadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(200) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  KEY `equipo_id` (`equipo_id`),
  CONSTRAINT `jugadores_ibfk_1` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.jugadores: ~0 rows (aproximadamente)

-- Volcando estructura para tabla fedexvb_intranet.licencias_arbitros
CREATE TABLE IF NOT EXISTS `licencias_arbitros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arbitro_id` int(11) NOT NULL,
  `fecha_curso` date NOT NULL,
  `lugar_curso` varchar(200) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `nivel_licencia` enum('anotador','n1','n2','n3') NOT NULL,
  `activa` tinyint(1) DEFAULT 1,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_arbitro_licencia` (`arbitro_id`),
  KEY `idx_fecha_vencimiento` (`fecha_vencimiento`),
  KEY `idx_activa` (`activa`),
  CONSTRAINT `licencias_arbitros_ibfk_1` FOREIGN KEY (`arbitro_id`) REFERENCES `arbitros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.licencias_arbitros: ~1 rows (aproximadamente)
INSERT INTO `licencias_arbitros` (`id`, `arbitro_id`, `fecha_curso`, `lugar_curso`, `fecha_inicio`, `fecha_vencimiento`, `nivel_licencia`, `activa`, `observaciones`, `fecha_creacion`, `fecha_actualizacion`) VALUES
	(4, 12, '2024-11-11', 'Mérida', '2024-11-25', '2025-08-25', 'n1', 1, 'PRUEBA', '2025-08-19 21:41:41', '2025-08-19 21:46:37');

-- Volcando estructura para tabla fedexvb_intranet.liquidaciones
CREATE TABLE IF NOT EXISTS `liquidaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arbitro_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `numero_partidos` int(11) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `estado` enum('pendiente','aprobada','pagada','rectificacion') DEFAULT 'pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `arbitro_id` (`arbitro_id`),
  CONSTRAINT `liquidaciones_ibfk_1` FOREIGN KEY (`arbitro_id`) REFERENCES `arbitros` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.liquidaciones: ~2 rows (aproximadamente)
INSERT INTO `liquidaciones` (`id`, `arbitro_id`, `fecha_inicio`, `fecha_fin`, `numero_partidos`, `observaciones`, `estado`, `fecha_creacion`) VALUES
	(10, 12, '2025-08-18', '2025-08-29', 0, '', 'pagada', '2025-08-19 22:38:02'),
	(11, 12, '2025-08-13', '2025-08-31', 0, '', 'pendiente', '2025-08-20 12:03:44');

-- Volcando estructura para tabla fedexvb_intranet.liquidaciones_partidos
CREATE TABLE IF NOT EXISTS `liquidaciones_partidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `liquidacion_id` int(11) NOT NULL,
  `partido_id` int(11) NOT NULL,
  `rol_arbitro` varchar(50) NOT NULL,
  `importe_partido` decimal(10,2) DEFAULT 0.00,
  `importe_dieta` decimal(10,2) DEFAULT 0.00,
  `importe_kilometraje` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `liquidacion_id` (`liquidacion_id`),
  KEY `partido_id` (`partido_id`),
  CONSTRAINT `liquidaciones_partidos_ibfk_1` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `liquidaciones_partidos_ibfk_2` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.liquidaciones_partidos: ~3 rows (aproximadamente)
INSERT INTO `liquidaciones_partidos` (`id`, `liquidacion_id`, `partido_id`, `rol_arbitro`, `importe_partido`, `importe_dieta`, `importe_kilometraje`) VALUES
	(57, 10, 15, 'Anotador', 8.00, 14.00, 55.00),
	(58, 11, 15, '1º Árbitro', 10.00, 14.00, 0.00),
	(59, 11, 16, '1º Árbitro', 12.00, 14.00, 0.00);

-- Volcando estructura para tabla fedexvb_intranet.liquidacion_detalles
CREATE TABLE IF NOT EXISTS `liquidacion_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `liquidacion_id` int(11) NOT NULL,
  `partido_id` int(11) NOT NULL,
  `cantidad_partido` decimal(8,2) DEFAULT 0.00,
  `dieta` decimal(8,2) DEFAULT 0.00,
  `kilometraje` decimal(8,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `liquidacion_id` (`liquidacion_id`),
  KEY `partido_id` (`partido_id`),
  CONSTRAINT `liquidacion_detalles_ibfk_1` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `liquidacion_detalles_ibfk_2` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.liquidacion_detalles: ~0 rows (aproximadamente)

-- Volcando estructura para tabla fedexvb_intranet.pabellones
CREATE TABLE IF NOT EXISTS `pabellones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `ciudad` varchar(100) NOT NULL,
  `direccion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.pabellones: ~6 rows (aproximadamente)
INSERT INTO `pabellones` (`id`, `nombre`, `ciudad`, `direccion`) VALUES
	(1, 'Pabellón Municipal de Badajoz', 'Badajoz', 'Av. Europa, 1, 06004 Badajoz'),
	(2, 'Palacio de Deportes de Mérida', 'Mérida', 'C/ Reyes Huertas, s/n, 06800 Mérida'),
	(3, 'Pabellón Ciudad de Cáceres', 'Cáceres', 'Av. Virgen de Guadalupe, 10003 Cáceres'),
	(4, 'Polideportivo San Fernando', 'Badajoz', 'C/ San Fernando, 45, 06006 Badajoz'),
	(5, 'Pabellón IES Al-Qázeres', 'Cáceres', 'C/ Compositor Ángel Barja, 10005 Cáceres'),
	(6, 'Antonio Prueba', 'Ribera', 'asdf');

-- Volcando estructura para tabla fedexvb_intranet.partidos
CREATE TABLE IF NOT EXISTS `partidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_local_id` int(11) NOT NULL,
  `equipo_visitante_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `pabellon_id` int(11) NOT NULL,
  `arbitro_principal_id` int(11) DEFAULT NULL,
  `arbitro_segundo_id` int(11) DEFAULT NULL,
  `anotador_id` int(11) DEFAULT NULL,
  `finalizado` tinyint(1) DEFAULT 0,
  `sets_local` int(11) DEFAULT NULL,
  `sets_visitante` int(11) DEFAULT NULL,
  `estado` enum('programado','finalizado','cancelado') DEFAULT 'programado',
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `equipo_local_id` (`equipo_local_id`),
  KEY `equipo_visitante_id` (`equipo_visitante_id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `pabellon_id` (`pabellon_id`),
  KEY `arbitro_principal_id` (`arbitro_principal_id`),
  KEY `arbitro_segundo_id` (`arbitro_segundo_id`),
  KEY `anotador_id` (`anotador_id`),
  CONSTRAINT `partidos_ibfk_1` FOREIGN KEY (`equipo_local_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `partidos_ibfk_2` FOREIGN KEY (`equipo_visitante_id`) REFERENCES `equipos` (`id`),
  CONSTRAINT `partidos_ibfk_3` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `partidos_ibfk_4` FOREIGN KEY (`pabellon_id`) REFERENCES `pabellones` (`id`),
  CONSTRAINT `partidos_ibfk_5` FOREIGN KEY (`arbitro_principal_id`) REFERENCES `arbitros` (`id`),
  CONSTRAINT `partidos_ibfk_6` FOREIGN KEY (`arbitro_segundo_id`) REFERENCES `arbitros` (`id`),
  CONSTRAINT `partidos_ibfk_7` FOREIGN KEY (`anotador_id`) REFERENCES `arbitros` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.partidos: ~2 rows (aproximadamente)
INSERT INTO `partidos` (`id`, `equipo_local_id`, `equipo_visitante_id`, `categoria_id`, `fecha`, `pabellon_id`, `arbitro_principal_id`, `arbitro_segundo_id`, `anotador_id`, `finalizado`, `sets_local`, `sets_visitante`, `estado`, `fecha_actualizacion`) VALUES
	(15, 18, 19, 1, '2025-08-19 20:00:00', 1, 12, 12, 12, 0, 2, 1, 'finalizado', '2025-08-20 11:47:47'),
	(16, 18, 19, 1, '2025-08-20 12:00:00', 3, 12, 12, 12, 0, NULL, NULL, 'programado', '2025-08-20 11:51:42');

-- Volcando estructura para tabla fedexvb_intranet.rectificaciones_liquidaciones
CREATE TABLE IF NOT EXISTS `rectificaciones_liquidaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `liquidacion_id` int(11) NOT NULL,
  `arbitro_id` int(11) NOT NULL,
  `motivo` varchar(100) NOT NULL,
  `observaciones` text NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `respuesta_admin` text DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_respuesta` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `liquidacion_id` (`liquidacion_id`),
  KEY `arbitro_id` (`arbitro_id`),
  CONSTRAINT `rectificaciones_liquidaciones_ibfk_1` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rectificaciones_liquidaciones_ibfk_2` FOREIGN KEY (`arbitro_id`) REFERENCES `arbitros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.rectificaciones_liquidaciones: ~2 rows (aproximadamente)
INSERT INTO `rectificaciones_liquidaciones` (`id`, `liquidacion_id`, `arbitro_id`, `motivo`, `observaciones`, `estado`, `respuesta_admin`, `fecha_solicitud`, `fecha_respuesta`) VALUES
	(15, 10, 12, 'error_importe', '8 es poco', 'rechazada', 'que te den', '2025-08-19 22:44:23', '2025-08-19 22:45:10'),
	(16, 11, 12, 'partido_faltante', 'el del sabado', 'rechazada', 'asd', '2025-08-20 12:04:31', '2025-08-20 12:10:09');

-- Volcando estructura para tabla fedexvb_intranet.sets_partidos
CREATE TABLE IF NOT EXISTS `sets_partidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partido_id` int(11) NOT NULL,
  `numero_set` int(11) NOT NULL,
  `puntos_local` int(11) NOT NULL,
  `puntos_visitante` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_partido` (`partido_id`),
  CONSTRAINT `sets_partidos_ibfk_1` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.sets_partidos: ~3 rows (aproximadamente)
INSERT INTO `sets_partidos` (`id`, `partido_id`, `numero_set`, `puntos_local`, `puntos_visitante`, `fecha_creacion`) VALUES
	(1, 15, 1, 25, 3, '2025-08-20 11:38:21'),
	(2, 15, 2, 25, 23, '2025-08-20 11:38:21'),
	(3, 15, 3, 25, 22, '2025-08-20 11:38:21');

-- Volcando estructura para tabla fedexvb_intranet.tecnicos
CREATE TABLE IF NOT EXISTS `tecnicos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(200) NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nivel` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tecnicos_dni` (`dni`),
  KEY `equipo_id` (`equipo_id`),
  CONSTRAINT `tecnicos_ibfk_1` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.tecnicos: ~0 rows (aproximadamente)

-- Volcando estructura para tabla fedexvb_intranet.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_usuario` enum('administrador','arbitro','club') NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_temporal` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla fedexvb_intranet.usuarios: ~4 rows (aproximadamente)
INSERT INTO `usuarios` (`id`, `tipo_usuario`, `email`, `password`, `password_temporal`, `fecha_creacion`, `activo`) VALUES
	(16, 'administrador', 'admin@gmail.com', '$2y$10$WQo.ToeWzNhajlFGQnxhB.g2nELSp/nFowuOfPIFLf4J1Os5dmibK', 0, '2025-08-10 23:20:07', 1),
	(19, 'club', 'club@gmail.com', '$2y$10$1oF.ZqNqYKl3tIXFEoHLg.n1STFkOQZRJ7u7zO3u8PtzWQvfFAbpK', 0, '2025-08-11 09:10:28', 1),
	(26, 'arbitro', 'arbitro@gmail.com', '$2y$10$W5wltB6jJx14o587zuZof.mCEt8N5J0arQgqjIlfwYhmfpfsVyJPW', 0, '2025-08-19 21:01:46', 1),
	(42, 'administrador', 'asdasd@gmail.com', '$2y$10$iyBUmhGJDKcXWD/P8NRHV.A3zzKna49YjqEKd5xllhvd2pAkkKQZi', 1, '2025-08-20 10:34:51', 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
