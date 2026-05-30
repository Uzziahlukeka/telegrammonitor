@php
    $configJson = $configJson ?? json_encode([
        'baseUrl'        => rtrim(config('app.url', ''), '/') . '/telegram-support/chat',
        'title'          => config('telegramlogs.web_chat.widget.title', 'Support'),
        'subtitle'       => config('telegramlogs.web_chat.widget.subtitle', 'Nous répondons rapidement'),
        'color'          => config('telegramlogs.web_chat.widget.color', '#0088CC'),
        'requireName'    => config('telegramlogs.web_chat.widget.require_name', false),
        'placeholder'    => config('telegramlogs.web_chat.widget.placeholder', 'Votre message...'),
        'welcomeMessage' => config('telegramlogs.web_chat.widget.welcome_message', 'Bonjour ! Comment pouvons-nous vous aider ?'),
        'pollInterval'   => (int) config('telegramlogs.web_chat.poll_interval_ms', 3000),
    ]);
@endphp

<div id="tgw-root">

    <style>
        :root{--tgw-c:{{ config('telegramlogs.web_chat.widget.color','#0088CC') }}}
        #tgw-root *{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
        #tgw-btn{position:fixed;bottom:24px;right:24px;z-index:9998;width:56px;height:56px;border-radius:50%;background:var(--tgw-c);border:none;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:transform .2s,box-shadow .2s}
        #tgw-btn:hover{transform:scale(1.08);box-shadow:0 6px 20px rgba(0,0,0,.3)}
        #tgw-btn svg{width:28px;height:28px;fill:#fff}
        #tgw-badge{position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;font-size:11px;font-weight:700;border-radius:50%;min-width:18px;height:18px;display:none;align-items:center;justify-content:center;padding:0 4px}

        #tgw-win{position:fixed;bottom:92px;right:24px;z-index:9999;width:360px;max-width:calc(100vw - 32px);height:520px;max-height:calc(100vh - 110px);background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.2);display:flex;flex-direction:column;overflow:hidden;transform:scale(.85) translateY(20px);opacity:0;pointer-events:none;transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s}
        #tgw-win.open{transform:scale(1) translateY(0);opacity:1;pointer-events:all}

        /* Header */
        #tgw-head{background:var(--tgw-c);padding:14px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0}
        #tgw-head-avatar{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        #tgw-head-avatar svg{width:22px;height:22px;fill:#fff}
        #tgw-head-info{flex:1;min-width:0}
        #tgw-head-title{color:#fff;font-size:15px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #tgw-head-sub{color:rgba(255,255,255,.8);font-size:12px;margin-top:1px}
        #tgw-close{background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);padding:4px;border-radius:50%;display:flex;transition:background .15s}
        #tgw-close:hover{background:rgba(255,255,255,.2);color:#fff}
        #tgw-close svg{width:18px;height:18px;fill:currentColor}

        /* Body */
        #tgw-body{flex:1;overflow-y:auto;padding:14px 12px;display:flex;flex-direction:column;gap:8px;background:#f5f7fa;scroll-behavior:smooth}
        #tgw-body::-webkit-scrollbar{width:4px}
        #tgw-body::-webkit-scrollbar-thumb{background:#ccc;border-radius:2px}

        /* Name prompt */
        #tgw-name-form{background:#fff;border-radius:12px;padding:20px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        #tgw-name-form h3{margin:0 0 6px;font-size:15px;color:#222}
        #tgw-name-form p{margin:0 0 14px;font-size:13px;color:#666}
        #tgw-name-input{width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;outline:none;transition:border .15s}
        #tgw-name-input:focus{border-color:var(--tgw-c)}
        #tgw-name-btn{margin-top:10px;width:100%;padding:10px;background:var(--tgw-c);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .15s}
        #tgw-name-btn:hover{opacity:.9}

        /* Welcome bubble */
        .tgw-welcome{background:#fff;border-radius:12px;padding:12px 14px;font-size:13.5px;color:#444;line-height:1.5;box-shadow:0 1px 4px rgba(0,0,0,.06);max-width:85%}

        /* Messages */
        .tgw-msg{display:flex;flex-direction:column;max-width:80%}
        .tgw-msg.user{align-self:flex-end;align-items:flex-end}
        .tgw-msg.agent{align-self:flex-start;align-items:flex-start}
        .tgw-bubble{padding:9px 13px;border-radius:16px;font-size:14px;line-height:1.45;word-break:break-word}
        .tgw-msg.user .tgw-bubble{background:var(--tgw-c);color:#fff;border-bottom-right-radius:4px}
        .tgw-msg.agent .tgw-bubble{background:#fff;color:#222;border-bottom-left-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
        .tgw-agent-name{font-size:11px;color:#888;margin-bottom:3px;padding-left:2px}
        .tgw-time{font-size:11px;color:#aaa;margin-top:3px;padding:0 2px}
        .tgw-msg.user .tgw-time{text-align:right}

        /* File message */
        .tgw-file{display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit}
        .tgw-file-icon{width:32px;height:32px;flex-shrink:0;opacity:.85}
        .tgw-msg.user .tgw-file-icon{filter:brightness(10)}
        .tgw-file-info{min-width:0}
        .tgw-file-name{font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px}
        .tgw-file-size{font-size:11px;opacity:.7}

        /* Image message */
        .tgw-img{max-width:200px;border-radius:10px;display:block;cursor:zoom-in}

        /* Typing indicator */
        #tgw-typing{align-self:flex-start;display:none}
        #tgw-typing .tgw-bubble{display:flex;gap:4px;align-items:center;padding:10px 14px}
        #tgw-typing span{width:7px;height:7px;background:#bbb;border-radius:50%;animation:tgw-dot 1.2s infinite}
        #tgw-typing span:nth-child(2){animation-delay:.2s}
        #tgw-typing span:nth-child(3){animation-delay:.4s}
        @keyframes tgw-dot{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-5px)}}

        /* Closed banner */
        #tgw-closed{background:#f0f0f0;border-radius:8px;padding:10px 14px;font-size:12.5px;color:#666;text-align:center;flex-shrink:0;margin:6px 0}

        /* Footer */
        #tgw-footer{padding:10px 10px 10px;background:#fff;border-top:1px solid #eee;flex-shrink:0}
        #tgw-input-row{display:flex;gap:8px;align-items:flex-end}
        #tgw-upload-btn{background:none;border:none;cursor:pointer;color:#aaa;padding:6px;border-radius:8px;display:flex;flex-shrink:0;transition:color .15s,background .15s}
        #tgw-upload-btn:hover{color:var(--tgw-c);background:#f0f5ff}
        #tgw-upload-btn svg{width:20px;height:20px;fill:currentColor}
        #tgw-upload-input{display:none}
        #tgw-textarea{flex:1;border:1.5px solid #e5e5e5;border-radius:10px;padding:8px 12px;font-size:14px;resize:none;outline:none;max-height:120px;line-height:1.4;transition:border .15s;font-family:inherit}
        #tgw-textarea:focus{border-color:var(--tgw-c)}
        #tgw-send-btn{background:var(--tgw-c);border:none;cursor:pointer;border-radius:10px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .15s}
        #tgw-send-btn:hover{opacity:.85}
        #tgw-send-btn svg{width:18px;height:18px;fill:#fff}
        #tgw-send-btn:disabled{opacity:.4;cursor:default}
        #tgw-upload-bar{margin-top:6px;font-size:12px;color:#888;display:none;align-items:center;gap:6px}
        #tgw-upload-progress{flex:1;height:3px;background:#eee;border-radius:2px;overflow:hidden}
        #tgw-upload-fill{height:100%;background:var(--tgw-c);width:0%;transition:width .3s}
    </style>

    <!-- Floating button -->
    <button id="tgw-btn" aria-label="Ouvrir le chat support">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 2H4C2.9 2 2 2.9 2 4v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
        </svg>
        <span id="tgw-badge"></span>
    </button>

    <!-- Chat window -->
    <div id="tgw-win" role="dialog" aria-label="Chat support">
        <div id="tgw-head">
            <div id="tgw-head-avatar">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            </div>
            <div id="tgw-head-info">
                <div id="tgw-head-title">Support</div>
                <div id="tgw-head-sub">Nous répondons rapidement</div>
            </div>
            <button id="tgw-close" aria-label="Fermer">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>

        <div id="tgw-body">
            <!-- Messages rendered here by JS -->
        </div>

        <div id="tgw-footer">
            <div id="tgw-input-row">
                <button id="tgw-upload-btn" title="Joindre un fichier" type="button">
                    <svg viewBox="0 0 24 24"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5a2.5 2.5 0 0 1 5 0v10.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5V6H9v9.5a3 3 0 0 0 6 0V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/></svg>
                </button>
                <input id="tgw-upload-input" type="file" accept="*/*">
                <textarea id="tgw-textarea" rows="1" placeholder="Votre message..."></textarea>
                <button id="tgw-send-btn" type="button" disabled aria-label="Envoyer">
                    <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
            <div id="tgw-upload-bar">
                <span id="tgw-upload-name"></span>
                <div id="tgw-upload-progress"><div id="tgw-upload-fill"></div></div>
            </div>
        </div>
    </div>

</div>{{-- #tgw-root --}}

<script>
    (function() {
        'use strict';

        var CFG = {!! $configJson !!};

        /* ── DOM refs ─────────────────────────────────────────────────── */
        var btn       = document.getElementById('tgw-btn');
        var badge     = document.getElementById('tgw-badge');
        var win       = document.getElementById('tgw-win');
        var body      = document.getElementById('tgw-body');
        var closeBtn  = document.getElementById('tgw-close');
        var textarea  = document.getElementById('tgw-textarea');
        var sendBtn   = document.getElementById('tgw-send-btn');
        var uploadBtn = document.getElementById('tgw-upload-btn');
        var uploadInp = document.getElementById('tgw-upload-input');
        var uploadBar = document.getElementById('tgw-upload-bar');
        var uploadNm  = document.getElementById('tgw-upload-name');
        var uploadFl  = document.getElementById('tgw-upload-fill');
        var headTitle = document.getElementById('tgw-head-title');
        var headSub   = document.getElementById('tgw-head-sub');

        /* ── State ────────────────────────────────────────────────────── */
        var state = {
            token:       localStorage.getItem('tgw_token'),
            lastId:      0,
            isOpen:      false,
            sessionStatus: 'open',
            unread:      0,
            pollTimer:   null,
            initialized: false,
            nameRequired: CFG.requireName && !localStorage.getItem('tgw_name_set'),
        };

        /* ── Apply config ─────────────────────────────────────────────── */
        headTitle.textContent = CFG.title;
        headSub.textContent   = CFG.subtitle;
        textarea.placeholder  = CFG.placeholder;
        document.getElementById('tgw-root').style.setProperty('--tgw-c-runtime', CFG.color);

        /* ── Helpers ──────────────────────────────────────────────────── */
        function csrfToken() {
            var m = document.querySelector('meta[name="csrf-token"]');
            return m ? m.content : '';
        }

        function api(path, opts) {
            var url = CFG.baseUrl + path;
            var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
            if (state.token) headers['X-Chat-Token'] = state.token;
            var csrf = csrfToken();
            if (csrf) headers['X-CSRF-TOKEN'] = csrf;
            return fetch(url, Object.assign({ headers: headers }, opts));
        }

        function formatTime(iso) {
            var d = new Date(iso);
            return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
        }

        function formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' o';
            if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' Ko';
            return (bytes/1048576).toFixed(1) + ' Mo';
        }

        function scrollBottom() {
            body.scrollTop = body.scrollHeight;
        }

        function setBadge(n) {
            state.unread = n;
            badge.textContent = n > 9 ? '9+' : n;
            badge.style.display = n > 0 ? 'flex' : 'none';
        }

        /* ── Render messages ──────────────────────────────────────────── */
        function renderMessage(m) {
            var isUser  = m.direction === 'user_to_agent';
            var wrapper = document.createElement('div');
            wrapper.className = 'tgw-msg ' + (isUser ? 'user' : 'agent');
            wrapper.dataset.id = m.id;

            if (!isUser && m.agent_name) {
                var nameEl = document.createElement('div');
                nameEl.className = 'tgw-agent-name';
                nameEl.textContent = m.agent_name;
                wrapper.appendChild(nameEl);
            }

            var bubble = document.createElement('div');
            bubble.className = 'tgw-bubble';

            if (m.file) {
                var isImg = m.file.mime && m.file.mime.startsWith('image/');
                if (isImg) {
                    var img = document.createElement('img');
                    img.src = m.file.url + '?t=' + state.token;
                    img.className = 'tgw-img';
                    img.alt = m.file.name;
                    img.onclick = function() { window.open(this.src, '_blank'); };
                    bubble.appendChild(img);
                } else {
                    var link = document.createElement('a');
                    link.href = m.file.url + '?session_token=' + encodeURIComponent(state.token);
                    link.target = '_blank';
                    link.className = 'tgw-file';
                    link.innerHTML =
                        '<svg class="tgw-file-icon" viewBox="0 0 24 24"><path d="M6 2c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6H6zm7 7V3.5L18.5 9H13z"/></svg>' +
                        '<div class="tgw-file-info"><div class="tgw-file-name">' + escHtml(m.file.name) + '</div>' +
                        '<div class="tgw-file-size">' + formatSize(m.file.size) + ' — Télécharger</div></div>';
                    bubble.appendChild(link);
                }
                if (m.content) {
                    var caption = document.createElement('div');
                    caption.style.marginTop = '6px';
                    caption.style.fontSize = '13px';
                    caption.textContent = m.content;
                    bubble.appendChild(caption);
                }
            } else {
                bubble.textContent = m.content || '';
            }

            wrapper.appendChild(bubble);

            var timeEl = document.createElement('div');
            timeEl.className = 'tgw-time';
            timeEl.textContent = formatTime(m.created_at);
            wrapper.appendChild(timeEl);

            body.appendChild(wrapper);
            return wrapper;
        }

        function escHtml(s) {
            return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function renderWelcome() {
            var el = document.createElement('div');
            el.className = 'tgw-welcome';
            el.textContent = CFG.welcomeMessage;
            body.appendChild(el);
        }

        function renderClosed() {
            if (document.getElementById('tgw-closed')) return;
            var el = document.createElement('div');
            el.id = 'tgw-closed';
            el.textContent = 'Cette session est fermée. Actualisez la page pour en ouvrir une nouvelle.';
            document.getElementById('tgw-footer').prepend(el);
            textarea.disabled = true;
            sendBtn.disabled  = true;
            uploadBtn.disabled = true;
        }

        /* ── Name prompt ──────────────────────────────────────────────── */
        function showNamePrompt() {
            var form = document.createElement('div');
            form.id = 'tgw-name-form';
            form.innerHTML =
                '<h3>Avant de commencer</h3>' +
                '<p>Comment souhaitez-vous être appelé ?</p>' +
                '<input id="tgw-name-input" type="text" placeholder="Votre prénom (optionnel)" maxlength="80">' +
                '<button id="tgw-name-btn" type="button">Démarrer la conversation</button>';
            body.appendChild(form);

            document.getElementById('tgw-name-btn').onclick = function() {
                var name = (document.getElementById('tgw-name-input').value || '').trim();
                localStorage.setItem('tgw_name_set', '1');
                startSession(name || null);
                form.remove();
            };
            document.getElementById('tgw-name-input').onkeydown = function(e) {
                if (e.key === 'Enter') document.getElementById('tgw-name-btn').click();
            };
        }

        /* ── Session ──────────────────────────────────────────────────── */
        function startSession(name) {
            api('/start', {
                method: 'POST',
                body: JSON.stringify({ session_token: state.token, name: name }),
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.session_token) {
                        state.token = data.session_token;
                        localStorage.setItem('tgw_token', state.token);
                        state.sessionStatus = data.status || 'open';
                        state.initialized   = true;
                        if (!data.resumed) renderWelcome();
                        startPolling();
                    }
                })
                .catch(function(e) { console.error('[TGW] start failed', e); });
        }

        /* ── Polling ──────────────────────────────────────────────────── */
        function startPolling() {
            pollMessages();
            state.pollTimer = setInterval(pollMessages, CFG.pollInterval);
        }

        function stopPolling() {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }

        function pollMessages() {
            if (!state.token) return;

            api('/messages?session_token=' + encodeURIComponent(state.token) + '&after=' + state.lastId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.messages) return;

                    var newAgent = 0;
                    data.messages.forEach(function(m) {
                        if (m.id > state.lastId) {
                            state.lastId = m.id;
                            renderMessage(m);
                            if (m.direction === 'agent_to_user' && !state.isOpen) newAgent++;
                        }
                    });

                    if (newAgent > 0) setBadge(state.unread + newAgent);

                    state.sessionStatus = data.status || state.sessionStatus;
                    if (data.status === 'closed') {
                        stopPolling();
                        renderClosed();
                    }

                    scrollBottom();
                })
                .catch(function() {});
        }

        /* ── Send text ────────────────────────────────────────────────── */
        function sendMessage() {
            var text = textarea.value.trim();
            if (!text || !state.token) return;

            // Optimistic render
            var now = new Date().toISOString();
            renderMessage({ id: 'p' + Date.now(), direction: 'user_to_agent', content: text, created_at: now });
            scrollBottom();

            textarea.value = '';
            autoGrow();
            sendBtn.disabled = true;

            api('/send', {
                method: 'POST',
                body: JSON.stringify({ session_token: state.token, message: text }),
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) console.warn('[TGW] send error:', data.error);
                })
                .catch(function(e) { console.error('[TGW] send failed', e); });
        }

        /* ── Upload file ──────────────────────────────────────────────── */
        uploadBtn.onclick = function() { uploadInp.click(); };

        uploadInp.onchange = function() {
            var file = uploadInp.files[0];
            if (!file || !state.token) return;

            var maxMb = {{ (int) config('telegramlogs.web_chat.max_upload_mb', 20) }};
            if (file.size > maxMb * 1024 * 1024) {
                alert('Fichier trop volumineux (max ' + maxMb + ' Mo).');
                uploadInp.value = '';
                return;
            }

            uploadBar.style.display = 'flex';
            uploadNm.textContent = file.name;
            uploadFl.style.width = '0%';

            var fd = new FormData();
            fd.append('file', file);
            fd.append('session_token', state.token);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', CFG.baseUrl + '/upload');
            xhr.setRequestHeader('X-Chat-Token', state.token);
            xhr.setRequestHeader('Accept', 'application/json');
            var csrf = csrfToken();
            if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    uploadFl.style.width = Math.round(e.loaded / e.total * 100) + '%';
                }
            };

            xhr.onload = function() {
                uploadBar.style.display = 'none';
                uploadFl.style.width = '0%';
                uploadInp.value = '';

                if (xhr.status === 201) {
                    var data = JSON.parse(xhr.responseText);
                    renderMessage({
                        id: 'f' + Date.now(),
                        direction: 'user_to_agent',
                        content: null,
                        created_at: new Date().toISOString(),
                        file: { name: data.name, size: data.size, mime: '', url: '' },
                    });
                    scrollBottom();
                } else {
                    try {
                        var err = JSON.parse(xhr.responseText);
                        alert(err.error || 'Erreur lors de l\'envoi du fichier.');
                    } catch(e) {
                        alert('Erreur lors de l\'envoi du fichier.');
                    }
                }
            };

            xhr.onerror = function() {
                uploadBar.style.display = 'none';
                alert('Erreur réseau lors de l\'envoi du fichier.');
            };

            xhr.send(fd);
        };

        /* ── Textarea auto-grow ───────────────────────────────────────── */
        function autoGrow() {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        textarea.oninput = function() {
            autoGrow();
            sendBtn.disabled = textarea.value.trim() === '';
        };

        textarea.onkeydown = function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendBtn.disabled) sendMessage();
            }
        };

        sendBtn.onclick = sendMessage;

        /* ── Open / Close ─────────────────────────────────────────────── */
        function openWidget() {
            state.isOpen = true;
            win.classList.add('open');
            setBadge(0);

            if (!state.initialized) {
                if (CFG.requireName && state.nameRequired) {
                    showNamePrompt();
                } else {
                    startSession(null);
                }
            }

            if (state.initialized && !state.pollTimer && state.sessionStatus !== 'closed') {
                startPolling();
            }

            scrollBottom();
            setTimeout(function() { textarea.focus(); }, 250);
        }

        function closeWidget() {
            state.isOpen = false;
            win.classList.remove('open');
            // Keep polling in background for unread badge
        }

        btn.onclick   = function() { state.isOpen ? closeWidget() : openWidget(); };
        closeBtn.onclick = closeWidget;

        /* ── Keyboard (Escape) ────────────────────────────────────────── */
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && state.isOpen) closeWidget();
        });

        /* ── Bootstrap: if token exists, start background polling ──── */
        if (state.token) {
            // Validate existing token silently
            api('/messages?session_token=' + encodeURIComponent(state.token) + '&after=0')
                .then(function(r) {
                    if (r.status === 401) {
                        localStorage.removeItem('tgw_token');
                        state.token = null;
                        return null;
                    }
                    return r.json();
                })
                .then(function(data) {
                    if (!data) return;
                    state.initialized = true;
                    state.sessionStatus = data.status;
                    // Count unread agent messages
                    var unread = 0;
                    data.messages.forEach(function(m) {
                        if (m.id > state.lastId) state.lastId = m.id;
                        if (m.direction === 'agent_to_user') unread++;
                    });
                    if (unread > 0) setBadge(unread);
                    if (data.status !== 'closed') {
                        state.pollTimer = setInterval(pollMessages, CFG.pollInterval);
                    }
                })
                .catch(function() {});
        }

    })();
</script>
