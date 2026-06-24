-- Sistema de Favoritos: favoritar relatórios e fixar na dashboard
USE bi_portal;

CREATE TABLE IF NOT EXISTS user_relatorio_favorito (
    user_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NOT NULL,
    fixado TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = fixado na dashboard',
    ordem INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordem dos fixados (menor = primeiro)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, report_id),
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
    INDEX idx_favorito_user (user_id),
    INDEX idx_favorito_fixado (user_id, fixado, ordem)
) ENGINE = InnoDB;