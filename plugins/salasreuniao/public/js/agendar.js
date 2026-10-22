(function () {
    'use strict';

    var form = document.getElementById('salas-form');
    if (!form) {
        return;
    }

    var cfg       = JSON.parse(form.dataset.config);
    var saida     = document.getElementById('salas-resultado');
    var resumo    = document.getElementById('salas-resumo');
    var confirmar = document.getElementById('salas-confirmar');
    var inSala    = form.querySelector('input[name="rooms_id"]');
    var inHora    = form.querySelector('input[name="hora"]');
    var consulta  = 0;
    var espera    = null;

    function el(tag, classe, texto) {
        var e = document.createElement(tag);
        if (classe) {
            e.className = classe;
        }
        if (texto !== undefined) {
            e.textContent = texto;
        }
        return e;
    }

    function aviso(tipo, texto) {
        saida.replaceChildren(el('div', 'alert alert-' + tipo + ' mb-0', texto));
    }

    function limparEscolha() {
        inSala.value = '';
        inHora.value = '';
        confirmar.disabled = true;
        resumo.textContent = 'Escolha um horário acima.';
    }

    function somarMinutos(hora, minutos) {
        var p = hora.split(':');
        var total = parseInt(p[0], 10) * 60 + parseInt(p[1], 10) + minutos;
        return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
    }

    function dataBr(iso) {
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function escolher(botao, sala, hora) {
        saida.querySelectorAll('.salas-horario.active').forEach(function (b) {
            b.classList.remove('active', 'btn-primary');
            b.classList.add('btn-outline-primary');
            b.setAttribute('aria-pressed', 'false');
        });
        botao.classList.remove('btn-outline-primary');
        botao.classList.add('active', 'btn-primary');
        botao.setAttribute('aria-pressed', 'true');

        var duracao = parseInt(form.duracao.value, 10);
        inSala.value = sala.id;
        inHora.value = hora;
        resumo.textContent = sala.nome + ' — ' + dataBr(form.data.value) + ', das ' + hora + ' às ' + somarMinutos(hora, duracao);
        confirmar.disabled = false;
    }

    function desenhar(resposta) {
        if (!resposta.ok) {
            aviso('warning', resposta.erro || 'Não foi possível consultar os horários.');
            return;
        }
        if (!resposta.salas.length) {
            aviso('warning', 'Nenhuma sala comporta ' + form.pessoas.value + ' pessoas.');
            return;
        }

        var lista = el('div', 'd-flex flex-column gap-3');
        var algum = false;
        var comErro = resposta.salas.filter(function (s) { return s.erro; }).length;
        resposta.salas.forEach(function (sala) {
            var bloco = el('div', 'border rounded p-3');
            var topo  = el('div', 'd-flex flex-wrap align-items-baseline gap-2 mb-2');
            topo.append(el('strong', '', sala.nome));
            var detalhes = [];
            if (sala.capacidade > 0) {
                detalhes.push('até ' + sala.capacidade + ' pessoas');
            }
            if (sala.local) {
                detalhes.push(sala.local);
            }
            if (detalhes.length) {
                topo.append(el('span', 'text-muted small', detalhes.join(' · ')));
            }
            bloco.append(topo);

            if (sala.erro) {
                bloco.append(el('div', 'text-danger small', sala.erro));
            } else if (!sala.horarios.length) {
                bloco.append(el('div', 'text-muted small', 'Sem horários livres neste dia para essa duração.'));
            } else {
                algum = true;
                var horarios = el('div', 'd-flex flex-wrap gap-2');
                sala.horarios.forEach(function (hora) {
                    var b = el('button', 'btn btn-outline-primary btn-sm salas-horario', hora);
                    b.type = 'button';
                    b.setAttribute('aria-pressed', 'false');
                    b.setAttribute('aria-label', sala.nome + ' às ' + hora);
                    b.addEventListener('click', function () {
                        escolher(b, sala, hora);
                    });
                    if (cfg.sala === sala.id && cfg.hora === hora) {
                        b.dataset.preselecionar = '1';
                    }
                    horarios.append(b);
                });
                bloco.append(horarios);
            }
            lista.append(bloco);
        });

        saida.replaceChildren(lista);
        if (form.data.value === cfg.hoje) {
            var agora = new Date();
            var hhmm = String(agora.getHours()).padStart(2, '0') + ':' + String(agora.getMinutes()).padStart(2, '0');
            saida.prepend(el('div', 'alert alert-secondary py-2',
                'Hoje só aparecem os horários a partir de agora (' + hhmm + '). As salas ficam disponíveis até as ' +
                cfg.fecha + ', então a reunião precisa terminar até lá. Para ver o dia todo, escolha outra data.'));
        }
        if (!algum && comErro === resposta.salas.length) {
            saida.prepend(el('div', 'alert alert-danger', 'Não foi possível consultar a agenda das salas no Google. Avise a equipe de TI.'));
        } else if (!algum) {
            saida.prepend(el('div', 'alert alert-info', 'Nenhuma sala tem horário livre nesse dia para essa duração. Tente outro dia ou uma duração menor.'));
        }

        // Remarcação: já deixa marcado o horário atual, se ele ainda estiver livre
        var atual = saida.querySelector('[data-preselecionar="1"]');
        if (atual) {
            atual.click();
            cfg.sala = 0;
        }
    }

    function buscar() {
        limparEscolha();
        var pessoas = parseInt(form.pessoas.value, 10);
        if (!form.data.value || !form.duracao.value || !(pessoas > 0)) {
            saida.replaceChildren(el('div', 'text-muted', 'Preencha o dia, a duração e a quantidade de pessoas para ver os horários livres.'));
            return;
        }

        var minha = ++consulta;
        var carregando = el('div', 'text-muted');
        carregando.append(el('span', 'spinner-border spinner-border-sm me-2'), document.createTextNode('Consultando a agenda das salas…'));
        saida.replaceChildren(carregando);

        var qs = new URLSearchParams({
            data: form.data.value,
            duracao: form.duracao.value,
            pessoas: String(pessoas),
            remarcar: String(cfg.remarcar || 0)
        });
        fetch(cfg.ajax + '?' + qs.toString(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (minha === consulta) {
                    desenhar(j);
                }
            })
            .catch(function () {
                if (minha === consulta) {
                    aviso('danger', 'Não foi possível consultar as salas. Tente novamente.');
                }
            });
    }

    function agendarBusca() {
        window.clearTimeout(espera);
        espera = window.setTimeout(buscar, 350);
    }

    ['data', 'duracao', 'pessoas'].forEach(function (nome) {
        form[nome].addEventListener('change', agendarBusca);
        form[nome].addEventListener('input', agendarBusca);
    });

    form.addEventListener('submit', function (ev) {
        if (!inSala.value || !inHora.value) {
            ev.preventDefault();
            resumo.textContent = 'Escolha uma sala e um horário antes de confirmar.';
        }
    });

    if (form.data.value) {
        buscar();
    }
})();
