<?php
/**
 * Create/edit player form (profile + statistics + photo).
 * @var array|null $player
 * @var array $teams
 * @var array|null $scopeTeam team staff are locked to their own team
 */
use App\Models\Player;

$isEdit = $player !== null;
$action = $isEdit ? url('/players/' . $player['id']) : url('/players');
$value = static fn (string $key, string $default = '') => old($key, $player[$key] ?? $default);
$photo = old('photo', $player['photo'] ?? null);
?>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <h2 class="h6 text-uppercase text-muted mb-3"><i class="bi bi-person-lines-fill me-2"></i>Profile</h2>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="first_name">First name <span class="text-danger">*</span></label>
                            <input id="first_name" name="first_name"
                                   class="form-control <?= error('first_name') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) $value('first_name')) ?>" required>
                            <?php if (error('first_name')): ?><div class="invalid-feedback"><?= e(error('first_name')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="last_name">Last name <span class="text-danger">*</span></label>
                            <input id="last_name" name="last_name"
                                   class="form-control <?= error('last_name') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) $value('last_name')) ?>" required>
                            <?php if (error('last_name')): ?><div class="invalid-feedback"><?= e(error('last_name')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="team_id">Team <span class="text-danger">*</span></label>
                            <?php if ($scopeTeam !== null): ?>
                                <input type="hidden" name="team_id" value="<?= (int) $scopeTeam['id'] ?>">
                                <input id="team_id" class="form-control" value="<?= e($scopeTeam['name']) ?>" disabled>
                            <?php else: ?>
                                <select id="team_id" name="team_id" class="form-select <?= error('team_id') ? 'is-invalid' : '' ?>" required>
                                    <option value="">Select a team</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= (int) $team['id'] ?>" <?= (int) $value('team_id') === (int) $team['id'] ? 'selected' : '' ?>>
                                            <?= e($team['name']) ?> (<?= e($team['code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <?php if (error('team_id')): ?><div class="invalid-feedback"><?= e(error('team_id')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="photo">Profile photo <span class="text-muted small">(max 3 MB)</span></label>
                            <input id="photo" name="photo" type="file" accept="image/*"
                                   class="form-control <?= error('photo') ? 'is-invalid' : '' ?>">
                            <?php if (error('photo')): ?><div class="invalid-feedback"><?= e(error('photo')) ?></div><?php endif; ?>
                            <?php if ($photo): ?>
                                <div class="form-text">
                                    <img src="<?= e(upload_url($photo)) ?>" alt="" class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover">
                                    Current photo shown &mdash; choose a file to replace it.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="jersey_number">Jersey number</label>
                            <input id="jersey_number" name="jersey_number" type="number" min="0" max="99"
                                   class="form-control <?= error('jersey_number') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) $value('jersey_number')) ?>" placeholder="10">
                            <?php if (error('jersey_number')): ?><div class="invalid-feedback"><?= e(error('jersey_number')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="position">Position</label>
                            <select id="position" name="position" class="form-select <?= error('position') ? 'is-invalid' : '' ?>">
                                <option value="">Unknown</option>
                                <?php foreach (Player::POSITIONS as $position): ?>
                                    <option value="<?= e($position) ?>" <?= $value('position') === $position ? 'selected' : '' ?>>
                                        <?= e($position) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (error('position')): ?><div class="invalid-feedback"><?= e(error('position')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="date_of_birth">Date of birth</label>
                            <input id="date_of_birth" name="date_of_birth" type="date"
                                   class="form-control <?= error('date_of_birth') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) $value('date_of_birth')) ?>">
                            <?php if (error('date_of_birth')): ?><div class="invalid-feedback"><?= e(error('date_of_birth')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="foot">Preferred foot</label>
                            <select id="foot" name="foot" class="form-select <?= error('foot') ? 'is-invalid' : '' ?>">
                                <option value="">Unknown</option>
                                <?php foreach (['left' => 'Left', 'right' => 'Right', 'both' => 'Both'] as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $value('foot') === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (error('foot')): ?><div class="invalid-feedback"><?= e(error('foot')) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="nationality">Nationality</label>
                            <input id="nationality" name="nationality"
                                   class="form-control <?= error('nationality') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) $value('nationality')) ?>" placeholder="English">
                            <?php if (error('nationality')): ?><div class="invalid-feedback"><?= e(error('nationality')) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h2 class="h6 text-uppercase text-muted mb-3"><i class="bi bi-bar-chart-line me-2"></i>Season statistics</h2>
                    <div class="row g-3">
                        <?php
                        $counters = [
                            'appearances' => 'Appearances',
                            'minutes_played' => 'Minutes played',
                            'goals' => 'Goals',
                            'assists' => 'Assists',
                            'yellow_cards' => 'Yellow cards',
                            'red_cards' => 'Red cards',
                        ];
                        ?>
                        <?php foreach ($counters as $field => $label): ?>
                            <div class="col-md-4 col-lg-2">
                                <label class="form-label" for="<?= $field ?>"><?= $label ?></label>
                                <input id="<?= $field ?>" name="<?= $field ?>" type="number" min="0"
                                       class="form-control <?= error($field) ? 'is-invalid' : '' ?>"
                                       value="<?= e((string) $value($field, '0')) ?>">
                                <?php if (error($field)): ?><div class="invalid-feedback"><?= e(error($field)) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label" for="rating">Avg. rating <span class="text-muted small">(0-10)</span></label>
                            <input id="rating" name="rating" type="number" step="0.01" min="0" max="10"
                                   class="form-control <?= error('rating') ? 'is-invalid' : '' ?>"
                                   value="<?= e((string) $value('rating')) ?>" placeholder="7.50">
                            <?php if (error('rating')): ?><div class="invalid-feedback"><?= e(error('rating')) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= e($isEdit ? url('/players/' . $player['id']) : url('/players')) ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-success" type="submit">
                            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save changes' : 'Add player' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
