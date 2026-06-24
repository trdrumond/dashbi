-- Imagem de identificação por relatório (opcional)
-- Se NULL, o frontend usa img/dashbi02.png como padrão.

ALTER TABLE reports
ADD COLUMN imagem VARCHAR(500) NULL COMMENT 'Caminho da imagem (ex.: img/reports/5.png)' AFTER nome;
