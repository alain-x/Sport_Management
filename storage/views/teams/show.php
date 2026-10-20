<?php
/**
 * Team detail page.
 * @var array $team includes 'players' and 'finished_matches'
 * @var bool $canManage admin or this team's own staff
 * @var int $staffCount
 */
$authTeamId = auth_team_id();
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <a href="<?= e(url('/teams')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to teams
    </a>
    <div class="d-flex gap-2">
        <?php if ($canManage): ?>
            <a href="<?= e(url('/teams/' . $team['id'] . '/staff')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-person-badge me-1"></i>Staff (<?= $staffCount ?>)
            </a>
            <a href="<?= e(url('/teams/' . $team['id'] . '/edit')) ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <a href="<?= e(url('/players/create')) ?>" class="btn btn-outline-success">
                <i class="bi bi-person-plus me-1"></i>Add player
            </a>
            <?php if (is_admin()): ?>
                <form method="post" action="<?= e(url('/teams/' . $team['id'] . '/delete')) ?>" class="d-inline"
                      onsubmit="return confirm('Delete <?= e($team['name']) ?>? Its players and matches will also be removed.');">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-4">
                <?= crest_html($team['logo'] ?? null, $team['code'], 'mx-auto mb-3') ?>
                <h1 class="h4 mb-1"><?= e($team['name']) ?></h1>
                <p class="text-muted mb-3"><?= e($team['city'] ?? 'City not set') ?></p>
                <?php if ($authTeamId !== null && (int) $team['id'] === $authTeamId): ?>
                    <span class="badge text-bg-primary mb-2"><i class="bi bi-star-fill me-1"></i>Your team</span>
                <?php endif; ?>

                <div class="row text-center g-2 mt-1">
                    <div class="col-4">
                        <div class="fs-4 fw-bold"><?= $team['players_count'] ?></div>
                        <small class="text-muted text-uppercase">Players</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-4 fw-bold"><?= $staffCount ?></div>
                        <small class="text-muted text-uppercase">Staff</small>
                    </div>
                    <div class="col-4">
                        <div class="fs-4 fw-bold"><?= $team['finished_matches'] ?></div>
                        <small class="text-muted text-uppercase">Played</small>
                    </div>
                </div>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Head coach</span><strong><?= e($team['coach'] ?? '-') ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Short code</span><span class="badge text-bg-dark"><?= e($team['code']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Founded</span><span><?= e($team['founded_year'] ?? '-') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Created</span><span><?= e(format_date($team['created_at'])) ?></span>
                </li>
            </ul>
            <?php if (!empty($team['description'])): ?>
                <div class="card-footer bg-white">
                    <div class="fw-semibold small text-uppercase text-muted mb-1">About</div>
                    <p class="small mb-0"><?= nl2br(e($team['description'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-people me-2"></i>Squad (<?= count($team['players']) ?>)</span>
                <?php if ($canManage): ?>
                    <a href="<?= e(url('/players/create')) ?>" class="small">Add player</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 70px">#</th>
                        <th>Player</th>
                        <th>Position</th>
                        <th>Nationality</th>
                        <th class="text-center">G</th>
                        <th class="text-center">A</th>
                        <th class="text-center">Rating</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($team['players'] === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No players registered yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($team['players'] as $player): ?>
                        <tr>
                            <td>
                                <?php if ($player['jersey_number'] !== null): ?>
                                    <span class="badge text-bg-light border"><?= e($player['jersey_number']) ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="fw-semibold">
                                <a href="<?= e(url('/players/' . $player['id'])) ?>" class="text-decoration-none">
                                    <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
                                </a>
                            </td>
                            <td><span class="badge text-bg-secondary"><?= e($player['position'] ?? 'n/a') ?></span></td>
                            <td><?= e($player['nationality'] ?? '-') ?></td>
                            <td class="text-center"><?= (int) $player['goals'] ?></td>
                            <td class="text-center"><?= (int) $player['assists'] ?></td>
                            <td class="text-center">
                                <?php if ($player['rating'] !== null): ?>
                                    <span class="badge text-bg-success"><?= number_format((float) $player['rating'], 2) ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($canManage): ?>
                                    <a href="<?= e(url('/players/' . $player['id'] . '/edit')) ?>"
                                       class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="<?= e(url('/players/' . $player['id'] . '/delete')) ?>"
                                          class="d-inline" onsubmit="return confirm('Delete this player?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?= e(url('/players/' . $player['id'])) ?>"
                                       class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
