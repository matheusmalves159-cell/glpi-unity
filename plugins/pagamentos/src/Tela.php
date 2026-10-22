<?php

namespace GlpiPlugin\Pagamentos;

use CommonGLPI;
use Session;

/**
 * Menu, acesso e períodos da tela "Planilha de Pagamentos".
 */
class Tela extends CommonGLPI
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return 'Planilha de Pagamentos';
    }

    public static function getIcon()
    {
        return 'ti ti-file-spreadsheet';
    }

    /**
     * Administradores e membros dos grupos configurados (Financeiro).
     */
    public static function canView(): bool
    {
        if (Session::getLoginUserID() === false) {
            return false;
        }
        if (Session::haveRight('empresa_pagamentos', READ)) {
            return true;
        }
        $grupos_usuario = array_map('intval', (array) ($_SESSION['glpigroups'] ?? []));
        return array_intersect(Settings::ids('groups_ids'), $grupos_usuario) !== [];
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/pagamentos/front/planilha.php',
            'icon'  => self::getIcon(),
        ];
    }

    /**
     * Filtros escolhidos na tela (padrão: hoje, todos os setores e formas).
     *
     * @return array{de:string, ate:string, setor:string, forma:string}
     */
    public static function filtros(array $get): array
    {
        $data = static fn ($v, string $padrao): string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $padrao;
        $de   = $data($get['de'] ?? null, date('Y-m-d'));
        $ate  = $data($get['ate'] ?? null, $de);
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }
        $forma = (string) ($get['forma'] ?? '');

        return [
            'de'    => $de,
            'ate'   => $ate,
            'setor' => is_string($get['setor'] ?? null) ? trim($get['setor']) : '',
            'forma' => in_array($forma, Pedido::FORMAS, true) ? $forma : '',
        ];
    }

    /**
     * Pedidos do período já filtrados por setor e forma de pagamento.
     */
    public static function pedidos(array $filtros): array
    {
        return array_values(array_filter(
            Dados::buscar($filtros['de'], $filtros['ate']),
            fn (array $p) => ($filtros['setor'] === '' || $p['setor'] === $filtros['setor'])
                && ($filtros['forma'] === '' || $p['forma'] === $filtros['forma'])
        ));
    }

    /**
     * Nome do arquivo: "PAGAMENTOS 20.10 - Comercial - PIX.xlsx"
     */
    public static function nomeArquivo(array $filtros): string
    {
        $partes = [Planilha::nomePeriodo($filtros['de'], $filtros['ate'])];
        if ($filtros['setor'] !== '') {
            $partes[] = $filtros['setor'];
        }
        if ($filtros['forma'] !== '') {
            $partes[] = $filtros['forma'] === Pedido::FORMAS['pix'] ? 'PIX' : 'Cartao';
        }
        $nome = 'PAGAMENTOS ' . implode(' - ', $partes) . '.xlsx';
        return preg_replace('/[\\\\\/:*?"<>|]+/', '-', $nome);
    }
}
