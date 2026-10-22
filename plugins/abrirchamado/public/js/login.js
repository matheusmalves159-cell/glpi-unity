(function () {
    'use strict';

    // A sessão do GLPI expira em 1 hora sem uso. Se a tela de login ficar
    // aberta mais que isso, o envio falharia com "Sua sessão expirou".
    var RENOVAR_APOS_MS = 55 * 60 * 1000;
    var carregada = Date.now();

    function formulario() {
        return document.querySelector('form[action$="/front/login.php"]');
    }

    function vazio(form) {
        return Array.prototype.every.call(
            form.querySelectorAll('input[type="text"], input[type="password"]'),
            function (campo) { return campo.value === ''; }
        );
    }

    function antiga() {
        return Date.now() - carregada > RENOVAR_APOS_MS;
    }

    // Nada digitado ainda: só recarrega a página para começar uma sessão nova
    function recarregarSePreciso() {
        var form = formulario();
        if (form && antiga() && vazio(form) && !document.hidden) {
            window.location.reload();
        }
    }
    window.setInterval(recarregarSePreciso, 30000);
    document.addEventListener('visibilitychange', recarregarSePreciso);

    // Já digitou: pega um token novo antes de enviar, sem perder o que foi digitado
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (form !== formulario() || !antiga() || form.dataset.renovado === '1') {
            return;
        }
        ev.preventDefault();

        function enviar() {
            form.dataset.renovado = '1';
            if (!form.querySelector('input[type="hidden"][name="submit"]')) {
                var botao = document.createElement('input');
                botao.type = 'hidden';
                botao.name = 'submit';
                botao.value = '';
                form.appendChild(botao);
            }
            HTMLFormElement.prototype.submit.call(form);
        }

        fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var novo = new DOMParser().parseFromString(html, 'text/html')
                    .querySelector('form[action$="/front/login.php"] input[name="_glpi_csrf_token"]');
                var atual = form.querySelector('input[name="_glpi_csrf_token"]');
                if (novo && atual) {
                    atual.value = novo.value;
                }
                enviar();
            })
            .catch(enviar);
    }, true);
})();
