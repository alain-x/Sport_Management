<?php
/**
 * @var array $players
 * @var string $search
 * @var int|null $teamId
 * @var array $teams
 * @var array|null $scopeTeam set for team staff (team filter is locked)
 * @var array $pagination
 */
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="get" action="<?= e(url('/players')) ?>" class="d-flex flex-wrap gap-2">
        <div class="input-group" style="min-width: 260px">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search player or nationality...">
        </div>
        <?php if ($scopeTeam !== null): ?>
            <input type="hidden" name="team_id" value="<?= (int) $scopeTeam['id'] ?>">
            <span class="input-group-text bg-primary-subtle text-primary-emphasis">
                <i class="bi bi-shield-fill me-1"></i><?= e($scopeTeam['name']) ?>
            </span>
        <?php else: ?>
            <select name="team_id" class="form-select" style="width: auto">
                <option value="">All teams</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= (int) $team['id'] ?>" <?= $teamId === (int) $team['id'] ? 'selected' : '' ?>>
                        <?= e($team['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
        <?php if ($search !== '' || ($teamId !== null && $scopeTeam === null)): ?>
            <a href="<?= e(url('/players')) ?>" class="btn btn-outline-danger">Clear</a>
        <?php endif; ?>
    </form>
    <a href="<?= e(url('/players/create')) ?>" class="btn btn-success">
        <i class="bi bi-person-plus me-1"></i>Add player
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width: 60px">#</th>
                <th>Player</th>
                <th>Team</th>
                <th>Position</th>
                <th>Born</th>
                <th>Nationality</th>
                <th class="text-center">Apps</th>
                <th class="text-center">G</th>
                <th class="text-center">A</th>
                <th class="text-center">Rating</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($players === []): ?>
                <tr><td colspan="11" class="text-center text-muted py-5">No players match your filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($players as $player): ?>
                <tr>
                    <td>
                        <?php if ($player['jersey_number'] !== null): ?>
                            <span class="badge text-bg-dark"><?= e($player['jersey_number']) ?></span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= e(url('/players/' . $player['id'])) ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none fw-semibold">
                            <?= avatar_html($player['photo'] ?? null, $player['first_name'] . ' ' . $player['last_name'], 'avatar-sm') ?>
                            <span><?= e($player['first_name'] . ' ' . $player['last_name']) ?></span>
                        </a>
                    </td>
                    <td>
                        <a href="<?= e(url('/teams/' . $player['team_id'])) ?>" class="text-decoration-none">
                            <?= e($player['team_name']) ?>
                        </a>
                    </td>
                    <td><span class="badge text-bg-secondary"><?= e($player['position'] ?? 'n/a') ?></span></td>
                    <td><?= e(format_date($player['date_of_birth'])) ?></td>
                    <td><?= e($player['nationality'] ?? '-') ?></td>
                    <td class="text-center"><?= (int) $player['appearances'] ?></td>
                    <td class="text-center fw-bold"><?= (int) $player['goals'] ?></td>
                    <td class="text-center"><?= (int) $player['assists'] ?></td>
                    <td class="text-center">
                        <?php if ($player['rating'] !== null): ?>
                            <span class="badge text-bg-success"><?= number_format((float) $player['rating'], 2) ?></span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('/players/' . $player['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Profile">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php $canTouch = is_admin() || auth_team_id() === (int) $player['team_id']; ?>
                        <?php if ($canTouch): ?>
                            <a href="<?= e(url('/players/' . $player['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" action="<?= e(url('/players/' . $player['id'] . '/delete')) ?>" class="d-inline"
                                  onsubmit="return confirm('Delete <?= e($player['first_name'] . ' ' . $player['last_name']) ?>?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="small text-muted">
            Showing <?= $players === [] ? 0 : ($pagination['page'] - 1) * $pagination['perPage'] + 1 ?>&ndash;
            <?= min($pagination['total'], $pagination['page'] * $pagination['perPage']) ?>
            of <?= $pagination['total'] ?> players
        </span>
        <?= pagination_links($pagination) ?>
    </div>
</div>
