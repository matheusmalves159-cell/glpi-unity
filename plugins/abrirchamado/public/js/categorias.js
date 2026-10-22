// Lista de categorias em árvore recolhível: abre só com o primeiro nível
// e cada categoria com subcategorias abre/fecha ao clicar.
// Ao digitar na busca, a lista volta a ser a busca normal do GLPI.
(function () {
    'use strict';

    var $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.select2) {
        return;
    }

    var Utils = null;
    try {
        Utils = $.fn.select2.amd.require('select2/utils');
    } catch (e) {
        Utils = null;
    }

    function ehCategoria(sel) {
        return /itilcategor/i.test((sel.name || '') + ' ' + (sel.id || ''));
    }

    function dadosDe(li) {
        var d = null;
        if (Utils && Utils.GetData) {
            d = Utils.GetData(li, 'data');
        }
        return d || $(li).data('data') || null;
    }

    function iniciar(sel) {
        var lista = document.getElementById('select2-' + sel.id + '-results');
        if (!lista || lista.dataset.catarvore) {
            return;
        }
        lista.dataset.catarvore = '1';

        var abertos = {};
        var jaAbriuSelecionado = false;
        var aplicando = false;

        function busca() {
            var campo = document.querySelector('.select2-search__field[aria-controls="' + lista.id + '"]');
            return campo ? campo.value.trim() : '';
        }

        function aplicar() {
            if (aplicando) {
                return;
            }
            aplicando = true;
            try {
                montar();
            } finally {
                aplicando = false;
            }
        }

        function montar() {
            var emBusca = busca() !== '';
            lista.classList.toggle('catarvore', !emBusca);

            var itens = [];
            lista.querySelectorAll('li.select2-results__option').forEach(function (li) {
                var d = dadosDe(li);
                if (d && d.id && d.level) {
                    itens.push({li: li, id: String(d.id), nivel: parseInt(d.level, 10) || 1});
                }
            });

            if (emBusca) {
                itens.forEach(function (it) {
                    it.li.classList.remove('catarvore-oculto');
                });
                return;
            }

            // Ao abrir com uma categoria já escolhida, mostra o caminho até ela
            var pilha = [];
            if (!jaAbriuSelecionado) {
                itens.forEach(function (it) {
                    while (pilha.length && pilha[pilha.length - 1].nivel >= it.nivel) {
                        pilha.pop();
                    }
                    if (it.li.getAttribute('aria-selected') === 'true') {
                        jaAbriuSelecionado = true;
                        pilha.forEach(function (p) {
                            abertos[p.id] = true;
                        });
                    }
                    pilha.push(it);
                });
            }

            pilha = [];
            itens.forEach(function (it, i) {
                while (pilha.length && pilha[pilha.length - 1].nivel >= it.nivel) {
                    pilha.pop();
                }
                var visivel = pilha.every(function (p) {
                    return abertos[p.id];
                });
                var proximo = itens[i + 1];
                var temFilhos = !!(proximo && proximo.nivel > it.nivel);

                if (!it.li.dataset.catarvoreItem) {
                    it.li.dataset.catarvoreItem = '1';
                    var span = it.li.firstElementChild;
                    if (span) {
                        // Troca o recuo "   »" do GLPI pela seta + recuo por nível
                        span.innerHTML = span.innerHTML.replace(/^(\s|&nbsp;| )*(&raquo;|»)/, '');
                    }
                    var seta = document.createElement('span');
                    seta.className = 'catarvore-seta ti ti-chevron-right';
                    it.li.insertBefore(seta, it.li.firstChild);
                    it.li.style.paddingLeft = (0.5 + (it.nivel - 1) * 1.25) + 'rem';
                }

                it.li.classList.toggle('catarvore-oculto', !visivel);
                it.li.classList.toggle('catarvore-pai', temFilhos);
                it.li.classList.toggle('catarvore-aberto', temFilhos && !!abertos[it.id]);
                it.li.dataset.catarvoreId = it.id;
                pilha.push(it);
            });

            // O GLPI entrega as categorias em páginas; carrega todas para a árvore ficar completa
            if (lista.querySelector('.select2-results__option--load-more')) {
                setTimeout(function () {
                    var inst = $(sel).data('select2');
                    var res = inst && inst.results;
                    if (res && typeof res.loadMore === 'function' && !res.loading) {
                        res.loadMore();
                    }
                }, 0);
            }
        }

        // Clique numa categoria com subcategorias: abre/fecha em vez de selecionar
        // (o select2 seleciona no "mouseup").
        function clique(ev) {
            if (!lista.classList.contains('catarvore')) {
                return;
            }
            var li = ev.target.closest && ev.target.closest('li.catarvore-pai');
            if (!li || !lista.contains(li)) {
                return;
            }
            ev.preventDefault();
            ev.stopImmediatePropagation();
            if (ev.type === 'mouseup') {
                var id = li.dataset.catarvoreId;
                abertos[id] = !abertos[id];
                aplicar();
            }
        }
        var caixa = lista.parentNode;
        caixa.addEventListener('mouseup', clique, true);
        caixa.addEventListener('click', clique, true);

        var observador = new MutationObserver(function () {
            if (!aplicando) {
                aplicar();
            }
        });
        observador.observe(lista, {childList: true, subtree: true});
        aplicar();

        $(sel).one('select2:close', function () {
            observador.disconnect();
            caixa.removeEventListener('mouseup', clique, true);
            caixa.removeEventListener('click', clique, true);
            delete lista.dataset.catarvore;
        });
    }

    $(document).on('select2:open', function (e) {
        var sel = e.target;
        if (sel && ehCategoria(sel)) {
            iniciar(sel);
        }
    });
})();
