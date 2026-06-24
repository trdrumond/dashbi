(function() {
    var BASE = '';
    function api(path, opts) {
        opts = opts || {};
        opts.credentials = 'include';
        opts.headers = opts.headers || {};
        if (opts.body !== undefined && typeof opts.body !== 'string') opts.body = JSON.stringify(opts.body);
        opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
        return fetch((BASE || '') + 'api/' + path, opts)
            .then(function(r) {
                return r.text().then(function(txt) {
                    var d = null;
                    try {
                        d = txt ? JSON.parse(txt) : {};
                    } catch (e) {
                        var preview = (txt || '').replace(/\s+/g, ' ').trim().slice(0, 180);
                        d = {
                            success: false,
                            message: 'Resposta inválida do servidor (HTTP ' + r.status + '). ' + (preview ? ('Trecho: ' + preview) : 'Verifique o rewrite/URL da API.')
                        };
                    }
                    return { status: r.status, data: d };
                });
            })
            .catch(function(err) {
                return {
                    status: 0,
                    data: {
                        success: false,
                        message: 'Falha de conexão com a API: ' + (err && err.message ? err.message : 'erro desconhecido')
                    }
                };
            });
    }
    function msg(txt, ok) {
        var m = document.getElementById('msg');
        m.textContent = txt;
        m.className = 'msg ' + (ok ? 'ok' : 'err');
        m.style.display = 'block';
        setTimeout(function() { m.style.display = 'none'; }, 4000);
    }
    function modal(titulo, corpoHtml, botoes) {
        document.getElementById('modalTitulo').textContent = titulo;
        document.getElementById('modalCorpo').innerHTML = corpoHtml;
        var r = document.getElementById('modalRodape');
        r.innerHTML = '';
        (botoes || []).forEach(function(b) {
            var btn = document.createElement('button');
            btn.className = 'btn ' + (b.sec ? 'sec' : '');
            btn.textContent = b.label;
            // Não fechar automaticamente: a ação decide quando fechar (ex.: apenas após sucesso no save).
            btn.onclick = function() { if (b.fn) b.fn(); };
            r.appendChild(btn);
        });
        document.getElementById('modal').classList.add('ativo');
    }
    function fecharModal() { document.getElementById('modal').classList.remove('ativo'); }
    function modalSavingController(messageElId, savingLabel, idleLabel) {
        var rodape = document.getElementById('modalRodape');
        var botoes = rodape ? [].slice.call(rodape.querySelectorAll('button')) : [];
        var salvarBtn = botoes.length > 1 ? botoes[1] : null;
        var msgEl = messageElId ? document.getElementById(messageElId) : null;
        return function(state, texto) {
            botoes.forEach(function(b) { b.disabled = state; });
            if (salvarBtn) salvarBtn.textContent = state ? (savingLabel || 'Salvando...') : (idleLabel || 'Salvar');
            if (msgEl) {
                msgEl.style.display = state ? 'block' : 'none';
                msgEl.textContent = texto || 'Processando, aguarde...';
            }
        };
    }

    function carregarTenants() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Tenants (conexão Microsoft) <button class="btn" id="btnNovoTenant">Novo tenant</button></h2><p class="carregando">Carregando…</p>';
        api('tenants').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            if (!x.data.success) { p.innerHTML = '<h2>Tenants</h2><p class="erro">' + (x.data.message || 'Erro') + '</p>'; return; }
            var list = x.data.data || [];
            var html = '<h2>Tenants (conexão Microsoft) <button class="btn" id="btnNovoTenant">Novo tenant</button></h2>';
            if (list.length === 0) html += '<p class="vazio">Nenhum tenant. Clique em Novo tenant para cadastrar a conexão com o Microsoft Azure.</p>';
            else {
                html += '<table><tr><th>ID</th><th>Nome</th><th>Tenant ID</th><th>Workspace ID</th><th>Ativo</th><th>Ações</th></tr>';
                list.forEach(function(t) {
                    html += '<tr><td>' + t.id + '</td><td>' + (t.nome || '') + '</td><td>' + (t.tenant_id || '') + '</td><td>' + (t.workspace_id || '-') + '</td><td>' + (t.ativo ? 'Sim' : 'Não') + '</td><td>' +
                        '<button class="btn btn sm" data-tenant-id="' + t.id + '" data-tenant-editar>Editar</button>' +
                        '<button class="btn btn sm sec" data-tenant-id="' + t.id + '" data-tenant-testar>Testar</button>' +
                        '<button class="btn btn sm danger" data-tenant-id="' + t.id + '" data-tenant-excluir>Excluir</button></td></tr>';
                });
                html += '</table>';
            }
            p.innerHTML = html;
            document.getElementById('btnNovoTenant').onclick = function() { formTenant(); };
            p.querySelectorAll('[data-tenant-editar]').forEach(function(btn) { btn.onclick = function() { formTenant(parseInt(btn.dataset.tenantId)); }; });
            p.querySelectorAll('[data-tenant-testar]').forEach(function(btn) { btn.onclick = function() {
                var original = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Testando...';
                var id = parseInt(btn.dataset.tenantId);
                api('tenants/' + id + '/test', { method: 'POST' }).then(function(r) {
                    msg(r.data.message || (r.data.data && r.data.data.connected ? 'Conexão OK' : 'Falha'), r.data.success);
                }).finally(function() {
                    btn.disabled = false;
                    btn.textContent = original;
                });
            }; });
            p.querySelectorAll('[data-tenant-excluir]').forEach(function(btn) { btn.onclick = function() {
                if (!confirm('Excluir este tenant?')) return;
                api('tenants/' + btn.dataset.tenantId, { method: 'DELETE' }).then(function(r) { msg(r.data.message, r.data.success); if (r.data.success) carregarTenants(); });
            }; });
        });
    }
    function formTenant(id) {
        var titulo = id ? 'Editar tenant' : 'Novo tenant';
        var corpo = '<div class="form-g"><label>Nome</label><input type="text" id="t_nome" required></div>' +
            '<div class="form-g"><label>Tenant ID (Azure)</label><input type="text" id="t_tenant_id" required></div>' +
            '<div class="form-g"><label>Client ID</label><input type="text" id="t_client_id" required></div>' +
            '<div class="form-g"><label>Client Secret</label><input type="password" id="t_client_secret" placeholder="Cole o VALOR do secret" autocomplete="off"><p style="margin:0.35rem 0 0;font-size:0.8rem;color:#6b7280;">Use o <strong>Valor</strong> do secret (não o ID). No Azure: Registro de aplicativo → Certificados e segredos → ao criar o secret, copie o campo &quot;Valor&quot;.</p></div>' +
            '<div class="form-g"><label>Workspace ID (opcional)</label><input type="text" id="t_workspace_id"></div>' +
            '<div class="form-g"><label><input type="checkbox" id="t_ativo" checked> Ativo</label></div>' +
            '<div id="t_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
        modal(titulo, corpo, [
            { label: 'Cancelar', sec: true, fn: fecharModal },
            { label: 'Salvar', fn: function() {
                var setSaving = modalSavingController('t_saving', 'Salvando...', 'Salvar');
                var secret = document.getElementById('t_client_secret').value.trim();
                if (secret && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(secret)) {
                    if (!confirm('O valor informado parece ser o ID do secret (GUID), não o Valor. No Azure você deve copiar o campo "Valor", não o "ID do secret". Deseja continuar mesmo assim?')) return;
                }
                var d = { nome: document.getElementById('t_nome').value.trim(), tenant_id: document.getElementById('t_tenant_id').value.trim(), client_id: document.getElementById('t_client_id').value.trim(), ativo: document.getElementById('t_ativo').checked ? 1 : 0, workspace_id: document.getElementById('t_workspace_id').value.trim() || null };
                if (secret) d.client_secret = secret;
                var url = id ? 'tenants/' + id : 'tenants';
                var method = id ? 'PUT' : 'POST';
                setSaving(true, id ? 'Salvando tenant...' : 'Criando tenant e validando conexão...');
                api(url, { method: method, body: d }).then(function(r) {
                    msg(r.data.message, r.data.success);
                    if (r.data.success) { fecharModal(); carregarTenants(); return; }
                    setSaving(false);
                });
            }}
        ]);
        if (id) {
            api('tenants/' + id).then(function(x) {
                if (x.data.success && x.data.data) {
                    var t = x.data.data;
                    document.getElementById('t_nome').value = t.nome || '';
                    document.getElementById('t_tenant_id').value = t.tenant_id || '';
                    document.getElementById('t_client_id').value = t.client_id || '';
                    document.getElementById('t_workspace_id').value = t.workspace_id || '';
                    document.getElementById('t_ativo').checked = t.ativo !== 0;
                }
            });
        }
    }

    function carregarUsuarios() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Usuários <button class="btn" id="btnNovoUser">Novo usuário</button></h2><p class="carregando">Carregando…</p>';
        api('users').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            if (!x.data.success) { p.innerHTML = '<h2>Usuários</h2><p class="erro">' + (x.data.message || 'Erro') + '</p>'; return; }
            var list = x.data.data || [];
            var html = '<h2>Usuários <button class="btn" id="btnNovoUser">Novo usuário</button></h2>';
            if (list.length === 0) html += '<p class="vazio">Nenhum usuário. Clique em Novo usuário.</p>';
            else {
                html += '<table><tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Empresa ID</th><th>Ativo</th><th>Ações</th></tr>';
                list.forEach(function(u) {
                    html += '<tr><td>' + u.id + '</td><td>' + (u.nome || '') + '</td><td>' + (u.email || '') + '</td><td>' + (u.perfil || '') + '</td><td>' + (u.empresa_id || '-') + '</td><td>' + (u.ativo ? 'Sim' : 'Não') + '</td><td>' +
                        '<button class="btn sm" data-user-id="' + u.id + '" data-user-editar>Editar</button>' +
                        '<button class="btn sm danger" data-user-id="' + u.id + '" data-user-excluir>Excluir</button></td></tr>';
                });
                html += '</table>';
            }
            p.innerHTML = html;
            document.getElementById('btnNovoUser').onclick = function() { formUser(); };
            p.querySelectorAll('[data-user-editar]').forEach(function(btn) { btn.onclick = function() { formUser(parseInt(btn.dataset.userId)); }; });
            p.querySelectorAll('[data-user-excluir]').forEach(function(btn) { btn.onclick = function() {
                if (!confirm('Excluir este usuário?')) return;
                api('users/' + btn.dataset.userId, { method: 'DELETE' }).then(function(r) { msg(r.data.message, r.data.success); if (r.data.success) carregarUsuarios(); });
            }; });
        });
    }
    function formUser(id) {
        var titulo = id ? 'Editar usuário' : 'Novo usuário';
        var corpo = '<div class="form-g"><label>Nome</label><input type="text" id="u_nome" required></div>' +
            '<div class="form-g"><label>E-mail</label><input type="email" id="u_email" required></div>' +
            '<div class="form-g"><label>Senha ' + (id ? '(deixe em branco para não alterar)' : '(será gerada e enviada por e-mail)') + '</label><input type="password" id="u_senha" placeholder="' + (id ? 'Deixe em branco para não alterar' : 'Não preencher – senha aleatória será enviada ao e-mail') + '"></div>' +
            '<div class="form-g"><label>Perfil</label><select id="u_perfil"><option value="usuario">Usuário</option><!-- <option value="supervisor">Supervisor</option><option value="gestor">Gestor</option> --><option value="admin">Administrador</option><option value="master">Master</option></select></div>' +
            '<div class="form-g"><label>Empresa ID (RLS)</label><input type="text" id="u_empresa_id" placeholder="Para RLS multi-tenant (EffectiveIdentity)"></div>' +
            '<div class="form-g"><label>Grupos</label><select id="u_grupos" multiple></select></div>' +
            '<div class="form-g"><label>Pastas (acesso aos relatórios das pastas selecionadas)</label><select id="u_pastas" multiple style="height:100px;"></select><small>Segure Ctrl para múltiplos</small></div>' +
            '<div class="form-g"><label><input type="checkbox" id="u_ativo" checked> Ativo</label></div>' +
            '<div id="u_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
        modal(titulo, corpo, [
            { label: 'Cancelar', sec: true, fn: fecharModal },
            { label: 'Salvar', fn: function() {
                var setSaving = modalSavingController('u_saving', 'Salvando...', 'Salvar');
                var d = { nome: document.getElementById('u_nome').value.trim(), email: document.getElementById('u_email').value.trim(), perfil: document.getElementById('u_perfil').value, ativo: document.getElementById('u_ativo').checked ? 1 : 0 };
                var empresaId = document.getElementById('u_empresa_id').value.trim();
                if (empresaId) d.empresa_id = empresaId;
                var senha = document.getElementById('u_senha').value;
                if (senha) d.senha = senha;
                var url = id ? 'users/' + id : 'users';
                var method = id ? 'PUT' : 'POST';
                setSaving(true, id ? 'Salvando alterações do usuário...' : 'Criando usuário e enviando e-mail de acesso. Aguarde...');
                api(url, { method: method, body: d }).then(function(r) {
                    if (!r.data.success) { msg(r.data.message, false); setSaving(false); return; }
                    var gids = [].slice.call(document.getElementById('u_grupos').selectedOptions).map(function(o) { return parseInt(o.value); });
                    var folderIds = [].slice.call(document.getElementById('u_pastas').selectedOptions).map(function(o) { return parseInt(o.value, 10); });
                    var uid = id || (r.data.data && r.data.data.id);
                    if (uid) {
                        api('users/' + uid + '/groups', { method: 'POST', body: { group_ids: gids } }).then(function(gr) {
                            if (!gr.data.success) { msg(gr.data.message || 'Usuário criado, mas falhou ao salvar grupos.', false); setSaving(false); return; }
                            api('users/' + uid + '/folders', { method: 'POST', body: { folder_ids: folderIds } }).then(function(fr) {
                                if (!fr.data.success) msg(fr.data.message || 'Falha ao salvar pastas do usuário.', false);
                                msg(r.data.message, true); fecharModal(); carregarUsuarios();
                            });
                        });
                    } else { msg(r.data.message, true); fecharModal(); carregarUsuarios(); }
                });
            }}
        ]);
        var promises = [api('groups'), api('folders')];
        if (id) promises.push(api('users/' + id));
        Promise.all(promises).then(function(res) {
            var groupsResp = res[0], foldersResp = res[1], userResp = id ? res[2] : null;
            var sel = document.getElementById('u_grupos');
            sel.innerHTML = '';
            (groupsResp.data.data || []).forEach(function(g) { var o = document.createElement('option'); o.value = g.id; o.textContent = g.nome; sel.appendChild(o); });
            var selPastas = document.getElementById('u_pastas');
            selPastas.innerHTML = '';
            (foldersResp.data.data || []).forEach(function(f) {
                if (f.ativo) { var o = document.createElement('option'); o.value = f.id; o.textContent = f.nome; selPastas.appendChild(o); }
            });
            if (userResp && userResp.data.success && userResp.data.data) {
                var us = userResp.data.data;
                document.getElementById('u_nome').value = us.nome || '';
                document.getElementById('u_email').value = us.email || '';
                document.getElementById('u_perfil').value = us.perfil || 'usuario';
                document.getElementById('u_ativo').checked = us.ativo !== 0;
                document.getElementById('u_empresa_id').value = us.empresa_id || '';
                (us.group_ids || []).forEach(function(gid) {
                    var opt = sel.querySelector('option[value="' + gid + '"]');
                    if (opt) opt.selected = true;
                });
                (us.folder_ids || []).forEach(function(fid) {
                    var opt = selPastas.querySelector('option[value="' + fid + '"]');
                    if (opt) opt.selected = true;
                });
            }
        });
    }

    function carregarGrupos() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Grupos <button class="btn" id="btnNovoGroup">Novo grupo</button></h2><p class="carregando">Carregando…</p>';
        api('groups').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            if (!x.data.success) { p.innerHTML = '<h2>Grupos</h2><p class="erro">' + (x.data.message || 'Erro') + '</p>'; return; }
            var list = x.data.data || [];
            var html = '<h2>Grupos <button class="btn" id="btnNovoGroup">Novo grupo</button></h2>';
            if (list.length === 0) html += '<p class="vazio">Nenhum grupo. Clique em Novo grupo.</p>';
            else {
                html += '<table><tr><th>ID</th><th>Nome</th><th>Descrição</th><th>Ações</th></tr>';
                list.forEach(function(g) {
                    html += '<tr><td>' + g.id + '</td><td>' + (g.nome || '') + '</td><td>' + (g.descricao || '') + '</td><td>' +
                        '<button class="btn sm" data-group-id="' + g.id + '" data-group-editar>Editar</button>' +
                        '<button class="btn sm danger" data-group-id="' + g.id + '" data-group-excluir>Excluir</button></td></tr>';
                });
                html += '</table>';
            }
            p.innerHTML = html;
            document.getElementById('btnNovoGroup').onclick = function() { formGroup(); };
            p.querySelectorAll('[data-group-editar]').forEach(function(btn) { btn.onclick = function() { formGroup(parseInt(btn.dataset.groupId)); }; });
            p.querySelectorAll('[data-group-excluir]').forEach(function(btn) { btn.onclick = function() {
                if (!confirm('Excluir este grupo?')) return;
                api('groups/' + btn.dataset.groupId, { method: 'DELETE' }).then(function(r) { msg(r.data.message, r.data.success); if (r.data.success) carregarGrupos(); });
            }; });
        });
    }
    function formGroup(id) {
        var titulo = id ? 'Editar grupo' : 'Novo grupo';
        var corpo = '<div class="form-g"><label>Nome</label><input type="text" id="g_nome" required></div><div class="form-g"><label>Descrição</label><input type="text" id="g_descricao"></div>' +
            '<div class="form-g"><label>Pastas (acesso aos relatórios das pastas selecionadas)</label><select id="g_pastas" multiple style="height:120px;width:100%;"></select><small>Segure Ctrl para selecionar várias</small></div>' +
            '<div id="g_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
        modal(titulo, corpo, [
            { label: 'Cancelar', sec: true, fn: fecharModal },
            { label: 'Salvar', fn: function() {
                var setSaving = modalSavingController('g_saving', 'Salvando...', 'Salvar');
                var d = { nome: document.getElementById('g_nome').value.trim(), descricao: document.getElementById('g_descricao').value.trim() };
                setSaving(true, id ? 'Salvando grupo...' : 'Criando grupo...');
                api(id ? 'groups/' + id : 'groups', { method: id ? 'PUT' : 'POST', body: d }).then(function(r) {
                    if (!r.data.success) { msg(r.data.message, false); setSaving(false); return; }
                    var gid = id || (r.data.data && r.data.data.id);
                    var folderIds = [].slice.call(document.getElementById('g_pastas').selectedOptions).map(function(o) { return parseInt(o.value, 10); });
                    if (gid) {
                        api('groups/' + gid + '/folders', { method: 'POST', body: { folder_ids: folderIds } }).then(function(fr) {
                            if (!fr.data.success) { msg(fr.data.message || 'Grupo salvo, mas falha ao salvar pastas.', false); setSaving(false); return; }
                            msg(r.data.message, true); fecharModal(); carregarGrupos();
                        });
                    } else { msg(r.data.message, true); fecharModal(); carregarGrupos(); }
                });
            }}
        ]);
        api('folders').then(function(x) {
            var sel = document.getElementById('g_pastas');
            sel.innerHTML = '';
            (x.data.data || []).forEach(function(f) {
                if (f.ativo) { var o = document.createElement('option'); o.value = f.id; o.textContent = f.nome; sel.appendChild(o); }
            });
            if (id) api('groups/' + id).then(function(g) {
                if (g.data.success && g.data.data) {
                    document.getElementById('g_nome').value = g.data.data.nome || '';
                    document.getElementById('g_descricao').value = g.data.data.descricao || '';
                    (g.data.data.folder_ids || []).forEach(function(fid) {
                        var opt = sel.querySelector('option[value="' + fid + '"]');
                        if (opt) opt.selected = true;
                    });
                }
            });
        });
    }

    function carregarPastas() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Pastas <button class="btn" id="btnNovaPasta">Nova pasta</button></h2><p class="carregando">Carregando…</p>';
        api('folders').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            if (!x.data.success) { p.innerHTML = '<h2>Pastas</h2><p class="erro">' + (x.data.message || 'Erro') + '</p>'; return; }
            var list = x.data.data || [];
            var html = '<h2>Pastas <button class="btn" id="btnNovaPasta">Nova pasta</button></h2>';
            html += '<p class="vazio" style="margin-bottom:1rem;">Configure pastas e associe relatórios a cada uma. Um relatório pode estar em várias pastas. Ao configurar grupos ou usuários, selecione as pastas para definir o acesso.</p>';
            if (list.length === 0) html += '<p class="vazio">Nenhuma pasta. Clique em Nova pasta.</p>';
            else {
                html += '<table><tr><th>ID</th><th>Nome</th><th>Descrição</th><th>Ativo</th><th>Ações</th></tr>';
                list.forEach(function(f) {
                    html += '<tr><td>' + f.id + '</td><td>' + (f.nome || '') + '</td><td>' + (f.descricao || '') + '</td><td>' + (f.ativo ? 'Sim' : 'Não') + '</td><td>' +
                        '<button class="btn sm" data-folder-id="' + f.id + '" data-folder-editar>Editar</button> ' +
                        '<button class="btn sm sec" data-folder-id="' + f.id + '" data-folder-relatorios>Relatórios</button> ' +
                        '<button class="btn sm danger" data-folder-id="' + f.id + '" data-folder-excluir>Excluir</button></td></tr>';
                });
                html += '</table>';
            }
            p.innerHTML = html;
            document.getElementById('btnNovaPasta').onclick = function() { formFolder(); };
            p.querySelectorAll('[data-folder-editar]').forEach(function(btn) { btn.onclick = function() { formFolder(parseInt(btn.dataset.folderId)); }; });
            p.querySelectorAll('[data-folder-relatorios]').forEach(function(btn) { btn.onclick = function() { formFolderReports(parseInt(btn.dataset.folderId)); }; });
            p.querySelectorAll('[data-folder-excluir]').forEach(function(btn) { btn.onclick = function() {
                if (!confirm('Excluir esta pasta?')) return;
                api('folders/' + btn.dataset.folderId, { method: 'DELETE' }).then(function(r) { msg(r.data.message, r.data.success); if (r.data.success) carregarPastas(); });
            }; });
        });
    }

    function formFolder(id) {
        var titulo = id ? 'Editar pasta' : 'Nova pasta';
        var corpo = '<div class="form-g"><label>Nome</label><input type="text" id="f_nome" required></div>' +
            '<div class="form-g"><label>Descrição</label><input type="text" id="f_descricao"></div>' +
            '<div class="form-g"><label><input type="checkbox" id="f_ativo" checked> Ativo</label></div>' +
            '<div id="f_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
        modal(titulo, corpo, [
            { label: 'Cancelar', sec: true, fn: fecharModal },
            { label: 'Salvar', fn: function() {
                var setSaving = modalSavingController('f_saving', 'Salvando...', 'Salvar');
                var d = { nome: document.getElementById('f_nome').value.trim(), descricao: document.getElementById('f_descricao').value.trim(), ativo: document.getElementById('f_ativo').checked ? 1 : 0 };
                setSaving(true, id ? 'Salvando pasta...' : 'Criando pasta...');
                api(id ? 'folders/' + id : 'folders', { method: id ? 'PUT' : 'POST', body: d }).then(function(r) {
                    msg(r.data.message, r.data.success);
                    if (r.data.success) { fecharModal(); carregarPastas(); return; }
                    setSaving(false);
                });
            }}
        ]);
        if (id) api('folders/' + id).then(function(x) {
            if (x.data.success && x.data.data) {
                var f = x.data.data;
                document.getElementById('f_nome').value = f.nome || '';
                document.getElementById('f_descricao').value = f.descricao || '';
                document.getElementById('f_ativo').checked = f.ativo !== 0;
            }
        });
    }

    function formFolderReports(folderId) {
        Promise.all([api('folders/' + folderId), api('reports/catalog')]).then(function(res) {
            var folderResp = res[0], catalogResp = res[1];
            if (!folderResp.data.success || !folderResp.data.data) { msg('Pasta não encontrada', false); return; }
            if (!catalogResp.data.success) { msg('Erro ao carregar catálogo de relatórios', false); return; }
            var folder = folderResp.data.data;
            var reportIds = folder.report_ids || [];
            var reports = catalogResp.data.data || [];
            var opts = reports.map(function(r) {
                var sel = reportIds.indexOf(parseInt(r.id, 10)) >= 0 ? ' selected' : '';
                return '<option value="' + r.id + '"' + sel + '>' + (r.nome || ('Relatório #' + r.id)) + '</option>';
            }).join('');
            var corpo = '<p><strong>Pasta:</strong> ' + (folder.nome || '') + '</p>' +
                '<div class="form-g"><label>Relatórios nesta pasta (Ctrl+clique para múltiplos)</label><select id="folder_reports" multiple style="height:220px;width:100%;">' + opts + '</select></div>' +
                '<div id="folder_reports_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
            modal('Relatórios da pasta', corpo, [
                { label: 'Cancelar', sec: true, fn: fecharModal },
                { label: 'Salvar', fn: function() {
                    var setSaving = modalSavingController('folder_reports_saving', 'Salvando...', 'Salvar');
                    var sel = document.getElementById('folder_reports');
                    var ids = [].slice.call(sel.selectedOptions).map(function(o) { return parseInt(o.value, 10); });
                    setSaving(true, 'Salvando...');
                    api('folders/' + folderId + '/reports', { method: 'PUT', body: { report_ids: ids } }).then(function(r) {
                        msg(r.data.message, r.data.success);
                        if (r.data.success) { fecharModal(); carregarPastas(); return; }
                        setSaving(false);
                    });
                }}
            ]);
        });
    }

    function carregarRelatoriosPermissoes() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Relatórios e permissões</h2><div class="filtros"><div class="form-g"><label>Tenant</label><select id="selTenant"><option value="">-- Selecione --</option></select></div><div class="form-g"><label>Workspace</label><select id="selWorkspace"><option value="">-- Selecione o tenant --</option></select></div><div class="form-g"><label>Relatório</label><select id="selReport"><option value="">-- Selecione o workspace --</option></select></div></div><div id="areaPermissoes"></div>';
        api('tenants').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            (x.data.data || []).forEach(function(t) { var o = document.createElement('option'); o.value = t.id; o.textContent = t.nome; document.getElementById('selTenant').appendChild(o); });
        });
        document.getElementById('selTenant').onchange = function() {
            var tid = this.value;
            var selW = document.getElementById('selWorkspace');
            selW.innerHTML = '<option value="">-- Carregando --</option>';
            document.getElementById('selReport').innerHTML = '<option value="">-- Selecione o workspace --</option>';
            document.getElementById('areaPermissoes').innerHTML = '';
            if (!tid) return;
            api('tenants/' + tid + '/workspaces').then(function(r) {
                selW.innerHTML = '<option value="">-- Selecione --</option>';
                (r.data.data || []).forEach(function(w) { var o = document.createElement('option'); o.value = w.id; o.textContent = w.nome; selW.appendChild(o); });
            });
        };
        document.getElementById('selWorkspace').onchange = function() {
            var wid = this.value;
            var selR = document.getElementById('selReport');
            selR.innerHTML = '<option value="">-- Carregando --</option>';
            document.getElementById('areaPermissoes').innerHTML = '';
            if (!wid) return;
            api('workspaces/' + wid + '/reports/all').then(function(r) {
                selR.innerHTML = '<option value="">-- Selecione um relatório --</option>';
                (r.data.data || []).forEach(function(rep) { var o = document.createElement('option'); o.value = rep.id; o.textContent = rep.nome; selR.appendChild(o); });
            });
        };
        document.getElementById('selReport').onchange = function() {
            var rid = this.value;
            var area = document.getElementById('areaPermissoes');
            area.innerHTML = '';
            if (!rid) return;
            area.innerHTML = '<p class="carregando">Carregando permissões…</p>';
            api('reports/' + rid + '/permissions').then(function(r) {
                var list = r.data.data || [];
                var html = '<h3>Permissões deste relatório <button class="btn sm" id="btnEditarReport">Editar relatório</button> <button class="btn sm" id="btnNovaPerm">Adicionar permissão</button></h3>';
                if (list.length === 0) html += '<p class="vazio">Nenhuma permissão. Clique em Adicionar permissão.</p>';
                else {
                    html += '<table><tr><th>Tipo</th><th>Usuário/Grupo</th><th>Visualizar</th><th>Mostrar abas</th><th>Mostrar filtros</th><th>Válido até</th><th>Ações</th></tr>';
                    list.forEach(function(perm) {
                        var nome = perm.user_nome || perm.user_email || perm.group_nome || '-';
                        var tipo = perm.user_id ? 'Usuário' : 'Grupo';
                        html += '<tr><td>' + tipo + '</td><td>' + nome + '</td><td>' + (perm.pode_visualizar ? 'Sim' : 'Não') + '</td><td>' + (perm.mostrar_abas ? 'Sim' : 'Não') + '</td><td>' + (perm.mostrar_filtros ? 'Sim' : 'Não') + '</td><td>' + (perm.data_fim || '-') + '</td><td><button class="btn sm" data-perm-id="' + perm.id + '" data-perm-editar>Editar</button> <button class="btn sm danger" data-perm-id="' + perm.id + '" data-perm-excluir>Excluir</button></td></tr>';
                    });
                    html += '</table>';
                }
                area.innerHTML = html;
                document.getElementById('btnEditarReport').onclick = function() { formEditarReport(parseInt(rid)); };
                document.getElementById('btnNovaPerm').onclick = function() { formPermissao(parseInt(rid)); };
                area.querySelectorAll('[data-perm-editar]').forEach(function(btn) {
                    btn.onclick = function() {
                        var id = parseInt(btn.dataset.permId, 10);
                        var perm = null;
                        for (var i = 0; i < list.length; i++) {
                            if (parseInt(list[i].id, 10) === id) { perm = list[i]; break; }
                        }
                        if (!perm) { msg('Permissão não encontrada.', false); return; }
                        formEditarPermissao(perm, function() {
                            document.getElementById('selReport').dispatchEvent(new Event('change'));
                        });
                    };
                });
                area.querySelectorAll('[data-perm-excluir]').forEach(function(btn) {
                    btn.onclick = function() {
                        if (!confirm('Remover esta permissão?')) return;
                        api('permissions/' + btn.dataset.permId, { method: 'DELETE' }).then(function(r) { msg(r.data.message, r.data.success); document.getElementById('selReport').dispatchEvent(new Event('change')); });
                    };
                });
            });
        };
    }

    function formEditarReport(reportId) {
        api('reports/' + reportId).then(function(x) {
            if (!x.data.success || !x.data.data) { msg('Relatório não encontrado', false); return; }
            var rep = x.data.data;
            var imgSrc = rep.imagem ? (rep.imagem.indexOf('/') === 0 ? rep.imagem : rep.imagem) : '';
            var donoVal = (rep.dono_texto || rep.dono_nome || rep.dono_email || '').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            var ultimaAtual = (rep.ultima_atualizacao || '').toString().trim();
            if (ultimaAtual.length >= 19) ultimaAtual = ultimaAtual.slice(0, 16).replace('T', ' ');
            else if (ultimaAtual.length >= 10) ultimaAtual = ultimaAtual.slice(0, 10);
            var corpo = '<div class="form-g"><label>Nome</label><input type="text" id="rep_nome" value="' + (rep.nome || '').replace(/"/g, '&quot;') + '"></div>' +
                '<div class="form-g"><label>Descrição</label><textarea rows="2" class="input-readonly" readonly style="width:100%;background:#f3f4f6;cursor:default;resize:none;">' + (rep.descricao || '-').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') + '</textarea></div>' +
                '<div class="form-g"><label>Dono do relatório</label><input type="text" id="rep_dono_texto" value="' + donoVal + '" placeholder="Nome ou e-mail do responsável" style="width:100%;"></div>' +
                '<div class="form-g"><label>Última atualização</label><input type="text" class="input-readonly" value="' + (ultimaAtual || '-').replace(/"/g, '&quot;') + '" readonly style="background:#f3f4f6;cursor:default;"></div>' +
                '<p class="vazio" style="margin:0.5rem 0;font-size:0.85rem;">Descrição e data são atualizadas na sincronização. O dono pode ser preenchido pela sincronização ou editado aqui.</p>' +
                '<div class="form-g"><label>Imagem de identificação</label>' +
                '<div id="rep_img_preview" style="margin:0.5rem 0;">' +
                (imgSrc ? '<img src="' + imgSrc + '" alt="" style="max-width:120px;max-height:80px;object-fit:contain;">' : '<span class="vazio">Nenhuma (usa padrão dashbi02.png)</span>') +
                '</div>' +
                '<input type="file" id="rep_imagem_file" accept="image/jpeg,image/png,image/gif,image/webp" style="margin-bottom:0.5rem;">' +
                '<div class="form-g"><label><input type="checkbox" id="rep_usar_padrao"> Usar apenas imagem padrão (remover imagem atual)</label></div>' +
                '</div>' +
                '<div id="rep_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
            modal('Editar relatório', corpo, [
                { label: 'Cancelar', sec: true, fn: fecharModal },
                { label: 'Salvar', fn: function() {
                    var setSaving = modalSavingController('rep_saving', 'Salvando...', 'Salvar');
                    var nome = document.getElementById('rep_nome').value.trim();
                    var usarPadrao = document.getElementById('rep_usar_padrao').checked;
                    var fileInput = document.getElementById('rep_imagem_file');
                    var hasFile = fileInput.files && fileInput.files.length > 0;
                    function doPut(payload) {
                        if (Object.keys(payload).length === 0) { fecharModal(); document.getElementById('selReport').dispatchEvent(new Event('change')); return; }
                        api('reports/' + reportId, { method: 'PUT', body: payload }).then(function(r) {
                            msg(r.data.message, r.data.success);
                            if (r.data.success) { fecharModal(); document.getElementById('selReport').dispatchEvent(new Event('change')); return; }
                            setSaving(false);
                        });
                    }
                    var donoTexto = document.getElementById('rep_dono_texto').value.trim();
                    var d = {};
                    if (nome !== (rep.nome || '')) d.nome = nome;
                    if (donoTexto !== (rep.dono_texto || rep.dono_nome || rep.dono_email || '').toString().trim()) d.dono_texto = donoTexto || null;
                    if (usarPadrao) d.imagem = null;
                    setSaving(true, 'Salvando alterações do relatório...');
                    if (hasFile) {
                        var fd = new FormData();
                        fd.append('imagem', fileInput.files[0]);
                        fetch('api/reports/' + reportId + '/imagem', { method: 'POST', credentials: 'include', body: fd }).then(function(res) { return res.json(); }).then(function(r) {
                            if (r.success) {
                                if (r.data && r.data.imagem) d.imagem = r.data.imagem;
                                doPut(d);
                            } else { msg(r.message || 'Erro ao enviar imagem', false); setSaving(false); }
                        }).catch(function() { msg('Erro ao enviar imagem', false); setSaving(false); });
                    } else {
                        doPut(d);
                    }
                }}
            ]);
        });
    }

    function formPermissao(reportId) {
        var corpo = '<div class="form-g"><label>Tipo</label><select id="perm_tipo"><option value="user">Usuário</option><option value="group">Grupo</option></select></div>' +
            '<div class="form-g" id="perm_user_g"><label>Usuário</label><select id="perm_user_id"></select></div>' +
            '<div class="form-g" id="perm_group_g" style="display:none"><label>Grupo</label><select id="perm_group_id"></select></div>' +
            '<div class="form-g"><label><input type="checkbox" id="perm_visualizar" checked> Pode visualizar</label></div>' +
            '<div class="form-g"><label><input type="checkbox" id="perm_mostrar_abas"> Mostrar abas (páginas)</label></div>' +
            '<div class="form-g"><label>Páginas permitidas (vazio = todas)</label><select id="perm_paginas" multiple style="height:120px;width:100%;"></select><small>Segure Ctrl para selecionar várias</small></div>' +
            '<div class="form-g"><label><input type="checkbox" id="perm_mostrar_filtros"> Mostrar painel de filtros</label></div>' +
            '<div class="form-g"><label>Data fim (opcional)</label><input type="date" id="perm_data_fim"></div>' +
            '<div id="perm_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
        modal('Adicionar permissão', corpo, [
            { label: 'Cancelar', sec: true, fn: fecharModal },
            { label: 'Salvar', fn: function() {
                var setSaving = modalSavingController('perm_saving', 'Salvando...', 'Salvar');
                var tipo = document.getElementById('perm_tipo').value;
                var d = {
                    report_id: reportId,
                    pode_visualizar: document.getElementById('perm_visualizar').checked ? 1 : 0,
                    mostrar_abas: document.getElementById('perm_mostrar_abas').checked ? 1 : 0,
                    mostrar_filtros: document.getElementById('perm_mostrar_filtros').checked ? 1 : 0
                };
                var selPaginas = document.getElementById('perm_paginas');
                var paginas = Array.from(selPaginas.selectedOptions).map(function(o) { return o.value; });
                if (paginas.length) d.pagina_restrita = paginas;
                var dataFim = document.getElementById('perm_data_fim').value;
                if (dataFim) d.data_fim = dataFim;
                if (tipo === 'user') d.user_id = parseInt(document.getElementById('perm_user_id').value);
                else d.group_id = parseInt(document.getElementById('perm_group_id').value);
                setSaving(true, 'Salvando permissão...');
                api('reports/' + reportId + '/permissions', { method: 'POST', body: d }).then(function(r) {
                    msg(r.data.message, r.data.success);
                    if (r.data.success) { fecharModal(); document.getElementById('selReport').dispatchEvent(new Event('change')); return; }
                    setSaving(false);
                });
            }}
        ]);
        document.getElementById('perm_tipo').onchange = function() {
            document.getElementById('perm_user_g').style.display = this.value === 'user' ? 'block' : 'none';
            document.getElementById('perm_group_g').style.display = this.value === 'group' ? 'block' : 'none';
        };
        api('reports/' + reportId + '/pages').then(function(x) {
            var sel = document.getElementById('perm_paginas');
            var list = (x.data.data || x.data || []);
            list.forEach(function(p) {
                var o = document.createElement('option');
                o.value = p.name || p.displayName || '';
                o.textContent = p.displayName || p.name || o.value;
                sel.appendChild(o);
            });
        }).catch(function() {
            document.getElementById('perm_paginas').innerHTML = '<option value="">Não foi possível carregar as páginas</option>';
        });
        api('users').then(function(x) {
            var sel = document.getElementById('perm_user_id');
            sel.innerHTML = '<option value="">-- Selecione --</option>';
            (x.data.data || []).forEach(function(u) { var o = document.createElement('option'); o.value = u.id; o.textContent = u.nome + ' (' + u.email + ')'; sel.appendChild(o); });
        });
        api('groups').then(function(x) {
            var sel = document.getElementById('perm_group_id');
            sel.innerHTML = '<option value="">-- Selecione --</option>';
            (x.data.data || []).forEach(function(g) { var o = document.createElement('option'); o.value = g.id; o.textContent = g.nome; sel.appendChild(o); });
        });
    }

    function formEditarPermissao(perm, onSaved) {
        var pr = perm.pagina_restrita;
        if (typeof pr === 'string') { try { pr = JSON.parse(pr); } catch(e) { pr = []; } }
        if (!Array.isArray(pr)) pr = [];
        var tipo = perm.user_id ? 'Usuário' : 'Grupo';
        var alvo = perm.user_nome || perm.user_email || perm.group_nome || '-';
        var corpo = '<div class="form-g"><label>Tipo</label><input type="text" value="' + tipo + '" readonly class="input-readonly"></div>' +
            '<div class="form-g"><label>Alvo</label><input type="text" value="' + String(alvo).replace(/"/g, '&quot;') + '" readonly class="input-readonly"></div>' +
            '<div class="form-g"><label><input type="checkbox" id="perm_e_visualizar" ' + (perm.pode_visualizar ? 'checked' : '') + '> Pode visualizar</label></div>' +
            '<div class="form-g"><label><input type="checkbox" id="perm_e_mostrar_abas" ' + (perm.mostrar_abas ? 'checked' : '') + '> Mostrar abas (páginas)</label></div>' +
            '<div class="form-g"><label>Páginas permitidas (vazio = todas)</label><select id="perm_e_paginas" multiple style="height:120px;width:100%;"></select><small>Segure Ctrl para selecionar várias</small></div>' +
            '<div class="form-g"><label><input type="checkbox" id="perm_e_mostrar_filtros" ' + (perm.mostrar_filtros ? 'checked' : '') + '> Mostrar painel de filtros</label></div>' +
            '<div class="form-g"><label>Data fim (opcional)</label><input type="date" id="perm_e_data_fim" value="' + (perm.data_fim || '') + '"></div>' +
            '<div id="perm_e_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
        modal('Editar permissão', corpo, [
            { label: 'Cancelar', sec: true, fn: fecharModal },
            { label: 'Salvar', fn: function() {
                var setSaving = modalSavingController('perm_e_saving', 'Salvando...', 'Salvar');
                var d = {
                    pode_visualizar: document.getElementById('perm_e_visualizar').checked ? 1 : 0,
                    mostrar_abas: document.getElementById('perm_e_mostrar_abas').checked ? 1 : 0,
                    mostrar_filtros: document.getElementById('perm_e_mostrar_filtros').checked ? 1 : 0
                };
                var selPaginas = document.getElementById('perm_e_paginas');
                var paginas = Array.from(selPaginas.selectedOptions).map(function(o) { return o.value; });
                d.pagina_restrita = paginas.length ? paginas : [];
                var dataFim = document.getElementById('perm_e_data_fim').value;
                d.data_fim = dataFim ? dataFim : null;
                setSaving(true, 'Salvando permissão...');
                api('permissions/' + perm.id, { method: 'PUT', body: d }).then(function(r) {
                    msg(r.data.message, r.data.success);
                    if (r.data.success) {
                        fecharModal();
                        if (onSaved) onSaved();
                        return;
                    }
                    setSaving(false);
                });
            }}
        ]);
        var reportId = perm.report_id;
        api('reports/' + reportId + '/pages').then(function(x) {
            var sel = document.getElementById('perm_e_paginas');
            var list = (x.data.data || x.data || []);
            list.forEach(function(p) {
                var name = p.name || p.displayName || '';
                var o = document.createElement('option');
                o.value = name;
                o.textContent = p.displayName || p.name || name;
                o.selected = pr.indexOf(name) >= 0;
                sel.appendChild(o);
            });
        }).catch(function() {
            document.getElementById('perm_e_paginas').innerHTML = '<option value="">Não foi possível carregar as páginas</option>';
        });
    }

    function carregarTvPanels() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Painel TV <button class="btn" id="btnNovoTvPanel">Novo painel</button></h2><p class="carregando">Carregando…</p>';
        api('tv-panels').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            if (!x.data.success) { p.innerHTML = '<h2>Painel TV</h2><p class="erro">' + (x.data.message || 'Erro') + '</p>'; return; }
            var list = x.data.data || [];
            var html = '<h2>Painel TV <button class="btn" id="btnNovoTvPanel">Novo painel</button></h2>';
            if (list.length === 0) {
                html += '<p class="vazio">Nenhum painel cadastrado.</p>';
            } else {
                html += '<table><tr><th>ID</th><th>Nome</th><th>Ativo</th><th>Criado por</th><th>Ações</th></tr>';
                list.forEach(function(tp) {
                    html += '<tr><td>' + tp.id + '</td><td>' + (tp.nome || '') + '</td><td>' + ((tp.ativo && parseInt(tp.ativo, 10) === 1) ? 'Sim' : 'Não') + '</td><td>' + (tp.criado_por_nome || '-') + '</td><td>' +
                        '<button class="btn sm" data-tv-editar="' + tp.id + '">Editar</button> ' +
                        '<button class="btn sm sec" data-tv-items="' + tp.id + '">Itens</button> ' +
                        '<a class="btn sm sec" href="tv.php?panel_id=' + tp.id + '" target="_blank">Abrir TV</a> ' +
                        '<button class="btn sm danger" data-tv-excluir="' + tp.id + '">Excluir</button></td></tr>';
                });
                html += '</table>';
            }
            p.innerHTML = html;
            document.getElementById('btnNovoTvPanel').onclick = function() { formTvPanel(); };
            p.querySelectorAll('[data-tv-editar]').forEach(function(btn) {
                btn.onclick = function() { formTvPanel(parseInt(btn.dataset.tvEditar, 10)); };
            });
            p.querySelectorAll('[data-tv-items]').forEach(function(btn) {
                btn.onclick = function() { formTvPanelItems(parseInt(btn.dataset.tvItems, 10)); };
            });
            p.querySelectorAll('[data-tv-excluir]').forEach(function(btn) {
                btn.onclick = function() {
                    if (!confirm('Excluir este painel TV?')) return;
                    api('tv-panels/' + btn.dataset.tvExcluir, { method: 'DELETE' }).then(function(r) {
                        msg(r.data.message, r.data.success);
                        if (r.data.success) carregarTvPanels();
                    });
                };
            });
        });
    }

    function formTvPanel(id) {
        var titulo = id ? 'Editar Painel TV' : 'Novo Painel TV';
        var corpo = '<div class="form-g"><label>Nome do painel</label><input type="text" id="tv_nome" required></div>' +
            '<div class="form-g"><label>Modo de reprodução</label><select id="tv_modo"><option value="loop">Loop (contínuo)</option><option value="once">Uma passada (encerra no último)</option></select></div>' +
            '<div class="form-g"><label><input type="checkbox" id="tv_contador" checked> Mostrar contador regressivo na TV</label></div>' +
            '<div class="form-g"><label><input type="checkbox" id="tv_ativo" checked> Ativo</label></div>' +
            '<div id="tv_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>';
        modal(titulo, corpo, [
            { label: 'Cancelar', sec: true, fn: fecharModal },
            { label: 'Salvar', fn: function() {
                var setSaving = modalSavingController('tv_saving', 'Salvando...', 'Salvar');
                var d = {
                    nome: document.getElementById('tv_nome').value.trim(),
                    modo_reproducao: document.getElementById('tv_modo').value,
                    mostrar_contador: document.getElementById('tv_contador').checked ? 1 : 0,
                    ativo: document.getElementById('tv_ativo').checked ? 1 : 0
                };
                setSaving(true, id ? 'Salvando painel TV...' : 'Criando painel TV...');
                api(id ? 'tv-panels/' + id : 'tv-panels', { method: id ? 'PUT' : 'POST', body: d }).then(function(r) {
                    msg(r.data.message, r.data.success);
                    if (r.data.success) { fecharModal(); carregarTvPanels(); return; }
                    setSaving(false);
                });
            } }
        ]);
        if (id) {
            api('tv-panels/' + id).then(function(x) {
                if (x.data.success && x.data.data) {
                    document.getElementById('tv_nome').value = x.data.data.nome || '';
                    document.getElementById('tv_modo').value = (x.data.data.modo_reproducao || 'loop');
                    document.getElementById('tv_contador').checked = !!parseInt(x.data.data.mostrar_contador, 10);
                    document.getElementById('tv_ativo').checked = parseInt(x.data.data.ativo, 10) === 1;
                }
            });
        }
    }

    function formTvPanelItems(panelId) {
        Promise.all([api('tv-panels/' + panelId), api('reports/catalog')]).then(function(res) {
            var panelResp = res[0];
            var reportResp = res[1];
            if (!panelResp.data.success) { msg(panelResp.data.message || 'Painel não encontrado', false); return; }
            if (!reportResp.data.success) { msg(reportResp.data.message || 'Erro ao carregar relatórios', false); return; }
            var panel = panelResp.data.data || {};
            var items = panel.items || [];
            var reports = reportResp.data.data || [];
            var reportOpts = reports.map(function(r) {
                return '<option value="' + r.id + '">' + (r.nome || ('Relatório #' + r.id)) + '</option>';
            }).join('');

            function rowHtml(item) {
                var rid = parseInt(item.report_id || 0, 10);
                var tempo = parseInt(item.tempo_segundos || 30, 10);
                return '<div class="tv-item-row" draggable="true" style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.5rem;padding:0.35rem;border:1px dashed #d1d5db;border-radius:6px;background:#fff;">' +
                    '<span title="Arraste para reordenar" style="cursor:grab;color:#6b7280;user-select:none;">☰</span>' +
                    '<select class="tv_report_id" style="flex:1;"><option value="">-- Relatório --</option>' + reportOpts + '</select>' +
                    '<select class="tv_page_name" style="min-width:160px;" title="Página do relatório"><option value="">-- Selecione o relatório --</option></select>' +
                    '<input class="tv_tempo" type="number" min="5" max="3600" value="' + tempo + '" style="width:120px;" title="Tempo (segundos)">' +
                    '<button type="button" class="btn sm danger" data-tv-remover-item>Remover</button>' +
                    '</div>';
            }

            function loadPagesForRow(row, reportId, selectedPageName) {
                var selPage = row.querySelector('.tv_page_name');
                selPage.innerHTML = '<option value="">Carregando...</option>';
                if (!reportId || reportId <= 0) {
                    selPage.innerHTML = '<option value="">-- Selecione o relatório --</option>';
                    return;
                }
                api('reports/' + reportId + '/pages').then(function(x) {
                    var list = (x.data.data || x.data || []);
                    selPage.innerHTML = '<option value="">Página padrão (primeira)</option>';
                    list.forEach(function(p) {
                        var name = p.name || p.displayName || '';
                        var label = p.displayName || p.name || name;
                        var o = document.createElement('option');
                        o.value = name;
                        o.textContent = label;
                        if (selectedPageName && name === selectedPageName) o.selected = true;
                        selPage.appendChild(o);
                    });
                }).catch(function() {
                    selPage.innerHTML = '<option value="">-- Erro ao carregar --</option>';
                });
            }

            var corpo = '<p><strong>Painel:</strong> ' + (panel.nome || '') + '</p>' +
                '<p class="vazio">Configure a ordem dos relatórios, a página (aba) de cada item e o tempo em segundos. Para exibir várias páginas do mesmo relatório, adicione um item por página.</p>' +
                '<div id="tvItemsWrap"></div>' +
                '<div id="tv_items_saving" class="vazio" style="display:none;padding:0.25rem 0 0 0;">Processando, aguarde...</div>' +
                '<button type="button" class="btn sm" id="btnTvAddItem">Adicionar relatório</button>';
            modal('Itens do Painel TV', corpo, [
                { label: 'Cancelar', sec: true, fn: fecharModal },
                { label: 'Salvar lista', fn: function() {
                    var setSaving = modalSavingController('tv_items_saving', 'Salvando...', 'Salvar lista');
                    var rows = [].slice.call(document.querySelectorAll('#tvItemsWrap .tv-item-row'));
                    var out = [];
                    rows.forEach(function(row) {
                        var reportId = parseInt(row.querySelector('.tv_report_id').value || '0', 10);
                        var tempo = parseInt(row.querySelector('.tv_tempo').value || '30', 10);
                        var paginaNome = (row.querySelector('.tv_page_name').value || '').trim() || null;
                        if (reportId > 0) {
                            out.push({
                                report_id: reportId,
                                tempo_segundos: tempo > 0 ? tempo : 30,
                                pagina_nome: paginaNome
                            });
                        }
                    });
                    setSaving(true, 'Salvando lista de itens do painel...');
                    api('tv-panels/' + panelId + '/items', { method: 'PUT', body: { items: out } }).then(function(r) {
                        msg(r.data.message, r.data.success);
                        if (r.data.success) { fecharModal(); return; }
                        setSaving(false);
                    });
                } }
            ]);

            var wrap = document.getElementById('tvItemsWrap');
            var draggingRow = null;
            function bindRowEvents(root) {
                root.querySelectorAll('[data-tv-remover-item]').forEach(function(btn) {
                    btn.onclick = function() {
                        var row = btn.closest('.tv-item-row');
                        if (row && row.parentNode) row.parentNode.removeChild(row);
                    };
                });
                root.querySelectorAll('.tv_report_id').forEach(function(sel) {
                    var row = sel.closest('.tv-item-row');
                    if (!row || row._tvReportBound) return;
                    row._tvReportBound = true;
                    sel.onchange = function() {
                        var reportId = parseInt(sel.value || '0', 10);
                        loadPagesForRow(row, reportId, null);
                    };
                });
                root.querySelectorAll('.tv-item-row').forEach(function(row) {
                    row.ondragstart = function() {
                        draggingRow = row;
                        row.style.opacity = '0.5';
                    };
                    row.ondragend = function() {
                        row.style.opacity = '1';
                        draggingRow = null;
                    };
                });
            }
            wrap.ondragover = function(e) {
                if (!draggingRow) return;
                e.preventDefault();
                var target = e.target.closest('.tv-item-row');
                if (!target || target === draggingRow || target.parentNode !== wrap) return;
                var rect = target.getBoundingClientRect();
                var before = e.clientY < rect.top + rect.height / 2;
                if (before) wrap.insertBefore(draggingRow, target);
                else wrap.insertBefore(draggingRow, target.nextSibling);
            };
            function addRow(item) {
                var holder = document.createElement('div');
                holder.innerHTML = rowHtml(item || {});
                var row = holder.firstChild;
                wrap.appendChild(row);
                if (item && item.report_id) {
                    row.querySelector('.tv_report_id').value = String(item.report_id);
                    loadPagesForRow(row, parseInt(item.report_id, 10), item.pagina_nome || null);
                }
                bindRowEvents(row.parentNode || wrap);
            }

            if (items.length === 0) addRow({ tempo_segundos: 30 });
            else items.forEach(function(item) { addRow(item); });

            document.getElementById('btnTvAddItem').onclick = function() { addRow({ tempo_segundos: 30 }); };
            bindRowEvents(wrap);
        });
    }

    function carregarLogs() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Logs de acesso</h2><p class="carregando">Carregando…</p>';
        Promise.all([api('logs'), api('logs/metrics')]).then(function(res) {
            var x = res[0], m = res[1];
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            var list = x.data.data || [];
            var metrics = (m.data.success && m.data.data) ? m.data.data : {};
            var relatorios = metrics.relatorios_mais_acessados || [];
            var usuarios = metrics.usuarios_mais_ativos || [];
            var porHora = metrics.acessos_por_hora || [];
            var html = '<h2>Logs de acesso</h2>';
            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1.5rem;">';
            html += '<div class="painel" style="margin:0;"><h3 style="margin:0 0 0.5rem 0;">Relatórios mais acessados</h3>';
            if (relatorios.length === 0) html += '<p class="vazio">Nenhum dado ainda.</p>';
            else { relatorios.slice(0, 10).forEach(function(r, i) { html += '<div style="padding:0.25rem 0;font-size:0.9rem;">' + (i + 1) + '. ' + (r.report_nome || '#' + r.relatorio_id) + ' <strong>' + (r.total_acessos || 0) + '</strong> acessos</div>'; }); }
            html += '</div>';
            html += '<div class="painel" style="margin:0;"><h3 style="margin:0 0 0.5rem 0;">Usuários mais ativos</h3>';
            if (usuarios.length === 0) html += '<p class="vazio">Nenhum dado ainda.</p>';
            else { usuarios.slice(0, 10).forEach(function(u, i) { html += '<div style="padding:0.25rem 0;font-size:0.9rem;">' + (i + 1) + '. ' + (u.user_nome || u.user_email || '#' + u.usuario_id) + ' <strong>' + (u.total_acessos || 0) + '</strong> acessos</div>'; }); }
            html += '</div>';
            html += '<div class="painel" style="margin:0;"><h3 style="margin:0 0 0.5rem 0;">Horários de pico</h3>';
            if (porHora.length === 0) html += '<p class="vazio">Nenhum dado ainda.</p>';
            else { var maxH = Math.max.apply(null, porHora.map(function(h) { return parseInt(h.total, 10) || 0; })); porHora.forEach(function(h) { var n = parseInt(h.total, 10) || 0; var bar = maxH > 0 ? Math.round((n / maxH) * 100) : 0; html += '<div style="padding:0.2rem 0;font-size:0.85rem;">' + (h.hora < 10 ? '0' : '') + h.hora + 'h: ' + n + ' <span style="display:inline-block;background:#2563eb;width:' + bar + '%;height:8px;border-radius:2px;vertical-align:middle;"></span></div>'; }); }
            html += '</div></div>';
            html += '<h3>Últimos acessos (access_logs)</h3>';
            if (list.length === 0) html += '<p class="vazio">Nenhum registro.</p>';
            else {
                html += '<table><tr><th>Data</th><th>Usuário</th><th>Relatório</th><th>Ação</th><th>IP</th></tr>';
                list.forEach(function(l) { html += '<tr><td>' + (l.created_at || '') + '</td><td>' + (l.user_nome || l.user_id) + '</td><td>' + (l.report_nome || l.report_id) + '</td><td>' + (l.acao || '') + '</td><td>' + (l.ip || '') + '</td></tr>'; });
                html += '</table>';
            }
            p.innerHTML = html;
        });
    }

    function carregarDatasets() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Controle de Dataset / Refresh</h2><p class="carregando">Carregando…</p>';
        api('datasets').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            if (!x.data.success) {
                p.innerHTML = '<h2>Controle de Dataset / Refresh</h2><p class="erro">' + (x.data.message || 'Erro') + '</p>';
                return;
            }
            var list = x.data.data || [];
            var html = '<h2>Controle de Dataset / Refresh</h2>';
            html += '<p style="margin-bottom:1rem;">Ver status do refresh, histórico (incluindo falhas) e forçar refresh dos datasets dos relatórios Power BI.</p>';
            if (list.length === 0) {
                html += '<p class="vazio">Nenhum dataset encontrado. Execute a sincronização para trazer relatórios com dataset_id.</p>';
            } else {
                html += '<table><tr><th>Workspace</th><th>Dataset ID</th><th>Relatórios</th><th>Ações</th></tr>';
                list.forEach(function(d) {
                    var dsShort = (d.dataset_id || '').substring(0, 20) + ((d.dataset_id || '').length > 20 ? '…' : '');
                    var names = (d.report_names || '').length > 50 ? (d.report_names || '').substring(0, 50) + '…' : (d.report_names || '-');
                    var groupId = (d.powerbi_workspace_id || '').replace(/"/g, '&quot;');
                    html += '<tr><td>' + (d.workspace_nome || '-') + '</td><td><code title="' + (d.dataset_id || '') + '">' + dsShort + '</code></td><td>' + (d.report_count || 0) + ': ' + names + '</td><td>' +
                        '<button class="btn btn sm" data-dataset-id="' + (d.dataset_id || '').replace(/"/g, '&quot;') + '" data-tenant-id="' + (d.tenant_id || '') + '" data-group-id="' + groupId + '" data-ds-hist>Ver histórico</button> ' +
                        '<button class="btn btn sm sec" data-dataset-id="' + (d.dataset_id || '').replace(/"/g, '&quot;') + '" data-tenant-id="' + (d.tenant_id || '') + '" data-group-id="' + groupId + '" data-ds-refresh>Forçar refresh</button></td></tr>';
                });
                html += '</table>';
            }
            p.innerHTML = html;
            p.querySelectorAll('[data-ds-hist]').forEach(function(btn) {
                btn.onclick = function() {
                    var datasetId = btn.dataset.datasetId || '';
                    var tenantId = btn.dataset.tenantId || '';
                    var groupId = btn.dataset.groupId || '';
                    if (!datasetId || !tenantId || !groupId) return;
                    var q = 'dataset_id=' + encodeURIComponent(datasetId) + '&tenant_id=' + encodeURIComponent(tenantId) + '&group_id=' + encodeURIComponent(groupId) + '&top=30';
                    api('datasets/refreshes?' + q).then(function(r) {
                        if (r.status === 401) { window.location.href = 'login.html'; return; }
                        var titulo = 'Histórico de refresh';
                        var corpo = '';
                        if (!r.data.success) {
                            corpo = '<p class="erro">' + (r.data.message || 'Erro ao carregar histórico.') + '</p>';
                        } else {
                            var hist = r.data.data || [];
                            if (hist.length === 0) corpo = '<p class="vazio">Nenhum registro de refresh.</p>';
                            else {
                                corpo = '<table><tr><th>Início</th><th>Fim</th><th>Status</th><th>Erro</th></tr>';
                                hist.forEach(function(h) {
                                    var status = (h.status || 'Unknown');
                                    var statusClass = status === 'Completed' ? '' : (status === 'Failed' ? ' style="color:#b91c1c;"' : '');
                                    var err = (h.serviceExceptionJson || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                    if (err.length > 80) err = err.substring(0, 80) + '…';
                                    corpo += '<tr><td>' + (h.startTime || '-') + '</td><td>' + (h.endTime || '-') + '</td><td' + statusClass + '>' + status + '</td><td title="' + err + '">' + (err || '-') + '</td></tr>';
                                });
                                corpo += '</table>';
                            }
                        }
                        modal(titulo, corpo, [{ label: 'Fechar', sec: true, fn: fecharModal }]);
                    });
                };
            });
            p.querySelectorAll('[data-ds-refresh]').forEach(function(btn) {
                btn.onclick = function() {
                    var datasetId = btn.dataset.datasetId || '';
                    var tenantId = btn.dataset.tenantId || '';
                    var groupId = btn.dataset.groupId || '';
                    if (!datasetId || !tenantId || !groupId) return;
                    if (!confirm('Disparar refresh deste dataset agora?')) return;
                    api('datasets/refresh', { method: 'POST', body: { dataset_id: datasetId, tenant_id: parseInt(tenantId, 10), group_id: groupId } }).then(function(r) {
                        if (r.status === 401) { window.location.href = 'login.html'; return; }
                        msg(r.data.message || (r.data.success ? 'Refresh disparado.' : 'Erro'), r.data.success);
                    });
                };
            });
        });
    }

    function carregarManual() {
        var p = document.getElementById('painel');
        var html = '';
        html += '<h2>Manual do sistema DashBI 2.0</h2>';
        html += '<div class="painel">';
        html += '<p style="margin-top:0;">Este manual foi pensado para ser um passo a passo completo para administradores e gestores que utilizam o DashBI 2.0 como camada de governança sobre o Power BI.</p>';
        html += '<p>Recomenda-se ler pelo menos uma vez todas as seções abaixo e, depois, usar o manual como consulta rápida sempre que surgir dúvida de configuração.</p>';
        html += '<h3>1. Conceitos básicos</h3>';
        html += '<ul>';
        html += '<li><strong>DashBI 2.0</strong>: camada intermediária entre usuários de negócio e o Power BI. Controla quem vê cada relatório, registra acessos e organiza a distribuição.</li>';
        html += '<li><strong>Tenant</strong>: representa a conexão com o Azure AD / Power BI (Client ID, Secret, Tenant ID, Workspace padrão).</li>';
        html += '<li><strong>Workspace</strong>: área de trabalho do Power BI (Workspace do serviço).</li>';
        html += '<li><strong>Relatório</strong>: relatório Power BI sincronizado e controlado pelo DashBI.</li>';
        html += '<li><strong>Usuário</strong>: pessoa que acessa o DashBI (com login/senha) e visualiza relatórios.</li>';
        html += '<li><strong>Grupo</strong>: conjunto de usuários que compartilham mesmas permissões (ex.: Financeiro, Comercial).</li>';
        html += '<li><strong>Pastas</strong>: agrupadores de relatórios dentro do DashBI (não são pastas físicas no Power BI; servem para organização e concessão de acesso).</li>';
        html += '<li><strong>Permissão</strong>: regra que define quem pode ver cada relatório, com quais páginas e quais filtros.</li>';
        html += '</ul>';
        html += '<p>Fluxo recomendado de configuração:</p>';
        html += '<pre style="background:#f9fafb;padding:0.75rem 1rem;border-radius:6px;overflow:auto;">1. Tenant (Microsoft)  →  2. Sincronizar  →  3. Relatórios no sistema\n        ↓                        ↓\n4. Usuários e Grupos  →  5. Permissões por relatório  →  Quem vê o quê</pre>';

        html += '<h3>2. Acessando as configurações (administração)</h3>';
        html += '<ol>';
        html += '<li>Acesse o DashBI e faça login normalmente.</li>';
        html += '<li>Na tela de relatórios, clique em <strong>Configurações</strong> (visível para perfis <strong>admin</strong> e <strong>master</strong>).</li>';
        html += '<li>O menu lateral exibirá seções como: <strong>Meu perfil</strong>, <strong>Manual do sistema</strong>, <strong>Relatórios e permissões</strong>, <strong>Pastas</strong>, <strong>Usuários</strong>, <strong>Grupos</strong>, <strong>Painel TV</strong>, <strong>Logs</strong>, <strong>Dataset / Refresh</strong> e <strong>Sincronizar</strong> (algumas restritas a master).</li>';
        html += '<li>Use este manual como referência enquanto navega pelas opções do menu.</li>';
        html += '</ol>';

        html += '<h3>3. Cadastro e manutenção de Tenants (conexão Microsoft)</h3>';
        html += '<p><strong>Quem acessa:</strong> apenas perfil <strong>master</strong>, pela opção <strong>Tenants (Microsoft)</strong>.</p>';
        html += '<p>Os tenants determinam qual aplicação Azure/Power BI o DashBI utilizará para listar workspaces e gerar embeds.</p>';
        html += '<p><strong>Passo a passo para configurar um tenant:</strong></p>';
        html += '<ol>';
        html += '<li>No Azure Portal, crie um <em>App Registration</em> e conceda as permissões necessárias do Power BI (por exemplo: <code>Dataset.Read.All</code>, <code>Report.Read.All</code> e permissões de Tenant se aplicável).</li>';
        html += '<li>Copie o <strong>Tenant ID</strong>, <strong>Client ID</strong> e o <strong>Valor</strong> do <strong>Client Secret</strong> (não use o ID do secret).</li>';
        html += '<li>No menu <strong>Tenants (Microsoft)</strong>, clique em <strong>Novo tenant</strong>.</li>';
        html += '<li>Preencha: <strong>Nome</strong>, <strong>Tenant ID</strong>, <strong>Client ID</strong>, <strong>Client Secret</strong> e, se desejar, um <strong>Workspace ID</strong> padrão.</li>';
        html += '<li>Marque a opção <strong>Ativo</strong> e salve.</li>';
        html += '<li>Use o botão <strong>Testar</strong> para validar a conexão. Caso haja erro, verifique se o service principal tem permissão no Power BI Admin para usar as APIs e se foi adicionado como membro aos workspaces desejados.</li>';
        html += '</ol>';
        html += '<p><strong>Dicas:</strong> sempre que alterar secret ou permissões no Azure, volte nesta tela e atualize o tenant.</p>';

        html += '<h3>4. Sincronização com o Power BI</h3>';
        html += '<p><strong>Quem acessa:</strong> perfil <strong>master</strong>, pela opção <strong>Sincronizar</strong>.</p>';
        html += '<p>A sincronização é responsável por trazer para o DashBI:</p>';
        html += '<ul>';
        html += '<li>Lista de <strong>workspaces</strong> e <strong>relatórios</strong> de cada tenant configurado.</li>';
        html += '<li>Informações do catálogo, como descrição, última atualização e dono (quando disponível nas APIs do Power BI).</li>';
        html += '</ul>';
        html += '<p><strong>Como executar manualmente:</strong></p>';
        html += '<ol>';
        html += '<li>No menu, abra <strong>Sincronizar</strong>.</li>';
        html += '<li>Clique em <strong>Sincronizar todos</strong>. O sistema exibirá um resumo com quantidade de workspaces e relatórios trazidos e, abaixo, um detalhamento por tenant/workspace.</li>';
        html += '<li>Se a contagem de workspaces/relatórios vier zero, verifique se o aplicativo do Azure está como <em>membro</em> dos workspaces no Power BI Service e se as permissões de APIs estão liberadas pelo administrador do tenant.</li>';
        html += '</ol>';

        html += '<h3>5. Pastas de relatórios</h3>';
        html += '<p><strong>Quem acessa:</strong> perfis <strong>admin</strong> e <strong>master</strong>, pela opção <strong>Pastas</strong>.</p>';
        html += '<p>Pastas são agrupadores lógicos de relatórios dentro do DashBI. Elas facilitam tanto a navegação dos usuários quanto a concessão de acesso por grupos/usuários.</p>';
        html += '<p><strong>Fluxo recomendado:</strong></p>';
        html += '<ol>';
        html += '<li>Defina uma taxonomia de pastas que faça sentido para a empresa (ex.: &quot;Financeiro / Faturamento&quot;, &quot;Comercial / Vendas Regionais&quot;, &quot;Diretoria&quot;).</li>';
        html += '<li>Crie as pastas na tela <strong>Pastas</strong> (nome, descrição e se está ativa).</li>';
        html += '<li>Para cada pasta, clique em <strong>Relatórios</strong> e associe os relatórios que pertencem a ela.</li>';
        html += '<li>Ao configurar usuários e grupos, selecione as pastas às quais eles devem ter acesso para que os relatórios apareçam na dashboard.</li>';
        html += '</ol>';

        html += '<h3>6. Usuários</h3>';
        html += '<p><strong>Quem acessa:</strong> perfis <strong>admin</strong> e <strong>master</strong>, pela opção <strong>Usuários</strong>.</p>';
        html += '<p>Nesta tela você cadastra e mantém os usuários que efetivamente fazem login no DashBI.</p>';
        html += '<p><strong>Campos principais:</strong></p>';
        html += '<ul>';
        html += '<li><strong>Nome</strong>: nome da pessoa.</li>';
        html += '<li><strong>E-mail</strong>: usado como login; precisa ser único.</li>';
        html += '<li><strong>Perfil</strong>: define o nível de permissão no sistema (usuário, admin, master).</li>';
        html += '<li><strong>Empresa ID (RLS)</strong>: opcional, usado para cenários multi-tenant com RLS (EffectiveIdentity).</li>';
        html += '<li><strong>Grupos</strong>: seleção de grupos aos quais o usuário pertence.</li>';
        html += '<li><strong>Pastas</strong>: define acesso às pastas de relatórios (em conjunto com permissões e grupos).</li>';
        html += '<li><strong>Ativo</strong>: controla se o usuário pode acessar o sistema.</li>';
        html += '</ul>';
        html += '<p><strong>Criação de usuário:</strong> clique em <strong>Novo usuário</strong>, preencha os campos, selecione grupos e pastas, e salve. Dependendo da configuração, a senha inicial poderá ser enviada por e-mail ou definida manualmente.</p>';

        html += '<h3>7. Grupos</h3>';
        html += '<p><strong>Quem acessa:</strong> perfis <strong>admin</strong> e <strong>master</strong>, pela opção <strong>Grupos</strong>.</p>';
        html += '<p>Grupos simplificam a gestão de permissões: em vez de conceder acesso relatório a relatório para cada usuário, você concede aos grupos e depois inclui os usuários nesses grupos.</p>';
        html += '<p><strong>Boas práticas:</strong></p>';
        html += '<ul>';
        html += '<li>Crie grupos pensando em funções ou áreas de negócio (ex.: &quot;Financeiro&quot;, &quot;Controladoria&quot;, &quot;Diretoria Executiva&quot;).</li>';
        html += '<li>Evite grupos vazios ou sobrepostos demais, para não gerar confusão.</li>';
        html += '<li>Use as pastas associadas ao grupo para filtrar automaticamente quais relatórios aquele grupo enxerga na dashboard.</li>';
        html += '</ul>';

        html += '<h3>8. Relatórios e permissões</h3>';
        html += '<p><strong>Quem acessa:</strong> perfis <strong>admin</strong> e <strong>master</strong>, pela opção <strong>Relatórios e permissões</strong>.</p>';
        html += '<p>Esta é a tela central de governança dos relatórios.</p>';
        html += '<p><strong>Passos para configurar permissões de um relatório:</strong></p>';
        html += '<ol>';
        html += '<li>Selecione o <strong>Tenant</strong>.</li>';
        html += '<li>Selecione o <strong>Workspace</strong>.</li>';
        html += '<li>Selecione o <strong>Relatório</strong> desejado.</li>';
        html += '<li>Na área &quot;Permissões deste relatório&quot;, visualize quem já tem acesso e seus detalhes.</li>';
        html += '<li>Clique em <strong>Adicionar permissão</strong> para criar uma nova regra.</li>';
        html += '</ol>';
        html += '<p><strong>Ao adicionar/editar uma permissão:</strong></p>';
        html += '<ul>';
        html += '<li>Escolha se é por <strong>Usuário</strong> ou por <strong>Grupo</strong>.</li>';
        html += '<li>Defina se <strong>pode visualizar</strong> o relatório (sem isso, não aparece na lista do usuário).</li>';
        html += '<li>Decida se as <strong>abas (páginas)</strong> do relatório serão exibidas e, opcionalmente, quais páginas estão permitidas.</li>';
        html += '<li>Defina se o <strong>painel de filtros</strong> do Power BI deve ser visível.</li>';
        html += '<li>Informe uma <strong>data fim</strong> de validade, se o acesso for temporário.</li>';
        html += '</ul>';
        html += '<p>Ao salvar, o sistema passa a considerar essa permissão sempre que o usuário tentar visualizar o relatório (inclusive para geração do embed token).</p>';

        html += '<h3>9. Edição de metadados do relatório</h3>';
        html += '<p>Ainda na tela de <strong>Relatórios e permissões</strong>, use o botão <strong>Editar relatório</strong> para ajustar metadados do catálogo.</p>';
        html += '<p>Nesta tela é possível:</p>';
        html += '<ul>';
        html += '<li>Alterar o <strong>nome</strong> exibido do relatório.</li>';
        html += '<li>Visualizar a <strong>descrição</strong> em modo somente leitura (vem da sincronização com o Power BI).</li>';
        html += '<li>Alterar o campo <strong>Dono do relatório</strong>, caso deseje exibir um texto diferente do que vem da API.</li>';
        html += '<li>Ver a <strong>data da última atualização</strong> do dataset.</li>';
        html += '<li>Anexar uma <strong>imagem de identificação</strong> personalizada para o card do relatório na dashboard.</li>';
        html += '</ul>';

        html += '<h3>10. Painel TV</h3>';
        html += '<p><strong>Quem acessa:</strong> perfis <strong>admin</strong> e <strong>master</strong>, pela opção <strong>Painel TV</strong>.</p>';
        html += '<p>O Painel TV permite montar playlists de relatórios para exibição contínua em monitores (ex.: sala da diretoria, chão de fábrica, NOC etc.).</p>';
        html += '<p><strong>Configurações principais:</strong></p>';
        html += '<ul>';
        html += '<li><strong>Nome do painel</strong>: identificação da playlist.</li>';
        html += '<li><strong>Modo de reprodução</strong>: loop contínuo ou uma única passada.</li>';
        html += '<li><strong>Mostrar contador</strong>: exibe na tela quanto tempo falta para trocar de relatório.</li>';
        html += '</ul>';
        html += '<p>Ao entrar em <strong>Itens</strong>, você define:</p>';
        html += '<ul>';
        html += '<li>Quais relatórios entram no painel.</li>';
        html += '<li>Qual <strong>página</strong> de cada relatório deve ser exibida.</li>';
        html += '<li>Quanto tempo (em segundos) cada item ficará na tela.</li>';
        html += '<li>A <strong>ordem</strong> dos itens (arraste para reordenar).</li>';
        html += '</ul>';

        html += '<h3>11. Logs de acesso</h3>';
        html += '<p><strong>Quem acessa:</strong> perfil <strong>master</strong>, pela opção <strong>Logs de acesso</strong>.</p>';
        html += '<p>Esta tela mostra:</p>';
        html += '<ul>';
        html += '<li>Relatórios mais acessados.</li>';
        html += '<li>Usuários mais ativos.</li>';
        html += '<li>Horários de pico de acesso.</li>';
        html += '<li>Lista detalhada dos últimos acessos, com data, usuário, relatório, ação e IP.</li>';
        html += '</ul>';
        html += '<p>Use essas informações para identificar relatórios críticos, usuários-chave e possíveis gargalos ou acessos indevidos.</p>';

        html += '<h3>12. Dataset / Refresh</h3>';
        html += '<p><strong>Quem acessa:</strong> perfil <strong>master</strong>, pela opção <strong>Dataset / Refresh</strong>.</p>';
        html += '<p>Nesta tela você acompanha o status de refresh dos datasets Power BI relacionados aos relatórios controlados pelo DashBI.</p>';
        html += '<p>Você pode:</p>';
        html += '<ul>';
        html += '<li>Ver o histórico de refresh de um dataset específico.</li>';
        html += '<li>Disparar manualmente um refresh (quando as permissões do Power BI permitirem).</li>';
        html += '</ul>';

        html += '<h3>13. Dashboard do usuário final</h3>';
        html += '<p>Os usuários que não são administradores acessam principalmente a tela <strong>Ver relatórios</strong> (dashboard).</p>';
        html += '<p>Nela, cada relatório aparece como um <strong>card</strong> com:</p>';
        html += '<ul>';
        html += '<li>Nome e descrição.</li>';
        html += '<li>Imagem (padrão ou personalizada).</li>';
        html += '<li>Data da última atualização.</li>';
        html += '</ul>';
        html += '<p>O usuário pode:</p>';
        html += '<ul>';
        html += '<li>Clicar no card para abrir o embed do relatório.</li>';
        html += '<li>Marcar como <strong>favorito</strong>.</li>';
        html += '<li>Fixar relatórios na área de <strong>Fixados</strong> para acesso rápido.</li>';
        html += '</ul>';

        html += '<h3>14. Problemas comuns e como verificar</h3>';
        html += '<ul>';
        html += '<li><strong>Relatório não aparece para o usuário:</strong> verifique se existe permissão por usuário ou por grupo, se o usuário está nos grupos corretos e se a pasta do relatório está associada ao usuário/grupo.</li>';
        html += '<li><strong>Erro de acesso negado no embed:</strong> confirme as permissões de <strong>PermissionService</strong> (permite visualizar) e se a data de validade da permissão não expirou.</li>';
        html += '<li><strong>Sincronização não traz nenhum relatório:</strong> confira se o aplicativo do Azure está como membro dos workspaces no Power BI e se o admin habilitou o uso de service principals nas configurações do tenant.</li>';
        html += '<li><strong>Logs vazios:</strong> verifique se os usuários realmente acessaram relatórios e se não há bloqueios de gravação no banco ou no storage de logs.</li>';
        html += '</ul>';

        html += '<h3>15. Recomendações de governança</h3>';
        html += '<ul>';
        html += '<li>Mantenha um número controlado de <strong>admins</strong> e apenas um ou poucos <strong>masters</strong>.</li>';
        html += '<li>Use grupos para refletir a estrutura organizacional (cargos/áreas), não pessoas individuais.</li>';
        html += '<li>Revise periodicamente as permissões de relatórios e as datas de validade.</li>';
        html += '<li>Acompanhe logs de acesso e dashboards de uso para identificar relatórios obsoletos ou pouco usados.</li>';
        html += '</ul>';

        html += '<p style="margin-top:1.25rem;">Este manual resume as principais operações do DashBI 2.0 dentro da área de Configurações. Em caso de customizações específicas no seu ambiente, registre essas particularidades em documentação interna complementar.</p>';
        html += '</div>';
        p.innerHTML = html;
    }

    function carregarSync() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Sincronizar</h2><p>Trazer workspaces e relatórios do Power BI para o sistema (todos os tenants ativos).</p><button class="btn" id="btnSync">Sincronizar todos</button><div id="syncResult" style="margin-top:0.75rem;"></div>';
        document.getElementById('btnSync').onclick = function() {
            var el = document.getElementById('syncResult');
            el.innerHTML = '<span class="carregando">Sincronizando…</span>';
            api('sync/all', { method: 'POST' }).then(function(x) {
                if (x.status === 401) { window.location.href = 'login.html'; return; }
                if (x.data.success) {
                    var w = x.data.data.workspaces || 0;
                    var r = x.data.data.reports || 0;
                    el.innerHTML = '<p class="msg ok">OK: ' + w + ' workspaces, ' + r + ' relatórios.</p>';
                    var tenantDetails = x.data.data.tenants || [];
                    if (tenantDetails.length > 0) {
                        var html = '<div style="margin-top:0.75rem;"><h3 style="margin:0 0 0.5rem 0;">Detalhes da sincronização</h3>';
                        tenantDetails.forEach(function(t) {
                            html += '<div style="border:1px solid #e5e7eb;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.5rem;background:#fff;">' +
                                '<div><strong>Tenant:</strong> ' + (t.tenant_name || ('ID ' + t.tenant_id)) + '</div>' +
                                '<div><strong>Workspaces:</strong> ' + (t.workspaces || 0) + ' | <strong>Relatórios:</strong> ' + (t.reports || 0) + '</div>';
                            var wsList = t.workspace_items || [];
                            if (wsList.length > 0) {
                                html += '<ul style="margin:0.5rem 0 0 1rem;padding:0;">';
                                wsList.forEach(function(ws) {
                                    html += '<li style="margin-bottom:0.35rem;"><strong>' + (ws.workspace_name || 'Sem nome') + '</strong> ' +
                                        '(<code>' + (ws.workspace_id || '-') + '</code>)';
                                    var reps = ws.reports || [];
                                    if (reps.length > 0) {
                                        html += '<ul style="margin:0.25rem 0 0 1rem;padding:0;">';
                                        reps.forEach(function(rep) {
                                            html += '<li>' + (rep.report_name || 'Sem nome') + ' ' +
                                                '(<code>' + (rep.report_id || '-') + '</code>)</li>';
                                        });
                                        html += '</ul>';
                                    } else {
                                        html += ' - sem relatórios';
                                    }
                                    html += '</li>';
                                });
                                html += '</ul>';
                            }
                            html += '</div>';
                        });
                        html += '</div>';
                        el.innerHTML += html;
                    }
                    if (w === 0 && r === 0) el.innerHTML += '<p class="vazio" style="margin-top:0.5rem;"><strong>Nenhum workspace encontrado?</strong> O aplicativo (Service Principal) precisa ser <strong>membro de cada workspace</strong> no Power BI. No Power BI Service, abra cada workspace → Acesso → Adicionar pessoas ou grupos → informe o nome do seu aplicativo Azure. Veja também: Configurações do locatário no Admin do Power BI → permitir que service principals usem as APIs.</p>';
                } else {
                    el.innerHTML = '<p class="msg err">Erro: ' + (x.data.message || '') + '</p>';
                }
            });
        };
    }

    function carregarPerfil() {
        var p = document.getElementById('painel');
        p.innerHTML = '<h2>Meu perfil</h2><p class="carregando">Carregando…</p>';
        api('auth/me').then(function(x) {
            if (x.status === 401) { window.location.href = 'login.html'; return; }
            if (!x.data.success) { p.innerHTML = '<h2>Meu perfil</h2><p class="erro">' + (x.data.message || 'Erro') + '</p>'; return; }
            var u = x.data.data;
            var html = '<h2>Meu perfil</h2>' +
                '<form id="formPerfil" class="painel">' +
                '<div class="form-g"><label>Nome de usuário (e-mail)</label><input type="text" id="p_email" value="' + (u.email || '').replace(/"/g, '&quot;') + '" readonly class="input-readonly"></div>' +
                '<div class="form-g"><label>Nome</label><input type="text" id="p_nome" value="' + (u.nome || '').replace(/"/g, '&quot;') + '" placeholder="Seu nome"></div>' +
                '<div class="form-g"><label>Nova senha</label><input type="password" id="p_senha" placeholder="Deixe em branco para não alterar" autocomplete="new-password"></div>' +
                '<div class="form-g"><label>Confirmar nova senha</label><input type="password" id="p_confirmar" placeholder="Repita a nova senha" autocomplete="new-password"></div>' +
                '<button type="submit" class="btn">Salvar</button>' +
                '</form>';
            p.innerHTML = html;
            document.getElementById('formPerfil').addEventListener('submit', function(e) {
                e.preventDefault();
                var nome = document.getElementById('p_nome').value.trim();
                var senha = document.getElementById('p_senha').value;
                var confirmar = document.getElementById('p_confirmar').value;
                if (senha !== confirmar) { msg('Senha e confirmação não conferem.', false); return; }
                var body = { nome: nome };
                if (senha) { body.senha = senha; body.confirmar_senha = confirmar; }
                api('auth/profile', { method: 'PUT', body: body }).then(function(r) {
                    msg(r.data.message || 'Perfil atualizado', r.data.success);
                    if (r.data.success) {
                        document.getElementById('p_senha').value = '';
                        document.getElementById('p_confirmar').value = '';
                        document.getElementById('userNome').textContent = (r.data.data.nome || r.data.data.email || 'Usuário');
                    }
                });
            });
        });
    }

    var userPerfil = '';
    function mostrar(sec) {
        var masterOnly = ['tenants', 'logs', 'datasets', 'sync'].indexOf(sec) >= 0;
        var adminOnly = ['reports', 'folders', 'tvpanels', 'users', 'groups'].indexOf(sec) >= 0;
        if (masterOnly && userPerfil !== 'master') {
            document.querySelectorAll('.menu a').forEach(function(a) { a.classList.remove('ativo'); });
            document.getElementById('painel').innerHTML = '<h2>Acesso negado</h2><p class="erro">Apenas usuários com perfil <strong>master</strong> podem acessar Tenants, Logs de acesso, Dataset/Refresh e Sincronização.</p>';
            return;
        }
        if (adminOnly && userPerfil !== 'master' && userPerfil !== 'admin') {
            document.querySelectorAll('.menu a').forEach(function(a) { a.classList.remove('ativo'); });
            document.getElementById('painel').innerHTML = '<h2>Acesso negado</h2><p class="erro">Apenas perfis <strong>master</strong> e <strong>administrador</strong> podem editar relatórios, Painel TV, usuários e grupos.</p>';
            return;
        }
        document.querySelectorAll('.menu a').forEach(function(a) { a.classList.remove('ativo'); if (a.dataset.sec === sec) a.classList.add('ativo'); });
        if (sec === 'profile') carregarPerfil();
        else if (sec === 'manual') carregarManual();
        else if (sec === 'tenants') carregarTenants();
        else if (sec === 'users') carregarUsuarios();
        else if (sec === 'groups') carregarGrupos();
        else if (sec === 'folders') carregarPastas();
        else if (sec === 'reports') carregarRelatoriosPermissoes();
        else if (sec === 'tvpanels') carregarTvPanels();
        else if (sec === 'logs') carregarLogs();
        else if (sec === 'datasets') carregarDatasets();
        else if (sec === 'sync') carregarSync();
        else document.getElementById('painel').innerHTML = '<h2>DashBI 2.0</h2><p>Use o menu para administrar tenants, usuários, grupos, relatórios e permissões.</p>';
    }
    document.querySelectorAll('a[data-sec]').forEach(function(a) {
        a.addEventListener('click', function(e) { e.preventDefault(); mostrar(a.dataset.sec); });
    });
    api('auth/me').then(function(x) {
        if (x.status !== 200 || !x.data.success) { window.location.href = 'login.html'; return; }
        var user = x.data.data;
        if (user.trocar_senha_proximo_acesso) { window.location.href = 'trocar-senha.html'; return; }
        document.getElementById('userNome').textContent = (user.nome || user.email || 'Usuário');
        userPerfil = (user.perfil || '').toLowerCase();
        var isMaster = userPerfil === 'master';
        var isAdminOrMaster = isMaster || userPerfil === 'admin';
        document.querySelectorAll('.menu a[data-master-only]').forEach(function(a) {
            a.style.display = isMaster ? '' : 'none';
        });
        document.querySelectorAll('.menu a[data-admin-only]').forEach(function(a) {
            a.style.display = isAdminOrMaster ? '' : 'none';
        });
        // Ao entrar nas configurações:
        // - Master: abre por padrão em Painel TV
        // - Admin: abre em Relatórios/Permissões
        // - Demais perfis: abre em Meu perfil
        if (isMaster) mostrar('tvpanels');
        else if (isAdminOrMaster) mostrar('reports');
        else mostrar('profile');
    });
    document.getElementById('sair').addEventListener('click', function(e) {
        e.preventDefault();
        api('auth/logout', { method: 'POST' }).then(function() { window.location.href = 'login.html'; });
    });
})();
