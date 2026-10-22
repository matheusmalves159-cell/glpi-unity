<?php

namespace GlpiPlugin\Chamadoti;

use SLA;

/**
 * Prazos (SLA) de cada prioridade, lidos das regras de negócio de chamados
 * ("SLA - Urgência …"): se o SLA mudar no GLPI, a tela mostra o novo prazo.
 */
class Prazos
{
    /** Prioridades na ordem da tela, com a explicação para o usuário */
    public const PRIORIDADES = [
        5 => ['Muito alta', 'Empresa ou setor inteiro parado', 'danger'],
        4 => ['Alta', 'Meu trabalho está parado e não há alternativa', 'orange'],
        3 => ['Média', 'Atrapalha, mas consigo continuar trabalhando', 'yellow'],
        2 => ['Baixa', 'Pode esperar alguns dias', 'green'],
        1 => ['Muito baixa', 'Sem pressa: melhoria ou pedido futuro', 'secondary'],
    ];

    /**
     * @return array<int, array{tto:string, ttr:string}>
     */
    public static function porPrioridade(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $prazos = [];
        foreach (array_keys(self::PRIORIDADES) as $urgencia) {
            $prazos[$urgencia] = ['tto' => '', 'ttr' => ''];
        }

        $regras = $DB->request([
            'SELECT'     => ['r.id', 'c.pattern'],
            'FROM'       => 'glpi_rules AS r',
            'INNER JOIN' => ['glpi_rulecriterias AS c' => ['ON' => ['c' => 'rules_id', 'r' => 'id']]],
            'WHERE'      => [
                'r.sub_type'  => 'RuleTicket',
                'r.is_active' => 1,
                'c.criteria'  => 'urgency',
                'c.condition' => 0,
            ],
            'ORDER'      => 'r.ranking',
        ]);
        foreach ($regras as $regra) {
            $urgencia = (int) $regra['pattern'];
            if (!isset($prazos[$urgencia])) {
                continue;
            }
            $acoes = $DB->request([
                'FROM'  => 'glpi_ruleactions',
                'WHERE' => ['rules_id' => $regra['id'], 'field' => ['slas_id_tto', 'slas_id_ttr']],
            ]);
            foreach ($acoes as $acao) {
                $sla = new SLA();
                if ($sla->getFromDB((int) $acao['value'])) {
                    $prazos[$urgencia][$acao['field'] === 'slas_id_tto' ? 'tto' : 'ttr'] = self::texto($sla->fields);
                }
            }
        }
        return $prazos;
    }

    private static function texto(array $sla): string
    {
        $n = (int) $sla['number_time'];
        return match ($sla['definition_time']) {
            'minute' => $n . ' min',
            'hour'   => $n . ' h',
            'day'    => $n . ($n === 1 ? ' dia útil' : ' dias úteis'),
            'month'  => $n . ($n === 1 ? ' mês' : ' meses'),
            default  => '',
        };
    }
}
