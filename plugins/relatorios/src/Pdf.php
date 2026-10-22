<?php

namespace GlpiPlugin\Relatorios;

use TCPDF;

/**
 * PDF do relatório: indicadores, gráficos (imagens geradas no navegador),
 * rankings, SLA estourado e a lista de chamados para auditoria.
 */
class Pdf extends TCPDF
{
    /** Limite de linhas da lista detalhada no PDF (a lista completa está no Excel) */
    public const MAX_LINHAS = 3000;

    private string $rodape = '';

    public function Header()
    {
        $this->SetFillColor(31, 42, 68);
        $this->Rect(0, 0, $this->getPageWidth(), 12, 'F');
        $this->SetFillColor(253, 195, 0);
        $this->Rect(0, 12, $this->getPageWidth(), 1.2, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY(10, 3);
        $this->Cell(0, 6, 'Relatório de chamados', 0, 0, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->SetXY(10, 3);
        $this->Cell(0, 6, $this->title, 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(0, 5, $this->rodape, 0, 0, 'L');
        $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    /**
     * @param array<string, string> $graficos título => data URL PNG
     */
    public static function gerar(array $chamados, array $analise, array $filtros, string $usuario, array $graficos): string
    {
        $pdf = new self('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle($filtros['Período'] ?? 'Relatório de chamados');
        $pdf->SetCreator('GLPI');
        $pdf->SetAuthor($usuario);
        $pdf->rodape = sprintf('Gerado em %s por %s', date('d/m/Y H:i'), $usuario);
        $pdf->SetMargins(10, 18, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->AddPage();

        $k = $analise['kpis'];
        $html = '<h2 style="color:#1f2a44;">Resumo</h2>';
        $html .= '<table cellpadding="3" style="font-size:8.5pt;">';
        foreach ($filtros as $nome => $valor) {
            $html .= '<tr><td width="25%"><b>' . self::e($nome) . '</b></td><td width="75%">' . self::e($valor) . '</td></tr>';
        }
        $html .= '</table><br>';

        $cartoes = [
            ['Chamados', (string) $k['total']],
            ['Em aberto', (string) $k['abertos']],
            ['Solucionados', (string) $k['solucionados']],
            ['Abertos com SLA estourado', (string) $k['abertos_atrasados']],
            ['SLA de resposta', $k['sla_resposta'] !== null ? number_format($k['sla_resposta'], 1, ',', '.') . '%' : '—'],
            ['SLA de solução', $k['sla_solucao'] !== null ? number_format($k['sla_solucao'], 1, ',', '.') . '%' : '—'],
            ['Média 1ª resposta', Dados::duracao($k['media_resposta'])],
            ['Média de solução', Dados::duracao($k['media_solucao'])],
        ];
        $html .= '<table cellpadding="6" cellspacing="3"><tr>';
        foreach ($cartoes as $i => [$rotulo, $valor]) {
            if ($i === 4) {
                $html .= '</tr><tr>';
            }
            $html .= '<td width="25%" style="background-color:#f4f6fa;border-left:3px solid #fdc300;"><span style="font-size:8pt;color:#667382;">'
                . self::e($rotulo) . '</span><br><span style="font-size:15pt;font-weight:bold;color:#1f2a44;">' . self::e($valor) . '</span></td>';
        }
        $html .= '</tr></table>';
        $html .= '<p style="font-size:7.5pt;color:#667382;">Tempos contados em horário comercial (mesmo calendário dos SLAs). SLA % = no prazo ÷ (no prazo + atrasados); chamados ainda dentro do prazo não entram na conta.</p>';
        $pdf->writeHTML($html, true, false, true);

        // Gráficos: dois por linha
        $imagens = array_filter($graficos, fn ($d) => is_string($d) && str_starts_with($d, 'data:image/png;base64,'));
        if ($imagens !== []) {
            $pdf->AddPage();
            $pdf->writeHTML('<h2 style="color:#1f2a44;">Gráficos</h2>', true, false, true);
            $largura = ($pdf->getPageWidth() - 25) / 2;
            $altura  = $largura * 0.55;
            $col = 0;
            foreach ($imagens as $titulo => $dado) {
                if ($pdf->GetY() + $altura + 8 > $pdf->getPageHeight() - 14) {
                    $pdf->AddPage();
                    $col = 0;
                }
                $x = 10 + $col * ($largura + 5);
                $y = $pdf->GetY();
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetXY($x, $y);
                $pdf->Cell($largura, 5, $titulo, 0, 0, 'L');
                $pdf->Image('@' . base64_decode(substr($dado, strlen('data:image/png;base64,'))), $x, $y + 6, $largura, $altura, 'PNG', '', '', true, 150, '', false, false, 1, 'CM');
                if ($col === 1) {
                    $pdf->SetY($y + $altura + 10);
                    $col = 0;
                } else {
                    $pdf->SetY($y);
                    $col = 1;
                }
            }
            $pdf->SetFont('helvetica', '', 9);
        }

        // Rankings
        $pdf->AddPage();
        $pdf->writeHTML(self::ranking('Técnicos', 'Técnico', $analise['tecnicos']), true, false, true);
        $pdf->writeHTML(self::ranking('Grupos atribuídos', 'Grupo', $analise['grupos']), true, false, true);
        $pdf->writeHTML(self::ranking('Setores (requerente)', 'Setor', $analise['setores']), true, false, true);
        $pdf->writeHTML(self::ranking('Quem mais abriu chamados (top 30)', 'Requerente / setor', array_slice($analise['requerentes'], 0, 30), true), true, false, true);
        $pdf->writeHTML(self::ranking('Categorias (top 30)', 'Categoria', array_slice($analise['categorias'], 0, 30)), true, false, true);
        $pdf->writeHTML(self::ranking('Prioridades', 'Prioridade', $analise['prioridades']), true, false, true);

        // SLA estourado
        $atrasados = array_values(array_filter($chamados, fn ($c) => $c['sla_resposta'] === 'atrasado' || $c['sla_solucao'] === 'atrasado'));
        $pdf->AddPage();
        $html = '<h2 style="color:#1f2a44;">Chamados com SLA estourado (' . count($atrasados) . ')</h2>';
        $html .= self::lista($atrasados, false);
        $pdf->writeHTML($html, true, false, true);

        // Lista completa para auditoria
        $pdf->AddPage();
        $parte = array_slice($chamados, 0, self::MAX_LINHAS);
        $html = '<h2 style="color:#1f2a44;">Lista de chamados (' . count($chamados) . ')</h2>';
        if (count($chamados) > self::MAX_LINHAS) {
            $html .= '<p>Mostrando os primeiros ' . self::MAX_LINHAS . '. A lista completa está na exportação em Excel.</p>';
        }
        $html .= self::lista($parte, true);
        $pdf->writeHTML($html, true, false, true);

        $arquivo = tempnam(GLPI_TMP_DIR, 'relatorio_') . '.pdf';
        $pdf->Output($arquivo, 'F');
        return $arquivo;
    }

    private static function ranking(string $titulo, string $rotulo, array $lista, bool $comSetor = false): string
    {
        $html = '<h3 style="color:#1f2a44;">' . self::e($titulo) . '</h3>';
        if ($lista === []) {
            return $html . '<p>Sem dados.</p>';
        }
        $th = 'style="background-color:#fdc300;color:#1f2a44;font-weight:bold;"';
        $html .= '<table border="0.3" cellpadding="3" style="font-size:7.5pt;"><thead><tr>'
            . '<th width="25%" ' . $th . '>' . self::e($rotulo) . '</th>'
            . '<th width="6%" ' . $th . ' align="right">Total</th>'
            . '<th width="7%" ' . $th . ' align="right">Em aberto</th>'
            . '<th width="8%" ' . $th . ' align="right">Solucionados</th>'
            . '<th width="8%" ' . $th . ' align="right">Abertos atrasados</th>'
            . '<th width="8%" ' . $th . ' align="right">SLA resposta</th>'
            . '<th width="8%" ' . $th . ' align="right">SLA solução</th>'
            . '<th width="10%" ' . $th . ' align="right">Média 1ª resposta</th>'
            . '<th width="10%" ' . $th . ' align="right">Média solução</th>'
            . '<th width="10%" ' . $th . ' align="right">Maior solução</th>'
            . '</tr></thead><tbody>';
        foreach ($lista as $i => $g) {
            $nome = $comSetor ? str_replace("\t", ' — ', $g['nome']) : $g['nome'];
            $fundo = $i % 2 ? ' style="background-color:#f7f8fa;"' : '';
            $html .= '<tr' . $fundo . '><td width="25%">' . self::e($nome) . '</td>'
                . '<td width="6%" align="right">' . $g['total'] . '</td>'
                . '<td width="7%" align="right">' . $g['abertos'] . '</td>'
                . '<td width="8%" align="right">' . $g['solucionados'] . '</td>'
                . '<td width="8%" align="right">' . ($g['abertos_atrasados'] ? '<b style="color:#c2410c;">' . $g['abertos_atrasados'] . '</b>' : '0') . '</td>'
                . '<td width="8%" align="right">' . self::pct($g['sla_resposta']) . '</td>'
                . '<td width="8%" align="right">' . self::pct($g['sla_solucao']) . '</td>'
                . '<td width="10%" align="right">' . Dados::duracao($g['media_resposta']) . '</td>'
                . '<td width="10%" align="right">' . Dados::duracao($g['media_solucao']) . '</td>'
                . '<td width="10%" align="right">' . Dados::duracao($g['sol_max'] ?: null) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private static function lista(array $chamados, bool $completa): string
    {
        if ($chamados === []) {
            return '<p>Nenhum chamado.</p>';
        }
        $th = 'style="background-color:#1f2a44;color:#ffffff;font-weight:bold;"';
        $html = '<table border="0.3" cellpadding="2" style="font-size:6.5pt;"><thead><tr>'
            . '<th width="4%" ' . $th . '>Nº</th><th width="7%" ' . $th . '>Abertura</th><th width="17%" ' . $th . '>Título</th>'
            . '<th width="13%" ' . $th . '>Categoria</th><th width="10%" ' . $th . '>Requerente</th><th width="8%" ' . $th . '>Setor</th>'
            . '<th width="10%" ' . $th . '>Técnico(s)</th><th width="6%" ' . $th . '>Prioridade</th><th width="7%" ' . $th . '>Situação</th>'
            . '<th width="6%" ' . $th . '>SLA resp.</th><th width="6%" ' . $th . '>SLA sol.</th><th width="6%" ' . $th . '>' . ($completa ? 'Tempo sol.' : 'Prazo sol.') . '</th>'
            . '</tr></thead><tbody>';
        foreach ($chamados as $i => $c) {
            $fundo = $i % 2 ? ' style="background-color:#f7f8fa;"' : '';
            $html .= '<tr' . $fundo . '><td width="4%">' . $c['id'] . '</td><td width="7%">' . Dados::data($c['abertura']) . '</td>'
                . '<td width="17%">' . self::e($c['titulo']) . '</td><td width="13%">' . self::e($c['categoria']) . '</td>'
                . '<td width="10%">' . self::e($c['requerente']) . '</td><td width="8%">' . self::e($c['setor']) . '</td>'
                . '<td width="10%">' . self::e(implode(', ', $c['tecnicos'])) . '</td><td width="6%">' . self::e($c['prioridade']) . '</td>'
                . '<td width="7%">' . self::e($c['status']) . '</td>'
                . '<td width="6%">' . self::sla($c['sla_resposta']) . '</td><td width="6%">' . self::sla($c['sla_solucao']) . '</td>'
                . '<td width="6%">' . ($completa ? Dados::duracao($c['tempo_solucao']) : Dados::data($c['prazo_solucao'])) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private static function sla(string $s): string
    {
        return match ($s) {
            'atrasado'  => '<b style="color:#c2410c;">Atrasado</b>',
            'no_prazo'  => '<span style="color:#1e8449;">No prazo</span>',
            'andamento' => 'No prazo*',
            default     => '—',
        };
    }

    private static function pct(?float $v): string
    {
        return $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
    }

    private static function e(string $t): string
    {
        return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    }
}
