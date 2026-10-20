<?php
/**
 * Month calendar: events + fixtures overlaid on a 6x7 grid.
 *
 * @var string $monthKey 'YYYY-MM' of the visible month
 * @var string $monthLabel e.g. "October 2026"
 * @var string $prevMonth 'YYYY-MM'
 * @var string $nextMonth 'YYYY-MM'
 * @var array $cells 42 cells: key (Y-m-d), day, inMonth, isToday
 * @var array<string, array<int, array{kind:string,row:array}>> $byDate items grouped by day
 * @var array $upcoming next events for the current scope
 * @var int|null $scopeTeamId null for admins, the manager's team otherwise
 */
$typeClass = [
    'training' => 'type-training',
    'meeting' => 'type-meeting',
    'travel' => 'type-travel',
    'deadline' => 'type-deadline',
    'media' => 'type-media',
    'other' => 'type-other',
];
$matchClass = ['scheduled' => 'type-scheduled', 'live' => 'type-live', 'finished' => 'type-finished'];

$canManageEvent = static function (array $event) use ($scopeTeamId): bool {
    if (is_admin()) {
        return true;
    }

    return $scopeTeamId !== null
        && $event['team_id'] !== null
        && (int) $event['team_id'] === $scopeTeamId;
};
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="btn-group">
        <a href="<?= e(url('/calendar?month=' . $prevMonth)) ?>" class="btn btn-outline-secondary" title="Previous month">
            <i class="bi bi-chevron-left"></i>
        </a>
        <a href="<?= e(url('/calendar')) ?>" class="btn btn-outline-secondary">Today</a>
        <a href="<?= e(url('/calendar?month=' . $nextMonth)) ?>" class="btn btn-outline-secondary" title="Next month">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
    <h1 class="h4 mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i><?= e($monthLabel) ?></h1>
    <a href="<?= e(url('/events/create?month=' . $monthKey)) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New event
    </a>
</div>

<div class="d-flex flex-wrap gap-3 small text-muted mb-2">
    <span><span class="legend-dot type-training"></span>Training</span>
    <span><span class="legend-dot type-meeting"></span>Meeting</span>
    <span><span class="legend-dot type-travel"></span>Travel</span>
    <span><span class="legend-dot type-deadline"></span>Deadline</span>
    <span><span class="legend-dot type-media"></span>Media</span>
    <span><span class="legend-dot type-other"></span>Other</span>
    <span><span class="legend-dot type-scheduled"></span>Match (scheduled)</span>
    <span><span class="legend-dot type-live"></span>Match (live)</span>
    <span><span class="legend-dot type-finished"></span>Match (finished)</span>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-2 p-md-3">
        <div class="calendar-grid calendar-head">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow): ?>
                <div class="cal-dow"><?= $dow ?></div>
            <?php endforeach; ?>
        </div>

        <div class="calendar-grid calendar-body">
            <?php foreach ($cells as $cell): ?>
                <div class="cal-day <?= $cell['inMonth'] ? '' : 'other-month' ?> <?= $cell['isToday'] ? 'is-today' : '' ?>">
                    <div class="cal-day-num"><?= $cell['day'] ?></div>
                    <div class="cal-items">
                        <?php foreach ($byDate[$cell['key']] ?? [] as $item): ?>
                            <?php if ($item['kind'] === 'event'): $event = $item['row']; ?>
                                <?php if ($canManageEvent($event)): ?>
                                    <a class="cal-chip <?= e($typeClass[$event['type']] ?? 'type-other') ?>"
                                       href="<?= e(url('/events/' . $event['id'] . '/edit')) ?>"
                                       title="<?= e($event['title']) ?> (edit)">
                                        <span class="chip-time"><?= e(date('H:i', strtotime($event['starts_at']))) ?></span>
                                        <?= e($event['title']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="cal-chip <?= e($typeClass[$event['type']] ?? 'type-other') ?>"
                                          title="<?= e($event['title']) ?>">
                                        <span class="chip-time"><?= e(date('H:i', strtotime($event['starts_at']))) ?></span>
                                        <?= e($event['title']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: $match = $item['row']; ?>
                                <span class="cal-chip <?= e($matchClass[$match['status']] ?? 'type-scheduled') ?>"
                                      title="<?= e($match['home_name'] . ' vs ' . $match['away_name']) ?>">
                                    <span class="chip-time"><?= e(date('H:i', strtotime($match['match_date']))) ?></span>
                                    <?= e($match['home_code']) ?> vs <?= e($match['away_code']) ?>
                                    <?php if ($match['status'] === 'finished' || $match['status'] === 'live'): ?>
                                        <strong><?= (int) $match['home_score'] ?>&ndash;<?= (int) $match['away_score'] ?></strong>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clock-history me-2 text-primary"></i>Upcoming events
        <span class="text-muted small fw-normal">(next <?= count($upcoming) ?>)</span>
    </div>
    <div class="list-group list-group-flush">
        <?php if ($upcoming === []): ?>
            <div class="list-group-item text-muted small">No upcoming events. Use "New event" to schedule one.</div>
        <?php endif; ?>
        <?php foreach ($upcoming as $event): ?>
            <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <span class="badge text-bg-light border text-nowrap">
                    <i class="bi bi-clock me-1"></i><?= e(format_date($event['starts_at'], true)) ?>
                </span>
                <span class="badge <?= e($typeClass[$event['type']] ?? 'type-other') ?> text-nowrap">
                    <?= e(ucfirst($event['type'])) ?>
                </span>
                <div class="flex-grow-1 text-truncate">
                    <span class="fw-semibold"><?= e($event['title']) ?></span>
                    <span class="d-block small text-muted">
                        <?= $event['team_name'] !== null ? e($event['team_name']) : 'League-wide' ?>
                        <?php if ($event['tournament_name'] !== null): ?> &middot; <?= e($event['tournament_name']) ?><?php endif; ?>
                        <?php if (!empty($event['description'])): ?> &middot; <?= e($event['description']) ?><?php endif; ?>
                    </span>
                </div>
                <?php if ($canManageEvent($event)): ?>
                    <a href="<?= e(url('/events/' . $event['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="<?= e(url('/events/' . $event['id'] . '/delete')) ?>" class="d-inline"
                          onsubmit="return confirm('Delete this event?');">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
