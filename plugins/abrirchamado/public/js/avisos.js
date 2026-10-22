// Avisos laterais: mostra na própria página as notificações do GLPI no modo "Navegador"
// (chamado novo, atribuição, pendência, solução, prazo do chamado...), sem precisar da
// permissão de notificação do navegador. Usa a mesma fila do GLPI (/ajax/notifications_ajax.php).
(function () {
    'use strict';

    var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
    var ENDPOINT = root + '/ajax/notifications_ajax.php';
    var INTERVALO_MS = 10000;
    var DURACAO_MS = 15000;
    var MAXIMO = 4;
    var CHAVE = 'abrirchamado.avisos.ultimaConsulta';

    // O GLPI pede permissão ao navegador e, se concedida, mostra a notificação do sistema.
    // Aqui a fila é consumida pelo aviso lateral, então o aviso do sistema é desligado.
    try {
        if ('Notification' in window) {
            Object.defineProperty(window.Notification, 'permission', { get: function () { return 'denied'; } });
        }
    } catch (e) { /* segue sem o ajuste */ }

    function limparTexto(texto) {
        return String(texto || '')
            .replace(/^\s*\[GLPI #\d+\]\s*/, '')
            .replace(/\s*Automaticamente gerado por (o )?GLPI\.?\s*$/i, '')
            .replace(/\b0{2,}(\d+)\b/g, '#$1');
    }

    // [palavra no título, classe, ícone]
    var TIPOS = [
        [/prazo/i, 'ac-aviso-prazo', 'ti-alarm'],
        [/pend[êe]ncia/i, 'ac-aviso-pendencia', 'ti-player-pause'],
        [/solucionad|encerrad|aprovad/i, 'ac-aviso-ok', 'ti-circle-check'],
        [/reprovad/i, 'ac-aviso-pendencia', 'ti-arrow-back-up'],
        [/novo chamado/i, 'ac-aviso-novo', 'ti-bell-ringing'],
        [/atribu|atendimento/i, 'ac-aviso-novo', 'ti-user-check']
    ];

    function tipoDe(titulo) {
        for (var i = 0; i < TIPOS.length; i++) {
            if (TIPOS[i][0].test(titulo)) {
                return TIPOS[i];
            }
        }
        return [null, 'ac-aviso-novo', 'ti-bell'];
    }

    function caixa() {
        var c = document.querySelector('.ac-avisos');
        if (!c) {
            c = document.createElement('div');
            c.className = 'ac-avisos';
            c.setAttribute('role', 'region');
            c.setAttribute('aria-label', 'Avisos');
            document.body.appendChild(c);
        }
        return c;
    }

    function mostrar(aviso) {
        var titulo = limparTexto(aviso.title);
        var corpo = limparTexto(aviso.body);
        var tipo = tipoDe(titulo);
        var c = caixa();

        while (c.children.length >= MAXIMO) {
            c.removeChild(c.firstElementChild);
        }

        var el = document.createElement('div');
        el.className = 'ac-aviso ' + tipo[1];
        el.setAttribute('role', 'status');

        var icone = document.createElement('span');
        icone.className = 'ac-aviso-icone';
        icone.innerHTML = '<i class="ti ' + tipo[2] + '"></i>';

        var texto = document.createElement('div');
        texto.className = 'ac-aviso-texto';
        var t = document.createElement('div');
        t.className = 'ac-aviso-titulo';
        t.textContent = titulo;
        var b = document.createElement('div');
        b.className = 'ac-aviso-corpo';
        b.textContent = corpo;
        texto.appendChild(t);
        if (corpo) {
            texto.appendChild(b);
        }

        var fechar = document.createElement('button');
        fechar.type = 'button';
        fechar.className = 'ac-aviso-fechar';
        fechar.setAttribute('aria-label', 'Fechar');
        fechar.innerHTML = '<i class="ti ti-x"></i>';

        el.appendChild(icone);
        el.appendChild(texto);
        el.appendChild(fechar);
        c.appendChild(el);

        var remover = function () {
            el.classList.add('saindo');
            window.setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 250);
        };
        var timer = window.setTimeout(remover, DURACAO_MS);
        el.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
        el.addEventListener('mouseleave', function () { timer = window.setTimeout(remover, DURACAO_MS / 3); });
        fechar.addEventListener('click', function (e) { e.stopPropagation(); window.clearTimeout(timer); remover(); });
        if (aviso.url) {
            el.classList.add('ac-aviso-link');
            el.addEventListener('click', function () { window.location.href = aviso.url; });
        }
    }

    function consumir(id) {
        // Marca como entregue (o GLPI apaga da fila)
        return fetch(ENDPOINT + '?delete=' + encodeURIComponent(id), { credentials: 'same-origin' }).catch(function () {});
    }

    var consultando = false;
    function consultar() {
        if (consultando || document.hidden) {
            return;
        }
        // Várias abas abertas: só uma consulta por intervalo
        try {
            var ultima = parseInt(localStorage.getItem(CHAVE) || '0', 10);
            var agora = Date.now();
            if (agora - ultima < INTERVALO_MS - 500) {
                return;
            }
            localStorage.setItem(CHAVE, String(agora));
        } catch (e) { /* sem localStorage: consulta mesmo assim */ }

        consultando = true;
        fetch(ENDPOINT, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (lista) {
                if (!Array.isArray(lista)) {
                    return;
                }
                lista.forEach(function (a) {
                    mostrar(a);
                    consumir(a.id);
                });
            })
            .catch(function () {})
            .then(function () { consultando = false; });
    }

    function iniciar() {
        // A fila nativa fica para o nosso aviso (evita consumir a mesma notificação duas vezes)
        if (window.notifications_ajax && typeof window.notifications_ajax === 'object') {
            window.notifications_ajax.checkNewNotifications = function () {};
        }
        consultar();
        window.setInterval(consultar, INTERVALO_MS);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                consultar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.setTimeout(iniciar, 300); });
    } else {
        window.setTimeout(iniciar, 300);
    }
})();
