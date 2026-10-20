<?php
/**
 * @var array $tournaments
 */
$statusBadge = [
    'upcoming' => 'text-bg-info',
    'ongoing' => 'text-bg-success',
    'completed' => 'text-bg-secondary',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small"><?= count($tournaments) ?> tournament(s) on record</span>
    <?php if (is_admin()): ?>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#tournamentModal"
                onclick="prepareTournamentForm('')">
            <i class="bi bi-plus-lg me-1"></i>New tournament
        </button>
    <?php endif; ?>
</div>

<?php if ($tournaments === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-award display-4 d-block mb-2"></i>
            No tournaments yet.<?= is_admin() ? ' Use "New tournament" to create the first one.' : '' ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($tournaments as $tournament): ?>
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge <?= $statusBadge[$tournament['status']] ?? 'text-bg-secondary' ?>">
                            <?= e(ucfirst($tournament['status'])) ?>
                        </span>
                        <?php if (is_admin()): ?>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" type="button" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#tournamentModal"
                                        onclick='prepareTournamentForm(<?= json_encode($tournament, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" action="<?= e(url('/tournaments/' . $tournament['id'] . '/delete')) ?>"
                                      class="d-inline" onsubmit="return confirm('Delete this tournament?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-outline-danger" type="submit" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h2 class="h6 mb-1">
                        <a href="<?= e(url('/tournaments/' . $tournament['id'])) ?>" class="text-decoration-none">
                            <?= e($tournament['name']) ?>
                        </a>
                    </h2>
                    <p class="small text-muted mb-2">
                        <?= e($tournament['sport']) ?> &middot;
                        <?= e(format_date($tournament['start_date'])) ?> &ndash; <?= e(format_date($tournament['end_date'])) ?>
                    </p>
                    <div class="d-flex gap-3 small">
                        <span><i class="bi bi-calendar-check me-1"></i><?= (int) $tournament['matches_count'] ?> matches</span>
                        <a href="<?= e(url('/standings/' . $tournament['id'])) ?>">
                            <i class="bi bi-bar-chart-steps me-1"></i>Standings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (is_admin()): ?>
<!-- Create / edit modal -->
<div class="modal fade" id="tournamentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" id="tournamentForm" action="<?= e(url('/tournaments')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h6" id="tournamentModalTitle">New tournament</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="t_name">Name <span class="text-danger">*</span></label>
                            <input id="t_name" name="name" class="form-control" placeholder="Premier Championship 2026" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="t_sport">Sport <span class="text-danger">*</span></label>
                            <input id="t_sport" name="sport" class="form-control" value="Football" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="t_start">Start date</label>
                            <input id="t_start" name="start_date" type="date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="t_end">End date</label>
                            <input id="t_end" name="end_date" type="date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="t_status">Status <span class="text-danger">*</span></label>
                            <select id="t_status" name="status" class="form-select" required>
                                <option value="upcoming">Upcoming</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="t_description">Description</label>
                            <textarea id="t_description" name="description" rows="3" class="form-control"
                                      placeholder="Optional notes about the tournament..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareTournamentForm(tournament) {
    const form = document.getElementById('tournamentForm');
    const editing = tournament && tournament.id;
    document.getElementById('tournamentModalTitle').textContent = editing ? 'Edit tournament' : 'New tournament';
    form.action = editing ? '<?= e(url('/tournaments/')) ?>' + tournament.id : '<?= e(url('/tournaments')) ?>';

    form.name.value = editing ? tournament.name : '';
    form.sport.value = editing ? tournament.sport : 'Football';
    form.start_date.value = editing && tournament.start_date ? tournament.start_date.substring(0, 10) : '';
    form.end_date.value = editing && tournament.end_date ? tournament.end_date.substring(0, 10) : '';
    form.status.value = editing ? tournament.status : 'upcoming';
    form.description.value = editing && tournament.description ? tournament.description : '';
}
</script>
<?php endif; ?>
