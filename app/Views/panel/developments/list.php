<?= $this->extend('panel/layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var list<array> $rows
 * @var string|null $kind Filtro por tipo (desarrollo | proyecto)
 * @var string $responsable Filtro por programador ('' | 'none' | IdUser)
 * @var list<array> $agents
 */
use App\Libraries\DevelopmentBoard as Board;

$isAdmin = panel_is_admin();
?>
<a class="back-link" href="<?= site_url('panel/desarrollos') ?>"><?= icon('arrow-left') ?> Plan del mes</a>

<div class="page-head">
    <div>
        <h1><?= panel_is_admin() ? 'Desarrollos' : 'Mis desarrollos' ?></h1>
        <p class="muted">Todo lo que nació de un ticket de desarrollo o de proyecto, de todos los meses.</p>
    </div>
    <?php if (Board::projectsAvailable()): ?>
        <div class="month-nav">
            <a class="btn btn-sm <?= $kind === null ? 'btn-primary' : 'btn-outline' ?>" href="<?= site_url('panel/desarrollos/todos' . ($responsable !== '' ? '?responsable=' . rawurlencode($responsable) : '')) ?>">Todos</a>
            <?php foreach (Board::KINDS as $k => $label): ?>
                <a class="btn btn-sm <?= $kind === $k ? 'btn-primary' : 'btn-outline' ?>" href="<?= site_url('panel/desarrollos/todos?tipo=' . $k . ($responsable !== '' ? '&responsable=' . rawurlencode($responsable) : '')) ?>"><?= $label ?>s</a>
            <?php endforeach ?>
            <a class="btn btn-outline btn-sm" href="<?= site_url('panel/desarrollos/proyectos') ?>">Reporte de proyectos</a>
        </div>
    <?php endif ?>
</div>

<section class="card table-card">
    <?php if ($isAdmin): ?>
        <form method="get" class="period" data-autosubmit style="padding:16px 20px 0">
            <?php if ($kind !== null): ?><input type="hidden" name="tipo" value="<?= esc($kind, 'attr') ?>"><?php endif ?>
            <select name="responsable" class="select" aria-label="Filtrar por responsable">
                <option value="">Todos los responsables</option>
                <option value="none" <?= $responsable === 'none' ? 'selected' : '' ?>>Sin asignar</option>
                <?php foreach ($agents as $a): ?>
                    <option value="<?= esc($a['IdUser'], 'attr') ?>" <?= $responsable === (string) $a['IdUser'] ? 'selected' : '' ?>><?= esc($a['FullName']) ?></option>
                <?php endforeach ?>
            </select>
            <noscript><button class="btn btn-outline btn-sm">Filtrar</button></noscript>
        </form>
    <?php endif ?>
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <?= icon('code', 'icon icon-xl') ?>
            <strong>Aún no hay desarrollos</strong>
            <span class="muted">Se crean al agregar un ticket de desarrollo al plan del mes.</span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Desarrollo</th>
                        <th>Mes</th>
                        <th>Prioridad</th>
                        <th>Programador</th>
                        <th>Estado</th>
                        <th>Avance</th>
                        <th>Inconvenientes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $d): ?>
                        <tr data-href="<?= site_url('panel/desarrollos/' . $d['IdDevelopment']) ?>">
                            <td class="cell-main">
                                <span class="mono muted small"><?= esc($d['Code']) ?> · <?= ticket_code($d['IdTicket']) ?> · Cía <?= esc($d['CodCompanies']) ?></span>
                                <span class="cell-title"><?= esc($d['Subject']) ?></span>
                                <?php if ($d['IsProject']): ?><div><?= dev_kind_chip($d) ?></div><?php endif ?>
                            </td>
                            <td><?= esc(Board::monthName($d['PlanMonth'])) ?></td>
                            <td><?= priority_badge($d['Priority']) ?></td>
                            <td><?= esc($d['ProgrammerName'] ?? 'Sin asignar') ?></td>
                            <td><div><?= dev_state_badge($d) ?></div></td>
                            <td><?= dev_progress($d) ?></td>
                            <td>
                                <?php if ((int) $d['OpenIssues'] > 0): ?>
                                    <div><span class="chip chip-late"><?= (int) $d['OpenIssues'] ?> abierto<?= (int) $d['OpenIssues'] === 1 ? '' : 's' ?></span></div>
                                <?php else: ?>
                                    <span class="muted small">Ninguno</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
