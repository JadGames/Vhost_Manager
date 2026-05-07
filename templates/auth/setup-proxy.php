<?php declare(strict_types=1); ?>
<?php
$fe = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$isNpm = (($selectedProxyProvider ?? '') === 'npm');
$isStepTwo = $isNpm && (($proxyStep ?? '1') === '2');
?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: Proxy Integration</h1>
        <?php 
            $totalSteps = ($enableIntegrations ?? true) ? 5 : 3;
            $stepNumber = ($enableIntegrations ?? true) ? 2 : 1;
        ?>
        <p class="auth-subtitle">Step <?= $stepNumber ?> of <?= $totalSteps ?>: Configure Reverse Proxy (optional)</p>

        <form class="form" method="post" action="/?route=setup-proxy" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="proxy_step" value="<?= e((string) ($isStepTwo ? '2' : '1')) ?>">

            <div class="form-group">
                <label class="form-label" for="proxy_provider">Provider</label>
                <select class="form-select<?= !empty($fe['proxy_provider']) ? ' is-error' : '' ?>" id="proxy_provider" name="proxy_provider">
                    <?php foreach (($proxyProviders ?? []) as $providerKey => $provider): ?>
                        <option
                            value="<?= e((string) $providerKey) ?>"
                            data-description="<?= e((string) ($provider['description'] ?? '')) ?>"
                            <?= (($selectedProxyProvider ?? '') === $providerKey) ? 'selected' : '' ?>
                        >
                            <?= e((string) ($provider['label'] ?? $providerKey)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($fe['proxy_provider'])): ?><span class="form-field-error"><?= e((string) $fe['proxy_provider']) ?></span><?php endif; ?>
                <span class="form-hint" id="proxy_provider_hint"></span>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Custom Name</label>
                <input class="form-input<?= !empty($fe['name']) ? ' is-error' : '' ?>" id="name" type="text" name="name" value="<?= e((string) $name) ?>" placeholder="e.g. Main NPM, Production NPM">
                <?php if (!empty($fe['name'])): ?><span class="form-field-error"><?= e((string) $fe['name']) ?></span><?php else: ?><span class="form-hint">A label to identify this proxy integration.</span><?php endif; ?>
            </div>

            <div id="proxy-provider-npm-fields">
                <?php if (!$isStepTwo): ?>
                    <p class="form-hint" style="margin-bottom: 12px;">Step 2.1: Enter one-time admin credentials to provision a dedicated non-admin VHM runtime account. Admin credentials are never stored.</p>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="npm_base_url_scheme">Base URL</label>
                        <div class="app-url-input-row">
                            <select class="form-select" id="npm_base_url_scheme" name="npm_base_url_scheme" aria-label="NPM URL protocol">
                                <option value="http" <?= (($npmBaseUrlScheme ?? 'http') === 'http') ? 'selected' : '' ?>>http://</option>
                                <option value="https" <?= (($npmBaseUrlScheme ?? 'http') === 'https') ? 'selected' : '' ?>>https://</option>
                            </select>
                            <input class="form-input<?= !empty($fe['npm_base_url_input']) ? ' is-error' : '' ?>" id="npm_base_url_input" type="text" name="npm_base_url_input" placeholder="npm.example.com:81 or 192.168.1.100:81" value="<?= e((string) ($npmBaseUrlInput ?? '')) ?>">
                        </div>
                        <?php if (!empty($fe['npm_base_url_input'])): ?><span class="form-field-error"><?= e((string) $fe['npm_base_url_input']) ?></span><?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="npm_admin_identity">Admin Email</label>
                        <input class="form-input<?= !empty($fe['npm_admin_identity']) ? ' is-error' : '' ?>" id="npm_admin_identity" type="email" name="npm_admin_identity" value="<?= e((string) ($npmAdminIdentity ?? '')) ?>" placeholder="admin@example.com" autocomplete="off">
                        <?php if (!empty($fe['npm_admin_identity'])): ?><span class="form-field-error"><?= e((string) $fe['npm_admin_identity']) ?></span><?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="npm_admin_secret">Admin Password or API Token</label>
                        <div class="secret-input-wrap">
                            <input class="form-input<?= !empty($fe['npm_admin_secret']) ? ' is-error' : '' ?>" id="npm_admin_secret" type="password" name="npm_admin_secret" placeholder="Admin password or Bearer token" autocomplete="off" spellcheck="false">
                            <button class="secret-toggle-btn" type="button" data-secret-target="npm_admin_secret" aria-label="Show secret">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($fe['npm_admin_secret'])): ?><span class="form-field-error"><?= e((string) $fe['npm_admin_secret']) ?></span><?php endif; ?>
                    </div>

                    <?php if (!empty($fe['npm_step_1'])): ?><span class="form-field-error"><?= e((string) $fe['npm_step_1']) ?></span><?php endif; ?>
                <?php else: ?>
                    <p class="form-hint" style="margin-bottom: 12px;">Step 2.2: Runtime account created. Set your forward target and continue.</p>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="npm_runtime_identity">Runtime Account</label>
                        <input class="form-input" id="npm_runtime_identity" type="text" value="<?= e((string) ($npmRuntimeIdentity ?? '')) ?>" readonly>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label class="form-label" for="npm_forward_host">Forward URL/Host</label>
                            <div class="app-url-input-row">
                                <input class="form-input<?= !empty($fe['npm_forward_host']) ? ' is-error' : '' ?>" id="npm_forward_host" type="text" name="npm_forward_host" placeholder="127.0.0.1" value="<?= e((string) ($npmForwardHost ?? '')) ?>">
                                <button class="btn btn--ghost btn--sm" type="button" id="setup_npm_get_ip">Get IP</button>
                            </div>
                            <?php if (!empty($fe['npm_forward_host'])): ?><span class="form-field-error"><?= e((string) $fe['npm_forward_host']) ?></span><?php endif; ?>
                        </div>
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label class="form-label" for="npm_forward_port">Forward Port</label>
                            <input class="form-input<?= !empty($fe['npm_forward_port']) ? ' is-error' : '' ?>" id="npm_forward_port" type="number" name="npm_forward_port" min="1" max="65535" value="<?= e((string) ($npmForwardPort ?? '80')) ?>">
                            <?php if (!empty($fe['npm_forward_port'])): ?><span class="form-field-error"><?= e((string) $fe['npm_forward_port']) ?></span><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group" id="proxy-provider-generic-note" hidden>
                <span class="form-hint">This provider will be enabled during setup and can be configured in detail later from Integrations.</span>
            </div>

            <div style="margin-top:20px; display:flex; gap:8px;">
                <?php if ($isStepTwo): ?>
                    <button class="btn btn--secondary" type="button" id="setup-proxy-back-button" style="flex:1;">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                <?php else: ?>
                    <a href="/?route=setup" class="btn btn--secondary" style="flex:1; text-align:center; text-decoration:none;">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                <?php endif; ?>

                <?php if (!$isStepTwo): ?>
                    <button class="btn btn--ghost" type="button" formnovalidate style="flex:1;" id="proxy-skip-button">
                        Skip
                    </button>
                <?php endif; ?>

                <button class="btn btn--primary" type="submit" style="flex:1;" id="proxy-primary-submit">
                    <span id="proxy-submit-label"><?= $isStepTwo ? 'Continue' : 'Test &amp; Continue' ?></span> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<dialog id="setup-proxy-skip-confirm-modal" aria-modal="true" aria-labelledby="setup-proxy-skip-confirm-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="setup-proxy-skip-confirm-title">Skip Proxy Setup?</p>
            <p class="dialog-subtitle">You have entered proxy integration details.</p>
        </div>
        <button class="dialog-close-btn" type="button" id="setup-proxy-skip-confirm-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <p style="margin:0; color:var(--text-3);">You have entered data into the setup fields. Are you sure you want to skip this step?</p>
        <div class="dialog-footer">
            <button class="btn btn--ghost" type="button" id="setup-proxy-skip-cancel">Keep Editing</button>
            <button class="btn btn--primary" type="button" id="setup-proxy-skip-confirm">Skip Anyway</button>
        </div>
    </div>
</dialog>

<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
(function () {
    var providerSelect = document.getElementById('proxy_provider');
    var providerHint = document.getElementById('proxy_provider_hint');
    var npmFields = document.getElementById('proxy-provider-npm-fields');
    var genericNote = document.getElementById('proxy-provider-generic-note');
    var submitLabel = document.getElementById('proxy-submit-label');
    var primarySubmit = document.getElementById('proxy-primary-submit');
    var backButton = document.getElementById('setup-proxy-back-button');
    var form = document.querySelector('form[action="/?route=setup-proxy"]');
    var skipButton = document.getElementById('proxy-skip-button');
    var skipConfirmModal = document.getElementById('setup-proxy-skip-confirm-modal');
    var skipConfirmClose = document.getElementById('setup-proxy-skip-confirm-close');
    var skipConfirmCancel = document.getElementById('setup-proxy-skip-cancel');
    var skipConfirmConfirm = document.getElementById('setup-proxy-skip-confirm');
    var setupGetIpButton = document.getElementById('setup_npm_get_ip');
    var setupForwardHostInput = document.getElementById('npm_forward_host');

    if (!providerSelect || !providerHint || !npmFields || !genericNote || !submitLabel || !form || !primarySubmit) {
        return;
    }

    function submitWithHiddenAction(name, value) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = value;
        form.appendChild(hidden);
        form.submit();
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

    function hasEnteredProxyData() {
        var nameInput = document.getElementById('name');
        var baseUrlInput = document.getElementById('npm_base_url_input');
        var adminIdentityInput = document.getElementById('npm_admin_identity');
        var adminSecretInput = document.getElementById('npm_admin_secret');

        return [nameInput, baseUrlInput, adminIdentityInput, adminSecretInput].some(function (input) {
            return input && String(input.value || '').trim() !== '';
        });
    }

    function requestServerIp() {
        var tokenInput = form.querySelector('input[name="csrf_token"]');
        var body = new URLSearchParams();
        body.set('csrf_token', tokenInput ? tokenInput.value : '');

        return fetch('/?route=setup-server-ip', {
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

    function applyProviderState() {
        var option = providerSelect.options[providerSelect.selectedIndex];
        providerHint.textContent = option ? (option.getAttribute('data-description') || '') : '';

        var isNpm = providerSelect.value === 'npm';
        npmFields.hidden = !isNpm;
        genericNote.hidden = isNpm;

        if (!isNpm) {
            submitLabel.textContent = 'Continue';
            return;
        }

        submitLabel.textContent = <?= json_encode($isStepTwo ? 'Continue' : 'Test & Continue') ?>;
    }

    providerSelect.addEventListener('change', applyProviderState);
    applyProviderState();

    form.addEventListener('keydown', function (event) {
        var target = event.target;
        if (!target || target.tagName === 'TEXTAREA') {
            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        primarySubmit.click();
    });

    if (backButton) {
        backButton.addEventListener('click', function () {
            submitWithHiddenAction('back_step', '1');
        });
    }

    if (skipButton && skipConfirmModal) {
        skipButton.addEventListener('click', function (event) {
            if (!hasEnteredProxyData()) {
                submitWithHiddenAction('skip', '1');
                return;
            }

            event.preventDefault();
            openDialog(skipConfirmModal);
        });
    }

    if (skipConfirmClose) {
        skipConfirmClose.addEventListener('click', function () {
            closeDialog(skipConfirmModal);
        });
    }

    if (skipConfirmCancel) {
        skipConfirmCancel.addEventListener('click', function () {
            closeDialog(skipConfirmModal);
        });
    }

    if (skipConfirmConfirm) {
        skipConfirmConfirm.addEventListener('click', function () {
            closeDialog(skipConfirmModal);

            var skipInput = document.createElement('input');
            skipInput.type = 'hidden';
            skipInput.name = 'skip';
            skipInput.value = '1';
            form.appendChild(skipInput);
            form.submit();
        });
    }

    if (setupGetIpButton && setupForwardHostInput) {
        setupGetIpButton.addEventListener('click', function () {
            setupGetIpButton.disabled = true;
            setupGetIpButton.textContent = 'Detecting...';

            requestServerIp()
                .then(function (ip) {
                    setupForwardHostInput.value = ip;
                })
                .catch(function (error) {
                    window.alert(error && error.message ? error.message : 'Unable to detect server IP.');
                })
                .finally(function () {
                    setupGetIpButton.disabled = false;
                    setupGetIpButton.textContent = 'Get IP';
                });
        });
    }
})();
</script>
