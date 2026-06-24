-- Opções avançadas do Painel TV
-- modo_reproducao: loop (reinicia ao fim) ou once (encerra ao fim)
-- mostrar_contador: exibe contador regressivo por item durante a reprodução

ALTER TABLE tv_panels
ADD COLUMN modo_reproducao VARCHAR(20) NOT NULL DEFAULT 'loop' AFTER ativo,
ADD COLUMN mostrar_contador TINYINT(1) NOT NULL DEFAULT 1 AFTER modo_reproducao;
