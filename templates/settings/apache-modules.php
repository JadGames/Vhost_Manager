<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Apache Modules</h1>
        <p class="page-description">Search common Apache modules and enable or disable supported ones.</p>
    </div>
</div>

<section class="form-card settings-card">
    <h2 class="settings-title">Module Controls</h2>
    <p class="settings-subtitle"><?= e((string) count($modules ?? [])) ?> listed · <?= e((string) ($requiredCount ?? 0)) ?> required by Vhost Manager</p>

    <div class="form-group">
        <label class="form-label" for="apache-module-search">Search Modules</label>
        <input class="form-input" id="apache-module-search" type="search" placeholder="Search by name, purpose, or keyword" autocomplete="off">
    </div>

    <div class="form-group" style="margin-top: 10px;">
        <label class="form-label" for="apache-module-status-filter">Filter</label>
        <select class="form-select" id="apache-module-status-filter" aria-label="Filter modules by enabled status">
            <option value="all" selected>All Modules</option>
            <option value="enabled">Enabled Only</option>
            <option value="disabled">Disabled Only</option>
        </select>
    </div>

    <div id="apache-modules-empty" class="form-hint" hidden>No modules match your search.</div>

    <div id="apache-modules-list" class="apache-modules-grid">
        <?php foreach (($modules ?? []) as $module): ?>
            <?php
                $name = (string) ($module['module'] ?? '');
                $label = (string) ($module['label'] ?? $name);
                $description = (string) ($module['description'] ?? '');
                $keywords = (string) ($module['keywords'] ?? '');
                $isEnabled = !empty($module['enabled']);
                $isRequired = !empty($module['required']);
                $requiredMessage = (string) ($module['required_message'] ?? '');
            ?>
            <article
                class="apache-module-card<?= $isRequired ? ' is-required' : '' ?>"
                data-module-card
                data-module-title="<?= e(strtolower($label . ' ' . $name)) ?>"
                data-module-description="<?= e(strtolower($description . ' ' . $keywords)) ?>"
                data-module-enabled="<?= $isEnabled ? '1' : '0' ?>"
                data-search-text="<?= e(strtolower($label . ' ' . $name . ' ' . $description . ' ' . $keywords . ' ' . ($isEnabled ? 'enabled' : 'disabled') . ' ' . ($isRequired ? 'required' : 'optional'))) ?>"
            >
                <div class="apache-module-card__header">
                    <div>
                        <div class="apache-module-card__title-row">
                            <h3 class="apache-module-card__title"><?= e($label) ?></h3>
                            <code class="apache-module-card__name"><?= e($name) ?></code>
                        </div>
                        <p class="apache-module-card__description"><?= e($description) ?></p>
                    </div>
                    <span class="apache-module-card__badge<?= $isEnabled ? ' is-enabled' : ' is-disabled' ?>">
                        <?= $isEnabled ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>

                <div class="apache-module-card__footer">
                    <?php if ($isRequired): ?>
                        <div class="apache-module-card__required"><?= e($requiredMessage) ?></div>
                        <button class="btn btn--ghost apache-module-card__btn" type="button" disabled aria-disabled="true">Required</button>
                    <?php else: ?>
                        <form class="apache-module-card__actions" method="post" action="/?route=settings-apache-modules-action" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                            <input type="hidden" name="module" value="<?= e($name) ?>">
                            <input type="hidden" name="enabled" value="<?= $isEnabled ? '0' : '1' ?>">
                            <button class="btn <?= $isEnabled ? 'btn--ghost' : 'btn--primary' ?> apache-module-card__btn" type="submit">
                                <?= $isEnabled ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
(function () {
    var input = document.getElementById('apache-module-search');
    var statusFilter = document.getElementById('apache-module-status-filter');
    var list = document.getElementById('apache-modules-list');
    var empty = document.getElementById('apache-modules-empty');

    if (!input || !list) {
        return;
    }

    var cards = Array.prototype.slice.call(list.querySelectorAll('[data-module-card]'));

    function applyFilter() {
        var query = String(input.value || '').trim().toLowerCase();
        var status = statusFilter ? String(statusFilter.value || 'all') : 'all';
        var visibleCount = 0;

        cards.forEach(function (card) {
            var title = String(card.getAttribute('data-module-title') || '').toLowerCase();
            var description = String(card.getAttribute('data-module-description') || '').toLowerCase();
            var enabled = String(card.getAttribute('data-module-enabled') || '0') === '1';

            var matchesText = query === ''
                || title.indexOf(query) !== -1
                || description.indexOf(query) !== -1;

            var matchesStatus = status === 'all'
                || (status === 'enabled' && enabled)
                || (status === 'disabled' && !enabled);

            var visible = matchesText && matchesStatus;
            card.hidden = !visible;
            card.classList.toggle('is-hidden', !visible);
            if (visible) {
                visibleCount += 1;
            }
        });

        if (empty) {
            empty.hidden = visibleCount !== 0;
        }
    }

    input.addEventListener('input', applyFilter);
    input.addEventListener('keyup', applyFilter);
    input.addEventListener('search', applyFilter);
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilter);
    }

    applyFilter();
}());
</script>