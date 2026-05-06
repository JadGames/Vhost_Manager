<?php declare(strict_types=1); ?>
<?php
$cfEnabled       = !empty($cfEnabled);
$npmEnabled      = !empty($npmEnabled);
$allowedDocrootBases = is_array($allowedDocrootBases ?? null) ? $allowedDocrootBases : [(string) ($defaultBase ?? '/var/www')];
$npmCertificates = is_array($npmCertificates ?? null) ? $npmCertificates : [];
$domains         = is_array($domains ?? null) ? $domains : [];
$dnsIntegrations = is_array($dnsIntegrations ?? null) ? $dnsIntegrations : [];
$hasDomains      = $domains !== [];
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
            <li><strong>Cloudflare DNS</strong> — optionally create an A record for this domain.</li>
        <?php endif; ?>
        <?php if ($npmEnabled): ?>
            <li><strong>Nginx Proxy Manager</strong> — optionally create a proxy host forwarding to <code><?= e((string) $npmForwardHost) ?>:<?= e((string) $npmForwardPort) ?></code>.</li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<div class="form-card">
    <form id="create-vhost-form" class="form" method="post" action="/?route=create-vhost" autocomplete="off"
          data-default-base="<?= e((string) $defaultBase) ?>">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
        <input type="hidden" name="domain" id="domain-hidden" value="">

        <div class="form-group">
            <label class="form-label" for="alias">Project Name <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input class="form-input" id="alias" type="text" name="alias" maxlength="122" placeholder="marketing or app.local">
            <span class="form-hint">Used as the folder inside the selected docroot. Leave blank to use the full domain.</span>
        </div>

        <?php if ($hasDomains): ?>

            <div class="form-row" id="domain-selector-row">
                <div class="form-group">
                    <label class="form-label" for="subdomain">Subdomain</label>
                    <input class="form-input" id="subdomain" type="text" name="subdomain" maxlength="120" placeholder="app">
                </div>
                <div class="form-group">
                    <label class="form-label" for="selected_domain">Domain</label>
                    <select class="form-select" id="selected_domain" name="selected_domain">
                        <?php foreach ($domains as $d): ?>
                            <option value="<?= e((string) $d['domain']) ?>"><?= e((string) $d['domain']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-group--preview">
                    <div class="fqdn-preview">
                        <span class="fqdn-preview-label">Resulting FQDN</span>
                        <code id="fqdn-preview" class="fqdn-preview-value">—</code>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="form-group" id="no-domains-row">
                <label class="form-label">Domain</label>
                <button type="button" class="btn btn--secondary" id="open-add-domain-btn">
                    <i class="fa-solid fa-plus"></i> Add Domain
                </button>
                <span class="form-hint" style="color:var(--color-error,#dc3545);margin-top:6px;display:block;">
                    No domains configured. Add a domain to continue.
                </span>
            </div>
            <div class="form-row" id="domain-selector-row" style="display:none;">
                <div class="form-group">
                    <label class="form-label" for="subdomain">Subdomain</label>
                    <input class="form-input" id="subdomain" type="text" name="subdomain" maxlength="120" placeholder="app">
                </div>
                <div class="form-group">
                    <label class="form-label" for="selected_domain">Domain</label>
                    <select class="form-select" id="selected_domain" name="selected_domain">
                    </select>
                </div>
                <div class="form-group form-group--preview">
                    <div class="fqdn-preview">
                        <span class="fqdn-preview-label">Resulting FQDN</span>
                        <code id="fqdn-preview" class="fqdn-preview-value">—</code>
                    </div>
                </div>
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
                <span class="form-hint">Choose the parent directory.</span>
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
                        <span class="form-hint">Could not load certificates from NPM.</span>
                    <?php endif; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <div class="btn-group" style="margin-top: 4px;">
            <button id="create-vhost-submit" class="btn btn--primary" type="submit" <?= !$hasDomains ? 'disabled' : '' ?>>
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
        <div class="dialog-row"><span class="dialog-row-label">FQDN</span><code id="confirm-fqdn" class="dialog-row-value">—</code></div>
        <div class="dialog-row"><span class="dialog-row-label">Project Name</span><code id="confirm-alias" class="dialog-row-value">—</code></div>
        <div class="dialog-row"><span class="dialog-row-label">Doc Root</span><code id="confirm-docroot" class="dialog-row-value">—</code></div>
        <?php if ($cfEnabled || $npmEnabled): ?>
            <div class="dialog-row"><span class="dialog-row-label">Integrations</span><code id="confirm-integrations" class="dialog-row-value">—</code></div>
        <?php endif; ?>
        <div class="dialog-row"><span class="dialog-row-label">NPM SSL</span><code id="confirm-npm-ssl" class="dialog-row-value">disabled</code></div>
    </div>
    <div class="btn-group">
        <button id="confirm-create" class="btn btn--primary" type="button">
            <i class="fa-solid fa-check"></i> Confirm and Create
        </button>
        <button id="confirm-cancel" class="btn btn--ghost" type="button">Back</button>
    </div>
</dialog>

<!-- ── Add Domain modal ── -->
<dialog id="add-domain-modal"
        data-csrf="<?= e((string) $csrfToken) ?>"
        data-integrations="<?= e(json_encode(array_values($dnsIntegrations), JSON_UNESCAPED_SLASHES)) ?>">

    <!-- Step 1 -->
    <div id="adm-step-1">
        <p class="dialog-title"><i class="fa-solid fa-globe"></i> Add Domain</p>

        <div class="form-group" style="margin-top:12px;">
            <label class="form-label" for="adm-domain">Domain (TLD)</label>
            <input class="form-input" id="adm-domain" type="text" placeholder="example.com" autocomplete="off" spellcheck="false">
            <span class="form-field-error" id="adm-domain-error" style="display:none;"></span>
        </div>

        <div class="form-group">
            <label class="form-label" for="adm-integration">DNS Integration</label>
            <select class="form-select" id="adm-integration" <?= $dnsIntegrations === [] ? 'disabled' : '' ?>>
                <?php if ($dnsIntegrations === []): ?>
                    <option value="">No DNS integration enabled</option>
                <?php else: ?>
                    <option value="">None (domain only)</option>
                    <?php foreach ($dnsIntegrations as $intg): ?>
                        <option value="<?= e((string) $intg['id']) ?>"
                                data-provider="<?= e((string) $intg['provider']) ?>"
                                data-name="<?= e((string) $intg['name']) ?>">
                            <?= e((string) $intg['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <?php if ($dnsIntegrations === []): ?>
                <span class="form-hint" style="color:var(--color-error,#dc3545);">Enable a DNS integration in Settings → Integrations first.</span>
            <?php endif; ?>
        </div>

        <div id="adm-step1-error" class="form-field-error" style="display:none;margin-bottom:8px;"></div>

        <div class="btn-group">
            <button type="button" class="btn btn--ghost" id="adm-cancel-1">Cancel</button>
            <button type="button" class="btn btn--primary" id="adm-next-btn">Add</button>
        </div>
    </div>

    <!-- Step 2 (Cloudflare) -->
    <div id="adm-step-2" style="display:none;">
        <p class="dialog-title"><i class="fa-solid fa-cloud"></i> <span id="adm-step2-title">Cloudflare Settings</span></p>

        <div class="form-row" style="margin-top:12px;">
            <div class="form-group">
                <label class="form-label" for="adm-zone-id">Zone ID</label>
                <input class="form-input" id="adm-zone-id" type="text" placeholder="32-char zone id" autocomplete="off" spellcheck="false">
            </div>
            <div class="form-group">
                <label class="form-label" for="adm-api-token">API Token</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="adm-api-token" type="password" autocomplete="off" spellcheck="false">
                    <button class="secret-toggle-btn" type="button" aria-label="Show secret" id="adm-token-toggle">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="adm-record-ip">Record IP</label>
                <input class="form-input" id="adm-record-ip" type="text" placeholder="1.2.3.4" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label" for="adm-ttl">TTL (seconds)</label>
                <input class="form-input" id="adm-ttl" type="number" value="120" min="1" max="86400">
            </div>
        </div>
        <label class="form-check" style="margin-bottom:12px;">
            <input type="checkbox" id="adm-proxied" checked>
            Proxied through Cloudflare
        </label>

        <div id="adm-step2-error" class="form-field-error" style="display:none;margin-bottom:8px;"></div>

        <div class="btn-group">
            <button type="button" class="btn btn--ghost" id="adm-cancel-2">Cancel</button>
            <button type="button" class="btn btn--primary" id="adm-submit-btn">Add Domain</button>
        </div>
    </div>

</dialog>

<script src="<?= e(asset_url('/assets/create-vhost.js')) ?>"></script>
