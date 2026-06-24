-- Catálogo Inteligente de Relatórios: descrição, dono, última atualização, indicadores, tags por área
-- Execute uma vez em bancos já existentes.

USE bi_portal;

-- Descrição funcional
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'descricao');
SET @sql = IF(@col = 0, 'ALTER TABLE reports ADD COLUMN descricao TEXT NULL COMMENT ''Descrição funcional do relatório'' AFTER nome', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Dono do relatório (usuário responsável)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'dono_id');
SET @sql = IF(@col = 0, 'ALTER TABLE reports ADD COLUMN dono_id INT UNSIGNED NULL COMMENT ''ID do usuário dono do relatório'' AFTER descricao', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND CONSTRAINT_NAME = 'fk_reports_dono');
SET @sql = IF(@fk = 0, 'ALTER TABLE reports ADD CONSTRAINT fk_reports_dono FOREIGN KEY (dono_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Data da última atualização (do relatório/dataset)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'ultima_atualizacao');
SET @sql = IF(@col = 0, 'ALTER TABLE reports ADD COLUMN ultima_atualizacao DATETIME NULL COMMENT ''Data da última atualização do relatório'' AFTER dono_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Indicadores principais exibidos (texto livre, ex: "Faturamento, Margem, Inadimplência")
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'indicadores_principais');
SET @sql = IF(@col = 0, 'ALTER TABLE reports ADD COLUMN indicadores_principais TEXT NULL COMMENT ''Indicadores principais exibidos (texto livre)'' AFTER ultima_atualizacao', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tags por área (JSON: ["Financeiro","Operacional","Comercial"])
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'area_tags');
SET @sql = IF(@col = 0, 'ALTER TABLE reports ADD COLUMN area_tags LONGTEXT NULL COMMENT ''Tags por área (JSON). Ex.: Financeiro, Operacional, Comercial'' AFTER indicadores_principais', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para filtrar por dono
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND INDEX_NAME = 'idx_reports_dono');
SET @sql = IF(@idx = 0, 'ALTER TABLE reports ADD INDEX idx_reports_dono (dono_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
