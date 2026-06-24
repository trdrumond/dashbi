-- Dono do relatório como texto livre (input editável)
-- Execute uma vez em bancos já existentes.

USE bi_portal;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'dono_texto');
SET @sql = IF(@col = 0, 'ALTER TABLE reports ADD COLUMN dono_texto VARCHAR(255) NULL COMMENT ''Dono do relatório (texto livre, editável)'' AFTER dono_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
