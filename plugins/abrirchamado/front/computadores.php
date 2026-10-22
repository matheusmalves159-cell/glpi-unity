<?php

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Abrirchamado\Computadores;

Session::checkLoginUser();
if (!Computadores::canView()) {
    throw new AccessDeniedHttpException();
}

$d   = Computadores::painel();
$h   = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
$url = $CFG_GLPI['root_doc'];

Html::header(Computadores::getTypeName(), $_SERVER['PHP_SELF'], 'assets', Computadores::class);

$cartao = function (string $titulo, int $valor, string $classe, string $ajuda) use ($h) {
    return '<div class="ac-comp-cartao ' . $classe . '"><div class="ac-comp-num">' . $valor . '</div><div class="ac-comp-tit">' . $h($titulo) . '</div><div class="ac-comp-ajuda">' . $h($ajuda) . '</div></div>';
};
?>
<style>
    .ac-comp { padding: 0 4px; }
    .ac-comp-cartoes { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .ac-comp-cartao { border: 1px solid var(--tblr-border-color, #d8dce3); border-left-width: 5px; border-radius: 8px; padding: 12px 14px; background: var(--tblr-bg-surface, #fff); }
    .ac-comp-cartao.ac-comp-ok { border-left-color: #2fb344; }
    .ac-comp-cartao.ac-comp-medio { border-left-color: #f59f00; }
    .ac-comp-cartao.ac-comp-ruim { border-left-color: #c2410c; }
    .ac-comp-cartao.ac-comp-info { border-left-color: #206bc4; }
    .ac-comp-num { font-size: 1.9rem; font-weight: 700; line-height: 1.1; }
    .ac-comp-tit { font-weight: 600; }
    .ac-comp-ajuda { font-size: .85rem; opacity: .75; }
    .ac-comp h3 { margin: 22px 0 8px; font-size: 1.1rem; }
    .ac-comp table { width: 100%; border-collapse: collapse; }
    .ac-comp th, .ac-comp td { padding: 7px 10px; border-bottom: 1px solid var(--tblr-border-color, #e6e8ec); text-align: left; vertical-align: top; }
    .ac-comp th { font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; opacity: .7; }
    .ac-comp-etiqueta { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .8rem; margin: 1px 4px 1px 0; background: rgba(245,159,0,.18); }
    .ac-comp-etiqueta.ac-comp-ruim { background: rgba(194,65,12,.18); }
    .ac-comp-etiqueta.ac-comp-ok { background: rgba(47,179,68,.18); }
</style>
<div class="ac-comp">
    <p>Veja quais computadores precisam de atenção. Os dados vêm do inventário automático e se atualizam sozinhos.</p>

    <div class="ac-comp-cartoes">
        <?= $cartao('Computadores', $d['total'], 'ac-comp-info', 'Inventariados no total') ?>
        <?= $cartao('Sem comunicação', $d['sem_contato'], $d['sem_contato'] ? 'ac-comp-medio' : 'ac-comp-ok', 'Mais de ' . Computadores::DIAS_SEM_CONTATO . ' dias sem falar com o inventário') ?>
        <?= $cartao('Windows ou servidor desatualizado', $d['desatualizados'], $d['desatualizados'] ? 'ac-comp-ruim' : 'ac-comp-ok', 'Sem suporte ou versão antiga') ?>
        <?= $cartao('Sem usuário', $d['sem_usuario'], $d['sem_usuario'] ? 'ac-comp-medio' : 'ac-comp-ok', 'Sem ninguém ligado ao computador') ?>
    </div>

    <h3>Sistemas operacionais</h3>
    <table>
        <thead><tr><th>Sistema</th><th>Versão</th><th>Computadores</th><th>Situação</th></tr></thead>
        <tbody>
        <?php foreach ($d['sistemas'] as $s) : [$texto, $classe] = Computadores::situacaoSistema($s['sistema'], $s['versao']); ?>
            <tr>
                <td><?= $h($s['sistema'] ?: 'Não informado') ?></td>
                <td><?= $h($s['versao']) ?></td>
                <td><?= (int) $s['total'] ?></td>
                <td><span class="ac-comp-etiqueta <?= $classe ?>"><?= $h($texto) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Computadores que precisam de atenção (<?= count($d['atencao']) ?>)</h3>
    <table>
        <thead><tr><th>Computador</th><th>Usuário</th><th>Sistema</th><th>Último contato</th><th>O que fazer</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($d['atencao'], 0, 200) as $c) : ?>
            <tr>
                <td><a href="<?= $url ?>/front/computer.form.php?id=<?= (int) $c['id'] ?>"><?= $h($c['name']) ?></a></td>
                <td><?= $h($c['usuario'] ?: ($c['contact'] ?: '—')) ?></td>
                <td><?= $h(trim($c['sistema'] . ' ' . $c['versao'])) ?></td>
                <td><?= $c['ultimo_contato'] ? $h(date('d/m/Y H:i', strtotime($c['ultimo_contato']))) : 'Nunca' ?></td>
                <td><?php foreach ($c['motivos'] as $m) : ?><span class="ac-comp-etiqueta"><?= $h($m) ?></span><?php endforeach; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$d['atencao']) : ?><tr><td colspan="5">Nenhum computador precisa de atenção.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
Html::footer();
