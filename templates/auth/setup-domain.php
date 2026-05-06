<?php declare(strict_types=1); ?>
<?php $fe = is_array($fieldErrors ?? null) ? $fieldErrors : []; ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: Add First Domain</h1>
        <p class="auth-subtitle">Step 4 of 5: Add your first domain (optional)</p>

        <form class="form" method="post" action="/?route=setup-domain" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="domain">Domain</label>
                <input class="form-input<?= !empty($fe['domain']) ? ' is-error' : '' ?>"
                       id="domain" type="text" name="domain"
                       value="<?= e((string) ($domain ?? '')) ?>"
                       placeholder="example.com"
                       autocomplete="off" spellcheck="false">
                <?php if (!empty($fe['domain'])): ?>
                    <span class="form-field-error"><?= e((string) $fe['domain']) ?></span>
                <?php else: ?>
                    <span class="form-hint">The base domain you will host vhosts on (e.g. example.com).</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($hasCfIntegration)): ?>
            <fieldset class="form-fieldset" style="margin-top:8px;">
                <legend class="form-legend"><i class="fa-solid fa-cloud"></i> Cloudflare Settings</legend>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cf_zone_id">Zone ID</label>
                        <input class="form-input<?= !empty($fe['cf_zone_id']) ? ' is-error' : '' ?>"
                               id="cf_zone_id" type="text" name="cf_zone_id"
                               value="<?= e((string) ($cfZoneId ?? '')) ?>"
                               placeholder="32-char hex zone id"
                               autocomplete="off" spellcheck="false">
                        <?php if (!empty($fe['cf_zone_id'])): ?>
                            <span class="form-field-error"><?= e((string) $fe['cf_zone_id']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cf_api_token">API Token</label>
                        <div class="secret-input-wrap">
                            <input class="form-input" id="cf_api_token" type="password" name="cf_api_token"
                                   autocomplete="off" spellcheck="false">
                            <button class="secret-toggle-btn" type="button" aria-label="Show secret" id="token-toggle">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cf_record_ip">Record IP</label>
                        <input class="form-input<?= !empty($fe['cf_record_ip']) ? ' is-error' : '' ?>"
                               id="cf_record_ip" type="text" name="cf_record_ip"
                               value="<?= e((string) ($cfRecordIp ?? '')) ?>"
                               placeholder="1.2.3.4">
                        <?php if (!empty($fe['cf_record_ip'])): ?>
                            <span class="form-field-error"><?= e((string) $fe['cf_record_ip']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cf_ttl">TTL (seconds)</label>
                        <input class="form-input" id="cf_ttl" type="number" name="cf_ttl"
                               value="<?= e((string) ($cfTtl ?? 120)) ?>" min="1" max="86400">
                    </div>
                </div>

                <label class="form-check">
                    <input type="checkbox" name="cf_proxied" value="1" <?= !empty($cfProxied) ? 'checked' : '' ?>>
                    Proxied through Cloudflare
                </label>

                <p class="form-hint" style="margin-top:8px;">
                    Zone ID and API Token are optional here — you can configure them per domain from Settings → Domains later.
                </p>
            </fieldset>
            <?php endif; ?>

            <div style="margin-top:20px; display:flex; gap:8px;">
                <a href="/?route=setup-dns" class="btn btn--secondary" style="flex:1; text-align:center; text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button class="btn btn--ghost" type="button" style="flex:1;" id="domain-skip-btn">
                    Skip
                </button>
                <button class="btn btn--primary" type="submit" style="flex:1;">
                    Add Domain &amp; Continue <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
(function () {
    var form   = document.querySelector('form[action="/?route=setup-domain"]');
    var skipBtn = document.getElementById('domain-skip-btn');
    var toggle  = document.getElementById('token-toggle');
    var tokenIn = document.getElementById('cf_api_token');

    if (skipBtn && form) {
        skipBtn.addEventListener('click', function () {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'skip';
            hidden.value = '1';
            form.appendChild(hidden);
            form.submit();
        });
    }

    if (toggle && tokenIn) {
        toggle.addEventListener('click', function () {
            var show = tokenIn.type === 'password';
            tokenIn.type = show ? 'text' : 'password';
            var icon = toggle.querySelector('i');
            if (icon) { icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'; }
        });
    }
})();
</script>
