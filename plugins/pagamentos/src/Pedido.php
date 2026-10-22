<?php

namespace GlpiPlugin\Pagamentos;

use CommonDBTM;
use CommonITILActor;
use Document;
use Group;
use Group_Ticket;
use Html;
use Session;
use Throwable;
use Ticket;
use TicketValidation;

/**
 * Solicitação de pagamento feita pela tela própria (abre chamado para o Financeiro).
 */
class Pedido extends CommonDBTM
{
    public static $rightname = 'config';

    public const FORMAS = [
        'cartao' => 'Cartão de Crédito',
        'pix'    => 'Transferência/PIX',
    ];

    /** Anexos aceitos (boleto, nota fiscal, orçamento) */
    public const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'doc', 'docx'];

    public const MAX_ANEXOS = 5;

    public const MAX_MB = 10;

    public static function getTypeName($nb = 0)
    {
        return 'Solicitar pagamento';
    }

    public static function getIcon()
    {
        return 'ti ti-cash';
    }

    /**
     * Pode abrir solicitação: grupos configurados (vazio = todos) e administradores.
     */
    public static function podeSolicitar(): bool
    {
        if (Session::getLoginUserID() === false) {
            return false;
        }
        $grupos = Settings::ids('request_groups_ids');
        if ($grupos === [] || Session::haveRight('empresa_pagamentos', UPDATE)) {
            return true;
        }
        return array_intersect($grupos, array_map('intval', (array) ($_SESSION['glpigroups'] ?? []))) !== [];
    }

    public static function canView(): bool
    {
        return self::podeSolicitar();
    }

    public static function canCreate(): bool
    {
        return self::podeSolicitar();
    }

    public static function getMenuContent()
    {
        return [
            'title' => self::getTypeName(),
            'page'  => '/plugins/pagamentos/front/solicitar.php',
            'icon'  => self::getIcon(),
            'links' => [
                'search' => '/plugins/pagamentos/front/minhas.php',
            ],
        ];
    }

    /**
     * Valida, abre o chamado para o Financeiro e anexa os arquivos.
     *
     * @return array{ok:bool, msg:string, tickets_id?:int}
     */
    public static function criar(array $in, array $arquivos): array
    {
        $texto = static fn (string $campo, int $max = 255): string => mb_substr(trim((string) ($in[$campo] ?? '')), 0, $max);

        $dados = [
            'local'       => $texto('local'),
            'fornecedor'  => $texto('fornecedor'),
            'documento'   => $texto('documento', 30),
            'valor'       => Dados::valor($in['valor'] ?? null),
            'servico'     => $texto('servico', 500),
            'setor'       => $texto('setor'),
            'forma'       => (string) ($in['forma'] ?? ''),
            'pix'         => $texto('pix'),
            'observacoes' => $texto('observacoes', 4000),
        ];

        $erros = [];
        if (!in_array($dados['local'], Settings::lista('locais'), true)) {
            $erros[] = 'Escolha o local referência da despesa.';
        }
        if ($dados['fornecedor'] === '') {
            $erros[] = 'Informe o nome do fornecedor.';
        }
        $digitos = preg_replace('/\D/', '', $dados['documento']);
        if (!in_array(strlen($digitos), [11, 14], true)) {
            $erros[] = 'Informe um CNPJ (14 dígitos) ou CPF (11 dígitos) válido.';
        }
        if ($dados['valor'] === null || $dados['valor'] <= 0) {
            $erros[] = 'Informe o valor do pagamento.';
        }
        if ($dados['servico'] === '') {
            $erros[] = 'Descreva o serviço ou produto.';
        }
        if (!in_array($dados['setor'], Settings::lista('setores'), true)) {
            $erros[] = 'Escolha o setor solicitante.';
        }
        if (!array_key_exists($dados['forma'], self::FORMAS)) {
            $erros[] = 'Escolha a forma de pagamento.';
        } elseif ($dados['forma'] === 'pix' && $dados['pix'] === '') {
            $erros[] = 'Informe os dados para transferência/PIX.';
        }
        $anexos = self::validarAnexos($arquivos, $erros);
        if ($erros !== []) {
            return ['ok' => false, 'msg' => implode(' ', $erros)];
        }

        // Dados de PIX só fazem sentido quando a forma é transferência/PIX
        if ($dados['forma'] !== 'pix') {
            $dados['pix'] = '';
        }
        $dados['forma']     = self::FORMAS[$dados['forma']];
        $dados['documento'] = self::formatarDocumento($digitos);

        $users_id = (int) Session::getLoginUserID();
        $grupo    = (int) Settings::get('groups_id_assign');

        $ticket = new Ticket();
        $tickets_id = (int) $ticket->add([
            'name'              => mb_substr(sprintf(
                'Solicitação de pagamento: %s — R$ %s',
                $dados['fornecedor'],
                number_format($dados['valor'], 2, ',', '.')
            ), 0, 255),
            'content'           => self::descricao($dados),
            'type'              => Ticket::DEMAND_TYPE,
            'itilcategories_id' => (int) Settings::get('itilcategories_id'),
            'entities_id'       => (int) ($_SESSION['glpiactive_entity'] ?? 0),
            '_actors'           => [
                'requester' => [[
                    'itemtype'          => 'User',
                    'items_id'          => $users_id,
                    'use_notification'  => 1,
                    'alternative_email' => '',
                ]],
                'assign'    => $grupo > 0 ? [['itemtype' => 'Group', 'items_id' => $grupo]] : [],
            ],
        ]);
        if ($tickets_id <= 0) {
            return ['ok' => false, 'msg' => 'Não foi possível abrir o chamado. Tente novamente.'];
        }

        // Quem não tem direito de atribuir não consegue atribuir na abertura: garante o Financeiro
        if ($grupo > 0 && countElementsInTable(Group_Ticket::getTable(), [
            'tickets_id' => $tickets_id, 'groups_id' => $grupo, 'type' => CommonITILActor::ASSIGN,
        ]) === 0) {
            (new Group_Ticket())->add(['tickets_id' => $tickets_id, 'groups_id' => $grupo, 'type' => CommonITILActor::ASSIGN]);
        }

        // Só o Financeiro aprova ou recusa a compra (aprovação do GLPI direcionada ao grupo)
        if ($grupo > 0) {
            (new TicketValidation())->add([
                'tickets_id'         => $tickets_id,
                'itemtype_target'    => Group::class,
                'items_id_target'    => $grupo,
                'comment_submission' => 'Aprovar ou recusar esta solicitação de pagamento.',
            ]);
        }

        (new self())->add($dados + ['tickets_id' => $tickets_id, 'users_id' => $users_id]);

        $falhas = self::anexar($anexos, $tickets_id, (int) $ticket->fields['entities_id']);
        $msg = sprintf('Solicitação de pagamento enviada. Chamado nº %d.', $tickets_id);
        if ($falhas > 0) {
            $msg .= sprintf(' Atenção: %d anexo(s) não puderam ser salvos; anexe pelo chamado.', $falhas);
        }
        return ['ok' => true, 'msg' => $msg, 'tickets_id' => $tickets_id];
    }

    /**
     * @return array<int, array{nome:string, tmp:string}>
     */
    private static function validarAnexos(array $arquivos, array &$erros): array
    {
        $lista = [];
        $nomes = (array) ($arquivos['name'] ?? []);
        foreach ($nomes as $i => $nome) {
            if ((int) ($arquivos['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || $nome === '') {
                continue;
            }
            if ((int) $arquivos['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivos['tmp_name'][$i])) {
                $erros[] = sprintf('Não foi possível receber o arquivo "%s".', $nome);
                continue;
            }
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSOES, true)) {
                $erros[] = sprintf('O arquivo "%s" não é aceito (use PDF, imagem, Excel ou Word).', $nome);
                continue;
            }
            if ((int) $arquivos['size'][$i] > self::MAX_MB * 1024 * 1024) {
                $erros[] = sprintf('O arquivo "%s" passa de %d MB.', $nome, self::MAX_MB);
                continue;
            }
            $lista[] = ['nome' => $nome, 'tmp' => $arquivos['tmp_name'][$i]];
        }
        if (count($lista) > self::MAX_ANEXOS) {
            $erros[] = sprintf('Envie no máximo %d anexos.', self::MAX_ANEXOS);
        }
        return $lista;
    }

    /**
     * Anexa os arquivos ao chamado. Devolve quantos falharam.
     */
    private static function anexar(array $anexos, int $tickets_id, int $entities_id): int
    {
        $falhas = 0;
        foreach ($anexos as $anexo) {
            try {
                // O GLPI move o arquivo a partir da pasta temporária dele
                $seguro = preg_replace('/[^A-Za-z0-9._-]+/', '_', $anexo['nome']);
                $prefixo = uniqid('', true) . '_';
                if (!move_uploaded_file($anexo['tmp'], GLPI_TMP_DIR . '/' . $prefixo . $seguro)) {
                    $falhas++;
                    continue;
                }
                $ok = (new Document())->add([
                    'name'              => $anexo['nome'],
                    'entities_id'       => $entities_id,
                    'is_recursive'      => 0,
                    'itemtype'          => Ticket::class,
                    'items_id'          => $tickets_id,
                    '_filename'         => [$prefixo . $seguro],
                    '_prefix_filename'  => [$prefixo],
                ]);
                if (!$ok) {
                    $falhas++;
                }
            } catch (Throwable $e) {
                $falhas++;
            }
        }
        return $falhas;
    }

    private static function formatarDocumento(string $digitos): string
    {
        return strlen($digitos) === 14
            ? vsprintf('%s.%s.%s/%s-%s', [substr($digitos, 0, 2), substr($digitos, 2, 3), substr($digitos, 5, 3), substr($digitos, 8, 4), substr($digitos, 12, 2)])
            : vsprintf('%s.%s.%s-%s', [substr($digitos, 0, 3), substr($digitos, 3, 3), substr($digitos, 6, 3), substr($digitos, 9, 2)]);
    }

    private static function descricao(array $d): string
    {
        $linhas = [
            'Fornecedor'           => $d['fornecedor'],
            'CNPJ/CPF'             => $d['documento'],
            'Valor'                => 'R$ ' . number_format($d['valor'], 2, ',', '.'),
            'Descrição'            => $d['servico'],
            'Setor solicitante'    => $d['setor'],
            'Forma de pagamento'   => $d['forma'],
            'Dados para PIX'       => $d['pix'],
            'Local da despesa'     => $d['local'],
            'Detalhes'             => $d['observacoes'],
        ];
        $html = '<p>Solicitação de pagamento enviada pela tela do GLPI.</p><table>';
        foreach ($linhas as $rotulo => $valor) {
            if ($valor === '') {
                continue;
            }
            $html .= sprintf('<tr><td><strong>%s</strong></td><td>%s</td></tr>', htmlescape($rotulo), nl2br(htmlescape($valor)));
        }
        return $html . '</table>';
    }

    /**
     * Cabeçalho da página nas duas interfaces.
     */
    public static function cabecalho(string $titulo): void
    {
        if (Session::getCurrentInterface() === 'helpdesk') {
            Html::helpHeader($titulo);
        } else {
            Html::header($titulo, '', 'helpdesk', self::class);
        }
    }

    public static function rodape(): void
    {
        if (Session::getCurrentInterface() === 'helpdesk') {
            Html::helpFooter();
        } else {
            Html::footer();
        }
    }
}
