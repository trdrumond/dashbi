-- Pastas de relatórios e relação N:N report_folder
-- Tipos INT UNSIGNED para coincidir com reports.id e folders.id (evitar errno 150)
-- Recriamos folders para garantir PRIMARY KEY em id (exige índice na tabela referenciada).

SET FOREIGN_KEY_CHECKS = 0;

-- Remover tabelas na ordem correta (report_folder pode referenciar folders)
DROP TABLE IF EXISTS report_folder;
DROP TABLE IF EXISTS folders;

-- Recriar folders com PRIMARY KEY explícito em id (obrigatório para FK)
CREATE TABLE folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_folders_ativo (ativo)
) ENGINE = InnoDB DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE report_folder (
    folder_id INT UNSIGNED NOT NULL,
    report_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (folder_id, report_id),
    INDEX idx_report_folder_report (report_id),
    INDEX idx_report_folder_folder (folder_id),
    CONSTRAINT fk_report_folder_folder FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE,
    CONSTRAINT fk_report_folder_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 1;
