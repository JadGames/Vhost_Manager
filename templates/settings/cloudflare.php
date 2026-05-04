<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Cloudflare Settings</h1>
        <p class="page-description">Enable Cloudflare integration for per-domain DNS settings.</p>
    </div>
</div>

<section class="form-card settings-card">
    <h2 class="settings-title">Cloudflare Integration</h2>
    <p class="settings-subtitle">Cloudflare API token, zone, and DNS record behavior are configured per domain from the Domains page.</p>

    <form class="form" method="post" action="/?route=settings-cloudflare-save" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

        <label class="form-check">
            <input type="checkbox" name="cf_enabled" value="1" <?= !empty($cfEnabled) ? 'checked' : '' ?>>
            Enable Cloudflare integration
        </label>

        <p class="form-hint" style="margin-top: 6px;">
            When enabled, domain forms can include Cloudflare fields (zone ID, API token, DNS defaults). These values are not managed globally.
        </p>

        <div class="btn-group" style="margin-top: 4px;">
            <button class="btn btn--primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Cloudflare Integration
            </button>
            <a href="/?route=domains" class="btn btn--ghost">Go to Domains</a>
        </div>
    </form>
</section>
