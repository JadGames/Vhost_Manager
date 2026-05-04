<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">System Logs</h1>
        <p class="page-description">Showing newest first (up to <?= e((string) $maxLines) ?> lines).</p>
    </div>
</div>

<div class="form-card settings-card logs-card">
    <form id="logs-controls" class="logs-controls" method="get" action="/" autocomplete="off">
        <input type="hidden" name="route" value="logs">

        <div class="logs-filters">
            <span class="logs-controls-label"><i class="fa-solid fa-filter"></i> Show Types:</span>
            <label class="form-check logs-check">
                <input type="checkbox" name="types[]" value="INFO" <?= in_array('INFO', $selectedTypes ?? [], true) ? 'checked' : '' ?>>
                <i class="fa-solid fa-circle-info"></i> INFO
            </label>
            <label class="form-check logs-check">
                <input type="checkbox" name="types[]" value="WARN" <?= in_array('WARN', $selectedTypes ?? [], true) ? 'checked' : '' ?>>
                <i class="fa-solid fa-triangle-exclamation"></i> WARN
            </label>
            <label class="form-check logs-check">
                <input type="checkbox" name="types[]" value="ERROR" <?= in_array('ERROR', $selectedTypes ?? [], true) ? 'checked' : '' ?>>
                <i class="fa-solid fa-circle-xmark"></i> ERROR
            </label>
        </div>

        <div class="logs-sort-wrap">
            <label class="form-label" for="logs_sort"><i class="fa-solid fa-arrow-up-wide-short"></i> Sort</label>
            <select class="form-select" id="logs_sort" name="sort">
                <option value="newest" <?= (($sort ?? 'newest') === 'newest') ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest" <?= (($sort ?? '') === 'oldest') ? 'selected' : '' ?>>Oldest first</option>
            </select>
        </div>
    </form>

    <form class="logs-actions" id="logs-clear-form" method="post" action="/?route=logs-clear">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
        <button class="btn btn--danger btn--sm" id="logs-clear-trigger" type="button">
            <i class="fa-solid fa-trash"></i>
            Clear Logs
        </button>
    </form>

    <dialog id="logs-clear-confirm-modal" aria-labelledby="logsClearConfirmTitle">
        <div class="dialog-header">
            <div class="dialog-header-text">
                <p class="dialog-title" id="logsClearConfirmTitle">Clear System Logs?</p>
                <p class="dialog-subtitle">This will permanently remove all current log entries.</p>
            </div>
        </div>
        <div class="dialog-body">
            <p class="form-hint" style="margin-bottom: 0;">Use this only if you are sure you no longer need these logs for troubleshooting.</p>
        </div>
        <div class="dialog-footer">
            <form method="dialog">
                <button class="btn btn--ghost" type="submit">Cancel</button>
            </form>
            <button class="btn btn--danger" id="logs-clear-confirm-btn" type="button">
                <i class="fa-solid fa-trash"></i>
                Yes, Clear Logs
            </button>
        </div>
    </dialog>

    <div class="logs-meta">Source: <?= e((string) $logFile) ?></div>

    <?php if (empty($entries)): ?>
        <p class="form-hint" style="margin-top: 10px;">No log entries found.</p>
    <?php else: ?>
        <div class="logs-list" role="list">
            <?php foreach ($entries as $entry): ?>
                <?php
                $level = strtoupper((string) ($entry['level'] ?? 'INFO'));
                $levelClass = match ($level) {
                    'ERROR' => 'logs-level--error',
                    'WARN', 'WARNING' => 'logs-level--warn',
                    default => 'logs-level--info',
                };
                $levelIcon = match ($level) {
                    'ERROR' => 'fa-circle-xmark',
                    'WARN', 'WARNING' => 'fa-triangle-exclamation',
                    default => 'fa-circle-info',
                };
                ?>
                <div class="logs-item" role="listitem" data-level="<?= e($level) ?>" data-epoch="<?= e((string) ($entry['epoch'] ?? 0)) ?>">
                    <div class="logs-line">
                        <span class="logs-level <?= e($levelClass) ?>"><i class="fa-solid <?= e($levelIcon) ?>"></i> [<?= e($level) ?>]</span>
                        <span class="logs-time"><?= e((string) ($entry['date'] ?? '--/--/--')) ?> - <?= e((string) ($entry['time'] ?? '--:--:--')) ?></span>
                        <span class="logs-message"><?= e((string) ($entry['message'] ?? '')) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
