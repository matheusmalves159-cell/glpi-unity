<?php

namespace GlpiPlugin\Relatorios;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Planilha completa do relatório (uma aba por visão + lista de auditoria).
 */
class Excel
{
    private const AMARELO = 'FFFDC300';
    private const AZUL    = 'FF1F2A44';

    /** Colunas das abas de ranking */
    private const RANKING = [
        'Total', 'Em aberto', 'Solucionados', 'Abertos atrasados', 'SLA resposta (%)', 'SLA solução (%)',
        'Média 1ª resposta (h úteis)', 'Média solução (h úteis)', 'Maior solução (h úteis)', 'Satisfação (1-5)',
    ];

    public static function gerar(array $chamados, array $analise, array $filtros, string $usuario): string
    {
        $planilha = new Spreadsheet();
        $planilha->getProperties()->setCreator($usuario)->setTitle('Relatório de chamados');

        self::resumo($planilha->getActiveSheet(), $analise['kpis'], $filtros, $usuario, count($chamados));
        self::chamados($planilha->createSheet(), $chamados);
        self::ranking($planilha->createSheet(), 'Técnicos', 'Técnico', $analise['tecnicos']);
        self::ranking($planilha->createSheet(), 'Grupos atribuídos', 'Grupo', $analise['grupos']);
        self::ranking($planilha->createSheet(), 'Setores', 'Setor (requerente)', $analise['setores']);
        self::requerentes($planilha->createSheet(), $analise['requerentes']);
        self::ranking($planilha->createSheet(), 'Categorias', 'Categoria', $analise['categorias']);
        self::ranking($planilha->createSheet(), 'Prioridades', 'Prioridade', $analise['prioridades']);
        self::atrasados($planilha->createSheet(), $chamados);
        self::evolucao($planilha->createSheet(), $analise['evolucao']);
        $planilha->setActiveSheetIndex(0);

        $arquivo = tempnam(GLPI_TMP_DIR, 'relatorio_') . '.xlsx';
        (new Xlsx($planilha))->save($arquivo);
        return $arquivo;
    }

    private static function resumo(Worksheet $aba, array $k, array $filtros, string $usuario, int $total): void
    {
        $aba->setTitle('Resumo');
        $aba->setCellValue('A1', 'Relatório de chamados');
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB(self::AZUL);
        $aba->setCellValue('A2', sprintf('Gerado em %s por %s', date('d/m/Y H:i'), $usuario));
        $aba->getStyle('A2')->getFont()->setItalic(true);

        $linha = 4;
        $aba->setCellValue("A{$linha}", 'Filtros');
        self::cabecalho($aba, "A{$linha}:B{$linha}");
        foreach ($filtros as $nome => $valor) {
            $linha++;
            $aba->setCellValue("A{$linha}", $nome);
            $aba->setCellValue("B{$linha}", $valor);
        }

        $linha += 2;
        $aba->setCellValue("A{$linha}", 'Indicador');
        $aba->setCellValue("B{$linha}", 'Valor');
        self::cabecalho($aba, "A{$linha}:B{$linha}");
        $inicio = $linha + 1;
        $itens = [
            ['Chamados no período', $total, '0'],
            ['Em aberto', $k['abertos'], '0'],
            ['Solucionados/fechados', $k['solucionados'], '0'],
            ['Em aberto e com SLA estourado', $k['abertos_atrasados'], '0'],
            ['SLA de resposta cumprido', $k['sla_resposta'] !== null ? $k['sla_resposta'] / 100 : '—', '0.0%'],
            ['SLA de solução cumprido', $k['sla_solucao'] !== null ? $k['sla_solucao'] / 100 : '—', '0.0%'],
            ['Respostas no prazo / atrasadas', $k['tto_ok'] . ' / ' . $k['tto_atraso'], '@'],
            ['Soluções no prazo / atrasadas', $k['ttr_ok'] . ' / ' . $k['ttr_atraso'], '@'],
            ['Tempo médio de 1ª resposta (horário comercial)', Dados::duracao($k['media_resposta']), '@'],
            ['Tempo médio de solução (horário comercial)', Dados::duracao($k['media_solucao']), '@'],
            ['Maior tempo de solução (horário comercial)', Dados::duracao($k['sol_max'] ?: null), '@'],
            ['Satisfação média (1 a 5)', $k['satisfacao'] ?? '—', '0.0'],
        ];
        foreach ($itens as $i => [$nome, $valor, $formato]) {
            $l = $inicio + $i;
            $aba->setCellValue("A{$l}", $nome);
            $aba->setCellValue("B{$l}", $valor);
            $aba->getStyle("B{$l}")->getNumberFormat()->setFormatCode($formato);
            $aba->getStyle("B{$l}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        self::bordas($aba, 'A' . ($inicio - 1) . ':B' . ($inicio + count($itens) - 1));
        $aba->getColumnDimension('A')->setWidth(48);
        $aba->getColumnDimension('B')->setWidth(42);
    }

    private static function chamados(Worksheet $aba, array $chamados): void
    {
        $aba->setTitle('Chamados (auditoria)');
        $colunas = [
            'Nº', 'Abertura', 'Título', 'Tipo', 'Categoria', 'Origem', 'Requerente', 'Setor', 'Técnico(s)', 'Grupo atribuído',
            'Urgência', 'Prioridade', 'Situação', '1ª resposta', 'Solução', 'Fechamento', 'Prazo de resposta', 'Prazo de solução',
            'SLA resposta', 'SLA solução', 'Tempo 1ª resposta (h úteis)', 'Tempo solução (h úteis)', 'Satisfação',
        ];
        $aba->fromArray($colunas, null, 'A1');
        $linhas = [];
        foreach ($chamados as $c) {
            $linhas[] = [
                $c['id'], Dados::data($c['abertura']), $c['titulo'], $c['tipo'], $c['categoria'], $c['origem'], $c['requerente'],
                $c['setor'], implode(', ', $c['tecnicos']), implode(', ', $c['grupos']), $c['urgencia'], $c['prioridade'], $c['status'],
                Dados::data($c['primeira_resposta']), Dados::data($c['solucao']), Dados::data($c['fechamento']),
                Dados::data($c['prazo_resposta']), Dados::data($c['prazo_solucao']),
                Dados::SLA_ROTULOS[$c['sla_resposta']], Dados::SLA_ROTULOS[$c['sla_solucao']],
                $c['tempo_resposta'] !== null ? round($c['tempo_resposta'] / 3600, 2) : '',
                $c['tempo_solucao'] !== null ? round($c['tempo_solucao'] / 3600, 2) : '',
                $c['satisfacao'] ?? '',
            ];
        }
        if ($linhas !== []) {
            $aba->fromArray($linhas, null, 'A2', true);
        }
        $ultima = count($linhas) + 1;
        self::tabela($aba, count($colunas), $ultima);
        $aba->getStyle("U2:V{$ultima}")->getNumberFormat()->setFormatCode('0.00');

        // Destaque dos SLAs estourados
        foreach ($chamados as $i => $c) {
            $l = $i + 2;
            foreach (['S' => $c['sla_resposta'], 'T' => $c['sla_solucao']] as $col => $sla) {
                if ($sla === 'atrasado') {
                    $aba->getStyle("{$col}{$l}")->getFont()->setBold(true)->getColor()->setARGB('FFC2410C');
                } elseif ($sla === 'no_prazo') {
                    $aba->getStyle("{$col}{$l}")->getFont()->getColor()->setARGB('FF1E8449');
                }
            }
        }
        $aba->getColumnDimension('C')->setAutoSize(false)->setWidth(45);
    }

    private static function ranking(Worksheet $aba, string $titulo, string $rotulo, array $lista): void
    {
        $aba->setTitle(mb_substr($titulo, 0, 31));
        $aba->fromArray(array_merge([$rotulo], self::RANKING), null, 'A1');
        $linhas = [];
        foreach ($lista as $g) {
            $linhas[] = array_merge([$g['nome']], self::valores($g));
        }
        if ($linhas !== []) {
            $aba->fromArray($linhas, null, 'A2', true);
        }
        $ultima = count($linhas) + 1;
        self::tabela($aba, count(self::RANKING) + 1, $ultima);
        $aba->getStyle("F2:G{$ultima}")->getNumberFormat()->setFormatCode('0.0%');
        $aba->getStyle("H2:J{$ultima}")->getNumberFormat()->setFormatCode('0.00');
        $aba->getStyle("K2:K{$ultima}")->getNumberFormat()->setFormatCode('0.0');
    }

    private static function requerentes(Worksheet $aba, array $lista): void
    {
        $aba->setTitle('Requerentes');
        $aba->fromArray(array_merge(['Requerente', 'Setor'], self::RANKING), null, 'A1');
        $linhas = [];
        foreach ($lista as $g) {
            [$nome, $setor] = array_pad(explode("\t", $g['nome']), 2, '');
            $linhas[] = array_merge([$nome, $setor], self::valores($g));
        }
        if ($linhas !== []) {
            $aba->fromArray($linhas, null, 'A2', true);
        }
        $ultima = count($linhas) + 1;
        self::tabela($aba, count(self::RANKING) + 2, $ultima);
        $aba->getStyle("G2:H{$ultima}")->getNumberFormat()->setFormatCode('0.0%');
        $aba->getStyle("I2:K{$ultima}")->getNumberFormat()->setFormatCode('0.00');
    }

    private static function atrasados(Worksheet $aba, array $chamados): void
    {
        $aba->setTitle('SLA estourado');
        $colunas = ['Nº', 'Abertura', 'Título', 'Categoria', 'Requerente', 'Setor', 'Técnico(s)', 'Prioridade', 'Situação', 'SLA resposta', 'Prazo de resposta', 'SLA solução', 'Prazo de solução', 'Solução'];
        $aba->fromArray($colunas, null, 'A1');
        $linhas = [];
        foreach ($chamados as $c) {
            if ($c['sla_resposta'] !== 'atrasado' && $c['sla_solucao'] !== 'atrasado') {
                continue;
            }
            $linhas[] = [
                $c['id'], Dados::data($c['abertura']), $c['titulo'], $c['categoria'], $c['requerente'], $c['setor'],
                implode(', ', $c['tecnicos']), $c['prioridade'], $c['status'], Dados::SLA_ROTULOS[$c['sla_resposta']],
                Dados::data($c['prazo_resposta']), Dados::SLA_ROTULOS[$c['sla_solucao']], Dados::data($c['prazo_solucao']), Dados::data($c['solucao']),
            ];
        }
        if ($linhas !== []) {
            $aba->fromArray($linhas, null, 'A2', true);
        }
        self::tabela($aba, count($colunas), count($linhas) + 1);
        $aba->getColumnDimension('C')->setAutoSize(false)->setWidth(45);
    }

    private static function evolucao(Worksheet $aba, array $e): void
    {
        $aba->setTitle('Evolução');
        $aba->fromArray([['dia' => 'Dia', 'semana' => 'Semana (início)', 'mes' => 'Mês'][$e['passo']], 'Abertos', 'Solucionados'], null, 'A1');
        $linhas = [];
        foreach ($e['rotulos'] as $i => $r) {
            $linhas[] = [$r, $e['abertos'][$i], $e['solucionados'][$i]];
        }
        if ($linhas !== []) {
            $aba->fromArray($linhas, null, 'A2', true);
        }
        self::tabela($aba, 3, count($linhas) + 1);
    }

    private static function valores(array $g): array
    {
        return [
            $g['total'], $g['abertos'], $g['solucionados'], $g['abertos_atrasados'],
            $g['sla_resposta'] !== null ? $g['sla_resposta'] / 100 : '',
            $g['sla_solucao'] !== null ? $g['sla_solucao'] / 100 : '',
            $g['media_resposta'] !== null ? round($g['media_resposta'] / 3600, 2) : '',
            $g['media_solucao'] !== null ? round($g['media_solucao'] / 3600, 2) : '',
            $g['sol_max'] ? round($g['sol_max'] / 3600, 2) : '',
            $g['satisfacao'] ?? '',
        ];
    }

    /**
     * Cabeçalho colorido, filtros, congelar primeira linha, bordas e largura automática.
     */
    private static function tabela(Worksheet $aba, int $colunas, int $ultima): void
    {
        $fim = Coordinate::stringFromColumnIndex($colunas);
        self::cabecalho($aba, "A1:{$fim}1");
        $aba->freezePane('A2');
        $aba->setAutoFilter("A1:{$fim}" . max(1, $ultima));
        self::bordas($aba, "A1:{$fim}" . max(1, $ultima));
        for ($i = 1; $i <= $colunas; $i++) {
            $aba->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $aba->getRowDimension(1)->setRowHeight(30);
    }

    private static function cabecalho(Worksheet $aba, string $faixa): void
    {
        $estilo = $aba->getStyle($faixa);
        $estilo->getFont()->setBold(true)->getColor()->setARGB(self::AZUL);
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::AMARELO);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    private static function bordas(Worksheet $aba, string $faixa): void
    {
        $aba->getStyle($faixa)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD0D4DA');
    }
}
