-- Grupos e usuários vinculados a pastas (acesso por pasta, não por relatório individual)
-- Tipos INT UNSIGNED para coincidir com groups.id, users.id, folders.id

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS group_folder (
    group_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, folder_id),
    CONSTRAINT fk_group_folder_group FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE,
    CONSTRAINT fk_group_folder_folder FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE,
    INDEX idx_group_folder_folder (folder_id)
) ENGINE = InnoDB DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_folder (
    user_id INT UNSIGNED NOT NULL,
    folder_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, folder_id),
    CONSTRAINT fk_user_folder_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_folder_folder FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE,
    INDEX idx_user_folder_folder (folder_id)
) ENGINE = InnoDB DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 1;
