<?php declare(strict_types=1); ?>

<?php
$cfEnabled = !empty($cfEnabled);
$domains = is_array($domains ?? null) ? $domains : [];
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Domains</h1>
        <p class="page-description">Store per-domain integration settings used by domain workflows.</p>
    </div>
</div>

<section class="form-card settings-card" style="max-width: 820px;">
    <h2 class="settings-title">Domain Profile</h2>
    <p class="settings-subtitle">Backend support is now ready for per-domain settings. Cloudflare fields appear only when Cloudflare integration is enabled.</p>

    <form class="form" method="post" action="/?route=domains-save" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

        <div class="form-group">
            <label class="form-label" for="domain">Domain</label>
            <input class="form-input" id="domain" type="text" name="domain" placeholder="example.com" required>
        </div>

        <?php if ($cfEnabled): ?>
            <fieldset class="form-fieldset">
                <legend class="form-legend"><i class="fa-solid fa-cloud"></i> Cloudflare (Per Domain)</legend>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cf_zone_id">Zone ID</label>
                        <input class="form-input" id="cf_zone_id" type="text" name="cf_zone_id" placeholder="32-char zone id">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cf_api_token">API Token</label>
                        <div class="secret-input-wrap">
                            <input class="form-input" id="cf_api_token" type="password" name="cf_api_token" autocomplete="off" spellcheck="false">
                            <button class="secret-toggle-btn" type="button" data-secret-target="cf_api_token" aria-controls="cf_api_token" aria-label="Show secret" aria-pressed="false">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cf_record_ip">Record IP</label>
                        <input class="form-input" id="cf_record_ip" type="text" name="cf_record_ip" placeholder="1.2.3.4">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cf_ttl">TTL</label>
                        <input class="form-input" id="cf_ttl" type="number" name="cf_ttl" min="1" max="86400" value="120">
                    </div>
                </div>

                <label class="form-check">
                    <input type="checkbox" name="cf_proxied" value="1" checked>
                    Proxied records by default
                </label>
            </fieldset>
        <?php endif; ?>

        <div class="btn-group" style="margin-top: 10px;">
            <button class="btn btn--primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Domain Profile
            </button>
        </div>
    </form>
</section>

<section class="form-card settings-card" style="max-width: 820px; margin-top: 14px;">
    <h2 class="settings-title">Saved Domains</h2>
    <?php if ($domains === []): ?>
        <p class="form-hint">No domain profiles saved yet.</p>
    <?php else: ?>
        <div class="logs-list" role="list">
            <?php foreach ($domains as $row): ?>
                <div class="logs-item" role="listitem">
                    <div class="logs-line">
                        <span class="logs-level logs-level--info"><i class="fa-solid fa-globe"></i> <?= e((string) ($row['domain'] ?? '')) ?></span>
                        <?php if ($cfEnabled && is_array($row['cloudflare'] ?? null)): ?>
                            <span class="logs-message">Cloudflare configured for this domain.</span>
                        <?php else: ?>
                            <span class="logs-message">No Cloudflare domain settings stored.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
