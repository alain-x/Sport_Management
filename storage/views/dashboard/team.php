<?php
/**
 * Team dashboard (team staff view).
 * @var array $team includes 'players', 'players_count', 'finished_matches'
 * @var int $staffCount
 * @var array $recentMatches finished matches involving the team
 * @var array $upcomingMatches scheduled matches involving the team
 * @var array<string,int> $fitness latest physical test status counts
 * @var array $alerts injured / low-wellbeing players
 * @var array $upcomingEvents team + league-wide events
 * @var array $topScorers team players ordered by goals
 */

$fitnessBadge = [
    'excellent' => 'success',
    'good' => 'success',
    'fair' => 'warning',
    'poor' => 'secondary',
    'injured' => 'danger',
    'untested' => 'light',
];

$cards = [
    ['label' => 'Squad', 'value' => $team['players_count'], 'icon' => 'bi-people-fill', 'color' => 'primary', 'href' => '/players?team_id=' . $team['id']],
    ['label' => 'Staff', 'value' => $staffCount, 'icon' => 'bi-person-badge', 'color' => 'secondary', 'href' => '/teams/' . $team['id'] . '/staff'],
    ['label' => 'Played', 'value' => $team['finished_matches'], 'icon' => 'bi-calendar-check', 'color' => 'info', 'href' => '/matches'],
    ['label' => 'Alerts', 'value' => count($alerts), 'icon' => 'bi-heart-pulse', 'color' => count($alerts) > 0 ? 'danger' : 'success', 'href' => '#squad-alerts'],
];
?>

<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): ?>
        <div class="col-xl-3 col-md-6">
            <a href="<?= e(url($card['href'])) ?>" class="text-decoration-none">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-<?= e($card['color']) ?>">
                            <i class="bi <?= e($card['icon']) ?>"></i>
                        </div>
                        <div>
                            <div class="fs-3 fw-bold text-dark"><?= (int) $card['value'] ?></div>
                            <div class="text-muted small text-uppercase"><?= e($card['label']) ?></div>
                        </div>
                        <i class="bi bi-arrow-right-circle ms-auto text-muted"></i>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center p-4">
                <?= crest_html($team['logo'] ?? null, $team['code'], 'mx-auto mb-3') ?>
                <h2 class="h5 mb-1"><?= e($team['name']) ?></h2>
                <p class="text-muted small mb-3">
                    <?= e($team['coach'] ?? 'Coach not set') ?>
                    <?= $team['city'] !== null ? ' &middot; ' . e($team['city']) : '' ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="<?= e(url('/teams/' . $team['id'])) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-shield me-1"></i>Team profile
                    </a>
                    <a href="<?= e(url('/teams/' . $team['id'] . '/staff')) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-person-badge me-1"></i>Staff
                    </a>
                </div>
            </div>
            <?php if (!empty($team['description'])): ?>
                <div class="card-footer bg-white small text-muted"><?= e($team['description']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-activity me-2 text-success"></i>Squad fitness (latest tests)
            </div>
            <div class="card-body">
                <?php foreach (['excellent', 'good', 'fair', 'poor', 'injured', 'untested'] as $status): ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-<?= e($fitnessBadge[$status]) ?> flex-shrink-0" style="min-width: 86px">
                            <?= e(ucfirst($status)) ?>
                        </span>
                        <div class="progress flex-grow-1" style="height: 8px">
                            <?php $pct = $team['players_count'] > 0 ? round($fitness[$status] / $team['players_count'] * 100) : 0; ?>
                            <div class="progress-bar bg-<?= e($fitnessBadge[$status] === 'light' ? 'secondary' : $fitnessBadge[$status]) ?>"
                                 style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="small text-muted" style="width: 24px; text-align: right"><?= (int) $fitness[$status] ?></span>
                    </div>
                <?php endforeach; ?>
                <a href="<?= e(url('/players?team_id=' . $team['id'])) ?>" class="small d-block mt-3">
                    View squad <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-4" id="squad-alerts">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-heart-pulse me-2 text-danger"></i>Medical &amp; wellbeing alerts
            </div>
            <div class="list-group list-group-flush">
                <?php if ($alerts === []): ?>
                    <div class="list-group-item text-muted small">
                        <i class="bi bi-check-circle me-1 text-success"></i>No players flagged. Everything looks good.
                    </div>
                <?php endif; ?>
                <?php foreach ($alerts as $alert): ?>
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <?= avatar_html($alert['photo'] ?? null, $alert['first_name'] . ' ' . $alert['last_name'], 'avatar-sm') ?>
                        <div class="flex-grow-1 text-truncate">
                            <a href="<?= e(url('/players/' . $alert['id'])) ?>" class="fw-semibold text-decoration-none">
                                <?= e($alert['first_name'] . ' ' . $alert['last_name']) ?>
                            </a>
                            <span class="d-block small text-muted text-truncate"><?= e($alert['flag_notes'] ?? 'No notes recorded.') ?></span>
                        </div>
                        <span class="badge text-bg-<?= $alert['flag'] === 'injured' ? 'danger' : 'warning' ?> text-nowrap">
                            <?= e(ucfirst($alert['flag'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-clock-history me-2 text-danger"></i>Recent results</span>
                <a href="<?= e(url('/matches?status=finished')) ?>" class="small">View all</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if ($recentMatches === []): ?>
                    <div class="list-group-item text-muted small">No finished matches yet.</div>
                <?php endif; ?>
                <?php foreach ($recentMatches as $m): ?>
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <span class="badge text-bg-light border"><?= e($m['home_code']) ?></span>
                        <span class="flex-grow-1 text-truncate"><?= e($m['home_name']) ?> vs <?= e($m['away_name']) ?></span>
                        <strong><?= (int) $m['home_score'] ?> : <?= (int) $m['away_score'] ?></strong>
                        <span class="badge text-bg-light border"><?= e($m['away_code']) ?></span>
                        <small class="text-muted d-none d-md-inline"><?= e(format_date($m['match_date'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar2-week me-2 text-primary"></i>Upcoming fixtures</span>
                <a href="<?= e(url('/matches?status=scheduled')) ?>" class="small">View all</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if ($upcomingMatches === []): ?>
                    <div class="list-group-item text-muted small">No scheduled matches.</div>
                <?php endif; ?>
                <?php foreach ($upcomingMatches as $m): ?>
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <div class="flex-grow-1 text-truncate">
                            <?= e($m['home_name']) ?>
                            <span class="text-muted small mx-1">vs</span>
                            <?= e($m['away_name']) ?>
                            <?php if ($m['tournament_name']): ?>
                                <span class="d-block small text-muted"><?= e($m['tournament_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="badge text-bg-primary border-0 text-nowrap">
                            <i class="bi bi-clock me-1"></i><?= e(format_date($m['match_date'], true)) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-check me-2 text-success"></i>Upcoming events</span>
                <a href="<?= e(url('/calendar')) ?>" class="small">Calendar</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if ($upcomingEvents === []): ?>
                    <div class="list-group-item text-muted small">No events scheduled.</div>
                <?php endif; ?>
                <?php foreach ($upcomingEvents as $event): ?>
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <span class="badge text-bg-light border text-nowrap">
                            <i class="bi bi-clock me-1"></i><?= e(format_date($event['starts_at'], true)) ?>
                        </span>
                        <div class="flex-grow-1 text-truncate">
                            <span class="fw-semibold"><?= e($event['title']) ?></span>
                            <span class="d-block small text-muted"><?= e(ucfirst($event['type'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-star-fill me-2 text-warning"></i>Team top scorers</span>
                <a href="<?= e(url('/players?team_id=' . $team['id'])) ?>" class="small">Squad</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 50px">#</th>
                        <th>Player</th>
                        <th class="text-center">Goals</th>
                        <th class="text-center">Assists</th>
                        <th class="text-center">Apps</th>
                        <th class="text-center">Rating</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($topScorers === []): ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No goals recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($topScorers as $i => $p): ?>
                        <tr>
                            <td><span class="badge rounded-pill <?= $i === 0 ? 'text-bg-warning' : 'text-bg-light border' ?>"><?= $i + 1 ?></span></td>
                            <td class="fw-semibold">
                                <a href="<?= e(url('/players/' . $p['id'])) ?>" class="text-decoration-none">
                                    <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
                                </a>
                            </td>
                            <td class="text-center fw-bold"><?= (int) $p['goals'] ?></td>
                            <td class="text-center"><?= (int) $p['assists'] ?></td>
                            <td class="text-center"><?= (int) $p['appearances'] ?></td>
                            <td class="text-center">
                                <?php if ($p['rating'] !== null): ?>
                                    <span class="badge text-bg-success"><?= number_format((float) $p['rating'], 2) ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
