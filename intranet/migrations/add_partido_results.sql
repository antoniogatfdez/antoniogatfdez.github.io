-- Migración para agregar campos de resultado a la tabla partidos
-- Fecha: 2025-08-20

-- Verificar si las columnas existen antes de agregarlas
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'fedexvb_intranet' 
     AND TABLE_NAME = 'partidos' 
     AND COLUMN_NAME = 'sets_local') = 0,
    'ALTER TABLE `partidos` ADD COLUMN `sets_local` int(11) DEFAULT NULL AFTER `anotador_id`',
    'SELECT "Column sets_local already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'fedexvb_intranet' 
     AND TABLE_NAME = 'partidos' 
     AND COLUMN_NAME = 'sets_visitante') = 0,
    'ALTER TABLE `partidos` ADD COLUMN `sets_visitante` int(11) DEFAULT NULL AFTER `sets_local`',
    'SELECT "Column sets_visitante already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'fedexvb_intranet' 
     AND TABLE_NAME = 'partidos' 
     AND COLUMN_NAME = 'estado') = 0,
    'ALTER TABLE `partidos` ADD COLUMN `estado` enum("programado","en_curso","finalizado","cancelado") DEFAULT "programado" AFTER `sets_visitante`',
    'SELECT "Column estado already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'fedexvb_intranet' 
     AND TABLE_NAME = 'partidos' 
     AND COLUMN_NAME = 'fecha_actualizacion') = 0,
    'ALTER TABLE `partidos` ADD COLUMN `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `estado`',
    'SELECT "Column fecha_actualizacion already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Crear tabla para almacenar detalles de cada set si no existe
CREATE TABLE IF NOT EXISTS `sets_partidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partido_id` int(11) NOT NULL,
  `numero_set` int(11) NOT NULL,
  `puntos_local` int(11) NOT NULL,
  `puntos_visitante` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `partido_id` (`partido_id`),
  CONSTRAINT `sets_partidos_ibfk_1` FOREIGN KEY (`partido_id`) REFERENCES `partidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actualizar partidos existentes finalizados (si los hay)
UPDATE `partidos` SET `estado` = 'finalizado' WHERE `finalizado` = 1;
