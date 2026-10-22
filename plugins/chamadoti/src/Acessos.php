<?php

namespace GlpiPlugin\Chamadoti;

use CommonGLPI;
use Session;

/**
 * Acessos simplificados (Administração > Acessos).
 *
 * Cada "área" liga/desliga um conjunto de permissões do GLPI num perfil.
 * Permissões que não pertencem a nenhuma área ficam como estão (a tela
 * avançada de perfis do GLPI continua valendo para ajustes finos).
 *
 * Permissão para um usuário específico: o GLPI só dá permissões por perfil,
 * então o usuário ganha um "perfil individual" (cópia do perfil dele) que só
 * ele usa, e as áreas são ligadas/desligadas nesse perfil.
 */
class Acessos extends CommonGLPI
{
    /** Perfil que nunca é alterado por esta tela (acesso total). */
    public const PERFIL_TOTAL = 4;

    /** Perfil padrão dos colaboradores (usado ao desfazer um perfil individual). */
    public const PERFIL_PADRAO = 38;

    private const MARCA_INDIVIDUAL = '[individual:';

    /**
     * Áreas: rótulo, explicação, ícone e bits de cada permissão do GLPI.
     * (READ=1, UPDATE=2, CREATE=4, DELETE=8, PURGE=16; chamados: 1024 todos,
     * 2048 do grupo, 4096 atribuídos, 8192 atribuir, 32768 assumir, 65536 prioridade.)
     */
    public const AREAS = [
        'atender' => [
            'Atender chamados', 'ti ti-headset',
            'Ver os chamados do seu grupo e os atribuídos a ele, responder, pôr em pendência, solucionar, atribuir e pedir aprovação.',
            ['ticket' => 112640, 'followup' => 8192, 'task' => 64535, 'document' => 7, 'itiltemplate' => 2,
                'slm' => 256, 'group' => 1, 'user' => 1, 'dashboard' => 2, 'ticketvalidation' => 3072],
        ],
        'todos' => [
            'Ver chamados de todos os setores', 'ti ti-eye',
            'Enxerga todos os chamados, não só os do próprio grupo.',
            ['ticket' => 1024],
        ],
        'parque' => [
            'Parque tecnológico', 'ti ti-device-desktop',
            'Computadores, monitores, impressoras, telefones, rede, periféricos e softwares: ver, cadastrar e editar.',
            ['computer' => 3951, 'monitor' => 3951, 'networking' => 3951, 'peripheral' => 3951, 'phone' => 3951,
                'printer' => 3951, 'software' => 3951, 'infocom' => 7],
        ],
        'conhecimento' => [
            'Escrever na base de conhecimento', 'ti ti-book',
            'Criar e editar artigos e perguntas frequentes (ler, todos já podem).',
            ['knowbase' => 4103],
        ],
        'relatorios' => [
            'Relatórios e estatísticas', 'ti ti-chart-bar',
            'Relatórios de chamados (Excel/PDF), estatísticas e relatórios do GLPI.',
            ['statistic' => 1, 'reports' => 1],
        ],
        'planejamento' => [
            'Planejamento', 'ti ti-calendar-time',
            'Agenda de tarefas da equipe.',
            ['planning' => 3073],
        ],
        'gerencia' => [
            'Fornecedores e contratos', 'ti ti-briefcase',
            'Fornecedores, contatos, contratos e documentos.',
            ['contact_enterprise' => 31, 'contract' => 31, 'document' => 31],
        ],
        'usuarios' => [
            'Usuários e grupos', 'ti ti-users',
            'Cadastrar e editar usuários e grupos.',
            ['user' => 31, 'group' => 23],
        ],
        'config' => [
            'Configurações', 'ti ti-settings',
            'Central de telas, categorias, níveis de serviço (SLA), listas, locais, motivos de pendência e notificações.',
            ['config' => 3, 'dropdown' => 23, 'itilcategory' => 23, 'slm' => 23, 'notification' => 23, 'calendar' => 23,
                'location' => 23, 'pendingreason' => 31, 'solutiontemplate' => 23, 'taskcategory' => 23],
        ],
        'perfis' => [
            'Perfis e permissões', 'ti ti-shield-lock',
            'Usar esta tela e a tela avançada de perfis.',
            ['profile' => 23],
        ],
    ];

    public static function getTypeName($nb = 0)
    {
        return 'Acessos';
    }

    public static function getIcon()
    {
        return 'ti ti-shield-check';
    }

    public static function canView(): bool
    {
        return Session::haveRight('profile', UPDATE);
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/chamadoti/front/acessos.php',
            'icon'  => self::getIcon(),
        ];
    }

    /** Direitos atuais de um perfil: [nome => valor]. */
    private static function direitos(int $perfil): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $d = [];
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $perfil]]) as $r) {
            $d[$r['name']] = (int) $r['rights'];
        }
        return $d;
    }

    /**
     * Situação de cada área num perfil: 'sim' (tudo ligado), 'nao' (nada) ou
     * 'parcial' (só parte das permissões da área — ajustado na tela avançada).
     */
    public static function situacao(int $perfil): array
    {
        $d = self::direitos($perfil);
        $situacao = [];
        foreach (self::AREAS as $chave => [, , , $bits]) {
            // Conta só o que é desta área (ex.: "ler usuários" também vem com "Atender chamados")
            $proprios = [];
            foreach ($bits as $nome => $b) {
                $outros = 0;
                foreach (self::AREAS as $outra => [, , , $bitsOutra]) {
                    if ($outra !== $chave) {
                        $outros |= $bitsOutra[$nome] ?? 0;
                    }
                }
                if (($b & ~$outros) !== 0) {
                    $proprios[$nome] = $b & ~$outros;
                }
            }
            if ($proprios !== []) {
                $bits = $proprios;
            }
            $tem = 0;
            $total = 0;
            foreach ($bits as $nome => $b) {
                $total += self::contarBits($b);
                $tem   += self::contarBits(($d[$nome] ?? 0) & $b);
            }
            $situacao[$chave] = $tem === $total ? 'sim' : ($tem === 0 ? 'nao' : 'parcial');
        }
        return $situacao;
    }

    private static function contarBits(int $n): int
    {
        return substr_count(decbin($n), '1');
    }

    /**
     * Grava as áreas de um perfil. $estado: [área => '1' liga, '0' desliga, outro valor mantém].
     * Desligar uma área não tira permissões que outra área ligada também usa;
     * permissões fora das áreas não mudam.
     */
    public static function salvarAreas(int $perfil, array $estado): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        if ($perfil === self::PERFIL_TOTAL) {
            return;
        }
        // Quem está usando a tela não tira de si mesmo o acesso a ela
        if ($perfil === (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0)) {
            $estado['perfis'] = '1';
        }

        $antes = self::situacao($perfil);
        $ligar = $desligar = $manterLigadas = [];
        foreach (self::AREAS as $chave => $area) {
            $v = (string) ($estado[$chave] ?? '');
            if ($v === '1') {
                $ligar[] = $chave;
            } elseif ($v === '0') {
                $desligar[] = $chave;
            } elseif ($antes[$chave] === 'sim') {
                $manterLigadas[] = $chave;
            }
        }

        $atuais = self::direitos($perfil);
        $nomes = [];
        foreach (self::AREAS as [, , , $bits]) {
            $nomes += array_fill_keys(array_keys($bits), true);
        }
        foreach (array_keys($nomes) as $nome) {
            $novo = $atuais[$nome] ?? 0;
            foreach ($desligar as $a) {
                $novo &= ~(self::AREAS[$a][3][$nome] ?? 0);
            }
            foreach (array_merge($ligar, $manterLigadas) as $a) {
                $novo |= self::AREAS[$a][3][$nome] ?? 0;
            }
            if (!array_key_exists($nome, $atuais)) {
                if ($novo !== 0) {
                    $DB->insert('glpi_profilerights', ['profiles_id' => $perfil, 'name' => $nome, 'rights' => $novo]);
                }
            } elseif ($novo !== $atuais[$nome]) {
                $DB->update('glpi_profilerights', ['rights' => $novo], ['profiles_id' => $perfil, 'name' => $nome]);
            }
        }
        $DB->update('glpi_profiles', ['date_mod' => $_SESSION['glpi_currenttime']], ['id' => $perfil]);
    }

    public static function salvarDescricao(int $perfil, string $texto): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $atual = (string) ($DB->request(['SELECT' => 'comment', 'FROM' => 'glpi_profiles', 'WHERE' => ['id' => $perfil]])->current()['comment'] ?? '');
        // Mantém a marca do perfil individual no fim do comentário
        $marca = str_contains($atual, self::MARCA_INDIVIDUAL) ? substr($atual, strpos($atual, self::MARCA_INDIVIDUAL)) : '';
        $DB->update('glpi_profiles', ['comment' => trim(mb_substr(trim($texto), 0, 500) . ($marca !== '' ? "\n" . $marca : ''))], ['id' => $perfil]);
    }

    /** Perfis para a tela: comuns primeiro, individuais depois. */
    public static function perfis(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $lista = [];
        foreach ($DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['interface' => 'central'], 'ORDER' => 'name']) as $p) {
            $comentario = (string) $p['comment'];
            $individual = preg_match('/\[individual:(\d+)\]/', $comentario, $m) ? (int) $m[1] : 0;
            $membros = (int) $DB->request([
                'SELECT'     => ['COUNT DISTINCT' => 'glpi_profiles_users.users_id AS cpt'],
                'FROM'       => 'glpi_profiles_users',
                'INNER JOIN' => ['glpi_users' => ['ON' => ['glpi_users' => 'id', 'glpi_profiles_users' => 'users_id']]],
                'WHERE'      => ['glpi_profiles_users.profiles_id' => $p['id'], 'glpi_users.is_active' => 1, 'glpi_users.is_deleted' => 0],
            ])->current()['cpt'];
            $lista[] = [
                'id'         => (int) $p['id'],
                'nome'       => (string) $p['name'],
                'descricao'  => trim(preg_replace('/\[individual:\d+\]/', '', $comentario)),
                'membros'    => $membros,
                'individual' => $individual,
                'total'      => (int) $p['id'] === self::PERFIL_TOTAL,
                'situacao'   => self::situacao((int) $p['id']),
            ];
        }
        usort($lista, fn ($a, $b) => [$a['individual'] > 0, $a['total'] ? 0 : 1, -$a['membros']] <=> [$b['individual'] > 0, $b['total'] ? 0 : 1, -$b['membros']]);
        return $lista;
    }

    /** Usuários ativos para a escolha do "usuário específico". */
    public static function usuarios(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $lista = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'firstname', 'realname', 'profiles_id'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['is_active' => 1, 'is_deleted' => 0, ['NOT' => ['name' => ['LIKE', 't.%']]]],
            'ORDER'  => ['firstname', 'realname', 'name'],
        ]) as $u) {
            $nome = trim($u['firstname'] . ' ' . $u['realname']);
            $lista[] = ['id' => (int) $u['id'], 'nome' => $nome !== '' ? $nome . ' (' . $u['name'] . ')' : $u['name']];
        }
        return $lista;
    }

    /** Situação de um usuário: perfis que tem e o perfil individual (se houver). */
    public static function usuario(int $users_id): ?array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $u = $DB->request(['FROM' => 'glpi_users', 'WHERE' => ['id' => $users_id]])->current();
        if (!$u) {
            return null;
        }
        $perfis = [];
        $individual = 0;
        foreach ($DB->request([
            'SELECT'     => ['glpi_profiles.id', 'glpi_profiles.name', 'glpi_profiles.comment', 'glpi_profiles_users.is_dynamic'],
            'FROM'       => 'glpi_profiles_users',
            'INNER JOIN' => ['glpi_profiles' => ['ON' => ['glpi_profiles' => 'id', 'glpi_profiles_users' => 'profiles_id']]],
            'WHERE'      => ['glpi_profiles_users.users_id' => $users_id],
        ]) as $p) {
            if (str_contains((string) $p['comment'], self::MARCA_INDIVIDUAL . $users_id . ']')) {
                $individual = (int) $p['id'];
            }
            $perfis[(int) $p['id']] = (string) $p['name'] . ($p['is_dynamic'] ? ' (pelo AD)' : '');
        }
        return [
            'id'         => $users_id,
            'nome'       => trim($u['firstname'] . ' ' . $u['realname']) ?: (string) $u['name'],
            'login'      => (string) $u['name'],
            'perfis'     => array_values($perfis),
            'padrao'     => (int) $u['profiles_id'],
            'individual' => $individual,
            'situacao'   => $individual ? self::situacao($individual) : [],
        ];
    }

    /**
     * Cria o perfil individual do usuário (cópia do perfil padrão dele) e o torna o perfil padrão.
     */
    public static function criarIndividual(int $users_id): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $u = self::usuario($users_id);
        if ($u === null) {
            return 0;
        }
        if ($u['individual']) {
            return $u['individual'];
        }
        $base = $u['padrao'] ?: self::PERFIL_PADRAO;
        if ($base === self::PERFIL_TOTAL) {
            // Quem já tem acesso total não precisa de perfil individual
            return 0;
        }
        $origem = $DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['id' => $base]])->current();
        if (!$origem) {
            return 0;
        }

        $copia = [];
        foreach (array_keys($DB->listFields('glpi_profiles')) as $coluna) {
            if (!in_array($coluna, ['id', 'name', 'comment', 'is_default', 'date_mod', 'date_creation'], true)) {
                $copia[$coluna] = $origem[$coluna];
            }
        }
        $copia['name']          = mb_substr('Individual - ' . $u['nome'], 0, 255);
        $copia['comment']       = 'Acessos só deste usuário (baseado em "' . $origem['name'] . '").' . "\n" . self::MARCA_INDIVIDUAL . $users_id . ']';
        $copia['is_default']    = 0;
        $copia['date_mod']      = $_SESSION['glpi_currenttime'];
        $copia['date_creation'] = $_SESSION['glpi_currenttime'];
        $DB->insert('glpi_profiles', $copia);
        $novo = (int) $DB->insertId();

        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $base]]) as $r) {
            $DB->insert('glpi_profilerights', ['profiles_id' => $novo, 'name' => $r['name'], 'rights' => $r['rights']]);
        }
        $DB->update('glpi_profiles_users', ['is_default_profile' => 0], ['users_id' => $users_id]);
        $DB->insert('glpi_profiles_users', [
            'users_id'           => $users_id,
            'profiles_id'        => $novo,
            'entities_id'        => 0,
            'is_recursive'       => 1,
            'is_dynamic'         => 0,
            'is_default_profile' => 1,
        ]);
        $DB->update('glpi_users', ['profiles_id' => $novo], ['id' => $users_id]);
        return $novo;
    }

    /** Desfaz o perfil individual: o usuário volta ao perfil que tinha. */
    public static function removerIndividual(int $users_id): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $u = self::usuario($users_id);
        if ($u === null || !$u['individual']) {
            return;
        }
        $perfil = $u['individual'];
        $DB->delete('glpi_profiles_users', ['profiles_id' => $perfil]);
        $DB->delete('glpi_profilerights', ['profiles_id' => $perfil]);
        $DB->delete('glpi_profiles', ['id' => $perfil]);

        $resto = $DB->request([
            'SELECT' => 'profiles_id',
            'FROM'   => 'glpi_profiles_users',
            'WHERE'  => ['users_id' => $users_id],
            'ORDER'  => 'is_dynamic DESC',
            'LIMIT'  => 1,
        ])->current();
        $volta = $resto ? (int) $resto['profiles_id'] : self::PERFIL_PADRAO;
        if (!$resto) {
            $DB->insert('glpi_profiles_users', [
                'users_id' => $users_id, 'profiles_id' => $volta, 'entities_id' => 0,
                'is_recursive' => 1, 'is_dynamic' => 0, 'is_default_profile' => 1,
            ]);
        } else {
            $DB->update('glpi_profiles_users', ['is_default_profile' => 1], ['users_id' => $users_id, 'profiles_id' => $volta]);
        }
        $DB->update('glpi_users', ['profiles_id' => $volta], ['id' => $users_id]);
    }
}
