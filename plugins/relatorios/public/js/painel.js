// Relatórios de chamados: gráficos (ECharts), atalhos de período e
// envio dos gráficos como imagem para o PDF.
(function () {
    'use strict';

    var dados = JSON.parse(document.getElementById('rl-dados').textContent);
    // Tema escuro do GLPI: gráficos escuros na tela; o PDF (página branca) sempre usa o claro
    var TELA_ESCURA = document.documentElement.getAttribute('data-glpi-theme-dark') === '1';
    var AZUL, FUNDO, BASE_CALOR, CORES;
    var AMARELO = '#fdc300';
    var VERDE = '#2fb344';
    var VERMELHO = '#e8590c'; // sem vermelho no GLPI: atrasos em laranja-escuro
    var graficos = {};

    function definirCores(escuro) {
        AZUL = escuro ? '#6d9eeb' : '#1f2a44';
        FUNDO = escuro ? '#232e3c' : '#ffffff';
        BASE_CALOR = escuro ? '#2c3a4b' : '#f4f6fa';
        CORES = [AZUL, AMARELO, '#4299e1', VERDE, '#ff922b', '#ae3ec9', '#17a2b8', VERMELHO, '#9aa0ac', '#74b816'];
    }
    definirCores(TELA_ESCURA);

    function criarGrafico(el, nome, escuro) {
        definirCores(escuro);
        var g = echarts.init(el, escuro ? 'dark' : null, { renderer: 'canvas' });
        g.setOption(Object.assign({ animation: !el.dataset.offscreen, backgroundColor: 'transparent', textStyle: { fontFamily: 'inherit' } }, opcoes[nome]()));
        definirCores(TELA_ESCURA);
        return g;
    }

    // Versão clara do gráfico, só para a imagem do PDF
    function imagemClara(nome, origem) {
        var div = document.createElement('div');
        div.dataset.offscreen = '1';
        div.style.cssText = 'position:fixed;left:-10000px;top:0;width:' + origem.getWidth() + 'px;height:' + origem.getHeight() + 'px';
        document.body.appendChild(div);
        var g = criarGrafico(div, nome, false);
        var url = g.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#ffffff' });
        g.dispose();
        div.remove();
        return url;
    }

    function eixoCategorias(nomes) {
        return {
            type: 'category',
            data: nomes,
            inverse: true,
            axisLabel: { width: 170, overflow: 'truncate' }
        };
    }

    function barrasHorizontais(lista, cor) {
        return {
            color: [cor],
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
            grid: { left: 10, right: 40, top: 10, bottom: 10, containLabel: true },
            xAxis: { type: 'value', minInterval: 1 },
            yAxis: eixoCategorias(lista.map(function (i) { return i.nome; })),
            series: [{ type: 'bar', data: lista.map(function (i) { return i.total; }), label: { show: true, position: 'right' }, barMaxWidth: 22 }]
        };
    }

    function rosca(pares, cores) {
        return {
            color: cores || CORES,
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            legend: { bottom: 0, type: 'scroll' },
            series: [{
                type: 'pie',
                radius: ['45%', '72%'],
                center: ['50%', '45%'],
                avoidLabelOverlap: true,
                itemStyle: { borderColor: FUNDO, borderWidth: 2 },
                label: { formatter: '{c}' },
                data: pares
            }]
        };
    }

    function slaRosca(par) {
        var total = par[0] + par[1];
        var opcao = rosca([
            { name: 'No prazo', value: par[0] },
            { name: 'Atrasado', value: par[1] }
        ], [VERDE, VERMELHO]);
        opcao.title = {
            text: total ? (Math.round(1000 * par[0] / total) / 10).toLocaleString('pt-BR') + '%' : '—',
            subtext: total ? 'cumprido' : 'sem SLA medido',
            left: 'center',
            top: '34%',
            textStyle: { fontSize: 22, color: AZUL }
        };
        return opcao;
    }

    var opcoes = {
        evolucao: function () {
            var e = dados.evolucao;
            return {
                color: [AZUL, AMARELO],
                tooltip: { trigger: 'axis' },
                legend: { top: 0 },
                grid: { left: 10, right: 20, top: 35, bottom: 10, containLabel: true },
                xAxis: { type: 'category', data: e.rotulos, boundaryGap: false },
                yAxis: { type: 'value', minInterval: 1 },
                series: [
                    { name: 'Abertos', type: 'line', smooth: true, areaStyle: { opacity: 0.12 }, data: e.abertos },
                    { name: 'Solucionados', type: 'line', smooth: true, areaStyle: { opacity: 0.12 }, data: e.solucionados }
                ]
            };
        },
        sla_tto: function () { return slaRosca(dados.sla.tto); },
        sla_ttr: function () { return slaRosca(dados.sla.ttr); },
        status: function () {
            return rosca(Object.keys(dados.status).map(function (k) { return { name: k, value: dados.status[k] }; }));
        },
        prioridades: function () {
            var p = dados.prioridades.filter(function (i) { return i.total > 0; });
            return {
                color: [VERDE, VERMELHO, '#9aa0ac'],
                tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
                legend: { top: 0 },
                grid: { left: 10, right: 20, top: 35, bottom: 10, containLabel: true },
                xAxis: { type: 'category', data: p.map(function (i) { return i.nome; }) },
                yAxis: { type: 'value', minInterval: 1 },
                series: [
                    { name: 'No prazo', type: 'bar', stack: 's', data: p.map(function (i) { return i.ttr_ok; }) },
                    { name: 'Atrasado', type: 'bar', stack: 's', data: p.map(function (i) { return i.ttr_atraso; }) },
                    { name: 'Em andamento/sem SLA', type: 'bar', stack: 's', data: p.map(function (i) { return i.total - i.ttr_ok - i.ttr_atraso; }) }
                ]
            };
        },
        tecnicos: function () {
            var t = dados.tecnicos;
            return {
                color: [AZUL, AMARELO],
                tooltip: {
                    trigger: 'axis',
                    formatter: function (ps) {
                        var i = t[ps[0].dataIndex];
                        return '<b>' + i.nome + '</b><br>Chamados: ' + i.total +
                            '<br>Média de solução: ' + (i.solucao === null ? '—' : i.solucao.toLocaleString('pt-BR') + ' h úteis') +
                            '<br>SLA de solução: ' + (i.sla === null ? '—' : i.sla.toLocaleString('pt-BR') + '%');
                    }
                },
                legend: { top: 0 },
                grid: { left: 10, right: 10, top: 35, bottom: 10, containLabel: true },
                xAxis: { type: 'category', data: t.map(function (i) { return i.nome; }), axisLabel: { rotate: 30, width: 90, overflow: 'truncate' } },
                yAxis: [
                    { type: 'value', name: 'Chamados', minInterval: 1 },
                    { type: 'value', name: 'Horas úteis', splitLine: { show: false } }
                ],
                series: [
                    { name: 'Chamados atendidos', type: 'bar', data: t.map(function (i) { return i.total; }), barMaxWidth: 30, label: { show: true, position: 'top' } },
                    { name: 'Média de solução (h úteis)', type: 'line', yAxisIndex: 1, data: t.map(function (i) { return i.solucao; }), symbolSize: 8 }
                ]
            };
        },
        setores: function () { return barrasHorizontais(dados.setores, AZUL); },
        requerentes: function () { return barrasHorizontais(dados.requerentes, '#4299e1'); },
        categorias: function () { return barrasHorizontais(dados.categorias, AMARELO); },
        calor: function () {
            var valores = [];
            var max = 1;
            Object.keys(dados.calor).forEach(function (k) {
                var p = k.split('-');
                valores.push([Number(p[1]), Number(p[0]), dados.calor[k]]);
                max = Math.max(max, dados.calor[k]);
            });
            var horas = [];
            for (var h = 0; h < 24; h++) { horas.push(h + 'h'); }
            return {
                tooltip: {
                    formatter: function (p) { return dados.dias[p.value[1]] + ' ' + p.value[0] + 'h: ' + p.value[2] + ' chamado(s)'; }
                },
                grid: { left: 10, right: 10, top: 10, bottom: 50, containLabel: true },
                xAxis: { type: 'category', data: horas, splitArea: { show: true } },
                yAxis: { type: 'category', data: dados.dias, splitArea: { show: true } },
                visualMap: { min: 0, max: max, calculable: true, orient: 'horizontal', left: 'center', bottom: 0, inRange: { color: [BASE_CALOR, AMARELO, '#ff922b', VERMELHO] } },
                series: [{ type: 'heatmap', data: valores, label: { show: false } }]
            };
        }
    };

    if (typeof echarts !== 'undefined') {
        document.querySelectorAll('.rl-grafico').forEach(function (el) {
            var nome = el.dataset.grafico;
            if (!opcoes[nome]) {
                return;
            }
            graficos[nome] = { grafico: criarGrafico(el, nome, TELA_ESCURA), titulo: el.dataset.titulo || nome };
        });
        window.addEventListener('resize', function () {
            Object.keys(graficos).forEach(function (n) { graficos[n].grafico.resize(); });
        });
        // Gráficos em abas/cartões escondidos: ajusta quando aparecem
        document.addEventListener('shown.bs.tab', function () {
            Object.keys(graficos).forEach(function (n) { graficos[n].grafico.resize(); });
        });
    }

    // Atalhos de período
    function iso(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    document.querySelectorAll('#rl-atalhos [data-periodo]').forEach(function (b) {
        b.addEventListener('click', function () {
            var hoje = new Date();
            var de = new Date(hoje);
            var ate = new Date(hoje);
            switch (b.dataset.periodo) {
                case '7': de.setDate(hoje.getDate() - 6); break;
                case '30': de.setDate(hoje.getDate() - 29); break;
                case 'mes': de = new Date(hoje.getFullYear(), hoje.getMonth(), 1); break;
                case 'mes_passado':
                    de = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
                    ate = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
                    break;
                case 'tri': de = new Date(hoje.getFullYear(), hoje.getMonth() - 2, 1); break;
                case 'ano': de = new Date(hoje.getFullYear(), 0, 1); break;
                case 'ano_passado':
                    de = new Date(hoje.getFullYear() - 1, 0, 1);
                    ate = new Date(hoje.getFullYear() - 1, 11, 31);
                    break;
            }
            document.getElementById('rl-de').value = iso(de);
            document.getElementById('rl-ate').value = iso(ate);
            document.getElementById('rl-filtros').submit();
        });
    });

    // Exportação: escolhe o formato e, no PDF, manda os gráficos como imagem
    var exportar = document.getElementById('rl-exportar');
    var botaoClicado = null;
    exportar.querySelectorAll('button[data-formato]').forEach(function (b) {
        b.addEventListener('click', function () { botaoClicado = b; });
    });
    exportar.addEventListener('submit', function () {
        var formato = botaoClicado ? botaoClicado.dataset.formato : 'xlsx';
        exportar.querySelector('input[name="formato"]').value = formato;
        var caixa = document.getElementById('rl-imagens');
        caixa.innerHTML = '';
        if (formato === 'pdf') {
            Object.keys(graficos).forEach(function (n) {
                var campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = 'graficos[' + graficos[n].titulo + ']';
                campo.value = TELA_ESCURA
                    ? imagemClara(n, graficos[n].grafico)
                    : graficos[n].grafico.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#ffffff' });
                caixa.appendChild(campo);
            });
        }
        // Download não sai da página: reabilita os botões depois de alguns segundos
        exportar.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        setTimeout(function () { exportar.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); }, 5000);
    });
})();
