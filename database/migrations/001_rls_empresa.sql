-- Migração: empresa_id em users, rls_role em reports (RLS e multi-tenant)
-- Execute uma vez em bancos já existentes. Novas instalações usam schema.sql.

USE bi_portal;

-- Coluna empresa_id em users (para RLS EffectiveIdentity)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'empresa_id');
SET @sql = IF(@col = 0, 'ALTER TABLE users ADD COLUMN empresa_id VARCHAR(100) NULL COMMENT ''Para RLS multi-tenant'' AFTER perfil, ADD INDEX idx_users_empresa (empresa_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Coluna rls_role em reports
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'bi_portal' AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'rls_role');
SET @sql = IF(@col = 0, 'ALTER TABLE reports ADD COLUMN rls_role VARCHAR(100) NULL COMMENT ''Nome da role RLS no dataset'' AFTER dataset_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
