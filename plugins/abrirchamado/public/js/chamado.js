// Tela do chamado (técnicos):
// - "Adicionar solução", "Pendência" e "Pedir aprovação" como botões ao lado de "Responder"
//   (sem "Criar uma tarefa" e sem a seta de outras ações);
// - "Responder" é só resposta (sem marcar pendência);
// - "Pendência" abre a resposta já marcada como pendente, com o motivo.
(function () {
    'use strict';

    var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
    if (window.location.pathname.indexOf(root + '/front/ticket.form.php') !== 0) {
        return;
    }
    document.documentElement.classList.add('abrirchamado-ticket');

    var modoPendencia = false;

    function botao(classe, alvo, icone, texto) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-primary answer-action ' + classe;
        b.setAttribute('data-bs-toggle', 'collapse');
        b.setAttribute('data-bs-target', alvo);
        b.innerHTML = '<i class="' + icone + '"></i><span>' + texto + '</span>';
        return b;
    }

    function controlePendencia(bloco) {
        return bloco ? bloco.querySelector('[id^="pending-reasons-control-"]') : null;
    }

    function montar() {
        var acoes = document.querySelector('#itil-footer .main-actions');
        if (!acoes || acoes.dataset.abrirchamado) {
            return;
        }
        acoes.dataset.abrirchamado = '1';

        var responder = acoes.querySelector(':scope > .answer-action');
        var seta = acoes.querySelector(':scope > .dropdown-toggle-split');
        var alvoResposta = responder ? responder.getAttribute('data-bs-target') : '';
        var blocoResposta = alvoResposta ? document.querySelector(alvoResposta) : null;
        var novos = [];

        // Ordem: Responder, Pendência, Solução
        if (responder && blocoResposta) {
            novos.push(botao('ac-pendencia', alvoResposta, 'ti ti-player-pause', 'Pendência'));
        }

        // Solução: sai do menu da seta e vira botão
        var itemSolucao = acoes.querySelector('.dropdown-menu .action-solution');
        if (itemSolucao) {
            novos.push(botao('action-solution', itemSolucao.getAttribute('data-bs-target'), 'ti ti-check', 'Adicionar solução'));
            itemSolucao.closest('li').remove();
        } else {
            var solucaoSeparada = acoes.querySelector(':scope > .action-solution');
            if (solucaoSeparada) {
                solucaoSeparada.querySelector('span').textContent = 'Adicionar solução';
            }
        }

        // Sem "Adicionar documento" (anexo vai junto da resposta) e sem "Criar uma tarefa"
        acoes.querySelectorAll('.dropdown-menu .action-document, :scope > .action-document, .dropdown-menu .action-task, :scope > .action-task').forEach(function (item) {
            (item.closest('li') || item).remove();
        });

        // Aprovação: sai do menu da seta e vira botão, como Pendência
        var itemAprovacao = acoes.querySelector('.dropdown-menu .action-validation');
        if (itemAprovacao) {
            novos.push(botao('ac-aprovacao', itemAprovacao.getAttribute('data-bs-target'), 'ti ti-thumb-up', 'Pedir aprovação'));
            itemAprovacao.closest('li').remove();
        }

        novos.forEach(function (b) {
            acoes.insertBefore(b, seta || null);
        });
        // Menu da seta vazio: some com a seta
        if (seta && !acoes.querySelector('.dropdown-menu li')) {
            seta.remove();
        }

        // Ao abrir a resposta: pendência só no modo "Pendência"
        if (blocoResposta) {
            blocoResposta.addEventListener('show.bs.collapse', function (ev) {
                if (ev.target !== blocoResposta) {
                    return;
                }
                blocoResposta.classList.toggle('ac-modo-pendencia', modoPendencia);
                ajustarPendencia(blocoResposta, modoPendencia);
            });
        }
    }

    // Marca/desmarca "pendente" assim que o formulário de resposta estiver carregado
    function ajustarPendencia(bloco, pendente) {
        function tentar() {
            var controle = controlePendencia(bloco);
            var marcar = controle ? controle.querySelector('input[type="checkbox"][name="pending"]') : null;
            if (!marcar) {
                return false;
            }
            controle.classList.toggle('ac-escondido', !pendente);
            if (marcar.checked !== pendente) {
                marcar.click();
            }
            return true;
        }
        if (tentar()) {
            return;
        }
        var observador = new MutationObserver(function () {
            if (tentar()) {
                observador.disconnect();
            }
        });
        observador.observe(bloco, { childList: true, subtree: true });
        setTimeout(function () { observador.disconnect(); }, 10000);
    }

    // Qual botão abriu a resposta. Escuta no window (captura) porque o Bootstrap
    // abre o formulário já na captura do document, antes do clique chegar ao botão.
    window.addEventListener('click', function (ev) {
        var alvo = ev.target.closest ? ev.target.closest('#itil-footer .answer-action') : null;
        if (alvo) {
            modoPendencia = alvo.classList.contains('ac-pendencia');
        }
    }, true);

    function iniciar() {
        montar();
        new MutationObserver(montar).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
