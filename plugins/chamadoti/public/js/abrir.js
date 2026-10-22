// Tela "Chamado de TI": busca ou escolha do pedido em botões, campos extras por
// tipo de pedido e prioridade sugerida a partir de quem está afetado.
(function () {
    'use strict';

    var form = document.getElementById('ct-form');
    if (!form) {
        return;
    }
    var dados = JSON.parse(document.getElementById('ct-dados').textContent);
    var nos = dados.nos;
    var campoCategoria = document.getElementById('ct-categoria');
    var niveis = document.getElementById('ct-niveis');
    var blocoExtras = document.getElementById('ct-bloco-extras');
    var resto = document.getElementById('ct-resto');
    var escolher = document.getElementById('ct-escolher');
    var escolha = document.getElementById('ct-escolha');
    var escolhaTexto = document.getElementById('ct-escolha-texto');
    var busca = document.getElementById('ct-busca');
    var resultados = document.getElementById('ct-resultados');
    var resumo = document.getElementById('ct-resumo');
    var prioridadeManual = false;
    var selecao = [];

    function no(id) {
        return nos[String(id)] || null;
    }

    function escapar(texto) {
        var d = document.createElement('div');
        d.textContent = texto;
        return d.innerHTML;
    }

    // Caminho do primeiro nível até a categoria (a raiz é a única sem pai na lista)
    function caminho(id) {
        var lista = [];
        var atual = no(id);
        while (atual && no(atual.pai)) {
            lista.unshift(Number(id));
            id = atual.pai;
            atual = no(id);
        }
        return lista;
    }

    function pergunta(pai) {
        var nome = (no(pai) || {}).nome || '';
        if (/não consigo acessar|rotina com erro/i.test(nome)) {
            return 'Qual sistema?';
        }
        if (/^erros$/i.test(nome)) {
            return 'Qual é o problema?';
        }
        return 'Escolha uma opção:';
    }

    // Botões de cada nível abaixo do tipo escolhido
    function desenhar() {
        niveis.innerHTML = '';
        document.querySelectorAll('.ct-tipo').forEach(function (b) {
            var ativo = selecao.length && Number(b.dataset.id) === selecao[0];
            b.classList.toggle('ativo', !!ativo);
            b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });
        for (var nivel = 0; nivel < selecao.length; nivel++) {
            var pai = no(selecao[nivel]);
            if (!pai || !pai.filhos.length) {
                break;
            }
            var bloco = document.createElement('div');
            bloco.className = 'ct-nivel mb-3';
            var html = '<div class="ct-nivel-pergunta">' + escapar(pergunta(selecao[nivel])) + '</div><div class="ct-opcoes">';
            pai.filhos.forEach(function (f) {
                var marcado = selecao[nivel + 1] === f;
                html += '<button type="button" class="ct-opcao' + (marcado ? ' ativo' : '') + '" data-nivel="' + (nivel + 1) +
                    '" data-id="' + f + '" aria-pressed="' + (marcado ? 'true' : 'false') + '">' + escapar(no(f).nome) +
                    (no(f).filhos.length ? ' <i class="ti ti-chevron-right"></i>' : '') + '</button>';
            });
            bloco.innerHTML = html + '</div>';
            niveis.appendChild(bloco);
        }
        atualizar();
    }

    // Campos criados na Central de telas para o pedido (mudam quando o pedido muda)
    var contCampos = document.getElementById('ct-campos-proprios');
    var chaveCampos = '';

    function htmlCampo(c) {
        var id = 'ct-cp-' + c.id;
        var nome = 'campo[' + c.id + ']';
        var req = c.obrigatorio ? ' required' : '';
        var antes = (dados.antigo_campo || {})[c.id];
        var valor = (antes !== undefined && antes !== null && !Array.isArray(antes)) ? String(antes) : '';
        var rotulo = '<label class="form-label' + (c.obrigatorio ? ' required' : '') + '" for="' + id + '">' + escapar(c.rotulo) + '</label>';
        var ajuda = c.ajuda ? '<div class="form-hint">' + escapar(c.ajuda) + '</div>' : '';
        var largura = (c.tipo === 'texto_longo' || c.tipo === 'multipla') ? 'col-12' : 'col-12 col-md-6';
        var campo = '';
        switch (c.tipo) {
            case 'texto_longo':
                campo = '<textarea class="form-control" id="' + id + '" name="' + nome + '" rows="3" maxlength="4000"' + req + '>' + escapar(valor) + '</textarea>';
                break;
            case 'numero':
                campo = '<input type="number" step="any" class="form-control" id="' + id + '" name="' + nome + '" value="' + atributo(valor) + '"' + req + '>';
                break;
            case 'data':
                campo = '<input type="date" class="form-control" id="' + id + '" name="' + nome + '" value="' + atributo(valor) + '"' + req + '>';
                break;
            case 'data_hora':
                campo = '<input type="datetime-local" class="form-control" id="' + id + '" name="' + nome + '" value="' + atributo(valor) + '"' + req + '>';
                break;
            case 'email':
                campo = '<input type="email" class="form-control" id="' + id + '" name="' + nome + '" maxlength="255" value="' + atributo(valor) + '"' + req + '>';
                break;
            case 'telefone':
                campo = '<input type="tel" class="form-control" id="' + id + '" name="' + nome + '" maxlength="30" value="' + atributo(valor) + '"' + req + '>';
                break;
            case 'lista':
                campo = '<select class="form-select" id="' + id + '" name="' + nome + '"' + req + '><option value="">Selecione…</option>' +
                    (c.opcoes || []).map(function (o) {
                        return '<option value="' + atributo(o) + '"' + (o === valor ? ' selected' : '') + '>' + escapar(o) + '</option>';
                    }).join('') + '</select>';
                break;
            case 'multipla':
                var marcados = Array.isArray(antes) ? antes : [];
                campo = '<div class="ct-cp-multipla" data-obrigatorio="' + (c.obrigatorio ? '1' : '') + '" data-rotulo="' + atributo(c.rotulo) + '">' +
                    (c.opcoes || []).map(function (o) {
                        return '<label class="form-check"><input class="form-check-input" type="checkbox" name="' + nome + '[]" value="' + atributo(o) + '"' +
                            (marcados.indexOf(o) >= 0 ? ' checked' : '') + '><span class="form-check-label">' + escapar(o) + '</span></label>';
                    }).join('') + '</div>';
                rotulo = '<label class="form-label' + (c.obrigatorio ? ' required' : '') + '">' + escapar(c.rotulo) + '</label>';
                break;
            case 'sim_nao':
                campo = ['sim', 'nao'].map(function (v) {
                    return '<label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="' + nome + '" value="' + v + '"' +
                        (valor === v ? ' checked' : '') + req + '><span class="form-check-label">' + (v === 'sim' ? 'Sim' : 'Não') + '</span></label>';
                }).join('');
                rotulo = '<label class="form-label d-block' + (c.obrigatorio ? ' required' : '') + '">' + escapar(c.rotulo) + '</label>';
                break;
            default:
                campo = '<input type="text" class="form-control" id="' + id + '" name="' + nome + '" maxlength="255" value="' + atributo(valor) + '"' + req + '>';
        }
        return '<div class="' + largura + '">' + rotulo + campo + ajuda + '</div>';
    }

    function desenharCampos(lista, ultimo) {
        var chave = ultimo + '|' + lista.map(function (c) { return c.id; }).join(',');
        if (chave !== chaveCampos) {
            chaveCampos = chave;
            contCampos.innerHTML = lista.map(htmlCampo).join('');
        }
        contCampos.classList.toggle('d-none', !lista.length);
    }

    // Categoria final, campos extras e etapas seguintes
    function atualizar() {
        var ultimo = selecao.length ? selecao[selecao.length - 1] : 0;
        var folha = ultimo && no(ultimo) && no(ultimo).filhos.length === 0;
        // Pedido feito em outra tela (ex.: reserva de sala): mostra o botão para ir até ela
        var link = folha ? (no(ultimo).link || '') : '';
        campoCategoria.value = folha && !link ? ultimo : '';

        escolha.classList.toggle('d-none', !folha);
        escolher.classList.toggle('d-none', !!folha);
        resto.classList.toggle('d-none', !folha || !!link);
        escolhaTexto.textContent = folha ? no(ultimo).rotulo.replace(/ > /g, ' › ') : '';
        document.getElementById('ct-link').classList.toggle('d-none', !link);
        document.getElementById('ct-link-botao').setAttribute('href', link || '#');

        var extras = folha && !link ? no(ultimo).extras : [];
        var algum = false;
        document.querySelectorAll('.ct-extra').forEach(function (bloco) {
            var mostrar = extras.indexOf(bloco.dataset.extra) >= 0;
            algum = algum || mostrar;
            bloco.classList.toggle('d-none', !mostrar);
            bloco.querySelectorAll('[data-req]').forEach(function (campo) {
                campo.required = mostrar;
            });
        });
        var proprios = folha && !link ? (no(ultimo).campos || []) : [];
        desenharCampos(proprios, ultimo);
        algum = algum || proprios.length > 0;
        blocoExtras.classList.toggle('d-none', !algum);
        if (typeof ajustarColaboradores === 'function') {
            ajustarColaboradores();
        }
        ajustarProcedimento();
        document.querySelectorAll('.ct-num').forEach(function (n, i) {
            n.textContent = String((algum ? 3 : 2) + i);
        });
        resumo.textContent = folha ? 'Pedido: ' + no(ultimo).rotulo : '';
    }

    function escolher_(lista) {
        selecao = lista;
        desenhar();
        var folha = campoCategoria.value !== '';
        if (folha) {
            escolha.scrollIntoView({ block: 'start', behavior: 'smooth' });
            var primeiro = resto.querySelector('.ct-extra:not(.d-none) input, .ct-extra:not(.d-none) textarea, #ct-campos-proprios input, #ct-campos-proprios select, #ct-campos-proprios textarea') ||
                document.getElementById('ct-descricao') || document.getElementById('ct-titulo');
            setTimeout(function () { if (primeiro) { primeiro.focus({ preventScroll: true }); } }, 300);
        } else {
            var ultimoNivel = niveis.lastElementChild;
            if (ultimoNivel) {
                ultimoNivel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
    }

    document.querySelectorAll('.ct-tipo').forEach(function (botao) {
        botao.addEventListener('click', function () {
            escolher_([Number(botao.dataset.id)]);
        });
    });

    niveis.addEventListener('click', function (ev) {
        var botao = ev.target.closest('.ct-opcao');
        if (!botao) {
            return;
        }
        var nivel = Number(botao.dataset.nivel);
        escolher_(selecao.slice(0, nivel).concat([Number(botao.dataset.id)]));
    });

    document.getElementById('ct-trocar').addEventListener('click', function () {
        // Volta para o nível de cima, mantendo o tipo escolhido
        selecao = selecao.slice(0, -1);
        desenhar();
        busca.value = '';
        escolher.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });

    // Busca em todas as opções finais (sem acento, sem maiúsculas)
    function normal(t) {
        return String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }
    var folhas = Object.keys(nos).filter(function (id) {
        return no(id).filhos.length === 0 && no(no(id).pai);
    });
    busca.addEventListener('input', function () {
        var termos = normal(busca.value).split(/\s+/).filter(Boolean);
        if (!termos.length) {
            resultados.classList.add('d-none');
            resultados.innerHTML = '';
            return;
        }
        var achados = folhas.filter(function (id) {
            var texto = normal(no(id).rotulo);
            return termos.every(function (t) { return texto.indexOf(t) >= 0; });
        }).slice(0, 8);
        resultados.innerHTML = achados.length
            ? achados.map(function (id) {
                return '<button type="button" class="list-group-item list-group-item-action" data-id="' + id + '"><i class="ti ti-arrow-right"></i> ' +
                    escapar(no(id).rotulo.replace(/ > /g, ' › ')) + '</button>';
            }).join('')
            : '<div class="list-group-item text-muted">Nada encontrado. Escolha pelo tipo abaixo.</div>';
        resultados.classList.remove('d-none');
    });
    resultados.addEventListener('click', function (ev) {
        var item = ev.target.closest('[data-id]');
        if (!item) {
            return;
        }
        resultados.classList.add('d-none');
        busca.value = '';
        escolher_(caminho(item.dataset.id));
    });
    busca.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            var primeiro = resultados.querySelector('[data-id]');
            if (primeiro) {
                primeiro.click();
            }
        }
    });

    // Prioridade sugerida por quem está afetado (até a pessoa escolher outra)
    form.querySelectorAll('input[name="urgencia"]').forEach(function (r) {
        r.addEventListener('change', function () { prioridadeManual = true; });
    });
    form.querySelectorAll('input[name="afetados"]').forEach(function (r) {
        r.addEventListener('change', function () {
            if (prioridadeManual) {
                return;
            }
            var alvo = form.querySelector('input[name="urgencia"][value="' + r.dataset.urgencia + '"]');
            if (alvo) {
                alvo.checked = true;
            }
        });
    });

    // Procedimento (FacPlan): inserção mostra e exige todos os campos; alteração só código e tabela
    function ajustarProcedimento() {
        var bloco = document.querySelector('.ct-extra[data-extra="procedimento"]');
        if (!bloco) {
            return;
        }
        var marcado = bloco.querySelector('[data-ct-proc-operacao]:checked');
        var insercao = !bloco.classList.contains('d-none') && marcado && marcado.value === 'insercao';
        var alteracao = !bloco.classList.contains('d-none') && marcado && marcado.value === 'alteracao';
        bloco.querySelector('[data-ct-proc-insercao]').classList.toggle('d-none', !insercao);
        bloco.querySelector('[data-ct-proc-alteracao]').classList.toggle('d-none', !alteracao);
        bloco.querySelectorAll('[data-req-ins]').forEach(function (campo) {
            campo.required = !!insercao;
        });
        // Perguntas Sim/Não com detalhe (DUT, limite, quantidade máxima, dependência):
        // o detalhe aparece e fica obrigatório só quando a resposta é "Sim"
        bloco.querySelectorAll('[data-ct-se-sim]').forEach(function (detalhe) {
            var resposta = bloco.querySelector('[name="' + detalhe.dataset.ctSeSim + '"]:checked');
            var mostrar = !!insercao && !!resposta && resposta.value === 'sim';
            detalhe.classList.toggle('d-none', !mostrar);
            detalhe.querySelectorAll('input, select').forEach(function (campo) {
                campo.required = mostrar;
            });
        });
    }
    document.querySelectorAll('[data-ct-proc-operacao], .ct-extra[data-extra="procedimento"] [data-req-ins][type="radio"]').forEach(function (r) {
        r.addEventListener('change', ajustarProcedimento);
    });

    // Colaboradores: adicionar e remover, numeração e "Setor anterior" só para remanejado
    var listaColab = document.getElementById('ct-colaboradores');
    var blocoColab = document.querySelector('.ct-extra[data-extra="colaboradores"]');

    function ajustarColaboradores() {
        listaColab.querySelectorAll('.ct-colaborador').forEach(function (card, i) {
            card.querySelector('.ct-colab-titulo').textContent = 'Colaborador ' + (i + 1);
        });
    }

    function limpar(card) {
        card.querySelectorAll('input').forEach(function (i) { i.value = ''; i.setCustomValidity(''); });
        card.querySelectorAll('select').forEach(function (s) { s.value = ''; });
        // Setor já vem com o setor de quem está abrindo
        var setor = card.querySelector('.ct-col-setor');
        if (setor && dados.meu_setor) {
            setor.value = String(dados.meu_setor);
        }
    }

    document.getElementById('ct-adicionar').addEventListener('click', function () {
        var cards = listaColab.querySelectorAll('.ct-colaborador');
        if (cards.length >= dados.max) {
            return;
        }
        var novo = cards[0].cloneNode(true);
        limpar(novo);
        listaColab.appendChild(novo);
        ajustarColaboradores();
        novo.querySelector('input').focus();
    });
    listaColab.addEventListener('click', function (ev) {
        var botao = ev.target.closest('.ct-remover');
        if (!botao) {
            return;
        }
        var card = botao.closest('.ct-colaborador');
        if (listaColab.querySelectorAll('.ct-colaborador').length > 1) {
            card.remove();
        } else {
            limpar(card);
        }
        ajustarColaboradores();
    });
    listaColab.addEventListener('change', function (ev) {
        // Trocou o setor: quem estava em "Copiar acesso de" pode não ser desse setor
        if (ev.target.classList.contains('ct-col-setor')) {
            var perfil = ev.target.closest('.ct-colaborador').querySelector('input[name="col_perfil[]"]');
            if (perfil.value) {
                validarCombo(perfil);
                if (!perfil.checkValidity()) {
                    perfil.value = '';
                    perfil.setCustomValidity('');
                }
            }
        }
    });
    listaColab.addEventListener('input', function (ev) {
        if (ev.target.name === 'col_cpf[]') {
            ev.target.value = ev.target.value.replace(/\D/g, '').slice(0, 6);
        }
    });

    // Lista de opções logo abaixo do campo (cargo e "Copiar acesso de"):
    // abre ao clicar, filtra ao digitar e só aceita o que está na lista.
    var listas = { cargos: dados.cargos || [], locais: dados.locais || [] };
    var combo = { lista: null, campo: null, marcado: -1 };

    // Opções do campo; "Copiar acesso de" = pessoas do setor escolhido no cartão
    function itensDe(campo) {
        if (campo.dataset.lista === 'pessoas_setor') {
            var setor = campo.closest('.ct-colaborador').querySelector('.ct-col-setor');
            return setor && setor.value ? ((dados.pessoas_setor || {})[setor.value] || []) : null;
        }
        return listas[campo.dataset.lista] || [];
    }

    function fecharCombo() {
        if (combo.lista) {
            combo.lista.remove();
        }
        combo.lista = null;
        combo.campo = null;
        combo.marcado = -1;
    }

    function atributo(t) {
        return escapar(t).replace(/"/g, '&quot;');
    }

    function abrirCombo(campo) {
        var itens = itensDe(campo);
        if (itens !== null && !itens.length && campo.dataset.lista !== 'pessoas_setor') {
            return;
        }
        var termo = normal(campo.value.trim());
        var achados = (itens || []).filter(function (i) { return !termo || normal(i).indexOf(termo) >= 0; });
        if (combo.lista && combo.campo !== campo) {
            fecharCombo();
        }
        if (!combo.lista) {
            combo.lista = document.createElement('div');
            combo.lista.className = 'list-group ct-combo-lista';
            campo.parentNode.classList.add('ct-combo-pai');
            campo.insertAdjacentElement('afterend', combo.lista);
            combo.lista.addEventListener('mousedown', function (ev) {
                var item = ev.target.closest('[data-valor]');
                ev.preventDefault();
                if (item) {
                    escolherCombo(item.getAttribute('data-valor'));
                }
            });
        }
        combo.campo = campo;
        combo.marcado = -1;
        // Logo abaixo do campo, com a mesma largura
        combo.lista.style.top = (campo.offsetTop + campo.offsetHeight) + 'px';
        combo.lista.style.left = campo.offsetLeft + 'px';
        combo.lista.style.width = campo.offsetWidth + 'px';
        combo.lista.innerHTML = achados.length
            ? achados.map(function (i) {
                return '<button type="button" tabindex="-1" class="list-group-item list-group-item-action" data-valor="' + atributo(i) + '">' + escapar(i) + '</button>';
            }).join('')
            : '<div class="list-group-item text-muted">' + (itens === null
                ? 'Escolha primeiro o setor para onde a pessoa vai.'
                : (itens.length ? 'Nada encontrado. Escolha uma opção da lista.' : 'Ninguém cadastrado neste setor.')) + '</div>';
    }

    function escolherCombo(valor) {
        var campo = combo.campo;
        if (!campo) {
            return;
        }
        campo.value = valor;
        campo.setCustomValidity('');
        fecharCombo();
        campo.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function validarCombo(campo) {
        var itens = itensDe(campo);
        var valor = campo.value.trim();
        if (!valor) {
            campo.setCustomValidity('');
            return;
        }
        if (itens === null) {
            campo.setCustomValidity('Escolha primeiro o setor.');
            return;
        }
        if (!itens.length && campo.dataset.lista !== 'pessoas_setor') {
            campo.setCustomValidity('');
            return;
        }
        var certo = itens.find(function (i) { return normal(i) === normal(valor); });
        if (certo) {
            campo.value = certo;
            campo.setCustomValidity('');
        } else {
            campo.setCustomValidity(campo.dataset.lista === 'pessoas_setor'
                ? 'Escolha alguém do setor na lista.'
                : 'Escolha uma opção da lista.');
        }
    }

    form.addEventListener('focusin', function (ev) {
        if (ev.target.classList.contains('ct-combo')) {
            abrirCombo(ev.target);
        }
    });
    form.addEventListener('click', function (ev) {
        if (ev.target.classList.contains('ct-combo') && !combo.lista) {
            abrirCombo(ev.target);
        }
    });
    form.addEventListener('input', function (ev) {
        if (ev.target.classList.contains('ct-combo')) {
            ev.target.setCustomValidity('');
            abrirCombo(ev.target);
        }
    });
    form.addEventListener('focusout', function (ev) {
        if (ev.target.classList.contains('ct-combo')) {
            validarCombo(ev.target);
            if (combo.campo === ev.target) {
                fecharCombo();
            }
        }
    });
    form.addEventListener('keydown', function (ev) {
        if (!combo.lista || ev.target !== combo.campo) {
            return;
        }
        var itens = combo.lista.querySelectorAll('[data-valor]');
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            if (!itens.length) {
                return;
            }
            combo.marcado = (combo.marcado + (ev.key === 'ArrowDown' ? 1 : -1) + itens.length) % itens.length;
            itens.forEach(function (b, i) { b.classList.toggle('active', i === combo.marcado); });
            itens[combo.marcado].scrollIntoView({ block: 'nearest' });
        } else if (ev.key === 'Enter') {
            ev.preventDefault();
            var escolhido = itens[combo.marcado >= 0 ? combo.marcado : 0];
            if (escolhido) {
                escolherCombo(escolhido.getAttribute('data-valor'));
            }
        } else if (ev.key === 'Escape') {
            fecharCombo();
        }
    });

    function avisar(campo, msg) {
        campo.setCustomValidity(msg);
        campo.reportValidity();
        campo.setCustomValidity('');
    }

    form.addEventListener('submit', function (ev) {
        ajustarProcedimento();
        var comboErrado = null;
        form.querySelectorAll('.ct-combo').forEach(function (c) {
            if (c.offsetParent !== null) {
                validarCombo(c);
                if (!comboErrado && !c.checkValidity()) {
                    comboErrado = c;
                }
            }
        });
        if (comboErrado) {
            ev.preventDefault();
            comboErrado.reportValidity();
            return;
        }
        if (!campoCategoria.value) {
            ev.preventDefault();
            escolher.scrollIntoView({ block: 'start' });
            busca.focus();
            return;
        }
        // Grupos de caixas de marcar obrigatórios (pelo menos uma)
        var marcarUm = { equipamento: 'Marque qual equipamento.', evento_interno: 'Marque onde será o evento.' };
        for (var chave in marcarUm) {
            var bloco = document.querySelector('.ct-extra[data-extra="' + chave + '"]');
            if (bloco && !bloco.classList.contains('d-none') && !bloco.querySelector('input:checked')) {
                ev.preventDefault();
                avisar(bloco.querySelector('input[type="checkbox"]'), marcarUm[chave]);
                return;
            }
        }
        // Campos próprios de várias opções obrigatórios: pelo menos uma marcada
        var grupos = form.querySelectorAll('.ct-cp-multipla[data-obrigatorio="1"]');
        for (var g = 0; g < grupos.length; g++) {
            if (!grupos[g].querySelector('input:checked')) {
                ev.preventDefault();
                avisar(grupos[g].querySelector('input[type="checkbox"]'), 'Marque pelo menos uma opção em "' + grupos[g].dataset.rotulo + '".');
                return;
            }
        }
        var anexos = document.getElementById('ct-anexos');
        if (anexos && anexos.files.length > dados.max_anexos) {
            ev.preventDefault();
            avisar(anexos, 'Envie no máximo ' + dados.max_anexos + ' arquivos.');
            return;
        }
        for (var i = 0; anexos && i < anexos.files.length; i++) {
            if (anexos.files[i].size > dados.max_mb * 1024 * 1024) {
                ev.preventDefault();
                avisar(anexos, 'O arquivo "' + anexos.files[i].name + '" passa de ' + dados.max_mb + ' MB.');
                return;
            }
        }
        var botao = form.querySelector('button[type="submit"]');
        setTimeout(function () { botao.disabled = true; botao.querySelector('span').textContent = 'Enviando…'; }, 0);
    });

    // Volta preenchido depois de um erro no envio
    if (campoCategoria.value && no(campoCategoria.value)) {
        selecao = caminho(campoCategoria.value);
        desenhar();
    } else {
        atualizar();
    }
})();
