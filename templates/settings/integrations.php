<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Integrations</h1>
        <p class="page-description">Configure reverse proxy and DNS provider integrations.</p>
    </div>
</div>

<?php
    $allProviders     = $providers ?? [];
    $proxyProviders   = array_filter($allProviders, static fn ($p) => $p['category'] === 'proxy');
    $dnsProviders     = array_filter($allProviders, static fn ($p) => $p['category'] === 'dns');
    $proxyList        = $proxyIntegrations ?? [];
    $dnsList          = $dnsIntegrations ?? [];
?>

<!-- ── Reverse Proxy Section ── -->
<div class="integrations-section">
    <div class="integrations-section__header">
        <div class="integrations-section__title-wrap">
            <div class="integrations-section__icon"><i class="fa-solid fa-network-wired"></i></div>
            <div>
                <div class="integrations-section__title">Reverse Proxy</div>
                <div class="integrations-section__description">Manage proxy hosts and SSL certificates via a proxy provider.</div>
            </div>
        </div>
    </div>

    <div class="integration-tiles-grid">
        <?php foreach ($proxyList as $int): ?>
            <?php
                $pInfo = $allProviders[$int['provider'] ?? ''] ?? null;
                $icon  = $pInfo ? $pInfo['icon'] : 'fa-network-wired';
            ?>
            <div class="integration-tile"
                 data-open-edit="<?= e((string) ($int['id'] ?? '')) ?>"
                 title="Edit <?= e((string) ($int['name'] ?? '')) ?>">
                <div class="integration-tile__header">
                    <div class="integration-tile__icon">
                        <i class="fa-solid <?= e($icon) ?>"></i>
                    </div>
                    <span class="integration-tile__badge"><?= e(ucfirst((string) ($int['provider'] ?? ''))) ?></span>
                </div>
                <div>
                    <div class="integration-tile__name"><?= e((string) ($int['name'] ?? '')) ?></div>
                    <div class="integration-tile__provider"><?= e((string) ($pInfo['label'] ?? ($int['provider'] ?? ''))) ?></div>
                </div>
                <div class="integration-tile__actions">
                    <?php if (($int['provider'] ?? '') !== 'cloudflare'): ?>
                        <button class="btn btn--ghost btn--sm"
                                type="button"
                                data-test-integration="<?= e((string) ($int['id'] ?? '')) ?>">
                            <i class="fa-solid fa-bolt"></i> Test
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add Proxy Integration tile -->
        <button class="integration-tile integration-tile--add"
                type="button"
                data-open-add-modal="proxy"
                aria-label="Add reverse proxy integration">
            <div class="integration-tile__add-icon"><i class="fa-solid fa-plus"></i></div>
            <div class="integration-tile__add-label">Add Integration</div>
            <div class="integration-tile__add-hint">Reverse Proxy provider</div>
        </button>
    </div>
</div>

<!-- ── DNS Section ── -->
<div class="integrations-section">
    <div class="integrations-section__header">
        <div class="integrations-section__title-wrap">
            <div class="integrations-section__icon"><i class="fa-solid fa-cloud"></i></div>
            <div>
                <div class="integrations-section__title">DNS</div>
                <div class="integrations-section__description">Automatically create and manage DNS records via your DNS provider.</div>
            </div>
        </div>
    </div>

    <div class="integration-tiles-grid">
        <?php foreach ($dnsList as $int): ?>
            <?php
                $pInfo = $allProviders[$int['provider'] ?? ''] ?? null;
                $icon  = $pInfo ? $pInfo['icon'] : 'fa-cloud';
            ?>
            <div class="integration-tile"
                 data-open-edit="<?= e((string) ($int['id'] ?? '')) ?>"
                 title="Edit <?= e((string) ($int['name'] ?? '')) ?>">
                <div class="integration-tile__header">
                    <div class="integration-tile__icon">
                        <i class="fa-solid <?= e($icon) ?>"></i>
                    </div>
                    <span class="integration-tile__badge"><?= e(ucfirst((string) ($int['provider'] ?? ''))) ?></span>
                </div>
                <div>
                    <div class="integration-tile__name"><?= e((string) ($int['name'] ?? '')) ?></div>
                    <div class="integration-tile__provider"><?= e((string) ($pInfo['label'] ?? ($int['provider'] ?? ''))) ?></div>
                </div>
                <div class="integration-tile__actions">
                    <button class="btn btn--ghost btn--sm"
                            type="button"
                            data-test-integration="<?= e((string) ($int['id'] ?? '')) ?>">
                        <i class="fa-solid fa-bolt"></i> Test
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add DNS Integration tile -->
        <button class="integration-tile integration-tile--add"
                type="button"
                data-open-add-modal="dns"
                aria-label="Add DNS integration">
            <div class="integration-tile__add-icon"><i class="fa-solid fa-plus"></i></div>
            <div class="integration-tile__add-label">Add Integration</div>
            <div class="integration-tile__add-hint">DNS provider</div>
        </button>
    </div>
</div>

<!-- ══ ADD INTEGRATION MODAL ══ -->
<dialog id="add-integration-modal" aria-modal="true" aria-labelledby="add-modal-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="add-modal-title">Add Integration</p>
            <p class="dialog-subtitle" id="add-modal-subtitle">Choose a provider to configure.</p>
        </div>
        <button class="dialog-close-btn" type="button" id="add-modal-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <form class="form" method="post" action="/?route=settings-integrations-action" autocomplete="off" id="add-integration-form">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="add">
            <input type="hidden" name="settings[bootstrap_key]" id="add_npm_bootstrap_key" value="">

            <div class="form-group">
                <label class="form-label" for="add_name">Custom Name</label>
                <input class="form-input" id="add_name" type="text" name="name" placeholder="e.g. Main NPM, Production CF" required>
                <span class="form-field-error" id="err_add_name" hidden></span>
                <span class="form-hint">A label to identify this integration — you can add multiple of the same provider.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="add_provider">Provider</label>
                <select class="form-select" id="add_provider" name="provider" required>
                    <option value="">— Select a provider —</option>
                    <?php foreach ($allProviders as $key => $p): ?>
                        <option value="<?= e($key) ?>" data-category="<?= e($p['category']) ?>">
                            <?= e($p['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="provider-fields" data-provider="npm" id="add-npm-fields">
                <p class="form-hint" style="margin-bottom: 12px;">Enter one-time admin credentials to provision a dedicated non-admin VHM runtime account. Admin credentials are never stored.</p>

                <div id="add-npm-step-1">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="add_npm_base_url_input">Base URL <span style="color:var(--danger)"> *</span></label>
                        <input type="hidden" name="settings[base_url]" id="add_npm_base_url">
                        <div class="app-url-input-row">
                            <select class="form-select" id="add_npm_base_url_scheme" aria-label="NPM URL protocol">
                                <option value="http" selected>http://</option>
                                <option value="https">https://</option>
                            </select>
                            <input class="form-input" id="add_npm_base_url_input" type="text" placeholder="npm.example.com:81 or 192.168.1.100:81" value="npm:81">
                        </div>
                        <span class="form-field-error" id="err_npm_base_url" hidden></span>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="add_npm_admin_identity">Admin Email <span style="color:var(--danger)"> *</span></label>
                        <input class="form-input" id="add_npm_admin_identity" type="email" placeholder="admin@example.com" autocomplete="off">
                        <span class="form-field-error" id="err_npm_admin_identity" hidden></span>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="add_npm_admin_secret">Admin Password or API Token <span style="color:var(--danger)"> *</span></label>
                        <div class="secret-input-wrap">
                            <input class="form-input" id="add_npm_admin_secret" type="password" placeholder="Admin password or Bearer token" autocomplete="off" spellcheck="false">
                            <button class="secret-toggle-btn" type="button" data-secret-target="add_npm_admin_secret" aria-label="Show secret">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <span class="form-field-error" id="err_npm_admin_secret" hidden></span>
                    </div>
                    <p class="form-field-error" id="err_npm_step1" hidden style="margin-top:6px;"></p>
                </div>

                <div id="add-npm-step-2" hidden>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="add_npm_runtime_identity">Runtime Account</label>
                        <input class="form-input" id="add_npm_runtime_identity" type="text" readonly>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label class="form-label" for="add_npm_forward_host">Forward Host <span style="color:var(--danger)"> *</span></label>
                            <div class="app-url-input-row">
                                <input class="form-input" id="add_npm_forward_host" type="text" name="settings[forward_host]" placeholder="127.0.0.1" value="127.0.0.1">
                                <button class="btn btn--ghost btn--sm" type="button" id="add_npm_get_ip">Get IP</button>
                            </div>
                            <span class="form-field-error" id="err_npm_forward_host" hidden></span>
                        </div>
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label class="form-label" for="add_npm_forward_port">Forward Port <span style="color:var(--danger)"> *</span></label>
                            <input class="form-input" id="add_npm_forward_port" type="number" name="settings[forward_port]" min="1" max="65535" value="80">
                            <span class="form-field-error" id="err_npm_forward_port" hidden></span>
                        </div>
                    </div>
                    <p class="form-field-error" id="err_npm_step2" hidden style="margin-top:6px;"></p>
                </div>
            </div>

            <div class="provider-fields" data-provider="cloudflare" id="add-cloudflare-fields">
                <p class="form-hint" style="margin-bottom: 10px;">Cloudflare is domain-based. Enable it here, then configure API token, zone, and DNS settings per domain under Domains.</p>
                <p class="form-field-error" id="err_cf_enable" hidden style="margin-top:6px;"></p>
            </div>

            <div class="dialog-footer">
                <button class="btn btn--ghost" type="button" id="add-modal-cancel">Cancel</button>
                <button class="btn btn--primary" type="button" id="add-modal-next" hidden disabled>
                    <i class="fa-solid fa-arrow-right"></i> Next
                </button>
                <button class="btn btn--primary" type="submit" id="add-modal-submit" hidden disabled>
                    <i class="fa-solid fa-power-off"></i> Enable
                </button>
                <button class="btn btn--primary" type="button" id="add-modal-enable" hidden disabled>
                    <i class="fa-solid fa-power-off"></i> Enable
                </button>
                <button class="btn btn--primary" type="button" id="add-modal-enable-domains" hidden disabled>
                    <i class="fa-solid fa-power-off"></i> Enable &amp; Go to Domains
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- ══ EDIT INTEGRATION MODAL ══ -->
<dialog id="edit-integration-modal" aria-modal="true" aria-labelledby="edit-modal-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="edit-modal-title">Edit Integration</p>
            <p class="dialog-subtitle" id="edit-modal-subtitle"></p>
        </div>
        <button class="dialog-close-btn" type="button" id="edit-modal-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <form class="form" method="post" action="/?route=settings-integrations-action" autocomplete="off" id="edit-integration-form">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label" for="edit_name">Custom Name</label>
                <input class="form-input" id="edit_name" type="text" name="name" required>
            </div>

            <div id="edit-provider-fields">
                <!-- Populated by JS -->
            </div>

            <div class="dialog-footer">
                <button class="btn btn--danger btn--sm" type="button" id="edit-modal-delete">
                    <i class="fa-solid fa-trash"></i> Remove
                </button>
                <div style="flex:1"></div>
                <button class="btn btn--ghost" type="button" id="edit-modal-cancel">Cancel</button>
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>

        <!-- Delete sub-form -->
        <form method="post" action="/?route=settings-integrations-action" id="edit-delete-form" style="display:none">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="delete">
            <input type="hidden" name="id" id="edit_delete_id">
            <input type="hidden" name="remove_npm_runtime" id="edit_delete_remove_npm_runtime" value="0">
        </form>
    </div>
</dialog>

<!-- ══ DELETE INTEGRATION CONFIRM MODAL ══ -->
<dialog id="integration-delete-confirm-modal" aria-modal="true" aria-labelledby="integration-delete-confirm-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="integration-delete-confirm-title">Remove Integration?</p>
            <p class="dialog-subtitle" id="integration-delete-confirm-subtitle">This action cannot be undone.</p>
        </div>
        <button class="dialog-close-btn" type="button" id="integration-delete-confirm-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <p id="integration-delete-confirm-message" style="margin:0 0 1rem 0; color:var(--text-2);">
            Remove this integration from Vhost Manager?
        </p>

        <div id="integration-delete-npm-extra" hidden>
            <label class="danger-switch" for="integration-delete-remove-npm-toggle">
                <input type="checkbox" id="integration-delete-remove-npm-toggle">
                <span class="danger-switch-track" aria-hidden="true"></span>
                <span class="danger-switch-label">Also remove the runtime account and related records in NPM</span>
            </label>
            <p class="form-hint" style="margin-top:6px;">Requires sufficient NPM permissions for this account.</p>
        </div>

        <div class="dialog-footer">
            <button class="btn btn--ghost" type="button" id="integration-delete-confirm-cancel">Cancel</button>
            <button class="btn btn--danger" type="button" id="integration-delete-confirm-submit">
                <i class="fa-solid fa-trash"></i> Remove Integration
            </button>
        </div>
    </div>
</dialog>

<!-- ══ TEST RESULT MODAL ══ -->
<dialog id="test-result-modal" aria-modal="true" aria-labelledby="test-result-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="test-result-title">Connection Test</p>
            <p class="dialog-subtitle" id="test-result-subtitle">Integration test result</p>
        </div>
        <button class="dialog-close-btn" type="button" id="test-result-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <p id="test-result-message" style="margin:0 0 1rem 0;"></p>
        <div class="dialog-footer">
            <div style="flex:1"></div>
            <button class="btn btn--primary" type="button" id="test-result-ok">OK</button>
        </div>
    </div>
</dialog>

<!-- ══ CLOUDFLARE DOMAINS MODAL ══ -->
<dialog id="cloudflare-domains-modal" aria-modal="true" aria-labelledby="cloudflare-domains-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="cloudflare-domains-title">Cloudflare Coverage</p>
            <p class="dialog-subtitle" id="cloudflare-domains-subtitle">Domains with Cloudflare enabled</p>
        </div>
        <button class="dialog-close-btn" type="button" id="cloudflare-domains-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <div id="cloudflare-domains-list-wrap" class="cf-domains-list-wrap"></div>
        <div class="dialog-footer">
            <div style="flex:1"></div>
            <button class="btn btn--ghost" type="button" id="cloudflare-domains-test-all">Test All</button>
            <button class="btn btn--primary" type="button" id="cloudflare-domains-ok">Close</button>
        </div>
    </div>
</dialog>

<!-- ══ CLOUDFLARE DOMAIN TEST RESULT MODAL ══ -->
<dialog id="cloudflare-domain-result-modal" aria-modal="true" aria-labelledby="cloudflare-domain-result-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="cloudflare-domain-result-title">Domain Test Result</p>
            <p class="dialog-subtitle" id="cloudflare-domain-result-subtitle">Single domain check</p>
        </div>
        <button class="dialog-close-btn" type="button" id="cloudflare-domain-result-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <p id="cloudflare-domain-result-message" style="margin:0 0 1rem 0;"></p>
        <div class="dialog-footer">
            <div style="flex:1"></div>
            <button class="btn btn--primary" type="button" id="cloudflare-domain-result-ok">Close</button>
        </div>
    </div>
</dialog>

<!-- ══ CLOUDFLARE TEST-ALL RESULTS MODAL ══ -->
<dialog id="cloudflare-test-all-modal" aria-modal="true" aria-labelledby="cloudflare-test-all-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="cloudflare-test-all-title">Cloudflare Test All Results</p>
            <p class="dialog-subtitle" id="cloudflare-test-all-subtitle">Domain validation summary</p>
        </div>
        <button class="dialog-close-btn" type="button" id="cloudflare-test-all-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <div id="cloudflare-test-all-results" class="cf-test-all-results"></div>
        <div class="dialog-footer">
            <div style="flex:1"></div>
            <button class="btn btn--primary" type="button" id="cloudflare-test-all-ok">Close</button>
        </div>
    </div>
</dialog>

<!-- All integration data for JS -->
<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
var VHM_INTEGRATIONS = <?= json_encode(array_map(static function (array $i): array {
    // Keep non-secret settings so edit modal can preload current values.
    $safe = $i;
    $safeSettings = [];
    foreach (($i['settings'] ?? []) as $key => $value) {
        $name = strtolower((string) $key);
        $isSensitive = str_contains($name, 'secret')
            || str_contains($name, 'password')
            || str_contains($name, 'token')
            || $name === 'bootstrap_key';
        $safeSettings[(string) $key] = $isSensitive ? '' : (string) $value;
    }
    $safe['settings'] = $safeSettings;
    return $safe;
}, $integrations ?? []), JSON_UNESCAPED_SLASHES) ?: '[]' ?>;

var VHM_PROVIDERS = <?= json_encode(array_map(static function (array $p): array {
    // Strip field defaults — only need field metadata for building forms
    $fields = array_values(array_filter($p['fields'], static function (array $f): bool {
        $name = (string) ($f['name'] ?? '');
        return !in_array($name, ['identity', 'secret', 'bootstrap_key'], true);
    }));

    return ['label' => $p['label'], 'category' => $p['category'], 'icon' => $p['icon'],
            'fields' => array_map(static fn ($f) => ['name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'], 'placeholder' => $f['placeholder']], $fields)];
}, $allProviders), JSON_UNESCAPED_SLASHES) ?: '{}' ?>;

var VHM_CF_ENABLED_DOMAINS = <?= json_encode(array_values($cloudflareEnabledDomains ?? []), JSON_UNESCAPED_SLASHES) ?: '[]' ?>;

(function () {
    var addModal   = document.getElementById('add-integration-modal');
    var editModal  = document.getElementById('edit-integration-modal');
    var deleteConfirmModal = document.getElementById('integration-delete-confirm-modal');
    var deleteConfirmMessage = document.getElementById('integration-delete-confirm-message');
    var deleteConfirmSubtitle = document.getElementById('integration-delete-confirm-subtitle');
    var deleteNpmExtra = document.getElementById('integration-delete-npm-extra');
    var deleteNpmToggle = document.getElementById('integration-delete-remove-npm-toggle');
    var deleteNpmHidden = document.getElementById('edit_delete_remove_npm_runtime');
    var testResultModal = document.getElementById('test-result-modal');
    var testResultMessage = document.getElementById('test-result-message');
    var testResultSubtitle = document.getElementById('test-result-subtitle');
    var cloudflareDomainsModal = document.getElementById('cloudflare-domains-modal');
    var cloudflareDomainsListWrap = document.getElementById('cloudflare-domains-list-wrap');
    var cloudflareDomainsTestAll = document.getElementById('cloudflare-domains-test-all');
    var cloudflareDomainResultModal = document.getElementById('cloudflare-domain-result-modal');
    var cloudflareDomainResultMessage = document.getElementById('cloudflare-domain-result-message');
    var cloudflareDomainResultSubtitle = document.getElementById('cloudflare-domain-result-subtitle');
    var cloudflareTestAllModal = document.getElementById('cloudflare-test-all-modal');
    var cloudflareTestAllResults = document.getElementById('cloudflare-test-all-results');
    var addForm    = document.getElementById('add-integration-form');
    var addProvider = document.getElementById('add_provider');
    var addSubmit  = document.getElementById('add-modal-submit');
    var addSubtitle = document.getElementById('add-modal-subtitle');
    var editModalDeleteBtn = document.getElementById('edit-modal-delete');
    var editDeleteForm = document.getElementById('edit-delete-form');

    var deleteConfirmClose = document.getElementById('integration-delete-confirm-close');
    var deleteConfirmCancel = document.getElementById('integration-delete-confirm-cancel');
    var deleteConfirmSubmit = document.getElementById('integration-delete-confirm-submit');

    var currentEditIntegration = null;

    if (!addModal || !editModal || !addForm || !addProvider || !addSubmit || !addSubtitle) {
        return;
    }

    function openDialog(dialogEl) {
        if (!dialogEl) {
            return;
        }

        if (typeof dialogEl.showModal === 'function') {
            dialogEl.showModal();
            return;
        }

        dialogEl.setAttribute('open', 'open');
    }

    function closeDialog(dialogEl) {
        if (!dialogEl) {
            return;
        }

        if (typeof dialogEl.close === 'function') {
            dialogEl.close();
            return;
        }

        dialogEl.removeAttribute('open');
    }

    function showTestResult(ok, message) {
        if (!testResultModal || !testResultMessage || !testResultSubtitle) {
            return;
        }

        testResultSubtitle.textContent = ok ? 'Success' : 'Failed';
        testResultMessage.textContent = message;
        testResultMessage.style.color = ok ? 'var(--accent)' : 'var(--danger)';
        openDialog(testResultModal);
    }

    function showCloudflareDomainsModal() {
        if (!cloudflareDomainsModal || !cloudflareDomainsListWrap) {
            return;
        }

        var domains = Array.isArray(VHM_CF_ENABLED_DOMAINS) ? VHM_CF_ENABLED_DOMAINS.slice() : [];
        domains = domains.filter(function (d) { return String(d || '').trim() !== ''; });

        if (domains.length === 0) {
            cloudflareDomainsListWrap.innerHTML =
                '<p style="margin:0 0 1rem 0; text-align:center; color:var(--text-3);">No domains currently have Cloudflare enabled.</p>' +
                '<div style="display:flex; justify-content:center;">' +
                '<a class="btn btn--primary" href="/?route=domains">Add Domain</a>' +
                '</div>';
            if (cloudflareDomainsTestAll) {
                cloudflareDomainsTestAll.style.display = 'none';
            }
        } else {
            // Build tiles with loading state, then auto-run all tests.
            if (cloudflareDomainsTestAll) {
                cloudflareDomainsTestAll.style.display = 'none';
            }
            var escapedDomains = domains.map(function (domain) {
                var item = document.createElement('span');
                item.textContent = String(domain);
                var safe = item.innerHTML;
                return '<div class="cf-domain-tile cf-domain-tile--testing" data-cf-domain="' + safe + '">' +
                    '<div class="cf-domain-tile__left">' +
                    '<span class="cf-domain-tile__status-dot is-loading"></span>' +
                    '<span class="cf-domain-tile__name">' + safe + '</span>' +
                    '</div>' +
                    '<span class="cf-domain-tile__result" data-cf-result="' + safe + '">' +
                    '<i class="fa-solid fa-spinner fa-spin"></i> Testing…' +
                    '</span>' +
                    '</div>';
            }).join('');
            cloudflareDomainsListWrap.innerHTML =
                '<p style="margin:0 0 .75rem 0; color:var(--text-3);">Testing Cloudflare credentials for each domain…</p>' +
                '<div class="cf-domains-grid">' + escapedDomains + '</div>';
        }

        openDialog(cloudflareDomainsModal);

        // Auto-run tests for all domains
        if (domains.length > 0) {
            domains.forEach(function (domain) {
                testCloudflareDomain(domain).then(function (result) {
                    var resultEl = cloudflareDomainsListWrap.querySelector('[data-cf-result="' + CSS.escape(domain) + '"]');
                    var tile     = cloudflareDomainsListWrap.querySelector('[data-cf-domain="' + CSS.escape(domain) + '"]');
                    var dot      = tile ? tile.querySelector('.cf-domain-tile__status-dot') : null;
                    if (resultEl) {
                        resultEl.innerHTML = result.ok
                            ? '<i class="fa-solid fa-circle-check" style="color:var(--accent)"></i> <span style="color:var(--accent)">Passed</span>'
                            : '<i class="fa-solid fa-circle-xmark" style="color:var(--danger)"></i> <span style="color:var(--danger);font-size:11px;">' + escapeHtml(result.message) + '</span>';
                    }
                    if (dot) {
                        dot.className = 'cf-domain-tile__status-dot ' + (result.ok ? 'is-ok' : 'is-fail');
                    }
                    if (tile) {
                        tile.classList.remove('cf-domain-tile--testing');
                        tile.classList.add(result.ok ? 'cf-domain-tile--ok' : 'cf-domain-tile--fail');
                    }
                });
            });
        }
    }

    function escapeHtml(str) {
        var node = document.createElement('span');
        node.textContent = String(str || '');
        return node.innerHTML;
    }

    function csrfTokenValue() {
        var tokenInput = document.querySelector('#add-integration-form input[name=csrf_token]');
        return tokenInput ? tokenInput.value : '';
    }

    function requestServerIp() {
        var body = new URLSearchParams();
        body.set('csrf_token', csrfTokenValue());

        return fetch('/?route=settings-integrations-server-ip', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (r) {
                return r.json().catch(function () {
                    return { ok: false, message: 'Invalid response from server.' };
                });
            })
            .then(function (data) {
                if (!data || !data.ok || !data.ip) {
                    throw new Error((data && data.message) ? data.message : 'Unable to detect server IP.');
                }

                return String(data.ip);
            });
    }

    function testCloudflareDomain(domain) {
        var body = new URLSearchParams();
        body.set('csrf_token', csrfTokenValue());
        body.set('domain', String(domain || ''));

        return fetch('/?route=settings-integrations-cloudflare-domain-test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (r) {
                return r.json().catch(function () { return { ok: false, message: 'Invalid response from server.' }; });
            })
            .then(function (data) {
                return {
                    ok: !!(data && data.ok),
                    message: (data && data.message) ? String(data.message) : 'Cloudflare test failed.',
                };
            })
            .catch(function () {
                return {
                    ok: false,
                    message: 'Cloudflare test failed.',
                };
            });
    }

    var reopenCloudflareListOnSingleClose = false;

    function showCloudflareSingleResult(domain, result) {
        if (!cloudflareDomainResultModal || !cloudflareDomainResultMessage || !cloudflareDomainResultSubtitle) {
            return;
        }

        closeDialog(cloudflareDomainsModal);
        cloudflareDomainResultSubtitle.textContent = String(domain || 'Domain');
        cloudflareDomainResultMessage.textContent = result && result.message ? result.message : (result && result.ok ? 'Success.' : 'Failed.');
        cloudflareDomainResultMessage.style.color = result && result.ok ? 'var(--accent)' : 'var(--danger)';
        reopenCloudflareListOnSingleClose = true;
        openDialog(cloudflareDomainResultModal);
    }

    function showCloudflareTestAllResults(results) {
        if (!cloudflareTestAllModal || !cloudflareTestAllResults) {
            return;
        }

        closeDialog(cloudflareDomainsModal);

        var rows = (results || []).map(function (row) {
            var domainNode = document.createElement('span');
            domainNode.textContent = String(row.domain || '');
            var messageNode = document.createElement('span');
            messageNode.textContent = String(row.message || '');
            return '<div class="cf-test-all-row">' +
                '<div class="cf-test-all-row__head">' +
                '<span class="cf-test-all-row__domain">' + domainNode.innerHTML + '</span>' +
                '<span class="cf-test-all-row__status ' + (row.ok ? 'is-ok' : 'is-fail') + '">' + (row.ok ? 'Success' : 'Failed') + '</span>' +
                '</div>' +
                '<div class="cf-test-all-row__message">' + messageNode.innerHTML + '</div>' +
                '</div>';
        }).join('');

        cloudflareTestAllResults.innerHTML = rows || '<p>No domain results.</p>';
        reopenCloudflareListOnSingleClose = false;
        openDialog(cloudflareTestAllModal);
    }

    function showFieldError(inputEl, msg) {
        if (!inputEl) { return; }
        inputEl.classList.add('is-error');
        var fg = inputEl.closest('.form-group');
        if (!fg) { return; }
        var err = fg.querySelector('.form-field-error');
        if (!err) {
            err = document.createElement('span');
            err.className = 'form-field-error';
            fg.appendChild(err);
        }
        err.textContent = msg;
        err.removeAttribute('hidden');
    }

    function clearFieldError(inputEl) {
        if (!inputEl) { return; }
        inputEl.classList.remove('is-error');
        var fg = inputEl.closest('.form-group');
        if (!fg) { return; }
        var err = fg.querySelector('.form-field-error');
        if (err) { err.setAttribute('hidden', ''); }
    }

    function showStepError(elId, msg) {
        var el = document.getElementById(elId);
        if (!el) { return; }
        el.textContent = msg;
        el.removeAttribute('hidden');
    }

    function clearAllFieldErrors(containerEl) {
        if (!containerEl) { return; }
        containerEl.querySelectorAll('.is-error').forEach(function (el) { el.classList.remove('is-error'); });
        containerEl.querySelectorAll('.form-field-error').forEach(function (el) {
            el.setAttribute('hidden', '');
            el.textContent = '';
        });
    }

    var addNext = document.getElementById('add-modal-next');
    var addEnable = document.getElementById('add-modal-enable');
    var addEnableDomains = document.getElementById('add-modal-enable-domains');
    var npmStep1 = document.getElementById('add-npm-step-1');
    var npmStep2 = document.getElementById('add-npm-step-2');
    var npmBootstrapKey = document.getElementById('add_npm_bootstrap_key');
    var npmRuntimeIdentity = document.getElementById('add_npm_runtime_identity');
    var addName = document.getElementById('add_name');
    var addNpmGetIp = document.getElementById('add_npm_get_ip');
    var addFlowCategory = null;

    function filterAddProviders(category) {
        Array.from(addProvider.options).forEach(function (opt) {
            if (!opt.value) { opt.hidden = false; return; }
            opt.hidden = category ? opt.dataset.category !== category : false;
        });
        addProvider.value = '';
        showProviderFields(null);
    }

    function resetAddFlowState() {
        clearAllFieldErrors(addForm);

        if (npmStep1) {
            npmStep1.hidden = false;
        }
        if (npmStep2) {
            npmStep2.hidden = true;
        }
        if (npmBootstrapKey) {
            npmBootstrapKey.value = '';
        }
        if (npmRuntimeIdentity) {
            npmRuntimeIdentity.value = '';
        }

        addSubmit.hidden = true;
        addSubmit.disabled = true;
        addSubmit.innerHTML = '<i class="fa-solid fa-power-off"></i> Enable';

        if (addNext) {
            addNext.hidden = true;
            addNext.disabled = true;
            addNext.innerHTML = '<i class="fa-solid fa-arrow-right"></i> Next';
        }

        if (addEnable) {
            addEnable.hidden = true;
            addEnable.disabled = true;
            addEnable.innerHTML = '<i class="fa-solid fa-power-off"></i> Enable';
        }

        if (addEnableDomains) {
            addEnableDomains.hidden = true;
            addEnableDomains.disabled = true;
            addEnableDomains.innerHTML = '<i class="fa-solid fa-power-off"></i> Enable &amp; Go to Domains';
        }
    }

    function isNonEmpty(value) {
        return String(value || '').trim() !== '';
    }

    function buildAddNpmBaseUrl() {
        var baseUrlSchemeInput = document.getElementById('add_npm_base_url_scheme');
        var baseUrlInput = document.getElementById('add_npm_base_url_input');
        var baseUrlHidden = document.getElementById('add_npm_base_url');
        var scheme = baseUrlSchemeInput ? String(baseUrlSchemeInput.value || 'http').trim() : 'http';
        var value = baseUrlInput ? String(baseUrlInput.value || '').trim() : '';

        value = value.replace(/^https?:\/\//i, '').replace(/^\/+/, '');

        var fullUrl = value ? (scheme + '://' + value) : '';
        if (baseUrlHidden) {
            baseUrlHidden.value = fullUrl;
        }

        return fullUrl;
    }

    function updateAddActionState() {
        var provider = addProvider.value;
        var hasName = addName ? isNonEmpty(addName.value) : false;
        var readyForProvider = isNonEmpty(provider);
        var commonReady = hasName && readyForProvider;

        if (!provider) {
            if (addNext) {
                addNext.disabled = true;
            }
            if (addSubmit) {
                addSubmit.disabled = true;
            }
            if (addEnable) {
                addEnable.disabled = true;
            }
            if (addEnableDomains) {
                addEnableDomains.disabled = true;
            }
            return;
        }

        if (provider === 'npm') {
            var inStep1 = npmStep1 && !npmStep1.hidden;
            if (inStep1) {
                var baseUrlInput = document.getElementById('add_npm_base_url_input');
                var adminIdentityInput = document.getElementById('add_npm_admin_identity');
                var adminSecretInput = document.getElementById('add_npm_admin_secret');
                var step1Ready = commonReady
                    && isNonEmpty(baseUrlInput ? baseUrlInput.value : '')
                    && isNonEmpty(adminIdentityInput ? adminIdentityInput.value : '')
                    && isNonEmpty(adminSecretInput ? adminSecretInput.value : '');
                if (addNext) {
                    addNext.disabled = !step1Ready;
                }
                if (addSubmit) {
                    addSubmit.disabled = true;
                }
                return;
            }

            var forwardHostInput = document.getElementById('add_npm_forward_host');
            var forwardPortInput = document.getElementById('add_npm_forward_port');
            var step2Ready = commonReady
                && isNonEmpty(npmBootstrapKey ? npmBootstrapKey.value : '')
                && isNonEmpty(forwardHostInput ? forwardHostInput.value : '')
                && isNonEmpty(forwardPortInput ? forwardPortInput.value : '');
            if (addSubmit) {
                addSubmit.disabled = !step2Ready;
            }
            return;
        }

        if (provider === 'cloudflare') {
            var canEnableCloudflare = commonReady && addFlowCategory === 'dns';
            if (addEnable) {
                addEnable.disabled = !canEnableCloudflare;
            }
            if (addEnableDomains) {
                addEnableDomains.disabled = !canEnableCloudflare;
            }
            return;
        }

        if (addSubmit) {
            addSubmit.disabled = !commonReady;
        }
    }

    function showProviderFields(providerKey) {
        resetAddFlowState();

        document.querySelectorAll('#add-integration-form .provider-fields').forEach(function (el) {
            el.classList.toggle('is-active', el.dataset.provider === providerKey);
        });

        if (!providerKey) {
            return;
        }

        if (providerKey === 'npm') {
            if (addNext) {
                addNext.hidden = false;
            }
            updateAddActionState();
            return;
        }

        if (providerKey === 'cloudflare') {
            // Cloudflare only makes sense in the DNS flow
            if (addFlowCategory === 'dns') {
                if (addEnable) { addEnable.hidden = false; }
                if (addEnableDomains) { addEnableDomains.hidden = false; }
            }
            updateAddActionState();
            return;
        }

        // Generic provider: show submit Enable
        addSubmit.hidden = false;
        updateAddActionState();
    }

    addProvider.addEventListener('change', function () {
        var key = addProvider.value;
        showProviderFields(key || null);
        updateAddActionState();
    });

    addForm.addEventListener('input', function (e) {
        clearFieldError(e.target);
        buildAddNpmBaseUrl();
        updateAddActionState();
    });
    addForm.addEventListener('change', function () {
        buildAddNpmBaseUrl();
        updateAddActionState();
    });

    if (addNext) {
        addNext.addEventListener('click', function () {
            var provider = addProvider.value;
            var csrfTokenInput = document.querySelector('#add-integration-form input[name=csrf_token]');
            var csrfToken = csrfTokenInput ? csrfTokenInput.value : '';

            if (provider === 'npm') {
                var baseUrlInput = document.getElementById('add_npm_base_url_input');
                var adminIdentityInput = document.getElementById('add_npm_admin_identity');
                var adminSecretInput = document.getElementById('add_npm_admin_secret');
                var baseUrl = buildAddNpmBaseUrl();
                var adminIdentity = adminIdentityInput ? adminIdentityInput.value.trim() : '';
                var adminSecret = adminSecretInput ? adminSecretInput.value : '';

                var hasError = false;
                if (!baseUrl) { showFieldError(baseUrlInput, 'Base URL is required.'); hasError = true; }
                if (!adminIdentity) { showFieldError(adminIdentityInput, 'Admin email is required.'); hasError = true; }
                if (!adminSecret) { showFieldError(adminSecretInput, 'Admin password is required.'); hasError = true; }
                if (hasError) { return; }

                addNext.disabled = true;
                addNext.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating';

                var npmBody = new URLSearchParams();
                npmBody.set('csrf_token', csrfToken);
                npmBody.set('base_url', baseUrl);
                npmBody.set('admin_identity', adminIdentity);
                npmBody.set('admin_secret', adminSecret);

                fetch('/?route=settings-integrations-npm-bootstrap', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: npmBody.toString(),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok) {
                            throw new Error(data.message || 'Unable to provision NPM runtime account.');
                        }

                        if (npmBootstrapKey) {
                            npmBootstrapKey.value = String(data.bootstrap_key || '');
                        }
                        if (npmRuntimeIdentity) {
                            npmRuntimeIdentity.value = String(data.runtime_identity || '');
                        }

                        if (npmStep1) {
                            npmStep1.hidden = true;
                        }
                        if (npmStep2) {
                            npmStep2.hidden = false;
                        }

                        addNext.hidden = true;
                        addSubmit.hidden = false;
                        addSubmit.disabled = true;
                        updateAddActionState();
                    })
                    .catch(function (err) {
                        showStepError('err_npm_step1', err.message || 'Unable to provision NPM runtime account.');
                        updateAddActionState();
                        addNext.innerHTML = '<i class="fa-solid fa-arrow-right"></i> Next';
                    });

                return;
            }
        });
    }

    function enableCloudflare(andGoToDomains) {
        var csrfTokenInput = document.querySelector('#add-integration-form input[name=csrf_token]');
        var csrfToken = csrfTokenInput ? csrfTokenInput.value : '';
        var name = addName ? addName.value.trim() : '';

        if (!name) {
            showFieldError(addName, 'A custom name is required.');
            return;
        }

        if (addEnable) {
            addEnable.disabled = true;
            addEnable.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enabling';
        }
        if (addEnableDomains) {
            addEnableDomains.disabled = true;
            addEnableDomains.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enabling';
        }

        var cfBody = new URLSearchParams();
        cfBody.set('csrf_token', csrfToken);
        cfBody.set('name', name);

        fetch('/?route=settings-integrations-enable-cloudflare', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: cfBody.toString(),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    throw new Error(data.message || 'Unable to enable Cloudflare integration.');
                }

                if (andGoToDomains) {
                    window.location.href = '/?route=domains';
                    return;
                }

                closeDialog(addModal);
                window.location.reload();
            })
            .catch(function (err) {
                showStepError('err_cf_enable', err.message || 'Unable to enable Cloudflare integration.');
                if (addEnable) {
                    addEnable.innerHTML = '<i class="fa-solid fa-power-off"></i> Enable';
                }
                if (addEnableDomains) {
                    addEnableDomains.innerHTML = '<i class="fa-solid fa-power-off"></i> Enable &amp; Go to Domains';
                }
                updateAddActionState();
            });
    }

    if (addEnable) {
        addEnable.addEventListener('click', function () {
            enableCloudflare(false);
        });
    }

    if (addEnableDomains) {
        addEnableDomains.addEventListener('click', function () {
            enableCloudflare(true);
        });
    }

    if (addNpmGetIp) {
        addNpmGetIp.addEventListener('click', function () {
            var target = document.getElementById('add_npm_forward_host');
            if (!target) {
                return;
            }

            addNpmGetIp.disabled = true;
            addNpmGetIp.textContent = 'Loading';
            requestServerIp()
                .then(function (ip) {
                    target.value = ip;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                })
                .catch(function (err) {
                    showStepError('err_npm_step2', err.message || 'Unable to detect server IP.');
                })
                .finally(function () {
                    addNpmGetIp.disabled = false;
                    addNpmGetIp.textContent = 'Get IP';
                });
        });
    }

    // ── Open Add modal ──
    document.addEventListener('click', function (event) {
        var addBtn = event.target.closest('[data-open-add-modal]');
        if (addBtn) {
            var category = addBtn.getAttribute('data-open-add-modal');
            addFlowCategory = category || null;
            addForm.reset();
            filterAddProviders(category);
            var label = category === 'proxy' ? 'Reverse Proxy' : 'DNS';
            addSubtitle.textContent = 'Choose a ' + label + ' provider.';
            updateAddActionState();
            openDialog(addModal);
            return;
        }

        var editTile = event.target.closest('[data-open-edit]');
        if (editTile) {
            if (event.target.closest('button')) {
                return;
            }

            var id = editTile.getAttribute('data-open-edit');
            var integration = VHM_INTEGRATIONS.find(function (i) { return i.id === id; });
            if (!integration) {
                return;
            }

            currentEditIntegration = integration;

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_delete_id').value = id;
            document.getElementById('edit_name').value = integration.name;
            document.getElementById('edit-modal-subtitle').textContent =
                (VHM_PROVIDERS[integration.provider] ? VHM_PROVIDERS[integration.provider].label : integration.provider);

            // Build provider fields dynamically
            var container = document.getElementById('edit-provider-fields');
            var prov = VHM_PROVIDERS[integration.provider];
            container.innerHTML = '';
            if (prov) {
                prov.fields.forEach(function (field) {
                    var value = (integration.settings && integration.settings[field.name]) || '';
                    var fg = document.createElement('div');
                    fg.className = 'form-group';
                    fg.style.marginBottom = '14px';

                    var labelNode = document.createElement('label');
                    labelNode.className = 'form-label';
                    labelNode.htmlFor = 'edit_field_' + field.name;
                    labelNode.textContent = field.label;
                    if (field.required) {
                        var star = document.createElement('span');
                        star.style.color = 'var(--danger)';
                        star.textContent = ' *';
                        labelNode.appendChild(star);
                    }
                    fg.appendChild(labelNode);

                    if (field.type === 'checkbox') {
                        var lbl = document.createElement('label');
                        lbl.className = 'form-check';
                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.id = 'edit_field_' + field.name;
                        cb.name = 'settings[' + field.name + ']';
                        cb.value = '1';
                        cb.checked = value === '1';
                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(' ' + field.label));
                        fg.appendChild(lbl);
                    } else if (field.type === 'password') {
                        var wrap = document.createElement('div');
                        wrap.className = 'secret-input-wrap';
                        var inp = document.createElement('input');
                        inp.className = 'form-input';
                        inp.id = 'edit_field_' + field.name;
                        inp.type = 'password';
                        inp.name = 'settings[' + field.name + ']';
                        inp.placeholder = field.placeholder || '(leave blank to keep current)';
                        inp.autocomplete = 'off';
                        inp.spellcheck = false;
                        var toggleBtn = document.createElement('button');
                        toggleBtn.type = 'button';
                        toggleBtn.className = 'secret-toggle-btn';
                        toggleBtn.setAttribute('aria-label', 'Show secret');
                        toggleBtn.innerHTML = '<i class="fa-solid fa-eye"></i>';
                        toggleBtn.addEventListener('click', function () {
                            inp.type = inp.type === 'password' ? 'text' : 'password';
                        });
                        wrap.appendChild(inp);
                        wrap.appendChild(toggleBtn);
                        fg.appendChild(wrap);
                        var hint = document.createElement('span');
                        hint.className = 'form-hint';
                        hint.textContent = 'Leave blank to keep the current value.';
                        fg.appendChild(hint);
                    } else {
                        var fieldInput = document.createElement('input');
                        fieldInput.className = 'form-input';
                        fieldInput.id = 'edit_field_' + field.name;
                        fieldInput.type = field.type;
                        fieldInput.name = 'settings[' + field.name + ']';
                        fieldInput.value = value;
                        fieldInput.placeholder = field.placeholder || '';
                        if (field.required) {
                            fieldInput.required = true;
                        }

                        if (field.name === 'forward_host') {
                            var row = document.createElement('div');
                            row.className = 'app-url-input-row';
                            row.appendChild(fieldInput);

                            var ipBtn = document.createElement('button');
                            ipBtn.type = 'button';
                            ipBtn.className = 'btn btn--ghost btn--sm';
                            ipBtn.setAttribute('data-edit-get-ip', '1');
                            ipBtn.setAttribute('data-target-input', fieldInput.id);
                            ipBtn.textContent = 'Get IP';
                            row.appendChild(ipBtn);

                            fg.appendChild(row);
                        } else {
                            fg.appendChild(fieldInput);
                        }
                    }

                    container.appendChild(fg);
                });
            }

            openDialog(editModal);
        }
    });

    // ── Close Add modal ──
    document.getElementById('add-modal-close').addEventListener('click', function () {
        addFlowCategory = null;
        resetAddFlowState();
        closeDialog(addModal);
    });
    document.getElementById('add-modal-cancel').addEventListener('click', function () {
        addFlowCategory = null;
        resetAddFlowState();
        closeDialog(addModal);
    });

    // ── Close Edit modal ──
    document.getElementById('edit-modal-close').addEventListener('click', function () {
        currentEditIntegration = null;
        closeDialog(editModal);
    });
    document.getElementById('edit-modal-cancel').addEventListener('click', function () {
        currentEditIntegration = null;
        closeDialog(editModal);
    });

    // ── Close Test Result modal ──
    var testResultClose = document.getElementById('test-result-close');
    var testResultOk = document.getElementById('test-result-ok');
    if (testResultClose) {
        testResultClose.addEventListener('click', function () { closeDialog(testResultModal); });
    }
    if (testResultOk) {
        testResultOk.addEventListener('click', function () { closeDialog(testResultModal); });
    }
    var cloudflareDomainsClose = document.getElementById('cloudflare-domains-close');
    var cloudflareDomainsOk = document.getElementById('cloudflare-domains-ok');
    if (cloudflareDomainsClose) {
        cloudflareDomainsClose.addEventListener('click', function () { closeDialog(cloudflareDomainsModal); });
    }
    if (cloudflareDomainsOk) {
        cloudflareDomainsOk.addEventListener('click', function () { closeDialog(cloudflareDomainsModal); });
    }
    if (cloudflareDomainsTestAll) {
        cloudflareDomainsTestAll.addEventListener('click', function () {
            var domains = Array.isArray(VHM_CF_ENABLED_DOMAINS) ? VHM_CF_ENABLED_DOMAINS.slice() : [];
            domains = domains.filter(function (d) { return String(d || '').trim() !== ''; });
            if (domains.length === 0) {
                return;
            }

            cloudflareDomainsTestAll.disabled = true;
            cloudflareDomainsTestAll.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing';

            Promise.all(domains.map(function (domain) {
                return testCloudflareDomain(domain).then(function (result) {
                    return {
                        domain: domain,
                        ok: result.ok,
                        message: result.message,
                    };
                });
            })).then(function (results) {
                showCloudflareTestAllResults(results);
            }).finally(function () {
                cloudflareDomainsTestAll.disabled = false;
                cloudflareDomainsTestAll.innerHTML = 'Test All';
            });
        });
    }

    var cloudflareDomainResultClose = document.getElementById('cloudflare-domain-result-close');
    var cloudflareDomainResultOk = document.getElementById('cloudflare-domain-result-ok');
    function closeSingleResultModal() {
        closeDialog(cloudflareDomainResultModal);
        if (reopenCloudflareListOnSingleClose) {
            openDialog(cloudflareDomainsModal);
        }
    }
    if (cloudflareDomainResultClose) {
        cloudflareDomainResultClose.addEventListener('click', closeSingleResultModal);
    }
    if (cloudflareDomainResultOk) {
        cloudflareDomainResultOk.addEventListener('click', closeSingleResultModal);
    }

    var cloudflareTestAllClose = document.getElementById('cloudflare-test-all-close');
    var cloudflareTestAllOk = document.getElementById('cloudflare-test-all-ok');
    function closeAllCloudflareModals() {
        closeDialog(cloudflareTestAllModal);
        closeDialog(cloudflareDomainResultModal);
        closeDialog(cloudflareDomainsModal);
        reopenCloudflareListOnSingleClose = false;
    }
    if (cloudflareTestAllClose) {
        cloudflareTestAllClose.addEventListener('click', closeAllCloudflareModals);
    }
    if (cloudflareTestAllOk) {
        cloudflareTestAllOk.addEventListener('click', closeAllCloudflareModals);
    }

    if (cloudflareDomainsListWrap) {
        cloudflareDomainsListWrap.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-cf-test-domain]');
            if (!btn) {
                return;
            }

            var domain = btn.getAttribute('data-cf-test-domain') || '';
            if (!domain) {
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing';
            testCloudflareDomain(domain)
                .then(function (result) {
                    showCloudflareSingleResult(domain, result);
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Test';
                });
        });
    }

    // ── Delete button ──
    if (editModalDeleteBtn) {
        editModalDeleteBtn.addEventListener('click', function () {
            if (!deleteConfirmModal || !deleteConfirmMessage || !deleteConfirmSubtitle || !editDeleteForm) {
                return;
            }

            var integrationName = currentEditIntegration && currentEditIntegration.name
                ? String(currentEditIntegration.name)
                : 'this integration';
            deleteConfirmSubtitle.textContent = 'You are about to remove "' + integrationName + '".';
            deleteConfirmMessage.textContent = 'Remove this integration from Vhost Manager?';

            var isNpm = !!(currentEditIntegration && currentEditIntegration.provider === 'npm');
            if (deleteNpmExtra) {
                deleteNpmExtra.hidden = !isNpm;
            }

            if (deleteNpmToggle) {
                deleteNpmToggle.checked = false;
            }

            if (deleteNpmHidden) {
                deleteNpmHidden.value = '0';
            }

            openDialog(deleteConfirmModal);
        });
    }

    function closeDeleteConfirmModal() {
        closeDialog(deleteConfirmModal);
    }

    if (deleteConfirmClose) {
        deleteConfirmClose.addEventListener('click', closeDeleteConfirmModal);
    }

    if (deleteConfirmCancel) {
        deleteConfirmCancel.addEventListener('click', closeDeleteConfirmModal);
    }

    if (deleteConfirmSubmit) {
        deleteConfirmSubmit.addEventListener('click', function () {
            if (!editDeleteForm) {
                return;
            }

            if (deleteNpmHidden && deleteNpmToggle) {
                deleteNpmHidden.value = deleteNpmToggle.checked ? '1' : '0';
            }

            closeDeleteConfirmModal();
            editDeleteForm.submit();
        });
    }

    // ── Test connection ──
    document.querySelectorAll('[data-test-integration]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            var id = btn.getAttribute('data-test-integration');
            var integration = VHM_INTEGRATIONS.find(function (i) { return i.id === id; });

            if (integration && integration.provider === 'cloudflare') {
                showCloudflareDomainsModal();
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            fetch('/?route=settings-integrations-test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(
                    document.querySelector('#add-integration-form input[name=csrf_token]').value
                )
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Test';
                if (data && data.ok) {
                    showTestResult(true, 'Connection successful.');
                } else {
                    showTestResult(false, (data && data.message) ? data.message : 'Connection test failed.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Test';
                showTestResult(false, 'Connection test failed.');
            });
        });
    });

    document.addEventListener('click', function (event) {
        var ipBtn = event.target.closest('[data-edit-get-ip]');
        if (!ipBtn) {
            return;
        }

        var targetId = ipBtn.getAttribute('data-target-input') || '';
        var targetInput = targetId ? document.getElementById(targetId) : null;
        if (!targetInput) {
            return;
        }

        ipBtn.disabled = true;
        ipBtn.textContent = 'Loading';
        requestServerIp()
            .then(function (ip) {
                targetInput.value = ip;
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            })
            .catch(function (err) {
                showTestResult(false, err.message || 'Unable to detect server IP.');
            })
            .finally(function () {
                ipBtn.disabled = false;
                ipBtn.textContent = 'Get IP';
            });
    });
}());
</script>
