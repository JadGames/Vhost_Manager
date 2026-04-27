<?php declare(strict_types=1); ?>
<?php
$cfEnabled         = !empty($cfEnabled);
$npmEnabled        = !empty($npmEnabled);
$domain            = (string) ($entry['domain'] ?? '');
$docroot           = (string) ($entry['docroot'] ?? '');
$npmCertificates   = is_array($npmCertificates ?? null) ? $npmCertificates : [];
$currentCfRecordId = (string) ($entry['cf_record_id'] ?? '');
$currentCfIp       = (string) ($entry['cf_record_ip'] ?? '');
$currentCfProxied  = !empty($entry['cf_proxied']);
$currentSslEnabled = !empty($entry['npm_ssl_enabled']);
$currentCertId     = (int) ($entry['npm_certificate_id'] ?? 0);
$currentSslForced  = !empty($entry['npm_ssl_forced']);
$currentHttp2      = !empty($entry['npm_http2_support']);
$currentHsts       = !empty($entry['npm_hsts_enabled']);
$currentHstsSubs   = !empty($entry['npm_hsts_subdomains']);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Edit Virtual Host</h1>
        <p class="page-description">Update document root and linked integration options.</p>
    </div>
</div>

<div class="form-card">
    <form class="form" method="post" action="/?route=edit-vhost" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
        <input type="hidden" name="domain" value="<?= e($domain) ?>">

        <div class="form-group">
            <label class="form-label">Domain Name</label>
            <input class="form-input" type="text" value="<?= e($domain) ?>" readonly>
        </div>

        <div class="form-group">
            <label class="form-label" for="docroot">Document Root</label>
            <input class="form-input" id="docroot" type="text" name="docroot" value="<?= e($docroot) ?>" required>
        </div>

        <?php if ($cfEnabled && $currentCfRecordId !== ''): ?>
            <fieldset class="form-fieldset">
                <legend class="form-legend"><i class="fa-solid fa-cloud"></i> Cloudflare DNS Options</legend>

                <div class="form-group">
                    <label class="form-label" for="cf_record_ip">A Record IP</label>
                    <input class="form-input" id="cf_record_ip" type="text" name="cf_record_ip"
                           value="<?= e($currentCfIp) ?>" placeholder="e.g. 122.199.1.122">
                </div>

                <label class="form-check">
                    <input id="cf_proxied" type="checkbox" name="cf_proxied" value="1" <?= $currentCfProxied ? 'checked' : '' ?>>
                    Proxied (orange cloud)
                </label>
            </fieldset>
        <?php elseif ($currentCfRecordId !== ''): ?>
            <p class="form-hint">Cloudflare record is linked, but Cloudflare integration is currently disabled in environment.</p>
        <?php endif; ?>

        <?php if ($npmEnabled && !empty($entry['npm_proxy_id'])): ?>
            <fieldset class="form-fieldset">
                <legend class="form-legend"><i class="fa-solid fa-lock"></i> NPM SSL Options</legend>

                <div class="form-checks">
                    <label class="form-check">
                        <input id="npm_ssl_enabled" type="checkbox" name="npm_ssl_enabled" value="1" <?= $currentSslEnabled ? 'checked' : '' ?>>
                        Enable SSL in NPM
                    </label>
                    <label class="form-check">
                        <input id="npm_ssl_forced" type="checkbox" name="npm_ssl_forced" value="1" <?= $currentSslForced ? 'checked' : '' ?>>
                        Force SSL redirect
                    </label>
                    <label class="form-check">
                        <input id="npm_http2_support" type="checkbox" name="npm_http2_support" value="1" <?= $currentHttp2 ? 'checked' : '' ?>>
                        Enable HTTP/2
                    </label>
                    <label class="form-check">
                        <input id="npm_hsts_enabled" type="checkbox" name="npm_hsts_enabled" value="1" <?= $currentHsts ? 'checked' : '' ?>>
                        Enable HSTS
                    </label>
                    <label class="form-check">
                        <input id="npm_hsts_subdomains" type="checkbox" name="npm_hsts_subdomains" value="1" <?= $currentHstsSubs ? 'checked' : '' ?>>
                        HSTS include subdomains
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="npm_certificate_id">Certificate</label>
                    <select class="form-select" id="npm_certificate_id" name="npm_certificate_id">
                        <option value="0">Select a certificate</option>
                        <?php foreach ($npmCertificates as $cert): ?>
                            <?php
                            $certId = (int) ($cert['id'] ?? 0);
                            if ($certId <= 0) { continue; }
                            $certName = (string) ($cert['name'] ?? ('Certificate #' . $certId));
                            ?>
                            <option value="<?= e((string) $certId) ?>" <?= ($currentCertId === $certId) ? 'selected' : '' ?>>
                                <?= e($certName) ?> (ID: <?= e((string) $certId) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>
        <?php elseif (!empty($entry['npm_proxy_id'])): ?>
            <p class="form-hint">NPM proxy host is linked, but NPM integration is currently disabled in environment.</p>
        <?php endif; ?>

        <div class="btn-group" style="margin-top: 4px;">
            <button class="btn btn--primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Changes
            </button>
            <a href="/?route=dashboard" class="btn btn--ghost">Cancel</a>
        </div>

    </form>
</div>
