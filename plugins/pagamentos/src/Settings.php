<?php

namespace GlpiPlugin\Pagamentos;

use Config;

class Settings
{
    public const CONTEXT = 'plugin:pagamentos';

    public const DEFAULTS = [
        // Formulários antigos do GLPI cujos pedidos também entram na planilha
        'forms_ids'          => '',
        // Grupos que geram a planilha (além dos administradores)
        'groups_ids'         => '',
        // Grupos que podem solicitar pagamento (vazio = todos)
        'request_groups_ids' => '',
        // Chamado aberto: grupo atribuído e categoria
        'groups_id_assign'   => '0',
        'itilcategories_id'  => '0',
        // Opções da tela de solicitação (uma por linha)
        'setores'            => "Administrativo\nAtendimento\nCadastro\nComercial\nConcierge\nConformidade\nContas Médicas\nCredenciamento\nCrônicos\nDiretoria\nFinanceiro\nGestão e Cultura\nJurídico\nMarketing\nNIS\nPrivacidade\nRegulação\nSAC\nSustentabilidade\nTI",
        'locais'             => "UNITY SAUDE ESCRITÓRIO\nUNIDADE CEILÂNDIA\nUNIDADE ASA SUL\nUNIDADE NOVA SEDE\nARQUIVO UNITY\nLOCAL PLACAS SOLARES\nLOJA PATIO CAPITAL UNITY",
        // Aviso no topo da tela de solicitação (uma linha por parágrafo; **texto** = negrito)
        'aviso'              => "Preencha abaixo **TODAS** as informações solicitadas para darmos andamento ao provisionamento do pagamento.\n**PRAZO DE 48 HORAS PARA REALIZAÇÃO DO PAGAMENTO APÓS O RECEBIMENTO DO CHAMADO E FORMULÁRIO.**\nCaso alguma informação não esteja preenchida e/ou esteja incorreta **NÃO** será realizado a programação do mesmo.\nQualquer dúvida estamos a disposição!!",
    ];

    /**
     * Aviso em parágrafos, com **negrito** já convertido (texto escapado).
     *
     * @return string[]
     */
    public static function avisoHtml(): array
    {
        $paragrafos = [];
        foreach (self::lista('aviso') as $linha) {
            $paragrafos[] = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlescape($linha));
        }
        return $paragrafos;
    }

    /**
     * Dados da tela de configuração (usada também na Central de telas).
     */
    public static function dadosConfig(): array
    {
        $formularios = [];
        foreach ((new \Glpi\Form\Form())->find([], ['name ASC']) as $f) {
            $formularios[(int) $f['id']] = trim((string) $f['name']) . ($f['is_active'] ? '' : ' (inativo)');
        }
        $grupos = [];
        foreach ((new \Group())->find([], ['completename ASC']) as $g) {
            $grupos[(int) $g['id']] = $g['completename'];
        }
        // Todas as categorias: a da tela pode estar oculta nos chamados ("Exibir nos chamados" desligado)
        $categorias = [];
        foreach ((new \ITILCategory())->find([], ['completename ASC']) as $c) {
            $categorias[(int) $c['id']] = $c['completename'];
        }
        return [
            'formularios'        => $formularios,
            'grupos'             => $grupos,
            'categorias'         => $categorias,
            'forms_ids'          => self::ids('forms_ids'),
            'groups_ids'         => self::ids('groups_ids'),
            'request_groups_ids' => self::ids('request_groups_ids'),
            'groups_id_assign'   => (int) self::get('groups_id_assign'),
            'itilcategories_id'  => (int) self::get('itilcategories_id'),
            'setores'            => implode("\n", self::lista('setores')),
            'locais'             => implode("\n", self::lista('locais')),
            'aviso'              => self::get('aviso'),
        ];
    }

    /**
     * Lista configurada (uma opção por linha), sem linhas vazias ou repetidas.
     *
     * @return string[]
     */
    public static function lista(string $nome): array
    {
        $itens = array_map('trim', preg_split('/\R/', self::get($nome)) ?: []);
        return array_values(array_unique(array_filter($itens, fn ($i) => $i !== '')));
    }

    public static function get(string $nome): string
    {
        $valores = array_merge(self::DEFAULTS, Config::getConfigurationValues(self::CONTEXT));
        return (string) ($valores[$nome] ?? '');
    }

    public static function save(array $valores): void
    {
        Config::setConfigurationValues(self::CONTEXT, array_intersect_key($valores, self::DEFAULTS));
    }

    /**
     * @return int[]
     */
    public static function ids(string $nome): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', self::get($nome))),
            fn (int $id) => $id > 0
        )));
    }
}
