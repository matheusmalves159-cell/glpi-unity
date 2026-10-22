// Textos do GLPI com a primeira letra de cada palavra maiúscula, menos as palavras
// pequenas de ligação (o, a, e, que, do, de, da, em, para...), que ficam minúsculas
// (exceto no começo de uma frase). O que as pessoas escrevem não é alterado:
// texto dos chamados e acompanhamentos, editor, campos digitados, e-mails e códigos.
(function () {
    'use strict';

    var MINUSCULAS = [
        'o', 'a', 'os', 'as', 'e', 'ou', 'que', 'se',
        'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas',
        'ao', 'aos', 'à', 'às', 'com', 'para', 'por', 'pelo', 'pela', 'pelos', 'pelas',
        'um', 'uma'
    ];

    var FORA = [
        'input', 'textarea', 'script', 'style', 'noscript', 'code', 'pre', 'kbd', 'svg',
        '[contenteditable]', '.tox', '.rich_text_container', '.timeline-content .content',
        '.itil-content', '.read-only-content', '.text-lowercase', '.text-uppercase',
        'a[href^="mailto:"]', '.email', '.select2-search__field', '.CodeMirror', '.ac-aviso-corpo', '.ac-livre', '.ac-ajuda',
        '.ac-comp p', '.ac-comp-ajuda', '.ac-comp-etiqueta'
    ].join(',');

    // Elementos "de linha": o texto antes deles conta como parte da mesma frase
    var EM_LINHA = /^(SPAN|A|STRONG|B|EM|I|U|SMALL|LABEL|ABBR|SUP|SUB|MARK|FONT|BDI|TIME)$/;

    function primeiraLetraMaiuscula(palavra) {
        var i = palavra.search(/\p{L}/u);
        if (i < 0) {
            return palavra;
        }
        return palavra.slice(0, i) + palavra.charAt(i).toUpperCase() + palavra.slice(i + 1);
    }

    function nucleo(palavra) {
        return palavra.replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '').toLowerCase();
    }

    // Há texto antes deste nó na mesma frase/bloco?
    function temTextoAntes(no) {
        var atual = no;
        while (atual) {
            var irmao = atual.previousSibling;
            while (irmao) {
                if ((irmao.textContent || '').trim() !== '') {
                    return /[.!?:]\s*$/.test(irmao.textContent) ? false : true;
                }
                irmao = irmao.previousSibling;
            }
            var pai = atual.parentNode;
            if (!pai || pai.nodeType !== 1 || !EM_LINHA.test(pai.tagName)) {
                return false;
            }
            atual = pai;
        }
        return false;
    }

    // A palavra continua do nó anterior? (ex.: "Co<u>m</u>putadores", letra sublinhada do atalho)
    function continuaPalavra(no) {
        var atual = no;
        while (atual) {
            var irmao = atual.previousSibling;
            while (irmao) {
                var t = irmao.textContent || '';
                if (t !== '') {
                    return /\p{L}$/u.test(t);
                }
                irmao = irmao.previousSibling;
            }
            var pai = atual.parentNode;
            if (!pai || pai.nodeType !== 1 || !EM_LINHA.test(pai.tagName)) {
                return false;
            }
            atual = pai;
        }
        return false;
    }

    function ajustar(texto, comecaFrase, continua) {
        var partes = texto.split(/(\s+)/);
        var inicio = comecaFrase;
        for (var i = 0; i < partes.length; i++) {
            var p = partes[i];
            if (p === '' || /^\s+$/.test(p)) {
                continue;
            }
            // Pedaço de uma palavra que começou em outro nó: não mexe
            if (i === 0 && continua) {
                inicio = /[.!?:]$/.test(p);
                continue;
            }
            // E-mails, endereços, caminhos e siglas com barra ficam como estão
            if (/[@\/\\_]|:\/\/|^www\./i.test(p) || !/\p{L}/u.test(p)) {
                inicio = /[.!?:]$/.test(p);
                continue;
            }
            if (!inicio && MINUSCULAS.indexOf(nucleo(p)) >= 0) {
                partes[i] = p.toLowerCase();
            } else {
                partes[i] = primeiraLetraMaiuscula(p);
            }
            inicio = /[.!?:]$/.test(p);
        }
        return partes.join('');
    }

    function tratarTexto(no) {
        var valor = no.nodeValue;
        if (!valor || !/\p{L}/u.test(valor)) {
            return;
        }
        var pai = no.parentElement;
        if (!pai || pai.closest(FORA)) {
            return;
        }
        var novo = ajustar(valor, !temTextoAntes(no), /^\p{L}/u.test(valor) && continuaPalavra(no));
        if (novo !== valor) {
            no.nodeValue = novo;
        }
    }

    function tratarElemento(raiz) {
        if (raiz.nodeType === 3) {
            tratarTexto(raiz);
            return;
        }
        if (raiz.nodeType !== 1 || raiz.closest(FORA)) {
            return;
        }
        var walker = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT);
        var no;
        var lista = [];
        while ((no = walker.nextNode())) {
            lista.push(no);
        }
        lista.forEach(tratarTexto);
        raiz.querySelectorAll('[placeholder]').forEach(function (campo) {
            if (campo.closest(FORA)) {
                return;
            }
            var ph = campo.getAttribute('placeholder');
            var novo = ajustar(ph, true);
            if (novo !== ph) {
                campo.setAttribute('placeholder', novo);
            }
        });
    }

    // Textos que aparecem depois (listas, janelas, abas carregadas por ajax)
    var pendentes = [];
    var agendado = false;
    function processarPendentes() {
        agendado = false;
        var lote = pendentes;
        pendentes = [];
        lote.forEach(function (n) {
            if (n.isConnected) {
                tratarElemento(n);
            }
        });
    }

    function iniciar() {
        tratarElemento(document.body);
        new MutationObserver(function (mudancas) {
            mudancas.forEach(function (m) {
                if (m.type === 'characterData') {
                    pendentes.push(m.target);
                } else {
                    m.addedNodes.forEach(function (n) { pendentes.push(n); });
                }
            });
            if (pendentes.length && !agendado) {
                agendado = true;
                requestAnimationFrame(processarPendentes);
            }
        }).observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
