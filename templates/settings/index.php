<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Settings</h1>
        <p class="page-description">System configuration and integration management.</p>
    </div>
</div>

<div class="settings-tiles-stack">

    <!-- Users tile -->
    <div class="settings-tile">
        <div class="settings-tile__header">
            <div class="settings-tile__header-left">
                <div class="settings-tile__icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="settings-tile__title">Users</div>
                    <div class="settings-tile__subtitle">
                        <?= e((string) ($adminUser ?? '')) ?> (admin)
                        <?php if ($usersCount > 0): ?>
                            · <?= e((string) $usersCount) ?> additional user<?= $usersCount !== 1 ? 's' : '' ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="settings-tile__header-actions">
                <a href="/?route=settings-users" class="btn btn--ghost btn--sm">Manage Users</a>
            </div>
        </div>
        <div class="settings-tile__body">
            <div class="settings-tile__list">
                <span class="settings-tile__tag is-active">
                    <span class="tag-dot"></span>
                    <?= e((string) ($adminUser ?? 'admin')) ?> (admin)
                </span>
                <?php foreach (($additionalUsers ?? []) as $uname => $hash): ?>
                    <span class="settings-tile__tag">
                        <span class="tag-dot"></span>
                        <?= e((string) $uname) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Apache Modules tile -->
    <div class="settings-tile">
        <div class="settings-tile__header">
            <div class="settings-tile__header-left">
                <div class="settings-tile__icon"><i class="fa-solid fa-plug-circle-bolt"></i></div>
                <div>
                    <div class="settings-tile__title">Apache Modules</div>
                    <div class="settings-tile__subtitle"><?= e((string) $enabledModuleCount) ?> enabled · <?= e((string) $apacheModulesCount) ?> total</div>
                </div>
            </div>
            <div class="settings-tile__header-actions">
                <a href="/?route=settings-apache-modules" class="btn btn--ghost btn--sm">Manage Modules</a>
            </div>
        </div>
        <div class="settings-tile__body">
            <div class="settings-tile__list">
                <?php foreach (($apacheModules ?? []) as $mod): ?>
                    <?php if (!empty($mod['enabled'])): ?>
                        <span class="settings-tile__tag is-active">
                            <span class="tag-dot"></span>
                            <?= e((string) ($mod['label'] ?? $mod['module'] ?? '')) ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($enabledModuleCount === 0): ?>
                    <span class="settings-tile__empty">No modules enabled.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Integrations tile -->
    <div class="settings-tile">
        <div class="settings-tile__header">
            <div class="settings-tile__header-left">
                <div class="settings-tile__icon"><i class="fa-solid fa-puzzle-piece"></i></div>
                <div>
                    <div class="settings-tile__title">Integrations</div>
                    <div class="settings-tile__subtitle"><?= e((string) count($integrations ?? [])) ?> configured</div>
                </div>
            </div>
            <div class="settings-tile__header-actions">
                <?php if (!empty($integrations)): ?>
                    <button class="btn btn--ghost btn--sm" id="test-all-integrations-btn" type="button">
                        <i class="fa-solid fa-bolt"></i> Test All
                    </button>
                <?php endif; ?>
                <a href="/?route=settings-integrations" class="btn btn--ghost btn--sm">Manage Integrations</a>
            </div>
        </div>
        <?php if (!empty($integrations)): ?>
            <div class="settings-tile__body">
                <?php foreach ($integrations as $int): ?>
                    <?php
                        $providers = \App\Services\IntegrationRegistry::providers();
                        $provInfo  = $providers[$int['provider'] ?? ''] ?? null;
                        $icon      = $provInfo ? $provInfo['icon'] : 'fa-puzzle-piece';
                    ?>
                    <div class="settings-tile__integration-row">
                        <div class="settings-tile__integration-info">
                            <div class="settings-tile__integration-icon">
                                <i class="fa-solid <?= e($icon) ?>"></i>
                            </div>
                            <div>
                                <div class="settings-tile__integration-name"><?= e((string) ($int['name'] ?? '')) ?></div>
                                <div class="settings-tile__integration-provider"><?= e((string) ($provInfo['label'] ?? ($int['provider'] ?? ''))) ?></div>
                            </div>
                        </div>
                        <div class="settings-tile__integration-actions">
                            <button class="btn btn--ghost btn--sm"
                                    type="button"
                                    data-test-integration="<?= e((string) ($int['id'] ?? '')) ?>"
                                    title="Test connection">
                                <i class="fa-solid fa-bolt"></i> Test
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="settings-tile__body">
                <p class="settings-tile__empty">No integrations configured. <a href="/?route=settings-integrations" class="link-accent">Add one</a>.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- General Settings tile -->
    <div class="settings-tile">
        <div class="settings-tile__header">
            <div class="settings-tile__header-left">
                <div class="settings-tile__icon"><i class="fa-solid fa-sliders"></i></div>
                <div>
                    <div class="settings-tile__title">General Settings</div>
                    <div class="settings-tile__subtitle">Core app behavior and defaults.</div>
                </div>
            </div>
        </div>
        <div class="settings-tile__body">
            <form class="form" method="post" action="/?route=settings-save-general" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="app_url">App URL</label>
                        <input class="form-input" id="app_url" type="url" name="app_url" value="<?= e((string) $appUrl) ?>" required>
                        <span class="form-hint">Used for generated links and redirect URLs.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="vhost_base_domain">Default Base Domain</label>
                        <select class="form-select" id="vhost_base_domain" name="vhost_base_domain">
                            <option value="">No default</option>
                            <?php foreach (($domainOptions ?? []) as $domainOption): ?>
                                <?php $domainOption = (string) $domainOption; ?>
                                <option value="<?= e($domainOption) ?>" <?= $domainOption === (string) $baseDomain ? 'selected' : '' ?>>
                                    <?= e($domainOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">This list is sourced from domain records and will grow as the Domains section is expanded.</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="default_docroot_base">Default Docroot Base</label>
                        <select class="form-select" id="default_docroot_base" name="default_docroot_base" required>
                            <?php foreach (($allowedDocrootBases ?? []) as $base): ?>
                                <?php $base = (string) $base; ?>
                                <option value="<?= e($base) ?>" <?= $base === (string) $defaultDocrootBase ? 'selected' : '' ?>>
                                    <?= e($base) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-hint">Add or remove docroot bases in docker compose, then restart.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="app_https">HTTPS</label>
                        <select class="form-select" id="app_https" name="app_https">
                            <option value="0" <?= !$appHttps ? 'selected' : '' ?>>Disabled</option>
                            <option value="1" <?= $appHttps ? 'selected' : '' ?>>Enabled</option>
                        </select>
                        <span class="form-hint">Enables secure session cookies and tells the app to generate HTTPS links.</span>
                    </div>
                </div>

                <?php foreach (($allowedDocrootBases ?? []) as $base): ?>
                    <input type="hidden" name="allowed_docroot_bases[]" value="<?= e((string) $base) ?>">
                <?php endforeach; ?>

                <div class="btn-group" style="margin-top: 4px;">
                    <button class="btn btn--primary" type="submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save General Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
(function () {
    // Test single integration
    document.querySelectorAll('[data-test-integration]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-test-integration');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            fetch('/?route=settings-integrations-test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(document.querySelector('input[name=csrf_token]') ? document.querySelector('input[name=csrf_token]').value : '')
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                btn.innerHTML = data.ok ? '<i class="fa-solid fa-circle-check" style="color:var(--accent)"></i> OK' : '<i class="fa-solid fa-circle-xmark" style="color:var(--danger)"></i> Failed';
                setTimeout(function () { btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Test'; }, 3000);
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Test';
            });
        });
    });

    // Test all integrations
    var testAllBtn = document.getElementById('test-all-integrations-btn');
    if (testAllBtn) {
        testAllBtn.addEventListener('click', function () {
            document.querySelectorAll('[data-test-integration]').forEach(function (b) { b.click(); });
        });
    }
}());
</script>
