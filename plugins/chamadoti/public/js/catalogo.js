(function () {
    'use strict';

    // Um cartão por tela de chamado (TI, Administrativo…) no Catálogo de serviços (só na raiz, sem pesquisa)
    var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
    if (window.location.pathname.indexOf(root + '/ServiceCatalog') !== 0) {
        return;
    }

    var telas = null;

    function escapar(t) {
        var d = document.createElement('div');
        d.textContent = t || '';
        return d.innerHTML;
    }

    // Cor de cada tela pela ordem (TI azul, Administrativo amarelo, as próximas em outras cores)
    var CORES = ['azul', 'amarelo', 'teal', 'laranja'];
    var CORES_VALIDAS = ['azul', 'amarelo', 'roxo', 'verde', 'teal', 'laranja'];

    function cartao(t, i) {
        var coluna = document.createElement('div');
        // Cor escolhida na Central de telas; sem escolha, segue a ordem
        var cor = CORES_VALIDAS.indexOf(t.cor) >= 0 ? t.cor : CORES[i % CORES.length];
        coluna.className = 'col-12 col-md-6 d-flex ac-catalogo-col ac-cartao-' + cor + ' chamadoti-catalogo';
        coluna.style.order = String(-20 + i);
        coluna.innerHTML =
            '<a class="card mx-1 my-2 flex-grow-1" href="' + root + '/plugins/chamadoti/front/abrir.php?tela=' + t.id + '">' +
            '<section class="card-body">' +
            '<div class="d-flex align-items-center">' +
            '<span class="ac-catalogo-icone flex-shrink-0"><i class="' + escapar(t.icone) + '"></i></span>' +
            '<div class="ms-4"><h2 class="card-title mb-1">' + escapar(t.titulo) + '</h2>' +
            '<div class="text-muted small">' + escapar(t.descricao) + '</div></div>' +
            '</div></section></a>';
        return coluna;
    }

    function colocar() {
        if (!telas) {
            return;
        }
        var params = new URLSearchParams(window.location.search);
        var categoria = params.get('category');
        var busca = document.querySelector('main input[type="search"], main input[placeholder*="esquisar"]');
        var naRaiz = (!categoria || categoria === '0') && (!busca || busca.value === '');
        var existentes = document.querySelectorAll('.chamadoti-catalogo');
        var primeiro = document.querySelector('main a.card.mx-1:not(.chamadoti-catalogo a)');
        // Catálogo sem formulários para este usuário: usa a área da mensagem "não há formulários"
        var vazio = document.querySelector('main section.row .empty');
        var lista = primeiro ? primeiro.parentElement.parentElement : (vazio ? vazio.parentElement : null);

        if (!naRaiz || !lista) {
            existentes.forEach(function (e) { e.remove(); });
            return;
        }
        if (!existentes.length) {
            if (vazio) {
                vazio.classList.add('d-none');
            }
            // Primeiros cartões: são os pedidos mais comuns
            for (var i = telas.length - 1; i >= 0; i--) {
                lista.insertBefore(cartao(telas[i], i), lista.firstChild);
            }
        }
    }

    function iniciar() {
        fetch(root + '/plugins/chamadoti/front/telas.php', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (lista) {
                telas = lista || [];
                colocar();
                new MutationObserver(colocar).observe(document.querySelector('main') || document.body, { childList: true, subtree: true });
            })
            .catch(function () { /* sem cartões */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
