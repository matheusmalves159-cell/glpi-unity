(function () {
    'use strict';

    // Destino do botão, página inicial e tempo máximo sem atividade
    var URL_CHAMADO = '/ServiceCatalog';
    var URL_INICIAL = '/front/ticket.php';
    var URL_SALAS = '/plugins/salasreuniao/front/agendar.php';
    var URL_MINHAS_SALAS = '/plugins/salasreuniao/front/minhasreservas.php';
    var TEMPO_INATIVO_MS = 60 * 60 * 1000;

    var root = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';

    // ---------------------------------------------------------------
    // Sem página Home: a página inicial passa a ser a lista de chamados.
    // Roda no <head>, antes de a Home ser desenhada. Mantém intactos os
    // acessos com parâmetros (ex.: ?redirect= dos links de e-mail).
    // ---------------------------------------------------------------
    var caminho = window.location.pathname;
    if ((caminho === root + '/front/central.php' || caminho === root + '/Central') && window.location.search === '') {
        window.location.replace(root + URL_INICIAL);
        return;
    }

    function ajustarLinksHome() {
        // Logos
        document.querySelectorAll('a.navbar-brand').forEach(function (a) {
            a.setAttribute('href', root + URL_INICIAL);
            a.setAttribute('title', 'Chamados');
        });
        // Item "Home" da trilha de navegação
        document.querySelectorAll('.breadcrumb > .breadcrumb-item').forEach(function (li) {
            if (li.querySelector('.ti-home-2')) {
                li.remove();
            }
        });
    }

    // ---------------------------------------------------------------
    // Botão "Abrir Chamados"
    // ---------------------------------------------------------------
    function adicionarBotao() {
        var menu = document.querySelector('#navbar-menu > ul.navbar-nav');
        if (!menu || menu.querySelector('.abrirchamado-item')) {
            return;
        }

        var item = document.createElement('li');
        item.className = 'nav-item abrirchamado-item';
        item.innerHTML =
            '<a class="abrirchamado-btn" href="' + root + URL_CHAMADO + '">' +
            '<i class="ti ti-headset"></i><span>Abrir Chamados</span></a>';

        // Logo antes de "Ativos" (primeiro item do menu); sem esse menu, no início
        var ativos = Array.prototype.find.call(menu.children, function (li) {
            var label = (li.getAttribute('aria-label') || '').trim().toLowerCase();
            return label === 'ativos' || label === 'ativo';
        });
        menu.insertBefore(item, ativos || menu.firstChild);
    }

    // ---------------------------------------------------------------
    // Menu principal organizado: cada item no menu certo, em grupos com título.
    // Só reorganiza o que o usuário já pode ver; itens não listados ficam no fim.
    // ---------------------------------------------------------------
    var MENU = {
        'Ativos': [
            ['', ['/front/computer.php', '/front/software.php', '/front/allassets.php']]
        ],
        'Assistência': [
            ['', ['/front/ticket.php', '/ServiceCatalog', '/plugins/salasreuniao/front/minhasreservas.php']]
        ],
        'Gerência': [
            ['', ['/front/document.php', '/plugins/pagamentos/front/planilha.php']]
        ],
        'Ferramentas': [
            ['Conhecimento', ['/front/knowbaseitem.php']],
            ['Relatórios', ['/plugins/relatorios/front/painel.php', '/front/stat.php', '/front/report.php']]
        ],
        'Administração': [
            ['Pessoas e acessos', ['/front/user.php', '/front/group.php', '/plugins/chamadoti/front/acessos.php', '/front/profile.php', '/front/entity.php']],
            ['Automação', ['/front/rule.php', '/front/dictionnary.php', '/front/form/form.php', '/Inventory/Configuration']],
            ['Acompanhamento', ['/front/logs.php', '/front/queuednotification.php']]
        ],
        'Configurar': [
            ['Chamados', ['/plugins/chamadoti/front/central.php', '/front/slm.php', '/plugins/salasreuniao/front/room.php', '/front/dropdown.php', '/front/setup.notification.php']],
            ['Sistema', ['/front/config.form.php', '/front/setup.auth.php', '/front/crontask.php', '/front/marketplace.php', '/plugins/brandkit/front/index.php']]
        ]
    };

    // Itens que não são usados e saem do menu (as telas continuam existindo)
    var FORA_DO_MENU = [
        '/front/monitor.php', '/front/networkequipment.php', '/front/peripheral.php', '/front/printer.php', '/front/phone.php',
        '/front/supplier.php', '/front/contact.php', '/front/contract.php',
        '/front/ticket.form.php', '/front/ticketrecurrent.php', '/front/planning.php',
        // Já ficam nos cartões da página "Abrir Chamados" / pesquisas salvas não são usadas
        '/plugins/salasreuniao/front/agendar.php', '/plugins/pagamentos/front/solicitar.php', '/front/savedsearch.php',
        // Configurar: não usados (as telas continuam existindo pelo endereço)
        '/front/mailcollector.php', '/front/fieldunicity.php', '/front/link.php', '/front/webhook.php',
        '/front/oauthclient.php', '/front/asset/assetdefinition.php', '/front/devices.php'
    ];

    // Menus pequenos ficam numa coluna só (grupos um embaixo do outro), sem espaço vazio
    var MAX_ITENS_UMA_COLUNA = 8;

    // Nomes mais claros para alguns itens
    var NOMES = {
        '/front/ticket.form.php': 'Criar chamado (formulário completo)',
        '/front/report.php': 'Relatórios do GLPI',
        '/plugins/salasreuniao/front/room.php': 'Cadastro de Salas de Reunião',
        '/plugins/brandkit/front/index.php': 'Identidade visual (logo e login)',
        '/front/mailcollector.php': 'Recebimento de e-mails'
    };

    function organizarMenu() {
        var raiz = document.querySelector('#navbar-menu > ul.navbar-nav');
        if (!raiz || raiz.dataset.acOrganizado) {
            return;
        }
        raiz.dataset.acOrganizado = '1';

        var menus = {};
        var itens = {};
        raiz.querySelectorAll(':scope > li.nav-item.dropdown').forEach(function (li) {
            menus[(li.getAttribute('aria-label') || '').trim()] = li;
            li.querySelectorAll('a.dropdown-item[href]').forEach(function (a) {
                var caminho = a.getAttribute('href').replace(root, '').split('?')[0];
                itens[caminho] = a;
            });
        });

        // Atalho "Minhas reservas de sala" em Assistência (para quem pode agendar sala)
        var agendar = itens[URL_SALAS];
        if (agendar && !itens[URL_MINHAS_SALAS]) {
            var minhas = agendar.cloneNode(false);
            minhas.setAttribute('href', root + URL_MINHAS_SALAS);
            minhas.setAttribute('aria-label', 'Reserva Sala de Reunião');
            minhas.removeAttribute('accesskey');
            minhas.classList.remove('active');
            minhas.innerHTML = '<i class="ti ti-calendar-check"></i><span class="text-wrap">Reserva Sala de Reunião</span>';
            if (caminho.indexOf(root + URL_MINHAS_SALAS) === 0) {
                minhas.classList.add('active');
            }
            agendar.parentNode.insertBefore(minhas, agendar);
            itens[URL_MINHAS_SALAS] = minhas;
        }

        FORA_DO_MENU.forEach(function (caminho) {
            if (itens[caminho]) {
                itens[caminho].remove();
                delete itens[caminho];
            }
        });

        Object.keys(NOMES).forEach(function (caminho) {
            var a = itens[caminho];
            var texto = a ? a.querySelector('.text-wrap') : null;
            if (texto) {
                texto.textContent = NOMES[caminho];
                a.setAttribute('aria-label', NOMES[caminho]);
            }
        });

        Object.keys(MENU).forEach(function (titulo) {
            var li = menus[titulo];
            var colunas = li ? li.querySelector('.dropdown-menu-columns') : null;
            if (!colunas) {
                return;
            }
            var sobras = Array.prototype.slice.call(colunas.querySelectorAll('a.dropdown-item'));
            var novas = document.createElement('div');
            novas.className = 'dropdown-menu-columns';
            MENU[titulo].forEach(function (grupo) {
                var coluna = document.createElement('div');
                coluna.className = 'dropdown-menu-column';
                if (grupo[0]) {
                    var cabecalho = document.createElement('h6');
                    cabecalho.className = 'dropdown-header ac-menu-grupo';
                    cabecalho.textContent = grupo[0];
                    coluna.appendChild(cabecalho);
                }
                grupo[1].forEach(function (caminho) {
                    if (itens[caminho]) {
                        coluna.appendChild(itens[caminho]);
                        sobras = sobras.filter(function (a) { return a !== itens[caminho]; });
                    }
                });
                if (coluna.querySelector('a.dropdown-item')) {
                    novas.appendChild(coluna);
                }
            });
            // Itens que não estão na lista (ex.: plugin novo) continuam aparecendo, no fim
            var restantes = sobras.filter(function (a) { return a.isConnected && colunas.contains(a); });
            var visiveis = restantes.filter(function (a) { return getComputedStyle(a).display !== 'none'; });
            if (visiveis.length) {
                var resto = document.createElement('div');
                resto.className = 'dropdown-menu-column';
                visiveis.forEach(function (a) { resto.appendChild(a); });
                novas.appendChild(resto);
            }
            // Itens escondidos de propósito (ver CSS) só acompanham, sem abrir coluna vazia
            restantes.filter(function (a) { return visiveis.indexOf(a) < 0; }).forEach(function (a) {
                (novas.lastElementChild || novas).appendChild(a);
            });
            var colunasNovas = novas.querySelectorAll(':scope > .dropdown-menu-column');
            if (colunasNovas.length > 1 && novas.querySelectorAll('a.dropdown-item').length <= MAX_ITENS_UMA_COLUNA) {
                var unica = colunasNovas[0];
                Array.prototype.slice.call(colunasNovas, 1).forEach(function (c) {
                    while (c.firstChild) {
                        unica.appendChild(c.firstChild);
                    }
                    c.remove();
                });
            }
            colunas.replaceWith(novas);
            // Com títulos de grupo, o título do menu repetido em cima sobra
            var tituloMenu = li.querySelector(':scope > .dropdown-menu > h6.dropdown-header');
            if (tituloMenu && novas.querySelector('.ac-menu-grupo')) {
                tituloMenu.classList.add('d-none');
            }
        });

        // Menus que ficaram vazios (ex.: "Plug-ins", cujo item foi para Configurar)
        raiz.querySelectorAll(':scope > li.nav-item.dropdown').forEach(function (li) {
            if (!li.querySelector('a.dropdown-item')) {
                li.classList.add('d-none');
            }
        });

        // Menu aberto perto da borda direita (ex.: Configurar, com 3 colunas) não sai da tela
        raiz.addEventListener('shown.bs.dropdown', function (e) {
            var li = e.target.closest('li.nav-item.dropdown');
            var dm = li ? li.querySelector(':scope > .dropdown-menu') : null;
            if (!dm || window.innerWidth < 992) {
                return;
            }
            // Medido pela largura real (o menu abre com animação de zoom, que distorce o retângulo)
            dm.style.marginLeft = '0px';
            var sobra = li.getBoundingClientRect().left + dm.offsetWidth - (window.innerWidth - 12);
            if (sobra > 0) {
                dm.style.marginLeft = (-sobra) + 'px';
            }
        });
    }

    // ---------------------------------------------------------------
    // Lista de chamados: colunas com nome repetido ("Observador - Observador")
    // ---------------------------------------------------------------
    var COLUNAS = {
        'atribuído - técnico': 'Técnico',
        'atribuído - grupo de técnicos': 'Grupo atribuído',
        'requerente - grupo requerente': 'Grupo requerente',
        'observador - grupo observador': 'Grupo observador'
    };

    function nomesDasColunas() {
        document.querySelectorAll('table.search-results thead th').forEach(function (th) {
            var walker = document.createTreeWalker(th, NodeFilter.SHOW_TEXT);
            var no;
            while ((no = walker.nextNode())) {
                var texto = no.nodeValue.trim();
                if (texto.indexOf(' - ') < 0) {
                    continue;
                }
                var partes = texto.split(' - ');
                var novo = COLUNAS[texto.toLowerCase()] ||
                    (partes.length === 2 && partes[0].trim().toLowerCase() === partes[1].trim().toLowerCase() ? partes[1].trim() : null);
                if (novo) {
                    no.nodeValue = no.nodeValue.replace(texto, novo);
                }
            }
        });
    }

    function vigiarColunas() {
        var lista = document.querySelector('[data-glpi-search-container], .search_page');
        if (!lista) {
            return;
        }
        nomesDasColunas();
        new MutationObserver(nomesDasColunas).observe(lista, { childList: true, subtree: true });
    }

    // ---------------------------------------------------------------
    // Botão de tema claro/escuro ao lado do usuário (salvo nas preferências)
    // ---------------------------------------------------------------
    function botaoTema() {
        // Menu do usuário existe em duas versões (tela grande e celular)
        document.querySelectorAll('.user-menu').forEach(function (menu) {
            var usuario = menu.querySelector(':scope > .nav-item.dropdown');
            if (usuario && !menu.querySelector('.ac-tema')) {
                menu.insertBefore(criarBotaoTema(), usuario);
            }
        });
    }

    function criarBotaoTema() {
        var escuro = document.documentElement.getAttribute('data-glpi-theme-dark') === '1';
        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'btn btn-icon btn-ghost-secondary ac-tema';
        botao.title = escuro ? 'Usar tema claro' : 'Usar tema escuro';
        botao.setAttribute('aria-label', botao.title);
        botao.innerHTML = '<i class="ti ' + (escuro ? 'ti-sun' : 'ti-moon') + '"></i>';
        botao.addEventListener('click', function () {
            botao.disabled = true;
            var corpo = new FormData();
            corpo.append('tema', escuro ? 'claro' : 'escuro');
            fetch(root + '/plugins/abrirchamado/front/tema.php', {
                method: 'POST',
                body: corpo,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': typeof getAjaxCsrfToken === 'function' ? getAjaxCsrfToken() : ''
                }
            }).then(function (r) {
                if (!r.ok) {
                    throw new Error(String(r.status));
                }
                window.location.reload();
            }).catch(function () {
                botao.disabled = false;
            });
        });
        return botao;
    }

    // ---------------------------------------------------------------
    // "Catálogo de serviços" passa a se chamar "Abrir Chamados".
    // A tradução está em files/_locales; isto cobre o menu que o GLPI
    // guarda na sessão (usuários que já estavam logados).
    // ---------------------------------------------------------------
    function renomearCatalogo() {
        var alvos = document.querySelectorAll('#navbar-menu .dropdown-item, #navbar-menu .nav-link, .breadcrumb-item, main h1, main h2');
        alvos.forEach(function (el) {
            var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            var no;
            while ((no = walker.nextNode())) {
                if (/Cat[aá]logo de servi[cç]os|Service catalog/i.test(no.nodeValue)) {
                    no.nodeValue = no.nodeValue.replace(/Cat[aá]logo de servi[cç]os|Service catalog/ig, 'Abrir Chamados');
                }
            }
            if (el.title && /Cat[aá]logo de servi[cç]os/i.test(el.title)) {
                el.title = 'Abrir Chamados';
            }
        });
        document.title = document.title.replace(/Cat[aá]logo de servi[cç]os/i, 'Abrir Chamados');
    }

    // ---------------------------------------------------------------
    // Catálogo de serviços: atalho para agendar sala de reunião
    // ---------------------------------------------------------------
    function cartaoSalaReuniao() {
        // Mesmo padrão dos outros cartões (ver .ac-catalogo-* no CSS)
        var coluna = document.createElement('div');
        coluna.className = 'col-12 col-md-6 d-flex ac-catalogo-col ac-cartao-roxo abrirchamado-catalogo-sala';
        coluna.style.order = '-7';
        // O cartão todo leva ao agendamento; o link "Ver minhas reservas" fica por cima (stretched-link)
        coluna.innerHTML =
            '<div class="card mx-1 my-2 flex-grow-1 position-relative">' +
            '<section class="card-body">' +
            '<div class="d-flex align-items-center">' +
            '<span class="ac-catalogo-icone flex-shrink-0"><i class="ti ti-calendar-event"></i></span>' +
            '<div class="ms-4"><h2 class="card-title mb-1">' +
            '<a class="stretched-link text-reset text-decoration-none" href="' + root + URL_SALAS + '">Sala de Reunião</a></h2>' +
            '<div class="text-muted small">Reserve uma sala para reuniões internas ou com visitantes</div>' +
            '<a class="ac-catalogo-extra" href="' + root + URL_MINHAS_SALAS + '">' +
            '<i class="ti ti-list-check"></i> Ver Minhas Reservas</a></div>' +
            '</div></section></div>';
        return coluna;
    }

    function atalhoCatalogo() {
        if (caminho.indexOf(root + '/ServiceCatalog') !== 0) {
            return;
        }
        // Nome da aba igual ao do menu ("Novo chamado" vem do GLPI)
        document.title = document.title.replace(/^[^-]*-/, 'Abrir Chamados -');
        var colocar = function () {
            // Só na raiz do catálogo e sem pesquisa digitada
            var params = new URLSearchParams(window.location.search);
            var categoria = params.get('category');
            var busca = document.querySelector('input[data-glpi-service-catalog-filter-items], main input[type="search"], main input[placeholder*="esquisar"]');
            var naRaiz = (!categoria || categoria === '0') && (!busca || busca.value === '');
            var existente = document.querySelector('.abrirchamado-catalogo-sala');
            var primeiro = document.querySelector('main a.card.mx-1');

            if (!naRaiz || !primeiro) {
                if (existente) {
                    existente.remove();
                }
                return;
            }
            if (!existente) {
                primeiro.parentElement.parentElement.insertBefore(cartaoSalaReuniao(), primeiro.parentElement);
            }
        };
        colocar();
        // O catálogo recarrega os cartões ao filtrar/navegar
        var alvo = document.querySelector('main') || document.body;
        new MutationObserver(colocar).observe(alvo, { childList: true, subtree: true });
    }

    // ---------------------------------------------------------------
    // Lista de chamados: filtros rápidos acima da tabela.
    // Usam a própria pesquisa do GLPI (criteria na URL), então o filtro
    // avançado "Filtrado por…" continua funcionando por baixo.
    // ---------------------------------------------------------------
    var CAMPO_STATUS = '12';
    var CAMPO_REQUERENTE = '4';
    var CAMPO_TECNICO = '5';
    var CAMPO_GRUPO_TECNICO = '8';
    var CAMPO_TITULO = '1';
    var CAMPO_ID = '2';
    var CAMPO_CATEGORIA = '7';

    // [valor, rótulo, cor do ponto] — cores iguais às dos cartões do painel
    var SITUACOES = [
        ['notold', 'Abertos', ''],
        ['1', 'Novo', '#1f6f33'],
        ['process', 'Atendimento', '#4299e1'],
        ['4', 'Pendente', '#f59f00'],
        ['5', 'Resolvido', '#5f6b7a'],
        ['6', 'Fechado', '#7048e8'],
        ['all', 'Todos', '']
    ];
    // [campo:valor, rótulo, ícone]
    var PESSOAS = [
        ['', 'Todos', 'ti-users'],
        [CAMPO_TECNICO + ':myself', 'Estou Atendendo', 'ti-headset'],
        [CAMPO_REQUERENTE + ':myself', 'Abri', 'ti-user'],
        [CAMPO_GRUPO_TECNICO + ':mygroups', 'Meu Grupo', 'ti-users-group']
    ];

    // Critérios que o GLPI está usando agora (lidos do formulário de pesquisa)
    function lerFiltros(form) {
        var atual = { situacao: 'all', pessoa: '', texto: '', setor: '', outros: 0 };
        var linhas = {};
        form.querySelectorAll('[name^="criteria["]').forEach(function (campo) {
            var m = campo.name.match(/^criteria\[(\d+)\]\[(\w+)\]$/);
            if (m) {
                linhas[m[1]] = linhas[m[1]] || {};
                linhas[m[1]][m[2]] = campo.value;
            }
        });
        Object.keys(linhas).forEach(function (i) {
            var c = linhas[i];
            var chave = c.field + ':' + c.value;
            if (c.field === CAMPO_STATUS && c.searchtype === 'equals') {
                atual.situacao = c.value;
            } else if (c.field === CAMPO_CATEGORIA && c.searchtype === 'under') {
                atual.setor = c.value;
            } else if (c.searchtype === 'equals' && PESSOAS.some(function (p) { return p[0] === chave; })) {
                atual.pessoa = chave;
            } else if (c.field === CAMPO_TITULO && c.searchtype === 'contains') {
                atual.texto = c.value;
            } else if (c.field === CAMPO_ID) {
                // O GLPI troca "equals" por "contains" no campo ID
                atual.texto = c.value;
            } else {
                atual.outros++;
            }
        });
        return atual;
    }

    // Monta a URL da lista com os filtros escolhidos (mantém a ordenação atual)
    function urlFiltros(form, f) {
        var p = new URLSearchParams();
        var acao = new URLSearchParams((form.getAttribute('action') || '').split('?')[1] || '');
        acao.forEach(function (v, k) {
            if (/^(sort|order)\[/.test(k)) {
                p.append(k, v);
            }
        });
        var n = 0;
        var add = function (campo, tipo, valor) {
            p.append('criteria[' + n + '][link]', 'AND');
            p.append('criteria[' + n + '][field]', campo);
            p.append('criteria[' + n + '][searchtype]', tipo);
            p.append('criteria[' + n + '][value]', valor);
            n++;
        };
        if (f.setor) {
            add(CAMPO_CATEGORIA, 'under', f.setor);
        }
        if (f.situacao && f.situacao !== 'all') {
            add(CAMPO_STATUS, 'equals', f.situacao);
        }
        if (f.pessoa) {
            add(f.pessoa.split(':')[0], 'equals', f.pessoa.split(':')[1]);
        }
        var texto = (f.texto || '').trim();
        if (texto) {
            add(/^#?\d+$/.test(texto) ? CAMPO_ID : CAMPO_TITULO, /^#?\d+$/.test(texto) ? 'equals' : 'contains', texto.replace(/^#/, ''));
        }
        if (n === 0) {
            // Sem critérios o GLPI reaproveita a última pesquisa da sessão
            add(CAMPO_STATUS, 'equals', 'all');
        }
        return root + '/front/ticket.php?' + p.toString();
    }

    function chip(rotulo, ativo, extra) {
        var a = document.createElement('a');
        a.className = 'btn btn-sm ac-chip' + (ativo ? ' active' : '');
        a.href = '#';
        a.innerHTML = (extra || '') + '<span>' + rotulo + '</span>';
        return a;
    }

    var setoresCache = null;
    function carregarSetores(depois) {
        if (setoresCache) {
            depois(setoresCache);
            return;
        }
        fetch(root + '/plugins/abrirchamado/front/setores.php', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : []; })
            .catch(function () { return []; })
            .then(function (lista) {
                setoresCache = Array.isArray(lista) ? lista : [];
                depois(setoresCache);
            });
    }

    function barraFiltros(form) {
        var atual = lerFiltros(form);
        var ir = function (mudanca) {
            var f = { situacao: atual.situacao, pessoa: atual.pessoa, texto: atual.texto, setor: atual.setor };
            Object.keys(mudanca).forEach(function (k) { f[k] = mudanca[k]; });
            window.location.href = urlFiltros(form, f);
        };

        var barra = document.createElement('div');
        barra.className = 'ac-filtros';

        // Setor (TI, Administrativo…): vem das telas de chamado ativas
        var linhaSetor = document.createElement('div');
        linhaSetor.className = 'ac-filtros-linha';
        linhaSetor.hidden = true;
        carregarSetores(function (setores) {
            if (!setores.length) {
                return;
            }
            linhaSetor.innerHTML = '<span class="ac-filtros-rotulo">Setor</span>';
            var todos = chip('Todos', !atual.setor, '<i class="ti ti-building"></i>');
            todos.addEventListener('click', function (e) { e.preventDefault(); ir({ setor: '' }); });
            linhaSetor.appendChild(todos);
            setores.forEach(function (s) {
                var c = chip(s.nome, atual.setor === String(s.id), '');
                c.addEventListener('click', function (e) { e.preventDefault(); ir({ setor: String(s.id) }); });
                linhaSetor.appendChild(c);
            });
            linhaSetor.hidden = false;
        });

        var linha1 = document.createElement('div');
        linha1.className = 'ac-filtros-linha';
        linha1.innerHTML = '<span class="ac-filtros-rotulo">Situação</span>';
        SITUACOES.forEach(function (s) {
            var ponto = s[2] ? '<span class="ac-ponto" style="background:' + s[2] + '"></span>' : '';
            var c = chip(s[1], atual.situacao === s[0], ponto);
            c.addEventListener('click', function (e) { e.preventDefault(); ir({ situacao: s[0] }); });
            linha1.appendChild(c);
        });

        var linha2 = document.createElement('div');
        linha2.className = 'ac-filtros-linha';
        linha2.innerHTML = '<span class="ac-filtros-rotulo">Quem</span>';
        PESSOAS.forEach(function (q) {
            var c = chip(q[1], atual.pessoa === q[0], '<i class="ti ' + q[2] + '"></i>');
            c.addEventListener('click', function (e) { e.preventDefault(); ir({ pessoa: q[0] }); });
            linha2.appendChild(c);
        });

        // Busca por título ou número (Enter)
        var busca = document.createElement('div');
        busca.className = 'ac-filtros-busca input-icon';
        busca.innerHTML =
            '<span class="input-icon-addon"><i class="ti ti-search"></i></span>' +
            '<input type="search" class="form-control form-control-sm" placeholder="Título ou número do chamado" autocomplete="off">';
        var campo = busca.querySelector('input');
        campo.value = atual.texto;
        campo.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                ir({ texto: campo.value });
            }
        });
        campo.addEventListener('search', function () {
            if (campo.value === '' && atual.texto !== '') {
                ir({ texto: '' });
            }
        });
        linha2.appendChild(busca);

        // Limpar: volta ao padrão (em aberto, todos)
        var padrao = atual.situacao === 'notold' && !atual.pessoa && !atual.texto && !atual.setor && atual.outros === 0;
        if (!padrao) {
            var limpar = chip('Limpar', false, '<i class="ti ti-x"></i>');
            limpar.className += ' ac-chip-limpar';
            limpar.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = urlFiltros(form, { situacao: 'notold', pessoa: '', texto: '', setor: '' });
            });
            linha2.appendChild(limpar);
        }

        barra.appendChild(linhaSetor);
        barra.appendChild(linha1);
        barra.appendChild(linha2);
        return barra;
    }

    function filtrosRapidos() {
        if (caminho !== root + '/front/ticket.php') {
            return;
        }
        var colocar = function () {
            var form = document.querySelector('form.search-form-container');
            var cartao = form && form.closest('.search-card');
            if (!cartao || cartao.querySelector('.ac-filtros')) {
                return;
            }
            cartao.insertBefore(barraFiltros(form), cartao.firstChild);
        };
        colocar();
        // A lista é redesenhada por AJAX ao pesquisar/paginar
        var alvo = document.querySelector('.search-container') || document.body;
        new MutationObserver(colocar).observe(alvo, { childList: true, subtree: true });
    }

    // ---------------------------------------------------------------
    // Logout após inatividade (compartilhado entre abas)
    // ---------------------------------------------------------------
    var CHAVE = 'abrirchamado.ultimaAtividade';
    var ultimaLocal = Date.now();
    var saindo = false;

    function lerUltima() {
        try {
            var v = parseInt(window.localStorage.getItem(CHAVE), 10);
            return isNaN(v) ? ultimaLocal : Math.max(v, ultimaLocal);
        } catch (e) {
            return ultimaLocal;
        }
    }

    function registrarAtividade() {
        var agora = Date.now();
        // Grava no máximo a cada 5s para não sobrecarregar o storage
        if (agora - ultimaLocal < 5000) {
            return;
        }
        ultimaLocal = agora;
        try {
            window.localStorage.setItem(CHAVE, String(agora));
        } catch (e) { /* storage indisponível: vale só para esta aba */ }
    }

    function verificar() {
        if (saindo) {
            return;
        }
        if (Date.now() - lerUltima() >= TEMPO_INATIVO_MS) {
            saindo = true;
            try {
                window.localStorage.removeItem(CHAVE);
            } catch (e) { /* ignora */ }
            window.location.href = root + '/front/logout.php?noAUTO=1';
        }
    }

    function iniciarInatividade() {
        try {
            window.localStorage.setItem(CHAVE, String(ultimaLocal));
        } catch (e) { /* ignora */ }
        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'].forEach(function (ev) {
            document.addEventListener(ev, registrarAtividade, { passive: true, capture: true });
        });
        // Ao voltar para a aba, confere na hora (timers ficam lentos em abas ocultas)
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                verificar();
            }
        });
        window.setInterval(verificar, 15000);
    }

    // Lista de usuários: sem o botão "Mostrar a lixeira" (usuários são geridos pelo AD)
    function esconderLixeiraUsuarios() {
        document.querySelectorAll('[data-glpi-search-container]').forEach(function (caixa) {
            var tipo = caixa.querySelector('input[name="itemtype"]');
            if (!tipo || tipo.value !== 'User') {
                return;
            }
            caixa.querySelectorAll('button[aria-label*="lixeira" i], button[data-bs-original-title*="lixeira" i]').forEach(function (botao) {
                botao.style.setProperty('display', 'none', 'important');
            });
        });
    }

    // Também vale para listas de usuários abertas em abas (grupo, perfil...), que carregam por AJAX
    function marcarPaginaUsuarios() {
        esconderLixeiraUsuarios();
        new MutationObserver(esconderLixeiraUsuarios).observe(document.body, { childList: true, subtree: true });
        window.addEventListener('load', esconderLixeiraUsuarios);
        [300, 1000, 2500].forEach(function (ms) {
            window.setTimeout(esconderLixeiraUsuarios, ms);
        });
    }

    function iniciar() {
        marcarPaginaUsuarios();
        ajustarLinksHome();
        adicionarBotao();
        organizarMenu();
        vigiarColunas();
        botaoTema();
        renomearCatalogo();
        atalhoCatalogo();
        iniciarInatividade();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
