<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/dashbi02.png">
    <title>Relatórios - DashBI 2.0</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background: #f0f2f5; }
        .topo {
            background: #0B1E3C;
            color: #fff;
            padding: 0.5rem 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topo .logo { height: 50px; width: auto; display: block; margin-right: 0.25rem; }
        .topo .titulo { display: flex; align-items: center; }
        .topo h1 { margin: 0; font-size: 1.25rem; }
        .topo a { color: #93c5fd; text-decoration: none; font-size: 0.9rem; }
        .layout { display: flex; min-height: calc(100vh - 48px); }
        .menu { width: 220px; background: #fff; border-right: 1px solid #e5e7eb; padding: 1rem 0; }
        .menu a { display: block; padding: 0.5rem 1.25rem; color: #374151; text-decoration: none; font-size: 0.9rem; }
        .menu a:hover { background: #f3f4f6; }
        .menu a.ativo { background: #eff6ff; color: #2563eb; font-weight: 600; }
        .conteudo { flex: 1; padding: 1.5rem; overflow: auto; }
        .conteudo.viewer-active { padding: 0.35rem 0.5rem; }
        .conteudo.viewer-active .viewer { margin-top: 0.25rem; }
        .conteudo.viewer-active #reportContainer { height: calc(100vh - 140px); min-height: 420px; }
        .card-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
        .workspace-list { display: flex; flex-direction: column; gap: 1rem; align-items: stretch; }
        .ws-group { margin-bottom: 1.25rem; }
        .workspace-list .ws-group { width: 100%; margin-bottom: 0; }
        .ws-title { margin: 0 0 0.65rem 0; font-size: 1rem; color: #334155; }
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            padding: 1rem 1.25rem 1.25rem;
            cursor: pointer;
            transition: box-shadow .2s;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 0;
        }
        .card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12); }
        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
            min-height: 2rem;
        }
        .card-header h3 {
            margin: 0;
            font-size: 1rem;
            color: #1f2937;
            line-height: 1.35;
            flex: 1;
            min-width: 0;
            padding-right: 0.25rem;
        }
        .card-actions {
            display: flex;
            gap: 0.2rem;
            flex-shrink: 0;
        }
        .card-actions button {
            background: #f3f4f6;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            padding: 0.35rem 0.4rem;
            font-size: 1rem;
            line-height: 1;
            opacity: 0.85;
            transition: background .15s, opacity .15s;
        }
        .card-actions button:hover { background: #e5e7eb; opacity: 1; }
        .card-actions button.ativo { background: #dbeafe; color: #1d4ed8; opacity: 1; }
        .card-actions .card-btn-fixar.ativo { background: #fef3c7; color: #b45309; }
        .card-body { display: flex; align-items: flex-start; gap: 0.75rem; min-width: 0; }
        .card-img-wrap { width: 52px; height: 52px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #f3f4f6; display: flex; align-items: center; justify-content: center; }
        .card-img-wrap img { width: 100%; height: 100%; object-fit: contain; }
        .card-content { flex: 1; min-width: 0; }
        .card-content p { margin: 0; font-size: 0.85rem; color: #6b7280; line-height: 1.4; }
        .card-meta { font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem; }
        .fixados-section { margin-bottom: 1.25rem; }
        .fixados-section .ws-title { color: #1e40af; }
        .viewer { margin-top: 1rem; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
        .viewer-header { padding: 0.75rem 1rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .viewer-header h2 { margin: 0; font-size: 1rem; }
        .viewer-header .back { color: #2563eb; cursor: pointer; font-size: 0.9rem; }
        .viewer-actions { display: flex; align-items: center; gap: 0.75rem; }
        .viewer-actions .action { color: #2563eb; cursor: pointer; font-size: 0.9rem; }
        #reportContainer { height: 72vh; min-height: 420px; }
        .msg { padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
        .msg.err { background: #fee2e2; color: #b91c1c; }
        .msg.ok { background: #dcfce7; color: #166534; }
        .loading { padding: 2rem; text-align: center; color: #6b7280; }
        .hidden { display: none; }
        .kpis-section { margin-bottom: 1.5rem; }
        .kpis-section h2 { margin: 0 0 0.75rem 0; font-size: 1.1rem; color: #334155; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; }
        .kpi-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            padding: 0.85rem 1rem;
            border-left: 4px solid #2563eb;
        }
        .kpi-card .kpi-val { font-size: 1.5rem; font-weight: 700; color: #1f2937; line-height: 1.2; }
        .kpi-card .kpi-label { font-size: 0.75rem; color: #6b7280; margin-top: 0.2rem; }
        .kpi-list { margin-top: 0.75rem; }
        .kpi-list h4 { margin: 0 0 0.35rem 0; font-size: 0.85rem; color: #374151; }
        .kpi-list ul { margin: 0; padding: 0 0 0 1rem; font-size: 0.8rem; color: #6b7280; }
        .kpi-list li { margin-bottom: 0.2rem; }
        .kpi-resumo-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            margin-top: 0.75rem;
            padding: 0.5rem;
            color: #2563eb;
            font-size: 0.9rem;
            cursor: pointer;
            background: linear-gradient(to bottom, transparent 0%, rgba(255,255,255,0.9) 20%);
            border: none;
            width: 100%;
            border-radius: 0 0 8px 8px;
            transition: color .2s, background .2s;
        }
        .kpi-resumo-toggle:hover { color: #1d4ed8; background: rgba(241,245,249,0.95); }
        .kpi-chevron { font-size: 0.75rem; transition: transform .25s ease; }
        .kpi-lists-wrap {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s ease-out;
        }
        .kpi-lists-wrap.expanded { max-height: 600px; }
        .kpi-lists-wrap .kpi-lists-inner { padding-top: 0.5rem; }
        .report-page-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            padding: 0.5rem 1rem;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .report-page-tabs:not(.hidden) { display: flex; }
        .report-page-tabs .page-tab {
            padding: 0.4rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            font-size: 0.85rem;
            color: #374151;
        }
        .report-page-tabs .page-tab:hover { background: #f3f4f6; }
        .report-page-tabs .page-tab.ativo { background: #2563eb; color: #fff; border-color: #2563eb; }
        .folder-section { margin-bottom: 1rem; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .folder-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #f9fafb;
            cursor: pointer;
            user-select: none;
            border: none;
            width: 100%;
            text-align: left;
            font: inherit;
            color: #334155;
        }
        .folder-header:hover { background: #f3f4f6; }
        .folder-header .folder-chevron {
            font-size: 0.75rem;
            color: #6b7280;
            transition: transform .2s ease;
        }
        .folder-section.collapsed .folder-chevron { transform: rotate(-90deg); }
        .folder-header .folder-name { flex: 1; font-weight: 600; font-size: 1rem; }
        .folder-header .folder-count { font-size: 0.85rem; color: #6b7280; }
        .folder-body { overflow: hidden; transition: max-height .25s ease-out; }
        .folder-section.collapsed .folder-body { max-height: 0 !important; }
        .folder-body-inner { padding: 1rem; }
    </style>
</head>
<body>
    <header class="topo">
        <div class="titulo">
            <img src="../img/dashbi03.png" alt="DashBI" class="logo">
            <h1>DashBI</h1>
        </div>
        <span><strong id="userName">--</strong> &nbsp; <a href="login.html">Sair</a></span>
    </header>
    <div class="layout">
        <nav class="menu">
            <a href="admin.html" id="menuPerfil">Meu perfil</a>
            <a href="dashboard.php" class="ativo">Ver relatórios</a>
            <a href="admin.html" id="menuConfig" class="hidden">Configurações</a>
            <a href="login.html">Sair</a>
        </nav>
        <main class="conteudo">
            <div id="msg" class="msg hidden"></div>
            <div id="kpisSection" class="kpis-section hidden">
                <h2>Resumo</h2>
                <div id="kpiGrid" class="kpi-grid"></div>
                <button type="button" id="kpiResumoToggle" class="kpi-resumo-toggle hidden" aria-expanded="false">Ver mais detalhes <span class="kpi-chevron">▼</span></button>
                <div id="kpiListsWrap" class="kpi-lists-wrap">
                    <div id="kpiLists" class="kpi-lists-inner" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:1rem;"></div>
                </div>
            </div>
            <div id="listSection">
                <h2>Meus relatórios</h2>
                <div id="reportList" class="card-list">
                    <p class="loading">Carregando…</p>
                </div>
            </div>
            <div id="viewerSection" class="viewer hidden">
                <div class="viewer-header">
                    <h2 id="reportTitle">Relatório</h2>
                    <div class="viewer-actions">
                        <span class="action" id="refreshReport">↻ Atualizar</span>
                        <span class="action" id="fullReport">⛶ Tela cheia</span>
                        <span class="back" id="backToList">← Voltar</span>
                    </div>
                </div>
                <div id="reportPageTabs" class="report-page-tabs hidden"></div>
                <div id="reportContainer"></div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/powerbi-client@2.21.0/dist/powerbi.min.js"></script>
    <script>
        (function() {
            var BASE = '';
            function api(path, opts) {
                opts = opts || {};
                opts.credentials = 'include';
                opts.headers = opts.headers || {};
                if (opts.body !== undefined && typeof opts.body !== 'string') opts.body = JSON.stringify(opts.body);
                opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
                var url = (BASE || '') + path;
                if (url.indexOf('api/') !== 0 && url.indexOf('/api/') !== 0) url = 'api/' + path;
                return fetch(url, opts).then(function(r) {
                    return r.text().then(function(txt) {
                        var data = null;
                        try {
                            data = txt ? JSON.parse(txt) : {};
                        } catch (e) {
                            data = {
                                success: false,
                                message: txt && txt.trim() ? txt.trim().slice(0, 500) : ('Resposta inválida da API (HTTP ' + r.status + ')')
                            };
                        }
                        return { status: r.status, data: data };
                    });
                }).catch(function(err) {
                    return {
                        status: 0,
                        data: { success: false, message: 'Falha de rede: ' + (err && err.message ? err.message : 'erro desconhecido') }
                    };
                });
            }
            function showMsg(txt, ok) {
                var m = document.getElementById('msg');
                m.textContent = txt;
                m.className = 'msg ' + (ok ? 'ok' : 'err');
                m.classList.remove('hidden');
                setTimeout(function() { m.classList.add('hidden'); }, 5000);
            }
            var listSection = document.getElementById('listSection');
            var viewerSection = document.getElementById('viewerSection');
            var reportContainer = document.getElementById('reportContainer');
            var reportTitle = document.getElementById('reportTitle');
            var embedInstance = null;
            var currentReportId = null;
            var currentReportName = '';
            var currentLogAcessoId = null;
            var embedOpenTime = null;
            function sendCloseLogAcesso() {
                if (!currentLogAcessoId) return;
                var segundos = embedOpenTime ? Math.max(0, Math.floor((Date.now() - embedOpenTime) / 1000)) : 0;
                api('reports/log-acesso/' + currentLogAcessoId, { method: 'PUT', body: { tempo_visualizacao: segundos } });
                currentLogAcessoId = null;
                embedOpenTime = null;
            }

            function resetEmbed() {
                if (embedInstance) {
                    try {
                        embedInstance.off('loaded');
                        embedInstance.off('error');
                    } catch (e) {}
                }
                if (window.powerbi && reportContainer) {
                    try { window.powerbi.reset(reportContainer); } catch (e) {}
                }
                embedInstance = null;
                reportContainer.innerHTML = '';
                var tabsEl = document.getElementById('reportPageTabs');
                if (tabsEl) { tabsEl.classList.add('hidden'); tabsEl.innerHTML = ''; }
            }

            var isAdminOrMaster = false;
            function loadUser() {
                return api('auth/me').then(function(x) {
                    if (x.status === 401) { window.location.href = 'login.html'; return null; }
                    if (x.data.success && x.data.data) {
                        var user = x.data.data;
                        if (user.trocar_senha_proximo_acesso) {
                            window.location.href = 'trocar-senha.html';
                            return null;
                        }
                        document.getElementById('userName').textContent = user.nome || user.email || 'Usuário';
                        var perfil = (user.perfil || '').toLowerCase();
                        isAdminOrMaster = (perfil === 'admin' || perfil === 'master');
                        if (isAdminOrMaster) {
                            var menuConfig = document.getElementById('menuConfig');
                            if (menuConfig) menuConfig.classList.remove('hidden');
                        }
                        return user;
                    }
                    return null;
                });
            }
            var favoritosData = { favoritos: [], fixados: [] };
            var lastDashboardData = null;
            var lastAllReports = null;
            var lastReports = null;

            function loadReports() {
                return api('reports').then(function(x) {
                    if (x.status === 401) { window.location.href = 'login.html'; return []; }
                    if (!x.data.success) return [];
                    return x.data.data || [];
                });
            }

            function loadFoldersForDashboard() {
                return api('folders/for-dashboard').then(function(x) {
                    if (x.status === 401) { window.location.href = 'login.html'; return null; }
                    if (!x.data.success || !x.data.data) return null;
                    return x.data.data;
                });
            }
            function loadFavoritos() {
                return api('favoritos').then(function(x) {
                    if (x.status === 401) return;
                    if (x.data.success && x.data.data) favoritosData = x.data.data;
                });
            }
            function loadKpis() {
                return api('dashboard/kpis').then(function(x) {
                    if (x.status === 401) return null;
                    if (x.data.success && x.data.data) return x.data.data;
                    return null;
                });
            }
            function renderKpis(k) {
                if (!k) return;
                document.getElementById('kpisSection').classList.remove('hidden');
                var grid = document.getElementById('kpiGrid');
                var fmtNum = function(n) { return n == null ? '0' : (Number(n).toLocaleString ? Number(n).toLocaleString('pt-BR') : n); };
                var fmtTempo = function(seg) {
                    if (seg == null || seg <= 0) return '-';
                    if (seg < 60) return seg + 's';
                    var m = Math.floor(seg / 60);
                    var s = Math.round(seg % 60);
                    return s > 0 ? m + 'min ' + s + 's' : m + ' min';
                };
                grid.innerHTML =
                    '<div class="kpi-card"><div class="kpi-val">' + fmtNum(k.total_relatorios) + '</div><div class="kpi-label">Total de relatórios</div></div>' +
                    '<div class="kpi-card"><div class="kpi-val">' + fmtNum(k.relatorios_atualizados_ultima_semana) + '</div><div class="kpi-label">Atualizados (7 dias)</div></div>' +
                    '<div class="kpi-card"><div class="kpi-val">' + fmtNum(k.acessos_hoje) + '</div><div class="kpi-label">Acessos hoje</div></div>' +
                    '<div class="kpi-card"><div class="kpi-val">' + fmtNum(k.acessos_semana) + '</div><div class="kpi-label">Acessos (7 dias)</div></div>' +
                    '<div class="kpi-card"><div class="kpi-val">' + fmtNum(k.acessos_mes) + '</div><div class="kpi-label">Acessos (30 dias)</div></div>' +
                    '<div class="kpi-card"><div class="kpi-val">' + fmtTempo(k.tempo_medio_visualizacao_segundos) + '</div><div class="kpi-label">Tempo médio (visualização)</div></div>' +
                    '<div class="kpi-card"><div class="kpi-val">' + fmtNum(k.favoritos_count) + '</div><div class="kpi-label">Favoritos</div></div>' +
                    '<div class="kpi-card"><div class="kpi-val">' + fmtNum(k.fixados_count) + '</div><div class="kpi-label">Fixados</div></div>';
                var listEl = document.getElementById('kpiLists');
                var listHtml = '';
                if (k.relatorios_mais_acessados && k.relatorios_mais_acessados.length > 0) {
                    listHtml += '<div class="kpi-list"><h4>Relatórios mais acessados</h4><ul>';
                    k.relatorios_mais_acessados.forEach(function(r) {
                        listHtml += '<li>' + (r.report_nome || '#' + r.relatorio_id).replace(/</g, '&lt;') + ' <strong>' + (r.total_acessos || 0) + '</strong></li>';
                    });
                    listHtml += '</ul></div>';
                }
                if (k.usuarios_mais_ativos && k.usuarios_mais_ativos.length > 0) {
                    listHtml += '<div class="kpi-list"><h4>Usuários mais ativos</h4><ul>';
                    k.usuarios_mais_ativos.forEach(function(u) {
                        listHtml += '<li>' + (u.user_nome || u.user_email || '#' + u.usuario_id).replace(/</g, '&lt;') + ' <strong>' + (u.total_acessos || 0) + '</strong></li>';
                    });
                    listHtml += '</ul></div>';
                }
                if (k.relatorios_sem_acesso_recente && k.relatorios_sem_acesso_recente.length > 0) {
                    listHtml += '<div class="kpi-list"><h4>Sem acesso há 30+ dias</h4><ul>';
                    k.relatorios_sem_acesso_recente.slice(0, 8).forEach(function(r) {
                        listHtml += '<li>' + (r.nome || '#' + r.id).replace(/</g, '&lt;') + '</li>';
                    });
                    if (k.relatorios_sem_acesso_recente.length > 8) listHtml += '<li>+ mais ' + (k.relatorios_sem_acesso_recente.length - 8) + '</li>';
                    listHtml += '</ul></div>';
                }
                listEl.innerHTML = listHtml || '';
                var toggleBtn = document.getElementById('kpiResumoToggle');
                var listsWrap = document.getElementById('kpiListsWrap');
                var chevron = toggleBtn ? toggleBtn.querySelector('.kpi-chevron') : null;
                if (listHtml && toggleBtn && listsWrap) {
                    toggleBtn.classList.remove('hidden');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    listsWrap.classList.remove('expanded');
                    if (chevron) chevron.textContent = '▼';
                    toggleBtn.onclick = function() {
                        var isExp = listsWrap.classList.toggle('expanded');
                        toggleBtn.setAttribute('aria-expanded', isExp ? 'true' : 'false');
                        if (chevron) chevron.textContent = isExp ? '▲' : '▼';
                    };
                } else if (toggleBtn) {
                    toggleBtn.classList.add('hidden');
                }
            }
            function renderCard(r, isFavorito, isFixado) {
                var imgPadrao = '../img/dashbi02.png';
                var imgSrc = (r.imagem && r.imagem.trim()) ? r.imagem.trim() : imgPadrao;
                var desc = (r.descricao || '').trim();
                if (desc.length > 100) desc = desc.slice(0, 97) + '...';
                var ultima = (r.ultima_atualizacao || '').toString().trim();
                if (ultima.length >= 10) ultima = ultima.slice(0, 10).split('-').reverse().join('/');
                else ultima = '';
                var metaHtml = ultima ? '<div class="card-meta">Atualizado: ' + ultima.replace(/</g, '&lt;') + '</div>' : '';
                var descHtml = desc ? '<p>' + desc.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>' : '<p>Clique para abrir</p>';
                var favClass = isFavorito ? ' ativo' : '';
                var fixClass = isFixado ? ' ativo' : '';
                return '<div class="card" data-id="' + r.id + '" data-nome="' + (r.nome || '').replace(/"/g, '&quot;') + '">' +
                    '<div class="card-header">' +
                    '<h3>' + (r.nome || 'Sem nome').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h3>' +
                    '<div class="card-actions"><button type="button" class="card-btn-favorito' + favClass + '" title="Favoritar" aria-label="Favoritar">' + (isFavorito ? '★' : '☆') + '</button><button type="button" class="card-btn-fixar' + fixClass + '" title="Fixar na dashboard" aria-label="Fixar">📌</button></div>' +
                    '</div>' +
                    '<div class="card-body">' +
                    '<div class="card-img-wrap"><img src="' + imgSrc.replace(/"/g, '&quot;') + '" alt="" onerror="this.onerror=null;this.src=\'' + imgPadrao + '\'"></div>' +
                    '<div class="card-content">' + descHtml + metaHtml + '</div>' +
                    '</div></div>';
            }
            function showList(reports) {
                var el = document.getElementById('reportList');
                if (!reports || reports.length === 0) {
                    el.className = 'card-list';
                    el.innerHTML = '<p class="loading">Nenhum relatório disponível para você.</p>' +
                        '<p style="font-size:0.9rem; color:#6b7280; margin-top:0.5rem;">Peça a um administrador para atribuir permissões em <strong>Relatórios e permissões</strong> no painel admin.</p>';
                    return;
                }
                var imgPadrao = '../img/dashbi02.png';
                var reportById = {};
                reports.forEach(function(r) { reportById[r.id] = r; });
                var fixadoIds = (favoritosData.fixados || []).map(function(x) { return x.report_id; });
                var favoritoSet = {};
                (favoritosData.favoritos || []).forEach(function(id) { favoritoSet[id] = true; });
                var fixadoSet = {};
                fixadoIds.forEach(function(id) { fixadoSet[id] = true; });
                var fixadosReports = fixadoIds.map(function(id) { return reportById[id]; }).filter(Boolean);
                var restReports = reports.filter(function(r) { return !fixadoSet[r.id]; });
                var groups = {};
                restReports.forEach(function(r) {
                    var ws = (r.workspace_nome && String(r.workspace_nome).trim()) ? String(r.workspace_nome).trim() : 'Sem workspace';
                    if (!groups[ws]) groups[ws] = [];
                    groups[ws].push(r);
                });
                var wsNames = Object.keys(groups).sort(function(a, b) { return a.localeCompare(b); });
                var html = '';
                if (fixadosReports.length > 0) {
                    html += '<div class="fixados-section ws-group"><h3 class="ws-title">📌 Fixados</h3><div class="card-list">';
                    html += fixadosReports.map(function(r) { return renderCard(r, !!favoritoSet[r.id], true); }).join('');
                    html += '</div></div>';
                }
                wsNames.forEach(function(ws) {
                    var cards = groups[ws].map(function(r) { return renderCard(r, !!favoritoSet[r.id], !!fixadoSet[r.id]); }).join('');
                    html += '<div class="ws-group"><h3 class="ws-title">' + ws.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</h3><div class="card-list">' + cards + '</div></div>';
                });
                el.className = 'workspace-list';
                el.innerHTML = html;
                el.querySelectorAll('.card').forEach(function(card) {
                    card.addEventListener('click', function(e) {
                        if (e.target.closest('.card-actions')) return;
                        openReport(parseInt(card.dataset.id, 10), card.dataset.nome || 'Relatório');
                    });
                });
                el.querySelectorAll('.card-btn-favorito').forEach(function(btn) {
                    btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation();
                        var card = btn.closest('.card'); var id = parseInt(card.dataset.id, 10);
                        var isFav = btn.classList.contains('ativo');
                        api('reports/' + id + '/favorito', { method: 'POST', body: { favorito: !isFav } }).then(function(x) {
                            if (x.data.success && x.data.data) { favoritosData = x.data.data; showList(reports); }
                        });
                    });
                });
                el.querySelectorAll('.card-btn-fixar').forEach(function(btn) {
                    btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation();
                        var card = btn.closest('.card'); var id = parseInt(card.dataset.id, 10);
                        var isFix = btn.classList.contains('ativo');
                        api('reports/' + id + '/fixar', { method: 'POST', body: { fixado: !isFix } }).then(function(x) {
                            if (x.data.success && x.data.data) { favoritosData = x.data.data; showList(reports); }
                        });
                    });
                });
            }

            function showListWithFolders(data, allReports) {
                var el = document.getElementById('reportList');
                var folders = data.folders || [];
                var reportsWithoutFolder = data.reports_without_folder || [];
                if (folders.length === 0 && reportsWithoutFolder.length === 0 && (!allReports || allReports.length === 0)) {
                    el.className = 'card-list';
                    el.innerHTML = '<p class="loading">Nenhum relatório disponível para você.</p>' +
                        '<p style="font-size:0.9rem; color:#6b7280; margin-top:0.5rem;">Peça a um administrador para atribuir permissões em <strong>Relatórios e permissões</strong> no painel admin.</p>';
                    return;
                }
                var reportById = {};
                (allReports || []).forEach(function(r) { reportById[r.id] = r; });
                var fixadoIds = (favoritosData.fixados || []).map(function(x) { return x.report_id; });
                var favoritoSet = {};
                (favoritosData.favoritos || []).forEach(function(id) { favoritoSet[id] = true; });
                var fixadoSet = {};
                fixadoIds.forEach(function(id) { fixadoSet[id] = true; });
                var fixadosReports = fixadoIds.map(function(id) { return reportById[id]; }).filter(Boolean);

                var html = '';
                if (fixadosReports.length > 0) {
                    html += '<div class="fixados-section ws-group"><h3 class="ws-title">📌 Fixados</h3><div class="card-list">';
                    html += fixadosReports.map(function(r) { return renderCard(r, !!favoritoSet[r.id], true); }).join('');
                    html += '</div></div>';
                }
                folders.forEach(function(folder) {
                    var reports = folder.reports || [];
                    var cards = reports.map(function(r) { return renderCard(r, !!favoritoSet[r.id], !!fixadoSet[r.id]); }).join('');
                    var folderId = 'folder-' + (folder.id || Math.random().toString(36).slice(2));
                    html += '<div class="folder-section" id="' + folderId + '" data-folder-id="' + folder.id + '">';
                    html += '<button type="button" class="folder-header" aria-expanded="true" aria-controls="' + folderId + '-body">';
                    html += '<span class="folder-chevron">▼</span>';
                    html += '<span class="folder-name">' + (folder.nome || 'Pasta').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
                    html += '<span class="folder-count">' + reports.length + ' relatório' + (reports.length !== 1 ? 's' : '') + '</span>';
                    html += '</button>';
                    html += '<div class="folder-body" id="' + folderId + '-body" style="max-height: 3200px;">';
                    html += '<div class="folder-body-inner"><div class="card-list">' + cards + '</div></div>';
                    html += '</div></div>';
                });
                if (reportsWithoutFolder.length > 0) {
                    var cards = reportsWithoutFolder.map(function(r) { return renderCard(r, !!favoritoSet[r.id], !!fixadoSet[r.id]); }).join('');
                    var folderId = 'folder-sem-pasta';
                    html += '<div class="folder-section" id="' + folderId + '">';
                    html += '<button type="button" class="folder-header" aria-expanded="true" aria-controls="' + folderId + '-body">';
                    html += '<span class="folder-chevron">▼</span>';
                    html += '<span class="folder-name">Outros relatórios</span>';
                    html += '<span class="folder-count">' + reportsWithoutFolder.length + ' relatório' + (reportsWithoutFolder.length !== 1 ? 's' : '') + '</span>';
                    html += '</button>';
                    html += '<div class="folder-body" id="' + folderId + '-body" style="max-height: 3200px;">';
                    html += '<div class="folder-body-inner"><div class="card-list">' + cards + '</div></div>';
                    html += '</div></div>';
                }
                el.className = 'workspace-list';
                el.innerHTML = html;

                el.querySelectorAll('.folder-header').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var section = btn.closest('.folder-section');
                        if (!section) return;
                        var isExpanded = section.getAttribute('aria-expanded') === 'true';
                        var newExpanded = !isExpanded;
                        section.classList.toggle('collapsed', !newExpanded);
                        section.setAttribute('aria-expanded', newExpanded);
                        btn.setAttribute('aria-expanded', newExpanded);
                    });
                });
                el.querySelectorAll('.card').forEach(function(card) {
                    card.addEventListener('click', function(e) {
                        if (e.target.closest('.card-actions')) return;
                        openReport(parseInt(card.dataset.id, 10), card.dataset.nome || 'Relatório');
                    });
                });
                el.querySelectorAll('.card-btn-favorito').forEach(function(btn) {
                    btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation();
                        var card = btn.closest('.card'); var id = parseInt(card.dataset.id, 10);
                        var isFav = btn.classList.contains('ativo');
                        api('reports/' + id + '/favorito', { method: 'POST', body: { favorito: !isFav } }).then(function(x) {
                            if (x.data.success && x.data.data) { favoritosData = x.data.data; refreshList(); }
                        });
                    });
                });
                el.querySelectorAll('.card-btn-fixar').forEach(function(btn) {
                    btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation();
                        var card = btn.closest('.card'); var id = parseInt(card.dataset.id, 10);
                        var isFix = btn.classList.contains('ativo');
                        api('reports/' + id + '/fixar', { method: 'POST', body: { fixado: !isFix } }).then(function(x) {
                            if (x.data.success && x.data.data) { favoritosData = x.data.data; refreshList(); }
                        });
                    });
                });
            }

            function refreshList() {
                if (lastDashboardData && lastAllReports) {
                    showListWithFolders(lastDashboardData, lastAllReports);
                } else if (lastReports) {
                    showList(lastReports);
                }
            }

            function loadCurrentReport() {
                if (!currentReportId) return;
                resetEmbed();
                reportTitle.textContent = currentReportName || 'Relatório';
                listSection.classList.add('hidden');
                var kpisEl = document.getElementById('kpisSection');
                if (kpisEl) kpisEl.classList.add('hidden');
                viewerSection.classList.remove('hidden');
                var conteudoEl = document.querySelector('main.conteudo');
                if (conteudoEl) conteudoEl.classList.add('viewer-active');
                reportContainer.innerHTML = '<p class="loading">Gerando visualização…</p>';
                api('reports/' + currentReportId + '/embed').then(function(x) {
                    if (x.status === 401) { window.location.href = 'login.html'; return; }
                    if (!x.data.success) {
                        reportContainer.innerHTML = '';
                        showMsg(x.data.message || 'Erro ao carregar relatório', false);
                        return;
                    }
                    var cfg = x.data.data;
                    if (!cfg.embedUrl || !cfg.accessToken || !cfg.reportId) {
                        reportContainer.innerHTML = '';
                        showMsg('Dados do embed incompletos', false);
                        return;
                    }
                    currentLogAcessoId = cfg.logAcessoId || null;
                    embedOpenTime = cfg.logAcessoId ? Date.now() : null;
                    reportContainer.innerHTML = '';
                    var models = (window['powerbi-client'] && window['powerbi-client'].models) || (window.powerbi && window.powerbi.models);
                    if (!window.powerbi || !models) {
                        showMsg('SDK do Power BI não carregado corretamente. Atualize a página e tente novamente.', false);
                        return;
                    }
                    var mostrarAbas = cfg.mostrarAbas === true;
                    var mostrarFiltros = cfg.mostrarFiltros === true;
                    var paginaRestrita = Array.isArray(cfg.paginaRestrita) ? cfg.paginaRestrita : [];
                    var usarAbasRestritas = paginaRestrita.length > 0;
                    var embedUrl = cfg.embedUrl;
                    var config = {
                        type: 'report',
                        tokenType: models.TokenType.Embed,
                        accessToken: cfg.accessToken,
                        embedUrl: embedUrl,
                        id: cfg.reportId,
                        settings: {
                            panes: {
                                filters: { visible: mostrarFiltros },
                                pageNavigation: { visible: mostrarAbas && !usarAbasRestritas }
                            }
                        }
                    };
                    var embed = powerbi.embed(reportContainer, config);
                    embedInstance = embed;
                    embed.off('loaded');
                    embed.on('loaded', function() {
                        var tabsEl = document.getElementById('reportPageTabs');
                        if (usarAbasRestritas && tabsEl) {
                            embed.getPages().then(function(pages) {
                                var allowSet = {};
                                paginaRestrita.forEach(function(n) { allowSet[n] = true; });
                                var allowed = pages.filter(function(p) { return allowSet[p.name]; });
                                if (allowed.length === 0) return;
                                tabsEl.classList.remove('hidden');
                                tabsEl.innerHTML = '';
                                allowed.forEach(function(p, idx) {
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'page-tab' + (idx === 0 ? ' ativo' : '');
                                    btn.textContent = p.displayName || p.name || ('Página ' + (idx + 1));
                                    btn.dataset.pageName = p.name;
                                    btn.addEventListener('click', function() {
                                        embed.setPage(p.name).then(function() {
                                            tabsEl.querySelectorAll('.page-tab').forEach(function(t) { t.classList.remove('ativo'); });
                                            btn.classList.add('ativo');
                                        });
                                    });
                                    tabsEl.appendChild(btn);
                                });
                                embed.setPage(allowed[0].name);
                            });
                        } else if (tabsEl) {
                            tabsEl.classList.add('hidden');
                            tabsEl.innerHTML = '';
                        }
                    });
                    embed.on('error', function(e) { showMsg('Erro no relatório: ' + (e.detail && e.detail.message ? e.detail.message : 'erro desconhecido'), false); });
                }).catch(function(e) {
                    reportContainer.innerHTML = '';
                    showMsg('Erro inesperado ao abrir relatório: ' + (e && e.message ? e.message : 'erro desconhecido'), false);
                });
            }
            function openReport(id, nome) {
                currentReportId = id;
                currentReportName = nome || 'Relatório';
                loadCurrentReport();
            }
            document.getElementById('backToList').addEventListener('click', function() {
                sendCloseLogAcesso();
                resetEmbed();
                currentReportId = null;
                currentReportName = '';
                viewerSection.classList.add('hidden');
                listSection.classList.remove('hidden');
                var kpisEl = document.getElementById('kpisSection');
                if (kpisEl) kpisEl.classList.remove('hidden');
                var conteudoEl = document.querySelector('main.conteudo');
                if (conteudoEl) conteudoEl.classList.remove('viewer-active');
            });
            window.addEventListener('beforeunload', function() { sendCloseLogAcesso(); });
            document.getElementById('refreshReport').addEventListener('click', function() {
                loadCurrentReport();
            });
            document.getElementById('fullReport').addEventListener('click', function() {
                if (!reportContainer) return;
                if (reportContainer.requestFullscreen) reportContainer.requestFullscreen();
            });
            loadUser().then(function() {
                var favoritosAndKpis = loadFavoritos();
                var kpisPromise = isAdminOrMaster ? loadKpis() : Promise.resolve(null);
                favoritosAndKpis.then(function() {
                    return loadFoldersForDashboard();
                }).then(function(folderData) {
                    var useFolders = folderData && (folderData.folders.length > 0 || (folderData.reports_without_folder && folderData.reports_without_folder.length > 0));
                    if (useFolders) {
                        var allReports = [];
                        (folderData.folders || []).forEach(function(f) { (f.reports || []).forEach(function(r) { allReports.push(r); }); });
                        (folderData.reports_without_folder || []).forEach(function(r) { allReports.push(r); });
                        lastDashboardData = folderData;
                        lastAllReports = allReports;
                        showListWithFolders(folderData, allReports);
                    } else {
                        return loadReports().then(function(reports) {
                            lastReports = reports;
                            showList(reports);
                        });
                    }
                }).then(function() {
                    return kpisPromise;
                }).then(function(k) {
                    if (k) renderKpis(k);
                });
            });
        })();
    </script>
</body>
</html>
