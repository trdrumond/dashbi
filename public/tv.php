<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel TV - DashBI</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: #0b1220; color: #fff; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .topbar { height: 56px; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; background: rgba(11, 30, 60, 0.95); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .topbar select, .topbar button, .topbar a {
            height: 36px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); background: #111827; color: #fff; padding: 0 0.6rem; text-decoration: none; display: inline-flex; align-items: center;
        }
        .topbar button { cursor: pointer; }
        .status { margin-left: auto; font-size: 0.9rem; color: #cbd5e1; }
        .stage { height: calc(100% - 56px); position: relative; }
        #reportHost { width: 100%; height: 100%; position: relative; background: #fff; overflow: hidden; }
        .report-layer {
            position: absolute;
            inset: 0;
            opacity: 0;
            z-index: 1;
            transition: opacity .25s ease;
        }
        .report-layer.active {
            opacity: 1;
            z-index: 2;
        }
        .overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 1.1rem; background: #0b1220; text-align: center; padding: 1rem; }
        .countdown {
            position: absolute;
            right: 12px;
            bottom: 12px;
            min-width: 64px;
            text-align: center;
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            background: rgba(17, 24, 39, 0.78);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            font-weight: 600;
        }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="topbar" id="topbar">
        <select id="panelSelect"><option value="">Carregando painéis...</option></select>
        <button id="btnPlay">Play</button>
        <button id="btnStop">Parar</button>
        <button id="btnFull">Tela cheia</button>
        <a href="dashboard.php">Voltar</a>
        <span class="status" id="statusTxt">Aguardando...</span>
    </div>
    <div class="stage" id="stage">
        <div id="reportHost">
            <div id="layer0" class="report-layer"></div>
            <div id="layer1" class="report-layer"></div>
        </div>
        <div class="overlay" id="overlayMsg">Selecione um Painel TV e clique em Play.</div>
        <div class="countdown hidden" id="countdownBox">--</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/powerbi-client@2.21.0/dist/powerbi.min.js"></script>
    <script>
        (function() {
            var panelItems = [];
            var currentPos = -1;
            var timer = null;
            var countdownTimer = null;
            var playing = false;
            var playbackMode = 'loop';
            var showCountdown = true;
            var layerEls = [document.getElementById('layer0'), document.getElementById('layer1')];
            var layerStates = [
                { pos: null, ready: false, embed: null },
                { pos: null, ready: false, embed: null }
            ];
            var activeLayer = 0;
            var embedCfgCache = {};
            var EMBED_CACHE_MS = 10 * 60 * 1000;

            function setStatus(txt) {
                document.getElementById('statusTxt').textContent = txt;
            }

            function showOverlay(txt) {
                var o = document.getElementById('overlayMsg');
                o.textContent = txt || '';
                o.classList.remove('hidden');
            }

            function hideOverlay() {
                document.getElementById('overlayMsg').classList.add('hidden');
            }

            function api(path, opts) {
                opts = opts || {};
                opts.credentials = 'include';
                opts.headers = opts.headers || {};
                if (opts.body !== undefined && typeof opts.body !== 'string') opts.body = JSON.stringify(opts.body);
                opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
                return fetch('api/' + path, opts)
                    .then(function(r) {
                        return r.text().then(function(txt) {
                            var data;
                            try { data = txt ? JSON.parse(txt) : {}; } catch (e) { data = { success: false, message: txt || ('HTTP ' + r.status) }; }
                            return { status: r.status, data: data };
                        });
                    })
                    .catch(function(err) {
                        return { status: 0, data: { success: false, message: 'Falha de rede: ' + (err && err.message ? err.message : 'erro') } };
                    });
            }

            function clearTimer() {
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
                if (countdownTimer) {
                    clearInterval(countdownTimer);
                    countdownTimer = null;
                }
            }

            function setCountdown(seconds) {
                var box = document.getElementById('countdownBox');
                if (!showCountdown) {
                    box.classList.add('hidden');
                    return;
                }
                var remain = parseInt(seconds || 0, 10);
                if (remain < 0) remain = 0;
                box.classList.remove('hidden');
                box.textContent = remain + 's';
                if (countdownTimer) {
                    clearInterval(countdownTimer);
                    countdownTimer = null;
                }
                countdownTimer = setInterval(function() {
                    remain -= 1;
                    if (remain <= 0) {
                        box.textContent = '0s';
                        clearInterval(countdownTimer);
                        countdownTimer = null;
                        return;
                    }
                    box.textContent = remain + 's';
                }, 1000);
            }

            function calcPos(rawPos) {
                if (!panelItems || panelItems.length === 0) return null;
                if (playbackMode === 'once') {
                    return rawPos >= panelItems.length ? null : rawPos;
                }
                return rawPos % panelItems.length;
            }

            function resetLayer(idx) {
                var st = layerStates[idx];
                if (st && st.embed) {
                    try { st.embed.off('loaded'); st.embed.off('error'); } catch (e) {}
                }
                if (window.powerbi && layerEls[idx]) {
                    try { window.powerbi.reset(layerEls[idx]); } catch (e) {}
                }
                layerEls[idx].innerHTML = '';
                layerStates[idx] = { pos: null, ready: false, embed: null };
            }

            function resetAllLayers() {
                resetLayer(0);
                resetLayer(1);
                layerEls[0].classList.remove('active');
                layerEls[1].classList.remove('active');
            }

            function activateLayer(idx) {
                layerEls[idx].classList.add('active');
                layerEls[1 - idx].classList.remove('active');
                activeLayer = idx;
            }

            function getEmbedConfig(reportId) {
                var cached = embedCfgCache[reportId];
                var now = Date.now();
                if (cached && (now - cached.ts) <= EMBED_CACHE_MS) {
                    return Promise.resolve({ success: true, data: cached.cfg });
                }
                return api('reports/' + reportId + '/embed').then(function(x) {
                    if (x.status === 401) { window.location.href = 'login.html'; return { success: false, message: 'Não autenticado' }; }
                    if (!x.data.success || !x.data.data) {
                        return { success: false, message: x.data.message || 'Erro ao gerar embed' };
                    }
                    embedCfgCache[reportId] = { cfg: x.data.data, ts: now };
                    return { success: true, data: x.data.data };
                });
            }

            function stopPlayback(msg) {
                playing = false;
                clearTimer();
                resetAllLayers();
                setStatus(msg || 'Parado');
                if (msg) showOverlay(msg);
                document.getElementById('countdownBox').classList.add('hidden');
            }

            function loadPanels() {
                return api('tv-panels?ativos=1').then(function(x) {
                    if (x.status === 401) { window.location.href = 'login.html'; return; }
                    var sel = document.getElementById('panelSelect');
                    if (!x.data.success) {
                        sel.innerHTML = '<option value="">Erro ao carregar</option>';
                        setStatus(x.data.message || 'Erro ao carregar painéis');
                        return;
                    }
                    var list = x.data.data || [];
                    sel.innerHTML = '<option value="">-- Selecione um painel --</option>';
                    list.forEach(function(p) {
                        var opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.nome;
                        sel.appendChild(opt);
                    });
                    var qp = new URLSearchParams(window.location.search).get('panel_id');
                    if (qp) sel.value = qp;
                });
            }

            function preloadPosToLayer(pos, layerIdx) {
                return new Promise(function(resolve) {
                    if (pos === null || pos < 0 || pos >= panelItems.length) {
                        resolve(false);
                        return;
                    }
                    var item = panelItems[pos];
                    var reportId = parseInt(item.report_id || 0, 10);
                    if (reportId <= 0) {
                        resolve(false);
                        return;
                    }
                    var models = (window['powerbi-client'] && window['powerbi-client'].models) || (window.powerbi && window.powerbi.models);
                    if (!window.powerbi || !models) {
                        showOverlay('SDK Power BI não carregado.');
                        resolve(false);
                        return;
                    }
                    getEmbedConfig(reportId).then(function(resp) {
                        if (!resp.success) {
                            showOverlay(resp.message || 'Erro ao carregar embed');
                            resolve(false);
                            return;
                        }
                        var cfg = resp.data;
                        resetLayer(layerIdx);
                        var config = {
                            type: 'report',
                            tokenType: models.TokenType.Embed,
                            accessToken: cfg.accessToken,
                            embedUrl: cfg.embedUrl,
                            id: cfg.reportId,
                            settings: {
                                panes: {
                                    filters: { visible: false },
                                    pageNavigation: { visible: false }
                                }
                            }
                        };
                        var report = powerbi.embed(layerEls[layerIdx], config);
                        layerStates[layerIdx].embed = report;
                        layerStates[layerIdx].pos = pos;
                        layerStates[layerIdx].ready = false;
                        var done = false;
                        report.off('loaded');
                        report.off('error');
                        report.on('loaded', function() {
                            if (done) return;
                            done = true;
                            var pageName = (item.pagina_nome || '').trim();
                            if (pageName) {
                                report.getPages().then(function(pages) {
                                    var p = pages.find(function(px) {
                                        return (px.name && px.name === pageName) || (px.displayName && px.displayName === pageName);
                                    });
                                    if (p) report.setPage(p.name);
                                }).catch(function() {});
                            }
                            layerStates[layerIdx].ready = true;
                            resolve(true);
                        });
                        report.on('error', function(e) {
                            if (done) return;
                            done = true;
                            showOverlay('Erro no relatório: ' + (e && e.detail && e.detail.message ? e.detail.message : 'erro desconhecido'));
                            resolve(false);
                        });
                    });
                });
            }

            function scheduleCurrent() {
                if (!playing || panelItems.length === 0) {
                    stopPlayback('Sem itens para reprodução.');
                    return;
                }
                var item = panelItems[currentPos];
                var tempo = parseInt(item.tempo_segundos || 30, 10);
                if (tempo < 5) tempo = 5;
                clearTimer();
                setCountdown(tempo);
                setStatus('Exibindo: ' + (item.report_nome || ('Relatório #' + item.report_id)) + ' (' + tempo + 's)');
                timer = setTimeout(advancePlayback, tempo * 1000);
            }

            function prepareHiddenNext() {
                var nextPos = calcPos(currentPos + 1);
                if (nextPos === null) return;
                var hiddenLayer = 1 - activeLayer;
                preloadPosToLayer(nextPos, hiddenLayer);
            }

            function advancePlayback() {
                if (!playing) return;
                var nextPos = calcPos(currentPos + 1);
                if (nextPos === null) {
                    stopPlayback('Apresentação finalizada.');
                    return;
                }
                var hiddenLayer = 1 - activeLayer;
                var ready = layerStates[hiddenLayer].ready && layerStates[hiddenLayer].pos === nextPos;
                var continueWithLayer = function() {
                    currentPos = nextPos;
                    activateLayer(hiddenLayer);
                    hideOverlay();
                    scheduleCurrent();
                    prepareHiddenNext();
                };
                if (ready) {
                    continueWithLayer();
                    return;
                }
                preloadPosToLayer(nextPos, hiddenLayer).then(function(ok) {
                    if (!playing) return;
                    if (!ok) {
                        stopPlayback('Erro ao carregar próximo relatório.');
                        return;
                    }
                    continueWithLayer();
                });
            }

            function startPlayback() {
                var panelId = parseInt(document.getElementById('panelSelect').value || '0', 10);
                if (!panelId) {
                    showOverlay('Selecione um painel TV antes de iniciar.');
                    return;
                }
                api('tv-panels/' + panelId + '/play').then(function(x) {
                    if (x.status === 401) { window.location.href = 'login.html'; return; }
                    if (!x.data.success) {
                        showOverlay(x.data.message || 'Erro ao carregar painel.');
                        return;
                    }
                    panelItems = (x.data.data && x.data.data.items) ? x.data.data.items : [];
                    if (panelItems.length === 0) {
                        showOverlay('Este painel não tem relatórios visíveis para o usuário logado.');
                        return;
                    }
                    playbackMode = (x.data.data && x.data.data.modo_reproducao === 'once') ? 'once' : 'loop';
                    showCountdown = !x.data.data || x.data.data.mostrar_contador !== false;
                    resetAllLayers();
                    playing = true;
                    currentPos = calcPos(0);
                    if (currentPos === null) {
                        stopPlayback('Sem itens para reprodução.');
                        return;
                    }
                    activeLayer = 0;
                    preloadPosToLayer(currentPos, activeLayer).then(function(ok) {
                        if (!playing) return;
                        if (!ok) {
                            stopPlayback('Não foi possível iniciar a apresentação.');
                            return;
                        }
                        activateLayer(activeLayer);
                        hideOverlay();
                        scheduleCurrent();
                        prepareHiddenNext();
                    });
                });
            }

            document.getElementById('btnPlay').addEventListener('click', startPlayback);
            document.getElementById('btnStop').addEventListener('click', function() { stopPlayback('Apresentação interrompida.'); });
            document.getElementById('btnFull').addEventListener('click', function() {
                var root = document.documentElement;
                if (root.requestFullscreen) root.requestFullscreen();
            });
            document.getElementById('panelSelect').addEventListener('change', function() {
                if (playing) stopPlayback('Painel alterado. Clique em Play para iniciar.');
            });

            loadPanels();
        })();
    </script>
</body>
</html>
