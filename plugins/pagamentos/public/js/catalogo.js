(function () {
    'use strict';

    // Cartão "Solicitação de pagamento" no Catálogo de serviços (só na raiz, sem pesquisa)
    var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
    if (window.location.pathname.indexOf(root + '/ServiceCatalog') !== 0) {
        return;
    }

    function cartao() {
        var coluna = document.createElement('div');
        // Mesmo padrão dos outros cartões da página (estilo .ac-catalogo-* do plugin Abrir Chamado)
        coluna.className = 'col-12 col-md-6 d-flex ac-catalogo-col ac-cartao-verde pagamentos-catalogo';
        coluna.style.order = '-6';
        coluna.innerHTML =
            '<a class="card mx-1 my-2 flex-grow-1" href="' + root + '/plugins/pagamentos/front/solicitar.php">' +
            '<section class="card-body">' +
            '<div class="d-flex align-items-center">' +
            '<span class="ac-catalogo-icone flex-shrink-0"><i class="ti ti-cash"></i></span>' +
            '<div class="ms-4"><h2 class="card-title mb-1">Solicitação de pagamento</h2>' +
            '<div class="text-muted small">Peça ao Financeiro o pagamento de fornecedores e compras</div></div>' +
            '</div></section></a>';
        return coluna;
    }

    function colocar() {
        var params = new URLSearchParams(window.location.search);
        var categoria = params.get('category');
        var busca = document.querySelector('main input[type="search"], main input[placeholder*="esquisar"]');
        var naRaiz = (!categoria || categoria === '0') && (!busca || busca.value === '');
        var existente = document.querySelector('.pagamentos-catalogo');
        var primeiro = document.querySelector('main a.card.mx-1');

        if (!naRaiz || !primeiro) {
            if (existente) {
                existente.remove();
            }
            return;
        }
        if (!existente) {
            primeiro.parentElement.parentElement.appendChild(cartao());
        }
    }

    function iniciar() {
        colocar();
        new MutationObserver(colocar).observe(document.querySelector('main') || document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
