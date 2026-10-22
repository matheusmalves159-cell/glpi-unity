<?php

namespace GlpiPlugin\Pagamentos;

/**
 * Lê as respostas do formulário de solicitação de pagamento e devolve os
 * pedidos já organizados.
 */
class Dados
{
    /**
     * Pedidos abertos no período, ordenados por setor e horário.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buscar(string $de, string $ate): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $pedidos = [];

        // Pedidos da tela "Solicitar pagamento"
        $tela = $DB->request([
            'FROM'  => 'glpi_plugin_pagamentos_pedidos',
            'WHERE' => [
                ['date_creation' => ['>=', $de . ' 00:00:00']],
                ['date_creation' => ['<=', $ate . ' 23:59:59']],
            ],
        ]);
        foreach ($tela as $p) {
            $pedidos[] = [
                'data'        => new \DateTimeImmutable((string) $p['date_creation']),
                'chamado'     => (int) $p['tickets_id'],
                'fornecedor'  => (string) $p['fornecedor'],
                'documento'   => (string) $p['documento'],
                'valor'       => (float) $p['valor'],
                'valor_texto' => (string) $p['valor'],
                'servico'     => (string) $p['servico'],
                'setor'       => (string) $p['setor'],
                'forma'       => (string) $p['forma'],
                'pix'         => (string) $p['pix'],
                'observacoes' => (string) $p['observacoes'],
                'local'       => (string) $p['local'],
            ];
        }

        // Pedidos antigos, feitos pelo formulário do GLPI
        $formularios = Settings::ids('forms_ids');
        if ($formularios === []) {
            usort($pedidos, fn ($a, $b) => [mb_strtolower($a['setor']), $a['data']] <=> [mb_strtolower($b['setor']), $b['data']]);
            return $pedidos;
        }

        $opcoes  = [];
        $respostas = $DB->request([
            'FROM'  => 'glpi_forms_answerssets',
            'WHERE' => [
                'forms_forms_id' => $formularios,
                ['date_creation' => ['>=', $de . ' 00:00:00']],
                ['date_creation' => ['<=', $ate . ' 23:59:59']],
            ],
            'ORDER' => ['date_creation ASC'],
        ]);
        foreach ($respostas as $resposta) {
            $form = (int) $resposta['forms_forms_id'];
            $opcoes[$form] ??= self::opcoesDasPerguntas($form);
            $pedidos[] = self::montar($resposta, $opcoes[$form]);
        }

        usort($pedidos, fn ($a, $b) => [mb_strtolower($a['setor']), $a['data']] <=> [mb_strtolower($b['setor']), $b['data']]);
        return $pedidos;
    }

    /**
     * Agrupa por setor: [setor => [pedidos]]
     */
    public static function porSetor(array $pedidos): array
    {
        $grupos = [];
        foreach ($pedidos as $pedido) {
            $grupos[$pedido['setor'] !== '' ? $pedido['setor'] : 'Sem setor'][] = $pedido;
        }
        return $grupos;
    }

    private static function montar(array $resposta, array $opcoes): array
    {
        $campos = self::lerRespostas((string) $resposta['answers'], $opcoes);

        return [
            'data'        => new \DateTimeImmutable((string) $resposta['date_creation']),
            'chamado'     => self::chamado((int) $resposta['id']),
            'fornecedor'  => (string) ($campos['fornecedor'] ?? ''),
            'documento'   => (string) ($campos['documento'] ?? ''),
            'valor'       => self::valor($campos['valor'] ?? null),
            'valor_texto' => (string) ($campos['valor'] ?? ''),
            'servico'     => (string) ($campos['servico'] ?? ''),
            'setor'       => (string) ($campos['setor'] ?? ''),
            'forma'       => (string) ($campos['forma'] ?? ''),
            'pix'         => (string) ($campos['pix'] ?? ''),
            'observacoes' => (string) ($campos['observacoes'] ?? ''),
            'local'       => (string) ($campos['local'] ?? ''),
        ];
    }

    /**
     * Número do chamado aberto pela resposta (0 se não houver).
     */
    private static function chamado(int $answerset_id): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $link = $DB->request([
            'SELECT' => ['items_id'],
            'FROM'   => 'glpi_forms_destinations_answerssets_formdestinationitems',
            'WHERE'  => ['forms_answerssets_id' => $answerset_id, 'itemtype' => 'Ticket'],
            'LIMIT'  => 1,
        ])->current();
        return $link ? (int) $link['items_id'] : 0;
    }

    /**
     * Opções das perguntas de escolha (para traduzir a chave salva no texto exibido).
     *
     * @return array<int, array<string, string>>
     */
    private static function opcoesDasPerguntas(int $form_id): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $opcoes = [];
        $perguntas = $DB->request([
            'SELECT'     => ['glpi_forms_questions.id', 'glpi_forms_questions.extra_data'],
            'FROM'       => 'glpi_forms_questions',
            'INNER JOIN' => [
                'glpi_forms_sections' => [
                    'ON' => ['glpi_forms_sections' => 'id', 'glpi_forms_questions' => 'forms_sections_id'],
                ],
            ],
            'WHERE'      => ['glpi_forms_sections.forms_forms_id' => $form_id],
        ]);
        foreach ($perguntas as $p) {
            $extra = json_decode((string) $p['extra_data'], true);
            if (is_array($extra['options'] ?? null)) {
                $opcoes[(int) $p['id']] = array_map('strval', $extra['options']);
            }
        }
        return $opcoes;
    }

    /**
     * @return array<string, string>
     */
    private static function lerRespostas(string $json, array $opcoes): array
    {
        $campos = [];
        foreach ((array) json_decode($json, true) as $resposta) {
            $chave = self::campoDaPergunta((string) ($resposta['question_label'] ?? ''));
            if ($chave === null || isset($campos[$chave])) {
                continue;
            }
            $bruto = $resposta['raw_answer'] ?? '';
            $mapa  = $opcoes[(int) ($resposta['question_id'] ?? 0)] ?? [];

            if (is_array($bruto)) {
                $valores = array_map(fn ($v) => is_scalar($v) ? ($mapa[(string) $v] ?? (string) $v) : '', $bruto);
                $campos[$chave] = implode(', ', array_filter($valores, fn ($v) => $v !== ''));
            } elseif (is_scalar($bruto)) {
                $campos[$chave] = $mapa[(string) $bruto] ?? trim(strip_tags((string) $bruto));
            }
        }
        return $campos;
    }

    /**
     * Descobre o campo pelo texto da pergunta (funciona mesmo se a pergunta for renomeada de leve).
     */
    private static function campoDaPergunta(string $rotulo): ?string
    {
        $r = strtoupper(strtr($rotulo, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C',
        ]));

        return match (true) {
            str_contains($r, 'SETOR')                                    => 'setor',
            str_contains($r, 'VALOR')                                    => 'valor',
            str_contains($r, 'CNPJ') || str_contains($r, 'CPF')          => 'documento',
            str_contains($r, 'PIX') || str_contains($r, 'TRANSFERENCIA') => 'pix',
            str_contains($r, 'FORMA')                                    => 'forma',
            str_contains($r, 'FORNECEDOR')                               => 'fornecedor',
            str_contains($r, 'LOCAL')                                    => 'local',
            str_contains($r, 'SERVICO') || str_contains($r, 'PRODUTO')   => 'servico',
            str_contains($r, 'OBSERV')                                   => 'observacoes',
            default                                                      => null,
        };
    }

    /**
     * "R$ 1.234,56", "1234,56", "1234.56", "1.500" -> número; texto ilegível -> null.
     */
    public static function valor(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        $s = preg_replace('/[^\d,.\-]/', '', (string) $valor);
        if ($s === '' || $s === '-') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (substr_count($s, '.') > 1 || preg_match('/\.\d{3}$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        return is_numeric($s) ? round((float) $s, 2) : null;
    }
}
