<?php
/**
 * Standings overview / empty state.
 * @var array $tournaments
 * @var array|null $tournament
 * @var array $rows
 */
?>
<?php if ($tournaments === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-bar-chart-steps display-4 d-block mb-2"></i>
            Create a tournament first &mdash; standings are calculated from its finished matches.
            <div class="mt-3"><a href="<?= e(url('/tournaments')) ?>" class="btn btn-primary">Go to tournaments</a></div>
        </div>
    </div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($tournaments as $t): ?>
            <a href="<?= e(url('/standings/' . $t['id'])) ?>" class="list-group-item list-group-item-action">
                <?= e($t['name']) ?>
                <span class="badge text-bg-light border ms-2"><?= (int) $t['matches_count'] ?> matches</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
