-- DashBI 2.0 - MySQL 5.7+
-- Banco: web_dashbi_2

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS web_dashbi_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE web_dashbi_2;

-- Tenants (conexão Microsoft)
CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    tenant_id VARCHAR(100) NOT NULL COMMENT 'Azure AD Tenant ID',
    client_id VARCHAR(100) NOT NULL,
    client_secret TEXT NOT NULL COMMENT 'Criptografado',
    workspace_id VARCHAR(100) NULL COMMENT 'Workspace padrão Power BI (groupId)',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenants_ativo (ativo)
) ENGINE = InnoDB;

-- Workspaces Power BI sincronizados
CREATE TABLE IF NOT EXISTS workspaces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    powerbi_workspace_id VARCHAR(100) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    sincronizado_em TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    UNIQUE KEY uk_workspace_tenant (
        powerbi_workspace_id,
        tenant_id
    ),
    INDEX idx_workspaces_tenant (tenant_id)
) ENGINE = InnoDB;

-- Relatórios Power BI (Catálogo Inteligente: descrição, dono, última atualização, indicadores, tags)
CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    powerbi_report_id VARCHAR(100) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL COMMENT 'Descrição funcional do relatório',
    dono_id INT UNSIGNED NULL COMMENT 'ID do usuário dono do relatório',
    dono_texto VARCHAR(255) NULL COMMENT 'Dono do relatório (texto livre, editável)',
    ultima_atualizacao DATETIME NULL COMMENT 'Data da última atualização do relatório',
    indicadores_principais TEXT NULL COMMENT 'Indicadores principais exibidos (texto livre)',
    area_tags LONGTEXT NULL COMMENT 'Tags por área (JSON). Ex.: Financeiro, Operacional, Comercial',
    imagem VARCHAR(500) NULL COMMENT 'Imagem de identificação (ex.: img/reports/5.png). Se NULL, usa img/dashbi02.png',
    embed_url TEXT NULL,
    dataset_id VARCHAR(100) NULL,
    rls_role VARCHAR(100) NULL COMMENT 'Nome da role RLS no dataset (ex.: RLS_ROLE). Se preenchido, embed usa EffectiveIdentity.',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (workspace_id) REFERENCES workspaces (id) ON DELETE CASCADE,
    FOREIGN KEY (dono_id) REFERENCES users (id) ON DELETE SET NULL,
    UNIQUE KEY uk_report_workspace (
        powerbi_report_id,
        workspace_id
    ),
    INDEX idx_reports_workspace (workspace_id),
    INDEX idx_reports_ativo (ativo),
    INDEX idx_reports_dono (dono_id)
) ENGINE = InnoDB;

-- Usuários do sistema (login interno)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(50) NOT NULL DEFAULT 'usuario' COMMENT 'master, admin, gestor, supervisor, usuario',
    empresa_id VARCHAR(100) NULL COMMENT 'Para RLS multi-tenant: valor usado em EffectiveIdentity (username)',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    trocar_senha_proximo_acesso TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = usuário deve trocar a senha no próximo login',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users_email (email),
    INDEX idx_users_ativo (ativo),
    INDEX idx_users_empresa (empresa_id)
) ENGINE = InnoDB;

-- Grupos de usuários
CREATE TABLE IF NOT EXISTS groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao VARCHAR(500) NULL
) ENGINE = InnoDB;

-- Associação usuário-grupo
CREATE TABLE IF NOT EXISTS user_group (
    user_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE,
    INDEX idx_user_group_group (group_id)
) ENGINE = InnoDB;

-- Permissões por relatório (usuário ou grupo). Regra: user_id OU group_id preenchido (validado na aplicação).
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    group_id INT UNSIGNED NULL,
    pode_visualizar TINYINT(1) NOT NULL DEFAULT 1,
    mostrar_abas TINYINT(1) NOT NULL DEFAULT 0,
    mostrar_filtros TINYINT(1) NOT NULL DEFAULT 0,
    pode_exportar TINYINT(1) NOT NULL DEFAULT 0,
    pode_compartilhar TINYINT(1) NOT NULL DEFAULT 0,
    pagina_restrita LONGTEXT NULL COMMENT 'Páginas permitidas (JSON). Ex.: ["Page1","Page3"]',
    filtro_fixo LONGTEXT NULL COMMENT 'Filtro obrigatório por grupo (JSON)',
    data_inicio DATE NULL,
    data_fim DATE NULL,
    FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE,
    INDEX idx_permissions_report (report_id),
    INDEX idx_permissions_user (user_id),
    INDEX idx_permissions_group (group_id),
    INDEX idx_permissions_dates (data_inicio, data_fim)
) ENGINE = InnoDB;

-- Log de acesso (auditoria)
CREATE TABLE IF NOT EXISTS access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NOT NULL,
    acao VARCHAR(50) NOT NULL COMMENT 'view, export, deny',
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_access_logs_user (user_id),
    INDEX idx_access_logs_report (report_id),
    INDEX idx_access_logs_created (created_at)
) ENGINE = InnoDB;

-- Auditoria completa de uso (tempo de visualização, filtros, horários de pico)
CREATE TABLE IF NOT EXISTS log_acesso_relatorio (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    relatorio_id INT UNSIGNED NOT NULL,
    data_acesso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    filtros_utilizados LONGTEXT NULL COMMENT 'Filtros aplicados pelo usuário (JSON)',
    tempo_visualizacao INT UNSIGNED NULL COMMENT 'Segundos na visualização',
    ip VARCHAR(45) NULL,
    FOREIGN KEY (usuario_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (relatorio_id) REFERENCES reports (id) ON DELETE CASCADE,
    INDEX idx_log_acesso_usuario (usuario_id),
    INDEX idx_log_acesso_relatorio (relatorio_id),
    INDEX idx_log_acesso_data (data_acesso)
) ENGINE = InnoDB;

-- Favoritos e fixados na dashboard (por usuário)
CREATE TABLE IF NOT EXISTS user_relatorio_favorito (
    user_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NOT NULL,
    fixado TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, report_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
    INDEX idx_favorito_user (user_id),
    INDEX idx_favorito_fixado (user_id, fixado, ordem)
) ENGINE = InnoDB;

-- Solicitações de acesso (Fase 3)
CREATE TABLE IF NOT EXISTS access_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    aprovado_por INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
    INDEX idx_access_requests_status (status)
) ENGINE = InnoDB;

-- Painel TV (playlist de relatórios com tempo por item)
CREATE TABLE IF NOT EXISTS tv_panels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    criado_por INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    modo_reproducao VARCHAR(20) NOT NULL DEFAULT 'loop' COMMENT 'loop|once',
    mostrar_contador TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES users (id) ON DELETE RESTRICT,
    INDEX idx_tv_panels_ativo (ativo),
    INDEX idx_tv_panels_criado_por (criado_por)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS tv_panel_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    panel_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NOT NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    tempo_segundos INT UNSIGNED NOT NULL DEFAULT 30,
    FOREIGN KEY (panel_id) REFERENCES tv_panels (id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
    INDEX idx_tv_panel_items_panel (panel_id),
    INDEX idx_tv_panel_items_report (report_id),
    INDEX idx_tv_panel_items_ordem (panel_id, ordem)
) ENGINE = InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Usuário inicial: email admin@localhost, senha "password" (trocar após primeiro login)
INSERT INTO
    users (
        nome,
        email,
        senha_hash,
        perfil,
        ativo
    )
VALUES (
        'Administrador',
        'admin@localhost',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'master',
        1
    )
ON DUPLICATE KEY UPDATE
    id = id;