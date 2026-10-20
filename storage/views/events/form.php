<?php
/**
 * Create/edit calendar event.
 *
 * @var array|null $event
 * @var array $teams empty for team staff (their team is locked)
 * @var array $tournaments
 * @var string $backMonth 'YYYY-MM' of the calendar month to return to
 * @var int|null $scopeTeamId null for admins
 */
use App\Models\EventModel;

$isEdit = $event !== null;
$action = $isEdit ? url('/events/' . $event['id']) : url('/events');

// Old (failed-validation) input wins, then the stored row, then the fallback.
$raw = static fn (string $key, string $fallback = ''): string => (string) old($key, $event[$key] ?? $fallback);
$defaultDate = $isEdit ? substr((string) $event['starts_at'], 0, 10) : date('Y-m-d');
$defaultTime = $isEdit ? substr((string) $event['starts_at'], 11, 5) : '09:00';
$defaultTeam = $scopeTeamId !== null
    ? (string) $scopeTeamId
    : ($isEdit ? (string) ($event['team_id'] ?? '') : '');
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="<?= e($action) ?>">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="ev_title">Title <span class="text-danger">*</span></label>
                            <input id="ev_title" name="title" class="form-control <?= error('title') ? 'is-invalid' : '' ?>"
                                   value="<?= e($raw('title')) ?>" placeholder="e.g. Recovery session" required>
                            <?php if (error('title')): ?><div class="invalid-feedback"><?= e(error('title')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ev_type">Type <span class="text-danger">*</span></label>
                            <select id="ev_type" name="type" class="form-select <?= error('type') ? 'is-invalid' : '' ?>">
                                <?php foreach (EventModel::TYPES as $type): ?>
                                    <option value="<?= $type ?>" <?= $raw('type', 'training') === $type ? 'selected' : '' ?>>
                                        <?= e(ucfirst($type)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (error('type')): ?><div class="invalid-feedback"><?= e(error('type')) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="ev_date">Date <span class="text-danger">*</span></label>
                            <input id="ev_date" name="event_date" type="date"
                                   class="form-control <?= error('event_date') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) old('event_date', $defaultDate)) ?>" required>
                            <?php if (error('event_date')): ?><div class="invalid-feedback"><?= e(error('event_date')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ev_time">Time <span class="text-danger">*</span></label>
                            <input id="ev_time" name="event_time" type="time"
                                   class="form-control <?= error('event_time') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) old('event_time', $defaultTime)) ?>" required>
                            <?php if (error('event_time')): ?><div class="invalid-feedback"><?= e(error('event_time')) ?></div><?php endif; ?>
                        </div>

                        <?php if ($scopeTeamId !== null): ?>
                            <div class="col-md-4">
                                <label class="form-label">Team</label>
                                <input type="hidden" name="team_id" value="<?= $scopeTeamId ?>">
                                <input class="form-control" value="Your team (locked)" disabled>
                            </div>
                        <?php else: ?>
                            <div class="col-md-4">
                                <label class="form-label" for="ev_team">Team</label>
                                <select id="ev_team" name="team_id" class="form-select <?= error('team_id') ? 'is-invalid' : '' ?>">
                                    <option value="">League-wide (all teams)</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= (int) $team['id'] ?>" <?= $defaultTeam === (string) $team['id'] ? 'selected' : '' ?>>
                                            <?= e($team['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (error('team_id')): ?><div class="invalid-feedback"><?= e(error('team_id')) ?></div><?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label" for="ev_tournament">Tournament</label>
                            <select id="ev_tournament" name="tournament_id" class="form-select <?= error('tournament_id') ? 'is-invalid' : '' ?>">
                                <option value="">None</option>
                                <?php foreach ($tournaments as $tournament): ?>
                                    <option value="<?= (int) $tournament['id'] ?>" <?= $raw('tournament_id') === (string) $tournament['id'] ? 'selected' : '' ?>>
                                        <?= e($tournament['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (error('tournament_id')): ?><div class="invalid-feedback"><?= e(error('tournament_id')) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="ev_description">Description</label>
                            <textarea id="ev_description" name="description" rows="3"
                                      class="form-control <?= error('description') ? 'is-invalid' : '' ?>"
                                      placeholder="Meeting point, kit, agenda..."><?= e($raw('description')) ?></textarea>
                            <?php if (error('description')): ?><div class="invalid-feedback"><?= e(error('description')) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= e(url('/calendar?month=' . $backMonth)) ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save changes' : 'Create event' ?>
                        </button>
                    </div>
                </form>

                <?php if ($isEdit): ?>
                    <div class="border-top pt-3 mt-4 d-flex justify-content-between align-items-center">
                        <span class="small text-muted">
                            Created <?= e(format_date($event['created_at'])) ?>
                            <?php if (!empty($event['creator_name'])): ?> by <?= e($event['creator_name']) ?><?php endif; ?>
                        </span>
                        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/delete')) ?>" class="d-inline"
                              onsubmit="return confirm('Delete this event?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-danger btn-sm" type="submit">
                                <i class="bi bi-trash me-1"></i>Delete event
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
