-- Permissões de interface do relatório (Power BI)
-- mostrar_abas: mostra navegação de páginas
-- mostrar_filtros: mostra painel de filtros

ALTER TABLE permissions
ADD COLUMN mostrar_abas TINYINT(1) NOT NULL DEFAULT 0 AFTER pode_visualizar,
ADD COLUMN mostrar_filtros TINYINT(1) NOT NULL DEFAULT 0 AFTER mostrar_abas;
