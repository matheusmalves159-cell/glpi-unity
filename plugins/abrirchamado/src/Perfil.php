<?php

namespace GlpiPlugin\Abrirchamado;

use CommonGLPI;
use Profile;
use ProfileRight;
use Session;

/**
 * Aba "Módulos da Empresa" na tela de Perfis do GLPI (Administração > Perfis).
 *
 * Cada módulo nosso (relatórios, reservas de sala, pagamentos, telas de abertura) passa a ter
 * permissão própria, em vez de depender de "ser Super-Admin". Os direitos ficam na mesma tabela
 * de permissões do GLPI (glpi_profilerights), então valem para qualquer perfil e para o
 * resumo do perfil.
 */
class Perfil extends CommonGLPI
{
    public static $rightname = 'profile';

    /** Perfil com acesso total: não é alterado por esta aba. */
    public const PERFIL_TOTAL = 4;

    /**
     * nome do direito => [ícone, título, explicação, [bit => frase do que libera]]
     * (READ = 1, UPDATE = 2)
     */
    public const DIREITOS = [
        'empresa_relatorios' => [
            'ti-chart-bar', 'Relatórios',
            'Painel de relatórios de chamados e exportação para Excel e PDF.',
            [READ => 'Ver os relatórios e exportar'],
        ],
        'empresa_reservas' => [
            'ti-calendar-event', 'Reservas de sala de reunião',
            'Todo colaborador já agenda salas e vê as próprias reservas. Aqui fica o que é além disso.',
            [READ => 'Ver as reservas de toda a empresa', UPDATE => 'Cancelar e remarcar reservas de outras pessoas'],
        ],
        'empresa_salas' => [
            'ti-door', 'Cadastro das salas',
            'Cadastrar as salas, os horários e a integração com a agenda do Google.',
            [UPDATE => 'Cadastrar e configurar as salas'],
        ],
        'empresa_pagamentos' => [
            'ti-cash', 'Pagamentos',
            'Quem solicita pagamento é definido pelos grupos configurados no módulo. Aqui fica o acesso extra.',
            [READ => 'Ver todos os pedidos e gerar a planilha', UPDATE => 'Configurar setores e locais de pagamento'],
        ],
        'empresa_telas' => [
            'ti-layout-grid', 'Telas de abertura de chamado',
            'As telas por setor (TI, Administrativo...) com categorias, campos e cartões.',
            [UPDATE => 'Criar e configurar as telas de abertura'],
        ],
    ];

    public static function getTypeName($nb = 0)
    {
        return 'Módulos da Empresa';
    }

    public static function getIcon()
    {
        return 'ti ti-building';
    }

    // ---------------------------------------------------------------- aba no perfil

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getID() > 0 && ($item->fields['interface'] ?? '') === 'central') {
            return self::createTabEntry(self::getTypeName(), 0, null, self::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            self::formulario((int) $item->getID());
        }
        return true;
    }

    private static function formulario(int $perfil): void
    {
        $atuais = self::direitos($perfil);
        $total = $perfil === self::PERFIL_TOTAL;
        $pode = !$total && Session::haveRight('profile', UPDATE);
        $acao = htmlescape($GLOBALS['CFG_GLPI']['root_doc'] . '/plugins/abrirchamado/front/perfil.form.php');

        echo '<div class="ac-perfil-empresa p-3">';
        echo '<p class="text-muted mb-3 ac-livre">Permissões dos módulos da empresa. ' . ($total
            ? 'Este perfil tem acesso total e não é alterado aqui.'
            : 'Marque o que as pessoas deste perfil podem fazer.') . '</p>';
        echo '<form method="post" action="' . $acao . '">';
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . htmlescape(Session::getNewCSRFToken()) . '">';
        echo '<input type="hidden" name="profiles_id" value="' . $perfil . '">';

        foreach (self::DIREITOS as $nome => [$icone, $titulo, $texto, $bits]) {
            $valor = $total ? 255 : (int) ($atuais[$nome] ?? 0);
            echo '<div class="ac-perfil-linha">';
            echo '<span class="ac-perfil-icone"><i class="ti ' . $icone . '"></i></span>';
            echo '<div class="ac-perfil-corpo">';
            echo '<div class="fw-bold">' . htmlescape($titulo) . '</div>';
            echo '<div class="text-muted small ac-livre">' . htmlescape($texto) . '</div>';
            echo '<div class="ac-perfil-opcoes">';
            foreach ($bits as $bit => $frase) {
                $id = 'ac-' . $nome . '-' . $bit;
                echo '<label class="form-check mb-0" for="' . $id . '">';
                echo '<input class="form-check-input" type="checkbox" id="' . $id . '" name="d[' . $nome . '][' . $bit . ']" value="1"'
                    . (($valor & $bit) === $bit ? ' checked' : '') . ($pode ? '' : ' disabled') . '>';
                echo '<span class="form-check-label">' . htmlescape($frase) . '</span></label>';
            }
            echo '</div></div></div>';
        }

        if ($pode) {
            echo '<div class="mt-3 text-end"><button type="submit" name="salvar" value="1" class="btn btn-primary">'
                . '<i class="ti ti-device-floppy"></i><span>Salvar</span></button></div>';
        }
        echo '</form></div>';
    }

    // ---------------------------------------------------------------- resumo

    /**
     * O que o perfil pode fazer, em frases simples: [grupo => [[frase, liberado], ...]].
     * Bits dos chamados: 1 ver os meus, 2 editar, 4 criar, 8 lixeira, 1024 ver todos, 2048 ver do grupo,
     * 4096 ver atribuídos, 8192 atribuir, 16384 assumir, 65536 mudar prioridade.
     */
    public static function frases(int $perfil): array
    {
        $d = self::direitos($perfil);
        $tem = static fn(string $nome, int $bits): bool => (($d[$nome] ?? 0) & $bits) !== 0;
        $total = $perfil === self::PERFIL_TOTAL;

        $frases = [
            'Chamados' => [
                // O botão "Abrir Chamados" funciona para qualquer usuário logado (não depende do direito "criar")
                ['Abrir chamados pelo botão "Abrir Chamados"', true],
                ['Acompanhar os próprios chamados', $tem('ticket', 1)],
                ['Ver chamados do próprio setor e os atribuídos a ele', $tem('ticket', 2048 | 4096)],
                ['Ver chamados de todos os setores', $tem('ticket', 1024)],
                ['Editar o chamado de outras pessoas', $tem('ticket', 2)],
                ['Atribuir a técnicos e grupos', $tem('ticket', 8192)],
                ['Assumir chamado de outro técnico', $tem('ticket', 16384)],
                ['Mudar a prioridade', $tem('ticket', 65536)],
                ['Responder e acompanhar a conversa', $tem('followup', 255 | 4 | 8192)],
                ['Pedir e dar aprovação', $tem('ticketvalidation', 1024 | 2048 | 4096 | 8192)],
                ['Colocar chamado na lixeira', $tem('ticket', 8)],
            ],
            'Consultas e equipamentos' => [
                ['Ver computadores e softwares', $tem('computer', 1)],
                ['Cadastrar e editar computadores e softwares', $tem('computer', 2 | 4)],
                ['Ler a base de conhecimento', $tem('knowbase', 1)],
                ['Escrever na base de conhecimento', $tem('knowbase', 2 | 4)],
                ['Ver as estatísticas de chamados', $tem('statistic', 1)],
            ],
            'Administração' => [
                ['Ver usuários e grupos', $tem('user', 1)],
                ['Cadastrar e editar usuários', $tem('user', 2 | 4)],
                ['Mexer nas categorias, SLA e notificações', $tem('itilcategory', 2) || $tem('slm', 2) || $tem('notification', 2)],
                ['Alterar permissões de perfis', $tem('profile', 2)],
                ['Alterar a configuração geral do GLPI', $tem('config', 2)],
            ],
            'Módulos da empresa' => [],
        ];
        foreach (self::DIREITOS as $nome => [, , , $bits]) {
            foreach ($bits as $bit => $frase) {
                $frases['Módulos da empresa'][] = [$frase, $total || $tem($nome, $bit)];
            }
        }
        return $frases;
    }

    /** Cartão "O que este perfil pode fazer", no alto da aba Perfil. */
    public static function resumo(int $perfil): void
    {
        echo '<div class="card-body pb-0"><div class="ac-resumo-perfil">';
        echo '<div class="ac-resumo-titulo"><i class="ti ti-list-check"></i> O que este perfil pode fazer</div>';
        echo '<div class="ac-resumo-grade">';
        // Grupos em que o perfil não pode nada viram uma linha só, no fim
        $semAcesso = [];
        $grupos = [];
        foreach (self::frases($perfil) as $grupo => $itens) {
            $algum = false;
            foreach ($itens as [, $sim]) {
                $algum = $algum || $sim;
            }
            if ($algum) {
                $grupos[$grupo] = $itens;
            } else {
                $semAcesso[] = $grupo;
            }
        }
        foreach ($grupos as $grupo => $itens) {
            echo '<div class="ac-resumo-grupo"><div class="ac-resumo-grupo-nome">' . htmlescape($grupo) . '</div>';
            foreach ($itens as [$texto, $sim]) {
                echo '<div class="ac-resumo-item ' . ($sim ? 'sim' : 'nao') . ' ac-livre">'
                    . '<i class="ti ' . ($sim ? 'ti-circle-check' : 'ti-circle-dashed') . '"></i>'
                    . '<span>' . htmlescape($texto) . '</span></div>';
            }
            echo '</div>';
        }
        echo '</div>';
        if ($semAcesso !== []) {
            echo '<div class="ac-resumo-nota ac-livre"><i class="ti ti-lock"></i> Sem acesso a: '
                . htmlescape(implode(', ', array_map('mb_strtolower', $semAcesso))) . '.</div>';
        }
        echo '<div class="ac-resumo-nota ac-livre">Para ajustar, use as abas ao lado. '
            . 'A aba "Módulos da Empresa" libera relatórios, reservas, pagamentos e telas; as demais abas mostram o que o GLPI traz de fábrica.</div>';
        echo '</div></div>';
    }

    // ---------------------------------------------------------------- dados

    /** Direitos atuais do perfil: [nome => valor]. */
    public static function direitos(int $perfil): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $d = [];
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['profiles_id' => $perfil]]) as $r) {
            $d[$r['name']] = (int) $r['rights'];
        }
        return $d;
    }

    /** Grava o que foi marcado na aba. */
    public static function salvar(int $perfil, array $marcado): void
    {
        if ($perfil <= 0 || $perfil === self::PERFIL_TOTAL) {
            return;
        }
        $novos = [];
        foreach (self::DIREITOS as $nome => [, , , $bits]) {
            $soma = 0;
            foreach (array_keys($bits) as $bit) {
                if (!empty($marcado[$nome][$bit])) {
                    $soma |= $bit;
                }
            }
            $novos[$nome] = $soma;
        }
        ProfileRight::updateProfileRights($perfil, $novos);
    }

    // ---------------------------------------------------------------- instalação

    public static function instalar(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $nomes = array_keys(self::DIREITOS);
        // Só cria o que ainda não existe (a instalação pode rodar de novo ao atualizar o plugin)
        $novos = array_filter($nomes, static fn(string $nome): bool => countElementsInTable('glpi_profilerights', ['name' => $nome]) === 0);
        if ($novos !== []) {
            ProfileRight::addProfileRights(array_values($novos));
        }

        // Primeira instalação: mantém quem já tinha acesso (por "Configurar" ou "Estatísticas")
        $existentes = [];
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => $nomes]]) as $r) {
            $existentes[$r['profiles_id']][$r['name']] = (int) $r['rights'];
        }
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']) as $p) {
            $id = (int) $p['id'];
            if (array_sum($existentes[$id] ?? []) > 0) {
                continue; // já configurado
            }
            $d = self::direitos($id);
            $novos = [];
            if ($id === self::PERFIL_TOTAL || ($d['config'] ?? 0) >= 3) {
                foreach (self::DIREITOS as $nome => [, , , $bits]) {
                    $novos[$nome] = array_sum(array_keys($bits));
                }
            } elseif (($d['statistic'] ?? 0) >= 1) {
                $novos['empresa_relatorios'] = READ;
            }
            if ($novos !== []) {
                ProfileRight::updateProfileRights($id, $novos);
            }
        }
    }

    public static function desinstalar(): void
    {
        ProfileRight::deleteProfileRights(array_keys(self::DIREITOS));
    }
}
