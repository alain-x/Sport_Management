<?php
/**
 * @var array $stats teams/players/tournaments/matches counts
 * @var array $recentMatches finished matches (newest first)
 * @var array $upcomingMatches scheduled matches
 * @var array $topScorers players ordered by goals
 * @var array $ongoingTournaments
 * @var array|null $user
 */
$cards = [
    ['label' => 'Teams', 'value' => $stats['teams'], 'icon' => 'bi-shield-fill', 'color' => 'primary', 'href' => '/teams'],
    ['label' => 'Players', 'value' => $stats['players'], 'icon' => 'bi-people-fill', 'color' => 'success', 'href' => '/players'],
    ['label' => 'Tournaments', 'value' => $stats['tournaments'], 'icon' => 'bi-award-fill', 'color' => 'warning', 'href' => '/tournaments'],
    ['label' => 'Matches', 'value' => $stats['matches'], 'icon' => 'bi-calendar-event-fill', 'color' => 'info', 'href' => '/matches'],
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

<?php if ($ongoingTournaments !== []): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($ongoingTournaments as $t): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100 tournament-banner">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge text-bg-success mb-2"><i class="bi bi-broadcast me-1"></i>Ongoing</span>
                                <h2 class="h6 mb-1"><?= e($t['name']) ?></h2>
                                <p class="small text-muted mb-0">
                                    <?= e($t['sport']) ?> &middot;
                                    <?= e(format_date($t['start_date'])) ?> &ndash; <?= e(format_date($t['end_date'])) ?>
                                </p>
                            </div>
                            <a href="<?= e(url('/standings/' . $t['id'])) ?>" class="btn btn-sm btn-outline-light">
                                Table
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
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
                        <strong class"><?= (int) $m['home_score'] ?> : <?= (int) $m['away_score'] ?></strong>
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
                        <span class="badge text-bg-primary border-0">
                            <i class="bi bi-clock me-1"></i><?= e(format_date($m['match_date'], true)) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-star-fill me-2 text-warning"></i>Top scorers</span>
                <a href="<?= e(url('/players')) ?>" class="small">All players</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 60px">#</th>
                        <th>Player</th>
                        <th>Team</th>
                        <th class="text-center">Goals</th>
                        <th class="text-center">Assists</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($topScorers === []): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No goals recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($topScorers as $i => $p): ?>
                        <tr>
                            <td>
                                <span class="badge rounded-pill <?= $i === 0 ? 'text-bg-warning' : 'text-bg-light border' ?>">
                                    <?= $i + 1 ?>
                                </span>
                            </td>
                            <td class="fw-semibold">
                                <?php if ($p['jersey_number'] !== null): ?><span class="badge text-bg-light border">#<?= e($p['jersey_number']) ?></span> <?php endif; ?>
                                <a href="<?= e(url('/players/' . $p['id'])) ?>" class="text-decoration-none">
                                    <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
                                </a>
                            </td>
                            <td><?= e($p['team_name']) ?></td>
                            <td class="text-center fw-bold"><?= (int) $p['goals'] ?></td>
                            <td class="text-center"><?= (int) $p['assists'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-check me-2 text-success"></i>Upcoming events</span>
                <a href="<?= e(url('/calendar')) ?>" class="small">Calendar</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (($upcomingEvents ?? []) === []): ?>
                    <div class="list-group-item text-muted small">No events scheduled.</div>
                <?php endif; ?>
                <?php foreach (($upcomingEvents ?? []) as $event): ?>
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <span class="badge text-bg-light border text-nowrap">
                            <i class="bi bi-clock me-1"></i><?= e(format_date($event['starts_at'], true)) ?>
                        </span>
                        <div class="flex-grow-1 text-truncate">
                            <span class="fw-semibold"><?= e($event['title']) ?></span>
                            <span class="d-block small text-muted">
                                <?= e(ucfirst($event['type'])) ?>
                                <?= $event['team_name'] !== null ? ' &middot; ' . e($event['team_name']) : ' &middot; League-wide' ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
