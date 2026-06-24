-- Adiciona seleção de página do relatório por item do painel TV (uma página por item)
ALTER TABLE tv_panel_items
    ADD COLUMN pagina_nome VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nome da página (aba) do relatório a exibir; NULL = página padrão' AFTER tempo_segundos;
