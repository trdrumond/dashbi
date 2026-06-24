<?php

/**
 * Configuração Azure AD e Power BI
 * Service Principal (client_credentials) - não autentica usuário final no Power BI.
 * PHP 7.3+
 */

return [
    // URLs (use %s como placeholder do Tenant ID)
    'token_url' => 'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
    'powerbi_api_base' => 'https://api.powerbi.com/v1.0/myorg',
    'scope' => 'https://analysis.windows.net/powerbi/api/.default',

    // RLS (Row Level Security) - nome da role criada no dataset Power BI
    // Ex.: [empresa_id] = USERPRINCIPALNAME() ou [representante_email] = USERPRINCIPALNAME()
    'rls_role_default' => 'RLS_ROLE',

    // Cache de access token (margem em segundos antes de expirar)
    'token_cache_margin_seconds' => 60,
];
