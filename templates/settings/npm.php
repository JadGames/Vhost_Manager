<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">NPM Settings</h1>
        <p class="page-description">Provision a dedicated VHM account once, then manage runtime forwarding defaults.</p>
    </div>
</div>

<section class="form-card settings-card">
    <h2 class="settings-title">NPM Integration</h2>
    <p class="settings-subtitle">Admin credentials are used once to create a dedicated non-admin VHM account and are never saved.</p>

    <?php if (empty($hasNpmServiceAccount)): ?>
        <form class="form" method="post" action="/?route=settings-npm-save" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="bootstrap-account">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="npm_base_url">Base URL</label>
                    <input class="form-input" id="npm_base_url" type="url" name="npm_base_url" value="<?= e((string) $npmBaseUrl) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="npm_admin_identity">One-time Admin Email</label>
                    <input class="form-input" id="npm_admin_identity" type="email" name="npm_admin_identity" value="" placeholder="admin@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="npm_admin_secret">One-time Admin Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="npm_admin_secret" type="password" name="npm_admin_secret" value="" autocomplete="off" spellcheck="false" required>
                    <button class="secret-toggle-btn" type="button" data-secret-target="npm_admin_secret" aria-controls="npm_admin_secret" aria-label="Show secret" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <span class="form-hint">This is used one time to create <strong><?= e((string) (strtok((string) ($npmIdentity ?: 'vhm@example.com'), '@') ?: 'vhm')) ?>@vhost-manager.npm</strong> and is not persisted.</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="npm_forward_host">Forward Host</label>
                    <input class="form-input" id="npm_forward_host" type="text" name="npm_forward_host" value="<?= e((string) $npmForwardHost) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="npm_forward_port">Forward Port</label>
                    <input class="form-input" id="npm_forward_port" type="number" min="1" max="65535" name="npm_forward_port" value="<?= e((string) $npmForwardPort) ?>" required>
                </div>
            </div>

            <div class="btn-group" style="margin-top: 4px;">
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-user-plus"></i>
                    Create VHM NPM Account
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="integration-banner" style="margin-bottom: 12px;">
            <strong><i class="fa-solid fa-shield-halved"></i> Active VHM NPM Account:</strong>
            <span><?= e((string) $npmIdentity) ?></span>
        </div>

        <form class="form" method="post" action="/?route=settings-npm-save" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="save-routing">

            <label class="form-check">
                <input type="checkbox" name="npm_enabled" value="1" <?= !empty($npmEnabled) ? 'checked' : '' ?>>
                Enable NPM integration
            </label>

            <div class="form-group">
                <label class="form-label" for="npm_base_url">Base URL</label>
                <input class="form-input" id="npm_base_url" type="url" name="npm_base_url" value="<?= e((string) $npmBaseUrl) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="npm_forward_host">Forward Host</label>
                    <input class="form-input" id="npm_forward_host" type="text" name="npm_forward_host" value="<?= e((string) $npmForwardHost) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="npm_forward_port">Forward Port</label>
                    <input class="form-input" id="npm_forward_port" type="number" min="1" max="65535" name="npm_forward_port" value="<?= e((string) $npmForwardPort) ?>" required>
                </div>
            </div>

            <div class="btn-group" style="margin-top: 4px;">
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save NPM Settings
                </button>
                <a href="/?route=settings-npm-ssl" class="btn btn--ghost">Go to SSL Settings</a>
            </div>
        </form>
    <?php endif; ?>
</section>
