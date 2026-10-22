<?php

namespace GlpiPlugin\Chamadoti;

use CommonDBTM;
use ITILCategory;
use Session;

/**
 * Tela de abertura de chamado de um setor (TI, Administrativo…), configurável
 * na Central de telas: textos, campos, cartões e campos extras por categoria.
 */
class Tela extends CommonDBTM
{
    public static $rightname = 'config';

    /** Campos extras que podem ser ligados a uma categoria (e às abaixo dela) */
    public const EXTRAS = [
        'impacto'        => 'Quem é afetado e desde quando (sugere a prioridade)',
        'equipamento'    => 'Qual equipamento e patrimônio',
        'emails'         => 'E-mails para configurar',
        'backup'         => 'Caminho do arquivo e versão desejada',
        'colaboradores'  => 'Colaboradores (criar acesso)',
        'itens'          => 'Itens e quantidades',
        'local'          => 'Local / unidade',
        'veiculo'        => 'Veículo (modelo e placa)',
        'agenda'         => 'Data, horário, origem e destino',
        'evento'         => 'Evento: data, participantes, suporte e itens',
        'evento_interno' => 'Evento interno: locais (salas, áreas)',
        'evento_externo' => 'Evento externo: empresa e localização',
        'procedimento'   => 'Procedimento (FacPlan): inserção ou alteração, código e tabela',
    ];

    /** Ícone e texto padrão dos cartões pelo nome da categoria */
    public const VISUAL_PADRAO = [
        'Erros'                   => ['ti-bug', 'Algo deu erro ou não consigo acessar um sistema'],
        'Acessos e Senhas'        => ['ti-key', 'Criar, cancelar ou alterar acessos e senhas'],
        'Equipamentos'            => ['ti-device-laptop', 'Conserto, troca ou novo equipamento'],
        'Instalação/Configuração' => ['ti-settings', 'Impressora, e-mail, ramal, Konsist e outros'],
        'Backup'                  => ['ti-database', 'Recuperar um arquivo apagado ou alterado'],
        'Desenvolvimento'         => ['ti-code', 'Melhorias e ajustes nos sistemas'],
        'FacPlan/Portal'          => ['ti-building-hospital', 'Cadastro, contas a pagar, financeiro, guias, relatórios e planos'],
        'Gravação de Ligação'     => ['ti-phone-call', 'Pedir a gravação de uma ligação (data, horário, ramal e número)'],
        'Relatórios'              => ['ti-report-analytics', 'Pedido de relatório'],
        'Contratação de Sistema'  => ['ti-shopping-cart', 'Avaliação de um novo sistema'],
        'Acesso às Câmeras'       => ['ti-camera', 'Acesso às imagens das câmeras'],
        'Dúvidas'                 => ['ti-help-circle', 'Tire uma dúvida'],
        'Manutenção'              => ['ti-tool', 'Conserto e manutenção predial ou de veículo'],
        'Compras'                 => ['ti-shopping-cart', 'Pedido de compra de materiais e equipamentos'],
        'Solicitação'             => ['ti-clipboard-list', 'Chaves, correio, motorista, arquivo e outros pedidos'],
        'Eventos'                 => ['ti-calendar-event', 'Organização de eventos internos e externos'],
    ];

    /** Tipos dos campos que o administrador cria nos pedidos */
    public const TIPOS_CAMPO = [
        'texto'       => 'Texto curto',
        'texto_longo' => 'Texto longo',
        'numero'      => 'Número',
        'data'        => 'Data',
        'data_hora'   => 'Data e hora',
        'lista'       => 'Lista (escolher uma opção)',
        'multipla'    => 'Caixas de marcar (várias opções)',
        'sim_nao'     => 'Sim ou não',
        'email'       => 'E-mail',
        'telefone'    => 'Telefone / ramal',
    ];

    /** Cores do cartão da tela no Abrir Chamados */
    public const CORES = [
        'azul' => 'Azul', 'amarelo' => 'Amarelo', 'roxo' => 'Roxo',
        'verde' => 'Verde', 'teal' => 'Verde-água', 'laranja' => 'Laranja',
    ];

    public const DESCRICAO_MODOS = ['opcional' => 'Opcional', 'obrigatoria' => 'Obrigatória', 'oculta' => 'Escondida'];

    public const TIPOS_CHAMADO = [
        'auto'      => 'Automático (incidente quando o pedido pergunta o impacto; senão requisição)',
        'incidente' => 'Sempre incidente',
        'requisicao' => 'Sempre requisição',
    ];

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Telas de chamado' : 'Tela de chamado';
    }

    public static function getIcon()
    {
        return 'ti ti-layout-grid';
    }

    public static function canView(): bool
    {
        return Session::haveRight('empresa_telas', UPDATE);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight('empresa_telas', UPDATE);
    }

    /**
     * Telas ativas, na ordem da configuração.
     *
     * @return array<int, array>
     */
    public static function ativas(): array
    {
        return (new self())->find(['is_active' => 1], ['ordem ASC', 'nome ASC']);
    }

    /**
     * Configuração por categoria: ['cats' => [id => [oculto, ordem, icone, texto, extras[], link]]].
     */
    public function config(): array
    {
        $c = json_decode((string) ($this->fields['config'] ?? ''), true);
        $c = is_array($c) ? $c : [];
        $c['cats'] = is_array($c['cats'] ?? null) ? $c['cats'] : [];
        return $c;
    }

    public function configCategoria(int $id): array
    {
        $c = $this->config()['cats'][(string) $id] ?? [];
        return [
            'oculto' => !empty($c['oculto']),
            'ordem'  => (int) ($c['ordem'] ?? 0),
            'icone'  => (string) ($c['icone'] ?? ''),
            'texto'  => (string) ($c['texto'] ?? ''),
            'extras' => array_values(array_intersect((array) ($c['extras'] ?? []), array_keys(self::EXTRAS))),
            'link'   => (string) ($c['link'] ?? ''),
            'campos' => self::normalizarCampos((array) ($c['campos'] ?? [])),
        ];
    }

    /**
     * Opções da tela (cor, descrição, prefixo do título, quem vê...), com valores padrão.
     */
    public function opcoes(): array
    {
        return self::normalizarOpcoes((array) ($this->config()['opcoes'] ?? []));
    }

    /** Opções vindas do formulário ou da configuração gravada */
    public static function normalizarOpcoes(array $o): array
    {
        $txt = static fn (string $k, int $max): string => mb_substr(trim(str_replace("\r", '', (string) ($o[$k] ?? ''))), 0, $max);
        $cor = (string) ($o['cor'] ?? '');
        $modo = (string) ($o['desc_modo'] ?? '');
        $tipo = (string) ($o['tipo'] ?? '');
        return [
            'cor'            => isset(self::CORES[$cor]) ? $cor : '',
            'desc_modo'      => isset(self::DESCRICAO_MODOS[$modo]) ? $modo : 'opcional',
            'desc_ajuda'     => $txt('desc_ajuda', 300),
            'titulo_dica'    => $txt('titulo_dica', 150),
            'titulo_prefixo' => $txt('titulo_prefixo', 40),
            'tipo'           => isset(self::TIPOS_CHAMADO[$tipo]) ? $tipo : 'auto',
            'msg_sucesso'    => $txt('msg_sucesso', 300),
            'grupos_ver'     => array_values(array_unique(array_filter(array_map('intval', (array) ($o['grupos_ver'] ?? [])), fn ($g) => $g > 0))),
        ];
    }

    /**
     * Campos criados pelo administrador num pedido: [id, rotulo, tipo, obrigatorio, opcoes[], ajuda].
     */
    public static function normalizarCampos(array $lista): array
    {
        $saida = [];
        foreach ($lista as $c) {
            if (!is_array($c) || count($saida) >= 20) {
                continue;
            }
            $rotulo = mb_substr(trim((string) ($c['rotulo'] ?? '')), 0, 100);
            if ($rotulo === '') {
                continue;
            }
            $tipo = (string) ($c['tipo'] ?? 'texto');
            $tipo = isset(self::TIPOS_CAMPO[$tipo]) ? $tipo : 'texto';
            $opcoes = $c['opcoes'] ?? [];
            if (is_string($opcoes)) {
                $opcoes = preg_split('/\R/', $opcoes) ?: [];
            }
            $opcoes = array_slice(array_values(array_unique(array_filter(
                array_map(static fn ($o) => mb_substr(trim((string) $o), 0, 100), (array) $opcoes),
                static fn ($o) => $o !== ''
            ))), 0, 50);
            if (in_array($tipo, ['lista', 'multipla'], true) && $opcoes === []) {
                $tipo = 'texto';
            }
            $id = (string) ($c['id'] ?? '');
            $saida[] = [
                'id'          => preg_match('/^[a-z0-9]{4,12}$/', $id) ? $id : substr(md5(uniqid('', true)), 0, 8),
                'rotulo'      => $rotulo,
                'tipo'        => $tipo,
                'obrigatorio' => !empty($c['obrigatorio']),
                'opcoes'      => in_array($tipo, ['lista', 'multipla'], true) ? $opcoes : [],
                'ajuda'       => mb_substr(trim((string) ($c['ajuda'] ?? '')), 0, 200),
            ];
        }
        return $saida;
    }

    /**
     * A tela aparece para quem está logado? Sem grupos escolhidos = todos.
     * Quem administra as telas sempre vê.
     */
    public static function visivelParaMim(array $linha): bool
    {
        $cfg = json_decode((string) ($linha['config'] ?? ''), true);
        $grupos = array_filter(array_map('intval', (array) ($cfg['opcoes']['grupos_ver'] ?? [])));
        if ($grupos === [] || Session::haveRight('empresa_telas', UPDATE)) {
            return true;
        }
        return array_intersect($grupos, array_map('intval', (array) ($_SESSION['glpigroups'] ?? []))) !== [];
    }

    /** Telas ativas que o usuário logado pode ver */
    public static function ativasParaMim(): array
    {
        return array_filter(self::ativas(), [self::class, 'visivelParaMim']);
    }

    /**
     * Categorias abaixo da raiz (com nível e caminho), para a tela de configuração.
     */
    public function categorias(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $raiz = (int) $this->fields['itilcategories_id'];
        if ($raiz <= 0) {
            return [];
        }
        $ids  = array_map('intval', array_keys(getSonsOf(ITILCategory::getTable(), $raiz)));
        $base = (int) ($DB->request(['SELECT' => 'level', 'FROM' => ITILCategory::getTable(), 'WHERE' => ['id' => $raiz]])->current()['level'] ?? 1);
        $lista = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'completename', 'level', 'itilcategories_id', 'is_request', 'is_incident'],
            'FROM'   => ITILCategory::getTable(),
            'WHERE'  => ['id' => $ids, 'NOT' => ['id' => $raiz]],
            'ORDER'  => 'completename',
        ]) as $c) {
            $lista[] = $c + [
                'nivel'   => (int) $c['level'] - $base,
                'visivel' => (int) $c['is_request'] === 1 || (int) $c['is_incident'] === 1,
                'cfg'     => $this->configCategoria((int) $c['id']),
            ];
        }
        return $lista;
    }

    public function prepareInputForAdd($input)
    {
        return $this->limpar($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->limpar($input);
    }

    private function limpar(array $input): array
    {
        foreach (['nome', 'titulo', 'icone', 'descricao'] as $campo) {
            if (isset($input[$campo])) {
                $input[$campo] = mb_substr(trim((string) $input[$campo]), 0, 255);
            }
        }
        if (isset($input['icone'])) {
            $input['icone'] = preg_replace('/[^a-z0-9 -]/', '', mb_strtolower($input['icone']));
        }
        return $input;
    }
}
