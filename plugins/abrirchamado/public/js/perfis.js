// Tela de Perfis (Administração > Perfis): mostra só as permissões que a empresa usa,
// com nome e explicação em português simples. "Mostrar todas as permissões" traz tudo de volta.
// Não muda nenhuma permissão: só a forma de mostrar.
(function () {
    'use strict';

    var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
    if (window.location.pathname !== root + '/front/profile.form.php') {
        return;
    }

    var CHAVE = 'abrirchamado.perfil.completo';

    // Permissões que a empresa não usa (módulos fora do menu)
    var ESCONDER = (
        // ativos
        'monitor networking printer phone peripheral cartridge consumable internet devicesimcard_pinpuk ' +
        // chamados que não usamos
        'ticketcost ticketrecurrent change changevalidation recurrentchange rule_change problem rule_problem ' +
        'planning externalevent task global_validation itiltemplate ' +
        // gerência
        'license contact_enterprise contract infocom budget line certificate datacenter cluster domain appliance database cable_management ' +
        // ferramentas
        'reminder_public rssfeed_public bookmark_public reservation project projecttask ' +
        // administração
        'form inventory locked_field snmpcredential refusedequipment unmanaged agent rule_import rule_mailcollector ' +
        'rule_location rule_asset rule_softwarecategories rule_dictionnary_dropdown rule_dictionnary_printer ' +
        'rule_dictionnary_software transfer entity show_group_hardware ' +
        // configurar
        'device taskcategory tasktemplate state link lineoperator oauth_client defaultfilter itilvalidationtemplate ' +
        // tipos de ativo extras e restos de plugins que não existem mais
        'asset_carros asset_salas'
    ).split(/\s+/);

    // Abas inteiras que só aparecem no modo completo
    var ABAS_ESCONDIDAS = ['Ciclos de Vida', 'Ciclos de vida'];

    // nome do direito => [nome em português, o que libera]
    var NOMES = {
        computer: ['Computadores', 'Cadastro e consulta de computadores e do inventário.'],
        software: ['Softwares', 'Programas e licenças instalados nos computadores.'],
        ticket: ['Chamados', 'Quem pode ver, abrir, editar, atribuir e solucionar. Cada coluna é uma ação.'],
        followup: ['Respostas no chamado', 'Responder e acompanhar a conversa do chamado.'],
        ticketvalidation: ['Aprovações', 'Pedir e dar aprovação em um chamado.'],
        statistic: ['Estatísticas', 'Ver as estatísticas de chamados.'],
        pendingreason: ['Motivos de pendência', 'Cadastrar os motivos usados ao colocar um chamado em pendência.'],
        document: ['Documentos', 'Arquivos anexados e cadastrados no GLPI.'],
        reports: ['Relatórios do GLPI', 'Relatórios prontos do GLPI. Os relatórios da empresa ficam na aba "Módulos da Empresa".'],
        knowbase: ['Base de conhecimento', 'Ler e escrever artigos de ajuda.'],
        knowbasecategory: ['Categorias da base de conhecimento', 'Organizar os artigos por assunto.'],
        user: ['Usuários', 'Ver, cadastrar e editar usuários.'],
        group: ['Grupos (setores)', 'Ver e cadastrar grupos.'],
        profile: ['Perfis', 'Esta tela de permissões.'],
        queuednotification: ['Fila de e-mails e avisos', 'Ver o que está esperando para ser enviado.'],
        logs: ['Histórico', 'Ver quem alterou o quê nos itens.'],
        system_logs: ['Registros do sistema', 'Registros técnicos do GLPI.'],
        rule_ticket: ['Regras dos chamados', 'Regras automáticas que preenchem o chamado (SLA, grupo, urgência).'],
        rule_ldap: ['Regras do AD', 'Definem perfil e grupo de quem entra pelo AD.'],
        config: ['Configuração geral', 'Mexer nas configurações do GLPI. Libere só para administradores.'],
        personalization: ['Preferências pessoais', 'Tema, idioma e opções de cada pessoa.'],
        dashboard: ['Painéis', 'Ver e montar painéis.'],
        itilcategory: ['Categorias dos chamados', 'Cadastrar categorias e subcategorias.'],
        slm: ['Níveis de serviço (SLA)', 'Prazos de atendimento e de solução.'],
        notification: ['Notificações', 'Regras de e-mail e de avisos.'],
        calendar: ['Calendários', 'Horário comercial usado nos prazos.'],
        itilfollowuptemplate: ['Respostas prontas', 'Modelos de resposta para os chamados.'],
        solutiontemplate: ['Modelos de solução', 'Textos prontos para solucionar.'],
        location: ['Locais', 'Locais e unidades.'],
        dropdown: ['Listas suspensas', 'Listas usadas nos formulários.'],
        typedoc: ['Tipos de documento', 'Extensões de arquivo aceitas.'],
        search_config: ['Colunas das listas', 'Escolher as colunas que aparecem nas listas.']
    };

    function completo() {
        try { return window.localStorage.getItem(CHAVE) === '1'; } catch (e) { return false; }
    }

    function nomeDoDireito(tr) {
        var td = tr.querySelector('td[id^="row_label__"]');
        if (!td) {
            return null;
        }
        var m = td.id.match(/^row_label__(.+)_\d+$/);
        return m ? m[1] : null;
    }

    function aplicarLinha(tr, mostrarTudo) {
        var nome = nomeDoDireito(tr);
        if (!nome) {
            return;
        }
        var oculto = !mostrarTudo && ESCONDER.indexOf(nome) >= 0;
        tr.classList.toggle('ac-oculto', oculto);

        var td = tr.querySelector('td[id^="row_label__"]');
        if (!td.hasAttribute('data-ac-original')) {
            td.setAttribute('data-ac-original', td.textContent.replace(/\s+/g, ' ').trim());
        }
        var nova = NOMES[nome];
        if (nova && !mostrarTudo) {
            td.textContent = '';
            var t = document.createElement('div');
            t.className = 'fw-bold';
            t.textContent = nova[0];
            td.appendChild(t);
            if (nova[1]) {
                var a = document.createElement('div');
                a.className = 'ac-ajuda';
                a.textContent = nova[1];
                td.appendChild(a);
            }
        } else if (mostrarTudo && td.hasAttribute('data-ac-original') && td.querySelector('.ac-ajuda')) {
            td.textContent = td.getAttribute('data-ac-original');
        }
    }

    function aplicarTabelas(raiz, mostrarTudo) {
        raiz.querySelectorAll('table').forEach(function (tabela) {
            var linhas = Array.prototype.filter.call(tabela.querySelectorAll('tr'), function (tr) {
                return nomeDoDireito(tr) !== null;
            });
            if (!linhas.length) {
                return;
            }
            linhas.forEach(function (tr) { aplicarLinha(tr, mostrarTudo); });
            var tudoOculto = linhas.every(function (tr) { return tr.classList.contains('ac-oculto'); });
            tabela.classList.toggle('ac-oculto', tudoOculto);
        });
    }

    // Abas de permissões em que o perfil não tem nada marcado (descobertas ao abrir a tela)
    var abasVazias = null;
    var TRIVIAIS = ['dashboard', 'personalization', 'search_config'];

    function descobrirAbasVazias() {
        if (abasVazias !== null) {
            return;
        }
        abasVazias = [];
        var abas = [];
        for (var n = 2; n <= 8; n++) {
            var a = document.querySelector('[data-bs-target^="#tab-Profile_' + n + '-"]');
            if (a && a.getAttribute('data-glpi-ajax-content')) {
                abas.push(a);
            }
        }
        if (!abas.length) {
            abasVazias = null;
            return;
        }
        var parser = new DOMParser();
        Promise.all(abas.map(function (a) {
            return fetch(a.getAttribute('data-glpi-ajax-content'), { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = parser.parseFromString(html, 'text/html');
                    var marcada = false;
                    doc.querySelectorAll('tr').forEach(function (tr) {
                        var nome = nomeDoDireito(tr);
                        if (!nome || ESCONDER.indexOf(nome) >= 0 || TRIVIAIS.indexOf(nome) >= 0) {
                            return;
                        }
                        if (tr.querySelector('input[type="checkbox"]:checked')) {
                            marcada = true;
                        }
                    });
                    if (!marcada) {
                        abasVazias.push((a.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim());
                    }
                })
                .catch(function () { /* mantém a aba visível */ });
        })).then(aplicarTudo);
    }

    function aplicarAbas(mostrarTudo) {
        document.querySelectorAll('[role="tab"]').forEach(function (a) {
            var rotulo = (a.getAttribute('aria-label') || a.textContent || '').replace(/\s+/g, ' ').trim();
            var esconder = ABAS_ESCONDIDAS.indexOf(rotulo) >= 0 || (abasVazias && abasVazias.indexOf(rotulo) >= 0);
            if (esconder || (a.closest('li') || a).classList.contains('ac-oculto')) {
                (a.closest('li') || a).classList.toggle('ac-oculto', esconder && !mostrarTudo);
            }
        });
    }

    function aplicarTudo() {
        var mostrarTudo = completo();
        aplicarAbas(mostrarTudo);
        document.querySelectorAll('.tab-pane, #tabspanel').forEach(function (p) { aplicarTabelas(p, mostrarTudo); });
        var botao = document.querySelector('.ac-modo-perfil button');
        if (botao) {
            botao.innerHTML = mostrarTudo
                ? '<i class="ti ti-eye-off"></i><span>Mostrar só o que usamos</span>'
                : '<i class="ti ti-eye"></i><span>Mostrar todas as permissões</span>';
        }
        var texto = document.querySelector('.ac-modo-perfil .ac-modo-texto');
        if (texto) {
            texto.textContent = mostrarTudo
                ? 'Mostrando todas as permissões do GLPI, inclusive de módulos que não usamos.'
                : 'Mostrando só as permissões dos módulos que a empresa usa.';
        }
    }

    function barra() {
        var painel = document.querySelector('#tabspanel');
        if (!painel || document.querySelector('.ac-modo-perfil')) {
            return;
        }
        var div = document.createElement('div');
        div.className = 'ac-modo-perfil';
        div.innerHTML = '<span class="ac-modo-texto ac-livre"></span><button type="button" class="btn btn-sm btn-outline-secondary"></button>';
        div.querySelector('button').addEventListener('click', function () {
            try { window.localStorage.setItem(CHAVE, completo() ? '0' : '1'); } catch (e) { /* sem armazenamento */ }
            aplicarTudo();
        });
        // Acima do bloco "abas + conteúdo" (o #tabspanel é só a lista de abas, ao lado do conteúdo)
        var bloco = painel.closest('.card-tabs') || painel.parentNode;
        bloco.parentNode.insertBefore(div, bloco);
    }

    function iniciar() {
        barra();
        aplicarTudo();
        descobrirAbasVazias();
        var alvo = document.querySelector('.page-body') || document.body;
        var agendado = false;
        new MutationObserver(function () {
            if (agendado) { return; }
            agendado = true;
            window.requestAnimationFrame(function () {
                agendado = false;
                barra();
                aplicarTudo();
                descobrirAbasVazias();
            });
        }).observe(alvo, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
