<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Settings Overview</h1>
        <p class="page-description">General runtime settings and quick access to configuration modules.</p>
    </div>
</div>

<div class="settings-overview-grid">
    <a class="settings-overview-card" href="/?route=settings-users">
        <div class="settings-overview-icon"><i class="fa-solid fa-users"></i></div>
        <div class="settings-overview-title">Users</div>
        <div class="settings-overview-meta"><?= e((string) $usersCount) ?> additional users</div>
    </a>

    <a class="settings-overview-card" href="/?route=settings-cloudflare">
        <div class="settings-overview-icon"><i class="fa-solid fa-cloud"></i></div>
        <div class="settings-overview-title">Cloudflare</div>
        <div class="settings-overview-meta"><?= $cfEnabled ? 'Enabled' : 'Disabled' ?> · <?= e((string) $cfDomainMappingsCount) ?> domain mappings</div>
    </a>

    <a class="settings-overview-card" href="/?route=settings-npm">
        <div class="settings-overview-icon"><i class="fa-solid fa-network-wired"></i></div>
        <div class="settings-overview-title">NPM</div>
        <div class="settings-overview-meta"><?= $npmEnabled ? 'Enabled' : 'Disabled' ?></div>
    </a>
</div>

<section class="form-card settings-card" style="margin-top: 16px;">
    <h2 class="settings-title">General Settings</h2>
    <p class="settings-subtitle">Core app behavior and defaults.</p>

    <form class="form" method="post" action="/?route=settings-save-general" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="app_url">App URL</label>
                <input class="form-input" id="app_url" type="url" name="app_url" value="<?= e((string) $appUrl) ?>" required>
                <span class="form-hint">Used for generated links and HTTPS behavior. Keep this as your external Vhost Manager URL.</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="vhost_base_domain">Default Base Domain</label>
                <input class="form-input" id="vhost_base_domain" type="text" name="vhost_base_domain" value="<?= e((string) $baseDomain) ?>" placeholder="example.com">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="session_name">Session Name</label>
                <input class="form-input" id="session_name" type="text" name="session_name" value="<?= e((string) $sessionName) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="session_idle_timeout">Session Idle Timeout (seconds)</label>
                <input class="form-input" id="session_idle_timeout" type="number" min="300" max="86400" name="session_idle_timeout" value="<?= e((string) $sessionIdleTimeout) ?>" required>
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
                <span class="form-hint">Add or remove docroot bases in docker compose, then restart and log in again.</span>
            </div>
        </div>

        <?php foreach (($allowedDocrootBases ?? []) as $base): ?>
            <input type="hidden" name="allowed_docroot_bases[]" value="<?= e((string) $base) ?>">
        <?php endforeach; ?>

        <div class="form-group">
            <label class="form-label">Configured Docroot Bases</label>
            <code class="form-code-preview"><?= e(implode(', ', array_map('strval', $allowedDocrootBases ?? []))) ?></code>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="app_https">HTTPS</label>
                <select class="form-select" id="app_https" name="app_https">
                    <option value="0" <?= !$appHttps ? 'selected' : '' ?>>Disabled</option>
                    <option value="1" <?= $appHttps ? 'selected' : '' ?>>Enabled</option>
                </select>
            </div>
        </div>

        <div class="btn-group" style="margin-top: 4px;">
            <button class="btn btn--primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Save General Settings
            </button>
        </div>
    </form>
</section>
