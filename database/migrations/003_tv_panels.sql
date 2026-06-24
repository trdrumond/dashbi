-- Painel TV (playlist de relatórios com tempo por item)

CREATE TABLE IF NOT EXISTS tv_panels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    criado_por INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_tv_panels_ativo (ativo),
    INDEX idx_tv_panels_criado_por (criado_por)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tv_panel_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    panel_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NOT NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 1,
    tempo_segundos INT UNSIGNED NOT NULL DEFAULT 30,
    FOREIGN KEY (panel_id) REFERENCES tv_panels(id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    INDEX idx_tv_panel_items_panel (panel_id),
    INDEX idx_tv_panel_items_report (report_id),
    INDEX idx_tv_panel_items_ordem (panel_id, ordem)
) ENGINE=InnoDB;
