<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">NPM Settings</h1>
        <p class="page-description">Connection and proxy defaults for Nginx Proxy Manager integration.</p>
    </div>
</div>

<section class="form-card settings-card">
    <h2 class="settings-title">NPM Integration</h2>
    <p class="settings-subtitle">Store API credentials and upstream target defaults.</p>

    <form class="form" method="post" action="/?route=settings-npm-save" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

        <label class="form-check">
            <input type="checkbox" name="npm_enabled" value="1" <?= !empty($npmEnabled) ? 'checked' : '' ?>>
            Enable NPM integration
        </label>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="npm_base_url">Base URL</label>
                <input class="form-input" id="npm_base_url" type="url" name="npm_base_url" value="<?= e((string) $npmBaseUrl) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="npm_identity">Identity (email)</label>
                <input class="form-input" id="npm_identity" type="text" name="npm_identity" value="<?= e((string) $npmIdentity) ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="npm_secret">Secret (password)</label>
            <div class="secret-input-wrap">
                <input class="form-input" id="npm_secret" type="password" name="npm_secret" value="<?= e((string) $npmSecret) ?>" autocomplete="off" spellcheck="false">
                <button class="secret-toggle-btn" type="button" data-secret-target="npm_secret" aria-controls="npm_secret" aria-label="Show secret" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
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
</section>
