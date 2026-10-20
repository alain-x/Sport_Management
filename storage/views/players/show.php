<?php
/**
 * Player detail: profile, season statistics, physical tests and mental assessments.
 *
 * @var array $player
 * @var bool $canManage admin or staff of the player's team
 * @var array $metrics physical test history (canManage only)
 * @var array|null $latestMetric
 * @var array $welfare mental assessment history (canManage only)
 * @var array|null $latestWelfare
 */
use App\Models\Player;
use App\Models\PlayerMetric;
use App\Models\PlayerWelfare;

$full = $player['first_name'] . ' ' . $player['last_name'];

$age = null;
if (!empty($player['date_of_birth']) && strtotime($player['date_of_birth']) !== false) {
    $age = ((int) date('YMD')) - ((int) date('YMD', strtotime($player['date_of_birth'])));
    $age = (int) floor($age / 10000);
}

$bmi = null;
if ($latestMetric !== null && !empty($latestMetric['height_cm']) && !empty($latestMetric['weight_kg'])) {
    $meters = (float) $latestMetric['height_cm'] / 100;
    if ($meters > 0) {
        $bmi = round((float) $latestMetric['weight_kg'] / ($meters * $meters), 1);
    }
}

$fitnessBadge = ['excellent' => 'success', 'good' => 'success', 'fair' => 'warning', 'poor' => 'secondary', 'injured' => 'danger'];
$wellbeingBadge = ['great' => 'success', 'good' => 'success', 'okay' => 'secondary', 'low' => 'warning', 'struggling' => 'danger'];

// Open the tab that contains a validation error, if any.
$activeTab = 'overview';
if (error('assessment_date') || error('confidence') || error('focus') || error('motivation') || error('stress') || error('wellbeing')) {
    $activeTab = 'mental';
} elseif (error('test_date') || error('fitness') || error('height_cm') || error('weight_kg') || error('top_speed')
    || error('stamina') || error('strength') || error('agility') || error('body_fat')) {
    $activeTab = 'physical';
} elseif (error('rating') || error('goals') || error('assists') || error('appearances')
    || error('minutes_played') || error('yellow_cards') || error('red_cards')) {
    $activeTab = 'statistics';
}

$pane = static fn (string $id): string => $id === $activeTab ? 'tab-pane fade show active' : 'tab-pane fade';
$tab = static fn (string $id): string => $id === $activeTab ? 'nav-link active' : 'nav-link';
?>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <a href="<?= e(url('/players')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to players
    </a>
    <?php if ($canManage): ?>
        <div class="d-flex gap-2">
            <a href="<?= e(url('/players/' . $player['id'] . '/edit')) ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <form method="post" action="<?= e(url('/players/' . $player['id'] . '/delete')) ?>" class="d-inline"
                  onsubmit="return confirm('Delete <?= e($full) ?>? All recorded metrics and assessments will also be removed.');">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Delete</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Profile card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-4">
                <?= avatar_html($player['photo'] ?? null, $full, 'avatar-xl mx-auto mb-3') ?>
                <h1 class="h4 mb-1"><?= e($full) ?></h1>
                <div class="d-flex flex-wrap gap-1 justify-content-center mb-3">
                    <?php if ($player['jersey_number'] !== null): ?>
                        <span class="badge text-bg-dark">#<?= e($player['jersey_number']) ?></span>
                    <?php endif; ?>
                    <span class="badge text-bg-secondary"><?= e($player['position'] ?? 'Position n/a') ?></span>
                    <a href="<?= e(url('/teams/' . $player['team_id'])) ?>" class="badge text-bg-primary text-decoration-none">
                        <?= e($player['team_code']) ?> &middot; <?= e($player['team_name']) ?>
                    </a>
                    <?php if ($player['rating'] !== null): ?>
                        <span class="badge text-bg-success"><i class="bi bi-star-fill me-1"></i><?= number_format((float) $player['rating'], 2) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($canManage): ?>
                    <form method="post" action="<?= e(url('/players/' . $player['id'] . '/photo')) ?>"
                          class="mb-3" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="input-group input-group-sm">
                            <input type="file" name="photo" accept="image/*" class="form-control" aria-label="Profile photo">
                            <button class="btn btn-outline-primary" type="submit" title="Upload photo">
                                <i class="bi bi-upload"></i>
                            </button>
                        </div>
                        <div class="form-text">JPG, PNG, WebP or GIF &middot; max 3 MB</div>
                    </form>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Date of birth</span>
                    <strong><?= e(format_date($player['date_of_birth'])) ?><?= $age !== null ? ' (' . $age . ')' : '' ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Nationality</span><strong><?= e($player['nationality'] ?? '-') ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Preferred foot</span>
                    <strong><?= $player['foot'] !== null ? e(ucfirst($player['foot'])) : '-' ?></strong>
                </li>
                <?php if ($canManage): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Latest test</span>
                        <strong><?= $latestMetric !== null ? e(format_date($latestMetric['test_date'])) : 'Not tested yet' ?></strong>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Tabbed content -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs card-header-tabs m-2" role="tablist">
                    <li class="nav-item">
                        <button class="<?= $tab('overview') ?>" data-bs-toggle="tab" data-bs-target="#pane-overview" type="button" role="tab">
                            <i class="bi bi-person me-1"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="<?= $tab('statistics') ?>" data-bs-toggle="tab" data-bs-target="#pane-statistics" type="button" role="tab">
                            <i class="bi bi-bar-chart-line me-1"></i>Statistics
                        </button>
                    </li>
                    <?php if ($canManage): ?>
                        <li class="nav-item">
                            <button class="<?= $tab('physical') ?>" data-bs-toggle="tab" data-bs-target="#pane-physical" type="button" role="tab">
                                <i class="bi bi-activity me-1"></i>Physical
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="<?= $tab('mental') ?>" data-bs-toggle="tab" data-bs-target="#pane-mental" type="button" role="tab">
                                <i class="bi bi-emoji-smile me-1"></i>Mental
                            </button>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card-body tab-content">
                <!-- ============ OVERVIEW ============ -->
                <div class="<?= $pane('overview') ?>" id="pane-overview" role="tabpanel">
                    <div class="row g-3 text-center mb-4">
                        <?php
                        $stats = [
                            ['Apps', $player['appearances'], 'bi-person-check'],
                            ['Minutes', $player['minutes_played'], 'bi-clock'],
                            ['Goals', $player['goals'], 'bi-bullseye'],
                            ['Assists', $player['assists'], 'bi-signpost-split'],
                            ['Yellow', $player['yellow_cards'], 'bi-square-fill'],
                            ['Red', $player['red_cards'], 'bi-dash-circle-fill'],
                        ];
                        ?>
                        <?php foreach ($stats as [$label, $value, $icon]): ?>
                            <div class="col-4 col-md-2">
                                <div class="border rounded p-2 h-100">
                                    <i class="bi <?= $icon ?> text-muted"></i>
                                    <div class="fs-5 fw-bold"><?= (int) $value ?></div>
                                    <small class="text-muted text-uppercase"><?= $label ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($canManage): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h6 text-uppercase text-muted mb-3"><i class="bi bi-activity me-2"></i>Physical snapshot</h3>
                                    <?php if ($latestMetric === null): ?>
                                        <p class="text-muted small mb-0">No fitness test recorded yet.
                                            <a href="#physical" class="js-tab-link" data-tab="physical">Add the first one.</a>
                                        </p>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="text-muted small">Test date</span>
                                            <strong><?= e(format_date($latestMetric['test_date'])) ?></strong>
                                        </div>
                                        <div class="row text-center g-2">
                                            <?php
                                            $snapshot = [
                                                ['Weight', $latestMetric['weight_kg'] !== null ? $latestMetric['weight_kg'] . ' kg' : '-'],
                                                ['Height', $latestMetric['height_cm'] !== null ? $latestMetric['height_cm'] . ' cm' : '-'],
                                                ['BMI', $bmi ?? '-'],
                                                ['Top speed', $latestMetric['top_speed'] !== null ? $latestMetric['top_speed'] . ' km/h' : '-'],
                                            ];
                                            ?>
                                            <?php foreach ($snapshot as [$label, $value]): ?>
                                                <div class="col-6">
                                                    <div class="fw-bold"><?= e((string) $value) ?></div>
                                                    <small class="text-muted text-uppercase"><?= $label ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <span class="badge text-bg-<?= e($fitnessBadge[$latestMetric['fitness']] ?? 'secondary') ?> fs-6">
                                                <?= e(ucfirst($latestMetric['fitness'])) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h3 class="h6 text-uppercase text-muted mb-3"><i class="bi bi-emoji-smile me-2"></i>Wellbeing snapshot</h3>
                                    <?php if ($latestWelfare === null): ?>
                                        <p class="text-muted small mb-0">No assessment recorded yet.
                                            <a href="#mental" class="js-tab-link" data-tab="mental">Add the first one.</a>
                                        </p>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="text-muted small">Assessed</span>
                                            <strong><?= e(format_date($latestWelfare['assessment_date'])) ?></strong>
                                        </div>
                                        <?php foreach (['confidence', 'focus', 'motivation', 'stress'] as $score): ?>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="small text-muted" style="width: 84px"><?= ucfirst($score) ?></span>
                                                <div class="progress flex-grow-1" style="height: 8px">
                                                    <div class="progress-bar <?= $score === 'stress' ? 'bg-warning' : 'bg-success' ?>"
                                                         style="width: <?= ((int) $latestWelfare[$score]) * 10 ?>%"></div>
                                                </div>
                                                <span class="small fw-semibold" style="width: 34px; text-align: right"><?= (int) $latestWelfare[$score] ?>/10</span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="mt-2 text-end">
                                            <span class="badge text-bg-<?= e($wellbeingBadge[$latestWelfare['wellbeing']] ?? 'secondary') ?>">
                                                <?= e(ucfirst($latestWelfare['wellbeing'])) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ============ STATISTICS ============ -->
                <div class="<?= $pane('statistics') ?>" id="pane-statistics" role="tabpanel">
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= e(url('/players/' . $player['id'] . '/stats')) ?>">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <?php
                                $fields = [
                                    'appearances' => 'Appearances',
                                    'minutes_played' => 'Minutes played',
                                    'goals' => 'Goals',
                                    'assists' => 'Assists',
                                    'yellow_cards' => 'Yellow cards',
                                    'red_cards' => 'Red cards',
                                ];
                                ?>
                                <?php foreach ($fields as $field => $label): ?>
                                    <div class="col-md-4 col-lg-2">
                                        <label class="form-label" for="stat_<?= $field ?>"><?= $label ?></label>
                                        <input id="stat_<?= $field ?>" name="<?= $field ?>" type="number" min="0"
                                               class="form-control <?= error($field) ? 'is-invalid' : '' ?>"
                                               value="<?= e((string) old($field, $player[$field])) ?>">
                                        <?php if (error($field)): ?><div class="invalid-feedback"><?= e(error($field)) ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <div class="col-md-4 col-lg-2">
                                    <label class="form-label" for="stat_rating">Rating <small class="text-muted">(0-10)</small></label>
                                    <input id="stat_rating" name="rating" type="number" step="0.01" min="0" max="10"
                                           class="form-control <?= error('rating') ? 'is-invalid' : '' ?>"
                                           value="<?= e((string) old('rating', $player['rating'])) ?>">
                                    <?php if (error('rating')): ?><div class="invalid-feedback"><?= e(error('rating')) ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="btn btn-success" type="submit">
                                    <i class="bi bi-check-lg me-1"></i>Save statistics
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-0">Statistics are maintained by the player's team.</p>
                    <?php endif; ?>
                </div>

                <?php if ($canManage): ?>
                    <!-- ============ PHYSICAL ============ -->
                    <div class="<?= $pane('physical') ?>" id="pane-physical" role="tabpanel">
                        <div class="card bg-light border mb-4">
                            <div class="card-body">
                                <h3 class="h6 mb-3"><i class="bi bi-clipboard2-pulse me-2"></i>Record fitness test</h3>
                                <form method="post" action="<?= e(url('/players/' . $player['id'] . '/metrics')) ?>">
                                    <?= csrf_field() ?>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label" for="m_test_date">Test date <span class="text-danger">*</span></label>
                                            <input id="m_test_date" name="test_date" type="date"
                                                   class="form-control <?= error('test_date') ? 'is-invalid' : '' ?>"
                                                   value="<?= e((string) old('test_date', date('Y-m-d'))) ?>" required>
                                            <?php if (error('test_date')): ?><div class="invalid-feedback"><?= e(error('test_date')) ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="m_fitness">Fitness status <span class="text-danger">*</span></label>
                                            <select id="m_fitness" name="fitness" class="form-select <?= error('fitness') ? 'is-invalid' : '' ?>">
                                                <?php foreach (PlayerMetric::FITNESS as $status): ?>
                                                    <option value="<?= $status ?>" <?= old('fitness', 'good') === $status ? 'selected' : '' ?>>
                                                        <?= e(ucfirst($status)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (error('fitness')): ?><div class="invalid-feedback"><?= e(error('fitness')) ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="m_height_cm">Height (cm)</label>
                                            <input id="m_height_cm" name="height_cm" type="number" step="0.1" min="100" max="250"
                                                   class="form-control <?= error('height_cm') ? 'is-invalid' : '' ?>"
                                                   value="<?= e((string) old('height_cm')) ?>" placeholder="183">
                                            <?php if (error('height_cm')): ?><div class="invalid-feedback"><?= e(error('height_cm')) ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="m_weight_kg">Weight (kg)</label>
                                            <input id="m_weight_kg" name="weight_kg" type="number" step="0.1" min="25" max="250"
                                                   class="form-control <?= error('weight_kg') ? 'is-invalid' : '' ?>"
                                                   value="<?= e((string) old('weight_kg')) ?>" placeholder="78.5">
                                            <?php if (error('weight_kg')): ?><div class="invalid-feedback"><?= e(error('weight_kg')) ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="m_top_speed">Top speed (km/h)</label>
                                            <input id="m_top_speed" name="top_speed" type="number" step="0.1" min="0" max="60"
                                                   class="form-control <?= error('top_speed') ? 'is-invalid' : '' ?>"
                                                   value="<?= e((string) old('top_speed')) ?>" placeholder="34.2">
                                            <?php if (error('top_speed')): ?><div class="invalid-feedback"><?= e(error('top_speed')) ?></div><?php endif; ?>
                                        </div>
                                        <?php foreach (['stamina' => 'Stamina', 'strength' => 'Strength', 'agility' => 'Agility', 'body_fat' => 'Body fat %'] as $field => $label): ?>
                                            <div class="col-md-2 col-6">
                                                <label class="form-label" for="m_<?= $field ?>"><?= $label ?> (%)</label>
                                                <input id="m_<?= $field ?>" name="<?= $field ?>" type="number" min="0" max="100"
                                                       class="form-control <?= error($field) ? 'is-invalid' : '' ?>"
                                                       value="<?= e((string) old($field)) ?>" placeholder="85">
                                                <?php if (error($field)): ?><div class="invalid-feedback"><?= e(error($field)) ?></div><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="col-12">
                                            <label class="form-label" for="m_notes">Notes</label>
                                            <textarea id="m_notes" name="notes" rows="2"
                                                      class="form-control <?= error('notes') ? 'is-invalid' : '' ?>"
                                                      placeholder="Recovery status, load programme, medical notes..."><?= e((string) old('notes')) ?></textarea>
                                            <?php if (error('notes')): ?><div class="invalid-feedback"><?= e(error('notes')) ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="bi bi-plus-lg me-1"></i>Save test
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <h3 class="h6 text-uppercase text-muted mb-3">Test history (<?= count($metrics) ?>)</h3>
                        <?php if ($metrics === []): ?>
                            <p class="text-muted small">No physical tests recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-center">Ht</th>
                                        <th class="text-center">Wt</th>
                                        <th class="text-center">BMI</th>
                                        <th class="text-center">Speed</th>
                                        <th class="text-center">Stam</th>
                                        <th class="text-center">Str</th>
                                        <th class="text-center">Agi</th>
                                        <th class="text-center">Fat</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th class="text-end"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($metrics as $m): ?>
                                        <?php
                                        $bmiRow = null;
                                        if (!empty($m['height_cm']) && !empty($m['weight_kg'])) {
                                            $meters = (float) $m['height_cm'] / 100;
                                            $bmiRow = $meters > 0 ? round((float) $m['weight_kg'] / ($meters * $meters), 1) : null;
                                        }
                                        ?>
                                        <tr>
                                            <td class="text-nowrap"><?= e(format_date($m['test_date'])) ?></td>
                                            <td class="text-center"><?= $m['height_cm'] !== null ? e($m['height_cm']) : '-' ?></td>
                                            <td class="text-center"><?= $m['weight_kg'] !== null ? e($m['weight_kg']) : '-' ?></td>
                                            <td class="text-center"><?= $bmiRow ?? '-' ?></td>
                                            <td class="text-center"><?= $m['top_speed'] !== null ? e($m['top_speed']) : '-' ?></td>
                                            <td class="text-center"><?= $m['stamina'] !== null ? e($m['stamina']) : '-' ?></td>
                                            <td class="text-center"><?= $m['strength'] !== null ? e($m['strength']) : '-' ?></td>
                                            <td class="text-center"><?= $m['agility'] !== null ? e($m['agility']) : '-' ?></td>
                                            <td class="text-center"><?= $m['body_fat'] !== null ? e($m['body_fat']) : '-' ?></td>
                                            <td><span class="badge text-bg-<?= e($fitnessBadge[$m['fitness']] ?? 'secondary') ?>"><?= e(ucfirst($m['fitness'])) ?></span></td>
                                            <td class="small text-muted" style="max-width: 220px"><?= e($m['notes'] ?? '') ?></td>
                                            <td class="text-end text-nowrap">
                                                <small class="text-muted me-2"><?= e($m['recorded_by_name'] ?? 'System') ?></small>
                                                <form method="post" action="<?= e(url('/metrics/' . $m['id'] . '/delete')) ?>" class="d-inline"
                                                      onsubmit="return confirm('Delete this test entry?');">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-danger py-0" type="submit" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ============ MENTAL ============ -->
                    <div class="<?= $pane('mental') ?>" id="pane-mental" role="tabpanel">
                        <div class="card bg-light border mb-4">
                            <div class="card-body">
                                <h3 class="h6 mb-3"><i class="bi bi-clipboard2-heart me-2"></i>New wellbeing assessment</h3>
                                <form method="post" action="<?= e(url('/players/' . $player['id'] . '/welfare')) ?>">
                                    <?= csrf_field() ?>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label" for="w_date">Assessment date <span class="text-danger">*</span></label>
                                            <input id="w_date" name="assessment_date" type="date"
                                                   class="form-control <?= error('assessment_date') ? 'is-invalid' : '' ?>"
                                                   value="<?= e((string) old('assessment_date', date('Y-m-d'))) ?>" required>
                                            <?php if (error('assessment_date')): ?><div class="invalid-feedback"><?= e(error('assessment_date')) ?></div><?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="w_wellbeing">Overall wellbeing <span class="text-danger">*</span></label>
                                            <select id="w_wellbeing" name="wellbeing" class="form-select <?= error('wellbeing') ? 'is-invalid' : '' ?>">
                                                <?php foreach (PlayerWelfare::WELLBEING as $state): ?>
                                                    <option value="<?= $state ?>" <?= old('wellbeing', 'good') === $state ? 'selected' : '' ?>>
                                                        <?= e(ucfirst($state)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (error('wellbeing')): ?><div class="invalid-feedback"><?= e(error('wellbeing')) ?></div><?php endif; ?>
                                        </div>
                                        <?php foreach (['confidence' => 'Confidence', 'focus' => 'Focus', 'motivation' => 'Motivation', 'stress' => 'Stress (high = more)'] as $field => $label): ?>
                                            <div class="col-md-3 col-6">
                                                <label class="form-label" for="w_<?= $field ?>"><?= $label ?></label>
                                                <select id="w_<?= $field ?>" name="<?= $field ?>" class="form-select <?= error($field) ? 'is-invalid' : '' ?>">
                                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                                        <option value="<?= $i ?>" <?= (int) old($field, $field === 'stress' ? 4 : 7) === $i ? 'selected' : '' ?>><?= $i ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <?php if (error($field)): ?><div class="invalid-feedback"><?= e(error($field)) ?></div><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="col-12">
                                            <label class="form-label" for="w_notes">Notes</label>
                                            <textarea id="w_notes" name="notes" rows="2"
                                                      class="form-control <?= error('notes') ? 'is-invalid' : '' ?>"
                                                      placeholder="Mood, motivation, off-pitch concerns..."><?= e((string) old('notes')) ?></textarea>
                                            <?php if (error('notes')): ?><div class="invalid-feedback"><?= e(error('notes')) ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="bi bi-plus-lg me-1"></i>Save assessment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <h3 class="h6 text-uppercase text-muted mb-3">Assessment history (<?= count($welfare) ?>)</h3>
                        <?php if ($welfare === []): ?>
                            <p class="text-muted small">No wellbeing assessments recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-center">Confidence</th>
                                        <th class="text-center">Focus</th>
                                        <th class="text-center">Motivation</th>
                                        <th class="text-center">Stress</th>
                                        <th>Wellbeing</th>
                                        <th>Notes</th>
                                        <th class="text-end"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($welfare as $w): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= e(format_date($w['assessment_date'])) ?></td>
                                            <?php foreach (['confidence', 'focus', 'motivation', 'stress'] as $score): ?>
                                                <td class="text-center">
                                                    <span class="badge text-bg-<?= (int) $w[$score] >= 7 ? 'success' : ((int) $w[$score] >= 4 ? 'warning' : 'danger') ?>">
                                                        <?= (int) $w[$score] ?>
                                                    </span>
                                                </td>
                                            <?php endforeach; ?>
                                            <td><span class="badge text-bg-<?= e($wellbeingBadge[$w['wellbeing']] ?? 'secondary') ?>"><?= e(ucfirst($w['wellbeing'])) ?></span></td>
                                            <td class="small text-muted" style="max-width: 220px"><?= e($w['notes'] ?? '') ?></td>
                                            <td class="text-end text-nowrap">
                                                <small class="text-muted me-2"><?= e($w['assessed_by_name'] ?? 'System') ?></small>
                                                <form method="post" action="<?= e(url('/welfare/' . $w['id'] . '/delete')) ?>" class="d-inline"
                                                      onsubmit="return confirm('Delete this assessment?');">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-danger py-0" type="submit" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Deep links (#statistics, #physical, #mental) and inline snapshot links open their tab.
    (function () {
        function activate(hash) {
            const map = { '#statistics': 'pane-statistics', '#physical': 'pane-physical', '#mental': 'pane-mental' };
            const paneId = map[hash];
            if (!paneId || !document.getElementById(paneId)) return;
            const btn = document.querySelector('[data-bs-target="#' + paneId + '"]');
            if (btn) new bootstrap.Tab(btn).show();
        }
        if (location.hash) activate(location.hash);
        document.querySelectorAll('.js-tab-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                activate('#' + link.dataset.tab);
            });
        });
    })();
</script>
