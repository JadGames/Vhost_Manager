<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">NPM SSL Settings</h1>
        <p class="page-description">Default SSL behavior for created/updated NPM proxy hosts.</p>
    </div>
</div>

<section class="form-card settings-card">
    <h2 class="settings-title">SSL Defaults</h2>
    <p class="settings-subtitle">These values apply when provisioning NPM hosts.</p>

    <form class="form" method="post" action="/?route=settings-npm-ssl-save" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

        <label class="form-check">
            <input type="checkbox" name="npm_ssl_enabled" value="1" <?= !empty($npmSslEnabled) ? 'checked' : '' ?>>
            Enable SSL by default
        </label>

        <div class="form-group">
            <label class="form-label" for="npm_certificate_id">Default Certificate ID</label>
            <input class="form-input" id="npm_certificate_id" type="number" min="0" name="npm_certificate_id" value="<?= e((string) $npmCertificateId) ?>">
        </div>

        <div class="form-checks">
            <label class="form-check">
                <input type="checkbox" name="npm_ssl_forced" value="1" <?= !empty($npmSslForced) ? 'checked' : '' ?>>
                Force SSL redirect
            </label>
            <label class="form-check">
                <input type="checkbox" name="npm_http2_support" value="1" <?= !empty($npmHttp2Support) ? 'checked' : '' ?>>
                Enable HTTP/2
            </label>
            <label class="form-check">
                <input type="checkbox" name="npm_hsts_enabled" value="1" <?= !empty($npmHstsEnabled) ? 'checked' : '' ?>>
                Enable HSTS
            </label>
            <label class="form-check">
                <input type="checkbox" name="npm_hsts_subdomains" value="1" <?= !empty($npmHstsSubdomains) ? 'checked' : '' ?>>
                Include subdomains in HSTS
            </label>
        </div>

        <div class="btn-group" style="margin-top: 4px;">
            <button class="btn btn--primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Save SSL Settings
            </button>
        </div>
    </form>
</section>
