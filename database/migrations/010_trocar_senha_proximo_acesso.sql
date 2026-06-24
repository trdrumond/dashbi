-- Flag para obrigar troca de senha no próximo acesso (ex.: após reset por e-mail)
ALTER TABLE users ADD COLUMN trocar_senha_proximo_acesso TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = usuário deve trocar a senha no próximo login'
    AFTER ativo;
