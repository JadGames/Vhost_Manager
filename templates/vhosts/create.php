<?php declare(strict_types=1); ?>
<?php
$cfEnabled       = !empty($cfEnabled);
$npmEnabled      = !empty($npmEnabled);
$baseDomain      = strtolower(trim((string) ($baseDomain ?? '')));
$allowedDocrootBases = is_array($allowedDocrootBases ?? null) ? $allowedDocrootBases : [(string) ($defaultBase ?? '/var/www')];
$npmCertificates = is_array($npmCertificates ?? null) ? $npmCertificates : [];
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Create Virtual Host</h1>
        <p class="page-description">Confirm the final FQDN before provisioning resources.</p>
    </div>
</div>

<?php if ($cfEnabled || $npmEnabled): ?>
<div class="integration-banner">
    <strong><i class="fa-solid fa-bolt"></i> Available integrations for this vhost:</strong>
    <ul>
        <?php if ($cfEnabled): ?>
            <li><strong>Cloudflare DNS</strong> — optionally create an A record pointing to <code><?= e((string) $cfRecordIp) ?></code>.</li>
        <?php endif; ?>
        <?php if ($npmEnabled): ?>
            <li><strong>Nginx Proxy Manager</strong> — optionally create a proxy host forwarding to <code><?= e((string) $npmForwardHost) ?>:<?= e((string) $npmForwardPort) ?></code>.</li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<div class="form-card">
    <form id="create-vhost-form" class="form" method="post" action="/?route=create-vhost" autocomplete="off"
          data-base-domain="<?= e($baseDomain) ?>" data-default-base="<?= e((string) $defaultBase) ?>">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
        <input type="hidden" name="domain" id="domain-hidden" value="">

        <div class="form-group">
            <label class="form-label" for="alias">Project Name <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input class="form-input" id="alias" type="text" name="alias" maxlength="122" placeholder="marketing or app.local">
            <span class="form-hint">Used as the folder inside the selected docroot. Leave blank to use the full domain.</span>
        </div>

        <?php if ($baseDomain !== ''): ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="subdomain">Subdomain(s)</label>
                    <input class="form-input" id="subdomain" type="text" name="subdomain" required maxlength="120" placeholder="app or app.dev">
                    <span class="form-hint">Base domain: <strong><?= e($baseDomain) ?></strong></span>
                </div>
                <div class="form-group form-group--preview">
                    <div class="fqdn-preview">
                        <span class="fqdn-preview-label">Resulting FQDN</span>
                        <code id="fqdn-preview" class="fqdn-preview-value">—</code>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="form-group">
                <label class="form-label" for="domain">Domain (FQDN)</label>
                <input class="form-input" id="domain" type="text" name="domain" required maxlength="253" placeholder="example.com">
            </div>

        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="docroot_base">Document Root Base</label>
                <select class="form-select" id="docroot_base" name="docroot_base">
                    <?php foreach ($allowedDocrootBases as $base): ?>
                        <?php $base = (string) $base; ?>
                        <option value="<?= e($base) ?>" <?= $base === (string) $defaultBase ? 'selected' : '' ?>>
                            <?= e($base) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Choose the parent directory. The folder name below will be appended automatically.</span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Document Root Preview</label>
            <code id="docroot-preview" class="form-code-preview">—</code>
        </div>

        <?php if ($cfEnabled || $npmEnabled): ?>
            <fieldset class="form-fieldset">
                <legend class="form-legend"><i class="fa-solid fa-sliders"></i> Integrations</legend>
                <div class="form-checks">
                    <?php if ($cfEnabled): ?>
                        <label class="form-check">
                            <input id="create_cloudflare" type="checkbox" name="create_cloudflare" value="1" checked>
                            Create Cloudflare DNS record
                        </label>
                    <?php endif; ?>
                    <?php if ($npmEnabled): ?>
                        <label class="form-check">
                            <input id="create_npm" type="checkbox" name="create_npm" value="1" checked>
                            Create Nginx Proxy Manager host
                        </label>
                    <?php endif; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <?php if ($npmEnabled): ?>
            <fieldset class="form-fieldset" id="npm-options-fieldset">
                <legend class="form-legend"><i class="fa-solid fa-lock"></i> NPM SSL Options</legend>

                <div class="form-checks">
                    <label class="form-check">
                        <input id="npm_ssl_enabled" type="checkbox" name="npm_ssl_enabled" value="1" <?= !empty($npmSslEnabled) ? 'checked' : '' ?>>
                        Enable SSL in NPM
                    </label>
                    <label class="form-check">
                        <input id="npm_ssl_forced" type="checkbox" name="npm_ssl_forced" value="1" <?= !empty($npmSslForced) ? 'checked' : '' ?>>
                        Force SSL redirect
                    </label>
                    <label class="form-check">
                        <input id="npm_http2_support" type="checkbox" name="npm_http2_support" value="1" <?= !empty($npmHttp2) ? 'checked' : '' ?>>
                        Enable HTTP/2
                    </label>
                    <label class="form-check">
                        <input id="npm_hsts_enabled" type="checkbox" name="npm_hsts_enabled" value="1" <?= !empty($npmHsts) ? 'checked' : '' ?>>
                        Enable HSTS
                    </label>
                    <label class="form-check">
                        <input id="npm_hsts_subdomains" type="checkbox" name="npm_hsts_subdomains" value="1" <?= !empty($npmHstsSubs) ? 'checked' : '' ?>>
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
                            <option value="<?= e((string) $certId) ?>" <?= ((int) ($npmCertId ?? 0) === $certId) ? 'selected' : '' ?>>
                                <?= e($certName) ?> (ID: <?= e((string) $certId) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($npmCertificates)): ?>
                        <span class="form-hint">Could not load certificates from NPM. Set <strong>NPM_CERTIFICATE_ID</strong> in environment, then refresh.</span>
                    <?php endif; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <div class="btn-group" style="margin-top: 4px;">
            <button id="create-vhost-submit" class="btn btn--primary" type="submit">
                <i class="fa-solid fa-circle-plus"></i>
                Create Virtual Host
            </button>
            <a href="/?route=dashboard" class="btn btn--ghost">Cancel</a>
        </div>

    </form>
</div>

<!-- ── Confirm dialog ── -->
<dialog id="create-vhost-confirm">
    <p class="dialog-title">Confirm Virtual Host</p>
    <p class="dialog-subtitle">Review the values below before creating resources in Apache<?= $cfEnabled ? ', Cloudflare' : '' ?><?= $npmEnabled ? ', and NPM' : '' ?>.</p>

    <div class="dialog-rows">
        <div class="dialog-row">
            <span class="dialog-row-label">FQDN</span>
            <code id="confirm-fqdn" class="dialog-row-value">—</code>
        </div>
            <div class="dialog-row">
                <span class="dialog-row-label">Project Name</span>
                <code id="confirm-alias" class="dialog-row-value">—</code>
            </div>
        <div class="dialog-row">
            <span class="dialog-row-label">Doc Root</span>
            <code id="confirm-docroot" class="dialog-row-value">—</code>
        </div>
            <?php if ($cfEnabled || $npmEnabled): ?>
                <div class="dialog-row">
                    <span class="dialog-row-label">Integrations</span>
                    <code id="confirm-integrations" class="dialog-row-value">—</code>
                </div>
            <?php endif; ?>
        <div class="dialog-row">
            <span class="dialog-row-label">NPM SSL</span>
            <code id="confirm-npm-ssl" class="dialog-row-value">disabled</code>
        </div>
    </div>

    <div class="btn-group">
        <button id="confirm-create" class="btn btn--primary" type="button">
            <i class="fa-solid fa-check"></i>
            Confirm and Create
        </button>
        <button id="confirm-cancel" class="btn btn--ghost" type="button">Back</button>
    </div>
</dialog>

<script src="<?= e(asset_url('/assets/create-vhost.js')) ?>"></script>
