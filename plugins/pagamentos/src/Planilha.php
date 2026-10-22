<?php

namespace GlpiPlugin\Pagamentos;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Planilha dos pedidos de pagamento, no formato da planilha do Financeiro:
 * uma aba com o dia ("07.10") com todos os pedidos e uma aba por setor.
 */
class Planilha
{
    private const AZUL     = '1F2A44';
    private const AMARELO  = 'FDC300';
    private const LISTRA   = 'F4F6FA';
    private const BORDA    = 'D5DAE3';
    private const CINZA    = '6B7280';
    private const CONTABIL = '_-"R$" * #,##0.00_-;-"R$" * #,##0.00_-;_-"R$" * "-"??_-;_-@_-';

    /** campo => [cabeçalho, largura] */
    private const COLUNAS = [
        'chamado'     => ['CHAMADO', 10],
        'fornecedor'  => ['FORNECEDOR', 22],
        'documento'   => ['CNPJ', 19],
        'valor'       => ['VALOR', 13],
        'servico'     => ['DESCRIÇÃO', 60],
        'setor'       => ['SETOR SOLICITANTE', 22],
        'forma'       => ['PAGAMENTO', 17],
        'pix'         => ['CHAVE PIX', 22],
        'observacoes' => ['DETALHES', 30],
        'local'       => ['LOCAL', 24],
    ];

    /**
     * Devolve o conteúdo binário do .xlsx.
     */
    public static function gerar(array $pedidos, string $de, string $ate, bool $abas_por_setor = true): string
    {
        $planilha = new Spreadsheet();
        $planilha->getProperties()->setTitle('Solicitações de pagamento')->setCompany('Unity Saúde');
        $planilha->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        // Aba principal com o dia (ou período), como "07.10"
        $principal = $planilha->getActiveSheet();
        $principal->setTitle(self::nomePeriodo($de, $ate));
        self::preencher($principal, $pedidos);

        // Uma aba por setor
        $usados = [mb_strtolower($principal->getTitle()) => true];
        foreach ($abas_por_setor ? Dados::porSetor($pedidos) : [] as $setor => $do_setor) {
            $aba = $planilha->createSheet();
            $aba->setTitle(self::nomeAba($setor, $usados));
            self::preencher($aba, $do_setor);
        }

        $planilha->setActiveSheetIndex(0);

        $arquivo = fopen('php://temp', 'w+b');
        (new Xlsx($planilha))->save($arquivo);
        rewind($arquivo);
        $conteudo = (string) stream_get_contents($arquivo);
        fclose($arquivo);
        $planilha->disconnectWorksheets();

        return $conteudo;
    }

    public static function nomePeriodo(string $de, string $ate): string
    {
        $d = new DateTimeImmutable($de);
        $a = new DateTimeImmutable($ate);
        return $de === $ate ? $d->format('d.m') : $d->format('d.m') . ' a ' . $a->format('d.m');
    }

    private static function preencher(Worksheet $aba, array $pedidos): void
    {
        $campos     = array_keys(self::COLUNAS);
        $ultima_col = Coordinate::stringFromColumnIndex(count($campos));
        $col_valor  = Coordinate::stringFromColumnIndex(array_search('valor', $campos, true) + 1);

        foreach (array_values(self::COLUNAS) as $i => [$titulo, $largura]) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $aba->setCellValue("{$letra}1", $titulo);
            $aba->getColumnDimension($letra)->setWidth($largura);
        }

        $linha = 2;
        foreach ($pedidos as $pedido) {
            foreach ($campos as $i => $campo) {
                $celula = Coordinate::stringFromColumnIndex($i + 1) . $linha;
                $valor  = $pedido[$campo] ?? '';
                if ($campo === 'chamado') {
                    $aba->setCellValue($celula, $valor ?: '');
                } elseif ($campo === 'valor') {
                    if ($valor !== null) {
                        $aba->setCellValue($celula, $valor);
                    } else {
                        // Valor que não deu para entender fica como foi digitado, em vermelho
                        $aba->setCellValueExplicit($celula, (string) ($pedido['valor_texto'] ?? ''), DataType::TYPE_STRING);
                        $aba->getStyle($celula)->getFont()->getColor()->setRGB('B91C1C');
                    }
                } else {
                    $aba->setCellValueExplicit($celula, (string) $valor, DataType::TYPE_STRING);
                }
            }
            $linha++;
        }
        $ultima = $linha - 1;

        // Cabeçalho azul-escuro com filtro
        $cabecalho = $aba->getStyle("A1:{$ultima_col}1");
        $cabecalho->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $cabecalho->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AZUL);
        $aba->getRowDimension(1)->setRowHeight(22);

        if ($pedidos === []) {
            $aba->setCellValue('A2', 'Nenhum pedido no período.');
            $aba->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB(self::CINZA);
            $ultima = 2;
            $linha  = 3;
        } else {
            $aba->getStyle("A2:{$ultima_col}{$ultima}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDA);
            for ($l = 3; $l <= $ultima; $l += 2) {
                $aba->getStyle("A{$l}:{$ultima_col}{$l}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LISTRA);
            }
            $aba->setAutoFilter("A1:{$ultima_col}{$ultima}");
        }

        // Total em amarelo (soma só o que estiver visível no filtro)
        $aba->setCellValue("A{$linha}", 'TOTAL');
        $aba->setCellValue("B{$linha}", count($pedidos) . ' pedido(s)');
        $aba->setCellValue("{$col_valor}{$linha}", $pedidos === [] ? 0 : "=SUBTOTAL(109,{$col_valor}2:{$col_valor}{$ultima})");
        $total = $aba->getStyle("A{$linha}:{$ultima_col}{$linha}");
        $total->getFont()->setBold(true)->getColor()->setRGB(self::AZUL);
        $total->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AMARELO);
        $total->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::AZUL);

        // Tudo centralizado e valor em R$
        $aba->getStyle("A1:{$ultima_col}{$linha}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $aba->getStyle("{$col_valor}2:{$col_valor}{$linha}")->getNumberFormat()->setFormatCode(self::CONTABIL);

        $aba->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $aba->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
    }

    private static function nomeAba(string $nome, array &$usados): string
    {
        $base = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $nome)) ?: 'Setor', 0, 28);
        $final = $base;
        $n = 2;
        while (isset($usados[mb_strtolower($final)])) {
            $final = mb_substr($base, 0, 26) . ' ' . $n++;
        }
        $usados[mb_strtolower($final)] = true;
        return $final;
    }
}
