<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Cloudflare Settings</h1>
        <p class="page-description">Global Cloudflare integration defaults.</p>
    </div>
</div>

<section class="form-card settings-card">
    <h2 class="settings-title">Cloudflare Integration</h2>
    <p class="settings-subtitle">Use domain mappings for per-domain API token and zone configuration.</p>

    <form class="form" method="post" action="/?route=settings-cloudflare-save" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

        <label class="form-check">
            <input type="checkbox" name="cf_enabled" value="1" <?= !empty($cfEnabled) ? 'checked' : '' ?>>
            Enable Cloudflare integration
        </label>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="cf_api_token">Default API Token</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="cf_api_token" type="password" name="cf_api_token" value="<?= e((string) $cfApiToken) ?>" autocomplete="off" spellcheck="false">
                    <button class="secret-toggle-btn" type="button" data-secret-target="cf_api_token" aria-controls="cf_api_token" aria-label="Show secret" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="cf_zone_id">Default Zone ID</label>
                <input class="form-input" id="cf_zone_id" type="text" name="cf_zone_id" value="<?= e((string) $cfZoneId) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="cf_record_ip">Record IP</label>
                <input class="form-input" id="cf_record_ip" type="text" name="cf_record_ip" value="<?= e((string) $cfRecordIp) ?>" placeholder="1.2.3.4">
            </div>
            <div class="form-group">
                <label class="form-label" for="cf_ttl">TTL</label>
                <input class="form-input" id="cf_ttl" type="number" min="1" max="86400" name="cf_ttl" value="<?= e((string) $cfTtl) ?>">
            </div>
        </div>

        <label class="form-check">
            <input type="checkbox" name="cf_proxied" value="1" <?= !empty($cfProxied) ? 'checked' : '' ?>>
            Proxied records by default
        </label>

        <div class="btn-group" style="margin-top: 4px;">
            <button class="btn btn--primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Cloudflare Settings
            </button>
            <a href="/?route=settings-cloudflare-domains" class="btn btn--ghost">Manage Domain Mappings (<?= e((string) $mappingsCount) ?>)</a>
        </div>
    </form>
</section>
