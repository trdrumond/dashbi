-- Auditoria completa de uso: log de acesso por relatório com tempo e filtros
-- Permite: relatórios mais acessados, usuários ativos, relatórios esquecidos, horários de pico

USE bi_portal;

CREATE TABLE IF NOT EXISTS log_acesso_relatorio (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    relatorio_id INT UNSIGNED NOT NULL,
    data_acesso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    filtros_utilizados LONGTEXT NULL COMMENT 'Filtros aplicados pelo usuário (JSON)',
    tempo_visualizacao INT UNSIGNED NULL COMMENT 'Tempo em segundos na visualização',
    ip VARCHAR(45) NULL,
    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (relatorio_id) REFERENCES reports(id) ON DELETE CASCADE,
    INDEX idx_log_acesso_usuario (usuario_id),
    INDEX idx_log_acesso_relatorio (relatorio_id),
    INDEX idx_log_acesso_data (data_acesso)
) ENGINE=InnoDB;
