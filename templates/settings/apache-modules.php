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
            <option value="requested">Requested</option>
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
                $isRequested = !empty($module['requested']);
                $requestData = $module['request_data'] ?? null;
            ?>
            <article
                class="apache-module-card<?= $isRequired ? ' is-required' : '' ?><?= $isRequested ? ' is-requested' : '' ?>"
                data-module-card
                data-module-title="<?= e(strtolower($label . ' ' . $name)) ?>"
                data-module-description="<?= e(strtolower($description . ' ' . $keywords)) ?>"
                data-module-enabled="<?= $isEnabled ? '1' : '0' ?>"
                data-module-requested="<?= $isRequested ? '1' : '0' ?>"
                data-search-text="<?= e(strtolower($label . ' ' . $name . ' ' . $description . ' ' . $keywords . ' ' . ($isEnabled ? 'enabled' : 'disabled') . ' ' . ($isRequired ? 'required' : 'optional') . ' ' . ($isRequested ? 'requested' : ''))) ?>"
                style="position:relative;"
            >
                <?php if ($isAdmin && $isRequested): ?>
                    <span class="module-card-notif" title="Pending request"></span>
                <?php endif; ?>
                <div class="apache-module-card__header">
                    <div>
                        <div class="apache-module-card__title-row">
                            <h3 class="apache-module-card__title"><?= e($label) ?></h3>
                            <code class="apache-module-card__name"><?= e($name) ?></code>
                        </div>
                        <p class="apache-module-card__description"><?= e($description) ?></p>
                    </div>
                    <?php if ($isRequested): ?>
                        <span class="apache-module-card__badge is-requested">Requested</span>
                    <?php else: ?>
                        <span class="apache-module-card__badge<?= $isEnabled ? ' is-enabled' : ' is-disabled' ?>">
                            <?= $isEnabled ? 'Enabled' : 'Disabled' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="apache-module-card__footer">
                    <?php if ($isRequired): ?>
                        <div class="apache-module-card__required"><?= e($requiredMessage) ?></div>
                        <button class="btn btn--ghost apache-module-card__btn" type="button" disabled aria-disabled="true">Required</button>
                    <?php elseif ($isAdmin): ?>
                        <?php if ($isRequested && $requestData): ?>
                            <!-- Admin sees: Reason, Reject, Approve -->
                            <div class="apache-module-card__request-actions">
                                <button class="btn btn--ghost btn--sm apache-module-card__btn"
                                        type="button"
                                        data-action="reason-modal"
                                        data-module="<?= e($name) ?>"
                                        data-reason="<?= e((string) ($requestData['reason'] ?? '')) ?>"
                                        data-requested-by="<?= e((string) ($requestData['requested_by'] ?? '')) ?>">
                                    <i class="fa-solid fa-message"></i> Reason
                                </button>
                                <form method="post" action="/?route=settings-apache-modules-action" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                                    <input type="hidden" name="module" value="<?= e($name) ?>">
                                    <input type="hidden" name="intent" value="reject">
                                    <button class="btn btn--danger btn--sm apache-module-card__btn" type="submit">Reject</button>
                                </form>
                                <form method="post" action="/?route=settings-apache-modules-action" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                                    <input type="hidden" name="module" value="<?= e($name) ?>">
                                    <input type="hidden" name="intent" value="approve">
                                    <button class="btn btn--primary btn--sm apache-module-card__btn" type="submit">Approve</button>
                                </form>
                            </div>
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
                    <?php else: ?>
                        <!-- Non-admin user: Request button (only for non-enabled modules) -->
                        <?php if (!$isEnabled): ?>
                            <?php if ($isRequested): ?>
                                <button class="btn btn--ghost apache-module-card__btn" type="button" disabled aria-disabled="true">
                                    <i class="fa-solid fa-clock"></i> Requested
                                </button>
                            <?php else: ?>
                                <button class="btn btn--primary apache-module-card__btn" type="button"
                                        data-action="request-modal"
                                        data-module="<?= e($name) ?>">
                                    Request
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn--ghost apache-module-card__btn" type="button" disabled aria-disabled="true">Enabled</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Request Modal (non-admin) -->
<dialog id="module-request-modal" class="dialog">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title">Request Module</p>
            <p class="dialog-subtitle">Enable <strong id="module-request-modal-name"></strong></p>
        </div>
        <button class="dialog-close-btn" type="button" data-close-dialog="module-request-modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <form method="post" action="/?route=settings-apache-modules-action" id="module-request-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="module" id="module-request-module-input">
            <input type="hidden" name="intent" value="request">
            <div class="form-group">
                <label class="form-label" for="module-request-reason">Reason <span style="color:var(--text-3);font-size:11px;text-transform:none;letter-spacing:0;">(optional)</span></label>
                <textarea class="form-input" id="module-request-reason" name="reason" rows="5" placeholder="Briefly explain why you need this module…"></textarea>
            </div>
            <div class="dialog-footer" style="margin-top: 16px;">
                <button type="button" class="btn btn--ghost" data-close-dialog="module-request-modal">Cancel</button>
                <button type="submit" class="btn btn--primary">Submit Request</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Reason Modal (admin) -->
<dialog id="module-reason-modal" class="dialog">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title">Module Request</p>
            <p class="dialog-subtitle">Module: <strong id="reason-modal-module-name"></strong></p>
        </div>
        <button class="dialog-close-btn" type="button" data-close-dialog="module-reason-modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <div class="form-group">
            <label class="form-label">Requested By</label>
            <p id="reason-modal-requested-by" class="form-input" style="min-height:auto;padding:.45rem .65rem;color:var(--text-2);margin:0;"></p>
        </div>
        <div class="form-group" style="margin-top:10px;">
            <label class="form-label">Reason</label>
            <p id="reason-modal-reason" style="background:var(--surface-2);border-radius:var(--r-md);padding:.6rem .8rem;font-size:13px;min-height:60px;color:var(--text-2);margin:0;"></p>
        </div>
        <div class="dialog-footer" style="margin-top: 16px;">
            <button type="button" class="btn btn--ghost" data-close-dialog="module-reason-modal">Close</button>
            <form method="post" action="/?route=settings-apache-modules-action" style="display:inline;" id="reason-reject-form">
                <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                <input type="hidden" name="module" id="reason-modal-reject-module">
                <input type="hidden" name="intent" value="reject">
                <button type="submit" class="btn btn--danger">Reject</button>
            </form>
            <form method="post" action="/?route=settings-apache-modules-action" style="display:inline;" id="reason-approve-form">
                <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                <input type="hidden" name="module" id="reason-modal-approve-module">
                <input type="hidden" name="intent" value="approve">
                <button type="submit" class="btn btn--primary">Approve</button>
            </form>
        </div>
    </div>
</dialog>

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
            var requested = card.classList.contains('is-requested')
                || String(card.getAttribute('data-module-requested') || '0') === '1';

            var matchesText = query === ''
                || title.indexOf(query) !== -1
                || description.indexOf(query) !== -1;

            var matchesStatus = status === 'all'
                || (status === 'enabled'    && enabled)
                || (status === 'disabled'   && !enabled)
                || (status === 'requested'  && requested);

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

    // Open requested module reason modal when arriving from notifications.
    var params = new URLSearchParams(window.location.search || '');
    var openRequest = String(params.get('open_request') || '').trim().toLowerCase();
    if (openRequest !== '') {
        var reasonBtn = list.querySelector('[data-action="reason-modal"][data-module="' + openRequest + '"]');
        if (reasonBtn) {
            openModuleReasonModal(
                reasonBtn.getAttribute('data-module') || '',
                reasonBtn.getAttribute('data-reason') || '',
                reasonBtn.getAttribute('data-requested-by') || ''
            );
        }
    }

    // ── Event delegation for data-action buttons ─────────────────────────────

    document.addEventListener('click', function (e) {
        // Close dialog buttons
        var closeBtn = e.target.closest('[data-close-dialog]');
        if (closeBtn) {
            var dlgId = closeBtn.getAttribute('data-close-dialog');
            var dlg = document.getElementById(dlgId);
            if (dlg) { dlg.close(); }
            return;
        }

        // Request modal trigger
        var reqBtn = e.target.closest('[data-action="request-modal"]');
        if (reqBtn) {
            openModuleRequestModal(reqBtn.getAttribute('data-module') || '');
            return;
        }

        // Reason modal trigger
        var rsnBtn = e.target.closest('[data-action="reason-modal"]');
        if (rsnBtn) {
            openModuleReasonModal(
                rsnBtn.getAttribute('data-module') || '',
                rsnBtn.getAttribute('data-reason') || '',
                rsnBtn.getAttribute('data-requested-by') || ''
            );
            return;
        }
    });
}());

function openModuleRequestModal(moduleName) {
    var modal   = document.getElementById('module-request-modal');
    var nameEl  = document.getElementById('module-request-modal-name');
    var input   = document.getElementById('module-request-module-input');
    var reason  = document.getElementById('module-request-reason');
    if (!modal) { return; }
    if (nameEl)  { nameEl.textContent  = moduleName; }
    if (input)   { input.value         = moduleName; }
    if (reason)  { reason.value        = ''; }
    if (typeof openDialog === 'function') {
        openDialog(modal);
    } else {
        modal.showModal();
    }
}

function openModuleReasonModal(moduleName, reason, requestedBy) {
    var modal   = document.getElementById('module-reason-modal');
    if (!modal) { return; }
    var nameEl  = document.getElementById('reason-modal-module-name');
    var byEl    = document.getElementById('reason-modal-requested-by');
    var reasonEl= document.getElementById('reason-modal-reason');
    var rej1    = document.getElementById('reason-modal-reject-module');
    var app1    = document.getElementById('reason-modal-approve-module');
    if (nameEl)   { nameEl.textContent   = moduleName; }
    if (byEl)     { byEl.textContent     = requestedBy || '(unknown)'; }
    if (reasonEl) { reasonEl.textContent = reason || '(no reason provided)'; }
    if (rej1)     { rej1.value           = moduleName; }
    if (app1)     { app1.value           = moduleName; }
    if (typeof openDialog === 'function') {
        openDialog(modal);
    } else {
        modal.showModal();
    }
}
</script>